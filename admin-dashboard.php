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
    <!-- Category Performance Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Category Performance (7 Days)</h2>
        
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div style="width: 200px; height: 200px;">
                <canvas id="categoryChart" width="200" height="200"></canvas>
            </div>
        </div>
        
        <!-- Category Legend -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-1 gap-2 text-xs">
                <?php 
                $category_colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#74B9FF', '#A29BFE'];
                foreach ($category_sales as $index => $cat): 
                    if ($cat['total_revenue'] > 0): // Only show categories with sales
                ?>
                    <div class="flex items-center justify-between p-2 rounded" style="background: var(--bg-secondary)">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full" style="background: <?= $category_colors[$index % count($category_colors)] ?>"></div>
                            <span class="font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($cat['category']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($cat['total_revenue'], 2) ?></div>
                            <div style="color: var(--text-secondary)"><?= $cat['total_quantity_sold'] ?> items</div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                
                // If no categories have sales, show summary
                if (array_sum(array_column($category_sales, 'total_revenue')) == 0):
                ?>
                    <div class="text-center p-4 rounded" style="background: var(--bg-secondary)">
                        <div class="font-medium" style="color: var(--text-secondary)">No sales data for the last 7 days</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Start taking orders to see category performance</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top 5 Menu Items Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Top 5 Menu Items (7 Days)</h2>
        <div class="flex-1 relative min-h-0">
            <canvas id="topMenuChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Top Menu Summary -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-5 gap-1 text-xs">
                <?php foreach ($top_menu_items as $index => $item): ?>
                    <div class="text-center p-2 rounded" style="background: var(--bg-secondary)">
                        <div class="font-semibold" style="color: var(--text-primary)">
                            <?= $index + 1 ?>. <?= htmlspecialchars($item['name']) ?>
                        </div>
                        <div style="color: var(--text-secondary)">
                            <?= $item['quantity_sold'] ?> sold
                        </div>
                        <div class="font-medium text-green-500">
                            <?= htmlspecialchars($restaurant['currency']) ?><?= number_format($item['revenue'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Payment Type Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Payment Methods (7 Days)</h2>
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div style="width: 200px; height: 200px;">
                <canvas id="paymentChart" width="200" height="200"></canvas>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-1 gap-2 text-xs">
                <?php foreach ($payment_type_data as $index => $payment): 
                    if ($payment['amount'] > 0): // Only show payment methods with transactions
                ?>
                    <div class="flex items-center justify-between p-2 rounded" style="background: var(--bg-secondary)">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full" style="background: <?= $payment['method'] === 'Cash' ? '#10B981' : '#3B82F6' ?>"></div>
                            <span class="font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($payment['method']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-green-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($payment['amount'], 2) ?></div>
                            <div style="color: var(--text-secondary)"><?= $payment['transactions'] ?> transactions</div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                
                // If no payments, show message
                if (array_sum(array_column($payment_type_data, 'amount')) == 0):
                ?>
                    <div class="text-center p-4 rounded" style="background: var(--bg-secondary)">
                        <div class="font-medium" style="color: var(--text-secondary)">No payment data for the last 7 days</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Start processing orders to see payment breakdown</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sales Trend Chart - Full Width -->
<div class="theme-transition rounded-xl shadow-sm border p-6 mb-8" style="background: var(--bg-card); border-color: var(--border-primary)">
    <h2 class="text-xl font-bold mb-6 theme-header">Sales Trend (7 Days)</h2>
    <div class="relative" style="height: 300px;">
        <canvas id="salesTrendChart"></canvas>
    </div>
    
    <!-- Sales Summary -->
    <div class="mt-6 pt-4 border-t" style="border-color: var(--border-primary)">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div class="text-center p-4 rounded-lg" style="background: var(--bg-secondary)">
                <div class="font-semibold text-blue-500 text-2xl">
                    <?php 
                    $total_weekly_orders = array_sum(array_column($sales_trend_data, 'orders'));
                    echo $total_weekly_orders;
                    ?>
                </div>
                <div class="mt-1" style="color: var(--text-secondary)">Total Orders (7d)</div>
            </div>
            <div class="text-center p-4 rounded-lg" style="background: var(--bg-secondary)">
                <div class="font-semibold text-green-500 text-2xl">
                    <?php 
                    $total_weekly_sales = array_sum(array_column($sales_trend_data, 'sales'));
                    echo htmlspecialchars($restaurant['currency']) . number_format($total_weekly_sales, 2);
                    ?>
                </div>
                <div class="mt-1" style="color: var(--text-secondary)">Total Sales (7d)</div>
            </div>
            <div class="text-center p-4 rounded-lg" style="background: var(--bg-secondary)">
                <div class="font-semibold text-purple-500 text-2xl">
                    <?php 
                    $avg_daily_orders = $total_weekly_orders > 0 ? round($total_weekly_orders / 7, 1) : 0;
                    echo $avg_daily_orders;
                    ?>
                </div>
                <div class="mt-1" style="color: var(--text-secondary)">Avg Orders/Day</div>
            </div>
            <div class="text-center p-4 rounded-lg" style="background: var(--bg-secondary)">
                <div class="font-semibold text-orange-500 text-2xl">
                    <?php 
                    $avg_order_value = $total_weekly_orders > 0 ? $total_weekly_sales / $total_weekly_orders : 0;
                    echo htmlspecialchars($restaurant['currency']) . number_format($avg_order_value, 2);
                    ?>
                </div>
                <div class="mt-1" style="color: var(--text-secondary)">Avg Order Value</div>
            </div>
        </div>
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