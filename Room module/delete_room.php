<?php
/**
 * Room module/delete_room.php
 * ─────────────────────────────────────────────────────────────
 * Permanently delete a room from the system.
 *
 * If students are still allocated to the room, a confirmation
 * checkbox is required before proceeding. Deletion runs inside
 * a transaction: students are unallocated (room_id = NULL) first,
 * then the room row is deleted — rolled back if either step fails.
 *
 * On success: redirects to index.php?msg=deleted
 * ─────────────────────────────────────────────────────────────
 */

/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();
require_once '../includes/db.php';

/* ── Accept room ID from GET or POST ────────────── */
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
        ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$room_id) { header("Location: index.php"); exit(); }

/* ── Fetch room or redirect if not found ────────── */
$stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) { header("Location: index.php"); exit(); }

/* ── Check for students currently in this room ─── */
$alloc = $db->prepare("SELECT student_id, student_number, full_name FROM students WHERE room_id = ? AND status = 1 ORDER BY full_name");
$alloc->execute([$room_id]);
$allocated = $alloc->fetchAll();
$hasAllocations = count($allocated) > 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    /* ── Require checkbox if students are still allocated ── */
    if ($hasAllocations && empty($_POST['force'])) {
        $errors[] = "This room still has allocated students. Tick the confirmation box to proceed.";
    }
    if (empty($errors)) {
        try {
            /* ── Transaction: unallocate students then delete room ── */
            $db->beginTransaction();
            $db->prepare("UPDATE students SET room_id = NULL WHERE room_id = ?")->execute([$room_id]);
            $db->prepare("DELETE FROM rooms WHERE room_id = ?")->execute([$room_id]);
            $db->commit();
            header("Location: index.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Could not delete the room: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Delete Room</title>
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
        .alert-warning { background:#fffbeb; border:1px solid #fcd34d; color:#d97706; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
        .card { background:#fff; border-radius:16px; padding:28px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:2px solid #fda4af; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; }
        .info-row .lbl { font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:3px; }
        .info-row .val { font-size:14px; font-weight:600; color:#1e293b; }
        .student-table { width:100%; border-collapse:collapse; margin-top:12px; font-size:13px; }
        .student-table th { text-align:left; padding:8px 12px; background:#f8fafc; font-size:11px; color:#94a3b8; text-transform:uppercase; }
        .student-table td { padding:8px 12px; border-top:1px solid #f0f4f8; }
        .checkbox-wrap { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #fda4af; border-radius:10px; background:#fff1f2; margin-bottom:20px; }
        .checkbox-wrap input { width:16px; height:16px; accent-color:#dc2626; }
        .checkbox-wrap label { font-size:13px; color:#dc2626; font-weight:500; margin:0; }
        .btn-row { display:flex; gap:12px; }
        .btn-danger { background:#dc2626; color:#fff; border:none; padding:11px 28px; border-radius:10px; font-size:14px; cursor:pointer; font-weight:600; font-family:inherit; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-back { background:#fff; color:#64748b; border:1.5px solid #e2e8f0; padding:11px 28px; border-radius:10px; font-size:14px; text-decoration:none; font-weight:600; }
        .btn-back:hover { background:#f8fafc; text-decoration:none; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <h2>Delete Room</h2>
        <p>You are about to permanently delete room <strong><?= htmlspecialchars($room['room_number']) ?></strong></p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($errors[0]) ?></div>
    <?php endif; ?>

    <?php if ($hasAllocations): ?>
        <div class="alert-warning">⚠️ <strong>Warning:</strong> This room has <?= count($allocated) ?> active student(s). Deleting it will unallocate them.</div>
    <?php endif; ?>

    <div class="card">
        <div class="info-grid">
            <div class="info-row"><div class="lbl">Room Number</div><div class="val"><?= htmlspecialchars($room['room_number']) ?></div></div>
            <div class="info-row"><div class="lbl">Room Type</div><div class="val"><?= ucfirst(htmlspecialchars($room['room_type'])) ?></div></div>
            <div class="info-row"><div class="lbl">Capacity</div><div class="val"><?= (int)$room['capacity'] ?> person(s)</div></div>
            <div class="info-row"><div class="lbl">Price / Month</div><div class="val">£<?= number_format($room['price_per_month'],2) ?></div></div>
            <div class="info-row"><div class="lbl">Ensuite</div><div class="val"><?= $room['is_ensuite'] ? 'Yes' : 'No' ?></div></div>
            <div class="info-row"><div class="lbl">Available From</div><div class="val"><?= htmlspecialchars($room['available_from']) ?></div></div>
        </div>

        <?php if ($hasAllocations): ?>
            <p style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:8px;">Currently Allocated Students:</p>
            <table class="student-table">
                <thead><tr><th>Student #</th><th>Full Name</th></tr></thead>
                <tbody>
                <?php foreach ($allocated as $s): ?>
                    <tr><td><?= htmlspecialchars($s['student_number']) ?></td><td><?= htmlspecialchars($s['full_name']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="POST" action="" style="margin-top:24px;" onsubmit="return confirm('Permanently delete room <?= htmlspecialchars(addslashes($room['room_number'])) ?>? This cannot be undone.')">
            <input type="hidden" name="id"      value="<?= (int)$room_id ?>">
            <input type="hidden" name="confirm" value="yes">
            <?php if ($hasAllocations): ?>
                <div class="checkbox-wrap">
                    <input type="checkbox" id="force" name="force" value="1" required>
                    <label for="force">I understand — unallocate the <?= count($allocated) ?> student(s) and delete this room permanently.</label>
                </div>
            <?php endif; ?>
            <div class="btn-row">
                <button type="submit" class="btn-danger">🗑️ Delete Room</button>
                <a href="index.php" class="btn-back">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
