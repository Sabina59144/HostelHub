

-- ── Fees (your complete version) ────────────────────────────
CREATE TABLE IF NOT EXISTS fees (
    receipt_number VARCHAR(30)   NOT NULL,
    student_id     INT           NOT NULL,
    fee_type       ENUM('rent','deposit','utility','fine','laundry','other') NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    due_date       DATE          NOT NULL,
    is_paid        TINYINT(1)    NOT NULL DEFAULT 0,   -- 0 = unpaid, 1 = paid
    paid_at        DATETIME      NULL,
    payment_method VARCHAR(20)   NULL,
    fine_rate      DECIMAL(5,2)  NOT NULL DEFAULT 0.50,
    fine_cap       DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    fine_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_due      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    deleted_at     DATETIME      NULL,
    deleted_reason VARCHAR(255)  NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_number),
    CONSTRAINT fk_fee_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active) VALUES
('RCP-2026-0001', 100, 'rent',    500.00, '2026-06-01', 0, NULL,  0.50, 15.00,  0.00, 500.00, 1),
('RCP-2026-0002', 101, 'deposit', 200.00, '2026-05-15', 1, NOW(), 0.50, 15.00,  0.00, 200.00, 1),
('RCP-2026-0003', 102, 'utility',  75.00, '2026-03-01', 0, NULL,  0.50, 15.00, 15.00,  90.00, 1),
('RCP-2026-0004', 103, 'fine',     25.00, '2026-02-10', 0, NULL,  0.50, 15.00,  7.50,  32.50, 1),
('RCP-2026-0005', 104, 'laundry',  30.00, '2026-06-10', 0, NULL,  0.50, 15.00,  0.00,  30.00, 1),
('RCP-2026-0006', 105, 'rent',    500.00, '2026-06-01', 1, NOW(), 0.50, 15.00,  0.00, 500.00, 1),
('RCP-2026-0007', 106, 'other',    40.00, '2026-04-20', 0, NULL,  0.50, 15.00, 10.00,  50.00, 1),
('RCP-2026-0008', 107, 'deposit', 200.00, '2026-05-20', 0, NULL,  0.50, 15.00,  0.00, 200.00, 1);

