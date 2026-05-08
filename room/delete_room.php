<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Validate room id ──────────────────────────────────────────────────
$room_id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
        ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$room_id) {
    header("Location: index.php");
    exit();
}

// ── Load room ─────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    header("Location: index.php");
    exit();
}

// ── Allocated students (active) ───────────────────────────────────────
$alloc = $db->prepare(
    "SELECT student_id, student_number, full_name, email
     FROM students
     WHERE room_id = ? AND status = 1
     ORDER BY full_name"
);
$alloc->execute([$room_id]);
$allocated = $alloc->fetchAll();
$hasAllocations = count($allocated) > 0;

$errors = [];

// ── Handle confirmed deletion (POST) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {

    // Safety: block deletion if students are still allocated and the
    // user did not also tick the "force unallocate" box.
    if ($hasAllocations && empty($_POST['force'])) {
        $errors[] = "This room still has allocated students. Tick the confirmation box to unallocate them and continue, or cancel.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Unallocate any students linked to this room (foreign key
            // is ON DELETE SET NULL, but we close any open allocation
            // history rows here too for cleanliness).
            $db->prepare("UPDATE students SET room_id = NULL WHERE room_id = ?")
               ->execute([$room_id]);

            // If the optional `allocations` history table exists, close
            // its open rows for this room.
            try {
                $db->prepare("UPDATE allocations
                              SET end_date = CURDATE()
                              WHERE room_id = ? AND end_date IS NULL")
                   ->execute([$room_id]);
            } catch (PDOException $ignored) { /* table may not exist */ }

            // Delete the room
            $db->prepare("DELETE FROM rooms WHERE room_id = ?")
               ->execute([$room_id]);

            $db->commit();
            header("Location: index.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Could not delete the room: " . $e->getMessage();
        }
    }
}

$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Delete Room <?php echo htmlspecialchars($room['room_number']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .danger-card { border-top: 4px solid #ef4444; }
        .alloc-table { margin-top: 6px; }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Delete Room</h2>
        <p>Review the details before permanently removing room
           <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($hasAllocations): ?>
        <div class="alert-warning">
            <strong>Warning:</strong> this room currently has
            <?php echo count($allocated); ?> active student allocation<?php echo count($allocated) === 1 ? '' : 's'; ?>.
            Deleting the room will unallocate
            <?php echo count($allocated) === 1 ? 'this student' : 'these students'; ?>.
        </div>
    <?php endif; ?>

    <div class="card danger-card">
        <div class="card-header">
            <h3>Room Details</h3>
            <a href="index.php">Back to Rooms</a>
        </div>

        <div class="info-list">
            <div>
                <div class="label">Room Number</div>
                <div class="value"><?php echo htmlspecialchars($room['room_number']); ?></div>
            </div>
            <div>
                <div class="label">Room Type</div>
                <div class="value"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></div>
            </div>
            <div>
                <div class="label">Capacity</div>
                <div class="value"><?php echo (int)$room['capacity']; ?> person(s)</div>
            </div>
            <div>
                <div class="label">Price / Month</div>
                <div class="value">£<?php echo number_format($room['price_per_month'], 2); ?></div>
            </div>
            <div>
                <div class="label">Ensuite</div>
                <div class="value"><?php echo $room['ensuite_facility'] ? 'Yes' : 'No'; ?></div>
            </div>
            <div>
                <div class="label">Available From</div>
                <div class="value"><?php echo htmlspecialchars($room['available_from']); ?></div>
            </div>
        </div>

        <?php if ($hasAllocations): ?>
            <div class="section-title">Currently Allocated Students</div>
            <table class="alloc-table">
                <thead>
                    <tr>
                        <th>Student #</th>
                        <th>Name</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocated as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['student_number']); ?></td>
                        <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="POST" action="" style="margin-top: 22px;"
              onsubmit="return confirm('Permanently delete room <?php echo htmlspecialchars($room['room_number'], ENT_QUOTES); ?>? This cannot be undone.');">
            <input type="hidden" name="id"      value="<?php echo (int)$room_id; ?>">
            <input type="hidden" name="confirm" value="yes">

            <?php if ($hasAllocations): ?>
                <div class="checkbox-wrap" style="margin-bottom: 14px;">
                    <input type="checkbox" id="force" name="force" value="1" required>
                    <label for="force">
                        I understand — unallocate the
                        <?php echo count($allocated); ?> student<?php echo count($allocated) === 1 ? '' : 's'; ?>
                        listed above and delete this room.
                    </label>
                </div>
            <?php endif; ?>

            <div class="btn-row">
                <button type="submit" class="btn btn-danger">Delete Room</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

</div>

</body>
</html>
<?php $db = null; ?>
