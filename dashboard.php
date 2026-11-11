<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

if (is_admin()) {
    header('Location: ' . url('admin/dashboard.php'));
} elseif (is_driver()) {
    header('Location: ' . url('driver/dashboard.php'));
} else {
    // Unknown role — kill the session
    logout_user();
    header('Location: ' . url('login.php'));
}
exit;