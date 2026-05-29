<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['student_id'])) { header("Location: login.php"); exit(); }

$sid    = (int) $_SESSION['student_id'];
$filter = $_GET['f'] ?? '';

$sql    = "SELECT * FROM fees WHERE student_id = ?";
$params = [$sid];
if ($filter === 'paid')   { $sql .= " AND is_paid IS NOT NULL"; }
if ($filter === 'unpaid') { $sql .= " AND is_paid IS NULL"; }
$sql .= " ORDER BY due_date DESC";

$stmt = $db->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

$sq = $db->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total,
        COALESCE(SUM(CASE WHEN is_paid IS NOT NULL THEN amount ELSE 0 END), 0) AS paid,
        COALESCE(SUM(CASE WHEN is_paid IS NULL     THEN amount ELSE 0 END), 0) AS unpaid,
        COALESCE(SUM(CASE WHEN is_paid IS NULL AND due_date < CURDATE() THEN amount ELSE 0 END), 0) AS overdue
    FROM fees WHERE student_id = ?
");
$sq->execute([$sid]);
$sum = $sq->fetch();

$activePage = 'fees';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fees — Student Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="page">

    <div class="page-title">
        <h1>My Fees</h1>
        <p>All fee records linked to your student account</p>
    </div>

    <div class="stat-row">
        <div class="stat-box">
            <div class="s-label">Total</div>
            <div class="s-value"><?= number_format($sum['total'], 0) ?> kr.</div>
        </div>
        <div class="stat-box green">
            <div class="s-label">Paid</div>
            <div class="s-value"><?= number_format($sum['paid'], 0) ?> kr.</div>
        </div>
        <div class="stat-box <?= $sum['overdue'] > 0 ? 'red' : 'amber' ?>">
            <div class="s-label">Outstanding</div>
            <div class="s-value"><?= number_format($sum['unpaid'], 0) ?> kr.</div>
            <?php if ($sum['overdue'] > 0): ?>
                <div class="s-sub"><?= number_format($sum['overdue'], 0) ?> kr. overdue</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="fees.php"          class="<?= $filter === ''       ? 'on' : '' ?>">All</a>
        <a href="fees.php?f=paid"   class="<?= $filter === 'paid'   ? 'on' : '' ?>">Paid</a>
        <a href="fees.php?f=unpaid" class="<?= $filter === 'unpaid' ? 'on' : '' ?>">Unpaid / Overdue</a>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <div class="no-rows">No records found.</div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Receipt #</th><th>Type</th><th>Amount</th><th>Due Date</th><th>Paid On</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['receipt_number']) ?></strong></td>
                    <td><?= ucfirst($f['fee_type']) ?></td>
                    <td><?= number_format($f['amount'], 2) ?> kr.</td>
                    <td><?= date('d M Y', strtotime($f['due_date'])) ?></td>
                    <td><?= $f['is_paid'] ? date('d M Y', strtotime($f['is_paid'])) : '—' ?></td>
                    <td>
                        <?php if ($f['is_paid']): ?>
                            <span class="badge b-green">Paid</span>
                        <?php elseif ($f['due_date'] < date('Y-m-d')): ?>
                            <span class="badge b-red">Overdue</span>
                        <?php else: ?>
                            <span class="badge b-amber">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
<?php $db = null; ?>
