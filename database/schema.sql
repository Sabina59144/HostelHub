-- ─────────────────────────────────────────────────────────────────────────────
--  HostelHub — Database Schema
--
--  HOW TO USE:
--    Run this file once in phpMyAdmin or from the command line:
--      mysql -u root < schema.sql
--
--  This script creates the `hostelhub` database and all required tables.
--  It is SAFE to run again — the DROP TABLE and CREATE DATABASE IF NOT EXISTS
--  statements mean it resets cleanly each time.
--
--  Building layout:
--    5 floors labelled A, B, C, D, E  (A = 1st floor, E = 5th floor).
--    Each floor has 20 rooms numbered 01–20.
--    e.g. A01 … A20, B01 … B20, up to E01 … E20 = 100 rooms total.
--
--  The `floor` column in the rooms table is GENERATED automatically from
--  the first letter of room_number, so you never need to set it manually.
-- ─────────────────────────────────────────────────────────────────────────────


-- ── Create the database if it doesn't exist ──────────────────────────────────
CREATE DATABASE IF NOT EXISTS hostelhub
    CHARACTER SET utf8mb4            -- supports all characters including emojis
    COLLATE utf8mb4_unicode_ci;      -- case-insensitive, handles accented chars

-- Switch to the hostelhub database so all CREATE TABLE statements go here.
USE hostelhub;


-- ── Drop tables in safe order ────────────────────────────────────────────────
-- Foreign key checks are disabled so we can drop in any order.
-- Tables are dropped before being re-created so the script is re-runnable.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS allocations;    -- depends on students + rooms
DROP TABLE IF EXISTS maintenance;    -- depends on rooms + users
DROP TABLE IF EXISTS fees;           -- depends on students
DROP TABLE IF EXISTS students;       -- depends on rooms
DROP TABLE IF EXISTS rooms;          -- base table
DROP TABLE IF EXISTS users;          -- admin/staff accounts
SET FOREIGN_KEY_CHECKS = 1;


