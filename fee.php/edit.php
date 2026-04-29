<?php
include '../config/db.php';

$id = $_GET['id'];

// Mark as paid with current date
if (isset($_POST['update'])) {

    $stmt = $conn->prepare("UPDATE fees SET is_paid = CURDATE() WHERE fee_id = ?");
    $stmt->execute([$id]);

    echo "Marked as paid!";
}

$stmt = $conn->prepare("SELECT * FROM fees WHERE fee_id = ?");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<h2>Mark Fee as Paid</h2>

<p>Receipt: <?= $fee['receipt_number'] ?></p>

<form method="POST">
    <button type="submit" name="update">Mark as Paid</button>
</form>