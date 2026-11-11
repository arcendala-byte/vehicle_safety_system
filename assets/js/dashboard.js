/* ============================================================
   VEHISAFE — ADMIN DASHBOARD CHARTS
   Reads JSON payload from #dashboard-data and renders charts.
   ============================================================ */

(function () {
    'use strict';

    // ---- Read data payload ----
    var payloadEl = document.getElementById('dashboard-data');
    if (!payloadEl || typeof Chart === 'undefined') return;

    var data;
    try {
        data = JSON.parse(payloadEl.textContent);
    } catch (e) {
        console.error('Dashboard: invalid data payload', e);
        return;
    }

    // ---- Theme-aware colors ----
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name);
        return (v && v.trim()) || fallback;
    }

    var colors = {
        accent:   cssVar('--color-accent', '#2563eb'),
        success:  cssVar('--color-success', '#16a34a'),
        warning:  cssVar('--color-warning', '#d97706'),
        danger:   cssVar('--color-danger', '#dc2626'),
        info:     cssVar('--color-info', '#0284c7'),
        muted:    cssVar('--color-text-muted', '#64748b'),
        border:   cssVar('--color-border', '#e2e8f0')
    };

    // ---- Global Chart.js defaults ----
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = colors.muted;

    var tooltipOpts = {
        backgroundColor: '#0f172a',
        titleColor: '#ffffff',
        bodyColor: '#e2e8f0',
        borderColor: 'rgba(255,255,255,0.1)',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 6,
        displayColors: false
    };

    /* --------------------------------------------------------
       1. Violations Over Time (line chart)
       -------------------------------------------------------- */
    var overTimeEl = document.getElementById('chartViolationsOverTime');
    if (overTimeEl && data.violationsLast7Days) {
        var labels = Object.keys(data.violationsLast7Days).map(function (d) {
            var dt = new Date(d + 'T00:00:00');
            return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        });
        var values = Object.values(data.violationsLast7Days);

        new Chart(overTimeEl, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Violations',
                    data: values,
                    borderColor: colors.accent,
                    backgroundColor: 'rgba(37, 99, 235, 0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: colors.accent,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltipOpts },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: colors.border, drawBorder: false }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    /* --------------------------------------------------------
       2. Violations by Type (doughnut chart)
       -------------------------------------------------------- */
    var byTypeEl = document.getElementById('chartViolationsByType');
    if (byTypeEl && data.violationsByType) {
        var typeLabels = data.violationsByType.map(function (r) {
            return String(r.event_type)
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        });
        var typeValues = data.violationsByType.map(function (r) { return parseInt(r.total, 10); });

        // Colors per type — cycle through palette
        var palette = [colors.danger, colors.warning, colors.accent, colors.info, colors.success, colors.muted];

        new Chart(byTypeEl, {
            type: 'doughnut',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeValues,
                    backgroundColor: palette.slice(0, typeLabels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: tooltipOpts
                }
            }
        });
    }

    /* --------------------------------------------------------
       3. Black Spots by Risk Level (bar chart)
       -------------------------------------------------------- */
    var byRiskEl = document.getElementById('chartBlackspotsByRisk');
    if (byRiskEl && data.blackspotsByRisk) {
        var riskOrder = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        var riskMap = {};
        data.blackspotsByRisk.forEach(function (r) { riskMap[r.risk_level] = parseInt(r.total, 10); });
        var riskValues = riskOrder.map(function (k) { return riskMap[k] || 0; });

        var riskColors = [colors.success, colors.warning, colors.danger, '#7f1d1d'];

        new Chart(byRiskEl, {
            type: 'bar',
            data: {
                labels: riskOrder,
                datasets: [{
                    label: 'Black Spots',
                    data: riskValues,
                    backgroundColor: riskColors,
                    borderRadius: 6,
                    barThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltipOpts },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: colors.border, drawBorder: false }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    /* --------------------------------------------------------
       4. Sidebar toggle (mobile)
       -------------------------------------------------------- */
    var toggleBtn = document.querySelector('[data-sidebar-toggle]');
    var sidebar   = document.getElementById('appSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

})();