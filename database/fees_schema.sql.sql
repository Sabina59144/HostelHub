

CREATE TABLE IF NOT EXISTS fees (
    -- ─────────────────────────────────────────────────────────────────────
    -- PRIMARY KEY & IDENTIFIER
    -- ─────────────────────────────────────────────────────────────────────
    
    -- receipt_number: Unique identifier for this fee record
    -- Format: RCP-YYYY-NNNN (e.g., RCP-2025-0001)
    -- Max length: 30 characters (allows for custom suffixes like -DEP, -RENT)
    -- PRIMARY KEY ensures no duplicates
    receipt_number VARCHAR(30) NOT NULL,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- FOREIGN KEY RELATIONSHIP
    -- ─────────────────────────────────────────────────────────────────────
    
    -- student_id: Links this fee to a student record
    -- Type: INT (foreign key to students.student_id)
    -- NOT NULL because every fee must belong to a student
    -- Constraint: FOREIGN KEY with CASCADE UPDATE, RESTRICT DELETE
    --   * ON UPDATE CASCADE: If student ID changes, update all their fees
    --   * ON DELETE RESTRICT: Can't delete student with unpaid fees
    student_id INT NOT NULL,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- FEE DETAILS
    -- ─────────────────────────────────────────────────────────────────────
    
    -- fee_type: Categorizes what the fee is for
    -- Type: ENUM (fixed list of values)
    -- Values:
    --   'rent'    - Monthly accommodation fee
    --   'deposit' - Security deposit (returned at checkout)
    --   'utility' - Shared utilities (water, electricity, etc.)
    --   'fine'    - Late payment penalty or damages
    --   'laundry' - Shared laundry facilities
    --   'other'   - Miscellaneous charges
    -- Used for filtering and reporting
    fee_type ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    
    -- amount: The fee amount in Danish Kroner (DKK)
    -- Type: DECIMAL(10,2) allows up to 99,999,999.99
    -- Format: 10 digits total, 2 decimal places
    -- NOT NULL because every fee must have an amount
    -- Validation: must be > 0 in application layer
    amount DECIMAL(10,2) NOT NULL,
    
    -- due_date: When the payment is due (or was due)
    -- Type: DATE (format: YYYY-MM-DD)
    -- Used for:
    --   * Calculating if fee is overdue
    --   * Fine calculation (days past due_date)
    --   * Sorting and filtering fees
    due_date DATE NOT NULL,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- PAYMENT TRACKING
    -- ─────────────────────────────────────────────────────────────────────
    
    -- is_paid: Payment status flag
    -- Type: TINYINT(1) (0 = false, 1 = true)
    -- Default: 0 (unpaid when created)
    -- Used for:
    --   * Filtering (paid vs. unpaid)
    --   * AJAX toggle in UI
    --   * Determining if fee is overdue
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    
    -- paid_at: Timestamp when payment was recorded
    -- Type: TIMESTAMP NULL
    -- Default: NULL (null when unpaid)
    -- When set: Records the exact time payment was marked
    -- Used for:
    --   * Showing when fee was cleared
    --   * Audit trail
    --   * Late payment reporting
    paid_at TIMESTAMP NULL,
    
    -- payment_method: How the fee was paid
    -- Type: VARCHAR(20) (allows flexibility for various payment types)
    -- Common values:
    --   'cash'          - Physical cash payment
    --   'bank_transfer' - Bank account transfer
    --   'card'          - Credit/debit card
    --   'mobile'        - Mobile payment service
    --   'check'         - Cheque payment
    --   NULL            - Not yet specified
    -- Optional field (can be NULL)
    payment_method VARCHAR(20) NULL,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- FINE POLICY
    -- ─────────────────────────────────────────────────────────────────────
    
    -- fine_rate: Daily fine amount for overdue payments
    -- Type: DECIMAL(5,2) (up to 999.99)
    -- Default: 0.50 (50 øre per day)
    -- Formula: days_overdue × fine_rate = total_fine
    -- Example: 10 days overdue × 0.50 = 5.00 DKK fine
    -- Allows per-fee override if specific rates needed
    fine_rate DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- SOFT DELETE (Data Preservation)
    -- ─────────────────────────────────────────────────────────────────────
    
    -- is_active: Soft delete flag (logical deletion)
    -- Type: TINYINT(1) (0 = deleted/archived, 1 = active)
    -- Default: 1 (active)
    -- Benefit: Records never truly deleted, can be recovered if needed
    -- Used in WHERE clauses: WHERE is_active = 1
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    
    -- deleted_at: When this fee was soft-deleted
    -- Type: DATETIME NULL
    -- Default: NULL (null until actually deleted)
    -- Set to: NOW() when record is soft-deleted
    -- Preserved for audit trail
    deleted_at DATETIME NULL DEFAULT NULL,
    
    -- deleted_reason: Why this fee was deleted/archived
    -- Type: VARCHAR(255) (up to 255 characters)
    -- Example values:
    --   'Entered in error'
    --   'Duplicate entry'
    --   'Student transferred'
    --   'Fee waived by admin'
    -- Default: NULL
    -- Provides context for archival
    deleted_reason VARCHAR(255) NULL DEFAULT NULL,
    
    
    -- ─────────────────────────────────────────────────────────────────────
    -- AUDIT TIMESTAMPS
    -- ─────────────────────────────────────────────────────────────────────
    
    -- created_at: When this fee record was created
    -- Type: TIMESTAMP (automatically set to NOW())
    -- Default: CURRENT_TIMESTAMP
    -- Immutable - never changes after creation
    -- Used for:
    --   * Sorting by newest/oldest
    --   * Audit trail
    --   * Historical analysis
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- updated_at: When this fee record was last modified
    -- Type: TIMESTAMP (automatically updated)
    -- Default: CURRENT_TIMESTAMP
    -- Updated: Automatically set to NOW() ON UPDATE
    -- Tracks: When any field (except deleted_reason) was changed
    -- Used for:
    --   * Knowing when record was last touched
    --   * Audit trail of changes
    --   * Sorting by recently modified
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ─────────────────────────────────────────────────────────────────────
    -- CONSTRAINTS
    -- ─────────────────────────────────────────────────────────────────────
    
    -- PRIMARY KEY: Ensures receipt_number is unique
    PRIMARY KEY (receipt_number),
    
    -- FOREIGN KEY: Links to students table
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE     -- If student ID changes, update all their fees
        ON DELETE RESTRICT    -- Can't delete student with fees
        
) ENGINE=InnoDB                      -- InnoDB for transactions & constraints
  DEFAULT CHARSET=utf8mb4            -- UTF-8 encoding
  COLLATE utf8mb4_unicode_ci;        -- Case-insensitive collation

