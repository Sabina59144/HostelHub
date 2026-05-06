<?php
require_once("../includes/db.php");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid request.");
}

$id = $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE receipt_number = ? AND is_active = 1");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee record not found.");
}

$originalReceipt = $fee['receipt_number'];
$errors = [];

if (isset($_POST['mark_paid'])) {
    $db->prepare("
        UPDATE fees
        SET is_paid = 1, paid_at = NOW()
        WHERE receipt_number = ?
    ")->execute([$originalReceipt]);

    header("Location: index.php");
    exit;
}

if (isset($_POST['update'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id = trim($_POST['student_id']);
    $fee_type = trim($_POST['fee_type']);
    $amount = trim($_POST['amount']);
    $due_date = trim($_POST['due_date']);
    $fine_rate = trim($_POST['fine_rate']);
    $fine_cap = trim($_POST['fine_cap']);

    $check = $db->prepare("
        SELECT COUNT(*)
        FROM fees
        WHERE receipt_number = ?
        AND receipt_number != ?
    ");
    $check->execute([$receipt_number, $originalReceipt]);

    if ($check->fetchColumn() > 0) {
        $errors[] = "Receipt already exists.";
    }

    if (empty($errors)) {

        $today = date('Y-m-d');
        $fine_amount = 0;

        if (!$fee['is_paid'] && $due_date < $today) {
            $days = floor((strtotime($today) - strtotime($due_date)) / 86400);
            $fine_amount = min($days * $fine_rate, $fine_cap);
        }

        $total_due = $amount + $fine_amount;

        $stmt = $db->prepare("
            UPDATE fees
            SET receipt_number=?,
                student_id=?,
                fee_type=?,
                amount=?,
                due_date=?,
                fine_rate=?,
                fine_cap=?,
                fine_amount=?,
                total_due=?
            WHERE receipt_number=?
        ");

        $stmt->execute([
            $receipt_number,
            $student_id,
            $fee_type,
            $amount,
            $due_date,
            $fine_rate,
            $fine_cap,
            $fine_amount,
            $total_due,
            $originalReceipt
        ]);

        header("Location: index.php");
        exit;
    }
}
?>