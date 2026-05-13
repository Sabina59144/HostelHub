<?php
/* ════════════════════════════════════════════════════════════════════════════
   ADD FEE RECORD
   
   Purpose: Form interface to create new fee records for students
   - Auto-generate receipt numbers
   - Validate input data
   - Handle fee creation with optional immediate payment marking
   - Set custom fine rates and caps
════════════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

/**
 * Load all active students for dropdown selection
 * Orders by full name for easy scanning
 */
$students = $db->query("
    SELECT student_id, full_name, student_number
    FROM students WHERE status = 1 ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Generate unique receipt number
 * Format: RCP-YYYY-NNNN (e.g., RCP-2025-0001)
 * 
 * @param PDO $db Database connection
 * @return string Generated receipt number
 */
function generateReceipt($db): string {
    // Get the most recent receipt number
    $row = $db->query("
        SELECT receipt_number FROM fees ORDER BY created_at DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Extract the numeric part and increment
    if ($row && preg_match('/(\d+)$/', $row['receipt_number'], $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    
    return "RCP-" . date("Y") . "-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

$receipt_number     = generateReceipt($db);
$errors             = [];
$success            = false;
$preselectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

/* ── HANDLE FORM SUBMISSION ────────────────────────– */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Sanitize and collect form inputs
    $receipt_number   = trim($_POST['receipt_number'] ?? '');
    $student_id       = (int)($_POST['student_id'] ?? 0);
    $fee_type         = $_POST['fee_type']         ?? 'rent';
    $amount           = (float)($_POST['amount']   ?? 0);
    $due_date         = $_POST['due_date']          ?? '';
    $payment_method   = $_POST['payment_method']   ?? null;
    $is_paid          = isset($_POST['is_paid'])    ? 1 : 0;
    $fine_rate        = (float)($_POST['fine_rate'] ?? 0.50);
    $fine_cap         = (float)($_POST['fine_cap']  ?? 15.00);

    /* ── VALIDATE INPUTS ────────────────────────────– */
    if (empty($receipt_number))   $errors[] = "Receipt number is required.";
    if ($student_id <= 0)         $errors[] = "Please select a student.";
    if ($amount <= 0)             $errors[] = "Amount must be greater than 0.";
    if (empty($due_date))         $errors[] = "Please enter a due date.";

    /**
     * Check for duplicate receipt numbers
     * Prevents accidental duplicate entries
     */
    if (empty($errors)) {
        $dup = $db->prepare("SELECT 1 FROM fees WHERE receipt_number = ?");
        $dup->execute([$receipt_number]);
        if ($dup->fetch()) {
            $errors[] = "Receipt number already exists. A new one has been generated.";
            $receipt_number = generateReceipt($db);
        }
    }

    /**
     * Insert fee record into database
     * If marked as paid, set paid_at to current timestamp
     */
    if (empty($errors)) {
        $paid_at = $is_paid ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("
            INSERT INTO fees
                (receipt_number, student_id, fee_type, amount, due_date,
                 is_paid, paid_at, payment_method, fine_rate, fine_cap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $receipt_number, $student_id, $fee_type, $amount, $due_date,
            $is_paid, $paid_at, ($payment_method ?: null), $fine_rate, $fine_cap
        ]);
        
        // Redirect back to fee list or student-specific list
        header("Location: index.php" . ($preselectedStudent ? "?student_id=$preselectedStudent" : ""));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Fee — HostelHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
:root{--bg:#0e1117;--surface:#161b27;--card:#1c2235;--border:#2a3148;--accent:#4f7aff;--success:#22d3a5;--warning:#fbbf24;--danger:#f87171;--text:#e8eaf6;--muted:#8892b0;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;}
.topnav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--text);}
.brand span{color:var(--accent);}
.page{max-width:660px;margin:0 auto;padding:36px 24px;}
.page-hdr{margin-bottom:24px;}
.page-hdr h2{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px;}
.page-hdr p{color:var(--muted);font-size:13px;}
.form-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:30px;}
.error-box{background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.3);border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:13px;color:var(--danger);}
.error-box ul{margin:6px 0 0 18px;}
.section-divider{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin:24px 0 14px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;letter-spacing:.04em;text-transform:uppercase;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:14px;font-family:'Outfit',sans-serif;background:var(--surface);color:var(--text);outline:none;transition:border-color 0.15s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,122,255,0.15);}
.form-group input[readonly]{background:#12161f;color:var(--muted);font-family:'DM Mono',monospace;cursor:default;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.checkbox-group{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid var(--border);border-radius:9px;background:var(--surface);}
.checkbox-group input[type=checkbox]{width:18px;height:18px;cursor:pointer;}
.checkbox-group label{font-size:13px;color:var(--text);font-weight:500;cursor:pointer;margin:0;}
.hint{font-size:11px;color:var(--muted);margin-top:5px;}
.btn-submit{width:100%;padding:13px;background:var(--accent);color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:background 0.2s;margin-top:8px;}
.btn-submit:hover{background:#3d68e8;}
.back-link{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--accent);}

/* ── FLATPICKR DARK THEME ───────────────────────────── */
.flatpickr-calendar{background:var(--card)!important;border:1px solid var(--border)!important;
  box-shadow:0 12px 40px rgba(0,0,0,.55)!important;border-radius:12px!important;}
.flatpickr-day{color:var(--text)!important;border-radius:7px!important;}
.flatpickr-day:hover{background:rgba(79,122,255,.2)!important;border-color:transparent!important;}
.flatpickr-day.selected,.flatpickr-day.selected:hover{background:var(--accent)!important;border-color:var(--accent)!important;}
.flatpickr-day.today{border-color:var(--success)!important;color:var(--success)!important;}
.flatpickr-day.today.selected{color:#fff!important;}
.flatpickr-months .flatpickr-month,.flatpickr-weekdays,.flatpickr-weekday{
  background:var(--surface)!important;color:var(--muted)!important;border-radius:12px 12px 0 0!important;}
.flatpickr-current-month{color:var(--text)!important;font-family:'Outfit',sans-serif!important;font-weight:600!important;}
.flatpickr-current-month input.cur-year,.numInput{color:var(--text)!important;background:transparent!important;}
.flatpickr-prev-month svg,.flatpickr-next-month svg{fill:var(--muted)!important;}
.flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg{fill:var(--accent)!important;}

/* Calendar input wrapper with icon */
.cal-wrap{position:relative;}
.cal-wrap input{padding-right:36px!important;cursor:pointer;}
.cal-wrap::after{content:'📅';position:absolute;right:11px;top:50%;transform:translateY(-50%);
  font-size:14px;pointer-events:none;opacity:.6;}
</style>
</head>
<body>
<nav class="topnav">
    <div class="brand">🏠 Hostel<span>Hub</span></div>
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Dashboard</a>
        <a href="index.php" style="color:var(--muted);font-size:13px;text-decoration:none;">Fee Records</a>
    </div>
</nav>

<div class="page">
    <div class="page-hdr">
        <h2>Add Fee Record</h2>
        <p>Create a new fee entry for a student</p>
    </div>

    <div class="form-card">
        <!-- ERROR DISPLAY: Show validation errors -->
        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST">

            <!-- SECTION: Fee Details -->
            <div class="section-divider">Fee Details</div>

            <!-- Receipt Number: Auto-generated, read-only -->
            <div class="form-group">
                <label>Receipt Number</label>
                <input type="text" name="receipt_number" value="<?= htmlspecialchars($_POST['receipt_number'] ?? $receipt_number) ?>" readonly>
                <div class="hint">Auto-generated — do not modify</div>
            </div>

            <!-- Student: Required dropdown selection -->
            <div class="form-group">
                <label>Student *</label>
                <select name="student_id" required>
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s):
                        $sel = (isset($_POST['student_id']) && $_POST['student_id'] == $s['student_id'])
                            || (!isset($_POST['student_id']) && $preselectedStudent == $s['student_id']);
                    ?>
                    <option value="<?= $s['student_id'] ?>" <?= $sel ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_number']) ?>)
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
                        <option value="<?= $type ?>" <?= (($_POST['fee_type'] ?? 'rent') === $type) ? 'selected' : '' ?>>
                            <?= ucfirst($type) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (£) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required
                           placeholder="0.00" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                </div>
            </div>

            <!-- Due Date: Flatpickr calendar picker -->
            <div class="form-group">
                <label>Due Date *</label>
                <div class="cal-wrap">
                    <input type="text" id="due_date" name="due_date" required
                           value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>"
                           placeholder="Pick a date…" autocomplete="off" readonly>
                </div>
            </div>

            <!-- SECTION: Payment -->
            <div class="section-divider">Payment</div>

            <!-- Mark as Paid: Checkbox to mark immediately as paid -->
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_paid" name="is_paid" value="1"
                           <?= !empty($_POST['is_paid']) ? 'checked' : '' ?>>
                    <label for="is_paid">Mark as paid immediately</label>
                </div>
            </div>

            <!-- Payment Method: Optional method specification -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">— Not specified —</option>
                    <option value="cash"   <?= ($_POST['payment_method'] ?? '') === 'cash'   ? 'selected' : '' ?>>💵 Cash</option>
                    <option value="bank"   <?= ($_POST['payment_method'] ?? '') === 'bank'   ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                    <option value="mobile" <?= ($_POST['payment_method'] ?? '') === 'mobile' ? 'selected' : '' ?>>📱 Mobile Payment</option>
                </select>
            </div>

            <!-- SECTION: Fine Policy (Optional Overrides) -->
            <div class="section-divider">Fine Policy (optional overrides)</div>

            <!-- Fine Rate & Cap: Two-column layout -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Fine Rate (£/day)</label>
                    <input type="number" step="0.01" min="0" name="fine_rate"
                           value="<?= htmlspecialchars($_POST['fine_rate'] ?? '0.50') ?>">
                    <div class="hint">Default: £0.50/day</div>
                </div>
                <div class="form-group">
                    <label>Fine Cap (£ max)</label>
                    <input type="number" step="0.01" min="0" name="fine_cap"
                           value="<?= htmlspecialchars($_POST['fine_cap'] ?? '15.00') ?>">
                    <div class="hint">Default: £15.00 maximum</div>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <button type="submit" name="submit" class="btn-submit">💾 Save Fee Record</button>
        </form>

        <!-- BACK LINK -->
        <a href="<?= $preselectedStudent ? 'index.php?student_id='.$preselectedStudent : 'index.php' ?>" class="back-link">← Back to Fee Records</a>
    </div>
</div>

<script>
/* ── FLATPICKR: Due Date calendar ───────────────────── */
flatpickr("#due_date", {
    dateFormat: "Y-m-d",       // matches DB/PHP format
    allowInput: false,         // force calendar selection only
    disableMobile: false,      // native picker on mobile
    minDate: "today",          // can't set a due date in the past
    defaultDate: document.getElementById("due_date").value || null,
});
</script>
</body>
</html>