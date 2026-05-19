<?php
// Database connection settings — update these to match your environment
$host     = "localhost";   // Database server (same machine as PHP)
$user     = "root";        // MySQL username
$password = "";            // MySQL password (empty for local dev)
$database = "hostelhub";   // The database name to connect to

try {
    // Create a PDO connection with UTF-8 encoding
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);

    // Throw exceptions on SQL errors instead of silently failing
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Return rows as associative arrays (column name => value) by default
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Stop the page and show the error if the connection fails
    die("Connection failed: " . $e->getMessage());
}
?>