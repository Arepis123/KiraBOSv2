<?php
// admin.php - Simple admin dashboard
require_once 'config.php';
Security::requireAdmin();

// Determine current page
$current_page = $_GET['page'] ?? 'dashboard';
$valid_pages = ['dashboard', 'menu', 'users', 'logs', 'reports', 'settings'];
if (!in_array($current_page, $valid_pages)) {
    $current_page = 'dashboard';
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$restaurant_id = Security::getRestaurantId();
$restaurant = Restaurant::getCurrentRestaurant();

// Handle product management
if ($_POST && isset($_POST['action'])) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        if ($_POST['action'] === 'add_product') {
            $name = Security::sanitize($_POST['name']);
            $price = (float)$_POST['price'];
            $category = Security::sanitize($_POST['category']);
            
            if ($name && $price > 0 && $category) {
                // Check for duplicate product within the restaurant
                $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND name = :name AND price = :price AND category = :category";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->bindParam(':price', $price);
                $check_stmt->bindParam(':category', $category);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Product with the same name, price, and category already exists';
                } else {
                    $query = "INSERT INTO products (restaurant_id, name, price, category) VALUES (:restaurant_id, :name, :price, :category)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':category', $category);
                    
                    if ($stmt->execute()) {
                        $success = 'Product added successfully';
                    } else {
                        $error = 'Failed to add product';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        if ($_POST['action'] === 'toggle_product') {
            $product_id = (int)$_POST['product_id'];
            $is_active = (int)$_POST['is_active'];
            
            $query = "UPDATE products SET is_active = :is_active WHERE id = :id AND restaurant_id = :restaurant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $product_id);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->execute();
        }
        
        if ($_POST['action'] === 'edit_product') {
            $product_id = (int)$_POST['product_id'];
            $name = Security::sanitize($_POST['name']);
            $price = (float)$_POST['price'];
            $category = Security::sanitize($_POST['category']);
            
            if ($name && $price > 0 && $category) {
                // Check for duplicate product within restaurant (excluding current product)
                $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND name = :name AND price = :price AND category = :category AND id != :id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->bindParam(':price', $price);
                $check_stmt->bindParam(':category', $category);
                $check_stmt->bindParam(':id', $product_id);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Another product with the same name, price, and category already exists';
                } else {
                    $query = "UPDATE products SET name = :name, price = :price, category = :category WHERE id = :id AND restaurant_id = :restaurant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':category', $category);
                    $stmt->bindParam(':id', $product_id);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Product updated successfully';
                    } else {
                        $error = 'Failed to update product';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        // Category Management Actions
        if ($_POST['action'] === 'add_category') {
            $name = Security::sanitize($_POST['category_name']);
            $description = Security::sanitize($_POST['category_description']);
            $icon = Security::sanitize($_POST['category_icon']);
            
            if ($name) {
                // Check for duplicate category
                $check_query = "SELECT COUNT(*) as count FROM categories WHERE restaurant_id = :restaurant_id AND name = :name";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Category already exists';
                } else {
                    $query = "INSERT INTO categories (restaurant_id, name, description, icon) VALUES (:restaurant_id, :name, :description, :icon)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':icon', $icon);
                    
                    if ($stmt->execute()) {
                        $success = 'Category added successfully';
                    } else {
                        $error = 'Failed to add category';
                    }
                }
            } else {
                $error = 'Category name is required';
            }
        }
        
        if ($_POST['action'] === 'edit_category') {
            $category_id = (int)$_POST['category_id'];
            $name = Security::sanitize($_POST['category_name']);
            $description = Security::sanitize($_POST['category_description']);
            $icon = Security::sanitize($_POST['category_icon']);
            
            if ($name) {
                $query = "UPDATE categories SET name = :name, description = :description, icon = :icon WHERE id = :id AND restaurant_id = :restaurant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':icon', $icon);
                $stmt->bindParam(':id', $category_id);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                
                if ($stmt->execute()) {
                    $success = 'Category updated successfully';
                } else {
                    $error = 'Failed to update category';
                }
            } else {
                $error = 'Category name is required';
            }
        }
        
        if ($_POST['action'] === 'toggle_category') {
            $category_id = (int)$_POST['category_id'];
            $is_active = (int)$_POST['is_active'];
            
            $query = "UPDATE categories SET is_active = :is_active WHERE id = :id AND restaurant_id = :restaurant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $category_id);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->execute();
        }
        
        if ($_POST['action'] === 'delete_category') {
            $category_id = (int)$_POST['category_id'];
            
            // Check if category has products
            $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND category = (SELECT name FROM categories WHERE id = :category_id)";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':restaurant_id', $restaurant_id);
            $check_stmt->bindParam(':category_id', $category_id);
            $check_stmt->execute();
            $products_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($products_count > 0) {
                $error = 'Cannot delete category that has products. Please move or delete products first.';
            } else {
                $query = "DELETE FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $category_id);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                
                if ($stmt->execute()) {
                    $success = 'Category deleted successfully';
                } else {
                    $error = 'Failed to delete category';
                }
            }
        }
    }
    
    // Report Generation Handler
    if ($_POST['action'] === 'generate_report') {
        $period = Security::sanitize($_POST['period']);
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        // Calculate date range based on period
        switch ($period) {
            case 'today':
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
                $period_text = 'Today (' . date('M j, Y') . ')';
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d');
                $period_text = 'This Week (' . date('M j', strtotime($start_date)) . ' - ' . date('M j, Y') . ')';
                break;
            case 'month':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-d');
                $period_text = 'This Month (' . date('M Y') . ')';
                break;
            case 'year':
                $start_date = date('Y-01-01');
                $end_date = date('Y-m-d');
                $period_text = 'This Year (' . date('Y') . ')';
                break;
            case 'custom':
                if ($start_date && $end_date) {
                    $period_text = 'Custom Range (' . date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)) . ')';
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid date range']);
                    exit();
                }
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid period']);
                exit();
        }
        
        try {
            // Get overall statistics
            $query = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(SUM(tax_amount), 0) as total_tax
                FROM orders 
                WHERE restaurant_id = :restaurant_id 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                AND status = 'completed'";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get daily breakdown
            $daily_query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(SUM(tax_amount), 0) as tax
                FROM orders 
                WHERE restaurant_id = :restaurant_id 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                AND status = 'completed'
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
            
            $daily_stmt = $db->prepare($daily_query);
            $daily_stmt->bindParam(':restaurant_id', $restaurant_id);
            $daily_stmt->bindParam(':start_date', $start_date);
            $daily_stmt->bindParam(':end_date', $end_date);
            $daily_stmt->execute();
            $daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format daily data
            $formatted_daily = [];
            foreach ($daily_data as $day) {
                $formatted_daily[] = [
                    'date' => date('M j, Y', strtotime($day['date'])),
                    'orders' => (int)$day['orders'],
                    'revenue' => number_format((float)$day['revenue'], 2),
                    'tax' => number_format((float)$day['tax'], 2)
                ];
            }
            
            $report = [
                'period' => $period,
                'period_text' => $period_text,
                'total_orders' => (int)$stats['total_orders'],
                'total_revenue' => number_format((float)$stats['total_revenue'], 2),
                'total_tax' => number_format((float)$stats['total_tax'], 2),
                'avg_order' => $stats['total_orders'] > 0 ? number_format((float)$stats['total_revenue'] / $stats['total_orders'], 2) : '0.00',
                'daily_data' => $formatted_daily
            ];
            
            echo json_encode(['success' => true, 'report' => $report]);
            exit();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
            exit();
        }
    }
}

// Get statistics
$stats = [];

// Total sales today for current restaurant
$query = "SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM orders WHERE restaurant_id = :restaurant_id AND DATE(created_at) = CURDATE() AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$stats['today_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_sales'];

// Total orders today for current restaurant
$query = "SELECT COUNT(*) as today_orders FROM orders WHERE restaurant_id = :restaurant_id AND DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$stats['today_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_orders'];

// Total products for current restaurant (active and total)
$query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products
    FROM products WHERE restaurant_id = :restaurant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$product_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['active_products'] = $product_stats['active_products'];
$stats['total_products'] = $product_stats['total_products'];

// Get sales data for the last 7 days
$sales_trend = [];
$query = "SELECT 
    DATE(created_at) as sales_date,
    COALESCE(SUM(total_amount), 0) as daily_sales
    FROM orders 
    WHERE restaurant_id = :restaurant_id 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND status = 'completed'
    GROUP BY DATE(created_at) 
    ORDER BY sales_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in missing days with 0 sales
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $found = false;
    foreach ($sales_data as $row) {
        if ($row['sales_date'] === $date) {
            $sales_trend[] = [
                'date' => $date,
                'label' => date('M j', strtotime($date)),
                'sales' => (float)$row['daily_sales']
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $sales_trend[] = [
            'date' => $date,
            'label' => date('M j', strtotime($date)),
            'sales' => 0
        ];
    }
}

// Get all products for current restaurant
$query = "SELECT * FROM products WHERE restaurant_id = :restaurant_id ORDER BY category, name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category-wise sales data for dashboard
$category_sales = [];
try {
    $query = "SELECT 
        p.category,
        COUNT(oi.id) as total_items_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        WHERE p.restaurant_id = :restaurant_id
        GROUP BY p.category
        ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to basic categories if query fails
    $category_sales = [];
}

// Get top 3 selling menu items for dashboard
$top_menu_items = [];
try {
    $query = "SELECT 
        p.name,
        p.category,
        COUNT(oi.id) as total_items_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1
        GROUP BY p.id, p.name, p.category
        ORDER BY total_items_sold DESC, total_revenue DESC
        LIMIT 3";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $top_menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback if query fails
    $top_menu_items = [];
}

// Get all categories for current restaurant (with fallback)
$categories = [];
try {
    $query = "SELECT * FROM categories WHERE restaurant_id = :restaurant_id ORDER BY sort_order, name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback to default categories if table doesn't exist
    $categories = [
        ['id' => 0, 'name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'is_active' => 1],
        ['id' => 0, 'name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'is_active' => 1],
        ['id' => 0, 'name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'is_active' => 1]
    ];
}

// If no categories exist, create default ones
if (empty($categories)) {
    $default_categories = [
        ['name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals'],
        ['name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments'],
        ['name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts']
    ];
    
    foreach ($default_categories as $index => $cat) {
        try {
            $query = "INSERT INTO categories (restaurant_id, name, description, icon, sort_order) VALUES (:restaurant_id, :name, :description, :icon, :sort_order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':name', $cat['name']);
            $stmt->bindParam(':description', $cat['description']);
            $stmt->bindParam(':icon', $cat['icon']);
            $stmt->bindParam(':sort_order', $index + 1);
            $stmt->execute();
        } catch (PDOException $e) {
            // Ignore if categories table doesn't exist
        }
    }
    
    // Reload categories after creation
    try {
        $query = "SELECT * FROM categories WHERE restaurant_id = :restaurant_id ORDER BY sort_order, name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Use fallback if still failing
        $categories = [
            ['id' => 0, 'name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'is_active' => 1],
            ['id' => 0, 'name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'is_active' => 1],
            ['id' => 0, 'name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'is_active' => 1]
        ];
    }
}

// Get recent orders for current restaurant
$query = "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.restaurant_id = :restaurant_id ORDER BY o.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiraBOS - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#6366F1',
                        accent: '#EF4444',
                        food: '#FF6B6B',
                        drinks: '#4ECDC4',
                        dessert: '#FFE66D'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-header: linear-gradient(to right, #eef2ff, #f3e8ff);
            --bg-card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e0e7ff;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
        }
        
        [data-theme="dark"] {
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-header: linear-gradient(to right, #1f2937, #374151);
            --bg-card: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --border-primary: #4b5563;
            --accent-primary: #818cf8;
            --accent-secondary: #a78bfa;
        }
        
        [data-theme="minimal"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-header: linear-gradient(to right, #f8fafc, #f1f5f9);
            --bg-card: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-primary: #e2e8f0;
            --accent-primary: #0f172a;
            --accent-secondary: #334155;
        }
        
        [data-theme="original"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-header: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e5e7eb;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
        }
        
        .theme-transition {
            transition: all 0.3s ease;
        }
        
        /* Theme-specific header styles */
        .theme-header {
            color: var(--text-primary);
        }
        
        [data-theme="colorful"] .theme-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Theme option buttons */
        .theme-option {
            background: var(--bg-card);
            border-color: var(--border-primary);
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .theme-option.active {
            border-color: var(--accent-primary);
            background: var(--accent-primary);
            color: white;
        }
        
        .theme-option:hover {
            border-color: var(--accent-secondary);
            background: var(--bg-secondary);
        }
        
        /* Hide scrollbars globally but keep functionality */
        * {
            scrollbar-width: none !important; /* Firefox */
            -ms-overflow-style: none !important; /* Internet Explorer 10+ */
        }
        
        *::-webkit-scrollbar {
            width: 0px !important;
            height: 0px !important;
            background: transparent !important;
            display: none !important; /* Chrome, Safari, Opera */
        }
        
        *::-webkit-scrollbar-track {
            background: transparent !important;
        }
        
        *::-webkit-scrollbar-thumb {
            background: transparent !important;
        }
        
        html, body {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        
        html::-webkit-scrollbar, body::-webkit-scrollbar {
            width: 0px !important;
            height: 0px !important;
            display: none !important;
        }
        
        /* Ensure hidden class works */
        .hidden {
            display: none !important;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="theme-transition min-h-screen font-sans" style="background: var(--bg-primary)">
    <!-- Header -->
    <header class="theme-transition shadow-lg" style="background: var(--bg-header); border-bottom: 1px solid var(--border-primary)">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                <div>
                    <h1 class="text-2xl font-bold theme-header">Admin Dashboard</h1>
                    <p class="text-sm" style="color: var(--text-secondary)"><?= htmlspecialchars($restaurant['name']) ?></p>
                </div>
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <span class="text-sm hidden xs:inline" style="color: var(--text-secondary)">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                    <span class="text-sm xs:hidden" style="color: var(--text-secondary)"><?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                    <a href="cashier.php" class="text-sm font-medium hover:opacity-80 transition-opacity" style="color: var(--accent-primary)">Cashier View</a>
                    <a href="logout.php" class="text-accent hover:text-red-600 text-sm font-medium">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="theme-transition" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-primary)">
        <div class="container mx-auto px-4">
            <div class="flex space-x-1 overflow-x-auto">
                <a href="admin.php?page=dashboard" id="tab-dashboard" class="nav-tab <?= ($current_page === 'dashboard') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'dashboard') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'dashboard') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="admin.php?page=menu" id="tab-menu" class="nav-tab <?= ($current_page === 'menu') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'menu') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'menu') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>🍽️</span>
                    <span>Menu Management</span>
                </a>
                <a href="admin.php?page=users" id="tab-users" class="nav-tab <?= ($current_page === 'users') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'users') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'users') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>👥</span>
                    <span>User Management</span>
                </a>
                <a href="admin.php?page=logs" id="tab-logs" class="nav-tab <?= ($current_page === 'logs') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'logs') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'logs') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📝</span>
                    <span>Activity Logs</span>
                </a>
                <a href="admin.php?page=reports" id="tab-reports" class="nav-tab <?= ($current_page === 'reports') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'reports') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'reports') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📈</span>
                    <span>Sales Reports</span>
                </a>
                <a href="admin.php?page=settings" id="tab-settings" class="nav-tab <?= ($current_page === 'settings') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'settings') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'settings') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>⚙️</span>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <?php if (isset($error)): ?>
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 transition-all duration-500 relative">
                <?= htmlspecialchars($error) ?>
                <button onclick="hideMessage('error-message')" class="absolute top-2 right-4 text-red-600 hover:text-red-800 text-lg font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 transition-all duration-500 relative">
                <?= htmlspecialchars($success) ?>
                <button onclick="hideMessage('success-message')" class="absolute top-2 right-4 text-green-600 hover:text-green-800 text-lg font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Tab Content -->
        <!-- Content Container -->
        <div class="pb-16 mb-16">
            <?php
            // Include the appropriate page content
            $page_file = "admin-{$current_page}.php";
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                echo '<div class="p-8 text-center">';
                echo '<h2 class="text-2xl font-bold mb-4 theme-header">Page Not Found</h2>';
                echo '<p style="color: var(--text-secondary)">The requested page is not available.</p>';
                echo '</div>';
            }
            ?>
        </div>

    </div>
                <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_category">
                    <h3 class="font-semibold mb-3 theme-header">Add New Category</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <input type="text" name="category_name" placeholder="Category Name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                        <input type="text" name="category_description" placeholder="Description (optional)" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                        <input type="text" name="category_icon" placeholder="Icon (emoji)" maxlength="10" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" value="🍽️">
                        <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Category</button>
                    </div>
                </form>

                <!-- Categories Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($categories as $category): ?>
                        <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="category-<?= $category['id'] ?>">
                            <!-- Display Mode -->
                            <div class="category-display">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xl"><?= htmlspecialchars($category['icon']) ?></span>
                                        <h4 class="font-medium <?= $category['is_active'] ? '' : 'line-through opacity-50' ?>" style="color: var(--text-primary)">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </h4>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full <?= $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <?php if ($category['description']): ?>
                                    <p class="text-sm mb-3" style="color: var(--text-secondary)">
                                        <?= htmlspecialchars($category['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="flex space-x-2">
                                    <button onclick="editCategory(<?= $category['id'] ?>)" class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                                        Edit
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="toggle_category">
                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $category['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="text-xs px-3 py-1 rounded transition-colors border <?= $category['is_active'] ? 'text-orange-500 hover:bg-orange-50 border-orange-200' : 'text-green-500 hover:bg-green-50 border-green-200' ?>">
                                            <?= $category['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?')">
                                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                        <button type="submit" class="text-xs px-3 py-1 rounded transition-colors text-red-500 hover:bg-red-50 border border-red-200">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Edit Mode (Hidden by default) -->
                            <form class="category-edit hidden" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="edit_category">
                                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                
                                <div class="space-y-3">
                                    <input type="text" name="category_name" value="<?= htmlspecialchars($category['name']) ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Category Name">
                                    <input type="text" name="category_description" value="<?= htmlspecialchars($category['description']) ?>" class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Description">
                                    <input type="text" name="category_icon" value="<?= htmlspecialchars($category['icon']) ?>" maxlength="10" class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Icon">
                                </div>
                                
                                <div class="flex space-x-2 mt-3">
                                    <button type="submit" class="text-sm px-3 py-1 rounded text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save</button>
                                    <button type="button" onclick="cancelCategoryEdit(<?= $category['id'] ?>)" class="text-sm px-3 py-1 rounded transition-colors" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Product Management -->
            <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
                <h2 class="text-xl font-bold mb-6 theme-header">Product Management</h2>
                
                <!-- Add Product Form -->
                <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_product">
                    <h3 class="font-semibold mb-3 theme-header">Add New Product</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="text" name="name" placeholder="Product Name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                        <input type="number" name="price" step="0.01" placeholder="Price" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                        <select name="category" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if ($cat['is_active']): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Product</button>
                </form>

                <!-- Products Grid View -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($products as $product): ?>
                        <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="menu-product-<?= $product['id'] ?>">
                            <!-- Display Mode -->
                            <div class="product-display">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-medium <?= $product['is_active'] ? '' : 'line-through opacity-50' ?>" style="color: var(--text-primary)">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </h4>
                                    <span class="text-xs px-2 py-1 rounded-full <?= $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <p class="text-sm mb-2" style="color: var(--text-secondary)">
                                    <?= htmlspecialchars($restaurant['currency']) ?><?= number_format($product['price'], 2) ?>
                                </p>
                                <p class="text-xs mb-3" style="color: var(--text-secondary)">
                                    Category: <?= htmlspecialchars($product['category']) ?>
                                </p>
                                <div class="flex space-x-2">
                                    <button onclick="editMenuProduct(<?= $product['id'] ?>)" class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                                        Edit
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="toggle_product">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $product['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="text-xs px-3 py-1 rounded transition-colors border <?= $product['is_active'] ? 'text-red-500 hover:bg-red-50 border-red-200' : 'text-green-500 hover:bg-green-50 border-green-200' ?>">
                                            <?= $product['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Edit Mode (Hidden by default) -->
                            <form class="product-edit hidden" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="edit_product">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                
                                <div class="space-y-3">
                                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Product Name">
                                    <input type="number" name="price" step="0.01" value="<?= $product['price'] ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Price">
                                    <select name="category" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                        <?php foreach ($categories as $cat): ?>
                                            <?php if ($cat['is_active']): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $product['category'] === $cat['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex space-x-2 mt-3">
                                    <button type="submit" class="text-sm px-3 py-1 rounded text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save</button>
                                    <button type="button" onclick="cancelMenuEdit(<?= $product['id'] ?>)" class="text-sm px-3 py-1 rounded transition-colors" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- User Management Tab -->
        <div id="content-users" class="tab-content hidden">
            <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
                <h2 class="text-xl font-bold mb-6 theme-header">User Management</h2>
                
                <!-- Add User Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 theme-header">Add New User</h3>
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <form class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <input type="text" placeholder="First Name" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <input type="text" placeholder="Last Name" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <input type="text" placeholder="Username" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <select class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="user">Cashier</option>
                            </select>
                            <input type="email" placeholder="Email (optional)" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <input type="password" placeholder="Password" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)">
                                Add User
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Current Users -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 theme-header">Current Staff</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Sample user cards -->
                        <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                                        A
                                    </div>
                                    <div>
                                        <h4 class="font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?> <?= htmlspecialchars($_SESSION['last_name'] ?? 'User') ?></h4>
                                        <p class="text-sm" style="color: var(--text-secondary)">@<?= htmlspecialchars($_SESSION['username']) ?></p>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">
                                    <?= ucfirst($_SESSION['role']) ?>
                                </span>
                            </div>
                            <div class="flex space-x-2">
                                <button class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                                    Edit
                                </button>
                                <button class="text-xs px-3 py-1 rounded transition-colors " style="color: var(--text-secondary) hover:theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary) border border-gray-200">
                                    Disable
                                </button>
                            </div>
                        </div>
                        
                        <!-- Placeholder for more users -->
                        <div class="p-4 rounded-lg theme-transition border-2 border-dashed" style="border-color: var(--border-primary)">
                            <div class="text-center" style="color: var(--text-secondary)">
                                <div class="text-2xl mb-2">👥</div>
                                <p class="text-sm">Add more staff members</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Logs Tab -->
        <div id="content-logs" class="tab-content hidden">
            <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
                <h2 class="text-xl font-bold mb-6 theme-header">Activity Logs</h2>
                
                <!-- Filters -->
                <div class="mb-6">
                    <div class="flex flex-wrap gap-3 mb-4">
                        <select class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary)">
                            <option value="">All Actions</option>
                            <option value="login">Logins</option>
                            <option value="order">Orders</option>
                            <option value="product">Product Changes</option>
                            <option value="user">User Management</option>
                        </select>
                        <select class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary)">
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="all">All Time</option>
                        </select>
                        <input type="text" placeholder="Search logs..." class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary)">
                    </div>
                </div>
                
                <!-- Activity Timeline -->
                <div class="space-y-4">
                    <?php 
                    $sample_logs = [
                        ['action' => 'Login', 'user' => $_SESSION['username'], 'details' => 'User logged into admin dashboard', 'time' => '2 minutes ago', 'type' => 'success'],
                        ['action' => 'Product Added', 'user' => $_SESSION['username'], 'details' => 'Added new product: Special Burger', 'time' => '15 minutes ago', 'type' => 'info'],
                        ['action' => 'Order Processed', 'user' => 'cashier', 'details' => 'Order #1234 completed - Total: ' . htmlspecialchars($restaurant['currency']) . '25.50', 'time' => '32 minutes ago', 'type' => 'success'],
                        ['action' => 'Category Modified', 'user' => $_SESSION['username'], 'details' => 'Updated Food category settings', 'time' => '1 hour ago', 'type' => 'info'],
                        ['action' => 'User Login', 'user' => 'cashier', 'details' => 'Cashier logged into POS system', 'time' => '2 hours ago', 'type' => 'success']
                    ];
                    
                    foreach ($sample_logs as $log): 
                        $icon_map = [
                            'success' => '✅',
                            'info' => '📄',
                            'warning' => '⚠️',
                            'error' => '❌'
                        ];
                        $color_map = [
                            'success' => 'text-green-600',
                            'info' => 'text-blue-600',
                            'warning' => 'text-yellow-600',
                            'error' => 'text-red-600'
                        ];
                    ?>
                        <div class="flex items-start space-x-4 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                            <div class="text-lg"><?= $icon_map[$log['type']] ?></div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="font-medium <?= $color_map[$log['type']] ?>"><?= htmlspecialchars($log['action']) ?></h4>
                                    <span class="text-xs" style="color: var(--text-secondary)"><?= htmlspecialchars($log['time']) ?></span>
                                </div>
                                <p class="text-sm mb-1" style="color: var(--text-primary)"><?= htmlspecialchars($log['details']) ?></p>
                                <p class="text-xs" style="color: var(--text-secondary)">by @<?= htmlspecialchars($log['user']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Load More Button -->
                    <div class="text-center mt-6">
                        <button class="px-4 py-2 rounded-lg transition-colors" style="background: var(--bg-secondary); border: 1px solid var(--border-primary); color: var(--text-secondary)">
                            Load More Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Reports Tab -->
        <div id="content-reports" class="tab-content hidden">
            <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
                <h2 class="text-xl font-bold mb-6 theme-header">Sales Reports</h2>
                
                <!-- Report Filters -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <select id="report-period" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary)" onchange="toggleCustomDates()">
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <input id="start-date" type="date" class="px-3 py-2 rounded-lg theme-transition custom-date-input" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary); display: none;">
                        <input id="end-date" type="date" class="px-3 py-2 rounded-lg theme-transition custom-date-input" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary); display: none;">
                        <button onclick="generateReport()" class="px-4 py-2 rounded-lg font-medium text-white transition-colors hover:opacity-90" style="background: var(--accent-primary)">
                            <span id="generate-btn-text">Generate Report</span>
                        </button>
                    </div>
                </div>
                
                <!-- Report Results Area -->
                <div id="report-results" class="mb-8" style="display: none;">
                    <div class="p-6 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold theme-header">Generated Report</h3>
                            <button onclick="downloadReport()" class="px-3 py-1 text-sm rounded-lg font-medium text-white transition-colors hover:opacity-90" style="background: var(--accent-primary)">
                                📥 Download CSV
                            </button>
                        </div>
                        <div id="report-content" class="space-y-4">
                            <!-- Report content will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Sales Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary)">Total Revenue</p>
                                <p class="text-2xl font-bold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($stats['today_sales'], 2) ?></p>
                            </div>
                            <div class="text-2xl">💰</div>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary)">Total Orders</p>
                                <p class="text-2xl font-bold text-blue-500"><?= $stats['today_orders'] ?></p>
                            </div>
                            <div class="text-2xl">📋</div>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary)">Avg Order Value</p>
                                <p class="text-2xl font-bold text-purple-500"><?= htmlspecialchars($restaurant['currency']) ?><?= $stats['today_orders'] > 0 ? number_format($stats['today_sales'] / $stats['today_orders'], 2) : '0.00' ?></p>
                            </div>
                            <div class="text-2xl">📈</div>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary)">Tax Collected</p>
                                <p class="text-2xl font-bold text-orange-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($stats['today_sales'] * $restaurant['tax_rate'], 2) ?></p>
                            </div>
                            <div class="text-2xl">📄</div>
                        </div>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="p-4 rounded-lg theme-transition mb-8" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                    <h3 class="text-lg font-semibold mb-4 theme-header">Export Reports</h3>
                    <div class="flex flex-wrap gap-3">
                        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
                            📄 Export as PDF
                        </button>
                        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
                            📃 Export as Excel
                        </button>
                        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
                            📧 Email Report
                        </button>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="content-settings" class="tab-content hidden">
            <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
                <h2 class="text-xl font-bold mb-6 theme-header">Settings</h2>
                
                <!-- Theme Settings -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 theme-header">Appearance</h3>
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <label class="block text-sm font-medium " style="color: var(--text-primary) mb-3">Theme</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <button onclick="setTheme('colorful')" id="theme-colorful" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                                <div class="text-2xl mb-2">🎨</div>
                                <div class="text-sm font-medium">Colorful</div>
                            </button>
                            <button onclick="setTheme('dark')" id="theme-dark" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                                <div class="text-2xl mb-2">🌙</div>
                                <div class="text-sm font-medium">Dark</div>
                            </button>
                            <button onclick="setTheme('minimal')" id="theme-minimal" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                                <div class="text-2xl mb-2">⚪</div>
                                <div class="text-sm font-medium">Minimal</div>
                            </button>
                            <button onclick="setTheme('original')" id="theme-original" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                                <div class="text-2xl mb-2">⚫</div>
                                <div class="text-sm font-medium">Original</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Restaurant Settings -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 theme-header">Restaurant Information</h3>
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Restaurant Name</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['name']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Currency</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['currency']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Tax Rate</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= number_format($restaurant['tax_rate'] * 100, 2) ?>%</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Timezone</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['timezone']) ?></p>
                            </div>
                        </div>
                        <p class="text-xs mt-3" style="color: var(--text-secondary)">Contact your system administrator to modify these settings.</p>
                    </div>
                </div>

                <!-- User Settings -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 theme-header">Account Information</h3>
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Username</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Role</label>
                                <p class="text-sm " style="color: var(--text-primary) capitalize"><?= htmlspecialchars($_SESSION['role']) ?></p>
                            </div>
                            <?php if (isset($_SESSION['first_name'])): ?>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Name</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '')) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 theme-header">System Information</h3>
                    <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">System Version</label>
                                <p class="text-sm " style="color: var(--text-primary)">KiraBOS v2.0</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">PHP Version</label>
                                <p class="text-sm " style="color: var(--text-primary)"><?= phpversion() ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme functionality
        let currentTheme = localStorage.getItem('pos-admin-theme') || 'colorful';
        
        function setTheme(theme) {
            currentTheme = theme;
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('pos-admin-theme', theme);
            
            // Update theme option buttons
            document.querySelectorAll('.theme-option').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const activeThemeBtn = document.getElementById('theme-' + theme);
            if (activeThemeBtn) {
                activeThemeBtn.classList.add('active');
            }
            
            // Refresh charts with new theme colors
            setTimeout(() => {
                // Chart initialization removed
            }, 100);
        }
        
        // Remove old dropdown function as it's no longer needed
        
        // Product edit functions
        function editProduct(productId) {
            const productDiv = document.getElementById('product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
        }
        
        function cancelEdit(productId) {
            const productDiv = document.getElementById('product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
        }
        
        // Menu management product edit functions
        function editMenuProduct(productId) {
            const productDiv = document.getElementById('menu-product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
        }
        
        function cancelMenuEdit(productId) {
            const productDiv = document.getElementById('menu-product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
        }
        
        // Category management functions
        function editCategory(categoryId) {
            const categoryDiv = document.getElementById('category-' + categoryId);
            const displayDiv = categoryDiv.querySelector('.category-display');
            const editForm = categoryDiv.querySelector('.category-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
        }
        
        function cancelCategoryEdit(categoryId) {
            const categoryDiv = document.getElementById('category-' + categoryId);
            const displayDiv = categoryDiv.querySelector('.category-display');
            const editForm = categoryDiv.querySelector('.category-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
        }
        
        // Message management functions
        function hideMessage(messageId) {
            const messageEl = document.getElementById(messageId);
            if (messageEl) {
                messageEl.style.opacity = '0';
                messageEl.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    messageEl.style.display = 'none';
                }, 500);
            }
        }
        
        function autoHideMessages() {
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            
            if (successMessage) {
                setTimeout(() => hideMessage('success-message'), 4000); // Hide success after 4 seconds
            }
            
            if (errorMessage) {
                setTimeout(() => hideMessage('error-message'), 6000); // Hide error after 6 seconds
            }
        }
        
        // Order details functions
        function toggleOrderDetails(orderId) {
            const detailsDiv = document.getElementById('order-details-' + orderId);
            const button = document.getElementById('details-btn-' + orderId);
            
            if (detailsDiv.classList.contains('hidden')) {
                // Show details and load data if needed
                detailsDiv.classList.remove('hidden');
                button.textContent = 'Hide Details';
                
                // Check if data is already loaded
                if (!detailsDiv.hasAttribute('data-loaded')) {
                    loadOrderDetails(orderId);
                }
            } else {
                // Hide details
                detailsDiv.classList.add('hidden');
                button.textContent = 'View Details';
            }
        }
        
        function loadOrderDetails(orderId) {
            const detailsDiv = document.getElementById('order-details-' + orderId);
            
            fetch('get_order_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let itemsHtml = '<p class="text-sm font-medium mb-2" style="color: var(--text-primary)">Items Ordered:</p><div class="space-y-1">';
                    
                    data.items.forEach(item => {
                        itemsHtml += `
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary)">
                                    ${item.product_name} × ${item.quantity}
                                </span>
                                <span style="color: var(--text-secondary)">
                                    RM${(item.price * item.quantity).toFixed(2)}
                                </span>
                            </div>
                        `;
                    });
                    
                    itemsHtml += '</div>';
                    detailsDiv.innerHTML = itemsHtml;
                    detailsDiv.setAttribute('data-loaded', 'true');
                } else {
                    detailsDiv.innerHTML = '<p class="text-sm text-red-500">Failed to load order details</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                detailsDiv.innerHTML = '<p class="text-sm text-red-500">Error loading order details</p>';
            });
        }
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.style.color = 'var(--text-secondary)';
                tab.style.borderBottom = 'none';
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById('content-' + tabName);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }
            
            // Activate selected tab
            const selectedTab = document.getElementById('tab-' + tabName);
            if (selectedTab) {
                selectedTab.style.color = 'var(--accent-primary)';
                selectedTab.style.borderBottom = '2px solid var(--accent-primary)';
                selectedTab.classList.add('active');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme
            setTheme(currentTheme);
            
            // Initialize first tab as active
            showTab('dashboard');
            
            // Auto-hide messages
            autoHideMessages();
            
            // Initialize theme selection in Settings tab
            const activeThemeBtn = document.getElementById('theme-' + currentTheme);
            if (activeThemeBtn) {
                activeThemeBtn.classList.add('active');
            }
            
            // Initialize Charts after ensuring Chart.js is loaded
            // Chart initialization removed
        });
        
        // Chart.js variables removed
        
        // All Chart.js functions removed
        
        // Report generation functions
        // All Chart.js functions and code completely removed
        
        // Report generation functions
        // Removed duplicate toggleCustomDates function
        
        // Removed incomplete generateReport function - complete version exists below
        
        // All remaining Chart.js functions completely removed
        
        // Report generation functions
        // Removed duplicate toggleCustomDates function
        
        
        // All Chart.js functions completely removed
        
        // Report generation functions
        function toggleCustomDates() {
            const periodSelect = document.getElementById('report-period');
            const customInputs = document.querySelectorAll('.custom-date-input');
            
            if (periodSelect.value === 'custom') {
                customInputs.forEach(input => input.style.display = 'block');
            } else {
                customInputs.forEach(input => input.style.display = 'none');
            }
        }
        
        function generateReport() {
            const period = document.getElementById('report-period').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const btnText = document.getElementById('generate-btn-text');
            const resultsArea = document.getElementById('report-results');
            const reportContent = document.getElementById('report-content');
            
            // Validate custom date range
            if (period === 'custom' && (!startDate || !endDate)) {
                showToast('Please select both start and end dates', true);
                return;
            }
            
            if (period === 'custom' && new Date(startDate) > new Date(endDate)) {
                showToast('Start date cannot be after end date', true);
                return;
            }
            
            // Show loading state
            btnText.textContent = 'Generating...';
            
            // Prepare data
            const formData = new FormData();
            formData.append('action', 'generate_report');
            formData.append('period', period);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('csrf_token', '<?= Security::generateCSRFToken() ?>');
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnText.textContent = 'Generate Report';
                
                if (data.success) {
                    displayReport(data.report);
                    resultsArea.style.display = 'block';
                    resultsArea.scrollIntoView({ behavior: 'smooth' });
                } else {
                    showToast(data.message || 'Failed to generate report', true);
                }
            })
            .catch(error => {
                btnText.textContent = 'Generate Report';
                showToast('Error generating report', true);
            });
        }
        
        function displayReport(reportData) {
            const reportContent = document.getElementById('report-content');
            
            let html = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="text-center p-4 rounded-lg" style="background: var(--bg-primary)">
                        <div class="text-2xl font-bold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?>${reportData.total_revenue}</div>
                        <div class="text-sm" style="color: var(--text-secondary)">Total Revenue</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background: var(--bg-primary)">
                        <div class="text-2xl font-bold text-blue-500">${reportData.total_orders}</div>
                        <div class="text-sm" style="color: var(--text-secondary)">Total Orders</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background: var(--bg-primary)">
                        <div class="text-2xl font-bold text-purple-500"><?= htmlspecialchars($restaurant['currency']) ?>${reportData.avg_order}</div>
                        <div class="text-sm" style="color: var(--text-secondary)">Avg Order Value</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background: var(--bg-primary)">
                        <div class="text-2xl font-bold text-orange-500"><?= htmlspecialchars($restaurant['currency']) ?>${reportData.total_tax}</div>
                        <div class="text-sm" style="color: var(--text-secondary)">Total Tax</div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold theme-header">Period: ${reportData.period_text}</h4>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse rounded-lg overflow-hidden" style="border: 1px solid var(--border-primary)">
                            <thead>
                                <tr style="background: var(--bg-primary); border-bottom: 2px solid var(--border-primary)">
                                    <th class="text-left p-3 font-medium " style="color: var(--text-primary); border-right: 1px solid var(--border-primary)">Date</th>
                                    <th class="text-left p-3 font-medium " style="color: var(--text-primary); border-right: 1px solid var(--border-primary)">Orders</th>
                                    <th class="text-left p-3 font-medium " style="color: var(--text-primary); border-right: 1px solid var(--border-primary)">Revenue</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-primary)">Tax</th>
                                </tr>
                            </thead>
                            <tbody>`;
                            
            if (reportData.daily_data && reportData.daily_data.length > 0) {
                reportData.daily_data.forEach(day => {
                    html += `
                        <tr style="border-bottom: 1px solid var(--border-primary)">
                            <td class="p-3" style="color: var(--text-primary); border-right: 1px solid var(--border-primary)">${day.date}</td>
                            <td class="p-3" style="color: var(--text-primary); border-right: 1px solid var(--border-primary)">${day.orders}</td>
                            <td class="p-3 text-green-600 font-medium" style="border-right: 1px solid var(--border-primary)"><?= htmlspecialchars($restaurant['currency']) ?>${day.revenue}</td>
                            <td class="p-3 text-orange-600"><?= htmlspecialchars($restaurant['currency']) ?>${day.tax}</td>
                        </tr>`;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="4" class="p-8 text-center" style="color: var(--text-secondary)">
                            No sales data found for the selected period
                        </td>
                    </tr>`;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            reportContent.innerHTML = html;
            window.currentReportData = reportData; // Store for CSV download
        }
        
        function downloadReport() {
            if (!window.currentReportData) {
                showToast('No report data available', true);
                return;
            }
            
            const data = window.currentReportData;
            let csv = 'Date,Orders,Revenue,Tax\n';
            
            if (data.daily_data && data.daily_data.length > 0) {
                data.daily_data.forEach(day => {
                    // Clean the date string and escape quotes
                    const cleanDate = day.date.replace(/"/g, '""');
                    csv += `"${cleanDate}",${day.orders},${day.revenue},${day.tax}\n`;
                });
            } else {
                csv += 'No data available for selected period,0,0.00,0.00\n';
            }
            
            // Add summary section
            csv += '\n';
            csv += 'Summary\n';
            csv += `"Period","${data.period_text.replace(/"/g, '""')}"\n`;
            csv += `"Total Revenue",${data.total_revenue}\n`;
            csv += `"Total Orders",${data.total_orders}\n`;
            csv += `"Average Order Value",${data.avg_order}\n`;
            csv += `"Total Tax",${data.total_tax}\n`;
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `sales-report-${data.period}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Toast notification function
        function showToast(message, isError = false) {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-3 rounded-lg text-white font-medium z-50 transform transition-all duration-300 translate-x-full`;
            toast.style.background = isError ? '#ef4444' : '#10b981';
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Animate out and remove
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>