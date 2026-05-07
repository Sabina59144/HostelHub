USE hostelhub;

CREATE TABLE IF NOT EXISTS staff (
    staff_id      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clear any existing staff rows before inserting
DELETE FROM staff;

-- ============================================================
-- IMPORTANT: These hashes were generated fresh.
-- admin  -> admin123
-- staff1 -> staff123
-- staff2 -> staff123
-- ============================================================

INSERT INTO staff (full_name, username, password_hash, role) VALUES
(
    'System Admin',
    'admin',
    '$2y$12$c41ypyspdIsp1QPstiik4elkxBiWURp/GzBVkQ9cYCNl/YaNAlg1i',
    'admin'
),
(
    'Staff Member One',
    'staff1',
    '$2y$12$kl3gfGGg1qbANZZ/.DX1KeHDv60WMTqWnWh35BLqeo.iK5gVI1ocy',
    'staff'
),
(
    'Staff Member Two',
    'staff2',
    '$2y$12$kl3gfGGg1qbANZZ/.DX1KeHDv60WMTqWnWh35BLqeo.iK5gVI1ocy',
    'staff'
);

-- ============================================================
-- TO REGENERATE PASSWORDS MANUALLY in XAMPP shell:
--   php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
-- Then update:
--   UPDATE staff SET password_hash='<output>' WHERE username='admin';
-- ============================================================
