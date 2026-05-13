<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * DATABASE CONNECTION CONFIGURATION
 * 
 * Location: includes/db.php
 * Purpose: Central database connection handler for all modules
 * ═══════════════════════════════════════════════════════════════════════════
 */

// Database credentials
$host     = "localhost";
$dbname   = "hostelhub";
$username = "root";
$password = "";

try {
    // Create PDO connection with UTF-8 encoding
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    
    // Set error mode to exceptions for better error handling
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error and display friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}
?>
