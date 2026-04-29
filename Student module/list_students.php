<?php

// ── Start session and check login ─────────────────────────────
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ── Connect to database ───────────────────────────────────────
require_once 'config/db.php';

// ── Handle delete request ─────────────────────────────────────
// If delete button is clicked, remove the student record
$deleteMsg = "";
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];

    // Prepare delete statement to prevent SQL injection
    $delStmt = mysqli_prepare($conn, "DELETE FROM students WHERE student_id = ?");
    mysqli_stmt_bind_param($delStmt, "i", $del_id);

    if (mysqli_stmt_execute($delStmt)) {
        $deleteMsg = "Student record deleted successfully.";
    } else {
        $deleteMsg = "Could not delete student. Please try again.";
    }
    mysqli_stmt_close($delStmt);
}

// ── Fetch all students with their room number ─────────────────
// LEFT JOIN so students with no room assigned still appear
$query = "
    SELECT
        s.student_id,
        s.student_number,
        s.full_name,
        s.email,
        s.date_of_birth,
        s.status,
        r.room_number
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    ORDER BY s.student_id ASC
";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — All Students</title>
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
        .container { padding: 30px; max-width: 1200px; margin: 0 auto; }

        /* ── Page title ── */
        .page-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 24px;
        }
        .page-header h2 { font-size: 22px; color: #B71C1C; }
        .btn-add {
            background: #B71C1C; color: white;
            text-decoration: none; padding: 10px 20px;
            border-radius: 6px; font-size: 13px; font-weight: 600;
        }
        .btn-add:hover { background: #8B0000; }

        /* ── Alert messages ── */
        .alert-success {
            background: #e8f5e9; border: 1px solid #a5d6a7;
            color: #2e7d32; padding: 12px 16px;
            border-radius: 6px; margin-bottom: 20px; font-size: 13px;
        }
        .alert-error {
            background: #ffebee; border: 1px solid #ef9a9a;
            color: #c62828; padding: 12px 16px;
            border-radius: 6px; margin-bottom: 20px; font-size: 13px;
        }

        /* ── Table card ── */
        .table-card {
            background: white; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden;
        }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; }
        thead { background: #B71C1C; color: white; }
        thead th { padding: 13px 16px; text-align: left; font-size: 13px; font-weight: 600; }
        tbody tr { border-bottom: 1px solid #f0f0f0; }
        tbody tr:hover { background: #fafafa; }
        tbody td { padding: 12px 16px; font-size: 13px; }

        /* ── Status badge ── */
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-active   { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #ffebee; color: #c62828; }

        /* ── Action buttons ── */
        .btn-edit {
            background: #1565C0; color: white;
            text-decoration: none; padding: 5px 12px;
            border-radius: 4px; font-size: 12px; margin-right: 4px;
        }
        .btn-edit:hover { background: #0D47A1; }
        .btn-delete {
            background: #B71C1C; color: white;
            text-decoration: none; padding: 5px 12px;
            border-radius: 4px; font-size: 12px;
        }
        .btn-delete:hover { background: #8B0000; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 50px 20px; color: #999;
        }
        .empty-state p { font-size: 15px; margin-bottom: 12px; }

        /* ── Total count ── */
        .total-count {
            font-size: 13px; color: #666; margin-bottom: 12px;
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
        <a href="index.php">👨‍🎓 Students</a>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <!-- Page header with Add button -->
    <div class="page-header">
        <h2>📋 All Students</h2>
        <a href="add_student.php" class="btn-add">➕ Add New Student</a>
    </div>

    <!-- Delete feedback message -->
    <?php if ($deleteMsg): ?>
        <?php if (strpos($deleteMsg, 'successfully') !== false): ?>
            <div class="alert-success">✅ <?php echo $deleteMsg; ?></div>
        <?php else: ?>
            <div class="alert-error">⚠️ <?php echo $deleteMsg; ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Total count -->
    <p class="total-count">
        Total students found: <strong><?php echo mysqli_num_rows($result); ?></strong>
    </p>

    <!-- Students table -->
    <div class="table-card">
        <?php if (mysqli_num_rows($result) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Number</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Date of Birth</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <?php echo $row['date_of_birth']
                            ? date('d M Y', strtotime($row['date_of_birth']))
                            : '<span style="color:#bbb;">—</span>';
                        ?>
                    </td>
                    <td>
                        <?php echo $row['room_number']
                            ? htmlspecialchars($row['room_number'])
                            : '<span style="color:#bbb;">Not assigned</span>';
                        ?>
                    </td>
                    <td>
                        <?php if ($row['status'] == 1): ?>
                            <span class="badge badge-active">Enrolled</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Out of Hostel</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Edit button — links to edit page (Week 3) -->
                        <a href="edit_student.php?id=<?php echo $row['student_id']; ?>"
                           class="btn-edit">✏️ Edit</a>

                        <!-- Delete button — confirms before deleting -->
                        <a href="list_students.php?delete=<?php echo $row['student_id']; ?>"
                           class="btn-delete"
                           onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($row['full_name']); ?>? This cannot be undone.');">
                           🗑️ Delete
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php else: ?>
        <!-- No students found -->
        <div class="empty-state">
            <p>No students found in the database.</p>
            <a href="add_student.php" class="btn-add">➕ Add your first student</a>
        </div>
        <?php endif; ?>
    </div>

</div><!-- end container -->

</body>
</html>
<?php mysqli_close($conn); ?>
