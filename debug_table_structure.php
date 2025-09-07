<?php
require_once 'config.php';

echo "<h1>Table Structure Debug</h1>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check order_items table structure
    echo "<h2>order_items table structure:</h2>";
    $query = "DESCRIBE order_items";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check a sample order_items record
    echo "<h2>Sample order_items records:</h2>";
    $query = "SELECT * FROM order_items LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($samples);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>