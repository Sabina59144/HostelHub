<?php
require_once("../includes/db.php");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid request.");
}

$id = $_GET['id'];

$stmt = $db->prepare("
    SELECT * FROM fees
    WHERE receipt_number = ? AND is_active = 1
");
$stmt->execute([$id]);

$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee record not found.");
}

if (isset($_POST['confirm_delete'])) {

    $reason = trim($_POST['deleted_reason'] ?? '');
    $reason = $reason ?: null;

    $db->prepare("
        UPDATE fees
        SET is_active = 0,
            deleted_at = NOW(),
            deleted_reason = ?
        WHERE receipt_number = ?
    ")->execute([$reason, $id]);

    header("Location: index.php");
    exit;
}
?>