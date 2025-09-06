<?php
// Quick test to see what's happening with payment method submission
if ($_POST) {
    echo "<h2>POST Debug Data:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Payment Method Analysis:</h3>";
    echo "Raw payment_method: " . (isset($_POST['payment_method']) ? $_POST['payment_method'] : 'NOT SET') . "<br>";
    echo "After sanitization: " . htmlspecialchars(strip_tags(trim($_POST['payment_method'] ?? 'NOT SET'))) . "<br>";
    echo "Is it 'qr'? " . (($_POST['payment_method'] ?? '') === 'qr' ? 'YES' : 'NO') . "<br>";
    echo "Is it 'cash'? " . (($_POST['payment_method'] ?? '') === 'cash' ? 'YES' : 'NO') . "<br>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Method Debug Test</title>
</head>
<body>
    <h1>Payment Method Debug Test</h1>
    
    <form method="POST">
        <h3>Test Cash Payment:</h3>
        <input type="hidden" name="payment_method" value="cash">
        <button type="submit">Submit Cash Payment</button>
    </form>
    
    <form method="POST">
        <h3>Test QR Payment:</h3>
        <input type="hidden" name="payment_method" value="qr">
        <button type="submit">Submit QR Payment</button>
    </form>
    
    <script>
        // Test what JavaScript is sending
        function testAjaxPayment(method) {
            const formData = new FormData();
            formData.append('payment_method', method);
            formData.append('test', 'ajax');
            
            fetch('test_payment_debug.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                console.log('Response:', data);
                document.body.innerHTML += '<div><h3>AJAX Test Result for ' + method + ':</h3><pre>' + data + '</pre></div>';
            });
        }
        
        console.log('Use testAjaxPayment("cash") or testAjaxPayment("qr") in console to test');
    </script>
</body>
</html>