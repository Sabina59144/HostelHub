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

    $today = date('Y-m-d');

    $stmt = $db->prepare("UPDATE fees SET is_paid = ? WHERE fee_id = ?");
    $stmt->execute([$today, $id]);

    header("Location: index.php");
    exit;
}
?>

<h2>Mark Fee as Paid</h2>

<p>Receipt: <?= htmlspecialchars($fee['receipt_number']) ?></p>

<form method="POST">
    <button type="submit" name="update">Mark as Paid</button>
</form>