<?php
require_once("../includes/db.php");

// Only show active (non-deleted) records
$stmt = $db->prepare("SELECT * FROM fees WHERE is_active = 1 ORDER BY fee_id DESC");
$stmt->execute();
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fees List — HostelHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h2>Hostel Fee Records</h2>

    <a href="add.php" class="add-btn">+ Add Fee</a>

    <table>
        <tr>
            <th>ID</th>
            <th>Receipt</th>
            <th>Student</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Fine</th>
            <th>Total Due</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Paid At</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($fees as $fee): ?>

        <?php
        if ($fee['is_paid'] == 1) {
            $status      = "Paid";
            $statusClass = "paid";
        } elseif ($fee['due_date'] < $today) {
            $status      = "Overdue";
            $statusClass = "overdue";
        } else {
            $status      = "Unpaid";
            $statusClass = "unpaid";
        }
        ?>

        <tr>
            <td><?= $fee['fee_id'] ?></td>
            <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            <td><?= htmlspecialchars($fee['student_id']) ?></td>
            <td><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></td>
            <td>$<?= number_format($fee['amount'], 2) ?></td>
            <td class="<?= $fee['fine_amount'] > 0 ? 'has-fine' : '' ?>">
                <?= $fee['fine_amount'] > 0 ? '$' . number_format($fee['fine_amount'], 2) : '—' ?>
            </td>
            <td><strong>$<?= number_format($fee['total_due'], 2) ?></strong></td>
            <td><?= htmlspecialchars($fee['due_date']) ?></td>
            <td class="<?= $statusClass ?>"><?= $status ?></td>
            <td><?= $fee['paid_at'] ? htmlspecialchars($fee['paid_at']) : '—' ?></td>
            <td>
                <a class="pay-btn" href="edit.php?id=<?= $fee['fee_id'] ?>">✏ Edit</a>
                <a class="delete-btn" href="delete.php?id=<?= $fee['fee_id'] ?>">🗑 Delete</a>
            </td>
        </tr>

        <?php endforeach; ?>

    </table>

</div>

</body>
</html>
