<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);
$tripId   = (int) ($_GET['id'] ?? 0);

$trip = $tripId > 0 ? get_trip($tripId) : null;

// Guard: trip exists AND belongs to this driver
if (!$trip || (int) $trip['driver_id'] !== $driverId) {
    set_flash('error', 'Trip not found.');
    header('Location: ' . url('driver/trips/index.php'));
    exit;
}

$gpsPoints  = get_trip_gps_points($tripId, 500);
$violations = get_trip_violations($tripId);

$mapPayload = [
    'gps' => array_map(fn($p) => [
        'lat' => (float) $p['latitude'],
        'lng' => (float) $p['longitude'],
        'spd' => (float) ($p['speed_kmh'] ?? 0),
        't'   => $p['recorded_at'],
    ], $gpsPoints),
    'violations' => array_map(fn($v) => [
        'lat'  => (float) $v['latitude'],
        'lng'  => (float) $v['longitude'],
        'type' => $v['event_type'],
        'sev'  => $v['severity'],
        'desc' => $v['description'],
        't'    => $v['occurred_at'],
    ], array_filter($violations, fn($v) => $v['latitude'] !== null && $v['longitude'] !== null)),
    'start' => ['lat' => (float) $trip['start_latitude'], 'lng' => (float) $trip['start_longitude']],
    'end'   => $trip['end_latitude'] !== null
                ? ['lat' => (float) $trip['end_latitude'], 'lng' => (float) $trip['end_longitude']]
                : null,
];

