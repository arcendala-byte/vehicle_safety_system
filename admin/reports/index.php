<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$summary = report_system_summary();
$user      = current_user();
$pageTitle = 'Reports & Analytics';
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

            <div class="page-header">
                <h1 class="page-title">Reports & Analytics</h1>
                <p class="page-subtitle">
                    Generate detailed reports for road safety analysis and compliance.
                    Click any report below to explore.
                </p>
            </div>

            <!-- System summary KPIs -->
            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Drivers</div>
                        <div class="kpi__value"><?= $summary['total_drivers'] ?></div>
                        <div class="kpi__meta">Active</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="users"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Completed Trips</div>
                        <div class="kpi__value"><?= $summary['completed_trips'] ?></div>
                        <div class="kpi__meta"><?= $summary['active_trips'] ?> active</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="navigation"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Violations</div>
                        <div class="kpi__value"><?= $summary['total_violations'] ?></div>
                        <div class="kpi__meta"><?= $summary['critical_violations'] ?> critical</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Distance</div>
                        <div class="kpi__value"><?= number_format($summary['total_km'], 0) ?><span class="kpi__unit">km</span></div>
                        <div class="kpi__meta">Fleet-wide</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="route"></i></div>
                </div>
            </div>

            <div class="section-divider">
                <span>Available Reports</span>
            </div>

            <!-- Report cards -->
            <div class="grid grid--kpis report-grid">

                <a href="<?= url('admin/reports/driver-safety.php') ?>" class="report-card">
                    <div class="report-card__icon report-card__icon--accent">
                        <i data-lucide="user-check"></i>
                    </div>
                    <div class="report-card__title">Driver Safety</div>
                    <div class="report-card__desc">
                        Per-driver scores, violations, and trip history with date filters.
                    </div>
                    <div class="report-card__action">
                        View Report <i data-lucide="arrow-right"></i>
                    </div>
                </a>

                <a href="<?= url('admin/reports/violations.php') ?>" class="report-card">
                    <div class="report-card__icon report-card__icon--danger">
                        <i data-lucide="alert-triangle"></i>
                    </div>
                    <div class="report-card__title">Violations</div>
                    <div class="report-card__desc">
                        Full violation log with severity breakdown and date filters.
                    </div>
                    <div class="report-card__action">
                        View Report <i data-lucide="arrow-right"></i>
                    </div>
                </a>

                <a href="<?= url('admin/reports/trips.php') ?>" class="report-card">
                    <div class="report-card__icon report-card__icon--info">
                        <i data-lucide="navigation"></i>
                    </div>
                    <div class="report-card__title">Trips</div>
                    <div class="report-card__desc">
                        Trip statistics and fleet utilization across vehicles.
                    </div>
                    <div class="report-card__action">
                        View Report <i data-lucide="arrow-right"></i>
                    </div>
                </a>

                <a href="<?= url('admin/reports/blackspots.php') ?>" class="report-card">
                    <div class="report-card__icon report-card__icon--warning">
                        <i data-lucide="map-pin"></i>
                    </div>
                    <div class="report-card__title">Black Spots</div>
                    <div class="report-card__desc">
                        Ranked risk locations with accident counts and conditions.
                    </div>
                    <div class="report-card__action">
                        View Report <i data-lucide="arrow-right"></i>
                    </div>
                </a>

                <a href="<?= url('admin/reports/analytics.php') ?>" class="report-card">
                    <div class="report-card__icon report-card__icon--success">
                        <i data-lucide="bar-chart-3"></i>
                    </div>
                    <div class="report-card__title">Analytics</div>
                    <div class="report-card__desc">
                        Interactive charts showing trends over the last 30 days.
                    </div>
                    <div class="report-card__action">
                        View Report <i data-lucide="arrow-right"></i>
                    </div>
                </a>

            </div>

            <!-- Export info banner -->
            <div class="alert alert--info" style="margin-top: var(--space-5);">
                <i data-lucide="download"></i>
                <span>
                    <strong>Export any report</strong> as CSV by clicking the <strong>Export CSV</strong> button on the report page.
                    Print-friendly views are available via the <strong>Print</strong> button.
                </span>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>