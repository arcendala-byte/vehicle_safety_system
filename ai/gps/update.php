<?php
/**
 * ============================================================
 * API: GPS Position Update
 * ============================================================
 * POST /api/gps/update.php
 * Body: { trip_id, latitude, longitude, speed_kmh?, heading_deg? }
 *
 * - Stores GPS point in gps_locations
 * - Returns nearby black spots within radius
 * - Calculates haversine distance to each
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

// Validate
$tripId    = (int) ($input['trip_id']    ?? 0);
$latitude  = isset($input['latitude'])  ? (float) $input['latitude']  : null;
$longitude = isset($input['longitude']) ? (float) $input['longitude'] : null;
$speed     = isset($input['speed_kmh']) ? (float) $input['speed_kmh'] : null;
$heading   = isset($input['heading_deg']) ? (int) $input['heading_deg'] : null;

if ($tripId <= 0 || $latitude === null || $longitude === null) {
    json_response(['success' => false, 'error' => 'Missing trip_id, latitude, or longitude'], 400);
}

// Validate latitude/longitude ranges
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    json_response(['success' => false, 'error' => 'Invalid coordinates'], 400);
}

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    json_response(['success' => false, 'error' => 'No driver profile linked'], 403);
}

$pdo = Database::getConnection();

// Verify trip belongs to this driver and is active
$stmt = $pdo->prepare(
    "SELECT trip_id, status FROM trips WHERE trip_id = :id AND driver_id = :did LIMIT 1"
);
$stmt->execute([':id' => $tripId, ':did' => $driverId]);
$trip = $stmt->fetch();

if (!$trip) {
    json_response(['success' => false, 'error' => 'Trip not found'], 404);
}
if ($trip['status'] !== 'active') {
    json_response(['success' => false, 'error' => 'Trip is not active'], 400);
}

// Store the GPS point
$stmt = $pdo->prepare(
    "INSERT INTO gps_locations
        (trip_id, driver_id, latitude, longitude, speed_kmh, heading_deg, recorded_at)
     VALUES
        (:tid, :did, :lat, :lng, :spd, :hdg, NOW())"
);
$stmt->execute([
    ':tid' => $tripId,
    ':did' => $driverId,
    ':lat' => $latitude,
    ':lng' => $longitude,
    ':spd' => $speed,
    ':hdg' => $heading,
]);

// Find nearby black spots (within 5 km)
$radiusKm = 5.0;
$stmt = $pdo->query(
    "SELECT blackspot_id, road_name, latitude, longitude,
            risk_level, risk_score, accident_count
     FROM accident_blackspots
     WHERE status = 'active'"
);
$allBlackspots = $stmt->fetchAll();

$nearby = [];
foreach ($allBlackspots as $bs) {
    $distKm = haversine_km(
        $latitude, $longitude,
        (float) $bs['latitude'], (float) $bs['longitude']
    );
    if ($distKm <= $radiusKm) {
        $bs['distance_km'] = round($distKm, 3);
        $bs['distance_m']  = (int) round($distKm * 1000);
        $nearby[] = $bs;
    }
}

// Sort by distance
usort($nearby, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

// Build warning if within critical threshold
$warning = null;
$criticalThresholdM = 500;
if (!empty($nearby)) {
    $nearest = $nearby[0];
    if ($nearest['distance_m'] <= $criticalThresholdM
        && in_array($nearest['risk_level'], ['HIGH', 'CRITICAL'], true)) {
        $warning = [
            'level'       => $nearest['risk_level'],
            'message'     => sprintf(
                '%s risk area %dm ahead: %s',
                $nearest['risk_level'],
                $nearest['distance_m'],
                $nearest['road_name']
            ),
            'blackspot_id' => (int) $nearest['blackspot_id'],
            'road_name'    => $nearest['road_name'],
            'distance_m'   => $nearest['distance_m'],
        ];

        // Log the warning (debounced: once per 2 min per blackspot)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM driver_violations
             WHERE driver_id = :did
               AND trip_id = :tid
               AND event_type = 'blackspot_warning'
               AND occurred_at >= (NOW() - INTERVAL 2 MINUTE)"
        );
        $stmt->execute([':did' => $driverId, ':tid' => $tripId]);
        $recentWarnings = (int) $stmt->fetchColumn();

        if ($recentWarnings === 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO driver_violations
                    (driver_id, vehicle_id, trip_id, event_type, severity,
                     confidence, latitude, longitude, description, status, occurred_at)
                 VALUES
                    (:did, NULL, :tid, 'blackspot_warning', 'medium',
                     1.0, :lat, :lng, :desc, 'open', NOW())"
            );
            $stmt->execute([
                ':did'  => $driverId,
                ':tid'  => $tripId,
                ':lat'  => $latitude,
                ':lng'  => $longitude,
                ':desc' => $warning['message'],
            ]);
        }
    }
}

json_response([
    'success'         => true,
    'stored'          => true,
    'nearby_count'    => count($nearby),
    'nearby'          => $nearby,
    'warning'         => $warning,
    'server_time'     => date('c'),
]);