CREATE TABLE IF NOT EXISTS students (
    student_id      INT          NOT NULL AUTO_INCREMENT,
    student_number  VARCHAR(20)  NOT NULL,           -- e.g. STU-2024-001
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    date_of_birth   DATE         DEFAULT NULL,
    room_id         INT          DEFAULT NULL,
    status          BOOLEAN      NOT NULL DEFAULT 1,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_number (student_number),
    UNIQUE KEY uq_student_email  (email),
    CONSTRAINT fk_student_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;