<?php
// ── Start session and check login ─────────────────────────────
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ── Connect to database ───────────────────────────────────────
require_once '../includes/db.php';

// ── Initialise variables ──────────────────────────────────────
$errors          = [];
$success         = "";
$room_number     = $room_type = $available_from = "";
$capacity        = $price_per_month = "";
$ensuite_facility = 0;

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Get and sanitise inputs ───────────────────────────────
    $room_number      = trim($_POST['room_number']);
    $room_type        = trim($_POST['room_type']);
    $capacity         = trim($_POST['capacity']);
    $price_per_month  = trim($_POST['price_per_month']);
    $available_from   = trim($_POST['available_from']);
    $ensuite_facility = isset($_POST['ensuite_facility']) ? 1 : 0;

    // ── Validation ────────────────────────────────────────────

    // Room number — required
    if (empty($room_number)) {
        $errors[] = "Room number is required.";
    }

    // Room type — must be a valid option
    $validTypes = ['single', 'double', 'triple'];
    if (empty($room_type) || !in_array($room_type, $validTypes)) {
        $errors[] = "Please select a valid room type.";
    }

    // Capacity — required, must be a positive integer
    if ($capacity === '') {
        $errors[] = "Capacity is required.";
    } elseif (!ctype_digit($capacity) || (int)$capacity < 1) {
        $errors[] = "Capacity must be a whole number greater than 0.";
    }

    // Price — required, must be a valid positive number
    if ($price_per_month === '') {
        $errors[] = "Price per month is required.";
    } elseif (!is_numeric($price_per_month) || (float)$price_per_month < 0) {
        $errors[] = "Price per month must be a valid positive number.";
    }

    // Available from — required, must be a valid date
    if (empty($available_from)) {
        $errors[] = "Available from date is required.";
    } elseif (!strtotime($available_from)) {
        $errors[] = "Please enter a valid date for available from.";
    }

    // Check room number is unique
    if (empty($errors)) {
        $checkRoom = $db->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
        $checkRoom->execute([$room_number]);
        if ($checkRoom->rowCount() > 0) {
            $errors[] = "This room number already exists. Please use a unique room number.";
        }
    }

    // ── Insert into database if no errors ─────────────────────
    if (empty($errors)) {
        $stmt = $db->prepare(
            "INSERT INTO rooms (room_number, room_type, capacity, price_per_month, ensuite_facility, available_from)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($stmt->execute([$room_number, $room_type, (int)$capacity, (float)$price_per_month, $ensuite_facility, $available_from])) {
            header("Location: index.php?msg=added");
            exit();
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Add Room</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }

        /* ── Navbar ── */
        .navbar {
            background: #0665d1; color: white;
            padding: 14px 30px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a  { color: white; text-decoration: none; margin-left: 20px; font-size: 13px; }
        .navbar a:hover { text-decoration: underline; }

        /* ── Container ── */
        .container { padding: 30px; max-width: 700px; margin: 0 auto; }

        /* ── Page title ── */
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 22px; color: #B71C1C; }
        .page-title p  { font-size: 13px; color: #666; margin-top: 4px; }

        /* ── Alerts ── */
        .alert-error {
            background: #ffebee; border: 1px solid #ef9a9a;
            color: #c62828; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;
        }
        .alert-error ul { padding-left: 18px; margin-top: 6px; }

        /* ── Form card ── */
        .form-card {
            background: white; border-radius: 10px;
            padding: 28px 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* ── Form row (two columns) ── */
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }

        /* ── Form fields ── */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-size: 13px;
            font-weight: 600; margin-bottom: 6px; color: #444;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: 10px 12px;
            border: 1px solid #ddd; border-radius: 6px;
            font-size: 14px; font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #B71C1C;
        }
        .form-group .hint {
            font-size: 11px; color: #999; margin-top: 4px;
        }

        /* ── Checkbox ── */
        .checkbox-group {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border: 1px solid #ddd;
            border-radius: 6px; background: #fafafa;
        }
        .checkbox-group input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #B71C1C; cursor: pointer;
        }
        .checkbox-group label {
            font-size: 14px; color: #444;
            margin: 0; font-weight: normal; cursor: pointer;
        }

        /* ── Buttons ── */
        .btn-row { display: flex; gap: 12px; margin-top: 24px; }
        .btn-submit {
            background: #B71C1C; color: white;
            border: none; padding: 11px 28px;
            border-radius: 6px; font-size: 14px;
            cursor: pointer; font-weight: 600;
        }
        .btn-submit:hover { background: #8B0000; }
        .btn-back {
            background: white; color: #555;
            border: 1px solid #ccc; padding: 11px 28px;
            border-radius: 6px; font-size: 14px;
            text-decoration: none; font-weight: 600;
        }
        .btn-back:hover { background: #f5f5f5; }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <img src="../logo.png" alt="HostelHub Logo"
         height="100px"
         style="vertical-align: middle; margin-right: 8px; margin-top: -20px; margin-bottom: -20px;">
    <div>
        <span style="font-size:13px; opacity:0.85;">Logged in as:
            <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </span>
        <a href="index.php">🛏️ Rooms</a>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <div class="page-title">
        <h2>➕ Add New Room</h2>
        <p>Fill in the form below to register a new room into the system</p>
    </div>

    <!-- Error messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            ⚠️ Please fix the following errors:
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add Room Form -->
    <div class="form-card">
        <form method="POST" action="">

            <!-- Room Number + Room Type -->
            <div class="form-row">
                <div class="form-group">
                    <label for="room_number">Room Number *</label>
                    <input type="text" id="room_number" name="room_number"
                           value="<?php echo htmlspecialchars($room_number); ?>"
                           placeholder="e.g. 101A">
                    <div class="hint">Must be unique across all rooms</div>
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type">
                        <option value="">-- Select type --</option>
                        <option value="single"  <?php echo $room_type === 'single'  ? 'selected' : ''; ?>>Single</option>
                        <option value="double"  <?php echo $room_type === 'double'  ? 'selected' : ''; ?>>Double</option>
                        <option value="triple"  <?php echo $room_type === 'triple'  ? 'selected' : ''; ?>>Triple</option>
                    </select>
                </div>
            </div>

            <!-- Capacity + Price -->
            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity *</label>
                    <input type="number" id="capacity" name="capacity"
                           value="<?php echo htmlspecialchars($capacity); ?>"
                           placeholder="e.g. 1" min="1" max="10">
                    <div class="hint">Number of students this room can hold</div>
                </div>
                <div class="form-group">
                    <label for="price_per_month">Price per Month (£) *</label>
                    <input type="number" id="price_per_month" name="price_per_month"
                           value="<?php echo htmlspecialchars($price_per_month); ?>"
                           placeholder="e.g. 450.00" step="0.01" min="0">
                </div>
            </div>

            <!-- Available From -->
            <div class="form-group">
                <label for="available_from">Available From *</label>
                <input type="date" id="available_from" name="available_from"
                       value="<?php echo htmlspecialchars($available_from); ?>">
                <div class="hint">Date from which the room is available for assignment</div>
            </div>

            <!-- Ensuite Facility -->
            <div class="form-group">
                <label>Ensuite Facility</label>
                <div class="checkbox-group">
                    <input type="checkbox" id="ensuite_facility" name="ensuite_facility"
                           <?php echo $ensuite_facility ? 'checked' : ''; ?>>
                    <label for="ensuite_facility">This room has an ensuite bathroom</label>
                </div>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <button type="submit" class="btn-submit">➕ Add Room</button>
                <a href="index.php" class="btn-back">← Back</a>
            </div>

        </form>
    </div>

</div><!-- end container -->

</body>
</html>
<?php $db = null; ?>