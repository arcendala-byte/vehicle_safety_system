<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('admin');

$tripId = (int) ($_GET['id'] ?? 0);
$trip   = $tripId > 0 ? get_trip($tripId) : null;

if (!$trip) {
    set_flash('error', 'Trip not found.');
    header('Location: ' . url('admin/trips/index.php'));
    exit;
}

$gpsPoints  = get_trip_gps_points($tripId, 500);
$violations = get_trip_violations($tripId);

// Prepare map payload
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
    'start' => [
        'lat' => (float) $trip['start_latitude'],
        'lng' => (float) $trip['start_longitude'],
    ],
    'end' => $trip['end_latitude'] !== null ? [
        'lat' => (float) $trip['end_latitude'],
        'lng' => (float) $trip['end_longitude'],
    ] : null,
];

$user      = current_user();
$pageTitle = 'Trip #' . $tripId;
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
                        <a href="<?= url('admin/trips/index.php') ?>">Trips</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>#<?= (int) $tripId ?></span>
                    </nav>
                    <h1 class="page-title">Trip #<?= (int) $tripId ?></h1>
                    <p class="page-subtitle">
                        <?= e($trip['driver_name']) ?> &middot; <?= e($trip['registration_number']) ?> &middot;
                        <?= e(date('d M Y, H:i', strtotime($trip['start_time']))) ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <?php if ($trip['status'] === 'active'): ?>
                        <a href="<?= url('admin/trips/cancel.php?id=' . $tripId) ?>" class="btn btn--danger">
                            <i data-lucide="square"></i> Cancel Trip
                        </a>
                    <?php else: ?>
                        <a href="<?= url('admin/trips/delete.php?id=' . $tripId) ?>" class="btn btn--danger">
                            <i data-lucide="trash-2"></i> Delete
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

            <!-- Map + Timeline -->
            <div class="split-grid" style="margin-top: var(--space-5);">

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Route Map</div>
                            <div class="section-card__subtitle">GPS trail with violation markers</div>
                        </div>
                    </div>
                    <div class="section-card__body">
                        <?php if (empty($gpsPoints)): ?>
                            <div class="empty-state">
                                <i data-lucide="map"></i>
                                <div class="empty-state__title">No GPS data</div>
                                <div class="empty-state__text">This trip has no recorded GPS points.</div>
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
                            <div class="section-card__subtitle"><?= count($violations) ?> event<?= count($violations) === 1 ? '' : 's' ?> during this trip</div>
                        </div>
                    </div>
                    <div class="section-card__body">
                        <?php if (empty($violations)): ?>
                            <div class="empty-state">
                                <i data-lucide="shield-check"></i>
                                <div class="empty-state__title">Clean trip</div>
                                <div class="empty-state__text">No violations were detected during this journey.</div>
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
                                            · Status: <?= e(format_label($v['status'])) ?>
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

            <!-- Details -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Driver</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div><dt>Name</dt>
                                <dd>
                                    <a href="<?= url('admin/drivers/view.php?id=' . (int) $trip['driver_id']) ?>" class="link-plain">
                                        <?= e($trip['driver_name']) ?>
                                    </a>
                                </dd>
                            </div>
                            <div><dt>License</dt><dd><?= e($trip['driver_license']) ?></dd></div>
                            <div><dt>Current score</dt>
                                <dd>
                                    <span class="badge <?= (int) $trip['driver_score'] >= 85 ? 'badge--success' : ((int) $trip['driver_score'] >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                        <?= (int) $trip['driver_score'] ?>
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Vehicle</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div><dt>Reg No</dt>
                                <dd>
                                    <a href="<?= url('admin/vehicles/view.php?id=' . (int) $trip['vehicle_id']) ?>" class="link-plain">
                                        <?= e($trip['registration_number']) ?>
                                    </a>
                                </dd>
                            </div>
                            <div><dt>Make / Model</dt><dd><?= e(trim(($trip['make'] ?? '') . ' ' . ($trip['model'] ?? ''))) ?: '—' ?></dd></div>
                            <div><dt>Type</dt><dd><?= e(ucfirst($trip['vehicle_type'])) ?></dd></div>
                        </dl>
                    </div>
                </div>

            </div>

            <!-- Coordinates -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Route Coordinates</div>
                </div>
                <div class="section-card__body">
                    <div class="split-grid" style="margin-top: 0;">
                        <dl class="detail-list">
                            <div><dt>Start time</dt><dd><?= e(date('d M Y, H:i:s', strtotime($trip['start_time']))) ?></dd></div>
                            <div><dt>Start lat/lng</dt>
                                <dd>
                                    <?= $trip['start_latitude'] !== null
                                        ? e(number_format((float) $trip['start_latitude'], 6) . ', ' . number_format((float) $trip['start_longitude'], 6))
                                        : '—' ?>
                                </dd>
                            </div>
                        </dl>
                        <dl class="detail-list">
                            <div><dt>End time</dt><dd><?= $trip['end_time'] ? e(date('d M Y, H:i:s', strtotime($trip['end_time']))) : '—' ?></dd></div>
                            <div><dt>End lat/lng</dt>
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

<?php if (!empty($gpsPoints)): ?>
<script id="trip-map-data" type="application/json">
<?= json_encode($mapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/trip-map.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>