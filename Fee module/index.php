<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 * FEE RECORDS LIST PAGE (index.php)
 * 
 * Purpose: Display all fee records with filtering, search, and AJAX payment toggle
 * Key Features:
 * - AJAX toggle for marking fees as paid/unpaid
 * - Advanced filtering (status, type, student, date range)
 * - Real-time fine calculation
 * - Responsive data table with status badges
 * ════════════════════════════════════════════════════════════════════════════
 */

/* ─────────────────────────────────────────────────────────────────────────
   INCLUDES & AUTHENTICATION
   ───────────────────────────────────────────────────────────────────────── */
// Require session setup and verify user is logged in
require_once __DIR__ . '/../includes/session.php';
requireLogin();

// Load database connection
require_once __DIR__ . '/../includes/db.php';

/* ═════════════════════════════════════════════════════════════════════════════
   AJAX ENDPOINT: TOGGLE PAYMENT STATUS
   
   When user clicks "Mark Paid" or "Unmark", JavaScript makes a fetch request
   to this endpoint. It returns JSON so the UI updates instantly without reload.
   ═════════════════════════════════════════════════════════════════════════════ */
if (isset($_GET['ajax_toggle'])) {
    // Set response header to JSON
    header('Content-Type: application/json');

    // Get receipt number from query parameter
    $id   = $_GET['ajax_toggle'];
    
    // Query current payment status
    $stmt = $db->prepare("SELECT is_paid FROM fees WHERE receipt_number = ? AND is_active = 1");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    // Exit with error if fee not found
    if (!$row) { 
        echo json_encode(['ok' => false]); 
        exit; 
    }

    // Toggle the payment status
    if ($row['is_paid']) {
        // ── UNMARK: Clear the paid status and timestamp
        $db->prepare("UPDATE fees SET is_paid = 0, paid_at = NULL WHERE receipt_number = ?")
           ->execute([$id]);
        // Return JSON response indicating now unpaid
        echo json_encode(['ok' => true, 'now_paid' => false, 'paid_at' => null]);
    } else {
        // ── MARK PAID: Set paid status and record current timestamp
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE fees SET is_paid = 1, paid_at = ? WHERE receipt_number = ?")
           ->execute([$now, $id]);
        // Return JSON response indicating now paid
        echo json_encode(['ok' => true, 'now_paid' => true, 'paid_at' => $now]);
    }
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────
   SETUP: Initialize filters, counters, and display variables
   ───────────────────────────────────────────────────────────────────────── */
// Check if current user is admin (needed for edit/delete buttons)
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
// Get current date for relative date calculations
$today   = new DateTime();

// Initialize filter variables from query parameters
$search            = trim($_GET['search'] ?? '');       // Full-text search term
$filterStudentId   = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;  // Filter by student ID
$filterStatus      = $_GET['filter']    ?? '';          // Filter by status: paid|unpaid|overdue
$filterType        = $_GET['fee_type']  ?? '';          // Filter by fee type: rent|deposit|utility|fine|laundry|other
$filterStudentName = '';                                // Will store student name if filtering by ID

// If filtering by student ID, look up their name for display
if ($filterStudentId) {
    $sRow = $db->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $sRow->execute([$filterStudentId]);
    $filterStudentName = $sRow->fetchColumn() ?: '';
}

/* ─────────────────────────────────────────────────────────────────────────
   BUILD DYNAMIC SQL QUERY
   
   Constructs WHERE clause based on active filters. Uses parameterized
   queries to prevent SQL injection.
   ───────────────────────────────────────────────────────────────────────── */
$where  = ["f.is_active = 1"];  // Only show active (non-deleted) fees
$params = [];                    // Placeholder values for prepared statement

// Apply student filter if specified
if ($filterStudentId) { 
    $where[] = "f.student_id = ?"; 
    $params[] = $filterStudentId; 
}

// Apply search filter across receipt number, student name, and ID
if (!empty($search)) {
    // LIKE queries with wildcards for flexible matching
    $where[]  = "(f.receipt_number LIKE ? OR s.full_name LIKE ? OR s.student_number LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%";
}

// Apply fee type filter
if ($filterType) { 
    $where[] = "f.fee_type = ?"; 
    $params[] = $filterType; 
}

// Apply status filter: paid/unpaid/overdue
if ($filterStatus === 'paid') {
    // Paid fees: is_paid = 1
    $where[] = "f.is_paid = 1"; 
}
elseif ($filterStatus === 'unpaid') {
    // Unpaid fees that are NOT overdue: is_paid = 0 AND due_date >= today
    $where[] = "f.is_paid = 0 AND f.due_date >= CURDATE()"; 
}
elseif ($filterStatus === 'overdue') {
    // Overdue fees: is_paid = 0 AND due_date < today
    $where[] = "f.is_paid = 0 AND f.due_date < CURDATE()"; 
}

// Build the complete SELECT statement
$sql  = "SELECT f.*, s.full_name, s.student_number FROM fees f LEFT JOIN students s ON s.student_id = f.student_id";
$sql .= " WHERE " . implode(" AND ", $where);  // Join all WHERE conditions
$sql .= " ORDER BY f.created_at DESC";         // Most recent first

// Execute query with parameters
$stmt = $db->prepare($sql);
$stmt->execute($params);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ─────────────────────────────────────────────────────────────────────────
   CALCULATE SUMMARY STATISTICS
   
   Iterate through results to calculate totals, counts, and amounts
   for the summary display at top of page.
   ───────────────────────────────────────────────────────────────────────── */
// Initialize counter array
$cnt = [
    'total'=>count($fees),           // Total fee records
    'paid'=>0,                       // Count of paid fees
    'unpaid'=>0,                     // Count of unpaid (not yet due) fees
    'overdue'=>0,                    // Count of overdue (unpaid and past due) fees
    'total_amt'=>0,                  // Sum of all fee amounts
    'paid_amt'=>0,                   // Sum of paid amounts
    'outstanding'=>0                 // Sum of unpaid/overdue amounts (including fines)
];

// Default fine rate (kr per day overdue)
$FINE_RATE = 0.50;

// Loop through each fee record
foreach ($fees as $f) {
    // Parse due date for comparison
    $due = new DateTime($f['due_date']);
    
    // Add fee amount to total
    $cnt['total_amt'] += $f['amount'];
    
    // Categorize by payment status and due date
    if ($f['is_paid']) {
        // Fee is paid
        $cnt['paid']++;
        $cnt['paid_amt'] += $f['amount'];
    } elseif ($today > $due) {
        // Fee is unpaid AND past due date (overdue)
        $cnt['overdue']++;
        
        // Calculate fine: days overdue × fine rate per day
        $days = $due->diff($today)->days;
        $fine = $days * ($f['fine_rate'] ?? $FINE_RATE);
        
        // Add both original amount and calculated fine
        $cnt['outstanding'] += $f['amount'] + $fine;
    } else {
        // Fee is unpaid but not yet due (pending)
        $cnt['unpaid']++;
        $cnt['outstanding'] += $f['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Meta information for browser -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fee Records — HostelHub</title>

<!-- Google Fonts: Syne (headings), DM Mono (monospace), Outfit (body) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Flatpickr: Date picker library with calendar UI -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
/* ══════════════════════════════════════════════════════════════════════════
   DESIGN SYSTEM: CSS Custom Properties (Variables)
   
   These variables define the entire color scheme, spacing, and typography
   for the page. Changing them updates the entire design consistently.
   ══════════════════════════════════════════════════════════════════════════ */
:root{
  --bg:#0e1117;           /* Main background color (very dark blue) */
  --surface:#161b27;      /* Secondary background (cards, surface) */
  --card:#1c2235;         /* Card/container background (lighter than surface) */
  --border:#2a3148;       /* Border color (dark blue-gray) */
  --accent:#4f7aff;       /* Primary accent (bright blue) */
  --success:#22d3a5;      /* Success state (green) */
  --warning:#fbbf24;      /* Warning state (amber/yellow) */
  --danger:#f87171;       /* Danger/error state (red) */
  --text:#e8eaf6;         /* Primary text color (light) */
  --muted:#8892b0;        /* Secondary text/muted (gray) */
  --faint:#3a4260;        /* Very muted text (lighter gray) */
  --radius:14px;          /* Standard border radius */
}

/* Reset all default browser styles */
*{box-sizing:border-box;margin:0;padding:0;}

/* Base body styling */
body{
  background:var(--bg);
  color:var(--text);
  font-family:'Outfit',sans-serif;
  min-height:100vh;
}

/* ──────────────────────────────────────────────────────────────────────────
   TOPNAV: Fixed navigation bar at top
   ────────────────────────────────────────────────────────────────────────── */
.topnav{
  background:var(--surface);
  border-bottom:1px solid var(--border);
  padding:0 32px;
  height:60px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  position:sticky;
  top:0;
  z-index:100;  /* Ensure it stays on top when scrolling */
}

.brand{
  font-family:'Syne',sans-serif;
  font-weight:800;
  font-size:20px;
  color:var(--text);
}
.brand span{color:var(--accent);}  /* "Hub" in accent color */

.nav-links{display:flex;gap:4px;}
.nav-links a{
  padding:6px 14px;
  border-radius:8px;
  font-size:13px;
  font-weight:500;
  color:var(--muted);
  text-decoration:none;
  transition:all .15s;
}
.nav-links a:hover{background:var(--card);color:var(--text);}
.nav-links a.active{background:var(--accent);color:#fff;}

/* ──────────────────────────────────────────────────────────────────────────
   PAGE LAYOUT: Main content container
   ────────────────────────────────────────────────────────────────────────── */
.page{
  max-width:1320px;
  margin:0 auto;
  padding:28px 32px;
}

.page-hdr{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  margin-bottom:20px;
  flex-wrap:wrap;  /* Wrap on smaller screens */
}
.page-hdr h2{
  font-family:'Syne',sans-serif;
  font-size:24px;
  font-weight:800;
  margin-bottom:4px;
}
.page-hdr p{color:var(--muted);font-size:13px;}

/* ──────────────────────────────────────────────────────────────────────────
   BUTTONS: Primary and secondary button styles
   ────────────────────────────────────────────────────────────────────────── */
.btn{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:9px 18px;
  border-radius:9px;
  font-size:13px;
  font-weight:600;
  text-decoration:none;
  transition:all .15s;
  border:1px solid transparent;
  cursor:pointer;
  font-family:'Outfit',sans-serif;
}
.btn-primary{background:var(--accent);color:#fff;}
.btn-primary:hover{background:#3d68e8;}
.btn-ghost{background:var(--card);color:var(--text);border-color:var(--border);}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent);}

/* ──────────────────────────────────────────────────────────────────────────
   FILTER ROW: Search and filter controls
   ────────────────────────────────────────────────────────────────────────── */
.filter-row{
  display:flex;
  gap:10px;
  margin-bottom:20px;
  flex-wrap:wrap;
  align-items:center;
}

.filter-row form{
  display:flex;
  gap:8px;
  flex:1;
  min-width:280px;
  align-items:center;
  flex-wrap:wrap;
}

/* Search input */
.filter-row input[type=text]{
  flex:1;
  padding:9px 14px;
  border:1px solid var(--border);
  border-radius:9px;
  font-size:13px;
  background:var(--card);
  color:var(--text);
  font-family:'Outfit',sans-serif;
  outline:none;
  transition:border-color .15s;
}
.filter-row input[type=text]:focus{border-color:var(--accent);}

/* Dropdown selects */
.filter-row select{
  padding:9px 12px;
  border:1px solid var(--border);
  border-radius:9px;
  font-size:13px;
  background:var(--card);
  color:var(--text);
  font-family:'Outfit',sans-serif;
  outline:none;
  cursor:pointer;
}
.filter-row select:focus{border-color:var(--accent);}

/* Date range filter */
.date-filter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.date-filter label{font-size:11px;color:var(--muted);font-weight:600;letter-spacing:.04em;text-transform:uppercase;}
.date-filter input{
  width:140px;
  padding:8px 12px;
  border:1px solid var(--border);
  border-radius:8px;
  font-size:12px;
  background:var(--card);
  color:var(--text);
  font-family:'DM Mono',monospace;
  outline:none;
  cursor:pointer;
}
.date-filter input:focus{border-color:var(--accent);}

/* ──────────────────────────────────────────────────────────────────────────
   STAT STRIP: Summary KPI cards at top
   ────────────────────────────────────────────────────────────────────────── */
.stat-strip{
  display:flex;
  gap:12px;
  margin-bottom:20px;
  flex-wrap:wrap;
}

.stat-chip{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:10px;
  padding:10px 16px;
  font-size:12px;
  color:var(--muted);
  min-width:120px;
}
.stat-chip strong{
  display:block;
  font-family:'Syne',sans-serif;
  font-size:18px;
  font-weight:700;
  color:var(--text);
}

/* Color-coded stat chips */
.stat-chip.s-green strong{color:var(--success);}
.stat-chip.s-amber strong{color:var(--warning);}
.stat-chip.s-red  strong{color:var(--danger);}
.stat-chip.s-blue strong{color:var(--accent);}

/* ──────────────────────────────────────────────────────────────────────────
   TABLE: Main data table styling
   ────────────────────────────────────────────────────────────────────────── */
.table-wrap{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
}

.table-wrap table{width:100%;border-collapse:collapse;}

.table-wrap thead th{
  background:var(--surface);
  padding:11px 14px;
  font-size:10px;
  font-weight:700;
  letter-spacing:.07em;
  text-transform:uppercase;
  color:var(--muted);
  text-align:left;
  border-bottom:1px solid var(--border);
  white-space:nowrap;
}

.table-wrap td{
  padding:12px 14px;
  border-bottom:1px solid rgba(42,49,72,0.4);
  font-size:13px;
}

.table-wrap tbody tr{transition:background .1s;}
.table-wrap tbody tr:hover{background:rgba(79,122,255,0.04);}

/* Row states */
.row-late{background:rgba(248,113,113,0.06);}
.row-cleared{background:rgba(34,211,165,0.04);}

/* ──────────────────────────────────────────────────────────────────────────
   BADGES: Status indicators
   ────────────────────────────────────────────────────────────────────────── */
.badge{
  display:inline-block;
  padding:4px 12px;
  border-radius:20px;
  font-size:11px;
  font-weight:700;
}

.b-cleared{background:rgba(34,211,165,0.2);color:var(--success);}
.b-unpaid{background:rgba(251,191,36,0.2);color:var(--warning);}
.b-overdue{background:rgba(248,113,113,0.2);color:var(--danger);}

/* ──────────────────────────────────────────────────────────────────────────
   BUTTONS: Action buttons in table
   ────────────────────────────────────────────────────────────────────────── */
.btn-pay-toggle{
  padding:7px 12px;
  border:1px solid transparent;
  border-radius:7px;
  font-size:12px;
  font-weight:600;
  cursor:pointer;
  font-family:'Outfit',sans-serif;
  transition:all .15s;
  white-space:nowrap;
}

.btn-pay-toggle.state-pay{
  background:rgba(34,211,165,0.15);
  color:var(--success);
  border-color:rgba(34,211,165,0.3);
}
.btn-pay-toggle.state-pay:hover{background:rgba(34,211,165,0.25);}

.btn-pay-toggle.state-unpay{
  background:rgba(251,191,36,0.12);
  color:var(--warning);
  border-color:rgba(251,191,36,0.3);
}
.btn-pay-toggle.state-unpay:hover{background:rgba(251,191,36,0.22);}

.btn-pay-toggle:disabled{opacity:0.6;cursor:not-allowed;}

.btn-edit, .btn-delete{
  padding:7px 12px;
  border:1px solid var(--border);
  border-radius:7px;
  font-size:12px;
  font-weight:600;
  cursor:pointer;
  font-family:'Outfit',sans-serif;
  background:var(--card);
  color:var(--text);
  text-decoration:none;
  transition:all .15s;
}

.btn-edit:hover{color:var(--accent);border-color:var(--accent);}
.btn-delete:hover{color:var(--danger);border-color:var(--danger);}

/* ──────────────────────────────────────────────────────────────────────────
   LOADING SPINNER: Animated spinner for button states
   ────────────────────────────────────────────────────────────────────────── */
.spinner{
  display:inline-block;
  width:12px;
  height:12px;
  border:2px solid rgba(255,255,255,0.3);
  border-top-color:white;
  border-radius:50%;
  animation:spin .6s linear infinite;
}

@keyframes spin{to{transform:rotate(360deg);}}

/* ──────────────────────────────────────────────────────────────────────────
   TOAST NOTIFICATION: Success/error messages
   ────────────────────────────────────────────────────────────────────────── */
#toast{
  position:fixed;
  bottom:24px;
  left:24px;
  padding:12px 18px;
  background:var(--success);
  color:#fff;
  border-radius:10px;
  font-size:13px;
  font-weight:600;
  opacity:0;
  transition:opacity .2s;
  z-index:1000;
}

#toast.show{opacity:1;}
#toast.t-amber{background:var(--warning);}
#toast.t-red{background:var(--danger);}

/* ──────────────────────────────────────────────────────────────────────────
   UTILITY: Helper classes
   ────────────────────────────────────────────────────────────────────────── */
.mono{font-family:'DM Mono',monospace;font-size:12px;}

.no-data{
  text-align:center;
  padding:40px 20px;
  color:var(--muted);
}
.no-data p{margin:0;}

/* Responsive adjustments for mobile */
@media (max-width:900px){
  .page{padding:16px;}
  .table-wrap{overflow-x:auto;}
  .filter-row{flex-direction:column;align-items:stretch;}
  .filter-row form{flex-direction:column;}
  .filter-row input, .filter-row select{width:100%;}
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════════════════════
     TOPNAV: Navigation bar with logo and quick links
     ══════════════════════════════════════════════════════════════════════════ -->
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="nav-links">
        <a href="../dashboard.php">← Home</a>
        <a href="dashboard.php">Fee Dashboard</a>
        <?php if ($isAdmin): ?>
        <a href="add.php" class="btn btn-primary">➕ New Fee</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ══════════════════════════════════════════════════════════════════════════
     MAIN CONTENT
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="page">

    <!-- PAGE HEADER -->
    <div class="page-hdr">
        <div>
            <h2>Fee Records</h2>
            <p><?= $filterStudentName ? 'Fees for ' . htmlspecialchars($filterStudentName) : 'All active fee records' ?></p>
        </div>
        <a href="report.php" class="btn btn-ghost">📈 View Reports</a>
    </div>

    <!-- SUMMARY STATS -->
    <div class="stat-strip">
        <div class="stat-chip s-blue">
            <span class="label">Total</span>
            <strong><?= $cnt['total'] ?></strong>
            <span class="label">fees</span>
        </div>
        <div class="stat-chip s-green">
            <span class="label">Collected</span>
            <strong>kr <?= number_format($cnt['paid_amt'],0) ?></strong>
            <span class="label"><?= $cnt['paid'] ?> paid</span>
        </div>
        <div class="stat-chip s-amber">
            <span class="label">Pending</span>
            <strong><?= $cnt['unpaid'] ?></strong>
            <span class="label">unpaid</span>
        </div>
        <div class="stat-chip s-red">
            <span class="label">Overdue</span>
            <strong><?= $cnt['overdue'] ?></strong>
            <span class="label">fees</span>
        </div>
    </div>

    <!-- FILTER CONTROLS -->
    <div class="filter-row">
        <!-- Search form -->
        <form method="GET" style="flex:1;">
            <!-- Search by receipt, student name, or student ID -->
            <input type="text" name="search" placeholder="Search receipt, name, ID…" value="<?= htmlspecialchars($search) ?>">
            
            <!-- Filter by status -->
            <select name="filter" onchange="this.form.submit()">
                <option value="">— All Status —</option>
                <option value="paid"    <?= $filterStatus === 'paid'    ? 'selected' : '' ?>>✅ Paid</option>
                <option value="unpaid"  <?= $filterStatus === 'unpaid'  ? 'selected' : '' ?>>⏳ Unpaid</option>
                <option value="overdue" <?= $filterStatus === 'overdue' ? 'selected' : '' ?>>⚠ Overdue</option>
            </select>

            <!-- Filter by fee type -->
            <select name="fee_type" onchange="this.form.submit()">
                <option value="">— All Types —</option>
                <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>>
                    <?= ucfirst($t) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">🔍 Search</button>
        </form>

        <!-- Clear filters -->
        <?php if ($search || $filterStatus || $filterType || $filterStudentId): ?>
        <a href="index.php" class="btn btn-ghost">✕ Clear Filters</a>
        <?php endif; ?>
    </div>

    <!-- DATA TABLE -->
    <?php if (!empty($fees)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Fine</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>

            <!-- LOOP: Render each fee record as a table row -->
            <?php foreach ($fees as $f):
                // Parse dates for calculations
                $due = new DateTime($f['due_date']);
                $isOverdue = ($today > $due && !$f['is_paid']);
                
                // Calculate fine if overdue
                $fine = 0;
                if ($isOverdue) {
                    $days = $due->diff($today)->days;
                    $fine = $days * ($f['fine_rate'] ?? 0.50);
                }
                
                // Calculate total owing
                $total = $f['amount'] + $fine;
            ?>
            <tr id="row-<?= htmlspecialchars($f['receipt_number']) ?>" 
                class="<?= $f['is_paid'] ? 'row-cleared' : ($isOverdue ? 'row-late' : '') ?>"
                data-receipt="<?= htmlspecialchars($f['receipt_number']) ?>"
                data-amount="<?= $f['amount'] ?>"
                data-fine-rate="<?= $f['fine_rate'] ?? 0.50 ?>"
                data-due="<?= $f['due_date'] ?>"
                data-is-paid="<?= $f['is_paid'] ? '1' : '0' ?>"
                data-paid-at="<?= $f['paid_at'] ?? '' ?>">

                <!-- Receipt Number (monospace, unique identifier) -->
                <td class="mono"><?= htmlspecialchars($f['receipt_number']) ?></td>

                <!-- Student Name (or dash if no student assigned) -->
                <td><?= htmlspecialchars($f['full_name'] ?? '—') ?></td>

                <!-- Fee Type (rent, deposit, utility, etc.) -->
                <td><?= ucfirst(htmlspecialchars($f['fee_type'])) ?></td>

                <!-- Base Amount (without fines) -->
                <td class="mono">kr <?= number_format($f['amount'], 2) ?></td>

                <!-- Due Date (with "days late" indicator if overdue) -->
                <td id="due-<?= htmlspecialchars($f['receipt_number']) ?>">
                    <?php if ($isOverdue): ?>
                        <?= $f['due_date'] ?>
                        <div style="font-size:10px;color:var(--danger);" class="days-late">
                            <?= $due->diff($today)->days ?>d late
                        </div>
                    <?php else: ?>
                        <?= $f['due_date'] ?>
                    <?php endif; ?>
                </td>

                <!-- Fine (kr per day × days overdue, or — if not overdue) -->
                <td id="fine-<?= htmlspecialchars($f['receipt_number']) ?>" class="mono">
                    <?php if ($fine > 0): ?>
                        +kr <?= number_format($fine, 2) ?>
                    <?php else: ?>
                        <span style="color:var(--faint);">—</span>
                    <?php endif; ?>
                </td>

                <!-- Total Due (amount + fine, or — if already paid) -->
                <td id="total-<?= htmlspecialchars($f['receipt_number']) ?>" class="mono">
                    <?php if ($f['is_paid']): ?>
                        <span style="color:var(--faint);">—</span>
                    <?php else: ?>
                        kr <?= number_format($total, 2) ?>
                    <?php endif; ?>
                </td>

                <!-- Status Badge (Cleared/Unpaid/Overdue) -->
                <td id="status-<?= htmlspecialchars($f['receipt_number']) ?>">
                    <?php if ($f['is_paid']): ?>
                        <span class="badge b-cleared">✅ Cleared</span>
                    <?php elseif ($isOverdue): ?>
                        <span class="badge b-overdue">⚠ Overdue</span>
                    <?php else: ?>
                        <span class="badge b-unpaid">⏳ Unpaid</span>
                    <?php endif; ?>
                </td>

                <!-- Paid On (timestamp when payment was recorded, or — if unpaid) -->
                <td id="paidat-<?= htmlspecialchars($f['receipt_number']) ?>" style="font-size:11px;">
                    <?php if ($f['paid_at']): ?>
                        <?= substr($f['paid_at'], 0, 10) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>

                <!-- Action Buttons (Pay/Edit/Delete) -->
                <td style="text-align:right;">
                    <!-- AJAX Toggle Button: Mark Paid / Unmark -->
                    <button type="button" class="btn-pay-toggle <?= $f['is_paid'] ? 'state-unpay' : 'state-pay' ?>"
                            data-receipt="<?= htmlspecialchars($f['receipt_number']) ?>"
                            onclick="togglePay(this)"
                            title="<?= $f['is_paid'] ? 'Click to mark as unpaid' : 'Click to mark as paid' ?>">
                        <?= $f['is_paid'] ? '↩ Unmark' : '✓ Mark Paid' ?>
                    </button>

                    <!-- Edit Button (admin only) -->
                    <?php if ($isAdmin): ?>
                    <a href="edit.php?id=<?= urlencode($f['receipt_number']) ?>" class="btn-edit" title="Edit">✎</a>
                    <?php endif; ?>

                    <!-- Delete Button (admin only) -->
                    <?php if ($isAdmin): ?>
                    <a href="delete.php?id=<?= urlencode($f['receipt_number']) ?>" class="btn-delete" title="Delete">🗑</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <!-- NO DATA MESSAGE: Show if filter returns empty results -->
    <?php else: ?>
    <div class="no-data">
        <p>No fees found matching your filters.</p>
    </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TOAST NOTIFICATION CONTAINER
     Used by JavaScript to display success/error messages
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="toast"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT: Dynamic interactions and AJAX functionality
     ══════════════════════════════════════════════════════════════════════════ -->
<script>
/* ────────────────────────────────────────────────────────────────────────────
   TOAST NOTIFICATION SYSTEM
   
   Display temporary toast messages at bottom left of screen
   Auto-dismisses after 2.8 seconds
   ──────────────────────────────────────────────────────────────────────────── */
function showToast(msg, type = 'green') {
    // Get toast element
    const t = document.getElementById('toast');
    // Set message text
    t.textContent = msg;
    // Apply styling (class determines color)
    t.className = 'show t-' + type;
    // Clear any existing timeout
    clearTimeout(t._timer);
    // Auto-hide after 2.8 seconds
    t._timer = setTimeout(() => { t.className = ''; }, 2800);
}

/* ────────────────────────────────────────────────────────────────────────────
   FINE CALCULATOR
   
   Calculates the fine based on days overdue and per-day fine rate.
   Mirrors the PHP logic for consistency.
   
   @param dueStr ISO date string (YYYY-MM-DD)
   @param fineRate Fine amount in kr per day
   @return Fine amount in kr (0 if not yet due)
   ──────────────────────────────────────────────────────────────────────────── */
function calcFine(dueStr, fineRate) {
    // Parse due date (add time to avoid timezone issues)
    const due   = new Date(dueStr + 'T00:00:00');
    // Get today's date at midnight
    const today = new Date(); 
    today.setHours(0,0,0,0);
    
    // If today <= due date, no fine yet
    if (today <= due) return 0;
    
    // Calculate days overdue (86400000 = ms in 1 day)
    const days = Math.floor((today - due) / 86400000);
    return days * fineRate;
}

/* ────────────────────────────────────────────────────────────────────────────
   DATE FORMATTING HELPERS
   
   Format dates for display in various formats
   ──────────────────────────────────────────────────────────────────────────── */
// Format ISO date to dd Mon yyyy (e.g., "15 Mar 2026")
function fmtDate(iso) {
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
}

// Format ISO datetime to "dd Mon yyyy, HH:mm"
function fmtDateTime(iso) {
    const d = new Date(iso.replace(' ','T'));
    return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) +
           ', ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}

/* ════════════════════════════════════════════════════════════════════════════
   MAIN FUNCTION: TOGGLE PAY STATUS VIA AJAX
   
   This is the core function that:
   1. Sends AJAX request to toggle payment status
   2. Updates all affected table cells instantly
   3. Recalculates fines for unpaid fees
   4. Shows confirmation toast
   
   @param btn The button element that was clicked
   ════════════════════════════════════════════════════════════════════════════ */
async function togglePay(btn) {
    // Get receipt number from button's data attribute
    const receipt = btn.dataset.receipt;
    // Find the corresponding table row
    const row     = document.getElementById('row-' + receipt);

    // Read fee details from row data attributes
    const amount   = parseFloat(row.dataset.amount);         // Base fee amount
    const fineRate = parseFloat(row.dataset.fineRate);       // Fine rate (kr/day)
    const dueStr   = row.dataset.due;                         // Due date (YYYY-MM-DD)

    // Disable button and show loading spinner
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> …';

    try {
        // Send AJAX request to toggle payment status
        const res  = await fetch('index.php?ajax_toggle=' + encodeURIComponent(receipt));
        const data = await res.json();

        // Check for server errors
        if (!data.ok) throw new Error('Server error');

        // Extract new payment status from response
        const nowPaid = data.now_paid;

        /* ──────────────────────────────────────────────────────────────────
           Update row data attributes for future reference
           ────────────────────────────────────────────────────────────────── */
        row.dataset.isPaid  = nowPaid ? '1' : '0';
        row.dataset.paidAt  = data.paid_at ?? '';

        /* ──────────────────────────────────────────────────────────────────
           Get references to all cells that need updating
           ────────────────────────────────────────────────────────────────── */
        const fineEl  = document.getElementById('fine-' + receipt);
        const totalEl = document.getElementById('total-' + receipt);
        const dueEl   = document.getElementById('due-' + receipt);
        const statEl  = document.getElementById('status-' + receipt);
        const paidEl  = document.getElementById('paidat-' + receipt);

        if (nowPaid) {
            /* ══════════════════════════════════════════════════════════════
               MARKED AS PAID: Hide financial details, show cleared status
               ══════════════════════════════════════════════════════════════ */
            // Hide fine (already paid)
            fineEl.innerHTML  = '<span style="color:var(--faint);">—</span>';
            // Hide total (already paid)
            totalEl.innerHTML = '<span style="color:var(--faint);">—</span>';
            // Hide due date (not relevant for paid fees)
            dueEl.innerHTML   = '<span style="color:var(--faint);">—</span>';
            // Show cleared badge
            statEl.innerHTML  = '<span class="badge b-cleared">✅ Cleared</span>';
            // Show payment timestamp
            paidEl.textContent = fmtDateTime(data.paid_at);

            // Update row visual styling
            row.classList.remove('row-late');
            row.classList.add('row-cleared');

            // Update button state (now can unmark)
            btn.className   = 'btn-pay-toggle state-unpay';
            btn.textContent = '↩ Unmark';
            btn.title       = 'Click to mark as unpaid';

            // Show success toast
            showToast('✅ Fee marked as cleared', 'green');

        } else {
            /* ══════════════════════════════════════════════════════════════
               MARKED AS UNPAID: Recalculate all financial details
               ══════════════════════════════════════════════════════════════ */
            // Recalculate fine based on current date
            const fine  = calcFine(dueStr, fineRate);
            const total = amount + fine;

            // Update fine cell (show if any, otherwise hide)
            fineEl.innerHTML = fine > 0
                ? '+kr ' + fine.toFixed(2)
                : '<span style="color:var(--faint);">—</span>';

            // Update total cell
            totalEl.textContent = 'kr ' + total.toFixed(2);

            // Update due date cell and check if overdue
            const due   = new Date(dueStr + 'T00:00:00');
            const today = new Date(); 
            today.setHours(0,0,0,0);
            const overdue = today > due;
            
            if (overdue) {
                // If overdue, show days late indicator
                const days = Math.floor((today - due) / 86400000);
                dueEl.innerHTML = fmtDate(dueStr) +
                    '<div style="font-size:10px;color:var(--danger);" class="days-late">' + days + 'd late</div>';
            } else {
                // Otherwise just show date
                dueEl.textContent = fmtDate(dueStr);
            }

            // Update status badge
            if (overdue) {
                // Overdue badge
                statEl.innerHTML = '<span class="badge b-overdue">⚠ Overdue</span>';
                row.classList.add('row-late');
            } else {
                // Unpaid (pending) badge
                statEl.innerHTML = '<span class="badge b-unpaid">⏳ Unpaid</span>';
            }
            row.classList.remove('row-cleared');

            // Clear paid-on date
            paidEl.textContent = '—';

            // Update button state (now can mark as paid)
            btn.className   = 'btn-pay-toggle state-pay';
            btn.textContent = '✓ Mark Paid';
            btn.title       = 'Click to mark as paid';

            // Show notification about recalculation
            showToast('↩ Fee unmarked — fine recalculated', 'amber');
        }

    } catch (err) {
        // Show error message
        showToast('❌ Something went wrong — please try again', 'amber');
        // Restore button text based on current state
        const isPaid = row.dataset.isPaid === '1';
        btn.className   = 'btn-pay-toggle ' + (isPaid ? 'state-unpay' : 'state-pay');
        btn.textContent = isPaid ? '↩ Unmark' : '✓ Mark Paid';
    }

    // Re-enable button
    btn.disabled = false;
}
</script>

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
        <div style="width:48px;height:1px;background:var(--border);margin:0 auto 16px;"></div>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--text);">🏠 Hostel<span style="color:var(--accent);">Hub</span></span>
        </div>
        <p style="font-size:11px;color:var(--muted);margin:0;">
            Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub &nbsp;·&nbsp; All records are encrypted and access-controlled.
        </p>
    </div>
</footer>

</body>
</html>
