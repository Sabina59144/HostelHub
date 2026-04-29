<?php
require_once("../includes/db.php");

$message = "";

if (isset($_POST['submit'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id = (int) $_POST['student_id'];
    $fee_type = $_POST['fee_type'];
    $amount = (float) $_POST['amount'];
    $due_date = $_POST['due_date'];

    if ($amount <= 0) {
        $message = "Amount must be greater than 0.";
    } else {

        $stmt = $db->prepare("
            INSERT INTO fees
            (receipt_number, student_id, fee_type, amount, due_date, is_paid)
            VALUES (?, ?, ?, ?, ?, NULL)
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

<h2>Add Fee</h2>

<?php if ($message) echo "<p>$message</p>"; ?>

<form method="POST">

Receipt Number: <input type="text" name="receipt_number" required><br><br>

Student ID: <input type="number" name="student_id" required><br><br>

Fee Type:
<select name="fee_type">
    <option value="rent">Rent</option>
    <option value="deposit">Deposit</option>
    <option value="utility">Utility</option>
    <option value="fine">Fine</option>
    <option value="other">Other</option>
</select><br><br>

Amount: <input type="number" step="0.01" name="amount" required><br><br>

Due Date: <input type="date" name="due_date" required><br><br>

<button type="submit" name="submit">Save</button>

</form>