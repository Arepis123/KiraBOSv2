<?php
require 'config.php';
Security::validateSession();

$restaurant = Restaurant::getCurrentRestaurant();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tax Display Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-4">Tax Display Test</h1>

    <div class="bg-gray-100 p-4 rounded mb-4">
        <h2 class="font-bold mb-2">Restaurant Config:</h2>
        <pre><?php print_r($restaurant); ?></pre>
    </div>

    <div class="bg-blue-100 p-4 rounded mb-4">
        <h2 class="font-bold mb-2">JavaScript Config:</h2>
        <pre id="jsConfig"></pre>
    </div>

    <div class="bg-green-100 p-4 rounded mb-4">
        <h2 class="font-bold mb-2">Tax Calculation Test:</h2>
        <p>Subtotal: RM <span id="subtotal">10.00</span></p>
        <p>Tax Rate: <span id="taxRateDisplay">-</span>%</p>
        <p>Tax Amount: RM <span id="taxAmount">-</span></p>
        <p>Total: RM <span id="total">-</span></p>
    </div>

    <script>
        const restaurantConfig = {
            currency: '<?= htmlspecialchars($restaurant['currency']) ?>',
            taxEnabled: <?= !empty($restaurant['tax_enabled']) ? 'true' : 'false' ?>,
            taxRate: <?= $restaurant['tax_rate'] ?? 0.0850 ?>,
            name: '<?= htmlspecialchars($restaurant['name']) ?>'
        };

        document.getElementById('jsConfig').textContent = JSON.stringify(restaurantConfig, null, 2);

        // Test calculation
        const subtotal = 10.00;
        const tax_rate = restaurantConfig.taxEnabled ? restaurantConfig.taxRate : 0;
        const tax_amount = restaurantConfig.taxEnabled ? (subtotal * tax_rate) : 0;
        const total = subtotal + tax_amount;

        document.getElementById('taxRateDisplay').textContent = (tax_rate * 100).toFixed(2);
        document.getElementById('taxAmount').textContent = tax_amount.toFixed(2);
        document.getElementById('total').textContent = total.toFixed(2);

        console.log('Calculation:', {
            subtotal,
            taxEnabled: restaurantConfig.taxEnabled,
            taxRate: tax_rate,
            taxAmount: tax_amount,
            total
        });
    </script>
</body>
</html>
