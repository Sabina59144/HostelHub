<?php
require_once("../db.php");

try {
    $query = "
        CREATE TABLE IF NOT EXISTS maintenance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            request_type TEXT NOT NULL,
            description TEXT NOT NULL,
            date_reported TEXT NOT NULL,
            status TEXT DEFAULT 'Pending',
            resolution_note TEXT
        );
    ";

    $db->exec($query);

    echo "Maintenance table created successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}