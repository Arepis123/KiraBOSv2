# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

KiraBOSv2 is a **multi-tenant** Point of Sale (POS) system built with PHP and MySQL, running on XAMPP. The system supports multiple restaurants with isolated data, role-based access (admin/cashier), and comprehensive activity logging.

## Architecture

### Multi-Tenancy Model
The system uses a **shared database, shared schema** architecture where all restaurants share the same tables but are isolated by `restaurant_id`. Key principles:
- Every data table (products, orders, users) includes `restaurant_id` foreign key
- All database queries MUST filter by current restaurant's ID via `Security::getRestaurantId()`
- Restaurant context is established at login and stored in `$_SESSION['restaurant_id']`
- Cross-restaurant data access is prevented at the database layer through foreign keys

### Core Components

**config.php** - Foundation classes (read this first when making changes):
- `Database` - Singleton pattern with environment-aware credentials (auto-detects production vs local)
- `Security` - Session validation, CSRF tokens, restaurant isolation, admin access control
- `Restaurant` - Restaurant data retrieval with 5-minute caching
- `ActivityLogger` - Audit trail for all significant actions (create/update/delete/login)

**login.php** - Multi-restaurant authentication:
- Handles single-restaurant login (direct) or multi-restaurant selection
- Validates username/password and verifies restaurant is active
- Sets session: user_id, restaurant_id, role, restaurant_name, restaurant_slug
- Logs all login attempts via ActivityLogger

**admin.php** - Multi-page admin interface with routing:
- Uses `?page=` parameter to load different admin modules
- Valid pages: dashboard, menu, users, logs, reports, settings
- Each page is loaded from separate `admin-{page}.php` file
- Requires admin role via `Security::requireAdmin()`

**admin-*.php modules**:
- `admin-dashboard.php` - Sales analytics, order statistics, revenue charts
- `admin-menu.php` - Product CRUD, category management, stock tracking, image uploads
- `admin-users.php` - User management for the restaurant
- `admin-logs.php` - Activity log viewer with filtering
- `admin-reports.php` - Custom reporting interface
- `admin-settings.php` - Restaurant configuration

