<?php
// This file contains the menu tab content (Product and Category Management)
?>

<!-- Menu Management Accordion -->
<div class="space-y-4">
    
    <!-- Category Management Section -->
    <div class="theme-transition rounded-xl shadow-sm border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <!-- Accordion Header -->
        <div class="accordion-header p-4 cursor-pointer flex items-center justify-between" 
             onclick="toggleAccordion('category-management')">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--accent-primary-10, rgba(0, 123, 255, 0.1));">
                    <span class="text-lg">🏷️</span>
                </div>
                <div>
                    <h2 class="text-xl font-bold theme-header">Category Management</h2>
                    <p class="text-sm" style="color: var(--text-secondary)">Manage product categories, colors, and organization</p>
                </div>
            </div>
            <svg id="category-management-icon" class="w-6 h-6 transform transition-transform duration-200" 
                 style="color: var(--text-secondary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        
        <!-- Accordion Content -->
        <div id="category-management" class="accordion-content p-6">
            <h3 class="sr-only">Category Management Content</h3>
    
    <!-- Add Category Form -->
    <form method="POST" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
        <input type="hidden" name="action" value="add_category">
        <h3 class="font-semibold mb-3 theme-header">Add New Category</h3>
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            <input type="text" name="category_name" placeholder="Category Name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <input type="text" name="category_description" placeholder="Description (optional)" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            <input type="text" name="category_icon" placeholder="Icon (emoji)" maxlength="10" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" value="🍽️">
            <div class="flex items-center space-x-2">
                <input type="color" name="category_color" value="#FF6B6B" class="w-12 h-10 rounded-lg border theme-transition cursor-pointer" style="border-color: var(--border-primary)" title="Choose category color">
                <span class="text-xs" style="color: var(--text-secondary)">Color</span>
            </div>
            <input type="number" name="sort_order" placeholder="Order" min="1" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" value="<?= count($categories) + 1 ?>">
            <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Category</button>
        </div>
    </form>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($categories as $category): ?>
            <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="category-<?= $category['id'] ?>" data-category-id="<?= $category['id'] ?>">
                <!-- Display Mode -->
                <div class="category-display">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center space-x-2">
                            <span class="text-xl"><?= htmlspecialchars($category['icon']) ?></span>
                            <div class="w-4 h-4 rounded-full border" style="background: <?= htmlspecialchars($category['color'] ?? '#FF6B6B') ?>; border-color: var(--border-primary)" title="Category Color"></div>
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
                        <div class="flex items-center space-x-3">
                            <input type="color" name="category_color" value="<?= htmlspecialchars($category['color'] ?? '#FF6B6B') ?>" class="w-12 h-10 rounded border theme-transition cursor-pointer" style="border-color: var(--border-primary)" title="Choose category color">
                            <span class="text-sm" style="color: var(--text-secondary)">Category Color</span>
                        </div>
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
    </div>
    
    <!-- Product Management Section -->
    <div class="theme-transition rounded-xl shadow-sm border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <!-- Accordion Header -->
        <div class="accordion-header p-4 cursor-pointer flex items-center justify-between" 
             onclick="toggleAccordion('product-management')">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--accent-primary-10, rgba(0, 123, 255, 0.1));">
                    <span class="text-lg">🍽️</span>
                </div>
                <div>
                    <h2 class="text-xl font-bold theme-header">Product Management</h2>
                    <p class="text-sm" style="color: var(--text-secondary)">Add, edit, and manage menu items with images</p>
                </div>
            </div>
            <svg id="product-management-icon" class="w-6 h-6 transform transition-transform duration-200" 
                 style="color: var(--text-secondary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        
        <!-- Accordion Content -->
        <div id="product-management" class="accordion-content p-6">
            <h3 class="sr-only">Product Management Content</h3>
    
    <!-- Add Product Form -->
    <form method="POST" enctype="multipart/form-data" class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
        <input type="hidden" name="action" value="add_product">
        <h3 class="font-semibold mb-3 theme-header">Add New Product</h3>
        
        <!-- Row 1: Basic Product Info -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div class="flex flex-col">
                <label class="text-sm font-medium mb-1" style="color: var(--text-secondary)">Product Name *</label>
                <input type="text" name="name" placeholder="Enter product name" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-medium mb-1" style="color: var(--text-secondary)">Price (RM) *</label>
                <input type="number" name="price" placeholder="0.00" step="0.01" min="0" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-medium mb-1" style="color: var(--text-secondary)">Category *</label>
                <select name="category" required class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Row 2: Description and Image -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="flex flex-col">
                <label class="text-sm font-medium mb-1" style="color: var(--text-secondary)">Description</label>
                <textarea name="description" placeholder="Optional product description..." rows="3" class="px-3 py-2 rounded-lg theme-transition resize-none" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)"></textarea>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-medium mb-1" style="color: var(--text-secondary)">Product Image</label>
                <div class="relative">
                    <input type="file" name="product_image" accept="image/*" id="product-image-upload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="flex items-center justify-between p-5 rounded-lg border-2 border-dashed theme-transition hover:border-opacity-60" style="border-color: var(--border-primary); background: var(--bg-card);">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5" style="color: var(--text-secondary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <span class="text-sm" style="color: var(--text-secondary)" id="file-name">Choose image or drag & drop</span>
                        </div>
                        <button type="button" class="px-3 py-1 text-xs rounded-md theme-transition" style="background: var(--accent-primary); color: white; opacity: 0.8" onclick="document.getElementById('product-image-upload').click()">Browse</button>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <small class="text-xs" style="color: var(--text-secondary)">JPG, PNG, GIF up to 2MB</small>
                    <small class="text-xs" style="color: var(--text-secondary)">Optional - uses category icon if not provided</small>
                </div>
            </div>
        </div>
        
        <!-- Row 3: Stock Management -->
        <div class="mb-4 p-4 rounded-lg" style="background: var(--bg-primary); border: 1px solid var(--border-primary);">
            <div class="flex items-center mb-3">
                <input type="checkbox" name="track_stock" id="track-stock-new" value="1" class="mr-2" onchange="toggleStockFields('new')">
                <label for="track-stock-new" class="text-sm font-medium" style="color: var(--text-primary)">
                    📦 Track Stock for this Product
                </label>
            </div>
            <p class="text-xs mb-3" style="color: var(--text-secondary)">Enable for bottled drinks, packaged items, etc. Disable for made-to-order dishes.</p>
            
            <div id="stock-fields-new" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" style="display: none;">
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Current Stock</label>
                    <input type="number" name="stock_quantity" min="0" step="0.1" value="0" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="0">
                </div>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Minimum Level</label>
                    <input type="number" name="min_stock_level" min="0" step="0.1" value="5" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="5">
                </div>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Maximum Level</label>
                    <input type="number" name="max_stock_level" min="0" step="0.1" value="100" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="100">
                </div>
                <div class="flex flex-col">
                    <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Unit</label>
                    <select name="stock_unit" class="px-3 py-2 text-sm rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                        <option value="pieces">Pieces</option>
                        <option value="bottles">Bottles</option>
                        <option value="cans">Cans</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="g">Grams (g)</option>
                        <option value="liters">Liters (L)</option>
                        <option value="ml">Milliliters (ml)</option>
                        <option value="packs">Packs</option>
                        <option value="boxes">Boxes</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="flex items-center justify-between">
            <select name="is_active" class="px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button type="submit" class="px-6 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Add Product</button>
        </div>
    </form>
    
    <!-- Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($products as $product): ?>
            <div class="p-4 rounded-lg theme-transition border <?= $product['is_active'] ? '' : 'opacity-60' ?>" style="background: var(--bg-secondary); border-color: var(--border-primary)" id="product-<?= $product['id'] ?>" data-product-id="<?= $product['id'] ?>">
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
                <form class="product-edit hidden" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <div class="space-y-3">
                        <!-- Basic Product Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-medium mb-1 block" style="color: var(--text-secondary)">Product Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Product Name">
                            </div>
                            <div>
                                <label class="text-xs font-medium mb-1 block" style="color: var(--text-secondary)">Price (RM)</label>
                                <input type="number" name="price" value="<?= $product['price'] ?>" step="0.01" min="0" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Price">
                            </div>
                        </div>
                        
                        <!-- Category -->
                        <div>
                            <label class="text-xs font-medium mb-1 block" style="color: var(--text-secondary)">Category</label>
                            <select name="category" required class="w-full px-3 py-2 text-sm rounded theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $product['category'] === $cat['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label class="text-xs font-medium mb-1 block" style="color: var(--text-secondary)">Description</label>
                            <textarea name="description" rows="2" class="w-full px-3 py-2 text-sm rounded theme-transition resize-none" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)" placeholder="Optional description..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Current Image & Upload -->
                        <div>
                            <label class="text-xs font-medium mb-2 block" style="color: var(--text-secondary)">Product Image</label>
                            
                            <!-- Current Image Preview -->
                            <?php if ($product['image'] && file_exists(__DIR__ . '/' . $product['image'])): ?>
                                <div class="mb-3 p-2 rounded border" style="border-color: var(--border-primary); background: var(--bg-secondary);">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?= htmlspecialchars($product['image']) ?>" class="w-12 h-12 object-cover rounded border" style="border-color: var(--border-primary)">
                                        <div>
                                            <p class="text-xs font-medium" style="color: var(--text-primary)">Current Image</p>
                                            <p class="text-xs" style="color: var(--text-secondary)"><?= basename($product['image']) ?></p>
                                        </div>
                                        <div class="ml-auto">
                                            <button type="button" onclick="removeProductImage(<?= $product['id'] ?>)" class="text-xs px-2 py-1 rounded text-red-500 hover:bg-red-50 border border-red-200">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-3 p-3 rounded border text-center" style="border-color: var(--border-primary); background: var(--bg-secondary);">
                                    <?php 
                                    $icons = ['Food' => '🍔', 'Drinks' => '☕', 'Dessert' => '🍰'];
                                    $icon = $icons[$product['category']] ?? '🍽️';
                                    ?>
                                    <div class="text-2xl mb-1"><?= $icon ?></div>
                                    <p class="text-xs" style="color: var(--text-secondary)">Using category icon</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Upload New Image -->
                            <div class="relative">
                                <input type="file" name="product_image" accept="image/*" id="product-image-edit-<?= $product['id'] ?>" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <div class="flex items-center justify-between p-2 rounded border border-dashed theme-transition hover:border-opacity-60" style="border-color: var(--border-primary); background: var(--bg-card);">
                                    <div class="flex items-center space-x-2">
                                        <svg class="w-4 h-4" style="color: var(--text-secondary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <span class="text-xs" style="color: var(--text-secondary)" id="file-name-edit-<?= $product['id'] ?>">
                                            <?= $product['image'] ? 'Replace image' : 'Add image' ?>
                                        </span>
                                    </div>
                                    <button type="button" class="px-2 py-1 text-xs rounded theme-transition" style="background: var(--accent-primary); color: white; opacity: 0.8" onclick="document.getElementById('product-image-edit-<?= $product['id'] ?>').click()">Browse</button>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary)">JPG, PNG, GIF up to 2MB (optional)</p>
                            </div>
                        </div>
                        
                        <!-- Stock Management -->
                        <div>
                            <div class="mb-4 p-3 rounded-lg" style="background: var(--bg-primary); border: 1px solid var(--border-primary);">
                                <div class="flex items-center mb-3">
                                    <input type="checkbox" name="track_stock" id="track-stock-<?= $product['id'] ?>" value="1" 
                                           <?= $product['track_stock'] ? 'checked' : '' ?> 
                                           class="mr-2" onchange="toggleStockFields('<?= $product['id'] ?>')">
                                    <label for="track-stock-<?= $product['id'] ?>" class="text-xs font-medium" style="color: var(--text-primary)">
                                        📦 Track Stock for this Product
                                    </label>
                                </div>
                                
                                <div id="stock-fields-<?= $product['id'] ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-2" 
                                     style="display: <?= $product['track_stock'] ? 'grid' : 'none' ?>;">
                                    <div class="flex flex-col">
                                        <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Current Stock</label>
                                        <input type="number" name="stock_quantity" min="0" step="0.1" 
                                               value="<?= $product['stock_quantity'] ?? 0 ?>" 
                                               class="px-2 py-1 text-xs rounded theme-transition" 
                                               style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Min Level</label>
                                        <input type="number" name="min_stock_level" min="0" step="0.1" 
                                               value="<?= $product['min_stock_level'] ?? 5 ?>" 
                                               class="px-2 py-1 text-xs rounded theme-transition" 
                                               style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Max Level</label>
                                        <input type="number" name="max_stock_level" min="0" step="0.1" 
                                               value="<?= $product['max_stock_level'] ?? 100 ?>" 
                                               class="px-2 py-1 text-xs rounded theme-transition" 
                                               style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-xs font-medium mb-1" style="color: var(--text-secondary)">Unit</label>
                                        <select name="stock_unit" class="px-2 py-1 text-xs rounded theme-transition" 
                                                style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                                            <option value="pieces" <?= ($product['stock_unit'] ?? 'pieces') === 'pieces' ? 'selected' : '' ?>>Pieces</option>
                                            <option value="bottles" <?= ($product['stock_unit'] ?? '') === 'bottles' ? 'selected' : '' ?>>Bottles</option>
                                            <option value="cans" <?= ($product['stock_unit'] ?? '') === 'cans' ? 'selected' : '' ?>>Cans</option>
                                            <option value="kg" <?= ($product['stock_unit'] ?? '') === 'kg' ? 'selected' : '' ?>>Kilograms</option>
                                            <option value="g" <?= ($product['stock_unit'] ?? '') === 'g' ? 'selected' : '' ?>>Grams</option>
                                            <option value="liters" <?= ($product['stock_unit'] ?? '') === 'liters' ? 'selected' : '' ?>>Liters</option>
                                            <option value="ml" <?= ($product['stock_unit'] ?? '') === 'ml' ? 'selected' : '' ?>>Milliliters</option>
                                            <option value="packs" <?= ($product['stock_unit'] ?? '') === 'packs' ? 'selected' : '' ?>>Packs</option>
                                            <option value="boxes" <?= ($product['stock_unit'] ?? '') === 'boxes' ? 'selected' : '' ?>>Boxes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
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
    </div>
    
