<?php
require 'config.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['restaurant_id'] = 1;
$restaurant_id = 1;
$db = Database::getInstance()->getConnection();

$count_stmt = $db->query("SELECT COUNT(*) FROM activity_logs WHERE restaurant_id = 1 AND action_type != 'view_menu'");
$total_logs = $count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <h1 class="text-2xl mb-4">Pagination Test</h1>
    <p>Total logs: <?= $total_logs ?></p>
    <p>Condition (<?= $total_logs ?> > 10): <?= $total_logs > 10 ? 'TRUE' : 'FALSE' ?></p>

    <div class="mt-8 border p-4">
        <h2 class="text-xl mb-4">Pagination Area:</h2>

        <?php if ($total_logs > 10): ?>
            <?php
            $current_page = 1;
            $logs_per_page = 10;
            $total_pages = ceil($total_logs / $logs_per_page);
            ?>

            <div class="bg-green-100 p-4">
                <p class="font-bold text-green-800">✓ PAGINATION RENDERS!</p>
                <p>Total pages: <?= $total_pages ?></p>

                <div class="flex items-center justify-between mt-4">
                    <div>
                        <p class="text-sm">
                            Showing 1 to 10 of <?= $total_logs ?> results
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-2 border rounded">Previous</button>
                        <button class="px-3 py-2 border rounded bg-blue-500 text-white">1</button>
                        <button class="px-3 py-2 border rounded">2</button>
                        <button class="px-3 py-2 border rounded">3</button>
                        <button class="px-3 py-2 border rounded">Next</button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-100 p-4">
                <p class="font-bold text-red-800">✗ NOT ENOUGH LOGS</p>
                <p>Need more than 10 logs to show pagination</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
