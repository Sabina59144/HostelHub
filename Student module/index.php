<?php
// session_start();
// Temporarily disabled — uncomment when login.php is ready
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }


require_once '../includes/db.php';

// ── Stats ─────────────────────────────────────────────────────────────
$totalStudents   = $db->query("SELECT COUNT(*) AS c FROM students")->fetch()['c'];
$totalActive     = $db->query("SELECT COUNT(*) AS c FROM students WHERE status = 1")->fetch()['c'];
$totalInactive   = $db->query("SELECT COUNT(*) AS c FROM students WHERE status = 0")->fetch()['c'];
$totalUnassigned = $db->query("SELECT COUNT(*) AS c FROM students WHERE room_id IS NULL")->fetch()['c'];

// ── 5 most recently added students ────────────────────────────────────
$recentStmt = $db->query(
    "SELECT s.student_id, s.student_number, s.full_name, s.email, s.status,
            r.room_number
     FROM   students s
     LEFT JOIN rooms r ON s.room_id = r.room_id
     ORDER BY s.student_id DESC
     LIMIT 5"
);
$recentStudents = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Student Module</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }

        /* ── Navbar ── */
        .navbar {
            background: #B71C1C; color: white;
            padding: 14px 30px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a  { color: white; text-decoration: none; margin-left: 20px; font-size: 13px; }
        .navbar a:hover { text-decoration: underline; }

        /* ── Container ── */
        .container { padding: 30px; max-width: 1100px; margin: 0 auto; }

        /* ── Page title ── */
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 24px; color: #B71C1C; }
        .page-title p  { font-size: 13px; color: #666; margin-top: 4px; }

        /* ── Stat cards ── */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 10px;
            padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-left: 5px solid #ccc;
            transition: transform 0.15s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.total   { border-color: #B71C1C; }
        .stat-card.active  { border-color: #2e7d32; }
        .stat-card.inactive{ border-color: #e65100; }
        .stat-card.unassigned { border-color: #1565c0; }
        .stat-number {
            font-size: 36px; font-weight: 700; color: #222;
            line-height: 1;
        }
        .stat-label {
            font-size: 12px; color: #777; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .stat-icon { font-size: 28px; float: right; opacity: 0.15; margin-top: -4px; }

        /* ── Action buttons ── */
        .section-title {
            font-size: 14px; font-weight: 700; color: #555;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 14px;
        }
        .action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: white; border-radius: 10px;
            padding: 20px 16px; text-decoration: none; color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex; flex-direction: column; align-items: center;
            gap: 8px; transition: all 0.15s; border: 2px solid transparent;
        }
        .action-btn:hover { border-color: #B71C1C; transform: translateY(-2px); }
        .action-btn .btn-icon { font-size: 28px; }
        .action-btn .btn-label { font-size: 14px; font-weight: 700; color: #222; }
        .action-btn .btn-desc  { font-size: 11px; color: #999; text-align: center; }

        /* ── Recent students table ── */
        .card {
            background: white; border-radius: 10px;
            padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px;
        }
        .card-header h3 { font-size: 15px; color: #333; }
        .card-header a  { font-size: 13px; color: #B71C1C; text-decoration: none; }
        .card-header a:hover { text-decoration: underline; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 11px; color: #999;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 8px 12px; border-bottom: 2px solid #f0f0f0;
        }
        td {
            padding: 10px 12px; font-size: 13px;
            border-bottom: 1px solid #f5f5f5;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        .badge {
            display: inline-block; padding: 2px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-active   { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #fff3e0; color: #e65100; }
        .badge-unassigned { background: #e3f2fd; color: #1565c0; font-size: 11px; }

        .no-data { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }

        /* ── Progress bar ── */
        .progress-wrap { margin-bottom: 30px; }
        .progress-label {
            display: flex; justify-content: space-between;
            font-size: 12px; color: #666; margin-bottom: 6px;
        }
        .progress-bar {
            background: #f0f0f0; border-radius: 20px; height: 10px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; border-radius: 20px; background: #B71C1C;
            transition: width 1s ease;
        }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <h1>🏨 HostelHub</h1>
    <div>
        <span style="font-size:13px; opacity:0.85;">Logged in as:
            <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </span>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <!-- Page heading -->
    <div class="page-title">
        <h2>👨‍🎓 Student Module</h2>
        <p>Manage hostel student records — add, view, edit, and search students</p>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-cards">
        <div class="stat-card total">
            <div class="stat-icon">👥</div>
            <div class="stat-number"><?php echo $totalStudents; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card active">
            <div class="stat-icon">✅</div>
            <div class="stat-number"><?php echo $totalActive; ?></div>
            <div class="stat-label">Active / Enrolled</div>
        </div>
        <div class="stat-card inactive">
            <div class="stat-icon">🚫</div>
            <div class="stat-number"><?php echo $totalInactive; ?></div>
            <div class="stat-label">Inactive / Left</div>
        </div>
        <div class="stat-card unassigned">
            <div class="stat-icon">🛏️</div>
            <div class="stat-number"><?php echo $totalUnassigned; ?></div>
            <div class="stat-label">No Room Assigned</div>
        </div>
    </div>

    <!-- ── Occupancy progress bar ── -->
    <?php
    $pct = $totalStudents > 0 ? round(($totalActive / $totalStudents) * 100) : 0;
    ?>
    <div class="progress-wrap">
        <div class="progress-label">
            <span>Active enrolment rate</span>
            <span><?php echo $pct; ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $pct; ?>%"></div>
        </div>
    </div>

    <!-- ── Quick Actions ── -->
    <div class="section-title">Quick Actions</div>
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
        <a href="search_student.php" class="action-btn">
            <div class="btn-icon">🔍</div>
            <div class="btn-label">Search</div>
            <div class="btn-desc">Find a student by name or ID</div>
        </a>
        <a href="list_students.php?filter=unassigned" class="action-btn">
            <div class="btn-icon">🛏️</div>
            <div class="btn-label">Unassigned</div>
            <div class="btn-desc">Students without a room</div>
        </a>
    </div>

    <!-- ── Recent Students ── -->
    <div class="card">
        <div class="card-header">
            <h3>🕐 Recently Added Students</h3>
            <a href="list_students.php">View all →</a>
        </div>

        <?php if (empty($recentStudents)): ?>
            <div class="no-data">No students registered yet. <a href="add_student.php">Add the first one →</a></div>
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
                    <td><strong><?php echo htmlspecialchars($s['student_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td style="color:#777;"><?php echo htmlspecialchars($s['email']); ?></td>
                    <td>
                        <?php if ($s['room_number']): ?>
                            <?php echo htmlspecialchars($s['room_number']); ?>
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
                        <a href="edit_student.php?id=<?php echo $s['student_id']; ?>"
                           style="color:#B71C1C; font-size:13px; text-decoration:none;">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- end container -->

</body>
</html>
<?php $db = null; ?>
