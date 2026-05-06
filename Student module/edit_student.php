<?php
require_once '../includes/session.php';
requireLogin();
require_once '../includes/db.php';

// ── Load student ───────────────────────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list_students.php");
    exit();
}
$student_id = (int)$_GET['id'];

$stmt = $db->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: list_students.php");
    exit();
}

// ── Load rooms ─────────────────────────────────────────────────
$roomsResult = $db->query("SELECT room_id, room_number, room_type FROM rooms ORDER BY room_number")->fetchAll();

$errors  = [];
$success = "";

// ── Handle form submit ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number']);
    $full_name      = trim($_POST['full_name']);
    $email          = trim($_POST['email']);
    $date_of_birth  = trim($_POST['date_of_birth']);
    $room_id        = $_POST['room_id'] !== '' ? (int)$_POST['room_id'] : null;
    $status         = (int)$_POST['status'];

    if (empty($student_number)) {
        $errors[] = "Student number is required.";
    } elseif (!preg_match('/^STU-\d{4}-\d{3}$/', $student_number)) {
        $errors[] = "Student number must follow the format STU-YYYY-XXX (e.g. STU-2024-001).";
    }
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email))     $errors[] = "Email address is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";

    // Check duplicate student_number (excluding current)
    if (empty($errors)) {
        $chk = $db->prepare("SELECT student_id FROM students WHERE student_number = ? AND student_id != ?");
        $chk->execute([$student_number, $student_id]);
        if ($chk->rowCount() > 0) $errors[] = "This student number is already in use.";
    }
    // Check duplicate email (excluding current)
    if (empty($errors)) {
        $chk = $db->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
        $chk->execute([$email, $student_id]);
        if ($chk->rowCount() > 0) $errors[] = "This email address is already registered.";
    }

    if (empty($errors)) {
        $dob  = !empty($date_of_birth) ? $date_of_birth : null;
        $upd  = $db->prepare(
            "UPDATE students SET student_number=?, full_name=?, email=?, date_of_birth=?, room_id=?, status=? WHERE student_id=?"
        );
        if ($upd->execute([$student_number, $full_name, $email, $dob, $room_id, $status, $student_id])) {
            $success = "Student record updated successfully!";
            // Refresh student data
            $stmt = $db->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
} else {
    // Pre-fill from DB
    $student_number = $student['student_number'];
    $full_name      = $student['full_name'];
    $email          = $student['email'];
    $date_of_birth  = $student['date_of_birth'] ?? '';
    $room_id        = $student['room_id'] ?? '';
    $status         = $student['status'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Edit Student</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:740px; margin:0 auto; }

        .page-header { margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; margin-bottom:4px; }
        .page-header p  { color:#64748b; font-size:14px; }

        .alert-success {
            background:#ecfdf5; border:1px solid #6ee7b7;
            color:#059669; padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px;
        }
        .alert-error {
            background:#fff1f2; border:1px solid #fda4af;
            color:#dc2626; padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px;
        }
        .alert-error ul { padding-left:18px; margin-top:6px; }

        .form-card {
            background:#fff; border-radius:16px; padding:32px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3;
        }

        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:#1e293b; }
        .form-group input,
        .form-group select {
            width:100%; padding:10px 14px;
            border:1.5px solid #e2e8f0; border-radius:10px;
            font-size:14px; font-family:inherit; color:#1e293b;
            background:#f8fafc; transition:border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline:none; border-color:#1a56db;
            box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#fff;
        }
        .hint { font-size:11px; color:#94a3b8; margin-top:5px; }

        .btn-row { display:flex; gap:12px; margin-top:28px; flex-wrap:wrap; }
        .btn-submit {
            background:#1a56db; color:#fff; border:none;
            padding:11px 28px; border-radius:10px; font-size:14px;
            cursor:pointer; font-weight:600; font-family:inherit;
            transition:background 0.2s;
        }
        .btn-submit:hover { background:#1341b0; }
        .btn-back {
            background:#fff; color:#64748b; border:1.5px solid #e2e8f0;
            padding:11px 28px; border-radius:10px; font-size:14px;
            text-decoration:none; font-weight:600; transition:background 0.2s;
        }
        .btn-back:hover { background:#f8fafc; text-decoration:none; }
        .btn-danger {
            background:#fff1f2; color:#dc2626; border:1.5px solid #fda4af;
            padding:11px 28px; border-radius:10px; font-size:14px;
            text-decoration:none; font-weight:600; transition:background 0.2s; margin-left:auto;
        }
        .btn-danger:hover { background:#dc2626; color:#fff; text-decoration:none; border-color:#dc2626; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h2>Edit Student</h2>
        <p>Updating record for <strong><?= htmlspecialchars($student['full_name']) ?></strong> · <?= htmlspecialchars($student['student_number']) ?></p>
    </div>

    <?php if ($success): ?>
        <div class="alert-success">✅ <?= $success ?>
            <a href="list_students.php" style="margin-left:10px; color:#059669; font-weight:600;">View all students →</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            ⚠️ Please fix the following errors:
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="">
            <div class="form-group">
                <label for="student_number">Student Number *</label>
                <input type="text" id="student_number" name="student_number"
                       value="<?= htmlspecialchars($student_number) ?>"
                       placeholder="e.g. STU-2024-001">
                <div class="hint">Format must be STU-YYYY-XXX</div>
            </div>
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name"
                       value="<?= htmlspecialchars($full_name) ?>"
                       placeholder="e.g. John Smith">
            </div>
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="e.g. john@student.edu">
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth"
                       value="<?= htmlspecialchars($date_of_birth) ?>">
                <div class="hint">Optional</div>
            </div>
            <div class="form-group">
                <label for="room_id">Assigned Room</label>
                <select id="room_id" name="room_id">
                    <option value="">-- No room assigned --</option>
                    <?php foreach ($roomsResult as $room): ?>
                        <option value="<?= $room['room_id'] ?>"
                            <?= ($room_id == $room['room_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room['room_number'] . ' (' . $room['room_type'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status">
                    <option value="1" <?= $status == 1 ? 'selected' : '' ?>>Active / Enrolled</option>
                    <option value="0" <?= $status == 0 ? 'selected' : '' ?>>Inactive / Left</option>
                </select>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn-submit">💾 Save Changes</button>
                <a href="list_students.php" class="btn-back">← Back</a>
                <a href="list_students.php?delete=<?= $student_id ?>"
                   class="btn-danger"
                   onclick="return confirm('Delete this student? This cannot be undone.')">
                   🗑️ Delete
                </a>
            </div>
        </form>
    </div>
</div>

<?php $db = null; ?>
</body>
</html>
