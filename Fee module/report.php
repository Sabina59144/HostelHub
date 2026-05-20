<?php
// Load the session manager and enforce that the user is logged in
require_once __DIR__ . '/../includes/session.php';

// Redirect to login if the user is not authenticated
requireLogin();

// Load the database connection (PDO instance stored in $db)
require_once __DIR__ . '/../includes/db.php';

// Check if the logged-in user has the 'admin' role (used to show/hide admin-only UI elements)
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Create a DateTime object representing today's date/time (used for the report timestamp)
$today   = new DateTime();

/* ── Summary for report ─────────────────────────────── */
// Run a single aggregate query to pull all top-level KPI numbers in one trip to the DB
$summary = $db->query("
    SELECT
        COUNT(*)                  AS total,          -- Total number of active fee records
        SUM(amount)               AS billed,          -- Total amount billed across all fees
        SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END) AS collected,    -- Total amount actually collected
        SUM(CASE WHEN is_paid=0 AND due_date < CURDATE() THEN amount ELSE 0 END) AS overdue_amt, -- Money at risk (unpaid + past due)
        COUNT(CASE WHEN is_paid=1 THEN 1 END)  AS cnt_paid,      -- Count of fully paid fees
        COUNT(CASE WHEN is_paid=0 AND due_date >= CURDATE() THEN 1 END) AS cnt_pending,  -- Count of unpaid but not yet overdue
        COUNT(CASE WHEN is_paid=0 AND due_date < CURDATE() THEN 1 END)  AS cnt_overdue   -- Count of overdue (unpaid + past due date)
    FROM fees WHERE is_active=1  -- Only consider active (non-deleted) records
")->fetch(PDO::FETCH_ASSOC); // Fetch the single summary row as an associative array

/* ── Per-student summary ────────────────────────────── */
// Aggregate fee data grouped by student to show the top 10 by total billed amount
$byStudent = $db->query("
    SELECT s.full_name, s.student_number,
           COUNT(f.receipt_number) AS cnt,           -- Number of fee records for this student
           SUM(f.amount)           AS total,          -- Total amount billed to this student
           SUM(CASE WHEN f.is_paid=1 THEN f.amount ELSE 0 END) AS paid,  -- Amount paid by this student
           COUNT(CASE WHEN f.is_paid=0 AND f.due_date < CURDATE() THEN 1 END) AS overdue_cnt  -- Number of overdue fees for this student
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id  -- Join to get student name/number; LEFT JOIN keeps fees with no student match
    WHERE f.is_active=1             -- Only active fee records
    GROUP BY f.student_id           -- One row per student
    ORDER BY total DESC             -- Sort by highest total billed first
    LIMIT 10                        -- Only return the top 10 students
")->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows as associative arrays

/* ── Monthly breakdown ──────────────────────────────── */
// Group fees by the month of their due date to show a month-by-month breakdown
$monthly = $db->query("
    SELECT
        DATE_FORMAT(due_date, '%Y-%m') AS ym,         -- Sortable year-month key (e.g., '2024-03')
        DATE_FORMAT(due_date, '%b %Y') AS label,      -- Human-readable label (e.g., 'Mar 2024')
        COUNT(*) AS cnt,                              -- Number of fees due in this month
        SUM(amount) AS total,                         -- Total billed in this month
        SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END) AS paid  -- Amount collected in this month
    FROM fees WHERE is_active=1     -- Only active records
    GROUP BY DATE_FORMAT(due_date,'%Y-%m')  -- Group by year-month
    ORDER BY ym DESC                -- Most recent month first
    LIMIT 12                        -- Show only the last 12 months
")->fetchAll(PDO::FETCH_ASSOC); // Fetch all monthly rows as associative arrays
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<!-- Responsive viewport for mobile devices -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Report — HostelHub</title>
<!-- Preconnect to Google Fonts CDN for faster DNS resolution -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<!-- Load Syne (headings), DM Mono (numbers/code), and Outfit (body text) fonts -->
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* Dark theme colour tokens as CSS custom properties */
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;}

/* Universal reset: remove default margin/padding, use border-box sizing */
*{box-sizing:border-box;margin:0;padding:0;}

/* Page base: dark background, light text, Outfit font, minimum full viewport height */
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}

/* Sticky top navigation bar that stays visible while scrolling */
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}

/* Brand logo using Syne bold font */
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}

/* Accent colour for the "Hub" suffix in the logo */
.brand span{color:var(--accent);}

/* Main content wrapper: max 1100px wide, centred, with padding */
.page{max-width:1100px;margin:0 auto;padding:32px;}

/* Page header row: title on the left, print button on the right */
.page-hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;}

/* Page title in large Syne font */
.page-hdr h2{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px;}

/* Subtitle text below the page title */
.page-hdr p{color:var(--muted);font-size:13px;}

