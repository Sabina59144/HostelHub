<?php

/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

/* ════════════════════════════════════════════════════════
   AJAX TOGGLE ENDPOINT
   Handles Mark Paid / Unmark via fetch() — returns JSON
   so the UI can update instantly without a page reload.
════════════════════════════════════════════════════════ */
if (isset($_GET['ajax_toggle'])) {
    header('Content-Type: application/json');

    $id   = $_GET['ajax_toggle'];
    $stmt = $db->prepare("SELECT is_paid FROM fees WHERE receipt_number = ? AND is_active = 1");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) { echo json_encode(['ok' => false]); exit; }

    if ($row['is_paid']) {
        // ── UNMARK: clear paid state ───────────────────
        $db->prepare("UPDATE fees SET is_paid = 0, paid_at = NULL WHERE receipt_number = ?")
           ->execute([$id]);
        echo json_encode(['ok' => true, 'now_paid' => false, 'paid_at' => null]);
    } else {
        // ── MARK PAID: stamp now ───────────────────────
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE fees SET is_paid = 1, paid_at = ? WHERE receipt_number = ?")
           ->execute([$now, $id]);
        echo json_encode(['ok' => true, 'now_paid' => true, 'paid_at' => $now]);
    }
    exit;
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$today   = new DateTime();

/* ── FILTER VARIABLES ───────────────────────────────── */
$search            = trim($_GET['search'] ?? '');
$filterStudentId   = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$filterStatus      = $_GET['filter']    ?? '';
$filterType        = $_GET['fee_type']  ?? '';
$filterStudentName = '';

if ($filterStudentId) {
    $sRow = $db->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $sRow->execute([$filterStudentId]);
    $filterStudentName = $sRow->fetchColumn() ?: '';
}

/* ── BUILD DYNAMIC QUERY ────────────────────────────── */
$where  = ["f.is_active = 1"];
$params = [];

if ($filterStudentId) { $where[] = "f.student_id = ?"; $params[] = $filterStudentId; }

