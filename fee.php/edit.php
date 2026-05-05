<?php
require_once("../includes/db.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ Invalid request — no fee ID provided.");
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE fee_id = ? AND is_active = 1");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("❌ Fee record not found.");
}

$errors  = [];
$success = "";

// ── Handle Mark as Paid ───────────────────────────────────────────────────────
if (isset($_POST['mark_paid'])) {
    $db->prepare("UPDATE fees SET is_paid = 1, paid_at = NOW() WHERE fee_id = ?")
       ->execute([$id]);
    header("Location: index.php");
    exit;
}

// ── Handle Full Edit ──────────────────────────────────────────────────────────
if (isset($_POST['update'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id     = trim($_POST['student_id']);
    $fee_type       = trim($_POST['fee_type']);
    $amount         = trim($_POST['amount']);
    $due_date       = trim($_POST['due_date']);
    $fine_rate      = trim($_POST['fine_rate']);
    $fine_cap       = trim($_POST['fine_cap']);

    // Validation
    if (empty($receipt_number)) {
        $errors[] = "Receipt number is required.";
    } elseif (!preg_match('/^RCP-\d{4}-\d{4}$/', $receipt_number)) {
        $errors[] = "Receipt number must follow format: RCP-YYYY-XXXX (e.g. RCP-2025-0001).";
    } else {
        $check = $db->prepare("SELECT COUNT(*) FROM fees WHERE receipt_number = ? AND fee_id != ?");
        $check->execute([$receipt_number, $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = "Receipt number already exists for another record.";
        }
    }

    if (empty($student_id)) {
        $errors[] = "Student ID is required.";
    } elseif (!ctype_digit($student_id) || (int)$student_id <= 0) {
        $errors[] = "Student ID must be a positive whole number.";
    }

    $allowed_types = ['rent', 'deposit', 'utility', 'fine', 'laundry', 'other'];
    if (!in_array($fee_type, $allowed_types)) {
        $errors[] = "Invalid fee type selected.";
    }

    if (empty($amount)) {
        $errors[] = "Amount is required.";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = "Amount must be a positive number greater than 0.";
    } elseif ((float)$amount > 99999.99) {
        $errors[] = "Amount cannot exceed $99,999.99.";
    }

    if (empty($due_date)) {
        $errors[] = "Due date is required.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $due_date);
        if (!$d || $d->format('Y-m-d') !== $due_date) {
            $errors[] = "Due date must be a valid date (YYYY-MM-DD).";
        }
    }

    if (!is_numeric($fine_rate) || (float)$fine_rate < 0 || (float)$fine_rate > 99.99) {
        $errors[] = "Fine rate must be between 0.00 and 99.99.";
    }

    if (!is_numeric($fine_cap) || (float)$fine_cap < 0 || (float)$fine_cap > 99.99) {
        $errors[] = "Fine cap must be between 0.00 and 99.99.";
    }

    if (empty($errors)) {
        // Recalculate fine and total_due
        $today        = date('Y-m-d');
        $fine_amount  = 0.00;
        if (!$fee['is_paid'] && $due_date < $today) {
            $days_overdue = (int) round((strtotime($today) - strtotime($due_date)) / 86400);
            $fine_amount  = min(round($days_overdue * (float)$fine_rate, 2), (float)$fine_cap);
        }
        $total_due = (float)$amount + $fine_amount;

        $stmt = $db->prepare("
            UPDATE fees
            SET receipt_number = ?,
                student_id     = ?,
                fee_type       = ?,
                amount         = ?,
                due_date       = ?,
                fine_rate      = ?,
                fine_cap       = ?,
                fine_amount    = ?,
                total_due      = ?
            WHERE fee_id = ?
        ");
        $stmt->execute([
            $receipt_number,
            (int) $student_id,
            $fee_type,
            (float) $amount,
            $due_date,
            (float) $fine_rate,
            (float) $fine_cap,
            $fine_amount,
            $total_due,
            $id
        ]);

        header("Location: index.php");
        exit;
    }

    // Re-populate on error
    $fee['receipt_number'] = $receipt_number;
    $fee['student_id']     = $student_id;
    $fee['fee_type']       = $fee_type;
    $fee['amount']         = $amount;
    $fee['due_date']       = $due_date;
    $fee['fine_rate']      = $fine_rate;
    $fee['fine_cap']       = $fine_cap;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Fee — HostelHub</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .edit-header-meta { font-size: 0.85rem; opacity: 0.85; margin-top: 4px; }
        .validation-errors {
            background: #fee2e2; border-left: 5px solid #dc2626; color: #b91c1c;
            padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 0.9rem;
        }
        .validation-errors ul { margin: 6px 0 0 0; padding-left: 18px; }
        .validation-errors ul li { margin-bottom: 4px; }
        .btn-row { display: flex; gap: 10px; margin-top: 6px; }
        .btn-row button { flex: 1; }
        .btn-paid { background: #16a34a !important; }
        .btn-paid:hover { background: #15803d !important; }
        .btn-cancel { display: block; text-align: center; margin-top: 14px;
                      color: #555; text-decoration: none; font-size: 0.9rem; }
        .btn-cancel:hover { color: #000; text-decoration: underline; }
        .paid-badge {
            display: inline-block; background: #dcfce7; color: #15803d;
            border: 1px solid #86efac; padding: 4px 12px; border-radius: 20px;
            font-size: 0.85rem; font-weight: 700; margin-left: 8px; vertical-align: middle;
        }
        label { display: block; font-weight: 600; font-size: 0.88rem; color: #444; margin-bottom: 2px; }
        .fine-settings { background: #f8fafc; border: 1px solid #e2e8f0;
                         border-radius: 8px; padding: 14px; margin-bottom: 15px; }
        .fine-settings p { margin: 0 0 10px; font-size: 0.85rem; color: #555; font-weight: 600; }
        .total-due-display {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;
            padding: 10px 14px; margin-bottom: 15px; font-size: 0.95rem;
        }
        .total-due-display span { font-weight: 700; color: #1d4ed8; }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    <div class="invoice-card">

        <div class="invoice-header">
            <h2>
                Edit Fee Record
                <?php if ($fee['is_paid']): ?>
                    <span class="paid-badge">✔ Paid</span>
                <?php endif; ?>
            </h2>
            <p class="edit-header-meta">Fee ID #<?= $id ?> &nbsp;|&nbsp; <?= htmlspecialchars($fee['receipt_number']) ?></p>
        </div>

        <div class="invoice-body">

            <?php if (!empty($errors)): ?>
                <div class="validation-errors">
                    <strong>⚠ Please fix the following:</strong>
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($fee['is_paid'] && $fee['paid_at']): ?>
                <div style="background:#dcfce7;border-left:5px solid #16a34a;color:#15803d;
                            padding:10px 14px;border-radius:8px;margin-bottom:15px;font-size:0.9rem;">
                    ✔ Paid on <?= htmlspecialchars($fee['paid_at']) ?>
                </div>
            <?php endif; ?>

            <div class="total-due-display">
                Current Total Due: <span>$<?= number_format($fee['total_due'], 2) ?></span>
                <?php if ($fee['fine_amount'] > 0): ?>
                    &nbsp;<small style="color:#dc2626;">(includes $<?= number_format($fee['fine_amount'], 2) ?> fine)</small>
                <?php endif; ?>
            </div>

            <form method="POST">

                <label>Receipt Number</label>
                <input type="text" name="receipt_number"
                       value="<?= htmlspecialchars($fee['receipt_number']) ?>"
                       placeholder="RCP-2025-0001" required>

                <label>Student ID</label>
                <input type="number" name="student_id" min="1"
                       value="<?= htmlspecialchars($fee['student_id']) ?>" required>

                <label>Fee Type</label>
                <select name="fee_type" required>
                    <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $t): ?>
                        <option value="<?= $t ?>" <?= $fee['fee_type'] === $t ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Amount ($)</label>
                <input type="number" step="0.01" min="0.01" max="99999.99"
                       name="amount" value="<?= htmlspecialchars($fee['amount']) ?>" required>

                <label>Due Date</label>
                <input type="text" id="due_date" name="due_date"
                       value="<?= htmlspecialchars($fee['due_date']) ?>" required readonly>

                <div class="fine-settings">
                    <p>⚙ Fine Settings</p>
                    <label>Fine Rate ($ per day overdue)</label>
                    <input type="number" step="0.01" min="0" max="99.99"
                           name="fine_rate" value="<?= htmlspecialchars($fee['fine_rate']) ?>">
                    <label>Fine Cap ($)</label>
                    <input type="number" step="0.01" min="0" max="99.99"
                           name="fine_cap" value="<?= htmlspecialchars($fee['fine_cap']) ?>">
                </div>

                <div class="btn-row">
                    <button type="submit" name="update">💾 Save Changes</button>

                    <?php if (!$fee['is_paid']): ?>
                        <button type="submit" name="mark_paid" class="btn-paid"
                                onclick="return confirm('Mark this fee as PAID? This cannot be undone.')">
                            ✔ Mark as Paid
                        </button>
                    <?php endif; ?>
                </div>

            </form>

            <a class="btn-cancel" href="index.php">← Cancel and go back</a>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr("#due_date", { dateFormat: "Y-m-d" });</script>

</body>
</html>
