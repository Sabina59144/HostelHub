<?php
/**
 * run_all_tests.php
 * ──────────────────────────────────────────────────────────────
 * Portfolio 2 – Student Module Validation Test Suite
 * Open in browser:  localhost/Hostelhub/Student%20module/tests/run_all_tests.php
 * ──────────────────────────────────────────────────────────────
 */

$runners = [
    require __DIR__ . '/test_full_name.php',
    require __DIR__ . '/test_phone.php',
    require __DIR__ . '/test_email.php',
    require __DIR__ . '/test_room_number.php',
];

/* ── Aggregate totals ─────────────────────────── */
$grand_pass  = 0;
$grand_fail  = 0;
$grand_total = 0;

foreach ($runners as $r) {
    $t = $r->getTotals();
    $grand_pass  += $t['pass'];
    $grand_fail  += $t['fail'];
    $grand_total += $t['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Module – Validation Test Suite</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            padding: 0 0 60px;
        }

        /* ── Header ── */
        .site-header {
            background: linear-gradient(135deg, #0f1923 0%, #1a3a5c 100%);
            color: #fff;
            padding: 36px 48px 30px;
            border-bottom: 4px solid #1a56db;
        }
        .site-header h1 { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
        .site-header p  { font-size: 14px; color: #94a3b8; }

        /* ── Summary bar ── */
        .summary {
            display: flex;
            gap: 20px;
            padding: 20px 48px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 28px;
            text-align: center;
            min-width: 140px;
        }
        .summary-card .num  { font-size: 32px; font-weight: 800; line-height: 1; }
        .summary-card .lbl  { font-size: 12px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: .04em; }
        .summary-card.total .num { color: #1e293b; }
        .summary-card.pass  .num { color: #059669; }
        .summary-card.fail  .num { color: #dc2626; }
        .summary-card.pct   .num { color: #1a56db; font-size: 24px; }

        /* ── Progress bar ── */
        .progress-wrap {
            padding: 0 48px 24px;
            background: #fff;
        }
        .progress-bar {
            height: 10px;
            background: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #059669, #34d399);
            border-radius: 5px;
            transition: width .5s ease;
        }

        /* ── Sections ── */
        .section-wrap { padding: 32px 48px 0; }
        .section-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 32px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .section-head h2 { font-size: 16px; font-weight: 700; color: #0f1923; }
        .section-badge {
            font-size: 12px; font-weight: 700;
            padding: 4px 12px; border-radius: 999px;
        }
        .badge-ok   { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fee2e2; color: #991b1b; }

        /* ── Table ── */
        .test-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .test-table th {
            background: #1e293b;
            color: #e2e8f0;
            text-align: left;
            padding: 11px 16px;
            font-weight: 600;
            letter-spacing: .03em;
            font-size: 12px;
            text-transform: uppercase;
        }
        .test-table td {
            padding: 11px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            line-height: 1.5;
        }
        .test-table tr:last-child td { border-bottom: none; }
        .test-table tr:hover td     { background: #f8fafc; }

        /* type column */
        .col-type { width: 150px; font-weight: 600; color: #334155; }
        .col-data { width: 22%; font-family: monospace; font-size: 12px; color: #475569; }
        .col-exp  { width: 28%; color: #475569; }
        .col-act  { width: 28%; font-weight: 600; }
        .col-res  { width: 80px; text-align: center; }

        .badge-pass {
            display: inline-block;
            background: #d1fae5; color: #065f46;
            padding: 3px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
        }
        .badge-fail {
            display: inline-block;
            background: #fee2e2; color: #991b1b;
            padding: 3px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
        }
        .actual-pass { color: #059669; }
        .actual-fail { color: #dc2626; }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 32px;
            font-size: 12px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .site-header, .summary, .progress-wrap, .section-wrap { padding-left: 20px; padding-right: 20px; }
            .test-table { font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- ══ HEADER ══ -->
<div class="site-header">
    <h1>🧪 Student Module – Validation Test Suite</h1>
    <p>Portfolio 2 &nbsp;|&nbsp; Boundary Value Analysis &nbsp;|&nbsp; HostelHub &nbsp;|&nbsp;
       Run date: <?= date('d/m/Y H:i:s') ?></p>
</div>

<!-- ══ SUMMARY BAR ══ -->
<div class="summary">
    <div class="summary-card total">
        <div class="num"><?= $grand_total ?></div>
        <div class="lbl">Total Tests</div>
    </div>
    <div class="summary-card pass">
        <div class="num"><?= $grand_pass ?></div>
        <div class="lbl">Passed</div>
    </div>
    <div class="summary-card fail">
        <div class="num"><?= $grand_fail ?></div>
        <div class="lbl">Failed</div>
    </div>
    <div class="summary-card pct">
        <div class="num"><?= $grand_total > 0 ? round(($grand_pass/$grand_total)*100) : 0 ?>%</div>
        <div class="lbl">Pass Rate</div>
    </div>
</div>

<!-- ── Progress bar ── -->
<div class="progress-wrap">
    <?php $pct = $grand_total > 0 ? ($grand_pass / $grand_total) * 100 : 0; ?>
    <div class="progress-bar">
        <div class="progress-fill" style="width:<?= round($pct) ?>%"></div>
    </div>
</div>

<!-- ══ SECTIONS ══ -->
<div class="section-wrap">

<?php foreach ($runners as $runner):
    $sections = $runner->getSections();
    $totals   = $runner->getTotals();

    foreach ($sections as $sectionName => $rows):
        $sPass  = array_sum(array_column($rows, 'passed'));
        $sFail  = count($rows) - $sPass;
        $allOk  = ($sFail === 0);
?>

<div class="section-card">
    <div class="section-head">
        <h2><?= htmlspecialchars($sectionName) ?></h2>
        <span class="section-badge <?= $allOk ? 'badge-ok' : 'badge-warn' ?>">
            <?= $sPass ?> PASS &nbsp;/&nbsp; <?= $sFail ?> FAIL
        </span>
    </div>

    <table class="test-table">
        <thead>
            <tr>
                <th class="col-type">Test Type</th>
                <th class="col-data">Test Data</th>
                <th class="col-exp">Expected Result</th>
                <th class="col-act">Actual Result</th>
                <th class="col-res">Result</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td class="col-type"><?= htmlspecialchars($row['type']) ?></td>
                <td class="col-data"><?= htmlspecialchars($row['data']) ?></td>
                <td class="col-exp"><?= htmlspecialchars($row['expected']) ?></td>
                <td class="col-act <?= $row['passed'] ? 'actual-pass' : 'actual-fail' ?>">
                    <?= htmlspecialchars($row['actual']) ?>
                </td>
                <td class="col-res">
                    <span class="<?= $row['passed'] ? 'badge-pass' : 'badge-fail' ?>">
                        <?= $row['label'] ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endforeach; endforeach; ?>

</div><!-- /section-wrap -->

<div class="footer">
    Student Module &nbsp;&middot;&nbsp; HostelHub &nbsp;&middot;&nbsp;
    CTEC2713 Portfolio 2 &nbsp;&middot;&nbsp; Diwash Ghimire
</div>

</body>
</html>
