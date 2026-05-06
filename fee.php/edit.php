<?php
require_once("../includes/db.php");

$id = $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE receipt_number = ?");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

$students = $db->query("
    SELECT student_id, full_name
    FROM students
    WHERE status = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   MARK PAID
========================= */
if (isset($_POST['mark_paid'])) {

    $payment_method = $_POST['payment_method'] ?? 'cash';

    $db->prepare("
        UPDATE fees
        SET is_paid = 1,
            paid_at = NOW(),
            payment_method = ?
        WHERE receipt_number = ?
    ")->execute([$payment_method, $id]);

    header("Location: index.php");
    exit;
}

/* =========================
   UPDATE FEE
========================= */
if (isset($_POST['update'])) {

    $stmt = $db->prepare("
        UPDATE fees SET
            student_id = ?,
            fee_type = ?,
            amount = ?,
            due_date = ?,
            payment_method = ?
        WHERE receipt_number = ?
    ");

    $stmt->execute([
        $_POST['student_id'],
        $_POST['fee_type'],
        $_POST['amount'],
        $_POST['due_date'],
        $_POST['payment_method'],
        $id
    ]);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Fee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="invoice-wrapper">
<div class="invoice-card">

<div class="invoice-header">
    <h2>Edit Fee</h2>
</div>

<div class="invoice-body">

<!-- MARK PAID -->
<form method="POST">
    <select name="payment_method" style="margin-bottom:10px;">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
        <option value="mobile">Mobile</option>
    </select>

    <button type="submit" name="mark_paid"
            style="background:#28a745;margin-bottom:10px;">
        Mark as Paid
    </button>
</form>

<form method="POST">

    <!-- KEEP RECEIPT (NO CHANGE) -->
    <label>Receipt</label>
    <input type="text" value="<?= $fee['receipt_number'] ?>" readonly>

    <label>Student</label>
    <select name="student_id">
        <?php foreach ($students as $s): ?>
            <option value="<?= $s['student_id'] ?>"
                <?= $fee['student_id'] == $s['student_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['full_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Fee Type</label>
    <select name="fee_type">
        <?php foreach (['rent','deposit','utility','fine','laundry','other'] as $type): ?>
            <option value="<?= $type ?>"
                <?= $fee['fee_type'] == $type ? 'selected' : '' ?>>
                <?= ucfirst($type) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Amount</label>
    <input type="number" step="0.01" name="amount"
           value="<?= $fee['amount'] ?>">

    <label>Due Date</label>
    <input type="date" name="due_date"
           value="<?= $fee['due_date'] ?>">

    <label>Payment Method</label>
    <select name="payment_method">
        <option value="cash" <?= $fee['payment_method']=='cash'?'selected':'' ?>>Cash</option>
        <option value="bank" <?= $fee['payment_method']=='bank'?'selected':'' ?>>Bank</option>
        <option value="mobile" <?= $fee['payment_method']=='mobile'?'selected':'' ?>>Mobile</option>
    </select>

    <button type="submit" name="update">Update</button>

</form>

</div>
</div>
</div>

</body>
</html>