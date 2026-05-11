<?php
/* ── Auth & DB ─────────────────────────────────── */
require_once '../includes/session.php';
requireLogin();          // Redirect to login if not authenticated
require_once '../includes/db.php';

/* ── Handle inline delete from action link ─────── */
$deleteMsg = "";
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $del = $db->prepare("DELETE FROM students WHERE student_id = ?");
    $deleteMsg = $del->execute([$del_id])
        ? "Student record deleted successfully."
        : "Could not delete student. Please try again.";
}

/* ── Build dynamic WHERE clause from URL filters ── */
$where  = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where[]  = "s.status = ?";
    $params[] = (int)$_GET['status'];
}
if (isset($_GET['room']) && $_GET['room'] === 'unassigned') {
    $where[] = "s.room_id IS NULL";  // Filter students with no room
}
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search   = '%' . $_GET['search'] . '%';
    $where[]  = "(s.full_name LIKE ? OR s.student_number LIKE ? OR s.email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

/* ── Fetch students joined with room number ─────── */
$sql = "SELECT s.student_id, s.student_number, s.full_name, s.email,
               s.date_of_birth, s.status, r.room_number
        FROM students s
        LEFT JOIN rooms r ON s.room_id = r.room_id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY s.student_id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

/* ── Page title label based on active filter ─────── */
$filterLabel = "All Students";
if (isset($_GET['status']) && $_GET['status'] == 1) $filterLabel = "Active Students";
if (isset($_GET['status']) && $_GET['status'] == 0) $filterLabel = "Inactive Students";
if (isset($_GET['room']) && $_GET['room'] === 'unassigned') $filterLabel = "Unassigned Students";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — <?= $filterLabel ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#f0f4f8; font-family:'DM Sans',sans-serif; }
        .container { padding:32px 40px; max-width:1200px; margin:0 auto; }

        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .page-header h2 { font-family:'Playfair Display',serif; font-size:26px; color:#0f1923; }
        .btn-add {
            background:#1a56db; color:#fff; text-decoration:none;
            padding:10px 20px; border-radius:10px; font-size:13px;
            font-weight:600; transition:background 0.2s;
        }
        .btn-add:hover { background:#1341b0; text-decoration:none; color:#fff; }

        .toolbar { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
        .search-box {
            flex:1; min-width:200px; padding:9px 14px;
            border:1.5px solid #e2e8f0; border-radius:10px;
            font-size:13px; font-family:inherit; color:#1e293b;
            background:#fff; outline:none; transition:border-color 0.2s;
        }
        .search-box:focus { border-color:#1a56db; box-shadow:0 0 0 3px rgba(26,86,219,0.1); }

        .filter-btns { display:flex; gap:6px; flex-wrap:wrap; }
        .filter-btn {
            padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600;
            text-decoration:none; border:1.5px solid #e2e8f0;
            color:#64748b; background:#fff; transition:all 0.15s;
        }
        .filter-btn:hover { border-color:#1a56db; color:#1a56db; text-decoration:none; }
        .filter-btn.active { background:#1a56db; color:#fff; border-color:#1a56db; }

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

<?php include '../includes/navbar.php'; /* Shared navigation bar */ ?>

<div class="container">
    <div class="page-header">
        <h2><?= $filterLabel ?></h2>
        <a href="add_student.php" class="btn-add">➕ Add New Student</a>
    </div>

    <?php if ($deleteMsg): ?>
        <?php if (strpos($deleteMsg, 'successfully') !== false): ?>
            <div class="alert-success">✅ <?= $deleteMsg ?></div>
        <?php else: ?>
            <div class="alert-error">⚠️ <?= $deleteMsg ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="toolbar">
        <form method="GET" action="" style="display:flex;flex:1;gap:8px;align-items:center;">
            <input type="text" name="search" class="search-box"
                   placeholder="🔍 Search by name, number or email…"
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <?php if (isset($_GET['status'])):  ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']) ?>">
            <?php endif; ?>
            <?php if (isset($_GET['room'])): ?>
                <input type="hidden" name="room" value="<?= htmlspecialchars($_GET['room']) ?>">
            <?php endif; ?>
            <button type="submit" style="background:#1a56db;color:#fff;border:none;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;">Search</button>
            <?php if (!empty($_GET['search'])): ?>
                <a href="list_students.php" style="font-size:12px;color:#64748b;white-space:nowrap;">✕ Clear</a>
            <?php endif; ?>
        </form>
        <div class="filter-btns">
            <a href="list_students.php" class="filter-btn <?= !isset($_GET['status']) && !isset($_GET['room']) ? 'active' : '' ?>">All</a>
            <a href="list_students.php?status=1" class="filter-btn <?= (($_GET['status'] ?? '') == '1') ? 'active' : '' ?>">Active</a>
            <a href="list_students.php?status=0" class="filter-btn <?= (($_GET['status'] ?? '') == '0') ? 'active' : '' ?>">Inactive</a>
            <a href="list_students.php?room=unassigned" class="filter-btn <?= (($_GET['room'] ?? '') == 'unassigned') ? 'active' : '' ?>">Unassigned</a>
        </div>
    </div>

    <p class="total-count">Showing <strong><?= count($students) ?></strong> student(s)</p>

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
                    <td>
                        <a href="view_student.php?id=<?= $row['student_id'] ?>"
                           style="color:#1e293b;font-weight:500;text-decoration:none;">
                            <?= htmlspecialchars($row['full_name']) ?>
                        </a>
                    </td>
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
                           onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['full_name'])) ?>? This cannot be undone.')">
                           🗑️ Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p>No students found<?= (!empty($_GET['search']) || isset($_GET['status']) || isset($_GET['room'])) ? ' matching your search.' : '.' ?></p>
            <?php if (empty($_GET['search']) && !isset($_GET['status']) && !isset($_GET['room'])): ?>
                <a href="add_student.php" class="btn-add">➕ Add your first student</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $db = null; /* Close DB connection */ ?>
</body>
</html>
