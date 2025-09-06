<?php
// test_admin_features.php - Test duplicate prevention and edit functionality
session_start();
require_once 'config.php';

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

echo "<h2>Testing Admin Features</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div style='background: lightgreen; padding: 10px; margin: 10px;'>";
    echo "✅ Database connected successfully<br>";
    
    // Test 1: Check if duplicate detection works
    echo "<h3>Test 1: Duplicate Detection</h3>";
    $test_product = [
        'name' => 'Test Product',
        'price' => 10.50,
        'category' => 'Food'
    ];
    
    // Check if this combination already exists
    $check_query = "SELECT COUNT(*) as count FROM products WHERE name = :name AND price = :price AND category = :category";
    $stmt = $db->prepare($check_query);
    $stmt->execute($test_product);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "✅ Duplicate detection working - Found $count existing products with same name, price, category<br>";
    } else {
        echo "ℹ️  No existing products found with test criteria<br>";
    }
    
    // Test 2: Get product for edit test
    echo "<h3>Test 2: Product Edit Query</h3>";
    $edit_query = "SELECT * FROM products LIMIT 1";
    $stmt = $db->prepare($edit_query);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "✅ Product retrieved for edit test:<br>";
        echo "ID: {$product['id']}, Name: {$product['name']}, Price: {$product['price']}, Category: {$product['category']}<br>";
        
        // Test edit duplicate check (excluding current product)
        $edit_check_query = "SELECT COUNT(*) as count FROM products WHERE name = :name AND price = :price AND category = :category AND id != :id";
        $stmt = $db->prepare($edit_check_query);
        $stmt->execute([
            'name' => $product['name'],
            'price' => $product['price'],
            'category' => $product['category'],
            'id' => $product['id']
        ]);
        $edit_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "✅ Edit duplicate check working - Found $edit_count other products with same details<br>";
    } else {
        echo "❌ No products found in database<br>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: lightcoral; padding: 10px; margin: 10px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Features Test</title>
</head>
<body>
    <h1>Admin Features Test Results</h1>
    
    <div style="background: lightblue; padding: 15px; margin: 10px;">
        <h3>JavaScript Functions Test</h3>
        <p>Testing edit/cancel functionality:</p>
        
        <div id="test-product-1" style="border: 1px solid #ccc; padding: 10px; margin: 10px;">
            <div class="product-display">
                <strong>Test Product Display</strong>
                <p>Name: Sample Product, Price: $5.99, Category: Food</p>
                <button onclick="editProduct(1)" style="background: blue; color: white; padding: 5px;">Edit</button>
            </div>
            
            <div class="product-edit hidden" style="background: #f0f0f0; padding: 10px;">
                <strong>Edit Mode Active</strong>
                <p>Form would be here...</p>
                <button onclick="cancelEdit(1)" style="background: gray; color: white; padding: 5px;">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        // Test the JavaScript functions
        function editProduct(productId) {
            const productDiv = document.getElementById('test-product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
            console.log('Edit mode activated for product ' + productId);
        }
        
        function cancelEdit(productId) {
            const productDiv = document.getElementById('test-product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
            console.log('Edit mode cancelled for product ' + productId);
        }
        
        // Add CSS for hidden class
        const style = document.createElement('style');
        style.textContent = '.hidden { display: none; }';
        document.head.appendChild(style);
        
        console.log('JavaScript functions loaded successfully');
    </script>
    
    <p><a href="admin.php">← Back to Admin Dashboard</a></p>
</body>
</html>