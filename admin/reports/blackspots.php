<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$rows = report_blackspots();

// Summary
$riskCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
$totalAccidents = 0;
foreach ($rows as $r) {
    $riskCounts[$r['risk_level']] = ($riskCounts[$r['risk_level']] ?? 0) + 1;
    $totalAccidents += (int) $r['accident_count'];
}

$user      = current_user();
$pageTitle = 'Black Spots Report';
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
                <h1>VEHISAFE — Black Spots Report</h1>
                <div class="meta">
                    Generated: <?= date('d M Y, H:i') ?> |
                    <?= count($rows) ?> active locations
                </div>
            </div>

            <div class="page-header page-header--row no-print">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/reports/index.php') ?>">Reports</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>Black Spots</span>
                    </nav>
                    <h1 class="page-title">Black Spots Report</h1>
                    <p class="page-subtitle"><?= count($rows) ?> active locations</p>
                </div>
                <div class="report-actions">
                    <button type="button" onclick="window.print()" class="btn btn--ghost">
                        <i data-lucide="printer"></i> Print
                    </button>
                    <a href="<?= url('api/reports/export.php?type=blackspots') ?>" class="btn btn--primary">
                        <i data-lucide="download"></i> Export CSV
                    </a>
                </div>
            </div>

            <div class="grid grid--kpis" style="margin-bottom: var(--space-4);">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">CRITICAL</div>
                        <div class="kpi__value"><?= $riskCounts['CRITICAL'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-octagon"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">HIGH</div>
                        <div class="kpi__value"><?= $riskCounts['HIGH'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">MEDIUM</div>
                        <div class="kpi__value"><?= $riskCounts['MEDIUM'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="alert-circle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Accidents</div>
                        <div class="kpi__value"><?= $totalAccidents ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="activity"></i></div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Road</th>
                                <th>Risk</th>
                                <th>Score</th>
                                <th>Accidents</th>
                                <th>Conditions</th>
                                <th>Location</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td>
                                        <div class="cell-primary"><?= e($r['road_name']) ?></div>
                                        <div class="cell-secondary"><?= e($r['description'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= e(badge_class($r['risk_level'])) ?>">
                                            <?= e($r['risk_level']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format((float) $r['risk_score'], 3) ?></td>
                                    <td><?= (int) $r['accident_count'] ?></td>
                                    <td class="cell-secondary">
                                        <?= e(ucfirst($r['road_condition'])) ?>,
                                        <?= e(ucfirst($r['weather_typical'])) ?>,
                                        <?= e(ucfirst($r['traffic_density'])) ?>
                                    </td>
                                    <td><code><?= number_format((float) $r['latitude'], 4) ?>, <?= number_format((float) $r['longitude'], 4) ?></code></td>
                                    <td><?= e(date('d M Y', strtotime($r['last_updated']))) ?></td>
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
