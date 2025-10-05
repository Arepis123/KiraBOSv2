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

    <!-- Tax Settings -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4 theme-header">Tax Configuration</h3>
        <div class="p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Enable Tax</label>
                        <p class="text-xs" style="color: var(--text-secondary)">When disabled, no tax will be calculated on orders</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="taxEnabledCheckbox" value="1" class="hidden"
                               <?= !empty($restaurant['tax_enabled']) ? 'checked' : '' ?>
                               onchange="saveTaxEnabled(this.checked)">
                        <div id="toggleSwitch" class="w-11 h-6 rounded-full relative transition-all"
                             style="background: <?= !empty($restaurant['tax_enabled']) ? 'var(--accent-primary)' : 'var(--border-primary)' ?>">
                            <div id="toggleCircle" class="absolute top-[2px] left-[2px] bg-white rounded-full h-5 w-5 transition-transform"
                                 style="transform: translateX(<?= !empty($restaurant['tax_enabled']) ? '20px' : '0' ?>)"></div>
                        </div>
                    </label>
                </div>

                <form method="POST" action="admin.php?page=settings" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <input type="hidden" name="update_tax_rate" value="1">

                    <div id="taxRateSection" class="transition-opacity <?= empty($restaurant['tax_enabled']) ? 'opacity-50' : '' ?>">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary)">Tax Rate (%)</label>
                        <input type="number"
                               id="taxRateInput"
                               name="tax_rate"
                               step="0.01"
                               min="0"
                               max="100"
                               value="<?= number_format(($restaurant['tax_rate'] ?? 0) * 100, 2) ?>"
                               class="w-full px-3 py-2 rounded-lg border text-sm"
                               style="background: var(--bg-card); color: var(--text-primary); border-color: var(--border-primary)"
                               <?= empty($restaurant['tax_enabled']) ? 'disabled' : '' ?>>
                        <p class="text-xs mt-1" style="color: var(--text-secondary)">Enter percentage (e.g., 8.5 for 8.5%)</p>
                    </div>

                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white transition-opacity hover:opacity-80"
                            style="background: var(--accent-primary)">
                        Save Tax Rate
                    </button>
                </form>
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

<script>
function saveTaxEnabled(enabled) {
    const toggleSwitch = document.getElementById('toggleSwitch');
    const toggleCircle = document.getElementById('toggleCircle');
    const taxRateSection = document.getElementById('taxRateSection');
    const taxRateInput = document.getElementById('taxRateInput');

    // Update UI immediately
    if (enabled) {
        toggleSwitch.style.background = 'var(--accent-primary)';
        toggleCircle.style.transform = 'translateX(20px)';
        taxRateSection.classList.remove('opacity-50');
        taxRateInput.disabled = false;
    } else {
        toggleSwitch.style.background = 'var(--border-primary)';
        toggleCircle.style.transform = 'translateX(0)';
        taxRateSection.classList.add('opacity-50');
        taxRateInput.disabled = true;
    }

    // Save to server via form submission
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin.php?page=settings';

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= Security::generateCSRFToken() ?>';
    form.appendChild(csrfInput);

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'update_tax_enabled';
    actionInput.value = '1';
    form.appendChild(actionInput);

    const taxEnabledInput = document.createElement('input');
    taxEnabledInput.type = 'hidden';
    taxEnabledInput.name = 'tax_enabled';
    taxEnabledInput.value = enabled ? '1' : '0';
    form.appendChild(taxEnabledInput);

    document.body.appendChild(form);
    form.submit();
}
</script>