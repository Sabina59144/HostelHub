<?php
/* ════════════════════════════════════════════════════════════════════════════
   EDIT FEE RECORD
   Purpose : Modify an existing fee record with a full audit trail
   Features: Toggle paid/unpaid status, update fee details, manage fine rate
════════════════════════════════════════════════════════════════════════════ */

// Load the session helper and enforce that the visitor is logged in
require_once __DIR__ . '/../includes/session.php';

// Only admins may access this page; non-admins are redirected automatically
requireRole('admin');

// Load the PDO database connection stored in $db
require_once __DIR__ . '/../includes/db.php';

/**
 * Load the fee record to edit.
 * Joins with the students table so we can display the student's name/number.
 */
// Read the receipt number from the URL; default to empty string if not present
$id   = $_GET['id'] ?? '';

// Prepare a parameterised query to prevent SQL injection
$stmt = $db->prepare("
    SELECT f.*, s.full_name, s.student_number
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id  -- keep the row even if no matching student
    WHERE f.receipt_number = ?
");
$stmt->execute([$id]);          // bind the receipt number safely
$fee = $stmt->fetch(PDO::FETCH_ASSOC); // retrieve one row as a key→value array

// If no record exists for this ID, send the user back to the list and stop
if (!$fee) {
    header("Location: index.php");
    exit;
}

/**
 * Load all active students for the reassignment dropdown.
 * Ordered A→Z for easy browsing.
 */
$students = $db->query("
    SELECT student_id, full_name, student_number
    FROM students
    WHERE status = 1          -- only active (non-archived) students
    ORDER BY full_name ASC    -- alphabetical order
")->fetchAll(PDO::FETCH_ASSOC); // return every row as an associative array

// Initialise error and success message containers
$errors  = [];
$success = '';

/* ── MARK FEE AS PAID ───────────────────────────────────────────────────── */
// Triggered when the admin submits the "Mark as Paid" form button
if (isset($_POST['mark_paid'])) {
    // Set is_paid = 1 and stamp the current date/time as the payment timestamp
    $db->prepare("UPDATE fees SET is_paid = 1, paid_at = NOW() WHERE receipt_number = ?")
       ->execute([$id]);
    // Redirect back to the same page; ?saved=1 triggers the success message below
    header("Location: edit.php?id=" . urlencode($id) . "&saved=1");
    exit; // always exit immediately after a redirect
}

/* ── MARK FEE AS UNPAID ─────────────────────────────────────────────────── */
// Triggered when the admin submits the "Mark as Unpaid" form button
if (isset($_POST['mark_unpaid'])) {
    // Clear the paid flag and erase the payment timestamp (NULL = no payment recorded)
    $db->prepare("UPDATE fees SET is_paid = 0, paid_at = NULL WHERE receipt_number = ?")
       ->execute([$id]);
    header("Location: edit.php?id=" . urlencode($id) . "&saved=1");
    exit;
}

/* ── UPDATE FEE DETAILS ─────────────────────────────────────────────────── */
// Triggered when the admin submits the main "Save Changes" form
if (isset($_POST['update'])) {
    // Cast and sanitise each POST value; provide safe defaults if missing
    $student_id     = (int)($_POST['student_id']     ?? 0);      // must be a positive integer
    $fee_type       = $_POST['fee_type']              ?? 'rent';  // default to 'rent'
    $amount         = (float)($_POST['amount']        ?? 0);      // monetary value
    $due_date       = $_POST['due_date']              ?? '';       // YYYY-MM-DD string from Flatpickr
    $payment_method = $_POST['payment_method']        ?? null;    // cash | bank | mobile | null
    $fine_rate      = (float)($_POST['fine_rate']     ?? 0.50);   // kr per day, default 0.50

    // --- Server-side validation ---
    if ($student_id <= 0)  $errors[] = "Please select a student.";   // 0 means no selection
    if ($amount <= 0)      $errors[] = "Amount must be greater than 0.";
    if (empty($due_date))  $errors[] = "Please enter a due date.";

    // Only run the UPDATE if there are no validation errors
    if (empty($errors)) {
        $db->prepare("
            UPDATE fees SET
                student_id = ?, fee_type = ?, amount = ?, due_date = ?,
                payment_method = ?, fine_rate = ?,
                updated_at = NOW()          -- automatically stamp the edit time
            WHERE receipt_number = ?
        ")->execute([
            $student_id, $fee_type, $amount, $due_date,
            $payment_method ?: null,        // coerce empty string to NULL for the DB
            $fine_rate,
            $id                             // WHERE clause value
        ]);

        // Re-fetch the record so the form reflects the newly saved values
        $stmt->execute([$id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = "Fee record updated successfully.";
    }
}

// If the page was loaded after a mark-paid/unpaid redirect, show a short success notice
isset($_GET['saved']) && $success = "Changes saved.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<!-- Responsive viewport so the layout scales properly on mobile -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Fee — HostelHub</title>
<!-- Preconnect to Google Fonts CDN for faster DNS lookup -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<!-- Load Syne (headings), DM Mono (monospace numbers), and Outfit (body) typefaces -->
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Flatpickr CSS: styles the date-picker calendar widget -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS: the calendar library itself (loaded in <head> so it's ready when the DOM fires) -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
/* Dark theme colour palette as CSS custom properties */
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;}

/* Universal reset: remove browser default margin/padding, use border-box sizing */
*{box-sizing:border-box;margin:0;padding:0;}

/* Page base: dark background, light text, minimum full viewport height */
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}

/* Sticky top navigation bar — stays visible while scrolling */
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}

/* Brand/logo text */
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}

