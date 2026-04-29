<?php
include '../config/db.php';

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$id = $_GET['id'];

if (isset($_POST['update'])) {
    $stmt = $conn->prepare("UPDATE fees SET is_paid = 1 WHERE fee_id = ?");
    $stmt->execute([$id]);

    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM fees WHERE fee_id = ?");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee not found");
}
?>

<h2>Mark Fee as Paid</h2>

<p>Receipt: <?= htmlspecialchars($fee['receipt_number']) ?></p>

<form method="POST">
    <button type="submit" name="update">Mark as Paid</button>
</form>