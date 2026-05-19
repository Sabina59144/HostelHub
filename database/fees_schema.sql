USE hostelhub;

-- ========================================
-- CORRECTED FEES TABLE SCHEMA
-- ========================================
CREATE TABLE IF NOT EXISTS fees (
    receipt_number VARCHAR(30) NOT NULL,
    student_id INT NOT NULL,
    fee_type ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    paid_at TIMESTAMP NULL,
    payment_method VARCHAR(20) NULL,
    fine_rate DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    fine_cap DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL DEFAULT NULL,
    deleted_reason VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (receipt_number),
    INDEX idx_student_id (student_id),
    INDEX idx_is_paid (is_paid),
    INDEX idx_due_date (due_date),
    INDEX idx_fee_type (fee_type),
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ========================================
-- AUTO CREATE FEES WHEN ROOM IS ASSIGNED
-- ========================================
DROP TRIGGER IF EXISTS after_student_room_assigned;
DELIMITER $$

CREATE TRIGGER after_student_room_assigned
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    IF OLD.room_id IS NULL AND NEW.room_id IS NOT NULL THEN
        
        SET @room_price = 0;
        SELECT price_per_month 
        INTO @room_price
        FROM rooms
        WHERE room_id = NEW.room_id;

        SET @last_n = 1;
        SELECT COALESCE(
            CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED),
            0
        ) + 1
        INTO @last_n
        FROM fees;

        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(@last_n, 4, '0'));

        -- Deposit
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            CONCAT(@receipt, '-DEP'),
            NEW.student_id,
            'deposit',
            @room_price,
            DATE_ADD(CURDATE(), INTERVAL 7 DAY),
            0,
            NULL
        );

        -- First Rent
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            CONCAT(@receipt, '-RENT'),
            NEW.student_id,
            'rent',
            @room_price,
            DATE_ADD(CURDATE(), INTERVAL 30 DAY),
            0,
            NULL
        );
    END IF;
END$$
DELIMITER ;


-- ========================================
-- AUTO CREATE DEPOSIT WHEN NEW STUDENT IS INSERTED
-- ========================================
DROP TRIGGER IF EXISTS after_student_insert;
DELIMITER $$

CREATE TRIGGER after_student_insert
AFTER INSERT ON students
FOR EACH ROW
BEGIN
    IF NEW.room_id IS NOT NULL THEN
        
        SET @room_price = 0;
        SELECT price_per_month
        INTO @room_price
        FROM rooms
        WHERE room_id = NEW.room_id;

        SET @last_n = 1;
        SELECT COALESCE(
            CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED),
            0
        ) + 1
        INTO @last_n
        FROM fees;

        SET @receipt = CONCAT(
            'RCP-',
            YEAR(CURDATE()),
            '-',
            LPAD(@last_n, 4, '0'),
            '-AUTO'
        );

        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            @receipt,
            NEW.student_id,
            'deposit',
            @room_price,
            DATE_ADD(CURDATE(), INTERVAL 14 DAY),
            0,
            NULL
        );
    END IF;
END$$
DELIMITER ;


-- ========================================
-- SAMPLE TEST DATA (for demonstration)
-- ========================================

-- Clear existing sample data
DELETE FROM fees WHERE student_id IN (SELECT student_id FROM students WHERE full_name LIKE 'Test %' OR full_name LIKE 'Sample %');

-- Insert sample fees (assuming students table has IDs 1-5)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
-- Student 1: Mixed statuses
('RCP-2026-0001-RENT', 1, 'rent', 450.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 0, NULL, NULL),
('RCP-2026-0002-DEP', 1, 'deposit', 450.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'bank_transfer'),
('RCP-2026-0003-UTIL', 1, 'utility', 75.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 0, NULL, NULL),

-- Student 2: All paid
('RCP-2026-0004-RENT', 2, 'rent', 500.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 1, DATE_SUB(NOW(), INTERVAL 20 DAY), 'card'),
('RCP-2026-0005-DEP', 2, 'deposit', 500.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 1, DATE_SUB(NOW(), INTERVAL 15 DAY), 'bank_transfer'),

-- Student 3: All unpaid
('RCP-2026-0006-RENT', 3, 'rent', 400.00, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 0, NULL, NULL),
('RCP-2026-0007-DEP', 3, 'deposit', 400.00, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 0, NULL, NULL),
('RCP-2026-0008-LAUN', 3, 'laundry', 25.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, NULL),

-- Student 4: Overdue (needs fine)
('RCP-2026-0009-RENT', 4, 'rent', 475.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 0, NULL, NULL),
('RCP-2026-0010-UTIL', 4, 'utility', 85.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 0, NULL, NULL),

-- Student 5: Recently paid
('RCP-2026-0011-RENT', 5, 'rent', 425.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1, NOW(), 'card'),
('RCP-2026-0012-DEP', 5, 'deposit', 425.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, NOW(), 'card'),
('RCP-2026-0013-FINE', 5, 'fine', 10.50, CURDATE(), 0, NULL, NULL),

-- Student 1: Additional future fees
('RCP-2026-0014-RENT', 1, 'rent', 450.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 0, NULL, NULL),
('RCP-2026-0015-UTIL', 2, 'utility', 80.00, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 0, NULL, NULL);

-- ========================================
-- VERIFICATION QUERIES
-- ========================================
-- Check fee summary
SELECT 
    COUNT(*) as total_fees,
    SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN is_paid = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN is_paid = 0 AND due_date >= CURDATE() THEN 1 ELSE 0 END) as unpaid_count,
    SUM(amount) as total_amount,
    SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) as paid_amount
FROM fees
WHERE is_active = 1;

-- Check by student
SELECT 
    s.student_id,
    s.full_name,
    COUNT(f.receipt_number) as fee_count,
    SUM(CASE WHEN f.is_paid = 1 THEN 1 ELSE 0 END) as paid_count,
    SUM(f.amount) as total_amount,
    SUM(CASE WHEN f.is_paid = 1 THEN f.amount ELSE 0 END) as paid_amount
FROM students s
LEFT JOIN fees f ON s.student_id = f.student_id AND f.is_active = 1
GROUP BY s.student_id, s.full_name
ORDER BY s.full_name;