</div> <!-- End Menu Management Accordion -->

<script>
// Accordion functionality
function toggleAccordion(sectionId) {
    const content = document.getElementById(sectionId);
    const icon = document.getElementById(sectionId + '-icon');
    const header = content.previousElementSibling; // Get the header (previous sibling)
    
    if (content.classList.contains('expanded')) {
        // Collapse
        content.style.maxHeight = null;
        content.classList.remove('expanded');
        header.classList.remove('expanded'); // Remove expanded class from header
        icon.style.transform = 'rotate(0deg)';
    } else {
        // Expand with generous height
        content.classList.add('expanded');
        header.classList.add('expanded'); // Add expanded class to header
        content.style.maxHeight = 'none'; // Allow unlimited height when expanded
        icon.style.transform = 'rotate(180deg)';
    }
}

// Function to recalculate accordion heights (called when content changes)
function recalculateAccordionHeight(sectionId) {
    const content = document.getElementById(sectionId);
    if (content && content.classList.contains('expanded')) {
        content.style.maxHeight = 'none'; // Ensure expanded sections show all content
    }
}

// Initialize accordions - Product Management open by default
document.addEventListener('DOMContentLoaded', function() {
    // Add CSS for accordion transitions
    const style = document.createElement('style');
    style.textContent = `
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .accordion-content.expanded {
            max-height: none !important;
            overflow: visible;
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }
        .accordion-content:not(.expanded) {
            padding-top: 0;
            padding-bottom: 0;
        }
        .accordion-header {
            transition: background-color 0.2s ease, border-radius 0.3s ease;
            border-radius: 0.75rem; /* All corners rounded by default (when closed) */
            border-bottom: 1px solid var(--border-primary, #e2e8f0);
            margin: 0; /* Ensure no margin interferes */
        }
        .accordion-header:hover {
            background-color: var(--bg-secondary, rgba(0, 0, 0, 0.02));
            border-radius: 0.75rem; /* Maintain all rounded corners when closed */
        }
        /* When accordion is expanded, only top corners should be rounded */
        .accordion-header.expanded {
            border-radius: 0.75rem 0.75rem 0 0;
        }
        .accordion-header.expanded:hover {
            border-radius: 0.75rem 0.75rem 0 0;
        }
        .accordion-header:hover svg {
            color: var(--accent-primary);
        }
    `;
    document.head.appendChild(style);
    
    // Open Product Management by default
    const productManagement = document.getElementById('product-management');
    const productIcon = document.getElementById('product-management-icon');
    if (productManagement) {
        productManagement.classList.add('expanded');
        const productHeader = productManagement.previousElementSibling;
        if (productHeader) productHeader.classList.add('expanded');
        productIcon.style.transform = 'rotate(180deg)';
    }
});

