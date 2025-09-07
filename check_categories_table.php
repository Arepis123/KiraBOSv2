<?php
require_once 'config.php';

echo "<h2>Categories Table Structure</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check if categories table exists
    $query = "DESCRIBE categories";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "<p style='color: red;'>Categories table not found!</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>" . $column['Field'] . "</strong></td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show sample data
    echo "<h3>Sample Categories Data</h3>";
    $query = "SELECT * FROM categories LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($categories)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        $first_row = true;
        foreach ($categories as $category) {
            if ($first_row) {
                echo "<tr style='background: #f0f0f0;'>";
                foreach (array_keys($category) as $key) {
                    echo "<th>" . $key . "</th>";
                }
                echo "</tr>";
                $first_row = false;
            }
            echo "<tr>";
            foreach ($category as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No categories found in database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>