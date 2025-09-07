<!DOCTYPE html>
<html>
<head>
    <title>AJAX Test</title>
</head>
<body>
    <h1>AJAX Test for Order Details</h1>
    
    <button onclick="testOrderDetails(1)">Test Order Details (Order ID: 1)</button>
    <div id="result"></div>
    
    <script>
        function testOrderDetails(orderId) {
            console.log('Testing order details for ID:', orderId);
            
            fetch('get_order_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text(); // Get as text first to see what we're getting
            })
            .then(text => {
                console.log('Response text:', text);
                document.getElementById('result').innerHTML = '<pre>' + text + '</pre>';
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('result').innerHTML = '<p>Error: ' + error.message + '</p>';
            });
        }
    </script>
</body>
</html>