<?php
// This file contains the logs tab content
// Determine which submenu is active
$log_view = $_GET['log_view'] ?? 'activity';

// Get activity logs with pagination
// Exclude view_menu logs as they have their own tab
$db = Database::getInstance()->getConnection();
$current_page = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;
$logs_per_page = 10;
$offset = ($current_page - 1) * $logs_per_page;

$logs_query = "SELECT al.*, u.username
               FROM activity_logs al
               LEFT JOIN users u ON al.user_id = u.id
               WHERE al.restaurant_id = :restaurant_id
               AND al.action_type != 'view_menu'
               ORDER BY al.created_at DESC
               LIMIT :limit OFFSET :offset";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->bindParam(':restaurant_id', $restaurant_id);
$logs_stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
$logs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count excluding view_menu
$count_query = "SELECT COUNT(*) FROM activity_logs
                WHERE restaurant_id = :restaurant_id
                AND action_type != 'view_menu'";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':restaurant_id', $restaurant_id);
$count_stmt->execute();
$total_logs = $count_stmt->fetchColumn();

// Get unique users for filter
$users_query = "SELECT id, username FROM users WHERE restaurant_id = :restaurant_id ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->bindParam(':restaurant_id', $restaurant_id);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Action type options
$action_types = ['create', 'update', 'delete', 'login', 'logout', 'enable', 'disable'];
?>

<div class="space-y-6">
    <!-- Submenu -->
    <div class="flex space-x-2 border-b pb-2" style="border-color: var(--border-primary)">
        <a href="admin.php?page=logs&log_view=activity"
           class="px-4 py-2 rounded-lg font-medium text-sm transition-colors <?= $log_view === 'activity' ? 'text-white' : '' ?>"
           style="background: <?= $log_view === 'activity' ? 'var(--accent-primary)' : 'transparent' ?>; color: <?= $log_view === 'activity' ? 'white' : 'var(--text-secondary)' ?>">
            <i data-lucide="activity" class="w-4 h-4 inline-block mr-1"></i>
            Current Activity
        </a>
        <a href="admin.php?page=logs&log_view=orders"
           class="px-4 py-2 rounded-lg font-medium text-sm transition-colors <?= $log_view === 'orders' ? 'text-white' : '' ?>"
           style="background: <?= $log_view === 'orders' ? 'var(--accent-primary)' : 'transparent' ?>; color: <?= $log_view === 'orders' ? 'white' : 'var(--text-secondary)' ?>">
            <i data-lucide="shopping-cart" class="w-4 h-4 inline-block mr-1"></i>
            Order Activity
        </a>
        <a href="admin.php?page=logs&log_view=menu_views"
           class="px-4 py-2 rounded-lg font-medium text-sm transition-colors <?= $log_view === 'menu_views' ? 'text-white' : '' ?>"
           style="background: <?= $log_view === 'menu_views' ? 'var(--accent-primary)' : 'transparent' ?>; color: <?= $log_view === 'menu_views' ? 'white' : 'var(--text-secondary)' ?>">
            <i data-lucide="eye" class="w-4 h-4 inline-block mr-1"></i>
            Menu Views
        </a>
    </div>

    <style>
    /* Ensure responsive display works correctly */
    @media (min-width: 768px) {
        #desktopTableView, #orderTableView, #menuViewTableView {
            display: block !important;
        }
    }
    @media (max-width: 767px) {
        #desktopTableView, #orderTableView, #menuViewTableView {
            display: none !important;
        }
    }
    </style>

    <script>
    // Reinitialize Lucide icons for this page
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    </script>

