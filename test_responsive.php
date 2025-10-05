<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .debug-border { border: 3px solid red; }
    </style>
</head>
<body class="p-8">
    <h1 class="text-2xl mb-4">Responsive Test</h1>
    <p class="mb-4">Resize your browser to test:</p>

    <div class="mb-8">
        <h2 class="font-bold mb-2">Mobile View (should only show on small screens):</h2>
        <div class="block md:hidden debug-border p-4 bg-blue-100">
            <p>📱 MOBILE VIEW - You should see this on small screens only</p>
            <p class="text-sm">Screen width less than 768px</p>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="font-bold mb-2">Desktop View (should only show on medium+ screens):</h2>
        <div class="hidden md:block debug-border p-4 bg-green-100">
            <p>🖥️ DESKTOP VIEW - You should see this on medium+ screens only</p>
            <p class="text-sm">Screen width 768px or more</p>
        </div>
    </div>

    <div class="mt-8 p-4 border">
        <p class="font-bold">Current Screen Size:</p>
        <p class="block sm:hidden">📱 Extra Small (< 640px)</p>
        <p class="hidden sm:block md:hidden">📱 Small (640px - 767px)</p>
        <p class="hidden md:block lg:hidden">🖥️ Medium (768px - 1023px)</p>
        <p class="hidden lg:block xl:hidden">🖥️ Large (1024px - 1279px)</p>
        <p class="hidden xl:block">🖥️ Extra Large (1280px+)</p>
    </div>
</body>
</html>
