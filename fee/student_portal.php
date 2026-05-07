<?php
require_once(__DIR__ . "/../includes/db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guard: must be a logged-in student
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Fetch student details
$stmtStudent = $db->prepare("
    SELECT * FROM students WHERE student_id = ? AND status = 1
");
$stmtStudent->execute([$student_id]);
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: student_login.php");
    exit;
}

// Fetch this student's fees only
$stmtFees = $db->prepare("
    SELECT * FROM fees
    WHERE student_id = ? AND is_active = 1
    ORDER BY created_at DESC
");
$stmtFees->execute([$student_id]);
$fees = $stmtFees->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime();

// Calculate summary totals
$totalFees    = 0;
$totalFines   = 0;
$totalPaid    = 0;
$totalUnpaid  = 0;
$countOverdue = 0;

foreach ($fees as &$fee) {
    $dueDate    = new DateTime($fee['due_date']);
    $fineAmount = 0;
    $status     = 'unpaid';
    $statusLabel = 'Unpaid';
    $rowClass   = '';

    if ($fee['is_paid']) {
        $status      = 'paid';
        $statusLabel = 'Paid';
        $totalPaid  += $fee['amount'];
    } elseif ($today > $dueDate) {
        $daysOverdue = $dueDate->diff($today)->days;
        $fineAmount  = min($daysOverdue * $fee['fine_rate'], $fee['fine_cap']);
        $status      = 'overdue';
        $statusLabel = 'Overdue';
        $rowClass    = 'late-row';
        $countOverdue++;
        $totalFines += $fineAmount;
        $totalUnpaid += $fee['amount'] + $fineAmount;
    } else {
        $totalUnpaid += $fee['amount'];
    }

    $fee['_fine_amount']  = $fineAmount;
    $fee['_total_due']    = $fee['amount'] + $fineAmount;
    $fee['_status']       = $status;
    $fee['_status_label'] = $statusLabel;
    $fee['_row_class']    = $rowClass;
    $totalFees += $fee['amount'];
}
unset($fee);

$grandTotal = $totalUnpaid; // what they still owe
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fees — HostelHub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            color: #1f2937;
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: white;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .topbar .brand { font-size: 1.15rem; font-weight: 700; }
        .topbar .brand span { opacity: 0.75; font-weight: 400; font-size: 0.85rem; margin-left: 8px; }

        .topbar .right { display: flex; align-items: center; gap: 14px; font-size: 0.875rem; }

        .topbar .student-badge {
            background: rgba(255,255,255,0.15);
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 600;
        }

        .topbar a.logout-btn {
            background: rgba(220,38,38,0.8);
            color: white;
            padding: 7px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .topbar a.logout-btn:hover { background: #dc2626; }

        /* ── PAGE BODY ── */
        .page { max-width: 1100px; margin: 0 auto; padding: 28px 20px 48px; }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 28px;
        }

        /* ── STUDENT INFO STRIP ── */
        .student-info {
            background: white;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 5px solid #3b82f6;
        }

        .student-info .avatar {
            width: 54px; height: 54px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white; flex-shrink: 0;
        }

        .student-info .details { flex: 1; }
        .student-info .details h3 { font-size: 1.1rem; color: #111827; }
        .student-info .details p { font-size: 0.85rem; color: #6b7280; margin-top: 2px; }

        .student-info .meta {
            display: flex; gap: 16px; flex-wrap: wrap;
        }

        .meta-item { text-align: center; }
        .meta-item .label { font-size: 0.72rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; letter-spacing: 0.05em; }
        .meta-item .value { font-size: 0.92rem; font-weight: 600; color: #374151; margin-top: 2px; }

        /* ── SUMMARY CARDS ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-top: 4px solid #e5e7eb;
            transition: transform 0.15s;
        }

        .summary-card:hover { transform: translateY(-2px); }
        .summary-card.blue  { border-top-color: #3b82f6; }
        .summary-card.green { border-top-color: #22c55e; }
        .summary-card.red   { border-top-color: #ef4444; }
        .summary-card.amber { border-top-color: #f59e0b; }

        .summary-card .s-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9ca3af;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .summary-card .s-value {
            font-size: 1.7rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .summary-card .s-sub {
            font-size: 0.78rem;
            color: #6b7280;
            margin-top: 5px;
        }

        /* ── TABLE ── */
        .table-wrap {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .table-header {
            padding: 18px 22px 14px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h3 { font-size: 1rem; color: #111827; font-weight: 700; }
        .table-header p  { font-size: 0.82rem; color: #9ca3af; margin-top: 2px; }

        .readonly-badge {
            background: #f3f4f6;
            color: #6b7280;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .table-scroll { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f9fafb;
            color: #374151;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f9fafb; }

        tbody tr.late-row { background: #fff5f5 !important; border-left: 4px solid #ef4444; }
        tbody tr.late-row td { color: #7f1d1d; }

        .badge {
            display: inline-block;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.paid    { background: #dcfce7; color: #166534; }
        .badge.unpaid  { background: #fef3c7; color: #92400e; }
        .badge.overdue { background: #fee2e2; color: #991b1b; }

        .fee-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #eff6ff;
            color: #1d4ed8;
            text-transform: capitalize;
        }

        .amount { font-weight: 700; color: #111827; }
        .fine-amount { color: #dc2626; font-weight: 600; }
        .total-amount { font-weight: 800; color: #1e3a8a; }

        /* ── EMPTY STATE ── */
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty .icon { font-size: 3rem; display: block; margin-bottom: 12px; }
        .empty h3 { font-size: 1.1rem; color: #6b7280; }
        .empty p  { font-size: 0.875rem; margin-top: 6px; }

        /* ── NOTICE BANNER ── */
        .overdue-notice {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-left: 5px solid #ef4444;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.875rem;
            color: #7f1d1d;
        }

        .overdue-notice strong { display: block; margin-bottom: 4px; font-size: 0.95rem; }

        /* ── PRINT ── */
        .print-btn {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .print-btn:hover { background: #e5e7eb; }

        @media print {
            .topbar, .print-btn, .readonly-badge { display: none; }
            body { background: white; }
            .page { padding: 0; }
            .summary-card, .table-wrap, .student-info { box-shadow: none; border: 1px solid #e5e7eb; }
        }

        @media (max-width: 600px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .student-info { flex-direction: column; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="brand">🏠 HostelHub <span>Student Fee Portal</span></div>
    <div class="right">
        <div class="student-badge">🎓 <?= htmlspecialchars($student_name) ?></div>
        <a href="student_logout.php" class="logout-btn">🚪 Sign Out</a>
    </div>
</div>

<div class="page">
    <div class="page-title">My Fee Records</div>
    <div class="page-subtitle">Read-only view of your hostel fee history. Contact admin for changes.</div>

    <!-- STUDENT INFO STRIP -->
    <div class="student-info">
        <div class="avatar">🎓</div>
        <div class="details">
            <h3><?= htmlspecialchars($student['full_name']) ?></h3>
            <p><?= htmlspecialchars($student['email']) ?></p>
        </div>
        <div class="meta">
            <div class="meta-item">
                <div class="label">Student No.</div>
                <div class="value"><?= htmlspecialchars($student['student_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Total Records</div>
                <div class="value"><?= count($fees) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Overdue</div>
                <div class="value" style="color:<?= $countOverdue > 0 ? '#dc2626' : '#16a34a' ?>">
                    <?= $countOverdue ?>
                </div>
            </div>
        </div>
    </div>

    <!-- OVERDUE NOTICE -->
    <?php if ($countOverdue > 0): ?>
    <div class="overdue-notice">
        <strong>⚠️ You have <?= $countOverdue ?> overdue fee<?= $countOverdue > 1 ? 's' : '' ?></strong>
        Late fines are being applied. Please settle outstanding balances as soon as possible.
        Total fines accumulated: <strong>$<?= number_format($totalFines, 2) ?></strong>
    </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="summary-card blue">
            <div class="s-label">Total Fees Charged</div>
            <div class="s-value">$<?= number_format($totalFees, 2) ?></div>
            <div class="s-sub"><?= count($fees) ?> fee record<?= count($fees) != 1 ? 's' : '' ?></div>
        </div>
        <div class="summary-card green">
            <div class="s-label">Total Paid</div>
            <div class="s-value">$<?= number_format($totalPaid, 2) ?></div>
            <div class="s-sub">Amount settled</div>
        </div>
        <div class="summary-card red">
            <div class="s-label">Total Outstanding</div>
            <div class="s-value">$<?= number_format($totalUnpaid, 2) ?></div>
            <div class="s-sub">Including fines</div>
        </div>
        <div class="summary-card amber">
            <div class="s-label">Fines Accrued</div>
            <div class="s-value">$<?= number_format($totalFines, 2) ?></div>
            <div class="s-sub">From overdue fees</div>
        </div>
    </div>

    <!-- FEE TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <div>
                <h3>Fee Breakdown</h3>
                <p>All your hostel fee records</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <a href="#" onclick="window.print();return false;" class="print-btn">🖨️ Print</a>
                <span class="readonly-badge">👁 View Only</span>
            </div>
        </div>

        <?php if (empty($fees)): ?>
        <div class="empty">
            <span class="icon">📋</span>
            <h3>No fee records found</h3>
            <p>Your fee records will appear here once added by the admin.</p>
        </div>
        <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Fee Type</th>
                    <th>Amount</th>
                    <th>Fine</th>
                    <th>Total Due</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fees as $fee): ?>
            <tr class="<?= $fee['_row_class'] ?>">
                <td style="font-family:monospace;font-size:0.82rem;">
                    <?= htmlspecialchars($fee['receipt_number']) ?>
                </td>
                <td>
                    <span class="fee-type-badge"><?= ucfirst($fee['fee_type']) ?></span>
                </td>
                <td class="amount">$<?= number_format($fee['amount'], 2) ?></td>
                <td>
                    <?php if ($fee['_fine_amount'] > 0): ?>
                        <span class="fine-amount">+$<?= number_format($fee['_fine_amount'], 2) ?></span>
                        <div style="font-size:0.72rem;color:#9ca3af;margin-top:2px;">
                            $<?= $fee['fine_rate'] ?>/day · max $<?= $fee['fine_cap'] ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#d1d5db;">—</span>
                    <?php endif; ?>
                </td>
                <td class="total-amount">$<?= number_format($fee['_total_due'], 2) ?></td>
                <td>
                    <?php
                    $dueDate = new DateTime($fee['due_date']);
                    echo $dueDate->format('d M Y');
                    if ($fee['_status'] === 'overdue') {
                        $days = $dueDate->diff($today)->days;
                        echo "<div style='font-size:0.72rem;color:#dc2626;margin-top:2px;'>{$days} days overdue</div>";
                    }
                    ?>
                </td>
                <td>
                    <span class="badge <?= $fee['_status'] ?>">
                        <?= $fee['_status'] === 'overdue' ? '⚠️ ' : '' ?><?= $fee['_status_label'] ?>
                    </span>
                </td>
                <td>
                    <?php if ($fee['payment_method']): ?>
                        <?php
                        $icons = ['cash' => '💵', 'bank' => '🏦', 'mobile' => '📱'];
                        $m = $fee['payment_method'];
                        echo ($icons[$m] ?? '') . ' ' . ucfirst(htmlspecialchars($m));
                        ?>
                    <?php else: ?>
                        <span style="color:#d1d5db;">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.82rem;color:#6b7280;">
                    <?= $fee['paid_at'] ? (new DateTime($fee['paid_at']))->format('d M Y, H:i') : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- FINE POLICY NOTE -->
    <div style="margin-top:16px;font-size:0.78rem;color:#9ca3af;text-align:center;">
        💡 Late fine policy: $0.50/day · maximum $15.00 per fee · Applied automatically on overdue fees.
        &nbsp;&nbsp;|&nbsp;&nbsp; Contact hostel admin for disputes or payment confirmation.
    </div>

</div>
</body>
</html>
