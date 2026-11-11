<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);
$tripId   = (int) ($_GET['id'] ?? $_POST['trip_id'] ?? 0);

$trip = $tripId > 0 ? get_trip($tripId) : null;

// Guard: trip exists, belongs to driver, and is active
if (!$trip || (int) $trip['driver_id'] !== $driverId) {
    set_flash('error', 'Trip not found.');
    header('Location: ' . url('driver/trips/index.php'));
    exit;
}
if ($trip['status'] !== 'active') {
    set_flash('info', 'This trip is not active.');
    header('Location: ' . url('driver/trips/view.php?id=' . $tripId));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $result = end_trip($tripId);
        if ($result['ok']) {
            set_flash('success',
                "Trip ended. Distance: {$result['distance_km']} km · "
                . "Violations: {$result['violations']} · "
                . "Safety score: {$result['score']}/100"
            );
            header('Location: ' . url('driver/trips/view.php?id=' . $tripId));
            exit;
        }
        $error = $result['message'];
    }
}

// Pre-fetch summary for the confirmation page
$gpsPoints  = get_trip_gps_points($tripId, 2000);
$violations = get_trip_violations($tripId);
$distancePreview = 0.0;
for ($i = 1; $i < count($gpsPoints); $i++) {
    $distancePreview += haversine_km(
        (float) $gpsPoints[$i - 1]['latitude'], (float) $gpsPoints[$i - 1]['longitude'],
        (float) $gpsPoints[$i]['latitude'],     (float) $gpsPoints[$i]['longitude']
    );
}

$pageTitle = 'End Trip #' . $tripId;
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

            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="<?= url('driver/dashboard.php') ?>">Dashboard</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('driver/trips/index.php') ?>">My Trips</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('driver/trips/view.php?id=' . $tripId) ?>">#<?= (int) $tripId ?></a>
                    <span class="breadcrumb__sep">/</span>
                    <span>End</span>
                </nav>
            </div>

            <div class="confirm-card">
                <div class="confirm-card__icon" style="background: var(--color-warning-bg); color: var(--color-warning);">
                    <i data-lucide="square"></i>
                </div>
                <h1 class="confirm-card__title">End this trip?</h1>
                <p class="confirm-card__text">
                    Your trip in <strong><?= e($trip['registration_number']) ?></strong>
                    started <?= e(date('d M Y, H:i', strtotime($trip['start_time']))) ?> will be
                    finalized. A safety score will be calculated based on violations detected.
                </p>

                <div class="stat-strip" style="margin: var(--space-6) 0; text-align: left;">
                    <div class="stat-strip__item">
                        <div class="stat-strip__label">Duration</div>
                        <div class="stat-strip__value"><?= e(format_duration($trip['start_time'], date('Y-m-d H:i:s'))) ?></div>
                    </div>
                    <div class="stat-strip__item">
                        <div class="stat-strip__label">Distance (est.)</div>
                        <div class="stat-strip__value"><?= number_format($distancePreview, 2) ?> km</div>
                    </div>
                    <div class="stat-strip__item">
                        <div class="stat-strip__label">Violations</div>
                        <div class="stat-strip__value"><?= count($violations) ?></div>
                    </div>
                    <div class="stat-strip__item">
                        <div class="stat-strip__label">GPS Points</div>
                        <div class="stat-strip__value"><?= count($gpsPoints) ?></div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert--danger">
                        <i data-lucide="alert-circle"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('driver/trips/end.php?id=' . $tripId) ?>" class="confirm-card__actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="trip_id" value="<?= (int) $tripId ?>">

                    <a href="<?= url('driver/trips/view.php?id=' . $tripId) ?>" class="btn btn--ghost">
                        <i data-lucide="arrow-left"></i> Keep Driving
                    </a>
                    <button type="submit" class="btn btn--danger">
                        <i data-lucide="square"></i> End Trip &amp; Calculate Score
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>