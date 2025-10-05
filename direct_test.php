<?php
require 'config.php';
session_start();

$_SESSION['user_id'] = 1;
$_SESSION['restaurant_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['restaurant_name'] = 'Test Restaurant';

$restaurant_id = 1;
$log_view = 'activity';

// Get activity logs with pagination
$db = Database::getInstance()->getConnection();
$current_page = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;
$logs_per_page = 10;
$offset = ($current_page - 1) * $logs_per_page;

$logs_query = "SELECT al.*, u.username
               FROM activity_logs al
               LEFT JOIN users u ON al.user_id = u.id
               WHERE al.restaurant_id = :restaurant_id
               AND al.action_type != 'view_menu'
               ORDER BY al.created_at DESC
               LIMIT :limit OFFSET :offset";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->bindParam(':restaurant_id', $restaurant_id);
$logs_stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
$logs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count excluding view_menu
$count_query = "SELECT COUNT(*) FROM activity_logs
                WHERE restaurant_id = :restaurant_id
                AND action_type != 'view_menu'";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':restaurant_id', $restaurant_id);
$count_stmt->execute();
$total_logs = $count_stmt->fetchColumn();

echo "<h2>Debug Info:</h2>";
echo "Total logs: $total_logs<br>";
echo "Logs fetched: " . count($logs) . "<br>";
echo "Empty logs? " . (empty($logs) ? 'YES' : 'NO') . "<br>";
echo "Show pagination? " . ($total_logs > 10 ? 'YES' : 'NO') . "<br>";
echo "<hr>";

if (empty($logs)) {
    echo "EMPTY BLOCK RENDERS<br>";
} else {
    echo "TABLE BLOCK RENDERS<br>";
    if ($total_logs > 10) {
        echo "<strong>PAGINATION SHOULD RENDER!</strong><br>";
        $total_pages = ceil($total_logs / $logs_per_page);
        echo "Total pages: $total_pages<br>";
        echo "Current page: $current_page<br>";
    } else {
        echo "NO PAGINATION (not enough logs)<br>";
    }
}
?>
