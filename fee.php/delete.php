<?php
require_once("../includes/db.php");

// ── Validate fee ID ───────────────────────────────────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ Invalid request — no fee ID provided.");
}

$id = (int) $_GET['id'];

// Only fetch active records
$stmt = $db->prepare("SELECT * FROM fees WHERE fee_id = ? AND is_active = 1");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    die("❌ Fee record not found or already deleted.");
}

// ── Handle confirmed soft-delete ──────────────────────────────────────────────
if (isset($_POST['confirm_delete'])) {
    $reason = trim($_POST['deleted_reason'] ?? '');
    if (empty($reason)) {
        $reason = null;
    }

    $db->prepare("
        UPDATE fees
        SET is_active      = 0,
            deleted_at     = NOW(),
            deleted_reason = ?
        WHERE fee_id = ?
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
    <style>
        .confirm-card {
            max-width: 460px;
            margin: 80px auto;
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        .confirm-header {
            background: #dc2626;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .confirm-header h2 { color: white; margin: 0 0 4px; font-size: 1.4rem; }
        .confirm-header p  { margin: 0; opacity: 0.9; font-size: 0.9rem; }
        .confirm-body { padding: 28px; }
        .fee-detail-table {
            width: 100%; border-collapse: collapse;
            margin-bottom: 24px; font-size: 0.95rem;
        }
        .fee-detail-table tr { border-bottom: 1px solid #f0f0f0; }
        .fee-detail-table tr:last-child { border-bottom: none; }
        .fee-detail-table td { padding: 10px 6px; }
        .fee-detail-table td:first-child { color: #777; font-weight: 600; width: 40%; }
        .warning-text {
            background: #fff7ed; border-left: 5px solid #f97316; color: #9a3412;
            padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 18px;
        }
        .reason-field { margin-bottom: 22px; }
        .reason-field label { display: block; font-weight: 600; font-size: 0.88rem;
                               color: #444; margin-bottom: 6px; }
        .reason-field textarea {
            width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc;
            box-sizing: border-box; font-family: Arial, sans-serif; font-size: 0.9rem;
            resize: vertical; min-height: 70px;
        }
        .btn-row { display: flex; gap: 10px; }
        .btn-danger { flex: 1; background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-cancel-link {
            flex: 1; display: inline-block; text-align: center; padding: 12px;
            background: #f1f3f5; color: #333; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: background 0.2s;
        }
        .btn-cancel-link:hover { background: #e2e5e9; }
    </style>
</head>
<body>

<div class="confirm-card">

    <div class="confirm-header">
        <h2>🗑 Delete Fee Record?</h2>
        <p>The record will be archived, not permanently removed</p>
    </div>

    <div class="confirm-body">

        <table class="fee-detail-table">
            <tr>
                <td>Receipt #</td>
                <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            </tr>
            <tr>
                <td>Student ID</td>
                <td><?= htmlspecialchars($fee['student_id']) ?></td>
            </tr>
            <tr>
                <td>Fee Type</td>
                <td><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></td>
            </tr>
            <tr>
                <td>Amount</td>
                <td>$<?= number_format($fee['amount'], 2) ?></td>
            </tr>
            <tr>
                <td>Fine</td>
                <td><?= $fee['fine_amount'] > 0 ? '$' . number_format($fee['fine_amount'], 2) : '—' ?></td>
            </tr>
            <tr>
                <td>Total Due</td>
                <td><strong>$<?= number_format($fee['total_due'], 2) ?></strong></td>
            </tr>
            <tr>
                <td>Due Date</td>
                <td><?= htmlspecialchars($fee['due_date']) ?></td>
            </tr>
            <tr>
                <td>Status</td>
                <td><?= $fee['is_paid'] ? '✔ Paid' : '⏳ Unpaid' ?></td>
            </tr>
        </table>

        <div class="warning-text">
            ⚠ This record will be archived (soft-deleted). It will no longer appear in the fees list but remains in the database for other purposes (audit).
        </div>

        <form method="POST">
            <div class="reason-field">
                <label>Reason for deletion <small>(optional)</small></label>
                <textarea name="deleted_reason" placeholder="e.g. Duplicate entry, data entry error…"></textarea>
            </div>

            <div class="btn-row">
                <button type="submit" name="confirm_delete" class="btn-danger">
                    Yes, Archive It
                </button>
                <a href="index.php" class="btn-cancel-link">Cancel</a>
            </div>
        </form>

    </div>
</div>

</body>
</html>
