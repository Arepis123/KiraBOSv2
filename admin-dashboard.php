<?php
// This file contains the dashboard tab content
// Include this file in admin.php
?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 mb-8">
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
    <div class="theme-transition rounded-xl shadow-sm hover:shadow-md p-6 border" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h3 class="text-lg font-semibold mb-2 theme-header">Today's Expenses</h3>
        <p class="text-3xl font-bold text-red-500"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($today_expenses, 2) ?></p>
        <div class="mt-2 text-xs" style="color: var(--text-secondary)">💸 Costs</div>
    </div>
</div>

<!-- First Row: Category Performance, Expenses, Payment Methods -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
    <!-- Category Performance Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Category Performance (7 Days)</h2>
        
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div style="width: 150px; height: 150px;">
                <canvas id="categoryChart" width="150" height="150"></canvas>
            </div>
        </div>
        
        <!-- Category Legend -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-2 gap-1 text-xs">
                <?php 
                foreach ($category_sales as $index => $cat): 
                    if ($cat['total_revenue'] > 0): // Only show categories with sales
                        // Get the custom color for this category, fallback to defaults
                        $category_color = $cat['color'] ?? ['#FF6B6B', '#4ECDC4', '#FFE66D', '#74B9FF', '#A29BFE'][$index % 5];
                ?>
                    <div class="text-center p-1 rounded" style="background: var(--bg-secondary)">
                        <div class="flex items-center justify-center space-x-1 mb-1">
                            <div class="w-2 h-2 rounded-full" style="background: <?= htmlspecialchars($category_color) ?>"></div>
                            <span class="font-medium text-xs truncate" style="color: var(--text-primary)" title="<?= htmlspecialchars($cat['category']) ?>">
                                <?= htmlspecialchars($cat['category']) ?>
                            </span>
                        </div>
                        <div class="font-semibold text-green-500 text-xs">
                            <?= htmlspecialchars($restaurant['currency']) ?><?= number_format($cat['total_revenue'], 2) ?>
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary)">
                            <?= $cat['total_quantity_sold'] ?> items
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                
                // If no categories have sales, show summary
                if (array_sum(array_column($category_sales, 'total_revenue')) == 0):
                ?>
                    <div class="col-span-2 text-center p-4 rounded" style="background: var(--bg-secondary)">
                        <div class="font-medium text-xs" style="color: var(--text-secondary)">No sales data for the last 7 days</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Start taking orders to see category performance</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Expenses Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Expenses by Category (7 Days)</h2>
        
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div style="width: 150px; height: 150px;">
                <canvas id="expensesChart" width="150" height="150"></canvas>
            </div>
        </div>
        
        <!-- Expenses Legend -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-3 gap-1 text-xs">
                <?php 
                $expense_colors = ['#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16', '#22C55E', '#10B981', '#14B8A6'];
                foreach ($expenses_data as $index => $expense): 
                    if ($expense['total_amount'] > 0): // Only show categories with expenses
                ?>
                    <div class="text-center p-1 rounded" style="background: var(--bg-secondary)">
                        <div class="flex items-center justify-center space-x-1 mb-1">
                            <div class="w-2 h-2 rounded-full" style="background: <?= $expense_colors[$index % count($expense_colors)] ?>"></div>
                            <span class="font-medium text-xs truncate" style="color: var(--text-primary)" title="<?= htmlspecialchars($expense['category']) ?>">
                                <?= htmlspecialchars($expense['category']) ?>
                            </span>
                        </div>
                        <div class="font-semibold text-red-500 text-xs">
                            <?= htmlspecialchars($restaurant['currency']) ?><?= number_format($expense['total_amount'], 2) ?>
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary)">
                            <?= $expense['transaction_count'] ?>x
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                
                // If no expenses, show summary
                if (array_sum(array_column($expenses_data, 'total_amount')) == 0):
                ?>
                    <div class="col-span-3 text-center p-4 rounded" style="background: var(--bg-secondary)">
                        <div class="font-medium text-xs" style="color: var(--text-secondary)">No expenses data for the last 7 days</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Start tracking expenses to see category breakdown</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Type Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Payment Methods (7 Days)</h2>
        <div class="flex-1 relative min-h-0 flex items-center justify-center">
            <div style="width: 150px; height: 150px;">
                <canvas id="paymentChart" width="150" height="150"></canvas>
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
                            <span class="font-medium text-xs" style="color: var(--text-primary)"><?= htmlspecialchars($payment['method']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-green-500 text-xs"><?= htmlspecialchars($restaurant['currency']) ?><?= number_format($payment['amount'], 2) ?></div>
                            <div class="text-xs" style="color: var(--text-secondary)"><?= $payment['transactions'] ?> transactions</div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                
                // If no payments, show message
                if (array_sum(array_column($payment_type_data, 'amount')) == 0):
                ?>
                    <div class="text-center p-4 rounded" style="background: var(--bg-secondary)">
                        <div class="font-medium text-xs" style="color: var(--text-secondary)">No payment data for the last 7 days</div>
                        <div class="text-xs" style="color: var(--text-secondary)">Start processing orders to see payment breakdown</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Second Row: Sales Trend and Top 5 Menu Items -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6">
    <!-- Sales Trend Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Sales Trend (7 Days)</h2>
        <div class="flex-1 relative min-h-0" style="height: 200px;">
            <canvas id="salesTrendChart"></canvas>
        </div>
        
        <!-- Sales Summary -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-center p-3 rounded-lg" style="background: var(--bg-secondary)">
                    <div class="font-semibold text-blue-500 text-lg">
                        <?php 
                        $total_weekly_orders = array_sum(array_column($sales_trend_data, 'orders'));
                        echo $total_weekly_orders;
                        ?>
                    </div>
                    <div class="mt-1 text-xs" style="color: var(--text-secondary)">Total Orders (7d)</div>
                </div>
                <div class="text-center p-3 rounded-lg" style="background: var(--bg-secondary)">
                    <div class="font-semibold text-green-500 text-lg">
                        <?php 
                        $total_weekly_sales = array_sum(array_column($sales_trend_data, 'sales'));
                        echo htmlspecialchars($restaurant['currency']) . number_format($total_weekly_sales, 2);
                        ?>
                    </div>
                    <div class="mt-1 text-xs" style="color: var(--text-secondary)">Total Sales (7d)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 Menu Items Chart -->
    <div class="theme-transition rounded-xl shadow-sm border p-6 flex flex-col min-h-[300px]" style="background: var(--bg-card); border-color: var(--border-primary)">
        <h2 class="text-xl font-bold mb-6 theme-header">Top 5 Menu Items (7 Days)</h2>
        <div class="flex-1 relative min-h-0" style="height: 200px;">
            <canvas id="topMenuChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Top Menu Summary -->
        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-primary)">
            <div class="grid grid-cols-5 gap-1 text-xs">
                <?php foreach ($top_menu_items as $index => $item): ?>
                    <div class="text-center p-2 rounded" style="background: var(--bg-secondary)">
                        <div class="font-semibold text-xs truncate" style="color: var(--text-primary)" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= $index + 1 ?>. <?= htmlspecialchars($item['name']) ?>
                        </div>
                        <div class="mt-1 font-medium text-green-500 text-xs">
                            <?= htmlspecialchars($restaurant['currency']) ?><?= number_format($item['revenue'], 2) ?>
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary)">
                            <?= $item['quantity_sold'] ?> sold
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<!-- Recent Orders Section -->
<div class="p-6 rounded-lg theme-transition mb-8" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
        <h3 class="text-lg font-semibold theme-header">Recent Orders</h3>
        <button onclick="toggleOrderFilters()" class="text-sm px-4 py-2 rounded-lg transition-colors" style="background: var(--accent-primary); color: white;">
            🔍 Filter Orders
        </button>
    </div>
    
    <!-- Filter Panel -->
    <div id="order-filters" class="hidden mb-6 p-4 rounded-lg" style="background: var(--bg-primary); border: 1px solid var(--border-primary)">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Date Range Filter -->
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Date From</label>
                <input type="date" id="filter-date-from" class="w-full px-3 py-2 rounded border text-sm" style="border-color: var(--border-primary); background: var(--bg-card); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Date To</label>
                <input type="date" id="filter-date-to" class="w-full px-3 py-2 rounded border text-sm" style="border-color: var(--border-primary); background: var(--bg-card); color: var(--text-primary);">
            </div>
            
            <!-- Payment Method Filter -->
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Payment Method</label>
                <select id="filter-payment-method" class="w-full px-3 py-2 rounded border text-sm" style="border-color: var(--border-primary); background: var(--bg-card); color: var(--text-primary);">
                    <option value="">All Methods</option>
                    <option value="cash">Cash</option>
                    <option value="qr_code">QR Code</option>
                    <option value="card">Card</option>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Order Status</label>
                <select id="filter-status" class="w-full px-3 py-2 rounded border text-sm" style="border-color: var(--border-primary); background: var(--bg-card); color: var(--text-primary);">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="flex justify-between items-center mt-4">
            <div class="flex space-x-2">
                <button onclick="applyOrderFilters()" class="px-4 py-2 rounded text-sm font-medium transition-colors" style="background: var(--accent-primary); color: white;">
                    Apply Filters
                </button>
                <button onclick="clearOrderFilters()" class="px-4 py-2 rounded text-sm font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-primary);">
                    Clear All
                </button>
            </div>
            <div id="orders-count" class="text-sm" style="color: var(--text-secondary)">
                Showing <?= count($recent_orders) ?> orders
            </div>
        </div>
    </div>
    
    <!-- Orders Loading State -->
    <div id="orders-loading" class="hidden text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <p class="mt-2 text-sm" style="color: var(--text-secondary)">Loading orders...</p>
    </div>
    
    <!-- Orders Grid -->
    <div id="orders-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($recent_orders)): ?>
            <div class="col-span-full text-center py-8">
                <div class="text-4xl mb-4">📋</div>
                <p style="color: var(--text-secondary)">No recent orders found</p>
                <p class="text-sm mt-2" style="color: var(--text-muted)">Orders will appear here once customers start placing them</p>
            </div>
        <?php else: ?>
            <?php foreach ($recent_orders as $order): ?>
                <div class="p-4 rounded-lg theme-transition border" style="background: var(--bg-primary); border-color: var(--border-primary)">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary)">Order #<?= $order['id'] ?></p>
                            <p class="text-sm" style="color: var(--text-secondary)">By: <?= htmlspecialchars($order['username'] ?? 'Unknown') ?></p>
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
                        <p class="text-xs" style="color: var(--text-secondary)">Payment: <?= $order['payment_method'] === 'qr_code' ? 'QR Code' : ucfirst($order['payment_method']) ?></p>
                    </div>
                    
                    <!-- Order Items Container (loaded on demand) -->
                    <div id="order-details-<?= $order['id'] ?>" class="hidden border-t pt-3 mt-3" style="border-color: var(--border-primary)">
                        <div class="loading-spinner text-center py-2">
                            <span style="color: var(--text-secondary)">Loading...</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>