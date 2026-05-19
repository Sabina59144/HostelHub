<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$today   = new DateTime();

/* ── Summary for report ─────────────────────────────── */
$summary = $db->query("
    SELECT
        COUNT(*)                  AS total,
        SUM(amount)               AS billed,
        SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END) AS collected,
        SUM(CASE WHEN is_paid=0 AND due_date < CURDATE() THEN amount ELSE 0 END) AS overdue_amt,
        COUNT(CASE WHEN is_paid=1 THEN 1 END)  AS cnt_paid,
        COUNT(CASE WHEN is_paid=0 AND due_date >= CURDATE() THEN 1 END) AS cnt_pending,
        COUNT(CASE WHEN is_paid=0 AND due_date < CURDATE() THEN 1 END)  AS cnt_overdue
    FROM fees WHERE is_active=1
")->fetch(PDO::FETCH_ASSOC);

/* ── Per-student summary ────────────────────────────── */
$byStudent = $db->query("
    SELECT s.full_name, s.student_number,
           COUNT(f.receipt_number) AS cnt,
           SUM(f.amount)           AS total,
           SUM(CASE WHEN f.is_paid=1 THEN f.amount ELSE 0 END) AS paid,
           COUNT(CASE WHEN f.is_paid=0 AND f.due_date < CURDATE() THEN 1 END) AS overdue_cnt
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.is_active=1
    GROUP BY f.student_id
    ORDER BY total DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Monthly breakdown ──────────────────────────────── */
$monthly = $db->query("
    SELECT
        DATE_FORMAT(due_date, '%Y-%m') AS ym,
        DATE_FORMAT(due_date, '%b %Y') AS label,
        COUNT(*) AS cnt,
        SUM(amount) AS total,
        SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END) AS paid
    FROM fees WHERE is_active=1
    GROUP BY DATE_FORMAT(due_date,'%Y-%m')
    ORDER BY ym DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Report — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}
.page{max-width:1100px;margin:0 auto;padding:32px;}
.page-hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;}
.page-hdr h2{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}
.print-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;background:var(--card);color:var(--text);border:1px solid var(--border);font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;font-family:'Outfit',sans-serif;}
.print-btn:hover{border-color:var(--accent);color:var(--accent);}
.kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px 22px;}
.kpi .label{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:8px;}
.kpi .val{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;}
.kpi .sub{font-size:12px;color:var(--muted);margin-top:4px;}
.kpi.blue .val{color:var(--accent);}
.kpi.green .val{color:var(--success);}
.kpi.amber .val{color:var(--warning);}
.kpi.red .val{color:var(--danger);}
.panel{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}
.panel-head{padding:16px 22px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:15px;font-weight:700;}
.tbl{width:100%;border-collapse:collapse;}
.tbl th{padding:10px 16px;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);text-align:left;border-bottom:1px solid var(--border);background:var(--surface);}
.tbl td{padding:11px 16px;font-size:13px;border-bottom:1px solid rgba(42,49,72,0.4);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(79,122,255,0.04);}
.mono{font-family:'DM Mono',monospace;font-size:12px;}
.bar-track{height:5px;background:var(--border);border-radius:99px;overflow:hidden;margin-top:4px;}
.bar-fill{height:100%;background:var(--accent);border-radius:99px;}
.back-link{font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}
@media print{.topnav,.print-btn{display:none;} body{background:#fff;color:#000;} .kpi,.panel{border:1px solid #ccc;background:#fff;} .kpi .val,.panel-head{color:#000;}}
</style>
</head>
<body>
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="../dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">← Home</a>
        <a href="dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <a href="index.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>
<div class="page">
    <div class="page-hdr">
        <div>
            <h2>Fee Report</h2>
            <p>Generated on <?= $today->format('d F Y, H:i') ?></p>
        </div>
        <button onclick="window.print()" class="print-btn">🖨️ Print / Export</button>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi blue"><div class="label">Total Billed</div><div class="val">£<?= number_format($summary['billed'],0) ?></div><div class="sub"><?= $summary['total'] ?> fee records</div></div>
        <div class="kpi green"><div class="label">Collected</div><div class="val">£<?= number_format($summary['collected'],0) ?></div><div class="sub"><?= $summary['cnt_paid'] ?> fees paid</div></div>
        <div class="kpi amber"><div class="label">Outstanding</div><div class="val">£<?= number_format($summary['billed']-$summary['collected'],0) ?></div><div class="sub"><?= $summary['cnt_pending']+$summary['cnt_overdue'] ?> fees pending</div></div>
    </div>
    <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="kpi"><div class="label">Collection Rate</div><div class="val" style="color:var(--success);"><?= $summary['billed']>0 ? round(($summary['collected']/$summary['billed'])*100) : 0 ?>%</div></div>
        <div class="kpi amber"><div class="label">Pending Fees</div><div class="val"><?= $summary['cnt_pending'] ?></div></div>
        <div class="kpi red"><div class="label">Overdue Fees</div><div class="val"><?= $summary['cnt_overdue'] ?></div><div class="sub">£<?= number_format($summary['overdue_amt'],0) ?> at risk</div></div>
    </div>

    <!-- By Student -->
    <div class="panel">
        <div class="panel-head">Top Students by Balance</div>
        <table class="tbl">
            <thead><tr><th>Student</th><th>Student No.</th><th>Records</th><th>Total Billed</th><th>Paid</th><th>Collection</th><th>Overdue</th></tr></thead>
            <tbody>
            <?php foreach ($byStudent as $row):
                $rate = $row['total']>0 ? round(($row['paid']/$row['total'])*100) : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['full_name'] ?? '—') ?></td>
                <td class="mono"><?= htmlspecialchars($row['student_number'] ?? '—') ?></td>
                <td><?= $row['cnt'] ?></td>
                <td class="mono">£<?= number_format($row['total'],2) ?></td>
                <td class="mono" style="color:var(--success);">£<?= number_format($row['paid'],2) ?></td>
                <td>
                    <span style="font-size:12px;"><?= $rate ?>%</span>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div>
                </td>
                <td><?= $row['overdue_cnt'] > 0 ? '<span style="color:var(--danger);font-weight:700;">'.$row['overdue_cnt'].'</span>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Monthly -->
    <div class="panel">
        <div class="panel-head">Monthly Breakdown (by Due Date)</div>
        <table class="tbl">
            <thead><tr><th>Month</th><th>Fee Count</th><th>Total Billed</th><th>Collected</th><th>Outstanding</th><th>Rate</th></tr></thead>
            <tbody>
            <?php foreach ($monthly as $m):
                $rate = $m['total']>0 ? round(($m['paid']/$m['total'])*100) : 0;
            ?>
            <tr>
                <td><?= $m['label'] ?></td>
                <td><?= $m['cnt'] ?></td>
                <td class="mono">£<?= number_format($m['total'],2) ?></td>
                <td class="mono" style="color:var(--success);">£<?= number_format($m['paid'],2) ?></td>
                <td class="mono" style="color:var(--warning);">£<?= number_format($m['total']-$m['paid'],2) ?></td>
                <td>
                    <span style="font-size:12px;"><?= $rate ?>%</span>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
