<?php
/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();
require_once '../includes/db.php';

/* ── Validate room ID from URL ──────────────────── */
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$room_id) { header("Location: index.php"); exit(); }

/* ── Fetch room or redirect if not found ────────── */
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) { header("Location: index.php"); exit(); }

/* ── Count active occupants (capacity floor guard) ── */
$occStmt = $db->prepare("SELECT COUNT(*) AS c FROM students WHERE room_id = ? AND status = 1");
$occStmt->execute([$room_id]);
$currentOccupants = (int)$occStmt->fetch()['c'];

$errors = [];
$room_number     = $room['room_number'];
$room_type       = $room['room_type'];
$capacity        = $room['capacity'];
$price_per_month = $room['price_per_month'];
$available_from  = $room['available_from'];
$is_ensuite      = (int)$room['is_ensuite'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ── Sanitise POST input ────────────────────── */
    $room_number     = trim($_POST['room_number'] ?? '');
    $room_type       = trim($_POST['room_type'] ?? '');
    $capacity        = trim($_POST['capacity'] ?? '');
    $price_per_month = trim($_POST['price_per_month'] ?? '');
    $available_from  = trim($_POST['available_from'] ?? '');
    $is_ensuite      = isset($_POST['is_ensuite']) ? 1 : 0;

    /* ── Validation (capacity cannot go below occupants) ── */
    if (empty($room_number)) $errors[] = "Room number is required.";
    $validTypes = ['single', 'double', 'triple'];
    if (!in_array($room_type, $validTypes, true)) $errors[] = "Please select a valid room type.";
    if ($capacity === '' || !ctype_digit((string)$capacity)) $errors[] = "Capacity must be a whole number.";
    elseif ((int)$capacity < $currentOccupants) $errors[] = "Capacity cannot be lower than current occupants ($currentOccupants).";
    if ($price_per_month === '' || !is_numeric($price_per_month) || (float)$price_per_month < 0) $errors[] = "Price per month must be a valid positive number.";
    if (empty($available_from)) $errors[] = "Available from date is required.";

    /* ── Duplicate room number check (exclude self) ── */
    if (empty($errors)) {
        $chk = $db->prepare("SELECT room_id FROM rooms WHERE room_number = ? AND room_id != ?");
        $chk->execute([$room_number, $room_id]);
        if ($chk->rowCount() > 0) $errors[] = "Another room is already using that room number.";
    }

    /* ── Update & redirect on success ──────────── */
    if (empty($errors)) {
        $upd = $db->prepare("UPDATE rooms SET room_number=?, room_type=?, capacity=?, price_per_month=?, is_ensuite=?, available_from=? WHERE room_id=?");
        if ($upd->execute([$room_number, $room_type, (int)$capacity, (float)$price_per_month, $is_ensuite, $available_from, $room_id])) {
            header("Location: index.php?msg=updated");
            exit();
        } else {
            $errors[] = "Could not save changes. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Edit Room</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:740px; margin:0 auto; }
        .page-header { margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }
        .alert-error   { background:#fff1f2; border:1px solid #fda4af; color:#dc2626; padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; }
        .alert-error ul { padding-left:18px; margin-top:6px; }
        .alert-warning { background:#fffbeb; border:1px solid #fcd34d; color:#d97706; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
        .form-card { background:#fff; border-radius:16px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:#1e293b; }
        .form-group input, .form-group select { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; color:#1e293b; background:#f8fafc; transition:border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#1a56db; box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#fff; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .hint { font-size:11px; color:#94a3b8; margin-top:5px; }
        .checkbox-wrap { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .checkbox-wrap input { width:16px; height:16px; accent-color:#1a56db; }
        .checkbox-wrap label { font-size:13px; font-weight:500; margin:0; }
        .btn-row { display:flex; gap:12px; margin-top:28px; flex-wrap:wrap; align-items:center; }
        .btn-submit { background:#1a56db; color:#fff; border:none; padding:11px 28px; border-radius:10px; font-size:14px; cursor:pointer; font-weight:600; font-family:inherit; }
        .btn-submit:hover { background:#1341b0; }
        .btn-back { background:#fff; color:#64748b; border:1.5px solid #e2e8f0; padding:11px 28px; border-radius:10px; font-size:14px; text-decoration:none; font-weight:600; }
        .btn-back:hover { background:#f8fafc; text-decoration:none; }
        .btn-danger { background:#fff1f2; color:#dc2626; border:1.5px solid #fda4af; padding:11px 28px; border-radius:10px; font-size:14px; text-decoration:none; font-weight:600; margin-left:auto; }
        .btn-danger:hover { background:#dc2626; color:#fff; text-decoration:none; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <h2>Edit Room</h2>
        <p>Updating details for room <strong><?= htmlspecialchars($room['room_number']) ?></strong></p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">⚠️ Please fix the following:<ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($currentOccupants > 0): ?>
        <div class="alert-warning">This room has <strong><?= $currentOccupants ?></strong> student(s) currently allocated. Capacity cannot be reduced below this.</div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="room_number">Room Number *</label>
                    <input type="text" id="room_number" name="room_number" value="<?= htmlspecialchars($room_number) ?>">
                </div>
                <div class="form-group">
                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type">
                        <option value="single" <?= $room_type==='single'?'selected':'' ?>>Single</option>
                        <option value="double" <?= $room_type==='double'?'selected':'' ?>>Double</option>
                        <option value="triple" <?= $room_type==='triple'?'selected':'' ?>>Triple</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity *</label>
                    <input type="number" id="capacity" name="capacity" value="<?= htmlspecialchars($capacity) ?>" min="<?= max(1,$currentOccupants) ?>" max="10">
                    <?php if ($currentOccupants > 0): ?><div class="hint">Currently occupied by <?= $currentOccupants ?> student(s)</div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="price_per_month">Price per Month (£) *</label>
                    <input type="number" id="price_per_month" name="price_per_month" value="<?= htmlspecialchars($price_per_month) ?>" step="0.01" min="0">
                </div>
            </div>
            <div class="form-group">
                <label for="available_from">Available From *</label>
                <input type="date" id="available_from" name="available_from" value="<?= htmlspecialchars($available_from) ?>">
            </div>
            <div class="form-group">
                <label>Ensuite Facility</label>
                <div class="checkbox-wrap">
                    <input type="checkbox" id="is_ensuite" name="is_ensuite" <?= $is_ensuite ? 'checked' : '' ?>>
                    <label for="is_ensuite">🚿 This room has a private ensuite bathroom</label>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn-submit">💾 Save Changes</button>
                <a href="index.php" class="btn-back">← Back</a>
                <a href="delete_room.php?id=<?= $room_id ?>" class="btn-danger">🗑️ Delete</a>
            </div>
        </form>
    </div>
</div>
<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
