<?php
require 'config.php';
session_start();

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['restaurant_id'] = 1;
$_SESSION['role'] = 'admin';

$restaurant_id = 1;
$db = Database::getInstance()->getConnection();

// This is the same query from admin-logs.php
$count_query = "SELECT COUNT(*) FROM activity_logs
                WHERE restaurant_id = :restaurant_id
                AND action_type != 'view_menu'";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':restaurant_id', $restaurant_id);
$count_stmt->execute();
$total_logs = $count_stmt->fetchColumn();

echo "Total logs: $total_logs\n";
echo "Condition ($total_logs > 10): " . ($total_logs > 10 ? 'TRUE' : 'FALSE') . "\n";
echo "Should show pagination: " . ($total_logs > 10 ? 'YES' : 'NO') . "\n";
?>
