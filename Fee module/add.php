<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 * ADD FEE RECORD (add.php)
 * 
 * Purpose: Form interface to create new fee records for students
 * Key Features:
 * - Auto-generate unique receipt numbers
 * - Validate all input data
 * - Support optional immediate payment marking
 * - Configurable fine rates per fee
 * - Date picker for due date selection
 * ════════════════════════════════════════════════════════════════════════════
 */

/* ─────────────────────────────────────────────────────────────────────────
   INCLUDES & AUTHENTICATION
   ───────────────────────────────────────────────────────────────────────── */
// Require session setup and verify user is an admin
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');  // Only admins can add fees

// Load database connection
require_once __DIR__ . '/../includes/db.php';

/**
 * Load all active students for dropdown selection
 * Ordered by full name for easy scanning and selection
 * 
 * @var array $students Array of [student_id, full_name, student_number]
 */
$students = $db->query("
    SELECT student_id, full_name, student_number
    FROM students WHERE status = 1 ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Generate unique receipt number
 * 
 * Format: RCP-YYYY-NNNN (e.g., RCP-2025-0001)
 * - RCP: Receipt prefix
 * - YYYY: Current year
 * - NNNN: Incrementing sequence number
 * 
 * @param PDO $db Database connection
 * @return string Newly generated receipt number
 */
function generateReceipt($db): string {
    // Get the most recently created fee record
    $row = $db->query("
        SELECT receipt_number FROM fees ORDER BY created_at DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Extract the numeric part from the most recent receipt
    if ($row && preg_match('/(\d+)$/', $row['receipt_number'], $m)) {
        // Increment the last number
        $next = (int)$m[1] + 1;
    } else {
        // Start from 1 if no previous receipts
        $next = 1;
    }
    
    // Return formatted receipt number: RCP-YYYY-0001
    return "RCP-" . date("Y") . "-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

// Generate initial receipt number for the form
$receipt_number     = generateReceipt($db);
// Array to store validation errors
$errors             = [];
// Flag for successful submission (not used, but kept for consistency)
$success            = false;
// Pre-select a student if coming from their profile (student_id in URL)
$preselectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

/* ═════════════════════════════════════════════════════════════════════════════
   HANDLE FORM SUBMISSION (POST)
   
   When user submits the form, validate inputs and insert into database
   ═════════════════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // ─────────────────────────────────────────────────────────────────────
    // STEP 1: Collect and sanitize form inputs
    // ─────────────────────────────────────────────────────────────────────
    $receipt_number   = trim($_POST['receipt_number'] ?? '');              // Receipt ID
    $student_id       = (int)($_POST['student_id'] ?? 0);                   // Student ID (cast to int)
    $fee_type         = $_POST['fee_type']         ?? 'rent';               // Type of fee
    $amount           = (float)($_POST['amount']   ?? 0);                   // Fee amount in kr
    $due_date         = $_POST['due_date']          ?? '';                  // When fee is due
    $payment_method   = $_POST['payment_method']   ?? null;                 // How it was/will be paid
    $is_paid          = isset($_POST['is_paid'])    ? 1 : 0;                // Mark as already paid?
    $fine_rate        = (float)($_POST['fine_rate'] ?? 0.50);               // Fine per day overdue

    // ─────────────────────────────────────────────────────────────────────
    // STEP 2: Validate inputs
    // ─────────────────────────────────────────────────────────────────────
    if (empty($receipt_number))   
        $errors[] = "Receipt number is required.";
    
    if ($student_id <= 0)         
        $errors[] = "Please select a student.";
    
    if ($amount <= 0)             
        $errors[] = "Amount must be greater than 0.";
    
    if (empty($due_date))         
        $errors[] = "Please enter a due date.";

    /**
     * Check for duplicate receipt numbers
     * Prevents accidentally creating two fees with same receipt ID
     */
    if (empty($errors)) {
        $dup = $db->prepare("SELECT 1 FROM fees WHERE receipt_number = ?");
        $dup->execute([$receipt_number]);
        if ($dup->fetch()) {
            // Receipt already exists, generate a new one
            $errors[] = "Receipt number already exists. A new one has been generated.";
            $receipt_number = generateReceipt($db);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 3: Insert fee record into database
    // ─────────────────────────────────────────────────────────────────────
    if (empty($errors)) {
        // If marked as paid, record the current timestamp
        $paid_at = $is_paid ? date('Y-m-d H:i:s') : null;
        
        // Build parameterized INSERT statement
        $stmt = $db->prepare("
            INSERT INTO fees
                (receipt_number, student_id, fee_type, amount, due_date,
                 is_paid, paid_at, payment_method, fine_rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Execute with all parameters
        $stmt->execute([
            $receipt_number,           // Unique receipt ID
            $student_id,               // Student this fee is for
            $fee_type,                 // Type of fee (rent, deposit, etc.)
            $amount,                   // Amount in kr
            $due_date,                 // Due date (YYYY-MM-DD)
            $is_paid,                  // Payment status (0 or 1)
            $paid_at,                  // Timestamp of payment (null if unpaid)
            ($payment_method ?: null), // Payment method (or null)
            $fine_rate                 // Fine rate in kr per day
        ]);
        
        // ─────────────────────────────────────────────────────────────────
        // STEP 4: Redirect back to fee list
        // ─────────────────────────────────────────────────────────────────
        // If we came from a student profile, return to their fees
        // Otherwise return to general fee list
        header("Location: index.php" . ($preselectedStudent ? "?student_id=$preselectedStudent" : ""));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Meta information -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Fee — HostelHub</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Flatpickr: Date picker library -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
/* ══════════════════════════════════════════════════════════════════════════
   DESIGN SYSTEM: CSS Variables
   ══════════════════════════════════════════════════════════════════════════ */
:root{
  --bg:#0e1117;              /* Main background */
  --surface:#161b27;         /* Secondary background */
  --card:#1c2235;            /* Card/container background */
  --border:#2a3148;          /* Border color */
  --accent:#4f7aff;          /* Primary accent (blue) */
  --success:#22d3a5;         /* Success state (green) */
  --warning:#fbbf24;         /* Warning state (amber) */
  --danger:#f87171;          /* Danger state (red) */
  --text:#e8eaf6;            /* Primary text (light) */
  --muted:#8892b0;           /* Secondary text (gray) */
}

/* Reset styles */
*{box-sizing:border-box;margin:0;padding:0;}

/* Base body */
body{
  background:var(--bg);
  color:var(--text);
  font-family:'Outfit',sans-serif;
  min-height:100vh;
}

/* ──────────────────────────────────────────────────────────────────────────
   NAVIGATION BAR
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
  z-index:100;
}

.brand{
  font-family:'Syne',sans-serif;
  font-weight:800;
  font-size:20px;
  color:var(--text);
}
.brand span{color:var(--accent);}

/* ──────────────────────────────────────────────────────────────────────────
   PAGE LAYOUT
   ────────────────────────────────────────────────────────────────────────── */
.page{
  max-width:660px;          /* Narrow column for form */
  margin:0 auto;
  padding:36px 24px;
}

.page-hdr{margin-bottom:24px;}
.page-hdr h2{
  font-family:'Syne',sans-serif;
  font-size:26px;
  font-weight:800;
  margin-bottom:4px;
}
.page-hdr p{color:var(--muted);font-size:13px;}

/* ──────────────────────────────────────────────────────────────────────────
   FORM CARD
   ────────────────────────────────────────────────────────────────────────── */
.form-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:16px;
  padding:30px;
}

/* ──────────────────────────────────────────────────────────────────────────
   ERROR BOX
   ────────────────────────────────────────────────────────────────────────── */
.error-box{
  background:rgba(248,113,113,0.08);  /* Light red background */
  border:1px solid rgba(248,113,113,0.3);
  border-radius:10px;
  padding:14px 18px;
  margin-bottom:22px;
  font-size:13px;
  color:var(--danger);
}
.error-box ul{margin:6px 0 0 18px;}

/* ──────────────────────────────────────────────────────────────────────────
   SECTION DIVIDERS
   ────────────────────────────────────────────────────────────────────────── */
.section-divider{
  font-size:11px;
  font-weight:700;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:var(--muted);
  margin:24px 0 14px;
  padding-bottom:8px;
  border-bottom:1px solid var(--border);
}

/* ──────────────────────────────────────────────────────────────────────────
   FORM GROUPS (Labels, inputs, selects)
   ────────────────────────────────────────────────────────────────────────── */
.form-group{margin-bottom:18px;}

.form-group label{
  display:block;
  font-size:12px;
  font-weight:600;
  color:var(--muted);
  margin-bottom:6px;
  letter-spacing:.04em;
  text-transform:uppercase;
}

/* All input elements */
.form-group input,
.form-group select,
.form-group textarea{
  width:100%;
  padding:10px 14px;
  border:1px solid var(--border);
  border-radius:9px;
  font-size:14px;
  font-family:'Outfit',sans-serif;
  background:var(--surface);
  color:var(--text);
  outline:none;
  transition:border-color 0.15s;
}

/* Focus state: highlight with accent color */
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(79,122,255,0.15);
}

/* Read-only inputs (like receipt number) */
.form-group input[readonly]{
  background:#12161f;
  color:var(--muted);
  font-family:'DM Mono',monospace;
  cursor:default;
}

/* ──────────────────────────────────────────────────────────────────────────
   FORM GRID: Two-column layout for related fields
   ────────────────────────────────────────────────────────────────────────── */
.form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}

/* ──────────────────────────────────────────────────────────────────────────
   CHECKBOX GROUP
   ────────────────────────────────────────────────────────────────────────── */
.checkbox-group{
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 14px;
  border:1px solid var(--border);
  border-radius:9px;
  background:var(--surface);
}

.checkbox-group input[type=checkbox]{
  width:18px;
  height:18px;
  cursor:pointer;
}

.checkbox-group label{
  font-size:13px;
  color:var(--text);
  font-weight:500;
  cursor:pointer;
  margin:0;
}

/* ──────────────────────────────────────────────────────────────────────────
   HELPER TEXT
   ────────────────────────────────────────────────────────────────────────── */
.hint{
  font-size:11px;
  color:var(--muted);
  margin-top:5px;
}

/* ──────────────────────────────────────────────────────────────────────────
   SUBMIT BUTTON
   ────────────────────────────────────────────────────────────────────────── */
.btn-submit{
  width:100%;
  padding:13px;
  background:var(--accent);
  color:#fff;
  border:none;
  border-radius:11px;
  font-size:15px;
  font-weight:700;
  cursor:pointer;
  font-family:'Outfit',sans-serif;
  transition:background 0.2s;
  margin-top:8px;
}
.btn-submit:hover{background:#3d68e8;}

/* ──────────────────────────────────────────────────────────────────────────
   BACK LINK
   ────────────────────────────────────────────────────────────────────────── */
.back-link{
  display:block;
  text-align:center;
  margin-top:16px;
  font-size:13px;
  color:var(--muted);
  text-decoration:none;
}
.back-link:hover{color:var(--accent);}

/* ──────────────────────────────────────────────────────────────────────────
   FLATPICKR DARK THEME (Date picker styling)
   ────────────────────────────────────────────────────────────────────────── */
.flatpickr-calendar{
  background:var(--card)!important;
  border:1px solid var(--border)!important;
  box-shadow:0 12px 40px rgba(0,0,0,.55)!important;
  border-radius:12px!important;
}

.flatpickr-day{
  color:var(--text)!important;
  border-radius:7px!important;
}

.flatpickr-day:hover{
  background:rgba(79,122,255,.2)!important;
  border-color:transparent!important;
}

.flatpickr-day.selected,
.flatpickr-day.selected:hover{
  background:var(--accent)!important;
  border-color:var(--accent)!important;
}

.flatpickr-day.today{
  border-color:var(--success)!important;
  color:var(--success)!important;
}

.flatpickr-day.today.selected{
  color:#fff!important;
}

.flatpickr-months .flatpickr-month,
.flatpickr-weekdays,
.flatpickr-weekday{
  background:var(--surface)!important;
  color:var(--muted)!important;
  border-radius:12px 12px 0 0!important;
}

.flatpickr-current-month{
  color:var(--text)!important;
  font-family:'Outfit',sans-serif!important;
  font-weight:600!important;
}

.flatpickr-current-month input.cur-year,
.numInput{
  color:var(--text)!important;
  background:transparent!important;
}

.flatpickr-prev-month svg,
.flatpickr-next-month svg{
  fill:var(--muted)!important;
}

.flatpickr-prev-month:hover svg,
.flatpickr-next-month:hover svg{
  fill:var(--accent)!important;
}

/* Calendar input wrapper */
.cal-wrap{position:relative;}
.cal-wrap input{padding-right:36px;}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════════════════════
     NAVIGATION BAR
     ══════════════════════════════════════════════════════════════════════════ -->
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="../dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">← Home</a>
        <a href="dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <a href="index.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>

<!-- ══════════════════════════════════════════════════════════════════════════
     MAIN CONTENT: Add Fee Form
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="page">
    <div class="page-hdr">
        <h2>Add Fee Record</h2>
        <p>Create a new fee entry for a student</p>
    </div>

    <div class="form-card">
        <!-- ERROR DISPLAY: Show validation errors if any -->
        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST">

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 1: Fee Details
                 ══════════════════════════════════════════════════════════════ -->
            <div class="section-divider">Fee Details</div>

            <!-- Receipt Number: Auto-generated, read-only -->
            <div class="form-group">
                <label>Receipt Number</label>
                <input type="text" 
                       name="receipt_number" 
                       value="<?= htmlspecialchars($_POST['receipt_number'] ?? $receipt_number) ?>" 
                       readonly>
                <div class="hint">Auto-generated — do not modify</div>
            </div>

            <!-- Student: Required dropdown selection -->
            <div class="form-group">
                <label>Student *</label>
                <select name="student_id" required>
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s):
                        // Determine if this student should be pre-selected
                        $sel = (isset($_POST['student_id']) && $_POST['student_id'] == $s['student_id'])
                            || (!isset($_POST['student_id']) && $preselectedStudent == $s['student_id']);
                    ?>
                    <option value="<?= $s['student_id'] ?>" <?= $sel ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['full_name']) ?> 
                        (<?= htmlspecialchars($s['student_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fee Type & Amount: Two-column layout -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Fee Type *</label>
                    <select name="fee_type" required>
                        <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
                        <option value="<?= $type ?>" 
                                <?= (($_POST['fee_type'] ?? 'rent') === $type) ? 'selected' : '' ?>>
                            <?= ucfirst($type) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (kr) *</label>
                    <input type="number" 
                           step="0.01" 
                           min="0.01" 
                           name="amount" 
                           required
                           placeholder="0.00" 
                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                </div>
            </div>

            <!-- Due Date: Flatpickr calendar picker -->
            <div class="form-group">
                <label>Due Date *</label>
                <div class="cal-wrap">
                    <input type="text" 
                           id="due_date" 
                           name="due_date" 
                           required
                           value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>"
                           placeholder="Pick a date…" 
                           autocomplete="off" 
                           readonly>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 2: Payment Information
                 ══════════════════════════════════════════════════════════════ -->
            <div class="section-divider">Payment</div>

            <!-- Mark as Paid: Checkbox to mark immediately as paid -->
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" 
                           id="is_paid" 
                           name="is_paid" 
                           value="1"
                           <?= !empty($_POST['is_paid']) ? 'checked' : '' ?>>
                    <label for="is_paid">Mark as paid immediately</label>
                </div>
            </div>

            <!-- Payment Method: Optional method specification -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">— Not specified —</option>
                    <option value="cash"   <?= ($_POST['payment_method'] ?? '') === 'cash'   ? 'selected' : '' ?>>
                        💵 Cash
                    </option>
                    <option value="bank"   <?= ($_POST['payment_method'] ?? '') === 'bank'   ? 'selected' : '' ?>>
                        🏦 Bank Transfer
                    </option>
                    <option value="mobile" <?= ($_POST['payment_method'] ?? '') === 'mobile' ? 'selected' : '' ?>>
                        📱 Mobile Payment
                    </option>
                </select>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 3: Fine Policy (Optional)
                 ══════════════════════════════════════════════════════════════ -->
            <div class="section-divider">Fine Policy (optional override)</div>

            <!-- Fine Rate: Custom fine per day overdue -->
            <div class="form-group">
                <label>Fine Rate (kr/day)</label>
                <input type="number" 
                       step="0.01" 
                       min="0" 
                       name="fine_rate"
                       value="<?= htmlspecialchars($_POST['fine_rate'] ?? '0.50') ?>">
                <div class="hint">Default: kr 0.50/day — applied per day overdue</div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 SUBMIT BUTTON
                 ══════════════════════════════════════════════════════════════ -->
            <button type="submit" name="submit" class="btn-submit">💾 Save Fee Record</button>
        </form>

        <!-- BACK LINK -->
        <a href="<?= $preselectedStudent ? 'index.php?student_id='.$preselectedStudent : 'index.php' ?>" 
           class="back-link">
            ← Back to Fee Records
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT: Initialize date picker
     ══════════════════════════════════════════════════════════════════════════ -->
<script>
/* Initialize Flatpickr date picker for due date field */
flatpickr("#due_date", {
    dateFormat: "Y-m-d",       // Format matches database (YYYY-MM-DD)
    allowInput: false,         // Force calendar selection (no manual typing)
    disableMobile: false,      // Use native picker on mobile
    minDate: "today",          // Can't set a due date in the past
    defaultDate: document.getElementById("due_date").value || null,  // Pre-fill if editing
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
        <div style="width:48px;height:1px;background:var(--border);margin:0 auto 16px;"></div>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:6px;">
            <span style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--text);">
                🏠 Hostel<span style="color:var(--accent);">Hub</span>
            </span>
        </div>
        <p style="font-size:11px;color:var(--muted);margin:0;">
            Hostel Fee Management System &nbsp;·&nbsp; &copy; <?= date('Y') ?> HostelHub &nbsp;·&nbsp; 
            All records are encrypted and access-controlled.
        </p>
    </div>
</footer>

</body>
</html>
