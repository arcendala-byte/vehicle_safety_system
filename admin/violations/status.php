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

$violationId = (int) ($_POST['violation_id'] ?? 0);
$newStatus   = (string) ($_POST['new_status'] ?? '');

if ($violationId <= 0) {
    set_flash('error', 'Invalid violation ID.');
    header('Location: ' . url('admin/violations/index.php'));
    exit;
}

$result = set_violation_status($violationId, $newStatus);

if ($result['ok']) {
    set_flash('success', 'Status updated to "' . ucfirst($newStatus) . '".');
} else {
    set_flash('error', $result['message']);
}

header('Location: ' . url('admin/violations/view.php?id=' . $violationId));
exit;