<?php
$base = '../../../';
require_once $base . 'includes/session.php';
requireLogin();
require_once $base . 'includes/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Maintenance Request – HostelHub</title>
    <link rel="stylesheet" href="<?= $base ?>css/style.css">
    <link rel="stylesheet" href="../../css/styles.css?v=20260501a">
</head>
<body>
<?php require_once $base . 'includes/navbar.php'; ?>

<main class="app-shell">
    <header class="app-header">
        <h1>Maintenance Requests</h1>
        <p>Log and track hostel maintenance issues quickly.</p>
        <nav>
            <ul class="top-nav">
                <li><a class="active" href="plan.php">Add Request</a></li>
                <li><a href="index.php">View Requests</a></li>
            </ul>
        </nav>
    </header>

    <section class="card">
        <h2>Create New Request</h2>
        <p class="muted-text">All fields marked with * are required.</p>
        <form id="maintenanceForm" class="simple-form">
            <div class="form-row">
                <label for="ticket_number">Ticket Number*</label>
                <input type="text" id="ticket_number" name="ticket_number" maxlength="20" placeholder="e.g. MT-2026-001" required />
            </div>
            <div class="form-row">
                <label for="room_id">Room*</label>
                <select id="room_id" name="room_id" required>
                    <option value="">-- Select Room --</option>
                </select>
            </div>
            <div class="form-row">
                <label for="assigned_to">Assigned To*</label>
                <select id="assigned_to" name="assigned_to" required>
                    <option value="">-- Select Staff --</option>
                </select>
            </div>
            <div class="form-row">
                <label for="date_reported">Date Reported*</label>
                <input type="date" id="date_reported" name="date_reported" required />
            </div>
            <div class="form-row">
                <label for="reported_by">Reported By*</label>
                <select id="reported_by" name="reported_by" required>
                    <option value="">-- Select User --</option>
                </select>
            </div>
            <!-- Status defaults to Pending on creation -->
            <div>
                <button type="submit">Submit Request</button>
            </div>
        </form>
        <div id="formMessage" role="status"></div>
    </section>
</main>

<script>
const form = document.getElementById('maintenanceForm');
const msg  = document.getElementById('formMessage');
const dateInput = document.getElementById('date_reported');
if (dateInput) {
    dateInput.value = new Date().toISOString().slice(0, 10);
}

function showMessage(type, text) {
    msg.className  = 'alert ' + type;
    msg.textContent = text;
}

function validateMaintenanceForm(data) {
    const errors = [];
    if (!data.ticket_number || !data.ticket_number.trim()) errors.push('Ticket Number is required.');
    if (!data.room_id || !String(data.room_id).trim())   errors.push('Room is required.');
    if (!data.assigned_to || !String(data.assigned_to).trim()) errors.push('Assigned To is required.');
    if (!data.date_reported) errors.push('Date Reported is required.');
    if (!data.reported_by || !String(data.reported_by).trim()) errors.push('Reported By is required.');
    if (data.ticket_number && data.ticket_number.trim().length > 20) errors.push('Ticket Number must be 20 characters or less.');
    if (data.room_id && !/^\d+$/.test(String(data.room_id))) errors.push('Room must be a valid room ID.');
    if (data.date_reported && !/^\d{4}-\d{2}-\d{2}$/.test(data.date_reported)) errors.push('Date must be in YYYY-MM-DD format.');
    if (data.reported_by && !/^\d+$/.test(String(data.reported_by))) errors.push('Reported By must be a valid user ID.');
    return errors;
}

async function fetchJson(url) {
    const response = await fetch(url, { cache: 'no-store' });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
}

async function populateSelects() {
    try {
        // Rooms
        const roomsResp = await fetchJson('../../api/maintenance/list_rooms.php');
        if (roomsResp && roomsResp.success) {
            const sel = document.getElementById('room_id');
            roomsResp.data.forEach(rm => {
                const opt = document.createElement('option');
                opt.value       = rm.room_id;
                opt.textContent = `${rm.room_number} (cap: ${rm.capacity || 1})`;
                sel.appendChild(opt);
            });
        } else {
            showMessage('error', roomsResp.errors ? roomsResp.errors.join(' ') : 'Failed to load rooms.');
            return;
        }

        // Staff (assigned_to stores the full_name text in the DB)
        const staffResp = await fetchJson('../../api/maintenance/list_staffs.php');
        if (staffResp && staffResp.success) {
            const sel = document.getElementById('assigned_to');
            staffResp.data.forEach(s => {
                const opt = document.createElement('option');
                opt.value       = s.full_name;          // stored as text in maintenance.assigned_to
                opt.textContent = `${s.full_name} (${s.role || 'staff'})`;
                sel.appendChild(opt);
            });
        } else {
            showMessage('error', staffResp.errors ? staffResp.errors.join(' ') : 'Failed to load staff.');
            return;
        }

        // Users who can report (reported_by is INT FK → users.user_id)
        const usersResp = await fetchJson('../../api/maintenance/list_students.php');
        if (usersResp && usersResp.success) {
            const sel = document.getElementById('reported_by');
            usersResp.data.forEach(u => {
                const opt = document.createElement('option');
                opt.value       = u.user_id;            // stored as INT in maintenance.reported_by
                opt.textContent = `${u.full_name} (${u.role || ''})`;
                sel.appendChild(opt);
            });
        } else {
            showMessage('error', usersResp.errors ? usersResp.errors.join(' ') : 'Failed to load users.');
        }
    } catch (err) {
        showMessage('error', 'Failed to load dropdown options. Make sure you\'re opening this via XAMPP (e.g. http://localhost/HostelHub/...).');
    }
}

populateSelects();

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.className  = '';
    msg.textContent = '';

    const data = {
        ticket_number : document.getElementById('ticket_number').value.trim(),
        room_id       : document.getElementById('room_id').value.trim(),
        assigned_to   : document.getElementById('assigned_to').value.trim(),
        date_reported : document.getElementById('date_reported').value,
        reported_by   : document.getElementById('reported_by').value.trim(),
    };

    const errors = validateMaintenanceForm(data);
    if (errors.length) {
        showMessage('error', errors.join(' '));
        return;
    }

    try {
        const resp = await fetch('../../api/maintenance/submit_maintenance.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams(data)
        });
        const json = await resp.json();
        if (json.success) {
            form.style.display = 'none';
            document.querySelector('.muted-text') && (document.querySelector('.muted-text').style.display = 'none');
            msg.className  = 'alert success';
            msg.innerHTML  = '✅ Maintenance request submitted successfully. <a href="index.php">View all requests</a> or <a href="plan.php">add another</a>.';
        } else {
            showMessage('error', json.errors ? json.errors.join(' ') : 'Submission failed.');
        }
    } catch (err) {
        showMessage('error', 'Network or server error.');
    }
});
</script>

</body>
</html>