/* Accent colour for the "Hub" part of the logo */
.brand span{color:var(--accent);}

/* Main content area: max 660px wide, centred, padded */
.page{max-width:660px;margin:0 auto;padding:36px 24px;}

/* Page header section spacing */
.page-hdr{margin-bottom:24px;}

/* Page title in Syne bold */
.page-hdr h2{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px;}

/* Subtitle/description line */
.page-hdr p{color:var(--muted);font-size:13px;}

/* Card container for each form section */
.form-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px 30px;margin-bottom:18px;}

/* Section heading inside a card — uses Syne font with a bottom divider line */
.form-card h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);}

/* Success alert box: green-tinted, shown after a successful save or status toggle */
.alert-success{background:rgba(34,211,165,0.1);border:1px solid rgba(34,211,165,0.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--success);}

/* Error alert box: red-tinted, shown when form validation fails */
.alert-error{background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--danger);}

/* Indent the error bullet list inside the alert */
.alert-error ul{margin:6px 0 0 18px;}

/* Spacing between each form field group (label + input pair) */
.form-group{margin-bottom:18px;}

/* Small uppercase label above each form field */
.form-group label{display:block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}

/* Text inputs and select dropdowns: full width, dark surface background, smooth focus transition */
.form-group input,.form-group select{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:14px;font-family:'Outfit',sans-serif;background:var(--surface);color:var(--text);outline:none;transition:border-color 0.15s;}

/* Accent glow on focus to clearly indicate the active field */
.form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,122,255,0.15);}

/* Two-column grid used for Fee Type + Amount fields side by side */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

