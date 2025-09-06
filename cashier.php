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
                
                // Apply restaurant tax rate
                $tax_rate = $restaurant['tax_rate'] ?? 0.0850; // Default 8.5%
                $tax_amount = $subtotal * $tax_rate;
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
                $query = "INSERT INTO orders (restaurant_id, user_id, order_number, subtotal, tax_amount, total_amount, status, payment_method, payment_received, change_amount) VALUES (:restaurant_id, :user_id, :order_number, :subtotal, :tax_amount, :total, 'completed', :payment_method, :payment_received, :change_amount)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':order_number', $order_number);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':tax_amount', $tax_amount);
                $stmt->bindParam(':total', $total);
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
                
                $db->commit();
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

// Get active categories for current restaurant
$query = "SELECT name FROM categories WHERE restaurant_id = :restaurant_id AND is_active = 1 ORDER BY sort_order, name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$active_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get products for current restaurant - using JOIN to only get products from active categories
$query = "SELECT p.* FROM products p 
          INNER JOIN categories c ON p.category = c.name AND c.restaurant_id = p.restaurant_id 
          WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1 AND c.is_active = 1 
          ORDER BY c.sort_order, p.category, p.name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            transform: scale(1.02);
            transition: all 0.3s ease;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            color: white !important;
            font-weight: 600 !important;
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
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                        <!-- Theme Switcher -->
                        <div class="relative">
                            <button id="theme-toggle" class="flex items-center space-x-1 px-2 py-1 rounded-lg bg-white/50 hover:bg-white/80 transition-colors border border-indigo-200">
                                <span id="theme-icon" class="text-sm">🎨</span>
                                <span id="theme-text" class="text-xs font-medium text-gray-700 hidden sm:inline">Colorful</span>
                                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="theme-dropdown" class="absolute right-0 mt-1 w-32 bg-white rounded-lg shadow-lg border border-gray-200 z-50 hidden">
                                <button onclick="setTheme('colorful')" class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 rounded-t-lg flex items-center space-x-2">
                                    <span>🎨</span><span>Colorful</span>
                                </button>
                                <button onclick="setTheme('dark')" class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 flex items-center space-x-2">
                                    <span>🌙</span><span>Dark</span>
                                </button>
                                <button onclick="setTheme('minimal')" class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 flex items-center space-x-2">
                                    <span>⚪</span><span>Minimal</span>
                                </button>
                                <button onclick="setTheme('original')" class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 rounded-b-lg flex items-center space-x-2">
                                    <span>⚫</span><span>Original</span>
                                </button>
                            </div>
                        </div>
                        
                        <span class="text-xs sm:text-sm text-gray-600 hidden xs:inline">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                        <span class="text-xs sm:text-sm text-gray-600 xs:hidden"><?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                        <a href="logout.php" class="text-accent hover:text-red-600 text-xs sm:text-sm font-medium">Logout</a>
                    </div>
                </div>

                <!-- Category Tabs -->
                <div class="flex space-x-1 bg-white/50 p-1 rounded-lg shadow-sm">
                    <button onclick="showAllProducts(this)" class="tab-btn active px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-sm">All</button>
                    <?php foreach ($category_names as $category): 
                        $categoryColors = [
                            'Food' => 'from-red-400 to-pink-500',
                            'Drinks' => 'from-cyan-400 to-teal-500', 
                            'Dessert' => 'from-yellow-400 to-orange-500'
                        ];
                        $colorClass = $categoryColors[$category] ?? 'from-gray-400 to-gray-500';
                    ?>
                        <button onclick="showCategory('<?= strtolower($category) ?>', this)" class="tab-btn px-4 py-2 rounded-md font-medium text-sm transition-colors text-gray-600 hover:bg-gradient-to-r hover:<?= $colorClass ?> hover:text-white"><?= htmlspecialchars($category) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto scrollbar-hide p-4">
                <div class="product-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-3 sm:gap-4">
                    <?php foreach ($products as $product): 
                        $categoryBg = [
                            'Food' => 'from-red-100 to-pink-100 border-red-200',
                            'Drinks' => 'from-cyan-100 to-teal-100 border-cyan-200',
                            'Dessert' => 'from-yellow-100 to-orange-100 border-yellow-200'
                        ];
                        $categoryAccent = [
                            'Food' => 'from-red-400 to-pink-500',
                            'Drinks' => 'from-cyan-400 to-teal-500',
                            'Dessert' => 'from-yellow-400 to-orange-500'
                        ];
                        $bgClass = $categoryBg[$product['category']] ?? 'from-gray-100 to-gray-200 border-gray-200';
                        $accentClass = $categoryAccent[$product['category']] ?? 'from-gray-400 to-gray-500';
                    ?>
                        <div class="product-card theme-card rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 cursor-pointer border hover:scale-105 flex flex-col items-center justify-center text-center" 
                             data-category="<?= strtolower($product['category']) ?>"
                             data-name="<?= strtolower($product['name']) ?>"
                             data-bg-class="<?= $bgClass ?>"
                             data-accent-class="<?= $accentClass ?>"
                             onclick="addToCart(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['price'] ?>, this)">
                            <div class="aspect-square theme-card-header rounded-t-xl flex items-center justify-center relative overflow-hidden" data-accent-class="<?= $accentClass ?>">
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
                            </div>
                            <div class="p-2.5 theme-card-content rounded-b-xl" style="background: var(--bg-secondary)">
                                <h3 class="font-medium text-sm truncate leading-tight" style="color: var(--text-primary)"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="font-bold text-base mt-1" style="color: var(--accent-primary)"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($product['price'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bottom Navigation -->
            <div class="theme-transition px-4 py-2" style="background: var(--bg-secondary); border-top: 1px solid var(--border-primary)">
                <div class="flex justify-center space-x-6">
                    <button class="flex flex-col items-center py-1" style="color: var(--accent-primary)">
                        <div class="w-5 h-5 mb-1">📋</div>
                        <span class="text-xs font-medium">Orders</span>
                    </button>
                    <button class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <div class="w-5 h-5 mb-1">📊</div>
                        <span class="text-xs font-medium">Reports</span>
                    </button>
                    <button class="flex flex-col items-center py-1" style="color: var(--text-secondary)" onclick="window.location.href='<?= $_SESSION['role'] === 'admin' ? 'admin.php' : '#' ?>'">
                        <div class="w-5 h-5 mb-1">⚙️</div>
                        <span class="text-xs font-medium">Settings</span>
                    </button>
                    <button class="flex flex-col items-center py-1" style="color: var(--text-secondary)">
                        <div class="w-5 h-5 mb-1">🔔</div>
                        <span class="text-xs font-medium">Alerts</span>
                    </button>
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
                <div class="flex justify-between text-lg font-bold">
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
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-auto max-h-[85vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="text-center p-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-primary mb-1">Payment</h3>
                <div id="modal-total" class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($restaurant['currency']) ?>0.00</div>
            </div>

            <!-- Payment Method Selection -->
            <div class="p-4">
                <div class="mb-3">
                    <div class="flex space-x-2">
                        <button id="cash-btn" onclick="selectPaymentMethod('cash')" class="flex-1 bg-primary text-white px-3 py-2 rounded-lg text-sm font-medium flex items-center justify-center space-x-1 transition-colors">
                            <span>💵</span>
                            <span>Cash</span>
                        </button>
                        <button id="qr-btn" onclick="selectPaymentMethod('qr')" class="flex-1 bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium flex items-center justify-center space-x-1 transition-colors">
                            <span>📱</span>
                            <span>QR Code</span>
                        </button>
                    </div>
                </div>

                <!-- Cash Payment Section -->
                <div id="cash-section" class="space-y-3">
                    <div>
                        <input type="text" id="amount-input" class="w-full text-xl font-bold text-center border-2 border-gray-200 rounded-lg py-2 focus:border-primary focus:outline-none" placeholder="0.00" readonly>
                    </div>

                    <!-- Number Pad -->
                    <div class="number-pad grid grid-cols-3 gap-2">
                        <button onclick="addDigit('7')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">7</button>
                        <button onclick="addDigit('8')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">8</button>
                        <button onclick="addDigit('9')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">9</button>
                        
                        <button onclick="addDigit('4')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">4</button>
                        <button onclick="addDigit('5')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">5</button>
                        <button onclick="addDigit('6')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">6</button>
                        
                        <button onclick="addDigit('1')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">1</button>
                        <button onclick="addDigit('2')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">2</button>
                        <button onclick="addDigit('3')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">3</button>
                        
                        <button onclick="addDigit('0')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">0</button>
                        <button onclick="addDigit('.')" class="number-btn bg-gray-100 hover:bg-gray-200 py-3 rounded-lg font-semibold">.</button>
                        <button onclick="clearAmount()" class="bg-red-400 hover:bg-red-500 text-white py-3 rounded-lg font-semibold text-sm">C</button>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="flex space-x-1">
                        <button onclick="setExactAmount()" class="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-medium">Exact</button>
                        <button onclick="addQuickAmount(10)" class="bg-primary hover:bg-secondary text-white px-2 py-1 rounded text-xs font-medium">+10</button>
                        <button onclick="addQuickAmount(20)" class="bg-primary hover:bg-secondary text-white px-2 py-1 rounded text-xs font-medium">+20</button>
                        <button onclick="addQuickAmount(50)" class="bg-primary hover:bg-secondary text-white px-2 py-1 rounded text-xs font-medium">+50</button>
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
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                        <div class="w-12 h-12 bg-primary rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <div class="grid grid-cols-3 gap-1">
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                                <div class="w-1 h-1 bg-white rounded-sm"></div>
                            </div>
                        </div>
                        <div class="text-primary font-medium text-sm">Customer scans QR code</div>
                        <div id="qr-total" class="text-xs text-gray-600 mt-1">Total: <?= htmlspecialchars($restaurant['currency']) ?>0.00</div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex space-x-2 p-4 border-t border-gray-100">
                <button onclick="closePaymentModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 px-3 rounded-lg font-medium text-sm">Cancel</button>
                <button id="complete-payment-btn" onclick="completePayment()" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded-lg font-medium text-sm">Complete</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
        <span id="toast-message"></span>
    </div>

    <script>
        const csrfToken = '<?= Security::generateCSRFToken() ?>';
        const restaurantConfig = {
            currency: '<?= htmlspecialchars($restaurant['currency']) ?>',
            taxRate: <?= $restaurant['tax_rate'] ?? 0.0850 ?>,
            name: '<?= htmlspecialchars($restaurant['name']) ?>'
        };
        let cart = <?= json_encode($_SESSION['cart'] ?? []) ?>;
        let currentTotal = 0;
        let currentPaymentMethod = 'cash';
        let amountTendered = '';
        
        // Currency formatting helper
        function formatCurrency(amount) {
            return restaurantConfig.currency + amount.toFixed(2);
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

        function addToCart(productId, productName, productPrice, buttonElement) {
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
            
            buttonElement.innerHTML = '<div class="loading-spinner"></div>';
            buttonElement.classList.add('loading');
            
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
                    updateCartDisplay();
                    
                    // Success state - but allow immediate re-clicking
                    buttonElement.innerHTML = '✓ Added!';
                    buttonElement.classList.remove('loading');
                    buttonElement.classList.add('success');
                    
                    showToast(`${productName} added`, false);
                    
                    // Reset to normal after short delay, but don't disable clicking
                    buttonElement.resetTimeout = setTimeout(() => {
                        if (!buttonElement.classList.contains('loading')) {
                            buttonElement.innerHTML = buttonElement.originalContent;
                            buttonElement.classList.remove('success');
                        }
                        buttonElement.resetTimeout = null;
                    }, 800); // Shorter delay
                } else {
                    if (buttonElement.resetTimeout) {
                        clearTimeout(buttonElement.resetTimeout);
                        buttonElement.resetTimeout = null;
                    }
                    buttonElement.innerHTML = buttonElement.originalContent;
                    buttonElement.classList.remove('loading');
                    showToast(data.message, true);
                }
            })
            .catch(error => {
                if (buttonElement.resetTimeout) {
                    clearTimeout(buttonElement.resetTimeout);
                    buttonElement.resetTimeout = null;
                }
                buttonElement.innerHTML = buttonElement.originalContent;
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
                                <button onclick="updateQuantity(${id}, ${quantity + 1})" class="w-6 h-6 theme-cart-btn rounded flex items-center justify-center text-sm" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary);">+</button>
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
            
            // Apply tax rate (same as backend calculation)
            const tax_rate = <?= $restaurant['tax_rate'] ?? 0.0850 ?>;
            const tax_amount = subtotal * tax_rate;
            const total = subtotal + tax_amount;
            currentTotal = total;
            
            cartItems.innerHTML = html;
            
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
            
            // Reset payment form
            selectPaymentMethod('cash');
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
                cashBtn.classList.remove('bg-gray-200', 'text-gray-700');
                cashBtn.classList.add('bg-primary', 'text-white');
                qrBtn.classList.remove('bg-primary', 'text-white');
                qrBtn.classList.add('bg-gray-200', 'text-gray-700');
                
                cashSection.classList.remove('hidden');
                qrSection.classList.add('hidden');
            } else {
                qrBtn.classList.remove('bg-gray-200', 'text-gray-700');
                qrBtn.classList.add('bg-primary', 'text-white');
                cashBtn.classList.remove('bg-primary', 'text-white');
                cashBtn.classList.add('bg-gray-200', 'text-gray-700');
                
                qrSection.classList.remove('hidden');
                cashSection.classList.add('hidden');
            }
            
            updatePaymentButton();
        }

        function addDigit(digit) {
            if (digit === '.' && amountTendered.includes('.')) return;
            if (digit === '.' && amountTendered === '') amountTendered = '0';
            
            amountTendered += digit;
            updateAmountDisplay();
        }

        function clearAmount() {
            amountTendered = '';
            updateAmountDisplay();
        }

        function setExactAmount() {
            // Ensure same precision as backend calculation
            amountTendered = (Math.round(currentTotal * 100) / 100).toFixed(2);
            updateAmountDisplay();
        }

        function addQuickAmount(amount) {
            const newAmount = currentTotal + amount;
            amountTendered = newAmount.toFixed(2);
            updateAmountDisplay();
        }

        function updateAmountDisplay() {
            const amountInput = document.getElementById('amount-input');
            const changeDisplay = document.getElementById('change-display');
            const changeAmount = document.getElementById('change-amount');
            const insufficientWarning = document.getElementById('insufficient-warning');
            
            if (amountInput) amountInput.value = amountTendered;
            
            if (amountTendered && !isNaN(parseFloat(amountTendered))) {
                const tendered = parseFloat(amountTendered);
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
            
            if (currentPaymentMethod === 'qr') {
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
                const tendered = parseFloat(amountTendered || 0);
                
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
            } else {
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
                if (data.success) {
                    cart = {};
                    updateCartDisplay();
                    closePaymentModal();
                    
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
                showToast('Payment failed', true);
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
            if (Object.keys(cart).length > 0 && confirm('Clear all items from order?')) {
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
        }
        
        function updateProductCardTheme(theme) {
            const productCards = document.querySelectorAll('.product-card');
            const categoryTabs = document.querySelectorAll('.tab-btn');
            
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateCartDisplay();
            setActiveTab(document.querySelector('.tab-btn.active'));
            
            // Initialize theme with a small delay to ensure DOM is fully ready
            setTimeout(() => {
                setTheme(currentTheme);
                // Show all products and apply theme styling
                showAllProducts();
            }, 100);
            
            // Theme toggle button
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleThemeDropdown);
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.getElementById('theme-dropdown');
                const toggle = document.getElementById('theme-toggle');
                if (dropdown && !dropdown.contains(e.target) && !toggle.contains(e.target)) {
                    dropdown.classList.add('hidden');
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
        });

        // Backup initialization on window load to ensure proper card styling
        window.addEventListener('load', function() {
            updateProductCardTheme(currentTheme);
        });
    </script>
</body>
</html>