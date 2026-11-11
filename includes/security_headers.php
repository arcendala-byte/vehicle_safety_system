<?php
/**
 * ============================================================
 * SECURITY HEADERS
 * ============================================================
 * Sets standard HTTP security headers on every page.
 * Include this file in header.php (before any output).
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

// Only set headers if not already sent
if (headers_sent()) {
    return;
}

// ---- Prevent MIME type sniffing ----
header('X-Content-Type-Options: nosniff');

// ---- Prevent clickjacking (iframes) ----
header('X-Frame-Options: DENY');

// ---- Referrer policy ----
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---- Permissions (block camera/mic unless same-origin) ----
header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(self)');

// ---- Content Security Policy ----
// Allow:
//   - 'self' (same origin)
//   - 'unsafe-inline' for inline styles/scripts (used by Chart.js templates)
//   - 'unsafe-eval' required by some Chart.js evaluation
//   - CDNs we actually use
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com",
    "font-src 'self' https://fonts.gstatic.com data:",
    "img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com",
    "connect-src 'self' http://127.0.0.1:5000 http://localhost:5000",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
];
header('Content-Security-Policy: ' . implode('; ', $csp));

// ---- HSTS (only meaningful over HTTPS — safe to send regardless) ----
// Commented out for local HTTP dev; uncomment in production
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
