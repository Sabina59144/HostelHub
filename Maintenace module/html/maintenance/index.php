<?php
$base = '../../../';
require_once $base . 'includes/session.php';
requireLogin();
require_once $base . 'includes/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance – HostelHub</title>
    <link rel="stylesheet" href="<?= $base ?>css/style.css">
    <link rel="stylesheet" href="../../css/styles.css?v=20260501a">
</head>
<body>
<?php require_once $base . 'includes/navbar.php'; ?>

<main class="app-shell">
    <header class="app-header">
        <h1>Maintenance Requests</h1>
        <p>Monitor all maintenance tickets and their current status.</p>
        <nav>
            <ul class="top-nav">
                <li><a href="plan.php">Add Request</a></li>
                <li><a class="active" href="index.php">View Requests</a></li>
            </ul>
        </nav>
    </header>

    <section class="card">
        <h2>All Requests</h2>
        <div class="table-wrap">
            <table id="maintenanceTable">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Room</th>
                        <th>Assigned To</th>
                        <th>Reported By</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="tableMessage"></div>
    </section>

    <!-- Edit modal -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Edit Maintenance Request</h3>
            <form id="editForm">
                <input type="hidden" name="maintenance_id" id="maintenance_id">
                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="Pending">Pending</option>
                        <option value="Inprogress">Inprogress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label for="resolution_note">Resolution Note</label>
                    <textarea name="resolution_note" id="resolution_note" rows="4"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" id="saveEdit">Save</button>
                    <button type="button" id="cancelEdit">Cancel</button>
                </div>
                <div id="editErrors" class="alert error" style="display:none;margin-top:8px;"></div>
            </form>
        </div>
    </div>
</main>

<script>
function createCell(value) {
    const td = document.createElement('td');
    td.textContent = value ?? '';
    return td;
}

function createStatusCell(status) {
    const td = document.createElement('td');
    const badge = document.createElement('span');
    const s = (status || '').toLowerCase();
    if (s === 'completed' || s === 'resolved') {
        badge.className = 'status-badge resolved';
        badge.textContent = 'Completed';
    } else if (s === 'inprogress' || s === 'in-progress') {
        badge.className = 'status-badge inprogress';
        badge.textContent = 'Inprogress';
    } else {
        badge.className = 'status-badge pending';
        badge.textContent = 'Pending';
    }
    td.appendChild(badge);
    return td;
}

function createActionsCell(id) {
    const td = document.createElement('td');
    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.className = 'btn small';
    editBtn.addEventListener('click', () => openEditModal(id));

    const delBtn = document.createElement('button');
    delBtn.textContent = 'Delete';
    delBtn.className = 'btn small danger';
    delBtn.addEventListener('click', () => deleteMaintenance(id));

    td.appendChild(editBtn);
    td.appendChild(document.createTextNode(' '));
    td.appendChild(delBtn);
    return td;
}

function showTableMessage(type, text) {
    const tableMessage = document.getElementById('tableMessage');
    tableMessage.className = 'alert ' + type;
    tableMessage.textContent = text;
}

async function loadMaintenance() {
    try {
        const resp = await fetch('../../api/maintenance/get_maintenance.php', { cache: 'no-store' });
        const json = await resp.json();
        const tbody = document.querySelector('#maintenanceTable tbody');
        tbody.innerHTML = '';

        if (json.success && Array.isArray(json.data)) {
            if (json.data.length === 0) {
                showTableMessage('info', 'No maintenance requests found.');
                return;
            }
            document.getElementById('tableMessage').className = '';
            document.getElementById('tableMessage').textContent = '';
            json.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.appendChild(createCell(row.ticket_number));
                tr.appendChild(createCell(row.room_number));
                tr.appendChild(createCell(row.assigned_to_name));
                tr.appendChild(createCell(row.reported_by_name));
                tr.appendChild(createCell(row.date_reported));
                const status = row.status !== undefined ? row.status : (row.is_resolved == 1 ? 'Completed' : 'Pending');
                tr.appendChild(createStatusCell(status));
                tr.appendChild(createActionsCell(row.maintenance_id));
                tbody.appendChild(tr);
            });
        } else {
            showTableMessage('error', json.errors ? json.errors.join(' ') : 'Failed to fetch maintenance requests.');
        }
    } catch (err) {
        showTableMessage('error', 'Failed to load maintenance requests.');
    }
}

loadMaintenance();

function openEditModal(id) {
    fetch(`../../api/maintenance/get_maintenance_item.php?id=${encodeURIComponent(id)}`)
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert(json.errors ? json.errors.join('\n') : 'Failed to fetch item');
                return;
            }
            const data = json.data;
            document.getElementById('maintenance_id').value = data.maintenance_id;
            document.getElementById('status').value = data.status || (data.is_resolved == 1 ? 'Completed' : 'Pending');
            document.getElementById('resolution_note').value = data.resolution_note || '';
            document.getElementById('editErrors').style.display = 'none';
            document.getElementById('editModal').style.display = 'block';
        })
        .catch(() => alert('Failed to fetch maintenance item.'));
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('cancelEdit').addEventListener('click', closeEditModal);

document.getElementById('saveEdit').addEventListener('click', async () => {
    const id = document.getElementById('maintenance_id').value;
    const status = document.getElementById('status').value;
    const resolution_note = document.getElementById('resolution_note').value.trim();

    const errors = [];
    if (!status) errors.push('Status is required.');
    if (status === 'Completed' && resolution_note.length === 0) errors.push('Resolution Note is required when marked Completed.');
    if (resolution_note.length > 2000) errors.push('Resolution Note must be 2000 characters or less.');

    const errBox = document.getElementById('editErrors');
    if (errors.length) {
        errBox.style.display = 'block';
        errBox.textContent = errors.join(' ');
        return;
    }

    try {
        const resp = await fetch('../../api/maintenance/update_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ maintenance_id: id, status, resolution_note })
        });
        const json = await resp.json();
        if (json.success) {
            closeEditModal();
            loadMaintenance();
        } else {
            errBox.style.display = 'block';
            errBox.textContent = json.errors ? json.errors.join(' ') : 'Failed to update.';
        }
    } catch (err) {
        errBox.style.display = 'block';
        errBox.textContent = 'Request failed.';
    }
});

async function deleteMaintenance(id) {
    if (!confirm('Delete this maintenance request? This cannot be undone.')) return;
    try {
        const resp = await fetch('../../api/maintenance/delete_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ maintenance_id: id })
        });
        const json = await resp.json();
        if (json.success) {
            loadMaintenance();
        } else {
            alert(json.errors ? json.errors.join('\n') : 'Failed to delete.');
        }
    } catch (err) {
        alert('Delete request failed.');
    }
}
</script>

</body>
</html>
