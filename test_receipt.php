<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Preview - KiraBOS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            max-width: 400px;
            margin: 0 auto;
        }
        .receipt {
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .border-b { border-bottom: 1px solid #e5e7eb; }
        .border-b-2 { border-bottom: 2px solid #e5e7eb; }
        .border-dashed { border-style: dashed; }
        .text-center { text-align: center; }
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .pb-4 { padding-bottom: 1rem; }
        .font-bold { font-weight: bold; }
        .font-semibold { font-weight: 600; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-lg { font-size: 1.125rem; }
        .text-2xl { font-size: 1.5rem; }
        .space-y-2 > * + * { margin-top: 0.5rem; }
        .text-green-600 { color: #059669; }
        .text-gray-600 { color: #6b7280; }
        .flex-1 { flex: 1; }
    </style>
</head>
<body>
    <h1 style="text-align: center; margin-bottom: 20px;">Receipt Preview</h1>

    <div class="receipt">
        <!-- Receipt Header -->
        <div class="text-center mb-6 pb-4 border-b-2 border-dashed">
            <h2 class="text-2xl font-bold mb-2">Nasi Lemak Malam-Malam</h2>
            <p class="text-sm text-gray-600">Thank you for your purchase!</p>
        </div>

        <!-- Order Info -->
        <div class="mb-4 pb-4 border-b">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Order #:</span>
                <span class="font-semibold">ORD-20250104-0001</span>
            </div>
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Date:</span>
                <span>1/4/2025, 2:30:45 PM</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Cashier:</span>
                <span>admin</span>
            </div>
        </div>

        <!-- Items -->
        <div class="mb-4 pb-4 border-b">
            <h3 class="font-semibold mb-3">Items</h3>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <div class="flex-1">
                        <span>2x Nasi Lemak Special</span>
                    </div>
                    <span>RM12.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <div class="flex-1">
                        <span>1x Teh Tarik</span>
                    </div>
                    <span>RM2.50</span>
                </div>
                <div class="flex justify-between text-sm">
                    <div class="flex-1">
                        <span>3x Roti Canai</span>
                    </div>
                    <span>RM3.60</span>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="space-y-2 mb-4 pb-4 border-b-2 border-dashed">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span>RM18.10</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Tax (8.00%):</span>
                <span>RM1.45</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
                <span>Total:</span>
                <span>RM19.55</span>
            </div>
        </div>

        <!-- Payment Info (Cash Example) -->
        <div class="space-y-2 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Payment Method:</span>
                <span class="font-semibold">Cash</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Amount Tendered:</span>
                <span>RM20.00</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Change:</span>
                <span class="font-semibold text-green-600">RM0.45</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-600">
            <p>Powered by KiraBOS</p>
        </div>
    </div>

    <h2 style="text-align: center; margin-top: 40px; margin-bottom: 20px;">QR Code Payment Example</h2>

    <div class="receipt">
        <!-- Receipt Header -->
        <div class="text-center mb-6 pb-4 border-b-2 border-dashed">
            <h2 class="text-2xl font-bold mb-2">Nasi Lemak Malam-Malam</h2>
            <p class="text-sm text-gray-600">Thank you for your purchase!</p>
        </div>

        <!-- Order Info -->
        <div class="mb-4 pb-4 border-b">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Order #:</span>
                <span class="font-semibold">ORD-20250104-0002</span>
            </div>
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Date:</span>
                <span>1/4/2025, 3:15:20 PM</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Cashier:</span>
                <span>cashier</span>
            </div>
        </div>

        <!-- Items -->
        <div class="mb-4 pb-4 border-b">
            <h3 class="font-semibold mb-3">Items</h3>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <div class="flex-1">
                        <span>1x Mee Goreng</span>
                    </div>
                    <span>RM7.50</span>
                </div>
                <div class="flex justify-between text-sm">
                    <div class="flex-1">
                        <span>2x Air Sirap</span>
                    </div>
                    <span>RM3.00</span>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="space-y-2 mb-4 pb-4 border-b-2 border-dashed">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span>RM10.50</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Tax (8.00%):</span>
                <span>RM0.84</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
                <span>Total:</span>
                <span>RM11.34</span>
            </div>
        </div>

        <!-- Payment Info (QR Code - no tendered/change) -->
        <div class="space-y-2 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Payment Method:</span>
                <span class="font-semibold">QR Code</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-600">
            <p>Powered by KiraBOS</p>
        </div>
    </div>

    <p style="text-align: center; margin-top: 30px; color: #6b7280;">
        This is how the receipt will look when printed or displayed on screen.
    </p>
</body>
</html>
