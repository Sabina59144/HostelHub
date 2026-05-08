<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

$errors  = [];
$success = "";

// Optional pre-selected room from index/list pages
$preRoomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT) ?: 0;

// ── Handle allocation submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $room_id    = filter_input(INPUT_POST, 'room_id',    FILTER_VALIDATE_INT);

    if (!$student_id) $errors[] = "Please choose a student.";
    if (!$room_id)    $errors[] = "Please choose a room.";

    if (empty($errors)) {
        // Make sure student exists and is active
        $sStmt = $db->prepare(
            "SELECT student_id, room_id, full_name, status
             FROM students WHERE student_id = ?"
        );
        $sStmt->execute([$student_id]);
        $student = $sStmt->fetch();

        if (!$student) {
            $errors[] = "That student does not exist.";
        } elseif ((int)$student['status'] !== 1) {
            $errors[] = "That student is not active and cannot be allocated.";
        }

        // Make sure room exists and has capacity
        $rStmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
        $rStmt->execute([$room_id]);
        $room = $rStmt->fetch();

        if (!$room) {
            $errors[] = "That room does not exist.";
        } else {
            // Available?
            if ($room['available_from'] > date('Y-m-d')) {
                $errors[] = "Room {$room['room_number']} is not available until {$room['available_from']}.";
            }

            // Spots left?
            $cStmt = $db->prepare(
                "SELECT COUNT(*) AS c FROM students
                 WHERE room_id = ? AND status = 1"
            );
            $cStmt->execute([$room_id]);
            $occ  = (int)$cStmt->fetch()['c'];
            $left = (int)$room['capacity'] - $occ;

            if ($left <= 0) {
                $errors[] = "Room {$room['room_number']} is already at full capacity ($occ / {$room['capacity']}).";
            }
        }

        // Already in this exact room?
        if (empty($errors) && (int)$student['room_id'] === (int)$room_id) {
            $errors[] = "{$student['full_name']} is already allocated to this room.";
        }
    }

    // ── Persist if valid ──────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Move the student into the new room (replaces any prior room)
            $db->prepare("UPDATE students SET room_id = ? WHERE student_id = ?")
               ->execute([$room_id, $student_id]);

            // Append to allocation history if the table exists
            try {
                // Close any prior open allocation rows for this student
                $db->prepare("UPDATE allocations
                              SET end_date = CURDATE()
                              WHERE student_id = ? AND end_date IS NULL")
                   ->execute([$student_id]);

                // Insert the new active allocation
                $db->prepare("INSERT INTO allocations (student_id, room_id, start_date)
                              VALUES (?, ?, CURDATE())")
                   ->execute([$student_id, $room_id]);
            } catch (PDOException $ignored) { /* table may not exist */ }

            $db->commit();
            header("Location: index.php?msg=allocated");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Could not save the allocation: " . $e->getMessage();
        }
    }

    // Re-populate the form selections on error
    $preRoomId = $room_id ?: $preRoomId;
    $preStudentId = $student_id ?: 0;
}

// ── Fetch dropdown data ───────────────────────────────────────────────
// Rooms with at least one spot left
$roomsWithCapacity = $db->query(
    "SELECT r.*, COUNT(s.student_id) AS occupants
     FROM rooms r
     LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
     GROUP BY r.room_id
     HAVING occupants < r.capacity
     ORDER BY r.room_number"
)->fetchAll();

// Active students (we still allow re-allocating those already in a room)
$students = $db->query(
    "SELECT student_id, student_number, full_name, room_id
     FROM students
     WHERE status = 1
     ORDER BY full_name"
)->fetchAll();

$preStudentId = $preStudentId ?? 0;

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
        .room-summary {
            display: none;
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 16px;
            margin-top: 14px;
        }
        .room-summary.show { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .room-summary .label { font-size: 11px; font-weight: 700; color: #6b7280;
                               text-transform: uppercase; letter-spacing: 0.5px; }
        .room-summary .value { font-size: 14px; color: #111827; font-weight: 600; margin-top: 3px; }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Allocate Room</h2>
        <p>Assign a student to a room. Rooms that are full or unavailable are hidden from the list.</p>
    </div>

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

    <?php if (empty($students)): ?>
        <div class="alert-warning">
            There are no active students to allocate yet.
            <a href="../Student module/index.php" style="color: inherit; text-decoration: underline; font-weight: 600;">
                Add a student
            </a> first.
        </div>
    <?php endif; ?>

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
                <div class="form-group">
                    <label for="student_id">Student <span class="req">*</span></label>
                    <select id="student_id" name="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?>>
                        <option value="">-- Select a student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['student_id']; ?>"
                                    <?php echo $preStudentId == $s['student_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['student_number']); ?> —
                                <?php echo htmlspecialchars($s['full_name']); ?>
                                <?php if ($s['room_id']): ?>
                                    (currently allocated)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Re-selecting a student already in a room will move them.</div>
                </div>

                <div class="form-group">
                    <label for="room_id">Room <span class="req">*</span></label>
                    <select id="room_id" name="room_id" required <?php echo empty($roomsWithCapacity) ? 'disabled' : ''; ?>>
                        <option value="">-- Select a room --</option>
                        <?php foreach ($roomsWithCapacity as $r):
                            $left = (int)$r['capacity'] - (int)$r['occupants'];
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

            <!-- Live room preview -->
            <div id="roomSummary" class="room-summary">
                <div><div class="label">Type</div>     <div class="value" id="sumType">—</div></div>
                <div><div class="label">Capacity</div> <div class="value" id="sumCap">—</div></div>
                <div><div class="label">Spots Left</div><div class="value" id="sumLeft">—</div></div>
                <div><div class="label">Price / Month</div><div class="value" id="sumPrice">—</div></div>
            </div>

            <div class="btn-row">
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
    // Live preview of room details when one is picked
    (function () {
        const sel  = document.getElementById('room_id');
        const box  = document.getElementById('roomSummary');
        const out  = {
            type:  document.getElementById('sumType'),
            cap:   document.getElementById('sumCap'),
            left:  document.getElementById('sumLeft'),
            price: document.getElementById('sumPrice'),
        };

        function update() {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { box.classList.remove('show'); return; }
            out.type.textContent  = (opt.dataset.type  || '').replace(/^./, c => c.toUpperCase());
            out.cap.textContent   = opt.dataset.occupants + ' / ' + opt.dataset.capacity;
            out.left.textContent  = opt.dataset.left + ' spot(s)';
            out.price.textContent = '£' + opt.dataset.price + ' (Ensuite: ' + opt.dataset.ensuite + ')';
            box.classList.add('show');
        }
        sel.addEventListener('change', update);
        update();
    })();
</script>

</body>
</html>
<?php $db = null; ?>
