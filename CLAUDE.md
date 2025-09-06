# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

KiraBOSv2 is a Point of Sale (POS) system built with PHP and MySQL, running on XAMPP. The system provides role-based access with admin and cashier functionality.

## Architecture

### Core Components

- **config.php**: Database connection and security functions (Database and Security classes)
- **login.php**: Authentication system with role-based redirection
- **admin.php**: Admin dashboard for product management and sales analytics
- **cashier.php**: POS interface for order processing and payment handling
- **logout.php**: Session cleanup and logout functionality

### Database Schema

The system uses MySQL with the following main tables:
- `users` - User accounts with role-based permissions (admin/user)
- `products` - Menu items with categories (Food/Drinks/Dessert)
- `orders` - Transaction records with payment details
- `order_items` - Line items linking orders to products

### Security Features

- CSRF token validation on all forms
- Session management with periodic regeneration
- SQL injection protection via prepared statements
- Input sanitization through Security::sanitize()
- Password hashing using PHP's password_hash()

## Development Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser for testing

### Database Setup
1. Start XAMPP services (Apache + MySQL)
2. Import database schema: `KiraBOSv2.sql` via phpMyAdmin
3. Update database credentials in `config.php` if needed:
   - Host: localhost
   - Database: pos_system  
   - Username: root
   - Password: (empty by default)

### Running the Application
1. Place files in `C:\xampp\htdocs\KiraBOSv2\`
2. Access via `http://localhost/KiraBOSv2/`
3. Login with demo accounts:
   - Admin: `admin / admin123`
   - Cashier: `cashier / cashier123`

## Key Features

### Admin Dashboard (`admin.php`)
- Daily sales statistics and order tracking
- Product management (add, enable/disable products)
- Real-time order monitoring
- Category-based product organization

### Cashier Interface (`cashier.php`) 
- Product catalog with category filtering
- Shopping cart with quantity management
- Dual payment methods (Cash/QR Code)
- Built-in payment calculator with change calculation
- Session-based cart persistence
- AJAX-powered interactions

### Payment Processing
- Cash payments with amount validation and change calculation
- QR code payment simulation
- Tax calculation (8.5% hardcoded)
- Transaction logging with payment method tracking

## Technical Notes

### Frontend
- TailwindCSS for styling via CDN
- Vanilla JavaScript for interactivity
- Responsive design for various screen sizes
- No build process required

### Session Management
- Shopping cart stored in PHP sessions
- CSRF tokens for security
- Role-based access control
- Automatic session regeneration

### File Structure
```
KiraBOSv2/
├── config.php          # Database and security classes
├── login.php           # Authentication
├── admin.php           # Admin dashboard  
├── cashier.php         # POS interface
├── logout.php          # Session cleanup
├── KiraBOSv2.sql       # Database schema
└── setup_user.php      # User creation utility
```

No package managers, build tools, or additional dependencies are required beyond XAMPP.