<?php
// get_order_details.php - AJAX endpoint for order details
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Use Security class validation
Security::requireAdmin();
$restaurant_id = Security::getRestaurantId();

// Validate CSRF token (we'll skip for AJAX, but in production you should include it)
if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

$order_id = (int)$_POST['order_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // First verify the order belongs to the current restaurant
    $order_check = "SELECT id FROM orders WHERE id = :order_id AND restaurant_id = :restaurant_id";
    $check_stmt = $db->prepare($order_check);
    $check_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
        exit;
    }
    
    // Get order items with product details
    $query = "SELECT oi.*, 
                     COALESCE(oi.product_name, p.name) as product_name,
                     p.category,
                     oi.product_price as price
              FROM order_items oi 
              LEFT JOIN products p ON oi.product_id = p.id AND p.restaurant_id = :restaurant_id
              WHERE oi.order_id = :order_id
              ORDER BY oi.id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($items) {
        echo json_encode([
            'success' => true, 
            'items' => $items,
            'count' => count($items)
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'items' => [],
            'count' => 0,
            'message' => 'No items found for this order'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>