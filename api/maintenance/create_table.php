<?php
require_once("../config/db.php");
try {
    // Rooms
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (
        room_id INT AUTO_INCREMENT PRIMARY KEY,
        room_number VARCHAR(20) NOT NULL UNIQUE,
        capacity INT DEFAULT 1
    ) ENGINE=InnoDB;");

    // Students
    $db->exec("CREATE TABLE IF NOT EXISTS students (
        student_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE,
        room_id INT,
        FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // Staffs
    $db->exec("CREATE TABLE IF NOT EXISTS staffs (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(100),
        email VARCHAR(150) UNIQUE
    ) ENGINE=InnoDB;");

    // Maintenance (assigned_to references staffs, reported_by references students)
    $db->exec("CREATE TABLE IF NOT EXISTS maintenance (
        maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_number VARCHAR(20) NOT NULL UNIQUE,
        room_id INT NOT NULL,
        assigned_to INT,
        date_reported DATE DEFAULT (CURRENT_DATE),
        reported_by INT,
        is_resolved TINYINT(1) DEFAULT 0,
        FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES staffs(staff_id) ON DELETE SET NULL,
        FOREIGN KEY (reported_by) REFERENCES students(student_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // Seed some dummy rooms
    $db->exec("INSERT IGNORE INTO rooms (room_id, room_number, capacity) VALUES
        (1, 'A-101', 2),
        (2, 'A-102', 2),
        (3, 'B-201', 3)
    ;");

    // Seed some dummy staffs
    $db->exec("INSERT IGNORE INTO staffs (staff_id, name, role, email) VALUES
        (1, 'John Doe', 'Electrician', 'john.doe@example.com'),
        (2, 'Jane Smith', 'Plumber', 'jane.smith@example.com'),
        (3, 'Samuel Green', 'Caretaker', 'sam.green@example.com')
    ;");

    // Seed some dummy students
    $db->exec("INSERT IGNORE INTO students (student_id, name, email, room_id) VALUES
        (1, 'Alice Johnson', 'alice.j@example.com', 1),
        (2, 'Bob Williams', 'bob.w@example.com', 2),
        (3, 'Charlie Brown', 'charlie.b@example.com', 3)
    ;");


    echo "DB tables created and seeded successfully ✅";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}