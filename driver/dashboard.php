<?php
/**
 * ============================================================
 * DRIVER DASHBOARD — with live active-trip banner
 * ============================================================
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_queries.php';
require_once __DIR__ . '/../includes/trip_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    set_flash('error', 'Your account is not linked to a driver profile. Contact an administrator.');
    header('Location: ' . url('logout.php'));
    exit;
}

// Driver profile
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM drivers WHERE driver_id = :id LIMIT 1");
$stmt->execute([':id' => $driverId]);
$driver = $stmt->fetch();

// Active trip?
$activeTrip = get_active_trip_for_driver($driverId);

// Assigned vehicles
$stmt = $pdo->prepare("SELECT vehicle_id, registration_number, make, model, status
                       FROM vehicles WHERE assigned_driver_id = :id AND status = 'active'
                       ORDER BY registration_number ASC");
$stmt->execute([':id' => $driverId]);
$vehicles = $stmt->fetchAll();

// Recent trips
$tripsResult = list_driver_trips($driverId, ['per_page' => 5]);
$recentTrips = $tripsResult['rows'];

// Recent violations
$stmt = $pdo->prepare(
    "SELECT violation_id, event_type, severity, confidence, status, occurred_at
     FROM driver_violations
     WHERE driver_id = :id
     ORDER BY occurred_at DESC
     LIMIT 5"
);
$stmt->execute([':id' => $driverId]);
$recentViolations = $stmt->fetchAll();

// Quick stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM driver_violations WHERE driver_id = :id");
$stmt->execute([':id' => $driverId]);
$totalViolations = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE driver_id = :id AND status = 'completed'");
$stmt->execute([':id' => $driverId]);
$completedTrips = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(distance_km),0) FROM trips WHERE driver_id = :id AND status = 'completed'");
$stmt->execute([':id' => $driverId]);
$totalDistance = (float) $stmt->fetchColumn();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-shell app-shell--driver-dashboard">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_driver.php'; ?>

    <div class="app-main app-main--driver-dashboard">
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
                <h1 class="page-title">Karibu, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="page-subtitle">Your driving safety overview and active trip status.</p>
            </div>

            <!-- ACTIVE TRIP BANNER -->
            <?php if ($activeTrip): ?>
                <div class="active-trip-banner">
                    <div class="active-trip-banner__icon">
                        <i data-lucide="radio"></i>
                    </div>
                    <div class="active-trip-banner__text">
                        <div class="active-trip-banner__title">
                            Active trip in progress — <?= e($activeTrip['registration_number']) ?>
                        </div>
                        <div class="active-trip-banner__meta">
                            Started <?= e(date('d M Y, H:i', strtotime($activeTrip['start_time']))) ?>
                            · Trip #<?= (int) $activeTrip['trip_id'] ?>
                        </div>
                    </div>
                    <a href="<?= url('driver/trips/view.php?id=' . (int) $activeTrip['trip_id']) ?>" class="btn btn--ghost btn--sm">
                        <i data-lucide="eye"></i> View
                    </a>
                    <a href="<?= url('driver/trips/end.php?id=' . (int) $activeTrip['trip_id']) ?>" class="btn btn--danger btn--sm">
                        <i data-lucide="square"></i> End Trip
                    </a>
                </div>
            <?php elseif (!empty($vehicles)): ?>
                <div class="active-trip-banner" style="background: linear-gradient(135deg, #f9fafb, #f1f5f9); border-color: var(--color-border);">
                    <div class="active-trip-banner__icon" style="background: var(--color-text-muted); animation: none;">
                        <i data-lucide="pause-circle"></i>
                    </div>
                    <div class="active-trip-banner__text">
                        <div class="active-trip-banner__title">No active trip</div>
                        <div class="active-trip-banner__meta">Start a journey to begin monitoring.</div>
                    </div>
                    <a href="<?= url('driver/trips/start.php') ?>" class="btn btn--primary btn--sm">
                        <i data-lucide="play"></i> Start Trip
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert--warning">
                    <i data-lucide="alert-triangle"></i>
                    <span>No active vehicles are assigned to you. Contact an administrator to assign a vehicle before starting a trip.</span>
                </div>
            <?php endif; ?>

            <!-- KPIs -->
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
                        <div class="kpi__label">Completed Trips</div>
                        <div class="kpi__value"><?= $completedTrips ?></div>
                        <div class="kpi__meta">All-time</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="check-circle-2"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Distance</div>
                        <div class="kpi__value"><?= number_format($totalDistance, 1) ?><span class="kpi__unit">km</span></div>
                        <div class="kpi__meta">Across completed trips</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="route"></i></div>
                </div>

                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Violations</div>
                        <div class="kpi__value"><?= $totalViolations ?></div>
                        <div class="kpi__meta">All-time</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
            </div>

            <!-- Recent Trips + Violations -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Recent Trips</div>
                            <div class="section-card__subtitle">Your last 5 journeys</div>
                        </div>
                        <a href="<?= url('driver/trips/index.php') ?>" class="btn btn--sm">
                            View all <i data-lucide="arrow-right"></i>
                        </a>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($recentTrips)): ?>
                            <div class="empty-state">
                                <i data-lucide="navigation"></i>
                                <div class="empty-state__title">No trips yet</div>
                                <div class="empty-state__text">Start your first trip to see it here.</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Trip</th><th>Vehicle</th><th>Distance</th><th>Score</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTrips as $t): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= url('driver/trips/view.php?id=' . (int) $t['trip_id']) ?>" class="link-plain">
                                                    #<?= (int) $t['trip_id'] ?>
                                                </a>
                                                <div class="cell-secondary">
                                                    <?= e(date('d M, H:i', strtotime($t['start_time']))) ?>
                                                </div>
                                            </td>
                                            <td><?= e($t['registration_number']) ?></td>
                                            <td><?= number_format((float) $t['distance_km'], 1) ?> km</td>
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
                            <div class="section-card__title">Recent Violations</div>
                            <div class="section-card__subtitle">Your last 5 events</div>
                        </div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($recentViolations)): ?>
                            <div class="empty-state">
                                <i data-lucide="shield-check"></i>
                                <div class="empty-state__title">Clean record</div>
                                <div class="empty-state__text">No violations detected. Keep it up!</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Type</th><th>Severity</th><th>Status</th><th>When</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentViolations as $v): ?>
                                        <tr>
                                            <td><?= e(format_event_type($v['event_type'])) ?></td>
                                            <td>
                                                <span class="badge <?= e(badge_class($v['severity'])) ?>">
                                                    <?= e(format_label($v['severity'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= e(badge_class($v['status'])) ?>">
                                                    <?= e(format_label($v['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= e(date('d M, H:i', strtotime($v['occurred_at']))) ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
