/* ============================================================
   VEHISAFE — ANALYTICS CHARTS
   ============================================================ */
(function () {
    'use strict';
    var el = document.getElementById('analytics-data');
    if (!el || typeof Chart === 'undefined') return;
    var data;
    try { data = JSON.parse(el.textContent); } catch (e) { return; }

    var styles = getComputedStyle(document.documentElement);
    var accent  = styles.getPropertyValue('--color-accent').trim() || '#2563eb';
    var danger  = styles.getPropertyValue('--color-danger').trim() || '#dc2626';
    var border  = styles.getPropertyValue('--color-border').trim() || '#e2e8f0';
    var muted   = styles.getPropertyValue('--color-text-muted').trim() || '#64748b';

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = muted;

    var opts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0f172a',
                padding: 8,
                cornerRadius: 6,
                displayColors: false
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: border, drawBorder: false } },
            x: { grid: { display: false } }
        }
    };

    var vEl = document.getElementById('chartViolTrend');
    if (vEl && data.violations) {
        new Chart(vEl, {
            type: 'line',
            data: {
                labels: data.violations.labels || [],
                datasets: [{
                    data: data.violations.values || [],
                    borderColor: danger,
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                }]
            },
            options: opts
        });
    }

    var tEl = document.getElementById('chartTripTrend');
    if (tEl && data.trips) {
        new Chart(tEl, {
            type: 'bar',
            data: {
                labels: data.trips.labels || [],
                datasets: [{
                    data: data.trips.values || [],
                    backgroundColor: accent,
                    borderRadius: 4,
                }]
            },
            options: opts
        });
    }
})();