// Stock management toggle function
function toggleStockFields(formId) {
    const checkbox = document.getElementById('track-stock-' + formId);
    const fields = document.getElementById('stock-fields-' + formId);
    
    if (checkbox && fields) {
        if (checkbox.checked) {
            fields.style.display = 'grid';
        } else {
            fields.style.display = 'none';
        }
    }
}

// Enhance the original edit functions to handle accordion behavior
document.addEventListener('DOMContentLoaded', function() {
    // Store original functions if they exist
    const originalEditProduct = window.editProduct;
    const originalEditCategory = window.editCategory;
    const originalCancelEdit = window.cancelEdit;
    const originalCancelCategoryEdit = window.cancelCategoryEdit;
    
    // Enhanced editProduct with accordion support
    if (typeof originalEditProduct === 'function') {
        window.editProduct = function(productId) {
            originalEditProduct(productId);
            
            // Ensure product management accordion is open
            const productAccordion = document.getElementById('product-management');
            if (productAccordion && !productAccordion.classList.contains('expanded')) {
                toggleAccordion('product-management');
            }
            setTimeout(() => recalculateAccordionHeight('product-management'), 100);
        };
    }
    
    // Enhanced editCategory with accordion support
    if (typeof originalEditCategory === 'function') {
        window.editCategory = function(categoryId) {
            originalEditCategory(categoryId);
            
            // Ensure category management accordion is open
            const categoryAccordion = document.getElementById('category-management');
            if (categoryAccordion && !categoryAccordion.classList.contains('expanded')) {
                toggleAccordion('category-management');
            }
            setTimeout(() => recalculateAccordionHeight('category-management'), 100);
        };
    }
    
    // Enhanced cancelEdit with accordion support
    if (typeof originalCancelEdit === 'function') {
        window.cancelEdit = function(productId) {
            originalCancelEdit(productId);
            setTimeout(() => recalculateAccordionHeight('product-management'), 100);
        };
    }
    
    // Enhanced cancelCategoryEdit with accordion support
    if (typeof originalCancelCategoryEdit === 'function') {
        window.cancelCategoryEdit = function(categoryId) {
            originalCancelCategoryEdit(categoryId);
            setTimeout(() => recalculateAccordionHeight('category-management'), 100);
        };
    }
});

