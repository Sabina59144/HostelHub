<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/index.php  –  Room Module Dashboard
//
// This is the main page of the Room module. It shows:
//   1. Quick stat cards  (total rooms, available, ensuite, occupied)
//   2. An availability progress bar
//   3. Quick-action buttons (Add, View All, Allocate, Ensuite filter)
//   4. A search/filter form
//   5. A table of rooms (filtered if a search was entered)
// ─────────────────────────────────────────────────────────────────────────────

// Load the session helper (handles login checks) and the database connection.
// Both files live in the shared /includes/ folder so every module can use them.
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

// If the user is not logged in, send them to the login page and stop.
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
// Each query counts a specific group of rooms/students for the 4 stat cards.

// Total number of rooms in the hostel.
$totalRooms     = $db->query("SELECT COUNT(*) AS c FROM rooms")->fetch()['c'];

// Rooms that have at least one free bed (occupants < capacity).
// We use a subquery to count active students per room and compare to capacity.
// This is the true "available" number — rooms that can actually accept a new student.
$totalAvailable = $db->query("
    SELECT COUNT(*) AS c
    FROM rooms r
    WHERE (
        SELECT COUNT(*) FROM students s
        WHERE s.room_id = r.room_id AND s.status = 1
    ) < r.capacity
")->fetch()['c'];

// Rooms that have an ensuite (private) bathroom.
$totalEnsuite   = $db->query("SELECT COUNT(*) AS c FROM rooms WHERE ensuite_facility = 1")->fetch()['c'];

// Students who are currently assigned to a room and have an active status (status = 1).
// This is the true "students housed" count — only students with a room assigned.
$totalOccupied  = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NOT NULL AND status = 1")->fetch()['c'];

// Calculate what percentage of rooms still have free beds.
// Guard against divide-by-zero when there are no rooms yet.
$availabilityRate = $totalRooms > 0 ? round(($totalAvailable / $totalRooms) * 100) : 0;

// ── Success message from redirect ─────────────────────────────────────────────
// After an action (add/update/delete/allocate), the user is redirected back here
// with a short ?msg= code in the URL. We turn that code into a readable message.
$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')     $msg = "Room added successfully!";
    if ($_GET['msg'] === 'updated')   $msg = "Room updated successfully!";
    if ($_GET['msg'] === 'deleted')   $msg = "Room deleted successfully!";
    if ($_GET['msg'] === 'allocated') $msg = "Student allocated successfully!";
    if ($_GET['msg'] === 'removed')   $msg = "Student successfully removed from the room.";
}

// ── Search + Filter ───────────────────────────────────────────────────────────
// Read the search term and type filter from the URL (?search=...&type=...).
// trim() removes accidental spaces typed by the user.
$search = trim($_GET['search'] ?? '');
$type   = $_GET['type'] ?? '';

// Base SQL: fetch every room and count how many active students are in each.
// LEFT JOIN means rooms with zero students are still included.
$sql    = "SELECT r.*, COUNT(s.student_id) AS occupants
           FROM rooms r
           LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
           WHERE 1=1";  // WHERE 1=1 is a trick so we can safely append AND clauses below
$params = [];

// If the user typed something in the search box, add a LIKE filter on room_number.
// The % characters mean "anything before/after the search term".
if ($search !== '') {
    $sql     .= " AND r.room_number LIKE ?";
    $params[] = "%$search%";
}

// If the user picked a room type from the dropdown, filter by that type.
if ($type !== '') {
    $sql     .= " AND r.room_type = ?";
    $params[] = $type;
}

// Sort rooms alphabetically by room number and group by room_id (required by COUNT).
$sql .= " GROUP BY r.room_id ORDER BY r.room_number";

// Run the query safely using prepared statements (prevents SQL injection).
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();  // $rooms is now an array of room rows

