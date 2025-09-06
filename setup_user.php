<?php
// setup_users.php - Run this script once to create default users
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if users already exist
    $query = "SELECT COUNT(*) as user_count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['user_count'] > 0) {
        echo "Users already exist in database. Updating passwords...<br>";
        
        // Update existing users with new passwords
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $cashier_password = password_hash('cashier123', PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = :password WHERE username = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $admin_password);
        $stmt->execute();
        
        $query = "UPDATE users SET password = :password WHERE username = 'cashier'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $cashier_password);
        $stmt->execute();
        
        echo "Passwords updated successfully!<br>";
    } else {
        echo "Creating default users...<br>";
        
        // Create admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role) VALUES ('admin', :password, 'admin')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $admin_password);
        $stmt->execute();
        echo "Admin user created (username: admin, password: admin123)<br>";
        
        // Create cashier user
        $cashier_password = password_hash('cashier123', PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role) VALUES ('cashier', :password, 'user')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $cashier_password);
        $stmt->execute();
        echo "Cashier user created (username: cashier, password: cashier123)<br>";
    }
    
    // Display all users for verification
    echo "<br><strong>Current users in database:</strong><br>";
    $query = "SELECT id, username, role, created_at FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, Created: {$user['created_at']}<br>";
    }
    
    // Test password verification
    echo "<br><strong>Testing password verification:</strong><br>";
    $query = "SELECT username, password FROM users WHERE username IN ('admin', 'cashier')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $test_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($test_users as $user) {
        $test_password = ($user['username'] === 'admin') ? 'admin123' : 'cashier123';
        $is_valid = password_verify($test_password, $user['password']);
        echo "User: {$user['username']}, Password test: " . ($is_valid ? 'PASS ✓' : 'FAIL ✗') . "<br>";
    }
    
    echo "<br><strong>Setup completed successfully!</strong><br>";
    echo "You can now login with:<br>";
    echo "Admin: admin / admin123<br>";
    echo "Cashier: cashier / cashier123<br>";
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>POS System - User Setup</h1>
    <p><strong>Important:</strong> Delete this file after running it once for security.</p>
</body>
</html>