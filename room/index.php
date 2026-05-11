<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

// Redirect to login if not authenticated
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// ── Stats ─────────────────────────────────────────────────────────────
$totalRooms     = $db->query("SELECT COUNT(*) AS c FROM rooms")->fetch()['c'];
$totalAvailable = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE available_from <= CURDATE()")->fetch()['c'];
$totalEnsuite   = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE ensuite_facility = 1")->fetch()['c'];
$totalOccupied  = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NOT NULL AND status = 1")->fetch()['c'];

$availabilityRate = $totalRooms > 0 ? round(($totalAvailable / $totalRooms) * 100) : 0;

// ── Success message from redirect ─────────────────────────────────────
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')     $msg = "Room added successfully!";
    if ($_GET['msg'] === 'updated')   $msg = "Room updated successfully!";
    if ($_GET['msg'] === 'deleted')   $msg = "Room deleted successfully!";
    if ($_GET['msg'] === 'allocated') $msg = "Student allocated successfully!";
    if ($_GET['msg'] === 'removed')   $msg = "Student successfully removed from the room.";
}

// ── Search + Filter (PDO prepared) ────────────────────────────────────
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

$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Room Module</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* page-specific bits only */
        .stat-cards {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 18px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 12px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
            border-top: 4px solid #ccc;
            display: flex; align-items: center; gap: 16px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .stat-card.total     { border-top-color: #3b82f6; }
        .stat-card.available { border-top-color: #10b981; }
        .stat-card.ensuite   { border-top-color: #f59e0b; }
        .stat-card.occupied  { border-top-color: #ef4444; }
        .stat-icon-box {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-card.total     .stat-icon-box { background: #dbeafe; color: #2563eb; }
        .stat-card.available .stat-icon-box { background: #d1fae5; color: #059669; }
        .stat-card.ensuite   .stat-icon-box { background: #fef3c7; color: #d97706; }
        .stat-card.occupied  .stat-icon-box { background: #fee2e2; color: #dc2626; }
        .stat-text { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
        .stat-number { font-size: 32px; font-weight: 700; color: #111827; line-height: 1; }
        .stat-label  { font-size: 14px; color: #6b7280; font-weight: 500; }

        .progress-card { margin-bottom: 32px; }
        .progress-row {
            display: flex; justify-content: space-between;
            font-size: 14px; color: #4b5563; margin-bottom: 10px;
        }
        .progress-row .pct { font-weight: 600; color: #111827; }
        .progress-bar {
            background: #e5e7eb; border-radius: 999px;
            height: 6px; overflow: hidden;
        }
        .progress-fill {
            background: #3b82f6; height: 100%;
            border-radius: 999px; transition: width 0.4s;
        }

        .action-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 18px; margin-bottom: 32px;
        }
        .action-btn {
            background: white; border-radius: 12px;
            padding: 28px 16px; text-decoration: none; color: #111827;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
            display: flex; flex-direction: column; align-items: center;
            gap: 12px; transition: all 0.15s;
            border: 1px solid transparent;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #e5e7eb;
        }
        .action-btn .btn-icon {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .action-btn.add     .btn-icon { background: #ede9fe; color: #7c3aed; }
        .action-btn.view    .btn-icon { background: #fef3c7; color: #d97706; }
        .action-btn.allocate .btn-icon { background: #d1fae5; color: #059669; }
        .action-btn.ensuite .btn-icon { background: #dbeafe; color: #2563eb; }
        .action-btn .btn-label { font-size: 16px; font-weight: 700; color: #111827; }
        .action-btn .btn-desc  { font-size: 13px; color: #6b7280; text-align: center; }

        @media (max-width: 1000px) {
            .stat-cards, .action-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Room </h2>
        <p>Manage hostel rooms — add, view, edit, and search rooms</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="stat-cards">
        <div class="stat-card total">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/>
                    <path d="M2 17h20"/><path d="M6 8v9"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalRooms; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
        </div>

        <div class="stat-card available">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalAvailable; ?></div>
                <div class="stat-label">Available Now</div>
            </div>
        </div>

        <div class="stat-card ensuite">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12h16"/><path d="M4 12V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/>
                    <path d="M6 18v2"/><path d="M18 18v2"/>
                    <path d="M4 12v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalEnsuite; ?></div>
                <div class="stat-label">Ensuite Rooms</div>
            </div>
        </div>

        <div class="stat-card occupied">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalOccupied; ?></div>
                <div class="stat-label">Students Housed</div>
            </div>
        </div>
    </div>

    <div class="progress-card">
        <div class="progress-row">
            <span>Room availability rate</span>
            <span class="pct"><?php echo $availabilityRate; ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $availabilityRate; ?>%;"></div>
        </div>
    </div>

    <!-- ── Quick Actions ── -->
    <div class="section-title">Quick Actions</div>
    <div class="action-grid">
        <a href="add_room.php" class="action-btn add">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
            </div>
            <div class="btn-label">Add Room</div>
            <div class="btn-desc">Register a new room</div>
        </a>

        <a href="list_rooms.php" class="action-btn view">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <line x1="8" y1="8" x2="16" y2="8"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                    <line x1="8" y1="16" x2="13" y2="16"/>
                </svg>
            </div>
            <div class="btn-label">View All</div>
            <div class="btn-desc">Browse all room records</div>
        </a>

        <a href="allocate_room.php" class="action-btn allocate">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </div>
            <div class="btn-label">Allocate Room</div>
            <div class="btn-desc">Assign a student to a room</div>
        </a>

        <a href="list_rooms.php?filter=ensuite" class="action-btn ensuite">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v6"/>
                    <path d="M16 6 12 2 8 6"/>
                    <path d="M5 18a7 7 0 1 0 14 0H5z"/>
                </svg>
            </div>
            <div class="btn-label">Ensuite Rooms</div>
            <div class="btn-desc">Rooms with ensuite facility</div>
        </a>
    </div>

    <!-- ── Search & Filter ── -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <input type="text" name="search"
                       placeholder="Search by room number..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="single" <?php echo $type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo $type === 'double' ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo $type === 'triple' ? 'selected' : ''; ?>>Triple</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search !== '' || $type !== ''): ?>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="section-title">Recently Added</div>

    <div class="card">
        <div class="card-header">
            <h3><?php echo ($search !== '' || $type !== '') ? 'Search Results' : 'Recently Added Rooms'; ?></h3>
            <a href="add_room.php">+ Add New Room</a>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="no-data">
                <?php if ($search !== '' || $type !== ''): ?>
                    No rooms match your search. <a href="index.php">Clear filters</a>
                <?php else: ?>
                    No rooms registered yet. <a href="add_room.php">Add the first one</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="results-count"><?php echo count($rooms); ?> room(s) found</div>
            <table>
                <thead>
                    <tr>
                        <th>Floor</th>
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
                        <td><?php echo htmlspecialchars($room['floor'] ?? substr($room['room_number'], 0, 1)); ?></td>
                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                        <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                        <td><?php echo $room['capacity']; ?></td>
                        <td>
                            <?php
                            $occ  = (int)$room['occupants'];
                            $cap  = (int)$room['capacity'];
                            $left = $cap - $occ;
                            ?>
                            <?php if ($occ >= $cap): ?>
                                <span class="badge badge-full"><?php echo $occ; ?> / <?php echo $cap; ?></span>
                            <?php else: ?>
                                <span class="badge badge-spots"><?php echo $occ; ?> / <?php echo $cap; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($room['price_per_month'], 2); ?> kr.</td>
                        <td>
                            <?php if ($room['ensuite_facility']): ?>
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
                            <a href="allocate_room.php?room_id=<?php echo $room['room_id']; ?>" class="action-link allocate">Allocate</a>
                            <a href="delete_room.php?id=<?php echo $room['room_id']; ?>" class="action-link delete">Delete</a>
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
