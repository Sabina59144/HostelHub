<?php
// BUG FIX: All PHP files used require_once("../includes/db.php")
// but db.php lives in the SAME folder as the other files.
// Place all files in one folder (e.g. /hostelhub/) and use this file as-is.

$host     = "localhost";
$dbname   = "hostelhub";
$username = "root";
$password = "";

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
