<?php
/* ════════════════════════════════════════════════════════════════════════════
   EDIT FEE RECORD
   
   Purpose: Modify existing fee records with full audit trail
   - Toggle payment status with timestamp
   - Update fee details
   - Manage fine policies per fee
   - Preserve data integrity
════════════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

/**
 * Load the fee record to edit
 * Joins with student table for display info
 */
$id   = $_GET['id'] ?? '';
$stmt = $db->prepare("
    SELECT f.*, s.full_name, s.student_number
    FROM fees f
    LEFT JOIN students s ON s.student_id = f.student_id
    WHERE f.receipt_number = ?
");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirect if fee not found
if (!$fee) { 
    header("Location: index.php"); 
    exit; 
}

/**
 * Load all active students for reassignment
 * Ordered alphabetically for easy selection
 */
$students = $db->query("
    SELECT student_id, full_name, student_number FROM students WHERE status = 1 ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$errors  = [];
$success = '';

/* ── MARK FEE AS PAID ───────────────────────────────– */
/**
 * Handle "Mark as Paid" action
 * Sets is_paid flag and records current timestamp
 */
if (isset($_POST['mark_paid'])) {
    $db->prepare("UPDATE fees SET is_paid = 1, paid_at = NOW() WHERE receipt_number = ?")
       ->execute([$id]);
    header("Location: edit.php?id=" . urlencode($id) . "&saved=1");
    exit;
}

/* ── MARK FEE AS UNPAID ────────────────────────────– */
/**
 * Handle "Mark as Unpaid" action
 * Clears is_paid flag and removes timestamp
 */
if (isset($_POST['mark_unpaid'])) {
    $db->prepare("UPDATE fees SET is_paid = 0, paid_at = NULL WHERE receipt_number = ?")
       ->execute([$id]);
    header("Location: edit.php?id=" . urlencode($id) . "&saved=1");
    exit;
}

/* ── UPDATE FEE DETAILS ────────────────────────────– */
/**
 * Handle fee details update
 * Validates all inputs and updates record
 */
if (isset($_POST['update'])) {
    $student_id     = (int)($_POST['student_id']     ?? 0);
    $fee_type       = $_POST['fee_type']              ?? 'rent';
    $amount         = (float)($_POST['amount']        ?? 0);
    $due_date       = $_POST['due_date']              ?? '';
    $payment_method = $_POST['payment_method']        ?? null;
    $fine_rate      = (float)($_POST['fine_rate']     ?? 0.50);
    $fine_cap       = (float)($_POST['fine_cap']      ?? 15.00);

    // Validate inputs
    if ($student_id <= 0)  $errors[] = "Please select a student.";
    if ($amount <= 0)      $errors[] = "Amount must be greater than 0.";
    if (empty($due_date))  $errors[] = "Please enter a due date.";

    // Update if no errors
    if (empty($errors)) {
        $db->prepare("
            UPDATE fees SET
                student_id = ?, fee_type = ?, amount = ?, due_date = ?,
                payment_method = ?, fine_rate = ?, fine_cap = ?,
                updated_at = NOW()
            WHERE receipt_number = ?
        ")->execute([
            $student_id, $fee_type, $amount, $due_date,
            $payment_method ?: null, $fine_rate, $fine_cap,
            $id
        ]);
        
        // Reload fee data
        $stmt->execute([$id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);
        $success = "Fee record updated successfully.";
    }
}

// Set success message if coming from mark paid/unpaid
isset($_GET['saved']) && $success = "Changes saved.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Fee — HostelHub</title>
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
.form-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px 30px;margin-bottom:18px;}
.form-card h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.alert-success{background:rgba(34,211,165,0.1);border:1px solid rgba(34,211,165,0.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--success);}
.alert-error{background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--danger);}
.alert-error ul{margin:6px 0 0 18px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.form-group input,.form-group select{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:9px;font-size:14px;font-family:'Outfit',sans-serif;background:var(--surface);color:var(--text);outline:none;transition:border-color 0.15s;}
.form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,122,255,0.15);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.rcpt-display{background:#12161f;border:1px solid var(--border);border-radius:9px;padding:10px 14px;font-family:'DM Mono',monospace;font-size:13px;color:var(--muted);}
.paid-notice{background:rgba(34,211,165,0.08);border:1px solid rgba(34,211,165,0.25);border-radius:9px;padding:13px 16px;font-size:13px;color:var(--success);margin-bottom:14px;}
.btn{display:block;width:100%;padding:12px;border:none;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:background 0.2s;}
.btn-green{background:rgba(34,211,165,0.15);color:var(--success);border:1px solid rgba(34,211,165,0.3);}
.btn-green:hover{background:rgba(34,211,165,0.25);}
.btn-amber{background:rgba(251,191,36,0.12);color:var(--warning);border:1px solid rgba(251,191,36,0.3);}
.btn-amber:hover{background:rgba(251,191,36,0.22);}
.btn-blue{background:var(--accent);color:#fff;margin-top:8px;}
.btn-blue:hover{background:#3d68e8;}
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
        <h2>Edit Fee Record</h2>
        <p>Receipt: <code style="font-family:'DM Mono',monospace;background:rgba(79,122,255,0.1);padding:2px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($fee['receipt_number']) ?></code>
        · <?= htmlspecialchars($fee['full_name'] ?? '—') ?></p>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    
    <!-- ERROR MESSAGES -->
    <?php if (!empty($errors)): ?>
    <div class="alert-error"><strong>Please fix:</strong><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- CARD: Payment Status Management -->
    <div class="form-card">
        <h3>💳 Payment Status</h3>
        
        <?php if ($fee['is_paid']): ?>
        <!-- Fee is currently marked as PAID -->
        <div class="paid-notice">
            ✅ Paid on <strong><?= $fee['paid_at'] ? (new DateTime($fee['paid_at']))->format('d M Y, H:i') : 'date unknown' ?></strong>
            <?php if ($fee['payment_method']): ?>&nbsp;via <?= ucfirst($fee['payment_method']) ?><?php endif; ?>
        </div>
        <!-- Button to change to unpaid -->
        <form method="POST">
            <button type="submit" name="mark_unpaid" class="btn btn-amber"
                    onclick="return confirm('Mark this fee as unpaid? This will clear the payment timestamp.')">
                🔄 Mark as Unpaid
            </button>
        </form>
        <?php else: ?>
        <!-- Fee is currently marked as UNPAID -->
        <p style="color:var(--muted);font-size:13px;margin-bottom:14px;">
            This fee is currently unpaid. Click below to record payment.
        </p>
        <!-- Button to change to paid -->
        <form method="POST">
            <button type="submit" name="mark_paid" class="btn btn-green"
                    onclick="return confirm('Mark this fee as paid today? Current date/time will be recorded.')">
                ✅ Mark as Paid (today)
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- CARD: Edit Fee Details -->
    <div class="form-card">
        <h3>✏️ Edit Fee Details</h3>
        <form method="POST">
            <!-- Receipt Number: Display only (cannot change) -->
            <div class="form-group">
                <label>Receipt Number</label>
                <div class="rcpt-display"><?= htmlspecialchars($fee['receipt_number']) ?></div>
            </div>

            <!-- Student: Can reassign to different student -->
            <div class="form-group">
                <label>Student</label>
                <select name="student_id">
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>"
                        <?= $fee['student_id'] == $s['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fee Type & Amount: Two-column layout -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type">
                        <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
                        <option value="<?= $type ?>" <?= $fee['fee_type'] === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (£)</label>
                    <input type="number" step="0.01" min="0.01" name="amount"
                           value="<?= htmlspecialchars($fee['amount']) ?>">
                </div>
            </div>

            <!-- Due Date: Flatpickr calendar picker -->
            <div class="form-group">
                <label>Due Date</label>
                <div class="cal-wrap">
                    <input type="text" id="due_date" name="due_date"
                           value="<?= htmlspecialchars($fee['due_date']) ?>"
                           placeholder="Pick a date…" autocomplete="off" readonly>
                </div>
            </div>

            <!-- Payment Method: How payment was/will be made -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">— Not specified —</option>
                    <option value="cash"   <?= ($fee['payment_method'] ?? '') === 'cash'   ? 'selected' : '' ?>>💵 Cash</option>
                    <option value="bank"   <?= ($fee['payment_method'] ?? '') === 'bank'   ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                    <option value="mobile" <?= ($fee['payment_method'] ?? '') === 'mobile' ? 'selected' : '' ?>>📱 Mobile Payment</option>
                </select>
            </div>

            <!-- Fine Rate & Cap: Per-fee overrides -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Fine Rate (£/day)</label>
                    <input type="number" step="0.01" min="0" name="fine_rate"
                           value="<?= htmlspecialchars($fee['fine_rate'] ?? '0.50') ?>">
                </div>
                <div class="form-group">
                    <label>Fine Cap (£ max)</label>
                    <input type="number" step="0.01" min="0" name="fine_cap"
                           value="<?= htmlspecialchars($fee['fine_cap'] ?? '15.00') ?>">
                </div>
            </div>

            <!-- SAVE BUTTON -->
            <button type="submit" name="update" class="btn btn-blue">💾 Save Changes</button>
        </form>
    </div>

    <!-- BACK LINK -->
    <a href="index.php" class="back-link">← Back to Fee Records</a>
</div>

<script>
/* ── FLATPICKR: Due Date calendar ───────────────────── */
flatpickr("#due_date", {
    dateFormat: "Y-m-d",        // matches DB/PHP format
    allowInput: false,          // force calendar selection only
    disableMobile: false,       // native picker on mobile
    defaultDate: document.getElementById("due_date").value || null,
    onChange: function(selectedDates, dateStr) {
        // Value is automatically written to the input for form submission
    }
});
</script>
</body>
</html>