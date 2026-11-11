<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$summary = report_system_summary();
$violTrend = report_violation_trend(30);
$tripTrend = report_trip_trend(30);

$violChart = [
    'labels' => array_map(fn($d) => date('d M', strtotime($d)), array_keys($violTrend)),
    'values' => array_values($violTrend),
];
$tripChart = [
    'labels' => array_map(fn($d) => date('d M', strtotime($d)), array_keys($tripTrend)),
    'values' => array_map(fn($r) => $r['trips'], array_values($tripTrend)),
];

$user      = current_user();
$pageTitle = 'Analytics';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../../includes/partials/sidebar_admin.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button class="app-topbar__toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <i data-lucide="menu"></i>
                </button>
                <span class="app-topbar__title"><?= e($pageTitle) ?></span>
            </div>
            <div class="app-topbar__right">
                <span class="app-badge app-badge--info">DEMO MODE</span>
                <button class="app-topbar__icon-btn" type="button" data-theme-toggle aria-label="Toggle theme">
                    <i data-lucide="moon"></i>
                </button>
                <span class="app-user">
                    <i data-lucide="user-circle"></i>
                    <span class="app-user__name"><?= e($user['full_name'] ?? 'User') ?></span>
                    <span class="app-user__role"><?= e($user['role'] ?? '') ?></span>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn--sm">
                    <i data-lucide="log-out"></i> Sign out
                </a>
            </div>
        </header>

        <main class="app-content">
            <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

            <div class="report-print-header">
                <h1>VEHISAFE — System Analytics</h1>
                <div class="meta">
                    Generated: <?= date('d M Y, H:i') ?> |
                    Period: last 30 days
                </div>
            </div>

            <div class="page-header page-header--row no-print">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/reports/index.php') ?>">Reports</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>Analytics</span>
                    </nav>
                    <h1 class="page-title">System Analytics</h1>
                    <p class="page-subtitle">Trends over the last 30 days</p>
                </div>
                <div class="report-actions">
                    <button type="button" onclick="window.print()" class="btn btn--ghost">
                        <i data-lucide="printer"></i> Print
                    </button>
                </div>
            </div>

            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Avg Safety Score</div>
                        <div class="kpi__value"><?= number_format($summary['avg_score'], 1) ?></div>
                        <div class="kpi__meta">Fleet-wide</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="gauge"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Distance</div>
                        <div class="kpi__value"><?= number_format($summary['total_km'], 0) ?><span class="kpi__unit">km</span></div>
                        <div class="kpi__meta">Completed trips</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="route"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Violations</div>
                        <div class="kpi__value"><?= $summary['total_violations'] ?></div>
                        <div class="kpi__meta"><?= $summary['critical_violations'] ?> critical</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Black Spots</div>
                        <div class="kpi__value"><?= $summary['blackspots'] ?></div>
                        <div class="kpi__meta">Active</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="map-pin"></i></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card__title">Violations — Last 30 Days</div>
                    <div class="chart-card__canvas-wrap">
                        <canvas id="chartViolTrend"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card__title">Trips — Last 30 Days</div>
                    <div class="chart-card__canvas-wrap">
                        <canvas id="chartTripTrend"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script id="analytics-data" type="application/json">
<?= json_encode([
    'violations' => $violChart,
    'trips'      => $tripChart,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/analytics-charts.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
