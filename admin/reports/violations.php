<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$dateFrom = (string) ($_GET['from'] ?? '');
$dateTo   = (string) ($_GET['to']   ?? '');

$rows = report_violations([
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
]);

// Summary
$severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
$typeCounts = [];
foreach ($rows as $r) {
    $severityCounts[$r['severity']] = ($severityCounts[$r['severity']] ?? 0) + 1;
    $typeCounts[$r['event_type']] = ($typeCounts[$r['event_type']] ?? 0) + 1;
}

$user      = current_user();
$pageTitle = 'Violations Report';
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

            <!-- Print header -->
            <div class="report-print-header">
                <h1>VEHISAFE — Violations Report</h1>
                <div class="meta">
                    Generated: <?= date('d M Y, H:i') ?> |
                    Period: <?= $dateFrom ?: 'All time' ?> to <?= $dateTo ?: 'Now' ?> |
                    <?= count($rows) ?> violations
                </div>
            </div>

            <div class="page-header page-header--row no-print">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/reports/index.php') ?>">Reports</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>Violations</span>
                    </nav>
                    <h1 class="page-title">Violations Report</h1>
                    <p class="page-subtitle"><?= count($rows) ?> violations</p>
                </div>
                <div class="report-actions">
                    <button type="button" onclick="window.print()" class="btn btn--ghost">
                        <i data-lucide="printer"></i> Print
                    </button>
                    <a href="<?= url('api/reports/export.php?type=violations&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo)) ?>"
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
                    <a href="<?= url('admin/reports/violations.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="grid grid--kpis" style="margin-bottom: var(--space-4);">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Critical</div>
                        <div class="kpi__value"><?= $severityCounts['critical'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-octagon"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">High</div>
                        <div class="kpi__value"><?= $severityCounts['high'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Medium</div>
                        <div class="kpi__value"><?= $severityCounts['medium'] ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="alert-circle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total</div>
                        <div class="kpi__value"><?= count($rows) ?></div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="list"></i></div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($rows)): ?>
                        <div class="empty-state">
                            <i data-lucide="shield-check"></i>
                            <div class="empty-state__title">No violations</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Occurred</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= e(format_event_type($r['event_type'])) ?></td>
                                        <td><?= e($r['driver_name'] ?? '—') ?></td>
                                        <td><?= e($r['registration_number'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($r['severity'])) ?>">
                                                <?= e(format_label($r['severity'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($r['status'])) ?>">
                                                <?= e(format_label($r['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(date('d M Y H:i', strtotime($r['occurred_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="report-print-header print-only" style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
                <div class="meta">VEHISAFE · Generated <?= date('d M Y, H:i') ?> · Mount Kenya University</div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
