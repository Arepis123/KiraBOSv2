<?php
require 'config.php';

$db = Database::getInstance()->getConnection();

// Check orders
$stmt = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE restaurant_id = 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Orders count: " . $result['cnt'] . "\n";

// Check menu view logs
$stmt2 = $db->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE restaurant_id = 1 AND action_type = 'view_menu'");
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Menu view logs count: " . $result2['cnt'] . "\n";

// Get sample order
$stmt3 = $db->query("SELECT * FROM orders WHERE restaurant_id = 1 LIMIT 1");
$order = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "\nSample order:\n";
print_r($order);
?>
