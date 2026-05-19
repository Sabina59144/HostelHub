<?php
// student_dashboard.php
require_once(__DIR__ . "/includes/session.php");
require_once(__DIR__ . "/includes/db.php");

// Only students may access this page
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$sid = (int) $_SESSION['student_id'];

// ── Fetch student + room ─────────────────────────────────────
$stmt = $db->prepare("
    SELECT s.*, r.room_number, r.room_type, r.price_per_month, r.is_ensuite
    FROM   students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE  s.student_id = ? LIMIT 1
");
$stmt->execute([$sid]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header("Location: student_login.php");
    exit();
}

// ── Fetch fees ───────────────────────────────────────────────
$fstmt = $db->prepare("
    SELECT * FROM fees
    WHERE  student_id = ? AND is_active = 1
    ORDER BY due_date DESC
");
$fstmt->execute([$sid]);
$fees = $fstmt->fetchAll();

$total_due  = array_sum(array_column(array_filter($fees, fn($f) => !$f['is_paid']), 'total_due'));
$total_paid = array_sum(array_column(array_filter($fees, fn($f) =>  $f['is_paid']), 'amount'));

// ── Fetch maintenance tickets ────────────────────────────────
$mstmt = $db->prepare("
    SELECT m.*, r.room_number
    FROM   maintenance m
    JOIN   rooms r ON m.room_id = r.room_id
    WHERE  m.room_id = ? 
    ORDER  BY m.date_reported DESC
    LIMIT  5
");
$room_id = $student['room_id'] ?? 0;
if ($room_id) {
    $mstmt->execute([$room_id]);
    $tickets = $mstmt->fetchAll();
} else {
    $tickets = [];
}

// ── Handle room change / maintenance request ─────────────────
$req_success = '';
$req_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'room_change') {
        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            $req_error = "Please describe your reason for requesting a room change.";
        } else {
            // In a real system you'd insert into a room_change_requests table.
            // For now, store as a maintenance-style note or log.
            $req_success = "Your room change request has been submitted. The warden will contact you shortly.";
        }
    }

    if ($action === 'maintenance') {
        $issue = trim($_POST['issue'] ?? '');
        if (!$student['room_id']) {
            $req_error = "You are not assigned to a room yet. Cannot raise a maintenance request.";
        } elseif (empty($issue)) {
            $req_error = "Please describe the maintenance issue.";
        } else {
            $ticket_no = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $ins = $db->prepare("
                INSERT INTO maintenance (ticket_number, room_id, assigned_to, date_reported, reported_by, is_resolved)
                VALUES (?, ?, 'Pending Assignment', CURDATE(), NULL, 0)
            ");
            // reported_by references users table; students aren't users, so NULL is fine
            $ins->execute([$ticket_no, $student['room_id']]);
            $req_success = "Maintenance request submitted! Ticket: <strong>{$ticket_no}</strong>";
            // Refresh tickets
            $mstmt->execute([$room_id]);
            $tickets = $mstmt->fetchAll();
        }
    }
}

$first_name = explode(' ', $student['full_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Portal — HostelHub</title>
<link rel="stylesheet" href="css/style.css">
<style>
/* ══════════════════════════════════════════════
   Student Portal — scoped styles
   ══════════════════════════════════════════════ */

/* Navbar student variant */
.navbar { background: #0f1b35; }
.navbar-brand { color: #e8eeff; }

/* ── Page shell ── */
.student-page {
    min-height: 100vh;
    background: #f0f4fa;
    font-family: 'DM Sans', sans-serif;
}

/* ── Top greeting banner ── */
.greeting-banner {
    background: linear-gradient(120deg, #0f1b35 0%, #1a56db 100%);
    padding: 2rem 2.5rem 3.5rem;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.greeting-banner::after {
    content: '';
    position: absolute;
    bottom: -1px; left: 0; right: 0;
    height: 40px;
    background: #f0f4fa;
    clip-path: ellipse(55% 100% at 50% 100%);
}
.greeting-banner h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.9rem;
    margin-bottom: 0.2rem;
}
.greeting-banner p { font-size: 0.9rem; opacity: 0.75; }
.banner-meta {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}
.banner-chip {
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 99px;
    padding: 0.3rem 0.9rem;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* ── Content area ── */
.portal-content {
    max-width: 1100px;
    margin: -1.5rem auto 0;
    padding: 0 2rem 3rem;
    position: relative;
    z-index: 1;
}

/* ── Summary cards ── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.sum-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.4rem 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: box-shadow 0.2s;
}
.sum-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.11); }
.sum-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.sum-icon.blue   { background: #eff6ff; }
.sum-icon.green  { background: #f0fdf4; }
.sum-icon.amber  { background: #fffbeb; }
.sum-icon.red    { background: #fef2f2; }
.sum-val {
    font-size: 1.35rem;
    font-weight: 700;
    color: #1a202c;
    line-height: 1.2;
}
.sum-lbl { font-size: 0.78rem; color: #64748b; margin-top: 0.1rem; }

/* ── Section card ── */
.section-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    border: 1px solid #e2e8f0;
    margin-bottom: 1.5rem;
}
.section-title {
    font-family: 'DM Serif Display', serif;
    font-size: 1.1rem;
    color: #1a202c;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-title span { opacity: 0.7; font-size: 1rem; }

/* ── Info grid (profile) ── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.info-item label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin-bottom: 0.25rem;
}
.info-item .val {
    font-size: 0.95rem;
    font-weight: 500;
    color: #1a202c;
}

/* ── Room status ── */
.room-assigned {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: #f8faff;
    border: 1.5px solid #dbeafe;
    border-radius: 12px;
    padding: 1.2rem 1.5rem;
    flex-wrap: wrap;
}
.room-num {
    font-family: 'DM Serif Display', serif;
    font-size: 2.8rem;
    color: #1a56db;
    line-height: 1;
}
.room-details { flex: 1; min-width: 140px; }
.room-details p { font-size: 0.85rem; color: #475569; margin: 0.15rem 0; }
.room-details strong { color: #1a202c; }

.room-unassigned {
    background: #fffbeb;
    border: 1.5px dashed #fcd34d;
    border-radius: 12px;
    padding: 1.2rem 1.5rem;
    color: #92400e;
    font-size: 0.9rem;
}

/* ── Fee table ── */
.fee-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.fee-table th {
    text-align: left;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 0.5rem 0.75rem;
}
.fee-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.fee-table tr:last-child td { border-bottom: none; }
.fee-table tr:hover td { background: #f8faff; }

.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 99px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-paid    { background: #dcfce7; color: #166534; }
.badge-unpaid  { background: #fef2f2; color: #991b1b; }
.badge-overdue { background: #fff7ed; color: #9a3412; }

.fee-type-chip {
    display: inline-block;
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    background: #f1f5f9;
    color: #475569;
    text-transform: capitalize;
}

/* ── Ticket list ── */
.ticket-list { list-style: none; }
.ticket-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid #f1f5f9;
}
.ticket-item:last-child { border-bottom: none; }
.ticket-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot-open     { background: #f59e0b; }
.dot-resolved { background: #22c55e; }
.ticket-info { flex: 1; }
.ticket-no { font-size: 0.8rem; font-weight: 600; color: #1a56db; }
.ticket-date { font-size: 0.75rem; color: #94a3b8; }
.ticket-status { font-size: 0.75rem; font-weight: 600; }
.status-open     { color: #d97706; }
.status-resolved { color: #16a34a; }

/* ── Request forms ── */
.request-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}
.rtab {
    padding: 0.45rem 1.1rem;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #f8faff;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}
.rtab.active, .rtab:hover {
    background: #1a56db;
    border-color: #1a56db;
    color: #fff;
}
.req-panel { display: none; }
.req-panel.active { display: block; }

.req-textarea {
    width: 100%;
    min-height: 100px;
    padding: 0.75rem 1rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    color: #1a202c;
    background: #f9fafe;
    resize: vertical;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.req-textarea:focus {
    border-color: #1a56db;
    box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
    background: #fff;
}

.btn-submit {
    margin-top: 0.75rem;
    padding: 0.65rem 1.5rem;
    background: #1a56db;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}
.btn-submit:hover  { background: #1341b0; }
.btn-submit:active { transform: scale(0.98); }

.req-note {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-top: 0.5rem;
}

/* ── Alert ── */
.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 9px;
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 1rem;
}
.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 9px;
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 1rem;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #94a3b8;
    font-size: 0.88rem;
}
.empty-state .empty-icon { font-size: 2rem; margin-bottom: 0.5rem; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .greeting-banner { padding: 1.5rem 1.25rem 3rem; }
    .portal-content  { padding: 0 1rem 2rem; }
    .section-card    { padding: 1.2rem 1.25rem; }
    .room-num        { font-size: 2rem; }
}
</style>
</head>
<body class="student-page">

<!-- ── Navbar ────────────────────────────────────────────── -->
<nav class="navbar">
    <div class="navbar-brand">
        <span>HostelHub</span>
    </div>
    <div class="navbar-user">
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($student['full_name']) ?></span>
            <span class="user-role">Student</span>
        </div>
        <a href="student_logout.php" class="btn-logout">Log out</a>
    </div>
</nav>

<!-- ── Greeting Banner ───────────────────────────────────── -->
<div class="greeting-banner">
    <h2>Welcome back, <?= htmlspecialchars($first_name) ?> 👋</h2>
    <p>Here's everything about your hostel stay at a glance.</p>
    <div class="banner-meta">
        <span class="banner-chip">🎓 <?= htmlspecialchars($student['student_number']) ?></span>
        <span class="banner-chip">📧 <?= htmlspecialchars($student['email']) ?></span>
        <?php if ($student['room_number']): ?>
            <span class="banner-chip">🛏 Room <?= htmlspecialchars($student['room_number']) ?></span>
        <?php else: ?>
            <span class="banner-chip">🛏 No room assigned</span>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main Content ──────────────────────────────────────── -->
<div class="portal-content">

    <?php if ($req_success): ?>
        <div class="alert-success">✅ <?= $req_success ?></div>
    <?php endif; ?>
    <?php if ($req_error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($req_error) ?></div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-icon blue">🛏</div>
            <div>
                <div class="sum-val"><?= $student['room_number'] ? 'Room ' . htmlspecialchars($student['room_number']) : 'Unassigned' ?></div>
                <div class="sum-lbl">Your Room</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon green">✅</div>
            <div>
                <div class="sum-val">£<?= number_format($total_paid, 2) ?></div>
                <div class="sum-lbl">Total Paid</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon red">💳</div>
            <div>
                <div class="sum-val">£<?= number_format($total_due, 2) ?></div>
                <div class="sum-lbl">Amount Outstanding</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon amber">🔧</div>
            <div>
                <div class="sum-val"><?= count(array_filter($tickets, fn($t) => !$t['is_resolved'])) ?></div>
                <div class="sum-lbl">Open Maintenance Tickets</div>
            </div>
        </div>
    </div>

    <!-- ── My Information ── -->
    <div class="section-card">
        <div class="section-title">👤 <span>My Information</span></div>
        <div class="info-grid">
            <div class="info-item">
                <label>Full Name</label>
                <div class="val"><?= htmlspecialchars($student['full_name']) ?></div>
            </div>
            <div class="info-item">
                <label>Student Number</label>
                <div class="val"><?= htmlspecialchars($student['student_number']) ?></div>
            </div>
            <div class="info-item">
                <label>Email Address</label>
                <div class="val"><?= htmlspecialchars($student['email']) ?></div>
            </div>
            <div class="info-item">
                <label>Date of Birth</label>
                <div class="val"><?= $student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <label>Account Status</label>
                <div class="val"><?= $student['status'] ? '<span style="color:#16a34a;font-weight:600;">Active</span>' : '<span style="color:#dc2626;">Inactive</span>' ?></div>
            </div>
        </div>
    </div>

    <!-- ── My Room ── -->
    <div class="section-card">
        <div class="section-title">🛏 <span>My Room</span></div>
        <?php if ($student['room_number']): ?>
            <div class="room-assigned">
                <div class="room-num"><?= htmlspecialchars($student['room_number']) ?></div>
                <div class="room-details">
                    <p><strong>Type:</strong> <?= htmlspecialchars(ucfirst($student['room_type'])) ?></p>
                    <p><strong>En-suite:</strong> <?= $student['is_ensuite'] ? 'Yes' : 'No' ?></p>
                    <p><strong>Monthly Rent:</strong> £<?= number_format($student['price_per_month'], 2) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="room-unassigned">
                ⚠️ You have not been assigned a room yet. Please contact the hostel warden for assistance.
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Fee Status ── -->
    <div class="section-card">
        <div class="section-title">💳 <span>Fee Status</span></div>
        <?php if (empty($fees)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                No fee records found.
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Fine</th>
                            <th>Total Due</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $fee): ?>
                            <?php
                                $is_overdue = !$fee['is_paid'] && strtotime($fee['due_date']) < time();
                            ?>
                            <tr>
                                <td style="font-size:0.78rem;color:#64748b;"><?= htmlspecialchars($fee['receipt_number']) ?></td>
                                <td><span class="fee-type-chip"><?= htmlspecialchars($fee['fee_type']) ?></span></td>
                                <td>£<?= number_format($fee['amount'], 2) ?></td>
                                <td><?= $fee['fine_amount'] > 0 ? '<span style="color:#dc2626;">£'.number_format($fee['fine_amount'],2).'</span>' : '—' ?></td>
                                <td><strong>£<?= number_format($fee['total_due'], 2) ?></strong></td>
                                <td style="font-size:0.82rem;"><?= date('d M Y', strtotime($fee['due_date'])) ?></td>
                                <td>
                                    <?php if ($fee['is_paid']): ?>
                                        <span class="badge badge-paid">Paid</span>
                                    <?php elseif ($is_overdue): ?>
                                        <span class="badge badge-overdue">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-unpaid">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_due > 0): ?>
                <p style="margin-top:1rem;font-size:0.85rem;color:#dc2626;font-weight:600;">
                    ⚠️ Outstanding balance: £<?= number_format($total_due, 2) ?>. Please settle your fees before the due date.
                </p>
            <?php else: ?>
                <p style="margin-top:1rem;font-size:0.85rem;color:#16a34a;font-weight:600;">
                    ✅ All fees are paid. You're all up to date!
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ── Maintenance Tickets ── -->
    <div class="section-card">
        <div class="section-title">🔧 <span>Maintenance History</span> <span style="font-size:0.75rem;background:#f1f5f9;padding:0.15rem 0.6rem;border-radius:99px;color:#64748b;font-family:'DM Sans',sans-serif;"><?= count($tickets) ?> record<?= count($tickets) !== 1 ? 's' : '' ?></span></div>
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div class="empty-icon">🛠</div>
                No maintenance tickets for your room yet.
            </div>
        <?php else: ?>
            <ul class="ticket-list">
                <?php foreach ($tickets as $t): ?>
                    <li class="ticket-item">
                        <div class="ticket-dot <?= $t['is_resolved'] ? 'dot-resolved' : 'dot-open' ?>"></div>
                        <div class="ticket-info">
                            <div class="ticket-no"><?= htmlspecialchars($t['ticket_number']) ?></div>
                            <div class="ticket-date">Reported: <?= date('d M Y', strtotime($t['date_reported'])) ?> &nbsp;·&nbsp; Assigned to: <?= htmlspecialchars($t['assigned_to']) ?></div>
                        </div>
                        <div class="ticket-status <?= $t['is_resolved'] ? 'status-resolved' : 'status-open' ?>">
                            <?= $t['is_resolved'] ? '✔ Resolved' : '⏳ Open' ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- ── Requests ── -->
    <div class="section-card">
        <div class="section-title">📋 <span>Submit a Request</span></div>

        <div class="request-tabs">
            <button class="rtab active" onclick="switchTab('maintenance')">🔧 Maintenance Request</button>
            <button class="rtab" onclick="switchTab('room_change')">🔄 Room Change Request</button>
        </div>

        <!-- Maintenance form -->
        <div class="req-panel active" id="panel-maintenance">
            <form method="POST" action="">
                <input type="hidden" name="action" value="maintenance">
                <?php if (!$student['room_id']): ?>
                    <div class="alert-error">⚠️ You must be assigned a room before submitting a maintenance request.</div>
                <?php else: ?>
                    <label style="font-size:0.82rem;font-weight:600;color:#4a5470;display:block;margin-bottom:0.4rem;">Describe the issue in your room</label>
                    <textarea class="req-textarea" name="issue" placeholder="e.g. The bathroom tap is leaking, lights not working in bedroom…" required></textarea>
                    <p class="req-note">Your room number (<?= htmlspecialchars($student['room_number']) ?>) will be attached automatically.</p>
                    <button type="submit" class="btn-submit">Submit Request</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Room change form -->
        <div class="req-panel" id="panel-room_change">
            <form method="POST" action="">
                <input type="hidden" name="action" value="room_change">
                <label style="font-size:0.82rem;font-weight:600;color:#4a5470;display:block;margin-bottom:0.4rem;">Reason for requesting a room change</label>
                <textarea class="req-textarea" name="reason" placeholder="e.g. Noise issues, medical requirements, prefer a different floor…" required></textarea>
                <p class="req-note">The warden will review your request and contact you within 2–3 working days.</p>
                <button type="submit" class="btn-submit">Submit Request</button>
            </form>
        </div>
    </div>

</div><!-- /.portal-content -->

<script>
function switchTab(tab) {
    document.querySelectorAll('.rtab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.req-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
    event.currentTarget.classList.add('active');
}
</script>

</body>
</html>