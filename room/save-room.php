<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/save-room.php  –  Legacy Room Insert Script (OLD VERSION)
//
// ⚠️  WARNING: This is an old file that uses the mysqli extension directly
//     and builds SQL by inserting raw user input into the query string.
//     This is UNSAFE because it is vulnerable to SQL injection attacks.
//
//     The modern, safe version that uses PDO + prepared statements is in:
//         room/add_room.php
//
//     This file is kept for reference only. Do NOT use it for new features.
//     Replace any links pointing here with add_room.php instead.
// ─────────────────────────────────────────────────────────────────────────────

// Includes the old db.php which provides a $conn (mysqli connection).
// Note: the modern code uses /includes/db.php (PDO) instead.
include 'db.php';

// Read form fields directly from $_POST with NO validation or sanitisation.
// In the modern version (add_room.php) every field is validated before use.
$room_number = $_POST['room_number'];
$room_type   = $_POST['room_type'];
$capacity    = $_POST['capacity'];
$price       = $_POST['price'];
$ensuite     = $_POST['ensuite'];
$available   = $_POST['available_from'];

// Build the SQL by string interpolation — this is the unsafe part.
// If $room_number contains something like: 101'; DROP TABLE rooms; --
// it would delete the whole table. Prepared statements (as in add_room.php)
// prevent this completely.
$sql = "INSERT INTO rooms(room_number,room_type,capacity,price_per_month,ensuite_facility,available_from)
VALUES('$room_number','$room_type','$capacity','$price','$ensuite','$available')";

// Execute using the old mysqli API.
mysqli_query($conn, $sql);

// Redirect to the rooms list after inserting.
header("Location:index.php");
?>
