<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/list_rooms.php  –  Full Room Listing with Filters
//
// Shows a searchable, filterable table of every room in the hostel.
// Rooms are grouped by floor (A = 1st, B = 2nd, etc.).
// Each row has a clickable button to expand and see who is living in that room.
// Admins can Edit, Allocate, or Delete from the Actions column.
//
// Supported URL parameters:
//   ?search=101         — search by room number
//   ?floor=B            — show only a specific floor
//   ?type=double        — show only a room type (single/double/triple)
//   ?filter=ensuite     — ensuite | available | full
// ─────────────────────────────────────────────────────────────────────────────

// NOTE: Session login check commented out — room module auth is used.
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

// Load the shared database connection.
require_once '../includes/db.php';

// ── Success message from a previous action ────────────────────────────────────
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'removed') $msg = "Student successfully removed from the room.";
}

// ── Read and sanitise filter/search values from the URL ──────────────────────
// ?? '' means "use empty string if the key doesn't exist in $_GET".
$search = trim($_GET['search'] ?? '');
$type   = $_GET['type']   ?? '';
$filter = $_GET['filter'] ?? '';   // one of: ensuite | available | full | ''
$floor  = strtoupper(trim($_GET['floor'] ?? ''));  // A–E or '' for all

// Only allow known values to prevent unexpected SQL behaviour.
$validTypes   = ['', 'single', 'double', 'triple'];
$validFilters = ['', 'ensuite', 'available', 'full'];
$validFloors  = ['', 'A', 'B', 'C', 'D', 'E'];
if (!in_array($type,   $validTypes,   true)) $type   = '';
if (!in_array($filter, $validFilters, true)) $filter = '';
if (!in_array($floor,  $validFloors,  true)) $floor  = '';

// ── Build the SQL query dynamically ──────────────────────────────────────────
// COUNT(s.student_id) AS occupants: counts how many active students are in each room.
// LEFT JOIN means rooms with zero students are still returned.
$sql = "SELECT r.*, COUNT(s.student_id) AS occupants
        FROM rooms r
        LEFT JOIN students s
            ON s.room_id = r.room_id AND s.status = 1
        WHERE 1 = 1";   // WHERE 1=1 lets us safely chain AND clauses below
$params = [];

// Append each active filter as an extra AND condition.
if ($search !== '') {
    $sql     .= " AND r.room_number LIKE ?";
    $params[] = "%$search%";   // % matches anything before/after the search term
}
if ($type !== '') {
    $sql     .= " AND r.room_type = ?";
    $params[] = $type;
}
if ($floor !== '') {
    $sql     .= " AND r.floor = ?";   // floor is a generated column from room_number
    $params[] = $floor;
}
if ($filter === 'ensuite') {
    $sql .= " AND r.ensuite_facility = 1";   // 1 = has private bathroom
}
if ($filter === 'available') {
    $sql .= " AND r.available_from <= CURDATE()";   // room is available as of today
}

// Group by room so the COUNT works correctly.
$sql .= " GROUP BY r.room_id";

// HAVING is applied AFTER grouping — needed for the 'full' filter
// which compares the counted occupants with the capacity column.
if ($filter === 'full') {
    $sql .= " HAVING occupants >= r.capacity";
}

$sql .= " ORDER BY r.room_number";   // sort alphabetically

// Run the prepared query.
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// ── Build a human-readable page heading based on active filters ───────────────
$headings = [
    ''          => 'All Rooms',
    'ensuite'   => 'Ensuite Rooms',
    'available' => 'Available Rooms',
    'full'      => 'Fully Occupied Rooms',
];
$heading = $headings[$filter];
if ($type  !== '') $heading = ucfirst($type) . ' ' . $heading;       // "Double All Rooms" → clearer
if ($floor !== '') $heading = "Floor $floor " . $heading;            // "Floor B Double All Rooms"

