<?php
require_once 'config.php';

echo "<h2>🔍 Debug Product 23 (Ice Cream)</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check product 23 details
    $query = "SELECT * FROM products WHERE id = 23";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "<h3>Product 23 Details:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($product as $field => $value) {
            echo "<tr><td><strong>$field</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
        
        echo "<h3>Stock Tracking Status:</h3>";
        if ($product['track_stock']) {
            echo "<p style='color: green;'>✅ Stock tracking is ENABLED</p>";
            echo "<p>Current stock: " . ($product['stock_quantity'] ?? 0) . "</p>";
            echo "<p>Min level: " . ($product['min_stock_level'] ?? 0) . "</p>";
            echo "<p>Max level: " . ($product['max_stock_level'] ?? 0) . "</p>";
            echo "<p>Unit: " . htmlspecialchars($product['stock_unit'] ?? 'N/A') . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Stock tracking is DISABLED</p>";
            echo "<p><strong>This is why no badge is showing!</strong></p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Product with ID 23 not found!</p>";
    }
    
    // Show all products with their IDs and stock status
    echo "<h3>All Products with Stock Status:</h3>";
    $query = "SELECT id, name, track_stock, stock_quantity FROM products ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Stock Tracking</th><th>Current Stock</th></tr>";
    
    foreach ($products as $prod) {
        $trackingStatus = $prod['track_stock'] ? 'YES' : 'NO';
        $statusColor = $prod['track_stock'] ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $prod['id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($prod['name']) . "</strong></td>";
        echo "<td style='color: $statusColor;'>" . $trackingStatus . "</td>";
        echo "<td>" . ($prod['track_stock'] ? $prod['stock_quantity'] : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>