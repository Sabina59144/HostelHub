<?php
require_once("../config/db.php");

try {
    $query = "
    CREATE TABLE IF NOT EXISTS maintenance (
        maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_number VARCHAR(20) NOT NULL UNIQUE,
        room_id INT NOT NULL,
        assigned_to VARCHAR(100) NOT NULL,
        date_reported DATE DEFAULT CURRENT_DATE,
        reported_by INT,
        is_resolved BOOLEAN DEFAULT FALSE
    );
    ";

    $db->exec($query);

    echo "Maintenance table created successfully ✅";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}