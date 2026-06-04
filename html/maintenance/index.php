<?php
require_once(__DIR__ . '/../../api/config/auth.php');
require_once(__DIR__ . '/../../api/config/db.php');
require_once(__DIR__ . '/../../includes/session.php');
requireLoginPage('../login.php');
$authUser = authCurrentUser();

// For students, pre-fetch their assigned room so the form can auto-select it
$studentRoomId = null;
if ($authUser && $authUser['role'] === 'student') {
    $rStmt = $db->prepare("SELECT room_id FROM students WHERE student_id = :id LIMIT 1");
    $rStmt->bindValue(':id', (int)$authUser['id'], PDO::PARAM_INT);
    $rStmt->execute();
    $row = $rStmt->fetch(PDO::FETCH_ASSOC);
    $studentRoomId = ($row && $row['room_id']) ? (int)$row['room_id'] : null;
    $db = null;
}
?>

<?php
/*
 * Maintenance Module - index page
 *
 * Purpose:
 * - Presents the maintenance requests UI for students and admins.
 * - Requires authentication via api/config/auth.php and redirects to login when needed.
 * - Uses AJAX calls to api/maintenance/* endpoints to list, create, update,
 *   archive and restore maintenance requests.
 *
 * Notes for maintainers:
 * - Client-side logic lives in the <script> block at the end of this file.
 * - Keep UI-only changes here; API behavior is implemented under api/maintenance/.
 */
?>

<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Module</title>
    <link rel="stylesheet" href="../../css/styles.css">
 <link rel="stylesheet" href="../../css/style.css">

</head>
<body>
<main class="app-shell">
    <?php include("../../includes/navbar.php"); ?>
    <header class="app-header">
        <h1>Maintenance Requests</h1>
        <p>Monitor all maintenance tickets and their current status.</p>
        <nav>
            <ul class="top-nav">
                <li><a href="#" id="openCreateLink">Add Request</a></li>
                <li><a class="active" href="#" data-view="active">Active Requests</a></li>
                <li><a href="#" data-view="archived">Archived</a></li>
            </ul>
        </nav>
    </header>

    <section class="card">
        <h2 id="tableHeading">Active Requests</h2>
        <div class="table-toolbar">
            <div class="toolbar-group">
                <div class="form-row">
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" placeholder="Ticket, room, staff, student, note" />
                </div>
                <div class="form-row">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="inprogress">Inprogress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table id="maintenanceTable">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Room</th>
                        <th>Description</th>
                        <th>Assigned To</th>
                        <th>Reported By</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                        <th>Resolution Note</th>
                        <th id="actionsHeader">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="tableMessage"></div>
    </section>

    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Maintenance Request</h3>
            </div>
            <p class="muted-text">Ticket number is generated automatically.</p>
            <form id="createForm" class="simple-form">
                <div class="form-row">
                    <label for="room_id">Room*</label>
                    <select id="room_id" name="room_id" required>
                        <option value="">-- Select Room --</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="description">Description*</label>
                    <textarea id="description" name="description" rows="3" placeholder="Describe the issue..." required style="width:100%;resize:vertical;"></textarea>
                </div>
                <div class="form-row">
                    <label for="date_reported">Date Reported*</label>
                    <input type="date" id="date_reported" name="date_reported" required />
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn">Submit Request</button>
                    <button type="button" class="btn secondary" id="cancelCreate">Cancel</button>
                </div>
                <div id="createErrors" class="alert error" style="display:none;margin-top:8px;"></div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Maintenance Request</h3>
            <form id="editForm" class="simple-form">
                <input type="hidden" name="maintenance_id" id="maintenance_id">

                <div id="adminEditFields">
                    <div class="form-row">
                        <label for="edit_assigned_to">Assign To</label>
                        <select id="edit_assigned_to" name="edit_assigned_to">
                            <option value="">-- Select Staff --</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="Pending">Pending</option>
                            <option value="Inprogress">Inprogress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="resolution_note">Resolution Note</label>
                        <textarea name="resolution_note" id="resolution_note" rows="4"></textarea>
                    </div>
                </div>

                <div id="studentEditFields">
                    <div class="form-row">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="edit_description" rows="4" style="width:100%;resize:vertical;"></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" id="saveEdit">Save</button>
                    <button type="button" class="btn secondary" id="cancelEdit">Cancel</button>
                </div>
                <div id="editErrors" class="alert error" style="display:none;margin-top:8px;"></div>
            </form>
        </div>
    </div>
