<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/driver_helpers.php';

require_role('admin');

$driverId = (int) ($_GET['id'] ?? 0);
$driver   = $driverId > 0 ? get_driver($driverId) : null;

if (!$driver) {
    set_flash('error', 'Driver not found.');
    header('Location: ' . url('admin/drivers/index.php'));
    exit;
}

$vehicles   = get_driver_vehicles($driverId);
$violations = get_driver_violations($driverId, 10);
$trips      = get_driver_trips($driverId, 10);

$user      = current_user();
$pageTitle = $driver['full_name'];
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
                        <a href="<?= url('admin/drivers/index.php') ?>">Drivers</a>
                        <span class="breadcrumb__sep">/</span>
                        <span><?= e($driver['full_name']) ?></span>
                    </nav>
                    <h1 class="page-title"><?= e($driver['full_name']) ?></h1>
                    <p class="page-subtitle">
                        License <?= e($driver['license_number']) ?>
                        &middot; Driver ID #<?= (int) $driver['driver_id'] ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <a href="<?= url('admin/drivers/edit.php?id=' . (int) $driver['driver_id']) ?>" class="btn">
                        <i data-lucide="pencil"></i> Edit
                    </a>
                    <?php if ($driver['status'] !== 'inactive'): ?>
                        <a href="<?= url('admin/drivers/delete.php?id=' . (int) $driver['driver_id']) ?>"
                           class="btn btn--danger">
                            <i data-lucide="user-x"></i> Deactivate
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile summary cards -->
            <div class="grid grid--kpis">

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Safety Score</div>
                        <div class="kpi__value"><?= (int) $driver['current_score'] ?><span class="kpi__unit">/100</span></div>
                        <div class="kpi__meta">Current rating</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="gauge"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Status</div>
                        <div class="kpi__value">
                            <span class="badge <?= e(badge_class($driver['status'])) ?>">
                                <?= e(format_label($driver['status'])) ?>
                            </span>
                        </div>
                        <div class="kpi__meta">Account state</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="activity"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Assigned Vehicles</div>
                        <div class="kpi__value"><?= count($vehicles) ?></div>
                        <div class="kpi__meta">Active fleet</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="car"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Violations</div>
                        <div class="kpi__value"><?= count($violations) ?></div>
                        <div class="kpi__meta">Recent 10 shown</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>

            </div>

            <!-- Details + contact -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Profile Details</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div>
                                <dt>Full name</dt>
                                <dd><?= e($driver['full_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Phone</dt>
                                <dd><?= e($driver['phone'] ?: '—') ?></dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd><?= e($driver['email'] ?: '—') ?></dd>
                            </div>
                            <div>
                                <dt>License number</dt>
                                <dd><?= e($driver['license_number']) ?></dd>
                            </div>
                            <div>
                                <dt>License expiry</dt>
                                <dd>
                                    <?= $driver['license_expiry']
                                        ? e(date('d M Y', strtotime($driver['license_expiry'])))
                                        : '—' ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Linked user account</dt>
                                <dd>
                                    <?php if ($driver['user_id']): ?>
                                        <code><?= e($driver['username'] ?? '—') ?></code>
                                    <?php else: ?>
                                        <span class="text-subtle">No login account</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Registered</dt>
                                <dd><?= e(date('d M Y, H:i', strtotime($driver['created_at']))) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Assigned Vehicles</div>
                        <div class="section-card__subtitle"><?= count($vehicles) ?> vehicle<?= count($vehicles) === 1 ? '' : 's' ?></div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($vehicles)): ?>
                            <div class="empty-state">
                                <i data-lucide="car"></i>
                                <div class="empty-state__title">No vehicles assigned</div>
                                <div class="empty-state__text">Assign a vehicle from the Vehicles module.</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Reg No</th><th>Vehicle</th><th>Type</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $v): ?>
                                        <tr>
                                            <td><strong><?= e($v['registration_number']) ?></strong></td>
                                            <td><?= e(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''))) ?: '—' ?></td>
                                            <td><?= e(ucfirst($v['vehicle_type'])) ?></td>
                                            <td>
                                                <span class="badge <?= e(badge_class($v['status'])) ?>">
                                                    <?= e(format_label($v['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Violations -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Recent Violations</div>
                    <div class="section-card__subtitle">Latest 10 events</div>
                </div>
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($violations)): ?>
                        <div class="empty-state">
                            <i data-lucide="shield-check"></i>
                            <div class="empty-state__title">No violations on record</div>
                            <div class="empty-state__text">This driver has a clean safety history.</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Vehicle</th>
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

            <!-- Trips -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Recent Trips</div>
                    <div class="section-card__subtitle">Latest 10 journeys</div>
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
                                    <th>Vehicle</th>
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
                                        <td><?= e($t['registration_number'] ?? '—') ?></td>
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

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>