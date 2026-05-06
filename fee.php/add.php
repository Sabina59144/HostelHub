<?php
require_once("../includes/db.php");

$message = "";

/* -----------------------------
   LOAD STUDENTS
------------------------------*/
$students = $db->query("
    SELECT student_id, full_name
    FROM students
    WHERE status = 1
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------
   GENERATE NEXT RECEIPT
------------------------------*/
function generateReceipt($db) {

    $stmt = $db->query("
        SELECT receipt_number
        FROM fees
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && preg_match('/(\d+)$/', $row['receipt_number'], $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }

    return "RCP-" . date("Y") . "-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

$receipt_number = generateReceipt($db);

/* -----------------------------
   INSERT DATA
------------------------------*/
if (isset($_POST['submit'])) {

    $receipt_number = $_POST['receipt_number'];
    $student_id     = $_POST['student_id'];
    $fee_type       = $_POST['fee_type'];
    $amount         = (float) $_POST['amount'];
    $due_date       = $_POST['due_date'];
    $payment_method = $_POST['payment_method'];

    $fine_rate = 0.50;
    $fine_cap  = 15.00;

    $today = date('Y-m-d');
    $fine_amount = 0;

    if ($due_date < $today) {
        $days = floor((strtotime($today) - strtotime($due_date)) / 86400);
        $fine_amount = min($days * $fine_rate, $fine_cap);
    }

    $total_due = $amount + $fine_amount;

    $stmt = $db->prepare("
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            payment_method,
            is_paid,
            fine_rate,
            fine_cap,
            fine_amount,
            total_due,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $receipt_number,
        $student_id,
        $fee_type,
        $amount,
        $due_date,
        $payment_method,
        $fine_rate,
        $fine_cap,
        $fine_amount,
        $total_due
    ]);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Fee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="invoice-wrapper">
<div class="invoice-card">

<div class="invoice-header">
    <h2>Add Fee</h2>
</div>

<div class="invoice-body">

<form method="POST">

<!-- RECEIPT -->
<label>Receipt Number</label>
<input type="text" name="receipt_number"
       id="receipt_number"
       value="<?= $receipt_number ?>" readonly>

<!-- STUDENT -->
<label>Student</label>
<select name="student_id" id="student_id" required>
    <option value="">-- Select Student --</option>
    <?php foreach($students as $s): ?>
        <option value="<?= $s['student_id'] ?>">
            <?= htmlspecialchars($s['full_name']) ?>
        </option>
    <?php endforeach; ?>
</select>

<!-- FEE TYPE -->
<label>Fee Type</label>
<select name="fee_type" required>
    <option value="rent">Rent</option>
    <option value="deposit">Deposit</option>
    <option value="utility">Utility</option>
    <option value="fine">Fine</option>
    <option value="laundry">Laundry</option>
    <option value="other">Other</option>
</select>

<label>Amount</label>
<input type="number" step="0.01" name="amount" required>

<label>Due Date</label>
<input type="date" name="due_date" required>

<label>Payment Method</label>
<select name="payment_method" required>
    <option value="cash">Cash</option>
    <option value="bank">Bank</option>
    <option value="mobile">Mobile</option>
</select>

<button type="submit" name="submit">Save Fee</button>

</form>

</div>
</div>
</div>

<script>
/* -----------------------------
   AUTO RECEIPT CHANGE ON STUDENT CHANGE
   (Frontend logic)
------------------------------*/
document.getElementById("student_id").addEventListener("change", function () {

    let studentId = this.value;
    let receiptField = document.getElementById("receipt_number");

    if (!studentId) return;

    // simple dynamic receipt logic (safe display only)
    let year = new Date().getFullYear();
    let random = Math.floor(Math.random() * 9000) + 1000;

    receiptField.value = "RCP-" + year + "-" + random;
});
</script>

</body>
</html>