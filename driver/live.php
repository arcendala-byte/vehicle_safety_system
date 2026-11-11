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

// Assigned vehicle
$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT vehicle_id, registration_number FROM vehicles
     WHERE assigned_driver_id = :id AND status = 'active'
     ORDER BY vehicle_id ASC LIMIT 1"
);
$stmt->execute([':id' => $driverId]);
$vehicle = $stmt->fetch();

$pageTitle = 'Live Dashboard';
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
                <h1 class="page-title">Live Dashboard</h1>
                <p class="page-subtitle">
                    Real-time driver safety monitoring — camera, GPS, alerts.
                    <?php if ($activeTrip): ?>
                        Trip #<?= (int) $activeTrip['trip_id'] ?> active
                    <?php else: ?>
                        <a href="<?= url('driver/trips/start.php') ?>" class="link-plain">Start a trip →</a>
                    <?php endif; ?>
                </p>
            </div>

            <div id="liveDashboard"
                 data-api-detect="<?= url('api/monitoring/detect.php') ?>"
                 data-api-snapshot="<?= url('api/monitoring/snapshot.php') ?>"
                 data-api-gps="<?= url('api/gps/update.php') ?>"
                 data-api-blackspot="<?= url('api/blackspots/predict.php') ?>"
                 data-trip-id="<?= $activeTrip ? (int) $activeTrip['trip_id'] : 0 ?>"
                 data-mode="<?= is_demo_mode() ? 'simulated' : 'real' ?>"
                 data-test-image-url="<?= asset('images/test-driver.jpg') ?>">

                <div class="dashboard-grid">

                    <!-- CAMERA -->
                    <div class="dash-card dash-card--camera">
                        <div class="dash-card__header">
                            <div class="dash-card__title">
                                <i data-lucide="video"></i> Live Camera
                            </div>
                            <span class="dash-badge dash-badge--live" id="dashCameraStatus">OFFLINE</span>
                        </div>
                        <div class="dash-camera">
                            <video id="dashVideo" autoplay muted playsinline></video>
                            <canvas id="dashCanvas" style="display:none;"></canvas>
                            <div class="dash-camera__controls">
                                <button type="button" class="dash-btn" id="dashStartBtn">
                                    <i data-lucide="play"></i> Start
                                </button>
                                <button type="button" class="dash-btn" id="dashStopBtn">
                                    <i data-lucide="square"></i> Stop
                                </button>
                                <button type="button" class="dash-btn" id="dashSimBtn">
                                    <i data-lucide="image"></i> Test Frame
                                </button>
                            </div>
                            <div class="dash-camera__overlay">
                                <div class="dash-camera__stat">
                                    <span class="dash-stat-label">FPS</span>
                                    <span class="dash-stat-value" id="dashFps">—</span>
                                </div>
                                <div class="dash-camera__stat">
                                    <span class="dash-stat-label">Frames</span>
                                    <span class="dash-stat-value" id="dashFrames">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETECTION STATUS -->
                    <div class="dash-card">
                        <div class="dash-card__header">
                            <div class="dash-card__title">
                                <i data-lucide="cpu"></i> Detection Status
                            </div>
                        </div>
                        <div class="dash-card__body">
                            <div class="dash-detections" id="dashDetections">
                                <div class="dash-detection">
                                    <span class="dash-detection__name">Seatbelt</span>
                                    <span class="dash-detection__badge" id="detSeatbelt">—</span>
                                </div>
                                <div class="dash-detection">
                                    <span class="dash-detection__name">Phone</span>
                                    <span class="dash-detection__badge" id="detPhone">—</span>
                                </div>
                                <div class="dash-detection">
                                    <span class="dash-detection__name">Drowsiness</span>
                                    <span class="dash-detection__badge" id="detDrowsiness">—</span>
                                </div>
                                <div class="dash-detection">
                                    <span class="dash-detection__name">Distraction</span>
                                    <span class="dash-detection__badge" id="detDistraction">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GPS -->
                    <div class="dash-card">
                        <div class="dash-card__header">
                            <div class="dash-card__title">
                                <i data-lucide="map-pin"></i> Location
                            </div>
                        </div>
                        <div class="dash-card__body">
                            <div class="dash-gps" id="dashGps">
                                <div class="dash-gps__row">
                                    <span class="dash-gps__label">Latitude</span>
                                    <span class="dash-gps__value" id="gpsLat">—</span>
                                </div>
                                <div class="dash-gps__row">
                                    <span class="dash-gps__label">Longitude</span>
                                    <span class="dash-gps__value" id="gpsLng">—</span>
                                </div>
                                <div class="dash-gps__row">
                                    <span class="dash-gps__label">Speed</span>
                                    <span class="dash-gps__value" id="gpsSpeed">—</span>
                                </div>
                                <div class="dash-gps__row">
                                    <span class="dash-gps__label">GPS Points</span>
                                    <span class="dash-gps__value" id="gpsCount">0</span>
                                </div>
                            </div>
                            <div id="dashNearby" class="dash-nearby">
                                <div class="text-subtle" style="font-size: 12px;">Loading black spots…</div>
                            </div>
                        </div>
                    </div>

                    <!-- SCORE -->
                    <div class="dash-card">
                        <div class="dash-card__header">
                            <div class="dash-card__title">
                                <i data-lucide="gauge"></i> Safety Score
                            </div>
                        </div>
                        <div class="dash-card__body">
                            <div class="dash-score">
                                <div class="dash-score__ring" id="scoreRing" style="--pct: 0;">
                                    <div class="dash-score__value" id="scoreValue">—</div>
                                </div>
                                <div class="dash-score__meta">
                                    <div><strong id="scoreViolations">0</strong> violations</div>
                                    <div class="text-subtle" style="font-size: 12px;" id="scoreTrip">
                                        No active trip
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IGNITION (Arduino prototype) -->
                    <div class="dash-card">
                        <div class="dash-card__header">
                            <div class="dash-card__title">
                                <i data-lucide="key"></i> Ignition
                            </div>
                            <span class="dash-badge dash-badge--neutral" id="dashIgnitionMode">SIMULATION</span>
                        </div>
                        <div class="dash-card__body">
                            <div class="dash-ignition" id="dashIgnition">
                                <div class="dash-ignition__ring dash-ignition__ring--off" id="ignitionRing">
                                    <div class="dash-ignition__icon">
                                        <i data-lucide="lock"></i>
                                    </div>
                                </div>
                                <div class="dash-ignition__text">
                                    <div class="dash-ignition__status dash-ignition__status--off" id="ignitionStatus">LOCKED</div>
                                    <div class="dash-ignition__desc" id="ignitionDesc">
                                        Seatbelt not fastened — engine disabled
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- EVENTS -->
                <div class="dash-card" style="margin-top: var(--space-5);">
                    <div class="dash-card__header">
                        <div class="dash-card__title">
                            <i data-lucide="list"></i> Recent Events
                        </div>
                        <button type="button" class="dash-btn" id="dashRefreshBtn">
                            <i data-lucide="refresh-cw"></i> Refresh
                        </button>
                    </div>
                    <div class="dash-card__body">
                        <div id="dashEvents" class="dash-events">
                            <div class="empty-state" style="padding: 24px;">
                                <div class="empty-state__title">No events yet</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
window.__VEHISAFE_AUDIO_BASE__ = '<?= asset('audio') ?>/';
window.__VEHISAFE_AI_URL__ = '<?= AI_SERVICE_URL ?>';
window.__VEHISAFE_AI_KEY__ = '<?= AI_API_KEY ?>';
</script>
<script src="<?= asset('js/alert-manager.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<script src="<?= asset('js/live-dashboard.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>