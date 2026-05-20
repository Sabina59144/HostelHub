<?php
/**
 * Fee module/delete.php
 * ─────────────────────────────────────────────────────────────
 * Delete a fee record permanently (admin only).
 *
 * Shows a confirmation page with full record details before
 * performing a hard DELETE (the fees table has no soft-delete
 * columns such as is_active or deleted_at).
 *
 * Reached via: index.php → Delete button (passes ?id=receipt_number)
 * ─────────────────────────────────────────────────────────────
 */

/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireRole('admin'); // Only admins can delete fee records
require_once __DIR__ . '/../includes/db.php';

/* ── Validate request ───────────────────────────────── */
if (empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id   = $_GET['id'];
$stmt = $db->prepare("
    SELECT fees.*, students.full_name
    FROM fees
    LEFT JOIN students ON students.student_id = fees.student_id
    WHERE fees.receipt_number = ? AND fees.is_active = 1
");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    header("Location: index.php");
    exit;
}

/* ── Soft delete on confirm ─────────────────────────── */
if (isset($_POST['confirm_delete'])) {
    $reason = trim($_POST['reason'] ?? '');
    $db->prepare("
        UPDATE fees
        SET is_active = 0,
            deleted_at = NOW(),
            deleted_reason = ?
        WHERE receipt_number = ?
    ")->execute([$reason ?: null, $id]);
    header("Location: index.php?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Fee — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 560px; margin: 0 auto; }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #dc2626; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .confirm-card {
            background: #fff; border-radius: 16px; padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #fecaca;
        }

        .warning-banner {
            background: #fff5f5; border: 1px solid #fecaca; border-radius: 10px;
            padding: 14px 18px; margin-bottom: 24px; font-size: 14px; color: #7f1d1d;
        }
        .warning-banner strong { display: block; margin-bottom: 4px; font-size: 15px; }

        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table tr:nth-child(even) { background: #f9fafb; }
        .detail-table td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        .detail-table td:first-child { font-weight: 600; color: #374151; width: 130px; }

        .btn-delete {
            width: 100%; padding: 12px; background: #dc2626; color: #fff;
            border: none; border-radius: 10px; font-size: 15px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background 0.2s;
        }
        .btn-delete:hover { background: #b91c1c; }

        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 14px; color: #64748b; }
        .back-link:hover { color: #1a56db; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h2>Delete Fee Record</h2>
        <p>This will permanently remove the record from the database.</p>
    </div>

    <div class="confirm-card">
        <div class="warning-banner">
            <strong>⚠️ This action cannot be undone.</strong>
            Once deleted, this fee record is gone permanently.
        </div>

        <table class="detail-table">
            <tr>
                <td>Receipt</td>
                <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            </tr>
            <tr>
                <td>Student</td>
                <td><?= htmlspecialchars($fee['full_name'] ?? '—') ?></td>
            </tr>
            <tr>
                <td>Fee Type</td>
                <td><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></td>
            </tr>
            <tr>
                <td>Amount</td>
                <td>£<?= number_format($fee['amount'], 2) ?></td>
            </tr>
            <tr>
                <td>Due Date</td>
                <td><?= htmlspecialchars($fee['due_date']) ?></td>
            </tr>
            <tr>
                <td>Status</td>
                <td><?= $fee['is_paid'] ? '✅ Paid' : '❌ Unpaid' ?></td>
            </tr>
        </table>

        <form method="POST">
            <button type="submit" name="confirm_delete" class="btn-delete"
                    onclick="return confirm('Permanently delete this fee record?')">
                🗑 Confirm Delete
            </bu