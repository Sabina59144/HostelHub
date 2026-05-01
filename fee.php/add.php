<?php
require_once("../includes/db.php");

$message = "";

// Generate automatic receipt number
$stmt = $db->query("SELECT MAX(fee_id) AS last_id FROM fees");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$next_id = ($row['last_id'] ?? 0) + 1;
$receipt_number = "RCP-" . date("Y") . "-" . str_pad($next_id, 4, "0", STR_PAD_LEFT);

if (isset($_POST['submit'])) {

    $student_id = (int) $_POST['student_id'];
    $fee_type   = $_POST['fee_type'];
    $amount     = (float) $_POST['amount'];
    $due_date   = $_POST['due_date'];

    if ($student_id <= 0 || $amount <= 0 || empty($due_date)) {
        $message = "Please fill all fields correctly.";
    } else {

        $stmt = $db->prepare("
            INSERT INTO fees
            (receipt_number, student_id, fee_type, amount, due_date, is_paid)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $receipt_number,
            $student_id,
            $fee_type,
            $amount,
            $due_date
        ]);

        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Fee</title>
    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>

<div class="invoice-wrapper">

<div class="invoice-card">

    <div class="invoice-header">
        <h2>Generate Fee Entry</h2>
        <p>Hostel Fee Management System</p>
    </div>

    <div class="invoice-body">

        <?php if ($message): ?>
            <div class="error"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">

            <label>Receipt Number</label>
            <input type="text" name="receipt_number" value="<?= $receipt_number ?>" readonly>

            <label>Student ID</label>
            <input type="number" name="student_id" required>

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
            <input type="text" id="due_date" name="due_date" required readonly>

            <button type="submit" name="submit">Confirm Fee Entry</button>

        </form>

    </div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#due_date", {
    dateFormat: "Y-m-d"
});
</script>

</body>
</html>