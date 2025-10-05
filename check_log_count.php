<?php
require 'config.php';

$db = Database::getInstance()->getConnection();

// Count total logs
$stmt = $db->query("SELECT COUNT(*) FROM activity_logs WHERE restaurant_id = 1");
$total = $stmt->fetchColumn();

// Count excluding view_menu
$stmt = $db->query("SELECT COUNT(*) FROM activity_logs WHERE restaurant_id = 1 AND action_type != 'view_menu'");
$excluding_view = $stmt->fetchColumn();

echo "Total activity logs: $total\n";
echo "Excluding view_menu: $excluding_view\n";
echo "\nPagination shows when logs > 10\n";
?>
