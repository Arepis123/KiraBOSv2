<?php
require_once 'config.php';

echo "<h2>🔍 Category Performance Debug</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    $restaurant_id = 1; // Assuming restaurant_id = 1
    
    echo "<h3>Step 1: Check Orders Table</h3>";
    $query = "SELECT COUNT(*) as total_orders FROM orders WHERE restaurant_id = :restaurant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
    echo "<p><strong>Total Orders:</strong> $total_orders</p>";
    
    if ($total_orders > 0) {
        // Check recent orders
        echo "<h4>Recent Orders (Last 10):</h4>";
        $query = "SELECT id, order_number, total_amount, status, created_at FROM orders WHERE restaurant_id = :restaurant_id ORDER BY created_at DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->execute();
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr>";
        foreach ($recent_orders as $order) {
            echo "<tr>";
            echo "<td>" . $order['id'] . "</td>";
            echo "<td>" . $order['order_number'] . "</td>";
            echo "<td>RM" . number_format($order['total_amount'], 2) . "</td>";
            echo "<td>" . $order['status'] . "</td>";
            echo "<td>" . $order['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Step 2: Check Order Items</h3>";
    $query = "SELECT COUNT(*) as total_items FROM order_items oi 
              INNER JOIN orders o ON oi.order_id = o.id 
              WHERE o.restaurant_id = :restaurant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total_items'];
    echo "<p><strong>Total Order Items:</strong> $total_items</p>";
    
    echo "<h3>Step 3: Check Products and Categories</h3>";
    $query = "SELECT p.category, COUNT(*) as product_count FROM products p WHERE p.restaurant_id = :restaurant_id GROUP BY p.category";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $product_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Products by Category:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Category</th><th>Product Count</th></tr>";
    foreach ($product_categories as $cat) {
        echo "<tr><td>" . $cat['category'] . "</td><td>" . $cat['product_count'] . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Step 4: Check Categories Table</h3>";
    $query = "SELECT name, color, is_active FROM categories WHERE restaurant_id = :restaurant_id ORDER BY sort_order, name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Categories Table:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Name</th><th>Color</th><th>Active</th></tr>";
    foreach ($categories as $cat) {
        $status = $cat['is_active'] ? 'Yes' : 'No';
        echo "<tr><td>" . $cat['name'] . "</td><td><div style='width: 30px; height: 20px; background: " . $cat['color'] . "; border: 1px solid #ccc;'></div></td><td>" . $status . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Step 5: Test Current Category Performance Query</h3>";
    $query = "SELECT 
        p.category,
        c.color,
        COUNT(oi.id) as total_items_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue,
        COALESCE(SUM(oi.quantity), 0) as total_quantity_sold
        FROM products p
        LEFT JOIN categories c ON p.category = c.name AND c.restaurant_id = :restaurant_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1
        GROUP BY p.category, c.color
        HAVING COUNT(DISTINCT p.id) > 0
        ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Category Performance Query Result:</h4>";
    if (empty($category_sales)) {
        echo "<p style='color: red;'><strong>No data returned by category performance query!</strong></p>";
        
        echo "<h4>Debug: Simplified Query (All Orders)</h4>";
        $query = "SELECT 
            p.category,
            COUNT(oi.id) as total_items_sold,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COALESCE(SUM(oi.quantity), 0) as total_quantity_sold
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
            WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1
            GROUP BY p.category
            ORDER BY total_revenue DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->execute();
        $simplified_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Category</th><th>Items Sold</th><th>Revenue</th><th>Quantity</th></tr>";
        foreach ($simplified_results as $result) {
            echo "<tr>";
            echo "<td>" . $result['category'] . "</td>";
            echo "<td>" . $result['total_items_sold'] . "</td>";
            echo "<td>RM" . number_format($result['total_revenue'], 2) . "</td>";
            echo "<td>" . $result['total_quantity_sold'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Category</th><th>Color</th><th>Items Sold</th><th>Revenue</th><th>Quantity</th></tr>";
        foreach ($category_sales as $result) {
            echo "<tr>";
            echo "<td>" . $result['category'] . "</td>";
            echo "<td><div style='width: 30px; height: 20px; background: " . ($result['color'] ?? '#ccc') . "; border: 1px solid #ccc;'></div></td>";
            echo "<td>" . $result['total_items_sold'] . "</td>";
            echo "<td>RM" . number_format($result['total_revenue'], 2) . "</td>";
            echo "<td>" . $result['total_quantity_sold'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Step 6: Check Date Range</h3>";
    $today = date('Y-m-d');
    $six_days_ago = date('Y-m-d', strtotime('-6 days'));
    echo "<p><strong>Date Range:</strong> $six_days_ago to $today</p>";
    
    $query = "SELECT DATE(created_at) as order_date, COUNT(*) as orders_count 
              FROM orders 
              WHERE restaurant_id = :restaurant_id AND status = 'completed' 
              AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              GROUP BY DATE(created_at) 
              ORDER BY order_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $daily_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Orders by Date (Last 7 days):</h4>";
    if (empty($daily_orders)) {
        echo "<p style='color: red;'>No completed orders found in the last 7 days!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Date</th><th>Orders</th></tr>";
        foreach ($daily_orders as $day) {
            echo "<tr><td>" . $day['order_date'] . "</td><td>" . $day['orders_count'] . "</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>