</main>

<script>
// Authenticated user data injected from PHP
const authUser = <?php echo json_encode($authUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
// Student's assigned room (null if admin or no room assigned)
const studentRoomId = <?php echo json_encode($studentRoomId); ?>;
// Role helpers used to show/hide UI and actions
const isAdmin = authUser && authUser.role === 'admin';
const isStudent = authUser && authUser.role === 'student';

// DOM references
const tableMessage = document.getElementById('tableMessage');
const tableHeading = document.getElementById('tableHeading');
const actionsHeader = document.getElementById('actionsHeader');
const tbody = document.querySelector('#maintenanceTable tbody');
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const createModal = document.getElementById('createModal');
const editModal = document.getElementById('editModal');
const createForm = document.getElementById('createForm');
const createErrors = document.getElementById('createErrors');
const dateInput = document.getElementById('date_reported');
const tabLinks = document.querySelectorAll('.top-nav a[data-view]');
const addRequestLink = document.getElementById('openCreateLink');
const archivedTabLink = document.querySelector('.top-nav a[data-view="archived"]');
const adminEditFields = document.getElementById('adminEditFields');
const studentEditFields = document.getElementById('studentEditFields');

let maintenanceData = [];
let selectsLoaded = false;
let editRoomsLoaded = false;
let currentView = 'active';

if (dateInput) {
    dateInput.value = new Date().toISOString().slice(0, 10);
}

if (!isStudent) {
    addRequestLink.style.display = 'none';
}
if (!isAdmin && archivedTabLink) {
    archivedTabLink.style.display = 'none';
}

// Modal helpers
function openModal(modal) {
    modal.classList.add('is-open');
}

function closeModal(modal) {
    modal.classList.remove('is-open');
}

// DOM cell factories for table rows
function createCell(value) {
    const td = document.createElement('td');
    td.textContent = value ?? '';
    return td;
}

function createNoteCell(note) {
    const td = document.createElement('td');
    const text = (note || '').trim();
    if (!text) {
        td.className = 'muted-text';
        td.textContent = '-';
        return td;
    }
    const span = document.createElement('span');
    span.className = 'truncate';
    span.textContent = text;
    span.title = text;
    td.className = 'note-cell';
    td.appendChild(span);
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

// Create the actions cell depending on view and role
function createActionsCell(row) {
    const td = document.createElement('td');
    if (currentView === 'archived') {
        if (!isAdmin) {
            td.textContent = '-';
            return td;
        }
        const restoreBtn = document.createElement('button');
        restoreBtn.textContent = 'Unarchive';
        restoreBtn.className = 'btn small secondary';
        restoreBtn.addEventListener('click', () => restoreMaintenance(row.maintenance_id));
        td.appendChild(restoreBtn);
        return td;
    }

    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.className = 'btn small';
    editBtn.addEventListener('click', () => openEditModal(row.maintenance_id));

    td.appendChild(editBtn);

    if (isAdmin) {
        const delBtn = document.createElement('button');
        delBtn.textContent = 'Archive';
        delBtn.className = 'btn small danger';
        delBtn.addEventListener('click', () => deleteMaintenance(row.maintenance_id));
        td.appendChild(document.createTextNode(' '));
        td.appendChild(delBtn);
    }
    return td;
}

// Normalize status strings coming from the API to consistent labels
function normalizeStatus(status, isResolved) {
    const value = (status || '').toString().trim();
    const lower = value.toLowerCase();
    if (lower === 'completed' || lower === 'resolved') return 'Completed';
    if (lower === 'inprogress' || lower === 'in-progress') return 'Inprogress';
    if (lower === 'pending') return 'Pending';
    if (value) return value;
    return isResolved == 1 ? 'Completed' : 'Pending';
}

// Table message helpers
function showTableMessage(type, text) {
    tableMessage.className = 'alert ' + type;
    tableMessage.textContent = text;
}

function clearTableMessage() {
    tableMessage.className = '';
    tableMessage.textContent = '';
}

// Filtering logic for search and status dropdown
function matchesFilters(row, query, statusValue) {
    const statusText = normalizeStatus(row.status, row.is_resolved);
    if (statusValue !== 'all' && statusText.toLowerCase() !== statusValue) return false;
    if (!query) return true;
    const haystack = [
        row.ticket_number,
        row.room_number,
        row.assigned_to_name,
        row.reported_by_name,
        row.date_reported,
        statusText,
        row.resolution_note
    ].map(value => (value || '').toString().toLowerCase()).join(' ');
    return haystack.includes(query);
}

// Render table rows based on `maintenanceData` and current filters
function renderMaintenance() {
    tbody.innerHTML = '';
    if (!maintenanceData.length) {
        showTableMessage('info', currentView === 'active' ? 'No maintenance requests found.' : 'No archived requests found.');
        return;
    }

    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const statusValue = (statusFilter ? statusFilter.value : 'all').toLowerCase();
    const filtered = maintenanceData.filter(row => matchesFilters(row, query, statusValue));

    if (!filtered.length) {
        showTableMessage('info', 'No matching requests found.');
        return;
    }

    clearTableMessage();
    filtered.forEach(row => {
        const tr = document.createElement('tr');
        const statusText = normalizeStatus(row.status, row.is_resolved);
        tr.appendChild(createCell(row.ticket_number));
        tr.appendChild(createCell(row.room_number));
        tr.appendChild(createNoteCell(row.description));
        tr.appendChild(createCell(row.assigned_to_name || 'None'));
        tr.appendChild(createCell(row.reported_by_name));
        tr.appendChild(createCell(row.date_reported));
        tr.appendChild(createStatusCell(statusText));
        tr.appendChild(createNoteCell(row.resolution_note));
        tr.appendChild(createActionsCell(row));
        tbody.appendChild(tr);
    });
}

// Load maintenance list from API (active or archived)
async function loadMaintenance() {
    try {
        const resp = await fetch(`../../api/maintenance/get_maintenance.php?view=${encodeURIComponent(currentView)}`, { cache: 'no-store' });
        const json = await resp.json();
        if (json.success && Array.isArray(json.data)) {
            maintenanceData = json.data;
            renderMaintenance();
        } else {
            maintenanceData = [];
            tbody.innerHTML = '';
            showTableMessage('error', json.errors ? json.errors.join(' ') : 'Failed to fetch maintenance requests.');
        }
    } catch (err) {
        maintenanceData = [];
        tbody.innerHTML = '';
        showTableMessage('error', 'Failed to load maintenance requests.');
    }
}

// Helpers to try multiple candidate URLs (local relative, project root, absolute)
function candidateUrls(endpointPath) {
    const path = endpointPath.startsWith('/') ? endpointPath : '/' + endpointPath;
    return [
        '../../api/maintenance/' + endpointPath,
        '/HostelHub/api/maintenance/' + endpointPath,
        path
    ];
}

// Fetch JSON from several candidate URLs until one succeeds
async function fetchJsonWithFallback(endpointFile) {
    const urls = candidateUrls(endpointFile);
    for (const url of urls) {
        try {
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) continue;
            const text = await response.text();
            return JSON.parse(text);
        } catch (err) {
            // try next candidate
        }
    }
    return { success: false, errors: ['Failed to connect to API endpoint: ' + endpointFile] };
}

// Submit a new maintenance request (tries candidate URLs)
async function submitMaintenance(data) {
    const urls = candidateUrls('submit_maintenance.php');
    for (const url of urls) {
        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });
            if (!resp.ok) continue;
            const text = await resp.text();
            return JSON.parse(text);
        } catch (err) {
            // try next candidate
        }
    }
    return { success: false, errors: ['Failed to submit maintenance request.'] };
}

