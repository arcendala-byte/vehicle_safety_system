<?php
/**
 * ============================================================
 * API: Live Monitoring Snapshot
 * ============================================================
 * GET /api/monitoring/snapshot.php
 * Returns current status snapshot: active trip, recent
 * violations, GPS count, safety score.
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/trip_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    json_response(['success' => false, 'error' => 'No driver profile'], 403);
}

$pdo = Database::getConnection();

// Driver info
$stmt = $pdo->prepare("SELECT current_score FROM drivers WHERE driver_id = :id");
$stmt->execute([':id' => $driverId]);
$driverScore = (int) $stmt->fetchColumn();

// Active trip
$activeTrip = get_active_trip_for_driver($driverId);

// Recent violations (last 10)
$stmt = $pdo->prepare(
    "SELECT violation_id, event_type, severity, confidence, description, occurred_at
     FROM driver_violations
     WHERE driver_id = :id
     ORDER BY occurred_at DESC
     LIMIT 10"
);
$stmt->execute([':id' => $driverId]);
$recentViolations = $stmt->fetchAll();

// If active trip: get counts
$gpsCount = 0;
$tripViolations = 0;
if ($activeTrip) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gps_locations WHERE trip_id = :tid");
    $stmt->execute([':tid' => $activeTrip['trip_id']]);
    $gpsCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM driver_violations WHERE trip_id = :tid"
    );
    $stmt->execute([':tid' => $activeTrip['trip_id']]);
    $tripViolations = (int) $stmt->fetchColumn();
}

// Latest GPS point (for map marker)
$latestGps = null;
if ($activeTrip) {
    $stmt = $pdo->prepare(
        "SELECT latitude, longitude, recorded_at
         FROM gps_locations
         WHERE trip_id = :tid
         ORDER BY recorded_at DESC LIMIT 1"
    );
    $stmt->execute([':tid' => $activeTrip['trip_id']]);
    $latestGps = $stmt->fetch() ?: null;
}

json_response([
    'success'            => true,
    'driver_score'       => $driverScore,
    'active_trip'        => $activeTrip ? [
        'trip_id'    => (int) $activeTrip['trip_id'],
        'start_time' => $activeTrip['start_time'],
        'vehicle'    => $activeTrip['registration_number'] ?? null,
        'gps_points' => $gpsCount,
        'violations' => $tripViolations,
    ] : null,
    'latest_gps'         => $latestGps,
    'recent_violations'  => $recentViolations,
    'server_time'        => date('c'),
]);
