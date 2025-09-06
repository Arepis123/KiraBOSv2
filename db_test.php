<?php
// Database Connection Test Script
// Remove this file after fixing the database issue

echo "<h2>Database Connection Test</h2>";

// Test with different database names
$test_configs = [
    [
        'host' => 'localhost',
        'db_name' => 'kirabos_multitenant',
        'username' => 'test',
        'password' => '357u1oLM#'
    ],
    [
        'host' => 'localhost',
        'db_name' => 'kirabos_multitenant',
        'username' => 'root',
        'password' => ''
    ]
];

foreach ($test_configs as $index => $config) {
    echo "<h3>Test " . ($index + 1) . ": Database '{$config['db_name']}'</h3>";
    
    try {
        $conn = new PDO(
            "mysql:host={$config['host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "✅ <strong>SUCCESS</strong>: Connected to database '{$config['db_name']}'<br>";
        
        // Test if required tables exist
        $required_tables = ['restaurants', 'users', 'products', 'orders'];
        foreach ($required_tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' missing<br>";
            }
        }
        
        // Test if there are any restaurants
        $stmt = $conn->query("SELECT COUNT(*) as count FROM restaurants");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "ℹ️ Found {$result['count']} restaurants<br>";
        
        if ($result['count'] > 0) {
            $stmt = $conn->query("SELECT name, slug FROM restaurants LIMIT 3");
            $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "📋 Restaurant list:<br>";
            foreach ($restaurants as $restaurant) {
                echo "&nbsp;&nbsp;- {$restaurant['name']} (slug: {$restaurant['slug']})<br>";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ <strong>FAILED</strong>: " . $e->getMessage() . "<br>";
    }
    echo "<hr>";
}

echo "<h3>PHP MySQL Extension Check</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "✅ PDO MySQL extension is loaded<br>";
} else {
    echo "❌ PDO MySQL extension is NOT loaded<br>";
}

if (extension_loaded('mysql') || extension_loaded('mysqli')) {
    echo "✅ MySQL/MySQLi extension is available<br>";
} else {
    echo "❌ No MySQL extension found<br>";
}

echo "<h3>Server Information</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "<br>";

echo "<p><small>⚠️ <strong>Important:</strong> Delete this file (db_test.php) after fixing the database issue for security.</small></p>";
?>