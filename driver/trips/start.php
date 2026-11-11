<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
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

// Guard: already active?
$activeTrip = get_active_trip_for_driver($driverId);
if ($activeTrip) {
    set_flash('info', 'You already have an active trip in progress.');
    header('Location: ' . url('driver/trips/view.php?id=' . (int) $activeTrip['trip_id']));
    exit;
}

// Assigned active vehicles
$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT vehicle_id, registration_number, make, model, vehicle_type
     FROM vehicles
     WHERE assigned_driver_id = :id AND status = 'active'
     ORDER BY registration_number ASC"
);
$stmt->execute([':id' => $driverId]);
$vehicles = $stmt->fetchAll();

$formError = null;
$selectedVehicle = (int) ($_POST['vehicle_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $formError = 'Security check failed. Please try again.';
    } elseif ($selectedVehicle <= 0) {
        $formError = 'Please select a vehicle.';
    } else {
        // Location (optional — sent by JS)
        $lat = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float) $_POST['latitude']  : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;

        $result = start_trip($driverId, $selectedVehicle, $lat, $lng);
        if ($result['ok']) {
            set_flash('success', 'Trip #' . $result['trip_id'] . ' started. Drive safely.');
            header('Location: ' . url('driver/trips/view.php?id=' . (int) $result['trip_id']));
            exit;
        }
        $formError = $result['message'];
    }
}

$pageTitle = 'Start Trip';
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
                    <span>Start</span>
                </nav>
                <h1 class="page-title">Start New Trip</h1>
                <p class="page-subtitle">Select a vehicle to begin a monitored journey.</p>
            </div>

            <?php if (empty($vehicles)): ?>
                <div class="alert alert--warning">
                    <i data-lucide="alert-triangle"></i>
                    <span>You have no active vehicles assigned. Contact an administrator.</span>
                </div>
                <a href="<?= url('driver/dashboard.php') ?>" class="btn btn--ghost">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            <?php else: ?>

                <?php if ($formError): ?>
                    <div class="alert alert--danger">
                        <i data-lucide="alert-circle"></i>
                        <span><?= e($formError) ?></span>
                    </div>
                <?php endif; ?>

                <div class="section-card">
                    <div class="section-card__body">
                        <form method="POST" action="<?= url('driver/trips/start.php') ?>" class="form" novalidate id="startTripForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="latitude"  id="latitudeInput"  value="">
                            <input type="hidden" name="longitude" id="longitudeInput" value="">

                            <div class="form-section">
                                <div class="form-section__header">
                                    <div class="form-section__title">Select Vehicle</div>
                                    <div class="form-section__subtitle">Choose which vehicle you'll be driving</div>
                                </div>

                                <div class="vehicle-picker">
                                    <?php foreach ($vehicles as $v): ?>
                                        <label class="vehicle-option">
                                            <input type="radio" name="vehicle_id" value="<?= (int) $v['vehicle_id'] ?>"
                                                <?= $selectedVehicle === (int) $v['vehicle_id'] ? 'checked' : '' ?> required>
                                            <div class="vehicle-option__body">
                                                <div class="vehicle-option__icon">
                                                    <i data-lucide="car"></i>
                                                </div>
                                                <div>
                                                    <div class="vehicle-option__reg"><?= e($v['registration_number']) ?></div>
                                                    <div class="vehicle-option__meta">
                                                        <?= e(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?: ucfirst($v['vehicle_type'])) ?>
                                                    </div>
                                                </div>
                                                <div class="vehicle-option__check">
                                                    <i data-lucide="check-circle-2"></i>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section__header">
                                    <div class="form-section__title">Starting Location</div>
                                    <div class="form-section__subtitle">We'll try to capture your GPS position (optional)</div>
                                </div>

                                <div id="gpsStatus" class="alert alert--info">
                                    <i data-lucide="map-pin"></i>
                                    <span id="gpsStatusText">Click "Start Trip" — we'll attempt to capture your GPS location.</span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="<?= url('driver/dashboard.php') ?>" class="btn btn--ghost">
                                    <i data-lucide="arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn--primary">
                                    <i data-lucide="play"></i> Start Trip
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
        </main>
    </div>
</div>

<?php if (!empty($vehicles)): ?>
<script src="<?= asset('js/start-trip.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>