// Utility to preserve placeholder option and clear remaining options
function resetSelectOptions(select) {
    const placeholder = select.querySelector('option') ? select.querySelector('option').cloneNode(true) : null;
    select.innerHTML = '';
    if (placeholder) select.appendChild(placeholder);
}

function showCreateError(text) {
    createErrors.style.display = 'block';
    createErrors.textContent = text;
}

function clearCreateError() {
    createErrors.style.display = 'none';
    createErrors.textContent = '';
}

// Populate room dropdown from API
async function populateRoomOptions(selectElement) {
    resetSelectOptions(selectElement);
    const roomsResp = await fetchJsonWithFallback('list_rooms.php');
    if (!roomsResp || !roomsResp.success) {
        return roomsResp && roomsResp.errors ? roomsResp.errors.join(' ') : 'Failed to load rooms.';
    }
    roomsResp.data.forEach(rm => {
        const opt = document.createElement('option');
        opt.value = rm.room_id;
        opt.textContent = `${rm.room_number} (cap:${rm.capacity || 1})`;
        selectElement.appendChild(opt);
    });
    // For students, auto-select their assigned room and lock the field
    if (isStudent && studentRoomId) {
        selectElement.value = String(studentRoomId);
        selectElement.disabled = true;
    }
    return null;
}

