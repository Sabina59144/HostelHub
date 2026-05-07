<?php
require_once(__DIR__ . "/../includes/db.php");
require_once(__DIR__ . "/../includes/auth.php");
requireRole('admin');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid request.");
}

$id = $_GET['id'];

$stmt = $db->prepare("SELECT * FROM fees WHERE receipt_number=? AND is_active=1");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("Fee record not found.");
}

if (isset($_POST['confirm_delete'])) {
    $reason = trim($_POST['deleted_reason'] ?? '');
    $reason = $reason ?: null;

    $db->prepare("
        UPDATE fees SET is_active=0, deleted_at=NOW(), deleted_reason=?
        WHERE receipt_number=?
    ")->execute([$reason, $id]);

    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Fee — HostelHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="invoice-wrapper">
    <div class="invoice-card">
        <div class="invoice-header" style="background:#dc3545;">
            <h2>Delete Fee Record</h2>
            <p>Logged in as: <?= htmlspecialchars($_SESSION['full_name']) ?> (Admin)</p>
        </div>
        <div class="invoice-body">
            <p style="text-align:center;color:#555;">You are about to soft-delete the following record:</p>

            <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                <tr>
                    <td style="padding:8px;font-weight:bold;">Receipt</td>
                    <td style="padding:8px;"><?= htmlspecialchars($fee['receipt_number']) ?></td>
                </tr>
                <tr style="background:#f9f9f9;">
                    <td style="padding:8px;font-weight:bold;">Student ID</td>
                    <td style="padding:8px;"><?= htmlspecialchars($fee['student_id']) ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;font-weight:bold;">Fee Type</td>
                    <td style="padding:8px;"><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></td>
                </tr>
                <tr style="background:#f9f9f9;">
                    <td style="padding:8px;font-weight:bold;">Amount</td>
                    <td style="padding:8px;">$<?= number_format($fee['amount'], 2) ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;font-weight:bold;">Due Date</td>
                    <td style="padding:8px;"><?= htmlspecialchars($fee['due_date']) ?></td>
                </tr>
            </table>

            <form method="POST">
                <label>Reason for Deletion (optional)</label>
                <textarea name="deleted_reason" rows="3" placeholder="Enter reason..."></textarea>
                <button type="submit" name="confirm_delete" style="background:#dc3545;"
                        onclick="return confirm('Are you sure you want to delete this fee record?')">
                    🗑 Confirm Delete
                </button>
            </form>

            <br>
            <a href="index.php" style="display:block;text-align:center;color:#555;">&#8592; Cancel, go back</a>
        </div>
    </div>
</div>
</body>
</html>
