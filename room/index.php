<?php
// session_start();
// Temporarily disabled — uncomment when login.php is ready
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Stats ─────────────────────────────────────────────────────────────
$totalRooms     = $db->query("SELECT COUNT(*) AS c FROM rooms")->fetch()['c'];
$totalAvailable = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE available_from <= CURDATE()")->fetch()['c'];
$totalEnsuite   = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE is_ensuite = 1")->fetch()['c'];
$totalOccupied  = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NOT NULL AND status = 1")->fetch()['c'];

// ── Success message from redirect ─────────────────────────────────────
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')   $msg = "✅ Room added successfully!";
    if ($_GET['msg'] === 'updated') $msg = "✅ Room updated successfully!";
    if ($_GET['msg'] === 'deleted') $msg = "✅ Room deleted successfully!";
}

// ── Search + Filter (PDO prepared) ────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$type   = $_GET['type'] ?? '';

$sql    = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND room_number LIKE ?";
    $params[] = "%$search%";
}
if ($type !== '') {
    $sql     .= " AND room_type = ?";
    $params[] = $type;
}
$sql .= " ORDER BY room_number";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// ── Recent rooms for dashboard table ─────────────────────────────────
$recentRooms = $db->query(
    "SELECT r.*, 
            COUNT(s.student_id) AS occupants
     FROM rooms r
     LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
     GROUP BY r.room_id
     ORDER BY r.room_id DESC
     LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Room Module</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }

        /* ── Navbar ── */
        .navbar {
            background: #B71C1C; color: white;
            padding: 14px 30px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a  { color: white; text-decoration: none; margin-left: 20px; font-size: 13px; }
        .navbar a:hover { text-decoration: underline; }

        /* ── Container ── */
        .container { padding: 30px; max-width: 1100px; margin: 0 auto; }

        /* ── Page title ── */
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 24px; color: #B71C1C; }
        .page-title p  { font-size: 13px; color: #666; margin-top: 4px; }

        /* ── Alert ── */
        .alert-success {
            background: #e8f5e9; border: 1px solid #a5d6a7;
            color: #2e7d32; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;
        }

        /* ── Stat cards ── */
        .stat-cards {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 16px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 10px;
            padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-left: 5px solid #ccc; transition: transform 0.15s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.total     { border-color: #B71C1C; }
        .stat-card.available { border-color: #2e7d32; }
        .stat-card.ensuite   { border-color: #6a1b9a; }
        .stat-card.occupied  { border-color: #e65100; }
        .stat-number { font-size: 36px; font-weight: 700; color: #222; line-height: 1; }
        .stat-label  { font-size: 12px; color: #777; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon   { font-size: 28px; float: right; opacity: 0.15; margin-top: -4px; }

        /* ── Section title ── */
        .section-title {
            font-size: 14px; font-weight: 700; color: #555;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;
        }

        /* ── Action buttons ── */
        .action-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 14px; margin-bottom: 30px;
        }
        .action-btn {
            background: white; border-radius: 10px;
            padding: 20px 16px; text-decoration: none; color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex; flex-direction: column; align-items: center;
            gap: 8px; transition: all 0.15s; border: 2px solid transparent;
        }
        .action-btn:hover { border-color: #B71C1C; transform: translateY(-2px); }
        .action-btn .btn-icon  { font-size: 28px; }
        .action-btn .btn-label { font-size: 14px; font-weight: 700; color: #222; }
        .action-btn .btn-desc  { font-size: 11px; color: #999; text-align: center; }

        /* ── Search bar ── */
        .search-card {
            background: white; border-radius: 10px;
            padding: 18px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .search-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-row input, .search-row select {
            padding: 9px 12px; border: 1px solid #ddd;
            border-radius: 6px; font-size: 13px; font-family: inherit;
        }
        .search-row input:focus, .search-row select:focus {
            outline: none; border-color: #B71C1C;
        }
        .search-row input { flex: 1; min-width: 200px; }
        .btn-search {
            background: #B71C1C; color: white; border: none;
            padding: 9px 20px; border-radius: 6px; font-size: 13px;
            cursor: pointer; font-weight: 600;
        }
        .btn-search:hover { background: #8B0000; }
        .btn-clear {
            background: white; color: #555; border: 1px solid #ccc;
            padding: 9px 16px; border-radius: 6px; font-size: 13px;
            text-decoration: none; font-weight: 600;
        }

        /* ── Table card ── */
        .card {
            background: white; border-radius: 10px;
            padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px;
        }
        .card-header h3 { font-size: 15px; color: #333; }
        .card-header a  { font-size: 13px; color: #B71C1C; text-decoration: none; }
        .card-header a:hover { text-decoration: underline; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 11px; color: #999;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 8px 12px; border-bottom: 2px solid #f0f0f0;
        }
        td {
            padding: 10px 12px; font-size: 13px;
            border-bottom: 1px solid #f5f5f5; vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        .badge {
            display: inline-block; padding: 2px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-available { background: #e8f5e9; color: #2e7d32; }
        .badge-soon      { background: #fff3e0; color: #e65100; }
        .badge-ensuite   { background: #f3e5f5; color: #6a1b9a; }
        .badge-shared    { background: #f5f5f5; color: #777; }

        .action-link { font-size: 13px; text-decoration: none; margin-right: 8px; }
        .action-link.edit   { color: #1565c0; }
        .action-link.delete { color: #c62828; }
        .action-link:hover  { text-decoration: underline; }

        .no-data { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        .results-count { font-size: 12px; color: #999; margin-bottom: 12px; }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <h1><img src="../room/home.png" alt=""></h1>
    <div>
        <span style="font-size:13px; opacity:0.85;">Logged in as:
            <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?></strong>
        </span>
        <a href="../Student module/index.php">👨‍🎓 Students</a>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <!-- Page heading -->
    <div class="page-title">
        <h2>🛏️ Room Module</h2>
        <p>Manage hostel rooms — add, view, edit, and search rooms</p>
    </div>

    <!-- Success message -->
    <?php if ($msg): ?>
        <div class="alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="stat-cards">
        <div class="stat-card total">
            <div class="stat-icon">🛏️</div>
            <div class="stat-number"><?php echo $totalRooms; ?></div>
            <div class="stat-label">Total Rooms</div>
        </div>
        <div class="stat-card available">
            <div class="stat-icon">✅</div>
            <div class="stat-number"><?php echo $totalAvailable; ?></div>
            <div class="stat-label">Available Now</div>
        </div>
        <div class="stat-card ensuite">
            <div class="stat-icon">🚿</div>
            <div class="stat-number"><?php echo $totalEnsuite; ?></div>
            <div class="stat-label">Ensuite Rooms</div>
        </div>
        <div class="stat-card occupied">
            <div class="stat-icon">👤</div>
            <div class="stat-number"><?php echo $totalOccupied; ?></div>
            <div class="stat-label">Students Housed</div>
        </div>
    </div>

    <!-- ── Quick Actions ── -->
    <div class="section-title">Quick Actions</div>
    <div class="action-grid">
        <a href="add_room.php" class="action-btn">
            <div class="btn-icon">➕</div>
            <div class="btn-label">Add Room</div>
            <div class="btn-desc">Register a new room</div>
        </a>
        <a href="list_rooms.php" class="action-btn">
            <div class="btn-icon">📋</div>
            <div class="btn-label">View All</div>
            <div class="btn-desc">Browse all room records</div>
        </a>
        <a href="list_rooms.php?type=single" class="action-btn">
            <div class="btn-icon">🔍</div>
            <div class="btn-label">Single Rooms</div>
            <div class="btn-desc">Filter single occupancy</div>
        </a>
        <a href="list_rooms.php?filter=ensuite" class="action-btn">
            <div class="btn-icon">🚿</div>
            <div class="btn-label">Ensuite Rooms</div>
            <div class="btn-desc">Rooms with ensuite facility</div>
        </a>
    </div>

    <!-- ── Search & Filter ── -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <input type="text" name="search"
                       placeholder="🔍  Search by room number..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="single" <?php echo $type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo $type === 'double' ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo $type === 'triple' ? 'selected' : ''; ?>>Triple</option>
                </select>
                <button type="submit" class="btn-search">Search</button>
                <?php if ($search !== '' || $type !== ''): ?>
                    <a href="index.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Rooms Table ── -->
    <div class="card">
        <div class="card-header">
            <h3>🛏️ <?php echo ($search !== '' || $type !== '') ? 'Search Results' : 'Recently Added Rooms'; ?></h3>
            <a href="add_room.php">+ Add New Room</a>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="no-data">
                <?php if ($search !== '' || $type !== ''): ?>
                    No rooms match your search. <a href="index.php">Clear filters →</a>
                <?php else: ?>
                    No rooms registered yet. <a href="add_room.php">Add the first one →</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="results-count"><?php echo count($rooms); ?> room(s) found</div>
            <table>
                <thead>
                    <tr>
                        <th>Room No.</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Price / Month</th>
                        <th>Ensuite</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                        <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                        <td><?php echo $room['capacity']; ?> person(s)</td>
                        <td>£<?php echo number_format($room['price_per_month'], 2); ?></td>
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
                                <span class="badge badge-soon">From <?php echo $room['available_from']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_room.php?id=<?php echo $room['room_id']; ?>" class="action-link edit">Edit</a>
                            <a href="delete_room.php?id=<?php echo $room['room_id']; ?>"
                               class="action-link delete"
                               onclick="return confirm('Are you sure you want to delete room <?php echo htmlspecialchars($room['room_number']); ?>?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div><!-- end container -->

</body>
</html>
<?php $db = null; ?>
