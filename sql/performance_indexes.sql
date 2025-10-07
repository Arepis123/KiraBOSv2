-- Performance Optimization Indexes for KiraBOSv2
-- Run these indexes to dramatically improve query performance

-- Critical indexes for activity_logs table (most important)
-- Index for restaurant-specific log queries with date ordering
ALTER TABLE activity_logs ADD INDEX idx_restaurant_created_at (restaurant_id, created_at DESC);

-- Index for filtered log queries (action type + user filtering)
ALTER TABLE activity_logs ADD INDEX idx_restaurant_action_user (restaurant_id, action_type, user_id);

-- Index for user-specific activity queries
ALTER TABLE activity_logs ADD INDEX idx_user_created_at (user_id, created_at DESC);

-- Indexes for orders table (improves dashboard and reporting)
-- Index for restaurant orders with date ordering
ALTER TABLE orders ADD INDEX idx_restaurant_date_status (restaurant_id, created_at DESC, status);

-- Index for user orders (cashier activity tracking)
ALTER TABLE orders ADD INDEX idx_user_orders (user_id, created_at DESC, restaurant_id);

-- Index for completed orders (reporting queries)
ALTER TABLE orders ADD INDEX idx_restaurant_completed (restaurant_id, status, created_at DESC);

-- Indexes for order_items table (improves order details and reporting)
-- Index for product sales analysis
ALTER TABLE order_items ADD INDEX idx_product_order (product_id, order_id);

-- Index for order item lookups
ALTER TABLE order_items ADD INDEX idx_order_items (order_id, product_id);

-- Indexes for products table (improves cashier interface)
-- Index for active products by category
ALTER TABLE products ADD INDEX idx_restaurant_category_active (restaurant_id, category, is_active);

-- Index for product ordering in cashier view
ALTER TABLE products ADD INDEX idx_restaurant_active_sort (restaurant_id, is_active, sort_order, name);

-- Indexes for categories table (improves admin interface)
-- Index for active categories with sort order
ALTER TABLE categories ADD INDEX idx_restaurant_active_sort (restaurant_id, is_active, sort_order);

-- Index for category lookups
ALTER TABLE categories ADD INDEX idx_restaurant_name (restaurant_id, name);

-- Indexes for users table (improves authentication and user management)
-- Index for login queries
ALTER TABLE users ADD INDEX idx_username_restaurant (username, restaurant_id, is_active);

-- Index for restaurant user listings
ALTER TABLE users ADD INDEX idx_restaurant_users (restaurant_id, is_active, created_at DESC);

-- Indexes for restaurants table (improves multi-tenant performance)
-- Index for slug lookups
ALTER TABLE restaurants ADD INDEX idx_slug_active (slug, is_active);

-- Index for active restaurants
ALTER TABLE restaurants ADD INDEX idx_active_created (is_active, created_at DESC);

-- Show index creation progress
SELECT 'Performance indexes created successfully!' as status;

-- Optional: Analyze tables after index creation for better query optimization
-- ANALYZE TABLE activity_logs, orders, order_items, products, categories, users, restaurants;