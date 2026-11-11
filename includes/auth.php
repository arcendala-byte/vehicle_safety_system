<?php
/**
 * ============================================================
 * AUTHENTICATION & SESSION MANAGEMENT
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// SESSION BOOTSTRAP
// ------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Basic session hardening
    if (!isset($_SESSION['_init'])) {
        $_SESSION['_init']      = time();
        $_SESSION['_last_seen'] = time();
    }
}

// ------------------------------------------------------------
// USER LOOKUP
// ------------------------------------------------------------
function find_user_by_login(string $login): ?array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "SELECT user_id, username, email, password_hash, role, full_name, status
         FROM users
         WHERE (username = :login_username OR email = :login_email)
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([
        ':login_username' => $login,
        ':login_email'    => $login,
    ]);
    $user = $stmt->fetch();
    return $user ?: null;
}

// ------------------------------------------------------------
// LOGIN
// ------------------------------------------------------------
function attempt_login(string $login, string $password): array
{
    $user = find_user_by_login($login);

    if (!$user) {
        return ['ok' => false, 'message' => 'Invalid credentials.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'message' => 'Invalid credentials.'];
    }

    // Rehash if algorithm changed
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :h WHERE user_id = :id");
        $stmt->execute([':h' => $newHash, ':id' => $user['user_id']]);
    }

    // Update last_login
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
    $stmt->execute([':id' => $user['user_id']]);

    // Rotate session ID (prevents fixation)
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int)$user['user_id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['login_time'] = time();
    $_SESSION['_last_seen'] = time();

    // Fetch linked driver record (for driver role)
    if ($user['role'] === 'driver') {
        $stmt = $pdo->prepare("SELECT driver_id FROM drivers WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $user['user_id']]);
        $driver = $stmt->fetch();
        if ($driver) {
            $_SESSION['driver_id'] = (int)$driver['driver_id'];
        }
    }

    return ['ok' => true, 'user' => $user];
}

// ------------------------------------------------------------
// LOGOUT
// ------------------------------------------------------------
function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

// ------------------------------------------------------------
// CURRENT USER HELPERS
// ------------------------------------------------------------
function is_logged_in(): bool
{
    start_secure_session();
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'role'      => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'driver_id' => $_SESSION['driver_id'] ?? null,
    ];
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function is_driver(): bool
{
    return current_role() === 'driver';
}

// ------------------------------------------------------------
// GUARDS
// ------------------------------------------------------------
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please sign in to continue.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    // Refresh last_seen
    $_SESSION['_last_seen'] = time();
}

function require_role(string $role): void
{
    require_login();
    if (current_role() !== $role) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'You do not have access to that page.';
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// ------------------------------------------------------------
// CSRF PROTECTION
// ------------------------------------------------------------
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): bool
{
    if (empty($_POST[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $_POST[CSRF_TOKEN_NAME]);
}

// ------------------------------------------------------------
// FLASH MESSAGES
// ------------------------------------------------------------
function set_flash(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    start_secure_session();
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

// ------------------------------------------------------------
// AUTO-START SESSION
// ------------------------------------------------------------
start_secure_session();