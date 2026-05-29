<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['student_id'])) { header("Location: login.php"); exit(); }

$sid = (int) $_SESSION['student_id'];

// Get student room
$sq = $db->prepare("SELECT s.room_id, r.room_number FROM students s LEFT JOIN rooms r ON r.room_id = s.room_id WHERE s.student_id = ?");
$sq->execute([$sid]); $student = $sq->fetch();

$filter  = $_GET['f'] ?? '';
$records = [];

if ($student['room_id']) {
    $sql    = "SELECT * FROM maintenance WHERE room_id = ?";
    $params = [$student['room_id']];
    if ($filter === 'open')     { $sql .= " AND is_resolved = 0"; }
    if ($filter === 'resolved') { $sql .= " AND is_resolved = 1"; }
    $sql .= " ORDER BY date_reported DESC";
    $mq = $db->prepare($sql); $mq->execute($params);
    $records = $mq->fetchAll();
}

$activePage = 'maintenance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — Student Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<div class="page">

    <div class="page-title">
        <h1>Maintenance</h1>
        <p>Maintenance tickets raised for your room</p>
    </div>

    <?php if (!$student['room_id']): ?>
        <div class="notice">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            You don't have a room allocated yet — no maintenance records available.
        </div>
    <?php else: ?>

        <div style="margin-bottom:14px">
            <span class="badge b-blue" style="font-size:0.8rem;padding:5px 14px">
                Room <?= htmlspecialchars($student['room_number']) ?>
            </span>
        </div>

        <div class="filter-tabs">
            <a href="maintenance.php"            class="<?= $filter===''         ?'on':'' ?>">All</a>
            <a href="maintenance.php?f=open"     class="<?= $filter==='open'     ?'on':'' ?>">In Progress</a>
            <a href="maintenance.php?f=resolved" class="<?= $filter==='resolved' ?'on':'' ?>">Resolved</a>
        </div>

        <div class="card">
            <?php if (empty($records)): ?>
                <div class="no-rows">No maintenance records found.</div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th>Ticket #</th><th>Description</th><th>Assigned To</th><th>Reported</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['ticket_number']) ?></strong></td>
                        <td><?= htmlspecialchars($m['description'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['assigned_to']) ?></td>
                        <td><?= date('d M Y', strtotime($m['date_reported'])) ?></td>
                        <td>
                            <?php if ($m['is_resolved']): ?>
                                <span class="badge b-green">Resolved</span>
                            <?php else: ?>
                                <span class="badge b-amber">In Progress</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
</body>
</html>
<?php $db = null; ?>
