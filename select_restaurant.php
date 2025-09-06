<?php
session_start();
require_once 'config.php';

// Redirect if no temp users or not password verified
if (!isset($_SESSION['temp_users']) || !isset($_SESSION['temp_password_verified'])) {
    header("Location: login.php");
    exit();
}

$error = '';

// Handle restaurant selection
if ($_POST && isset($_POST['restaurant_id'])) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $selected_restaurant_id = (int)$_POST['restaurant_id'];
        
        // Find the user for the selected restaurant
        $selected_user = null;
        foreach ($_SESSION['temp_users'] as $user) {
            if ($user['restaurant_id'] == $selected_restaurant_id) {
                $selected_user = $user;
                break;
            }
        }
        
        if ($selected_user) {
            // Set session variables
            session_regenerate_id(true);
            $_SESSION['user_id'] = $selected_user['id'];
            $_SESSION['username'] = $selected_user['username'];
            $_SESSION['role'] = $selected_user['role'];
            $_SESSION['restaurant_id'] = $selected_user['restaurant_id'];
            $_SESSION['restaurant_name'] = $selected_user['restaurant_name'];
            $_SESSION['restaurant_slug'] = $selected_user['restaurant_slug'];
            $_SESSION['first_name'] = $selected_user['first_name'];
            $_SESSION['last_name'] = $selected_user['last_name'];
            $_SESSION['last_regeneration'] = time();
            
            // Clean up temp session variables
            unset($_SESSION['temp_users']);
            unset($_SESSION['temp_password_verified']);
            
            // Redirect based on role
            if ($selected_user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: cashier.php");
            }
            exit();
        } else {
            $error = 'Invalid restaurant selection.';
        }
    }
}

$restaurants = $_SESSION['temp_users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Restaurant - KiraBOS</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Select Restaurant</h1>
            <p class="text-gray-600">Choose your restaurant to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
            
            <div class="space-y-3">
                <?php foreach ($restaurants as $restaurant): ?>
                    <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition duration-200">
                        <input 
                            type="radio" 
                            name="restaurant_id" 
                            value="<?= $restaurant['restaurant_id'] ?>" 
                            class="text-primary focus:ring-primary h-4 w-4"
                            required
                        >
                        <div class="ml-3 flex-1">
                            <div class="font-medium text-gray-900"><?= htmlspecialchars($restaurant['restaurant_name']) ?></div>
                            <div class="text-sm text-gray-500">Role: <?= ucfirst($restaurant['role']) ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <button 
                type="submit" 
                class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105 mt-6"
            >
                Continue
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-sm text-gray-600 hover:text-primary">
                ← Back to Login
            </a>
        </div>
    </div>
</body>
</html>