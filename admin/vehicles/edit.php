<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/vehicle_helpers.php';

require_role('admin');

$vehicleId = (int) ($_GET['id'] ?? $_POST['vehicle_id'] ?? 0);
$vehicle   = $vehicleId > 0 ? get_vehicle($vehicleId) : null;

if (!$vehicle) {
    set_flash('error', 'Vehicle not found.');
    header('Location: ' . url('admin/vehicles/index.php'));
    exit;
}

$formValues = [
    'registration_number' => $vehicle['registration_number'],
    'make'                => $vehicle['make']  ?? '',
    'model'               => $vehicle['model'] ?? '',
    'year'                => $vehicle['year']  ?? '',
    'vehicle_type'        => $vehicle['vehicle_type'],
    'assigned_driver_id'  => (int) ($vehicle['assigned_driver_id'] ?? 0),
    'status'              => $vehicle['status'],
];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $result = validate_vehicle_input($_POST, $vehicleId);
        $formValues = $result['values'];
        $formErrors = $result['errors'];

        if (empty($formErrors)) {
            try {
                update_vehicle($vehicleId, $formValues);
                set_flash('success', 'Vehicle updated successfully.');
                header('Location: ' . url('admin/vehicles/view.php?id=' . $vehicleId));
                exit;
            } catch (Throwable $e) {
                error_log('[update_vehicle] ' . $e->getMessage());
                $formErrors['_general'] = 'Unable to update vehicle. Please try again.';
            }
        }
    }
}

$user      = current_user();
$pageTitle = 'Edit ' . $vehicle['registration_number'];
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

            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('admin/vehicles/index.php') ?>">Vehicles</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('admin/vehicles/view.php?id=' . $vehicleId) ?>">
                        <?= e($vehicle['registration_number']) ?>
                    </a>
                    <span class="breadcrumb__sep">/</span>
                    <span>Edit</span>
                </nav>
                <h1 class="page-title">Edit Vehicle</h1>
                <p class="page-subtitle">Update vehicle details, assignment, and status.</p>
            </div>

            <?php if (isset($formErrors['_general'])): ?>
                <div class="alert alert--danger">
                    <i data-lucide="alert-circle"></i>
                    <span><?= e($formErrors['_general']) ?></span>
                </div>
            <?php endif; ?>

            <div class="section-card">
                <div class="section-card__body">
                    <?php
                        $formAction      = url('admin/vehicles/edit.php?id=' . $vehicleId);
                        $formSubmitLabel = 'Save Changes';
                        require __DIR__ . '/../../includes/partials/vehicle_form.php';
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>