<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Validate room id ──────────────────────────────────────────────────
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$room_id) {
    header("Location: index.php");
    exit();
}

// ── Load existing room ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    header("Location: index.php?msg=notfound");
    exit();
}

// Count current occupants — used for capacity warnings
$occStmt = $db->prepare(
    "SELECT COUNT(*) AS c FROM students
     WHERE room_id = ? AND status = 1"
);
$occStmt->execute([$room_id]);
$currentOccupants = (int)$occStmt->fetch()['c'];

// ── Initialise form values from DB ────────────────────────────────────
$errors          = [];
$room_number     = $room['room_number'];
$room_type       = $room['room_type'];
$capacity        = $room['capacity'];
$price_per_month = $room['price_per_month'];
$available_from  = $room['available_from'];
$ensuite_facility = (int)$room['ensuite_facility'];

// ── Handle form submission ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $room_number      = trim($_POST['room_number']      ?? '');
    $room_type        = trim($_POST['room_type']        ?? '');
    $capacity         = trim($_POST['capacity']         ?? '');
    $price_per_month  = trim($_POST['price_per_month']  ?? '');
    $available_from   = trim($_POST['available_from']   ?? '');
    $ensuite_facility = isset($_POST['ensuite_facility']) ? 1 : 0;

    // Required: room number
    if ($room_number === '') {
        $errors[] = "Room number is required.";
    } elseif (strlen($room_number) > 20) {
        $errors[] = "Room number must be 20 characters or fewer.";
    }

    // Room type — must be one of the enum options
    $validTypes = ['single', 'double', 'triple'];
    if (!in_array($room_type, $validTypes, true)) {
        $errors[] = "Please select a valid room type.";
    }

    // Capacity — positive integer 1–10
    if ($capacity === '' || !ctype_digit((string)$capacity)) {
        $errors[] = "Capacity must be a whole number.";
    } elseif ((int)$capacity < 1 || (int)$capacity > 10) {
        $errors[] = "Capacity must be between 1 and 10.";
    } elseif ((int)$capacity < $currentOccupants) {
        $errors[] = "Capacity ($capacity) cannot be lower than the current number of occupants ($currentOccupants). Move students out first.";
    }

    // Price — non-negative number
    if ($price_per_month === '' || !is_numeric($price_per_month)) {
        $errors[] = "Price per month must be a valid number.";
    } elseif ((float)$price_per_month < 0) {
        $errors[] = "Price per month cannot be negative.";
    }

    // Available date — required, parsable
    if ($available_from === '' || !strtotime($available_from)) {
        $errors[] = "Please enter a valid 'available from' date.";
    }

    // Room number unique (excluding this room)
    if (empty($errors)) {
        $check = $db->prepare(
            "SELECT room_id FROM rooms WHERE room_number = ? AND room_id <> ?"
        );
        $check->execute([$room_number, $room_id]);
        if ($check->rowCount() > 0) {
            $errors[] = "Another room is already using that room number.";
        }
    }

    // ── Update on success ─────────────────────────────────────────────
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
            $room_id
        ]);

        if ($ok) {
            header("Location: index.php?msg=updated");
            exit();
        } else {
            $errors[] = "Could not save the changes. Please try again.";
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
    <title>HostelHub — Edit Room <?php echo htmlspecialchars($room['room_number']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Edit Room</h2>
        <p>Update the details for room <strong><?php echo htmlspecialchars($room['room_number']); ?></strong></p>
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

        <form method="POST" action="" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="room_number">Room Number <span class="req">*</span></label>
                    <input type="text" id="room_number" name="room_number"
                           value="<?php echo htmlspecialchars($room_number); ?>"
                           maxlength="20" required>
                    <div class="hint">Must be unique across all rooms.</div>
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type <span class="req">*</span></label>
                    <select id="room_type" name="room_type" required>
                        <option value="single" <?php echo $room_type==='single' ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo $room_type==='double' ? 'selected' : ''; ?>>Double</option>
                        <option value="triple" <?php echo $room_type==='triple' ? 'selected' : ''; ?>>Triple</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity <span class="req">*</span></label>
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
                    <label for="price_per_month">Price per Month (£) <span class="req">*</span></label>
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
<?php $db = null; ?>
