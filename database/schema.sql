

DROP TABLE IF EXISTS fees;

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
