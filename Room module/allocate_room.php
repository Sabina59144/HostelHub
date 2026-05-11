<?php
/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();
require_once '../includes/db.php';

$errors  = [];
$success = "";

/* ── Pre-select room/student if linked from elsewhere ─── */
$preRoom    = filter_input(INPUT_GET, 'room_id',    FILTER_VALIDATE_INT) ?: 0;
$preStu     = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0;

/* ── Rooms with available space only ────────────── */
$roomStmt = $db->query(
    "SELECT r.room_id, r.room_number, r.room_type, r.capacity, r.price_per_month, r.is_ensuite,
            COUNT(s.student_id) AS occupants
     FROM rooms r
     LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
     GROUP BY r.room_id
     HAVING occupants < r.capacity
     ORDER BY r.room_number"
);
$rooms = $roomStmt->fetchAll();

/* ── Students without a room assigned ──────────── */
$stuStmt = $db->query(
    "SELECT student_id, student_number, full_name
     FROM students
     WHERE room_id IS NULL AND status = 1
     ORDER BY full_name"
);
$students = $stuStmt->fetchAll();

$selectedRoom    = null;
$selectedStudent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ── Validate both selections ────────────────── */
    $room_id    = filter_input(INPUT_POST, 'room_id',    FILTER_VALIDATE_INT);
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if (!$room_id)    $errors[] = "Please select a room.";
    if (!$student_id) $errors[] = "Please select a student.";

    if (empty($errors)) {
        /* ── Re-verify room still has space (race-condition guard) ── */
        $chkRoom = $db->prepare(
            "SELECT r.capacity, COUNT(s.student_id) AS occ
             FROM rooms r
             LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
             WHERE r.room_id = ?
             GROUP BY r.room_id"
        );
        $chkRoom->execute([$room_id]);
        $roomData = $chkRoom->fetch();
        if (!$roomData) {
            $errors[] = "Selected room does not exist.";
        } elseif ((int)$roomData['occ'] >= (int)$roomData['capacity']) {
            $errors[] = "That room is now full. Please choose another.";
        }

        /* ── Re-verify student still unassigned ─── */
        $chkStu = $db->prepare("SELECT room_id FROM students WHERE student_id = ? AND status = 1");
        $chkStu->execute([$student_id]);
        $stuData = $chkStu->fetch();
        if (!$stuData) {
            $errors[] = "Selected student does not exist or is inactive.";
        } elseif ($stuData['room_id'] !== null) {
            $errors[] = "That student already has a room assigned.";
        }
    }

    /* ── Set room_id on student record ─────────── */
    if (empty($errors)) {
        $upd = $db->prepare("UPDATE students SET room_id = ? WHERE student_id = ?");
        if ($upd->execute([$room_id, $student_id])) {
            header("Location: index.php?msg=allocated");
            exit();
        } else {
            $errors[] = "Could not complete the allocation. Please try again.";
        }
    }

    /* ── Keep selections visible on error ─── */
    $preRoom = $room_id;
    $preStu  = $student_id;
}

