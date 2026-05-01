<?php
require_once("../includes/db.php");

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE fee_id = ?");
$stmt->execute([$id]);
$fee = $stmt->fetch();

if (!$fee) {
    die("Fee not found");
}

if (isset($_POST['update'])) {

    $stmt = $db->prepare("UPDATE fees SET is_paid = 1 WHERE fee_id = ?");
    $stmt->execute([$id]);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mark Fee Paid</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h2>Mark Fee as Paid</h2>

    <p><strong>Receipt:</strong> <?= htmlspecialchars($fee['receipt_number']) ?></p>
    <p><strong>Student ID:</strong> <?= htmlspecialchars($fee['student_id']) ?></p>
    <p><strong>Amount:</strong> $<?= htmlspecialchars($fee['amount']) ?></p>

    <form method="POST">
        <button type="submit" name="update">Mark as Paid</button>
    </form>

</div>

</body>
</html>