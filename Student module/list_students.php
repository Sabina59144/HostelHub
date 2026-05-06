<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';

// ── Handle delete ──────────────────────────────────────────────
$deleteMsg = "";
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $del = $db->prepare("DELETE FROM students WHERE student_id = ?");
    $deleteMsg = $del->execute([$del_id])
        ? "Student record deleted successfully."
        : "Could not delete student. Please try again.";
}

// ── Fetch all students ─────────────────────────────────────────
$students = $db->query(
    "SELECT s.student_id, s.student_number, s.full_name, s.email,
            s.date_of_birth, s.status, r.room_number
     FROM students s
     LEFT JOIN rooms r ON s.room_id = r.room_id
     ORDER BY s.student_id ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — All Students</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:1200px; margin:0 auto; }

        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; }
        .btn-add {
            background:#1a56db; color:#fff; text-decoration:none;
            padding:10px 20px; border-radius:10px; font-size:13px;
            font-weight:600; transition:background 0.2s;
        }
        .btn-add:hover { background:#1341b0; text-decoration:none; color:#fff; }

        .alert-success { background:#ecfdf5; border:1px solid #6ee7b7; color:#059669; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
        .alert-error   { background:#fff1f2; border:1px solid #fda4af; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }

        .total-count { font-size:13px; color:#64748b; margin-bottom:14px; }

        .card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #e8edf3; overflow:hidden; }

        table { width:100%; border-collapse:collapse; }
        thead { background:#1e293b; }
        thead th { padding:13px 16px; text-align:left; font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; }
        tbody tr { border-bottom:1px solid #f0f4f8; }
        tbody tr:hover td { background:#f8fafc; }
        tbody td { padding:13px 16px; font-size:13px; vertical-align:middle; }

        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-active   { background:#ecfdf5; color:#059669; }
        .badge-inactive { background:#fff1f2; color:#dc2626; }

        .btn-edit {
            background:#eff6ff; color:#1a56db; text-decoration:none;
            padding:5px 12px; border-radius:6px; font-size:12px;
            font-weight:600; margin-right:4px; transition:background 0.2s;
        }
        .btn-edit:hover { background:#1a56db; color:#fff; text-decoration:none; }
        .btn-delete {
            background:#fff1f2; color:#dc2626; text-decoration:none;
            padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600;
            transition:background 0.2s;
        }
        .btn-delete:hover { background:#dc2626; color:#fff; text-decoration:none; }

        .empty-state { text-align:center; padding:50px 20px; color:#94a3b8; }
        .empty-state p { font-size:15px; margin-bottom:12px; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h2>All Students</h2>
        <a href="add_student.php" class="btn-add">➕ Add New Student</a>
    </div>

    <?php if ($deleteMsg): ?>
        <?php if (strpos($deleteMsg, 'successfully') !== false): ?>
            <div class="alert-success">✅ <?= $deleteMsg ?></div>
        <?php else: ?>
            <div class="alert-error">⚠️ <?= $deleteMsg ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="total-count">Total students: <strong><?= count($students) ?></strong></p>

    <div class="card">
        <?php if (!empty($students)): ?>
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
                <?php $i = 1; foreach ($students as $row): ?>
                <tr>
                    <td style="color:#94a3b8"><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['student_number']) ?></strong></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td style="color:#64748b"><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= $row['date_of_birth'] ? date('d M Y', strtotime($row['date_of_birth'])) : '<span style="color:#cbd5e1">—</span>' ?></td>
                    <td><?= $row['room_number'] ? htmlspecialchars($row['room_number']) : '<span style="color:#cbd5e1">Not assigned</span>' ?></td>
                    <td>
                        <?php if ($row['status'] == 1): ?>
                            <span class="badge badge-active">Enrolled</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Out of Hostel</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_student.php?id=<?= $row['student_id'] ?>" class="btn-edit">✏️ Edit</a>
                        <a href="list_students.php?delete=<?= $row['student_id'] ?>"
                           class="btn-delete"
                           onclick="return confirm('Delete <?= htmlspecialchars($row['full_name']) ?>? This cannot be undone.')">
                           🗑️ Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p>No students found in the database.</p>
            <a href="add_student.php" class="btn-add">➕ Add your first student</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $db = null; ?>
</body>
</html>
