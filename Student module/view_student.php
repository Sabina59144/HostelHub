<?php
/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();          // Redirect to login if not authenticated
require_once '../includes/db.php';

/* ── Validate student ID from URL ──────────────── */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list_students.php");
    exit();
}

$student_id = (int)$_GET['id'];

/* ── Fetch student with room details via LEFT JOIN ── */
$stmt = $db->prepare(
    "SELECT s.*, r.room_number, r.room_type, r.price_per_month
     FROM students s
     LEFT JOIN rooms r ON s.room_id = r.room_id
     WHERE s.student_id = ?"
);
$stmt->execute([$student_id]);
$student = $stmt->fetch();

/* ── Redirect if student not found ─────────────── */
if (!$student) {
    header("Location: list_students.php");
    exit();
}

/* ── Fee summary: total, paid, and outstanding ─── */
$feeStmt = $db->prepare(
    "SELECT COUNT(*) AS total_fees,
            SUM(amount) AS total_amount,
            SUM(CASE WHEN is_paid IS NOT NULL THEN amount ELSE 0 END) AS paid_amount,
            SUM(CASE WHEN is_paid IS NULL THEN amount ELSE 0 END) AS pending_amount
     FROM fees WHERE student_id = ?"
);
$feeStmt->execute([$student_id]);
$fees = $feeStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — <?= htmlspecialchars($student['full_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:900px; margin:0 auto; }

        .back-bar { margin-bottom:20px; }
        .back-link {
            color:#64748b; text-decoration:none; font-size:13px; font-weight:600;
            display:inline-flex; align-items:center; gap:6px;
        }
        .back-link:hover { color:#1a56db; text-decoration:none; }

        /* ── Profile Header ── */
        .profile-header {
            background:#fff; border-radius:16px; padding:28px 32px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3;
            display:flex; align-items:center; gap:24px; margin-bottom:24px;
        }
        .avatar {
            width:72px; height:72px; border-radius:50%;
            background:linear-gradient(135deg,#1a56db,#60a5fa);
            display:flex; align-items:center; justify-content:center;
            font-size:28px; font-weight:700; color:#fff; flex-shrink:0;
            font-family:'Playfair Display',serif;
        }
        .profile-info h2 {
            font-family:'Playfair Display',serif; font-size:22px; color:#0f1923; margin-bottom:4px;
        }
        .profile-info .student-num { font-size:13px; color:#64748b; margin-bottom:8px; }
        .profile-actions { margin-left:auto; display:flex; gap:10px; }
        .btn-edit-profile {
            background:#1a56db; color:#fff; text-decoration:none;
            padding:9px 20px; border-radius:10px; font-size:13px; font-weight:600;
            transition:background 0.2s;
        }
        .btn-edit-profile:hover { background:#1341b0; text-decoration:none; color:#fff; }

        /* ── Badge ── */
        .badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-active   { background:#ecfdf5; color:#059669; }
        .badge-inactive { background:#fff1f2; color:#dc2626; }

        /* ── Info Grid ── */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        .info-card {
            background:#fff; border-radius:16px; padding:24px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3;
        }
        .info-card h3 {
            font-size:12px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;
            color:#94a3b8; margin-bottom:16px;
        }
        .info-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f0f4f8; }
        .info-row:last-child { border-bottom:none; }
        .info-label { font-size:13px; color:#64748b; }
        .info-value { font-size:13px; font-weight:600; color:#1e293b; text-align:right; }

        /* ── Fee Summary ── */
        .fee-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
        .fee-card {
            background:#fff; border-radius:12px; padding:18px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3;
            text-align:center;
        }
        .fee-card .fee-num { font-family:'Playfair Display',serif; font-size:24px; font-weight:700; color:#0f1923; display:block; margin-bottom:4px; }
        .fee-card .fee-lbl { font-size:12px; color:#64748b; }
        .fee-card.pending .fee-num { color:#dc2626; }
        .fee-card.paid .fee-num    { color:#059669; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; /* Shared navigation bar */ ?>

<div class="container">

    <div class="back-bar">
        <a href="list_students.php" class="back-link">← Back to all students</a>
    </div>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="avatar"><?= strtoupper(substr($student['full_name'], 0, 1)) ?></div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($student['full_name']) ?></h2>
            <div class="student-num"><?= htmlspecialchars($student['student_number']) ?></div>
            <?php if ($student['status'] == 1): ?>
                <span class="badge badge-active">Active / Enrolled</span>
            <?php else: ?>
                <span class="badge badge-inactive">Inactive / Left</span>
            <?php endif; ?>
        </div>
        <div class="profile-actions">
            <a href="edit_student.php?id=<?= $student_id ?>" class="btn-edit-profile">✏️ Edit</a>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="info-grid">
        <div class="info-card">
            <h3>Personal Details</h3>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($student['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Address</span>
                <span class="info-value"><?= htmlspecialchars($student['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date of Birth</span>
                <span class="info-value">
                    <?= $student['date_of_birth']
                        ? date('d M Y', strtotime($student['date_of_birth']))
                        : '<span style="color:#cbd5e1">Not provided</span>' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Student ID</span>
                <span class="info-value">#<?= $student['student_id'] ?></span>
            </div>
        </div>

        <div class="info-card">
            <h3>Room Assignment</h3>
            <?php if ($student['room_number']): ?>
            <div class="info-row">
                <span class="info-label">Room Number</span>
                <span class="info-value"><?= htmlspecialchars($student['room_number']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Room Type</span>
                <span class="info-value"><?= htmlspecialchars($student['room_type']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Monthly Rate</span>
                <span class="info-value">£<?= number_format($student['price_per_month'], 2) ?></span>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:24px 0;color:#94a3b8;font-size:13px;">
                🛏️ No room assigned yet.<br>
                <a href="../Room%20module/allocate_room.php?student_id=<?= $student_id ?>" style="color:#1a56db;font-weight:600;font-size:13px;margin-top:8px;display:inline-block;">Allocate a room →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fee Summary -->
    <p style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px;">Fee Overview</p>
    <div class="fee-summary">
        <div class="fee-card">
            <span class="fee-num">£<?= number_format($fees['total_amount'] ?? 0, 2) ?></span>
            <span class="fee-lbl">Total Charged</span>
        </div>
        <div class="fee-card paid">
            <span class="fee-num">£<?= number_format($fees['paid_amount'] ?? 0, 2) ?></span>
            <span class="fee-lbl">Paid</span>
        </div>
        <div class="fee-card pending">
            <span class="fee-num">£<?= number_format($fees['pending_amount'] ?? 0, 2) ?></span>
            <span class="fee-lbl">Outstanding</span>
        </div>
    </div>

</div>

<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
            <span 