-- Create expenses table for KiraBOS expense tracking system
-- This table stores all expense records for restaurants

CREATE TABLE IF NOT EXISTS expenses (
    id INT(11) NOT NULL AUTO_INCREMENT,
    restaurant_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    
    -- Foreign key constraints (if your tables support them)
    -- Uncomment these lines if you have proper foreign key relationships
    -- FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    -- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for better performance
    INDEX idx_restaurant_date (restaurant_id, created_at),
    INDEX idx_category (category),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data for testing (optional)
-- You can remove this section if you don't want sample data
INSERT INTO expenses (restaurant_id, user_id, category, amount, description, created_at) VALUES
(1, 1, 'ingredients', 25.50, 'Emergency tomatoes purchase', NOW() - INTERVAL 2 HOUR),
(1, 1, 'supplies', 15.75, 'Cleaning supplies and napkins', NOW() - INTERVAL 1 HOUR),
(1, 1, 'maintenance', 8.00, 'Light bulb replacement', NOW() - INTERVAL 30 MINUTE),
(1, 2, 'delivery', 12.50, 'Gas for delivery vehicle', NOW() - INTERVAL 15 MINUTE),
(1, 2, 'utilities', 50.00, 'Electricity top-up', NOW() - INTERVAL 5 MINUTE);

-- Create a view for easier expense reporting (optional but useful)
CREATE OR REPLACE VIEW expense_summary AS
SELECT 
    e.id,
    e.restaurant_id,
    e.category,
    e.amount,
    e.description,
    e.created_at,
    u.username as added_by,
    DATE(e.created_at) as expense_date,
    TIME(e.created_at) as expense_time
FROM expenses e
LEFT JOIN users u ON e.user_id = u.id
ORDER BY e.created_at DESC;

-- Show table structure
DESCRIBE expenses;