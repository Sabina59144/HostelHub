<?php
/* ════════════════════════════════════════════════════════════════════════════
   STUDENT PORTAL - FEE MANAGEMENT
   
   Purpose: Student-facing view to check their fees and payment status
   - View all personal fees
   - See payment status and due dates
   - Filter fees by status (paid/unpaid/overdue)
   - Search and find specific fees
   - Calculate late fines automatically
   - Print fee records
════════════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . "/../includes/db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guard: Redirect to login if not authenticated
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

/**
 * Fetch student details from database
 * Validates that student exists and is active
 */
$stmtStudent = $db->prepare("
    SELECT * FROM students WHERE student_id = ? AND status = 1
");
$stmtStudent->execute([$student_id]);
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

// If student not found or inactive, logout
if (!$student) {
    session_destroy();
    header("Location: student_login.php");
    exit;
}

/* ── FILTER VARIABLES ───────────────────────────────– */
$search       = trim($_GET['search'] ?? '');     // Search by receipt or fee type
$filterStatus = $_GET['filter'] ?? '';           // paid | unpaid | overdue

/**
 * Fetch student fees with dynamic filtering
 * Only shows active fees for the logged-in student
 */
$sql = "
    SELECT * FROM fees
    WHERE student_id = ? AND is_active = 1
";
$params = [$student_id];

// Add search filter
if (!empty($search)) {
    $sql .= " AND (receipt_number LIKE ? OR fee_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add status filter
if ($filterStatus === 'paid') {
    $sql .= " AND is_paid = 1";
} elseif ($filterStatus === 'unpaid') {
    $sql .= " AND is_paid = 0 AND due_date >= CURDATE()";
} elseif ($filterStatus === 'overdue') {
    $sql .= " AND is_paid = 0 AND due_date < CURDATE()";
}

$sql .= " ORDER BY created_at DESC";

$stmtFees = $db->prepare($sql);
$stmtFees->execute($params);
$fees = $stmtFees->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime();

/* ── CALCULATE SUMMARY TOTALS ───────────────────────– */
$totalFees    = 0;    // Total amount billed
$totalFines   = 0;    // Total fines accrued
$totalPaid    = 0;    // Total amount paid
$totalUnpaid  = 0;    // Total amount unpaid
$countOverdue = 0;    // Count of overdue fees

/**
 * Process each fee to calculate totals and add computed fields
 * Determines payment status, calculates fines for overdue fees
 */
foreach ($fees as &$fee) {
    $dueDate    = new DateTime($fee['due_date']);
    $fineAmount = 0;
    $status     = 'unpaid';
    $statusLabel = 'Unpaid';
    $rowClass   = '';

    if ($fee['is_paid']) {
        // Fee has been paid
        $status      = 'paid';
        $statusLabel = 'Paid';
        $totalPaid  += $fee['amount'];
    } elseif ($today > $dueDate) {
        // Fee is overdue - calculate late fine
        $daysOverdue = $dueDate->diff($today)->days;
        $fineAmount  = min($daysOverdue * $fee['fine_rate'], $fee['fine_cap']);
        $status      = 'overdue';
        $statusLabel = 'Overdue';
        $rowClass    = 'late-row';
        $countOverdue++;
        $totalFines += $fineAmount;
        $totalUnpaid += $fee['amount'] + $fineAmount;
    } else {
        // Fee is unpaid but not yet due
        $totalUnpaid += $fee['amount'];
    }

    // Store calculated values in fee array
    $fee['_fine_amount']  = $fineAmount;
    $fee['_total_due']    = $fee['amount'] + $fineAmount;
    $fee['_status']       = $status;
    $fee['_status_label'] = $statusLabel;
    $fee['_row_class']    = $rowClass;
    
    // Add to total fees
    $totalFees += $fee['amount'];
}
unset($fee);

// Grand total is what student still owes
$grandTotal = $totalUnpaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fees — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0e1117;
            --surface: #161b27;
            --card: #1c2235;
            --border: #2a3148;
            --accent: #4f7aff;
            --success: #22d3a5;
            --warning: #fbbf24;
            --danger: #f87171;
            --text: #e8eaf6;
            --muted: #8892b0;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand { 
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem; 
            font-weight: 700; 
            color: var(--text);
        }
        
        .brand span { 
            color: var(--accent);
            opacity: 0.85; 
        }

        .topbar .right { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            font-size: 0.875rem; 
        }

        .student-badge {
            background: rgba(79, 122, 255, 0.15);
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .logout-btn {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
            padding: 7px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .logout-btn:hover { 
            background: rgba(248, 113, 113, 0.25);
        }

        /* ── PAGE BODY ── */
        .page { 
            max-width: 1100px; 
            margin: 0 auto; 
            padding: 28px 20px 48px; 
        }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 28px;
        }

        /* ── STUDENT INFO STRIP ── */
        .student-info {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            border-left: 5px solid var(--accent);
        }

        .student-info .avatar {
            width: 54px; height: 54px;
            background: var(--accent);
            border-radius: 50%;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.5rem; 
            color: white; 
            flex-shrink: 0;
        }

        .student-info .details { flex: 1; }
        .student-info .details h3 { font-size: 1.1rem; color: var(--text); }
        .student-info .details p { font-size: 0.85rem; color: var(--muted); margin-top: 2px; }

        .student-info .meta {
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap;
        }

        .meta-item { text-align: center; }
        .meta-item .label { 
            font-size: 0.72rem; 
            text-transform: uppercase; 
            color: var(--muted); 
            font-weight: 600; 
            letter-spacing: 0.05em; 
        }
        .meta-item .value { 
            font-size: 0.92rem; 
            font-weight: 600; 
            color: var(--text); 
            margin-top: 2px; 
        }

        /* ── SUMMARY CARDS ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            border-top: 4px solid var(--border);
            transition: transform 0.15s;
        }

        .summary-card:hover { transform: translateY(-2px); }
        .summary-card.blue  { border-top-color: var(--accent); }
        .summary-card.green { border-top-color: var(--success); }
        .summary-card.red   { border-top-color: var(--danger); }
        .summary-card.amber { border-top-color: var(--warning); }

        .summary-card .s-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .summary-card .s-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .summary-card .s-sub {
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── TABLE WRAP ── */
        .table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-header h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }

        .table-header p {
            font-size: 0.85rem;
            color: var(--muted);
            margin: 0;
        }

        /* FILTER ROW */
        .filter-row {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 12px 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-row form {
            display: flex;
            gap: 8px;
            flex: 1;
            min-width: 280px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-row input {
            flex: 1;
            min-width: 150px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: var(--card);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.15s;
        }

        .filter-row input:focus {
            border-color: var(--accent);
        }

        .filter-row select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: var(--card);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            outline: none;
            cursor: pointer;
            transition: border-color 0.15s;
        }

        .filter-row select:focus {
            border-color: var(--accent);
        }

        .btn-search {
            padding: 8px 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: background 0.2s;
        }

        .btn-search:hover {
            background: #3d68e8;
        }

        .btn-clear {
            padding: 8px 14px;
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-clear:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* TABLE */
        .table-scroll {
            overflow-x: auto;
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-wrap thead {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .table-wrap th {
            padding: 11px 15px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--muted);
            text-align: left;
            white-space: nowrap;
        }

        .table-wrap td {
            padding: 12px 15px;
            font-size: 13px;
            border-bottom: 1px solid rgba(42, 49, 72, 0.5);
            vertical-align: middle;
        }

        .table-wrap tbody tr:last-child td {
            border-bottom: none;
        }

        .table-wrap tbody tr:hover td {
            background: rgba(79, 122, 255, 0.04);
        }

        .table-wrap tbody tr.late-row {
            background: rgba(248, 113, 113, 0.04);
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge.paid {
            background: rgba(34, 211, 165, 0.15);
            color: var(--success);
        }

        .badge.unpaid {
            background: rgba(251, 191, 36, 0.15);
            color: var(--warning);
        }

        .badge.overdue {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
        }

        .fee-type-badge {
            display: inline-block;
            background: rgba(79, 122, 255, 0.15);
            color: #93b4ff;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .fine-amount {
            color: var(--danger);
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        .amount {
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
        }

        .total-amount {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--accent);
        }

        /* EMPTY STATE */
        .empty {
            padding: 48px 32px;
            text-align: center;
            color: var(--muted);
        }

        .empty .icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
        }

        .empty h3 {
            font-size: 1.1rem;
            color: var(--text);
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 0.875rem;
            margin-top: 6px;
        }

        /* ── NOTICE BANNER ── */
        .overdue-notice {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-left: 5px solid var(--danger);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.875rem;
            color: var(--danger);
        }

        .overdue-notice strong { 
            display: block; 
            margin-bottom: 4px; 
            font-size: 0.95rem; 
        }

        .readonly-badge {
            background: rgba(251, 191, 36, 0.15);
            color: var(--warning);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .print-btn {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            font-family: 'Outfit', sans-serif;
        }

        .print-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* POLICY NOTE */
        .policy-note {
            margin-top: 20px;
            font-size: 0.78rem;
            color: var(--muted);
            text-align: center;
            padding: 12px;
            background: var(--surface);
            border-radius: 10px;
        }

        @media print {
            .topbar, .print-btn, .readonly-badge, .filter-row { display: none; }
            body { background: var(--bg); }
            .page { padding: 0; }
            .summary-card, .table-wrap, .student-info { border: 1px solid var(--border); }
        }

        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .student-info { flex-direction: column; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
            .filter-row { flex-direction: column; }
            .filter-row form { width: 100%; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="right">
        <div class="student-badge">🎓 <?= htmlspecialchars($student_name) ?></div>
        <a href="student_logout.php" class="logout-btn">🚪 Sign Out</a>
    </div>
</div>

<div class="page">
    <div class="page-title">💳 My Fee Records</div>
    <div class="page-subtitle">View your hostel fees, payment status, and outstanding balances. Contact admin for questions.</div>

    <!-- STUDENT INFO STRIP -->
    <div class="student-info">
        <div class="avatar">🎓</div>
        <div class="details">
            <h3><?= htmlspecialchars($student['full_name']) ?></h3>
            <p><?= htmlspecialchars($student['email'] ?? 'No email') ?></p>
        </div>
        <div class="meta">
            <div class="meta-item">
                <div class="label">Student ID</div>
                <div class="value"><?= htmlspecialchars($student['student_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Total Records</div>
                <div class="value"><?= count($fees) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Overdue Fees</div>
                <div class="value" style="color:<?= $countOverdue > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= $countOverdue ?>
                </div>
            </div>
        </div>
    </div>

    <!-- OVERDUE NOTICE: Alert if fees are overdue -->
    <?php if ($countOverdue > 0): ?>
    <div class="overdue-notice">
        <strong>⚠️ You have <?= $countOverdue ?> overdue fee<?= $countOverdue > 1 ? 's' : '' ?></strong>
        Late fines are being applied automatically. Please settle outstanding balances as soon as possible.
        Total fines accumulated: <strong>£<?= number_format($totalFines, 2) ?></strong>
    </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS: Key financial metrics -->
    <div class="summary-grid">
        <div class="summary-card blue">
            <div class="s-label">Total Fees Charged</div>
            <div class="s-value">£<?= number_format($totalFees, 2) ?></div>
            <div class="s-sub"><?= count($fees) ?> fee record<?= count($fees) != 1 ? 's' : '' ?></div>
        </div>
        <div class="summary-card green">
            <div class="s-label">Total Paid</div>
            <div class="s-value">£<?= number_format($totalPaid, 2) ?></div>
            <div class="s-sub">Amount settled</div>
        </div>
        <div class="summary-card red">
            <div class="s-label">Total Outstanding</div>
            <div class="s-value">£<?= number_format($totalUnpaid, 2) ?></div>
            <div class="s-sub">Including fines</div>
        </div>
        <div class="summary-card amber">
            <div class="s-label">Fines Accrued</div>
            <div class="s-value">£<?= number_format($totalFines, 2) ?></div>
            <div class="s-sub">From overdue fees</div>
        </div>
    </div>

    <!-- FEE TABLE: Detailed list of all fees -->
    <div class="table-wrap">
        <div class="table-header">
            <div>
                <h3>Fee Breakdown</h3>
                <p>All your hostel fee records</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <a href="#" onclick="window.print();return false;" class="print-btn">🖨️ Print</a>
                <span class="readonly-badge">👁️ View Only</span>
            </div>
        </div>

        <!-- FILTER ROW: Search and status filter -->
        <div class="filter-row">
            <form method="GET">
                <input type="text" name="search" placeholder="Search receipt or fee type…" 
                       value="<?= htmlspecialchars($search) ?>">
                
                <!-- Status filter dropdown -->
                <select name="filter" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="paid"    <?= $filterStatus==='paid'    ? 'selected':'' ?>>✅ Paid</option>
                    <option value="unpaid"  <?= $filterStatus==='unpaid'  ? 'selected':'' ?>>⏳ Unpaid</option>
                    <option value="overdue" <?= $filterStatus==='overdue' ? 'selected':'' ?>>⚠️ Overdue</option>
                </select>
                
                <button type="submit" class="btn-search">🔍 Search</button>
                
                <!-- Clear filters button -->
                <?php if ($search || $filterStatus): ?>
                <a href="student_portal.php" class="btn-clear">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- FEES TABLE or EMPTY STATE -->
        <?php if (empty($fees)): ?>
        <!-- Empty state: No fees found -->
        <div class="empty">
            <span class="icon">📋</span>
            <h3>No fee records found</h3>
            <p>Your fee records will appear here once added by the admin.</p>
        </div>
        <?php else: ?>
        <!-- Fees table with scroll for mobile -->
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Fee Type</th>
                    <th>Amount</th>
                    <th>Late Fine</th>
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
                <!-- Receipt Number: Unique identifier -->
                <td style="font-family:'DM Mono',monospace;font-size:12px;color:var(--muted);">
                    <?= htmlspecialchars($fee['receipt_number']) ?>
                </td>
                
                <!-- Fee Type: Type of charge (rent, deposit, utility, etc) -->
                <td>
                    <span class="fee-type-badge"><?= ucfirst($fee['fee_type']) ?></span>
                </td>
                
                <!-- Amount: Base fee amount -->
                <td class="amount">£<?= number_format($fee['amount'], 2) ?></td>
                
                <!-- Late Fine: Calculated fine for overdue fees -->
                <td>
                    <?php if ($fee['_fine_amount'] > 0): ?>
                        <span class="fine-amount">+£<?= number_format($fee['_fine_amount'], 2) ?></span>
                        <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">
                            £<?= $fee['fine_rate'] ?>/day · max £<?= $fee['fine_cap'] ?>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                
                <!-- Total Due: Amount + Fine -->
                <td class="total-amount">£<?= number_format($fee['_total_due'], 2) ?></td>
                
                <!-- Due Date: When payment is due -->
                <td>
                    <?php
                    $dueDate = new DateTime($fee['due_date']);
                    echo $dueDate->format('d M Y');
                    if ($fee['_status'] === 'overdue') {
                        $days = $dueDate->diff($today)->days;
                        echo "<div style='font-size:0.72rem;color:var(--danger);margin-top:2px;font-weight:600;'>{$days} days late</div>";
                    }
                    ?>
                </td>
                
                <!-- Status: Paid/Unpaid/Overdue badge -->
                <td>
                    <span class="badge <?= $fee['_status'] ?>">
                        <?= $fee['_status'] === 'overdue' ? '⚠️ ' : '' ?>
                        <?= $fee['_status_label'] ?>
                    </span>
                </td>
                
                <!-- Payment Method: How it was/will be paid -->
                <td>
                    <?php if ($fee['payment_method']): ?>
                        <?php
                        $icons = ['cash' => '💵', 'bank' => '🏦', 'mobile' => '📱'];
                        $m = $fee['payment_method'];
                        echo ($icons[$m] ?? '') . ' ' . ucfirst(htmlspecialchars($m));
                        ?>
                    <?php else: ?>
                        <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                
                <!-- Paid At: Timestamp when fee was paid -->
                <td style="font-size:12px;color:var(--muted);">
                    <?= $fee['paid_at'] ? (new DateTime($fee['paid_at']))->format('d M Y') : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- POLICY NOTE: Late fine policy information -->
    <div class="policy-note">
        💡 <strong>Late Fine Policy:</strong> £0.50/day · maximum £15.00 per fee · Applied automatically on overdue fees.
        &nbsp;&nbsp;|&nbsp;&nbsp; Contact hostel admin for disputes or payment confirmation.
    </div>

</div>

</body>
</html>
