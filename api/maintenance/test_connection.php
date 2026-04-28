<?php
require_once("../db.php");

try {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='maintenance'");
    $table = $stmt->fetch();

    if ($table) {
        echo "Maintenance table exists ✅";
    } else {
        echo "Maintenance table NOT found ❌";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}