<?php

// ── Database Settings ────────────────────────────────────────
define('DB_HOST', 'localhost');   // XAMPP runs MySQL on localhost
define('DB_USER', 'root');        // Default XAMPP username is root
define('DB_PASS', '');            // Default XAMPP password is empty
define('DB_NAME', 'hostelhub');   // The database we created

// ── Create the connection ────────────────────────────────────
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ── Check if connection failed ───────────────────────────────
if (!$conn) {
    // Stop the script and show an error message if connection fails
    die("Connection failed: " . mysqli_connect_error());
}

// ── Set character encoding to UTF-8 ─────────────────────────
// This makes sure special characters are stored correctly
mysqli_set_charset($conn, "utf8mb4");

// Connection successful — $conn is now ready to use in other files
?>


