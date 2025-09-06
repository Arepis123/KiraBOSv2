<?php
// This file contains the menu tab content (Product and Category Management)
?>

<!-- Category Management -->
<div class="theme-transition rounded-xl shadow-sm border p-6 mb-6" style="background: var(--bg-card); border-color: var(--border-primary)">
    <h2 class="text-xl font-bold mb-6 theme-header">Category Management</h2>
    
    <!-- Add Category Form -->
    <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
        <input type="hidden" name="action" value="add_category">
        <h3 class="font-semibold mb-3 theme-header">Add New Category</h3>
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <input type="text" name="category_name" placeholder="Category Name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <input type="text" name="category_description" placeholder="Description (optional)" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <input type="text" name="category_icon" placeholder="Icon (emoji)" maxlength="10" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" value="🍽️">
            <input type="number" name="sort_order" placeholder="Order" min="1" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" value="<?= count($categories) + 1 ?>">
            <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Category</button>
        </div>
    </form>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($categories as $category): ?>
            <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="category-<?= $category['id'] ?>">
                <!-- Display Mode -->
                <div class="category-display">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center space-x-2">
                            <span class="text-xl"><?= htmlspecialchars($category['icon']) ?></span>
                            <h4 class="font-medium <?= $category['is_active'] ? '' : 'line-through opacity-50' ?>" style="color: var(--text-primary)">
                                <?= htmlspecialchars($category['name']) ?>
                            </h4>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full <?= $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <?php if ($category['description']): ?>
                        <p class="text-sm mb-2" style="color: var(--text-secondary)">
                            <?= htmlspecialchars($category['description']) ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-xs mb-3" style="color: var(--text-secondary)">
                        Display Order: <?= $category['sort_order'] ?? 0 ?>
                    </p>
                    <div class="flex space-x-2">
                        <button onclick="editCategory(<?= $category['id'] ?>)" class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                            Edit
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="toggle_category">
                            <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                            <input type="hidden" name="is_active" value="<?= $category['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="text-xs px-3 py-1 rounded transition-colors border <?= $category['is_active'] ? 'text-orange-500 hover:bg-orange-50 border-orange-200' : 'text-green-500 hover:bg-green-50 border-green-200' ?>">
                                <?= $category['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?')">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                            <button type="submit" class="text-xs px-3 py-1 rounded transition-colors text-red-500 hover:bg-red-50 border border-red-200">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Edit Mode (Hidden by default) -->
                <form class="category-edit hidden" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                    
                    <div class="space-y-3">
                        <input type="text" name="category_name" value="<?= htmlspecialchars($category['name']) ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Category Name">
                        <input type="text" name="category_description" value="<?= htmlspecialchars($category['description']) ?>" class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Description">
                        <input type="text" name="category_icon" value="<?= htmlspecialchars($category['icon']) ?>" maxlength="10" class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Icon">
                        <input type="number" name="sort_order" value="<?= $category['sort_order'] ?? 0 ?>" min="1" class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Display Order">
                    </div>
                    
                    <div class="flex space-x-2 mt-3">
                        <button type="submit" class="text-sm px-3 py-1 rounded text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save</button>
                        <button type="button" onclick="cancelCategoryEdit(<?= $category['id'] ?>)" class="text-sm px-3 py-1 rounded transition-colors" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary)">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Product Management -->
<div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
    <h2 class="text-xl font-bold mb-6 theme-header">Product Management</h2>
    
    <!-- Add Product Form -->
    <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
        <input type="hidden" name="action" value="add_product">
        <h3 class="font-semibold mb-3 theme-header">Add New Product</h3>
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <input type="text" name="name" placeholder="Product Name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <input type="number" name="price" placeholder="Price" step="0.01" min="0" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <select name="category" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="is_active" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Product</button>
        </div>
    </form>
    
    <!-- Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($products as $product): ?>
            <div class="p-4 rounded-lg theme-transition border <?= $product['is_active'] ? '' : 'opacity-60' ?>" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="product-<?= $product['id'] ?>">
                <!-- Display Mode -->
                <div class="product-display">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-medium <?= $product['is_active'] ? '' : 'line-through' ?>" style="color: var(--text-primary)">
                            <?= htmlspecialchars($product['name']) ?>
                        </h4>
                        <span class="text-xs px-2 py-1 rounded-full <?= $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <p class="text-lg font-bold text-green-500 mb-1"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($product['price'], 2) ?></p>
                    <p class="text-sm mb-3" style="color: var(--text-secondary)">Category: <?= htmlspecialchars($product['category']) ?></p>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="editProduct(<?= $product['id'] ?>)" class="text-xs px-3 py-1 rounded transition-colors text-blue-500 hover:bg-blue-50 border border-blue-200">
                            Edit
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="toggle_product">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="is_active" value="<?= $product['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="text-xs px-3 py-1 rounded transition-colors border <?= $product['is_active'] ? 'text-orange-500 hover:bg-orange-50 border-orange-200' : 'text-green-500 hover:bg-green-50 border-green-200' ?>">
                                <?= $product['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Edit Mode (Hidden by default) -->
                <form class="product-edit hidden" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <div class="space-y-3">
                        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Product Name">
                        <input type="number" name="price" value="<?= $product['price'] ?>" step="0.01" min="0" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Price">
                        <select name="category" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $product['category'] === $cat['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex space-x-2 mt-3">
                        <button type="submit" class="text-sm px-3 py-1 rounded text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save</button>
                        <button type="button" onclick="cancelEdit(<?= $product['id'] ?>)" class="text-sm px-3 py-1 rounded transition-colors" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary)">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>