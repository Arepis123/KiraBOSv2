<?php
require_once 'config.php';

echo "<h2>🎮 Enable Demo Stock Tracking</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Enable stock tracking for some common products
    $updates = [
        ['name' => 'Coca Cola', 'track_stock' => 1, 'stock_quantity' => 20, 'min_stock_level' => 5, 'max_stock_level' => 25, 'stock_unit' => 'bottles'],
        ['name' => 'Sprite', 'track_stock' => 1, 'stock_quantity' => 15, 'min_stock_level' => 3, 'max_stock_level' => 20, 'stock_unit' => 'bottles'],
        ['name' => 'Ice Cream', 'track_stock' => 1, 'stock_quantity' => 10, 'min_stock_level' => 2, 'max_stock_level' => 12, 'stock_unit' => 'pieces'],
        ['name' => 'French Fries', 'track_stock' => 1, 'stock_quantity' => 8, 'min_stock_level' => 2, 'max_stock_level' => 10, 'stock_unit' => 'portions']
    ];
    
    $query = "UPDATE products SET track_stock = :track_stock, stock_quantity = :stock_quantity, min_stock_level = :min_stock_level, max_stock_level = :max_stock_level, stock_unit = :stock_unit WHERE name = :name";
    $stmt = $db->prepare($query);
    
    foreach ($updates as $update) {
        $stmt->execute($update);
        $rowsAffected = $stmt->rowCount();
        
        if ($rowsAffected > 0) {
            echo "<p style='color: green;'>✅ Enabled stock tracking for: " . htmlspecialchars($update['name']) . " ({$update['stock_quantity']} {$update['stock_unit']})</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Product not found: " . htmlspecialchars($update['name']) . "</p>";
        }
    }
    
    // Show current products with stock tracking
    echo "<h3>📊 Products with Stock Tracking Enabled</h3>";
    $query = "SELECT name, track_stock, stock_quantity, min_stock_level, max_stock_level, stock_unit FROM products WHERE track_stock = 1 ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($products)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Product</th><th>Current Stock</th><th>Min Level</th><th>Max Level</th><th>Unit</th></tr>";
        
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($product['name']) . "</strong></td>";
            echo "<td>" . number_format($product['stock_quantity'], 0) . "</td>";
            echo "<td>" . number_format($product['min_stock_level'], 0) . "</td>";
            echo "<td>" . number_format($product['max_stock_level'], 0) . "</td>";
            echo "<td>" . htmlspecialchars($product['stock_unit']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<p style='margin: 20px 0;'>";
    echo "<a href='cashier.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test in Cashier</a>";
    echo "<a href='admin.php?page=menu' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Admin Menu</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>