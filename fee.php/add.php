<?php
include '../config/db.php';

if (isset($_POST['submit'])) {

    $receipt_number = $_POST['receipt_number'];
    $student_id     = $_POST['student_id'];
    $fee_type       = $_POST['fee_type'];
    $amount         = $_POST['amount'];
    $due_date       = $_POST['due_date'];
    $notes          = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO fees 
    (receipt_number, student_id, fee_type, amount, due_date, notes)
    VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $receipt_number,
        $student_id,
        $fee_type,
        $amount,
        $due_date,
        $notes
    ]);

    echo "Fee added successfully!";
}
?>

<h2>Add Fee</h2>

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
    Notes: <textarea name="notes"></textarea><br><br>

    <button type="submit" name="submit">Save</button>
</form>