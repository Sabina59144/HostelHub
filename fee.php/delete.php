<?php
include '../config/db.php';

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM fees WHERE fee_id=?");
$stmt->execute([$id]);

echo "Deleted successfully!";
?>