<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Module</title>
    <link rel="stylesheet" href="../../css/styles.css?v=20260501a">
</head>
<body>
<main class="app-shell">
    <header class="app-header">
        <h1>Maintenance Requests</h1>
        <p>Monitor all maintenance tickets and their current status.</p>
        <nav>
            <ul class="top-nav">
                <li><a href="plan.html">Add Request</a></li>
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
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="tableMessage"></div>
    </section>
</main>

<script>
function createCell(value) {
    const td = document.createElement('td');
    td.textContent = value ?? '';
    return td;
}

function createStatusCell(isResolved) {
    const td = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = 'status-badge ' + (isResolved == 1 ? 'resolved' : 'pending');
    badge.textContent = isResolved == 1 ? 'Resolved' : 'Not Resolved';
    td.appendChild(badge);
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
                tr.appendChild(createStatusCell(row.is_resolved));
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
</script>

</body>
</html>
