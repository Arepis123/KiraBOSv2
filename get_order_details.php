<?php
// Start output buffering to prevent any accidental output
ob_start();

require_once "config.php";

// Clear any previous output and set JSON header
ob_clean();
header("Content-Type: application/json");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Invalid request method"]);
    exit();
}

$order_id = null;
if (isset($_POST["order_id"]) && !empty($_POST["order_id"])) {
    $order_id = (int)$_POST["order_id"];
} else {
    // Try to get from raw input in case of content-type issues
    $raw_input = file_get_contents('php://input');
    parse_str($raw_input, $parsed_input);
    if (isset($parsed_input["order_id"]) && !empty($parsed_input["order_id"])) {
        $order_id = (int)$parsed_input["order_id"];
    }
}

if ($order_id === null || $order_id <= 0) {
    echo json_encode(["success" => false, "error" => "Order ID is required"]);
    exit();
}

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    $restaurant_id = (int)$_SESSION['restaurant_id'];
    // $order_id is already set above
    
    $query = "SELECT 
        oi.quantity,
        oi.subtotal,
        p.name as product_name,
        p.category,
        p.price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.id = :order_id 
        AND o.restaurant_id = :restaurant_id
        ORDER BY p.name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":order_id", $order_id);
    $stmt->bindParam(":restaurant_id", $restaurant_id);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(["success" => false, "error" => "No items found for this order"]);
        exit();
    }
    
    echo json_encode([
        "success" => true,
        "items" => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
