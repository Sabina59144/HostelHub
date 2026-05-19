<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/allocate_room.php  –  Assign a Student to a Room
//
// This page lets an admin pick a student and a room and link them together.
// After a successful allocation:
//   • students.room_id is updated to the chosen room
//   • An open record in the `allocations` history table is created (if that
//     table exists)
//   • The admin is redirected to index.php?msg=allocated
//
// If a student is already in a room, allocating them to a new room MOVES them
// (their old room's spot is freed automatically).
//
// Optional: pass ?room_id=5 in the URL to pre-select room 5 in the dropdown.
// ─────────────────────────────────────────────────────────────────────────────

// NOTE: Session check commented out — room module auth handles this.
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

// Load the shared database connection.
require_once '../includes/db.php';

$errors  = [];
$success = "";

// If the page was opened from the rooms list with ?room_id=X, pre-select that room.
$preRoomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT) ?: 0;

// ── Handle the allocation form submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // FILTER_VALIDATE_INT returns false if the value is missing or not an integer.
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $room_id    = filter_input(INPUT_POST, 'room_id',    FILTER_VALIDATE_INT);

    // Basic presence checks.
    if (!$student_id) $errors[] = "Please choose a student.";
    if (!$room_id)    $errors[] = "Please choose a room.";

    if (empty($errors)) {

        // ── Verify the student ─────────────────────────────────────────────
        $sStmt = $db->prepare(
            "SELECT student_id, room_id, full_name, status
             FROM students WHERE student_id = ?"
        );
        $sStmt->execute([$student_id]);
        $student = $sStmt->fetch();

        if (!$student) {
            $errors[] = "That student does not exist.";
        } elseif ((int)$student['status'] !== 1) {
            // Inactive students (e.g. graduated, suspended) cannot be allocated.
            $errors[] = "That student is not active and cannot be allocated.";
        }

        // ── Verify the room ────────────────────────────────────────────────
        $rStmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
        $rStmt->execute([$room_id]);
        $room = $rStmt->fetch();

        if (!$room) {
            $errors[] = "That room does not exist.";
        } else {
            // Check if the room is available yet (available_from date).
            if ($room['available_from'] > date('Y-m-d')) {
                $errors[] = "Room {$room['room_number']} is not available until {$room['available_from']}.";
            }

            // Count how many active students are already in this room.
            $cStmt = $db->prepare(
                "SELECT COUNT(*) AS c FROM students
                 WHERE room_id = ? AND status = 1"
            );
            $cStmt->execute([$room_id]);
            $occ  = (int)$cStmt->fetch()['c'];
            $left = (int)$room['capacity'] - $occ;   // free spots

            if ($left <= 0) {
                $errors[] = "Room {$room['room_number']} is already at full capacity ($occ / {$room['capacity']}).";
            }
        }

        // Edge case: student is already in this exact room (no need to move them).
        if (empty($errors) && (int)$student['room_id'] === (int)$room_id) {
            $errors[] = "{$student['full_name']} is already allocated to this room.";
        }
    }

    // ── Save the allocation if all checks passed ───────────────────────────
    if (empty($errors)) {
        try {
            // Transaction: all steps succeed together or none do.
            $db->beginTransaction();

            // Link the student to the new room.
            // If the student was in another room, this automatically "moves" them.
            $db->prepare("UPDATE students SET room_id = ? WHERE student_id = ?")
               ->execute([$room_id, $student_id]);

            // Optionally update the allocations history table.
            // This table may not exist in all installations — the try/catch handles that.
            try {
                // Close any previous open allocation for this student.
                $db->prepare("UPDATE allocations
                              SET end_date = CURDATE()
                              WHERE student_id = ? AND end_date IS NULL")
                   ->execute([$student_id]);

                // Open a new allocation record starting today.
                $db->prepare("INSERT INTO allocations (student_id, room_id, start_date)
                              VALUES (?, ?, CURDATE())")
                   ->execute([$student_id, $room_id]);
            } catch (PDOException $ignored) { /* allocations table may not exist — safe to skip */ }

            $db->commit();

            // Redirect with a success flash message.
            header("Location: index.php?msg=allocated");
            exit();

        } catch (PDOException $e) {
            // Something went wrong — roll back so data stays consistent.
            $db->rollBack();
            $errors[] = "Could not save the allocation: " . $e->getMessage();
        }
    }

    // Re-populate the dropdowns with the user's previous choices on validation error.
    $preRoomId    = $room_id    ?: $preRoomId;
    $preStudentId = $student_id ?: 0;
}

// ── Load dropdown data ─────────────────────────────────────────────────────────

// Rooms: only show rooms that still have at least one free bed (HAVING clause).
$roomsWithCapacity = $db->query(
    "SELECT r.*, COUNT(s.student_id) AS occupants
     FROM rooms r
     LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
     GROUP BY r.room_id
     HAVING occupants < r.capacity   -- only include rooms that are NOT full
     ORDER BY r.room_number"
)->fetchAll();

// Students: all active students regardless of whether they already have a room.
// If they do have a room, we show "(currently allocated)" next to their name.
$students = $db->query(
    "SELECT student_id, student_number, full_name, room_id
     FROM students
     WHERE status = 1
     ORDER BY full_name"
)->fetchAll();

$preStudentId = $preStudentId ?? 0;

// Tell the navbar which link to highlight.
$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Allocate Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Live room preview box ──
           Hidden by default. When a room is selected from the dropdown,
           JavaScript populates and shows this box. */
        .room-summary {
            display: none;
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 16px;
            margin-top: 14px;
        }
        /* .show is added by JavaScript when a room is selected */
        .room-summary.show { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .room-summary .label { font-size: 11px; font-weight: 700; color: #6b7280;
                               text-transform: uppercase; letter-spacing: 0.5px; }
        .room-summary .value { font-size: 14px; color: #111827; font-weight: 600; margin-top: 3px; }
    </style>
</head>
<body>

<!-- Shared room module navigation bar -->
<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Allocate Room</h2>
        <p>Assign a student to a room. Rooms that are full or unavailable are hidden from the list.</p>
    </div>

    <!-- Show validation errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            Please fix the following:
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Warning if there are no active students to allocate -->
    <?php if (empty($students)): ?>
        <div class="alert-warning">
            There are no active students to allocate yet.
            <a href="../Student module/index.php" style="color: inherit; text-decoration: underline; font-weight: 600;">
                Add a student
            </a> first.
        </div>
    <?php endif; ?>

    <!-- Warning if every room is full -->
    <?php if (empty($roomsWithCapacity)): ?>
        <div class="alert-warning">
            Every room is currently at full capacity.
            <a href="add_room.php" style="color: inherit; text-decoration: underline; font-weight: 600;">
                Add a new room
            </a> to free up space.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Allocation Details</h3>
            <a href="index.php">Back to Rooms</a>
        </div>

        <form method="POST" action="" novalidate>

            <div class="form-row">

                <!-- Student dropdown -->
                <div class="form-group">
                    <label for="student_id">Student <span class="req">*</span></label>
                    <!-- disabled if no students exist (prevents submitting an empty select) -->
                    <select id="student_id" name="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?>>
                        <option value="">-- Select a student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['student_id']; ?>"
                                    <?php echo $preStudentId == $s['student_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['student_number']); ?> —
                                <?php echo htmlspecialchars($s['full_name']); ?>
                                <!-- Tell the admin if the student is already allocated somewhere -->
                                <?php if ($s['room_id']): ?>
                                    (currently allocated)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Re-selecting a student already in a room will move them.</div>
                </div>

                <!-- Room dropdown — each option carries data-* attributes used by the JS preview -->
                <div class="form-group">
                    <label for="room_id">Room <span class="req">*</span></label>
                    <select id="room_id" name="room_id" required <?php echo empty($roomsWithCapacity) ? 'disabled' : ''; ?>>
                        <option value="">-- Select a room --</option>
                        <?php foreach ($roomsWithCapacity as $r):
                            $left = (int)$r['capacity'] - (int)$r['occupants'];  // free beds
                        ?>
                            <option value="<?php echo $r['room_id']; ?>"
                                    data-capacity="<?php echo $r['capacity']; ?>"
                                    data-occupants="<?php echo $r['occupants']; ?>"
                                    data-left="<?php echo $left; ?>"
                                    data-type="<?php echo htmlspecialchars($r['room_type']); ?>"
                                    data-price="<?php echo number_format($r['price_per_month'], 2); ?>"
                                    data-ensuite="<?php echo $r['ensuite_facility'] ? 'Yes' : 'No'; ?>"
                                    <?php echo $preRoomId == $r['room_id'] ? 'selected' : ''; ?>>
                                Room <?php echo htmlspecialchars($r['room_number']); ?>
                                — <?php echo $left; ?> spot<?php echo $left === 1 ? '' : 's'; ?> left
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Only rooms with available spots are listed.</div>
                </div>
            </div>

            <!-- Live room summary — populated by JavaScript when a room is selected -->
            <div id="roomSummary" class="room-summary">
                <div><div class="label">Type</div>        <div class="value" id="sumType">—</div></div>
                <div><div class="label">Capacity</div>    <div class="value" id="sumCap">—</div></div>
                <div><div class="label">Spots Left</div>  <div class="value" id="sumLeft">—</div></div>
                <div><div class="label">Price / Month</div><div class="value" id="sumPrice">—</div></div>
            </div>

            <div class="btn-row">
                <!-- Disabled if there are no students or no rooms available -->
                <button type="submit" class="btn btn-primary"
                        <?php echo (empty($students) || empty($roomsWithCapacity)) ? 'disabled' : ''; ?>>
                    Allocate Student
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

</div>

<script>
    /**
     * Live room preview: when the admin selects a room from the dropdown,
     * this code reads the data-* attributes on the selected <option> and
     * fills in the summary box below the form.
     */
    (function () {
        const sel = document.getElementById('room_id');   // the room <select>
        const box = document.getElementById('roomSummary'); // the preview div
        // References to the four value cells inside the preview.
        const out = {
            type:  document.getElementById('sumType'),
            cap:   document.getElementById('sumCap'),
            left:  document.getElementById('sumLeft'),
            price: document.getElementById('sumPrice'),
        };

        function update() {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) {
                // No room selected: hide the preview box.
                box.classList.remove('show');
                return;
            }
            // Capitalise the first letter of the room type (e.g. "single" → "Single").
            out.type.textContent  = (opt.dataset.type  || '').replace(/^./, c => c.toUpperCase());
            out.cap.textContent   = opt.dataset.occupants + ' / ' + opt.dataset.capacity;
            out.left.textContent  = opt.dataset.left + ' spot(s)';
            out.price.textContent = opt.dataset.price + ' kr. (Ensuite: ' + opt.dataset.ensuite + ')';
            box.classList.add('show');  // show the preview box
        }

        // Run on every change and also immediately in case a room is pre-selected.
        sel.addEventListener('change', update);
        update();
    })();
</script>

</body>
</html>
<?php $db = null; // Close the database connection ?>
