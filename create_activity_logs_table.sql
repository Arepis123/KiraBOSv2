-- Activity Logs table for audit trail
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    user_id INT,
    username VARCHAR(50),
    action_type ENUM('create', 'update', 'delete', 'login', 'logout', 'enable', 'disable') NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    description TEXT NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_restaurant_created (restaurant_id, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_created (action_type, created_at)
);