<?php
/* ════════════════════════════════════════════════════════════════════════════
   STUDENT PORTAL — FEE MANAGEMENT (read-only student view)
   Features : View personal fees, filter/search, auto-calculate late fines,
              summary totals, print fee records.
════════════════════════════════════════════════════════════════════════════ */

// Load the database connection ($db PDO instance)
require_once(__DIR__ . "/../includes/db.php");

// Start the PHP session only if one isn't already running
// (avoids "session already started" warnings)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guard: if the student has not logged in, send them to the login page
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit; // stop executing so nothing below leaks to an unauthenticated user
}

// Pull the authenticated student's ID and display name from the session
$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

/**
 * Re-validate the student against the database.
 * Ensures the account still exists and is still marked as active (status = 1).
 */
$stmtStudent = $db->prepare("
    SELECT * FROM students WHERE student_id = ? AND status = 1
");
$stmtStudent->execute([$student_id]); // bind the session ID safely
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC); // fetch one row

// If the student record is gone or deactivated, destroy the session and redirect
if (!$student) {
    session_destroy();                        // clear all session data
    header("Location: student_login.php");
    exit;
}

/* ── FILTER VARIABLES ───────────────────────────────────────────────────── */
// Search term from the URL query string (e.g. ?search=REC-001); trim whitespace
$search       = trim($_GET['search'] ?? '');
// Status filter from the URL (paid | unpaid | overdue | empty = all)
$filterStatus = $_GET['filter'] ?? '';

/**
 * Build the fee query dynamically based on active filters.
 * Only active (non-deleted) fees belonging to the logged-in student are shown.
 */
$sql    = "SELECT * FROM fees WHERE student_id = ? AND is_active = 1";
$params = [$student_id]; // base parameter: the student's own ID

// Append a LIKE search on receipt number or fee type if a search term was provided
if (!empty($search)) {
    $sql      .= " AND (receipt_number LIKE ? OR fee_type LIKE ?)";
    $params[]  = "%$search%"; // prefix/suffix wildcards for partial matching
    $params[]  = "%$search%";
}

// Append status-specific WHERE conditions based on the selected filter tab
if ($filterStatus === 'paid') {
    $sql .= " AND is_paid = 1";                               // fully paid fees
} elseif ($filterStatus === 'unpaid') {
    $sql .= " AND is_paid = 0 AND due_date >= CURDATE()";     // unpaid but not yet overdue
} elseif ($filterStatus === 'overdue') {
    $sql .= " AND is_paid = 0 AND due_date < CURDATE()";      // unpaid and past due date
}

// Always sort newest first so the student sees the most recent fees at the top
$sql .= " ORDER BY created_at DESC";

$stmtFees = $db->prepare($sql);
$stmtFees->execute($params);
$fees = $stmtFees->fetchAll(PDO::FETCH_ASSOC); // all matching fee rows

// Current date/time object used for overdue calculations throughout the page
$today = new DateTime();

/* ── CALCULATE SUMMARY TOTALS ───────────────────────────────────────────── */
// Running totals that accumulate as we loop through the fees below
$totalFees    = 0;  // sum of all base fee amounts (regardless of status)
$totalFines   = 0;  // sum of all automatically calculated late fines
$totalPaid    = 0;  // sum of amounts on paid fees
$totalUnpaid  = 0;  // sum of amounts + fines on unpaid/overdue fees
$countOverdue = 0;  // count of fees that are overdue (for the notice banner)

/**
 * Loop by reference (&$fee) so we can attach computed fields directly
 * to each array element without rebuilding the array.
 */
