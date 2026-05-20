<?php
/**
 * Room module/index.php
 * ─────────────────────────────────────────────────────────────
 * Room module overview dashboard.
 *
 * Shows:
 *   • Stat cards: total rooms / available / ensuite / students housed
 *   • Availability rate progress bar
 *   • Quick-action buttons (add, view all, allocate, ensuite filter)
 *   • Room list table with search and type filter
 *   • Flash message support (?msg=added|updated|deleted|allocated)
 *
 * Occupant counts are calculated live via LEFT JOIN + COUNT,
 * only counting students with status=1 (active/enrolled).
 * ─────────────────────────────────────────────────────────────
 */

/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();                          // Redirect to login if not authenticated
require_once '../includes/db.php';

/* ── Summary stats for stat cards ─────────────── */
$totalRooms     = $db->query("SELECT COUNT(*) AS c FROM rooms")->fetch()['c'];
$totalAvailable = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE available_from <= CURDATE()")->fetch()['c'];
$totalEnsuite   = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE is_ensuite = 1")->fetch()['c'];
$totalOccupied  = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NOT NULL AND status = 1")->fetch()['c'];
$availabilityRate = $totalRooms > 0 ? round(($totalAvailable / $totalRooms) * 100) : 0;

/* ── Flash message after add / edit / delete / allocate ── */
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')     $msg = "Room added successfully!";
    if ($_GET['msg'] === 'updated')   $msg = "Room updated successfully!";
    if ($_GET['msg'] === 'deleted')   $msg = "Room deleted successfully!";
    if ($_GET['msg'] === 'allocated') $msg = "Student allocated successfully!";
}

/* ── Search, type & availability filter ────────── */
$search    = trim($_GET['search']    ?? '');
$type      = $_GET['type']           ?? '';
$available = $_GET['available']      ?? '';

/* ── Build room list with live occupant count ─── */
$sql    = "SELECT r.*, COUNT(s.student_id) AS occupants
           FROM rooms r
           LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
           WHERE 1=1";
