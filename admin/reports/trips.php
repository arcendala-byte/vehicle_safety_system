<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$dateFrom = (string) ($_GET['from'] ?? '');
$dateTo   = (string) ($_GET['to']   ?? '');

$trips = report_trips($dateFrom ?: null, $dateTo ?: null);
$fleet = report_fleet_utilization();

$totalKm = array_sum(array_column($trips, 'distance_km'));
$totalViol = array_sum(array_column($trips, 'violation_count'));

$user      = current_user();
$pageTitle = 'Trips Report';
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
                <h1>VEHISAFE — Trips Report</h1>
                <div class="meta">
                    Generated: <?= date('d M Y, H:i') ?> |
                    Period: <?= $dateFrom ?: 'All time' ?> to <?= $dateTo ?: 'Now' ?> |
                    <?= count($trips) ?> trips
                </div>
            </div>

            <div class="page-header page-header--row no-print">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/reports/index.php') ?>">Reports</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>Trips</span>
                    </nav>
                    <h1 class="page-title">Trips Report</h1>
                    <p class="page-subtitle"><?= count($trips) ?> trips · <?= count($fleet) ?> vehicles</p>
                </div>
                <div class="report-actions">
                    <button type="button" onclick="window.print()" class="btn btn--ghost">
                        <i data-lucide="printer"></i> Print
                    </button>
                    <a href="<?= url('api/reports/export.php?type=trips&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo)) ?>"
                       class="btn btn--primary">
                        <i data-lucide="download"></i> Export CSV
                    </a>
                </div>
            </div>

            <form class="filter-bar no-print" method="GET">
                <label class="form-label" style="margin: 0;">From</label>
                <input type="date" name="from" class="form-input filter-bar__select" value="<?= e($dateFrom) ?>">
                <label class="form-label" style="margin: 0;">To</label>
                <input type="date" name="to" class="form-input filter-bar__select" value="<?= e($dateTo) ?>">
                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>
                <?php if ($dateFrom || $dateTo): ?>
                    <a href="<?= url('admin/reports/trips.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="grid grid--kpis" style="margin-bottom: var(--space-4);">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Trips</div>
                        <div class="kpi__value"><?= count($trips) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="navigation"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total KM</div>
                        <div class="kpi__value"><?= number_format($totalKm, 1) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="route"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Violations</div>
                        <div class="kpi__value"><?= $totalViol ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Vehicles</div>
                        <div class="kpi__value"><?= count($fleet) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="car"></i></div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Fleet Utilization</div>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <table class="data-table">
                        <thead>
                            <tr><th>Vehicle</th><th>Type</th><th>Driver</th><th>Status</th><th>Trips</th><th>Total KM</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fleet as $f): ?>
                                <tr>
                                    <td><strong><?= e($f['registration_number']) ?></strong></td>
                                    <td><?= e(ucfirst($f['vehicle_type'])) ?></td>
                                    <td><?= e($f['driver_name'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge <?= e(badge_class($f['status'])) ?>">
                                            <?= e(format_label($f['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= (int) $f['trip_count'] ?></td>
                                    <td><?= number_format((float) $f['total_km'], 1) ?> km</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Trip Details</div>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <table class="data-table">
                        <thead>
                            <tr><th>Trip</th><th>Driver</th><th>Vehicle</th><th>Distance</th><th>Violations</th><th>Score</th><th>Status</th><th>Started</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $t): ?>
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
                                    <td><?= e(date('d M Y H:i', strtotime($t['start_time']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-print-header print-only" style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
                <div class="meta">VEHISAFE · Generated <?= date('d M Y, H:i') ?> · Mount Kenya University</div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
