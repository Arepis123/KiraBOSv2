<?php
// This file contains the reports tab content
?>

<div class="theme-transition rounded-xl shadow-sm border p-6 mb-6" style="background: var(--bg-card); border-color: var(--border-primary)">
    <h2 class="text-xl font-bold mb-6 theme-header">Sales Reports</h2>
    
    <!-- Report Generation Form -->
    <form class="mb-6 p-4 rounded-lg theme-transition" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)" onsubmit="generateReport(); return false;">
        <h3 class="font-semibold mb-3 theme-header">Generate Report</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary)">Period</label>
                <select id="report-period" onchange="toggleCustomDates()" class="w-full px-3 py-2 rounded-lg theme-transition" style="border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-primary)">
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div>
                <input id="start-date" type="date" class="px-3 py-2 rounded-lg theme-transition custom-date-input" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary); display: none;">
            </div>
            <div>
                <input id="end-date" type="date" class="px-3 py-2 rounded-lg theme-transition custom-date-input" style="border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-primary); display: none;">
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg font-medium text-white transition-colors" style="background: var(--accent-primary)" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    <span id="generate-btn-text">Generate Report</span>
                </button>
            </div>
        </div>
    </form>

    <div id="report-results" class="mb-8" style="display: none;">
        <div id="report-content"></div>
    </div>
</div>

<!-- Export Options -->
<div class="p-4 rounded-lg theme-transition mb-8" style="background: var(--bg-secondary); border: 1px solid var(--border-primary)">
    <h3 class="text-lg font-semibold mb-4 theme-header">Export Reports</h3>
    <div class="flex flex-wrap gap-3">
        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
            📄 Export as PDF
        </button>
        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
            📃 Export as Excel
        </button>
        <button class="px-4 py-2 rounded-lg transition-colors border" style="border-color: var(--border-primary); color: var(--text-primary); background: var(--bg-primary)">
            📧 Email Report
        </button>
    </div>
</div>