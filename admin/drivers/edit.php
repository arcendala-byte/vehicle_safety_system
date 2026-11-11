<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/driver_helpers.php';

require_role('admin');

$driverId = (int) ($_GET['id'] ?? $_POST['driver_id'] ?? 0);
$driver   = $driverId > 0 ? get_driver($driverId) : null;

if (!$driver) {
    set_flash('error', 'Driver not found.');
    header('Location: ' . url('admin/drivers/index.php'));
    exit;
}

$formValues = [
    'full_name'      => $driver['full_name'],
    'phone'          => $driver['phone'] ?? '',
    'email'          => $driver['email'] ?? '',
    'license_number' => $driver['license_number'],
    'license_expiry' => $driver['license_expiry'] ?? '',
    'status'         => $driver['status'],
];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $result = validate_driver_input($_POST, $driverId);
        $formValues = $result['values'];
        $formErrors = $result['errors'];

        if (empty($formErrors)) {
            try {
                update_driver($driverId, $formValues);
                set_flash('success', 'Driver updated successfully.');
                header('Location: ' . url('admin/drivers/view.php?id=' . $driverId));
                exit;
            } catch (Throwable $e) {
                error_log('[update_driver] ' . $e->getMessage());
                $formErrors['_general'] = 'Unable to update driver. Please try again.';
            }
        }
    }
}

$user      = current_user();
$pageTitle = 'Edit ' . $driver['full_name'];
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
                    <a href="<?= url('admin/drivers/index.php') ?>">Drivers</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('admin/drivers/view.php?id=' . $driverId) ?>"><?= e($driver['full_name']) ?></a>
                    <span class="breadcrumb__sep">/</span>
                    <span>Edit</span>
                </nav>
                <h1 class="page-title">Edit Driver</h1>
                <p class="page-subtitle">Update profile, license, and account status.</p>
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
                        $formAction      = url('admin/drivers/edit.php?id=' . $driverId);
                        $formSubmitLabel = 'Save Changes';
                        require __DIR__ . '/../../includes/partials/driver_form.php';
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>