// Tell the navbar which link to highlight.
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
        /* ── Filter "chip" buttons (pill-shaped quick-filter links) ── */
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
        /* Highlighted (active) chip */
        .filter-chips a.active { background: #2563eb; color: white; border-color: #2563eb; }

        /* ── Expand/collapse button for occupants sub-table ── */
        .occupants-toggle {
            font-size: 12px; font-weight: 600;
            color: #7c3aed; background: #ede9fe;
            border: none; border-radius: 999px;
            padding: 3px 10px; cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .occupants-toggle:hover { background: #ddd6fe; }

        /* Hidden by default — shown when the toggle button is clicked */
        .occupants-row { display: none; }
        .occupants-row.open { display: table-row; }  /* JS adds/removes .open */

        /* Sub-table that appears when a room's occupants are expanded */
        .occupants-inner {
            background: #fafafa;
            border-top: 1px dashed #e5e7eb;
            padding: 14px 20px 14px 36px;
        }
        .occupants-inner table { width: 100%; border: none; margin: 0; }
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

        /* Red "Remove from Room" action link */
        .action-link.remove { color: #dc2626; }
    </style>
</head>
<body>

<!-- Include the shared room module navbar -->
<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2><?php echo htmlspecialchars($heading); ?></h2>
        <p>Browse and filter every room registered in HostelHub.</p>
    </div>

    <!-- Success message (e.g. "Student removed from room") -->
    <?php if ($msg): ?>
        <div class="alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- ── Floor filter chips (row 1) ── -->
    <!-- Clicking a chip reloads the page with ?floor=X set -->
    <div class="filter-chips">
        <a href="list_rooms.php"         class="<?php echo $floor==='' ? 'active' : ''; ?>">All floors</a>
        <a href="list_rooms.php?floor=A" class="<?php echo $floor==='A' ? 'active' : ''; ?>">Floor A (1st)</a>
        <a href="list_rooms.php?floor=B" class="<?php echo $floor==='B' ? 'active' : ''; ?>">Floor B (2nd)</a>
        <a href="list_rooms.php?floor=C" class="<?php echo $floor==='C' ? 'active' : ''; ?>">Floor C (3rd)</a>
        <a href="list_rooms.php?floor=D" class="<?php echo $floor==='D' ? 'active' : ''; ?>">Floor D (4th)</a>
        <a href="list_rooms.php?floor=E" class="<?php echo $floor==='E' ? 'active' : ''; ?>">Floor E (5th)</a>
    </div>

    <!-- ── Status/type filter chips (row 2) ── -->
    <!-- When a floor is already selected, we keep it in the URL by appending &floor=X -->
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

    <!-- ── Search form with dropdowns ── -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <!-- Text search: finds rooms whose number contains the typed string -->
                <input type="text" name="search"
                       placeholder="Search by room number..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <!-- Floor dropdown (mirrors the chip buttons above) -->
                <select name="floor">
                    <option value="">All floors</option>
                    <?php foreach (['A'=>'1st','B'=>'2nd','C'=>'3rd','D'=>'4th','E'=>'5th'] as $f => $ord): ?>
                        <option value="<?php echo $f; ?>" <?php echo $floor === $f ? 'selected' : ''; ?>>
                            Floor <?php echo $f; ?> (<?php echo $ord; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Room type dropdown -->
                <select name="type">
                    <option value="">All Types</option>
                    <option value="single" <?php echo $type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo $type === 'double' ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo $type === 'triple' ? 'selected' : ''; ?>>Triple</option>
                </select>

                <!-- Status filter dropdown -->
                <select name="filter">
                    <option value=""          <?php echo $filter === ''          ? 'selected' : ''; ?>>Any status</option>
                    <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>Available now</option>
                    <option value="ensuite"   <?php echo $filter === 'ensuite'   ? 'selected' : ''; ?>>Ensuite only</option>
                    <option value="full"      <?php echo $filter === 'full'      ? 'selected' : ''; ?>>Fully occupied</option>
                </select>

                <button type="submit" class="btn btn-primary">Search</button>
                <!-- Only show "Clear" if any filter is active -->
                <?php if ($search !== '' || $type !== '' || $filter !== '' || $floor !== ''): ?>
                    <a href="list_rooms.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php
    // ── Group rooms by floor so they render in separate floor sections ────────
    // $byFloor['A'] = [...rooms on floor A...]
    $byFloor = [];
    foreach ($rooms as $r) {
        // Use the generated `floor` column, or fall back to the first letter of room_number.
        $f = $r['floor'] ?? substr($r['room_number'], 0, 1);
        $byFloor[$f][] = $r;
    }
    ksort($byFloor);  // Sort floors alphabetically (A, B, C, D, E)

    // Human-readable floor names for the card headers.
    $floorNames = ['A'=>'1st Floor', 'B'=>'2nd Floor', 'C'=>'3rd Floor', 'D'=>'4th Floor', 'E'=>'5th Floor'];

    // ── Pre-load all active students with a room ──────────────────────────────
    // We do this in ONE query rather than one per room (much more efficient).
    // Then we group the results by room_id for fast lookup in the table loop.
    $occupantRows = $db->query(
        "SELECT student_id, student_number, full_name, email, room_id
         FROM students
         WHERE room_id IS NOT NULL AND status = 1
         ORDER BY full_name"
    )->fetchAll();

    // Build an array: $occupantsByRoom[room_id] = [student, student, ...]
    $occupantsByRoom = [];
    foreach ($occupantRows as $oRow) {
        $occupantsByRoom[(int)$oRow['room_id']][] = $oRow;
    }
    ?>

    <!-- Summary line showing how many rooms were found -->
    <div class="results-count" style="margin-bottom: 18px;">
        <?php echo count($rooms); ?> room(s) found across
        <?php echo count($byFloor); ?> floor(s)
        &middot; <a href="add_room.php" style="color:#2563eb; text-decoration:none; font-weight:600;">+ Add New Room</a>
    </div>

    <?php if (empty($rooms)): ?>
        <!-- No results: friendly empty-state message -->
        <div class="card">
            <div class="no-data">
                No rooms match these filters.
                <a href="list_rooms.php">Clear filters</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Render one card per floor -->
        <?php foreach ($byFloor as $f => $floorRooms):
            // Calculate floor-level totals for the card header summary line.
            $fTotal = count($floorRooms);
            $fOcc   = array_sum(array_map(fn($r) => (int)$r['occupants'], $floorRooms));  // total occupants
            $fCap   = array_sum(array_map(fn($r) => (int)$r['capacity'],  $floorRooms));  // total capacity
            $fOpen  = max(0, $fCap - $fOcc);   // open beds on this floor (never negative)
        ?>
            <div class="card">
                <div class="card-header">
                    <h3>
                        Floor <?php echo $f; ?>
                        — <?php echo htmlspecialchars($floorNames[$f] ?? ''); ?>
                    </h3>
                    <!-- Quick summary: rooms count, beds occupied/total, open beds -->
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
                            <th>Occupied</th>   <!-- expand button to see who -->
                            <th>Spots Left</th>
                            <th>Price / Month</th>
                            <th>Ensuite</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($floorRooms as $room):
                            $occ      = (int)$room['occupants'];      // number of students currently here
                            $cap      = (int)$room['capacity'];       // maximum students allowed
                            $left     = max(0, $cap - $occ);          // free beds
                            $rid      = (int)$room['room_id'];
                            $students = $occupantsByRoom[$rid] ?? []; // students in this specific room
                            $toggleId = 'occ-' . $rid;               // unique HTML id for the expand row
                        ?>
                        <!-- Main room row -->
                        <tr>
                            <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                            <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                            <td><?php echo $cap; ?></td>
                            <td>
                                <?php if ($occ > 0): ?>
                                    <!-- Clickable button: calls toggleOccupants() to show the sub-row -->
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
                                <!-- Red "Full" badge or green spots-left badge -->
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
                                <!-- Only show Allocate if there are free beds -->
                                <?php if ($left > 0): ?>
                                    <a href="allocate_room.php?room_id=<?php echo $rid; ?>" class="action-link allocate">Allocate</a>
                                <?php endif; ?>
                                <a href="delete_room.php?id=<?php echo $rid; ?>" class="action-link delete">Delete</a>
                            </td>
                        </tr>

                        <!-- Hidden expandable row: shows the list of students in this room -->
                        <?php if ($occ > 0): ?>
                        <tr id="<?php echo $toggleId; ?>" class="occupants-row">
                            <!-- colspan=9 makes this row span all columns -->
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
                                                    <!-- Remove allocation: unlinks the student from this room -->
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
    /**
     * toggleOccupants — show or hide the student sub-row for a room.
     *
     * @param {string} rowId  - the HTML id of the <tr> to show/hide
     * @param {HTMLElement} btn - the button that was clicked (so we can update its icon)
     */
    function toggleOccupants(rowId, btn) {
        const row = document.getElementById(rowId);
        if (!row) return;
        // classList.toggle returns true if the class was ADDED (row is now open)
        const isOpen = row.classList.toggle('open');
        // Swap the icon between person emoji (closed) and up-arrow (open)
        btn.textContent = isOpen
            ? btn.textContent.replace('👥', '▲')
            : btn.textContent.replace('▲', '👥');
    }
</script>

</body>
</html>
<?php $db = null; // Close the database connection ?>
