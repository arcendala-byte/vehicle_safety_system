<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    set_flash('error', 'Your account is not linked to a driver profile.');
    header('Location: ' . url('logout.php'));
    exit;
}

$status = (string) ($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$result = list_driver_trips($driverId, [
    'status'   => $status,
    'page'     => $page,
    'per_page' => 10,
]);
$trips = $result['rows'];
$meta  = $result['meta'];

$activeTrip = get_active_trip_for_driver($driverId);

$pageTitle = 'My Trips';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../../includes/partials/sidebar_driver.php'; ?>

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
                    <h1 class="page-title">My Trips</h1>
                    <p class="page-subtitle"><?= (int) $meta['total'] ?> trip<?= $meta['total'] === 1 ? '' : 's' ?> on record</p>
                </div>
                <?php if ($activeTrip): ?>
                    <a href="<?= url('driver/trips/view.php?id=' . (int) $activeTrip['trip_id']) ?>" class="btn btn--danger">
                        <i data-lucide="radio"></i> Resume Active Trip
                    </a>
                <?php else: ?>
                    <a href="<?= url('driver/trips/start.php') ?>" class="btn btn--primary">
                        <i data-lucide="play"></i> Start New Trip
                    </a>
                <?php endif; ?>
            </div>

            <!-- Filter -->
            <form class="filter-bar" method="GET" action="<?= url('driver/trips/index.php') ?>">
                <select name="status" class="form-input filter-bar__select">
                    <option value="">All statuses</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>
                <?php if ($status !== ''): ?>
                    <a href="<?= url('driver/trips/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($trips)): ?>
                        <div class="empty-state">
                            <i data-lucide="navigation"></i>
                            <div class="empty-state__title">No trips found</div>
                            <div class="empty-state__text">
                                <?= $status !== '' ? 'Try a different status filter.' : 'Start your first trip to see it here.' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Trip</th>
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
                                                <span class="text-subtle">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($t['status'])) ?>">
                                                <?= e(format_label($t['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <a href="<?= url('driver/trips/view.php?id=' . (int) $t['trip_id']) ?>"
                                                   class="row-action" title="View">
                                                    <i data-lucide="eye"></i>
                                                </a>
                                                <?php if ($t['status'] === 'active'): ?>
                                                    <a href="<?= url('driver/trips/end.php?id=' . (int) $t['trip_id']) ?>"
                                                       class="row-action row-action--danger" title="End trip">
                                                        <i data-lucide="square"></i>
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

            <?= render_pagination($meta, 'driver/trips/index.php', ['status' => $status]) ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>