-- ============================================================
-- HostelHub — Full Database
-- Drop & recreate for clean import in XAMPP / phpMyAdmin
-- All modules use the same students, rooms, users
-- ============================================================

DROP DATABASE IF EXISTS hostelhub;
CREATE DATABASE hostelhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostelhub;

-- ============================================================
-- 1. USERS  (staff who log in + appear in maintenance)
-- Password for ALL accounts: password
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi
-- ============================================================
CREATE TABLE users (
    user_id    INT          NOT NULL AUTO_INCREMENT,
    username   VARCHAR(50)  NOT NULL,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    role       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, full_name, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi', 'System Administrator', 'admin', 1);

-- ============================================================
-- 2. ROOMS
-- ============================================================
CREATE TABLE rooms (
    room_id         INT           NOT NULL AUTO_INCREMENT,
    room_number     VARCHAR(10)   NOT NULL,
    room_type       VARCHAR(20)   NOT NULL,
    capacity        INT           NOT NULL DEFAULT 1,
    price_per_month DECIMAL(10,2) NOT NULL,
    is_ensuite      TINYINT(1)    NOT NULL DEFAULT 0,
    available_from  DATE          DEFAULT NULL,
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_room_number (room_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rooms seed removed; create rooms via admin


-- ============================================================
-- 3. STUDENTS
-- room_id references rooms above — every active student has a room
-- password column added for student portal login (all: "password")
-- ============================================================
CREATE TABLE students (
    student_id     INT          NOT NULL AUTO_INCREMENT,
    student_number VARCHAR(20)  NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    date_of_birth  DATE         DEFAULT NULL,
    room_id        INT          DEFAULT NULL,
    password       VARCHAR(255) DEFAULT NULL,
    status         TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email  (email),
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Students seed removed; create students via admin


-- ============================================================
-- 4. FEES
-- Every active student (1-9) has at least a deposit + rent record
-- ============================================================
CREATE TABLE fees (
    receipt_number  VARCHAR(30)   NOT NULL,
    student_id      INT           NOT NULL,
    fee_type        ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    due_date        DATE          NOT NULL,
    is_paid         TINYINT(1)    NOT NULL DEFAULT 0,
    paid_at         TIMESTAMP     NULL,
    payment_method  VARCHAR(20)   NULL,
    fine_rate       DECIMAL(5,2)  NOT NULL DEFAULT 0.50,
    fine_cap        DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    fine_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_due       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    deleted_at      DATETIME      NULL DEFAULT NULL,
    deleted_reason  VARCHAR(255)  NULL DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_number),
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_fee_student  ON fees (student_id);
CREATE INDEX idx_fee_is_paid  ON fees (is_paid);
CREATE INDEX idx_fee_due_date ON fees (due_date);
CREATE INDEX idx_fee_type     ON fees (fee_type);
CREATE INDEX idx_fee_active   ON fees (is_active);

-- Fees seed removed; fees will be generated when students are created/assigned


-- ============================================================
-- 5. MAINTENANCE
-- assigned_to_id = FK → users(user_id)  (NULL = not assigned)
-- assigned_to    = legacy VARCHAR column, kept NULL (migrated away)
-- reported_by    = student_id from students table (no FK — students != users)
-- ============================================================
CREATE TABLE maintenance (
    maintenance_id  INT          NOT NULL AUTO_INCREMENT,
    ticket_number   VARCHAR(20)  NOT NULL UNIQUE,
    room_id         INT          NOT NULL,
    description     TEXT         NULL,
    assigned_to     VARCHAR(100) NULL DEFAULT NULL,
    assigned_to_id  INT          NULL DEFAULT NULL,
    date_reported   DATE         NOT NULL DEFAULT (CURRENT_DATE),
    reported_by     INT          DEFAULT NULL,
    is_resolved     TINYINT(1)   NOT NULL DEFAULT 0,
    status          VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    resolution_note TEXT         NULL,
    is_deleted      TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at      DATETIME     NULL,
    PRIMARY KEY (maintenance_id),
    CONSTRAINT fk_maintenance_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_assigned
        FOREIGN KEY (assigned_to_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maintenance seed removed; create tickets via student/admin UI


-- ============================================================
-- 6. TRIGGERS  (auto-generate fees when a room is assigned)
-- ============================================================
DROP TRIGGER IF EXISTS after_student_room_assigned;
DELIMITER $$
CREATE TRIGGER after_student_room_assigned
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    IF OLD.room_id IS NULL AND NEW.room_id IS NOT NULL THEN
        SET @room_price = 0;
        SELECT price_per_month INTO @room_price FROM rooms WHERE room_id = NEW.room_id;
        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(NEW.student_id, 4, '0'));
        INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
        VALUES (CONCAT(@receipt, '-DEP'), NEW.student_id, 'deposit', @room_price, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
        INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
        VALUES (CONCAT(@receipt, '-RENT'), NEW.student_id, 'rent', @room_price, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS after_student_insert;
DELIMITER $$
CREATE TRIGGER after_student_insert
AFTER INSERT ON students
FOR EACH ROW
BEGIN
    IF NEW.room_id IS NOT NULL THEN
        SET @room_price = 0;
        SELECT price_per_month INTO @room_price FROM rooms WHERE room_id = NEW.room_id;
        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(NEW.student_id, 4, '0'), '-AUTO');
        INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
        VALUES (CONCAT(@receipt, '-DEP'), NEW.student_id, 'deposit', @room_price, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
        INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
        VALUES (CONCAT(@receipt, '-RENT'), NEW.student_id, 'rent', @room_price, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
    END IF;
END$$
DELIMITER ;
