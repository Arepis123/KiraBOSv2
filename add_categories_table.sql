-- Add categories table for better category management
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    icon VARCHAR(10) DEFAULT '🍽️',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_per_restaurant (restaurant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories for existing restaurants
INSERT INTO categories (restaurant_id, name, description, icon, sort_order) 
SELECT id, 'Food', 'Main dishes and meals', '🍔', 1 FROM restaurants;

INSERT INTO categories (restaurant_id, name, description, icon, sort_order) 
SELECT id, 'Drinks', 'Beverages and refreshments', '☕', 2 FROM restaurants;

INSERT INTO categories (restaurant_id, name, description, icon, sort_order) 
SELECT id, 'Dessert', 'Sweet treats and desserts', '🍰', 3 FROM restaurants;