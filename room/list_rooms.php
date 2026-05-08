<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Success message ───────────────────────────────────────────────────
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'removed') $msg = "Student successfully removed from the room.";
}

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

        /* Occupants toggle & sub-table */
        .occupants-toggle {
            font-size: 12px; font-weight: 600;
            color: #7c3aed; background: #ede9fe;
            border: none; border-radius: 999px;
            padding: 3px 10px; cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .occupants-toggle:hover { background: #ddd6fe; }

        .occupants-row { display: none; }
        .occupants-row.open { display: table-row; }

        .occupants-inner {
            background: #fafafa;
            border-top: 1px dashed #e5e7eb;
            padding: 14px 20px 14px 36px;
        }
        .occupants-inner table {
            width: 100%; border: none;
            margin: 0;
        }
        .occupants-inner table th {
            font-size: 11px; font-weight: 700;
            color: #9ca3af; text-transform: uppercase;
            letter-spacing: 0.5px; padding: 6px 12px;
            background: transparent; border-bottom: 1px solid #e5e7eb;
        }
        .occupants-inner table td {
            font-size: 13px; color: #374151;
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .occupants-inner table tr:last-child td { border-bottom: none; }
        .action-link.remove { color: #dc2626; }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2><?php echo htmlspecialchars($heading); ?></h2>
        <p>Browse and filter every room registered in HostelHub.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

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

    <?php
    // Group rooms by floor so each floor renders as its own section
    $byFloor = [];
    foreach ($rooms as $r) {
        $f = $r['floor'] ?? substr($r['room_number'], 0, 1);
        $byFloor[$f][] = $r;
    }
    ksort($byFloor);

    $floorNames = ['A'=>'1st Floor', 'B'=>'2nd Floor', 'C'=>'3rd Floor', 'D'=>'4th Floor', 'E'=>'5th Floor'];

    // Fetch all currently allocated active students (keyed by room_id)
    $occupantRows = $db->query(
        "SELECT student_id, student_number, full_name, email, room_id
         FROM students
         WHERE room_id IS NOT NULL AND status = 1
         ORDER BY full_name"
    )->fetchAll();

    $occupantsByRoom = [];
    foreach ($occupantRows as $oRow) {
        $occupantsByRoom[(int)$oRow['room_id']][] = $oRow;
    }
    ?>

    <div class="results-count" style="margin-bottom: 18px;">
        <?php echo count($rooms); ?> room(s) found across
        <?php echo count($byFloor); ?> floor(s)
        &middot; <a href="add_room.php" style="color:#2563eb; text-decoration:none; font-weight:600;">+ Add New Room</a>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="card">
            <div class="no-data">
                No rooms match these filters.
                <a href="list_rooms.php">Clear filters</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($byFloor as $f => $floorRooms):
            // Floor-level summary
            $fTotal = count($floorRooms);
            $fOcc   = array_sum(array_map(fn($r) => (int)$r['occupants'], $floorRooms));
            $fCap   = array_sum(array_map(fn($r) => (int)$r['capacity'],  $floorRooms));
            $fOpen  = max(0, $fCap - $fOcc);
        ?>
            <div class="card">
                <div class="card-header">
                    <h3>
                        Floor <?php echo $f; ?>
                        — <?php echo htmlspecialchars($floorNames[$f] ?? ''); ?>
                    </h3>
                    <span style="font-size:13px; color:#6b7280;">
                        <?php echo $fTotal; ?> rooms ·
                        <?php echo $fOcc; ?> / <?php echo $fCap; ?> beds occupied ·
                        <strong style="color:#059669;"><?php echo $fOpen; ?> open</strong>
                    </span>
                </div>

                <table>
                    <thead>
                        <tr>
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
                        <?php foreach ($floorRooms as $room):
                            $occ      = (int)$room['occupants'];
                            $cap      = (int)$room['capacity'];
                            $left     = max(0, $cap - $occ);
                            $rid      = (int)$room['room_id'];
                            $students = $occupantsByRoom[$rid] ?? [];
                            $toggleId = 'occ-' . $rid;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                            <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                            <td><?php echo $cap; ?></td>
                            <td>
                                <?php if ($occ > 0): ?>
                                    <button class="occupants-toggle"
                                            onclick="toggleOccupants('<?php echo $toggleId; ?>', this)"
                                            title="View students in this room">
                                        👥 <?php echo $occ; ?> student<?php echo $occ === 1 ? '' : 's'; ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color:#9ca3af; font-size:13px;">—</span>
                                <?php endif; ?>
                            </td>
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
                                <a href="edit_room.php?id=<?php echo $rid; ?>" class="action-link edit">Edit</a>
                                <?php if ($left > 0): ?>
                                    <a href="allocate_room.php?room_id=<?php echo $rid; ?>" class="action-link allocate">Allocate</a>
                                <?php endif; ?>
                                <a href="delete_room.php?id=<?php echo $rid; ?>" class="action-link delete">Delete</a>
                            </td>
                        </tr>

                        <?php if ($occ > 0): ?>
                        <tr id="<?php echo $toggleId; ?>" class="occupants-row">
                            <td colspan="9" style="padding: 0;">
                                <div class="occupants-inner">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Student #</th>
                                                <th>Full Name</th>
                                                <th>Email</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $st): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($st['student_number']); ?></td>
                                                <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($st['email']); ?></td>
                                                <td>
                                                    <a href="remove_allocation.php?student_id=<?php echo (int)$st['student_id']; ?>"
                                                       class="action-link remove">
                                                        Remove from Room
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
    function toggleOccupants(rowId, btn) {
        const row = document.getElementById(rowId);
        if (!row) return;
        const isOpen = row.classList.toggle('open');
        btn.textContent = isOpen
            ? btn.textContent.replace('👥', '▲')
            : btn.textContent.replace('▲', '👥');
    }
</script>

</body>
</html>
<?php $db = null; ?>
