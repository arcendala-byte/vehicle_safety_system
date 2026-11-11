<?php
/**
 * ============================================================
 * API: Change Password
 * ============================================================
 * POST /api/profile/password.php
 * Body: { current_password, new_password, confirm_password }
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
if (!verify_csrf()) {
    json_response(['success' => false, 'error' => 'CSRF token invalid'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

$current = (string) ($input['current_password'] ?? '');
$new     = (string) ($input['new_password']     ?? '');
$confirm = (string) ($input['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    json_response(['success' => false, 'error' => 'All fields are required.'], 400);
}
if (strlen($new) < PASSWORD_MIN_LENGTH) {
    json_response(['success' => false, 'error' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'], 400);
}
if ($new !== $confirm) {
    json_response(['success' => false, 'error' => 'Passwords do not match.'], 400);
}
if ($new === $current) {
    json_response(['success' => false, 'error' => 'New password must be different from current.'], 400);
}

$userId = (int) current_user()['user_id'];
$pdo    = Database::getConnection();

$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :id");
$stmt->execute([':id' => $userId]);
$hash = $stmt->fetchColumn();

if (!$hash || !password_verify($current, $hash)) {
    json_response(['success' => false, 'error' => 'Current password is incorrect.'], 401);
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = :h WHERE user_id = :id");
$stmt->execute([':h' => $newHash, ':id' => $userId]);

json_response(['success' => true, 'message' => 'Password updated successfully.']);
