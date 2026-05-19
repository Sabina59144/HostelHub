<?php
/**
 * Maintenace module/api/config/db.php
 * ─────────────────────────────────────────────────────────────
 * PDO database connection for the Maintenance module REST API.
 *
 * Separate from the shared includes/db.php because the maintenance
 * module has its own api/ directory structure.
 * NOTE: Do not echo anything here — every API endpoint that includes
 * this file must return only valid JSON.
 * ─────────────────────────────────────────────────────────────
 */
$host = "localhost";
$dbname = "hostelhub";
$username = "root";
$password = ""; // Default XAMPP — change in production

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // DB connection established. Do not echo here - included scripts expect JSON only.
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>