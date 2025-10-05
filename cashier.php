<?php
// cashier.php
require_once 'config.php';
Security::validateSession();

$database = Database::getInstance();
$db = $database->getConnection();
$restaurant_id = Security::getRestaurantId();
$restaurant = Restaurant::getCurrentRestaurant();

// Handle AJAX requests for adding items to cart
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Handle menu view logging (non-critical, skip CSRF for performance)
    if ($_POST['action'] === 'log_menu_view') {
        try {
            $product_id = (int)$_POST['product_id'];
            $product_name = Security::sanitize($_POST['product_name'] ?? '');

            if ($product_id > 0 && !empty($product_name)) {
                ActivityLogger::log(
                    'view_menu',
                    "Viewed menu item: {$product_name}",
                    'products',
                    $product_id
                );
            }
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
            exit();
        }
    }

    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    if ($_POST['action'] === 'add_to_cart') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($product_id > 0 && $quantity > 0) {
            // Get product details (restaurant-specific)
            $query = "SELECT * FROM products WHERE id = :id AND restaurant_id = :restaurant_id AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $product_id);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Initialize cart if not exists
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Add or update cart item
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity,
                        'category' => $product['category']
                    ];
                }
                
                echo json_encode(['success' => true, 'message' => 'Item added to cart']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'remove_from_cart') {
        $product_id = (int)$_POST['product_id'];
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            echo json_encode(['success' => true, 'message' => 'Item removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'update_quantity') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if (isset($_SESSION['cart'][$product_id])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
            echo json_encode(['success' => true, 'message' => 'Quantity updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'clear_cart') {
        try {
            $_SESSION['cart'] = [];
            echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error clearing cart: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'add_expense') {
        try {
            // Validate input
            $category = Security::sanitize($_POST['category'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $description = Security::sanitize($_POST['description'] ?? '');

            if (empty($category) || $amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid expense data']);
                exit();
            }

            // Valid categories
            $validCategories = ['ingredients', 'supplies', 'maintenance', 'delivery', 'utilities', 'other'];
            if (!in_array($category, $validCategories)) {
                echo json_encode(['success' => false, 'message' => 'Invalid category']);
                exit();
            }

            // Insert expense into database
            $query = "INSERT INTO expenses (restaurant_id, user_id, category, amount, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $restaurant_id,
                $_SESSION['user_id'],
                $category,
                $amount,
                $description
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Expense added successfully',
                    'expense_id' => $db->lastInsertId()
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save expense']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error adding expense: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'checkout') {
        if (!empty($_SESSION['cart'])) {
            try {
                $db->beginTransaction();
                
                // Get payment details
                $payment_method = Security::sanitize($_POST['payment_method'] ?? 'cash');
                $amount_tendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0;
                $change_given = isset($_POST['change_given']) ? (float)$_POST['change_given'] : 0;
                
                // Calculate totals with restaurant tax rate
                $subtotal = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }

                // Apply restaurant tax rate if enabled
                $tax_enabled = $restaurant['tax_enabled'] ?? 1;
                $tax_rate = $tax_enabled ? ($restaurant['tax_rate'] ?? 0.0850) : 0;
                $tax_amount = $tax_enabled ? ($subtotal * $tax_rate) : 0;
                $total = $subtotal + $tax_amount;
                
                // Validate cash payment (with proper decimal handling)
                if ($payment_method === 'cash') {
                    // Convert to cents to avoid floating point issues
                    $total_cents = round($total * 100);
                    $tendered_cents = round($amount_tendered * 100);
                    
                    if ($tendered_cents < $total_cents) {
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Insufficient payment amount',
                            'debug' => [
                                'subtotal' => $subtotal,
                                'tax_rate' => $tax_rate,
                                'tax_amount' => $tax_amount,
                                'backend_total' => $total,
                                'frontend_tendered' => $amount_tendered,
                                'total_cents' => $total_cents,
                                'tendered_cents' => $tendered_cents
                            ]
                        ]);
                        exit();
                    }
                }
                
                // Generate order number
                $order_number = 'ORD' . date('Ymd') . sprintf('%04d', rand(1, 9999));
                
                // Create order with restaurant context
                $query = "INSERT INTO orders (restaurant_id, user_id, order_number, subtotal, tax_amount, total_amount, status, payment_method, payment_received, change_amount) VALUES (:restaurant_id, :user_id, :order_number, :subtotal, :tax_amount, :total_amount, 'completed', :payment_method, :payment_received, :change_amount)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':order_number', $order_number);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':tax_amount', $tax_amount);
                $stmt->bindParam(':total_amount', $total);
                $stmt->bindParam(':payment_method', $payment_method);
                $stmt->bindParam(':payment_received', $amount_tendered);
                $stmt->bindParam(':change_amount', $change_given);
                $stmt->execute();
                
                $order_id = $db->lastInsertId();
                
                // Add order items with product name stored for historical accuracy
                $query = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (:order_id, :product_id, :product_name, :product_price, :quantity, :subtotal)";
                $stmt = $db->prepare($query);
                
                foreach ($_SESSION['cart'] as $item) {
                    $item_subtotal = $item['price'] * $item['quantity'];
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':product_id', $item['id']);
                    $stmt->bindParam(':product_name', $item['name']);
                    $stmt->bindParam(':product_price', $item['price']);
                    $stmt->bindParam(':quantity', $item['quantity']);
                    $stmt->bindParam(':subtotal', $item_subtotal);
                    $stmt->execute();
                }
                
                // Reduce stock quantities for products that track stock
                $stock_query = "UPDATE products SET stock_quantity = stock_quantity - :reduce_qty WHERE id = :product_id AND track_stock = 1 AND stock_quantity >= :min_qty";
                $stock_stmt = $db->prepare($stock_query);
                
                foreach ($_SESSION['cart'] as $item) {
                    $stock_stmt->bindParam(':reduce_qty', $item['quantity']);
                    $stock_stmt->bindParam(':min_qty', $item['quantity']);
                    $stock_stmt->bindParam(':product_id', $item['id']);
                    $stock_stmt->execute();
                }
                
                $db->commit();

                // Log order creation
                $item_summary = [];
                foreach ($_SESSION['cart'] as $item) {
                    $item_summary[] = $item['name'] . ' x' . $item['quantity'];
                }
                ActivityLogger::log(
                    'create',
                    "Created order #{$order_number} - Total: MYR " . number_format($total, 2) . " - Items: " . implode(', ', $item_summary),
                    'orders',
                    $order_id,
                    null,
                    [
                        'order_number' => $order_number,
                        'total' => $total,
                        'payment_method' => $payment_method,
                        'items_count' => count($_SESSION['cart'])
                    ]
                );

                $_SESSION['cart'] = [];

                echo json_encode([
                    'success' => true,
                    'message' => 'Order completed successfully',
                    'order_id' => $order_id,
                    'order_number' => $order_number,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax_amount,
                    'total' => $total,
                    'payment_method' => $payment_method,
                    'change_given' => $change_given
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        }
        exit();
    }
}

// Get active categories with colors for current restaurant
$query = "SELECT name, color FROM categories WHERE restaurant_id = :restaurant_id AND is_active = 1 ORDER BY sort_order, name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$active_categories_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create lookup arrays for easy access
$active_categories = array_column($active_categories_data, 'name');
$category_colors = [];
foreach ($active_categories_data as $cat) {
    $category_colors[$cat['name']] = $cat['color'] ?? '#6B7280'; // Default gray if no color
}

// Get products for current restaurant - using JOIN to only get products from active categories
$query = "SELECT p.*, c.sort_order as category_sort_order FROM products p 
          INNER JOIN categories c ON p.category = c.name AND c.restaurant_id = p.restaurant_id 
          WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1 AND c.is_active = 1 
          ORDER BY c.sort_order, p.category, p.name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's expenses for current restaurant
$today_expenses = [];
$today_expenses_total = 0;
try {
    $query = "SELECT category, amount, description, created_at, user_id 
              FROM expenses 
              WHERE restaurant_id = :restaurant_id 
              AND DATE(created_at) = CURDATE() 
              ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $today_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate today's total
    $today_expenses_total = array_sum(array_column($today_expenses, 'amount'));
} catch (Exception $e) {
    // If expenses table doesn't exist or there's an error, continue without expenses
    error_log("Error fetching expenses: " . $e->getMessage());
}

// Group products by category (only active categories will be shown)
$categories = [];
foreach ($products as $product) {
    $categories[$product['category']][] = $product;
}

// Get unique categories for tabs (only active ones)
$category_names = array_keys($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiraBOS - Cashier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
            --bg-cart: linear-gradient(to bottom, #ffffff, #eef2ff);
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e0e7ff;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
            --text-scale: 1;
            --hover-bg: #f3f4f6;
        }

        [data-theme="colorful"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-header: linear-gradient(to right, #eef2ff, #f3e8ff);
            --bg-cart: linear-gradient(to bottom, #ffffff, #eef2ff);
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e0e7ff;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
            --hover-bg: #f3f4f6;
        }

        [data-theme="dark"] {
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-header: linear-gradient(to right, #1f2937, #374151);
            --bg-cart: linear-gradient(to bottom, #1f2937, #111827);
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --border-primary: #4b5563;
            --accent-primary: #818cf8;
            --accent-secondary: #a78bfa;
            --hover-bg: #374151;
        }
        
        [data-theme="minimal"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f1f5f9;
            --bg-header: linear-gradient(to right, #f8fafc, #f1f5f9);
            --bg-cart: linear-gradient(to bottom, #f8fafc, #ffffff);
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-primary: #e2e8f0;
            --accent-primary: #0f172a;
            --accent-secondary: #334155;
            --hover-bg: #e2e8f0;
        }
        
        [data-theme="original"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #f8fafc;
            --bg-header: #ffffff;
            --bg-cart: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e5e7eb;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
            --hover-bg: #f3f4f6;
        }
        
        .scrollbar-hide {
            -ms-overflow-style: none;  /* Internet Explorer 10+ */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Safari and Chrome */
        }
        
        .theme-transition {
            transition: all 0.3s ease;
        }
        
        .theme-cart-btn:hover {
            opacity: 0.8;
        }
        
        [data-theme="dark"] .theme-cart-btn:hover {
            background: #374151 !important;
        }
        
        /* Loading States & Animations */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading {
            opacity: 0.8;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        
        .success {
            /* Removed purple background overlay - keep only subtle effects */
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        /* Button Loading States */
        .product-card.loading {
            transform: scale(0.98);
            opacity: 0.7;
            transition: all 0.2s ease;
        }
        
        .product-card.success {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(5, 150, 105, 0.3);
            transition: all 0.3s ease;
        }
        
        /* Enhanced Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            margin-bottom: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.3s ease forwards;
        }
        
        .toast.success {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        
        .toast.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        .toast.removing {
            animation: slideOut 0.3s ease forwards;
        }
        
        /* Modal Animations */
        #clear-order-modal {
            backdrop-filter: blur(4px);
        }
        
        #modal-content {
            transition: transform 0.2s ease-in-out, opacity 0.2s ease-in-out;
        }
        
        #modal-content.scale-95 {
            transform: scale(0.95);
            opacity: 0.9;
        }
        
        #modal-content.scale-100 {
            transform: scale(1);
            opacity: 1;
        }
        
        /* Cart Item Animations */
        .cart-item {
            transition: all 0.3s ease;
            transform: translateX(0);
            opacity: 1;
        }
        
        .cart-item.removing {
            transform: translateX(-100%);
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }
        
        .cart-item.slide-in {
            animation: slideInCart 0.3s ease forwards;
        }
        
        @keyframes slideInCart {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        /* Mobile & Tablet Optimizations */
        @media (max-width: 768px) {
            /* Mobile: Larger touch targets */
            .product-card {
                min-height: 140px;
                touch-action: manipulation;
            }
            
            .product-card:active {
                transform: scale(0.98);
            }
            
            /* Improved modal on mobile */
            .modal-content {
                margin: 1rem;
                max-height: calc(100vh - 2rem);
                overflow-y: auto;
            }
            
            /* Number pad improvements */
            .number-btn {
                min-height: 48px;
                font-size: 18px;
            }
            
            /* Cart sidebar adjustments */
            .cart-section {
                min-width: 320px;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1024px) {
            /* Tablet: Optimize for touch but more content */
            .product-card {
                min-height: 120px;
                touch-action: manipulation;
            }
            
            .product-card:hover {
                transform: scale(1.03);
            }
            
            .product-card:active {
                transform: scale(0.97);
            }
            
            /* Better grid spacing for tablets */
            .product-grid {
                gap: 1rem;
            }
            
            /* Larger text for readability */
            .product-name {
                font-size: 0.95rem;
            }
            
            .product-price {
                font-size: 1.1rem;
            }
        }
        
        /* Touch device optimizations */
        @media (pointer: coarse) {
            .product-card {
                min-height: 130px;
            }
            
            .btn {
                min-height: 44px;
                padding: 0.75rem 1rem;
            }
            
            .number-pad button {
                min-height: 50px;
                font-size: 1.2rem;
            }
            
            /* Improve cart item controls for touch */
            .cart-item-controls button {
                min-width: 40px;
                min-height: 40px;
            }
        }
        
        /* High DPI screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 2dppx) {
            .product-card {
                border-width: 0.5px;
            }
            
            .loading-spinner {
                border-width: 1.5px;
            }
        }
        
        /* Cart Feedback Animations */
        .cart-item {
            opacity: 0;
            transform: translateY(-10px);
            animation: slideInDown 0.3s ease forwards;
        }
        
        .cart-item.removing {
            animation: slideOutUp 0.3s ease forwards;
        }
        
        .cart-badge {
            animation: bounce 0.4s ease;
        }
        
        .cart-update {
            animation: pulse-green 0.5s ease;
        }
        
        @keyframes slideInDown {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOutUp {
            to {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: scale(1);
            }
            40%, 43% {
                transform: scale(1.2);
            }
            70% {
                transform: scale(1.1);
            }
            90% {
                transform: scale(1.05);
            }
        }
        
        @keyframes pulse-green {
            0%, 100% {
                background-color: inherit;
            }
            50% {
                background-color: rgba(139, 92, 246, 0.1);
                transform: scale(1.02);
            }
        }
        
        
        /* Cart total animation */
        .cart-total-highlight {
            animation: highlightTotal 0.4s ease;
        }
        
        @keyframes highlightTotal {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); color: #8b5cf6; }
            100% { transform: scale(1); }
        }
        
        /* Text scaling support */
        .scalable-text {
            font-size: calc(1rem * var(--text-scale, 1));
        }
        .product-card h3 {
            font-size: calc(1.125rem * var(--text-scale, 1)) !important;
        }
        .price-text {
            font-size: calc(1rem * var(--text-scale, 1)) !important;
        }
        .cart-item-name {
            font-size: calc(0.875rem * var(--text-scale, 1)) !important;
        }
        .modal-text {
            font-size: calc(1rem * var(--text-scale, 1)) !important;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Apply theme immediately before page renders to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('pos-theme') || 'colorful';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body class="theme-transition min-h-screen font-sans" style="background: var(--bg-primary)">
    <div class="flex h-screen theme-transition">
        <!-- Left Section - Menu -->
        <div class="flex-1 flex flex-col">
            <!-- Header with Tabs -->
            <div class="theme-transition px-6 py-4" style="background: var(--bg-header); border-bottom: 1px solid var(--border-primary)">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 space-y-3 sm:space-y-0">
                    <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 flex-1">
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent whitespace-nowrap">KiraBOS</h1>
                            <p class="text-xs sm:text-sm text-gray-600"><?= htmlspecialchars($restaurant['name']) ?></p>
                        </div>
                        <!-- Search Bar -->
                        <div class="flex-1 sm:max-w-xs md:max-w-sm lg:max-w-md xl:max-w-lg">
                            <div class="relative">
                                <input type="text" id="search-input" placeholder="Search menu items..." class="w-full px-3 sm:px-4 py-1.5 sm:py-2 pl-8 sm:pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors" oninput="searchProducts(this.value)">
                                <div class="absolute inset-y-0 left-0 pl-2 sm:pl-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <button onclick="clearSearch()" class="absolute inset-y-0 right-0 pr-2 sm:pr-3 flex items-center text-gray-400 hover:text-gray-600" id="clear-search" style="display: none;">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 sm:space-x-4">
                        <span class="text-xs sm:text-sm text-gray-600 hidden xs:inline">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                        <span class="text-xs sm:text-sm text-gray-600 xs:hidden"><?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                        <a href="logout.php" class="text-accent hover:text-red-600 text-xs sm:text-sm font-medium">Logout</a>
                    </div>
                </div>

                <!-- Category Tabs -->
                <div id="category-tabs-container" class="flex space-x-1 bg-white/50 p-1 rounded-lg shadow-sm">
                    <button onclick="showAllProducts(this)" class="tab-btn active px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-sm">All</button>
                    <?php foreach ($category_names as $category): 
                        $category_color = $category_colors[$category] ?? '#6B7280';
                        // Create hover style using the custom color
                        $hover_style = "background: linear-gradient(135deg, {$category_color}, {$category_color}DD); color: white;";
                        $normal_style = "color: #6b7280;";
                    ?>
                        <button onclick="showCategory('<?= strtolower($category) ?>', this)" 
                                class="tab-btn px-4 py-2 rounded-md font-medium text-sm transition-colors" 
                                style="color: #6b7280;"
                                onmouseover="this.style.cssText='<?= $hover_style ?>'" 
                                onmouseout="this.style.cssText='<?= $normal_style ?>'">
                            <?= htmlspecialchars($category) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto scrollbar-hide p-4">
                <div class="product-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-3 sm:gap-4">
                    <?php foreach ($products as $product): 
                        $category_color = $category_colors[$product['category']] ?? '#6B7280';
                        
                        // Convert hex to lighter RGB for background
                        $hex = ltrim($category_color, '#');
                        $r = hexdec(substr($hex, 0, 2));
                        $g = hexdec(substr($hex, 2, 2));
                        $b = hexdec(substr($hex, 4, 2));
                        
                        // Create light background and border colors
                        $light_bg = "rgba({$r}, {$g}, {$b}, 0.1)";
                        $border_color = "rgba({$r}, {$g}, {$b}, 0.3)";
                        $accent_color = $category_color;
                    ?>
                        <?php 
                        $is_out_of_stock = $product['track_stock'] && ($product['stock_quantity'] ?? 0) <= 0;
                        $card_classes = "product-card theme-card rounded-xl shadow-sm transition-all duration-200 border flex flex-col items-center justify-center text-center";
                        $onclick_handler = '';
                        
                        if ($is_out_of_stock) {
                            $card_classes .= " opacity-60 cursor-not-allowed";
                            $onclick_handler = 'onclick="showOutOfStockMessage()"';
                        } else {
                            $card_classes .= " hover:shadow-lg cursor-pointer hover:scale-105";
                            $onclick_handler = 'onclick="addToCart(' . $product['id'] . ', \'' . htmlspecialchars($product['name']) . '\', ' . $product['price'] . ', this)"';
                        }
                        ?>
                        <div class="<?= $card_classes ?>" 
                             data-category="<?= strtolower($product['category']) ?>"
                             data-name="<?= strtolower($product['name']) ?>"
                             data-product-id="<?= $product['id'] ?>"
                             data-track-stock="<?= $product['track_stock'] ? '1' : '0' ?>"
                             data-current-stock="<?= (int)($product['stock_quantity'] ?? 0) ?>"
                             data-max-stock="<?= (int)($product['max_stock_level'] ?? 100) ?>"
                             data-min-stock="<?= (int)($product['min_stock_level'] ?? 0) ?>"
                             style="background: <?= $light_bg ?>; border-color: <?= $border_color ?>;"
                             <?= $onclick_handler ?>>
                            <div class="aspect-square theme-card-header rounded-t-xl flex items-center justify-center relative overflow-hidden" style="background: <?= $accent_color ?>;">
                                <?php 
                                // Stock badge logic
                                $stock_badge_html = '';
                                if ($product['track_stock']) {
                                    $stock_qty = (int)($product['stock_quantity'] ?? 0);
                                    $max_level = (int)($product['max_stock_level'] ?? 100);
                                    $min_level = (int)($product['min_stock_level'] ?? 0);
                                    
                                    // Determine badge color and status
                                    if ($stock_qty <= 0) {
                                        $badge_color = 'bg-red-500';
                                        $badge_text = '0/' . $max_level;
                                        $text_color = 'text-white';
                                    } elseif ($stock_qty <= $min_level) {
                                        $badge_color = 'bg-orange-500';
                                        $badge_text = $stock_qty . '/' . $max_level;
                                        $text_color = 'text-white';
                                    } else {
                                        $badge_color = 'bg-green-500';
                                        $badge_text = $stock_qty . '/' . $max_level;
                                        $text_color = 'text-white';
                                    }
                                    
                                    $stock_badge_html = "<div id='stock-badge-{$product['id']}' class='absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-bold $badge_color $text_color z-10 shadow-lg'>$badge_text</div>";
                                    // Debug: Log badge generation
                                    error_log("Generated badge for product {$product['id']} ({$product['name']}): $stock_badge_html");
                                }
                                ?>
                                
                                <?php if ($stock_badge_html): ?>
                                    <?php error_log("Outputting badge for product {$product['id']}: {$stock_badge_html}"); ?>
                                    <!-- DEBUG: Badge for product <?= $product['id'] ?> (<?= $product['name'] ?>) -->
                                    <?= $stock_badge_html ?>
                                <?php endif; ?>
                                
                                <?php if ($product['image'] && file_exists(__DIR__ . '/' . $product['image'])): ?>
                                    <!-- Product Image -->
                                    <img src="<?= htmlspecialchars($product['image']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black/10"></div>
                                <?php else: ?>
                                    <!-- Fallback to Category Icon -->
                                    <div class="absolute inset-0 bg-white/20 theme-overlay"></div>
                                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-white rounded-full flex items-center justify-center shadow-sm z-10 theme-icon-container">
                                        <span class="text-xl sm:text-2xl">
                                            <?php
                                            $icons = [
                                                'Food' => '🍔',
                                                'Drinks' => '☕',
                                                'Dessert' => '🍰'
                                            ];
                                            echo $icons[$product['category']] ?? '🍽️';
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-2.5 theme-card-content rounded-b-xl" style="background: var(--bg-secondary)">
                                <h3 class="font-medium text-sm truncate leading-tight" style="color: var(--text-primary)"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="font-bold text-base mt-1" style="color: var(--accent-primary)"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($product['price'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Expenses Section -->
            <div id="expenses-section" class="flex-1 overflow-y-auto scrollbar-hide p-4 hidden">
                <!-- Expenses Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold" style="color: var(--text-primary)">Daily Expenses</h2>
                        <div class="bg-green-100 px-3 py-1 rounded-full">
                            <span class="text-sm font-medium text-green-800">Today: <?= htmlspecialchars($restaurant['currency']) ?><span id="today-expenses-total"><?= number_format($today_expenses_total, 2) ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Category Buttons Grid -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <button onclick="selectExpenseCategory('ingredients', '🥬')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-green-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">🥬</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Ingredients</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Food purchases</div>
                    </button>
                    
                    <button onclick="selectExpenseCategory('supplies', '🧹')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-blue-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">🧹</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Supplies</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Cleaning, packaging</div>
                    </button>
                    
                    <button onclick="selectExpenseCategory('maintenance', '🔧')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-orange-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">🔧</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Maintenance</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Repairs, tools</div>
                    </button>
                    
                    <button onclick="selectExpenseCategory('delivery', '🚗')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-red-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">🚗</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Delivery</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Fuel, parking</div>
                    </button>
                    
                    <button onclick="selectExpenseCategory('utilities', '💡')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-yellow-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">💡</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Utilities</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Electricity, Gas</div>
                    </button>
                    
                    <button onclick="selectExpenseCategory('other', '📦')" class="expense-category-btn p-4 rounded-xl theme-transition border-2 border-transparent hover:border-purple-300" style="background: var(--bg-secondary);">
                        <div class="text-3xl mb-2">📦</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Other</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Miscellaneous</div>
                    </button>
                </div>

                <!-- Expense Entry Form -->
                <div id="expense-form" class="hidden">
                    <div class="rounded-xl p-6 mb-4" style="background: var(--bg-secondary); border: 1px solid var(--border-primary);">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background: var(--bg-primary);" id="selected-category-icon">
                                <span class="text-2xl">🥬</span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-lg" style="color: var(--text-primary);" id="selected-category-name">Ingredients</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">Enter amount and description</p>
                            </div>
                        </div>
                        
                        <!-- Amount Display -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary)">Amount</label>
                            <div class="text-center p-4 rounded-lg" style="background: var(--bg-primary); border: 2px solid var(--border-primary);">
                                <span class="text-3xl font-bold" style="color: var(--accent-primary);">
                                    <?= htmlspecialchars($restaurant['currency']) ?><span id="expense-amount">0.00</span>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Calculator Numpad -->
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <button onclick="addExpenseDigit('7')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">7</button>
                            <button onclick="addExpenseDigit('8')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">8</button>
                            <button onclick="addExpenseDigit('9')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">9</button>
                            <button onclick="addExpenseDigit('4')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">4</button>
                            <button onclick="addExpenseDigit('5')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">5</button>
                            <button onclick="addExpenseDigit('6')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">6</button>
                            <button onclick="addExpenseDigit('1')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">1</button>
                            <button onclick="addExpenseDigit('2')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">2</button>
                            <button onclick="addExpenseDigit('3')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">3</button>
                            <button onclick="addExpenseDigit('0')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">0</button>
                            <button onclick="addExpenseDigit('0'); addExpenseDigit('0')" class="expense-num-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">00</button>
                            <button onclick="clearExpenseAmount()" class="bg-red-400 hover:bg-red-500 text-white py-3 rounded-lg font-semibold">C</button>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary)">Description (Optional)</label>
                            <input type="text" id="expense-description" placeholder="Brief description..." 
                                   class="w-full px-3 py-2 rounded-lg border" 
                                   style="border-color: var(--border-primary); background: var(--bg-primary); color: var(--text-primary);">
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex space-x-3">
                            <button onclick="cancelExpenseEntry()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-4 rounded-lg transition-colors">
                                Back
                            </button>
                            <button onclick="submitExpense()" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                Add Expense
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Expenses -->
                <div class="rounded-xl p-4" style="background: var(--bg-secondary); border: 1px solid var(--border-primary);">
                    <h3 class="font-semibold mb-3" style="color: var(--text-primary)">Today's Expenses</h3>
                    <div id="recent-expenses">
                        <?php if (empty($today_expenses)): ?>
                            <div class="text-center py-4" style="color: var(--text-secondary);">
                                <div class="text-2xl mb-2">💰</div>
                                <p class="text-sm">No expenses recorded today</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            // Define category icons
                            $category_icons = [
                                'ingredients' => '🥬',
                                'supplies' => '🧹',
                                'maintenance' => '🔧',
                                'delivery' => '🚗',
                                'utilities' => '💡',
                                'other' => '📦'
                            ];
                            
                            // Show last 5 expenses
                            $recent_expenses_display = array_slice($today_expenses, 0, 5);
                            foreach ($recent_expenses_display as $expense): 
                                $icon = $category_icons[$expense['category']] ?? '💰';
                                $time = date('g:i A', strtotime($expense['created_at']));
                            ?>
                                <div class="flex items-center justify-between p-3 rounded-lg mb-2" style="background: var(--bg-primary); border: 1px solid var(--border-primary);">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--bg-secondary);">
                                            <span class="text-lg"><?= $icon ?></span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary);"><?= ucfirst($expense['category']) ?></div>
                                            <div class="text-xs" style="color: var(--text-secondary);"><?= htmlspecialchars($expense['description'] ?: 'No description') ?> • <?= $time ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-red-600"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($expense['amount'], 2) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bottom Navigation -->
            <div class="theme-transition px-4 py-2" style="background: var(--bg-secondary); border-top: 1px solid var(--border-primary)">
                <div class="flex justify-center space-x-6">
                    <button onclick="showMenuSection()" class="flex flex-col items-center py-1" style="color: var(--accent-primary)">
                        <i data-lucide="clipboard-list" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Orders</span>
                    </button>
                    <!-- <button class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <i data-lucide="bar-chart-3" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Reports</span>
                    </button> -->
                    <button onclick="showSettingsModal()" class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <i data-lucide="settings" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Settings</span>
                    </button>
                    <!-- <button class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <i data-lucide="bell" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Alerts</span>
                    </button> -->
                    <button onclick="showExpensesSection()" class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <i data-lucide="wallet" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Expenses</span>
                    </button>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <button onclick="window.location.href='admin.php'" class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <i data-lucide="shield-user" class="w-5 h-5 mb-1"></i>
                        <span class="text-xs font-medium">Admin</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Section - Current Order -->
        <div class="w-80 flex flex-col theme-transition" style="background: var(--bg-cart); border-left: 1px solid var(--border-primary)">
            <!-- Order Header -->
            <div class="p-6 theme-transition" style="border-bottom: 1px solid var(--border-primary); background: var(--bg-header)">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Current Order</h2>
                    <span class="text-sm text-indigo-500 bg-indigo-100 px-2 py-1 rounded-full">(<span id="cart-count">0</span> items)</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center shadow-sm">
                        <span class="text-white text-sm font-bold"><?= substr($_SESSION['username'], 0, 1) ?></span>
                    </div>
                    <span class="text-sm font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="flex-1 overflow-y-auto scrollbar-hide p-4">
                <div id="cart-items" class="space-y-3">
                    <!-- Cart items will be loaded here -->
                </div>
            </div>

            <!-- Order Summary -->
            <div class="border-t p-4 space-y-3" style="border-color: var(--border-primary)">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between" style="color: var(--text-secondary)">
                        <span>Subtotal:</span>
                        <span id="subtotal-amount"><?= htmlspecialchars($restaurant['currency']) ?>0.00</span>
                    </div>
                    <?php if (!empty($restaurant['tax_enabled'])): ?>
                    <?php
                    $tax_percentage = $restaurant['tax_rate'] * 100;
                    $tax_display = ($tax_percentage == floor($tax_percentage)) ? number_format($tax_percentage, 0) : number_format($tax_percentage, 2);
                    ?>
                    <div class="flex justify-between" style="color: var(--text-secondary)">
                        <span>Tax (<?= $tax_display ?>%):</span>
                        <span id="tax-amount"><?= htmlspecialchars($restaurant['currency']) ?>0.00</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between text-lg font-bold pt-2 border-t" style="border-color: var(--border-primary)">
                    <span style="color: var(--text-primary)">Total:</span>
                    <span id="total-amount" style="color: var(--accent-primary)"><?= htmlspecialchars($restaurant['currency']) ?>0.00</span>
                </div>
                
                <!-- <button class="text-sm font-medium" style="color: var(--accent-primary)">+ Add discount</button> -->
                
                <button 
                    id="checkout-btn" 
                    onclick="checkout()" 
                    class="w-full bg-primary hover:bg-secondary text-white font-medium py-3 px-4 rounded-lg transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed text-base"
                    disabled
                >
                    <span id="checkout-text">Charge <?= htmlspecialchars($restaurant['currency']) ?>0.00</span>
                </button>
                
                <button 
                    onclick="clearCart()" 
                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors text-sm"
                >
                    Clear Order
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Calculator Modal -->
    <div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="rounded-xl shadow-2xl w-full max-w-sm mx-auto max-h-[95vh] overflow-y-auto scrollbar-hide" style="background: var(--bg-primary);">
            <!-- Modal Header -->
            <div class="text-center p-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-primary mb-1">Payment</h3>
                <div id="modal-total" class="text-2xl font-bold" style="color: var(--text-primary);"><?= htmlspecialchars($restaurant['currency']) ?>0.00</div>
            </div>

            <!-- Payment Method Selection -->
            <div class="p-4">
                <div class="mb-3">
                    <div class="flex space-x-2">
                        <button id="cash-btn" onclick="selectPaymentMethod('cash')" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium flex items-center justify-center space-x-1 transition-colors" style="background: var(--accent-primary); color: white;">
                            <span>💵</span>
                            <span>Cash</span>
                        </button>
                        <button id="qr-btn" onclick="selectPaymentMethod('qr_code')" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium flex items-center justify-center space-x-1 transition-colors" style="background: var(--bg-secondary); color: var(--text-secondary);">
                            <span>📱</span>
                            <span>QR Code</span>
                        </button>
                    </div>
                </div>

                <!-- Cash Payment Section -->
                <div id="cash-section" class="space-y-3">
                    <div>
                        <input type="text" id="amount-input" class="w-full text-xl font-bold text-center border-2 rounded-lg py-2 focus:border-primary focus:outline-none" style="border-color: var(--border-primary); background: var(--bg-primary); color: var(--text-primary);" placeholder="0.00" readonly>
                    </div>

                    <!-- Number Pad -->
                    <div class="number-pad grid grid-cols-3 gap-2">
                        <button onclick="addDigit('7')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">7</button>
                        <button onclick="addDigit('8')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">8</button>
                        <button onclick="addDigit('9')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">9</button>
                        
                        <button onclick="addDigit('4')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">4</button>
                        <button onclick="addDigit('5')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">5</button>
                        <button onclick="addDigit('6')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">6</button>
                        
                        <button onclick="addDigit('1')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">1</button>
                        <button onclick="addDigit('2')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">2</button>
                        <button onclick="addDigit('3')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">3</button>
                        
                        <button onclick="addDigit('0')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">0</button>
                        <button onclick="addDigit('0'); addDigit('0')" class="number-btn py-3 rounded-lg font-semibold transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='var(--bg-secondary)'">00</button>
                        <button onclick="clearAmount()" class="bg-red-400 hover:bg-red-500 text-white py-3 rounded-lg font-semibold text-sm">C</button>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="flex space-x-1">
                        <button onclick="setExactAmount()" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-medium">Exact</button>
                        <button onclick="addQuickAmount(10)" class="flex-1 px-2 py-1 rounded text-xs font-medium text-white transition-opacity hover:opacity-80" style="background: var(--accent-primary);">+10</button>
                        <button onclick="addQuickAmount(20)" class="flex-1 px-2 py-1 rounded text-xs font-medium text-white transition-opacity hover:opacity-80" style="background: var(--accent-primary);">+20</button>
                        <button onclick="addQuickAmount(50)" class="flex-1 px-2 py-1 rounded text-xs font-medium text-white transition-opacity hover:opacity-80" style="background: var(--accent-primary);">+50</button>
                    </div>

                    <!-- Change Display -->
                    <div id="change-display" class="bg-green-50 border border-green-200 rounded-lg p-3 hidden">
                        <div class="text-center">
                            <div class="text-xs text-green-700 mb-1">Change</div>
                            <div id="change-amount" class="text-xl font-bold text-green-800"><?= htmlspecialchars($restaurant['currency']) ?>0.00</div>
                        </div>
                    </div>

                    <!-- Insufficient Amount Warning -->
                    <div id="insufficient-warning" class="bg-red-50 border border-red-200 rounded-lg p-3 hidden">
                        <div class="text-center text-red-700">
                            <div class="font-medium text-sm">Insufficient Amount</div>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div id="qr-section" class="hidden text-center space-y-3">
                    <div class="border-2 border-dashed rounded-lg p-6" style="border-color: var(--border-primary);">
                        <div class="w-12 h-12 bg-primary rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-qr-code-icon lucide-qr-code">
                                <rect width="5" height="5" x="3" y="3" rx="1"/>
                                <rect width="5" height="5" x="16" y="3" rx="1"/>
                                <rect width="5" height="5" x="3" y="16" rx="1"/>
                                <path d="M21 16h-3a2 2 0 0 0-2 2v3"/>
                                <path d="M21 21v.01"/>
                                <path d="M12 7v3a2 2 0 0 1-2 2H7"/>
                                <path d="M3 12h.01"/>
                                <path d="M12 3h.01"/>
                                <path d="M12 16v.01"/>
                                <path d="M16 12h1"/>
                                <path d="M21 12v.01"/>
                                <path d="M12 21v-1"/>
                            </svg>
                        </div>
                        <div class="text-primary font-medium text-sm">Customer scans QR code</div>
                        <div id="qr-total" class="text-xs mt-1" style="color: var(--text-secondary);">Total: <?= htmlspecialchars($restaurant['currency']) ?>0.00</div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex space-x-2 p-4 border-t" style="border-color: var(--border-primary);">
                <button onclick="closePaymentModal()" class="flex-1 py-2 px-3 rounded-lg font-medium text-sm transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Cancel</button>
                <button id="complete-payment-btn" onclick="completePayment()" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded-lg font-medium text-sm">Complete</button>
            </div>
        </div>
    </div>

    <!-- Clear Order Confirmation Modal -->
    <div id="clear-order-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95" id="modal-content">
            <div class="p-6">
                <!-- Modal Header -->
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Clear Order</h3>
                        <p class="text-sm text-gray-500">Remove all items from cart</p>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <p class="text-gray-700 mb-6">
                    Clear all items from the current order? You can always add items back afterwards.
                </p>
                
                <!-- Modal Actions -->
                <div class="flex space-x-3">
                    <button onclick="cancelClearOrder()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmClearOrder()" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-medium py-2.5 px-4 rounded-lg transition-colors">
                        Clear Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div id="receipt-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 theme-transition" style="background: var(--bg-secondary);">
            <!-- Receipt Content -->
            <div id="receipt-content" class="p-6">
                <!-- Receipt Header -->
                <div class="text-center mb-6 pb-4 border-b-2 border-dashed" style="border-color: var(--border-primary);">
                    <h2 class="text-2xl font-bold mb-2" style="color: var(--text-primary);" id="receipt-restaurant-name">Restaurant Name</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Thank you for your purchase!</p>
                </div>

                <!-- Order Info -->
                <div class="mb-4 pb-4 border-b" style="border-color: var(--border-primary);">
                    <div class="flex justify-between text-sm mb-2">
                        <span style="color: var(--text-secondary);">Order #:</span>
                        <span class="font-semibold" style="color: var(--text-primary);" id="receipt-order-number">-</span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span style="color: var(--text-secondary);">Date:</span>
                        <span style="color: var(--text-primary);" id="receipt-date">-</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Cashier:</span>
                        <span style="color: var(--text-primary);" id="receipt-cashier">-</span>
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-4 pb-4 border-b" style="border-color: var(--border-primary);">
                    <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Items</h3>
                    <div id="receipt-items" class="space-y-2">
                        <!-- Items will be inserted here -->
                    </div>
                </div>

                <!-- Totals -->
                <div class="space-y-2 mb-4 pb-4 border-b-2 border-dashed" style="border-color: var(--border-primary);">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Subtotal:</span>
                        <span style="color: var(--text-primary);" id="receipt-subtotal">RM0.00</span>
                    </div>
                    <div class="flex justify-between text-sm" id="receipt-tax-row">
                        <span style="color: var(--text-secondary);" id="receipt-tax-label">Tax:</span>
                        <span style="color: var(--text-primary);" id="receipt-tax">RM0.00</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold">
                        <span style="color: var(--text-primary);">Total:</span>
                        <span style="color: var(--text-primary);" id="receipt-total">RM0.00</span>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="space-y-2 mb-6">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Payment Method:</span>
                        <span class="font-semibold" style="color: var(--text-primary);" id="receipt-payment-method">-</span>
                    </div>
                    <div class="flex justify-between text-sm" id="receipt-tendered-row">
                        <span style="color: var(--text-secondary);">Amount Tendered:</span>
                        <span style="color: var(--text-primary);" id="receipt-tendered">RM0.00</span>
                    </div>
                    <div class="flex justify-between text-sm" id="receipt-change-row">
                        <span style="color: var(--text-secondary);">Change:</span>
                        <span class="font-semibold text-green-600" id="receipt-change">RM0.00</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-xs" style="color: var(--text-secondary);">
                    <p>Powered by KiraBOS</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2 px-6 pb-6">
                <button onclick="printReceipt()" class="flex-1 py-3 rounded-lg font-medium text-white transition-opacity hover:opacity-80 flex items-center justify-center gap-2" style="background: var(--accent-primary);">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Print Receipt
                </button>
                <button onclick="closeReceiptModal()" class="flex-1 py-3 rounded-lg font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-primary);" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-96 max-w-md mx-4 theme-transition max-h-[90vh] overflow-y-auto" style="background: var(--bg-secondary); border: 1px solid var(--border-primary);">
            <div class="px-6 py-4 border-b sticky top-0" style="border-color: var(--border-primary); background: var(--bg-secondary);">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                        Cashier Settings
                    </h3>
                    <button onclick="closeSettingsModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
            
            <div class="px-6 py-4 space-y-6">
                <!-- Theme Section -->
                <div>
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="palette" class="w-4 h-4"></i>
                        Theme
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button id="theme-colorful" onclick="setTheme('colorful')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition flex items-center space-x-2" style="color: var(--text-primary); background: var(--bg-primary);">
                            <i data-lucide="sparkles" class="w-4 h-4"></i><span>Colorful</span>
                        </button>
                        <button id="theme-dark" onclick="setTheme('dark')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition flex items-center space-x-2" style="color: var(--text-primary); background: var(--bg-primary);">
                            <i data-lucide="moon" class="w-4 h-4"></i><span>Dark</span>
                        </button>
                        <button id="theme-minimal" onclick="setTheme('minimal')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition flex items-center space-x-2" style="color: var(--text-primary); background: var(--bg-primary);">
                            <i data-lucide="circle" class="w-4 h-4"></i><span>Minimal</span>
                        </button>
                        <button id="theme-original" onclick="setTheme('original')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition flex items-center space-x-2" style="color: var(--text-primary); background: var(--bg-primary);">
                            <i data-lucide="monitor" class="w-4 h-4"></i><span>Original</span>
                        </button>
                    </div>
                </div>

                <!-- Sound Settings -->
                <div class="border-t pt-4" style="border-color: var(--border-primary);">
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="volume-2" class="w-4 h-4"></i>
                        Sound Settings
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-primary);">Button Click Sounds</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="sound-clicks" class="sr-only peer" onchange="toggleSoundSetting('clicks', this.checked)">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-primary);">Order Success Sound</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="sound-success" class="sr-only peer" onchange="toggleSoundSetting('success', this.checked)" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex space-x-2 pt-2">
                            <button onclick="testSound('click')" class="flex-1 text-xs px-3 py-2 rounded-lg transition-colors flex items-center justify-center gap-1" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i data-lucide="volume-2" class="w-3 h-3"></i> Test Click
                            </button>
                            <button onclick="testSound('success')" class="flex-1 text-xs px-3 py-2 rounded-lg transition-colors flex items-center justify-center gap-1" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i data-lucide="party-popper" class="w-3 h-3"></i> Test Success
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Display Settings -->
                <div class="border-t pt-4" style="border-color: var(--border-primary);">
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="monitor" class="w-4 h-4"></i>
                        Display Settings
                    </label>
                    <div class="space-y-3">
                        <div>
                            <span class="block text-sm mb-1" style="color: var(--text-primary);">Text Size</span>
                            <select id="text-size" onchange="setTextSize(this.value)" class="w-full px-3 py-2 text-sm border rounded-lg" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large">Large</option>
                            </select>
                        </div>
                        <div>
                            <span class="block text-sm mb-1" style="color: var(--text-primary);">Currency Format</span>
                            <select id="currency-format" onchange="setCurrencyFormat(this.value)" class="w-full px-3 py-2 text-sm border rounded-lg" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                                <option value="RM0.00" selected>RM0.00</option>
                                <option value="RM 0.00">RM 0.00</option>
                                <option value="0.00 RM">0.00 RM</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Numpad Layout -->
                <div class="border-t pt-4" style="border-color: var(--border-primary);">
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="hash" class="w-4 h-4"></i>
                        Numpad Layout
                    </label>
                    <div class="grid grid-cols-1 gap-2">
                        <button onclick="setNumpadLayout('calculator')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition" style="color: var(--text-primary); background: var(--bg-primary);" id="layout-calculator">
                            <div class="flex items-center justify-between">
                                <span>Calculator (7-8-9 top)</span>
                                <div class="text-xs opacity-60">
                                    <div>7 8 9</div>
                                    <div>4 5 6</div>
                                    <div>1 2 3</div>
                                </div>
                            </div>
                        </button>
                        <button onclick="setNumpadLayout('phone')" class="text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition" style="color: var(--text-primary); background: var(--bg-primary);" id="layout-phone">
                            <div class="flex items-center justify-between">
                                <span>Phone (1-2-3 top)</span>
                                <div class="text-xs opacity-60">
                                    <div>1 2 3</div>
                                    <div>4 5 6</div>
                                    <div>7 8 9</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Workflow Settings -->
                <div class="border-t pt-4" style="border-color: var(--border-primary);">
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="zap" class="w-4 h-4"></i>
                        Workflow Settings
                    </label>
                    <div class="space-y-3">
                        <div>
                            <span class="block text-sm mb-1" style="color: var(--text-primary);">Default Payment Method</span>
                            <select id="default-payment" onchange="setDefaultPayment(this.value)" class="w-full px-3 py-2 text-sm border rounded-lg" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                                <option value="cash">Cash</option>
                                <option value="qr_code">QR Code</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--text-primary);">Auto-clear cart after payment</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="auto-clear" class="sr-only peer" onchange="toggleWorkflowSetting('autoClear', this.checked)" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div>
                            <span class="block text-sm mb-1" style="color: var(--text-primary);">Quick Amount Buttons</span>
                            <div class="flex space-x-1">
                                <input type="number" id="quick1" value="10" onchange="setQuickAmounts()" class="w-16 px-2 py-1 text-xs border rounded" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                                <input type="number" id="quick2" value="20" onchange="setQuickAmounts()" class="w-16 px-2 py-1 text-xs border rounded" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                                <input type="number" id="quick3" value="50" onchange="setQuickAmounts()" class="w-16 px-2 py-1 text-xs border rounded" style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-primary);">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Options -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="border-t pt-4" style="border-color: var(--border-primary);">
                    <label class="block text-sm font-medium mb-2 flex items-center gap-2" style="color: var(--text-primary);">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                        Admin Options
                    </label>
                    <button onclick="window.location.href='admin.php'" class="w-full text-left px-3 py-2 text-sm rounded-lg transition-colors theme-transition flex items-center space-x-2" style="color: var(--text-primary); background: var(--bg-primary);">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Admin Panel</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?= Security::generateCSRFToken() ?>';
        const restaurantConfig = {
            currency: '<?= htmlspecialchars($restaurant['currency']) ?>',
            taxEnabled: <?= !empty($restaurant['tax_enabled']) ? 'true' : 'false' ?>,
            taxRate: <?= $restaurant['tax_rate'] ?? 0.0850 ?>,
            name: '<?= htmlspecialchars($restaurant['name']) ?>'
        };
        let cart = <?= json_encode($_SESSION['cart'] ?? []) ?>;
        let currentTotal = 0;
        let currentPaymentMethod = 'cash';
        let amountTendered = '';
        
        // Get current cashier settings (helper function)
        function getCashierSettings() {
            return cashierSettings || {
                theme: { mode: 'modern' },
                sounds: { clicks: false, success: true },
                display: { textSize: 'medium', currencyFormat: 'RM0.00' },
                numpad: { layout: 'calculator' },
                workflow: { defaultPayment: 'cash', autoClear: true, quickAmounts: [10, 20, 50] }
            };
        }
        
        // Currency formatting helper
        function formatCurrency(amount) {
            const settings = getCashierSettings();
            if (settings.display.currencyFormat === 'RM 0.00') {
                return restaurantConfig.currency + ' ' + amount.toFixed(2);
            } else if (settings.display.currencyFormat === '0.00 RM') {
                return amount.toFixed(2) + ' ' + restaurantConfig.currency;
            }
            return restaurantConfig.currency + amount.toFixed(2); // Default format
        }


        function showToast(message, isError = false) {
            // Create toast container if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            // Create new toast
            const toast = document.createElement('div');
            toast.className = `toast ${isError ? 'error' : 'success'}`;
            toast.textContent = message;
            
            toastContainer.appendChild(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.classList.add('removing');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        function showAllProducts(buttonElement = null) {
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.display = 'block';
            });
            if (buttonElement) {
                setActiveTab(buttonElement);
            }
            // Reapply theme styling after showing cards
            updateProductCardTheme(currentTheme);
        }

        function showCategory(category, buttonElement = null) {
            document.querySelectorAll('.product-card').forEach(card => {
                if (card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            if (buttonElement) {
                setActiveTab(buttonElement);
            }
            // Reapply theme styling after showing cards
            updateProductCardTheme(currentTheme);
        }

        function setActiveTab(activeButton) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-white', 'text-primary', 'shadow-sm');
                btn.classList.add('text-gray-600', 'hover:text-gray-900');
                
                // Clear inline styles that might interfere
                btn.style.background = '';
                btn.style.color = '';
            });
            
            activeButton.classList.add('active');
            activeButton.classList.remove('text-gray-600', 'hover:text-gray-900');
            
            // Apply theme-appropriate styling to active tab
            if (currentTheme === 'original' || currentTheme === 'minimal' || currentTheme === 'dark') {
                if (currentTheme === 'original') {
                    activeButton.style.background = '#374151';
                } else if (currentTheme === 'minimal') {
                    activeButton.style.background = '#334155';
                } else if (currentTheme === 'dark') {
                    activeButton.style.background = '#818cf8';
                }
                activeButton.style.color = 'white';
                activeButton.classList.remove('bg-white', 'text-primary', 'shadow-sm');
            } else {
                activeButton.classList.add('bg-gradient-to-r', 'from-indigo-500', 'to-purple-500', 'text-white', 'shadow-sm');
            }
            
            // Update all tabs to respect current theme
            updateProductCardTheme(currentTheme);
        }

        function searchProducts(query) {
            const searchInput = document.getElementById('search-input');
            const clearButton = document.getElementById('clear-search');
            
            // Show/hide clear button
            if (query.trim()) {
                clearButton.style.display = 'flex';
            } else {
                clearButton.style.display = 'none';
            }
            
            // Clear active tab when searching
            if (query.trim()) {
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active', 'bg-white', 'text-primary', 'shadow-sm');
                    btn.classList.add('text-gray-600', 'hover:text-gray-900');
                });
            }
            
            const searchTerm = query.toLowerCase().trim();
            
            document.querySelectorAll('.product-card').forEach(card => {
                const productName = card.dataset.name;
                const category = card.dataset.category;
                
                if (!searchTerm) {
                    // Show all products when search is empty
                    card.style.display = 'block';
                } else if (productName.includes(searchTerm) || category.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-input');
            const clearButton = document.getElementById('clear-search');
            
            searchInput.value = '';
            clearButton.style.display = 'none';
            
            // Show all products
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.display = 'block';
            });
            
            // Reset to "All" tab
            showAllProducts();
        }

        // Show out of stock message
        function showOutOfStockMessage() {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
            toast.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>This item is out of stock</span>
            `;
            
            document.body.appendChild(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3000);
        }

        function updateAllStockBadges() {
            // Update all tracked products' stock badges based on current cart
            document.querySelectorAll('[data-track-stock="1"]').forEach(productCard => {
                const productId = productCard.dataset.productId;
                // Get original stock from data attribute (this should remain constant)
                const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
                const maxStock = parseInt(productCard.dataset.maxStock);
                const minStock = parseInt(productCard.dataset.minStock);
                
                // Calculate how many items from this product are in cart
                const cartQuantity = cart[productId] ? cart[productId].quantity : 0;
                const effectiveStock = Math.max(0, originalStock - cartQuantity);
                
                // Update stock badge using multiple selection methods
                let stockBadge = document.getElementById(`stock-badge-${productId}`);
                if (!stockBadge) {
                    stockBadge = productCard.querySelector(`#stock-badge-${productId}`);
                }
                if (!stockBadge) {
                    stockBadge = productCard.querySelector('[id^="stock-badge-"]');
                }
                
                if (stockBadge) {
                    let badgeColor, badgeText, textColor;
                    
                    if (effectiveStock <= 0) {
                        badgeColor = 'bg-red-500';
                        badgeText = `0/${maxStock}`;
                        textColor = 'text-white';
                        
                        // Disable the card
                        productCard.classList.add('opacity-60', 'cursor-not-allowed');
                        productCard.classList.remove('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                        productCard.onclick = () => showOutOfStockMessage();
                        
                    } else if (effectiveStock <= minStock) {
                        badgeColor = 'bg-orange-500';
                        badgeText = `${effectiveStock}/${maxStock}`;
                        textColor = 'text-white';
                        
                        // Re-enable if was disabled
                        productCard.classList.remove('opacity-60', 'cursor-not-allowed');
                        productCard.classList.add('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                        
                        // Get product details from card elements
                        const productName = productCard.querySelector('h3').textContent.trim();
                        const priceText = productCard.querySelector('p').textContent;
                        const productPrice = parseFloat(priceText.replace(/[^\d.]/g, ''));
                        productCard.onclick = () => addToCart(productId, productName, productPrice, productCard);
                        
                    } else {
                        badgeColor = 'bg-green-500';
                        badgeText = `${effectiveStock}/${maxStock}`;
                        textColor = 'text-white';
                        
                        // Re-enable if was disabled
                        productCard.classList.remove('opacity-60', 'cursor-not-allowed');
                        productCard.classList.add('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                        
                        // Get product details from card elements
                        const productName = productCard.querySelector('h3').textContent.trim();
                        const priceText = productCard.querySelector('p').textContent;
                        const productPrice = parseFloat(priceText.replace(/[^\d.]/g, ''));
                        productCard.onclick = () => addToCart(productId, productName, productPrice, productCard);
                    }
                    
                    // Update badge colors and text without breaking the DOM structure
                    stockBadge.classList.remove('bg-red-500', 'bg-orange-500', 'bg-green-500', 'text-white');
                    stockBadge.classList.add(badgeColor, textColor);
                    stockBadge.textContent = badgeText;
                }
            });
        }

        function updateSingleProductStockOptimistic(productId, quantityToAdd) {
            const productCard = document.querySelector(`[data-product-id="${productId}"]`);
            if (!productCard || productCard.dataset.trackStock !== '1') {
                return;
            }
            
            const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
            const maxStock = parseInt(productCard.dataset.maxStock);
            const minStock = parseInt(productCard.dataset.minStock);
            // Calculate optimistic cart quantity (current + what we're about to add)
            const currentCartQuantity = cart[productId] ? cart[productId].quantity : 0;
            const optimisticCartQuantity = currentCartQuantity + quantityToAdd;
            const effectiveStock = Math.max(0, originalStock - optimisticCartQuantity);
            
            // Try multiple ways to find the badge element
            let stockBadge = document.getElementById(`stock-badge-${productId}`);
            if (!stockBadge) {
                // Fallback: search within the product card
                stockBadge = productCard.querySelector(`#stock-badge-${productId}`);
            }
            if (!stockBadge) {
                // Fallback: search for any badge in the card
                stockBadge = productCard.querySelector('[id^="stock-badge-"]');
            }
            if (!stockBadge) {
                // Fallback: try querySelector on the entire document
                stockBadge = document.querySelector(`#stock-badge-${productId}`);
            }
            if (!stockBadge) {
                // Last resort: search all elements by tag and check ID
                const allDivs = productCard.getElementsByTagName('div');
                for (let div of allDivs) {
                    if (div.id === `stock-badge-${productId}`) {
                        stockBadge = div;
                        break;
                    }
                }
            }
            
            console.log(`Optimistic update - Product ${productId}: Badge found=${!!stockBadge}, Effective=${effectiveStock}, Current Cart=${currentCartQuantity}, Adding=${quantityToAdd}`);
            
            if (stockBadge) {
                let badgeColor, badgeText, textColor;
                
                if (effectiveStock <= 0) {
                    badgeColor = 'bg-red-500';
                    badgeText = `0/${maxStock}`;
                    textColor = 'text-white';
                } else if (effectiveStock <= minStock) {
                    badgeColor = 'bg-orange-500';
                    badgeText = `${effectiveStock}/${maxStock}`;
                    textColor = 'text-white';
                } else {
                    badgeColor = 'bg-green-500';
                    badgeText = `${effectiveStock}/${maxStock}`;
                    textColor = 'text-white';
                }
                
                // Update badge colors and text
                stockBadge.classList.remove('bg-red-500', 'bg-orange-500', 'bg-green-500', 'text-white');
                stockBadge.classList.add(badgeColor, textColor);
                stockBadge.textContent = badgeText;
            }
        }

        function updateSingleProductStock(productId) {
            const productCard = document.querySelector(`[data-product-id="${productId}"]`);
            if (!productCard || productCard.dataset.trackStock !== '1') {
                return; // Don't update if not tracking stock
            }
            
            const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
            const maxStock = parseInt(productCard.dataset.maxStock);
            const minStock = parseInt(productCard.dataset.minStock);
            const cartQuantity = cart[productId] ? cart[productId].quantity : 0;
            const effectiveStock = Math.max(0, originalStock - cartQuantity);
            
            const stockBadge = document.getElementById(`stock-badge-${productId}`);
            console.log(`Single update - Product ${productId}: Badge=${!!stockBadge}, Effective=${effectiveStock}`);
            
            if (stockBadge) {
                let badgeColor, badgeText, textColor;
                
                if (effectiveStock <= 0) {
                    badgeColor = 'bg-red-500';
                    badgeText = `0/${maxStock}`;
                    textColor = 'text-white';
                    
                    // Disable the card
                    productCard.classList.add('opacity-60', 'cursor-not-allowed');
                    productCard.classList.remove('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                    productCard.onclick = () => showOutOfStockMessage();
                    
                } else if (effectiveStock <= minStock) {
                    badgeColor = 'bg-orange-500';
                    badgeText = `${effectiveStock}/${maxStock}`;
                    textColor = 'text-white';
                    
                    // Re-enable if was disabled
                    productCard.classList.remove('opacity-60', 'cursor-not-allowed');
                    productCard.classList.add('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                    
                    const productName = productCard.querySelector('h3').textContent.trim();
                    const priceText = productCard.querySelector('p').textContent;
                    const productPrice = parseFloat(priceText.replace(/[^\d.]/g, ''));
                    productCard.onclick = () => addToCart(productId, productName, productPrice, productCard);
                    
                } else {
                    badgeColor = 'bg-green-500';
                    badgeText = `${effectiveStock}/${maxStock}`;
                    textColor = 'text-white';
                    
                    // Re-enable if was disabled
                    productCard.classList.remove('opacity-60', 'cursor-not-allowed');
                    productCard.classList.add('hover:shadow-lg', 'cursor-pointer', 'hover:scale-105');
                    
                    const productName = productCard.querySelector('h3').textContent.trim();
                    const priceText = productCard.querySelector('p').textContent;
                    const productPrice = parseFloat(priceText.replace(/[^\d.]/g, ''));
                    productCard.onclick = () => addToCart(productId, productName, productPrice, productCard);
                }
                
                // Update badge colors and text
                stockBadge.classList.remove('bg-red-500', 'bg-orange-500', 'bg-green-500', 'text-white');
                stockBadge.classList.add(badgeColor, textColor);
                stockBadge.textContent = badgeText;
            }
        }

        function updateStockDataAfterPayment() {
            // Update frontend stock data attributes to match the database after payment
            // The server has reduced stock, so we need to permanently update our data-current-stock values
            
            for (const [productId, item] of Object.entries(cart)) {
                const productCard = document.querySelector(`[data-product-id="${productId}"]`);
                if (productCard && productCard.dataset.trackStock === '1') {
                    const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
                    const newStock = Math.max(0, originalStock - item.quantity);
                    
                    // Update the data attribute to the new stock level
                    productCard.setAttribute('data-current-stock', newStock.toString());
                    console.log(`Updated product ${productId} stock data: ${originalStock} → ${newStock}`);
                }
            }
        }

        function addToCart(productId, productName, productPrice, buttonElement) {
            // Play click sound when adding item
            playClickSound();
            
            // Check if product is out of stock
            const productCard = document.querySelector(`[data-product-id="${productId}"]`);
            if (productCard && productCard.dataset.trackStock === '1') {
                const currentStock = parseInt(productCard.dataset.currentStock);
                if (currentStock <= 0) {
                    showOutOfStockMessage();
                    return;
                }
            }
            
            // Allow spam clicking but prevent too rapid requests
            if (buttonElement.classList.contains('loading')) {
                return;
            }
            
            // Store original content if not already stored
            if (!buttonElement.originalContent) {
                buttonElement.originalContent = buttonElement.innerHTML;
            }
            
            // Clear any existing reset timeout
            if (buttonElement.resetTimeout) {
                clearTimeout(buttonElement.resetTimeout);
            }
            
            // Don't replace entire card content - just add loading state
            buttonElement.classList.add('loading');
            
            // Update badge BEFORE server call (optimistic update)
            updateSingleProductStockOptimistic(productId, 1);
            
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_cart&product_id=${productId}&quantity=1&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (cart[productId]) {
                        cart[productId].quantity += 1;
                    } else {
                        cart[productId] = {
                            id: productId,
                            name: productName,
                            price: productPrice,
                            quantity: 1
                        };
                    }

                    // Log menu view activity
                    logMenuView(productId, productName);

                    // Update stock badge immediately for this specific product
                    updateSingleProductStock(productId);

                    updateCartDisplay();
                    
                    // Success state - but allow immediate re-clicking
                    buttonElement.classList.remove('loading');
                    buttonElement.classList.add('success');
                    
                    showToast(`${productName} added`, false);
                    
                    // Reset to normal after short delay, but don't disable clicking
                    buttonElement.resetTimeout = setTimeout(() => {
                        if (!buttonElement.classList.contains('loading')) {
                            buttonElement.classList.remove('success');
                        }
                        buttonElement.resetTimeout = null;
                    }, 800); // Shorter delay
                } else {
                    if (buttonElement.resetTimeout) {
                        clearTimeout(buttonElement.resetTimeout);
                        buttonElement.resetTimeout = null;
                    }
                    buttonElement.classList.remove('loading');
                    showToast(data.message, true);
                }
            })
            .catch(error => {
                if (buttonElement.resetTimeout) {
                    clearTimeout(buttonElement.resetTimeout);
                    buttonElement.resetTimeout = null;
                }
                buttonElement.classList.remove('loading');
                showToast('Error adding item', true);
            });
        }

        function removeFromCart(productId) {
            // Find the cart item element and add removing animation
            const cartItemElement = document.querySelector(`[data-item-id="${productId}"]`);
            if (cartItemElement) {
                cartItemElement.classList.add('removing');
                
                // Wait for animation to complete before making the server request
                setTimeout(() => {
                    fetch('cashier.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=remove_from_cart&product_id=${productId}&csrf_token=${csrfToken}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            delete cart[productId];
                            updateCartDisplay();
                            showToast('Item removed');
                        } else {
                            // If server request failed, remove the animation class
                            cartItemElement.classList.remove('removing');
                            showToast('Failed to remove item', true);
                        }
                    })
                    .catch(error => {
                        // If request failed, remove the animation class
                        cartItemElement.classList.remove('removing');
                        showToast('Error removing item', true);
                    });
                }, 300); // Match the CSS animation duration
            }
        }

        function updateQuantity(productId, quantity) {
            // If quantity is 0, use the remove animation
            if (quantity <= 0) {
                removeFromCart(productId);
                return;
            }
            
            // Check stock availability for products that track stock
            const productCard = document.querySelector(`[data-product-id="${productId}"]`);
            if (productCard && productCard.dataset.trackStock === '1') {
                const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
                const currentCartQuantity = cart[productId] ? cart[productId].quantity : 0;
                const quantityIncrease = quantity - currentCartQuantity;
                
                // Only check if we're trying to increase quantity
                if (quantityIncrease > 0) {
                    const availableStock = Math.max(0, originalStock - currentCartQuantity);
                    
                    if (quantityIncrease > availableStock) {
                        if (availableStock === 0) {
                            showToast('Cannot increase - item is out of stock', true);
                        } else {
                            showToast(`Only ${availableStock} more available in stock`, true);
                        }
                        return;
                    }
                }
            }
            
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity=${quantity}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cart[productId].quantity = quantity;
                    updateCartDisplay();
                }
            });
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const totalAmount = document.getElementById('total-amount');
            const cartCount = document.getElementById('cart-count');
            const checkoutBtn = document.getElementById('checkout-btn');
            const checkoutText = document.getElementById('checkout-text');
            
            let html = '';
            let subtotal = 0;
            let itemCount = 0;
            
            for (const [id, item] of Object.entries(cart)) {
                const price = parseFloat(item.price);
                const quantity = parseInt(item.quantity);
                const itemTotal = price * quantity;
                subtotal += itemTotal;
                itemCount += quantity;
                
                // Check if this is a new item by seeing if it exists in current DOM
                const existingItem = document.querySelector(`[data-item-id="${id}"]`);
                const isNewItem = !existingItem;
                const slideClass = isNewItem ? 'slide-in' : '';
                
                // Check stock availability for + button
                const productCard = document.querySelector(`[data-product-id="${id}"]`);
                let plusButtonDisabled = false;
                let plusButtonStyle = "background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary);";
                let plusButtonOnclick = `updateQuantity(${id}, ${quantity + 1})`;
                
                if (productCard && productCard.dataset.trackStock === '1') {
                    const originalStock = parseInt(productCard.getAttribute('data-current-stock'));
                    const availableStock = Math.max(0, originalStock - quantity);
                    
                    if (availableStock <= 0) {
                        plusButtonDisabled = true;
                        plusButtonStyle = "background: var(--bg-secondary); border: 1px solid var(--border-primary); color: var(--text-secondary); opacity: 0.5; cursor: not-allowed;";
                        plusButtonOnclick = `showToast('No more stock available', true)`;
                    }
                }
                
                html += `
                    <div class="cart-item theme-cart-item rounded-lg p-2 mb-2 ${slideClass}" style="background: var(--bg-secondary);" data-item-id="${id}">
                        <div class="flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-sm truncate" style="color: var(--text-primary);">${item.name}</h4>
                                <p class="text-xs" style="color: var(--text-secondary);">${formatCurrency(price)}</p>
                            </div>
                            <div class="flex items-center space-x-1 ml-2 cart-item-controls">
                                <button onclick="updateQuantity(${id}, ${quantity - 1})" class="w-6 h-6 theme-cart-btn rounded flex items-center justify-center text-sm" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary);">-</button>
                                <span class="text-sm font-medium w-6 text-center" style="color: var(--text-primary);">${quantity}</span>
                                <button onclick="${plusButtonOnclick}" class="w-6 h-6 theme-cart-btn rounded flex items-center justify-center text-sm" style="${plusButtonStyle}">+</button>
                                <button onclick="removeFromCart(${id})" class="w-6 h-6 hover:text-red-400 flex items-center justify-center ml-1" style="color: var(--text-secondary);">×</button>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs" style="color: var(--text-secondary);">${quantity} × ${formatCurrency(price)}</span>
                            <span class="font-semibold text-sm" style="color: var(--accent-primary);">${formatCurrency(itemTotal)}</span>
                        </div>
                    </div>
                `;
            }
            
            if (Object.keys(cart).length === 0) {
                html = '<div class="text-center py-4"><p class="text-sm" style="color: var(--text-secondary);">No items selected</p></div>';
            }
            
            // Apply tax rate if enabled (same as backend calculation)
            const tax_rate = restaurantConfig.taxEnabled ? restaurantConfig.taxRate : 0;
            const tax_amount = restaurantConfig.taxEnabled ? (subtotal * tax_rate) : 0;
            const total = subtotal + tax_amount;
            currentTotal = total;

            console.log('Tax Calculation Debug:', {
                subtotal: subtotal,
                taxEnabled: restaurantConfig.taxEnabled,
                taxRate: restaurantConfig.taxRate,
                taxAmount: tax_amount,
                total: total
            });

            cartItems.innerHTML = html;

            // Update subtotal display
            const subtotalElement = document.getElementById('subtotal-amount');
            if (subtotalElement) {
                subtotalElement.textContent = formatCurrency(subtotal);
            }

            // Update tax display if tax is enabled
            const taxElement = document.getElementById('tax-amount');
            if (taxElement) {
                taxElement.textContent = formatCurrency(tax_amount);
                console.log('Tax element updated with:', formatCurrency(tax_amount));
            } else {
                console.log('Tax element not found - tax might be disabled');
            }

            // Animate cart badge if item count changed
            if (cartCount.textContent != itemCount) {
                cartCount.classList.add('cart-badge');
                setTimeout(() => cartCount.classList.remove('cart-badge'), 400);
            }

            // Animate total if it changed
            const currentTotalText = formatCurrency(total);
            if (totalAmount.textContent !== currentTotalText) {
                totalAmount.classList.add('cart-total-highlight');
                setTimeout(() => totalAmount.classList.remove('cart-total-highlight'), 400);
            }

            totalAmount.textContent = currentTotalText;
            cartCount.textContent = itemCount;
            checkoutBtn.disabled = Object.keys(cart).length === 0;
            checkoutText.textContent = `Charge ${formatCurrency(total)}`;
            
            // Update stock badges based on current cart
            updateAllStockBadges();
        }

        function checkout() {
            if (Object.keys(cart).length === 0) {
                showToast('No items to checkout', true);
                return;
            }
            
            showPaymentModal();
        }

        function showPaymentModal() {
            const paymentModal = document.getElementById('payment-modal');
            if (!paymentModal) {
                console.error('Payment modal not found in DOM');
                showToast('Payment system error', true);
                return;
            }
            
            paymentModal.classList.remove('hidden');
            document.getElementById('modal-total').textContent = formatCurrency(currentTotal);
            document.getElementById('qr-total').textContent = `Total: ${formatCurrency(currentTotal)}`;
            
            // Apply default payment method from settings
            const settings = getCashierSettings();
            selectPaymentMethod(settings.workflow.defaultPayment);
            clearAmount();
        }

        function closePaymentModal() {
            const paymentModal = document.getElementById('payment-modal');
            if (paymentModal) {
                paymentModal.classList.add('hidden');
            }
            amountTendered = '';
            clearAmount();
        }

        function selectPaymentMethod(method) {
            currentPaymentMethod = method;
            
            const cashBtn = document.getElementById('cash-btn');
            const qrBtn = document.getElementById('qr-btn');
            const cashSection = document.getElementById('cash-section');
            const qrSection = document.getElementById('qr-section');
            
            if (method === 'cash') {
                // Remove class-based styling and use CSS variables
                cashBtn.classList.remove('bg-gray-200', 'text-gray-700', 'bg-primary', 'text-white');
                cashBtn.style.background = 'var(--accent-primary)';
                cashBtn.style.color = 'white';
                
                qrBtn.classList.remove('bg-primary', 'text-white', 'bg-gray-200', 'text-gray-700');
                qrBtn.style.background = 'var(--bg-secondary)';
                qrBtn.style.color = 'var(--text-secondary)';
                
                cashSection.classList.remove('hidden');
                qrSection.classList.add('hidden');
            } else if (method === 'qr_code') {
                // Remove class-based styling and use CSS variables
                qrBtn.classList.remove('bg-gray-200', 'text-gray-700', 'bg-primary', 'text-white');
                qrBtn.style.background = 'var(--accent-primary)';
                qrBtn.style.color = 'white';
                
                cashBtn.classList.remove('bg-primary', 'text-white', 'bg-gray-200', 'text-gray-700');
                cashBtn.style.background = 'var(--bg-secondary)';
                cashBtn.style.color = 'var(--text-secondary)';
                
                qrSection.classList.remove('hidden');
                cashSection.classList.add('hidden');
            }
            
            updatePaymentButton();
        }

        function addDigit(digit) {
            // Play click sound for numpad
            playClickSound();
            
            // Malaysian banking app approach - no decimal input needed
            // Always work in cents/sen, auto-format to ringgit display
            
            if (amountTendered.length >= 8) return; // Limit to reasonable amount (999999.99)
            
            amountTendered += digit;
            updateAmountDisplay();
        }

        function clearAmount() {
            amountTendered = '';
            updateAmountDisplay();
        }

        function setExactAmount() {
            // Set exact amount in dollars (not cents)
            const exactAmount = Math.round(currentTotal * 100) / 100;
            amountTendered = Math.round(exactAmount * 100).toString(); // Still store as cents for internal calculation
            updateAmountDisplay();
        }

        function addQuickAmount(amount) {
            // Add to current amount (additive behavior)
            const currentCents = parseInt(amountTendered || '0');
            const newCents = currentCents + Math.round(amount * 100);
            amountTendered = newCents.toString();
            updateAmountDisplay();
        }

        function updateAmountDisplay() {
            const amountInput = document.getElementById('amount-input');
            const changeDisplay = document.getElementById('change-display');
            const changeAmount = document.getElementById('change-amount');
            const insufficientWarning = document.getElementById('insufficient-warning');
            
            // Malaysian banking app approach - convert digits to currency with commas
            let displayAmount = '0.00';
            if (amountTendered && amountTendered !== '') {
                // Convert string of digits to proper currency format
                const cents = parseInt(amountTendered);
                const ringgit = cents / 100;
                displayAmount = formatMalaysianCurrency(ringgit);
            }
            
            if (amountInput) amountInput.value = displayAmount;
            
            if (amountTendered && amountTendered !== '') {
                // Parse from original digits, not formatted display
                const cents = parseInt(amountTendered);
                const tendered = cents / 100;
                // Use same precision as backend calculation
                const tenderedRounded = Math.round(tendered * 100) / 100;
                const totalRounded = Math.round(currentTotal * 100) / 100;
                const change = tenderedRounded - totalRounded;
                
                if (change >= 0) {
                    // Sufficient amount
                    if (changeAmount) changeAmount.textContent = formatCurrency(change);
                    if (changeDisplay) changeDisplay.classList.remove('hidden');
                    if (insufficientWarning) insufficientWarning.classList.add('hidden');
                } else {
                    // Insufficient amount
                    if (changeDisplay) changeDisplay.classList.add('hidden');
                    if (insufficientWarning) insufficientWarning.classList.remove('hidden');
                }
            } else {
                if (changeDisplay) changeDisplay.classList.add('hidden');
                if (insufficientWarning) insufficientWarning.classList.add('hidden');
            }
            
            updatePaymentButton();
        }

        function updatePaymentButton() {
            const completeBtn = document.getElementById('complete-payment-btn');
            
            if (currentPaymentMethod === 'qr_code') {
                if (completeBtn) {
                    completeBtn.disabled = false;
                    completeBtn.textContent = 'Complete Payment';
                }
            } else {
                const tendered = parseFloat(amountTendered || 0);
                // Use same precision as backend calculation
                const tenderedRounded = Math.round(tendered * 100) / 100;
                const totalRounded = Math.round(currentTotal * 100) / 100;
                const canComplete = tenderedRounded >= totalRounded;
                
                if (completeBtn) {
                    completeBtn.disabled = !canComplete;
                    completeBtn.textContent = 'Complete Payment';
                }
            }
        }

        function completePayment() {
            let paymentData = {
                action: 'checkout',
                csrf_token: csrfToken,
                payment_method: currentPaymentMethod
            };
            
            if (currentPaymentMethod === 'cash') {
                // Convert amountTendered from cents string back to dollars
                const tendered = parseFloat(amountTendered || 0) / 100;
                
                // Check if no amount was entered
                if (!amountTendered || tendered === 0) {
                    showToast('Please enter payment amount', true);
                    return;
                }
                
                // Use same precision as backend (convert to cents and back)
                const tenderedRounded = Math.round(tendered * 100) / 100;
                const totalRounded = Math.round(currentTotal * 100) / 100;
                
                if (tenderedRounded < totalRounded) {
                    showToast('Insufficient amount tendered', true);
                    return;
                }
                paymentData.amount_tendered = tenderedRounded;
                paymentData.change_given = tenderedRounded - totalRounded;
            } else if (currentPaymentMethod === 'qr_code') {
                // For QR code payments, amount tendered equals the total
                const totalRounded = Math.round(currentTotal * 100) / 100;
                paymentData.amount_tendered = totalRounded;
                paymentData.change_given = 0;
            }
            
            const completeBtn = document.getElementById('complete-payment-btn');
            const cancelBtn = document.querySelector('[onclick="closePaymentModal()"]');
            
            if (completeBtn) {
                completeBtn.disabled = true;
                completeBtn.innerHTML = '<div class="loading-spinner"></div> Processing...';
                completeBtn.classList.add('loading');
            }
            
            if (cancelBtn) {
                cancelBtn.disabled = true;
                cancelBtn.style.opacity = '0.5';
            }
            
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: Object.keys(paymentData).map(key => key + '=' + encodeURIComponent(paymentData[key])).join('&')
            })
            .then(response => response.json())
            .then(data => {
                console.log('Payment response received:', data);
                if (data.success) {
                    // Update frontend stock data to match database after successful payment
                    updateStockDataAfterPayment();

                    // Store cart items BEFORE any operations
                    console.log('Current cart before copy:', cart);
                    const cartItems = {};
                    for (const [id, item] of Object.entries(cart)) {
                        cartItems[id] = {
                            id: item.id,
                            name: item.name,
                            price: parseFloat(item.price),
                            quantity: parseInt(item.quantity)
                        };
                    }
                    console.log('Cart items copied:', cartItems);

                    // Store order data INCLUDING cart items before clearing cart
                    const orderData = {
                        order_number: data.order_number,
                        subtotal: parseFloat(data.subtotal),
                        tax_amount: parseFloat(data.tax_amount),
                        total: parseFloat(data.total),
                        payment_method: data.payment_method,
                        amount_tendered: parseFloat(paymentData.amount_tendered),
                        change_given: parseFloat(data.change_given),
                        items: cartItems
                    };

                    // Close payment modal first
                    closePaymentModal();

                    // Play success sound for completed payment
                    playSuccessSound();

                    // Show receipt modal
                    showReceipt(orderData);

                    // Clear cart after showing receipt
                    cart = {};
                    updateCartDisplay();

                    if (currentPaymentMethod === 'cash' && paymentData.change_given > 0) {
                        showToast(`Payment completed! Change: RM${paymentData.change_given.toFixed(2)}`);
                    } else {
                        showToast('Payment completed successfully!');
                    }
                } else {
                    console.log('Payment failed with data:', data);
                    console.log('Frontend payment data sent:', paymentData);
                    console.log('Frontend currentTotal:', currentTotal);
                    console.log('Frontend amountTendered:', amountTendered);
                    console.log('Frontend currentPaymentMethod:', currentPaymentMethod);
                    
                    if (data.debug) {
                        console.log('Backend Debug Info:', data.debug);
                    }
                    showToast(data.message, true);
                }
            })
            .catch(error => {
                console.error('Payment error:', error);
                showToast('Payment failed: ' + error.message, true);
            })
            .finally(() => {
                if (completeBtn) {
                    completeBtn.disabled = false;
                    completeBtn.innerHTML = 'Complete Payment';
                    completeBtn.classList.remove('loading');
                }
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                    cancelBtn.style.opacity = '1';
                }
            });
        }

        function clearCart() {
            if (Object.keys(cart).length > 0) {
                showClearOrderModal();
            }
        }
        
        // Clear Order Modal Functions
        function showClearOrderModal() {
            const modal = document.getElementById('clear-order-modal');
            const modalContent = document.getElementById('modal-content');
            
            modal.classList.remove('hidden');
            // Trigger animation
            setTimeout(() => {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }
        
        function cancelClearOrder() {
            const modal = document.getElementById('clear-order-modal');
            const modalContent = document.getElementById('modal-content');
            
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }
        
        function confirmClearOrder() {
            // Hide modal first
            cancelClearOrder();
            
            // Send AJAX request to clear server-side cart
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_cart&csrf_token=<?= Security::generateCSRFToken() ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cart = {};
                    updateCartDisplay();
                    showToast('Order cleared');
                } else {
                    console.log('Clear cart failed:', data);
                    showToast('Failed to clear cart: ' + (data.message || 'Unknown error'), true);
                }
            })
            .catch(error => {
                console.error('Error clearing cart:', error);
                showToast('Error clearing cart', true);
            });
        }

        // Currency formatting function - Malaysian style with commas
        function formatMalaysianCurrency(amount) {
            return parseFloat(amount).toLocaleString('en-MY', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Expense Management Functions
        let currentExpenseAmount = '';
        let selectedExpenseCategory = '';
        let selectedExpenseCategoryIcon = '';
        let todayExpensesTotal = <?= $today_expenses_total ?>;

        function showExpensesSection() {
            // Hide menu section
            const menuSection = document.querySelector('.product-grid').closest('.flex-1');
            const expensesSection = document.getElementById('expenses-section');
            
            menuSection.classList.add('hidden');
            expensesSection.classList.remove('hidden');
            
            // Update navigation active state
            updateNavigationState('expenses');
        }

        function showMenuSection() {
            // Show menu section
            const menuSection = document.querySelector('.product-grid').closest('.flex-1');
            const expensesSection = document.getElementById('expenses-section');
            
            expensesSection.classList.add('hidden');
            menuSection.classList.remove('hidden');
            
            // Update navigation active state
            updateNavigationState('menu');
        }

        function updateNavigationState(activeSection) {
            const buttons = document.querySelectorAll('.flex.justify-center button');
            buttons.forEach(btn => {
                btn.style.color = 'var(--text-secondary)';
            });
            
            if (activeSection === 'expenses') {
                buttons[3].style.color = 'var(--accent-primary)'; // Expenses button
            } else {
                buttons[0].style.color = 'var(--accent-primary)'; // Orders/Menu button
            }
        }

        function selectExpenseCategory(category, icon) {
            selectedExpenseCategory = category;
            selectedExpenseCategoryIcon = icon;
            
            // Update form header
            const iconElement = document.getElementById('selected-category-icon').querySelector('span');
            const nameElement = document.getElementById('selected-category-name');
            
            iconElement.textContent = icon;
            nameElement.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            
            // Show form
            document.getElementById('expense-form').classList.remove('hidden');
            
            // Reset amount
            currentExpenseAmount = '';
            document.getElementById('expense-amount').textContent = '0.00';
            document.getElementById('expense-description').value = '';
            
            // Scroll to form
            document.getElementById('expense-form').scrollIntoView({ behavior: 'smooth' });
        }

        function addExpenseDigit(digit) {
            // Malaysian banking app approach for expenses
            if (currentExpenseAmount.length >= 8) return; // Limit to reasonable amount
            
            currentExpenseAmount += digit;
            
            // Convert to currency display with comma formatting
            const cents = parseInt(currentExpenseAmount || '0');
            const ringgit = cents / 100;
            document.getElementById('expense-amount').textContent = formatMalaysianCurrency(ringgit);
        }

        function clearExpenseAmount() {
            currentExpenseAmount = '';
            document.getElementById('expense-amount').textContent = formatMalaysianCurrency(0);
        }

        function cancelExpenseEntry() {
            document.getElementById('expense-form').classList.add('hidden');
            document.getElementById('expense-description').value = '';
            currentExpenseAmount = '';
        }

        function submitExpense() {
            // Convert cents to ringgit for submission
            const cents = parseInt(currentExpenseAmount || '0');
            const amount = cents / 100;
            const description = document.getElementById('expense-description').value;
            
            if (amount <= 0) {
                showToast('Please enter a valid amount', true);
                return;
            }
            
            // Create expense data
            const expenseData = {
                action: 'add_expense',
                category: selectedExpenseCategory,
                amount: amount,
                description: description,
                csrf_token: csrfToken
            };
            
            // Submit expense
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: Object.keys(expenseData).map(key => key + '=' + encodeURIComponent(expenseData[key])).join('&')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Expense added: ${restaurantConfig.currency}${amount.toFixed(2)}`);
                    
                    // Update today's total
                    todayExpensesTotal += amount;
                    document.getElementById('today-expenses-total').textContent = todayExpensesTotal.toFixed(2);
                    
                    // Add to recent expenses list
                    addRecentExpenseToList({
                        category: selectedExpenseCategory,
                        icon: selectedExpenseCategoryIcon,
                        amount: amount,
                        description: description,
                        time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                    });
                    
                    // Reset form
                    cancelExpenseEntry();
                } else {
                    showToast('Failed to add expense: ' + (data.message || 'Unknown error'), true);
                }
            })
            .catch(error => {
                showToast('Error adding expense', true);
                console.error('Error:', error);
            });
        }

        function addRecentExpenseToList(expense) {
            const recentExpenses = document.getElementById('recent-expenses');
            
            // Remove "no expenses" message if it exists
            const noExpensesMsg = recentExpenses.querySelector('.text-center');
            if (noExpensesMsg) {
                noExpensesMsg.remove();
            }
            
            // Create expense item
            const expenseItem = document.createElement('div');
            expenseItem.className = 'flex items-center justify-between p-3 rounded-lg mb-2';
            expenseItem.style = 'background: var(--bg-primary); border: 1px solid var(--border-primary);';
            
            expenseItem.innerHTML = `
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--bg-secondary);">
                        <span class="text-lg">${expense.icon}</span>
                    </div>
                    <div>
                        <div class="text-sm font-medium" style="color: var(--text-primary);">${expense.category.charAt(0).toUpperCase() + expense.category.slice(1)}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">${expense.description || 'No description'} • ${expense.time}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-semibold text-red-600">${restaurantConfig.currency}${expense.amount.toFixed(2)}</div>
                </div>
            `;
            
            // Add to top of list
            recentExpenses.insertBefore(expenseItem, recentExpenses.firstChild);
            
            // Limit to 5 recent expenses
            const items = recentExpenses.children;
            if (items.length > 5) {
                recentExpenses.removeChild(items[items.length - 1]);
            }
        }

        // Theme functionality
        let currentTheme = localStorage.getItem('pos-theme') || 'colorful';
        
        function setTheme(theme) {
            currentTheme = theme;
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('pos-theme', theme);

            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');
            const dropdown = document.getElementById('theme-dropdown');

            const themes = {
                'colorful': { icon: '🎨', text: 'Colorful' },
                'dark': { icon: '🌙', text: 'Dark' },
                'minimal': { icon: '⚪', text: 'Minimal' },
                'original': { icon: '⚫', text: 'Original' }
            };

            if (themeIcon) themeIcon.textContent = themes[theme].icon;
            if (themeText) themeText.textContent = themes[theme].text;
            if (dropdown) dropdown.classList.add('hidden');

            // Update product cards based on theme
            updateProductCardTheme(theme);

            // Update theme selection in settings modal
            updateThemeSelection(theme);
        }
        
        function updateProductCardTheme(theme) {
            const productCards = document.querySelectorAll('.product-card');
            // Only target category tabs, not payment method buttons in modal
            const categoryTabs = document.querySelectorAll('#category-tabs-container .tab-btn');
            
            productCards.forEach(card => {
                const cardHeader = card.querySelector('.theme-card-header');
                const cardContent = card.querySelector('.theme-card-content');
                const iconContainer = card.querySelector('.theme-icon-container');
                const overlay = card.querySelector('.theme-overlay');
                
                if (theme === 'original' || theme === 'minimal' || theme === 'dark') {
                    // Colorless themes - no category colors
                    if (theme === 'original') {
                        card.style.background = 'white';
                        card.style.borderColor = '#e5e7eb';
                        if (cardHeader) {
                            cardHeader.style.background = 'linear-gradient(to bottom right, #f3f4f6, #e5e7eb)';
                        }
                        if (iconContainer) {
                            iconContainer.style.background = 'white';
                        }
                    } else if (theme === 'minimal') {
                        card.style.background = '#f8fafc';
                        card.style.borderColor = '#e2e8f0';
                        if (cardHeader) {
                            cardHeader.style.background = 'linear-gradient(to bottom right, #f1f5f9, #e2e8f0)';
                        }
                        if (iconContainer) {
                            iconContainer.style.background = 'white';
                        }
                    } else if (theme === 'dark') {
                        card.style.background = '#1f2937';
                        card.style.borderColor = '#374151';
                        if (cardHeader) {
                            cardHeader.style.background = 'linear-gradient(to bottom right, #374151, #1f2937)';
                        }
                        if (iconContainer) {
                            iconContainer.style.background = '#f9fafb';
                        }
                    }
                    
                    if (iconContainer) {
                        iconContainer.style.opacity = '1';
                    }
                    if (overlay) {
                        overlay.style.display = 'none';
                    }
                } else {
                    // Colorful themes - restore colorful backgrounds
                    const bgClass = card.dataset.bgClass;
                    const accentClass = card.dataset.accentClass;
                    
                    if (bgClass && accentClass) {
                        card.className = card.className.replace(/bg-\w+/, '').replace(/border-\w+/, '');
                        card.classList.add('bg-gradient-to-br');
                        
                        // Apply softer pastel colors for colorful theme
                        if (bgClass.includes('red')) {
                            card.style.background = 'linear-gradient(to bottom right, #fef7f7, #fef2f2)';
                            card.style.borderColor = '#fecaca';
                            if (cardHeader) cardHeader.style.background = 'linear-gradient(to bottom right, #fecaca, #fca5a5)';
                        } else if (bgClass.includes('cyan')) {
                            card.style.background = 'linear-gradient(to bottom right, #f0fdfa, #f0fdf4)';
                            card.style.borderColor = '#a7f3d0';
                            if (cardHeader) cardHeader.style.background = 'linear-gradient(to bottom right, #a7f3d0, #6ee7b7)';
                        } else if (bgClass.includes('yellow')) {
                            card.style.background = 'linear-gradient(to bottom right, #fffbeb, #fef3c7)';
                            card.style.borderColor = '#fed7aa';
                            if (cardHeader) cardHeader.style.background = 'linear-gradient(to bottom right, #fed7aa, #fbbf24)';
                        }
                        
                        if (iconContainer) {
                            iconContainer.style.background = 'rgba(255, 255, 255, 0.9)';
                        }
                        if (overlay) {
                            overlay.style.display = 'block';
                        }
                    }
                }
            });
            
            // Update category tabs
            categoryTabs.forEach(tab => {
                if (theme === 'original' || theme === 'minimal' || theme === 'dark') {
                    // Colorless themes - remove all colorful gradients
                    tab.style.background = '';
                    tab.className = tab.className.replace(/bg-gradient-to-r|from-\w+-\d+|to-\w+-\d+/g, '');
                    
                    if (tab.classList.contains('active')) {
                        if (theme === 'original') {
                            tab.style.background = '#374151';
                            tab.style.color = 'white';
                        } else if (theme === 'minimal') {
                            tab.style.background = '#334155';
                            tab.style.color = 'white';
                        } else if (theme === 'dark') {
                            tab.style.background = '#818cf8';
                            tab.style.color = 'white';
                        }
                    } else {
                        tab.style.background = '';
                        if (theme === 'dark') {
                            tab.style.color = '#d1d5db';
                        } else {
                            tab.style.color = '#6b7280';
                        }
                        
                        tab.addEventListener('mouseenter', function() {
                            if (!this.classList.contains('active')) {
                                if (theme === 'dark') {
                                    this.style.background = '#374151';
                                    this.style.color = '#f9fafb';
                                } else {
                                    this.style.background = '#f3f4f6';
                                    this.style.color = '#374151';
                                }
                            }
                        });
                        tab.addEventListener('mouseleave', function() {
                            if (!this.classList.contains('active')) {
                                this.style.background = '';
                                if (theme === 'dark') {
                                    this.style.color = '#d1d5db';
                                } else {
                                    this.style.color = '#6b7280';
                                }
                            }
                        });
                    }
                } else {
                    // Colorful themes - restore original hover classes
                    tab.style.background = '';
                    tab.style.color = '';
                    
                    if (tab.classList.contains('active')) {
                        tab.className = 'tab-btn active px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-sm';
                    } else {
                        // Reset hover classes based on tab text
                        const tabText = tab.textContent.trim();
                        let hoverClass = '';
                        if (tabText === 'Food') hoverClass = 'hover:from-red-400 hover:to-pink-500';
                        else if (tabText === 'Drinks') hoverClass = 'hover:from-cyan-400 hover:to-teal-500';
                        else if (tabText === 'Dessert') hoverClass = 'hover:from-yellow-400 hover:to-orange-500';
                        
                        tab.className = `tab-btn px-4 py-2 rounded-md font-medium text-sm transition-colors text-gray-600 hover:bg-gradient-to-r ${hoverClass} hover:text-white`;
                    }
                }
            });
        }
        
        function toggleThemeDropdown() {
            const dropdown = document.getElementById('theme-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        function showSettingsModal() {
            const modal = document.getElementById('settings-modal');
            if (modal) {
                modal.classList.remove('hidden');
                // Update settings UI when modal opens
                updateAllSettingsUI();
            }
        }

        function closeSettingsModal() {
            const modal = document.getElementById('settings-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        // Receipt Functions
        function showReceipt(orderData) {
            // Populate receipt with order data
            document.getElementById('receipt-restaurant-name').textContent = restaurantConfig.name;
            document.getElementById('receipt-order-number').textContent = orderData.order_number || '-';

            // Format date
            const now = new Date();
            const dateStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
            document.getElementById('receipt-date').textContent = dateStr;

            // Cashier name from session
            document.getElementById('receipt-cashier').textContent = '<?= htmlspecialchars($_SESSION['username'] ?? 'Cashier') ?>';

            // Populate items from orderData.items (passed from payment completion)
            const itemsContainer = document.getElementById('receipt-items');
            itemsContainer.innerHTML = '';

            for (const [id, item] of Object.entries(orderData.items)) {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'flex justify-between text-sm';
                itemDiv.innerHTML = `
                    <div class="flex-1">
                        <span style="color: var(--text-primary);">${item.quantity}x ${item.name}</span>
                    </div>
                    <span style="color: var(--text-primary);">${restaurantConfig.currency}${(item.price * item.quantity).toFixed(2)}</span>
                `;
                itemsContainer.appendChild(itemDiv);
            }

            // Populate totals - ensure all values are numbers
            const subtotal = parseFloat(orderData.subtotal) || 0;
            const taxAmount = parseFloat(orderData.tax_amount) || 0;
            const total = parseFloat(orderData.total) || 0;
            const changeGiven = parseFloat(orderData.change_given) || 0;
            const amountTendered = parseFloat(orderData.amount_tendered) || total;

            document.getElementById('receipt-subtotal').textContent = restaurantConfig.currency + subtotal.toFixed(2);

            if (restaurantConfig.taxEnabled && taxAmount > 0) {
                const taxRate = (restaurantConfig.taxRate * 100).toFixed(2);
                document.getElementById('receipt-tax-label').textContent = `Tax (${taxRate}%):`;
                document.getElementById('receipt-tax').textContent = restaurantConfig.currency + taxAmount.toFixed(2);
                document.getElementById('receipt-tax-row').style.display = 'flex';
            } else {
                document.getElementById('receipt-tax-row').style.display = 'none';
            }

            document.getElementById('receipt-total').textContent = restaurantConfig.currency + total.toFixed(2);

            // Payment info
            const paymentMethod = orderData.payment_method === 'cash' ? 'Cash' : 'QR Code';
            document.getElementById('receipt-payment-method').textContent = paymentMethod;

            if (orderData.payment_method === 'cash') {
                document.getElementById('receipt-tendered').textContent = restaurantConfig.currency + amountTendered.toFixed(2);
                document.getElementById('receipt-change').textContent = restaurantConfig.currency + changeGiven.toFixed(2);
                document.getElementById('receipt-tendered-row').style.display = 'flex';
                document.getElementById('receipt-change-row').style.display = 'flex';
            } else {
                document.getElementById('receipt-tendered-row').style.display = 'none';
                document.getElementById('receipt-change-row').style.display = 'none';
            }

            // Show modal
            const modal = document.getElementById('receipt-modal');
            modal.classList.remove('hidden');

            // Reinitialize Lucide icons for the receipt modal
            setTimeout(() => lucide.createIcons(), 100);
        }

        function closeReceiptModal() {
            const modal = document.getElementById('receipt-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function printReceipt() {
            // Get the receipt content
            const receiptContent = document.getElementById('receipt-content').innerHTML;

            // Create a new window for printing
            const printWindow = window.open('', '', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Receipt</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('.border-b { border-bottom: 1px solid #e5e7eb; }');
            printWindow.document.write('.border-b-2 { border-bottom: 2px solid #e5e7eb; }');
            printWindow.document.write('.border-dashed { border-style: dashed; }');
            printWindow.document.write('.text-center { text-align: center; }');
            printWindow.document.write('.flex { display: flex; }');
            printWindow.document.write('.justify-between { justify-content: space-between; }');
            printWindow.document.write('.mb-2 { margin-bottom: 0.5rem; }');
            printWindow.document.write('.mb-3 { margin-bottom: 0.75rem; }');
            printWindow.document.write('.mb-4 { margin-bottom: 1rem; }');
            printWindow.document.write('.mb-6 { margin-bottom: 1.5rem; }');
            printWindow.document.write('.pb-4 { padding-bottom: 1rem; }');
            printWindow.document.write('.font-bold { font-weight: bold; }');
            printWindow.document.write('.font-semibold { font-weight: 600; }');
            printWindow.document.write('.text-sm { font-size: 0.875rem; }');
            printWindow.document.write('.text-xs { font-size: 0.75rem; }');
            printWindow.document.write('.text-lg { font-size: 1.125rem; }');
            printWindow.document.write('.text-2xl { font-size: 1.5rem; }');
            printWindow.document.write('.space-y-2 > * + * { margin-top: 0.5rem; }');
            printWindow.document.write('.text-green-600 { color: #059669; }');
            printWindow.document.write('i { display: none; }'); // Hide lucide icons in print
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(receiptContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();

            // Print after content loads
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        // Cashier Settings Functions
        let cashierSettings = {
            theme: {
                mode: 'modern'
            },
            sounds: {
                clicks: false,
                success: true
            },
            display: {
                textSize: 'medium',
                currencyFormat: 'RM0.00'
            },
            numpad: {
                layout: 'calculator'
            },
            workflow: {
                defaultPayment: 'cash',
                autoClear: true,
                quickAmounts: [10, 20, 50]
            }
        };

        // Load settings from localStorage
        function loadCashierSettings() {
            const saved = localStorage.getItem('cashierSettings');
            if (saved) {
                try {
                    const savedSettings = JSON.parse(saved);
                    cashierSettings = {...cashierSettings, ...savedSettings};
                } catch (e) {
                    console.warn('Failed to load saved settings:', e);
                }
            }
            console.log('Cashier settings loaded:', cashierSettings);
        }

        // Save settings to localStorage
        function saveCashierSettings() {
            localStorage.setItem('cashierSettings', JSON.stringify(cashierSettings));
        }

        // Apply all settings to the interface
        function applyCashierSettings() {
            // Apply sound settings
            document.getElementById('sound-clicks').checked = cashierSettings.sounds.clicks;
            document.getElementById('sound-success').checked = cashierSettings.sounds.success;
            
            // Apply display settings
            document.getElementById('text-size').value = cashierSettings.display.textSize;
            document.getElementById('currency-format').value = cashierSettings.display.currencyFormat;
            setTextSize(cashierSettings.display.textSize);
            
            // Apply numpad layout
            setNumpadLayout(cashierSettings.numpad.layout);
            
            // Apply workflow settings
            document.getElementById('default-payment').value = cashierSettings.workflow.defaultPayment;
            document.getElementById('auto-clear').checked = cashierSettings.workflow.autoClear;
            currentPaymentMethod = cashierSettings.workflow.defaultPayment;
            
            // Apply quick amounts
            document.getElementById('quick1').value = cashierSettings.workflow.quickAmounts[0];
            document.getElementById('quick2').value = cashierSettings.workflow.quickAmounts[1];
            document.getElementById('quick3').value = cashierSettings.workflow.quickAmounts[2];
            updateQuickAmountButtons();
        }

        // Sound Settings
        function toggleSoundSetting(type, enabled) {
            cashierSettings.sounds[type] = enabled;
            saveCashierSettings();
            
            // Test sound when enabled
            if (enabled) {
                if (type === 'clicks') {
                    playClickSound();
                } else if (type === 'success') {
                    playSuccessSound();
                }
            }
        }
        
        // Test sound function for settings
        function testSound(type) {
            if (type === 'click') {
                playClickSound();
            } else if (type === 'success') {
                playSuccessSound();
            }
        }

        // Global audio context (initialize once)
        let globalAudioContext = null;
        
        function initAudioContext() {
            if (!globalAudioContext) {
                try {
                    globalAudioContext = new (window.AudioContext || window.webkitAudioContext)();
                } catch (e) {
                    console.warn('Web Audio API not supported:', e);
                    return null;
                }
            }
            
            // Resume audio context if suspended (required by browsers)
            if (globalAudioContext.state === 'suspended') {
                globalAudioContext.resume();
            }
            
            return globalAudioContext;
        }
        
        function playClickSound() {
            if (!cashierSettings.sounds.clicks) return;

            const audioContext = initAudioContext();
            if (!audioContext) return;

            try {
                // Create a short click sound
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 1200; // Higher frequency for clear click
                oscillator.type = 'square'; // Sharp click sound

                gainNode.gain.setValueAtTime(0.15, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.05);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.05);

                console.log('🔊 Click sound played');
            } catch (e) {
                console.warn('Failed to play click sound:', e);
            }
        }

        // Log menu view activity
        function logMenuView(productId, productName) {
            fetch('cashier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=log_menu_view&product_id=${productId}&product_name=${encodeURIComponent(productName)}`
            })
            .catch(error => {
                // Silent fail - logging is non-critical
            });
        }

        function playSuccessSound() {
            if (!cashierSettings.sounds.success) return;
            
            const audioContext = initAudioContext();
            if (!audioContext) return;
            
            try {
                // Create a pleasant success chime (two-tone)
                const frequencies = [523, 659]; // C and E notes for pleasant sound
                
                frequencies.forEach((freq, index) => {
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = freq;
                    oscillator.type = 'sine';
                    
                    const startTime = audioContext.currentTime + (index * 0.1);
                    gainNode.gain.setValueAtTime(0, startTime);
                    gainNode.gain.linearRampToValueAtTime(0.2, startTime + 0.05);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + 0.4);
                    
                    oscillator.start(startTime);
                    oscillator.stop(startTime + 0.4);
                });
                
                console.log('🎉 Success sound played');
            } catch (e) {
                console.warn('Failed to play success sound:', e);
            }
        }

        // Display Settings
        function setTextSize(size) {
            cashierSettings.display.textSize = size;
            saveCashierSettings();
            
            // Apply text scaling
            applyTextScale(size);
            
            // Update UI to show selection
            updateTextSizeSelection(size);
        }

        function setCurrencyFormat(format) {
            cashierSettings.display.currencyFormat = format;
            saveCashierSettings();
            updateCartDisplay(); // Refresh cart display with new format
        }

        // Numpad Layout
        function setNumpadLayout(layout) {
            cashierSettings.numpad.layout = layout;
            saveCashierSettings();
            
            // Apply layout and update UI
            applyNumpadLayout(layout);
            updateNumpadLayoutSelection(layout);
            
            console.log(`Numpad layout set to: ${layout}`);
        }

        function applyNumpadLayout(layout) {
            const numpadContainer = document.querySelector('.number-pad');
            if (!numpadContainer) return;
            
            // Store current buttons data
            const buttons = numpadContainer.querySelectorAll('button');
            const buttonData = Array.from(buttons).map(btn => ({
                text: btn.textContent,
                onclick: btn.getAttribute('onclick'),
                className: btn.className,
                style: btn.getAttribute('style') || '',
                onmouseover: btn.getAttribute('onmouseover') || '',
                onmouseout: btn.getAttribute('onmouseout') || ''
            }));
            
            // Clear existing layout
            numpadContainer.innerHTML = '';
            
            let buttonOrder;
            if (layout === 'calculator' || layout === 'accountant') {
                // Calculator layout: 7-8-9, 4-5-6, 1-2-3, 0-00-C
                // (accountant was identical, so we handle it here for backwards compatibility)
                buttonOrder = [7, 8, 9, 4, 5, 6, 1, 2, 3, 0, '00', 'C'];
            } else if (layout === 'phone') {
                // Phone layout: 1-2-3, 4-5-6, 7-8-9, 0-00-C
                buttonOrder = [1, 2, 3, 4, 5, 6, 7, 8, 9, 0, '00', 'C'];
            } else if (layout === 'atm') {
                // ATM layout: 1-2-3, 4-5-6, 7-8-9, C-0-00
                buttonOrder = [1, 2, 3, 4, 5, 6, 7, 8, 9, 'C', 0, '00'];
            } else {
                // Default calculator layout
                buttonOrder = [7, 8, 9, 4, 5, 6, 1, 2, 3, 0, '00', 'C'];
            }
            
            // Rebuild buttons in new order
            buttonOrder.forEach(num => {
                const btnData = buttonData.find(b => 
                    b.text === num.toString() || 
                    (num === 'C' && b.text === 'C') ||
                    (num === '00' && b.text === '00')
                );
                
                if (btnData) {
                    const button = document.createElement('button');
                    button.textContent = btnData.text;
                    button.setAttribute('onclick', btnData.onclick);
                    button.className = btnData.className;
                    if (btnData.style) button.setAttribute('style', btnData.style);
                    if (btnData.onmouseover) button.setAttribute('onmouseover', btnData.onmouseover);
                    if (btnData.onmouseout) button.setAttribute('onmouseout', btnData.onmouseout);
                    numpadContainer.appendChild(button);
                }
            });
            
            console.log(`Applied ${layout} numpad layout`);
        }

        // Workflow Settings
        function setDefaultPayment(method) {
            cashierSettings.workflow.defaultPayment = method;
            saveCashierSettings();
            
            // Update UI to show current selection
            updateDefaultPaymentSelection(method);
            
            console.log(`Default payment method set to: ${method}`);
        }

        function toggleWorkflowSetting(setting, enabled) {
            cashierSettings.workflow[setting] = enabled;
            saveCashierSettings();
        }

        function setQuickAmounts() {
            const quick1 = parseInt(document.getElementById('quick1').value) || 10;
            const quick2 = parseInt(document.getElementById('quick2').value) || 20;
            const quick3 = parseInt(document.getElementById('quick3').value) || 50;
            
            cashierSettings.workflow.quickAmounts = [quick1, quick2, quick3];
            saveCashierSettings();
            updateQuickAmountButtons();
        }

        function updateQuickAmountButtons() {
            const buttons = document.querySelectorAll('[onclick^="addQuickAmount"]');
            buttons.forEach((btn, index) => {
                if (index < cashierSettings.workflow.quickAmounts.length) {
                    const amount = cashierSettings.workflow.quickAmounts[index];
                    btn.textContent = `+${amount}`;
                    btn.onclick = () => addQuickAmount(amount);
                }
            });
        }
        
        // Initialize cashier settings system
        function initializeCashierSettings() {
            loadCashierSettings();
            
            // Apply theme
            setTheme(cashierSettings.theme.mode);
            
            // Apply display settings
            applyTextScale(cashierSettings.display.textSize);
            
            // Apply numpad layout
            setNumpadLayout(cashierSettings.numpad.layout);
            
            // Apply workflow settings
            setDefaultPayment(cashierSettings.workflow.defaultPayment);
            updateQuickAmountButtons();
            
            // Update all UI selections to reflect current settings
            updateAllSettingsUI();
            
            console.log('Cashier settings initialized:', cashierSettings);
        }
        
        // Update all settings UI to show current selections
        function updateAllSettingsUI() {
            // Delay to ensure DOM elements are available
            setTimeout(() => {
                // Update theme selection
                updateThemeSelection(currentTheme);

                // Update text size selection
                updateTextSizeSelection(cashierSettings.display.textSize);

                // Update numpad layout selection
                updateNumpadLayoutSelection(cashierSettings.numpad.layout);

                // Update currency format selection
                updateCurrencyFormatSelection(cashierSettings.display.currencyFormat);

                // Update sound toggles
                updateSoundToggles();

                // Update default payment method
                updateDefaultPaymentSelection(cashierSettings.workflow.defaultPayment);

                console.log('Settings UI updated for:', cashierSettings.display.textSize);
            }, 200);
        }
        
        // Update theme selection in UI
        function updateThemeSelection(theme) {
            document.querySelectorAll('[id^="theme-"]').forEach(btn => {
                btn.style.background = 'var(--bg-primary)';
                btn.style.fontWeight = 'normal';
                btn.style.color = 'var(--text-primary)';
                btn.style.border = '1px solid transparent';
            });

            const selectedBtn = document.getElementById(`theme-${theme}`);
            if (selectedBtn) {
                selectedBtn.style.background = 'var(--accent-primary)';
                selectedBtn.style.fontWeight = 'bold';
                selectedBtn.style.color = 'white';
                selectedBtn.style.border = '2px solid var(--accent-primary)';
            }
        }

        // Update numpad layout selection in UI
        function updateNumpadLayoutSelection(layout) {
            document.querySelectorAll('[id^="layout-"]').forEach(btn => {
                btn.style.background = 'var(--bg-primary)';
                btn.style.fontWeight = 'normal';
                btn.style.color = 'var(--text-primary)';
            });

            const selectedBtn = document.getElementById(`layout-${layout}`);
            if (selectedBtn) {
                selectedBtn.style.background = 'var(--accent-primary)';
                selectedBtn.style.fontWeight = 'bold';
                selectedBtn.style.color = 'white';
            }
        }
        
        // Update currency format selection in UI
        function updateCurrencyFormatSelection(format) {
            document.querySelectorAll('[id^="currency-"]').forEach(btn => {
                btn.style.background = 'var(--bg-primary)';
                btn.style.fontWeight = 'normal';
                btn.style.color = 'var(--text-primary)';
            });
            
            const formatId = format.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
            const selectedBtn = document.getElementById(`currency-${formatId}`) || 
                              document.getElementById('currency-rm0-00'); // fallback
            if (selectedBtn) {
                selectedBtn.style.background = 'var(--accent-primary)';
                selectedBtn.style.fontWeight = 'bold';
                selectedBtn.style.color = 'white';
            }
        }
        
        // Update sound toggles in UI
        function updateSoundToggles() {
            const clicksToggle = document.querySelector('input[type="checkbox"][onchange*="clicks"]');
            const successToggle = document.querySelector('input[type="checkbox"][onchange*="success"]');
            
            if (clicksToggle) clicksToggle.checked = cashierSettings.sounds.clicks;
            if (successToggle) successToggle.checked = cashierSettings.sounds.success;
        }
        
        // Update default payment method selection in UI
        function updateDefaultPaymentSelection(method) {
            const defaultPaymentSelect = document.getElementById('default-payment');
            if (defaultPaymentSelect) {
                defaultPaymentSelect.value = method;
                console.log(`Default payment select updated to: ${method}`);
            }
        }
        
        // Text scaling helper
        function applyTextScale(size) {
            const scales = {
                'small': '0.8',
                'medium': '0.9', 
                'large': '1',
                'extra-large': '1.1'
            };
            const scale = scales[size] || '0.9';
            
            // Set CSS custom property for text scaling
            document.documentElement.style.setProperty('--text-scale', scale);
            
            // Update settings UI to reflect current selection
            updateTextSizeSelection(size);
            
            console.log(`Text scale applied: ${scale} (${size})`);
        }
        
        // Update text size selection in settings UI
        function updateTextSizeSelection(size) {
            const textSizeSelect = document.getElementById('text-size');
            if (textSizeSelect) {
                textSizeSelect.value = size;
                console.log(`Text size select updated to: ${size}`);
            }
        }
        
        // Initialize

        document.addEventListener('DOMContentLoaded', function() {
            // Apply saved theme immediately
            const savedTheme = localStorage.getItem('pos-theme') || 'colorful';
            document.body.setAttribute('data-theme', savedTheme);
            currentTheme = savedTheme;

            // Initialize Lucide icons
            lucide.createIcons();

            updateCartDisplay();
            setActiveTab(document.querySelector('.tab-btn.active'));

            // Initialize theme and settings with a small delay to ensure DOM is fully ready
            setTimeout(() => {
                // Load and apply cashier settings
                initializeCashierSettings();

                // Show all products and apply theme styling
                showAllProducts();
                // Initialize stock badges to ensure they work from first click
                updateAllStockBadges();
            }, 100);
            
            // Close settings modal when clicking outside
            document.addEventListener('click', function(e) {
                const settingsModal = document.getElementById('settings-modal');
                const receiptModal = document.getElementById('receipt-modal');

                if (settingsModal && !settingsModal.classList.contains('hidden') && e.target === settingsModal) {
                    settingsModal.classList.add('hidden');
                }

                if (receiptModal && !receiptModal.classList.contains('hidden') && e.target === receiptModal) {
                    receiptModal.classList.add('hidden');
                }
            });
            
            // Close modal when clicking outside
            const paymentModal = document.getElementById('payment-modal');
            if (paymentModal) {
                paymentModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closePaymentModal();
                    }
                });
            }
            
            // Clear Order Modal event listeners
            const clearOrderModal = document.getElementById('clear-order-modal');
            if (clearOrderModal) {
                // Close modal when clicking outside
                clearOrderModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cancelClearOrder();
                    }
                });
            }
            
            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('clear-order-modal');
                    if (modal && !modal.classList.contains('hidden')) {
                        cancelClearOrder();
                    }
                }
            });

            // Auto-focus search bar when user starts typing
            document.addEventListener('keydown', function(e) {
                const searchInput = document.getElementById('search-input');
                const paymentModal = document.getElementById('payment-modal');
                const settingsModal = document.getElementById('settings-modal');
                const clearOrderModal = document.getElementById('clear-order-modal');

                // Check if any modal is open
                const isModalOpen = (paymentModal && !paymentModal.classList.contains('hidden')) ||
                                   (settingsModal && !settingsModal.classList.contains('hidden')) ||
                                   (clearOrderModal && !clearOrderModal.classList.contains('hidden'));

                // Check if user is already typing in an input field
                const isTypingInInput = document.activeElement.tagName === 'INPUT' ||
                                       document.activeElement.tagName === 'TEXTAREA';

                // Check if currently focused on the search input specifically
                const isFocusedOnSearch = document.activeElement === searchInput;

                // If focused on search input, allow all normal keyboard behavior
                if (isFocusedOnSearch) {
                    return; // Let browser handle everything normally
                }

                // If focused on OTHER inputs (like payment modal), don't interfere
                if (isTypingInInput && !isFocusedOnSearch) {
                    return;
                }

                // Only capture if: not in modal, not already typing in other inputs
                if (!isModalOpen && !isTypingInInput && searchInput) {
                    // Exclude special navigation keys
                    const excludeKeys = ['Enter', 'Tab', 'Escape', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

                    // For printable characters, focus the search
                    if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && !excludeKeys.includes(e.key)) {
                        searchInput.focus();
                        // Let the default behavior add the character to the input
                    }
                    // For Backspace or Delete, only focus if search has content
                    else if ((e.key === 'Backspace' || e.key === 'Delete') && searchInput.value.length > 0) {
                        searchInput.focus();
                        // Let the browser handle the deletion naturally after focus
                    }
                }
            });
        });

        // Backup initialization on window load to ensure proper card styling
        window.addEventListener('load', function() {
            updateProductCardTheme(currentTheme);
        });
    </script>
</body>
</html>