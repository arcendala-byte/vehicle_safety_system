<?php
/**
 * ============================================================
 * SHARED HELPER FUNCTIONS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/config.php';

/**
 * Escape HTML output safely.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Build a full URL from a relative path.
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Build an asset URL.
 */
function asset(string $path): string
{
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Render a JSON response (for API endpoints).
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Get the current ISO timestamp.
 */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Log a system event to the logs folder.
 */
function log_event(string $action, string $description = ''): void
{
    $line = sprintf(
        "[%s] %s | %s | IP: %s\n",
        now(),
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? 'cli'
    );
    @file_put_contents(LOGS_PATH . '/system.log', $line, FILE_APPEND);
}

/**
 * Check if demo mode is enabled.
 */
function is_demo_mode(): bool
{
    return DEMO_MODE === true;
}