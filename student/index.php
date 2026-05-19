<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['student_id'])) { header("Location: login.php"); exit(); }

$sid = (int) $_SESSION['student_id'];

// Student + room
$stmt = $db->prepare("
    SELECT s.*, r.room_number, r.floor, r.room_type, r.capacity, r.price_per_month, r.ensuite_facility
    FROM students s
    LEFT JOIN rooms r ON r.room_id = s.room_id
    WHERE s.student_id = ?
");
$stmt->execute([$sid]);
$s = $stmt->fetch();

// Fee summary
$fq = $db->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total,
        COALESCE(SUM(CASE WHEN is_paid IS NOT NULL THEN amount ELSE 0 END), 0) AS paid,
        COALESCE(SUM(CASE WHEN is_paid IS NULL THEN amount ELSE 0 END), 0) AS unpaid
    FROM fees WHERE student_id = ?
");
$fq->execute([$sid]);
$fees = $fq->fetch();

// Recent fees (3)
$rf = $db->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY due_date DESC LIMIT 3");
$rf->execute([$sid]);
$recentFees = $rf->fetchAll();

// Recent maintenance for room (3)
$recentMaint = [];
if ($s['room_id']) {
    $mq = $db->prepare("SELECT * FROM maintenance WHERE room_id = ? ORDER BY date_reported DESC LIMIT 3");
    $mq->execute([$s['room_id']]);
    $recentMaint = $mq->fetchAll();
}

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Student Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<div class="page">

    <div class="page-title">
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $s['full_name'])[0]) ?></h1>
        <p>Here's a quick overview of your hostel account.</p>
    </div>

    <!-- Fee summary stats -->
    <div class="stat-row">
        <div class="stat-box">
            <div class="s-label">Total Fees</div>
            <div class="s-value"><?= number_format($fees['total'], 0) ?> kr.</div>
            <div class="s-sub">All time</div>
        </div>
        <div class="stat-box green">
            <div class="s-label">Paid</div>
            <div class="s-value"><?= number_format($fees['paid'], 0) ?> kr.</div>
            <div class="s-sub">Cleared</div>
        </div>
        <div class="stat-box amber">
            <div class="s-label">Outstanding</div>
            <div class="s-value"><?= number_format($fees['unpaid'], 0) ?> kr.</div>
            <div class="s-sub">Remaining</div>
        </div>
    </div>

    <!-- Profile + Room -->
    <div class="grid-2">

        <!-- Profile -->
        <div class="card">
            <div class="card-head">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    My Profile
                </h2>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <li><span class="lbl">Full Name</span>   <span class="val"><?= htmlspecialchars($s['full_name']) ?></span></li>
                    <li><span class="lbl">Student No.</span> <span class="val"><?= htmlspecialchars($s['student_number']) ?></span></li>
                    <li><span class="lbl">Email</span>       <span class="val"><?= htmlspecialchars($s['email']) ?></span></li>
                    <li><span class="lbl">Phone</span>       <span class="val"><?= htmlspecialchars($s['phone'] ?? '—') ?></span></li>
                    <li><span class="lbl">Date of Birth</span>
                        <span class="val"><?= $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : '—' ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Room -->
        <div class="card">
            <div class="card-head">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    My Room
                </h2>
                <?php if ($s['room_id']): ?>
                    <a href="room_request.php" style="font-size:0.78rem;color:#7c3aed;text-decoration:none;font-weight:600;">Request Change →</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($s['room_id']): ?>
                    <ul class="info-list">
                        <li><span class="lbl">Room</span>        <span class="val"><span class="badge b-blue"><?= htmlspecialchars($s['room_number']) ?></span></span></li>
                        <li><span class="lbl">Floor</span>       <span class="val">Floor <?= htmlspecialchars($s['floor']) ?></span></li>
                        <li><span class="lbl">Type</span>        <span class="val"><?= ucfirst($s['room_type']) ?></span></li>
                        <li><span class="lbl">Capacity</span>    <span class="val"><?= $s['capacity'] ?> person(s)</span></li>
                        <li><span class="lbl">Ensuite</span>     <span class="val">
                            <?php if ($s['ensuite_facility']): ?><span class="badge b-green">Yes</span>
                            <?php else: ?><span class="badge b-gray">Shared</span><?php endif; ?>
                        </span></li>
                        <li><span class="lbl">Monthly Rate</span><span class="val" style="color:#7c3aed;font-weight:700"><?= number_format($s['price_per_month'], 2) ?> kr.</span></li>
                    </ul>
                <?php else: ?>
                    <div class="notice" style="margin:0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        No room allocated yet. Contact the hostel office.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Fees -->
    <div class="card">
        <div class="card-head">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                Recent Fees
            </h2>
            <a href="fees.php" style="font-size:0.78rem;color:#7c3aed;text-decoration:none;font-weight:600;">View all →</a>
        </div>
        <?php if (empty($recentFees)): ?>
            <div class="no-rows">No fee records yet.</div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Receipt</th><th>Type</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentFees as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['receipt_number']) ?></td>
                    <td><?= ucfirst($f['fee_type']) ?></td>
                    <td><?= number_format($f['amount'], 2) ?> kr.</td>
                    <td><?= date('d M Y', strtotime($f['due_date'])) ?></td>
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

    <!-- Recent Maintenance -->
    <div class="card">
        <div class="card-head">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
                Recent Maintenance
            </h2>
            <a href="maintenance.php" style="font-size:0.78rem;color:#7c3aed;text-decoration:none;font-weight:600;">View all →</a>
        </div>
        <?php if (!$s['room_id'] || empty($recentMaint)): ?>
            <div class="no-rows"><?= !$s['room_id'] ? 'No room allocated.' : 'No maintenance records for your room.' ?></div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Ticket</th><th>Description</th><th>Reported</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentMaint as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['ticket_number']) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth($m['description'] ?? '—', 0, 55, '…')) ?></td>
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

</div>
</body>
</html>
<?php $db = null; ?>
