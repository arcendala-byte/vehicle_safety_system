<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/violations/index.php'));
    exit;
}

if (!verify_csrf()) {
    set_flash('error', 'Security check failed.');
    header('Location: ' . url('admin/violations/index.php'));
    exit;
}

$ids       = (array) ($_POST['violation_ids'] ?? []);
$newStatus = (string) ($_POST['new_status'] ?? '');

$result = bulk_set_violation_status($ids, $newStatus);

if ($result['error']) {
    set_flash('error', $result['error']);
} else {
    $n = (int) $result['updated'];
    set_flash('success', "Updated {$n} violation" . ($n === 1 ? '' : 's') . " to \"" . ucfirst($newStatus) . "\".");
}

header('Location: ' . url('admin/violations/index.php'));
exit;