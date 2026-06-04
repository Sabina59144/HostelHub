<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/delete_room.php  –  Delete a Room
//
// This page shows a confirmation screen before deleting a room.
// It handles two cases safely:
//   • Empty room  → deletes immediately on confirmation
//   • Occupied room → shows a warning and requires the admin to tick an
//                     extra checkbox to confirm they want to unallocate the
//                     students living there AND delete the room.
//
// Usage: delete_room.php?id=5  (loads the confirmation screen)
//        Then POST with confirm=yes to actually delete.
// ─────────────────────────────────────────────────────────────────────────────

// NOTE: Session login check is commented out — relies on room module auth.
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

// Load the database connection.
require_once '../includes/db.php';

// ── Get and validate the room_id ──────────────────────────────────────────────
// Accept from GET (when the page first loads) or POST (when the form is submitted).
$room_id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
        ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$room_id) {
    // No valid ID in the URL or form — redirect to safety.
    header("Location: index.php");
    exit();
}

// ── Load the room from the database ──────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

// If the room doesn't exist (already deleted, or wrong ID), go back.
if (!$room) {
    header("Location: index.php");
    exit();
}

// ── Check for allocated students ──────────────────────────────────────────────
// We load the list of active students in this room so we can:
//   a) Warn the admin that students are present
//   b) Show their names in the confirmation table
$alloc = $db->prepare(
    "SELECT student_id, student_number, full_name, email
     FROM students
     WHERE room_id = ? AND status = 1
     ORDER BY full_name"
);
$alloc->execute([$room_id]);
$allocated    = $alloc->fetchAll();
$hasAllocations = count($allocated) > 0;  // true if at least one student lives here

$errors = [];

// ── Handle the confirmed deletion (POST) ──────────────────────────────────────
// Only runs when the admin has submitted the confirmation form with confirm=yes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {

    // Safety gate: if students are allocated, the admin MUST also tick
    // the "force unallocate" checkbox. If they didn't, show an error.
    if ($hasAllocations && empty($_POST['force'])) {
        $errors[] = "This room still has allocated students. Tick the confirmation box to unallocate them and continue, or cancel.";
    }

    if (empty($errors)) {
        try {
            // Use a database transaction so either ALL steps succeed or NONE do.
            // This protects against partial deletes if something goes wrong mid-way.
            $db->beginTransaction();

            // Step 1: Unlink any students from this room.
            // Setting room_id = NULL removes the assignment without deleting the student records.
            $db->prepare("UPDATE students SET room_id = NULL WHERE room_id = ?")
               ->execute([$room_id]);

            // Step 2: Close any open allocation history rows for this room.
            // The `allocations` table is optional — if it doesn't exist we catch
            // the error silently and continue.
            try {
                $db->prepare("UPDATE allocations
                              SET end_date = CURDATE()
                              WHERE room_id = ? AND end_date IS NULL")
                   ->execute([$room_id]);
            } catch (PDOException $ignored) { /* allocations table may not exist — safe to skip */ }

            // Step 3: Delete the room itself.
            $db->prepare("DELETE FROM rooms WHERE room_id = ?")
               ->execute([$room_id]);

            // All steps succeeded — commit the transaction.
            $db->commit();

            // Redirect to the rooms list with a success message.
            header("Location: index.php?msg=deleted");
            exit();

        } catch (PDOException $e) {
            // Something failed — roll back so the database stays consistent.
            $db->rollBack();
            $errors[] = "Could not delete the room: " . $e->getMessage();
        }
    }
}

// Tell the navbar which link to highlight.
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
        /* Red top-border on the confirmation card to signal danger */
        .danger-card { border-top: 4px solid #ef4444; }
        .alloc-table { margin-top: 6px; }
    </style>
</head>
<body>

<!-- Include the room module navbar -->
<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Delete Room</h2>
        <p>Review the details before permanently removing room
           <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>.</p>
    </div>

    <!-- Show any errors from the deletion attempt -->
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <!-- Orange warning if students are still living in this room -->
    <?php if ($hasAllocations): ?>
        <div class="alert-warning">
            <strong>Warning:</strong> this room currently has
            <?php echo count($allocated); ?> active student allocation<?php echo count($allocated) === 1 ? '' : 's'; ?>.
            Deleting the room will unallocate
            <?php echo count($allocated) === 1 ? 'this student' : 'these students'; ?>.
        </div>
    <?php endif; ?>

    <!-- Confirmation card with red top border -->
    <div class="card danger-card">
        <div class="card-header">
            <h3>Room Details</h3>
            <a href="index.php">Back to Rooms</a>
        </div>

        <!-- Summary of the room being deleted -->
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
                <div class="value"><?php echo number_format($room['price_per_month'], 2); ?> kr.</div>
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

        <!-- If students are allocated, list them so the admin knows who will be affected -->
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

        <!-- Confirmation form — a second browser confirm() dialog is shown on submit -->
        <form method="POST" action="" style="margin-top: 22px;"
              onsubmit="return confirm('Permanently delete room <?php echo htmlspecialchars($room['room_number'], ENT_QUOTES); ?>? This cannot be undone.');">

            <!-- Hidden fields carry the room id and confirmation flag -->
            <input type="hidden" name="id"      value="<?php echo (int)$room_id; ?>">
            <input type="hidden" name="confirm" value="yes">

            <!-- Extra required checkbox — only shown if students are present -->
            <?php if ($hasAllocations): ?>
                <div class="checkbox-wrap" style="margin-bottom: 14px;">
                    <!-- required: the browser won't submit the form unless this is ticked -->
                    <input type="checkbox" id="force" name="force" value="1" required>
                    <label for="force">
                        I understand — unallocate the
                        <?php echo count($allocated); ?> student<?php echo count($allocated) === 1 ? '' : 's'; ?>
                        listed above and delete this room.
                    </label>
                </div>
            <?php endif; ?>

            <div class="btn-row">
                <!-- Red delete button + safe cancel link -->
                <button type="submit" class="btn btn-danger">Delete Room</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

</div>

</body>
</html>
<?php $db = null; // Close the database connection ?>
