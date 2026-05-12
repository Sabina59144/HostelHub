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

