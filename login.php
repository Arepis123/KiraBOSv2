<?php
// login.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['restaurant_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: cashier.php");
    }
    exit();
}

require_once 'config.php';

$error = '';

if ($_POST) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = Security::sanitize($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                $database = Database::getInstance();
                $db = $database->getConnection();
                
                if (!$db) {
                    $error = 'Database connection failed.';
                } else {
                    $query = "SELECT u.id, u.username, u.password, u.role, u.restaurant_id, u.first_name, u.last_name, r.name as restaurant_name, r.slug as restaurant_slug 
                             FROM users u 
                             JOIN restaurants r ON u.restaurant_id = r.id 
                             WHERE u.username = :username AND u.is_active = 1 AND r.is_active = 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() >= 1) {
                        // If multiple restaurants have the same username, show restaurant selection
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($users) == 1) {
                            // Single restaurant match
                            $user = $users[0];
                            if (password_verify($password, $user['password'])) {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role'] = $user['role'];
                                $_SESSION['restaurant_id'] = $user['restaurant_id'];
                                $_SESSION['restaurant_name'] = $user['restaurant_name'];
                                $_SESSION['restaurant_slug'] = $user['restaurant_slug'];
                                $_SESSION['first_name'] = $user['first_name'];
                                $_SESSION['last_name'] = $user['last_name'];
                                $_SESSION['last_regeneration'] = time();
                                
                                // Log successful login
                                ActivityLogger::log('login', "User {$user['username']} ({$user['role']}) logged in successfully");
                                
                                // Redirect based on role
                                if ($user['role'] === 'admin') {
                                    header("Location: admin.php");
                                } else {
                                    header("Location: cashier.php");
                                }
                                exit();
                            } else {
                                $error = 'Invalid username or password.';
                            }
                        } else {
                            // Multiple restaurants - need to show selection
                            $valid_user = null;
                            foreach ($users as $user) {
                                if (password_verify($password, $user['password'])) {
                                    $valid_user = $user;
                                    break;
                                }
                            }
                            
                            if ($valid_user) {
                                $_SESSION['temp_users'] = $users;
                                $_SESSION['temp_password_verified'] = true;
                                header("Location: select_restaurant.php");
                                exit();
                            } else {
                                $error = 'Invalid username or password.';
                            }
                        }
                    } else {
                        $error = 'Invalid username or password.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error occurred. Please try again.';
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
    <title>KiraBOS - Login</title>
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
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">KiraBOS</h1>
            <p class="text-gray-600">Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>


        <form method="POST" action="login.php" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
            
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                    placeholder="Enter your password"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105"
            >
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600">
                Demo accounts:<br>
                <span class="font-mono bg-gray-100 px-2 py-1 rounded">admin / admin123</span><br>
                <span class="font-mono bg-gray-100 px-2 py-1 rounded">cashier / cashier123</span><br>
                <span class="text-xs text-gray-500 mt-2 block">Available for Demo Restaurant, Pizza Palace, and Burger House</span>
            </p>
        </div>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                New restaurant? 
                <a href="register_restaurant.php" class="text-primary hover:text-secondary font-semibold">Register here</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>