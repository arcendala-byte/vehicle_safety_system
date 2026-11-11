<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/vehicle_helpers.php';

require_role('admin');

$vehicleId = (int) ($_GET['id'] ?? 0);
$vehicle   = $vehicleId > 0 ? get_vehicle($vehicleId) : null;

if (!$vehicle) {
    set_flash('error', 'Vehicle not found.');
    header('Location: ' . url('admin/vehicles/index.php'));
    exit;
}

$trips      = get_vehicle_trips($vehicleId, 10);
$violations = get_vehicle_violations($vehicleId, 10);

$user      = current_user();
$pageTitle = $vehicle['registration_number'];
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

            <div class="page-header page-header--row">
                <div>
                    <nav class="breadcrumb">
                        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('admin/vehicles/index.php') ?>">Vehicles</a>
                        <span class="breadcrumb__sep">/</span>
                        <span><?= e($vehicle['registration_number']) ?></span>
                    </nav>
                    <h1 class="page-title"><?= e($vehicle['registration_number']) ?></h1>
                    <p class="page-subtitle">
                        <?= e(trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''))) ?: 'Unspecified vehicle' ?>
                        <?= $vehicle['year'] ? ' · ' . (int) $vehicle['year'] : '' ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <a href="<?= url('admin/vehicles/edit.php?id=' . (int) $vehicle['vehicle_id']) ?>" class="btn">
                        <i data-lucide="pencil"></i> Edit
                    </a>
                    <?php if ($vehicle['status'] !== 'inactive'): ?>
                        <a href="<?= url('admin/vehicles/delete.php?id=' . (int) $vehicle['vehicle_id']) ?>"
                           class="btn btn--danger">
                            <i data-lucide="ban"></i> Deactivate
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Status</div>
                        <div class="kpi__value">
                            <span class="badge <?= e(badge_class($vehicle['status'])) ?>">
                                <?= e(format_label($vehicle['status'])) ?>
                            </span>
                        </div>
                        <div class="kpi__meta">Operational state</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="activity"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Vehicle Type</div>
                        <div class="kpi__value" style="font-size: var(--fs-lg);">
                            <?= e(ucfirst($vehicle['vehicle_type'])) ?>
                        </div>
                        <div class="kpi__meta">Classification</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="truck"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Assigned Driver</div>
                        <div class="kpi__value" style="font-size: var(--fs-lg);">
                            <?= $vehicle['driver_name'] ? e($vehicle['driver_name']) : '—' ?>
                        </div>
                        <div class="kpi__meta">
                            <?= $vehicle['driver_name'] ? 'Score ' . (int) $vehicle['driver_score'] : 'Unassigned' ?>
                        </div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="user-check"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Recent Trips</div>
                        <div class="kpi__value"><?= count($trips) ?></div>
                        <div class="kpi__meta">Last 10 shown</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="navigation"></i></div>
                </div>
            </div>

            <!-- Details + driver -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Vehicle Details</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div><dt>Registration</dt><dd><?= e($vehicle['registration_number']) ?></dd></div>
                            <div><dt>Make</dt><dd><?= e($vehicle['make'] ?: '—') ?></dd></div>
                            <div><dt>Model</dt><dd><?= e($vehicle['model'] ?: '—') ?></dd></div>
                            <div><dt>Year</dt><dd><?= $vehicle['year'] ? (int) $vehicle['year'] : '—' ?></dd></div>
                            <div><dt>Type</dt><dd><?= e(ucfirst($vehicle['vehicle_type'])) ?></dd></div>
                            <div><dt>Registered</dt><dd><?= e(date('d M Y, H:i', strtotime($vehicle['created_at']))) ?></dd></div>
                        </dl>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Assigned Driver</div>
                    </div>
                    <div class="section-card__body">
                        <?php if ($vehicle['driver_name']): ?>
                            <dl class="detail-list">
                                <div><dt>Name</dt>
                                    <dd>
                                        <a href="<?= url('admin/drivers/view.php?id=' . (int) $vehicle['assigned_driver_id']) ?>"
                                           class="link-plain">
                                            <?= e($vehicle['driver_name']) ?>
                                        </a>
                                    </dd>
                                </div>
                                <div><dt>License</dt><dd><?= e($vehicle['driver_license']) ?></dd></div>
                                <div><dt>Safety score</dt>
                                    <dd>
                                        <span class="badge <?= (int) $vehicle['driver_score'] >= 85 ? 'badge--success' : ((int) $vehicle['driver_score'] >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                            <?= (int) $vehicle['driver_score'] ?>
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="user-x"></i>
                                <div class="empty-state__title">No driver assigned</div>
                                <div class="empty-state__text">Edit this vehicle to assign a driver.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Trips -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Recent Trips</div>
                    <div class="section-card__subtitle">Latest 10 journeys using this vehicle</div>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($trips)): ?>
                        <div class="empty-state">
                            <i data-lucide="navigation"></i>
                            <div class="empty-state__title">No trips recorded</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Trip #</th>
                                    <th>Driver</th>
                                    <th>Distance</th>
                                    <th>Violations</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $t): ?>
                                    <tr>
                                        <td>#<?= (int) $t['trip_id'] ?></td>
                                        <td><?= e($t['driver_name'] ?? '—') ?></td>
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

            <!-- Violations -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Recent Violations</div>
                    <div class="section-card__subtitle">Safety events tied to this vehicle</div>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($violations)): ?>
                        <div class="empty-state">
                            <i data-lucide="shield-check"></i>
                            <div class="empty-state__title">No violations on record</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Driver</th>
                                    <th>Severity</th>
                                    <th>Confidence</th>
                                    <th>Status</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($violations as $v): ?>
                                    <tr>
                                        <td><?= e(format_event_type($v['event_type'])) ?></td>
                                        <td><?= e($v['driver_name'] ?? '—') ?></td>
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

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>