-- ══════════════════════════════════════════════════════════
--  TABLE: users
--  Stores admin and staff accounts for the management portal.
--  Passwords are stored as bcrypt hashes (NEVER plain text).
-- ══════════════════════════════════════════════════════════
CREATE TABLE users (
    user_id     INT             NOT NULL AUTO_INCREMENT,  -- unique ID, auto-increments
    username    VARCHAR(50)     NOT NULL,                  -- login name (must be unique)
    password    VARCHAR(255)    NOT NULL,                  -- bcrypt hash (255 chars for future hash sizes)
    full_name   VARCHAR(100)    NOT NULL,                  -- display name shown in the navbar
    role        ENUM('admin','staff') NOT NULL DEFAULT 'staff', -- admin has full access, staff is limited
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,        -- 1 = can log in, 0 = account disabled
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the account was created

    PRIMARY KEY (user_id),
    UNIQUE KEY uq_username (username)   -- prevents two users sharing the same login name
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account (password: "admin123")
-- The hash was generated with PHP's password_hash('admin123', PASSWORD_BCRYPT).
-- IMPORTANT: Change this password after first login in a real deployment.
INSERT INTO users (username, password, full_name, role, is_active) VALUES
('admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'System Administrator',
 'admin',
 1);


-- ══════════════════════════════════════════════════════════
--  TABLE: rooms
--  Every physical room in the hostel.
--
--  Key design decisions:
--    • room_number must follow the pattern <floor letter><01-20>
--      e.g. A01, C14, E20.
--    • `floor` is a GENERATED column — MySQL computes it from the
--      first letter of room_number automatically. You never insert it.
--    • available_from: the earliest date a room can be allocated to a student.
--    • ensuite_facility: 1 = private bathroom, 0 = shared.
-- ══════════════════════════════════════════════════════════
CREATE TABLE rooms (
    room_id           INT             NOT NULL AUTO_INCREMENT,
    room_number       VARCHAR(20)     NOT NULL,            -- e.g. "A01", "E20"
    -- GENERATED column: takes the first character of room_number (uppercased).
    -- STORED means MySQL saves it to disk, so queries can filter/index on it.
    floor             CHAR(1)         GENERATED ALWAYS AS (UPPER(LEFT(room_number, 1))) STORED,
    room_type         ENUM('single','double','triple') NOT NULL DEFAULT 'single',
    capacity          INT             NOT NULL DEFAULT 1,  -- max number of students
    price_per_month   DECIMAL(8,2)    NOT NULL DEFAULT 0.00, -- rent in kr.
    ensuite_facility  TINYINT(1)      NOT NULL DEFAULT 0,  -- 0 = shared, 1 = private
    available_from    DATE            NOT NULL DEFAULT (CURDATE()), -- when the room is ready
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (room_id),
    UNIQUE KEY uq_room_number (room_number),  -- no two rooms can have the same number
    KEY idx_room_floor (floor)                -- index speeds up "show all rooms on floor A" queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════
--  TABLE: students
--  Every student registered in the hostel.
--
--  Key design decisions:
--    • room_id is nullable — NULL means the student is not yet assigned a room.
--    • ON DELETE SET NULL means if a room is deleted, the student stays in the
--      database but their room_id becomes NULL (they are not deleted too).
--    • status: 1 = active (currently enrolled/living here), 0 = inactive.
--    • date_of_birth is used for student login (student_number + dob).
-- ══════════════════════════════════════════════════════════
CREATE TABLE students (
    student_id      INT             NOT NULL AUTO_INCREMENT,
    student_number  VARCHAR(20)     NOT NULL,              -- e.g. "S2025001"
    full_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NOT NULL,
    phone           VARCHAR(20)     DEFAULT NULL,          -- optional
    date_of_birth   DATE            DEFAULT NULL,          -- used for student portal login
    room_id         INT             DEFAULT NULL,          -- NULL if not allocated to any room
    status          TINYINT(1)      NOT NULL DEFAULT 1,    -- 1 = active, 0 = inactive
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email (email),
    -- Foreign key: room_id links to rooms.room_id.
    -- ON UPDATE CASCADE: if the room_id in rooms changes, it updates here too.
    -- ON DELETE SET NULL: if the room is deleted, the student stays but loses their room.
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════
--  TABLE: fees
--  Every fee record linked to a student (rent, deposits, fines, etc.).
--
--  Key design decisions:
--    • is_paid is a DATE (not a boolean). NULL = unpaid.
--      When a payment is recorded, is_paid is set to the payment date.
--    • ON DELETE RESTRICT prevents accidentally deleting a student who
--      still has fee records.
-- ══════════════════════════════════════════════════════════
CREATE TABLE fees (
    fee_id          INT             NOT NULL AUTO_INCREMENT,
    receipt_number  VARCHAR(20)     NOT NULL,              -- unique reference, e.g. "REC-2025-001"
    student_id      INT             NOT NULL,              -- which student this fee belongs to
    fee_type        ENUM('rent','deposit','utility','fine','other') NOT NULL,
    amount          DECIMAL(10,2)   NOT NULL,              -- in kr., must be > 0 (enforced by CHECK)
    due_date        DATE            NOT NULL,              -- when the fee must be paid
    is_paid         DATE            DEFAULT NULL,          -- NULL = not paid; date = paid on this date

    PRIMARY KEY (fee_id),
    UNIQUE KEY uq_receipt_number (receipt_number),
    -- Cannot delete a student if they still have fee records.
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    -- Data quality check: fee amount must be positive.
    CONSTRAINT chk_fee_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════
--  TABLE: maintenance
--  Maintenance tickets for rooms (plumbing, electrical, etc.).
--
--  Key design decisions:
--    • Each ticket is linked to a room (required) and optionally to a
--      user (who reported it).
--    • ON DELETE RESTRICT on room: you can't delete a room that has
--      maintenance tickets — resolve or remove them first.
--    • ON DELETE SET NULL on reporter: if the staff member account is
--      deleted, the ticket stays but reported_by becomes NULL.
--    • is_resolved: 0 = still in progress, 1 = fixed.
-- ══════════════════════════════════════════════════════════
CREATE TABLE maintenance (
    maintenance_id  INT             NOT NULL AUTO_INCREMENT,
    ticket_number   VARCHAR(20)     NOT NULL,              -- e.g. "TK-2025-001"
    room_id         INT             NOT NULL,              -- which room this issue is in
    assigned_to     VARCHAR(100)    NOT NULL,              -- name or team handling the repair
    description     TEXT            DEFAULT NULL,          -- full description of the issue
    date_reported   DATE            NOT NULL DEFAULT (CURDATE()),
    reported_by     INT             DEFAULT NULL,          -- user_id of whoever logged the ticket
    is_resolved     TINYINT(1)      NOT NULL DEFAULT 0,    -- 0 = in progress, 1 = resolved

    PRIMARY KEY (maintenance_id),
    UNIQUE KEY uq_ticket_number (ticket_number),
    -- Room must exist (cannot delete a room with open tickets).
    CONSTRAINT fk_maintenance_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    -- Reporter is optional; if their account is removed the ticket still exists.
    CONSTRAINT fk_maintenance_reporter
        FOREIGN KEY (reported_by) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════
--  TABLE: allocations
--  History log of every time a student was assigned to a room
--  or moved out. Useful for audit / reporting.
--
--  Key design decisions:
--    • start_date: when the student moved in.
--    • end_date: NULL while they are still in the room.
--      When they move out (or the room is deleted), this is set to today.
--    • ON DELETE CASCADE: if a student or room is deleted, their
--      allocation history is deleted too (keeps the table clean).
-- ══════════════════════════════════════════════════════════
CREATE TABLE allocations (
    allocation_id   INT             NOT NULL AUTO_INCREMENT,
    student_id      INT             NOT NULL,
    room_id         INT             NOT NULL,
    start_date      DATE            NOT NULL DEFAULT (CURDATE()), -- move-in date
    end_date        DATE            DEFAULT NULL,                 -- NULL = still living there
    notes           VARCHAR(255)    DEFAULT NULL,                 -- optional admin note

    PRIMARY KEY (allocation_id),
    KEY idx_allocation_student (student_id),  -- fast lookup of all allocations for a student
    KEY idx_allocation_room    (room_id),     -- fast lookup of all occupants a room has had
    CONSTRAINT fk_alloc_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,   -- history deleted when the student is deleted
    CONSTRAINT fk_alloc_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE    -- history deleted when the room is deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════
--  SAMPLE DATA: 100 rooms (5 floors × 20 rooms)
--  The `floor` column is NOT included — it is generated automatically.
--  Prices generally increase on higher floors (A = cheapest, E = most expensive).
-- ══════════════════════════════════════════════════════════
INSERT INTO rooms (room_number, room_type, capacity, price_per_month, ensuite_facility, available_from) VALUES
-- ── Floor A (1st floor) ──────────────────────────────────
('A01', 'triple', 3, 3700.00, 0, CURDATE()),  -- triple, shared bathroom
('A02', 'single', 1, 3500.00, 1, CURDATE()),  -- single, ensuite
('A03', 'double', 2, 3450.00, 0, CURDATE()),
('A04', 'single', 1, 3200.00, 0, CURDATE()),
('A05', 'triple', 3, 3700.00, 0, CURDATE()),
('A06', 'triple', 3, 4000.00, 1, CURDATE()),  -- ensuite premium
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
-- ── Floor B (2nd floor) ──────────────────────────────────
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
-- ── Floor C (3rd floor) ──────────────────────────────────
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
-- ── Floor D (4th floor) ──────────────────────────────────
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
-- ── Floor E (5th floor) ──────────────────────────────────
('E01', 'triple', 3, 4500.00, 0, CURDATE()),
('E02', 'triple', 3, 4800.00, 1, CURDATE()),  -- premium ensuite triple
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


-- ══════════════════════════════════════════════════════════
--  SAMPLE DATA: 3 students
--  S2025001 is pre-allocated to room 1 (A01).
--  S2025002 is pre-allocated to room 21 (B01).
--  S2025003 has no room (room_id = NULL) — unallocated.
-- ══════════════════════════════════════════════════════════
INSERT INTO students (student_number, full_name, email, phone, date_of_birth, room_id, status) VALUES
('S2025001', 'Alex Johnson', 'alex.j@example.com',  '07123456001', '2003-04-12', 1,    1),
('S2025002', 'Priya Sharma', 'priya.s@example.com', '07123456002', '2002-11-03', 21,   1),
('S2025003', 'Tom Williams', 'tom.w@example.com',   '07123456003', '2004-01-22', NULL, 1);
-- Note: room_id 1 = A01 (first row inserted above), room_id 21 = B01 (21st row).