**cashier.php** - Full-featured POS interface:
- Product browsing with category filtering
- Session-based shopping cart management
- AJAX endpoints for cart operations (add, remove, update_quantity, clear_cart)
- Dual payment methods (cash/QR) with change calculation
- Order processing with tax calculation (uses restaurant's tax_rate from DB)
- Stock management with low-stock warnings

### Database Schema

Current database: `kirabos_multitenant` (see `KiraBOSv2_MultiTenant.sql`)

Core tables:
- `restaurants` - Master table for tenants (slug, tax_rate, subscription info)
- `users` - Per-restaurant users with unique constraint on (restaurant_id, username)
- `products` - Menu items with categories, prices, stock levels, images
- `orders` - Transactions with subtotal/tax/total breakdown, payment details
- `order_items` - Line items with snapshot of product name/price at order time
- `categories` - Product categories with color coding (added via migration)
- `activity_logs` - Audit trail with old_values/new_values JSON columns
- `expenses` - Restaurant expense tracking (added via migration)

Important indexes (see `performance_indexes.sql`):
- `idx_restaurant_category` on products for filtering
- `idx_restaurant_date` on orders for date-range queries
- `idx_restaurant_active` for active product queries

### Security Architecture

**CSRF Protection**: All forms include `Security::generateCSRFToken()` and validate via `Security::validateCSRFToken()`

**Session Security**:
- Session regeneration every 30 minutes (`$_SESSION['last_regeneration']`)
- Restaurant data cached in session for 5 minutes to reduce DB calls
- Session validation on every page via `Security::validateSession()`

**Multi-Tenant Isolation**:
- `Security::getRestaurantId()` returns current restaurant from session
- `Security::belongsToCurrentRestaurant($id)` validates ownership
- All queries MUST include `WHERE restaurant_id = :restaurant_id`

**Input Sanitization**: Use `Security::sanitize()` for all user input (applies htmlspecialchars + strip_tags + trim)

**Password Hashing**: Uses `password_hash()` with bcrypt (PASSWORD_DEFAULT)

## Development Commands

### Starting the Application
```bash
# Start XAMPP services
# On Windows: Open XAMPP Control Panel and start Apache + MySQL
# On Mac/Linux: sudo /opt/lampp/lampp start

# Access application
start http://localhost/KiraBOSv2/
```

### Database Operations
```bash
# Access phpMyAdmin
start http://localhost/phpmyadmin

# Import schema
# Navigate to phpMyAdmin > Import > Choose KiraBOSv2_MultiTenant.sql

# Run migrations
# Execute SQL files in phpMyAdmin in order:
# 1. KiraBOSv2_MultiTenant.sql (base schema)
# 2. add_categories_table.sql
# 3. create_activity_logs_table.sql
# 4. create_expenses_table.sql
# 5. performance_indexes.sql
```

### Testing Utilities
Several test/debug files exist for development:
- `setup_user.php` - Create new users programmatically
- `create_sample_data.php` - Generate sample orders/products
- `setup_product_images.php` - Batch add product images
- `setup_stock_management.php` - Initialize stock levels
- `debug_*.php` - Various debugging utilities (can be deleted in production)
- `test_*.php` - Test specific features (can be deleted in production)

### Common Development Tasks

**Add a new admin page**:
1. Create `admin-{pagename}.php` with page content
2. Add 'pagename' to `$valid_pages` array in admin.php:8
3. Add navigation link in admin.php sidebar

**Add a new product category**:
Categories are dynamic from DB, but colors are managed in categories table:
```sql
INSERT INTO categories (restaurant_id, name, color_hex)
VALUES (1, 'New Category', '#FF5733');
```

**Create a database migration**:
1. Create new .sql file with descriptive name
2. Use `IF NOT EXISTS` or `ADD COLUMN IF NOT EXISTS` for safety
3. Test in development database first
4. Document in this file

**Log user activity**:
```php
ActivityLogger::log(
    'create',              // action_type: create/update/delete/login/logout/enable/disable
    'User created product', // human-readable description
    'products',            // table_name (optional)
    $product_id,           // record_id (optional)
    null,                  // old_values (optional)
    ['name' => 'Pizza']    // new_values (optional)
);
```

## Technical Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+ with InnoDB
- **Frontend**: TailwindCSS (CDN), Vanilla JavaScript
- **Server**: Apache (via XAMPP)
- **Session Storage**: PHP native sessions (file-based)
- **Image Storage**: Local filesystem at `uploads/products/`

No package managers, build tools, or additional dependencies required.

## Environment Configuration

Database credentials auto-detect in `config.php`:
- **Local**: hostname != 'localhost' → `root` user, empty password
- **Production**: hostname == 'localhost' → `test` user, `357u1oLM#` password
- Database name: `kirabos_multitenant` (both environments)

To force environment:
```php
// In config.php setDatabaseCredentials()
$isProduction = true; // or false
```

## Multi-Restaurant Setup

**Register new restaurant**:
1. Navigate to `register_restaurant.php` (linked from login page)
2. Enter restaurant details (name, slug must be unique)
3. System creates restaurant record and admin user
4. Admin can then add products, users, etc.

**Restaurant selection flow**:
1. User enters username/password at `login.php`
2. If username exists in multiple restaurants → redirect to `select_restaurant.php`
3. User selects their restaurant → session established → redirect based on role

**Demo restaurants** (from KiraBOSv2_MultiTenant.sql):
- Demo Restaurant (slug: demo-restaurant)
- Pizza Palace (slug: pizza-palace)
- Burger House (slug: burger-house)

Default credentials: `admin / admin123` or `cashier / cashier123` (same for all restaurants)