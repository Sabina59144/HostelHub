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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Flatpickr: Date picker library -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
:root{
  --bg:#f0f4f8;
  --surface:#f8fafc;
  --card:#fff;
  --border:#e8edf3;
  --accent:#1a56db;
  --success:#059669;
  --warning:#d97706;
  --danger:#dc2626;
  --text:#0f1923;
  --muted:#64748b;
}

*{box-sizing:border-box;margin:0;padding:0;}

body{
  background:var(--bg);
  color:var(--text);
  font-family:'DM Sans',sans-serif;
  min-height:100vh;
}

.topnav{
  background:#fff;
  border-bottom:1px solid var(--border);
  padding:0 32px;
  height:60px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  position:sticky;
  top:0;
  z-index:100;
  box-shadow:0 1px 4px rgba(0,0,0,0.06);
}

.brand{
  font-family:'Playfair Display',serif;
  font-weight:700;
  font-size:20px;
  color:var(--text);
}
.brand span{color:var(--accent);}

.page{
  max-width:660px;
  margin:0 auto;
  padding:36px 24px;
}

.page-hdr{margin-bottom:24px;}
.page-hdr h2{
  font-family:'Playfair Display',serif;
  font-size:26px;
  font-weight:700;
  margin-bottom:4px;
  color:var(--text);
}
.page-hdr p{color:var(--muted);font-size:13px;}

.form-card{
  background:#fff;
  border:1px solid var(--border);
  border-radius:16px;
  padding:30px;
  box-shadow:0 2px 12px rgba(0,0,0,0.06);
}

.error-box{
  background:#fff1f2;
  border:1px solid #fecaca;
  border-radius:10px;
  padding:14px 18px;
  margin-bottom:22px;
  font-size:13px;
  color:var(--danger);
}
.error-box ul{margin:6px 0 0 18px;}

.section-divider{
  font-size:11px;
  font-weight:700;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:#94a3b8;
  margin:24px 0 14px;
  padding-bottom:8px;
  border-bottom:2px solid var(--border);
}

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

.form-group input,
.form-group select,
.form-group textarea{
  width:100%;
  padding:10px 14px;
  border:1px solid var(--border);
  border-radius:9px;
  font-size:14px;
  font-family:'DM Sans',sans-serif;
  background:var(--surface);
  color:var(--text);
  outline:none;
  transition:border-color 0.15s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(26,86,219,0.1);
}

.form-group input[readonly]{
  background:#f0f4f8;
  color:var(--muted);
  cursor:default;
}

.form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}

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
  accent-color:var(--accent);
}

.checkbox-group label{
  font-size:13px;
  color:var(--text);
  font-weight:500;
  cursor:pointer;
  margin:0;
}

.hint{
  font-size:11px;
  color:#94a3b8;
  margin-top:5px;
}

.btn-submit{
  width:100%;
  padding:13px;
  background:var(--accent);
  color:#fff;
  border:none;
  border-radius:11px;
  font-size:15px;
  font-weight:600;
  cursor:pointer;
  font-family:'DM Sans',sans-serif;
  transition:background 0.2s;
  margin-top:8px;
}
.btn-submit:hover{background:#1547c0;}

.back-link{
  display:block;
  text-align:center;
  margin-top:16px;
  font-size:13px;
  color:var(--muted);
  text-decoration:none;
}
.back-link:hover{color:var(--accent);}

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
        <a href="../dashboard.php" style="color:#64748b;font-size:13px;text-decoration:none;">← Home</a>
        <a href="dashboard.php" style="color:#64748b;font-size:13px;text-decoration:none;">Fee Dashboard</a>
        <a href="index.php" style="color:#64748b;font-size:13px;text-decoration:none;">Fee Records</a>
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
