<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_queries.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

$pdo = Database::getConnection();
$blackspots = $pdo->query(
    "SELECT blackspot_id, road_name, description, latitude, longitude,
            risk_level, risk_score, accident_count, road_condition
     FROM accident_blackspots
     WHERE status = 'active'
     ORDER BY risk_score DESC"
)->fetchAll();

$activeTrip = $pdo->prepare(
    "SELECT trip_id, start_time FROM trips
     WHERE driver_id = :id AND status = 'active'
     LIMIT 1"
);
$activeTrip->execute([':id' => $driverId]);
$activeTrip = $activeTrip->fetch();

$mapPayload = [
    'blackspots' => array_map(fn($b) => [
        'id'      => (int) $b['blackspot_id'],
        'name'    => $b['road_name'],
        'desc'    => $b['description'],
        'lat'     => (float) $b['latitude'],
        'lng'     => (float) $b['longitude'],
        'risk'    => $b['risk_level'],
        'score'   => (float) $b['risk_score'],
        'accidents'=> (int) $b['accident_count'],
    ], $blackspots),
    'current' => null,
];

$pageTitle = 'Nearby Black Spots';
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
                <h1 class="page-title">Nearby Black Spots</h1>
                <p class="page-subtitle">
                    <?= count($blackspots) ?> active accident-prone locations monitored by the system
                </p>
            </div>

            <div class="split-grid">

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Black Spot Map</div>
                            <div class="section-card__subtitle">Color = risk level</div>
                        </div>
                    </div>
                    <div class="section-card__body">
                        <div id="blackspotMap" class="map-container" style="height: 500px;"></div>
                    </div>
                </div>

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div>
                            <div class="section-card__title">Ranked Locations</div>
                            <div class="section-card__subtitle">By risk score</div>
                        </div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <table class="data-table">
                            <thead>
                                <tr><th>Road</th><th>Risk</th><th>Accidents</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blackspots as $bs): ?>
                                    <tr>
                                        <td><?= e($bs['road_name']) ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($bs['risk_level'])) ?>">
                                                <?= e($bs['risk_level']) ?>
                                            </span>
                                        </td>
                                        <td><?= (int) $bs['accident_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<script id="blackspot-map-data" type="application/json">
<?= json_encode($mapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/blackspot-map.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>