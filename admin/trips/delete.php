<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

require_role('admin');

$tripId = (int) ($_GET['id'] ?? $_POST['trip_id'] ?? 0);
$trip   = $tripId > 0 ? get_trip($tripId) : null;

if (!$trip) {
    set_flash('error', 'Trip not found.');
    header('Location: ' . url('admin/trips/index.php'));
    exit;
}

if ($trip['status'] === 'active') {
    set_flash('error', 'Cannot delete an active trip. Cancel it first.');
    header('Location: ' . url('admin/trips/view.php?id=' . $tripId));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $result = delete_trip($tripId);
        if ($result['ok']) {
            set_flash('success', 'Trip #' . $tripId . ' deleted.');
            header('Location: ' . url('admin/trips/index.php'));
            exit;
        }
        $error = $result['message'];
    }
}

$user      = current_user();
$pageTitle = 'Delete Trip #' . $tripId;
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
                    <a href="<?= url('admin/trips/index.php') ?>">Trips</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('admin/trips/view.php?id=' . $tripId) ?>">#<?= $tripId ?></a>
                    <span class="breadcrumb__sep">/</span>
                    <span>Delete</span>
                </nav>
            </div>

            <div class="confirm-card">
                <div class="confirm-card__icon confirm-card__icon--danger">
                    <i data-lucide="trash-2"></i>
                </div>
                <h1 class="confirm-card__title">Delete this trip permanently?</h1>
                <p class="confirm-card__text">
                    <strong>Trip #<?= (int) $tripId ?></strong> by
                    <strong><?= e($trip['driver_name']) ?></strong>
                    in <strong><?= e($trip['registration_number']) ?></strong>
                    (<?= e(date('d M Y, H:i', strtotime($trip['start_time']))) ?>)
                    will be deleted permanently. GPS breadcrumbs will also be removed.
                    This action cannot be undone.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert--danger">
                        <i data-lucide="alert-circle"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('admin/trips/delete.php?id=' . $tripId) ?>" class="confirm-card__actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="trip_id" value="<?= (int) $tripId ?>">

                    <a href="<?= url('admin/trips/view.php?id=' . $tripId) ?>" class="btn btn--ghost">
                        <i data-lucide="arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn--danger">
                        <i data-lucide="trash-2"></i> Yes, delete permanently
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>