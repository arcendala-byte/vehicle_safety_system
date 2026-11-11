<?php
/**
 * ============================================================
 * VEHICLE SAFETY SYSTEM - MAIN CONFIGURATION
 * ============================================================
 * Central configuration file. All paths, constants, and
 * environment settings are defined here.
 *
 * DO NOT commit secrets to Git. Use config/secrets.php
 * (gitignored) for sensitive values in production.
 * ============================================================
 */

// Prevent direct access
if (!defined('VS_APP')) {
    define('VS_APP', true);
}

// ------------------------------------------------------------
// ENVIRONMENT
// ------------------------------------------------------------
// 'development' | 'production'
define('VS_ENV', 'development');

// ------------------------------------------------------------
// ERROR REPORTING
// ------------------------------------------------------------
if (VS_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// ------------------------------------------------------------
// BRAND / APPLICATION
// ------------------------------------------------------------
define('APP_NAME',        'VEHISAFE');
define('APP_TAGLINE',     'Intelligent Vehicle Safety Platform');
define('APP_VERSION',     '1.0.0');
define('APP_ORGANIZATION','Mount Kenya University');

// ------------------------------------------------------------
// URL / PATHS
// ------------------------------------------------------------
// Auto-detect base URL (works with any Apache port)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');

// If the script is in a subfolder, strip it back to the project root
$projectFolder = '/vehicle_safety_system';
if (strpos($scriptDir, $projectFolder) !== false) {
    $scriptDir = substr($scriptDir, 0, strpos($scriptDir, $projectFolder) + strlen($projectFolder));
} else {
    $scriptDir = $projectFolder;
}

define('BASE_URL', $protocol . '://' . $host . $scriptDir);

// Filesystem paths
define('ROOT_PATH',    realpath(__DIR__ . '/..'));
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH',ROOT_PATH . '/includes');
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH',    ROOT_PATH . '/logs');
define('DATABASE_PATH',ROOT_PATH . '/database');

// Asset URLs
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ------------------------------------------------------------
// TIMEZONE
// ------------------------------------------------------------
date_default_timezone_set('Africa/Nairobi');

// ------------------------------------------------------------
// SESSION
// ------------------------------------------------------------
define('SESSION_NAME',     'vehisafe_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// ------------------------------------------------------------
// SECURITY
// ------------------------------------------------------------
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// ------------------------------------------------------------
// AI SERVICE
// ------------------------------------------------------------
define('AI_SERVICE_URL',     'http://127.0.0.1:5000');
define('AI_SERVICE_TIMEOUT', 5);
define('AI_API_KEY',         'change-me-vehisafe-dev-key-2025');
// MUST match AI_API_KEY in ai/.env

// ------------------------------------------------------------
// DEMO MODE
// ------------------------------------------------------------
// When true, simulated GPS, sample data, and hardware-free
// demonstrations are available. Set to false for production.
define('DEMO_MODE', true);

// ------------------------------------------------------------
// SAFETY SCORE RULES (configurable)
// ------------------------------------------------------------
define('SCORE_START', 100);
$GLOBALS['SCORE_PENALTIES'] = [
    'seatbelt_violation' => 15,
    'phone_usage'        => 20,
    'drowsiness'         => 20,
    'distraction'        => 10,
    'speeding'           => 15,
    'other'              => 5,
];

// ------------------------------------------------------------
// EVENT COOLDOWNS (seconds) - prevents duplicate records
// ------------------------------------------------------------
$GLOBALS['EVENT_COOLDOWNS'] = [
    'seatbelt_violation' => 60,
    'phone_usage'        => 30,
    'drowsiness'         => 45,
    'distraction'        => 30,
    'blackspot_warning'  => 120,
];

// ------------------------------------------------------------
// BLACK SPOT RISK THRESHOLDS
// ------------------------------------------------------------
$GLOBALS['RISK_LEVELS'] = [
    'LOW'      => ['min' => 0.00, 'max' => 0.39, 'color' => 'success'],
    'MEDIUM'   => ['min' => 0.40, 'max' => 0.59, 'color' => 'warning'],
    'HIGH'     => ['min' => 0.60, 'max' => 0.79, 'color' => 'danger'],
    'CRITICAL' => ['min' => 0.80, 'max' => 1.00, 'color' => 'danger'],
];