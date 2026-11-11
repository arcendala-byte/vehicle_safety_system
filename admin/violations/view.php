<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

require_role('admin');

$violationId = (int) ($_GET['id'] ?? 0);
$violation   = $violationId > 0 ? get_violation($violationId) : null;

if (!$violation) {
    set_flash('error', 'Violation not found.');
    header('Location: ' . url('admin/violations/index.php'));
    exit;
}

// Map payload for this violation (single marker)
$mapPayload = [
    'lat'   => $violation['latitude']  !== null ? (float) $violation['latitude']  : null,
    'lng'   => $violation['longitude'] !== null ? (float) $violation['longitude'] : null,
    'type'  => $violation['event_type'],
    'sev'   => $violation['severity'],
    'desc'  => $violation['description'],
    'when'  => $violation['occurred_at'],
    'driver'=> $violation['driver_name'],
];

$user      = current_user();
$pageTitle = 'Violation #' . $violationId;
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
                        <a href="<?= url('admin/violations/index.php') ?>">Violations</a>
                        <span class="breadcrumb__sep">/</span>
                        <span>#<?= (int) $violationId ?></span>
                    </nav>
                    <h1 class="page-title"><?= e(format_event_type($violation['event_type'])) ?></h1>
                    <p class="page-subtitle">
                        Detected <?= e(date('d M Y, H:i:s', strtotime($violation['occurred_at']))) ?>
                    </p>
                </div>
                <div class="page-header__actions">
                    <a href="<?= url('admin/violations/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Status strip -->
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
                    <div class="stat-strip__label">Current status</div>
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

            <!-- Status update -->
            <div class="section-card">
                <div class="section-card__header">
                    <div>
                        <div class="section-card__title">Update Status</div>
                        <div class="section-card__subtitle">Move this event through its lifecycle</div>
                    </div>
                </div>
                <div class="section-card__body">
                    <form method="POST" action="<?= url('admin/violations/status.php') ?>" class="form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="violation_id" value="<?= (int) $violationId ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_status" class="form-label">New status</label>
                                <div class="input-wrap">
                                    <i data-lucide="activity" class="input-icon"></i>
                                    <select name="new_status" id="new_status" class="form-input">
                                        <?php foreach (violation_statuses() as $s): ?>
                                            <option value="<?= e($s) ?>" <?= $violation['status'] === $s ? 'selected' : '' ?>>
                                                <?= e(ucfirst($s)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn--primary">
                                <i data-lucide="save"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Detail cards -->
            <div class="split-grid">

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Driver</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div><dt>Name</dt>
                                <dd>
                                    <a href="<?= url('admin/drivers/view.php?id=' . (int) $violation['driver_id']) ?>" class="link-plain">
                                        <?= e($violation['driver_name']) ?>
                                    </a>
                                </dd>
                            </div>
                            <div><dt>License</dt><dd><?= e($violation['driver_license']) ?></dd></div>
                        </dl>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Vehicle & Trip</div>
                    </div>
                    <div class="section-card__body">
                        <dl class="detail-list">
                            <div><dt>Vehicle</dt>
                                <dd>
                                    <?php if ($violation['vehicle_id']): ?>
                                        <a href="<?= url('admin/vehicles/view.php?id=' . (int) $violation['vehicle_id']) ?>" class="link-plain">
                                            <?= e($violation['registration_number']) ?>
                                        </a>
                                        <span class="text-subtle" style="margin-left: 6px;">
                                            <?= e(trim(($violation['make'] ?? '') . ' ' . ($violation['model'] ?? ''))) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-subtle">—</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div><dt>Trip</dt>
                                <dd>
                                    <?php if ($violation['trip_id']): ?>
                                        <a href="<?= url('admin/trips/view.php?id=' . (int) $violation['trip_id']) ?>" class="link-plain">
                                            #<?= (int) $violation['trip_id'] ?>
                                        </a>
                                        <span class="text-subtle" style="margin-left: 6px;">
                                            <?= e(ucfirst($violation['trip_status'] ?? '')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-subtle">Standalone (not part of a trip)</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
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
                        <?= $violation['latitude'] !== null ? 'GPS marker at the point of detection' : 'No location recorded' ?>
                    </div>
                </div>
                <div class="section-card__body">
                    <?php if ($violation['latitude'] !== null && $violation['longitude'] !== null): ?>
                        <div id="violationMap" class="map-container"></div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i data-lucide="map-pin"></i>
                            <div class="empty-state__title">No location data</div>
                            <div class="empty-state__text">This violation was recorded without GPS coordinates.</div>
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