<?php
// This file contains the settings tab content
?>

<div class="theme-transition rounded-xl shadow-sm border p-6" style="background: var(--bg-card); border-color: var(--border-primary)">
    <h2 class="text-xl font-bold mb-6 theme-header">Settings</h2>
    
    <!-- Theme Settings -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4 theme-header">Appearance</h3>
        <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
            <label class="block text-sm font-medium mb-3" style="color: var(--text-primary)">Theme</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button onclick="setTheme('colorful')" id="theme-colorful" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                    <div class="text-2xl mb-2">🎨</div>
                    <div class="text-sm font-medium">Colorful</div>
                </button>
                <button onclick="setTheme('dark')" id="theme-dark" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                    <div class="text-2xl mb-2">🌙</div>
                    <div class="text-sm font-medium">Dark</div>
                </button>
                <button onclick="setTheme('minimal')" id="theme-minimal" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                    <div class="text-2xl mb-2">⚪</div>
                    <div class="text-sm font-medium">Minimal</div>
                </button>
                <button onclick="setTheme('original')" id="theme-original" class="theme-option p-4 rounded-lg border-2 transition-all hover:shadow-md">
                    <div class="text-2xl mb-2">⚫</div>
                    <div class="text-sm font-medium">Original</div>
                </button>
            </div>
        </div>
    </div>

    <!-- Restaurant Settings -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4 theme-header">Restaurant Information</h3>
        <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Restaurant Name</label>
                    <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['name']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Currency</label>
                    <p class="text-sm " style="color: var(--text-primary)"><?= htmlspecialchars($restaurant['currency']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div>
        <h3 class="text-lg font-semibold mb-4 theme-header">System Information</h3>
        <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">System Version</label>
                    <p class="text-sm " style="color: var(--text-primary)">KiraBOS v2.0</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">PHP Version</label>
                    <p class="text-sm " style="color: var(--text-primary)"><?= phpversion() ?></p>
                </div>
            </div>
        </div>
    </div>
</div>