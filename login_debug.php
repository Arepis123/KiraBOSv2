<?php
// login_debug.php - Simplified debug version
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Login Debug</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
    echo "<strong>POST REQUEST RECEIVED!</strong><br>";
    echo "POST Data: ";
    print_r($_POST);
    echo "</div>";
    
    // Test config.php inclusion
    echo "<div style='background: lightgreen; padding: 10px; margin: 10px;'>";
    echo "Testing config.php inclusion...<br>";
    
    try {
        require_once 'config.php';
        echo "✅ config.php loaded successfully<br>";
        
        // Test database connection
        $database = new Database();
        $db = $database->getConnection();
        if ($db) {
            echo "✅ Database connected successfully<br>";
        } else {
            echo "❌ Database connection failed<br>";
        }
        
        // Test Security class
        $token = Security::generateCSRFToken();
        echo "✅ Security class working, CSRF token: " . substr($token, 0, 20) . "...<br>";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
} else {
    echo "<div style='background: lightblue; padding: 10px; margin: 10px;'>";
    echo "GET Request (page load)";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Debug</title>
</head>
<body>
    <h1>Login Debug Test</h1>
    
    <form method="POST" action="login_debug.php">
        <div>
            <label>Username:</label>
            <input type="text" name="username" value="admin" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" value="admin123" required>
        </div>
        <input type="hidden" name="csrf_token" value="test_token">
        <button type="submit">Debug Submit</button>
    </form>
</body>
</html>