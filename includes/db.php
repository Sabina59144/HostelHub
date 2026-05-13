<?php
/**
 * includes/db.php
 * ─────────────────────────────────────────────────────────────
 * Shared PDO database connection — included by every page that
 * needs to query the database.
 *
 * Exposes: $db  (PDO instance for the 'hostelhub' MySQL database)
 *
 * Settings:
 *   ERRMODE_EXCEPTION    — PDO throws exceptions on query errors
 *                          so we can catch them with try/catch
 *   DEFAULT_FETCH_MODE   — fetchAll() returns associative arrays
 *                          by default (no need to pass FETCH_ASSOC)
 *
 * To use: require_once __DIR__ . '/../includes/db.php';
 * ─────────────────────────────────────────────────────────────
 */
$host     = "localhost";
$user     = "root";
$password = "";       // Default XAMPP password — change in production
$database = "hostelhub";

try {
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);         // Throw exceptions on errors
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);    // Return rows as associative arrays
} catch (PDOException $e) {
    // Fatal — cannot continue without a database connection
    die("Connection failed: " . $e->getMessage());
}
