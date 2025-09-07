<?php
require_once 'config.php';

echo "<h2>🖼️ Product Images Setup</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Step 1: Add image field to products table
    echo "<h3>Step 1: Database Schema Update</h3>";
    
    // Check if image column exists
    $query = "DESCRIBE products";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasImageColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'image') {
            $hasImageColumn = true;
            break;
        }
    }
    
    if ($hasImageColumn) {
        echo "<p style='color: green;'>✅ Image column already exists!</p>";
    } else {
        echo "<p style='color: orange;'>➕ Adding image column to products table...</p>";
        
        $query = "ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER description";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        echo "<p style='color: green;'>✅ Image column added successfully!</p>";
    }
    
    // Step 2: Create uploads directory
    echo "<h3>Step 2: File System Setup</h3>";
    
    $upload_dir = __DIR__ . '/uploads';
    $products_dir = $upload_dir . '/products';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "<p style='color: green;'>✅ Created uploads directory</p>";
    } else {
        echo "<p style='color: green;'>✅ Uploads directory exists</p>";
    }
    
    if (!is_dir($products_dir)) {
        mkdir($products_dir, 0755, true);
        echo "<p style='color: green;'>✅ Created products directory</p>";
    } else {
        echo "<p style='color: green;'>✅ Products directory exists</p>";
    }
    
    // Create .htaccess for security
    $htaccess_content = "# Allow only image files
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Deny access to other file types
<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">
    Order Allow,Deny
    Deny from all
</FilesMatch>";
    
    $htaccess_file = $products_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, $htaccess_content);
        echo "<p style='color: green;'>✅ Created security .htaccess file</p>";
    } else {
        echo "<p style='color: green;'>✅ Security .htaccess exists</p>";
    }
    
    // Step 3: Show current products
    echo "<h3>Step 3: Current Products Status</h3>";
    
    $query = "SELECT id, name, category, image FROM products ORDER BY category, name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Category</th><th>Image Status</th><th>Display</th></tr>";
    
    foreach ($products as $product) {
        $image_status = $product['image'] ? 'Has Image' : 'Uses Category Icon';
        $image_class = $product['image'] ? 'green' : 'orange';
        
        echo "<tr>";
        echo "<td>" . $product['id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($product['name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($product['category']) . "</td>";
        echo "<td style='color: $image_class;'>$image_status</td>";
        echo "<td>";
        
        if ($product['image'] && file_exists(__DIR__ . '/' . $product['image'])) {
            echo "<img src='" . $product['image'] . "' style='width: 40px; height: 40px; object-fit: cover; border-radius: 4px;'>";
        } else {
            // Show category icon as fallback
            $icons = [
                'Food' => '🍔',
                'Drinks' => '☕',
                'Dessert' => '🍰'
            ];
            $icon = $icons[$product['category']] ?? '🍽️';
            echo "<span style='font-size: 30px;'>$icon</span>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3 style='color: green;'>🎉 Product Images Setup Complete!</h3>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>What's Ready:</h4>";
    echo "<ul>";
    echo "<li>✅ <strong>Database:</strong> Image column added to products table</li>";
    echo "<li>✅ <strong>File System:</strong> Upload directories created with security</li>";
    echo "<li>✅ <strong>Fallback System:</strong> Category icons used when no image uploaded</li>";
    echo "<li>➡️ <strong>Next:</strong> Admin interface ready for image uploads</li>";
    echo "<li>➡️ <strong>Next:</strong> Cashier interface will show images + fallbacks</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h4>🎯 Fallback Display Logic:</h4>";
    echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px;'>";
    echo "<ol>";
    echo "<li><strong>Product Image:</strong> If uploaded → Show actual food photo</li>";
    echo "<li><strong>Category Icon:</strong> If no image → Show 🍔 (Food), ☕ (Drinks), 🍰 (Dessert)</li>";
    echo "<li><strong>Default Icon:</strong> If no category match → Show 🍽️</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<p><a href='admin.php?page=menu' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Menu Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>