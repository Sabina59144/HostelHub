<?php
// Database connection file (shared across modules)

try {
    $db = new PDO("sqlite:" . __DIR__ . "/../database/hostelhub.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // echo "Connected successfully"; // debug
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>