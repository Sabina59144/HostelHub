<?php
require_once("../includes/db.php");

// Only show active (non-deleted) records
$stmt = $db->prepare("SELECT * FROM fees WHERE is_active = 1 ORDER BY fee_id DESC");
$stmt->execute();
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime();
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
            // Dynamic fine calculation
            $dueDate = new DateTime($fee['due_date']);
            $fineAmount = 0;

            if ($fee['is_paid'] == 1) {
                $status = "Paid";
                $statusClass = "paid";
            } elseif ($today > $dueDate) {
                $daysOverdue = $dueDate->diff($today)->days;

                $fineAmount = $daysOverdue * $fee['fine_rate'];

                if ($fineAmount > $fee['fine_cap']) {
                    $fineAmount = $fee['fine_cap'];
                }

                $status = "Overdue";
                $statusClass = "overdue";
            } else {
                $status = "Unpaid";
                $statusClass = "unpaid";
            }

            $totalDue = $fee['amount'] + $fineAmount;

            $rowClass = ($fineAmount > 0) ? 'has-fine' : '';
        ?>

        <tr class="<?= $rowClass ?>">
            <td><?= $fee['fee_id'] ?></td>
            <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            <td><?= htmlspecialchars($fee['student_id']) ?></td>
            <td><?= ucfirst(htmlspecialchars($fee['fee_type'])) ?></td>
            <td>$<?= number_format($fee['amount'], 2) ?></td>

            <td>
                <?= $fineAmount > 0 ? '$' . number_format($fineAmount, 2) : '—' ?>
            </td>

            <td><strong>$<?= number_format($totalDue, 2) ?></strong></td>

            <td><?= htmlspecialchars($fee['due_date']) ?></td>

            <td class="<?= $statusClass ?>">
                <?= $status ?>
            </td>

            <td>
                <?= $fee['paid_at'] ? htmlspecialchars($fee['paid_at']) : '—' ?>
            </td>

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