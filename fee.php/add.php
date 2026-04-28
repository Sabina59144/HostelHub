<?php
include '..//db.php';

$stmt = $conn->query("SELECT * FROM fees");
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Fees List</h2>

<a href="add.php">+ Add Fee</a>

<table border="1">
<tr>
    <th>ID</th>
    <th>Student ID</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php foreach ($fees as $fee): ?>
<tr>
    <td><?= $fee['id'] ?></td>
    <td><?= $fee['student_id'] ?></td>
    <td><?= $fee['amount'] ?></td>
    <td><?= $fee['status'] ?></td>
    <td>
        <a href="edit.php?id=<?= $fee['id'] ?>">Edit</a> |
        <a href="delete.php?id=<?= $fee['id'] ?>">Delete</a>
    </td>
</tr>
<?php endforeach; ?>

</table>
