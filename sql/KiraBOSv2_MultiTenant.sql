-- Multi-tenant KiraBOS Database Schema
CREATE DATABASE kirabos_multitenant;
USE kirabos_multitenant;

-- Restaurants table (master table for multi-tenancy)
CREATE TABLE restaurants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL, -- URL-friendly identifier
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    tax_rate DECIMAL(5,4) DEFAULT 0.0850, -- Default 8.5%
    currency VARCHAR(3) DEFAULT 'MYR',
    timezone VARCHAR(50) DEFAULT 'Asia/kuala_Lumpur',
    is_active BOOLEAN DEFAULT TRUE,
    subscription_plan ENUM('basic', 'premium') DEFAULT 'basic',
    subscription_expires DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table (updated for multi-tenancy)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_username_per_restaurant (restaurant_id, username)
);

-- Products/Menu table (restaurant-specific)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant_category (restaurant_id, category),
    INDEX idx_restaurant_active (restaurant_id, is_active)
);

-- Orders table (restaurant-specific)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    user_id INT NOT NULL,
    order_number VARCHAR(20) NOT NULL, -- Human-readable order number
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'qr_code', 'card') DEFAULT 'cash',
    payment_received DECIMAL(10,2),
    change_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_order_number_per_restaurant (restaurant_id, order_number),
    INDEX idx_restaurant_date (restaurant_id, created_at),
    INDEX idx_restaurant_status (restaurant_id, status)
);

-- Order items table (restaurant-specific through orders)
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL, -- Store name at time of order
    product_price DECIMAL(10,2) NOT NULL, -- Store price at time of order
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_items (order_id)
);

-- Insert sample restaurants
INSERT INTO restaurants (name, slug, address, phone, email, tax_rate) VALUES 
('Demo Restaurant', 'demo-restaurant', '123 Main St, City, State', '555-0123', 'admin@demo-restaurant.com', 0.0850),
('Pizza Palace', 'pizza-palace', '456 Oak Ave, City, State', '555-0456', 'admin@pizza-palace.com', 0.0875),
('Burger House', 'burger-house', '789 Pine Rd, City, State', '555-0789', 'admin@burger-house.com', 0.0825);

-- Insert sample admin users for each restaurant
-- Demo Restaurant admin (password: admin123)
INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES 
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', 'admin@demo-restaurant.com');

-- Demo Restaurant cashier (password: cashier123)
INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES 
(1, 'cashier', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user', 'John', 'Doe', 'cashier@demo-restaurant.com');

-- Pizza Palace admin (password: admin123)
INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES 
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Pizza', 'Admin', 'admin@pizza-palace.com');

-- Pizza Palace cashier (password: cashier123)
INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES 
(2, 'cashier', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user', 'Jane', 'Smith', 'cashier@pizza-palace.com');

-- Burger House admin (password: admin123)
INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES 
(3, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Burger', 'Admin', 'admin@burger-house.com');

-- Insert sample products for Demo Restaurant
INSERT INTO products (restaurant_id, name, description, price, category, sort_order) VALUES 
(1, 'Classic Burger', 'Beef patty with lettuce, tomato, onion', 8.99, 'Food', 1),
(1, 'French Fries', 'Crispy golden fries', 3.99, 'Food', 2),
(1, 'Coca Cola', 'Cold refreshing cola', 2.99, 'Drinks', 3),
(1, 'Coffee', 'Fresh brewed coffee', 4.99, 'Drinks', 4),
(1, 'Chicken Wings', '6 piece buffalo wings', 9.99, 'Food', 5),
(1, 'Water', 'Bottled water', 1.99, 'Drinks', 6);

-- Insert sample products for Pizza Palace
INSERT INTO products (restaurant_id, name, description, price, category, sort_order) VALUES 
(2, 'Margherita Pizza', 'Tomato sauce, mozzarella, basil', 12.99, 'Food', 1),
(2, 'Pepperoni Pizza', 'Tomato sauce, mozzarella, pepperoni', 14.99, 'Food', 2),
(2, 'Caesar Salad', 'Romaine lettuce, parmesan, croutons', 7.99, 'Food', 3),
(2, 'Italian Soda', 'Sparkling water with syrup', 3.49, 'Drinks', 4),
(2, 'Garlic Bread', 'Toasted bread with garlic butter', 5.99, 'Food', 5),
(2, 'Tiramisu', 'Traditional Italian dessert', 6.99, 'Dessert', 6);

-- Insert sample products for Burger House
INSERT INTO products (restaurant_id, name, description, price, category, sort_order) VALUES 
(3, 'House Special Burger', 'Double beef with cheese and bacon', 11.99, 'Food', 1),
(3, 'Chicken Sandwich', 'Grilled chicken breast sandwich', 9.49, 'Food', 2),
(3, 'Onion Rings', 'Beer battered onion rings', 4.99, 'Food', 3),
(3, 'Milkshake', 'Vanilla, chocolate, or strawberry', 4.49, 'Drinks', 4),
(3, 'Sweet Potato Fries', 'Crispy sweet potato fries', 5.49, 'Food', 5),
(3, 'Apple Pie', 'Homemade apple pie slice', 5.99, 'Dessert', 6);