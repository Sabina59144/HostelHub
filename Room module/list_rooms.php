<?php
/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();
require_once '../includes/db.php';

/* ── Filters from URL params ────────────────────── */
$search  = trim($_GET['search'] ?? '');
$type    = $_GET['type'] ?? '';
$ensuite = $_GET['ensuite'] ?? '';

/* ── Build query with optional filters & occupant count ── */
$sql    = "SELECT r.*, COUNT(s.student_id) AS occupants
           FROM rooms r
           LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
           WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= " AND r.room_number LIKE ?"; $params[] = "%$search%"; }
if ($type   !== '') { $sql .= " AND r.room_type = ?";      $params[] = $type; }
if ($ensuite !== '') { $sql .= " AND r.is_ensuite = ?";    $params[] = (int)$ensuite; }
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
    <title>HostelHub — All Rooms</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:1200px; margin:0 auto; }
        .page-header { margin-bottom:28px; display:flex; justify-content:space-between; align-items:flex-start; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }
        .btn-add { background:#1a56db; color:#fff; text-decoration:none; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:600; }
        .btn-add:hover { background:#1341b0; text-decoration:none; color:#fff; }
        .toolbar { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
        .search-box { flex:1; min-width:200px; padding:9px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; background:#fff; outline:none; }
        .search-box:focus { border-color:#1a56db; }
        .filter-select { padding:9px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; background:#fff; }
        .btn-search { background:#1a56db; color:#fff; border:none; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; }
        .btn-clear  { font-size:12px; color:#64748b; text-decoration:none; }
        .card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; overflow:hidden; }
        .card-header { display:flex; justify-content:space-between; align-items:center; padding:18px 20px; border-bottom:1px solid #f0f4f8; }
        .card-header h3 { font-size:15px; font-weight:600; color:#0f1923; }
        .results-count { font-size:13px; color:#64748b; padding:10px 20px; }
        table { width:100%; border-collapse:collapse; }
        thead { background:#1e293b; }
        thead th { padding:13px 16px; text-align:left; font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; }
        tbody tr { border-bottom:1px solid #f0f4f8; }
        tbody tr:hover td { background:#f8fafc; }
        tbody td { padding:12px 16px; font-size:13px; vertical-align:middle; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-available { background:#ecfdf5; color:#059669; }
        .badge-soon      { background:#fffbeb; color:#d97706; }
        .badge-full      { background:#fff1f2; color:#dc2626; }
        .badge-spots     { background:#eff6ff; color:#1a56db; }
        .badge-ensuite   { background:#ecfdf5; color:#059669; }
        .badge-shared    { background:#f1f5f9; color:#64748b; }
        .btn-edit     { background:#eff6ff; color:#1a56db; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; margin-right:4px; transition:background 0.2s; }
        .btn-edit:hover { background:#1a56db; color:#fff; text-decoration:none; }
        .btn-allocate { background:#ecfdf5; color:#059669; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; margin-right:4px; transition:background 0.2s; }
        .btn-allocate:hover { background:#059669; color:#fff; text-decoration:none; }
        .btn-delete   { background:#fff1f2; color:#dc2626; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; transition:background 0.2s; }
        .btn-delete:hover { background:#dc2626; color:#fff; text-decoration:none; }
        .no-data { text-align:center; padding:40px; color:#94a3b8; font-size:13px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <div>
            <h2>All Rooms</h2>
            <p>Browse, search and manage all hostel rooms</p>
        </div>
        <a href="add_room.php" class="btn-add">➕ Add Room</a>
    </div>

    <form method="GET" action="">
        <div class="toolbar">
            <input type="text" name="search" class="search-box" placeholder="🔍 Search by room number…" value="<?= htmlspecialchars($search) ?>">
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="single" <?= $type==='single'?'selected':'' ?>>Single</option>
                <option value="double" <?= $type==='double'?'selected':'' ?>>Double</option>
                <option value="triple" <?= $type==='triple'?'selected':'' ?>>Triple</option>
            </select>
            <select name="ensuite" class="filter-select">
                <option value="">All Bathrooms</option>
                <option value="1" <?= $ensuite==='1'?'selected':'' ?>>Ensuite Only</option>
                <option value="0" <?= $ensuite==='0'?'selected':'' ?>>Shared Only</option>
            </select>
            <button type="submit" class="btn-search">Search</button>
            <?php if ($search !== '' || $type !== '' || $ensuite !== ''): ?>
                <a href="list_rooms.php" class="btn-clear">✕ Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3>🏨 Rooms</h3>
            <a href="add_room.php" style="font-size:13px;color:#1a56db;text-decoration:none;font-weight:600;">➕ Add Room</a>
        </div>
        <?php if (empty($rooms)): ?>
            <div class="no-data">No rooms found. <a href="add_room.php">Add the first one →</a></div>
        <?php else: ?>
            <div class="results-count"><?= count($rooms) ?> room(s) found</div>
            <table>
                <thead>
                    <tr>
                        <th>Room No.</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Price/Month</th>
                        <th>Ensuite</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $room):
                    $occ = (int)$room['occupants'];
                    $cap = (int)$room['capacity'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                    <td><?= ucfirst(htmlspecialchars($room['room_type'])) ?></td>
                    <td><?= $cap ?></td>
                    <td>
                        <?php if ($occ >= $cap): ?>
                            <span class="badge badge-full"><?= $occ ?>/<?= $cap ?></span>
                        <?php else: ?>
                            <span class="badge badge-spots"><?= $occ ?>/<?= $cap ?></span>
                        <?php endif; ?>
                    </td>
                    <td>£<?= number_format($room['price_per_month'], 2) ?></td>
                    <td>
                        <?php if ($room['is_ensuite']): ?>
                            <span class="badge badge-ensuite">Yes</span>
                        <?php else: ?>
                            <span class="badge badge-shared">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($room['available_from'] <= date('Y-m-d')): ?>
                            <span class="badge badge-available">Available</span>
                        <?php else: ?>
                            <span class="badge badge-soon">From <?= htmlspecialchars($room['available_from']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_room.php?id=<?= $room['room_id'] ?>" class="btn-edit">✏️ Edit</a>
                        <?php if ($occ < $cap): ?>
                            <a href="allocate_room.php?room_id=<?= $room['room_id'] ?>" class="btn-allocate">🔑 Allocate</a>
                        <?php endif; ?>
                        <a href="delete_room.php?id=<?= $room['room_id'] ?>" class="btn-delete"
                           onclick="return confirm('Delete room <?= htmlspecialchars(addslashes($room['room_number'])) ?>?')">🗑️ Delete</a>
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
