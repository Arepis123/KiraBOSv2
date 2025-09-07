<?php
require_once 'config.php';

echo "<h2>🎨 Category Color Management Setup</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check if color column exists
    echo "<h3>Step 1: Checking Current Table Structure</h3>";
    $query = "DESCRIBE categories";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasColorColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'color') {
            $hasColorColumn = true;
            break;
        }
    }
    
    if ($hasColorColumn) {
        echo "<p style='color: green;'>✅ Color column already exists!</p>";
    } else {
        echo "<p style='color: orange;'>➕ Color column not found. Adding it...</p>";
        
        // Add color column
        $query = "ALTER TABLE categories ADD COLUMN color VARCHAR(7) DEFAULT '#FF6B6B' AFTER icon";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        echo "<p style='color: green;'>✅ Color column added successfully!</p>";
    }
    
    // Update existing categories with default colors
    echo "<h3>Step 2: Setting Default Colors</h3>";
    $query = "UPDATE categories SET color = CASE 
        WHEN LOWER(name) LIKE '%food%' OR LOWER(name) LIKE '%meal%' OR LOWER(name) LIKE '%main%' THEN '#FF6B6B'
        WHEN LOWER(name) LIKE '%drink%' OR LOWER(name) LIKE '%beverage%' OR LOWER(name) LIKE '%coffee%' OR LOWER(name) LIKE '%tea%' THEN '#4ECDC4'
        WHEN LOWER(name) LIKE '%dessert%' OR LOWER(name) LIKE '%sweet%' OR LOWER(name) LIKE '%cake%' THEN '#FFE66D'
        WHEN LOWER(name) LIKE '%appetizer%' OR LOWER(name) LIKE '%starter%' THEN '#74B9FF'
        WHEN LOWER(name) LIKE '%snack%' OR LOWER(name) LIKE '%side%' THEN '#A29BFE'
        ELSE '#FF6B6B'
    END
    WHERE color IS NULL OR color = '' OR color = '#FF6B6B'";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $rowsUpdated = $stmt->rowCount();
    
    echo "<p style='color: green;'>✅ Updated $rowsUpdated categories with default colors!</p>";
    
    // Show current categories with colors
    echo "<h3>Step 3: Current Categories with Colors</h3>";
    $query = "SELECT id, name, icon, color, description, is_active FROM categories ORDER BY sort_order, name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($categories)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Icon</th><th>Color</th><th>Color Preview</th><th>Description</th><th>Status</th></tr>";
        foreach ($categories as $category) {
            $status = $category['is_active'] ? 'Active' : 'Inactive';
            $statusColor = $category['is_active'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . $category['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($category['name']) . "</strong></td>";
            echo "<td style='font-size: 20px;'>" . $category['icon'] . "</td>";
            echo "<td><code>" . $category['color'] . "</code></td>";
            echo "<td><div style='width: 30px; height: 20px; background: " . $category['color'] . "; border: 1px solid #ccc; border-radius: 4px;'></div></td>";
            echo "<td>" . htmlspecialchars($category['description'] ?? '') . "</td>";
            echo "<td style='color: $statusColor;'><strong>$status</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No categories found. Creating default categories...</p>";
        
        // Create default categories
        $defaultCategories = [
            ['name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'color' => '#FF6B6B'],
            ['name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'color' => '#4ECDC4'],
            ['name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'color' => '#FFE66D']
        ];
        
        foreach ($defaultCategories as $index => $cat) {
            $query = "INSERT INTO categories (restaurant_id, name, description, icon, color, sort_order) VALUES (1, :name, :description, :icon, :color, :sort_order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $cat['name']);
            $stmt->bindParam(':description', $cat['description']);
            $stmt->bindParam(':icon', $cat['icon']);
            $stmt->bindParam(':color', $cat['color']);
            $stmt->bindParam(':sort_order', $index + 1);
            $stmt->execute();
        }
        
        echo "<p style='color: green;'>✅ Default categories created with colors!</p>";
    }
    
    echo "<h3 style='color: green;'>🎉 Category Color Management Setup Complete!</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Color column has been added to the categories table</li>";
    echo "<li>✅ Existing categories have been assigned default colors</li>";
    echo "<li>➡️ Admin interface will now support color picking</li>";
    echo "<li>➡️ Dashboard charts will use custom category colors</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure the categories table exists and you have proper database permissions.</p>";
}
?>