// Load selected room details for the preview panel
if ($preRoom) {
    $pr = $db->prepare(
        "SELECT r.*, COUNT(s.student_id) AS occupants
         FROM rooms r
         LEFT JOIN students s ON s.room_id = r.room_id AND s.status = 1
         WHERE r.room_id = ?
         GROUP BY r.room_id"
    );
    $pr->execute([$preRoom]);
    $selectedRoom = $pr->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Allocate Room</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:900px; margin:0 auto; }
        .page-header { margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }
        .alert-error { background:#fff1f2; border:1px solid #fda4af; color:#dc2626; padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; }
        .alert-error ul { padding-left:18px; margin-top:6px; }
        .alert-warning { background:#fffbeb; border:1px solid #fcd34d; color:#d97706; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
        .layout { display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start; }
        .form-card { background:#fff; border-radius:16px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:#1e293b; }
        .form-group select { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:inherit; color:#1e293b; background:#f8fafc; }
        .form-group select:focus { outline:none; border-color:#1a56db; box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#fff; }
        .hint { font-size:11px; color:#94a3b8; margin-top:5px; }
        .btn-row { display:flex; gap:12px; margin-top:28px; }
        .btn-submit { background:#1a56db; color:#fff; border:none; padding:11px 28px; border-radius:10px; font-size:14px; cursor:pointer; font-weight:600; font-family:inherit; }
        .btn-submit:hover { background:#1341b0; }
        .btn-back { background:#fff; color:#64748b; border:1.5px solid #e2e8f0; padding:11px 28px; border-radius:10px; font-size:14px; text-decoration:none; font-weight:600; }
        .btn-back:hover { background:#f8fafc; text-decoration:none; }
        /* Preview panel */
        .preview-card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .preview-card h4 { font-size:14px; font-weight:700; color:#0f1923; margin-bottom:16px; }
        .preview-row { margin-bottom:12px; }
        .preview-row .lbl { font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:2px; }
        .preview-row .val { font-size:14px; font-weight:600; color:#1e293b; }
        .occupancy-bar { background:#e8edf3; border-radius:20px; height:8px; overflow:hidden; margin-top:6px; }
        .occupancy-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#1a56db,#60a5fa); }
        .occupancy-fill.full { background:linear-gradient(90deg,#dc2626,#fb7185); }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-ensuite { background:#ecfdf5; color:#059669; }
        .badge-shared  { background:#f1f5f9; color:#64748b; }
        .empty-preview { color:#94a3b8; font-size:13px; text-align:center; padding:20px 0; }
        @media(max-width:700px) { .layout { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; /* Shared navigation */ ?>
<div class="container">
    <div class="page-header">
        <h2>Allocate Room</h2>
        <p>Assign an unhoused student to an available room</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">⚠️ Please fix the following:<ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (empty($students)): ?>
        <div class="alert-warning">⚠️ All active students already have rooms assigned. <a href="../Student%20module/add_student.php">Add a new student</a> first.</div>
    <?php endif; ?>

    <?php if (empty($rooms)): ?>
        <div class="alert-warning">⚠️ No rooms with available space found. <a href="add_room.php">Add a room</a> or increase capacity on an existing one.</div>
    <?php endif; ?>

    <div class="layout">
        <!-- Form -->
        <div class="form-card">
            <form method="POST" action="" id="allocForm">
                <div class="form-group">
                    <label for="room_id">Select Room *</label>
                    <select id="room_id" name="room_id" onchange="updatePreview(this.value)" <?= empty($rooms)?'disabled':'' ?>>
                        <option value="">-- Choose a room --</option>
                        <?php foreach ($rooms as $r): $occ=(int)$r['occupants']; $cap=(int)$r['capacity']; ?>
                            <option value="<?= $r['room_id'] ?>"
                                <?= ($preRoom == $r['room_id']) ? 'selected' : '' ?>
                                data-type="<?= htmlspecialchars($r['room_type']) ?>"
                                data-cap="<?= $cap ?>"
                                data-occ="<?= $occ ?>"
                                data-price="<?= $r['price_per_month'] ?>"
                                data-ensuite="<?= $r['is_ensuite'] ?>">
                                <?= htmlspecialchars($r['room_number']) ?> — <?= ucfirst($r['room_type']) ?> (<?= $occ ?>/<?= $cap ?> occupied)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Only rooms with available space are listed</div>
                </div>

                <div class="form-group">
                    <label for="student_id">Select Student *</label>
                    <select id="student_id" name="student_id" <?= empty($students)?'disabled':'' ?>>
                        <option value="">-- Choose a student --</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= $st['student_id'] ?>"
                                <?= ($preStu == $st['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st['full_name']) ?> (<?= htmlspecialchars($st['student_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Only students without a room are listed</div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-submit" <?= (empty($rooms)||empty($students))?'disabled':'' ?>>🔑 Allocate Room</button>
                    <a href="index.php" class="btn-back">← Back</a>
                </div>
            </form>
        </div>

        <!-- Preview panel -->
        <div class="preview-card" id="previewPanel">
            <?php if ($selectedRoom): ?>
                <h4>📋 Room Preview</h4>
                <div class="preview-row"><div class="lbl">Room Number</div><div class="val"><?= htmlspecialchars($selectedRoom['room_number']) ?></div></div>
                <div class="preview-row"><div class="lbl">Type</div><div class="val"><?= ucfirst(htmlspecialchars($selectedRoom['room_type'])) ?></div></div>
                <div class="preview-row"><div class="lbl">Capacity</div><div class="val"><?= (int)$selectedRoom['capacity'] ?> person(s)</div></div>
                <div class="preview-row">
                    <div class="lbl">Occupancy</div>
                    <div class="val"><?= (int)$selectedRoom['occupants'] ?> / <?= (int)$selectedRoom['capacity'] ?></div>
                    <?php $pct = round(($selectedRoom['occupants']/$selectedRoom['capacity'])*100); ?>
                    <div class="occupancy-bar"><div class="occupancy-fill <?= $pct>=100?'full':'' ?>" style="width:<?= $pct ?>%"></div></div>
                </div>
                <div class="preview-row"><div class="lbl">Price / Month</div><div class="val">£<?= number_format($selectedRoom['price_per_month'],2) ?></div></div>
                <div class="preview-row">
                    <div class="lbl">Bathroom</div>
                    <div class="val"><?= $selectedRoom['is_ensuite'] ? '<span class="badge badge-ensuite">Ensuite</span>' : '<span class="badge badge-shared">Shared</span>' ?></div>
                </div>
            <?php else: ?>
                <div class="empty-preview">Select a room to see its details here</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $db = null; /* Close DB connection */ ?>
<script>
/* ── JS: update preview panel when room dropdown changes ── */
// Build room data map from PHP
const roomData = {
    <?php foreach ($rooms as $r): ?>
    <?= $r['room_id'] ?>: {
        number: <?= json_encode($r['room_number']) ?>,
        type:   <?= json_encode(ucfirst($r['room_type'])) ?>,
        cap:    <?= (int)$r['capacity'] ?>,
        occ:    <?= (int)$r['occupants'] ?>,
        price:  <?= (float)$r['price_per_month'] ?>,
        ensuite: <?= (int)$r['is_ensuite'] ?>
    },
    <?php endforeach; ?>
};

function updatePreview(id) {
    const panel = document.getElementById('previewPanel');
    if (!id || !roomData[id]) {
        panel.innerHTML = '<div class="empty-preview">Select a room to see its details here</div>';
        return;
    }
    const r = roomData[id];
    const pct = Math.round((r.occ / r.cap) * 100);
    const ensBadge = r.ensuite
        ? '<span class="badge badge-ensuite">Ensuite</span>'
        : '<span class="badge badge-shared">Shared</span>';
    panel.innerHTML = `
        <h4>📋 Room Preview</h4>
        <div class="preview-row"><div class="lbl">Room Number</div><div class="val">${r.number}</div></div>
        <div class="preview-row"><div class="lbl">Type</div><div class="val">${r.type}</div></div>
        <div class="preview-row"><div class="lbl">Capacity</div><div class="val">${r.cap} person(s)</div></div>
        <div class="preview-row">
            <div class="lbl">Occupancy</div>
            <div class="val">${r.occ} / ${r.cap}</div>
            <div class="occupancy-bar"><div class="occupancy-fill ${pct>=100?'full':''}" style="width:${pct}%"></div></div>
        </div>
        <div class="preview-row"><div class="lbl">Price / Month</div><div class="val">£${r.price.toFixed(2)}</div></div>
        <div class="preview-row"><div class="lbl">Bathroom</div><div class="val">${ensBadge}</div></div>
    `;
}

// Trigger on load if room pre-selected
window.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('room_id');
    if (sel && sel.value) updatePreview(sel.value);
});
</script>
</body>
</html>
