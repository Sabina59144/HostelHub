<?php
/**
 * Student module/index.php
 * ─────────────────────────────────────────────────────────────
 * Student module overview dashboard.
 *
 * Shows:
 *   • Stat cards: total / active / inactive / unassigned students
 *   • Active enrolment progress bar
 *   • Quick-action buttons (add, view all, filter by status/room)
 *   • Table of the 5 most recently added students
 * ─────────────────────────────────────────────────────────────
 */

/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();          // Redirect to login if not authenticated
require_once '../includes/db.php';

/* ── Stat card counts ──────────────────────────── */
$totalStudents   = $db->query("SELECT COUNT(*) AS c FROM students")->fetch()['c'];
$totalActive     = $db->query("SELECT COUNT(*) AS c FROM students WHERE status = 1")->fetch()['c'];
$totalInactive   = $db->query("SELECT COUNT(*) AS c FROM students WHERE status = 0")->fetch()['c'];
$totalUnassigned = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NULL")->fetch()['c'];

/* ── Last 5 students added, joined with room number ── */
$recentStmt = $db->query(
    "SELECT s.student_id, s.student_number, s.full_name, s.email, s.status, r.room_number
     FROM students s
     LEFT JOIN rooms r ON s.room_id = r.room_id
     ORDER BY s.student_id DESC LIMIT 5"
);
$recentStudents = $recentStmt->fetchAll();

/* ── Active enrolment % for progress bar ──────── */
$pct = $totalStudents > 0 ? round(($totalActive / $totalStudents) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Student Module</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 1200px; margin: 0 auto; }

        .page-header { margin-bottom: 28px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #0f1923; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .stat-cards { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; margin-bottom: 32px; }
        .stat-card {
            background: #fff; border-radius: 16px; padding: 24px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e8edf3;
            position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:16px 16px 0 0;
        }
        .stat-card.blue::before  { background: linear-gradient(90deg,#1a56db,#60a5fa); }
        .stat-card.green::before { background: linear-gradient(90deg,#059669,#34d399); }
        .stat-card.amber::before { background: linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-card.rose::before  { background: linear-gradient(90deg,#dc2626,#fb7185); }

        .stat-icon-wrap {
            width:44px; height:44px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; margin-bottom:16px;
        }
        .blue  .stat-icon-wrap { background:#eff6ff; }
        .green .stat-icon-wrap { background:#ecfdf5; }
        .amber .stat-icon-wrap { background:#fffbeb; }
        .rose  .stat-icon-wrap { background:#fff1f2; }

        .stat-number { font-family:'Playfair Display',serif; font-size:2.2rem; font-weight:700; line-height:1; color:#0f1923; display:block; margin-bottom:5px; }
        .stat-label  { font-size:13px; color:#64748b; font-weight:500; display:block; }

        .section-label { font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#94a3b8; margin-bottom:16px; }

        .action-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px; }
        .action-btn {
            background:#fff; border-radius:12px; padding:20px 16px;
            text-decoration:none; color:#1e293b;
            box-shadow:0 1px 4px rgba(0,0,0,0.06); border:1px solid #e8edf3;
            display:flex; flex-direction:column; align-items:center; gap:8px;
            transition:all 0.18s;
        }
        .action-btn:hover { border-color:#1a56db; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.09); text-decoration:none; color:#1a56db; }
        .action-btn .btn-icon  { font-size:28px; }
        .action-btn .btn-label { font-size:14px; font-weight:600; }
        .action-btn .btn-desc  { font-size:11px; color:#94a3b8; text-align:center; }

        .progress-wrap { margin-bottom:32px; }
        .progress-meta { display:flex; justify-content:space-between; font-size:13px; color:#64748b; margin-bottom:8px; }
        .progress-bar  { background:#e8edf3; border-radius:20px; height:8px; overflow:hidden; }
        .progress-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#1a56db,#60a5fa); }

        .card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .card-header h3 { font-size:15px; font-weight:600; color:#0f1923; }
        .card-header a  { font-size:13px; color:#1a56db; text-decoration:none; }
        .card-header a:hover { text-decoration:underline; }

        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; padding:8px 12px; border-bottom:2px solid #f0f4f8; }
        td { padding:11px 12px; font-size:13px; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#f8fafc; }

        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-active     { background:#ecfdf5; color:#059669; }
        .badge-inactive   { background:#fff1f2; color:#dc2626; }
        .badge-unassigned { background:#eff6ff; color:#1a56db; }

        .no-data { text-align:center; padding:30px; color:#94a3b8; font-size:13px; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; /* Shared navigation bar */ ?>

<div class="container">

    <div class="page-header">
        <h2>Student Module</h2>
        <p>Manage hostel student records — add, view, edit, and search students</p>
    </div>

    <div class="stat-cards">
        <div class="stat-card blue">
            <div class="stat-icon-wrap">👥</div>
            <span class="stat-number"><?= $totalStudents ?></span>
            <span class="stat-label">Total Students</span>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrap">✅</div>
            <span class="stat-number"><?= $totalActive ?></span>
            <span class="stat-label">Active / Enrolled</span>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon-wrap">🚫</div>
            <span class="stat-number"><?= $totalInactive ?></span>
            <span class="stat-label">Inactive / Left</span>
        </div>
        <div class="stat-card rose">
            <div class="stat-icon-wrap">🛏️</div>
            <span class="stat-number"><?= $totalUnassigned ?></span>
            <span class="stat-label">No Room Assigned</span>
        </div>
    </div>

    <div class="progress-wrap">
        <div class="progress-meta">
            <span>Active enrolment rate</span>
            <span><?= $pct ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
    </div>

    <p class="section-label">Quick Actions</p>
    <div class="action-grid">
        <a href="add_student.php" class="action-btn">
            <div class="btn-icon">➕</div>
            <div class="btn-label">Add Student</div>
            <div class="btn-desc">Register a new student</div>
        </a>
        <a href="list_students.php" class="action-btn">
            <div class="btn-icon">📋</div>
            <div class="btn-label">View All</div>
            <div class="btn-desc">Browse all student records</div>
        </a>
        <a href="list_students.php?status=1" class="action-btn">
            <div class="btn-icon">✅</div>
            <div class="btn-label">Active</div>
            <div class="btn-desc">View enrolled students</div>
        </a>
        <a href="list_students.php?room=unassigned" class="action-btn">
            <div class="btn-icon">🛏️</div>
            <div class="btn-label">Unassigned</div>
            <div class="btn-desc">Students without a room</div>
        </a>
    </div>

    <p class="section-label">Recently Added</p>
    <div class="card">
        <div class="card-header">
            <h3>🕐 Latest Students</h3>
            <a href="list_students.php">View all →</a>
        </div>
        <?php if (empty($recentStudents)): ?>
            <div class="no-data">No students yet. <a href="add_student.php">Add the first one →</a></div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Student No.</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentStudents as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                    <td>
                        <a href="view_student.php?id=<?= $s['student_id'] ?>"
                           style="color:#1e293b;font-weight:500;text-decoration:none;">
                           <?= htmlspecialchars($s['full_name']) ?>
                        </a>
                    </td>
                    <td style="color:#64748b"><?= htmlspecialchars($s['email']) ?></td>
                    <td>
                        <?php if ($s['room_number']): ?>
                            <?= htmlspecialchars($s['room_number']) ?>
                        <?php else: ?>
                            <span class="badge badge-unassigned">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['status'] == 1): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_student.php?id=<?= $s['student_id'] ?>"
                           style="color:#1a56db; font-size:13px; text-decoration:none; font-weight:600;">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
