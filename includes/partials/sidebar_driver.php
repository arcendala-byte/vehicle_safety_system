<?php
if (!defined('VS_APP')) {
    define('VS_APP', true);
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../functions.php';

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir    = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

function nav_active_d(string $filename, string $current): string
{
    return $filename === $current ? 'nav-item--active' : '';
}
function nav_dir_active_d(string $dirname, string $current): string
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
            <a class="nav-item <?= nav_active_d('dashboard.php', $currentScript) ?>"
               href="<?= url('driver/dashboard.php') ?>">
                <i data-lucide="layout-dashboard"></i> Dashboard
            </a>
            <a class="nav-item <?= nav_active_d('live.php', $currentScript) ?>"
               href="<?= url('driver/live.php') ?>">
                <i data-lucide="radio"></i> Live Dashboard
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">My Driving</div>
            <a class="nav-item <?= nav_dir_active_d('trips', $currentDir) ?>"
               href="<?= url('driver/trips/index.php') ?>">
                <i data-lucide="navigation"></i> My Trips
            </a>
            <a class="nav-item <?= nav_active_d('monitoring.php', $currentScript) ?>"
               href="<?= url('driver/monitoring.php') ?>">
                <i data-lucide="video"></i> Live Monitoring
            </a>
            <a class="nav-item <?= nav_dir_active_d('violations', $currentDir) ?>"
               href="<?= url('driver/violations/index.php') ?>">
                <i data-lucide="alert-circle"></i> My Violations
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Safety</div>
            <a class="nav-item <?= nav_active_d('safety-score.php', $currentScript) ?>"
               href="<?= url('driver/safety-score.php') ?>">
                <i data-lucide="gauge"></i> Safety Score
            </a>
            <a class="nav-item <?= nav_active_d('map.php', $currentScript) ?>"
               href="<?= url('driver/map.php') ?>">
                <i data-lucide="map-pin"></i> Nearby Black Spots
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group__label">Account</div>
            <a class="nav-item <?= nav_active_d('profile.php', $currentScript) ?>"
               href="<?= url('driver/profile.php') ?>">
                <i data-lucide="user"></i> Profile
            </a>
        </div>
    </nav>
</aside>