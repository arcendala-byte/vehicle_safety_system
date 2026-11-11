<?php
/**
 * ============================================================
 * ADMIN DASHBOARD — Enterprise Overview
 * ============================================================
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_queries.php';

require_role('admin');

// -------- KPI data --------
$totalDrivers        = get_total_drivers();
$totalVehicles       = get_total_vehicles();
$activeTrips         = get_active_trips();
$totalViolations     = get_total_violations();
$openViolations      = get_open_violations();
$highRiskBlackspots  = get_high_risk_blackspots();
$avgSafetyScore      = get_average_safety_score();

// -------- Charts data --------
$violationsByType        = get_violations_by_type();
$violationsLast7Days     = get_violations_last_days(7);
$blackspotsByRisk        = get_blackspots_by_risk();

// -------- Recent activity --------
$recentViolations = get_recent_violations(8);
$recentTrips      = get_recent_trips(8);
$topDrivers       = get_top_drivers(5);
$topBlackspots    = get_top_blackspots(5);

$user      = current_user();
$pageTitle = 'Dashboard';

// Prepare chart payloads for JS (safe JSON)
$chartPayload = [
    'violationsByType'    => $violationsByType,
    'violationsLast7Days' => $violationsLast7Days,
    'blackspotsByRisk'    => $blackspotsByRisk,
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

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

            <?php require_once __DIR__ . '/../includes/flash.php'; ?>

            <div class="page-header">
                <h1 class="page-title">Welcome back, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="page-subtitle">System overview and road-safety intelligence at a glance.</p>
            </div>

            <!-- ============================================
                 KPI CARDS
                 ============================================ -->
            <div class="grid grid--kpis">

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Active Drivers</div>
                        <div class="kpi__value"><?= (int) $totalDrivers ?></div>
                        <div class="kpi__meta">Registered &amp; active</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent">
                        <i data-lucide="users"></i>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Active Vehicles</div>
                        <div class="kpi__value"><?= (int) $totalVehicles ?></div>
                        <div class="kpi__meta">Fleet in service</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info">
                        <i data-lucide="car"></i>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Active Trips</div>
                        <div class="kpi__value"><?= (int) $activeTrips ?></div>
                        <div class="kpi__meta">Currently in progress</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success">
                        <i data-lucide="navigation"></i>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Open Violations</div>
                        <div class="kpi__value"><?= (int) $openViolations ?></div>
                        <div class="kpi__meta"><?= (int) $totalViolations ?> total recorded</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning">
                        <i data-lucide="alert-triangle"></i>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">High-Risk Locations</div>
                        <div class="kpi__value"><?= (int) $highRiskBlackspots ?></div>
                        <div class="kpi__meta">HIGH + CRITICAL</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger">
                        <i data-lucide="map-pin"></i>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Average Safety Score</div>
                        <div class="kpi__value"><?= e(number_format($avgSafetyScore, 1)) ?><span class="kpi__unit">/100</span></div>
                        <div class="kpi__meta">Fleet-wide</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success">
                        <i data-lucide="gauge"></i>
                    </div>
                </div>

            </div>

            <!-- ============================================
                 CHARTS
                 ============================================ -->
            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card__title">Violations — Last 7 Days</div>
                    <div class="chart-card__canvas-wrap">
                        <canvas id="chartViolationsOverTime"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card__title">Violations by Type</div>
                    <div class="chart-card__canvas-wrap">
                        <canvas id="chartViolationsByType"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-card__title">Black Spots by Risk Level</div>
                    <div class="chart-card__canvas-wrap">
                        <canvas id="chartBlackspotsByRisk"></canvas>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 RECENT VIOLATIONS
                 ============================================ -->
            <div class="section-card">
                <div class="section-card__header">
                    <div>
                        <div class="section-card__title">Recent Violations</div>
                        <div class="section-card__subtitle">Latest safety events detected by the system</div>
                    </div>
                    <a href="#" class="btn btn--sm">
                        <i data-lucide="arrow-right"></i> View all
                    </a>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($recentViolations)): ?>
                        <div class="empty-state">
                            <i data-lucide="inbox"></i>
                            <div class="empty-state__title">No violations recorded</div>
                            <div class="empty-state__text">Safety events will appear here as they are detected.</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Severity</th>
                                    <th>Confidence</th>
                                    <th>Status</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentViolations as $v): ?>
                                    <tr>
                                        <td><?= e(format_event_type($v['event_type'])) ?></td>
                                        <td><?= e($v['driver_name']) ?></td>
                                        <td><?= e($v['registration_number'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($v['severity'])) ?>">
                                                <?= e(format_label($v['severity'])) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format((float) $v['confidence'] * 100, 0) ?>%</td>
                                        <td>
                                            <span class="badge <?= e(badge_class($v['status'])) ?>">
                                                <?= e(format_label($v['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(date('d M Y, H:i', strtotime($v['occurred_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================
                 RECENT TRIPS
                 ============================================ -->
            <div class="section-card">
                <div class="section-card__header">
                    <div>
                        <div class="section-card__title">Recent Trips</div>
                        <div class="section-card__subtitle">Latest journeys recorded across the fleet</div>
                    </div>
                    <a href="#" class="btn btn--sm">
                        <i data-lucide="arrow-right"></i> View all
                    </a>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($recentTrips)): ?>
                        <div class="empty-state">
                            <i data-lucide="inbox"></i>
                            <div class="empty-state__title">No trips recorded</div>
                            <div class="empty-state__text">Trips will appear here once drivers start journeys.</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Trip #</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Distance</th>
                                    <th>Violations</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTrips as $t): ?>
                                    <tr>
                                        <td>#<?= (int) $t['trip_id'] ?></td>
                                        <td><?= e($t['driver_name']) ?></td>
                                        <td><?= e($t['registration_number']) ?></td>
                                        <td><?= number_format((float) $t['distance_km'], 1) ?> km</td>
                                        <td><?= (int) $t['violation_count'] ?></td>
                                        <td><?= $t['safety_score'] !== null ? (int) $t['safety_score'] : '—' ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($t['status'])) ?>">
                                                <?= e(format_label($t['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(date('d M Y, H:i', strtotime($t['start_time']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================
                 TOP DRIVERS + TOP BLACK SPOTS (side by side)
                 ============================================ -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Top Drivers</div>
                            <div class="section-card__subtitle">Highest current safety scores</div>
                        </div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($topDrivers)): ?>
                            <div class="empty-state">
                                <i data-lucide="inbox"></i>
                                <div class="empty-state__title">No drivers yet</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th>License</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topDrivers as $d): ?>
                                        <tr>
                                            <td><?= e($d['full_name']) ?></td>
                                            <td><?= e($d['license_number']) ?></td>
                                            <td>
                                                <span class="badge <?= (int) $d['current_score'] >= 85 ? 'badge--success' : ((int) $d['current_score'] >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                                    <?= (int) $d['current_score'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Top Risk Black Spots</div>
                            <div class="section-card__subtitle">Ranked by risk score</div>
                        </div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($topBlackspots)): ?>
                            <div class="empty-state">
                                <i data-lucide="inbox"></i>
                                <div class="empty-state__title">No black spots recorded</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Road</th>
                                        <th>Risk</th>
                                        <th>Accidents</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topBlackspots as $b): ?>
                                        <tr>
                                            <td><?= e($b['road_name']) ?></td>
                                            <td>
                                                <span class="badge <?= e(badge_class($b['risk_level'])) ?>">
                                                    <?= e($b['risk_level']) ?>
                                                </span>
                                            </td>
                                            <td><?= (int) $b['accident_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Chart data payload for JS -->
<script id="dashboard-data" type="application/json">
<?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/dashboard.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>