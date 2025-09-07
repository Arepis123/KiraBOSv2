<?php
// Test script to debug order details
require_once 'config.php';

echo "<h1>Order Details Debug</h1>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check if we can connect to database
    echo "<p>✓ Database connection successful</p>";
    
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        echo "<p>✓ User is logged in: " . $_SESSION['user_id'] . "</p>";
    } else {
        echo "<p>❌ User is not logged in</p>";
    }
    
    // Check restaurant ID
    if (isset($_SESSION['restaurant_id'])) {
        echo "<p>✓ Restaurant ID: " . $_SESSION['restaurant_id'] . "</p>";
    } else {
        echo "<p>❌ No restaurant ID</p>";
    }
    
    // Get some sample orders
    $restaurant_id = Security::getRestaurantId();
    $query = "SELECT id, order_number, total_amount FROM orders WHERE restaurant_id = :restaurant_id ORDER BY created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Recent Orders:</h2>";
    foreach ($orders as $order) {
        echo "<p>Order ID: " . $order['id'] . " | Number: " . $order['order_number'] . " | Total: RM" . $order['total_amount'] . "</p>";
        
        // Test order items query
        $items_query = "SELECT 
            oi.quantity,
            oi.price,
            oi.subtotal,
            p.name as product_name,
            p.category
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.id = :order_id 
            AND o.restaurant_id = :restaurant_id
            ORDER BY p.name";
        
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bindParam(':order_id', $order['id']);
        $items_stmt->bindParam(':restaurant_id', $restaurant_id);
        $items_stmt->execute();
        
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>Items for Order " . $order['id'] . ": " . print_r($items, true) . "</pre>";
        break; // Just test the first order
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>