<?php if ($log_view === 'activity'): ?>
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 mb-8">
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Total Activities</h3>
            <p class="text-3xl font-bold text-blue-500"><?= $total_logs ?></p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">📊 All Activity Logs</div>
        </div>
        
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Active Users</h3>
            <p class="text-3xl font-bold text-green-500">
                <?= count(array_unique(array_filter(array_column($logs, 'user_id')))) ?>
            </p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">👥 Users with Activity</div>
        </div>
        
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Today's Actions</h3>
            <p class="text-3xl font-bold text-purple-500">
                <?= count(array_filter($logs, fn($log) => date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d'))) ?>
            </p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">📅 Today</div>
        </div>
        
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Most Recent</h3>
            <p class="text-2xl font-bold text-orange-500">
                <?= !empty($logs) ? date('H:i', strtotime($logs[0]['created_at'])) : 'N/A' ?>
            </p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">🕐 Last Activity</div>
        </div>
    </div>

    <!-- Activity Logs -->
    <div class="theme-transition rounded-xl shadow-sm border p-4 sm:p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
        <!-- Header and Filters -->
        <div class="flex flex-col space-y-4 mb-6 md:flex-row md:justify-between md:items-center md:space-y-0">
            <h2 class="text-xl font-bold theme-header">Activity Logs</h2>

            <!-- Filters -->
            <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-3">
                <select id="actionFilter" class="px-3 py-2 text-sm rounded-lg theme-transition w-full sm:w-auto" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="userFilter" class="px-3 py-2 text-sm rounded-lg theme-transition w-full sm:w-auto" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button onclick="clearFilters()" class="px-4 py-2 text-sm rounded-lg transition-colors text-white w-full sm:w-auto" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Clear Filters
                </button>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4 opacity-50">📋</div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary)">No Activity Logs Found</h3>
                <p class="text-sm" style="color: var(--text-secondary)">Activity logs will appear here as users interact with the system</p>
            </div>
        <?php else: ?>
            <!-- Mobile: Card View -->
            <div class="space-y-3 md:hidden">
                <?php foreach ($logs as $log): ?>
                    <div class="log-item rounded-lg p-4 transition-colors"
                         style="border: 1px solid var(--border-primary); background: var(--bg-secondary)"
                         data-action="<?= $log['action_type'] ?>"
                         data-user="<?= $log['user_id'] ?>"
                         data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>">

                        <div class="flex items-start justify-between mb-3">
                            <span class="inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium
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

                        <h4 class="font-medium mb-2" style="color: var(--text-primary)">
                            <?= htmlspecialchars($log['description']) ?>
                        </h4>

                        <div class="space-y-1 text-sm" style="color: var(--text-secondary)">
                            <div class="flex items-center">
                                <span class="w-20 font-medium">User:</span>
                                <span><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                            </div>
                            <?php if ($log['table_name']): ?>
                                <div class="flex items-center">
                                    <span class="w-20 font-medium">Table:</span>
                                    <span><?= ucfirst($log['table_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center">
                                <span class="w-20 font-medium">Date:</span>
                                <span><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></span>
                            </div>
                            <?php if ($log['ip_address']): ?>
                                <div class="flex items-center">
                                    <span class="w-20 font-medium">IP:</span>
                                    <span><?= htmlspecialchars($log['ip_address']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($log['old_values'] || $log['new_values']): ?>
                            <button onclick="toggleDetails(<?= $log['id'] ?>)"
                                    class="mt-3 text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 rounded hover:bg-blue-50">
                                View Changes
                            </button>
                            <div id="details-mobile-<?= $log['id'] ?>" class="hidden mt-3 p-3 rounded-lg bg-gray-100">
                                <?php if ($log['old_values']): ?>
                                    <div class="mb-2">
                                        <strong class="text-xs text-red-600">Previous Values:</strong>
                                        <pre class="text-xs mt-1 text-gray-600 overflow-x-auto"><?= htmlspecialchars(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT)) ?></pre>
                                    </div>
                                <?php endif; ?>
                                <?php if ($log['new_values']): ?>
                                    <div>
                                        <strong class="text-xs text-green-600">New Values:</strong>
                                        <pre class="text-xs mt-1 text-gray-600 overflow-x-auto"><?= htmlspecialchars(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT)) ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop: Table View -->
            <div class="block overflow-x-auto" id="desktopTableView">
                <table class="w-full" id="logsContainer">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-primary)">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Table</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">IP Address</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="log-item transition-colors"
                                style="border-bottom: 1px solid var(--border-primary)"
                                data-action="<?= $log['action_type'] ?>"
                                data-user="<?= $log['user_id'] ?>"
                                data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>"
                                onmouseover="this.style.background='var(--bg-secondary)'; this.style.opacity='0.8'"
                                onmouseout="this.style.background='transparent'; this.style.opacity='1'">

                                <!-- Action Badge -->
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center text-xs px-2.5 py-1 rounded-md font-medium
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
                                </td>

                                <!-- Description -->
                                <td class="px-4 py-3 font-medium" style="color: var(--text-primary)">
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>

                                <!-- User -->
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                </td>

                                <!-- Table -->
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= $log['table_name'] ? ucfirst($log['table_name']) : '-' ?>
                                </td>

                                <!-- Date & Time -->
                                <td class="px-4 py-3 text-sm whitespace-nowrap" style="color: var(--text-secondary)">
                                    <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                                </td>

                                <!-- IP Address -->
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= $log['ip_address'] ? htmlspecialchars($log['ip_address']) : '-' ?>
                                </td>

                                <!-- Changes Button -->
                                <td class="px-4 py-3 text-center">
                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                        <button onclick="toggleDetails(<?= $log['id'] ?>)"
                                                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 rounded hover:bg-blue-50">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs" style="color: var(--text-secondary)">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Changes Details Row (Hidden by default) -->
                            <?php if ($log['old_values'] || $log['new_values']): ?>
                                <tr id="details-<?= $log['id'] ?>" class="hidden" style="background: var(--bg-secondary)">
                                    <td colspan="7" class="px-4 py-3">
                                        <div class="p-3 rounded-lg bg-gray-100">
                                            <?php if ($log['old_values']): ?>
                                                <div class="mb-2">
                                                    <strong class="text-xs text-red-600">Previous Values:</strong>
                                                    <pre class="text-xs mt-1 text-gray-600 overflow-x-auto"><?= htmlspecialchars(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT)) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($log['new_values']): ?>
                                                <div>
                                                    <strong class="text-xs text-green-600">New Values:</strong>
                                                    <pre class="text-xs mt-1 text-gray-600 overflow-x-auto"><?= htmlspecialchars(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT)) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_logs > 10): ?>
            <?php
            $current_page = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;
            $logs_per_page = 10;
            $total_pages = ceil($total_logs / $logs_per_page);
            ?>
            <div class="flex flex-col md:flex-row items-center justify-between px-2 sm:px-4 py-3 sm:px-6 mt-4 gap-4" style="border-top: 1px solid var(--border-primary)">
                <!-- Mobile: Previous/Next -->
                <div class="flex flex-1 justify-between md:hidden w-full">
                    <?php if ($current_page > 1): ?>
                        <a href="admin.php?page=logs&log_view=activity&log_page=<?= $current_page - 1 ?>"
                           class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                           onmouseover="this.style.background='var(--bg-secondary)'"
                           onmouseout="this.style.background='var(--bg-card)'">
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                            Previous
                        </span>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="admin.php?page=logs&log_view=activity&log_page=<?= $current_page + 1 ?>"
                           class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                           onmouseover="this.style.background='var(--bg-secondary)'"
                           onmouseout="this.style.background='var(--bg-card)'">
                            Next
                        </a>
                    <?php else: ?>
                        <span class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                            Next
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Desktop: Full Pagination -->
                <div class="flex md:flex-1 md:items-center md:justify-between w-full">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary)">
                            Showing
                            <span class="font-medium" style="color: var(--text-primary)"><?= (($current_page - 1) * $logs_per_page) + 1 ?></span>
                            to
                            <span class="font-medium" style="color: var(--text-primary)"><?= min($current_page * $logs_per_page, $total_logs) ?></span>
                            of
                            <span class="font-medium" style="color: var(--text-primary)"><?= $total_logs ?></span>
                            results
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <!-- Previous Button -->
                            <?php if ($current_page > 1): ?>
                                <a href="admin.php?page=logs&log_view=activity&log_page=<?= $current_page - 1 ?>"
                                   class="relative inline-flex items-center rounded-l-md px-2 py-2 transition-colors"
                                   style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center rounded-l-md px-2 py-2"
                                      style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            // Show first page if not in range
                            if ($start_page > 1): ?>
                                <a href="admin.php?page=logs&log_view=activity&log_page=1"
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                   style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
                                          style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                        ...
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i === $current_page): ?>
                                    <span aria-current="page"
                                          class="relative z-10 inline-flex items-center px-4 py-2 text-sm font-semibold text-white"
                                          style="background: var(--accent-primary); border: 1px solid var(--accent-primary)">
                                        <?= $i ?>
                                    </span>
                                <?php else: ?>
                                    <a href="admin.php?page=logs&log_view=activity&log_page=<?= $i ?>"
                                       class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                       style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                       onmouseover="this.style.background='var(--bg-secondary)'"
                                       onmouseout="this.style.background='var(--bg-card)'">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Show last page if not in range -->
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
                                          style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="admin.php?page=logs&log_view=activity&log_page=<?= $total_pages ?>"
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                   style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <?= $total_pages ?>
                                </a>
                            <?php endif; ?>

                            <!-- Next Button -->
                            <?php if ($current_page < $total_pages): ?>
                                <a href="admin.php?page=logs&log_view=activity&log_page=<?= $current_page + 1 ?>"
                                   class="relative inline-flex items-center rounded-r-md px-2 py-2 transition-colors"
                                   style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center rounded-r-md px-2 py-2"
                                      style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php elseif ($log_view === 'orders'): ?>
    <!-- Order Activity View -->
    <?php
    // Get filter parameters
    $filter_user = isset($_GET['filter_user']) ? (int)$_GET['filter_user'] : null;
    $filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : null;
    $filter_payment = isset($_GET['filter_payment']) ? $_GET['filter_payment'] : null;

    // Build query with filters
    $order_conditions = ["o.restaurant_id = :restaurant_id"];
    $order_params = [':restaurant_id' => $restaurant_id];

    if ($filter_user) {
        $order_conditions[] = "o.user_id = :user_id";
        $order_params[':user_id'] = $filter_user;
    }

    if ($filter_date) {
        $order_conditions[] = "DATE(o.created_at) = :filter_date";
        $order_params[':filter_date'] = $filter_date;
    }

    if ($filter_payment) {
        $order_conditions[] = "o.payment_method = :payment_method";
        $order_params[':payment_method'] = $filter_payment;
    }

    $order_where = implode(' AND ', $order_conditions);

    // Pagination for orders
    $order_current_page = isset($_GET['order_page']) ? max(1, (int)$_GET['order_page']) : 1;
    $orders_per_page = 10;
    $order_offset = ($order_current_page - 1) * $orders_per_page;

    // Get total count for pagination
    $order_count_query = "SELECT COUNT(*) FROM orders o WHERE {$order_where}";
    $order_count_stmt = $db->prepare($order_count_query);
    foreach ($order_params as $key => $value) {
        $order_count_stmt->bindValue($key, $value);
    }
    $order_count_stmt->execute();
    $total_orders = $order_count_stmt->fetchColumn();

    // Get order activity logs with filters and pagination
    $order_logs_query = "SELECT o.*, u.username,
                         (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ', ')
                          FROM order_items oi WHERE oi.order_id = o.id) as items
                         FROM orders o
                         LEFT JOIN users u ON o.user_id = u.id
                         WHERE {$order_where}
                         ORDER BY o.created_at DESC
                         LIMIT :limit OFFSET :offset";
    $order_logs_stmt = $db->prepare($order_logs_query);
    foreach ($order_params as $key => $value) {
        $order_logs_stmt->bindValue($key, $value);
    }
    $order_logs_stmt->bindValue(':limit', $orders_per_page, PDO::PARAM_INT);
    $order_logs_stmt->bindValue(':offset', $order_offset, PDO::PARAM_INT);
    $order_logs_stmt->execute();
    $order_logs = $order_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique users for filter (already defined earlier in the file)
    // Get payment methods for filter
    $payment_methods = ['cash', 'qr_code', 'card'];
    ?>

    <div class="theme-transition rounded-xl shadow-sm border p-4 sm:p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
        <!-- Header and Filters -->
        <div class="flex flex-col space-y-4 mb-6 md:flex-row md:justify-between md:items-center md:space-y-0">
            <h2 class="text-xl font-bold theme-header">Order Activity Log</h2>

            <!-- Filters -->
            <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-3">
                <select id="orderUserFilter" class="px-3 py-2 text-sm rounded-lg theme-transition w-full sm:w-auto" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)"
                        onchange="applyOrderLogsFilters()">
                    <option value="">All Cashiers</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" id="orderDateFilter" class="px-3 py-2 text-sm rounded-lg theme-transition w-full sm:w-auto"
                       style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)"
                       value="<?= htmlspecialchars($filter_date ?? '') ?>"
                       onchange="applyOrderLogsFilters()">

                <select id="orderPaymentFilter" class="px-3 py-2 text-sm rounded-lg theme-transition w-full sm:w-auto" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)"
                        onchange="applyOrderLogsFilters()">
                    <option value="">All Payment Types</option>
                    <?php foreach ($payment_methods as $method): ?>
                        <option value="<?= $method ?>" <?= $filter_payment == $method ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $method)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button onclick="clearOrderLogsFilters()" class="px-4 py-2 text-sm rounded-lg transition-colors text-white w-full sm:w-auto" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Clear Filters
                </button>
            </div>
        </div>

        <?php if (empty($order_logs)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4 opacity-50">🛒</div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary)">No Orders Found</h3>
                <p class="text-sm" style="color: var(--text-secondary)">Order history will appear here</p>
            </div>
        <?php else: ?>
            <!-- Mobile: Card View -->
            <div class="space-y-3 md:hidden">
                <?php foreach ($order_logs as $order): ?>
                    <div class="rounded-lg p-4 transition-colors"
                         style="border: 1px solid var(--border-primary); background: var(--bg-secondary)">

                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white bg-green-500">
                                    🛒
                                </div>
                                <h4 class="font-medium" style="color: var(--text-primary)">
                                    Order #<?= htmlspecialchars($order['order_number']) ?>
                                </h4>
                            </div>
                            <span class="text-sm font-bold" style="color: var(--accent-primary)">
                                MYR <?= number_format($order['total_amount'], 2) ?>
                            </span>
                        </div>

                        <div class="text-sm mb-3" style="color: var(--text-secondary)">
                            <?= htmlspecialchars($order['items']) ?>
                        </div>

                        <div class="space-y-1 text-sm" style="color: var(--text-secondary)">
                            <div class="flex items-center">
                                <span class="w-24 font-medium">Cashier:</span>
                                <span><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-24 font-medium">Date:</span>
                                <span><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-24 font-medium">Payment:</span>
                                <span><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-24 font-medium">Status:</span>
                                <span class="px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop: Table View -->
            <div class="hidden md:block overflow-x-auto" id="orderTableView">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-primary)">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Items</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Cashier</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Payment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_logs as $order): ?>
                            <tr class="transition-colors"
                                style="border-bottom: 1px solid var(--border-primary)"
                                onmouseover="this.style.background='var(--bg-secondary)'; this.style.opacity='0.8'"
                                onmouseout="this.style.background='transparent'; this.style.opacity='1'">

                                <td class="px-4 py-3 font-medium" style="color: var(--text-primary)">
                                    #<?= htmlspecialchars($order['order_number']) ?>
                                </td>

                                <td class="px-4 py-3 text-sm max-w-xs truncate" style="color: var(--text-secondary)">
                                    <?= htmlspecialchars($order['items']) ?>
                                </td>

                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= htmlspecialchars($order['username'] ?? 'Unknown') ?>
                                </td>

                                <td class="px-4 py-3 text-sm whitespace-nowrap" style="color: var(--text-secondary)">
                                    <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                </td>

                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right font-bold" style="color: var(--accent-primary)">
                                    MYR <?= number_format($order['total_amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination for Orders -->
            <?php if ($total_orders > 10): ?>
            <?php
            $order_total_pages = ceil($total_orders / $orders_per_page);
            $base_url = "admin.php?page=logs&log_view=orders";
            if ($filter_user) $base_url .= "&filter_user={$filter_user}";
            if ($filter_date) $base_url .= "&filter_date={$filter_date}";
            if ($filter_payment) $base_url .= "&filter_payment={$filter_payment}";
            ?>
            <div class="flex flex-col md:flex-row items-center justify-between px-2 sm:px-4 py-3 sm:px-6 mt-4 gap-4" style="border-top: 1px solid var(--border-primary)">
                <!-- Mobile: Previous/Next -->
                <div class="flex flex-1 justify-between md:hidden w-full">
                    <?php if ($order_current_page > 1): ?>
                        <a href="<?= $base_url ?>&order_page=<?= $order_current_page - 1 ?>"
                           class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                           onmouseover="this.style.background='var(--bg-secondary)'"
                           onmouseout="this.style.background='var(--bg-card)'">
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                            Previous
                        </span>
                    <?php endif; ?>

                    <?php if ($order_current_page < $order_total_pages): ?>
                        <a href="<?= $base_url ?>&order_page=<?= $order_current_page + 1 ?>"
                           class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                           onmouseover="this.style.background='var(--bg-secondary)'"
                           onmouseout="this.style.background='var(--bg-card)'">
                            Next
                        </a>
                    <?php else: ?>
                        <span class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                            Next
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Desktop: Full Pagination -->
                <div class="flex md:flex-1 md:items-center md:justify-between w-full">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary)">
                            Showing
                            <span class="font-medium" style="color: var(--text-primary)"><?= (($order_current_page - 1) * $orders_per_page) + 1 ?></span>
                            to
                            <span class="font-medium" style="color: var(--text-primary)"><?= min($order_current_page * $orders_per_page, $total_orders) ?></span>
                            of
                            <span class="font-medium" style="color: var(--text-primary)"><?= $total_orders ?></span>
                            results
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <!-- Previous Button -->
                            <?php if ($order_current_page > 1): ?>
                                <a href="<?= $base_url ?>&order_page=<?= $order_current_page - 1 ?>"
                                   class="relative inline-flex items-center rounded-l-md px-2 py-2 transition-colors"
                                   style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center rounded-l-md px-2 py-2"
                                      style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $order_current_page - 2);
                            $end_page = min($order_total_pages, $order_current_page + 2);

                            if ($start_page > 1): ?>
                                <a href="<?= $base_url ?>&order_page=1"
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                   style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
                                          style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                        ...
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i === $order_current_page): ?>
                                    <span aria-current="page"
                                          class="relative z-10 inline-flex items-center px-4 py-2 text-sm font-semibold text-white"
                                          style="background: var(--accent-primary); border: 1px solid var(--accent-primary)">
                                        <?= $i ?>
                                    </span>
                                <?php else: ?>
                                    <a href="<?= $base_url ?>&order_page=<?= $i ?>"
                                       class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                       style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                       onmouseover="this.style.background='var(--bg-secondary)'"
                                       onmouseout="this.style.background='var(--bg-card)'">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($end_page < $order_total_pages): ?>
                                <?php if ($end_page < $order_total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
                                          style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="<?= $base_url ?>&order_page=<?= $order_total_pages ?>"
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors"
                                   style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <?= $order_total_pages ?>
                                </a>
                            <?php endif; ?>

                            <!-- Next Button -->
                            <?php if ($order_current_page < $order_total_pages): ?>
                                <a href="<?= $base_url ?>&order_page=<?= $order_current_page + 1 ?>"
                                   class="relative inline-flex items-center rounded-r-md px-2 py-2 transition-colors"
                                   style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)"
                                   onmouseover="this.style.background='var(--bg-secondary)'"
                                   onmouseout="this.style.background='var(--bg-card)'">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center rounded-r-md px-2 py-2"
                                      style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php elseif ($log_view === 'menu_views'): ?>
    <!-- Menu Views Activity -->
    <?php
    // Get filter parameters
    $filter_menu_user = isset($_GET['filter_menu_user']) ? (int)$_GET['filter_menu_user'] : null;
    $filter_menu_date = isset($_GET['filter_menu_date']) ? $_GET['filter_menu_date'] : null;

    // Build query with filters
    $menu_conditions = ["al.restaurant_id = :restaurant_id", "al.action_type = 'view_menu'"];
    $menu_params = [':restaurant_id' => $restaurant_id];

    if ($filter_menu_user) {
        $menu_conditions[] = "al.user_id = :user_id";
        $menu_params[':user_id'] = $filter_menu_user;
    }

    if ($filter_menu_date) {
        $menu_conditions[] = "DATE(al.created_at) = :filter_date";
        $menu_params[':filter_date'] = $filter_menu_date;
    }

    $menu_where = implode(' AND ', $menu_conditions);

    // Pagination for menu views
    $menu_current_page = isset($_GET['menu_page']) ? max(1, (int)$_GET['menu_page']) : 1;
    $menu_per_page = 10;
    $menu_offset = ($menu_current_page - 1) * $menu_per_page;

    // Get total count with filters
    $menu_count_query = "SELECT COUNT(*) FROM activity_logs al WHERE {$menu_where}";
    $menu_count_stmt = $db->prepare($menu_count_query);
    foreach ($menu_params as $key => $value) {
        $menu_count_stmt->bindValue($key, $value);
    }
    $menu_count_stmt->execute();
    $total_menu_views = $menu_count_stmt->fetchColumn();

    // Get menu view logs with filters and pagination
    $menu_logs_query = "SELECT al.*, u.username
                        FROM activity_logs al
                        LEFT JOIN users u ON al.user_id = u.id
                        WHERE {$menu_where}
                        ORDER BY al.created_at DESC
                        LIMIT :limit OFFSET :offset";
    $menu_logs_stmt = $db->prepare($menu_logs_query);
    foreach ($menu_params as $key => $value) {
        $menu_logs_stmt->bindValue($key, $value);
    }
    $menu_logs_stmt->bindValue(':limit', $menu_per_page, PDO::PARAM_INT);
    $menu_logs_stmt->bindValue(':offset', $menu_offset, PDO::PARAM_INT);
    $menu_logs_stmt->execute();
    $menu_view_logs = $menu_logs_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="theme-transition rounded-xl shadow-sm border p-4 sm:p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <h2 class="text-xl font-bold theme-header">Menu View Activity</h2>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3">
                <select id="menuUserFilter"
                        class="px-3 py-2 rounded-lg border text-sm w-full sm:w-auto"
                        style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-primary)"
                        onchange="applyMenuViewFilters()">
                    <option value="">All Users</option>
                    <?php foreach ($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_menu_user == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date"
                       id="menuDateFilter"
                       value="<?= htmlspecialchars($filter_menu_date ?? '') ?>"
                       class="px-3 py-2 rounded-lg border text-sm w-full sm:w-auto"
                       style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-primary)"
                       onchange="applyMenuViewFilters()">

                <?php if ($filter_menu_user || $filter_menu_date): ?>
                    <button onclick="clearMenuViewFilters()"
                            class="px-4 py-2 text-sm rounded-lg transition-colors text-white w-full sm:w-auto"
                            style="background: var(--accent-primary)">
                        Clear Filters
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($menu_view_logs)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4 opacity-50">👁️</div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary)">No Menu Views Logged</h3>
                <p class="text-sm" style="color: var(--text-secondary)">Menu view tracking will appear here when cashiers browse products</p>
            </div>
        <?php else: ?>
            <!-- Mobile: Card View -->
            <div class="space-y-3 md:hidden">
                <?php foreach ($menu_view_logs as $log): ?>
                    <div class="rounded-lg p-4 transition-colors"
                         style="border: 1px solid var(--border-primary); background: var(--bg-secondary)">

                        <div class="flex items-center space-x-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white bg-blue-500">
                                👁️
                            </div>
                            <h4 class="font-medium flex-grow" style="color: var(--text-primary)">
                                Menu View
                            </h4>
                        </div>

                        <div class="text-sm mb-3" style="color: var(--text-primary)">
                            <?= htmlspecialchars($log['description']) ?>
                        </div>

                        <div class="space-y-1 text-sm" style="color: var(--text-secondary)">
                            <div class="flex items-center">
                                <span class="w-20 font-medium">User:</span>
                                <span><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-20 font-medium">Date:</span>
                                <span><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></span>
                            </div>
                            <?php if ($log['ip_address']): ?>
                                <div class="flex items-center">
                                    <span class="w-20 font-medium">IP:</span>
                                    <span><?= htmlspecialchars($log['ip_address']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop: Table View -->
            <div class="hidden md:block overflow-x-auto" id="menuViewTableView">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-primary)">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_view_logs as $log): ?>
                            <tr class="transition-colors"
                                style="border-bottom: 1px solid var(--border-primary)"
                                onmouseover="this.style.background='var(--bg-secondary)'; this.style.opacity='0.8'"
                                onmouseout="this.style.background='transparent'; this.style.opacity='1'">

                                <td class="px-4 py-3 font-medium" style="color: var(--text-primary)">
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>

                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                </td>

                                <td class="px-4 py-3 text-sm whitespace-nowrap" style="color: var(--text-secondary)">
                                    <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                                </td>

                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary)">
                                    <?= $log['ip_address'] ? htmlspecialchars($log['ip_address']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination for Menu Views -->
            <?php if ($total_menu_views > 10): ?>
            <?php $menu_total_pages = ceil($total_menu_views / $menu_per_page); ?>
            <div class="flex flex-col md:flex-row items-center justify-between px-2 sm:px-4 py-3 sm:px-6 mt-4 gap-4" style="border-top: 1px solid var(--border-primary)">
                <!-- Mobile -->
                <div class="flex flex-1 justify-between md:hidden w-full">
                    <?php
                    $menu_filter_params = '';
                    if ($filter_menu_user) $menu_filter_params .= '&filter_menu_user=' . $filter_menu_user;
                    if ($filter_menu_date) $menu_filter_params .= '&filter_menu_date=' . $filter_menu_date;
                    ?>
                    <?php if ($menu_current_page > 1): ?>
                        <a href="admin.php?page=logs&log_view=menu_views&menu_page=<?= $menu_current_page - 1 ?><?= $menu_filter_params ?>"
                           class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)">Previous</a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">Previous</span>
                    <?php endif; ?>
                    <?php if ($menu_current_page < $menu_total_pages): ?>
                        <a href="admin.php?page=logs&log_view=menu_views&menu_page=<?= $menu_current_page + 1 ?><?= $menu_filter_params ?>"
                           class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
                           style="border: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)">Next</a>
                    <?php else: ?>
                        <span class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium"
                              style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card); opacity: 0.5; cursor: not-allowed;">Next</span>
                    <?php endif; ?>
                </div>

                <!-- Desktop -->
                <div class="flex md:flex-1 md:items-center md:justify-between w-full">
                    <p class="text-sm" style="color: var(--text-secondary)">
                        Showing <span class="font-medium" style="color: var(--text-primary)"><?= (($menu_current_page - 1) * $menu_per_page) + 1 ?></span>
                        to <span class="font-medium" style="color: var(--text-primary)"><?= min($menu_current_page * $menu_per_page, $total_menu_views) ?></span>
                        of <span class="font-medium" style="color: var(--text-primary)"><?= $total_menu_views ?></span> results
                    </p>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                        <?php if ($menu_current_page > 1): ?>
                            <a href="admin.php?page=logs&log_view=menu_views&menu_page=<?= $menu_current_page - 1 ?><?= $menu_filter_params ?>"
                               class="relative inline-flex items-center rounded-l-md px-2 py-2"
                               style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
                            </a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $menu_current_page - 2);
                        $end = min($menu_total_pages, $menu_current_page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i === $menu_current_page): ?>
                                <span class="relative z-10 inline-flex items-center px-4 py-2 text-sm font-semibold text-white"
                                      style="background: var(--accent-primary); border: 1px solid var(--accent-primary)"><?= $i ?></span>
                            <?php else: ?>
                                <a href="admin.php?page=logs&log_view=menu_views&menu_page=<?= $i ?><?= $menu_filter_params ?>"
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
                                   style="border-top: 1px solid var(--border-primary); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); background: var(--bg-card)"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($menu_current_page < $menu_total_pages): ?>
                            <a href="admin.php?page=logs&log_view=menu_views&menu_page=<?= $menu_current_page + 1 ?><?= $menu_filter_params ?>"
                               class="relative inline-flex items-center rounded-r-md px-2 py-2"
                               style="border: 1px solid var(--border-primary); color: var(--text-secondary); background: var(--bg-card)">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<script>
