<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart.js Test - KiraBOS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .chart-container { width: 600px; height: 400px; margin: 20px auto; }
    </style>
</head>
<body>
    <h1>Chart.js Integration Test</h1>
    <p>This page tests if Chart.js is loading correctly.</p>
    
    <div class="chart-container">
        <h2>Sales Trend Chart Test</h2>
        <canvas id="testChart"></canvas>
    </div>
    
    <div class="chart-container">
        <h2>Top 5 Menu Items Bar Chart Test</h2>
        <canvas id="testBarChart"></canvas>
    </div>
    
    <div class="chart-container">
        <h2>Category Performance Doughnut Chart Test</h2>
        <div style="width: 300px; height: 300px; margin: 0 auto;">
            <canvas id="testDoughnutChart"></canvas>
        </div>
    </div>
    
    <div class="chart-container">
        <h2>Payment Type Chart Test</h2>
        <div style="width: 300px; height: 300px; margin: 0 auto;">
            <canvas id="testPaymentChart"></canvas>
        </div>
    </div>
    
    <script>
        // Test data
        const testData = [
            {day_name: 'Dec 30', sales: 125.50, orders: 8},
            {day_name: 'Dec 31', sales: 89.25, orders: 5},
            {day_name: 'Jan 1', sales: 0, orders: 0},
            {day_name: 'Jan 2', sales: 245.75, orders: 12},
            {day_name: 'Jan 3', sales: 189.50, orders: 9},
            {day_name: 'Jan 4', sales: 156.25, orders: 7},
            {day_name: 'Jan 5', sales: 289.75, orders: 15}
        ];
        
        // Initialize chart
        const ctx = document.getElementById('testChart');
        const labels = testData.map(item => item.day_name);
        const salesValues = testData.map(item => item.sales);
        const orderCounts = testData.map(item => item.orders);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales (RM)',
                    data: salesValues,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }, {
                    label: 'Orders',
                    data: orderCounts,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Sales: RM' + context.parsed.y.toFixed(2);
                                } else {
                                    return 'Orders: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'RM' + value.toFixed(0);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return value + ' orders';
                            }
                        }
                    }
                }
            }
        });
        
        // Test data for Top 5 Menu Items
        const testMenuData = [
            {name: 'Nasi Lemak', category: 'Food', quantity_sold: 25, revenue: 375.00},
            {name: 'Teh Tarik', category: 'Drinks', quantity_sold: 18, revenue: 54.00},
            {name: 'Curry Chicken', category: 'Food', quantity_sold: 12, revenue: 180.00},
            {name: 'Roti Canai', category: 'Food', quantity_sold: 8, revenue: 24.00},
            {name: 'Ice Kacang', category: 'Dessert', quantity_sold: 5, revenue: 17.50}
        ];
        
        // Initialize Top 3 Menu Items Bar Chart
        const barCtx = document.getElementById('testBarChart');
        const menuLabels = testMenuData.map(item => item.name);
        const quantitySold = testMenuData.map(item => item.quantity_sold);
        
        const colors = [
            'rgba(255, 193, 7, 0.8)',   // Gold for #1
            'rgba(108, 117, 125, 0.8)', // Silver for #2
            'rgba(205, 127, 50, 0.8)',  // Bronze for #3
            'rgba(74, 144, 226, 0.8)',  // Blue for #4
            'rgba(156, 39, 176, 0.8)'   // Purple for #5
        ];
        
        const borderColors = [
            'rgba(255, 193, 7, 1)',
            'rgba(108, 117, 125, 1)',
            'rgba(205, 127, 50, 1)',
            'rgba(74, 144, 226, 1)',
            'rgba(156, 39, 176, 1)'
        ];
        
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: menuLabels,
                datasets: [{
                    label: 'Items Sold',
                    data: quantitySold,
                    backgroundColor: colors,
                    borderColor: borderColors,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.5,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const itemIndex = context.dataIndex;
                                const item = testMenuData[itemIndex];
                                return [
                                    `Items Sold: ${context.parsed.y}`,
                                    `Revenue: RM${item.revenue.toFixed(2)}`,
                                    `Category: ${item.category}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                return Math.floor(value) === value ? value : '';
                            }
                        }
                    }
                }
            }
        });
        
        // Test data for Category Performance
        const testCategoryData = [
            {category: 'Food', total_revenue: 850.50, total_quantity_sold: 45},
            {category: 'Drinks', total_revenue: 125.00, total_quantity_sold: 25},
            {category: 'Dessert', total_revenue: 67.50, total_quantity_sold: 12}
        ];
        
        // Initialize Category Doughnut Chart
        const doughnutCtx = document.getElementById('testDoughnutChart');
        const categoryLabels = testCategoryData.map(cat => cat.category);
        const categoryRevenues = testCategoryData.map(cat => cat.total_revenue);
        const categoryQuantities = testCategoryData.map(cat => cat.total_quantity_sold);
        
        const categoryColors = [
            'rgba(255, 107, 107, 0.8)', // Red
            'rgba(78, 205, 196, 0.8)',  // Teal
            'rgba(255, 230, 109, 0.8)', // Yellow
        ];
        
        const categoryBorderColors = [
            'rgba(255, 107, 107, 1)',
            'rgba(78, 205, 196, 1)',
            'rgba(255, 230, 109, 1)'
        ];
        
        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Revenue',
                    data: categoryRevenues,
                    backgroundColor: categoryColors,
                    borderColor: categoryBorderColors,
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const category = context.label;
                                const revenue = context.parsed;
                                const quantity = categoryQuantities[context.dataIndex];
                                const percentage = ((revenue / categoryRevenues.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                
                                return [
                                    `${category}`,
                                    `Revenue: RM${revenue.toFixed(2)}`,
                                    `Items Sold: ${quantity}`,
                                    `Share: ${percentage}%`
                                ];
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: false,
                    duration: 1000,
                    easing: 'easeInOutCubic'
                }
            }
        });
        
        // Test data for Payment Types
        const testPaymentData = [
            {method: 'Cash', transactions: 15, amount: 450.75},
            {method: 'QR Code', transactions: 8, amount: 125.50}
        ];
        
        // Initialize Payment Type Doughnut Chart
        const paymentCtx = document.getElementById('testPaymentChart');
        const paymentLabels = testPaymentData.map(payment => payment.method);
        const paymentAmounts = testPaymentData.map(payment => payment.amount);
        const paymentTransactions = testPaymentData.map(payment => payment.transactions);
        
        const paymentColors = [
            'rgba(16, 185, 129, 0.8)', // Green for Cash
            'rgba(59, 130, 246, 0.8)'   // Blue for QR Code
        ];
        
        const paymentBorderColors = [
            'rgba(16, 185, 129, 1)',
            'rgba(59, 130, 246, 1)'
        ];
        
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    label: 'Payment Amount',
                    data: paymentAmounts,
                    backgroundColor: paymentColors,
                    borderColor: paymentBorderColors,
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const method = context.label;
                                const amount = context.parsed;
                                const transactionCount = paymentTransactions[context.dataIndex];
                                const percentage = ((amount / paymentAmounts.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                
                                return [
                                    `${method}`,
                                    `Amount: RM${amount.toFixed(2)}`,
                                    `Transactions: ${transactionCount}`,
                                    `Share: ${percentage}%`
                                ];
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: false,
                    duration: 1000,
                    easing: 'easeInOutCubic'
                }
            }
        });
        
        console.log('Chart.js tests loaded successfully!');
    </script>
</body>
</html>