CREATE DATABASE IF NOT EXISTS hostelhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostelhub;

CREATE TABLE IF NOT EXISTS users (
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
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi', 'System Administrator', 'admin', 1),
('sarah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi', 'Sarah Johnson',        'staff', 1),
('james', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi', 'James Carter',        'staff', 1);

CREATE TABLE IF NOT EXISTS rooms (
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

INSERT INTO rooms (room_number, room_type, capacity, price_per_month, is_ensuite, available_from) VALUES
('101', 'single', 1, 450.00, 0, '2025-09-01'),
('102', 'single', 1, 450.00, 0, '2025-09-01'),
('103', 'double', 2, 350.00, 0, '2025-09-01'),
('104', 'double', 2, 350.00, 1, '2025-09-01'),
('105', 'single', 1, 550.00, 1, '2025-09-01'),
('106', 'studio', 1, 650.00, 1, '2025-09-01'),
('107', 'single', 1, 450.00, 0, '2025-09-01'),
('108', 'double', 2, 350.00, 0, '2025-09-01'),
('201', 'single', 1, 500.00, 1, '2025-09-01'),
('202', 'studio', 1, 700.00, 1, '2025-09-01');

CREATE TABLE IF NOT EXISTS students (
    student_id     INT          NOT NULL AUTO_INCREMENT,
    student_number VARCHAR(20)  NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    date_of_birth  DATE         DEFAULT NULL,
    room_id        INT          DEFAULT NULL,
    status         TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email  (email),
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO students (student_number, full_name, email, date_of_birth, room_id, status) VALUES
('STU-2025-001', 'Alice Morgan',   'alice.morgan@student.ac.uk',  '2003-04-12', 1, 1),
('STU-2025-002', 'Ben Thompson',   'ben.thompson@student.ac.uk',  '2002-08-23', 2, 1),
('STU-2025-003', 'Chloe Davis',    'chloe.davis@student.ac.uk',   '2003-01-07', 3, 1),
('STU-2025-004', 'David Lee',      'david.lee@student.ac.uk',     '2002-11-30', 3, 1),
('STU-2025-005', 'Emma Wilson',    'emma.wilson@student.ac.uk',   '2003-06-15', 4, 1),
('STU-2025-006', 'Finn OBrien',    'finn.obrien@student.ac.uk',   '2002-03-19', 5, 1),
('STU-2025-007', 'Grace Kim',      'grace.kim@student.ac.uk',     '2003-09-02', 6, 1),
('STU-2025-008', 'Harry Evans',    'harry.evans@student.ac.uk',   '2002-12-11', 7, 1),
('STU-2025-009', 'Isla Patel',     'isla.patel@student.ac.uk',    '2003-07-28', 8, 1),
('STU-2025-010', 'Jake Roberts',   'jake.roberts@student.ac.uk',  '2002-05-04', NULL, 0);

CREATE TABLE IF NOT EXISTS fees (
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

CREATE INDEX idx_student_id ON fees (student_id);
CREATE INDEX idx_is_paid    ON fees (is_paid);
CREATE INDEX idx_due_date   ON fees (due_date);
CREATE INDEX idx_fee_type   ON fees (fee_type);
CREATE INDEX idx_is_active  ON fees (is_active);

INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method, fine_rate, fine_cap, fine_amount, total_due, is_active) VALUES
('RCP-2025-0001', 1, 'deposit', 450.00, '2025-09-01', 1, '2025-08-28 10:30:00', 'bank',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2025-0002', 2, 'deposit', 450.00, '2025-09-01', 1, '2025-08-30 14:15:00', 'cash',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2025-0003', 3, 'deposit', 350.00, '2025-09-01', 1, '2025-09-01 09:00:00', 'bank',  0.50, 15.00,  0.00, 350.00, 1),
('RCP-2025-0004', 4, 'deposit', 350.00, '2025-09-01', 1, '2025-08-29 11:45:00', 'bank',  0.50, 15.00,  0.00, 350.00, 1),
('RCP-2025-0005', 5, 'deposit', 350.00, '2025-09-01', 0, NULL,                  NULL,    0.50, 15.00,  0.00, 350.00, 1),
('RCP-2026-0001', 1, 'rent',    450.00, '2026-01-01', 1, '2025-12-28 08:00:00', 'bank',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2026-0002', 2, 'rent',    450.00, '2026-01-01', 1, '2025-12-31 16:20:00', 'card',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2026-0003', 3, 'rent',    350.00, '2026-01-01', 1, '2026-01-02 10:00:00', 'bank',  0.50, 15.00,  0.00, 350.00, 1),
('RCP-2026-0004', 4, 'rent',    350.00, '2026-01-01', 0, NULL,                  NULL,    0.50, 15.00, 15.00, 365.00, 1),
('RCP-2026-0005', 5, 'rent',    350.00, '2026-01-01', 0, NULL,                  NULL,    0.50, 15.00, 15.00, 365.00, 1),
('RCP-2026-0006', 1, 'rent',    450.00, '2026-02-01', 1, '2026-01-30 09:30:00', 'bank',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2026-0007', 6, 'rent',    550.00, '2026-02-01', 1, '2026-02-01 12:00:00', 'card',  0.50, 15.00,  0.00, 550.00, 1),
('RCP-2026-0008', 7, 'rent',    650.00, '2026-02-01', 0, NULL,                  NULL,    0.50, 15.00, 15.00, 665.00, 1),
('RCP-2026-0009', 8, 'rent',    450.00, '2026-03-01', 0, NULL,                  NULL,    0.50, 15.00, 15.00, 465.00, 1),
('RCP-2026-0010', 2, 'utility',  75.00, '2026-03-01', 1, '2026-03-01 08:00:00', 'cash',  0.50, 15.00,  0.00,  75.00, 1),
('RCP-2026-0011', 3, 'utility',  75.00, '2026-03-01', 0, NULL,                  NULL,    0.50, 15.00, 15.00,  90.00, 1),
('RCP-2026-0012', 4, 'fine',     25.00, '2026-03-15', 0, NULL,                  NULL,    0.50, 15.00,  7.50,  32.50, 1),
('RCP-2026-0013', 5, 'laundry',  30.00, '2026-04-01', 0, NULL,                  NULL,    0.50, 15.00,  0.00,  30.00, 1),
('RCP-2026-0014', 1, 'rent',    450.00, '2026-04-01', 1, '2026-03-28 14:00:00', 'bank',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2026-0015', 9, 'deposit', 350.00, '2026-04-01', 1, '2026-04-01 10:00:00', 'bank',  0.50, 15.00,  0.00, 350.00, 1),
('RCP-2026-0016', 9, 'rent',    350.00, '2026-05-01', 0, NULL,                  NULL,    0.50, 15.00,  0.00, 350.00, 1),
('RCP-2026-0017', 1, 'rent',    450.00, '2026-05-01', 1, '2026-04-30 11:00:00', 'bank',  0.50, 15.00,  0.00, 450.00, 1),
('RCP-2026-0018', 6, 'rent',    550.00, '2026-05-01', 0, NULL,                  NULL,    0.50, 15.00,  0.00, 550.00, 1),
('RCP-2026-0019', 7, 'utility',  80.00, '2026-05-15', 0, NULL,                  NULL,    0.50, 15.00,  0.00,  80.00, 1),
('RCP-2026-0020', 3, 'other',    40.00, '2026-05-20', 0, NULL,                  NULL,    0.50, 15.00,  0.00,  40.00, 1);

CREATE TABLE IF NOT EXISTS maintenance (
    maintenance_id INT          NOT NULL AUTO_INCREMENT,
    ticket_number  VARCHAR(20)  NOT NULL UNIQUE,
    room_id        INT          NOT NULL,
    assigned_to    VARCHAR(100) NOT NULL,
    date_reported  DATE         NOT NULL DEFAULT (CURRENT_DATE),
    reported_by    INT          DEFAULT NULL,
    is_resolved    TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (maintenance_id),
    CONSTRAINT fk_maintenance_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_reporter
        FOREIGN KEY (reported_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO maintenance (ticket_number, room_id, assigned_to, date_reported, reported_by, is_resolved) VALUES
('TKT-2026-001', 1, 'John Smith',  '2026-01-10', 2, 1),
('TKT-2026-002', 3, 'Mike Brown',  '2026-01-15', 2, 1),
('TKT-2026-003', 5, 'John Smith',  '2026-02-03', 3, 0),
('TKT-2026-004', 2, 'Mike Brown',  '2026-02-14', 2, 1),
('TKT-2026-005', 7, 'Sarah Jones', '2026-02-20', 3, 0),
('TKT-2026-006', 4, 'John Smith',  '2026-03-05', 2, 1),
('TKT-2026-007', 8, 'Mike Brown',  '2026-03-18', 3, 0),
('TKT-2026-008', 6, 'Sarah Jones', '2026-04-02', 2, 0),
('TKT-2026-009', 1, 'John Smith',  '2026-04-22', 3, 0),
('TKT-2026-010', 9, 'Mike Brown',  '2026-05-01', 2, 0);

DROP TRIGGER IF EXISTS after_student_room_assigned;
DELIMITER $$
CREATE TRIGGER after_student_room_assigned
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    IF OLD.room_id IS NULL AND NEW.room_id IS NOT NULL THEN
        SET @room_price = 0;
        SELECT price_per_month INTO @room_price FROM rooms WHERE room_id = NEW.room_id;
        SET @last_n = 1;
        SELECT COALESCE(CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED), 0) + 1
        INTO @last_n FROM fees;
        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(@last_n, 4, '0'));
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
        SET @last_n = 1;
        SELECT COALESCE(CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED), 0) + 1
        INTO @last_n FROM fees;
        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(@last_n, 4, '0'), '-AUTO');
        INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
        VALUES (@receipt, NEW.student_id, 'deposit', @room_price, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
    END IF;
END$$
DELIMITER ;
