<?php
$host = "localhost";
$dbname = "hostelhub";
$username = "root";
$password = "";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "DB Connected ✅"; // For demo (you can remove later)
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>