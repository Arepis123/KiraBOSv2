<?php
require_once 'config.php';

echo "<h2>📦 Stock Management Setup</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Step 1: Check and add stock fields to products table
    echo "<h3>Step 1: Database Schema Update</h3>";
    
    // Check if stock columns exist
    $query = "DESCRIBE products";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_columns = array_column($columns, 'Field');
    $stock_columns = [
        'track_stock' => 'TINYINT(1) DEFAULT 0',
        'stock_quantity' => 'DECIMAL(10,2) DEFAULT 0',
        'min_stock_level' => 'DECIMAL(10,2) DEFAULT 5',
        'max_stock_level' => 'DECIMAL(10,2) DEFAULT 100',
        'stock_unit' => 'VARCHAR(20) DEFAULT "pieces"'
    ];
    
    foreach ($stock_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            echo "<p style='color: orange;'>➕ Adding column: $column</p>";
            
            $query = "ALTER TABLE products ADD COLUMN $column $definition";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            echo "<p style='color: green;'>✅ Column $column added successfully!</p>";
        } else {
            echo "<p style='color: green;'>✅ Column $column already exists</p>";
        }
    }
    
    // Step 2: Show current products with stock status
    echo "<h3>Step 2: Current Products Stock Status</h3>";
    
    $query = "SELECT id, name, category, track_stock, stock_quantity, min_stock_level, stock_unit FROM products ORDER BY category, name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p style='color: orange;'>⚠️ No products found in database</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Name</th><th>Category</th><th>Track Stock</th><th>Current Stock</th><th>Min Level</th><th>Unit</th><th>Status</th>";
        echo "</tr>";
        
        foreach ($products as $product) {
            $track_stock = $product['track_stock'] ? 'Yes' : 'No';
            $stock_status = '';
            $status_color = 'black';
            
            if ($product['track_stock']) {
                if ($product['stock_quantity'] <= 0) {
                    $stock_status = 'Out of Stock';
                    $status_color = 'red';
                } elseif ($product['stock_quantity'] <= $product['min_stock_level']) {
                    $stock_status = 'Low Stock';
                    $status_color = 'orange';
                } else {
                    $stock_status = 'Good Stock';
                    $status_color = 'green';
                }
            } else {
                $stock_status = 'Not Tracked';
                $status_color = 'gray';
            }
            
            echo "<tr>";
            echo "<td>" . $product['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($product['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($product['category']) . "</td>";
            echo "<td>" . $track_stock . "</td>";
            echo "<td>" . ($product['track_stock'] ? number_format($product['stock_quantity'], 2) : '-') . "</td>";
            echo "<td>" . ($product['track_stock'] ? number_format($product['min_stock_level'], 2) : '-') . "</td>";
            echo "<td>" . ($product['track_stock'] ? htmlspecialchars($product['stock_unit']) : '-') . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>$stock_status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Step 3: Sample stock setup suggestions
    echo "<h3>Step 3: Stock Tracking Suggestions</h3>";
    echo "<div style='background: #e3f2fd; border: 1px solid #2196f3; color: #0d47a1; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>💡 Recommended for Stock Tracking:</h4>";
    echo "<ul>";
    echo "<li><strong>Beverages:</strong> Coca Cola, Sprite, Bottled Water, Canned Drinks</li>";
    echo "<li><strong>Packaged Items:</strong> Chips, Crackers, Instant Noodles</li>";
    echo "<li><strong>Ingredients:</strong> Coffee Beans, Tea Bags, Sugar Packets</li>";
    echo "<li><strong>Frozen Items:</strong> Ice Cream, Frozen Vegetables</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3e0; border: 1px solid #ff9800; color: #e65100; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🍽️ Usually NOT Tracked:</h4>";
    echo "<ul>";
    echo "<li><strong>Made-to-Order:</strong> Nasi Lemak, Chicken Rice, Fresh Salads</li>";
    echo "<li><strong>Bulk Prepared:</strong> Soup of the Day, Daily Specials</li>";
    echo "<li><strong>Custom Items:</strong> Build-your-own dishes</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>🎉 Stock Management Setup Complete!</h3>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>What's Ready:</h4>";
    echo "<ul>";
    echo "<li>✅ <strong>Database Schema:</strong> Stock fields added to products table</li>";
    echo "<li>✅ <strong>Flexible System:</strong> Toggle stock tracking per product</li>";
    echo "<li>✅ <strong>Stock Levels:</strong> Current, minimum, and maximum levels</li>";
    echo "<li>✅ <strong>Units Support:</strong> Pieces, bottles, kg, liters, etc.</li>";
    echo "<li>➡️ <strong>Next:</strong> Update admin forms with stock management</li>";
    echo "<li>➡️ <strong>Next:</strong> Add stock reduction logic to cashier</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p>";
    echo "<a href='admin.php?page=menu' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Update Product Settings</a>";
    echo "<a href='admin.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Dashboard</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>