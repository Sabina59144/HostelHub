<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Filters from query string ─────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$type   = $_GET['type']   ?? '';
$filter = $_GET['filter'] ?? '';   // 'ensuite', 'available', 'full', ''
$floor  = strtoupper(trim($_GET['floor'] ?? ''));

$validTypes   = ['', 'single', 'double', 'triple'];
$validFilters = ['', 'ensuite', 'available', 'full'];
$validFloors  = ['', 'A', 'B', 'C', 'D', 'E'];
if (!in_array($type, $validTypes, true))     $type = '';
if (!in_array($filter, $validFilters, true)) $filter = '';
if (!in_array($floor, $validFloors, true))   $floor = '';

// ── Build query ───────────────────────────────────────────────────────
$sql = "SELECT r.*, COUNT(s.student_id) AS occupants
        FROM rooms r
        LEFT JOIN students s
            ON s.room_id = r.room_id AND s.status = 1
        WHERE 1 = 1";
$params = [];

if ($search !== '') {
    $sql     .= " AND r.room_number LIKE ?";
    $params[] = "%$search%";
}
if ($type !== '') {
    $sql     .= " AND r.room_type = ?";
    $params[] = $type;
}
if ($floor !== '') {
    $sql     .= " AND r.floor = ?";
    $params[] = $floor;
}
if ($filter === 'ensuite') {
    $sql .= " AND r.ensuite_facility = 1";
}
if ($filter === 'available') {
    $sql .= " AND r.available_from <= CURDATE()";
}

$sql .= " GROUP BY r.room_id";

if ($filter === 'full') {
    $sql .= " HAVING occupants >= r.capacity";
}

$sql .= " ORDER BY r.room_number";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// Page heading varies by filter
$headings = [
    ''          => 'All Rooms',
    'ensuite'   => 'Ensuite Rooms',
    'available' => 'Available Rooms',
    'full'      => 'Fully Occupied Rooms',
];
$heading = $headings[$filter];
if ($type !== '')  $heading = ucfirst($type)            . ' ' . $heading;
if ($floor !== '') $heading = "Floor $floor "          . $heading;

$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — <?php echo htmlspecialchars($heading); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-chips {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .filter-chips a {
            text-decoration: none; font-size: 13px; font-weight: 600;
            color: #4b5563; background: white;
            padding: 8px 16px; border-radius: 999px;
            border: 1px solid #e5e7eb;
            transition: all 0.15s;
        }
        .filter-chips a:hover { border-color: #2563eb; color: #2563eb; }
        .filter-chips a.active { background: #2563eb; color: white; border-color: #2563eb; }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2><?php echo htmlspecialchars($heading); ?></h2>
        <p>Browse and filter every room registered in HostelHub.</p>
    </div>

    <!-- Floor filter chips -->
    <div class="filter-chips">
        <a href="list_rooms.php"                   class="<?php echo $floor==='' ? 'active' : ''; ?>">All floors</a>
        <a href="list_rooms.php?floor=A"           class="<?php echo $floor==='A' ? 'active' : ''; ?>">Floor A (1st)</a>
        <a href="list_rooms.php?floor=B"           class="<?php echo $floor==='B' ? 'active' : ''; ?>">Floor B (2nd)</a>
        <a href="list_rooms.php?floor=C"           class="<?php echo $floor==='C' ? 'active' : ''; ?>">Floor C (3rd)</a>
        <a href="list_rooms.php?floor=D"           class="<?php echo $floor==='D' ? 'active' : ''; ?>">Floor D (4th)</a>
        <a href="list_rooms.php?floor=E"           class="<?php echo $floor==='E' ? 'active' : ''; ?>">Floor E (5th)</a>
    </div>

    <!-- Status / type filter chips -->
    <div class="filter-chips">
        <a href="list_rooms.php<?php echo $floor ? '?floor='.$floor : ''; ?>"
           class="<?php echo $filter==='' && $type==='' ? 'active' : ''; ?>">Any</a>
        <a href="list_rooms.php?filter=available<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $filter==='available' ? 'active' : ''; ?>">Available now</a>
        <a href="list_rooms.php?filter=ensuite<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $filter==='ensuite'   ? 'active' : ''; ?>">Ensuite</a>
        <a href="list_rooms.php?filter=full<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $filter==='full'      ? 'active' : ''; ?>">Full</a>
        <a href="list_rooms.php?type=single<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $type==='single'      ? 'active' : ''; ?>">Single</a>
        <a href="list_rooms.php?type=double<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $type==='double'      ? 'active' : ''; ?>">Double</a>
        <a href="list_rooms.php?type=triple<?php echo $floor ? '&floor='.$floor : ''; ?>"
           class="<?php echo $type==='triple'      ? 'active' : ''; ?>">Triple</a>
    </div>

    <!-- Search bar -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <input type="text" name="search"
                       placeholder="Search by room number..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <select name="floor">
                    <option value="">All floors</option>
                    <?php foreach (['A'=>'1st','B'=>'2nd','C'=>'3rd','D'=>'4th','E'=>'5th'] as $f => $ord): ?>
                        <option value="<?php echo $f; ?>" <?php echo $floor === $f ? 'selected' : ''; ?>>
                            Floor <?php echo $f; ?> (<?php echo $ord; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="type">
                    <option value="">All Types</option>
                    <option value="single" <?php echo $type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo $type === 'double' ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo $type === 'triple' ? 'selected' : ''; ?>>Triple</option>
                </select>

                <select name="filter">
                    <option value=""          <?php echo $filter === ''          ? 'selected' : ''; ?>>Any status</option>
                    <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>Available now</option>
                    <option value="ensuite"   <?php echo $filter === 'ensuite'   ? 'selected' : ''; ?>>Ensuite only</option>
                    <option value="full"      <?php echo $filter === 'full'      ? 'selected' : ''; ?>>Fully occupied</option>
                </select>

                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search !== '' || $type !== '' || $filter !== '' || $floor !== ''): ?>
                    <a href="list_rooms.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><?php echo count($rooms); ?> room(s) found</h3>
            <a href="add_room.php">+ Add New Room</a>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="no-data">
                No rooms match these filters.
                <a href="list_rooms.php">Clear filters</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Floor</th>
                        <th>Room No.</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Spots Left</th>
                        <th>Price / Month</th>
                        <th>Ensuite</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room):
                        $occ  = (int)$room['occupants'];
                        $cap  = (int)$room['capacity'];
                        $left = max(0, $cap - $occ);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($room['floor'] ?? substr($room['room_number'], 0, 1)); ?></td>
                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                        <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                        <td><?php echo $cap; ?></td>
                        <td><?php echo $occ; ?></td>
                        <td>
                            <?php if ($left === 0): ?>
                                <span class="badge badge-full">Full</span>
                            <?php else: ?>
                                <span class="badge badge-spots"><?php echo $left; ?> open</span>
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
                            <?php if ($left > 0): ?>
                                <a href="allocate_room.php?room_id=<?php echo $room['room_id']; ?>" class="action-link allocate">Allocate</a>
                            <?php endif; ?>
                            <a href="delete_room.php?id=<?php echo $room['room_id']; ?>" class="action-link delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
<?php $db = null; ?>
