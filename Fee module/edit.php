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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Flatpickr CSS: styles the date-picker calendar widget -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS: the calendar library itself (loaded in <head> so it's ready when the DOM fires) -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
:root{--bg:#f0f4f8;--surface:#f8fafc;--card:#fff;--border:#e8edf3;--accent:#1a56db;--success:#059669;--warning:#d97706;--danger:#dc2626;--text:#0f1923;--muted:#64748b;}

*{box-sizing:border-box;margin:0;padding:0;}

body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;}

.topnav{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,0.06);}

.brand{font-family:'Playfair Display',serif;font-weight:700;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}

.page{max-width:660px;margin:0 auto;padding:36px 24px;}
.page-hdr{margin-bottom:24px;}
.page-hdr h2{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;margin-bottom:4px;color:var(--text);}
.page-hdr p{color:var(--muted);font-size:13px;}

.form-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:28px 30px;margin-bottom:18px;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
.form-card h3{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--border);color:var(--text);}

.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--success);}
.alert-error{background:#fff1f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--danger);}
.alert-error ul{margin:6px 0 0 18px;}

.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.form-group input,.form-group select{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;background:var(--surface);color:var(--text);outline:none;transition:border-color 0.15s;}
.form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,86,219,0.1);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

.rcpt-display{background:#f0f4f8;border:1px solid var(--border);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--muted);}
.paid-notice{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:9px;padding:13px 16px;font-size:13px;color:var(--success);margin-bottom:14px;}

.btn{display:block;width:100%;padding:12px;border:none;border-radius:11px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background 0.2s;}
.btn-green{background:#ecfdf5;color:var(--success);border:1px solid #a7f3d0;}
.btn-green:hover{background:#d1fae5;}
.btn-amber{background:#fffbeb;color:var(--warning);border:1px solid #fde68a;}
.btn-amber:hover{background:#fef3c7;}
.btn-blue{background:var(--accent);color:#fff;margin-top:8px;}
.btn-blue:hover{background:#1547c0;}

.back-link{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}

.cal-wrap{position:relative;}
.cal-wrap input{padding-right:36px!important;cursor:pointer;}
.cal-wrap::after{content:'📅';position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none;opacity:.5;}
</style>
</head>
<body>

<!-- Sticky top navigation bar -->
<nav class="topnav">
    <!-- Brand logo: "🏠 HostelHub" -->
    <div class="brand">🏠 Hostel<span>Hub</span></div>

    <!-- Right-side breadcrumb links -->
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="../dashboard.php" style="color:#64748b;font-size:13px;text-decoration:none;">← Home</a>
        <a href="dashboard.php"   style="color:#64748b;font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <a href="index.php"       style="color:#64748b;font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>

<!-- Main page body -->
<div class="page">

    <!-- Page header: title + receipt/student subtitle -->
    <div class="page-hdr">
        <h2>Edit Fee Record</h2>
        <!-- Show the receipt number in a styled inline code pill and the student name -->
        <p>Receipt:
            <code style="font-family:monospace;background:#eff6ff;color:#1a56db;padding:2px 8px;border-radius:4px;font-size:12px;">
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
<footer style="background:#fff;border-top:1px solid #e8edf3;margin-top:48px;padding:24px 32px;text-align:center;font-family:'DM Sans',sans-serif;">
    <div style="max-width:1100px;margin:0 auto;">
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:#0f1923;">🏠 Hostel<span style="color:#1a56db;">Hub</span></span>
        </div>
        <p style="font-size:11px;color:#64748b;margin:0;">Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub</p>
    </div>
</footer>

</body>
</html>