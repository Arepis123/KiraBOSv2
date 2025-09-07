<?php
require_once 'config.php';

echo "<h2>🧪 Expenses Table Test</h2>";

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Test 1: Check if table exists
    echo "<h3>✅ Test 1: Table Structure</h3>";
    $query = "DESCRIBE expenses";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "<p style='color: red;'>❌ Expenses table not found! Please run the SQL script first.</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Column</th><th>Type</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>" . $column['Field'] . "</strong></td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test 2: Check sample data
    echo "<h3>📊 Test 2: Sample Data</h3>";
    $query = "SELECT COUNT(*) as count FROM expenses";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p><strong>Total expenses in database:</strong> {$count}</p>";
    
    if ($count > 0) {
        echo "<p style='color: green;'>✅ Sample data found! Here are some examples:</p>";
        
        $query = "SELECT e.*, u.username 
                  FROM expenses e 
                  LEFT JOIN users u ON e.user_id = u.id 
                  ORDER BY e.created_at DESC 
                  LIMIT 3";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Category</th><th>Amount</th><th>Description</th><th>Added By</th><th>Date</th></tr>";
        foreach ($samples as $expense) {
            echo "<tr>";
            echo "<td>" . $expense['id'] . "</td>";
            echo "<td><strong>" . ucfirst($expense['category']) . "</strong></td>";
            echo "<td style='color: #d73502;'><strong>RM" . number_format($expense['amount'], 2) . "</strong></td>";
            echo "<td>" . ($expense['description'] ?: '<em>No description</em>') . "</td>";
            echo "<td>" . ($expense['username'] ?: 'Unknown') . "</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($expense['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No sample data found. Table is empty but ready to use!</p>";
    }
    
    // Test 3: Test categories
    echo "<h3>🏷️ Test 3: Category Distribution</h3>";
    $query = "SELECT category, COUNT(*) as count, SUM(amount) as total 
              FROM expenses 
              GROUP BY category 
              ORDER BY total DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($categories)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Category</th><th>Count</th><th>Total Amount</th></tr>";
        foreach ($categories as $cat) {
            echo "<tr>";
            echo "<td><strong>" . ucfirst($cat['category']) . "</strong></td>";
            echo "<td>" . $cat['count'] . "</td>";
            echo "<td style='color: #d73502;'><strong>RM" . number_format($cat['total'], 2) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Today's expenses simulation
    echo "<h3>📅 Test 4: Today's Expenses Query</h3>";
    $query = "SELECT COALESCE(SUM(amount), 0) as today_total 
              FROM expenses 
              WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $todayTotal = $stmt->fetch(PDO::FETCH_ASSOC)['today_total'];
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h4 style='margin: 0; color: #2d5016;'>💰 Today's Total Expenses: <span style='color: #d73502;'>RM" . number_format($todayTotal, 2) . "</span></h4>";
    echo "</div>";
    
    echo "<h3 style='color: green;'>🎉 All Tests Passed!</h3>";
    echo "<p><strong>✅ Expenses table is ready to use!</strong></p>";
    echo "<p>You can now test the expense functionality in the cashier interface.</p>";
    
    echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc; margin: 20px 0;'>";
    echo "<h4 style='margin: 0 0 10px 0; color: #0066cc;'>🚀 Ready to Test:</h4>";
    echo "<ol>";
    echo "<li>Go to <strong>Cashier Interface</strong></li>";
    echo "<li>Click the <strong>💰 Expenses</strong> tab</li>";
    echo "<li>Select a category and add a test expense</li>";
    echo "<li>Verify it appears in the recent expenses list</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure you've run the SQL script to create the expenses table!</p>";
}
?>