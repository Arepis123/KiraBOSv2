<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['restaurant_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: cashier.php");
    }
    exit();
}

if ($_POST) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $restaurant_name = Security::sanitize($_POST['restaurant_name']);
        $restaurant_slug = Security::sanitize($_POST['restaurant_slug']);
        $address = Security::sanitize($_POST['address']);
        $phone = Security::sanitize($_POST['phone']);
        $email = Security::sanitize($_POST['email']);
        $admin_username = Security::sanitize($_POST['admin_username']);
        $admin_password = $_POST['admin_password'];
        $admin_first_name = Security::sanitize($_POST['admin_first_name']);
        $admin_last_name = Security::sanitize($_POST['admin_last_name']);
        $admin_email = Security::sanitize($_POST['admin_email']);
        
        // Validation
        if (empty($restaurant_name) || empty($restaurant_slug) || empty($admin_username) || empty($admin_password) || empty($admin_first_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($admin_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $restaurant_slug)) {
            $error = 'Restaurant slug can only contain lowercase letters, numbers, and hyphens.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $db->beginTransaction();
                
                // Check if restaurant slug already exists
                $query = "SELECT COUNT(*) as count FROM restaurants WHERE slug = :slug";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':slug', $restaurant_slug);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    throw new Exception('Restaurant URL slug already exists. Please choose a different one.');
                }
                
                // Check if admin username already exists (across all restaurants)
                $query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $admin_username);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    throw new Exception('Username already exists. Please choose a different one.');
                }
                
                // Create restaurant
                $query = "INSERT INTO restaurants (name, slug, address, phone, email) VALUES (:name, :slug, :address, :phone, :email)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $restaurant_name);
                $stmt->bindParam(':slug', $restaurant_slug);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                $restaurant_id = $db->lastInsertId();
                
                // Create admin user
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (restaurant_id, username, password, role, first_name, last_name, email) VALUES (:restaurant_id, :username, :password, 'admin', :first_name, :last_name, :email)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                $stmt->bindParam(':username', $admin_username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':first_name', $admin_first_name);
                $stmt->bindParam(':last_name', $admin_last_name);
                $stmt->bindParam(':email', $admin_email);
                $stmt->execute();
                
                // Create sample products for the restaurant
                $sample_products = [
                    ['Burger', 'Juicy beef burger with fresh vegetables', 8.99, 'Food'],
                    ['French Fries', 'Crispy golden french fries', 3.99, 'Food'],
                    ['Coca Cola', 'Cold refreshing cola drink', 2.99, 'Drinks'],
                    ['Coffee', 'Fresh brewed coffee', 4.99, 'Drinks'],
                    ['Ice Cream', 'Vanilla ice cream', 3.49, 'Dessert'],
                    ['Water', 'Bottled water', 1.99, 'Drinks']
                ];
                
                $query = "INSERT INTO products (restaurant_id, name, description, price, category) VALUES (:restaurant_id, :name, :description, :price, :category)";
                $stmt = $db->prepare($query);
                
                foreach ($sample_products as $index => $product) {
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':name', $product[0]);
                    $stmt->bindParam(':description', $product[1]);
                    $stmt->bindParam(':price', $product[2]);
                    $stmt->bindParam(':category', $product[3]);
                    $stmt->execute();
                }
                
                $db->commit();
                
                $success = "Restaurant registered successfully! You can now login with your credentials.";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Restaurant - KiraBOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1e40af'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center py-8">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Register Your Restaurant</h1>
            <p class="text-gray-600">Join KiraBOS and start managing your restaurant today</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($success) ?>
                <div class="mt-3">
                    <a href="login.php" class="text-green-800 font-semibold hover:underline">Go to Login →</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
            
            <!-- Restaurant Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Restaurant Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="restaurant_name" class="block text-sm font-medium text-gray-700 mb-2">Restaurant Name *</label>
                        <input 
                            type="text" 
                            id="restaurant_name" 
                            name="restaurant_name" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="Enter restaurant name"
                            value="<?= htmlspecialchars($_POST['restaurant_name'] ?? '') ?>"
                        >
                    </div>
                    
                    <div>
                        <label for="restaurant_slug" class="block text-sm font-medium text-gray-700 mb-2">URL Slug *</label>
                        <input 
                            type="text" 
                            id="restaurant_slug" 
                            name="restaurant_slug" 
                            required 
                            pattern="[a-z0-9-]+"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="my-restaurant"
                            value="<?= htmlspecialchars($_POST['restaurant_slug'] ?? '') ?>"
                        >
                        <p class="text-xs text-gray-500 mt-1">Only lowercase letters, numbers, and hyphens</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="Phone number"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        >
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Restaurant Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="restaurant@example.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea 
                        id="address" 
                        name="address" 
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                        placeholder="Restaurant address"
                    ><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Admin User Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Admin User Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="admin_first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                        <input 
                            type="text" 
                            id="admin_first_name" 
                            name="admin_first_name" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="First name"
                            value="<?= htmlspecialchars($_POST['admin_first_name'] ?? '') ?>"
                        >
                    </div>
                    
                    <div>
                        <label for="admin_last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input 
                            type="text" 
                            id="admin_last_name" 
                            name="admin_last_name" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="Last name"
                            value="<?= htmlspecialchars($_POST['admin_last_name'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label for="admin_username" class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                        <input 
                            type="text" 
                            id="admin_username" 
                            name="admin_username" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="Admin username"
                            value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>"
                        >
                    </div>
                    
                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input 
                            type="email" 
                            id="admin_email" 
                            name="admin_email" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                            placeholder="admin@example.com"
                            value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input 
                        type="password" 
                        id="admin_password" 
                        name="admin_password" 
                        required 
                        minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                        placeholder="Minimum 6 characters"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105"
            >
                Register Restaurant
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Already have an account? 
                <a href="login.php" class="text-primary hover:text-secondary font-semibold">Login here</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-generate slug from restaurant name
        document.getElementById('restaurant_name').addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            document.getElementById('restaurant_slug').value = slug;
        });
    </script>
</body>
</html>