-- ────────────────────────────────────────────────────────────────────────
-- INDEXES: Optimize query performance
-- ────────────────────────────────────────────────────────────────────────

-- Index 1: Student filtering
-- Used when: Looking up all fees for a specific student
-- Query: SELECT * FROM fees WHERE student_id = ?
-- Speed: With index ~O(log n), without ~O(n)
INDEX idx_student_id (student_id),

-- Index 2: Payment status filtering
-- Used when: Finding all paid/unpaid fees
-- Query: SELECT * FROM fees WHERE is_paid = 1
-- Speed: Dramatically faster for large tables
INDEX idx_is_paid (is_paid),

-- Index 3: Date range filtering
-- Used when: Finding overdue fees or sorting by date
-- Query: SELECT * FROM fees WHERE due_date < CURDATE()
-- Speed: Essential for date-based queries
INDEX idx_due_date (due_date),

-- Index 4: Fee type filtering
-- Used when: Filtering by rent/deposit/utility/etc
-- Query: SELECT * FROM fees WHERE fee_type = 'rent'
-- Speed: Improves filter performance
INDEX idx_fee_type (fee_type);


-- ════════════════════════════════════════════════════════════════════════════
-- TRIGGER 1: Auto-create fees when room is assigned to student
-- ════════════════════════════════════════════════════════════════════════════
--
-- Purpose: Automatically generate deposit + first rent fee when a student
--          is assigned to a hostel room
--
-- Trigger Point: AFTER UPDATE on students table
-- Condition: room_id changes from NULL to a value (first-time assignment)
--
-- What it does:
--   1. Looks up the room price
--   2. Generates a unique receipt number
--   3. Creates two fees:
--      - Deposit (due in 7 days)
--      - First Rent (due in 30 days)
--
-- Example:
--   UPDATE students SET room_id = 5 WHERE student_id = 1;
--   → Automatically creates RCP-2025-0001-DEP and RCP-2025-0001-RENT
--

