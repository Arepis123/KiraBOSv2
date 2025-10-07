-- Basic expenses table creation for KiraBOS
-- Run this in phpMyAdmin or your MySQL client

CREATE TABLE IF NOT EXISTS expenses (
    id INT(11) NOT NULL AUTO_INCREMENT,
    restaurant_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_restaurant_date (restaurant_id, created_at),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;