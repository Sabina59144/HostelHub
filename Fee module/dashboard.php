<?php
/* ── Auth & DB ───────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* ── Summary stats ───────────────────────────────── */
$stats = $db->query("
    SELECT
        COUNT(*)                                            AS total_records,
        COUNT(CASE WHEN is_paid = 1 THEN 1 END)            AS total_paid,
        COUNT(CASE WHEN is_paid = 0 THEN 1 END)            AS total_unpaid,
        COUNT(CASE WHEN is_paid = 0 AND due_date < CURDATE() THEN 1 END) AS overdue_count,
        COALESCE(SUM(amount), 0)                           AS total_amount,
        COALESCE(SUM(CASE WHEN is_paid = 1 THEN amount END), 0) AS amount_paid,
        COALESCE(SUM(CASE WHEN is_paid = 0 THEN amount END), 0) AS amount_unpaid
    FROM fees WHERE is_active = 1
")->fetch(PDO::FETCH_ASSOC);

/* ── Fee type breakdown ──────────────────────────── */
$byType = $db->query("
    SELECT fee_type,
           COUNT(*)        AS cnt,
           SUM(amount)     AS total,
           SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) AS paid_cnt
    FROM fees WHERE is_active = 1
    GROUP BY fee_type ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Recent fees (last 8) ────────────────────────── */
$recent = $db->query("
    SELECT f.*, s.full_name
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.is_active = 1
    ORDER BY f.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Overdue fees ────────────────────────────────── */
$overdueList = $db->query("
    SELECT f.*, s.full_name,
           DATEDIFF(CURDATE(), f.due_date) AS days_late,
           LEAST(DATEDIFF(CURDATE(), f.due_date) * f.fine_rate, f.fine_cap) AS fine_now
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.is_paid = 0 AND f.due_date < CURDATE() AND f.is_active = 1
    ORDER BY days_late DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Monthly collected (last 6 months) ───────────── */
$monthly = $db->query("
    SELECT DATE_FORMAT(paid_at, '%b %Y') AS month,
           SUM(amount) AS collected,
           COUNT(*)    AS count
    FROM fees
    WHERE is_paid = 1 AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
    ORDER BY MIN(paid_at) ASC
")->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime();
$collectedPct = $stats['total_amount'] > 0
    ? round(($stats['amount_paid'] / $stats['total_amount']) * 100)
    : 0;

// Totals including estimated fines on overdue
$totalFinesEstimate = 0;
foreach ($overdueList as $f) { $totalFinesEstimate += $f['fine_now']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Dashboard — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg:       #0e1117;
    --surface:  #161b27;
    --card:     #1c2235;
    --border:   #2a3148;
    --accent:   #4f7aff;
    --accent2:  #7c3aed;
    --success:  #22d3a5;
    --warning:  #fbbf24;
    --danger:   #f87171;
    --text:     #e8eaf6;
    --muted:    #8892b0;
    --faint:    #3a4260;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: 'Outfit', sans-serif; min-height: 100vh; }

/* TOPNAV */
.topnav {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 20px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.brand span { color: var(--accent); }
.nav-links { display: flex; gap: 4px; }
.nav-links a {
    padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500;
    color: var(--muted); text-decoration: none; transition: all 0.15s;
}
.nav-links a:hover { background: var(--card); color: var(--text); }
.nav-links a.active { background: var(--accent); color: #fff; }
.nav-right { display: flex; align-items: center; gap: 12px; }
.nav-user { font-size: 13px; color: var(--muted); }
.nav-user strong { color: var(--text); }

/* PAGE */
.page { max-width: 1280px; margin: 0 auto; padding: 32px; }

/* HERO HEADER */
.hero { margin-bottom: 32px; }
.hero h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; margin-bottom: 6px; }
.hero h1 span { color: var(--accent); }
.hero p { color: var(--muted); font-size: 14px; }

/* KPI GRID */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.kpi-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
    transition: border-color 0.2s;
}
.kpi-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: 14px 14px 0 0;
}
.kpi-card.blue::before { background: var(--accent); }
.kpi-card.green::before { background: var(--success); }
.kpi-card.amber::before { background: var(--warning); }
.kpi-card.red::before { background: var(--danger); }
.kpi-card:hover { border-color: var(--faint); }
.kpi-label { font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
.kpi-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; line-height: 1; }
.kpi-card.blue .kpi-value { color: var(--accent); }
.kpi-card.green .kpi-value { color: var(--success); }
.kpi-card.amber .kpi-value { color: var(--warning); }
.kpi-card.red .kpi-value { color: var(--danger); }
.kpi-sub { font-size: 12px; color: var(--muted); margin-top: 6px; }
.kpi-icon { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 36px; opacity: 0.06; }

/* SECTION GRID */
.main-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    margin-bottom: 20px;
}

/* CARDS */
.panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}
.panel-head {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.panel-head h3 { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 15px; }
.panel-head a { font-size: 12px; color: var(--accent); text-decoration: none; }
.panel-head a:hover { text-decoration: underline; }

/* PROGRESS BAR */
.collection-bar { padding: 20px 22px; }
.bar-meta { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 10px; color: var(--muted); }
.bar-meta strong { color: var(--text); font-size: 15px; }
.bar-track { height: 10px; background: var(--border); border-radius: 99px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--accent), var(--success)); transition: width 1s ease; }

/* SMALL TABLE */
.mini-table { width: 100%; border-collapse: collapse; }
.mini-table th {
    padding: 10px 16px; font-size: 10px; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase;
    color: var(--muted); text-align: left;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
}
.mini-table td {
    padding: 11px 16px; font-size: 13px;
    border-bottom: 1px solid rgba(42,49,72,0.5);
    vertical-align: middle;
}
.mini-table tr:last-child td { border-bottom: none; }
.mini-table tr:hover td { background: rgba(79,122,255,0.04); }

/* BADGES */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.b-paid    { background: rgba(34,211,165,0.15); color: var(--success); }
.b-unpaid  { background: rgba(251,191,36,0.15);  color: var(--warning); }
.b-overdue { background: rgba(248,113,113,0.15); color: var(--danger); }
.type-pill { background: rgba(79,122,255,0.15); color: #93b4ff; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }

/* OVERDUE ALERT */
.overdue-panel { background: rgba(248,113,113,0.07); border: 1px solid rgba(248,113,113,0.25); border-radius: 14px; overflow: hidden; }
.overdue-panel .panel-head { border-bottom: 1px solid rgba(248,113,113,0.2); }
.overdue-panel .panel-head h3 { color: var(--danger); }

/* FEE TYPE BARS */
.type-bar { padding: 6px 22px 16px; }
.type-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.type-row:last-child { margin-bottom: 0; }
.type-name { font-size: 12px; font-weight: 600; color: var(--muted); width: 60px; text-transform: capitalize; }
.type-track { flex: 1; height: 7px; background: var(--border); border-radius: 99px; overflow: hidden; }
.type-fill { height: 100%; border-radius: 99px; }
.fill-rent { background: var(--accent); }
.fill-deposit { background: var(--success); }
.fill-utility { background: var(--warning); }
.fill-fine { background: var(--danger); }
.fill-other { background: var(--faint); }
.fill-laundry { background: #a78bfa; }
.type-amt { font-size: 12px; font-family: 'DM Mono', monospace; color: var(--text); width: 70px; text-align: right; }

/* MONTHLY CHART */
.chart-bars { display: flex; align-items: flex-end; gap: 8px; padding: 16px 22px 8px; height: 120px; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; }
.bar-col .b { width: 100%; border-radius: 6px 6px 0 0; background: var(--accent); opacity: 0.7; transition: opacity 0.2s; min-height: 4px; }
.bar-col:hover .b { opacity: 1; }
.bar-col .lbl { font-size: 10px; color: var(--muted); white-space: nowrap; }

/* QUICK ACTION BUTTONS */
.action-strip { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.action-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    text-decoration: none; cursor: pointer; transition: all 0.15s;
    border: 1px solid transparent;
}
.action-btn.primary { background: var(--accent); color: #fff; }
.action-btn.primary:hover { background: #3d68e8; }
.action-btn.secondary { background: var(--card); color: var(--text); border-color: var(--border); }
.action-btn.secondary:hover { border-color: var(--accent); color: var(--accent); }
.action-btn.danger { background: rgba(248,113,113,0.1); color: var(--danger); border-color: rgba(248,113,113,0.3); }
.action-btn.danger:hover { background: rgba(248,113,113,0.2); }

/* AMOUNT TEXT */
.mono { font-family: 'DM Mono', monospace; font-size: 12px; }
.amount-val { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 500; }
.text-success { color: var(--success); }
.text-danger  { color: var(--danger); }
.text-warning { color: var(--warning); }
.text-muted   { color: var(--muted); }

/* EMPTY */
.empty-state { padding: 40px; text-align: center; color: var(--muted); }
.empty-state .icon { font-size: 28px; margin-bottom: 8px; }

/* RECEIPT MONO */
.rcpt { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--muted); }

/* TOGGLE BUTTONS (AJAX) */
.btn-pay-toggle {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px; font-size: 11px;
    font-weight: 700; cursor: pointer; border: none;
    font-family: 'Outfit', sans-serif; transition: all 0.2s; outline: none;
}
.btn-pay-toggle.state-pay   { background: rgba(34,211,165,0.15); color: var(--success); }
.btn-pay-toggle.state-pay:hover { background: rgba(34,211,165,0.3); }
.btn-pay-toggle.state-unpay { background: rgba(251,191,36,0.15);  color: var(--warning); }
.btn-pay-toggle.state-unpay:hover { background: rgba(251,191,36,0.3); }
.btn-pay-toggle:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { display: inline-block; width: 9px; height: 9px; border: 2px solid currentColor;
  border-top-color: transparent; border-radius: 50%; animation: spin 0.5s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* TOAST */
#toast { position: fixed; bottom: 28px; right: 28px; background: var(--card);
  border: 1px solid var(--border); border-radius: 12px; padding: 13px 20px;
  font-size: 13px; font-weight: 500; color: var(--text);
  box-shadow: 0 8px 32px rgba(0,0,0,0.4); z-index: 999;
  transform: translateY(80px); opacity: 0;
  transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
#toast.show { transform: translateY(0); opacity: 1; }
#toast.t-green { border-color: rgba(34,211,165,0.4); color: var(--success); }
#toast.t-amber { border-color: rgba(251,191,36,0.4);  color: var(--warning); }

@media (max-width: 1024px) {
    .main-grid { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .page { padding: 16px; }
    .kpi-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="nav-links">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="index.php">Fee Records</a>
        <?php if ($isAdmin): ?>
        <a href="add.php">Add Fee</a>
        <?php endif; ?>
        <a href="../students/index.php">Students</a>
        <a href="../rooms/index.php">Rooms</a>
        <a href="../maintenance/index.php">Maintenance</a>
    </div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Staff') ?></strong></span>
        <a href="../logout.php" class="action-btn danger" style="padding:7px 14px;font-size:12px;">Sign out</a>
    </div>
</nav>

<div class="page">

    <!-- HERO -->
    <div class="hero">
        <h1>Fee <span>Dashboard</span></h1>
        <p><?= $today->format('l, d F Y') ?> &mdash; Overview of all hostel fee activity</p>
    </div>

    <!-- QUICK ACTIONS (admin only) -->
    <?php if ($isAdmin): ?>
    <div class="action-strip">
        <a href="add.php" class="action-btn primary">＋ Add Fee Record</a>
        <a href="index.php" class="action-btn secondary">📋 All Records</a>
        <a href="index.php?filter=overdue" class="action-btn danger">⚠ View Overdue</a>
        <a href="index.php?filter=unpaid" class="action-btn secondary">💷 Unpaid Only</a>
        <a href="report.php" class="action-btn secondary">📊 Export Report</a>
    </div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Total Billed</div>
            <div class="kpi-value">£<?= number_format($stats['total_amount'], 0) ?></div>
            <div class="kpi-sub"><?= $stats['total_records'] ?> total fee records</div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Collected</div>
            <div class="kpi-value">£<?= number_format($stats['amount_paid'], 0) ?></div>
            <div class="kpi-sub"><?= $stats['total_paid'] ?> fees paid · <?= $collectedPct ?>% rate</div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-value">£<?= number_format($stats['amount_unpaid'], 0) ?></div>
            <div class="kpi-sub"><?= $stats['total_unpaid'] ?> fees pending</div>
        </div>
        <div class="kpi-card red">
            <div class="kpi-icon">⚠</div>
            <div class="kpi-label">Overdue Fees</div>
            <div class="kpi-value"><?= $stats['overdue_count'] ?></div>
            <div class="kpi-sub">Est. £<?= number_format($totalFinesEstimate, 2) ?> in fines</div>
        </div>
    </div>

    <!-- MAIN GRID: Recent + Sidebar -->
    <div class="main-grid">

        <!-- LEFT: Recent + Collection Rate -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Collection Rate -->
            <div class="panel">
                <div class="panel-head">
                    <h3>Collection Rate</h3>
                    <span style="font-size:12px;color:var(--success);font-weight:600;"><?= $collectedPct ?>% collected</span>
                </div>
                <div class="collection-bar">
                    <div class="bar-meta">
                        <span>£<?= number_format($stats['amount_paid'], 2) ?> collected</span>
                        <strong>£<?= number_format($stats['total_amount'], 2) ?> total billed</strong>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $collectedPct ?>%"></div>
                    </div>
                    <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;">
                        <?php foreach ($byType as $t): $pct = $t['cnt'] > 0 ? round(($t['paid_cnt']/$t['cnt'])*100) : 0; ?>
                        <span style="font-size:11px;color:var(--muted);background:var(--surface);padding:3px 9px;border-radius:6px;">
                            <?= ucfirst($t['fee_type']) ?> <?= $pct ?>%
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Monthly Chart -->
            <?php if (!empty($monthly)): ?>
            <div class="panel">
                <div class="panel-head"><h3>Monthly Collections</h3><a href="index.php">View all →</a></div>
                <?php
                $maxCol = max(array_column($monthly, 'collected')) ?: 1;
                ?>
                <div class="chart-bars">
                    <?php foreach ($monthly as $m):
                        $h = max(10, round(($m['collected'] / $maxCol) * 80));
                    ?>
                    <div class="bar-col">
                        <span style="font-size:10px;color:var(--accent);font-family:'DM Mono',monospace;">£<?= number_format($m['collected'],0) ?></span>
                        <div class="b" style="height:<?= $h ?>px;margin-top:auto;"></div>
                        <span class="lbl"><?= $m['month'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Fees -->
            <div class="panel">
                <div class="panel-head"><h3>Recent Fee Records</h3><a href="index.php">View all →</a></div>
                <?php if (empty($recent)): ?>
                <div class="empty-state"><div class="icon">📋</div>No fee records yet</div>
                <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Student</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $f):
                        $due = new DateTime($f['due_date']);
                        $isOverdue = !$f['is_paid'] && $today > $due;
                        $status = $f['is_paid'] ? 'paid' : ($isOverdue ? 'overdue' : 'unpaid');
                        $labels = ['paid' => '✅ Paid', 'unpaid' => '⏳ Unpaid', 'overdue' => '⚠ Overdue'];
                        $bclass = ['paid' => 'b-paid', 'unpaid' => 'b-unpaid', 'overdue' => 'b-overdue'];
                        $rcpt   = htmlspecialchars($f['receipt_number']);
                    ?>
                    <tr id="dash-row-<?= $rcpt ?>" data-is-paid="<?= $f['is_paid'] ? '1' : '0' ?>">
                        <td><span class="rcpt"><?= $rcpt ?></span></td>
                        <td><?= htmlspecialchars($f['full_name'] ?? '—') ?></td>
                        <td><span class="type-pill"><?= ucfirst($f['fee_type']) ?></span></td>
                        <td class="amount-val">£<?= number_format($f['amount'], 2) ?></td>
                        <td style="font-size:12px;color:var(--muted);"><?= $due->format('d M Y') ?></td>
                        <td id="dash-status-<?= $rcpt ?>"><span class="badge <?= $bclass[$status] ?>"><?= $labels[$status] ?></span></td>
                        <td style="white-space:nowrap;">
                            <?php if ($isAdmin): ?>
                            <a href="edit.php?id=<?= urlencode($f['receipt_number']) ?>" style="font-size:11px;color:var(--accent);text-decoration:none;margin-right:6px;">Edit</a>
                            <?php endif; ?>
                            <button
                                class="btn-pay-toggle <?= $f['is_paid'] ? 'state-unpay' : 'state-pay' ?>"
                                id="dash-btn-<?= $rcpt ?>"
                                data-receipt="<?= $rcpt ?>"
                                onclick="dashToggle(this)"
                                title="<?= $f['is_paid'] ? 'Mark as unpaid' : 'Mark as paid' ?>">
                                <?= $f['is_paid'] ? '↩ Unmark' : '✓ Mark Paid' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT SIDEBAR -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Fee Type Breakdown -->
            <div class="panel">
                <div class="panel-head"><h3>By Fee Type</h3></div>
                <?php if (empty($byType)): ?>
                <div class="empty-state"><div class="icon">📊</div>No data yet</div>
                <?php else:
                    $maxType = max(array_column($byType, 'total')) ?: 1;
                ?>
                <div class="type-bar">
                    <?php foreach ($byType as $t):
                        $w = round(($t['total'] / $maxType) * 100);
                        $fillClass = 'fill-' . $t['fee_type'];
                    ?>
                    <div class="type-row">
                        <span class="type-name"><?= ucfirst($t['fee_type']) ?></span>
                        <div class="type-track">
                            <div class="type-fill <?= $fillClass ?>" style="width:<?= $w ?>%"></div>
                        </div>
                        <span class="type-amt">£<?= number_format($t['total'], 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:0 22px 16px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr style="font-size:10px;color:var(--muted);font-weight:700;letter-spacing:.06em;text-transform:uppercase;">
                            <td style="padding:6px 0;">Type</td>
                            <td style="text-align:right;padding:6px 0;">Count</td>
                            <td style="text-align:right;padding:6px 0;">Paid</td>
                            <td style="text-align:right;padding:6px 0;">Total</td>
                        </tr>
                        <?php foreach ($byType as $t): ?>
                        <tr style="font-size:12px;border-top:1px solid var(--border);">
                            <td style="padding:7px 0;color:var(--muted);text-transform:capitalize;"><?= $t['fee_type'] ?></td>
                            <td style="text-align:right;padding:7px 0;"><?= $t['cnt'] ?></td>
                            <td style="text-align:right;padding:7px 0;color:var(--success);"><?= $t['paid_cnt'] ?></td>
                            <td style="text-align:right;padding:7px 0;font-family:'DM Mono',monospace;">£<?= number_format($t['total'],0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Overdue Alert Panel -->
            <?php if (!empty($overdueList)): ?>
            <div class="overdue-panel">
                <div class="panel-head">
                    <h3>🚨 Overdue Fees</h3>
                    <a href="index.php?filter=overdue" style="color:var(--danger);">View all</a>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Days Late</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($overdueList as $f): ?>
                    <tr>
                        <td>
                            <div style="font-size:13px;"><?= htmlspecialchars($f['full_name'] ?? '—') ?></div>
                            <div class="rcpt"><?= htmlspecialchars($f['receipt_number']) ?></div>
                        </td>
                        <td style="color:var(--danger);font-weight:700;"><?= $f['days_late'] ?>d</td>
                        <td class="amount-val text-danger">+£<?= number_format($f['fine_now'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="padding:12px 16px;font-size:11px;color:rgba(248,113,113,0.7);border-top:1px solid rgba(248,113,113,0.2);">
                    Fine policy: £0.50/day · max £15.00 per fee
                </div>
            </div>
            <?php endif; ?>

            <!-- Login Info -->
            <div class="panel">
                <div class="panel-head"><h3>Session Info</h3></div>
                <div style="padding:16px 22px;display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span class="text-muted">User</span>
                        <strong><?= htmlspecialchars($_SESSION['full_name'] ?? '—') ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span class="text-muted">Role</span>
                        <span style="background:rgba(79,122,255,0.15);color:#93b4ff;padding:2px 9px;border-radius:6px;font-size:12px;font-weight:700;text-transform:capitalize;">
                            <?= htmlspecialchars($_SESSION['role'] ?? '—') ?>
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span class="text-muted">Today</span>
                        <span><?= $today->format('d M Y') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p style="text-align:center;font-size:11px;color:var(--faint);margin-top:8px;">
        HostelHub Fee Management · Late fines auto-calculated: £0.50/day, max £15.00
    </p>
</div>

<!-- Toast notification -->
<div id="toast"></div>

<script>
/* ════════════════════════════════════════════════════
   TOAST HELPER
════════════════════════════════════════════════════ */
function showToast(msg, type = 'green') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show t-' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 2800);
}

/* ════════════════════════════════════════════════════
   DASHBOARD TOGGLE — AJAX pay/unmark
   Calls the same index.php?ajax_toggle= endpoint
   Updates badge + button without page reload
════════════════════════════════════════════════════ */
async function dashToggle(btn) {
    const receipt = btn.dataset.receipt;
    const row     = document.getElementById('dash-row-' + receipt);
    const statEl  = document.getElementById('dash-status-' + receipt);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
        const res  = await fetch('index.php?ajax_toggle=' + encodeURIComponent(receipt));
        const data = await res.json();

        if (!data.ok) throw new Error('fail');

        if (data.now_paid) {
            statEl.innerHTML = '<span class="badge b-paid">✅ Paid</span>';
            btn.className    = 'btn-pay-toggle state-unpay';
            btn.textContent  = '↩ Unmark';
            btn.title        = 'Mark as unpaid';
            row.dataset.isPaid = '1';
            showToast('✅ Fee marked as paid', 'green');
        } else {
            statEl.innerHTML = '<span class="badge b-unpaid">⏳ Unpaid</span>';
            btn.className    = 'btn-pay-toggle state-pay';
            btn.textContent  = '✓ Mark Paid';
            btn.title        = 'Mark as paid';
            row.dataset.isPaid = '0';
            showToast('↩ Fee unmarked', 'amber');
        }
    } catch (e) {
        showToast('❌ Something went wrong', 'amber');
        const isPaid = row.dataset.isPaid === '1';
        btn.className   = 'btn-pay-toggle ' + (isPaid ? 'state-unpay' : 'state-pay');
        btn.textContent = isPaid ? '↩ Unmark' : '✓ Mark Paid';
    }

    btn.disabled = false;
}
</script>
</body>
</html>