<?php
include 'db.php';

$room_number = $_POST['room_number'];
$room_type = $_POST['room_type'];
$capacity = $_POST['capacity'];
$price = $_POST['price'];
$ensuite = $_POST['ensuite'];
$available = $_POST['available_from'];

$sql = "INSERT INTO rooms(room_number,room_type,capacity,price_per_month,ensuite_facility,available_from)
VALUES('$room_number','$room_type','$capacity','$price','$ensuite','$available')";

mysqli_query($conn,$sql);

header("Location:index.php");
?>