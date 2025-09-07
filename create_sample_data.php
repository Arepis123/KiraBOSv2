<?php
require_once 'config.php';

echo "<h2>🎯 Creating Sample Data for Category Performance</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    $restaurant_id = 1; // Assuming restaurant_id = 1
    
    // Step 1: Ensure we have categories
    echo "<h3>Step 1: Setting up Categories</h3>";
    
    // Check if categories exist
    $query = "SELECT COUNT(*) as count FROM categories WHERE restaurant_id = :restaurant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $category_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($category_count == 0) {
        echo "<p>Creating default categories...</p>";
        $default_categories = [
            ['name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'color' => '#FF6B6B'],
            ['name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'color' => '#4ECDC4'],
            ['name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'color' => '#FFE66D']
        ];
        
        foreach ($default_categories as $index => $cat) {
            $query = "INSERT INTO categories (restaurant_id, name, description, icon, color, sort_order, is_active) VALUES (:restaurant_id, :name, :description, :icon, :color, :sort_order, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':name', $cat['name']);
            $stmt->bindParam(':description', $cat['description']);
            $stmt->bindParam(':icon', $cat['icon']);
            $stmt->bindParam(':color', $cat['color']);
            $stmt->bindParam(':sort_order', $index + 1);
            $stmt->execute();
            echo "<p>✅ Created category: " . $cat['name'] . " (" . $cat['color'] . ")</p>";
        }
    } else {
        echo "<p>✅ Categories already exist ($category_count found)</p>";
    }
    
    // Step 2: Create sample products
    echo "<h3>Step 2: Creating Sample Products</h3>";
    
    // Check if products exist
    $query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $product_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($product_count < 10) {
        echo "<p>Creating sample products...</p>";
        $sample_products = [
            // Food items
            ['name' => 'Nasi Lemak', 'category' => 'Food', 'price' => 8.50, 'description' => 'Traditional Malaysian rice dish'],
            ['name' => 'Chicken Rice', 'category' => 'Food', 'price' => 7.50, 'description' => 'Hainanese chicken rice'],
            ['name' => 'Beef Rendang', 'category' => 'Food', 'price' => 12.00, 'description' => 'Spicy beef curry'],
            ['name' => 'Mee Goreng', 'category' => 'Food', 'price' => 6.50, 'description' => 'Fried noodles'],
            
            // Drinks
            ['name' => 'Teh Tarik', 'category' => 'Drinks', 'price' => 3.00, 'description' => 'Pulled milk tea'],
            ['name' => 'Kopi O', 'category' => 'Drinks', 'price' => 2.50, 'description' => 'Black coffee'],
            ['name' => 'Fresh Orange Juice', 'category' => 'Drinks', 'price' => 4.50, 'description' => 'Freshly squeezed orange juice'],
            
            // Desserts
            ['name' => 'Cendol', 'category' => 'Dessert', 'price' => 4.00, 'description' => 'Traditional shaved ice dessert'],
            ['name' => 'Kuih Lapis', 'category' => 'Dessert', 'price' => 2.50, 'description' => 'Layered cake'],
            ['name' => 'Ice Kacang', 'category' => 'Dessert', 'price' => 5.00, 'description' => 'Mixed ice dessert']
        ];
        
        foreach ($sample_products as $product) {
            $query = "INSERT INTO products (restaurant_id, name, category, price, description, is_active) VALUES (:restaurant_id, :name, :category, :price, :description, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':name', $product['name']);
            $stmt->bindParam(':category', $product['category']);
            $stmt->bindParam(':price', $product['price']);
            $stmt->bindParam(':description', $product['description']);
            $stmt->execute();
            echo "<p>✅ Created product: " . $product['name'] . " (RM" . number_format($product['price'], 2) . ")</p>";
        }
    } else {
        echo "<p>✅ Products already exist ($product_count found)</p>";
    }
    
    // Step 3: Create sample orders
    echo "<h3>Step 3: Creating Sample Orders</h3>";
    
    // Get all products
    $query = "SELECT * FROM products WHERE restaurant_id = :restaurant_id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p style='color: red;'>❌ No products found to create orders!</p>";
        exit;
    }
    
    // Create orders for the last 7 days
    $orders_created = 0;
    for ($day = 6; $day >= 0; $day--) {
        $order_date = date('Y-m-d H:i:s', strtotime("-{$day} days"));
        $daily_orders = rand(3, 8); // 3-8 orders per day
        
        for ($i = 0; $i < $daily_orders; $i++) {
            // Create order
            $order_number = 'ORD' . date('Ymd', strtotime($order_date)) . sprintf('%04d', rand(1000, 9999));
            $payment_method = rand(0, 1) ? 'Cash' : 'QR Code';
            
            $query = "INSERT INTO orders (restaurant_id, user_id, order_number, subtotal, tax_amount, total_amount, status, payment_method, payment_received, change_amount, created_at) VALUES (:restaurant_id, 1, :order_number, 0, 0, 0, 'completed', :payment_method, 0, 0, :created_at)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':order_number', $order_number);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':created_at', $order_date);
            $stmt->execute();
            
            $order_id = $db->lastInsertId();
            
            // Add 2-5 random items to each order
            $items_count = rand(2, 5);
            $order_total = 0;
            
            for ($j = 0; $j < $items_count; $j++) {
                $product = $products[rand(0, count($products) - 1)];
                $quantity = rand(1, 3);
                $subtotal = $product['price'] * $quantity;
                $order_total += $subtotal;
                
                $query = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (:order_id, :product_id, :product_name, :product_price, :quantity, :subtotal)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':product_id', $product['id']);
                $stmt->bindParam(':product_name', $product['name']);
                $stmt->bindParam(':product_price', $product['price']);
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->execute();
            }
            
            // Update order totals
            $tax_amount = $order_total * 0.085; // 8.5% tax
            $total_amount = $order_total + $tax_amount;
            
            $query = "UPDATE orders SET subtotal = :subtotal, tax_amount = :tax_amount, total_amount = :total_amount, payment_received = :payment_received WHERE id = :order_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':subtotal', $order_total);
            $stmt->bindParam(':tax_amount', $tax_amount);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':payment_received', $total_amount);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            $orders_created++;
        }
        
        echo "<p>✅ Created $daily_orders orders for " . date('Y-m-d', strtotime($order_date)) . "</p>";
    }
    
    echo "<h3>🎉 Sample Data Creation Complete!</h3>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Summary:</h4>";
    echo "<ul>";
    echo "<li>✅ Categories created/verified with custom colors</li>";
    echo "<li>✅ " . count($sample_products) . " sample products created</li>";
    echo "<li>✅ $orders_created sample orders created over the last 7 days</li>";
    echo "<li>✅ Order items linked to products with proper categories</li>";
    echo "</ul>";
    echo "<p><strong>Now go check your admin dashboard - the Category Performance chart should show data!</strong></p>";
    echo "</div>";
    
    echo "<p><a href='admin.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>