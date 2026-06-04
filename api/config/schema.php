<?php

function schemaColumnExists(PDO $db, string $tableName, string $columnName): bool
{
    $stmt = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1");
    $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
    $stmt->bindValue(':column_name', $columnName, PDO::PARAM_STR);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function ensureMaintenanceArchiveSchema(PDO $db): void
{
    if (!schemaColumnExists($db, 'maintenance', 'is_deleted')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!schemaColumnExists($db, 'maintenance', 'deleted_at')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN deleted_at DATETIME NULL");
    }
    if (!schemaColumnExists($db, 'maintenance', 'description')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN description TEXT NULL AFTER room_id");
    }
    if (!schemaColumnExists($db, 'maintenance', 'status')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Pending'");
    }
    if (!schemaColumnExists($db, 'maintenance', 'resolution_note')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN resolution_note TEXT NULL");
    }
    // Add proper FK column for assigned staff (INT ref → users)
    if (!schemaColumnExists($db, 'maintenance', 'assigned_to_id')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN assigned_to_id INT NULL DEFAULT NULL");
        try {
            $db->exec("ALTER TABLE maintenance ADD CONSTRAINT fk_maintenance_assigned FOREIGN KEY (assigned_to_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (PDOException $e) {
            // FK may already exist or be unsupported in this context — safe to ignore
        }
    }
    // Make legacy assigned_to nullable
    $db->exec("ALTER TABLE maintenance MODIFY COLUMN assigned_to VARCHAR(100) NULL DEFAULT NULL");
    // Clean up legacy 'Pending Assignment' placeholder stored by old code
    $db->exec("UPDATE maintenance SET assigned_to = NULL WHERE assigned_to = 'Pending Assignment'");
    // Drop FK on reported_by — students are reporters but are not in the users table
    try {
        $db->exec("ALTER TABLE maintenance DROP FOREIGN KEY fk_maintenance_reporter");
    } catch (PDOException $e) {
        // Already dropped or never existed — safe to ignore
    }
}

function ensureAuthSchema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    if (!schemaColumnExists($db, 'students', 'password')) {
        $db->exec("ALTER TABLE students ADD COLUMN password VARCHAR(255) NULL");
    }

    $defaultStudentPassword = password_hash('student123', PASSWORD_DEFAULT);
    $studentPasswordStmt = $db->prepare("UPDATE students SET password = :password WHERE password IS NULL OR password = ''");
    $studentPasswordStmt->bindValue(':password', $defaultStudentPassword, PDO::PARAM_STR);
    $studentPasswordStmt->execute();

    $adminStmt = $db->prepare("SELECT admin_id FROM admins WHERE username = :username LIMIT 1");
    $adminStmt->bindValue(':username', 'admin', PDO::PARAM_STR);
    $adminStmt->execute();
    if (!$adminStmt->fetch(PDO::FETCH_ASSOC)) {
        $insertAdminStmt = $db->prepare("INSERT INTO admins (username, name, password) VALUES (:username, :name, :password)");
        $insertAdminStmt->bindValue(':username', 'admin', PDO::PARAM_STR);
        $insertAdminStmt->bindValue(':name', 'Hostel Admin', PDO::PARAM_STR);
        $insertAdminStmt->bindValue(':password', password_hash('admin123', PASSWORD_DEFAULT), PDO::PARAM_STR);
        $insertAdminStmt->execute();
    }
}

