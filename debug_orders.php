<?php
// debug_orders.php - Check orders in database
session_start();
require_once 'config.php';

echo "<h2>Orders Debug</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all orders with user info
    $query = "SELECT o.*, u.username, u.role FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Orders (Latest 20)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Role</th><th>Total</th><th>Status</th><th>Payment Method</th><th>Created At</th></tr>";
    
    if (count($all_orders) > 0) {
        foreach ($all_orders as $order) {
            echo "<tr>";
            echo "<td>" . $order['id'] . "</td>";
            echo "<td>" . $order['user_id'] . "</td>";
            echo "<td>" . ($order['username'] ?? 'NULL') . "</td>";
            echo "<td>" . ($order['role'] ?? 'NULL') . "</td>";
            echo "<td>RM" . number_format($order['total_amount'], 2) . "</td>";
            echo "<td>" . $order['status'] . "</td>";
            echo "<td>" . $order['payment_method'] . "</td>";
            echo "<td>" . $order['created_at'] . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='8'>No orders found</td></tr>";
    }
    echo "</table>";
    
    // Get all users
    echo "<h3>All Users</h3>";
    $query = "SELECT * FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check current session
    echo "<h3>Current Session</h3>";
    echo "<p>Session User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
    echo "<p>Session Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";
    echo "<p>Session Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Orders Debug</title>
    <style>
        table { margin: 10px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <p><a href="admin.php">← Back to Admin</a> | <a href="cashier.php">Go to Cashier</a></p>
</body>
</html>