/* Print/export button: outlined style that glows accent colour on hover */
.print-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;background:var(--card);color:var(--text);border:1px solid var(--border);font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;font-family:'Outfit',sans-serif;}
.print-btn:hover{border-color:var(--accent);color:var(--accent);}

/* KPI grid: 3 equal columns with gaps between cards */
.kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}

/* Individual KPI card */
.kpi{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px 22px;}

/* Small uppercase label above the KPI value */
.kpi .label{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:8px;}

/* Large bold KPI number in Syne font */
.kpi .val{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;}

/* Supplementary info below the KPI value */
.kpi .sub{font-size:12px;color:var(--muted);margin-top:4px;}

/* Colour modifiers for KPI card values */
.kpi.blue .val{color:var(--accent);}    /* Blue for total billed */
.kpi.green .val{color:var(--success);}  /* Green for collected */
.kpi.amber .val{color:var(--warning);}  /* Amber for outstanding/pending */
.kpi.red .val{color:var(--danger);}     /* Red for overdue */

/* Panel: card container used for the data tables */
.panel{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}

/* Panel header bar above each table */
.panel-head{padding:16px 22px;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-size:15px;font-weight:700;}

/* Table inside a panel: full width, no border spacing */
.tbl{width:100%;border-collapse:collapse;}

/* Table column headers: uppercase, muted, small text on a slightly darker background */
.tbl th{padding:10px 16px;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);text-align:left;border-bottom:1px solid var(--border);background:var(--surface);}

/* Table data cells: comfortable padding, small font, thin bottom border */
.tbl td{padding:11px 16px;font-size:13px;border-bottom:1px solid rgba(42,49,72,0.4);vertical-align:middle;}

/* Remove the border from the last row's cells */
.tbl tr:last-child td{border-bottom:none;}

/* Subtle accent highlight when hovering over a table row */
.tbl tr:hover td{background:rgba(79,122,255,0.04);}

/* Monospace class for numbers/codes to align digits properly */
.mono{font-family:'DM Mono',monospace;font-size:12px;}

/* Progress bar track (background/container) */
.bar-track{height:5px;background:var(--border);border-radius:99px;overflow:hidden;margin-top:4px;}

/* Progress bar fill; width is set inline as a percentage from PHP */
.bar-fill{height:100%;background:var(--accent);border-radius:99px;}

/* Back/breadcrumb link in the nav */
.back-link{font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}

