<?php
// login_fixed.php - Working version
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$error = '';
$debug = '';

if ($_POST) {
    $debug .= "Form submitted. ";
    
    $username = Security::sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $debug .= "Username: '$username', Password length: " . strlen($password) . ". ";
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
        $debug .= "Empty fields detected. ";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                $error = 'Database connection failed.';
                $debug .= "Database connection is null. ";
            } else {
                $debug .= "Database connected successfully. ";
                
                $query = "SELECT id, username, password, role FROM users WHERE username = :username";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                $debug .= "Query executed, found " . $stmt->rowCount() . " rows. ";
                
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $debug .= "User found: " . $row['username'] . " (role: " . $row['role'] . "). ";
                    
                    if (password_verify($password, $row['password'])) {
                        $debug .= "Password verified successfully. ";
                        
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['last_regeneration'] = time();
                        
                        $debug .= "Session set. Redirecting to " . ($row['role'] === 'admin' ? 'admin.php' : 'cashier.php') . ". ";
                        
                        // Redirect based on role
                        if ($row['role'] === 'admin') {
                            header("Location: admin.php");
                        } else {
                            header("Location: cashier.php");
                        }
                        exit();
                    } else {
                        $error = 'Invalid username or password. (Password verification failed)';
                        $debug .= "Password verification failed. ";
                    }
                } else {
                    $error = 'Invalid username or password. (User not found)';
                    $debug .= "User not found in database. ";
                }
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
            $debug .= "Exception: " . $e->getMessage() . ". ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Login (Fixed)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">POS System (Fixed)</h1>
            <p class="text-gray-600">Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($debug && !empty($debug)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg mb-6">
                <strong>Debug Info:</strong> <?= htmlspecialchars($debug) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login_fixed.php" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
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
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    placeholder="Enter your password"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200"
            >
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600">
                Demo accounts:<br>
                <span class="font-mono bg-gray-100 px-2 py-1 rounded">admin / admin123</span><br>
                <span class="font-mono bg-gray-100 px-2 py-1 rounded">cashier / cashier123</span>
            </p>
        </div>
    </div>
</body>
</html>