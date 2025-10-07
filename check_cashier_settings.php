<?php
require_once 'config.php';
Security::validateSession();

$restaurant_id = Security::getRestaurantId();
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT id, name, cashier_settings FROM restaurants WHERE id = :restaurant_id");
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h1>Cashier Settings Debug</h1>";
echo "<h2>Restaurant: " . htmlspecialchars($restaurant['name']) . "</h2>";

echo "<h3>Raw Database Value:</h3>";
echo "<pre>";
echo htmlspecialchars($restaurant['cashier_settings']);
echo "</pre>";

echo "<h3>Decoded JSON:</h3>";
echo "<pre>";
$settings = json_decode($restaurant['cashier_settings'], true);
print_r($settings);
echo "</pre>";

echo "<h3>What will be passed to JavaScript:</h3>";
echo "<pre>";
echo !empty($restaurant['cashier_settings']) ? $restaurant['cashier_settings'] : 'null';
echo "</pre>";

echo "<p><a href='cashier.php'>Back to Cashier</a></p>";
?>
