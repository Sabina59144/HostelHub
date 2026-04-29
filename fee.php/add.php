<?php
include '../config/db.php';

$message = "";

if (isset($_POST['submit'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id     = (int) $_POST['student_id'];
    $fee_type       = $_POST['fee_type'];
    $amount         = (float) $_POST['amount'];
    $due_date       = $_POST['due_date'];

    if ($amount <= 0) {
        $message = "Amount must be greater than 0.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO fees 
            (receipt_number, student_id, fee_type, amount, due_date)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $receipt_number,
            $student_id,
            $fee_type,
            $amount,
            $due_date
        ]);

        $message = "Fee added successfully!";
    }
}
?>

<h2>Add Fee</h2>
<p><?= $message ?></p>

<form method="POST">
    Receipt Number: <input type="text" name="receipt_number" required><br><br>

    Student ID: <input type="number" name="student_id" required><br><br>

    Fee Type:
    <select name="fee_type" required>
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