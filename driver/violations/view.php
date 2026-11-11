<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);
$violationId = (int) ($_GET['id'] ?? 0);

$violation = $violationId > 0 ? get_violation($violationId) : null;

// Guard: belongs to this driver
if (!$violation || (int) $violation['driver_id'] !== $driverId) {
    set_flash('error', 'Violation not found.');
    header('Location: ' . url('driver/violations/index.php'));
    exit;
}

$mapPayload = [
    'lat'   => $violation['latitude']  !== null ? (float) $violation['latitude']  : null,
    'lng'   => $violation['longitude'] !== null ? (float) $violation['longitude'] : null,
    'type'  => $violation['event_type'],
    'sev'   => $violation['severity'],
    'desc'  => $violation['description'],
    'when'  => $violation['occurred_at'],
    'driver'=> $violation['driver_name'],
];

$pageTitle = format_event_type($violation['event_type']);
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
                        <a href="<?= url('driver/violations/index.php') ?>">My Violations</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>#<?= (int) $violationId ?></span>
                    </nav>
                    <h1 class="page-title"><?= e(format_event_type($violation['event_type'])) ?></h1>
                    <p class="page-subtitle">
                        Detected <?= e(date('d M Y, H:i:s', strtotime($violation['occurred_at']))) ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <a href="<?= url('driver/violations/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Info banner (read-only notice) -->
            <div class="alert alert--info">
                <i data-lucide="info"></i>
                <span>
                    This is a read-only view. Only administrators can change the status of a violation.
                </span>
            </div>

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Severity</div>
                    <div class="stat-strip__value">
                        <span class="badge <?= e(badge_class($violation['severity'])) ?>">
                            <?= e(format_label($violation['severity'])) ?>
                        </span>
                    </div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Confidence</div>
                    <div class="stat-strip__value">
                        <?= $violation['confidence'] !== null
                            ? number_format((float) $violation['confidence'] * 100, 1) . '%'
                            : '—' ?>
                    </div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Status</div>
                    <div class="stat-strip__value">
                        <span class="badge <?= e(badge_class($violation['status'])) ?>">
                            <?= e(format_label($violation['status'])) ?>
                        </span>
                    </div>
                </div>
                <div class="stat-strip__item">
                    <div class="stat-strip__label">Location</div>
                    <div class="stat-strip__value">
                        <?= $violation['latitude'] !== null
                            ? e(number_format((float) $violation['latitude'], 4) . ', ' . number_format((float) $violation['longitude'], 4))
                            : '—' ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle + Trip -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Vehicle</div>
                    </div>
                    <div class="section-card__body">
                        <?php if ($violation['vehicle_id']): ?>
                            <dl class="detail-list">
                                <div><dt>Reg No</dt>
                                    <dd><a href="<?= url('driver/trips/index.php') ?>" class="link-plain">
                                        <?= e($violation['registration_number']) ?>
                                    </a></dd>
                                </div>
                                <div><dt>Make / Model</dt>
                                    <dd><?= e(trim(($violation['make'] ?? '') . ' ' . ($violation['model'] ?? ''))) ?: '—' ?></dd>
                                </div>
                            </dl>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="car"></i>
                                <div class="empty-state__title">No vehicle recorded</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Trip</div>
                    </div>
                    <div class="section-card__body">
                        <?php if ($violation['trip_id']): ?>
                            <dl class="detail-list">
                                <div><dt>Trip</dt>
                                    <dd><a href="<?= url('driver/trips/view.php?id=' . (int) $violation['trip_id']) ?>" class="link-plain">
                                        #<?= (int) $violation['trip_id'] ?>
                                    </a></dd>
                                </div>
                                <div><dt>Status</dt>
                                    <dd><?= e(ucfirst($violation['trip_status'] ?? '—')) ?></dd>
                                </div>
                            </dl>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="navigation"></i>
                                <div class="empty-state__title">Standalone event</div>
                                <div class="empty-state__text">Not part of a trip.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Description -->
            <?php if ($violation['description']): ?>
                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Description</div>
                    </div>
                    <div class="section-card__body">
                        <p><?= e($violation['description']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Map -->
            <div class="section-card">
                <div class="section-card__header">
                    <div class="section-card__title">Location</div>
                    <div class="section-card__subtitle">
                        <?= $violation['latitude'] !== null
                            ? 'Where the event was recorded'
                            : 'No GPS coordinates recorded for this event' ?>
                    </div>
                </div>
                <div class="section-card__body">
                    <?php if ($violation['latitude'] !== null && $violation['longitude'] !== null): ?>
                        <div id="violationMap" class="map-container"></div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i data-lucide="map-pin"></i>
                            <div class="empty-state__title">No location data</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<?php if ($violation['latitude'] !== null && $violation['longitude'] !== null): ?>
<script id="violation-map-data" type="application/json">
<?= json_encode($mapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/violation-map.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>