// Populate room dropdown for the create form
async function populateSelects() {
    try {
        clearCreateError();
        const roomSelect = document.getElementById('room_id');
        const roomErr = await populateRoomOptions(roomSelect);
        if (roomErr) { showCreateError(roomErr); return false; }
        return true;
    } catch (err) {
        showCreateError('Failed to load room options.');
        return false;
    }
}

// Populate staff dropdown for the admin edit form (lazy-loaded)
let editStaffsLoaded = false;
async function populateEditStaffs() {
    const staffSelect = document.getElementById('edit_assigned_to');
    resetSelectOptions(staffSelect);
    const staffResp = await fetchJsonWithFallback('list_staffs.php');
    if (staffResp && staffResp.success) {
        staffResp.data.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.staff_id;
            opt.textContent = `${s.name} (${s.role || 'staff'})`;
            staffSelect.appendChild(opt);
        });
        return true;
    }
    return false;
}

// Form helpers
function resetCreateForm() {
    createForm.reset();
    if (dateInput) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
}

// Open create modal (students only in active view) and load selects lazily
async function openCreateModal() {
    if (currentView !== 'active' || !isStudent) return;
    resetCreateForm();
    clearCreateError();
    openModal(createModal);
    if (!selectsLoaded) {
        selectsLoaded = await populateSelects();
    }
}

function closeCreateModal() {
    closeModal(createModal);
}

addRequestLink.addEventListener('click', (event) => {
    event.preventDefault();
    openCreateModal();
});

document.getElementById('cancelCreate').addEventListener('click', closeCreateModal);

// Validate create form data on the client before submission
function validateMaintenanceForm(data) {
    const errors = [];
    if (!data.room_id || !String(data.room_id).trim()) errors.push('Room ID is required.');
    if (!data.description || !String(data.description).trim()) errors.push('Description is required.');
    if (!data.date_reported) errors.push('Date Reported is required.');
    if (data.room_id && !/^\d+$/.test(String(data.room_id))) errors.push('Room ID must be a room ID.');
    if (data.date_reported && !/^\d{4}-\d{2}-\d{2}$/.test(data.date_reported)) errors.push('Date must be YYYY-MM-DD.');
    return errors;
}

// Create form submission handler
createForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearCreateError();

    const roomSelect = document.getElementById('room_id');
    const data = {
        // Use studentRoomId as fallback because disabled selects don't submit values
        room_id:       roomSelect.value.trim() || (studentRoomId ? String(studentRoomId) : ''),
        description:   document.getElementById('description').value.trim(),
        date_reported: document.getElementById('date_reported').value
    };

    const errors = validateMaintenanceForm(data);
    if (errors.length) {
        showCreateError(errors.join(' '));
        return;
    }

    try {
        const json = await submitMaintenance(data);
        if (json.success) {
            closeCreateModal();
            resetCreateForm();
            loadMaintenance();
        } else {
            showCreateError(json.errors ? json.errors.join(' ') : 'Submission failed.');
        }
    } catch (err) {
        showCreateError('Network or server error.');
    }
});

if (searchInput) {
    searchInput.addEventListener('input', renderMaintenance);
}

if (statusFilter) {
    statusFilter.addEventListener('change', renderMaintenance);
}

