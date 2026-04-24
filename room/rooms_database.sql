CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,

    room_number VARCHAR(10) NOT NULL UNIQUE,

    room_type ENUM('single', 'double', 'triple') NOT NULL,

    capacity INT NOT NULL CHECK (capacity > 0),

    price_per_month DECIMAL(10,2) NOT NULL CHECK (price_per_month >= 0),

    bathroom_type ENUM('ensuite', 'private', 'shared') NOT NULL,

    available_from DATE
);