DROP TRIGGER IF EXISTS after_student_room_assigned;
DELIMITER $$

CREATE TRIGGER after_student_room_assigned
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    -- Only run if room_id is being SET for the first time
    IF OLD.room_id IS NULL AND NEW.room_id IS NOT NULL THEN
        
        -- Step 1: Get the monthly price of the assigned room
        SET @room_price = 0;
        SELECT price_per_month 
        INTO @room_price
        FROM rooms
        WHERE room_id = NEW.room_id;

        -- Step 2: Generate unique sequence number
        -- Gets the highest existing number and adds 1
        SET @last_n = 1;
        SELECT COALESCE(
            CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED),
            0
        ) + 1
        INTO @last_n
        FROM fees;

        -- Step 3: Create base receipt number (without suffix)
        SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(@last_n, 4, '0'));

        -- Step 4: Create DEPOSIT fee
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            CONCAT(@receipt, '-DEP'),        -- Receipt: RCP-2025-0001-DEP
            NEW.student_id,                  -- For this student
            'deposit',                        -- Fee type
            @room_price,                      -- Amount = room price
            DATE_ADD(CURDATE(), INTERVAL 7 DAY),  -- Due in 7 days
            0,                                -- Not yet paid
            NULL                              -- No payment timestamp
        );

        -- Step 5: Create FIRST RENT fee
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            CONCAT(@receipt, '-RENT'),       -- Receipt: RCP-2025-0001-RENT
            NEW.student_id,                  -- For this student
            'rent',                           -- Fee type
            @room_price,                      -- Amount = room price
            DATE_ADD(CURDATE(), INTERVAL 30 DAY),  -- Due in 30 days
            0,                                -- Not yet paid
            NULL                              -- No payment timestamp
        );
    END IF;
END$$
DELIMITER ;


-- ════════════════════════════════════════════════════════════════════════════
-- TRIGGER 2: Auto-create deposit when new student is registered with room
-- ════════════════════════════════════════════════════════════════════════════
--
-- Purpose: Automatically create a deposit fee when a new student is
--          registered and already has a room assigned
--
-- Trigger Point: AFTER INSERT on students table
-- Condition: New student has room_id set (not NULL)
--
-- What it does:
--   1. Gets the room price
--   2. Generates receipt number
--   3. Creates one deposit fee (due in 14 days)
--
-- Example:
--   INSERT INTO students (full_name, student_number, room_id)
--   VALUES ('John Doe', 'ST12345', 5);
--   → Automatically creates RCP-2025-0002-AUTO (deposit fee)
--

DROP TRIGGER IF EXISTS after_student_insert;
DELIMITER $$

CREATE TRIGGER after_student_insert
AFTER INSERT ON students
FOR EACH ROW
BEGIN
    -- Only run if room_id is provided in INSERT
    IF NEW.room_id IS NOT NULL THEN
        
        -- Step 1: Get room price
        SET @room_price = 0;
        SELECT price_per_month
        INTO @room_price
        FROM rooms
        WHERE room_id = NEW.room_id;

        -- Step 2: Generate sequence number
        SET @last_n = 1;
        SELECT COALESCE(
            CAST(SUBSTRING_INDEX(MAX(receipt_number), '-', -1) AS UNSIGNED),
            0
        ) + 1
        INTO @last_n
        FROM fees;

        -- Step 3: Create receipt number with -AUTO suffix
        SET @receipt = CONCAT(
            'RCP-',
            YEAR(CURDATE()),
            '-',
            LPAD(@last_n, 4, '0'),
            '-AUTO'  -- -AUTO suffix indicates auto-generated on insert
        );

        -- Step 4: Create DEPOSIT fee
        INSERT INTO fees (
            receipt_number,
            student_id,
            fee_type,
            amount,
            due_date,
            is_paid,
            paid_at
        ) VALUES (
            @receipt,                         -- RCP-2025-0002-AUTO
            NEW.student_id,                   -- For this new student
            'deposit',                        -- Deposit fee
            @room_price,                      -- Amount = room price
            DATE_ADD(CURDATE(), INTERVAL 14 DAY),  -- Due in 14 days
            0,                                -- Not yet paid
            NULL                              -- No payment timestamp
        );
    END IF;
