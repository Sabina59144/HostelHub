<?php
include '../config/db.php';

$stmt = $conn->prepare("SELECT * FROM fees ORDER BY fee_id DESC");
$stmt->execute();
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Fees List</h2>

<a href="add.php">+ Add Fee</a>

<table border="1" cellpadding="10">
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
$today = date('Y-m-d');

if (!empty($fee['is_paid'])) {
    $status = "Paid";
} elseif ($fee['due_date'] < $today) {
    $status = "Overdue";
} else {
    $status = "Unpaid";
}
?>

<tr>
    <td><?= $fee['fee_id'] ?></td>
    <td><?= $fee['receipt_number'] ?></td>
    <td><?= $fee['student_id'] ?></td>
    <td><?= $fee['fee_type'] ?></td>
    <td><?= $fee['amount'] ?></td>
    <td><?= $fee['due_date'] ?></td>
    <td><?= $status ?></td>

    <td>
        <a href="edit.php?id=<?= $fee['fee_id'] ?>">Edit</a> |
        <a href="delete.php?id=<?= $fee['fee_id'] ?>">Delete</a>
    </td>
</tr>

<?php endforeach; ?>

</table>