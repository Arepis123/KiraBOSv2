<?php
$db = new PDO('mysql:host=localhost;dbname=kirabos_multitenant', 'root', '');
$stmt = $db->query('SELECT id, name, tax_rate, tax_enabled FROM restaurants');
$restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "All Restaurants Tax Rates:\n\n";
foreach ($restaurants as $r) {
    echo "ID: {$r['id']}\n";
    echo "Name: {$r['name']}\n";
    echo "Tax Rate (decimal): {$r['tax_rate']}\n";
    echo "Tax Rate (percentage): " . ($r['tax_rate'] * 100) . "%\n";
    echo "Tax Enabled: {$r['tax_enabled']}\n";
    echo "---\n";
}
echo "</pre>";
?>
