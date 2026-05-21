-- ============================================================
-- HostelHub — Merged Schema
-- Run this once on a fresh database: hostelhub
-- ============================================================

-- Create the database if it doesn't already exist, using full Unicode support
CREATE DATABASE IF NOT EXISTS hostelhub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Switch to the hostelhub database for all statements below
USE hostelhub;

-- ── Users (staff & admins) ──────────────────────────────────
-- Stores every staff member and admin who can log in to the system
CREATE TABLE IF NOT EXISTS users (
    user_id     INT          NOT NULL AUTO_INCREMENT,  -- Unique ID, auto-increments
    username    VARCHAR(50)  NOT NULL,                 -- Login username
    password    VARCHAR(255) NOT NULL,                 -- Bcrypt-hashed password
    full_name   VARCHAR(100) NOT NULL,                 -- Display name
    role        ENUM('admin','staff') NOT NULL DEFAULT 'staff', -- Permission level
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,       -- 1 = active, 0 = disabled
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Row creation time
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_username (username)                  -- Usernames must be unique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data: two default accounts (password for both: "password")
-- The long hash is the bcrypt hash of "password"
INSERT INTO users (username, password, full_name, role, is_active) VALUES
('admin',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Member One',     'staff', 1);

-- ── Rooms ───────────────────────────────────────────────────
-- Stores every physical room in the hostel
CREATE TABLE IF NOT EXISTS rooms (
    room_id         INT           NOT NULL AUTO_INCREMENT,  -- Unique room ID
    room_number     VARCHAR(10)   NOT NULL,                 -- e.g. "101", "A2"
    room_type       VARCHAR(20)   NOT NULL,                 -- e.g. "single", "shared"
    capacity        INT           NOT NULL,                 -- Max number of occupants
    price_per_month DECIMAL(10,2) NOT NULL,                 -- Monthly rent amount
    is_ensuite      TINYINT(1)    NOT NULL DEFAULT 0,       -- 1 = has private bathroom
    available_from  DATE          DEFAULT NULL,             -- NULL = currently occupied
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_room_number (room_number)                 -- Room numbers must be unique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Students ────────────────────────────────────────────────
-- Stores every student registered in the hostel
CREATE TABLE IF NOT EXISTS students (
    student_id     INT          NOT NULL AUTO_INCREMENT,    -- Internal unique ID
    student_number VARCHAR(20)  NOT NULL,                   -- Official student number e.g. "STU-2024-001"
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    date_of_birth  DATE         DEFAULT NULL,
    room_id        INT          DEFAULT NULL,               -- NULL = not currently assigned a room
    status         TINYINT(1)   NOT NULL DEFAULT 1,         -- 1 = active, 0 = inactive/left
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email  (email),
    -- Links student to a room; if the room is deleted, room_id becomes NULL
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data: 8 sample students, all unassigned to rooms (room_id = NULL)
INSERT INTO students (student_id, student_number, full_name, email, date_of_birth, room_id, status) VALUES
(100, 'STU-2024-001', 'James Carter',    'james.carter@student.edu',    '2001-03-15', NULL, 1),
(101, 'STU-2024-002', 'Priya Sharma',    'priya.sharma@student.edu',    '2002-07-22', NULL, 1),
(102, 'STU-2024-003', 'Oliver Bennett',  'oliver.bennett@student.edu',  '2001-11-05', NULL, 1),
(103, 'STU-2024-004', 'Amara Osei',      'amara.osei@student.edu',      '2003-01-18', NULL, 1),
(104, 'STU-2024-005', 'Lucas Rivera',    'lucas.rivera@student.edu',    '2002-09-30', NULL, 1),
(105, 'STU-2024-006', 'Sophie Walsh',    'sophie.walsh@student.edu',    '2001-06-12', NULL, 1),
(106, 'STU-2024-007', 'Daniel Mwangi',   'daniel.mwangi@student.edu',   '2003-04-25', NULL, 1),
(107, 'STU-2024-008', 'Emma Thompson',   'emma.thompson@student.edu',   '2002-08-09', NULL, 1);

-- ── Fees (your complete version) ────────────────────────────
-- Tracks every charge raised against a student (rent, deposits, fines, etc.)
CREATE TABLE IF NOT EXISTS fees (
    receipt_number VARCHAR(30)   NOT NULL,   -- Human-readable receipt ID e.g. "RCP-2026-0001"
    student_id     INT           NOT NULL,   -- Which student owes this fee
    fee_type       ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,   -- Original fee amount before any fine
    due_date       DATE          NOT NULL,   -- Payment deadline
    is_paid        TINYINT(1)    NOT NULL DEFAULT 0,   -- 0 = unpaid, 1 = paid
    paid_at        DATETIME      NULL,                 -- When payment was recorded (NULL if unpaid)
    payment_method VARCHAR(20)   NULL,                 -- e.g. "cash", "card", "transfer"
    fine_rate      DECIMAL(5,2)  NOT NULL DEFAULT 0.50,  -- Daily fine rate (£/day)
    fine_cap       DECIMAL(5,2)  NOT NULL DEFAULT 15.00, -- Maximum fine that can accumulate
    fine_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- Calculated fine so far
    total_due      DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- amount + fine_amount
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,     -- 0 = soft-deleted
    deleted_at     DATETIME      NULL,                   -- When it was soft-deleted
    deleted_reason VARCHAR(255)  NULL,                   -- Why it was deleted
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_number),
    -- Prevents deleting a student who still has fee records
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data: 8 sample fee records covering various types and statuses
-- RCP-0002 and RCP-0006 are already paid (is_paid = 1, paid_at = NOW())
-- RCP-0003, RCP-0004, RCP-0007 have accumulated fines (overdue)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active) VALUES
('RCP-2026-0001', 100, 'rent',    500.00, '2026-06-01', 0, NULL,  0.50, 15.00,  0.00, 500.00, 1),
('RCP-2026-0002', 101, 'deposit', 200.00, '2026-05-15', 1, NOW(), 0.50, 15.00,  0.00, 200.00, 1),
('RCP-2026-0003', 102, 'utility',  75.00, '2026-03-01', 0, NULL,  0.50, 15.00, 15.00,  90.00, 1),
('RCP-2026-0004', 103, 'fine',     25.00, '2026-02-10', 0, NULL,  0.50, 15.00,  7.50,  32.50, 1),
('RCP-2026-0005', 104, 'laundry',  30.00, '2026-06-10', 0, NULL,  0.50, 15.00,  0.00,  30.00, 1),
('RCP-2026-0006', 105, 'rent',    500.00, '2026-06-01', 1, NOW(), 0.50, 15.00,  0.00, 500.00, 1),
('RCP-2026-0007', 106, 'other',    40.00, '2026-04-20', 0, NULL,  0.50, 15.00, 10.00,  50.00, 1),
('RCP-2026-0008', 107, 'deposit', 200.00, '2026-05-20', 0, NULL,  0.50, 15.00,  0.00, 200.00, 1);

-- ── Maintenance ─────────────────────────────────────────────
-- Tracks repair/maintenance requests raised for rooms
CREATE TABLE IF NOT EXISTS maintenance (
    maintenance_id INT          NOT NULL AUTO_INCREMENT,  -- Unique ticket ID
    ticket_number  VARCHAR(20)  NOT NULL UNIQUE,          -- Human-readable ref e.g. "TKT-001"
    room_id        INT          NOT NULL,                 -- Which room needs maintenance
    assigned_to    VARCHAR(100) NOT NULL,                 -- Name of the contractor/staff member
    date_reported  DATE         NOT NULL DEFAULT (CURRENT_DATE), -- When the issue was logged
    reported_by    INT          DEFAULT NULL,             -- User ID of the staff who raised it (nullable)
    is_resolved    TINYINT(1)   NOT NULL DEFAULT 0,       -- 0 = open, 1 = resolved
    PRIMARY KEY (maintenance_id),
    -- Prevents deleting a room that still has open maintenance tickets
    CONSTRAINT fk_maintenance_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- If the reporting user is deleted, reported_by becomes NULL (not a blocker)
    CONSTRAINT fk_maintenance_reporter
        FOREIGN KEY (reported_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;