<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$dateFrom = (string) ($_GET['from'] ?? '');
$dateTo   = (string) ($_GET['to']   ?? '');

$rows = report_driver_safety($dateFrom ?: null, $dateTo ?: null);

// Summary metrics
$totalDrivers   = count($rows);
$totalTrips     = array_sum(array_column($rows, 'completed_trips'));
$totalViolations= array_sum(array_column($rows, 'total_violations'));
$totalKm        = array_sum(array_column($rows, 'total_km'));
$avgScore       = $totalDrivers > 0
    ? round(array_sum(array_column($rows, 'current_score')) / $totalDrivers, 1)
    : 0;

$user      = current_user();
$pageTitle = 'Driver Safety Report';
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

            <!-- Print-only header (hidden on screen) -->
            <div class="report-print-header">
                <h1>VEHISAFE — Driver Safety Report</h1>
                <div class="meta">
                    Generated: <?= date('d M Y, H:i') ?> |
                    Period: <?= $dateFrom ?: 'All time' ?> to <?= $dateTo ?: 'Now' ?> |
                    <?= $totalDrivers ?> drivers
                </div>
            </div>

            <!-- Screen header -->
            <div class="page-header page-header--row no-print">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/reports/index.php') ?>">Reports</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>Driver Safety</span>
                    </nav>
                    <h1 class="page-title">Driver Safety Report</h1>
                    <p class="page-subtitle"><?= $totalDrivers ?> drivers analyzed</p>
                </div>
                <div class="report-actions">
                    <button type="button" onclick="window.print()" class="btn btn--ghost">
                        <i data-lucide="printer"></i> Print
                    </button>
                    <a href="<?= url('api/reports/export.php?type=driver-safety&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo)) ?>"
                       class="btn btn--primary">
                        <i data-lucide="download"></i> Export CSV
                    </a>
                </div>
            </div>

            <!-- Filter bar (hidden on print) -->
            <form class="filter-bar no-print" method="GET">
                <label class="form-label" style="margin: 0;">From</label>
                <input type="date" name="from" class="form-input filter-bar__select" value="<?= e($dateFrom) ?>">
                <label class="form-label" style="margin: 0;">To</label>
                <input type="date" name="to" class="form-input filter-bar__select" value="<?= e($dateTo) ?>">
                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>
                <?php if ($dateFrom || $dateTo): ?>
                    <a href="<?= url('admin/reports/driver-safety.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Summary KPIs -->
            <div class="grid grid--kpis" style="margin-bottom: var(--space-4);">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Drivers</div>
                        <div class="kpi__value"><?= $totalDrivers ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="users"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Average Score</div>
                        <div class="kpi__value"><?= number_format($avgScore, 1) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="gauge"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Trips</div>
                        <div class="kpi__value"><?= $totalTrips ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="navigation"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Violations</div>
                        <div class="kpi__value"><?= $totalViolations ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total KM</div>
                        <div class="kpi__value"><?= number_format($totalKm, 0) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="route"></i></div>
                </div>
            </div>

            <!-- Data table -->
            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($rows)): ?>
                        <div class="empty-state">
                            <i data-lucide="users"></i>
                            <div class="empty-state__title">No drivers</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>License</th>
                                    <th>Score</th>
                                    <th>Trips</th>
                                    <th>Total KM</th>
                                    <th>Violations</th>
                                    <th>Severe</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('admin/drivers/view.php?id=' . (int) $r['driver_id']) ?>" class="link-plain">
                                                <?= e($r['full_name']) ?>
                                            </a>
                                        </td>
                                        <td><code><?= e($r['license_number']) ?></code></td>
                                        <td>
                                            <?php $s = (int) $r['current_score']; ?>
                                            <span class="badge <?= $s >= 85 ? 'badge--success' : ($s >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                                <?= $s ?>
                                            </span>
                                        </td>
                                        <td><?= (int) $r['completed_trips'] ?></td>
                                        <td><?= number_format((float) $r['total_km'], 1) ?></td>
                                        <td><?= (int) $r['total_violations'] ?></td>
                                        <td>
                                            <?php if ((int) $r['severe_violations'] > 0): ?>
                                                <span class="badge badge--danger"><?= (int) $r['severe_violations'] ?></span>
                                            <?php else: ?>
                                                <span class="text-subtle">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($r['status'])) ?>">
                                                <?= e(format_label($r['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Print footer -->
            <div class="report-print-header print-only" style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
                <div class="meta">
                    VEHISAFE · Generated <?= date('d M Y, H:i') ?> · Mount Kenya University
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
