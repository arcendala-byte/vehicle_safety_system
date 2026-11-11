<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_queries.php';
require_once __DIR__ . '/../includes/trip_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);
$activeTrip = get_active_trip_for_driver($driverId);

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT vehicle_id, registration_number FROM vehicles
     WHERE assigned_driver_id = :id AND status = 'active'
     ORDER BY vehicle_id ASC LIMIT 1"
);
$stmt->execute([':id' => $driverId]);
$vehicle = $stmt->fetch();

$pageTitle = 'Live Monitoring';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_driver.php'; ?>

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
            <?php require_once __DIR__ . '/../includes/flash.php'; ?>

            <div class="page-header">
                <h1 class="page-title">Live Driver Monitoring</h1>
                <p class="page-subtitle">
                    Real-time AI on your webcam — 4 detectors running in parallel.
                    <?php if ($activeTrip): ?>
                        Trip #<?= (int) $activeTrip['trip_id'] ?> is active — detections will be logged.
                    <?php else: ?>
                        Start a trip from your dashboard for detections to be logged as violations.
                    <?php endif; ?>
                </p>
            </div>

            <div class="live-monitor" id="liveMonitor"
                 data-api-url="<?= url('api/monitoring/detect.php') ?>"
                 data-trip-id="<?= $activeTrip ? (int) $activeTrip['trip_id'] : 0 ?>"
                 data-interval="800"
                 data-test-image-url="<?= asset('images/test-driver.jpg') ?>">

                <div class="live-grid">

                    <div class="live-camera-panel">
                        <div class="live-camera-wrapper">
                            <video id="liveVideo" class="live-camera" autoplay muted playsinline></video>
                            <canvas id="liveCaptureCanvas" style="display:none;"></canvas>
                            <div class="live-rec" id="liveStatus">Ready</div>
                        </div>

                        <div class="live-controls">
                            <button type="button" class="btn btn--primary" id="liveStartBtn">
                                <i data-lucide="play"></i> Start Monitoring
                            </button>
                            <button type="button" class="btn btn--ghost" id="liveStopBtn">
                                <i data-lucide="square"></i> Stop
                            </button>
                            <button type="button" class="btn btn--ghost" id="liveSimBtn">
                                <i data-lucide="image"></i> Simulate Frame
                            </button>
                        </div>

                        <div class="live-stats">
                            <div class="live-stat">
                                <span class="live-stat__label">Frames</span>
                                <span class="live-stat__value" id="liveFrameCount">0</span>
                            </div>
                            <div class="live-stat">
                                <span class="live-stat__label">FPS</span>
                                <span class="live-stat__value" id="liveFps">—</span>
                            </div>
                            <div class="live-stat">
                                <span class="live-stat__label">Trip</span>
                                <span class="live-stat__value">
                                    <?= $activeTrip ? '#' . (int) $activeTrip['trip_id'] : 'None' ?>
                                </span>
                            </div>
                            <div class="live-stat">
                                <span class="live-stat__label">Vehicle</span>
                                <span class="live-stat__value">
                                    <?= $vehicle ? e($vehicle['registration_number']) : '—' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="live-results-panel">
                        <div class="section-card" style="margin-top: 0;">
                            <div class="section-card__header">
                                <div class="section-card__title">Live Detection</div>
                            </div>
                            <div class="section-card__body">
                                <div id="liveResults" class="live-results">
                                    <div class="empty-state">
                                        <i data-lucide="camera"></i>
                                        <div class="empty-state__title">Waiting for first frame</div>
                                        <div class="empty-state__text">Click Start Monitoring above.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-card" style="margin-top: var(--space-4);">
                            <div class="section-card__header">
                                <div class="section-card__title">Recent Events</div>
                            </div>
                            <div class="section-card__body">
                                <div id="liveEvents" class="live-events">
                                    <div class="empty-state">
                                        <i data-lucide="list"></i>
                                        <div class="empty-state__title">No events yet</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
</div>

<script>window.__VEHISAFE_AUDIO_BASE__ = '<?= asset('audio') ?>/';</script>
<script src="<?= asset('js/alert-manager.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<script src="<?= asset('js/live-monitor.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<button type="button" class="alert-test-fab btn btn--primary"
        onclick="window.VehiSafeAlerts.test()"
        title="Test the alert system">
    <i data-lucide="bell"></i> Test Alert
</button>

<script>
    if (window.lucide) lucide.createIcons();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