// Tell the navbar which menu item to highlight.
$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Room Module</title>
    <!-- Main stylesheet for the room module -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Page-specific styles ── */
        /* These styles are only needed on this page, so they live here
           instead of in the shared style.css file. */

        /* 4-column grid for the stat cards */
        .stat-cards {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 18px; margin-bottom: 24px;
        }
        /* Each individual stat card */
        .stat-card {
            background: white; border-radius: 12px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
            border-top: 4px solid #ccc; /* coloured top border per card type */
            display: flex; align-items: center; gap: 16px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        /* Different top-border colours for each card */
        .stat-card.total     { border-top-color: #3b82f6; }
        .stat-card.available { border-top-color: #10b981; }
        .stat-card.ensuite   { border-top-color: #f59e0b; }
        .stat-card.occupied  { border-top-color: #ef4444; }
        /* Square icon box inside each card */
        .stat-icon-box {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        /* Background + icon colour per card type */
        .stat-card.total     .stat-icon-box { background: #dbeafe; color: #2563eb; }
        .stat-card.available .stat-icon-box { background: #d1fae5; color: #059669; }
        .stat-card.ensuite   .stat-icon-box { background: #fef3c7; color: #d97706; }
        .stat-card.occupied  .stat-icon-box { background: #fee2e2; color: #dc2626; }
        .stat-text { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
        .stat-number { font-size: 32px; font-weight: 700; color: #111827; line-height: 1; }
        .stat-label  { font-size: 14px; color: #6b7280; font-weight: 500; }

        /* Availability progress bar */
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

        /* 4-column grid for the quick-action buttons */
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
        /* Icon colours per button */
        .action-btn.add      .btn-icon { background: #ede9fe; color: #7c3aed; }
        .action-btn.view     .btn-icon { background: #fef3c7; color: #d97706; }
        .action-btn.allocate .btn-icon { background: #d1fae5; color: #059669; }
        .action-btn.ensuite  .btn-icon { background: #dbeafe; color: #2563eb; }
        .action-btn .btn-label { font-size: 16px; font-weight: 700; color: #111827; }
        .action-btn .btn-desc  { font-size: 13px; color: #6b7280; text-align: center; }

        /* On smaller screens, collapse 4 columns to 2 */
        @media (max-width: 1000px) {
            .stat-cards, .action-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- Include the shared navigation bar from _navbar.php -->
<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Room </h2>
        <p>Manage hostel rooms — add, view, edit, and search rooms</p>
    </div>

    <!-- Show a success message if one was passed in the URL -->
    <?php if ($msg): ?>
        <div class="alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <!-- Each card shows one number pulled from the database queries above -->
    <div class="stat-cards">

        <!-- Card 1: Total rooms -->
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

        <!-- Card 2: Rooms available today -->
        <div class="stat-card available">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalAvailable; ?></div>
                <div class="stat-label">Rooms with Free Beds</div>
            </div>
        </div>

        <!-- Card 3: Rooms with ensuite bathroom -->
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

        <!-- Card 4: Students currently living in a room -->
        <div class="stat-card occupied">
            <div class="stat-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="stat-text">
                <div class="stat-number"><?php echo $totalOccupied; ?></div>
                <div class="stat-label">Rooms Assigned</div>
            </div>
        </div>
    </div>

    <!-- ── Availability Progress Bar ── -->
    <!-- The bar width is set inline using the PHP-calculated percentage -->
    <div class="progress-card">
        <div class="progress-row">
            <span>Rooms with free beds</span>
            <span class="pct"><?php echo $availabilityRate; ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $availabilityRate; ?>%;"></div>
        </div>
    </div>

    <!-- ── Quick Action Buttons ── -->
    <!-- Four shortcut links to the most common tasks -->
    <div class="section-title">Quick Actions</div>
    <div class="action-grid">

        <!-- Add a new room -->
        <a href="add_room.php" class="action-btn add">
            <div class="btn-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
            </div>
            <div class="btn-label">Add Room</div>
            <div class="btn-desc">Register a new room</div>
        </a>

        <!-- View the full room list -->
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

        <!-- Go to the allocate-room page -->
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

        <!-- View only ensuite rooms (passes ?filter=ensuite to list_rooms.php) -->
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

    <!-- ── Search & Filter Form ── -->
    <!-- GET method so the filter values stay visible in the URL (easy to bookmark/share) -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <!-- Text search: matches any part of the room number -->
                <input type="text" name="search"
                       placeholder="Search by room number..."
                       value="<?php echo htmlspecialchars($search); ?>"><!-- htmlspecialchars prevents XSS -->

                <!-- Dropdown to filter by room type -->
                <select name="type">
                    <option value="">All Types</option>
                    <!-- "selected" attribute is added if this option matches the current filter -->
                    <option value="single" <?php echo $type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo $type === 'double' ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo $type === 'triple' ? 'selected' : ''; ?>>Triple</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>

                <!-- Show a "Clear" link only when a filter is active -->
                <?php if ($search !== '' || $type !== ''): ?>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="section-title">Recently Added</div>

    <!-- ── Rooms Table ── -->
    <div class="card">
        <div class="card-header">
            <!-- Change the heading depending on whether the user is searching or not -->
            <h3><?php echo ($search !== '' || $type !== '') ? 'Search Results' : 'Recently Added Rooms'; ?></h3>
            <a href="add_room.php">+ Add New Room</a>
        </div>

        <?php if (empty($rooms)): ?>
            <!-- No results: show a friendly message with a hint -->
            <div class="no-data">
                <?php if ($search !== '' || $type !== ''): ?>
                    No rooms match your search. <a href="index.php">Clear filters</a>
                <?php else: ?>
                    No rooms registered yet. <a href="add_room.php">Add the first one</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Show how many rooms were found -->
            <div class="results-count"><?php echo count($rooms); ?> room(s) found</div>
            <table>
                <thead>
                    <tr>
                        <th>Floor</th>
                        <th>Room No.</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Occupied</th>    <!-- how many students / total capacity -->
                        <th>Price / Month</th>
                        <th>Ensuite</th>
                        <th>Status</th>      <!-- Available or upcoming date -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <!-- Floor is a generated column (first letter of room_number). Fallback just in case. -->
                        <td><?php echo htmlspecialchars($room['floor'] ?? substr($room['room_number'], 0, 1)); ?></td>
                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                        <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                        <td><?php echo $room['capacity']; ?></td>
                        <td>
                            <?php
                            // Calculate how many beds are free
                            $occ  = (int)$room['occupants'];   // current occupants (from COUNT in SQL)
                            $cap  = (int)$room['capacity'];    // max capacity
                            $left = $cap - $occ;               // free spots (not displayed but useful)
                            ?>
                            <!-- Show a red badge if full, green badge if space available -->
                            <?php if ($occ >= $cap): ?>
                                <span class="badge badge-full"><?php echo $occ; ?> / <?php echo $cap; ?></span>
                            <?php else: ?>
                                <span class="badge badge-spots"><?php echo $occ; ?> / <?php echo $cap; ?></span>
                            <?php endif; ?>
                        </td>
                        <!-- number_format adds 2 decimal places, e.g. 1200.00 -->
                        <td><?php echo number_format($room['price_per_month'], 2); ?> kr.</td>
                        <td>
                            <!-- Green "Yes" badge for ensuite rooms, grey "No" for shared -->
                            <?php if ($room['ensuite_facility']): ?>
                                <span class="badge badge-ensuite">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-shared">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- If available_from is today or earlier the room is available now -->
                            <?php if ($room['available_from'] <= date('Y-m-d')): ?>
                                <span class="badge badge-available">Available</span>
                            <?php else: ?>
                                <!-- Show the future date when the room becomes available -->
                                <span class="badge badge-soon">From <?php echo $room['available_from']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Action links: each passes the room_id in the URL so the target page knows which room -->
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

</div><!-- end .container -->

</body>
</html>
<?php $db = null; // Close the database connection cleanly ?>
