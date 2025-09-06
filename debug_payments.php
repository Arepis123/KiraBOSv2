<?php
require_once 'config.php';

// Simple debug page to check payment methods
$database = Database::getInstance();
$db = $database->getConnection();

echo "<h2>Payment Methods Debug</h2>";

try {
    // Get all unique payment methods
    $query = "SELECT DISTINCT payment_method, COUNT(*) as count FROM orders GROUP BY payment_method ORDER BY count DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Payment Methods in Database:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Payment Method</th><th>Count</th></tr>";
    
    foreach ($payment_methods as $method) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($method['payment_method']) . "</td>";
        echo "<td>" . $method['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get recent orders with payment methods
    $query = "SELECT id, payment_method, total_amount, created_at FROM orders ORDER BY created_at DESC LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Recent Orders (Last 10):</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Order ID</th><th>Payment Method</th><th>Amount</th><th>Date</th></tr>";
    
    foreach ($recent_orders as $order) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . htmlspecialchars($order['payment_method']) . "</td>";
        echo "<td>RM" . number_format($order['total_amount'], 2) . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>