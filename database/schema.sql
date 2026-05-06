DROP TABLE IF EXISTS fees;

CREATE TABLE fees (
    fee_id          INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number  VARCHAR(30) NOT NULL UNIQUE,
    student_id      INT NOT NULL,
    fee_type        ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL CHECK (amount > 0),
    due_date        DATE NOT NULL,
    is_paid         TINYINT(1) NOT NULL DEFAULT 0,
    paid_at         DATETIME NULL,
    fine_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    fine_cap        DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    fine_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_due       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at      DATETIME NULL,
    deleted_reason  VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
);
);

INSERT INTO fees
    (receipt_number, student_id, fee_type, amount, due_date, is_paid, fine_amount, total_due)
VALUES
    ('RCP-2025-0001', 101, 'rent',    500.00, '2026-06-01', 0,   0.00,  500.00),
    ('RCP-2025-0002', 102, 'deposit', 200.00, '2026-05-15', 1,   0.00,  200.00),
    ('RCP-2025-0003', 103, 'utility',  75.00, '2026-03-01', 0,  22.50,   97.50),
    ('RCP-2025-0004', 104, 'fine',     25.00, '2026-02-10', 0,   7.50,   32.50);
DROP TABLE IF EXISTS fees;

CREATE TABLE fees (
    receipt_number  VARCHAR(30) NOT NULL PRIMARY KEY,
    student_id      INT NOT NULL,
    fee_type        ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL CHECK (amount > 0),
    due_date        DATE NOT NULL,
    is_paid         TINYINT(1) NOT NULL DEFAULT 0,
    paid_at         DATETIME NULL,
    fine_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    fine_cap        DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    fine_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_due       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at      DATETIME NULL,
    deleted_reason  VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO fees
    (receipt_number, student_id, fee_type, amount, due_date, is_paid, fine_amount, total_due)
VALUES
    ('RCP-2025-0001', 101, 'rent',    500.00, '2026-06-01', 0,   0.00, 500.00),
    ('RCP-2025-0002', 102, 'deposit', 200.00, '2026-05-15', 1,   0.00, 200.00),
    ('RCP-2025-0003', 103, 'utility',  75.00, '2026-03-01', 0,  22.50, 97.50),
    ('RCP-2025-0004', 104, 'fine',     25.00, '2026-02-10', 0,   7.50, 32.50);