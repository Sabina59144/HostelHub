<?php
include '../config/db.php';

$id = $_GET['id'];

if (isset($_POST['update'])) {
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE fees SET status=? WHERE fee_id=?");
    $stmt->execute([$status, $id]);

    echo "Updated successfully!";
}

$stmt = $conn->prepare("SELECT * FROM fees WHERE fee_id=?");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<h2>Edit Fee Status</h2>

<form method="POST">
    Status:
    <select name="status">
        <option value="paid">Paid</option>
        <option value="unpaid">Unpaid</option>
        <option value="overdue">Overdue</option>
        <option value="waived">Waived</option>
    </select><br><br>

    <button type="submit" name="update">Update</button>
</form>