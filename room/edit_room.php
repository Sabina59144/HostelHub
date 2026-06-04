<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/edit_room.php  –  Edit an Existing Room
//
// This page does two things:
//   GET  request  → loads the room from the database and shows a pre-filled form
//   POST request  → validates the submitted form and saves the changes
//
// Usage: edit_room.php?id=5  (where 5 is the room_id to edit)
// ─────────────────────────────────────────────────────────────────────────────

// NOTE: The session login check is commented out because the page relies on the
// shared room module authentication. Uncomment when needed.
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

// Load the shared database connection.
require_once '../includes/db.php';

// ── Validate the room_id from the URL ─────────────────────────────────────────
// FILTER_VALIDATE_INT makes sure the id is a real integer (not a random string).
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$room_id) {
    // No valid id? Redirect to the rooms list and stop.
    header("Location: index.php");
    exit();
}

// ── Load the existing room from the database ──────────────────────────────────
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

// If the room doesn't exist (maybe it was deleted already), redirect away.
if (!$room) {
    header("Location: index.php?msg=notfound");
    exit();
}

// ── Count current occupants ────────────────────────────────────────────────────
// This is used to prevent reducing capacity below the number of students
// who are already living in this room (status = 1 means active student).
$occStmt = $db->prepare(
    "SELECT COUNT(*) AS c FROM students
     WHERE room_id = ? AND status = 1"
);
$occStmt->execute([$room_id]);
$currentOccupants = (int)$occStmt->fetch()['c'];

// ── Pre-fill form variables from the database row ─────────────────────────────
// These are used as the default values in the HTML form fields.
$errors          = [];
$room_number     = $room['room_number'];
$room_type       = $room['room_type'];
$capacity        = $room['capacity'];
$price_per_month = $room['price_per_month'];
$available_from  = $room['available_from'];
$ensuite_facility = (int)$room['ensuite_facility'];