// Filter functionality for Current Activity
function filterLogs() {
    const actionFilter = document.getElementById('actionFilter').value;
    const userFilter = document.getElementById('userFilter').value;
    const logItems = document.querySelectorAll('.log-item');

    logItems.forEach(item => {
        const action = item.getAttribute('data-action');
        const user = item.getAttribute('data-user');

        let showItem = true;

        if (actionFilter && action !== actionFilter) {
            showItem = false;
        }

        if (userFilter && user !== userFilter) {
            showItem = false;
        }

        // Check if it's a table row (desktop) or card (mobile)
        const isTableRow = item.tagName === 'TR';

        if (isTableRow) {
            item.style.display = showItem ? 'table-row' : 'none';

            // Also hide/show the associated details row if it exists
            const detailsRow = item.nextElementSibling;
            if (detailsRow && detailsRow.id && detailsRow.id.startsWith('details-')) {
                detailsRow.style.display = showItem ? (detailsRow.classList.contains('hidden') ? 'none' : 'table-row') : 'none';
            }
        } else {
            // Mobile card view
            item.style.display = showItem ? 'block' : 'none';
        }
    });
}

function clearFilters() {
    document.getElementById('actionFilter').value = '';
    document.getElementById('userFilter').value = '';
    filterLogs();
}

