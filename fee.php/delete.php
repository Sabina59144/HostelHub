<?php
require_once("../includes/db.php");

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("DELETE FROM fees WHERE fee_id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;