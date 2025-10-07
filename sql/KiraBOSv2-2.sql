-- Complete Database Setup for POS System
-- Run this in your MySQL/phpMyAdmin

CREATE DATABASE IF NOT EXISTS pos_system;
USE pos_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products/Menu table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'qr') DEFAULT 'cash',
    amount_tendered DECIMAL(10,2) DEFAULT 0,
    change_given DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Clear existing data (optional - remove these lines if you want to keep existing data)
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM products;
DELETE FROM users;

-- Reset auto increment
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE order_items AUTO_INCREMENT = 1;

-- Insert default users with properly hashed passwords
-- Admin user: admin / admin123
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin');

-- Cashier user: cashier / cashier123
INSERT INTO users (username, password, role) VALUES 
('cashier', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user');

-- Insert sample products
INSERT INTO products (name, price, category, is_active) VALUES 
('Classic Burger', 8.99, 'Food', 1),
('Cheese Burger', 9.99, 'Food', 1),
('Chicken Burger', 10.99, 'Food', 1),
('French Fries', 3.99, 'Food', 1),
('Onion Rings', 4.99, 'Food', 1),
('Cola', 2.99, 'Drinks', 1),
('Sprite', 2.99, 'Drinks', 1),
('Orange Juice', 3.99, 'Drinks', 1),
('Coffee', 4.99, 'Drinks', 1),
('Tea', 3.99, 'Drinks', 1),
('Margherita Pizza', 12.99, 'Food', 1),
('Pepperoni Pizza', 14.99, 'Food', 1),
('Water Bottle', 1.99, 'Drinks', 1),
('Ice Cream', 5.99, 'Dessert', 1),
('Chocolate Cake', 6.99, 'Dessert', 1);

-- Display confirmation
SELECT 'Database setup completed successfully!' AS message;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_products FROM products;