// Order Activity Logs Filters (renamed to avoid conflict with dashboard)
function applyOrderLogsFilters() {
    const userFilterEl = document.getElementById('orderUserFilter');
    const dateFilterEl = document.getElementById('orderDateFilter');
    const paymentFilterEl = document.getElementById('orderPaymentFilter');

    // Safety check - ensure elements exist
    if (!userFilterEl || !dateFilterEl || !paymentFilterEl) {
        console.error('Filter elements not found');
        return;
    }

    const userFilter = userFilterEl.value;
    const dateFilter = dateFilterEl.value;
    const paymentFilter = paymentFilterEl.value;

    // Build URL with filters
    let url = 'admin.php?page=logs&log_view=orders';

    if (userFilter) {
        url += '&filter_user=' + userFilter;
    }

    if (dateFilter) {
        url += '&filter_date=' + dateFilter;
    }

    if (paymentFilter) {
        url += '&filter_payment=' + paymentFilter;
    }

    window.location.href = url;
}

function clearOrderLogsFilters() {
    window.location.href = 'admin.php?page=logs&log_view=orders';
}

function applyMenuViewFilters() {
    const userFilter = document.getElementById('menuUserFilter').value;
    const dateFilter = document.getElementById('menuDateFilter').value;

    // Build URL with filters
    let url = 'admin.php?page=logs&log_view=menu_views';

    if (userFilter) {
        url += '&filter_menu_user=' + userFilter;
    }

    if (dateFilter) {
        url += '&filter_menu_date=' + dateFilter;
    }

    window.location.href = url;
}

function clearMenuViewFilters() {
    window.location.href = 'admin.php?page=logs&log_view=menu_views';
}

function toggleDetails(logId) {
    // Try desktop version first
    const details = document.getElementById('details-' + logId);
    if (details) {
        details.classList.toggle('hidden');
    }

    // Try mobile version
    const detailsMobile = document.getElementById('details-mobile-' + logId);
    if (detailsMobile) {
        detailsMobile.classList.toggle('hidden');
    }
}

// Add event listeners only if elements exist (Current Activity tab)
const actionFilter = document.getElementById('actionFilter');
const userFilter = document.getElementById('userFilter');

if (actionFilter) {
    actionFilter.addEventListener('change', filterLogs);
}

if (userFilter) {
    userFilter.addEventListener('change', filterLogs);
}
</script>