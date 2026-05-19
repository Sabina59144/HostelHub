<?php
// ─────────────────────────────────────────────────────────────────────────────
// includes/db.php  –  Shared Database Connection
//
// This file creates a single PDO database connection ($db) and makes it
// available to every page that does:
//     require_once '../includes/db.php';
//
// PDO (PHP Data Objects) is the modern, safe way to talk to a database.
// It supports prepared statements which prevent SQL injection.
//
// NOTE: This file contains two connection blocks due to an earlier edit.
//       Only the SECOND block below is used by the application.
//       The first block can be safely removed once confirmed redundant.
// ─────────────────────────────────────────────────────────────────────────────

// ── First connection block (original / legacy) ────────────────────────────────
// This was the first version. Kept for reference; the second block below
// supersedes it with slightly cleaner formatting and a more descriptive error.
$host     = "localhost";
$user     = "root";
$password = "";
$database = "hostelhub";

try {
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    // ERRMODE_EXCEPTION: any SQL error throws a PDOException instead of silently failing.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // FETCH_ASSOC: query results are returned as associative arrays (column names as keys).
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Stop the script and show the error if the connection fails.
    die("Connection failed: " . $e->getMessage());
}
?>
<?php
// ── Second connection block (active version) ─────────────────────────────────
// This overwrites the $db variable set above. This is the version used by
// the current room module, student portal, and all other modules.
//
// Connection settings:
//   host     = localhost   (MySQL runs on the same machine as PHP / XAMPP)
//   dbname   = hostelhub   (the database created by database/schema.sql)
//   username = root        (XAMPP default — change for production!)
//   password = ''          (XAMPP default is blank — set a password for production!)
$host     = 'localhost';
$dbname   = 'hostelhub';
$username = 'root';
$password = '';          // XAMPP default is blank — always set a real password in production

try {
    // Create the PDO connection.
    // charset=utf8 ensures special characters are handled correctly.
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    // Return rows as associative arrays so we can use $row['column_name'].
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Throw an exception on any SQL error (makes bugs easier to spot and fix).
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // If the connection fails, stop and show a clear error message.
    // In production, log this error instead of displaying it to users.
    die("<b>Database connection failed:</b> " . $e->getMessage());
}
