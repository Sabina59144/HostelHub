<?php
require_once("../includes/db.php");

$stmt = $db->prepare("SELECT * FROM fees ORDER BY fee_id DESC");
$stmt->execute();
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fees List</title>
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
            <th>Due Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php foreach ($fees as $fee): ?>

        <?php
        if ($fee['is_paid'] == 1) {
            $status = "Paid";
            $statusClass = "paid";
        } elseif ($fee['due_date'] < $today) {
            $status = "Overdue";
            $statusClass = "overdue";
        } else {
            $status = "Unpaid";
            $statusClass = "unpaid";
        }
        ?>

        <tr>
            <td><?= $fee['fee_id'] ?></td>
            <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            <td><?= htmlspecialchars($fee['student_id']) ?></td>
            <td><?= htmlspecialchars($fee['fee_type']) ?></td>
            <td>$<?= htmlspecialchars($fee['amount']) ?></td>
            <td><?= htmlspecialchars($fee['due_date']) ?></td>

            <td class="<?= $statusClass ?>">
                <?= $status ?>
            </td>

            <td>
                <a class="pay-btn" href="edit.php?id=<?= $fee['fee_id'] ?>">Mark Paid</a>
                <a class="delete-btn"
                   href="delete.php?id=<?= $fee['fee_id'] ?>"
                   onclick="return confirm('Delete this fee record?')">
                   Delete
                </a>
            </td>
        </tr>

        <?php endforeach; ?>
    </table>

</div>

</body>
</html>