$params = [];
if ($search !== '')   { $sql .= " AND r.room_number LIKE ?"; $params[] = "%$search%"; }
if ($type !== '')     { $sql .= " AND r.room_type = ?";      $params[] = $type; }
if ($available === '1') { $sql .= " AND r.available_from <= CURDATE()"; }
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
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:1200px; margin:0 auto; }
        .page-header { margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }
        .stat-cards { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:32px; }
        .stat-card { background:#fff; border-radius:16px; padding:24px 22px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; position:relative; overflow:hidden; transition:transform 0.2s,box-shadow 0.2s; }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 10px 30px rgba(0,0,0,0.1); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:16px 16px 0 0; }
        .stat-card.blue::before  { background:linear-gradient(90deg,#1a56db,#60a5fa); }
        .stat-card.green::before { background:linear-gradient(90deg,#059669,#34d399); }
        .stat-card.amber::before { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-card.rose::before  { background:linear-gradient(90deg,#dc2626,#fb7185); }
        .stat-icon-wrap { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:16px; }
        .blue .stat-icon-wrap { background:#eff6ff; } .green .stat-icon-wrap { background:#ecfdf5; }
        .amber .stat-icon-wrap { background:#fffbeb; } .rose .stat-icon-wrap { background:#fff1f2; }
        .stat-number { font-family:'Playfair Display',serif; font-size:2.2rem; font-weight:700; line-height:1; color:#0f1923; display:block; margin-bottom:5px; }
        .stat-label  { font-size:13px; color:#64748b; font-weight:500; display:block; }
        .section-label { font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#94a3b8; margin-bottom:16px; }
        .progress-wrap { margin-bottom:32px; }
        .progress-meta { display:flex; justify-content:space-between; font-size:13px; color:#64748b; margin-bottom:8px; }
        .progress-bar  { background:#e8edf3; border-radius:20px; height:8px; overflow:hidden; }
        .progress-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#1a56db,#60a5fa); }
        .action-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px; }
        .action-btn { background:#fff; border-radius:12px; padding:20px 16px; text-decoration:none; color:#1e293b; box-shadow:0 1px 4px rgba(0,0,0,0.06); border:1px solid #e8edf3; display:flex; flex-direction:column; align-items:center; gap:8px; transition:all 0.18s; }
        .action-btn:hover { border-color:#1a56db; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.09); text-decoration:none; color:#1a56db; }
        .action-btn .btn-icon { font-size:28px; } .action-btn .btn-label { font-size:14px; font-weight:600; } .action-btn .btn-desc { font-size:11px; color:#94a3b8; text-align:center; }
        .toolbar { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
        .search-box { flex:1; min-width:200px; padding:9px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; background:#fff; outline:none; }
        .search-box:focus { border-color:#1a56db; }
        .filter-select { padding:9px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; background:#fff; }
        .btn-search { background:#1a56db; color:#fff; border:none; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; }
        .btn-clear  { font-size:12px; color:#64748b; text-decoration:none; }
        .alert-success { background:#ecfdf5; border:1px solid #6ee7b7; color:#059669; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
        .card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; overflow:hidden; margin-bottom:24px; }
        .card-header { display:flex; justify-content:space-between; align-items:center; padding:18px 20px; border-bottom:1px solid #f0f4f8; }
        .card-header h3 { font-size:15px; font-weight:600; color:#0f1923; } .card-header a { font-size:13px; color:#1a56db; text-decoration:none; font-weight:600; }
        .results-count { font-size:13px; color:#64748b; padding:10px 20px; }
        table { width:100%; border-collapse:collapse; } thead { background:#1e293b; }
        thead th { padding:13px 16px; text-align:left; font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; }
        tbody tr { border-bottom:1px solid #f0f4f8; } tbody tr:hover td { background:#f8fafc; }
        tbody td { padding:12px 16px; font-size:13px; vertical-align:middle; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-available { background:#ecfdf5; color:#059669; } .badge-soon { background:#fffbeb; color:#d97706; }
        .badge-full { background:#fff1f2; color:#dc2626; } .badge-spots { background:#eff6ff; color:#1a56db; }
        .badge-ensuite { background:#ecfdf5; color:#059669; } .badge-shared { background:#f1f5f9; color:#64748b; }
        .btn-edit { background:#eff6ff; color:#1a56db; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; margin-right:4px; transition:background 0.2s; }
        .btn-edit:hover { background:#1a56db; color:#fff; text-decoration:none; }
        .btn-allocate { background:#ecfdf5; color:#059669; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; margin-right:4px; transition:background 0.2s; }
        .btn-allocate:hover { background:#059669; color:#fff; text-decoration:none; }
        .btn-delete { background:#fff1f2; color:#dc2626; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; transition:background 0.2s; }
        .btn-delete:hover { background:#dc2626; color:#fff; text-decoration:none; }
        .no-data { text-align:center; padding:40px; color:#94a3b8; font-size:13px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <h2>Room Module
            <?php if ($available === '1'): ?>
                <span style="font-size:14px;background:#ecfdf5;color:#059669;padding:3px 12px;border-radius:20px;font-family:'DM Sans',sans-serif;font-weight:600;margin-left:8px;">Available only</span>
            <?php endif; ?>
        </h2>
        <?php if ($available === '1'): ?>
            <p>Showing available rooms only &nbsp;·&nbsp; <a href="index.php" style="color:#1a56db;font-size:13px;">Clear filter</a></p>
        <?php else: ?>
            <p>Manage hostel rooms — add, view, edit, and allocate rooms</p>
        <?php endif; ?>
    </div>
    <?php if ($msg): ?><div class="alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="stat-cards">
        <div class="stat-card blue"><div class="stat-icon-wrap">🏨</div><span class="stat-number"><?= $totalRooms ?></span><span class="stat-label">Total Rooms</span></div>
        <div class="stat-card green"><div class="stat-icon-wrap">✅</div><span class="stat-number"><?= $totalAvailable ?></span><span class="stat-label">Available Now</span></div>
        <div class="stat-card amber"><div class="stat-icon-wrap">🚿</div><span class="stat-number"><?= $totalEnsuite ?></span><span class="stat-label">Ensuite Rooms</span></div>
        <div class="stat-card rose"><div class="stat-icon-wrap">👥</div><span class="stat-number"><?= $totalOccupied ?></span><span class="stat-label">Students Housed</span></div>
    </div>
    <div class="progress-wrap">
        <div class="progress-meta"><span>Room availability rate</span><span><?= $availabilityRate ?>%</span></div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $availabilityRate ?>%"></div></div>
    </div>
    <p class="section-label">Quick Actions</p>
    <div class="action-grid">
        <a href="add_room.php" class="action-btn"><div class="btn-icon">➕</div><div class="btn-label">Add Room</div><div class="btn-desc">Register a new room</div></a>
        <a href="list_rooms.php" class="action-btn"><div class="btn-icon">📋</div><div class="btn-label">View All</div><div class="btn-desc">Browse all rooms</div></a>
        <a href="allocate_room.php" class="action-btn"><div class="btn-icon">🔑</div><div class="btn-label">Allocate Room</div><div class="btn-desc">Assign student to room</div></a>
        <a href="list_rooms.php?ensuite=1" class="action-btn"><div class="btn-icon">🚿</div><div class="btn-label">Ensuite Rooms</div><div class="btn-desc">Rooms with ensuite</div></a>
    </div>
    <p class="section-label">Room List</p>
    <form method="GET" action="">
        <div class="toolbar">
            <input type="text" name="search" class="search-box" placeholder="🔍 Search by room number…" value="<?= htmlspecialchars($search) ?>">
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="single" <?= $type==='single'?'selected':'' ?>>Single</option>
                <option value="double" <?= $type==='double'?'selected':'' ?>>Double</option>
                <option value="triple" <?= $type==='triple'?'selected':'' ?>>Triple</option>
                <option value="studio" <?= $type==='studio'?'selected':'' ?>>Studio</option>
            </select>
            <button type="submit" class="btn-search">Search</button>
            <?php if ($search !== '' || $type !== ''): ?><a href="index.php" class="btn-clear">✕ Clear</a><?php endif; ?>
        </div>
    </form>
    <div class="card">
        <div class="card-header"><h3>🏨 Rooms</h3><a href="add_room.php">➕ Add Room</a></div>
        <?php if (empty($rooms)): ?>
            <div class="no-data">No rooms found. <a href="add_room.php">Add the first one →</a></div>
        <?php else: ?>
            <div class="results-count"><?= count($rooms) ?> room(s) found</div>
            <table>
                <thead><tr><th>Room No.</th><th>Type</th><th>Capacity</th><th>Occupied</th><th>Price/Month</th><th>Ensuite</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $room): $occ=(int)$room['occupants']; $cap=(int)$room['capacity']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                    <td><?= ucfirst(htmlspecialchars($room['room_type'])) ?></td>
                    <td><?= $cap ?></td>
                    <td><?php if ($occ>=$cap): ?><span class="badge badge-full"><?= $occ ?>/<?= $cap ?></span><?php else: ?><span class="badge badge-spots"><?= $occ ?>/<?= $cap ?></span><?php endif; ?></td>
                    <td>£<?= number_format($room['price_per_month'],2) ?></td>
                    <td><?php if ($room['is_ensuite']): ?><span class="badge badge-ensuite">Yes</span><?php else: ?><span class="badge badge-shared">No</span><?php endif; ?></td>
                    <td><?php if ($room['available_from']<=date('Y-m-d')): ?><span class="badge badge-available">Available</span><?php else: ?><span class="badge badge-soon">From <?= $room['available_from'] ?></span><?php endif; ?></td>
                    <td>
                        <a href="edit_room.php?id=<?= $room['room_id'] ?>" class="btn-edit">✏️ Edit</a>
                        <a href="allocate_room.php?room_id=<?= $room['room_id'] ?>" class="btn-allocate">🔑 Allocate</a>
                        <a href="delete_room.php?id=<?= $room['room_id'] ?>" class="btn-delete" onclick="return confirm('Delete room <?= htmlspecialchars(addslashes($room['room_number'])) ?>?')">🗑️ Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