// ── Handle the form submission (user clicked "Save Changes") ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read the submitted values and clean up whitespace.
    $room_number      = trim($_POST['room_number']      ?? '');
    $room_type        = trim($_POST['room_type']        ?? '');
    $capacity         = trim($_POST['capacity']         ?? '');
    $price_per_month  = trim($_POST['price_per_month']  ?? '');
    $available_from   = trim($_POST['available_from']   ?? '');
    $ensuite_facility = isset($_POST['ensuite_facility']) ? 1 : 0;

    // ── Validation ─────────────────────────────────────────────────────────
    // Room number: required AND must match the pattern A01 – E20.
    // A–E = floors 1 to 5; 01–20 = room number within the floor.
    if ($room_number === '') {
        $errors[] = "Room number is required.";
    } elseif (!preg_match('/^[A-E](0[1-9]|1[0-9]|20)$/', strtoupper($room_number))) {
        $errors[] = "Room number must be in the format A01–E20 (floor letter A–E, then 01–20).";
    } else {
        // Normalise to uppercase so A01 and a01 are treated the same.
        $room_number = strtoupper($room_number);
    }

    // Room type: must be one of the three allowed enum values.
    $validTypes = ['single', 'double', 'triple'];
    if (!in_array($room_type, $validTypes, true)) {
        $errors[] = "Please select a valid room type.";
    }

    // Capacity: positive integer between 1 and 10.
    // Also cannot be reduced below the current number of occupants.
    if ($capacity === '' || !ctype_digit((string)$capacity)) {
        $errors[] = "Capacity must be a whole number.";
    } elseif ((int)$capacity < 1 || (int)$capacity > 10) {
        $errors[] = "Capacity must be between 1 and 10.";
    } elseif ((int)$capacity < $currentOccupants) {
        // Protect occupied rooms: don't allow shrinking capacity below occupancy.
        $errors[] = "Capacity ($capacity) cannot be lower than the current number of occupants ($currentOccupants). Move students out first.";
    }

    // Price: must be a valid non-negative number.
    if ($price_per_month === '' || !is_numeric($price_per_month)) {
        $errors[] = "Price per month must be a valid number.";
    } elseif ((float)$price_per_month < 0) {
        $errors[] = "Price per month cannot be negative.";
    }

    // Available date: must be present and parseable by PHP.
    if ($available_from === '' || !strtotime($available_from)) {
        $errors[] = "Please enter a valid 'available from' date.";
    }

    // Uniqueness check: no other room (not this one) can have the same room_number.
    // We exclude the current room's own ID using "room_id <> ?".
    if (empty($errors)) {
        $check = $db->prepare(
            "SELECT room_id FROM rooms WHERE room_number = ? AND room_id <> ?"
        );
        $check->execute([$room_number, $room_id]);
        if ($check->rowCount() > 0) {
            $errors[] = "Another room is already using that room number.";
        }
    }

    // ── Save changes to the database (only if all checks passed) ───────────
    if (empty($errors)) {
        $upd = $db->prepare(
            "UPDATE rooms
             SET room_number      = ?,
                 room_type        = ?,
                 capacity         = ?,
                 price_per_month  = ?,
                 ensuite_facility = ?,
                 available_from   = ?
             WHERE room_id = ?"
        );

        $ok = $upd->execute([
            $room_number,
            $room_type,
            (int)$capacity,
            (float)$price_per_month,
            $ensuite_facility,
            $available_from,
            $room_id   // the WHERE clause — ensures we update the correct room
        ]);

        if ($ok) {
            // Redirect with a success code so the index page shows a message.
            header("Location: index.php?msg=updated");
            exit();
        } else {
            $errors[] = "Could not save the changes. Please try again.";
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
    <title>HostelHub — Edit Room <?php echo htmlspecialchars($room['room_number']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Include the room module navigation bar -->
<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Edit Room</h2>
        <p>Update the details for room <strong><?php echo htmlspecialchars($room['room_number']); ?></strong></p>
    </div>

    <!-- Show all validation errors if any were found -->
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

    <!-- Warning banner: tells the admin not to reduce capacity below current occupancy -->
    <?php if ($currentOccupants > 0): ?>
        <div class="alert-warning">
            This room currently has <strong><?php echo $currentOccupants; ?></strong>
            allocated student<?php echo $currentOccupants === 1 ? '' : 's'; ?>.
            Capacity cannot be reduced below this number.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Room Details</h3>
            <a href="index.php">Back to Rooms</a>
        </div>

        <!-- novalidate: we rely on PHP-side validation, not browser's built-in -->
        <form method="POST" action="" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="room_number">Room Number <span class="req">*</span></label>
                    <!-- htmlspecialchars() prevents XSS if the room number contains special characters -->
                    <input type="text" id="room_number" name="room_number"
                           value="<?php echo htmlspecialchars($room_number); ?>"
                           pattern="[A-E](0[1-9]|1[0-9]|20)"
                           title="Floor letter A–E followed by a room number 01–20, e.g. A01 or E20"
                           maxlength="3" required>
                    <div class="hint">Format: floor letter (A–E) + room 01–20, e.g. <code>A01</code>, <code>C14</code>, <code>E20</code>. Must be unique.</div>
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type <span class="req">*</span></label>
                    <select id="room_type" name="room_type" required>
                        <!-- "selected" keeps the current choice when the form is re-shown after an error -->
                        <option value="single" <?php echo $room_type==='single' ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo $room_type==='double' ? 'selected' : ''; ?>>Double</option>
                        <option value="triple" <?php echo $room_type==='triple' ? 'selected' : ''; ?>>Triple</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity <span class="req">*</span></label>
                    <!-- min is dynamically set to the higher of 1 or the current occupant count -->
                    <input type="number" id="capacity" name="capacity"
                           value="<?php echo htmlspecialchars($capacity); ?>"
                           min="<?php echo max(1, $currentOccupants); ?>" max="10" required>
                    <div class="hint">
                        Number of students the room can hold
                        <?php if ($currentOccupants > 0): ?>
                            (currently occupied by <?php echo $currentOccupants; ?>)
                        <?php endif; ?>.
                    </div>
                </div>
                <div class="form-group">
                    <label for="price_per_month">Price per Month (kr.) <span class="req">*</span></label>
                    <input type="number" id="price_per_month" name="price_per_month"
                           value="<?php echo htmlspecialchars($price_per_month); ?>"
                           step="0.01" min="0" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="available_from">Available From <span class="req">*</span></label>
                    <input type="date" id="available_from" name="available_from"
                           value="<?php echo htmlspecialchars($available_from); ?>" required>
                </div>
                <div class="form-group">
                    <label>Ensuite Facility</label>
                    <!-- Checkbox: the "checked" attribute is added if ensuite_facility is 1 (true) -->
                    <div class="checkbox-wrap">
                        <input type="checkbox" id="ensuite_facility" name="ensuite_facility" value="1"
                               <?php echo $ensuite_facility ? 'checked' : ''; ?>>
                        <label for="ensuite_facility">This room has a private ensuite bathroom</label>
                    </div>
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

</div>

</body>
</html>
<?php $db = null; // Close the database connection ?>
