<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

// Stat counts
$totalRooms     = $db->query("SELECT COUNT(*) AS c FROM rooms")->fetch()['c'];
$totalAvailable = $db->query("
    SELECT COUNT(*) AS c FROM rooms r
    WHERE (SELECT COUNT(*) FROM students s WHERE s.room_id = r.room_id AND s.status = 1) < r.capacity
")->fetch()['c'];
$totalEnsuite  = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE ensuite_facility = 1")->fetch()['c'];
$totalOccupied = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NOT NULL AND status = 1")->fetch()['c'];
$availabilityRate = $totalRooms > 0 ? round(($totalAvailable / $totalRooms) * 100) : 0;

// Success message
$msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')     $msg = "Room added successfully!";
    if ($_GET['msg'] === 'updated')   $msg = "Room updated successfully!";
    if ($_GET['msg'] === 'deleted')   $msg = "Room deleted successfully!";
    if ($_GET['msg'] === 'allocated') $msg = "Student allocated successfully!";
    if ($_GET['msg'] === 'removed')   $msg = "Student successfully removed from the room.";
}

// Search + filter
$search = trim($_GET['search'] ?? '');
$type   = $_GET['type'] ?? '';

$sql    = "SELECT r.*, COUNT(s.student_id) AS occupants
           FROM rooms r
           LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
           WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND r.room_number LIKE ?";
    $params[] = "%$search%";
}
if ($type !== '') {
    $sql     .= " AND r.room_type = ?";
    $params[] = $type;
}
$sql .= " GROUP BY r.room_id ORDER BY r.room_number";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Room Module</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 1200px; margin: 0 auto; }

        .page-header { margin-bottom: 28px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #0f1923; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

        .stat-cards { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; margin-bottom: 32px; }
        .stat-card {
            background: #fff; border-radius: 16px; padding: 24px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e8edf3;
            position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:16px 16px 0 0; }
        .stat-card.blue::before  { background: linear-gradient(90deg,#1a56db,#60a5fa); }
        .stat-card.green::before { background: linear-gradient(90deg,#059669,#34d399); }
        .stat-card.amber::before { background: linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-card.rose::before  { background: linear-gradient(90deg,#dc2626,#fb7185); }

        .stat-icon-wrap { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:16px; }
        .blue  .stat-icon-wrap { background:#eff6ff; }
        .green .stat-icon-wrap { background:#ecfdf5; }
        .amber .stat-icon-wrap { background:#fffbeb; }
        .rose  .stat-icon-wrap { background:#fff1f2; }

        .stat-number { font-family:'Playfair Display',serif; font-size:2.2rem; font-weight:700; line-height:1; color:#0f1923; display:block; margin-bottom:5px; }
        .stat-label  { font-size:13px; color:#64748b; font-weight:500; display:block; }

        .section-label { font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#94a3b8; margin-bottom:16px; }

        .action-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px; }
        .action-btn {
            background:#fff; border-radius:12px; padding:20px 16px;
            text-decoration:none; color:#1e293b;
            box-shadow:0 1px 4px rgba(0,0,0,0.06); border:1px solid #e8edf3;
            display:flex; flex-direction:column; align-items:center; gap:8px; transition:all 0.18s;
        }
        .action-btn:hover { border-color:#1a56db; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.09); text-decoration:none; color:#1a56db; }
        .action-btn .btn-icon  { font-size:28px; }
        .action-btn .btn-label { font-size:14px; font-weight:600; }
        .action-btn .btn-desc  { font-size:11px; color:#94a3b8; text-align:center; }

        .progress-wrap { margin-bottom:32px; }
        .progress-meta { display:flex; justify-content:space-between; font-size:13px; color:#64748b; margin-bottom:8px; }
        .progress-bar  { background:#e8edf3; border-radius:20px; height:8px; overflow:hidden; }
        .progress-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#1a56db,#60a5fa); }

        /* Search bar */
        .search-wrap { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .search-wrap input, .search-wrap select {
            padding: 9px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; font-family: 'DM Sans', sans-serif; color: #1e293b;
            background: #fff; outline: none;
        }
        .search-wrap input { flex: 1; min-width: 180px; }
        .search-wrap input:focus, .search-wrap select:focus { border-color: #1a56db; }
        .btn-search { padding: 9px 18px; background: #1a56db; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-clear  { padding: 9px 14px; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; text-decoration: none; }

        .card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .card-header h3 { font-size:15px; font-weight:600; color:#0f1923; }
        .card-header a  { font-size:13px; color:#1a56db; text-decoration:none; font-weight:600; }
        .card-header a:hover { text-decoration:underline; }

        .results-count { font-size:12px; color:#94a3b8; margin-bottom:12px; }

        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; padding:8px 12px; border-bottom:2px solid #f0f4f8; }
        td { padding:11px 12px; font-size:13px; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#f8fafc; }

        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-available { background:#ecfdf5; color:#059669; }
        .badge-full      { background:#fff1f2; color:#dc2626; }
        .badge-ensuite   { background:#eff6ff; color:#1a56db; }
        .badge-shared    { background:#f1f5f9; color:#64748b; }
        .badge-soon      { background:#fffbeb; color:#d97706; }
        .badge-spots     { background:#ecfdf5; color:#059669; }

        .action-link { font-size:12px; font-weight:600; text-decoration:none; margin-right:8px; }
        .action-link.edit     { color:#1a56db; }
        .action-link.allocate { color:#059669; }
        .action-link.delete   { color:#dc2626; }

        .no-data { text-align:center; padding:30px; color:#94a3b8; font-size:13px; }
        .no-data a { color:#1a56db; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">

    <div class="page-header">
        <h2>Rooms </h2>
        <p>Manage hostel rooms — add, view, edit, and allocate rooms to students</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card blue">
            <div class="stat-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
            </div>
            <span class="stat-number"><?= $totalRooms ?></span>
            <span class="stat-label">Total Rooms</span>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="stat-number"><?= $totalAvailable ?></span>
            <span class="stat-label">Rooms with Free Beds</span>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/><path d="M4 12V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/><path d="M6 18v2"/><path d="M18 18v2"/><path d="M4 12v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>
            </div>
            <span class="stat-number"><?= $totalEnsuite ?></span>
            <span class="stat-label">Ensuite Rooms</span>
        </div>
        <div class="stat-card rose">
            <div class="stat-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="stat-number"><?= $totalOccupied ?></span>
            <span class="stat-label">Students Housed</span>
        </div>
    </div>

    <div class="progress-wrap">
        <div class="progress-meta">
            <span>Room availability rate</span>
            <span><?= $availabilityRate ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $availabilityRate ?>%"></div>
        </div>
    </div>

    <p class="section-label">Quick Actions</p>
    <div class="action-grid">
        <a href="add_room.php" class="action-btn">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </div>
            <div class="btn-label">Add Room</div>
            <div class="btn-desc">Register a new room</div>
        </a>
        <a href="list_rooms.php" class="action-btn">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/></svg>
            </div>
            <div class="btn-label">View All</div>
            <div class="btn-desc">Browse all room records</div>
        </a>
        <a href="allocate_room.php" class="action-btn">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
            <div class="btn-label">Allocate Room</div>
            <div class="btn-desc">Assign a student to a room</div>
        </a>
        <a href="list_rooms.php?filter=ensuite" class="action-btn">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/><path d="M4 12V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/><path d="M6 18v2"/><path d="M18 18v2"/><path d="M4 12v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>
            </div>
            <div class="btn-label">Ensuite Rooms</div>
            <div class="btn-desc">View ensuite rooms</div>
        </a>
    </div>

    <p class="section-label">Room List</p>

    <form method="GET" action="">
        <div class="search-wrap">
            <input type="text" name="search" placeholder="Search by room number..." value="<?= htmlspecialchars($search) ?>">
            <select name="type">
                <option value="">All Types</option>
                <option value="single" <?= $type === 'single' ? 'selected' : '' ?>>Single</option>
                <option value="double" <?= $type === 'double' ? 'selected' : '' ?>>Double</option>
                <option value="triple" <?= $type === 'triple' ? 'selected' : '' ?>>Triple</option>
            </select>
            <button type="submit" class="btn-search">Search</button>
            <?php if ($search !== '' || $type !== ''): ?>
                <a href="index.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3><?= ($search !== '' || $type !== '') ? 'Search Results' : 'All Rooms' ?></h3>
            <a href="add_room.php">+ Add New Room</a>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="no-data">
                <?php if ($search !== '' || $type !== ''): ?>
                    No rooms match your search. <a href="index.php">Clear filters</a>
                <?php else: ?>
                    No rooms yet. <a href="add_room.php">Add the first one →</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="results-count"><?= count($rooms) ?> room(s) found</div>
            <table>
                <thead>
                    <tr>
                        <th>Room No.</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Price / Month</th>
                        <th>Ensuite</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                        <td><?= ucfirst(htmlspecialchars($room['room_type'])) ?></td>
                        <td><?= $room['capacity'] ?></td>
                        <td>
                            <?php $occ = (int)$room['occupants']; $cap = (int)$room['capacity']; ?>
                            <?php if ($occ >= $cap): ?>
                                <span class="badge badge-full"><?= $occ ?> / <?= $cap ?></span>
                            <?php else: ?>
                                <span class="badge badge-spots"><?= $occ ?> / <?= $cap ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($room['price_per_month'], 2) ?> kr.</td>
                        <td>
                            <?php if ($room['ensuite_facility']): ?>
                                <span class="badge badge-ensuite">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-shared">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$room['available_from'] || $room['available_from'] <= date('Y-m-d')): ?>
                                <span class="badge badge-available">Available</span>
                            <?php else: ?>
                                <span class="badge badge-soon">From <?= $room['available_from'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_room.php?id=<?= $room['room_id'] ?>" class="action-link edit">Edit</a>
                            <a href="allocate_room.php?room_id=<?= $room['room_id'] ?>" class="action-link allocate">Allocate</a>
                            <a href="delete_room.php?id=<?= $room['room_id'] ?>" class="action-link delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php $db = null; ?>
</body>
</html>