foreach ($fees as &$fee) {
    $dueDate     = new DateTime($fee['due_date']); // parse the due_date string into a DateTime
    $fineAmount  = 0;        // default: no fine
    $status      = 'unpaid'; // default status label key
    $statusLabel = 'Unpaid'; // human-readable label shown in the badge
    $rowClass    = '';        // CSS class applied to the <tr>; set to 'late-row' for overdue

    if ($fee['is_paid']) {
        // ── Branch 1: fee has been paid ──────────────────────────────────
        $status     = 'paid';
        $statusLabel = 'Paid';
        $totalPaid  += $fee['amount']; // add to the paid total

    } elseif ($today > $dueDate) {
        // ── Branch 2: fee is overdue (unpaid and past due date) ──────────
        $daysOverdue = $dueDate->diff($today)->days; // integer: days since due date
        $fineAmount  = $daysOverdue * $fee['fine_rate']; // fine = days × daily rate (kr)
        $status      = 'overdue';
        $statusLabel = 'Overdue';
        $rowClass    = 'late-row';    // red row highlight (defined in style.css)
        $countOverdue++;              // increment overdue counter for the banner
        $totalFines  += $fineAmount; // accumulate fine total
        $totalUnpaid += $fee['amount'] + $fineAmount; // outstanding = base + fine

    } else {
        // ── Branch 3: fee is unpaid but still within the due date ────────
        $totalUnpaid += $fee['amount']; // no fine yet; add only the base amount
    }

    // Attach all computed values back onto the fee array so the HTML template can use them
    $fee['_fine_amount']  = $fineAmount;                    // late fine in kr
    $fee['_total_due']    = $fee['amount'] + $fineAmount;   // total the student owes for this fee
    $fee['_status']       = $status;                        // key: paid | unpaid | overdue
    $fee['_status_label'] = $statusLabel;                   // display string for the badge
    $fee['_row_class']    = $rowClass;                      // CSS class for the table row

    $totalFees += $fee['amount']; // always add the base amount to the gross total
}
unset($fee); // break the reference to prevent accidental mutation after the loop