END$$
DELIMITER ;


-- ════════════════════════════════════════════════════════════════════════════
-- SAMPLE TEST DATA
-- For development and testing only
-- ════════════════════════════════════════════════════════════════════════════

-- Clear existing test data first (prevent duplicates)
DELETE FROM fees 
WHERE student_id IN (
    SELECT student_id FROM students 
    WHERE full_name LIKE 'Test %' OR full_name LIKE 'Sample %'
);

-- Insert sample fees for testing various scenarios
-- Assumes students table has IDs 1-5

-- Student 1: Mixed payment statuses (some paid, some pending, some overdue)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0001-RENT', 1, 'rent', 450.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 0, NULL, NULL),  -- Overdue 10 days
('RCP-2026-0002-DEP', 1, 'deposit', 450.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'bank_transfer'),  -- Paid
('RCP-2026-0003-UTIL', 1, 'utility', 75.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 0, NULL, NULL);  -- Pending

-- Student 2: All paid (good payer)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0004-RENT', 2, 'rent', 500.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 1, DATE_SUB(NOW(), INTERVAL 20 DAY), 'card'),
('RCP-2026-0005-DEP', 2, 'deposit', 500.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 1, DATE_SUB(NOW(), INTERVAL 15 DAY), 'bank_transfer');

-- Student 3: All unpaid (potential issue)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0006-RENT', 3, 'rent', 400.00, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 0, NULL, NULL),
('RCP-2026-0007-DEP', 3, 'deposit', 400.00, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 0, NULL, NULL),
('RCP-2026-0008-LAUN', 3, 'laundry', 25.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, NULL);

-- Student 4: Overdue (needs fine calculation)
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0009-RENT', 4, 'rent', 475.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 0, NULL, NULL),
('RCP-2026-0010-UTIL', 4, 'utility', 85.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 0, NULL, NULL);

-- Student 5: Recently paid and some pending
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0011-RENT', 5, 'rent', 425.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1, NOW(), 'card'),
('RCP-2026-0012-DEP', 5, 'deposit', 425.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, NOW(), 'card'),
('RCP-2026-0013-FINE', 5, 'fine', 10.50, CURDATE(), 0, NULL, NULL);

-- Additional future fees
INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, payment_method) VALUES
('RCP-2026-0014-RENT', 1, 'rent', 450.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 0, NULL, NULL),
('RCP-2026-0015-UTIL', 2, 'utility', 80.00, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 0, NULL, NULL);


-- ════════════════════════════════════════════════════════════════════════════
-- VERIFICATION QUERIES
-- Use these to verify the schema and data are correct
-- ════════════════════════════════════════════════════════════════════════════

-- Verify overall fee summary
SELECT 
    COUNT(*) as total_fees,                                          -- Total fee records
    SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_count,    -- Count of paid
    SUM(CASE WHEN is_paid = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,  -- Overdue
    SUM(CASE WHEN is_paid = 0 AND due_date >= CURDATE() THEN 1 ELSE 0 END) as unpaid_count,  -- Pending
    SUM(amount) as total_amount,                                     -- Total billed
    SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) as paid_amount  -- Total paid
FROM fees
WHERE is_active = 1;

-- Verify by student (summary of each student's fees)
SELECT 
    s.student_id,
    s.full_name,
    COUNT(f.receipt_number) as fee_count,                              -- How many fees
    SUM(CASE WHEN f.is_paid = 1 THEN 1 ELSE 0 END) as paid_count,    -- How many paid
    SUM(f.amount) as total_amount,                                     -- Total owed
    SUM(CASE WHEN f.is_paid = 1 THEN f.amount ELSE 0 END) as paid_amount  -- Total paid
FROM students s
LEFT JOIN fees f ON s.student_id = f.student_id AND f.is_active = 1
GROUP BY s.student_id, s.full_name
ORDER BY s.full_name;

-- End of schema file