// Switch between active and archived views (archived only for admins)
function setActiveView(view) {
    currentView = (view === 'archived' && isAdmin) ? 'archived' : 'active';
    tabLinks.forEach(link => {
        link.classList.toggle('active', link.dataset.view === currentView);
    });
    tableHeading.textContent = currentView === 'archived' ? 'Archived Requests' : 'Active Requests';
    actionsHeader.textContent = currentView === 'archived' ? 'Archive Action' : 'Actions';
    closeCreateModal();
    closeEditModal();
    loadMaintenance();
}

tabLinks.forEach(link => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        setActiveView(link.dataset.view);
    });
});

loadMaintenance();

// Open edit modal and populate fields for the given item id
async function openEditModal(id) {
    if (currentView !== 'active') return;
    try {
        const resp = await fetch(`../../api/maintenance/get_maintenance_item.php?id=${encodeURIComponent(id)}`);
        const json = await resp.json();
        if (!json.success) {
            alert(json.errors ? json.errors.join('\n') : 'Failed to fetch item');
            return;
        }
        const data = json.data;
        document.getElementById('maintenance_id').value = data.maintenance_id;
        document.getElementById('editErrors').style.display = 'none';
        document.getElementById('editErrors').textContent = '';

        if (isAdmin) {
            adminEditFields.style.display = 'block';
            studentEditFields.style.display = 'none';
            document.getElementById('status').value = normalizeStatus(data.status, data.is_resolved);
            document.getElementById('resolution_note').value = data.resolution_note || '';
            if (!editStaffsLoaded) {
                editStaffsLoaded = await populateEditStaffs();
            }
            // Pre-select current assigned staff by their user_id (reliable, no name-matching)
            const staffSel = document.getElementById('edit_assigned_to');
            staffSel.value = data.assigned_to_id ? String(data.assigned_to_id) : '';
        } else {
            adminEditFields.style.display = 'none';
            studentEditFields.style.display = 'block';
            document.getElementById('edit_description').value = data.description || '';
        }

        openModal(editModal);
    } catch (err) {
        alert('Failed to fetch maintenance item.');
    }
}

function closeEditModal() {
    closeModal(editModal);
}

document.getElementById('cancelEdit').addEventListener('click', closeEditModal);

document.getElementById('saveEdit').addEventListener('click', async () => {
    const id = document.getElementById('maintenance_id').value;
    const errBox = document.getElementById('editErrors');
    const payload = new URLSearchParams({ maintenance_id: id });
    const errors = [];

    if (isAdmin) {
        const status          = document.getElementById('status').value;
        const resolution_note = document.getElementById('resolution_note').value.trim();
        const assigned_to     = document.getElementById('edit_assigned_to').value.trim();
        if (!status) errors.push('Status is required.');
        if (status === 'Completed' && resolution_note.length === 0) errors.push('Resolution Note is required when marked Completed.');
        if (resolution_note.length > 2000) errors.push('Resolution Note must be 2000 characters or less.');
        payload.append('status', status);
        payload.append('resolution_note', resolution_note);
        // Always send assigned_to: empty string = clear assignment, digit = assign to that user
        payload.append('assigned_to', assigned_to);
    } else {
        const description = document.getElementById('edit_description').value.trim();
        if (!description) errors.push('Description is required.');
        payload.append('description', description);
    }

    if (errors.length) {
        errBox.style.display = 'block';
        errBox.textContent = errors.join(' ');
        return;
    }

    try {
        const resp = await fetch('../../api/maintenance/update_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload
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
    if (!isAdmin) return;
    if (!confirm('Archive this maintenance request?')) return;
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
            alert(json.errors ? json.errors.join('\n') : 'Failed to archive.');
        }
    } catch (err) {
        alert('Archive request failed.');
    }
}

async function restoreMaintenance(id) {
    if (!isAdmin) return;
    try {
        const resp = await fetch('../../api/maintenance/restore_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ maintenance_id: id })
        });
        const json = await resp.json();
        if (json.success) {
            loadMaintenance();
        } else {
            alert(json.errors ? json.errors.join('\n') : 'Failed to unarchive.');
        }
    } catch (err) {
        alert('Unarchive request failed.');
    }
}
</script>

</body>
</html>

