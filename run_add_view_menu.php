<?php
require_once 'config.php';

$database = Database::getInstance();
$db = $database->getConnection();

echo "<h1>Adding 'view_menu' to action_type ENUM</h1>";
echo "<pre>";

try {
    // Add view_menu to the ENUM
    $sql = "ALTER TABLE activity_logs
            MODIFY COLUMN action_type ENUM('create','update','delete','login','logout','enable','disable','view_menu') NOT NULL";

    $db->exec($sql);

    echo "✅ SUCCESS! 'view_menu' has been added to action_type ENUM\n\n";

    // Verify the change
    echo "=== Verifying Table Structure ===\n";
    $result = $db->query("DESCRIBE activity_logs");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'action_type') {
            echo "Field: {$row['Field']}\n";
            echo "Type: {$row['Type']}\n";
            echo "\n✅ You should see 'view_menu' in the ENUM list above\n";
        }
    }

    echo "\n=== Next Steps ===\n";
    echo "1. Go to cashier.php and click on products\n";
    echo "2. Check admin.php?page=logs&log_view=menu_views\n";
    echo "3. You should now see menu view logs!\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nPlease run the SQL manually in phpMyAdmin:\n";
    echo file_get_contents('add_view_menu_action_type.sql');
}

echo "</pre>";
?>
<br><br>
<a href="cashier.php" class="btn">Go to Cashier</a> |
<a href="admin.php?page=logs&log_view=menu_views" class="btn">Check Admin Logs</a> |
<a href="debug_activity_logs.php" class="btn">Debug Activity Logs</a>
