<?php
// This file contains the dashboard tab content
// Include this file in admin.php
?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
    <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h3 class="text-lg font-semibold mb-2 theme-header">Today's Sales</h3>
        <p class="text-3xl font-bold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($stats['today_sales'], 2) ?></p>
        <div class="mt-2 text-xs" style="color: var(--text-secondary)">💰 Revenue</div>
    </div>
    <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h3 class="text-lg font-semibold mb-2 theme-header">Today's Orders</h3>
        <p class="text-3xl font-bold text-blue-500"><?= $stats['today_orders'] ?></p>
        <div class="mt-2 text-xs" style="color: var(--text-secondary)">📋 Transactions</div>
    </div>
    <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h3 class="text-lg font-semibold mb-2 theme-header">Active Products</h3>
        <p class="text-3xl font-bold text-purple-500"><?= $stats['active_products'] ?><span class="text-sm " style="color: var(--text-secondary) font-normal"> / <?= $stats['total_products'] ?></span></p>
        <div class="mt-2 text-xs" style="color: var(--text-secondary)">🍽️ Menu Items</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
    <!-- Category Sales Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Category Performance</h2>
        
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div class="text-center">
                <div class="text-6xl mb-4">📊</div>
                <p style="color: var(--text-secondary)">Chart will be implemented later</p>
            </div>
        </div>
        
        <!-- Category Summary -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-center p-2 rounded" style="background: var(--bg-secondary)">
                    <div class="font-semibold" style="color: var(--text-primary)"><?= count($category_sales) ?></div>
                    <div style="color: var(--text-secondary)">Active Categories</div>
                </div>
                <div class="text-center p-2 rounded" style="background: var(--bg-secondary)">
                    <div class="font-semibold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format(array_sum(array_column($category_sales, 'total_revenue')), 2) ?></div>
                    <div style="color: var(--text-secondary)">Total Revenue (7 days)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 3 Menu Items Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Top 3 Menu Items</h2>
        <div class="flex-1 relative flex items-center justify-center">
            <div class="text-center">
                <div class="text-6xl mb-4">🏆</div>
                <p style="color: var(--text-secondary)">Chart will be implemented later</p>
            </div>
        </div>
        <!-- Top Menu Summary -->
        <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-primary)">
            <div class="text-sm">
                <div class="font-semibold" style="color: var(--text-primary)">Last 7 Days Performance</div>
                <div class="text-xs" style="color: var(--text-secondary)">Items sold & revenue</div>
            </div>
        </div>
    </div>

    <!-- Third chart placeholder -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Analytics</h2>
        <div class="flex-1 relative flex items-center justify-center">
            <div class="text-center">
                <div class="text-6xl mb-4">📈</div>
                <p style="color: var(--text-secondary)">More analytics coming soon</p>
            </div>
        </div>
    </div>
</div>

<!-- Top Products Section -->
<div class="p-6 rounded-lg theme-transition mb-8" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
    <h3 class="text-lg font-semibold mb-4 theme-header">Top Products</h3>
    <div class="space-y-3">
        <?php foreach (array_slice($products, 0, 5) as $index => $product): ?>
            <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-primary)">
                <div class="flex items-center space-x-3">
                    <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center"><?= $index + 1 ?></span>
                    <span style="color: var(--text-primary)"><?= htmlspecialchars($product['name']) ?></span>
                </div>
                <div class="text-right">
                    <p class="font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($product['price'], 2) ?></p>
                    <p class="text-xs" style="color: var(--text-secondary)">Category: <?= htmlspecialchars($product['category']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Orders Section -->
<div class="p-6 rounded-lg theme-transition mb-8" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
    <h3 class="text-lg font-semibold mb-4 theme-header">Recent Orders</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($recent_orders as $order): ?>
            <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-primary); border-color: var(--border-primary)">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary)">Order #<?= $order['id'] ?></p>
                        <p class="text-sm" style="color: var(--text-secondary)">By: <?= htmlspecialchars($order['username']) ?></p>
                        <p class="text-sm" style="color: var(--text-secondary)"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($order['total_amount'], 2) ?></p>
                        <span class="inline-block px-2 py-1 text-xs rounded-full mt-1 <?= $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <button 
                        onclick="toggleOrderDetails(<?= $order['id'] ?>)" 
                        class="text-sm px-3 py-1 rounded transition-colors hover:opacity-80"
                        style="background: var(--accent-primary); color: white;"
                        id="details-btn-<?= $order['id'] ?>"
                    >
                        View Details
                    </button>
                    <p class="text-xs" style="color: var(--text-secondary)">Payment: <?= ucfirst($order['payment_method']) ?></p>
                </div>
                
                <!-- Order Items Container (loaded on demand) -->
                <div id="order-details-<?= $order['id'] ?>" class="hidden border-t pt-3 mt-3" style="border-color: var(--border-primary)">
                    <div class="loading-spinner text-center py-2">
                        <span style="color: var(--text-secondary)">Loading...</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>