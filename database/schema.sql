USE hostelhub;

DROP TABLE IF EXISTS fees;
DROP TABLE IF EXISTS students;

CREATE TABLE students (
    student_id     INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    date_of_birth  DATE NOT NULL,
    room_id        INT NULL,
    status         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO students (student_id, student_number, full_name, email, date_of_birth, room_id, status) VALUES
(100, 'STU-2024-001', 'James Carter', 'james.carter@student.edu', '2001-03-15', NULL, 1),
(101, 'STU-2024-002', 'Priya Sharma', 'priya.sharma@student.edu', '2002-07-22', NULL, 1),
(102, 'STU-2024-003', 'Oliver Bennett', 'oliver.bennett@student.edu', '2001-11-05', NULL, 1),
(103, 'STU-2024-004', 'Amara Osei', 'amara.osei@student.edu', '2003-01-18', NULL, 1),
(104, 'STU-2024-005', 'Lucas Rivera', 'lucas.rivera@student.edu', '2002-09-30', NULL, 1),
(105, 'STU-2024-006', 'Sophie Walsh', 'sophie.walsh@student.edu', '2001-06-12', NULL, 1),
(106, 'STU-2024-007', 'Daniel Mwangi', 'daniel.mwangi@student.edu', '2003-04-25', NULL, 1),
(107, 'STU-2024-008', 'Emma Thompson', 'emma.thompson@student.edu', '2002-08-09', NULL, 1);

CREATE TABLE fees (
    receipt_number VARCHAR(30) NOT NULL PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    paid_at DATETIME NULL,
    fine_rate DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    fine_cap DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    deleted_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 
INSERT INTO fees (
    receipt_number,
    student_id,
    fee_type,
    amount,
    due_date,
    is_paid,
    paid_at,
    fine_rate,
    fine_cap,
    fine_amount,
    total_due,
    is_active
) VALUES
('RCP-2026-0001', 100, 'rent',    500.00, '2026-06-01', 0, NULL, 0.50, 15.00, 0.00, 500.00, 1),
('RCP-2026-0002', 101, 'deposit', 200.00, '2026-05-15', 1, NOW(), 0.50, 15.00, 0.00, 200.00, 1),
('RCP-2026-0003', 102, 'utility', 75.00, '2026-03-01', 0, NULL, 0.50, 15.00, 15.00, 90.00, 1),
('RCP-2026-0004', 103, 'fine',    25.00, '2026-02-10', 0, NULL, 0.50, 15.00, 7.50, 32.50, 1),
('RCP-2026-0005', 104, 'laundry', 30.00, '2026-06-10', 0, NULL, 0.50, 15.00, 0.00, 30.00, 1),
('RCP-2026-0006', 105, 'rent',   500.00, '2026-06-01', 1, NOW(), 0.50, 15.00, 0.00, 500.00, 1),
('RCP-2026-0007', 106, 'other',   40.00, '2026-04-20', 0, NULL, 0.50, 15.00, 10.00, 50.00, 1),
('RCP-2026-0008', 107, 'deposit', 200.00, '2026-05-20', 0, NULL, 0.50, 15.00, 0.00, 200.00, 1);