$pageTitle = 'Trip #' . $tripId;
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
                    <nav class="breadcrumb">
                        <a href="<?= url('driver/dashboard.php') ?>">Dashboard</a>
                        <span class="breadcrumb__sep">/</span>
                        <a href="<?= url('driver/trips/index.php') ?>">My Trips</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>#<?= (int) $tripId ?></span>
                    </nav>
                    <h1 class="page-title">Trip #<?= (int) $tripId ?></h1>
                    <p class="page-subtitle">
                        <?= e($trip['registration_number']) ?> &middot;
                        <?= e(date('d M Y, H:i', strtotime($trip['start_time']))) ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <?php if ($trip['status'] === 'active'): ?>
                        <a href="<?= url('driver/trips/end.php?id=' . $tripId) ?>" class="btn btn--danger">
                            <i data-lucide="square"></i> End Trip
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Status</div>
                    <div class="stat-strip__value">
                        <span class="badge <?= e(badge_class($trip['status'])) ?>">
                            <?= e(format_label($trip['status'])) ?>
                        </span>
                    </div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Duration</div>
                    <div class="stat-strip__value"><?= e(format_duration($trip['start_time'], $trip['end_time'])) ?></div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Distance</div>
                    <div class="stat-strip__value"><?= number_format((float) $trip['distance_km'], 2) ?> km</div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Violations</div>
                    <div class="stat-strip__value"><?= (int) $trip['violation_count'] ?></div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Safety Score</div>
                    <div class="stat-strip__value">
                        <?= $trip['safety_score'] !== null ? (int) $trip['safety_score'] . '/100' : '—' ?>
                    </div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">GPS Points</div>
                    <div class="stat-strip__value"><?= count($gpsPoints) ?></div>
                </div>
            </div>

            <!-- ============================================================
                 LIVE GPS TRACKER (only for active trips)
                 ============================================================ -->
            <?php if ($trip['status'] === 'active'): ?>
                <div class="section-card">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">
                                <i data-lucide="radio"></i>
                                Live GPS Tracking
                            </div>
                            <div class="section-card__subtitle">
                                Your location is being tracked. Black-spot warnings appear below.
                            </div>
                        </div>
                        <button type="button" class="btn btn--sm" onclick="location.reload()">
                            <i data-lucide="refresh-cw"></i> Refresh
                        </button>
                    </div>
                    <div class="section-card__body">
                        <div id="gpsTracker"
                             data-trip-id="<?= (int) $tripId ?>"
                             data-mode="<?= is_demo_mode() ? 'simulated' : 'real' ?>"
                             data-api-url="<?= url('api/gps/update.php') ?>">
                            <div class="gps-alert gps-alert--info" id="gpsWarning">
                                <i data-lucide="map-pin"></i>
                                <span id="gpsStatusText">Initializing GPS…</span>
                            </div>
                        </div>

                        <div style="margin-top: 16px;">
                            <div class="kpi__label" style="margin-bottom: 8px;">Nearby Black Spots</div>
                            <div id="gpsNearby">
                                <div class="text-subtle" style="font-size: 12px;">
                                    Waiting for GPS position…
                                </div>
                            </div>
                        </div>

                        <?php if (is_demo_mode()): ?>
                            <div class="alert alert--info" style="margin-top: 16px;">
                                <i data-lucide="info"></i>
                                <span>
                                    <strong>Demo mode:</strong> Using simulated GPS track (Nairobi → Ruiru).
                                    Set <code>DEMO_MODE = false</code> in <code>config.php</code> for real GPS.
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Active trip info banner -->
            <?php if ($trip['status'] === 'active'): ?>
                <div class="alert alert--info" style="margin-top: var(--space-5);">
                    <i data-lucide="radio"></i>
                    <span>
                        <strong>Trip in progress.</strong>
                        Monitoring is active. When you reach your destination, click <strong>End Trip</strong> to
                        finalize your safety score.
                    </span>
                </div>
            <?php endif; ?>

            <!-- ============================================================
                 MAP + VIOLATION TIMELINE
                 ============================================================ -->
            <div class="split-grid" style="margin-top: var(--space-5);">

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Route Map</div>
                            <div class="section-card__subtitle">Your GPS trail with violation markers</div>
                        </div>
                    </div>
                    <div class="section-card__body">
                        <?php if (empty($gpsPoints)): ?>
                            <div class="empty-state">
                                <i data-lucide="map"></i>
                                <div class="empty-state__title">No GPS data yet</div>
                                <div class="empty-state__text">GPS points will appear as your trip progresses.</div>
                            </div>
                        <?php else: ?>
                            <div id="tripMap" class="map-container"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Violation Timeline</div>
                            <div class="section-card__subtitle">
                                <?= count($violations) ?> event<?= count($violations) === 1 ? '' : 's' ?> during this trip
                            </div>
                        </div>
                    </div>
                    <div class="section-card__body">
                        <?php if (empty($violations)): ?>
                            <div class="empty-state">
                                <i data-lucide="shield-check"></i>
                                <div class="empty-state__title">Clean trip</div>
                                <div class="empty-state__text">No violations were detected.</div>
                            </div>
                        <?php else: ?>
                            <ul class="timeline">
                                <?php foreach ($violations as $v): ?>
                                    <?php
                                        $sevClass = match ($v['severity']) {
                                            'critical', 'high' => 'timeline__item--danger',
                                            'medium'           => 'timeline__item--warning',
                                            default            => 'timeline__item--info',
                                        };
                                    ?>
                                    <li class="timeline__item <?= $sevClass ?>">
                                        <div class="timeline__time">
                                            <?= e(date('H:i:s', strtotime($v['occurred_at']))) ?>
                                        </div>
                                        <div class="timeline__title">
                                            <?= e(format_event_type($v['event_type'])) ?>
                                        </div>
                                        <div class="timeline__meta">
                                            Severity: <strong><?= e(ucfirst($v['severity'])) ?></strong>
                                            · Confidence: <?= number_format((float) $v['confidence'] * 100, 0) ?>%
                                        </div>
                                        <?php if ($v['description']): ?>
                                            <div class="timeline__meta"><?= e($v['description']) ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- ============================================================
                 ROUTE COORDINATES
                 ============================================================ -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Route Coordinates</div>
                </div>
                <div class="section-card__body">
                    <div class="split-grid" style="margin-top: 0;">
                        <dl class="detail-list">
                            <div><dt>Start time</dt><dd><?= e(date('d M Y, H:i:s', strtotime($trip['start_time']))) ?></dd></div>
                            <div><dt>Start location</dt>
                                <dd>
                                    <?= $trip['start_latitude'] !== null
                                        ? e(number_format((float) $trip['start_latitude'], 6) . ', ' . number_format((float) $trip['start_longitude'], 6))
                                        : '—' ?>
                                </dd>
                            </div>
                        </dl>
                        <dl class="detail-list">
                            <div><dt>End time</dt><dd><?= $trip['end_time'] ? e(date('d M Y, H:i:s', strtotime($trip['end_time']))) : '—' ?></dd></div>
                            <div><dt>End location</dt>
                                <dd>
                                    <?= $trip['end_latitude'] !== null
                                        ? e(number_format((float) $trip['end_latitude'], 6) . ', ' . number_format((float) $trip['end_longitude'], 6))
                                        : '—' ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<?php if ($trip['status'] === 'active'): ?>
    <script src="<?= asset('js/gps-tracker.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php if (!empty($gpsPoints)): ?>
<script id="trip-map-data" type="application/json">
<?= json_encode($mapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/trip-map.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>