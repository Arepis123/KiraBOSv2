<?php
class Database {
    private static $instance = null;
    private $conn = null;
    private $host;
    private $db_name;
    private $username;
    private $password;

    private function __construct() {
        // Auto-detect environment and set appropriate database credentials
        $this->setDatabaseCredentials();
    }
    
    private function setDatabaseCredentials() {
        // Check if we're on a production server (you can customize this detection)
        $isProduction = !empty($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost';
        
        if ($isProduction) {
            // Production credentials
            $this->host = 'localhost';
            $this->db_name = 'kirabos_multitenant';
            $this->username = 'test';  
            $this->password = '357u1oLM#';
        } else {
            // Local development credentials
            $this->host = 'localhost';
            $this->db_name = 'kirabos_multitenant';
            $this->username = 'root';
            $this->password = '';
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                    $this->username, 
                    $this->password,
                    [
                        PDO::ATTR_PERSISTENT => true,  // Use persistent connections
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false  // Use native prepared statements
                    ]
                );
                $this->conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch(PDOException $exception) {
                error_log("Database connection error: " . $exception->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->conn;
    }
}

// Restaurant management class
class Restaurant {
    public static function getBySlug($slug) {
        $database = Database::getInstance();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM restaurants WHERE slug = :slug AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function getById($id) {
        $database = Database::getInstance();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM restaurants WHERE id = :id AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function getCurrentRestaurant() {
        if (isset($_SESSION['restaurant_id'])) {
            // Use cached data if available and recent
            if (isset($_SESSION['restaurant_cached_data']) && 
                isset($_SESSION['restaurant_cache_time']) &&
                (time() - $_SESSION['restaurant_cache_time'] < 300)) {
                return $_SESSION['restaurant_cached_data'];
            }
            return self::getById($_SESSION['restaurant_id']);
        }
        return null;
    }
}

// Security functions
class Security {
    public static function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    public static function validateSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
            header("Location: login.php");
            exit();
        }
        
        // Cache restaurant validation for 5 minutes to reduce database calls
        $restaurant_cache_time = $_SESSION['restaurant_cache_time'] ?? 0;
        $current_time = time();
        
        if ($current_time - $restaurant_cache_time > 300) { // 5 minutes cache
            // Verify restaurant is still active
            $restaurant = Restaurant::getCurrentRestaurant();
            if (!$restaurant) {
                session_destroy();
                header("Location: login.php?error=restaurant_inactive");
                exit();
            }
            // Update cache timestamp
            $_SESSION['restaurant_cache_time'] = $current_time;
            $_SESSION['restaurant_cached_data'] = $restaurant;
        }
        
        // Less frequent session regeneration (30 minutes instead of 5)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = $current_time;
        } elseif ($current_time - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = $current_time;
        }
    }
    
    public static function requireAdmin() {
        self::validateSession();
        if ($_SESSION['role'] !== 'admin') {
            header("Location: cashier.php");
            exit();
        }
    }
    
    public static function getRestaurantId() {
        self::validateSession();
        return $_SESSION['restaurant_id'];
    }
    
    public static function belongsToCurrentRestaurant($restaurant_id) {
        return $restaurant_id == self::getRestaurantId();
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Activity Logger class
class ActivityLogger {
    public static function log($action_type, $description, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
        try {
            $database = Database::getInstance();
            $db = $database->getConnection();
            
            $restaurant_id = $_SESSION['restaurant_id'] ?? null;
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Convert arrays to JSON
            $old_values_json = $old_values ? json_encode($old_values) : null;
            $new_values_json = $new_values ? json_encode($new_values) : null;
            
            $query = "INSERT INTO activity_logs (restaurant_id, user_id, username, action_type, table_name, record_id, description, old_values, new_values, ip_address, user_agent) 
                      VALUES (:restaurant_id, :user_id, :username, :action_type, :table_name, :record_id, :description, :old_values, :new_values, :ip_address, :user_agent)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':action_type', $action_type);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':old_values', $old_values_json);
            $stmt->bindParam(':new_values', $new_values_json);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Log errors silently to avoid breaking the main functionality
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getRecentLogs($restaurant_id, $limit = 20, $offset = 0, $action_type = null, $user_id = null) {
        try {
            $database = Database::getInstance();
            $db = $database->getConnection();
            
            $where_conditions = ["restaurant_id = :restaurant_id"];
            $params = [':restaurant_id' => $restaurant_id];
            
            if ($action_type) {
                $where_conditions[] = "action_type = :action_type";
                $params[':action_type'] = $action_type;
            }
            
            if ($user_id) {
                $where_conditions[] = "user_id = :user_id";
                $params[':user_id'] = $user_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "SELECT * FROM activity_logs WHERE $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to retrieve activity logs: " . $e->getMessage());
            return [];
        }
    }
    
    public static function getLogsCount($restaurant_id, $action_type = null, $user_id = null) {
        try {
            $database = Database::getInstance();
            $db = $database->getConnection();
            
            $where_conditions = ["restaurant_id = :restaurant_id"];
            $params = [':restaurant_id' => $restaurant_id];
            
            if ($action_type) {
                $where_conditions[] = "action_type = :action_type";
                $params[':action_type'] = $action_type;
            }
            
            if ($user_id) {
                $where_conditions[] = "user_id = :user_id";
                $params[':user_id'] = $user_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "SELECT COUNT(*) as total FROM activity_logs WHERE $where_clause";
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch (Exception $e) {
            error_log("Failed to count activity logs: " . $e->getMessage());
            return 0;
        }
    }
}
?>