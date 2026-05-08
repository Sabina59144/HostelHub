-- ─────────────────────────────────────────────────────────────────────
--  HostelHub — Database Schema
--  Run this once in phpMyAdmin (or `mysql -u root` from the CLI) to
--  create the database and all tables needed by the app.
--
--  Building layout: 5 floors labelled A, B, C, D, E (A = first floor).
--  Each floor has 20 rooms numbered 01–20, e.g. A01 … A20, B01 … B20,
--  up to E01 … E20 (100 rooms total). The "floor" column is computed
--  automatically from the room_number, so existing INSERT/UPDATE
--  queries do not need to set it.
-- ─────────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS hostelhub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hostelhub;

-- Drop in correct dependency order so the script is re-runnable
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS allocations;
DROP TABLE IF EXISTS maintenance;
DROP TABLE IF EXISTS fees;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────────────
--  users
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE users (
    user_id     INT             NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50)     NOT NULL,
    password    VARCHAR(255)    NOT NULL,
    full_name   VARCHAR(100)    NOT NULL,
    role        ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password hash for "admin123")
INSERT INTO users (username, password, full_name, role, is_active) VALUES
('admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'System Administrator',
 'admin',
 1);

-- ─────────────────────────────────────────────────────────────────────
--  rooms
--    floor — auto-derived from the first letter of room_number.
--            A = 1st floor, B = 2nd, C = 3rd, D = 4th, E = 5th.
--    room_number must follow "<A-E><01-20>", e.g. A01, C14, E20.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE rooms (
    room_id           INT             NOT NULL AUTO_INCREMENT,
    room_number       VARCHAR(20)     NOT NULL,
    floor             CHAR(1)         GENERATED ALWAYS AS (UPPER(LEFT(room_number, 1))) STORED,
    room_type         ENUM('single','double','triple') NOT NULL DEFAULT 'single',
    capacity          INT             NOT NULL DEFAULT 1,
    price_per_month   DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
    ensuite_facility  TINYINT(1)      NOT NULL DEFAULT 0,
    available_from    DATE            NOT NULL DEFAULT (CURDATE()),
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (room_id),
    UNIQUE KEY uq_room_number (room_number),
    KEY idx_room_floor (floor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
--  students
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE students (
    student_id      INT             NOT NULL AUTO_INCREMENT,
    student_number  VARCHAR(20)     NOT NULL,
    full_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NOT NULL,
    phone           VARCHAR(20)     DEFAULT NULL,
    date_of_birth   DATE            DEFAULT NULL,
    room_id         INT             DEFAULT NULL,
    status          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email (email),
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
--  fees
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE fees (
    fee_id          INT             NOT NULL AUTO_INCREMENT,
    receipt_number  VARCHAR(20)     NOT NULL,
    student_id      INT             NOT NULL,
    fee_type        ENUM('rent','deposit','utility','fine','other') NOT NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    due_date        DATE            NOT NULL,
    is_paid         DATE            DEFAULT NULL,

    PRIMARY KEY (fee_id),
    UNIQUE KEY uq_receipt_number (receipt_number),
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_fee_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
--  maintenance
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE maintenance (
    maintenance_id  INT             NOT NULL AUTO_INCREMENT,
    ticket_number   VARCHAR(20)     NOT NULL,
    room_id         INT             NOT NULL,
    assigned_to     VARCHAR(100)    NOT NULL,
    description     TEXT            DEFAULT NULL,
    date_reported   DATE            NOT NULL DEFAULT (CURDATE()),
    reported_by     INT             DEFAULT NULL,
    is_resolved     TINYINT(1)      NOT NULL DEFAULT 0,

    PRIMARY KEY (maintenance_id),
    UNIQUE KEY uq_ticket_number (ticket_number),
    CONSTRAINT fk_maintenance_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_reporter
        FOREIGN KEY (reported_by) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
--  allocations  (history of every assignment / move-out)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE allocations (
    allocation_id   INT             NOT NULL AUTO_INCREMENT,
    student_id      INT             NOT NULL,
    room_id         INT             NOT NULL,
    start_date      DATE            NOT NULL DEFAULT (CURDATE()),
    end_date        DATE            DEFAULT NULL,
    notes           VARCHAR(255)    DEFAULT NULL,

    PRIMARY KEY (allocation_id),
    KEY idx_allocation_student (student_id),
    KEY idx_allocation_room    (room_id),
    CONSTRAINT fk_alloc_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_alloc_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
--  Sample rooms — 100 rooms, 5 floors × 20 rooms each.
--  Capacity, type, ensuite and price are random per room.
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO rooms (room_number, room_type, capacity, price_per_month, ensuite_facility, available_from) VALUES
('A01', 'triple', 3, 3700.00, 0, CURDATE()),
('A02', 'single', 1, 3500.00, 1, CURDATE()),
('A03', 'double', 2, 3450.00, 0, CURDATE()),
('A04', 'single', 1, 3200.00, 0, CURDATE()),
('A05', 'triple', 3, 3700.00, 0, CURDATE()),
('A06', 'triple', 3, 4000.00, 1, CURDATE()),
('A07', 'triple', 3, 3700.00, 0, CURDATE()),
('A08', 'triple', 3, 3700.00, 0, CURDATE()),
('A09', 'single', 1, 3200.00, 0, CURDATE()),
('A10', 'single', 1, 3200.00, 0, CURDATE()),
('A11', 'single', 1, 3500.00, 1, CURDATE()),
('A12', 'triple', 3, 3700.00, 0, CURDATE()),
('A13', 'triple', 3, 3700.00, 0, CURDATE()),
('A14', 'triple', 3, 4000.00, 1, CURDATE()),
('A15', 'triple', 3, 4000.00, 1, CURDATE()),
('A16', 'double', 2, 3450.00, 0, CURDATE()),
('A17', 'double', 2, 3750.00, 1, CURDATE()),
('A18', 'double', 2, 3450.00, 0, CURDATE()),
('A19', 'single', 1, 3500.00, 1, CURDATE()),
('A20', 'double', 2, 3450.00, 0, CURDATE()),
('B01', 'double', 2, 3550.00, 0, CURDATE()),
('B02', 'single', 1, 3300.00, 0, CURDATE()),
('B03', 'single', 1, 3300.00, 0, CURDATE()),
('B04', 'double', 2, 3550.00, 0, CURDATE()),
('B05', 'double', 2, 3550.00, 0, CURDATE()),
('B06', 'triple', 3, 3800.00, 0, CURDATE()),
('B07', 'single', 1, 3600.00, 1, CURDATE()),
('B08', 'double', 2, 3850.00, 1, CURDATE()),
('B09', 'single', 1, 3300.00, 0, CURDATE()),
('B10', 'single', 1, 3600.00, 1, CURDATE()),
('B11', 'double', 2, 3850.00, 1, CURDATE()),
('B12', 'triple', 3, 3800.00, 0, CURDATE()),
('B13', 'triple', 3, 3800.00, 0, CURDATE()),
('B14', 'triple', 3, 3800.00, 0, CURDATE()),
('B15', 'single', 1, 3600.00, 1, CURDATE()),
('B16', 'single', 1, 3300.00, 0, CURDATE()),
('B17', 'single', 1, 3300.00, 0, CURDATE()),
('B18', 'single', 1, 3300.00, 0, CURDATE()),
('B19', 'double', 2, 3550.00, 0, CURDATE()),
('B20', 'triple', 3, 3800.00, 0, CURDATE()),
('C01', 'single', 1, 3500.00, 0, CURDATE()),
('C02', 'double', 2, 3750.00, 0, CURDATE()),
('C03', 'triple', 3, 4000.00, 0, CURDATE()),
('C04', 'triple', 3, 4300.00, 1, CURDATE()),
('C05', 'triple', 3, 4000.00, 0, CURDATE()),
('C06', 'triple', 3, 4300.00, 1, CURDATE()),
('C07', 'single', 1, 3800.00, 1, CURDATE()),
('C08', 'triple', 3, 4000.00, 0, CURDATE()),
('C09', 'single', 1, 3500.00, 0, CURDATE()),
('C10', 'double', 2, 3750.00, 0, CURDATE()),
('C11', 'triple', 3, 4300.00, 1, CURDATE()),
('C12', 'triple', 3, 4000.00, 0, CURDATE()),
('C13', 'triple', 3, 4000.00, 0, CURDATE()),
('C14', 'single', 1, 3500.00, 0, CURDATE()),
('C15', 'single', 1, 3500.00, 0, CURDATE()),
('C16', 'double', 2, 3750.00, 0, CURDATE()),
('C17', 'single', 1, 3500.00, 0, CURDATE()),
('C18', 'triple', 3, 4300.00, 1, CURDATE()),
('C19', 'double', 2, 3750.00, 0, CURDATE()),
('C20', 'triple', 3, 4000.00, 0, CURDATE()),
('D01', 'double', 2, 4250.00, 1, CURDATE()),
('D02', 'double', 2, 3950.00, 0, CURDATE()),
('D03', 'double', 2, 3950.00, 0, CURDATE()),
('D04', 'single', 1, 4000.00, 1, CURDATE()),
('D05', 'triple', 3, 4500.00, 1, CURDATE()),
('D06', 'double', 2, 4250.00, 1, CURDATE()),
('D07', 'triple', 3, 4200.00, 0, CURDATE()),
('D08', 'triple', 3, 4200.00, 0, CURDATE()),
('D09', 'double', 2, 3950.00, 0, CURDATE()),
('D10', 'single', 1, 4000.00, 1, CURDATE()),
('D11', 'double', 2, 3950.00, 0, CURDATE()),
('D12', 'single', 1, 3700.00, 0, CURDATE()),
('D13', 'single', 1, 4000.00, 1, CURDATE()),
('D14', 'single', 1, 4000.00, 1, CURDATE()),
('D15', 'double', 2, 4250.00, 1, CURDATE()),
('D16', 'single', 1, 3700.00, 0, CURDATE()),
('D17', 'double', 2, 4250.00, 1, CURDATE()),
('D18', 'double', 2, 4250.00, 1, CURDATE()),
('D19', 'double', 2, 4250.00, 1, CURDATE()),
('D20', 'single', 1, 4000.00, 1, CURDATE()),
('E01', 'triple', 3, 4500.00, 0, CURDATE()),
('E02', 'triple', 3, 4800.00, 1, CURDATE()),
('E03', 'double', 2, 4550.00, 1, CURDATE()),
('E04', 'double', 2, 4250.00, 0, CURDATE()),
('E05', 'double', 2, 4250.00, 0, CURDATE()),
('E06', 'single', 1, 4000.00, 0, CURDATE()),
('E07', 'single', 1, 4300.00, 1, CURDATE()),
('E08', 'triple', 3, 4500.00, 0, CURDATE()),
('E09', 'triple', 3, 4500.00, 0, CURDATE()),
('E10', 'triple', 3, 4500.00, 0, CURDATE()),
('E11', 'triple', 3, 4500.00, 0, CURDATE()),
('E12', 'triple', 3, 4800.00, 1, CURDATE()),
('E13', 'triple', 3, 4500.00, 0, CURDATE()),
('E14', 'single', 1, 4000.00, 0, CURDATE()),
('E15', 'single', 1, 4300.00, 1, CURDATE()),
('E16', 'triple', 3, 4500.00, 0, CURDATE()),
('E17', 'triple', 3, 4500.00, 0, CURDATE()),
('E18', 'double', 2, 4250.00, 0, CURDATE()),
('E19', 'single', 1, 4000.00, 0, CURDATE()),
('E20', 'double', 2, 4250.00, 0, CURDATE());

-- ─────────────────────────────────────────────────────────────────────
--  Sample students (a couple pre-allocated to demo the joins)
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO students (student_number, full_name, email, phone, date_of_birth, room_id, status) VALUES
('S2025001', 'Alex Johnson', 'alex.j@example.com',  '07123456001', '2003-04-12', 1,    1),
('S2025002', 'Priya Sharma', 'priya.s@example.com', '07123456002', '2002-11-03', 21,   1),
('S2025003', 'Tom Williams', 'tom.w@example.com',   '07123456003', '2004-01-22', NULL, 1);
