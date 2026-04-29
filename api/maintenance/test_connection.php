<?php
require_once("../config/db.php");

try {
    $stmt = $db->query("SHOW TABLES LIKE 'maintenance'");
    $result = $stmt->fetch();

    if ($result) {
        echo "Maintenance table exists ✅";
    } else {
        echo "Maintenance table NOT found ❌";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}