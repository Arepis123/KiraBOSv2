<?php
// This file contains the logs tab content
// Get initial batch of activity logs for the current restaurant (only first 10)
$logs = ActivityLogger::getRecentLogs($restaurant_id, 10, 0);
$total_logs = ActivityLogger::getLogsCount($restaurant_id);

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
    <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold theme-header">Activity Logs</h2>
            
            <!-- Filters -->
            <div class="flex space-x-3">
                <select id="actionFilter" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="userFilter" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button onclick="clearFilters()" class="px-4 py-2 text-sm rounded-lg transition-colors text-white" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
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
            <div class="space-y-3" id="logsContainer">
                <?php foreach ($logs as $log): ?>
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
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Load More Button -->
        <?php if ($total_logs > 10): ?>
            <div class="text-center mt-6">
                <button id="loadMoreBtn" onclick="loadMoreLogs()" 
                        class="px-6 py-3 rounded-lg transition-colors text-white font-medium" 
                        style="background: var(--accent-primary)" 
                        onmouseover="this.style.opacity='0.9'" 
                        onmouseout="this.style.opacity='1'">
                    Load More Logs (<span id="remainingCount"><?= $total_logs - 10 ?></span> remaining)
                </button>
                <div id="loadingSpinner" class="hidden mt-4">
                    <div class="flex justify-center items-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                        <span class="ml-3 text-sm" style="color: var(--text-secondary)">Loading more logs...</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Filter functionality
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
        
        item.style.display = showItem ? 'flex' : 'none';
    });
}

function clearFilters() {
    document.getElementById('actionFilter').value = '';
    document.getElementById('userFilter').value = '';
    filterLogs();
}

function toggleDetails(logId) {
    const details = document.getElementById('details-' + logId);
    details.classList.toggle('hidden');
}

// Add event listeners
document.getElementById('actionFilter').addEventListener('change', filterLogs);
document.getElementById('userFilter').addEventListener('change', filterLogs);

// Global variables for pagination
let currentOffset = 10;
const logsPerPage = 10;

// Load more logs function
function loadMoreLogs() {
    const loadBtn = document.getElementById('loadMoreBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const remainingCount = document.getElementById('remainingCount');
    
    // Show loading state
    loadBtn.style.display = 'none';
    loadingSpinner.classList.remove('hidden');
    
    // Make AJAX request
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=load_more_logs&offset=${currentOffset}&limit=${logsPerPage}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Append new logs to the container
            const logsContainer = document.getElementById('logsContainer');
            logsContainer.insertAdjacentHTML('beforeend', data.html);
            
            // Update offset
            currentOffset += logsPerPage;
            
            // Update remaining count
            const remaining = data.total_logs - currentOffset;
            if (remaining > 0) {
                remainingCount.textContent = remaining;
                loadBtn.style.display = 'block';
            } else {
                // No more logs to load
                loadBtn.style.display = 'none';
            }
        } else {
            console.error('Failed to load more logs:', data.error);
            alert('Failed to load more logs. Please try again.');
            loadBtn.style.display = 'block';
        }
        
        // Hide loading spinner
        loadingSpinner.classList.add('hidden');
    })
    .catch(error => {
        console.error('Error loading more logs:', error);
        alert('Error loading more logs. Please try again.');
        loadBtn.style.display = 'block';
        loadingSpinner.classList.add('hidden');
    });
}
</script>