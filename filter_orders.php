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

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    $restaurant_id = (int)$_SESSION['restaurant_id'];
    
    // Get filter parameters
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $status = $_POST['status'] ?? '';
    $limit = (int)($_POST['limit'] ?? 10);
    
    // Build the query with filters
    $query = "SELECT o.*, u.username FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              WHERE o.restaurant_id = :restaurant_id";
    
    $params = [':restaurant_id' => $restaurant_id];
    
    // Add date filters
    if (!empty($date_from)) {
        $query .= " AND DATE(o.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(o.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    // Add payment method filter
    if (!empty($payment_method)) {
        $query .= " AND o.payment_method = :payment_method";
        $params[':payment_method'] = $payment_method;
    }
    
    // Add status filter
    if (!empty($status)) {
        $query .= " AND o.status = :status";
        $params[':status'] = $status;
    }
    
    $query .= " ORDER BY o.created_at DESC LIMIT :limit";
    $params[':limit'] = $limit;
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        if ($key === ':limit') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count without limit
    $count_query = str_replace("SELECT o.*, u.username", "SELECT COUNT(*) as total", $query);
    $count_query = str_replace(" LIMIT :limit", "", $count_query);
    unset($params[':limit']);
    
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total_count' => (int)$total_count,
        'showing' => count($orders)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>