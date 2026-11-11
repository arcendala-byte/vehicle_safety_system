<?php
/**
 * ============================================================
 * API: Update Profile
 * ============================================================
 * POST /api/profile/update.php
 * Body: { full_name, email, phone? }
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';

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

$user     = current_user();
$userId   = (int) $user['user_id'];
$driverId = (int) ($user['driver_id'] ?? 0);

$v = new Validator($input);
$v->required('full_name', 'Full name')->max('full_name', 100)->email('email')->max('email', 120);

if ($v->fails()) {
    json_response(['success' => false, 'error' => 'Validation failed', 'errors' => $v->errors()], 400);
}

$fullName = $v->value('full_name');
$email    = $v->value('email');
$phone    = isset($input['phone']) ? clean((string) $input['phone']) : null;

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em WHERE user_id = :id");
    $stmt->execute([':fn' => $fullName, ':em' => $email, ':id' => $userId]);

    // Update driver record if applicable
    if ($driverId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE drivers SET full_name = :fn, email = :em, phone = :ph WHERE driver_id = :did"
        );
        $stmt->execute([
            ':fn' => $fullName,
            ':em' => $email,
            ':ph' => $phone,
            ':did' => $driverId,
        ]);
    }

    $pdo->commit();

    // Refresh session
    $_SESSION['full_name'] = $fullName;

    json_response([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'user' => [
            'full_name' => $fullName,
            'email'     => $email,
            'phone'     => $phone,
        ],
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        json_response(['success' => false, 'error' => 'Email already in use'], 409);
    }
    error_log('[profile/update] ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to update profile'], 500);
}