// Grand total = what the student still owes (unpaid base amounts + fines)
$grandTotal = $totalUnpaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Responsive viewport for mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fees — HostelHub</title>
    <!-- Preconnect to Google Fonts for faster DNS resolution -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <!-- Load Syne (headings), DM Mono (monospace numbers), and Outfit (body text) -->
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Dark theme colour tokens */
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

        /* Universal reset */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* Page base: dark background, light text, minimum full viewport height */
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        /* Sticky header so the brand and logout button stay visible while scrolling */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100; /* sits above all page content */
        }

        /* Brand/logo text in Syne bold */
        .brand {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
        }

        /* "Hub" suffix coloured in accent blue, slightly faded */
        .brand span {
            color: var(--accent);
            opacity: 0.85;
        }

        /* Right section of the top bar: student badge + logout button */
        .topbar .right {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.875rem;
        }

        /* Pill showing the logged-in student's name */
        .student-badge {
            background: rgba(79, 122, 255, 0.15); /* faint accent background */
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        /* Logout button: red-tinted ghost button */
        .logout-btn {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
            padding: 7px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s; /* smooth hover effect */
        }

        /* Deepen the red tint on hover */
        .logout-btn:hover {
            background: rgba(248, 113, 113, 0.25);
        }

        /* ── PAGE BODY ── */
        /* Max-width content column, centred, with comfortable padding */
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 20px 48px;
        }

        /* Large Syne page title */
        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        /* Muted subtitle below the page title */
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 28px;
        }

        /* ── STUDENT INFO STRIP ── */
        /* Horizontal card showing avatar, name, and quick-stat badges */
        .student-info {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;   /* wraps to column on narrow screens */
            gap: 20px;
            align-items: center;
            border-left: 5px solid var(--accent); /* accent left stripe as a visual anchor */
        }

        /* Circular avatar with initials icon */
        .student-info .avatar {
            width: 54px; height: 54px;
            background: var(--accent);
            border-radius: 50%;           /* circle */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;               /* prevent shrinking on small screens */
        }

        /* Text block: student name and email */
        .student-info .details { flex: 1; }
        .student-info .details h3 { font-size: 1.1rem; color: var(--text); }
        .student-info .details p  { font-size: 0.85rem; color: var(--muted); margin-top: 2px; }

        /* Row of stat mini-cards on the right side of the info strip */
        .student-info .meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* Individual stat: centred label + value pair */
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
        /* Responsive grid: columns shrink/expand automatically (auto-fit) */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        /* Individual summary card */
        .summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            border-top: 4px solid var(--border); /* overridden per colour variant below */
            transition: transform 0.15s;          /* smooth lift on hover */
        }

        /* Subtle lift effect when hovering a summary card */
        .summary-card:hover { transform: translateY(-2px); }

        /* Colour-coded top border per card variant */
        .summary-card.blue  { border-top-color: var(--accent); }
        .summary-card.green { border-top-color: var(--success); }
        .summary-card.red   { border-top-color: var(--danger); }
        .summary-card.amber { border-top-color: var(--warning); }

        /* Small uppercase card label */
        .summary-card .s-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Large bold card value in Syne font */
        .summary-card .s-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        /* Small helper text below the card value */
        .summary-card .s-sub {
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── TABLE WRAP ── */
        /* Card container for the filter bar + fees table */
        .table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden; /* clips the table's scrollbar inside the rounded card */
            margin-bottom: 24px;
        }

        /* Table header bar: title + print/readonly buttons */
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

        /* FILTER ROW: search input + status dropdown + buttons */
        .filter-row {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 12px 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* The form inside the filter row fills available width */
        .filter-row form {
            display: flex;
            gap: 8px;
            flex: 1;
            min-width: 280px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Search text input: expands to fill remaining space in the flex row */
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

        /* Accent border glow on focus */
        .filter-row input:focus {
            border-color: var(--accent);
        }

        /* Status filter dropdown */
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

        /* Accent border on focus for the dropdown */
        .filter-row select:focus {
            border-color: var(--accent);
        }

        /* Blue "Search" submit button */
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

        /* Darker blue on hover */
        .btn-search:hover {
            background: #3d68e8;
        }

        /* "Clear filters" button: outlined card-coloured button */
        .btn-clear {
            padding: 8px 14px;
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;  /* it's an <a> styled as a button */
            font-family: 'Outfit', sans-serif;
            transition: all 0.2s;
            display: inline-block;
        }

        /* Accent border + text on hover */
        .btn-clear:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* TABLE: horizontal scroll wrapper for narrow screens */
        .table-scroll {
            overflow-x: auto;
        }

        /* Full-width borderless table */
        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Table head: slightly darker surface background */
        .table-wrap thead {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        /* Column header cells: small uppercase muted text, no wrapping */
        .table-wrap th {
            padding: 11px 15px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--muted);
            text-align: left;
            white-space: nowrap; /* prevent headers from breaking across lines */
        }

        /* Data cells: comfortable padding, thin separator line */
        .table-wrap td {
            padding: 12px 15px;
            font-size: 13px;
            border-bottom: 1px solid rgba(42, 49, 72, 0.5);
            vertical-align: middle;
        }

        /* Remove the bottom border from the very last row */
        .table-wrap tbody tr:last-child td {
            border-bottom: none;
        }

        /* Subtle accent tint on row hover */
        .table-wrap tbody tr:hover td {
            background: rgba(79, 122, 255, 0.04);
        }

        /* Overdue row: faint red tint (the red left-border is in style.css .late-row) */
        .table-wrap tbody tr.late-row {
            background: rgba(248, 113, 113, 0.04);
        }

        /* ── STATUS BADGES ── */
        /* Shared pill base */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        /* Paid: green tint */
        .badge.paid {
            background: rgba(34, 211, 165, 0.15);
            color: var(--success);
        }

        /* Unpaid: amber tint */
        .badge.unpaid {
            background: rgba(251, 191, 36, 0.15);
            color: var(--warning);
        }

        /* Overdue: red tint */
        .badge.overdue {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
        }

        /* Fee type label: small blue pill (e.g. "Rent", "Deposit") */
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

        /* Late fine amount displayed in red monospace */
        .fine-amount {
            color: var(--danger);
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        /* Base fee amount: monospace for digit alignment */
        .amount {
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
        }

        /* Total due column: larger, accent-coloured monospace */
        .total-amount {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--accent);
        }

        /* ── EMPTY STATE ── */
        /* Shown when the fee query returns no rows */
        .empty {
            padding: 48px 32px;
            text-align: center;
            color: var(--muted);
        }

        /* Large emoji icon above the "no records" message */
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

        /* ── OVERDUE NOTICE BANNER ── */
        /* Red alert bar shown when countOverdue > 0 */
        .overdue-notice {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-left: 5px solid var(--danger); /* bold left accent stripe */
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.875rem;
            color: var(--danger);
        }

        /* Bold heading inside the notice */
        .overdue-notice strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        /* "View Only" amber pill badge in the table header */
        .readonly-badge {
            background: rgba(251, 191, 36, 0.15);
            color: var(--warning);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Print button: outlined, transitions to accent on hover */
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

        /* ── POLICY NOTE ── */
        /* Small footer note explaining the late fine policy */
        .policy-note {
            margin-top: 20px;
            font-size: 0.78rem;
            color: var(--muted);
            text-align: center;
            padding: 12px;
            background: var(--surface);
            border-radius: 10px;
        }

        /* ── PRINT MEDIA QUERY ── */
        @media print {
            /* Hide interactive elements when printing */
            .topbar, .print-btn, .readonly-badge, .filter-row { display: none; }
            body { background: var(--bg); }
            .page { padding: 0; }
            /* Keep card borders visible on paper */
            .summary-card, .table-wrap, .student-info { border: 1px solid var(--border); }
        }

        /* ── MOBILE RESPONSIVE ── */
        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; } /* stack brand + right */
            .student-info { flex-direction: column; }                          /* stack avatar + details */
            .summary-grid { grid-template-columns: 1fr 1fr; }                 /* 2-col instead of 4 */
            .filter-row { flex-direction: column; }                            /* stack filter controls */
            .filter-row form { width: 100%; }                                  /* form fills the column */
        }
    </style>
</head>
<body>

<!-- TOP BAR: brand logo + student name badge + logout -->
<div class="topbar">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div class="right">
        <!-- Show the logged-in student's name from the session -->
        <div class="student-badge">🎓 <?= htmlspecialchars($student_name) ?></div>
        <!-- Sign out link: destroys session on the logout page -->
        <a href="student_logout.php" class="logout-btn">🚪 Sign Out</a>
    </div>
</div>

<!-- PAGE CONTENT -->
<div class="page">
    <!-- Page title and subtitle -->
    <div class="page-title">💳 My Fee Records</div>
    <div class="page-subtitle">View your hostel fees, payment status, and outstanding balances. Contact admin for questions.</div>

    <!-- ── STUDENT INFO STRIP ──────────────────────────────────────────── -->
    <div class="student-info">
        <!-- Circular avatar with graduation cap emoji -->
        <div class="avatar">🎓</div>

        <!-- Student name + email fetched from the DB row -->
        <div class="details">
            <h3><?= htmlspecialchars($student['full_name']) ?></h3>
            <!-- Fall back to "No email" if the column is empty -->
            <p><?= htmlspecialchars($student['email'] ?? 'No email') ?></p>
        </div>

        <!-- Quick-stat mini-cards on the right -->
        <div class="meta">
            <!-- Student ID number from the DB -->
            <div class="meta-item">
                <div class="label">Student ID</div>
                <div class="value"><?= htmlspecialchars($student['student_number']) ?></div>
            </div>

            <!-- Total fee records returned by the query (may be filtered) -->
            <div class="meta-item">
                <div class="label">Total Records</div>
                <div class="value"><?= count($fees) ?></div>
            </div>

            <!-- Overdue count: red if > 0, green if none -->
            <div class="meta-item">
                <div class="label">Overdue Fees</div>
                <div class="value" style="color:<?= $countOverdue > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= $countOverdue ?>
                </div>
            </div>
        </div>
    </div><!-- /.student-info -->

    <!-- ── OVERDUE NOTICE BANNER ───────────────────────────────────────── -->
    <!-- Only rendered if at least one fee is overdue -->
    <?php if ($countOverdue > 0): ?>
    <div class="overdue-notice">
        <!-- Plural-aware count: "1 overdue fee" vs "2 overdue fees" -->
        <strong>⚠️ You have <?= $countOverdue ?> overdue fee<?= $countOverdue > 1 ? 's' : '' ?></strong>
        Late fines are being applied automatically. Please settle outstanding balances as soon as possible.
        <!-- Show the running total of all accumulated fines -->
        Total fines accumulated: <strong>kr <?= number_format($totalFines, 2) ?></strong>
    </div>
    <?php endif; ?>

    <!-- ── SUMMARY CARDS ───────────────────────────────────────────────── -->
    <div class="summary-grid">
        <!-- Total billed: sum of all base fee amounts -->
        <div class="summary-card blue">
            <div class="s-label">Total Fees Charged</div>
            <div class="s-value">kr <?= number_format($totalFees, 2) ?></div>
            <!-- Plural-aware record count -->
            <div class="s-sub"><?= count($fees) ?> fee record<?= count($fees) != 1 ? 's' : '' ?></div>
        </div>

        <!-- Total paid: only fees with is_paid = 1 -->
        <div class="summary-card green">
            <div class="s-label">Total Paid</div>
            <div class="s-value">kr <?= number_format($totalPaid, 2) ?></div>
            <div class="s-sub">Amount settled</div>
        </div>

        <!-- Total outstanding: unpaid base amounts + all fines -->
        <div class="summary-card red">
            <div class="s-label">Total Outstanding</div>
            <div class="s-value">kr <?= number_format($totalUnpaid, 2) ?></div>
            <div class="s-sub">Including fines</div>
        </div>

        <!-- Total fines accrued on overdue fees -->
        <div class="summary-card amber">
            <div class="s-label">Fines Accrued</div>
            <div class="s-value">kr <?= number_format($totalFines, 2) ?></div>
            <div class="s-sub">From overdue fees</div>
        </div>
    </div><!-- /.summary-grid -->

    <!-- ── FEE TABLE ──────────────────────────────────────────────────── -->
    <div class="table-wrap">

        <!-- Table header bar: title, print button, and read-only notice -->
        <div class="table-header">
            <div>
                <h3>Fee Breakdown</h3>
                <p>All your hostel fee records</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <!-- Print button: triggers the browser print dialog via JS -->
                <a href="#" onclick="window.print();return false;" class="print-btn">🖨️ Print</a>
                <!-- Read-only reminder so students know they cannot edit from here -->
                <span class="readonly-badge">👁️ View Only</span>
            </div>
        </div>

        <!-- ── FILTER ROW ── -->
        <!-- GET form so filters persist in the URL and the browser back button works -->
        <div class="filter-row">
            <form method="GET">
                <!-- Search input: pre-filled with the current search term -->
                <input type="text" name="search"
                       placeholder="Search receipt or fee type…"
                       value="<?= htmlspecialchars($search) ?>">

                <!-- Status dropdown: auto-submits on change for instant filtering -->
                <select name="filter" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <!-- 'selected' attribute restores the current selection after page reload -->
                    <option value="paid"    <?= $filterStatus==='paid'    ? 'selected':'' ?>>✅ Paid</option>
                    <option value="unpaid"  <?= $filterStatus==='unpaid'  ? 'selected':'' ?>>⏳ Unpaid</option>
                    <option value="overdue" <?= $filterStatus==='overdue' ? 'selected':'' ?>>⚠️ Overdue</option>
                </select>

                <!-- Explicit search button for users who prefer to type then press Enter/button -->
                <button type="submit" class="btn-search">🔍 Search</button>

                <!-- Only show the "Clear" link when a filter or search is active -->
                <?php if ($search || $filterStatus): ?>
                <a href="student_portal.php" class="btn-clear">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── EMPTY STATE or FEE TABLE ── -->
        <?php if (empty($fees)): ?>
        <!-- Shown when the query returns zero rows (no fees or no filter matches) -->
        <div class="empty">
            <span class="icon">📋</span>
            <h3>No fee records found</h3>
            <p>Your fee records will appear here once added by the admin.</p>
        </div>

        <?php else: ?>
        <!-- Scroll wrapper allows the table to scroll horizontally on narrow screens -->
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
            <!-- _row_class is 'late-row' for overdue fees, empty string otherwise -->
            <tr class="<?= $fee['_row_class'] ?>">

                <!-- Receipt Number: unique identifier in small monospace -->
                <td style="font-family:'DM Mono',monospace;font-size:12px;color:var(--muted);">
                    <?= htmlspecialchars($fee['receipt_number']) ?>
                </td>

                <!-- Fee Type: capitalised and wrapped in a blue pill badge -->
                <td>
                    <span class="fee-type-badge"><?= ucfirst($fee['fee_type']) ?></span>
                </td>

                <!-- Base Amount: monospace for digit alignment -->
                <td class="amount">kr <?= number_format($fee['amount'], 2) ?></td>

                <!-- Late Fine: only shown if a fine was calculated (> 0) -->
                <td>
                    <?php if ($fee['_fine_amount'] > 0): ?>
                        <!-- Fine amount in red; rate shown as a sub-label -->
                        <span class="fine-amount">+kr <?= number_format($fee['_fine_amount'], 2) ?></span>
                        <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">
                            kr <?= $fee['fine_rate'] ?>/day · applied per day overdue
                        </div>
                    <?php else: ?>
                        <!-- Em dash placeholder when no fine applies -->
                        <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>

                <!-- Total Due: base amount + any fine, in accent blue monospace -->
                <td class="total-amount">kr <?= number_format($fee['_total_due'], 2) ?></td>

                <!-- Due Date: formatted as "14 Jun 2025"; shows days-late for overdue fees -->
                <td>
                    <?php
                    $dueDate = new DateTime($fee['due_date']);       // parse the date string
                    echo $dueDate->format('d M Y');                  // human-readable format
                    if ($fee['_status'] === 'overdue') {
                        $days = $dueDate->diff($today)->days;        // days since due date
                        // Red "N days late" warning rendered as a sub-line
                        echo "<div style='font-size:0.72rem;color:var(--danger);margin-top:2px;font-weight:600;'>{$days} days late</div>";
                    }
                    ?>
                </td>

                <!-- Status Badge: paid / unpaid / overdue pill -->
                <td>
                    <!-- _status is the CSS class key; _status_label is the display text -->
                    <span class="badge <?= $fee['_status'] ?>">
                        <!-- Add warning emoji prefix for overdue fees -->
                        <?= $fee['_status'] === 'overdue' ? '⚠️ ' : '' ?>
                        <?= $fee['_status_label'] ?>
                    </span>
                </td>

                <!-- Payment Method: icon + label or dash if not specified -->
                <td>
                    <?php if ($fee['payment_method']): ?>
                        <?php
                        // Map method keys to emoji icons
                        $icons = ['cash' => '💵', 'bank' => '🏦', 'mobile' => '📱'];
                        $m = $fee['payment_method'];
                        // Output icon (or empty string if unknown) + capitalised method name
                        echo ($icons[$m] ?? '') . ' ' . ucfirst(htmlspecialchars($m));
                        ?>
                    <?php else: ?>
                        <!-- Em dash when no payment method is recorded -->
                        <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>

                <!-- Paid At: timestamp when payment was recorded; dash if still unpaid -->
                <td style="font-size:12px;color:var(--muted);">
                    <?= $fee['paid_at'] ? (new DateTime($fee['paid_at']))->format('d M Y') : '—' ?>
                </td>

            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- /.table-scroll -->
        <?php endif; ?>
    </div><!-- /.table-wrap -->

    <!-- ── POLICY NOTE ──────────────────────────────────────────────────── -->
    <!-- Static footer note explaining the fine policy and dispute contact -->
    <div class="policy-note">
        💡 <strong>Late Fine Policy:</strong> kr 0.50/day · maximum kr 15.00 per fee · Applied automatically on overdue fees.
        &nbsp;&nbsp;|&nbsp;&nbsp; Contact hostel admin for disputes or payment confirmation.
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

        <!-- Copyright — date('Y') outputs the current four-digit year dynamically -->
        <p style="font-size:11px;color:var(--muted);margin:0;">
            Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub &nbsp;·&nbsp; All records are encrypted and access-controlled.
        </p>
    </div>
</footer>

</body>
</html>