<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/vehicle_helpers.php';

require_role('admin');

$formValues = [
    'registration_number' => '',
    'make'                => '',
    'model'               => '',
    'year'                => '',
    'vehicle_type'        => 'private',
    'assigned_driver_id'  => 0,
    'status'              => 'active',
];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $result = validate_vehicle_input($_POST, 0);
        $formValues = $result['values'];
        $formErrors = $result['errors'];

        if (empty($formErrors)) {
            try {
                $newId = create_vehicle($formValues);
                set_flash('success', 'Vehicle registered successfully.');
                header('Location: ' . url('admin/vehicles/view.php?id=' . $newId));
                exit;
            } catch (Throwable $e) {
                error_log('[create_vehicle] ' . $e->getMessage());
                $formErrors['_general'] = 'Unable to save vehicle. Please try again.';
            }
        }
    }
}

$user      = current_user();
$pageTitle = 'Add Vehicle';
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
                    <span>Add</span>
                </nav>
                <h1 class="page-title">Add New Vehicle</h1>
                <p class="page-subtitle">Register a new vehicle in the fleet.</p>
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
                        $formAction      = url('admin/vehicles/create.php');
                        $formSubmitLabel = 'Create Vehicle';
                        require __DIR__ . '/../../includes/partials/vehicle_form.php';
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>