<?php
require 'config.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['restaurant_id'] = 1;

$db = Database::getInstance()->getConnection();

// Get a user ID
$stmt = $db->query("SELECT id FROM users WHERE restaurant_id = 1 LIMIT 1");
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    die("No users found for restaurant_id 1\n");
}

$actions = ['create', 'update', 'delete', 'login', 'logout', 'enable', 'disable'];
$tables = ['products', 'users', 'orders', 'categories'];
$descriptions = [
    'create' => ['Created new product', 'Added new user', 'Created order', 'Added category'],
    'update' => ['Updated product details', 'Modified user permissions', 'Updated order status', 'Changed category name'],
    'delete' => ['Deleted product', 'Removed user', 'Cancelled order', 'Deleted category'],
    'login' => ['User logged in', 'Admin logged in', 'Cashier logged in', 'System login'],
    'logout' => ['User logged out', 'Admin logged out', 'Cashier logged out', 'System logout'],
    'enable' => ['Enabled product', 'Activated user', 'Enabled feature', 'Activated category'],
    'disable' => ['Disabled product', 'Deactivated user', 'Disabled feature', 'Deactivated category']
];

// Create 25 sample logs
for ($i = 0; $i < 25; $i++) {
    $action = $actions[array_rand($actions)];
    $table = $tables[array_rand($tables)];
    $desc = $descriptions[$action][array_rand($descriptions[$action])];

    // Create log with varying timestamps
    $hours_ago = rand(0, 72);
    $timestamp = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hours"));

    $query = "INSERT INTO activity_logs (restaurant_id, user_id, action_type, description, table_name, ip_address, created_at)
              VALUES (:restaurant_id, :user_id, :action_type, :description, :table_name, :ip_address, :created_at)";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':restaurant_id' => 1,
        ':user_id' => $user_id,
        ':action_type' => $action,
        ':description' => $desc,
        ':table_name' => $table,
        ':ip_address' => '127.0.0.' . rand(1, 255),
        ':created_at' => $timestamp
    ]);
}

echo "Successfully created 25 sample activity logs!\n";
echo "Visit: http://localhost/KiraBOSv2/admin.php?page=logs to see the pagination\n";
?>
