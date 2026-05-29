<?php
/**
 * Room module/add_room.php
 * ─────────────────────────────────────────────────────────────
 * Add a new room to the hostel.
 *
 * Fields: room_number (unique), room_type (single/double/triple),
 *         capacity, price_per_month, available_from date,
 *         is_ensuite (checkbox → 0 or 1)
 *
 * On success: redirects to index.php?msg=added
 * ─────────────────────────────────────────────────────────────
 */

/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin(); // Any logged-in user can add rooms
require_once '../includes/db.php';

/* ── Default empty form values ─────────────────── */
$errors = [];
$success = "";
$room_number = $room_type = $available_from = "";
$capacity = $price_per_month = "";
$is_ensuite = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ── Sanitise POST input ────────────────────── */
    $room_number     = trim($_POST['room_number']);
    $room_type       = trim($_POST['room_type']);
    $capacity        = trim($_POST['capacity']);
    $price_per_month = trim($_POST['price_per_month']);
    $available_from  = trim($_POST['available_from']);
    $is_ensuite      = isset($_POST['is_ensuite']) ? 1 : 0;

    /* ── Validation ─────────────────────────────── */
    if (empty($room_number)) $errors[] = "Room number is required.";
    $validTypes = ['single', 'double', 'triple'];
    if (empty($room_type) || !in_array($room_type, $validTypes)) $errors[] = "Please select a valid room type.";
    if ($capacity === '') $errors[] = "Capacity is required.";
    elseif (!ctype_digit($capacity) || (int)$capacity < 1) $errors[] = "Capacity must be a whole number greater than 0.";
    if ($price_per_month === '') $errors[] = "Price per month is required.";
    elseif (!is_numeric($price_per_month) || (float)$price_per_month < 0) $errors[] = "Price per month must be a valid positive number.";
    if (empty($available_from)) $errors[] = "Available from date is required.";

    /* ── Duplicate room number check ────────────── */
    if (empty($errors)) {
        $chk = $db->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
        $chk->execute([$room_number]);
        if ($chk->rowCount() > 0) $errors[] = "This room number already exists.";
    }

    /* ── Insert & redirect on success ──────────── */
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO rooms (room_number, room_type, capacity, price_per_month, is_ensuite, available_from) VALUES (?,?,?,?,?,?)");
        if ($stmt->execute([$room_number, $room_type, (int)$capacity, (float)$price_per_month, $is_ensuite, $available_from])) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:740px; margin:0 auto; }
        .page-header { margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }
        .alert-error { background:#fff1f2; border:1px solid #fda4af; color:#dc2626; padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; }
        .alert-error ul { padding-left:18px; margin-top:6px; }
        .form-card { background:#fff; border-radius:16px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:#1e293b; }
        .form-group input, .form-group select { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; color:#1e293b; background:#f8fafc; transition:border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#1a56db; box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#fff; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .hint { font-size:11px; color:#94a3b8; margin-top:5px; }
        .checkbox-wrap { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #e2e8f0; border-radius:10px; background:#f8fafc; cursor:pointer; }
        .checkbox-wrap input { width:16px; height:16px; accent-color:#1a56db; }
        .checkbox-wrap label { font-size:13px; font-weight:500; cursor:pointer; margin:0; }
        .btn-row { display:flex; gap:12px; margin-top:28px; }
        .btn-submit { background:#1a56db; color:#fff; border:none; padding:11px 28px; border-radius:10px; font-size:14px; cursor:pointer; font-weight:600; font-family:inherit; transition:background 0.2s; }
        .btn-submit:hover { background:#1341b0; }
        .btn-back { background:#fff; color:#64748b; border:1.5px solid #e2e8f0; padding:11px 28px; border-radius:10px; font-size:14px; text-decoration:none; font-weight:600; }
        .btn-back:hover { background:#f8fafc; text-decoration:none; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <h2>Add New Room</h2>
        <p>Fill in the details below to register a new room</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">⚠️ Please fix the following errors:<ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="room_number">Room Number *</label>
                    <input type="text" id="room_number" name="room_number" value="<?= htmlspecialchars($room_number) ?>" placeholder="e.g. A01">
                    <div class="hint">Must be unique (e.g. A01, B12)</div>
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type">
                        <option value="">-- Select type --</option>
                        <option value="single" <?= $room_type==='single'?'selected':'' ?>>Single</option>
                        <option value="double" <?= $room_type==='double'?'selected':'' ?>>Double</option>
                        <option value="triple" <?= $room_type==='triple'?'selected':'' ?>>Triple</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity *</label>
                    <input type="number" id="capacity" name="capacity" value="<?= htmlspecialchars($capacity) ?>" placeholder="e.g. 2" min="1" max="10">
                </div>
                <div class="form-group">
                    <label for="price_per_month">Price per Month (£) *</label>
                    <input type="number" id="price_per_month" name="price_per_month" value="<?= htmlspecialchars($price_per_month) ?>" placeholder="e.g. 450.00" step="0.01" min="0">
                </div>
            </div>
            <div class="form-group">
                <label for="available_from">Available From *</label>
                <input type="date" id="available_from" name="available_from" value="<?= htmlspecialchars($available_from) ?>">
                <div class="hint">Earliest date this room can be assigned</div>
            </div>
            <div class="form-group">
                <label>Ensuite Facility</label>
                <div class="checkbox-wrap">
                    <input type="checkbox" id="is_ensuite" name="is_ensuite" <?= $is_ensuite ? 'checked' : '' ?>>
                    <label for="is_ensuite">🚿 This room has a private ensuite bathroom</label>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn-submit">➕ Add Room</button>
                <a href="index.php" class="btn-back">← Back</a>
            </div>
        </form>
    </div>
</div>
<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