if (!empty($search)) {
    $where[]  = "(f.receipt_number LIKE ? OR s.full_name LIKE ? OR s.student_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

if ($filterType) { $where[] = "f.fee_type = ?"; $params[] = $filterType; }

if ($filterStatus === 'paid')    { $where[] = "f.is_paid = 1"; }
elseif ($filterStatus === 'unpaid')  { $where[] = "f.is_paid = 0 AND f.due_date >= CURDATE()"; }
elseif ($filterStatus === 'overdue') { $where[] = "f.is_paid = 0 AND f.due_date < CURDATE()"; }

$sql  = "SELECT f.*, s.full_name, s.student_number FROM fees f LEFT JOIN students s ON s.student_id = f.student_id";
$sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY f.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── SUMMARY STATS ──────────────────────────────────── */
$cnt = ['total'=>count($fees),'paid'=>0,'unpaid'=>0,'overdue'=>0,'total_amt'=>0,'paid_amt'=>0,'outstanding'=>0];

$FINE_RATE = 0.50;
$FINE_CAP  = 15.00;

foreach ($fees as $f) {
    $due = new DateTime($f['due_date']);
    $cnt['total_amt'] += $f['amount'];
    if ($f['is_paid']) {
        $cnt['paid']++;
        $cnt['paid_amt'] += $f['amount'];
    } elseif ($today > $due) {
        $cnt['overdue']++;
        $days = $due->diff($today)->days;
        $fine = min($days * ($f['fine_rate'] ?? $FINE_RATE), $f['fine_cap'] ?? $FINE_CAP);
        $cnt['outstanding'] += $f['amount'] + $fine;
    } else {
        $cnt['unpaid']++;
        $cnt['outstanding'] += $f['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Records — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Flatpickr — rich date-picker with calendar UI -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
/* ══ DESIGN TOKENS ═══════════════════════════════════ */
:root{
  --bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;
  --accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;
  --text:#e8eaf6;--muted:#8892b0;--faint:#3a4260;
  --radius:14px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}

/* ── TOPNAV ─────────────────────────────────────────── */
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;
  display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}
.nav-links{display:flex;gap:4px;}
.nav-links a{padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;color:var(--muted);
  text-decoration:none;transition:all .15s;}
.nav-links a:hover{background:var(--card);color:var(--text);}
.nav-links a.active{background:var(--accent);color:#fff;}

/* ── PAGE LAYOUT ────────────────────────────────────── */
.page{max-width:1320px;margin:0 auto;padding:28px 32px;}
.page-hdr{display:flex;align-items:flex-start;justify-content:space-between;
  gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.page-hdr h2{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}

/* ── BUTTONS ────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;
  font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;
  border:1px solid transparent;cursor:pointer;font-family:'Outfit',sans-serif;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#3d68e8;}
.btn-ghost{background:var(--card);color:var(--text);border-color:var(--border);}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent);}

/* ── FILTER ROW ─────────────────────────────────────── */
.filter-row{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.filter-row form{display:flex;gap:8px;flex:1;min-width:280px;align-items:center;flex-wrap:wrap;}
.filter-row input[type=text]{flex:1;padding:9px 14px;border:1px solid var(--border);border-radius:9px;
  font-size:13px;background:var(--card);color:var(--text);font-family:'Outfit',sans-serif;
  outline:none;transition:border-color .15s;}
.filter-row input[type=text]:focus{border-color:var(--accent);}
.filter-row select{padding:9px 12px;border:1px solid var(--border);border-radius:9px;
  font-size:13px;background:var(--card);color:var(--text);font-family:'Outfit',sans-serif;
  outline:none;cursor:pointer;}
.filter-row select:focus{border-color:var(--accent);}

/* Date-range picker row */
.date-filter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.date-filter label{font-size:11px;color:var(--muted);font-weight:600;letter-spacing:.04em;text-transform:uppercase;}
.date-filter input{width:140px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;
  font-size:12px;background:var(--card);color:var(--text);font-family:'DM Mono',monospace;
  outline:none;cursor:pointer;}
.date-filter input:focus{border-color:var(--accent);}

/* ── STAT STRIP ─────────────────────────────────────── */
.stat-strip{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.stat-chip{background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:10px 16px;font-size:12px;color:var(--muted);min-width:120px;}
.stat-chip strong{display:block;font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:var(--text);}
.stat-chip.s-green strong{color:var(--success);}
.stat-chip.s-amber strong{color:var(--warning);}
.stat-chip.s-red  strong{color:var(--danger);}
.stat-chip.s-blue strong{color:var(--accent);}

/* ── TABLE ──────────────────────────────────────────── */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.table-wrap table{width:100%;border-collapse:collapse;}
.table-wrap thead th{background:var(--surface);padding:11px 14px;font-size:10px;font-weight:700;
  letter-spacing:.07em;text-transform:uppercase;color:var(--muted);text-align:left;
  border-bottom:1px solid var(--border);white-space:nowrap;}
.table-wrap tbody td{padding:12px 14px;font-size:13px;
  border-bottom:1px solid rgba(42,49,72,0.5);vertical-align:middle;transition:background .15s;}
.table-wrap tbody tr:last-child td{border-bottom:none;}
.table-wrap tbody tr:hover td{background:rgba(79,122,255,0.04);}
.table-wrap tbody tr.row-late td{background:rgba(248,113,113,0.03);}

/* Row state transitions */
.table-wrap tbody tr{transition:opacity .3s,background .3s;}
.table-wrap tbody tr.row-cleared{background:rgba(34,211,165,0.05) !important;}
.table-wrap tbody tr.row-cleared td{opacity:.85;}

/* ── BADGES ─────────────────────────────────────────── */
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;transition:all .3s;}
.b-paid   {background:rgba(34,211,165,0.15);color:var(--success);}
.b-unpaid {background:rgba(251,191,36,0.15);color:var(--warning);}
.b-overdue{background:rgba(248,113,113,0.15);color:var(--danger);}
.b-cleared{background:rgba(34,211,165,0.25);color:var(--success);}

.type-pill{background:rgba(79,122,255,0.15);color:#93b4ff;padding:3px 9px;
  border-radius:6px;font-size:11px;font-weight:600;text-transform:capitalize;}
.rcpt{font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);}
.mono{font-family:'DM Mono',monospace;font-size:12px;}
.fine-col{color:var(--danger);font-family:'DM Mono',monospace;font-size:12px;transition:all .3s;}
.total-col{font-family:'DM Mono',monospace;font-size:13px;font-weight:600;color:var(--text);transition:all .3s;}

/* ── ACTION LINKS ───────────────────────────────────── */
.act-link{color:var(--accent);font-size:11px;font-weight:600;text-decoration:none;
  margin:0 5px;padding:4px 8px;display:inline-block;transition:all .15s;
  border-radius:5px;}
.act-link:hover{background:rgba(79,122,255,.12);color:#fff;}
.act-edit{color:#93b4ff;}
.act-edit:hover{color:#c5d9ff;background:rgba(147,180,255,.1);}
.act-delete{color:#f87171;}
.act-delete:hover{color:#fca5a5;background:rgba(248,113,113,.1);}

/* Pay toggle button — styled as a small pill button */
.btn-pay-toggle{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
  border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;border:none;
  font-family:'Outfit',sans-serif;transition:all .2s;outline:none;}
.btn-pay-toggle.state-pay{background:rgba(34,211,165,.15);color:var(--success);}
.btn-pay-toggle.state-pay:hover{background:rgba(34,211,165,.3);}
.btn-pay-toggle.state-unpay{background:rgba(251,191,36,.15);color:var(--warning);}
.btn-pay-toggle.state-unpay:hover{background:rgba(251,191,36,.3);}
.btn-pay-toggle:disabled{opacity:.5;cursor:not-allowed;}

/* Spinner for loading state */
.spinner{display:inline-block;width:10px;height:10px;border:2px solid currentColor;
  border-top-color:transparent;border-radius:50%;animation:spin .5s linear infinite;margin-right:3px;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── TOAST NOTIFICATION ─────────────────────────────── */
#toast{position:fixed;bottom:28px;right:28px;background:var(--card);border:1px solid var(--border);
  border-radius:12px;padding:14px 20px;font-size:13px;font-weight:500;color:var(--text);
  box-shadow:0 8px 32px rgba(0,0,0,.4);z-index:999;
  transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);}
#toast.show{transform:translateY(0);opacity:1;}
#toast.t-green{border-color:rgba(34,211,165,.4);color:var(--success);}
#toast.t-amber{border-color:rgba(251,191,36,.4);color:var(--warning);}

/* ── EMPTY STATE ────────────────────────────────────── */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:60px 20px;text-align:center;}
.empty-state .icon{font-size:48px;margin-bottom:16px;}
.empty-state h3{font-family:'Syne',sans-serif;font-size:18px;margin-bottom:8px;}
.empty-state p{color:var(--muted);font-size:13px;}

/* ── FLATPICKR THEME OVERRIDES ──────────────────────── */
.flatpickr-calendar{background:var(--card) !important;border:1px solid var(--border) !important;
  box-shadow:0 12px 40px rgba(0,0,0,.5) !important;border-radius:12px !important;}
.flatpickr-day{color:var(--text) !important;}
.flatpickr-day:hover{background:rgba(79,122,255,.2) !important;}
.flatpickr-day.selected{background:var(--accent) !important;border-color:var(--accent) !important;}
.flatpickr-day.today{border-color:var(--success) !important;}
.flatpickr-months .flatpickr-month,.flatpickr-weekdays,.flatpickr-weekday{
  background:var(--surface) !important;color:var(--muted) !important;}
.flatpickr-current-month{color:var(--text) !important;}
.flatpickr-current-month input.cur-year{color:var(--text) !important;}
.numInput{color:var(--text) !important;}
.flatpickr-prev-month svg,.flatpickr-next-month svg{fill:var(--muted) !important;}
.flatpickr-day.inRange{background:rgba(79,122,255,.15) !important;border-color:transparent !important;}
.flatpickr-day.startRange,.flatpickr-day.endRange{background:var(--accent) !important;border-color:var(--accent) !important;}

/* ── RESPONSIVE ─────────────────────────────────────── */
@media(max-width:900px){
  .table-wrap{overflow-x:auto;}
  .page-hdr{flex-direction:column;}
  .filter-row form{min-width:200px;}
  .date-filter{flex-direction:column;align-items:flex-start;}
}
</style>
</head>
<body>

<!-- Navigation -->
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="nav-links">
        <a href="/dashboard">Dashboard</a>
        <a href="/students">Students</a>
        <a href="/fees" class="active">Fees</a>
        <?php if ($isAdmin): ?><a href="/admin">Admin</a><?php endif; ?>
    </div>
</nav>

<!-- Main Content -->
<div class="page">

    <!-- Page Header -->
    <div class="page-hdr">
        <div>
            <h2>Fee Records</h2>
            <p>View all hostel fees
            <?= $filterStudentName ? ' · Filtered for <strong>'.htmlspecialchars($filterStudentName).'</strong>' : '' ?>
            </p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="add.php<?= $filterStudentId ? '?student_id='.$filterStudentId : '' ?>" class="btn btn-primary">+ New Fee</a>
        <?php endif; ?>
    </div>

    <!-- Summary Statistics Strip -->
    <div class="stat-strip">
        <div class="stat-chip">
            <strong><?= $cnt['total'] ?></strong>Total Records
        </div>
        <div class="stat-chip s-green">
            <strong><?= $cnt['paid'] ?></strong>Paid
        </div>
        <div class="stat-chip s-amber">
            <strong><?= $cnt['unpaid'] ?></strong>Pending
        </div>
        <div class="stat-chip s-red">
            <strong><?= $cnt['overdue'] ?></strong>Overdue
        </div>
        <div class="stat-chip">
            <strong>£<?= number_format($cnt['total_amt'], 2) ?></strong>Total Amount
        </div>
        <div class="stat-chip s-green">
            <strong>£<?= number_format($cnt['paid_amt'], 2) ?></strong>Collected
        </div>
        <div class="stat-chip s-red">
            <strong>£<?= number_format($cnt['outstanding'], 2) ?></strong>Outstanding
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-row">
        <form method="get">
            <?php if ($filterStudentId): ?>
            <input type="hidden" name="student_id" value="<?= $filterStudentId ?>">
            <?php endif; ?>

            <input type="text" name="search" placeholder="🔍 Search receipt, name, or ID…"
                   value="<?= htmlspecialchars($search) ?>">

            <select name="filter">
                <option value="">All Status</option>
                <option value="paid"    <?= $filterStatus==='paid'    ?'selected':'' ?>>✅ Paid</option>
                <option value="unpaid"  <?= $filterStatus==='unpaid'  ?'selected':'' ?>>⏳ Unpaid</option>
                <option value="overdue" <?= $filterStatus==='overdue' ?'selected':'' ?>>⚠ Overdue</option>
            </select>

            <select name="fee_type">
                <option value="">All Types</option>
                <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
                <option value="<?= $type ?>" <?= $filterType===$type?'selected':'' ?>><?= ucfirst($type) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- ✅ AUTO CALENDAR: Date-range filter powered by Flatpickr -->
            <div class="date-filter">
                <label>Due</label>
                <input type="text" id="date_from" name="date_from" placeholder="From"
                       value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                <input type="text" id="date_to" name="date_to" placeholder="To"
                       value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search || $filterStatus || $filterType || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
            <a href="index.php<?= $filterStudentId ? '?student_id='.$filterStudentId : '' ?>" class="btn btn-ghost">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Fee Records Table -->
    <div class="table-wrap">
        <?php if (empty($fees)): ?>
        <div class="empty-state">
            <span class="icon">💷</span>
            <h3>No fee records found</h3>
            <p>Try a different search or filter, or add a new fee.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Late Fine</th>
                    <th>Total Due</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fees as $f):
                $due      = new DateTime($f['due_date']);
                $fineRate = (float)($f['fine_rate'] ?? $FINE_RATE);
                $fineCap  = (float)($f['fine_cap']  ?? $FINE_CAP);
                $fine     = 0;
                $status   = 'unpaid';
                $rowClass = '';

                if ($f['is_paid']) {
                    $status   = 'paid';
                    $rowClass = 'row-cleared';
                } elseif ($today > $due) {
                    $days     = $due->diff($today)->days;
                    $fine     = min($days * $fineRate, $fineCap);
                    $status   = 'overdue';
                    $rowClass = 'row-late';
                }

                $total    = $f['amount'] + $fine;
                $labels   = ['paid'=>'✅ Cleared','unpaid'=>'⏳ Unpaid','overdue'=>'⚠ Overdue'];
                $bclass   = ['paid'=>'b-cleared','unpaid'=>'b-unpaid','overdue'=>'b-overdue'];

                // Encode data attributes for JS to use
                $dataFineRate = $fineRate;
                $dataFineCap  = $fineCap;
                $dataDue      = $f['due_date'];
                $dataPaid     = $f['is_paid'] ? '1' : '0';
                $dataPaidAt   = $f['paid_at'] ?? '';
                $rcpt         = htmlspecialchars($f['receipt_number']);
            ?>
            <tr class="<?= $rowClass ?>"
                id="row-<?= $rcpt ?>"
                data-receipt="<?= $rcpt ?>"
                data-amount="<?= $f['amount'] ?>"
                data-fine-rate="<?= $dataFineRate ?>"
                data-fine-cap="<?= $dataFineCap ?>"
                data-due="<?= $dataDue ?>"
                data-is-paid="<?= $dataPaid ?>"
                data-paid-at="<?= htmlspecialchars($dataPaidAt) ?>">

                <td><span class="rcpt"><?= $rcpt ?></span></td>

                <td>
                    <div style="font-size:13px;"><?= htmlspecialchars($f['full_name'] ?? '—') ?></div>
                    <?php if (!empty($f['student_number'])): ?>
                    <div class="rcpt"><?= htmlspecialchars($f['student_number']) ?></div>
                    <?php endif; ?>
                </td>

                <td><span class="type-pill"><?= ucfirst($f['fee_type']) ?></span></td>

                <td class="mono">£<?= number_format($f['amount'], 2) ?></td>

                <!-- Fine column — updated by JS on toggle -->
                <td class="fine-col" id="fine-<?= $rcpt ?>">
                    <?php if ($f['is_paid']): ?>
                    <span style="color:var(--faint);">—</span>
                    <?php elseif ($fine > 0): ?>
                    +£<?= number_format($fine, 2) ?>
                    <?php else: ?>
                    <span style="color:var(--faint);">—</span>
                    <?php endif; ?>
                </td>

                <!-- Total column — updated by JS on toggle -->
                <td class="total-col" id="total-<?= $rcpt ?>">
                    <?php if ($f['is_paid']): ?>
                    <span style="color:var(--faint);">—</span>
                    <?php else: ?>
                    £<?= number_format($total, 2) ?>
                    <?php endif; ?>
                </td>

                <!-- Due Date column — updated by JS on toggle -->
                <td style="font-size:12px;color:var(--muted);" id="due-<?= $rcpt ?>">
                    <?php if ($f['is_paid']): ?>
                    <span style="color:var(--faint);">—</span>
                    <?php else: ?>
                    <?= $due->format('d M Y') ?>
                    <?php if ($status === 'overdue'): ?>
                    <div style="font-size:10px;color:var(--danger);" class="days-late">
                        <?= $due->diff($today)->days ?>d late
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>

                <!-- Status badge — updated by JS on toggle -->
                <td id="status-<?= $rcpt ?>">
                    <span class="badge <?= $bclass[$status] ?>"><?= $labels[$status] ?></span>
                </td>

                <!-- Paid On column — updated by JS on toggle -->
                <td style="font-size:11px;color:var(--muted);" id="paidat-<?= $rcpt ?>">
                    <?= $f['paid_at'] ? (new DateTime($f['paid_at']))->format('d M Y, H:i') : '—' ?>
                </td>

                <!-- Actions -->
                <td style="white-space:nowrap;">
                    <?php if ($isAdmin): ?>
                    <a href="edit.php?id=<?= urlencode($f['receipt_number']) ?>" class="act-link act-edit">Edit</a>
                    <a href="delete.php?id=<?= urlencode($f['receipt_number']) ?>" class="act-link act-delete">Del</a>
                    <?php endif; ?>

                    <!-- ✅ SMOOTH TOGGLE BUTTON (AJAX — no page reload) -->
                    <button
                        class="btn-pay-toggle <?= $f['is_paid'] ? 'state-unpay' : 'state-pay' ?>"
                        id="btn-<?= $rcpt ?>"
                        data-receipt="<?= $rcpt ?>"
                        onclick="togglePay(this)"
                        title="<?= $f['is_paid'] ? 'Click to mark as unpaid' : 'Click to mark as paid' ?>">
                        <?= $f['is_paid'] ? '↩ Unmark' : '✓ Mark Paid' ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Fine policy note -->
    <p style="margin-top:12px;font-size:11px;color:var(--faint);text-align:center;">
        Late fine policy: £<?= number_format($FINE_RATE,2) ?>/day · max £<?= number_format($FINE_CAP,2) ?> per fee ·
        calculated automatically on overdue fees · per-fee overrides apply where set.
    </p>
</div>

<!-- Toast notification -->
<div id="toast"></div>

<script>
/* ════════════════════════════════════════════════════════
   FLATPICKR — Auto-calendar date pickers
   Both pickers are linked: "From" constrains "To" max
════════════════════════════════════════════════════════ */
const fpFrom = flatpickr("#date_from", {
    dateFormat: "Y-m-d",
    allowInput: true,
    disableMobile: false,
    onChange: function(sel) {
        if (sel[0]) fpTo.set('minDate', sel[0]);
    }
});
const fpTo = flatpickr("#date_to", {
    dateFormat: "Y-m-d",
    allowInput: true,
    disableMobile: false,
    onChange: function(sel) {
        if (sel[0]) fpFrom.set('maxDate', sel[0]);
    }
});

/* ════════════════════════════════════════════════════════
   TOAST HELPER
════════════════════════════════════════════════════════ */
function showToast(msg, type = 'green') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show t-' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 2800);
}

/* ════════════════════════════════════════════════════════
   FINE CALCULATOR
   Mirrors the PHP logic: calculates fine based on days
   overdue, fine_rate and fine_cap from data attributes.
════════════════════════════════════════════════════════ */
function calcFine(dueStr, fineRate, fineCap) {
    const due   = new Date(dueStr + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    if (today <= due) return 0;
    const days = Math.floor((today - due) / 86400000);
    return Math.min(days * fineRate, fineCap);
}

/* ════════════════════════════════════════════════════════
   FORMAT DATE HELPER
════════════════════════════════════════════════════════ */
function fmtDate(iso) {
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
}
function fmtDateTime(iso) {
    const d = new Date(iso.replace(' ','T'));
    return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) +
           ', ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}

/* ════════════════════════════════════════════════════════
   TOGGLE PAY — Main AJAX function
   1. Show loading spinner on button
   2. POST to server via fetch
   3. Update ALL affected cells instantly
   4. Apply row transition classes
   5. Show toast
════════════════════════════════════════════════════════ */
async function togglePay(btn) {
    const receipt = btn.dataset.receipt;
    const row     = document.getElementById('row-' + receipt);

    // Read row data
    const amount   = parseFloat(row.dataset.amount);
    const fineRate = parseFloat(row.dataset.fineRate);
    const fineCap  = parseFloat(row.dataset.fineCap);
    const dueStr   = row.dataset.due;

    // Disable button + show spinner
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> …';

    try {
        const res  = await fetch('index.php?ajax_toggle=' + encodeURIComponent(receipt));
        const data = await res.json();

        if (!data.ok) throw new Error('Server error');

        const nowPaid = data.now_paid;

        /* ── Update data attributes for future toggles ── */
        row.dataset.isPaid  = nowPaid ? '1' : '0';
        row.dataset.paidAt  = data.paid_at ?? '';

        /* ── Fine cell ────────────────────────────────── */
        const fineEl  = document.getElementById('fine-' + receipt);
        /* ── Total cell ───────────────────────────────── */
        const totalEl = document.getElementById('total-' + receipt);
        /* ── Due date cell ────────────────────────────── */
        const dueEl   = document.getElementById('due-' + receipt);
        /* ── Status badge ─────────────────────────────── */
        const statEl  = document.getElementById('status-' + receipt);
        /* ── Paid-on cell ─────────────────────────────── */
        const paidEl  = document.getElementById('paidat-' + receipt);

        if (nowPaid) {
            /* ══ MARKED AS PAID ══════════════════════════
               Fine     → —
               Total    → —
               Due Date → —
               Status   → ✅ Cleared
               Paid On  → now
            ═════════════════════════════════════════════ */
            fineEl.innerHTML  = '<span style="color:var(--faint);">—</span>';
            totalEl.innerHTML = '<span style="color:var(--faint);">—</span>';
            dueEl.innerHTML   = '<span style="color:var(--faint);">—</span>';
            statEl.innerHTML  = '<span class="badge b-cleared">✅ Cleared</span>';
            paidEl.textContent = fmtDateTime(data.paid_at);

            // Row visual
            row.classList.remove('row-late');
            row.classList.add('row-cleared');

            // Button → unmark state
            btn.className   = 'btn-pay-toggle state-unpay';
            btn.textContent = '↩ Unmark';
            btn.title       = 'Click to mark as unpaid';

            showToast('✅ Fee marked as cleared', 'green');

        } else {
            /* ══ UNMARKED (back to unpaid / overdue) ══════
               Recalculate fine based on due date
               Restore all cells
            ═════════════════════════════════════════════ */
            const fine  = calcFine(dueStr, fineRate, fineCap);
            const total = amount + fine;

            // Fine cell
            fineEl.innerHTML = fine > 0
                ? '+£' + fine.toFixed(2)
                : '<span style="color:var(--faint);">—</span>';

            // Total cell
            totalEl.textContent = '£' + total.toFixed(2);

            // Due date cell
            const due   = new Date(dueStr + 'T00:00:00');
            const today = new Date(); today.setHours(0,0,0,0);
            const overdue = today > due;
            if (overdue) {
                const days = Math.floor((today - due) / 86400000);
                dueEl.innerHTML = fmtDate(dueStr) +
                    '<div style="font-size:10px;color:var(--danger);" class="days-late">' + days + 'd late</div>';
            } else {
                dueEl.textContent = fmtDate(dueStr);
            }

            // Status badge
            if (overdue) {
                statEl.innerHTML = '<span class="badge b-overdue">⚠ Overdue</span>';
                row.classList.add('row-late');
            } else {
                statEl.innerHTML = '<span class="badge b-unpaid">⏳ Unpaid</span>';
            }
            row.classList.remove('row-cleared');

            // Paid-on cell
            paidEl.textContent = '—';

            // Button → mark paid state
            btn.className   = 'btn-pay-toggle state-pay';
            btn.textContent = '✓ Mark Paid';
            btn.title       = 'Click to mark as paid';

            showToast('↩ Fee unmarked — fine recalculated', 'amber');
        }

    } catch (err) {
        showToast('❌ Something went wrong — please try again', 'amber');
        // Restore button text based on current state
        const isPaid = row.dataset.isPaid === '1';
        btn.className   = 'btn-pay-toggle ' + (isPaid ? 'state-unpay' : 'state-pay');
        btn.textContent = isPaid ? '↩ Unmark' : '✓ Mark Paid';
    }

    btn.disabled = false;
}
</script>
</body>
</html>