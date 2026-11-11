<?php
if (!defined('VS_APP')) {
    define('VS_APP', true);
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../functions.php';

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir    = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Fetch pending count for the notification badge
$pendingCount = 0;
try {
    $pdo = Database::getConnection();
    $pendingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM drivers d 
         JOIN users u ON u.user_id = d.user_id 
         WHERE d.status = 'inactive'"
    )->fetchColumn();
} catch (Exception $e) {}

function nav_active(string $filename, string $current): string
{
    return $filename === $current ? 'nav-item--active' : '';
}
function nav_dir_active(string $dirname, string $current): string
{
    return $dirname === $current ? 'nav-item--active' : '';
}
?>

<aside class="app-sidebar" id="appSidebar">
    <div class="app-sidebar__brand">
        <i data-lucide="shield-check"></i>
        <span><?= e(APP_NAME) ?></span>
    </div>

    <nav class="app-sidebar__nav">
        <div class="nav-group">
            <div class="nav-group__label">Overview</div>
            <a class="nav-item <?= nav_active('dashboard.php', $currentScript) ?>"
               href="<?= url('admin/dashboard.php') ?>">
                <i data-lucide="layout-dashboard"></i> Dashboard
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Operations</div>
            <a class="nav-item <?= nav_dir_active('drivers', $currentDir) ?>"
               href="<?= url('admin/drivers/index.php') ?>">
                <i data-lucide="users"></i> Drivers
            </a>
            <a class="nav-item <?= nav_active('pending.php', $currentScript) ?>"
               href="<?= url('admin/drivers/pending.php') ?>">
                <i data-lucide="user-check"></i> Pending Registrations
                <?php if ($pendingCount > 0): ?>
                    <span class="badge" style="margin-left: auto; background: var(--color-danger); color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px;">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a class="nav-item <?= nav_dir_active('vehicles', $currentDir) ?>"
               href="<?= url('admin/vehicles/index.php') ?>">
                <i data-lucide="car"></i> Vehicles
            </a>
            <a class="nav-item <?= nav_dir_active('trips', $currentDir) ?>"
               href="<?= url('admin/trips/index.php') ?>">
                <i data-lucide="navigation"></i> Trips
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Safety</div>
            <a class="nav-item <?= nav_dir_active('violations', $currentDir) ?>"
               href="<?= url('admin/violations/index.php') ?>">
                <i data-lucide="alert-triangle"></i> Violations
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Intelligence</div>
            <a class="nav-item <?= nav_dir_active('blackspots', $currentDir) ?>"
               href="<?= url('admin/blackspots/index.php') ?>">
                <i data-lucide="map-pin"></i> Black Spots
            </a>
            <a class="nav-item <?= nav_dir_active('reports', $currentDir) ?>"
               href="<?= url('admin/reports/index.php') ?>">
                <i data-lucide="file-text"></i> Reports
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Account</div>
            <a class="nav-item <?= nav_active('profile.php', $currentScript) ?>"
               href="<?= url('admin/profile.php') ?>">
                <i data-lucide="user"></i> My Profile
            </a>
            <a class="nav-item" href="<?= url('logout.php') ?>">
                <i data-lucide="log-out"></i> Sign Out
            </a>
        </div>
    </nav>
</aside>