/* Read-only receipt number display — looks like an input but cannot be edited */
.rcpt-display{background:#12161f;border:1px solid var(--border);border-radius:9px;padding:10px 14px;font-family:'DM Mono',monospace;font-size:13px;color:var(--muted);}

/* Green notice box shown when the fee has already been marked as paid */
.paid-notice{background:rgba(34,211,165,0.08);border:1px solid rgba(34,211,165,0.25);border-radius:9px;padding:13px 16px;font-size:13px;color:var(--success);margin-bottom:14px;}

/* Shared button base: full-width block, smooth hover transition */
.btn{display:block;width:100%;padding:12px;border:none;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:background 0.2s;}

/* Green ghost button — "Mark as Paid" */
.btn-green{background:rgba(34,211,165,0.15);color:var(--success);border:1px solid rgba(34,211,165,0.3);}
.btn-green:hover{background:rgba(34,211,165,0.25);}

/* Amber ghost button — "Mark as Unpaid" */
.btn-amber{background:rgba(251,191,36,0.12);color:var(--warning);border:1px solid rgba(251,191,36,0.3);}
.btn-amber:hover{background:rgba(251,191,36,0.22);}

/* Solid blue button — "Save Changes" */
.btn-blue{background:var(--accent);color:#fff;margin-top:8px;}
.btn-blue:hover{background:#3d68e8;}

/* Centred text link below the form card */
.back-link{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}

/* ── FLATPICKR DARK THEME OVERRIDES ─────────────────────────────────────── */
/* Calendar popup: dark card background, themed border and shadow */
.flatpickr-calendar{background:var(--card)!important;border:1px solid var(--border)!important;
  box-shadow:0 12px 40px rgba(0,0,0,.55)!important;border-radius:12px!important;}

/* Individual day cells: light text with a slightly rounded shape */
.flatpickr-day{color:var(--text)!important;border-radius:7px!important;}

/* Day hover: subtle accent tint */
.flatpickr-day:hover{background:rgba(79,122,255,.2)!important;border-color:transparent!important;}

/* Selected day: solid accent background */
.flatpickr-day.selected,.flatpickr-day.selected:hover{background:var(--accent)!important;border-color:var(--accent)!important;}

/* Today's date: highlighted in success green */
.flatpickr-day.today{border-color:var(--success)!important;color:var(--success)!important;}

/* Today when also selected: override green text with white so it's readable on the accent bg */
.flatpickr-day.today.selected{color:#fff!important;}

/* Month/weekday headers: dark surface background */
.flatpickr-months .flatpickr-month,.flatpickr-weekdays,.flatpickr-weekday{
  background:var(--surface)!important;color:var(--muted)!important;border-radius:12px 12px 0 0!important;}

/* Month/year text in the header bar */
.flatpickr-current-month{color:var(--text)!important;font-family:'Outfit',sans-serif!important;font-weight:600!important;}

/* Year number input inside the calendar header */
.flatpickr-current-month input.cur-year,.numInput{color:var(--text)!important;background:transparent!important;}

/* Navigation arrow icons: muted by default, accent on hover */
.flatpickr-prev-month svg,.flatpickr-next-month svg{fill:var(--muted)!important;}
.flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg{fill:var(--accent)!important;}

/* Calendar input wrapper: adds a 📅 icon via a CSS pseudo-element */
.cal-wrap{position:relative;}
/* Right padding so text doesn't overlap the icon */
.cal-wrap input{padding-right:36px!important;cursor:pointer;}
/* The calendar icon itself — purely decorative, pointer-events:none keeps clicks going to input */
.cal-wrap::after{content:'📅';position:absolute;right:11px;top:50%;transform:translateY(-50%);
  font-size:14px;pointer-events:none;opacity:.6;}
</style>
</head>
<body>

<!-- Sticky top navigation bar -->
<nav class="topnav">
    <!-- Brand logo: "🏠 HostelHub" -->
    <div class="brand">🏠 Hostel<span>Hub</span></div>

    <!-- Right-side breadcrumb links -->
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="../dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">← Home</a>
        <a href="dashboard.php"   style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <a href="index.php"       style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>

<!-- Main page body -->
<div class="page">

    <!-- Page header: title + receipt/student subtitle -->
    <div class="page-hdr">
        <h2>Edit Fee Record</h2>
        <!-- Show the receipt number in a styled inline code pill and the student name -->
        <p>Receipt:
            <code style="font-family:'DM Mono',monospace;background:rgba(79,122,255,0.1);padding:2px 8px;border-radius:4px;font-size:12px;">
                <?= htmlspecialchars($fee['receipt_number']) ?>
            </code>
            · <?= htmlspecialchars($fee['full_name'] ?? '—') ?>
        </p>
    </div>

    <!-- SUCCESS ALERT: displayed when a save or status-toggle succeeded -->
    <?php if ($success): ?>
    <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ERROR ALERT: displayed when server-side validation caught problems -->
    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <strong>Please fix:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
            <!-- Each validation message is escaped to prevent XSS -->
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ── CARD 1: Payment Status Toggle ──────────────────────────────── -->
    <div class="form-card">
        <h3>💳 Payment Status</h3>

        <?php if ($fee['is_paid']): ?>
        <!-- Branch A: fee is currently PAID — show paid details and an "Unmark" button -->
        <div class="paid-notice">
            ✅ Paid on
            <!-- Format the paid_at timestamp; fall back to "date unknown" if the column is NULL -->
            <strong><?= $fee['paid_at'] ? (new DateTime($fee['paid_at']))->format('d M Y, H:i') : 'date unknown' ?></strong>
            <!-- Optionally show the payment method if one was recorded -->
            <?php if ($fee['payment_method']): ?>
                &nbsp;via <?= ucfirst($fee['payment_method']) ?>
            <?php endif; ?>
        </div>
        <!-- Separate mini-form so clicking the button POSTs only mark_unpaid -->
        <form method="POST">
            <!-- onclick uses a native browser confirm() as a secondary safety prompt -->
            <button type="submit" name="mark_unpaid" class="btn btn-amber"
                    onclick="return confirm('Mark this fee as unpaid? This will clear the payment timestamp.')">
                🔄 Mark as Unpaid
            </button>
        </form>

        <?php else: ?>
        <!-- Branch B: fee is currently UNPAID — prompt admin to record payment -->
        <p style="color:var(--muted);font-size:13px;margin-bottom:14px;">
            This fee is currently unpaid. Click below to record payment.
        </p>
        <!-- Separate mini-form so clicking the button POSTs only mark_paid -->
        <form method="POST">
            <button type="submit" name="mark_paid" class="btn btn-green"
                    onclick="return confirm('Mark this fee as paid today? Current date/time will be recorded.')">
                ✅ Mark as Paid (today)
            </button>
        </form>
        <?php endif; ?>
    </div><!-- /.form-card (Payment Status) -->

    <!-- ── CARD 2: Edit Fee Details ───────────────────────────────────── -->
    <div class="form-card">
        <h3>✏️ Edit Fee Details</h3>
        <!-- Main edit form; posts to the same page which handles $_POST['update'] -->
        <form method="POST">

            <!-- Receipt Number: displayed read-only — admins cannot change the primary key -->
            <div class="form-group">
                <label>Receipt Number</label>
                <!-- .rcpt-display looks like an input but is just a styled div -->
                <div class="rcpt-display"><?= htmlspecialchars($fee['receipt_number']) ?></div>
            </div>

            <!-- Student dropdown: allows reassigning the fee to a different active student -->
            <div class="form-group">
                <label>Student</label>
                <select name="student_id">
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>"
                        <!-- 'selected' attribute pre-selects the student currently on the record -->
                        <?= $fee['student_id'] == $s['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Two-column grid row: Fee Type on the left, Amount on the right -->
            <div class="form-grid">
                <!-- Fee Type dropdown: iterates a hard-coded list of allowed types -->
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type">
                        <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
                        <!-- 'selected' pre-selects whichever type is stored in the DB -->
                        <option value="<?= $type ?>"
                            <?= $fee['fee_type'] === $type ? 'selected' : '' ?>>
                            <?= ucfirst($type) ?>  <!-- capitalise first letter for display -->
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Amount: numeric input, minimum 0.01, step 0.01 for cent-level precision -->
                <div class="form-group">
                    <label>Amount (kr)</label>
                    <input type="number" step="0.01" min="0.01" name="amount"
                           value="<?= htmlspecialchars($fee['amount']) ?>">
                </div>
            </div>

            <!-- Due Date: Flatpickr calendar picker (initialised in the <script> block below) -->
            <div class="form-group">
                <label>Due Date</label>
                <!-- .cal-wrap adds the 📅 icon via ::after pseudo-element -->
                <div class="cal-wrap">
                    <!-- readonly prevents the user from typing a date; they must use the picker -->
                    <input type="text" id="due_date" name="due_date"
                           value="<?= htmlspecialchars($fee['due_date']) ?>"
                           placeholder="Pick a date…" autocomplete="off" readonly>
                </div>
            </div>

            <!-- Payment Method: optional dropdown (NULL = not specified) -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <!-- Empty value maps to NULL in the update query -->
                    <option value="">— Not specified —</option>
                    <!-- Each option is pre-selected if it matches the stored value -->
                    <option value="cash"   <?= ($fee['payment_method'] ?? '') === 'cash'   ? 'selected' : '' ?>>💵 Cash</option>
                    <option value="bank"   <?= ($fee['payment_method'] ?? '') === 'bank'   ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                    <option value="mobile" <?= ($fee['payment_method'] ?? '') === 'mobile' ? 'selected' : '' ?>>📱 Mobile Payment</option>
                </select>
            </div>

            <!-- Fine Rate: per-fee override for the daily late-fine amount (in kr) -->
            <div class="form-group">
                <label>Fine Rate (kr/day)</label>
                <!-- Default is 0.50 kr/day if no value is stored -->
                <input type="number" step="0.01" min="0" name="fine_rate"
                       value="<?= htmlspecialchars($fee['fine_rate'] ?? '0.50') ?>">
            </div>

            <!-- Save button: submits the form and triggers the $_POST['update'] handler above -->
            <button type="submit" name="update" class="btn btn-blue">💾 Save Changes</button>
        </form>
    </div><!-- /.form-card (Edit Details) -->

    <!-- Cancel link: returns to the fee list without saving anything -->
    <a href="index.php" class="back-link">← Back to Fee Records</a>
</div><!-- /.page -->

<script>
/* ── FLATPICKR: Initialise the due-date calendar picker ───────────────── */
flatpickr("#due_date", {
    dateFormat: "Y-m-d",      // output format matches the DB column format (YYYY-MM-DD)
    allowInput: false,         // disable manual typing; force calendar selection only
    disableMobile: false,      // use native date picker on mobile for better UX
    // Pre-populate the calendar with the existing due date; fall back to null if empty
    defaultDate: document.getElementById("due_date").value || null,
    onChange: function(selectedDates, dateStr) {
        // The selected date is automatically written to the hidden input value;
        // no additional logic needed here — the form submission handles the rest.
    }
});
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
        <!-- Decorative thin horizontal rule -->
        <div style="width:48px;height:1px;background:var(--border);margin:0 auto 16px;"></div>

        <!-- Footer logo row -->
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--text);">
                🏠 Hostel<span style="color:var(--accent);">Hub</span>
            </span>
        </div>

        <!-- Copyright line — date('Y') outputs the current four-digit year dynamically -->
        <p style="font-size:11px;color:var(--muted);margin:0;">
            Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub &nbsp;·&nbsp; All records are encrypted and access-controlled.
        </p>
    </div>
</footer>

</body>
</html>