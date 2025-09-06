<?php
// admin.php - Simple admin dashboard
require_once 'config.php';
Security::requireAdmin();

// Determine current page
$current_page = $_GET['page'] ?? 'dashboard';
$valid_pages = ['dashboard', 'menu', 'users', 'logs', 'reports', 'settings'];
if (!in_array($current_page, $valid_pages)) {
    $current_page = 'dashboard';
}

$database = Database::getInstance();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$restaurant_id = Security::getRestaurantId();
$restaurant = Restaurant::getCurrentRestaurant();

// Handle AJAX requests for loading more activity logs
if (isset($_POST['action']) && $_POST['action'] === 'load_more_logs') {
    header('Content-Type: application/json');
    
    $offset = (int)($_POST['offset'] ?? 0);
    $limit = 10; // Load 10 logs at a time
    $action_type = $_POST['action_filter'] ?? null;
    $user_id = $_POST['user_filter'] ?? null;
    
    if ($action_type === '') $action_type = null;
    if ($user_id === '') $user_id = null;
    
    try {
        $logs = ActivityLogger::getRecentLogs($restaurant_id, $limit, $offset, $action_type, $user_id);
        $total_logs = ActivityLogger::getLogsCount($restaurant_id, $action_type, $user_id);
        
        ob_start();
    foreach ($logs as $log):
?>
        <div class="log-item flex items-start space-x-4 p-4 rounded-lg transition-colors hover:bg-gray-50" 
             style="border: 1px solid var(--border-primary); background: var(--bg-secondary)"
             data-action="<?= $log['action_type'] ?>" 
             data-user="<?= $log['user_id'] ?>"
             data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>">
            
            <!-- Action Icon -->
            <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-semibold
                <?php 
                    switch($log['action_type']) {
                        case 'create': echo 'bg-green-500'; break;
                        case 'update': echo 'bg-blue-500'; break;
                        case 'delete': echo 'bg-red-500'; break;
                        case 'login': echo 'bg-purple-500'; break;
                        case 'logout': echo 'bg-gray-500'; break;
                        case 'enable': echo 'bg-emerald-500'; break;
                        case 'disable': echo 'bg-orange-500'; break;
                        default: echo 'bg-gray-400';
                    }
                ?>">
                <?php 
                    switch($log['action_type']) {
                        case 'create': echo '➕'; break;
                        case 'update': echo '✏️'; break;
                        case 'delete': echo '🗑️'; break;
                        case 'login': echo '🔐'; break;
                        case 'logout': echo '🚪'; break;
                        case 'enable': echo '✅'; break;
                        case 'disable': echo '❌'; break;
                        default: echo '📝';
                    }
                ?>
            </div>
            
            <!-- Log Details -->
            <div class="flex-grow min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <h4 class="font-medium truncate" style="color: var(--text-primary)">
                        <?= htmlspecialchars($log['description']) ?>
                    </h4>
                    <span class="text-xs px-2 py-1 rounded-full font-medium
                        <?php 
                            switch($log['action_type']) {
                                case 'create': echo 'bg-green-100 text-green-800'; break;
                                case 'update': echo 'bg-blue-100 text-blue-800'; break;
                                case 'delete': echo 'bg-red-100 text-red-800'; break;
                                case 'login': case 'logout': echo 'bg-purple-100 text-purple-800'; break;
                                case 'enable': echo 'bg-emerald-100 text-emerald-800'; break;
                                case 'disable': echo 'bg-orange-100 text-orange-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                        <?= ucfirst($log['action_type']) ?>
                    </span>
                </div>
                
                <div class="flex items-center text-sm space-x-4" style="color: var(--text-secondary)">
                    <span>👤 <?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                    <span>🕐 <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></span>
                    <?php if ($log['table_name']): ?>
                        <span>📋 <?= ucfirst($log['table_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($log['ip_address']): ?>
                        <span>🌐 <?= htmlspecialchars($log['ip_address']) ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Show changes if available -->
                <?php if ($log['old_values'] || $log['new_values']): ?>
                    <button onclick="toggleDetails(<?= $log['id'] ?>)" 
                            class="text-xs text-blue-600 hover:text-blue-800 mt-2 font-medium">
                        View Changes
                    </button>
                    <div id="details-<?= $log['id'] ?>" class="hidden mt-3 p-3 rounded-lg bg-gray-100">
                        <?php if ($log['old_values']): ?>
                            <div class="mb-2">
                                <strong class="text-xs text-red-600">Previous Values:</strong>
                                <pre class="text-xs mt-1 text-gray-600"><?= htmlspecialchars(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT)) ?></pre>
                            </div>
                        <?php endif; ?>
                        <?php if ($log['new_values']): ?>
                            <div>
                                <strong class="text-xs text-green-600">New Values:</strong>
                                <pre class="text-xs mt-1 text-gray-600"><?= htmlspecialchars(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT)) ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    endforeach;
    $html = ob_get_clean();
    
        echo json_encode([
            'success' => true,
            'html' => $html,
            'has_more' => ($offset + $limit) < $total_logs,
            'total_logs' => $total_logs,
            'loaded' => $offset + count($logs)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load more logs: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Handle product management
if ($_POST && isset($_POST['action'])) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        if ($_POST['action'] === 'add_product') {
            $name = Security::sanitize($_POST['name']);
            $price = (float)$_POST['price'];
            $category = Security::sanitize($_POST['category']);
            
            if ($name && $price > 0 && $category) {
                // Check for duplicate product within the restaurant
                $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND name = :name AND price = :price AND category = :category";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->bindParam(':price', $price);
                $check_stmt->bindParam(':category', $category);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Product with the same name, price, and category already exists';
                } else {
                    $query = "INSERT INTO products (restaurant_id, name, price, category) VALUES (:restaurant_id, :name, :price, :category)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':category', $category);
                    
                    if ($stmt->execute()) {
                        $success = 'Product added successfully';
                    } else {
                        $error = 'Failed to add product';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        if ($_POST['action'] === 'toggle_product') {
            $product_id = (int)$_POST['product_id'];
            $is_active = (int)$_POST['is_active'];
            
            $query = "UPDATE products SET is_active = :is_active WHERE id = :id AND restaurant_id = :restaurant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $product_id);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->execute();
        }
        
        if ($_POST['action'] === 'edit_product') {
            $product_id = (int)$_POST['product_id'];
            $name = Security::sanitize($_POST['name']);
            $price = (float)$_POST['price'];
            $category = Security::sanitize($_POST['category']);
            
            if ($name && $price > 0 && $category) {
                // Check for duplicate product within restaurant (excluding current product)
                $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND name = :name AND price = :price AND category = :category AND id != :id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->bindParam(':price', $price);
                $check_stmt->bindParam(':category', $category);
                $check_stmt->bindParam(':id', $product_id);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Another product with the same name, price, and category already exists';
                } else {
                    $query = "UPDATE products SET name = :name, price = :price, category = :category WHERE id = :id AND restaurant_id = :restaurant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':category', $category);
                    $stmt->bindParam(':id', $product_id);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Product updated successfully';
                    } else {
                        $error = 'Failed to update product';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        // Category Management Actions
        if ($_POST['action'] === 'add_category') {
            $name = Security::sanitize($_POST['category_name']);
            $description = Security::sanitize($_POST['category_description']);
            $icon = Security::sanitize($_POST['category_icon']);
            $sort_order = (int)$_POST['sort_order'];
            
            if ($name) {
                // Check for duplicate category
                $check_query = "SELECT COUNT(*) as count FROM categories WHERE restaurant_id = :restaurant_id AND name = :name";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':name', $name);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Category already exists';
                } else {
                    $query = "INSERT INTO categories (restaurant_id, name, description, icon, sort_order) VALUES (:restaurant_id, :name, :description, :icon, :sort_order)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':icon', $icon);
                    $stmt->bindParam(':sort_order', $sort_order);
                    
                    if ($stmt->execute()) {
                        $category_id = $db->lastInsertId();
                        
                        // Log the activity
                        ActivityLogger::log('create', "Created new category: {$name}", 'categories', $category_id, null, [
                            'name' => $name,
                            'description' => $description,
                            'icon' => $icon,
                            'sort_order' => $sort_order
                        ]);
                        
                        $success = 'Category added successfully';
                    } else {
                        $error = 'Failed to add category';
                    }
                }
            } else {
                $error = 'Category name is required';
            }
        }
        
        if ($_POST['action'] === 'edit_category') {
            $category_id = (int)$_POST['category_id'];
            $name = Security::sanitize($_POST['category_name']);
            $description = Security::sanitize($_POST['category_description']);
            $icon = Security::sanitize($_POST['category_icon']);
            $sort_order = (int)$_POST['sort_order'];
            
            if ($name) {
                // Get old values before update
                $old_query = "SELECT name, description, icon, sort_order FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
                $old_stmt = $db->prepare($old_query);
                $old_stmt->bindParam(':id', $category_id);
                $old_stmt->bindParam(':restaurant_id', $restaurant_id);
                $old_stmt->execute();
                $old_values = $old_stmt->fetch(PDO::FETCH_ASSOC);
                
                $query = "UPDATE categories SET name = :name, description = :description, icon = :icon, sort_order = :sort_order WHERE id = :id AND restaurant_id = :restaurant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':icon', $icon);
                $stmt->bindParam(':sort_order', $sort_order);
                $stmt->bindParam(':id', $category_id);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                
                if ($stmt->execute()) {
                    // Log the activity with old and new values
                    $new_values = [
                        'name' => $name,
                        'description' => $description,
                        'icon' => $icon,
                        'sort_order' => $sort_order
                    ];
                    
                    ActivityLogger::log('update', "Updated category: {$name}", 'categories', $category_id, $old_values, $new_values);
                    
                    $success = 'Category updated successfully';
                } else {
                    $error = 'Failed to update category';
                }
            } else {
                $error = 'Category name is required';
            }
        }
        
        if ($_POST['action'] === 'toggle_category') {
            $category_id = (int)$_POST['category_id'];
            $is_active = (int)$_POST['is_active'];
            
            // Get old values before update
            $cat_query = "SELECT name, is_active FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
            $cat_stmt = $db->prepare($cat_query);
            $cat_stmt->bindParam(':id', $category_id);
            $cat_stmt->bindParam(':restaurant_id', $restaurant_id);
            $cat_stmt->execute();
            $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
            $old_values = ['is_active' => $category['is_active']];
            
            $query = "UPDATE categories SET is_active = :is_active WHERE id = :id AND restaurant_id = :restaurant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $category_id);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            
            if ($stmt->execute()) {
                // Log the activity with old and new values
                $action = $is_active ? 'enable' : 'disable';
                $new_values = ['is_active' => $is_active];
                ActivityLogger::log($action, ucfirst($action) . "d category: " . ($category['name'] ?? $category_id), 'categories', $category_id, $old_values, $new_values);
            }
        }
        
        if ($_POST['action'] === 'delete_category') {
            $category_id = (int)$_POST['category_id'];
            
            // Check if category has products
            $check_query = "SELECT COUNT(*) as count FROM products WHERE restaurant_id = :restaurant_id AND category = (SELECT name FROM categories WHERE id = :category_id)";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':restaurant_id', $restaurant_id);
            $check_stmt->bindParam(':category_id', $category_id);
            $check_stmt->execute();
            $products_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($products_count > 0) {
                $error = 'Cannot delete category that has products. Please move or delete products first.';
            } else {
                // Get category name for logging before deletion
                $cat_query = "SELECT name FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
                $cat_stmt = $db->prepare($cat_query);
                $cat_stmt->bindParam(':id', $category_id);
                $cat_stmt->bindParam(':restaurant_id', $restaurant_id);
                $cat_stmt->execute();
                $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                
                $query = "DELETE FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $category_id);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                
                if ($stmt->execute()) {
                    // Log the activity
                    ActivityLogger::log('delete', "Deleted category: " . ($category['name'] ?? $category_id), 'categories', $category_id);
                    
                    $success = 'Category deleted successfully';
                } else {
                    $error = 'Failed to delete category';
                }
            }
        }
        
        // User Management Actions
        if ($_POST['action'] === 'add_user') {
            $username = Security::sanitize($_POST['username']);
            $password = $_POST['password'];
            $role = Security::sanitize($_POST['role']);
            
            if ($username && $password && in_array($role, ['admin', 'user'])) {
                // Check for duplicate username within the same restaurant
                $check_query = "SELECT COUNT(*) as count FROM users WHERE restaurant_id = :restaurant_id AND username = :username";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Username already exists in this restaurant';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $query = "INSERT INTO users (restaurant_id, username, password, role) VALUES (:restaurant_id, :username, :password, :role)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':role', $role);
                    
                    if ($stmt->execute()) {
                        $user_id = $db->lastInsertId();
                        
                        // Log the activity
                        ActivityLogger::log('create', "Created new {$role} user: {$username}", 'users', $user_id, null, [
                            'username' => $username,
                            'role' => $role
                        ]);
                        
                        $success = 'User added successfully';
                    } else {
                        $error = 'Failed to add user';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        if ($_POST['action'] === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $username = Security::sanitize($_POST['username']);
            $role = Security::sanitize($_POST['role']);
            $new_password = $_POST['new_password'] ?? '';
            
            if ($username && in_array($role, ['admin', 'user'])) {
                // Check for duplicate username within restaurant (excluding current user)
                $check_query = "SELECT COUNT(*) as count FROM users WHERE restaurant_id = :restaurant_id AND username = :username AND id != :id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':restaurant_id', $restaurant_id);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':id', $user_id);
                $check_stmt->execute();
                $duplicate = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate['count'] > 0) {
                    $error = 'Username already exists in this restaurant';
                } else {
                    // Get old values before update
                    $old_query = "SELECT username, role FROM users WHERE id = :id AND restaurant_id = :restaurant_id";
                    $old_stmt = $db->prepare($old_query);
                    $old_stmt->bindParam(':id', $user_id);
                    $old_stmt->bindParam(':restaurant_id', $restaurant_id);
                    $old_stmt->execute();
                    $old_values = $old_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!empty($new_password)) {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $query = "UPDATE users SET username = :username, password = :password, role = :role WHERE id = :id AND restaurant_id = :restaurant_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':role', $role);
                        $stmt->bindParam(':id', $user_id);
                        $stmt->bindParam(':restaurant_id', $restaurant_id);
                    } else {
                        // Update without changing password
                        $query = "UPDATE users SET username = :username, role = :role WHERE id = :id AND restaurant_id = :restaurant_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':role', $role);
                        $stmt->bindParam(':id', $user_id);
                        $stmt->bindParam(':restaurant_id', $restaurant_id);
                    }
                    
                    if ($stmt->execute()) {
                        // Log the activity with old and new values
                        $new_values = [
                            'username' => $username,
                            'role' => $role
                        ];
                        if (!empty($new_password)) {
                            $new_values['password'] = '[Password Changed]';
                        }
                        
                        ActivityLogger::log('update', "Updated user: {$username}", 'users', $user_id, $old_values, $new_values);
                        
                        $success = 'User updated successfully';
                    } else {
                        $error = 'Failed to update user';
                    }
                }
            } else {
                $error = 'Please fill all fields correctly';
            }
        }
        
        if ($_POST['action'] === 'delete_user') {
            $user_id = (int)$_POST['user_id'];
            
            // Prevent deleting the current admin user
            if ($user_id == $_SESSION['user_id']) {
                $error = 'Cannot delete your own account';
            } else {
                // Verify user belongs to current restaurant before deletion
                $verify_query = "SELECT COUNT(*) as count FROM users WHERE id = :id AND restaurant_id = :restaurant_id";
                $verify_stmt = $db->prepare($verify_query);
                $verify_stmt->bindParam(':id', $user_id);
                $verify_stmt->bindParam(':restaurant_id', $restaurant_id);
                $verify_stmt->execute();
                $user_exists = $verify_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($user_exists == 0) {
                    $error = 'User not found or does not belong to your restaurant';
                } else {
                    // Get username before deletion for logging
                    $username_query = "SELECT username FROM users WHERE id = :id AND restaurant_id = :restaurant_id";
                    $username_stmt = $db->prepare($username_query);
                    $username_stmt->bindParam(':id', $user_id);
                    $username_stmt->bindParam(':restaurant_id', $restaurant_id);
                    $username_stmt->execute();
                    $deleted_user = $username_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if user has orders
                    $check_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = :user_id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    $orders_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($orders_count > 0) {
                        $error = 'Cannot delete user with existing orders. User has ' . $orders_count . ' order(s).';
                    } else {
                        $query = "DELETE FROM users WHERE id = :id AND restaurant_id = :restaurant_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $user_id);
                        $stmt->bindParam(':restaurant_id', $restaurant_id);
                        
                        if ($stmt->execute()) {
                            // Log the activity
                            ActivityLogger::log('delete', "Deleted user: " . ($deleted_user['username'] ?? $user_id), 'users', $user_id);
                            
                            $success = 'User deleted successfully';
                        } else {
                            $error = 'Failed to delete user';
                        }
                    }
                }
            }
        }
    }
    
    // Report Generation Handler
    if ($_POST['action'] === 'generate_report') {
        $period = Security::sanitize($_POST['period']);
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        // Calculate date range based on period
        switch ($period) {
            case 'today':
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
                $period_text = 'Today (' . date('M j, Y') . ')';
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d');
                $period_text = 'This Week (' . date('M j', strtotime($start_date)) . ' - ' . date('M j, Y') . ')';
                break;
            case 'month':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-d');
                $period_text = 'This Month (' . date('M Y') . ')';
                break;
            case 'year':
                $start_date = date('Y-01-01');
                $end_date = date('Y-m-d');
                $period_text = 'This Year (' . date('Y') . ')';
                break;
            case 'custom':
                if ($start_date && $end_date) {
                    $period_text = 'Custom Range (' . date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)) . ')';
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid date range']);
                    exit();
                }
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid period']);
                exit();
        }
        
        try {
            // Get overall statistics
            $query = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(SUM(tax_amount), 0) as total_tax
                FROM orders 
                WHERE restaurant_id = :restaurant_id 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                AND status = 'completed'";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get daily breakdown
            $daily_query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(SUM(tax_amount), 0) as tax
                FROM orders 
                WHERE restaurant_id = :restaurant_id 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                AND status = 'completed'
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
            
            $daily_stmt = $db->prepare($daily_query);
            $daily_stmt->bindParam(':restaurant_id', $restaurant_id);
            $daily_stmt->bindParam(':start_date', $start_date);
            $daily_stmt->bindParam(':end_date', $end_date);
            $daily_stmt->execute();
            $daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format daily data
            $formatted_daily = [];
            foreach ($daily_data as $day) {
                $formatted_daily[] = [
                    'date' => date('M j, Y', strtotime($day['date'])),
                    'orders' => (int)$day['orders'],
                    'revenue' => number_format((float)$day['revenue'], 2),
                    'tax' => number_format((float)$day['tax'], 2)
                ];
            }
            
            $report = [
                'period' => $period,
                'period_text' => $period_text,
                'total_orders' => (int)$stats['total_orders'],
                'total_revenue' => number_format((float)$stats['total_revenue'], 2),
                'total_tax' => number_format((float)$stats['total_tax'], 2),
                'avg_order' => $stats['total_orders'] > 0 ? number_format((float)$stats['total_revenue'] / $stats['total_orders'], 2) : '0.00',
                'daily_data' => $formatted_daily
            ];
            
            // Log the report generation activity
            ActivityLogger::log('create', "Generated sales report for {$period_text}", 'reports', null, null, [
                'period' => $period,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'total_orders' => (int)$stats['total_orders'],
                'total_revenue' => (float)$stats['total_revenue']
            ]);
            
            echo json_encode(['success' => true, 'report' => $report]);
            exit();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
            exit();
        }
    }
}

// Get statistics
$stats = [];

// Total sales today for current restaurant
$query = "SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM orders WHERE restaurant_id = :restaurant_id AND DATE(created_at) = CURDATE() AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$stats['today_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_sales'];

// Total orders today for current restaurant
$query = "SELECT COUNT(*) as today_orders FROM orders WHERE restaurant_id = :restaurant_id AND DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$stats['today_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_orders'];

// Total products for current restaurant (active and total)
$query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products
    FROM products WHERE restaurant_id = :restaurant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$product_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['active_products'] = $product_stats['active_products'];
$stats['total_products'] = $product_stats['total_products'];

// Get all products for current restaurant
$query = "SELECT * FROM products WHERE restaurant_id = :restaurant_id ORDER BY category, name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category-wise sales data for dashboard
$category_sales = [];
try {
    $query = "SELECT 
        p.category,
        COUNT(oi.id) as total_items_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue,
        COALESCE(SUM(oi.quantity), 0) as total_quantity_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        WHERE p.restaurant_id = :restaurant_id AND p.is_active = 1
        GROUP BY p.category
        HAVING COUNT(DISTINCT p.id) > 0
        ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $raw_category_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process category data for chart
    foreach ($raw_category_data as $cat_data) {
        $category_sales[] = [
            'category' => $cat_data['category'],
            'total_items_sold' => (int)$cat_data['total_items_sold'],
            'total_revenue' => (float)$cat_data['total_revenue'],
            'total_quantity_sold' => (int)$cat_data['total_quantity_sold']
        ];
    }
    
    // If no sales data, show categories with zero values for chart consistency
    if (empty($category_sales)) {
        $default_categories = ['Food', 'Drinks', 'Dessert'];
        foreach ($default_categories as $cat) {
            $category_sales[] = [
                'category' => $cat,
                'total_items_sold' => 0,
                'total_revenue' => 0,
                'total_quantity_sold' => 0
            ];
        }
    }
} catch (Exception $e) {
    // Fallback to basic categories if query fails
    $category_sales = [
        ['category' => 'Food', 'total_items_sold' => 0, 'total_revenue' => 0, 'total_quantity_sold' => 0],
        ['category' => 'Drinks', 'total_items_sold' => 0, 'total_revenue' => 0, 'total_quantity_sold' => 0],
        ['category' => 'Dessert', 'total_items_sold' => 0, 'total_revenue' => 0, 'total_quantity_sold' => 0]
    ];
}

// Get all categories for current restaurant (with fallback)
$categories = [];
try {
    $query = "SELECT * FROM categories WHERE restaurant_id = :restaurant_id ORDER BY sort_order, name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback to default categories if table doesn't exist
    $categories = [
        ['id' => 0, 'name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'is_active' => 1],
        ['id' => 0, 'name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'is_active' => 1],
        ['id' => 0, 'name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'is_active' => 1]
    ];
}

// If no categories exist, create default ones
if (empty($categories)) {
    $default_categories = [
        ['name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals'],
        ['name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments'],
        ['name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts']
    ];
    
    foreach ($default_categories as $index => $cat) {
        try {
            $query = "INSERT INTO categories (restaurant_id, name, description, icon, sort_order) VALUES (:restaurant_id, :name, :description, :icon, :sort_order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':name', $cat['name']);
            $stmt->bindParam(':description', $cat['description']);
            $stmt->bindParam(':icon', $cat['icon']);
            $stmt->bindParam(':sort_order', $index + 1);
            $stmt->execute();
        } catch (PDOException $e) {
            // Ignore if categories table doesn't exist
        }
    }
    
    // Reload categories after creation
    try {
        $query = "SELECT * FROM categories WHERE restaurant_id = :restaurant_id ORDER BY sort_order, name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Use fallback if still failing
        $categories = [
            ['id' => 0, 'name' => 'Food', 'icon' => '🍔', 'description' => 'Main dishes and meals', 'is_active' => 1],
            ['id' => 0, 'name' => 'Drinks', 'icon' => '☕', 'description' => 'Beverages and refreshments', 'is_active' => 1],
            ['id' => 0, 'name' => 'Dessert', 'icon' => '🍰', 'description' => 'Sweet treats and desserts', 'is_active' => 1]
        ];
    }
}

// Get recent orders for current restaurant
$query = "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.restaurant_id = :restaurant_id ORDER BY o.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for current restaurant only
$query = "SELECT id, username, role, created_at FROM users WHERE restaurant_id = :restaurant_id ORDER BY created_at ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily sales data for the last 7 days for Sales Trend chart
$sales_trend_data = [];
try {
    $query = "SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(total_amount), 0) as daily_sales,
        COUNT(*) as daily_orders
        FROM orders 
        WHERE restaurant_id = :restaurant_id 
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create array with all 7 days (including days with no sales)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('M j', strtotime("-$i days"));
        
        // Find data for this date
        $found_data = null;
        foreach ($daily_data as $data) {
            if ($data['date'] === $date) {
                $found_data = $data;
                break;
            }
        }
        
        $sales_trend_data[] = [
            'date' => $date,
            'day_name' => $day_name,
            'sales' => $found_data ? (float)$found_data['daily_sales'] : 0,
            'orders' => $found_data ? (int)$found_data['daily_orders'] : 0
        ];
    }
} catch (Exception $e) {
    // Fallback data if query fails
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('M j', strtotime("-$i days"));
        $sales_trend_data[] = [
            'date' => $date,
            'day_name' => $day_name,
            'sales' => 0,
            'orders' => 0
        ];
    }
}

// Get top 3 menu items data for the last 7 days
$top_menu_items = [];
try {
    $query = "SELECT 
        p.id,
        p.name,
        p.category,
        p.price,
        COALESCE(SUM(oi.quantity), 0) as total_quantity_sold,
        COALESCE(SUM(oi.subtotal), 0) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id 
        WHERE p.restaurant_id = :restaurant_id 
        AND p.is_active = 1
        AND (o.id IS NULL OR (o.status = 'completed' AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)))
        GROUP BY p.id, p.name, p.category, p.price
        ORDER BY total_quantity_sold DESC, total_revenue DESC
        LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $menu_items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure we have exactly 5 items (fill with placeholder if needed)
    for ($i = 0; $i < 5; $i++) {
        if (isset($menu_items_data[$i])) {
            $top_menu_items[] = [
                'name' => $menu_items_data[$i]['name'],
                'category' => $menu_items_data[$i]['category'],
                'price' => (float)$menu_items_data[$i]['price'],
                'quantity_sold' => (int)$menu_items_data[$i]['total_quantity_sold'],
                'revenue' => (float)$menu_items_data[$i]['total_revenue']
            ];
        } else {
            // Fill empty slots with placeholder data
            $top_menu_items[] = [
                'name' => 'No Data',
                'category' => '-',
                'price' => 0,
                'quantity_sold' => 0,
                'revenue' => 0
            ];
        }
    }
} catch (Exception $e) {
    // Fallback data if query fails
    $top_menu_items = [
        ['name' => 'No Data', 'category' => '-', 'price' => 0, 'quantity_sold' => 0, 'revenue' => 0],
        ['name' => 'No Data', 'category' => '-', 'price' => 0, 'quantity_sold' => 0, 'revenue' => 0],
        ['name' => 'No Data', 'category' => '-', 'price' => 0, 'quantity_sold' => 0, 'revenue' => 0],
        ['name' => 'No Data', 'category' => '-', 'price' => 0, 'quantity_sold' => 0, 'revenue' => 0],
        ['name' => 'No Data', 'category' => '-', 'price' => 0, 'quantity_sold' => 0, 'revenue' => 0]
    ];
}

// Get payment type data for the last 7 days
$payment_type_data = [];
try {
    $query = "SELECT 
        payment_method,
        COUNT(*) as total_transactions,
        COALESCE(SUM(total_amount), 0) as total_amount
        FROM orders 
        WHERE restaurant_id = :restaurant_id 
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND status = 'completed'
        GROUP BY payment_method
        ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process payment data and ensure we have both cash and qr
    $cash_data = ['payment_method' => 'cash', 'total_transactions' => 0, 'total_amount' => 0];
    $qr_data = ['payment_method' => 'qr', 'total_transactions' => 0, 'total_amount' => 0];
    
    foreach ($payment_data as $payment) {
        if ($payment['payment_method'] === 'cash') {
            $cash_data = $payment;
        } elseif ($payment['payment_method'] === 'qr_code') {
            $qr_data = $payment;
        }
    }
    
    $payment_type_data = [
        [
            'method' => 'Cash',
            'transactions' => (int)$cash_data['total_transactions'],
            'amount' => (float)$cash_data['total_amount']
        ],
        [
            'method' => 'QR Code',
            'transactions' => (int)$qr_data['total_transactions'],
            'amount' => (float)$qr_data['total_amount']
        ]
    ];
} catch (Exception $e) {
    // Fallback data if query fails
    $payment_type_data = [
        ['method' => 'Cash', 'transactions' => 0, 'amount' => 0],
        ['method' => 'QR Code', 'transactions' => 0, 'amount' => 0]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiraBOS - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#6366F1',
                        accent: '#EF4444',
                        food: '#FF6B6B',
                        drinks: '#4ECDC4',
                        dessert: '#FFE66D'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-header: linear-gradient(to right, #eef2ff, #f3e8ff);
            --bg-card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e0e7ff;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
        }
        
        [data-theme="dark"] {
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-header: linear-gradient(to right, #1f2937, #374151);
            --bg-card: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --border-primary: #4b5563;
            --accent-primary: #818cf8;
            --accent-secondary: #a78bfa;
        }
        
        [data-theme="minimal"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-header: linear-gradient(to right, #f8fafc, #f1f5f9);
            --bg-card: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-primary: #e2e8f0;
            --accent-primary: #0f172a;
            --accent-secondary: #334155;
        }
        
        [data-theme="original"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-header: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-primary: #e5e7eb;
            --accent-primary: #4f46e5;
            --accent-secondary: #6366f1;
        }
        
        .theme-transition {
            transition: all 0.3s ease;
        }
        
        /* Theme-specific header styles */
        .theme-header {
            color: var(--text-primary);
        }
        
        [data-theme="colorful"] .theme-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Theme option buttons */
        .theme-option {
            background: var(--bg-card);
            border-color: var(--border-primary);
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .theme-option.active {
            border-color: var(--accent-primary);
            background: var(--accent-primary);
            color: white;
        }
        
        .theme-option:hover {
            border-color: var(--accent-secondary);
            background: var(--bg-secondary);
        }
        
        /* Hide scrollbars globally but keep functionality */
        * {
            scrollbar-width: none !important; /* Firefox */
            -ms-overflow-style: none !important; /* Internet Explorer 10+ */
        }
        
        *::-webkit-scrollbar {
            width: 0px !important;
            height: 0px !important;
            background: transparent !important;
            display: none !important; /* Chrome, Safari, Opera */
        }
        
        *::-webkit-scrollbar-track {
            background: transparent !important;
        }
        
        *::-webkit-scrollbar-thumb {
            background: transparent !important;
        }
        
        html, body {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        
        html::-webkit-scrollbar, body::-webkit-scrollbar {
            width: 0px !important;
            height: 0px !important;
            display: none !important;
        }
        
        /* Ensure hidden class works */
        .hidden {
            display: none !important;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="theme-transition min-h-screen font-sans" style="background: var(--bg-primary)">
    <!-- Header -->
    <header class="theme-transition shadow-lg" style="background: var(--bg-header); border-bottom: 1px solid var(--border-primary)">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                <div>
                    <h1 class="text-2xl font-bold theme-header">Admin Dashboard</h1>
                    <p class="text-sm" style="color: var(--text-secondary)"><?= htmlspecialchars($restaurant['name']) ?></p>
                </div>
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <span class="text-sm hidden xs:inline" style="color: var(--text-secondary)">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                    <span class="text-sm xs:hidden" style="color: var(--text-secondary)"><?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                    <a href="cashier.php" class="text-sm font-medium hover:opacity-80 transition-opacity" style="color: var(--accent-primary)">Cashier View</a>
                    <a href="logout.php" class="text-accent hover:text-red-600 text-sm font-medium">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="theme-transition" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-primary)">
        <div class="container mx-auto px-4">
            <div class="flex space-x-1 overflow-x-auto">
                <a href="admin.php?page=dashboard" id="tab-dashboard" class="nav-tab <?= ($current_page === 'dashboard') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'dashboard') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'dashboard') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="admin.php?page=menu" id="tab-menu" class="nav-tab <?= ($current_page === 'menu') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'menu') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'menu') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>🍽️</span>
                    <span>Menu Management</span>
                </a>
                <a href="admin.php?page=users" id="tab-users" class="nav-tab <?= ($current_page === 'users') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'users') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'users') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>👥</span>
                    <span>User Management</span>
                </a>
                <a href="admin.php?page=logs" id="tab-logs" class="nav-tab <?= ($current_page === 'logs') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'logs') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'logs') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📝</span>
                    <span>Activity Logs</span>
                </a>
                <a href="admin.php?page=reports" id="tab-reports" class="nav-tab <?= ($current_page === 'reports') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'reports') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'reports') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>📈</span>
                    <span>Sales Reports</span>
                </a>
                <a href="admin.php?page=settings" id="tab-settings" class="nav-tab <?= ($current_page === 'settings') ? 'active' : '' ?> flex items-center space-x-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap" style="color: <?= ($current_page === 'settings') ? 'var(--accent-primary)' : 'var(--text-secondary)' ?>; border-bottom: <?= ($current_page === 'settings') ? '2px solid var(--accent-primary)' : 'none' ?>; text-decoration: none;">
                    <span>⚙️</span>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <?php if (isset($error)): ?>
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 transition-all duration-500 relative">
                <?= htmlspecialchars($error) ?>
                <button onclick="hideMessage('error-message')" class="absolute top-2 right-4 text-red-600 hover:text-red-800 text-lg font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 transition-all duration-500 relative">
                <?= htmlspecialchars($success) ?>
                <button onclick="hideMessage('success-message')" class="absolute top-2 right-4 text-green-600 hover:text-green-800 text-lg font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Content Container -->
        <div class="pb-16 mb-16">
            <?php
            // Include the appropriate page content
            $page_file = "admin-{$current_page}.php";
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                echo '<div class="p-8 text-center">';
                echo '<h2 class="text-2xl font-bold mb-4 theme-header">Page Not Found</h2>';
                echo '<p style="color: var(--text-secondary)">The requested page is not available.</p>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <script>
        // Theme functionality
        let currentTheme = localStorage.getItem('pos-admin-theme') || 'colorful';
        
        function setTheme(theme) {
            currentTheme = theme;
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('pos-admin-theme', theme);
            
            // Update theme option buttons
            document.querySelectorAll('.theme-option').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const activeThemeBtn = document.getElementById('theme-' + theme);
            if (activeThemeBtn) {
                activeThemeBtn.classList.add('active');
            }
        }
        
        // Product edit functions
        function editProduct(productId) {
            const productDiv = document.getElementById('product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
        }
        
        function cancelEdit(productId) {
            const productDiv = document.getElementById('product-' + productId);
            const displayDiv = productDiv.querySelector('.product-display');
            const editForm = productDiv.querySelector('.product-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
        }
        
        // Category management functions
        function editCategory(categoryId) {
            const categoryDiv = document.getElementById('category-' + categoryId);
            const displayDiv = categoryDiv.querySelector('.category-display');
            const editForm = categoryDiv.querySelector('.category-edit');
            
            displayDiv.classList.add('hidden');
            editForm.classList.remove('hidden');
        }
        
        function cancelCategoryEdit(categoryId) {
            const categoryDiv = document.getElementById('category-' + categoryId);
            const displayDiv = categoryDiv.querySelector('.category-display');
            const editForm = categoryDiv.querySelector('.category-edit');
            
            editForm.classList.add('hidden');
            displayDiv.classList.remove('hidden');
        }
        
        // Message management functions
        function hideMessage(messageId) {
            const messageEl = document.getElementById(messageId);
            if (messageEl) {
                messageEl.style.opacity = '0';
                messageEl.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    messageEl.style.display = 'none';
                }, 500);
            }
        }
        
        function autoHideMessages() {
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            
            if (successMessage) {
                setTimeout(() => hideMessage('success-message'), 4000);
            }
            
            if (errorMessage) {
                setTimeout(() => hideMessage('error-message'), 6000);
            }
        }
        
        // Order details functions
        function toggleOrderDetails(orderId) {
            const detailsDiv = document.getElementById('order-details-' + orderId);
            const button = document.getElementById('details-btn-' + orderId);
            
            if (detailsDiv.classList.contains('hidden')) {
                detailsDiv.classList.remove('hidden');
                button.textContent = 'Hide Details';
                
                if (!detailsDiv.hasAttribute('data-loaded')) {
                    loadOrderDetails(orderId);
                }
            } else {
                detailsDiv.classList.add('hidden');
                button.textContent = 'View Details';
            }
        }
        
        function loadOrderDetails(orderId) {
            const detailsDiv = document.getElementById('order-details-' + orderId);
            
            fetch('get_order_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let itemsHtml = '<p class="text-sm font-medium mb-2" style="color: var(--text-primary)">Items Ordered:</p><div class="space-y-1">';
                    
                    data.items.forEach(item => {
                        itemsHtml += `
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary)">
                                    ${item.product_name} × ${item.quantity}
                                </span>
                                <span style="color: var(--text-secondary)">
                                    RM${(item.price * item.quantity).toFixed(2)}
                                </span>
                            </div>
                        `;
                    });
                    
                    itemsHtml += '</div>';
                    detailsDiv.innerHTML = itemsHtml;
                    detailsDiv.setAttribute('data-loaded', 'true');
                } else {
                    detailsDiv.innerHTML = '<p class="text-sm text-red-500">Failed to load order details</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                detailsDiv.innerHTML = '<p class="text-sm text-red-500">Error loading order details</p>';
            });
        }
        
        // Reports functions
        function toggleCustomDates() {
            const periodSelect = document.getElementById('report-period');
            const customInputs = document.querySelectorAll('.custom-date-input');
            
            if (periodSelect && periodSelect.value === 'custom') {
                customInputs.forEach(input => input.style.display = 'block');
            } else {
                customInputs.forEach(input => input.style.display = 'none');
            }
        }
        
        function generateReport() {
            const period = document.getElementById('report-period')?.value;
            const startDate = document.getElementById('start-date')?.value;
            const endDate = document.getElementById('end-date')?.value;
            const btnText = document.getElementById('generate-btn-text');
            const resultsArea = document.getElementById('report-results');
            const reportContent = document.getElementById('report-content');
            
            if (!period) return;
            
            // Validate custom date range
            if (period === 'custom' && (!startDate || !endDate)) {
                showToast('Please select both start and end dates', true);
                return;
            }
            
            if (period === 'custom' && new Date(startDate) > new Date(endDate)) {
                showToast('End date must be after start date', true);
                return;
            }
            
            // Show loading state
            if (btnText) btnText.textContent = 'Generating...';
            if (resultsArea) resultsArea.style.display = 'block';
            if (reportContent) reportContent.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div><p class="mt-2" style="color: var(--text-secondary)">Generating report...</p></div>';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'generate_report');
            formData.append('period', period);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('csrf_token', '<?= Security::generateCSRFToken() ?>');
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (btnText) btnText.textContent = 'Generate Report';
                
                if (data.success) {
                    displayReport(data.report);
                    if (resultsArea) resultsArea.scrollIntoView({ behavior: 'smooth' });
                } else {
                    showToast(data.message || 'Failed to generate report', true);
                }
            })
            .catch(error => {
                if (btnText) btnText.textContent = 'Generate Report';
                showToast('Error generating report', true);
            });
        }
        
        function displayReport(reportData) {
            const reportContent = document.getElementById('report-content');
            if (!reportContent) return;
            
            window.currentReportData = reportData;
            
            let html = `
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <h4 class="font-medium mb-1" style="color: var(--text-secondary)">Total Orders</h4>
                        <div class="text-2xl font-bold text-blue-500">${reportData.total_orders}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <h4 class="font-medium mb-1" style="color: var(--text-secondary)">Total Revenue</h4>
                        <div class="text-2xl font-bold text-green-500">RM${reportData.total_revenue}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <h4 class="font-medium mb-1" style="color: var(--text-secondary)">Average Order</h4>
                        <div class="text-2xl font-bold text-purple-500">RM${reportData.avg_order}</div>
                    </div>
                    <div class="p-4 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <h4 class="font-medium mb-1" style="color: var(--text-secondary)">Total Tax</h4>
                        <div class="text-2xl font-bold text-orange-500">RM${reportData.total_tax}</div>
                    </div>
                </div>
            `;
            
            if (reportData.daily_data && reportData.daily_data.length > 0) {
                html += `
                    <div class="p-4 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <h4 class="font-semibold mb-4" style="color: var(--text-primary)">Daily Breakdown - ${reportData.period_text}</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border-primary)">
                                        <th class="text-left p-3" style="color: var(--text-secondary)">Date</th>
                                        <th class="text-center p-3" style="color: var(--text-secondary)">Orders</th>
                                        <th class="text-right p-3" style="color: var(--text-secondary)">Revenue</th>
                                        <th class="text-right p-3" style="color: var(--text-secondary)">Tax</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                reportData.daily_data.forEach(day => {
                    html += `
                        <tr style="border-bottom: 1px solid var(--border-primary)">
                            <td class="p-3" style="color: var(--text-primary)">${day.date}</td>
                            <td class="p-3 text-center text-blue-600">${day.orders}</td>
                            <td class="p-3 text-green-600 font-medium" style="text-align: right">RM${day.revenue}</td>
                            <td class="p-3 text-orange-600" style="text-align: right">RM${day.tax}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="p-8 text-center" style="background: var(--bg-card); border: 1px solid var(--border-primary)">
                        <p style="color: var(--text-secondary)">No data available for the selected period</p>
                    </div>
                `;
            }
            
            reportContent.innerHTML = html;
        }
        
        function showToast(message, isError = false) {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 transform translate-x-full transition-transform duration-300 ${isError ? 'bg-red-500' : 'bg-green-500'}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Animate out and remove
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        // Sales Trend Chart
        function initializeSalesTrendChart() {
            const ctx = document.getElementById('salesTrendChart');
            if (!ctx) return;
            
            // Sales trend data from PHP
            const salesData = <?= json_encode($sales_trend_data) ?>;
            
            const labels = salesData.map(item => item.day_name);
            const salesValues = salesData.map(item => parseFloat(item.sales));
            const orderCounts = salesData.map(item => parseInt(item.orders));
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales (RM)',
                        data: salesValues,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }, {
                        label: 'Orders',
                        data: orderCounts,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Sales: RM' + context.parsed.y.toFixed(2);
                                    } else {
                                        return 'Orders: ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'RM' + value.toFixed(0);
                                },
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return value + ' orders';
                                },
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }
        
        // Top Menu Items Bar Chart
        function initializeTopMenuChart() {
            const ctx = document.getElementById('topMenuChart');
            if (!ctx) return;
            
            // Top menu items data from PHP
            const menuData = <?= json_encode($top_menu_items) ?>;
            
            const labels = menuData.map(item => item.name);
            const quantitySold = menuData.map(item => parseInt(item.quantity_sold));
            const revenues = menuData.map(item => parseFloat(item.revenue));
            
            // Define colors for the bars
            const colors = [
                'rgba(255, 193, 7, 0.8)',   // Gold for #1
                'rgba(108, 117, 125, 0.8)', // Silver for #2
                'rgba(205, 127, 50, 0.8)',  // Bronze for #3
                'rgba(74, 144, 226, 0.8)',  // Blue for #4
                'rgba(156, 39, 176, 0.8)'   // Purple for #5
            ];
            
            const borderColors = [
                'rgba(255, 193, 7, 1)',
                'rgba(108, 117, 125, 1)',
                'rgba(205, 127, 50, 1)',
                'rgba(74, 144, 226, 1)',
                'rgba(156, 39, 176, 1)'
            ];
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Items Sold',
                        data: quantitySold,
                        backgroundColor: colors,
                        borderColor: borderColors,
                        borderWidth: 2,
                        borderRadius: {
                            topLeft: 4,
                            topRight: 4,
                            bottomLeft: 0,
                            bottomRight: 0
                        },
                        borderSkipped: 'bottom',
                        barPercentage: 0.5,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    const itemIndex = context.dataIndex;
                                    const item = menuData[itemIndex];
                                    return [
                                        `Items Sold: ${context.parsed.y}`,
                                        `Revenue: RM${item.revenue.toFixed(2)}`,
                                        `Category: ${item.category}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                maxRotation: 0,
                                callback: function(value, index) {
                                    const label = this.getLabelForValue(value);
                                    // Truncate long labels
                                    return label.length > 12 ? label.substring(0, 12) + '...' : label;
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return Math.floor(value) === value ? value : '';
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }
        
        // Category Performance Doughnut Chart
        function initializeCategoryChart() {
            const ctx = document.getElementById('categoryChart');
            if (!ctx) return;
            
            // Category performance data from PHP
            const categoryData = <?= json_encode($category_sales) ?>;
            
            // Filter out categories with no revenue for cleaner chart
            const categoriesWithSales = categoryData.filter(cat => cat.total_revenue > 0);
            
            // If no sales data, show a placeholder chart
            if (categoriesWithSales.length === 0) {
                const emptyChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['rgba(200, 200, 200, 0.5)'],
                            borderColor: ['rgba(150, 150, 150, 1)'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    }
                });
                return;
            }
            
            const labels = categoriesWithSales.map(cat => cat.category);
            const revenues = categoriesWithSales.map(cat => parseFloat(cat.total_revenue));
            const quantities = categoriesWithSales.map(cat => parseInt(cat.total_quantity_sold));
            
            // Define colors for categories
            const colors = [
                'rgba(255, 107, 107, 0.8)', // Red
                'rgba(78, 205, 196, 0.8)',  // Teal
                'rgba(255, 230, 109, 0.8)', // Yellow
                'rgba(116, 185, 255, 0.8)', // Blue
                'rgba(162, 155, 254, 0.8)', // Purple
                'rgba(255, 159, 67, 0.8)',  // Orange
                'rgba(72, 219, 251, 0.8)'   // Cyan
            ];
            
            const borderColors = [
                'rgba(255, 107, 107, 1)',
                'rgba(78, 205, 196, 1)',
                'rgba(255, 230, 109, 1)',
                'rgba(116, 185, 255, 1)',
                'rgba(162, 155, 254, 1)',
                'rgba(255, 159, 67, 1)',
                'rgba(72, 219, 251, 1)'
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenues,
                        backgroundColor: colors.slice(0, labels.length),
                        borderColor: borderColors.slice(0, labels.length),
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%', // Makes it a doughnut (hollow center)
                    plugins: {
                        legend: {
                            display: false // We'll use custom legend below chart
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const category = context.label;
                                    const revenue = context.parsed;
                                    const quantity = quantities[context.dataIndex];
                                    const percentage = ((revenue / revenues.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                    
                                    return [
                                        `${category}`,
                                        `Revenue: RM${revenue.toFixed(2)}`,
                                        `Items Sold: ${quantity}`,
                                        `Share: ${percentage}%`
                                    ];
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            cornerRadius: 6,
                            padding: 10
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: false,
                        duration: 1000,
                        easing: 'easeInOutCubic'
                    },
                    interaction: {
                        intersect: false
                    }
                }
            });
        }
        
        // Payment Type Doughnut Chart
        function initializePaymentChart() {
            const ctx = document.getElementById('paymentChart');
            if (!ctx) return;
            
            // Payment type data from PHP
            const paymentData = <?= json_encode($payment_type_data) ?>;
            
            // Filter out payment methods with no transactions for cleaner chart
            const paymentsWithTransactions = paymentData.filter(payment => payment.amount > 0);
            
            // If no payment data, show a placeholder chart
            if (paymentsWithTransactions.length === 0) {
                const emptyChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['rgba(200, 200, 200, 0.5)'],
                            borderColor: ['rgba(150, 150, 150, 1)'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    }
                });
                return;
            }
            
            const labels = paymentsWithTransactions.map(payment => payment.method);
            const amounts = paymentsWithTransactions.map(payment => parseFloat(payment.amount));
            const transactions = paymentsWithTransactions.map(payment => parseInt(payment.transactions));
            
            // Define colors for payment methods
            const colors = [
                'rgba(16, 185, 129, 0.8)', // Green for Cash
                'rgba(59, 130, 246, 0.8)',  // Blue for QR Code
                'rgba(245, 158, 11, 0.8)',  // Amber for other methods
                'rgba(139, 92, 246, 0.8)'   // Purple for additional methods
            ];
            
            const borderColors = [
                'rgba(16, 185, 129, 1)',
                'rgba(59, 130, 246, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(139, 92, 246, 1)'
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Payment Amount',
                        data: amounts,
                        backgroundColor: colors.slice(0, labels.length),
                        borderColor: borderColors.slice(0, labels.length),
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%', // Makes it a doughnut (hollow center)
                    plugins: {
                        legend: {
                            display: false // We'll use custom legend below chart
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const method = context.label;
                                    const amount = context.parsed;
                                    const transactionCount = transactions[context.dataIndex];
                                    const percentage = ((amount / amounts.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                    
                                    return [
                                        `${method}`,
                                        `Amount: RM${amount.toFixed(2)}`,
                                        `Transactions: ${transactionCount}`,
                                        `Share: ${percentage}%`
                                    ];
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            cornerRadius: 6,
                            padding: 10
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: false,
                        duration: 1000,
                        easing: 'easeInOutCubic'
                    },
                    interaction: {
                        intersect: false
                    }
                }
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme
            document.body.setAttribute('data-theme', currentTheme);
            const activeThemeBtn = document.getElementById('theme-' + currentTheme);
            if (activeThemeBtn) {
                activeThemeBtn.classList.add('active');
            }
            
            // Auto-hide messages
            autoHideMessages();
            
            // Initialize Charts
            initializeSalesTrendChart();
            initializeTopMenuChart();
            initializeCategoryChart();
            initializePaymentChart();
        });
    </script>
</body>
</html>