// Enhanced file upload experience
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('product-image-upload');
    const fileName = document.getElementById('file-name');
    const uploadArea = fileInput ? fileInput.closest('.relative') : null;
    
    if (fileInput && fileName && uploadArea) {
        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                // Check file size (2MB limit)
                if (file.size > 2 * 1024 * 1024) {
                    fileName.textContent = '⚠️ File too large (max 2MB)';
                    fileName.style.color = '#dc2626';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    fileName.textContent = '⚠️ Invalid file type';
                    fileName.style.color = '#dc2626';
                    return;
                }
                
                // Show file info
                fileName.textContent = `📸 ${file.name} (${fileSize} MB)`;
                fileName.style.color = 'var(--accent-primary)';
                
                // Add success styling
                const uploadDiv = uploadArea.querySelector('div');
                if (uploadDiv) {
                    uploadDiv.style.borderColor = 'var(--accent-primary)';
                    uploadDiv.style.backgroundColor = 'rgba(0, 123, 255, 0.1)';
                }
            } else {
                resetFileInput();
            }
        });
        
        // Handle drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.opacity = '0.8';
            const uploadDiv = this.querySelector('div');
            if (uploadDiv) uploadDiv.style.borderColor = 'var(--accent-primary)';
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.opacity = '1';
            const uploadDiv = this.querySelector('div');
            if (uploadDiv) uploadDiv.style.borderColor = 'var(--border-primary)';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.opacity = '1';
            const uploadDiv = this.querySelector('div');
            if (uploadDiv) uploadDiv.style.borderColor = 'var(--border-primary)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
        
        function resetFileInput() {
            fileName.textContent = 'Choose image or drag & drop';
            fileName.style.color = 'var(--text-secondary)';
            const uploadDiv = uploadArea.querySelector('div');
            if (uploadDiv) {
                uploadDiv.style.borderColor = 'var(--border-primary)';
                uploadDiv.style.backgroundColor = 'var(--bg-card)';
            }
        }
    }
    
    // Handle edit form file inputs
    document.querySelectorAll('[id^="product-image-edit-"]').forEach(input => {
        const productId = input.id.replace('product-image-edit-', '');
        const fileName = document.getElementById(`file-name-edit-${productId}`);
        const uploadArea = input.closest('.relative');
        
        if (input && fileName && uploadArea) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    
                    // Check file size (2MB limit)
                    if (file.size > 2 * 1024 * 1024) {
                        fileName.textContent = '⚠️ File too large (max 2MB)';
                        fileName.style.color = '#dc2626';
                        return;
                    }
                    
                    // Check file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        fileName.textContent = '⚠️ Invalid file type';
                        fileName.style.color = '#dc2626';
                        return;
                    }
                    
                    // Show file info
                    fileName.textContent = `📸 ${file.name} (${fileSize} MB)`;
                    fileName.style.color = 'var(--accent-primary)';
                } else {
                    fileName.textContent = fileName.textContent.includes('Replace') ? 'Replace image' : 'Add image';
                    fileName.style.color = 'var(--text-secondary)';
                }
            });
        }
    });
});

// Remove product image function
function removeProductImage(productId) {
    if (confirm('Are you sure you want to remove this product image?')) {
        const formData = new FormData();
        formData.append('action', 'remove_product_image');
        formData.append('product_id', productId);
        formData.append('csrf_token', '<?= Security::generateCSRFToken() ?>');
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Refresh to show updated form
            } else {
                alert('Error: ' + (data.error || 'Failed to remove image'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing image');
        });
    }
}
</script>