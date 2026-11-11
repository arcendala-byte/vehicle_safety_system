<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $result = deactivate_driver($driverId);
        if ($result['ok']) {
            set_flash('success', 'Driver "' . $driver['full_name'] . '" deactivated.');
            header('Location: ' . url('admin/drivers/index.php'));
            exit;
        }
        $error = $result['message'];
    }
}

$user      = current_user();
$pageTitle = 'Deactivate ' . $driver['full_name'];
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
                    <span>Deactivate</span>
                </nav>
            </div>

            <div class="confirm-card">
                <div class="confirm-card__icon confirm-card__icon--danger">
                    <i data-lucide="user-x"></i>
                </div>
                <h1 class="confirm-card__title">Deactivate this driver?</h1>
                <p class="confirm-card__text">
                    <strong><?= e($driver['full_name']) ?></strong>
                    (License <code><?= e($driver['license_number']) ?></code>)
                    will be marked as <strong>inactive</strong>. They will not be able to
                    start new trips until reactivated. Historical records are preserved.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert--danger">
                        <i data-lucide="alert-circle"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('admin/drivers/delete.php?id=' . $driverId) ?>" class="confirm-card__actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="driver_id" value="<?= (int) $driverId ?>">

                    <a href="<?= url('admin/drivers/view.php?id=' . $driverId) ?>" class="btn btn--ghost">
                        <i data-lucide="arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn--danger">
                        <i data-lucide="user-x"></i> Yes, deactivate
                    </button>
                </form>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>