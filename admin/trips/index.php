<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('admin');

$driverId  = (int)    ($_GET['driver_id']  ?? 0);
$vehicleId = (int)    ($_GET['vehicle_id'] ?? 0);
$status    = (string) ($_GET['status']     ?? '');
$dateFrom  = (string) ($_GET['date_from']  ?? '');
$dateTo    = (string) ($_GET['date_to']    ?? '');
$sort      = (string) ($_GET['sort']       ?? 'started');
$dir       = (string) ($_GET['dir']        ?? 'desc');
$page      = max(1, (int) ($_GET['page']   ?? 1));

$result = list_trips([
    'driver_id'  => $driverId,
    'vehicle_id' => $vehicleId,
    'status'     => $status,
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'sort'       => $sort,
    'dir'        => $dir,
    'page'       => $page,
    'per_page'   => 12,
]);

$trips = $result['rows'];
$meta  = $result['meta'];

// Quick KPIs
$pdo = Database::getConnection();
$kpiActive    = (int) $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'active'")->fetchColumn();
$kpiCompleted = (int) $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'completed'")->fetchColumn();
$kpiTotalKm   = (float) $pdo->query("SELECT COALESCE(SUM(distance_km),0) FROM trips WHERE status = 'completed'")->fetchColumn();
$kpiAvgScore  = (float) $pdo->query("SELECT COALESCE(AVG(safety_score),0) FROM trips WHERE status = 'completed' AND safety_score IS NOT NULL")->fetchColumn();

$drivers  = list_drivers_minimal();
$vehicles = list_vehicles_minimal();

$user      = current_user();
$pageTitle = 'Trips';
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
                    <h1 class="page-title">Trips</h1>
                    <p class="page-subtitle"><?= (int) $meta['total'] ?> trip<?= $meta['total'] === 1 ? '' : 's' ?> match your filters</p>
                </div>
            </div>

            <!-- Trip KPIs -->
            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Active Trips</div>
                        <div class="kpi__value"><?= $kpiActive ?></div>
                        <div class="kpi__meta">Currently in progress</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="radio"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Completed Trips</div>
                        <div class="kpi__value"><?= $kpiCompleted ?></div>
                        <div class="kpi__meta">All-time</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="check-circle-2"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Distance</div>
                        <div class="kpi__value"><?= number_format($kpiTotalKm, 1) ?><span class="kpi__unit">km</span></div>
                        <div class="kpi__meta">Completed trips</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="route"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Average Score</div>
                        <div class="kpi__value"><?= number_format($kpiAvgScore, 1) ?><span class="kpi__unit">/100</span></div>
                        <div class="kpi__meta">Completed trips</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="gauge"></i></div>
                </div>
            </div>

            <!-- Filter bar -->
            <form class="filter-bar" method="GET" action="<?= url('admin/trips/index.php') ?>">
                <select name="driver_id" class="form-input filter-bar__select">
                    <option value="">All drivers</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= (int) $d['driver_id'] ?>" <?= $driverId === (int) $d['driver_id'] ? 'selected' : '' ?>>
                            <?= e($d['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="vehicle_id" class="form-input filter-bar__select">
                    <option value="">All vehicles</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int) $v['vehicle_id'] ?>" <?= $vehicleId === (int) $v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= e($v['registration_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-input filter-bar__select">
                    <option value="">All statuses</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <input type="date" name="date_from" class="form-input filter-bar__select"
                       value="<?= e($dateFrom) ?>" placeholder="From">
                <input type="date" name="date_to" class="form-input filter-bar__select"
                       value="<?= e($dateTo) ?>" placeholder="To">

                <button type="submit" class="btn">
                    <i data-lucide="filter"></i> Apply
                </button>

                <?php if ($driverId || $vehicleId || $status || $dateFrom || $dateTo): ?>
                    <a href="<?= url('admin/trips/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Trips table -->
            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($trips)): ?>
                        <div class="empty-state">
                            <i data-lucide="navigation"></i>
                            <div class="empty-state__title">No trips found</div>
                            <div class="empty-state__text">
                                <?= ($driverId || $vehicleId || $status || $dateFrom || $dateTo)
                                    ? 'Try adjusting your filters.'
                                    : 'Trips will appear here as drivers start journeys.' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Trip</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Start</th>
                                    <th>Duration</th>
                                    <th>Distance</th>
                                    <th>Violations</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $t): ?>
                                    <tr>
                                        <td><strong>#<?= (int) $t['trip_id'] ?></strong></td>
                                        <td><?= e($t['driver_name']) ?></td>
                                        <td><?= e($t['registration_number']) ?></td>
                                        <td><?= e(date('d M, H:i', strtotime($t['start_time']))) ?></td>
                                        <td><?= e(format_duration($t['start_time'], $t['end_time'])) ?></td>
                                        <td><?= number_format((float) $t['distance_km'], 1) ?> km</td>
                                        <td><?= (int) $t['violation_count'] ?></td>
                                        <td>
                                            <?php if ($t['safety_score'] !== null): ?>
                                                <?php $sc = (int) $t['safety_score']; ?>
                                                <span class="badge <?= $sc >= 85 ? 'badge--success' : ($sc >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                                    <?= $sc ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-subtle">â€”</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($t['status'])) ?>">
                                                <?= e(format_label($t['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <a href="<?= url('admin/trips/view.php?id=' . (int) $t['trip_id']) ?>"
                                                   class="row-action" title="View">
                                                    <i data-lucide="eye"></i>
                                                </a>
                                                <?php if ($t['status'] === 'active'): ?>
                                                    <a href="<?= url('admin/trips/cancel.php?id=' . (int) $t['trip_id']) ?>"
                                                       class="row-action" title="Cancel trip">
                                                        <i data-lucide="square"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= url('admin/trips/delete.php?id=' . (int) $t['trip_id']) ?>"
                                                       class="row-action row-action--danger"  title="Delete" data-confirm="Are you sure you want to delete this item?">
                                                        <i data-lucide="trash-2"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?= render_pagination($meta, 'admin/trips/index.php', [
                'driver_id'  => $driverId,
                'vehicle_id' => $vehicleId,
                'status'     => $status,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'sort'       => $sort,
                'dir'        => $dir,
            ]) ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>