<?php
// This file contains the users tab content
?>

<div class="space-y-6">

    <!-- User Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Total Users</h3>
            <p class="text-3xl font-bold text-blue-500"><?= count($users) ?></p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">👥 System Users</div>
        </div>
        
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Administrators</h3>
            <p class="text-3xl font-bold text-purple-500">
                <?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?>
            </p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">👨‍💼 Admin Accounts</div>
        </div>
        
        <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
            <h3 class="text-lg font-semibold mb-2 theme-header">Cashiers</h3>
            <p class="text-3xl font-bold text-green-500">
                <?= count(array_filter($users, fn($u) => $u['role'] === 'user')) ?>
            </p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary)">👨‍💻 Cashier Accounts</div>
        </div>
    </div>

    <!-- User Management -->
    <div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">User Management</h2>
        
        <!-- Add User Form -->
        <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
            <input type="hidden" name="action" value="add_user">
            <h3 class="font-semibold mb-3 theme-header">Add New User</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input type="text" name="username" placeholder="Username" required 
                       class="px-3 py-2 rounded-lg theme-transition" 
                       style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                
                <div class="relative">
                    <input type="password" name="password" placeholder="Password" required id="add-password"
                           class="w-full px-3 py-2 pr-10 rounded-lg theme-transition" 
                           style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <button type="button" onclick="togglePassword('add-password', 'add-eye-icon')" 
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                        <svg id="add-eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
                
                <select name="role" required 
                        class="px-3 py-2 rounded-lg theme-transition" 
                        style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="user">Cashier</option>
                </select>
                
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium text-white transition-colors" 
                        style="background: var(--accent-primary)" 
                        onmouseover="this.style.opacity='0.9'" 
                        onmouseout="this.style.opacity='1'">
                    Add User
                </button>
            </div>
        </form>
        
        <!-- Users Grid -->
        <?php if (empty($users)): ?>
            <div class="text-center py-12">
                <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary)">No Users Found</h4>
                <p class="text-sm" style="color: var(--text-secondary)">Start by adding your first user above.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($users as $user): ?>
                <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="user-<?= $user['id'] ?>">
                    <!-- Display Mode -->
                    <div class="user-display">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium" style="color: var(--text-primary)">
                                <?= htmlspecialchars($user['username']) ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">You</span>
                                <?php endif; ?>
                            </h4>
                            <span class="text-xs px-2 py-1 rounded-full <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= ucfirst($user['role'] === 'user' ? 'Cashier' : $user['role']) ?>
                            </span>
                        </div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary)">ID: <?= $user['id'] ?></p>
                        <p class="text-sm mb-3" style="color: var(--text-secondary)">Created: <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="editUser(<?= $user['id'] ?>)" class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                                Edit
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="text-xs px-3 py-1 rounded transition-colors text-red-500 hover:bg-red-50 border border-red-200">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Edit Mode (Hidden by default) -->
                    <form class="user-edit hidden" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        
                        <div class="space-y-3">
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required 
                                   class="w-full px-3 py-2 text-sm rounded theme-transition" 
                                   style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" 
                                   placeholder="Username">
                            
                            <div class="relative">
                                <input type="password" name="new_password" id="edit-password-<?= $user['id'] ?>"
                                       placeholder="Leave blank to keep current"
                                       class="w-full px-3 py-2 pr-10 text-sm rounded theme-transition" 
                                       style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                <button type="button" onclick="togglePassword('edit-password-<?= $user['id'] ?>', 'edit-eye-icon-<?= $user['id'] ?>')" 
                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                                    <svg id="edit-eye-icon-<?= $user['id'] ?>" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <select name="role" required 
                                    class="w-full px-3 py-2 text-sm rounded theme-transition" 
                                    style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Cashier</option>
                            </select>
                        </div>
                        
                        <div class="flex space-x-2 mt-3">
                            <button type="submit" class="text-sm px-3 py-1 rounded text-white transition-colors" 
                                    style="background: var(--accent-primary)" 
                                    onmouseover="this.style.opacity='0.9'" 
                                    onmouseout="this.style.opacity='1'">
                                Save
                            </button>
                            <button type="button" onclick="cancelUserEdit(<?= $user['id'] ?>)" 
                                    class="text-sm px-3 py-1 rounded transition-colors" 
                                    style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary)">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// User management functions
function editUser(userId) {
    const userRow = document.getElementById('user-' + userId);
    const displayDiv = userRow.querySelector('.user-display');
    const editDiv = userRow.querySelector('.user-edit');
    
    displayDiv.classList.add('hidden');
    editDiv.classList.remove('hidden');
}

function cancelUserEdit(userId) {
    const userRow = document.getElementById('user-' + userId);
    const displayDiv = userRow.querySelector('.user-display');
    const editDiv = userRow.querySelector('.user-edit');
    
    editDiv.classList.add('hidden');
    displayDiv.classList.remove('hidden');
}

// Password visibility toggle function
function togglePassword(passwordFieldId, eyeIconId) {
    const passwordField = document.getElementById(passwordFieldId);
    const eyeIcon = document.getElementById(eyeIconId);
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        // Change to eye-off icon (eye with slash)
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
        `;
    } else {
        passwordField.type = 'password';
        // Change back to eye icon
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
        `;
    }
}
</script>