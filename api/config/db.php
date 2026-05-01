<?php
$host = "localhost";
$dbname = "hostelhub";
$username = "root";
$password = "";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // DB connection established. Do not echo here - included scripts expect JSON only.
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>