/* Print media query: hide nav and buttons, switch to white/black for paper */
@media print{
    .topnav,.print-btn{display:none;}   /* Hide interactive UI elements when printing */
    body{background:#fff;color:#000;}   /* White background, black text for print */
    .kpi,.panel{border:1px solid #ccc;background:#fff;} /* Light borders for print */
    .kpi .val,.panel-head{color:#000;}  /* Ensure headings print in black */
}
</style>
</head>
<body>

<!-- Sticky top navigation bar -->
<nav class="topnav">
    <!-- Brand logo -->
    <div class="brand">🏠 Hostel<span>Hub</span></div>

    <!-- Right-side nav links -->
    <div style="display:flex;gap:12px;align-items:center;">
        <!-- Link to the main application dashboard -->
        <a href="../dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">← Home</a>
        <!-- Link to the fee module dashboard -->
        <a href="dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <!-- Link to the full fee records list -->
        <a href="index.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>

<!-- Main page content -->
<div class="page">

    <!-- Page header: title + generated timestamp + print button -->
    <div class="page-hdr">
        <div>
            <h2>Fee Report</h2>
            <!-- Display the report generation date and time using the $today DateTime object -->
            <p>Generated on <?= $today->format('d F Y, H:i') ?></p>
        </div>
        <!-- Print button triggers the browser's native print dialog -->
        <button onclick="window.print()" class="print-btn">🖨️ Print / Export</button>
    </div>

    <!-- ── Row 1: Financial KPIs ── -->
    <div class="kpi-grid">
        <!-- Total Billed: sum of all fee amounts -->
        <div class="kpi blue">
            <div class="label">Total Billed</div>
            <div class="val">kr <?= number_format($summary['billed'],0) ?></div>
            <!-- Subtitle shows the raw record count -->
            <div class="sub"><?= $summary['total'] ?> fee records</div>
        </div>

        <!-- Collected: sum of paid fees only -->
        <div class="kpi green">
            <div class="label">Collected</div>
            <div class="val">kr <?= number_format($summary['collected'],0) ?></div>
            <div class="sub"><?= $summary['cnt_paid'] ?> fees paid</div>
        </div>

        <!-- Outstanding: billed minus collected = money not yet received -->
        <div class="kpi amber">
            <div class="label">Outstanding</div>
            <div class="val">kr <?= number_format($summary['billed']-$summary['collected'],0) ?></div>
            <!-- Combine pending and overdue counts for the subtitle -->
            <div class="sub"><?= $summary['cnt_pending']+$summary['cnt_overdue'] ?> fees pending</div>
        </div>
    </div>

    <!-- ── Row 2: Status KPIs ── -->
    <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
        <!-- Collection Rate: percentage of billed amount that has been paid -->
        <div class="kpi">
            <div class="label">Collection Rate</div>
            <!-- Avoid division by zero; multiply ratio by 100 and round to nearest integer -->
            <div class="val" style="color:var(--success);"><?= $summary['billed']>0 ? round(($summary['collected']/$summary['billed'])*100) : 0 ?>%</div>
        </div>

        <!-- Count of fees that are unpaid but not yet past their due date -->
        <div class="kpi amber">
            <div class="label">Pending Fees</div>
            <div class="val"><?= $summary['cnt_pending'] ?></div>
        </div>

        <!-- Count of fees that are both unpaid AND past their due date -->
        <div class="kpi red">
            <div class="label">Overdue Fees</div>
            <div class="val"><?= $summary['cnt_overdue'] ?></div>
            <!-- Show the total monetary value of overdue fees as a risk indicator -->
            <div class="sub">kr <?= number_format($summary['overdue_amt'],0) ?> at risk</div>
        </div>
    </div>

    <!-- ── Per-Student Summary Table ── -->
    <div class="panel">
        <div class="panel-head">Top Students by Balance</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student No.</th>
                    <th>Records</th>
                    <th>Total Billed</th>
                    <th>Paid</th>
                    <th>Collection</th>
                    <th>Overdue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byStudent as $row):
                // Calculate this student's collection rate; guard against division by zero
                $rate = $row['total']>0 ? round(($row['paid']/$row['total'])*100) : 0;
            ?>
            <tr>
                <!-- Student full name; falls back to '—' if name is null -->
                <td><?= htmlspecialchars($row['full_name'] ?? '—') ?></td>

                <!-- Student number in monospace for consistent digit alignment -->
                <td class="mono"><?= htmlspecialchars($row['student_number'] ?? '—') ?></td>

                <!-- Total number of fee records for this student -->
                <td><?= $row['cnt'] ?></td>

                <!-- Total amount billed to this student -->
                <td class="mono">kr <?= number_format($row['total'],2) ?></td>

                <!-- Amount paid (highlighted in success/green colour) -->
                <td class="mono" style="color:var(--success);">kr <?= number_format($row['paid'],2) ?></td>

                <!-- Visual collection rate: percentage text + progress bar -->
                <td>
                    <span style="font-size:12px;"><?= $rate ?>%</span>
                    <!-- Bar fill width is set dynamically from the calculated rate -->
                    <div class="bar-track"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div>
                </td>

                <!-- Overdue count: shown in red bold if > 0, otherwise a dash -->
                <td><?= $row['overdue_cnt'] > 0 ? '<span style="color:var(--danger);font-weight:700;">'.$row['overdue_cnt'].'</span>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Monthly Breakdown Table ── -->
    <div class="panel">
        <div class="panel-head">Monthly Breakdown (by Due Date)</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Fee Count</th>
                    <th>Total Billed</th>
                    <th>Collected</th>
                    <th>Outstanding</th>
                    <th>Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthly as $m):
                // Calculate collection rate for this month; guard against division by zero
                $rate = $m['total']>0 ? round(($m['paid']/$m['total'])*100) : 0;
            ?>
            <tr>
                <!-- Human-readable month label (e.g., "Mar 2024") -->
                <td><?= $m['label'] ?></td>

                <!-- Number of fee records due in this month -->
                <td><?= $m['cnt'] ?></td>

                <!-- Total amount billed in this month -->
                <td class="mono">kr <?= number_format($m['total'],2) ?></td>

                <!-- Amount collected in this month (green) -->
                <td class="mono" style="color:var(--success);">kr <?= number_format($m['paid'],2) ?></td>

                <!-- Outstanding = total billed minus amount collected (amber warning colour) -->
                <td class="mono" style="color:var(--warning);">kr <?= number_format($m['total']-$m['paid'],2) ?></td>

                <!-- Collection rate percentage + mini progress bar -->
                <td>
                    <span style="font-size:12px;"><?= $rate ?>%</span>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div><!-- /.page -->

<!-- HostelHub Footer -->
<footer style="
    background:var(--surface);
    border-top:1px solid var(--border);
    margin-top:48px;
    padding:28px 32px;
    text-align:center;
    font-family:'Outfit',sans-serif;
">
    <div style="max-width:1100px;margin:0 auto;">
        <!-- Decorative thin horizontal rule -->
        <div style="width:48px;height:1px;background:var(--border);margin:0 auto 16px;"></div>

        <!-- Footer logo -->
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--text);">
                🏠 Hostel<span style="color:var(--accent);">Hub</span>
            </span>
        </div>

        <!-- Copyright line; date('Y') outputs the current four-digit year dynamically -->
        <p style="font-size:11px;color:var(--muted);margin:0;">
            Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub &nbsp;·&nbsp; All records are encrypted and access-controlled.
        </p>
    </div>
</footer>

</body>
</html>