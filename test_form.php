<?php
// test_form.php - Simple form test
session_start();

echo "<h2>Form Test Debug</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
    echo "<strong>POST REQUEST RECEIVED!</strong><br>";
    echo "POST Data: ";
    print_r($_POST);
    echo "</div>";
} else {
    echo "<div style='background: lightblue; padding: 10px; margin: 10px;'>";
    echo "GET Request (page load)";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Form Test</title>
</head>
<body>
    <h1>Simple Form Test</h1>
    
    <form method="POST" action="test_form.php">
        <div>
            <label>Test Username:</label>
            <input type="text" name="username" value="admin" required>
        </div>
        <div>
            <label>Test Password:</label>
            <input type="password" name="password" value="admin123" required>
        </div>
        <button type="submit">Test Submit</button>
    </form>
    
    <script>
        console.log('Page loaded');
        document.querySelector('form').addEventListener('submit', function(e) {
            console.log('Form submit event fired');
        });
    </script>
</body>
</html>