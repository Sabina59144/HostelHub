<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "hostelhub";

try {
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<?php

$conn = mysqli_connect("localhost","root","","hostel_db");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

?>