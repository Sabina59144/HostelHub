<?php
require_once("../includes/db.php");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid request.");
}

$id = $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE receipt_number = ? AND is_active = 1");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee record not found.");
}

$originalReceipt = $fee['receipt_number'];
$errors = [];

// Handle Mark as Paid
if (isset($_POST['mark_paid'])) {
    $db->prepare("
        UPDATE fees SET is_paid = 1, paid_at = NOW()
        WHERE receipt_number = ?
    ")->execute([$originalReceipt]);

    header("Location: index.php");
    exit;
}

// Handle Update
if (isset($_POST['update'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id     = (int) trim($_POST['student_id']);
    $fee_type       = trim($_POST['fee_type']);
    $amount         = (float) trim($_POST['amount']);
    $due_date       = trim($_POST['due_date']);
    $fine_rate      = (float) trim($_POST['fine_rate']);
    $fine_cap       = (float) trim($_POST['fine_cap']);

    // Check duplicate receipt only if it changed
    $check = $db->prepare("
        SELECT COUNT(*) FROM fees
        WHERE receipt_number = ? AND receipt_number != ?
    ");
    $check->execute([$receipt_number, $originalReceipt]);

    if ($check->fetchColumn() > 0) {
        $errors[] = "Receipt number already exists.";
    }

    if (empty($errors)) {

        $today       = date('Y-m-d');
        $fine_amount = 0;

        if (!$fee['is_paid'] && $due_date < $today) {
            $days        = floor((strtotime($today) - strtotime($due_date)) / 86400);
            $fine_amount = min($days * $fine_rate, $fine_cap);
        }

        $total_due = $amount + $fine_amount;

        $db->prepare("
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
            WHERE receipt_number = ?
        ")->execute([
            $receipt_number, $student_id, $fee_type, $amount, $due_date,
            $fine_rate, $fine_cap, $fine_amount, $total_due,
            $originalReceipt
        ]);

        header("Location: index.php");
        exit;
    }
}

// BUG FIX 2: Load students for dropdown
$studentStmt = $db->query("SELECT student_id, full_name FROM students WHERE status = 1 ORDER BY full_name ASC");
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Fee — HostelHub</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>

<!-- BUG FIX 2: edit.php had NO HTML at all — page was blank. Added full form. -->
<div class="invoice-wrapper">
    <div class="invoice-card">

        <div class="invoice-header">
            <h2>Edit Fee Entry</h2>
            <p>Hostel Fee Management System</p>
        </div>

        <div class="invoice-body">

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $e): ?>
                    <div class="error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Mark as Paid button (separate form) -->
            <?php if (!$fee['is_paid']): ?>
            <form method="POST" style="margin-bottom:15px;">
                <button type="submit" name="mark_paid"
                        style="background:#28a745;"
                        onclick="return confirm('Mark this fee as paid?')">
                    ✅ Mark as Paid
                </button>
            </form>
            <?php else: ?>
                <p style="color:green;font-weight:bold;text-align:center;">
                    ✅ This fee was paid on <?= htmlspecialchars($fee['paid_at']) ?>
                </p>
            <?php endif; ?>

            <!-- Edit form -->
            <form method="POST">

                <label>Receipt Number</label>
                <input type="text"
                       name="receipt_number"
                       value="<?= htmlspecialchars($fee['receipt_number']) ?>"
                       required>

                <label>Student</label>
                <select name="student_id" required>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['student_id'] ?>"
                            <?= $s['student_id'] == $fee['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                            (ID: <?= $s['student_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Fee Type</label>
                <select name="fee_type" required>
                    <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
                        <option value="<?= $type ?>"
                            <?= $type === $fee['fee_type'] ? 'selected' : '' ?>>
                            <?= ucfirst($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Amount ($)</label>
                <input type="number" step="0.01" min="0.01"
                       name="amount"
                       value="<?= htmlspecialchars($fee['amount']) ?>"
                       required>

                <label>Due Date</label>
                <input type="text" id="due_date" name="due_date"
                       value="<?= htmlspecialchars($fee['due_date']) ?>"
                       required readonly>

                <div class="fine-settings">
                    <label>Fine Rate ($ per day overdue)</label>
                    <input type="number" step="0.01" min="0"
                           name="fine_rate"
                           value="<?= htmlspecialchars($fee['fine_rate']) ?>">

                    <label>Fine Cap ($)</label>
                    <input type="number" step="0.01" min="0"
                           name="fine_cap"
                           value="<?= htmlspecialchars($fee['fine_cap']) ?>">
                </div>

                <button type="submit" name="update">💾 Save Changes</button>

            </form>

            <br>
            <a href="index.php" style="display:block;text-align:center;color:#555;">← Back to List</a>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#due_date", { dateFormat: "Y-m-d" });
</script>

</body>
</html>
