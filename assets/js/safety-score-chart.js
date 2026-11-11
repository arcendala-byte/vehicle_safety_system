/* ============================================================
   VEHISAFE — DRIVER SAFETY SCORE TREND
   ============================================================ */

(function () {
    'use strict';

    var el = document.getElementById('safety-score-data');
    var canvas = document.getElementById('safetyScoreChart');
    if (!el || !canvas || typeof Chart === 'undefined') return;

    var data;
    try { data = JSON.parse(el.textContent); } catch (e) { return; }

    var styles = getComputedStyle(document.documentElement);
    var accent  = styles.getPropertyValue('--color-accent').trim()  || '#2563eb';
    var border  = styles.getPropertyValue('--color-border').trim()  || '#e2e8f0';
    var muted   = styles.getPropertyValue('--color-text-muted').trim() || '#64748b';

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = muted;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Safety Score',
                data: data.scores || [],
                borderColor: accent,
                backgroundColor: 'rgba(37, 99, 235, 0.10)',
                borderWidth: 2,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: accent,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: { stepSize: 20 },
                    grid: { color: border, drawBorder: false }
                },
                x: { grid: { display: false } }
            }
        }
    });
})();