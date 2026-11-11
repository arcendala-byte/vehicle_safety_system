<?php
/**
 * ============================================================
 * TRIP-SPECIFIC HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_queries.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/vehicle_helpers.php';

/* ------------------------------------------------------------
 * READ
 * ------------------------------------------------------------ */

/**
 * Get full trip details with driver + vehicle info + score summary.
 */
function get_trip(int $tripId): ?array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT
            t.*,
            d.full_name      AS driver_name,
            d.license_number AS driver_license,
            d.current_score  AS driver_score,
            v.registration_number,
            v.make, v.model, v.vehicle_type
         FROM trips t
         JOIN drivers  d ON d.driver_id  = t.driver_id
         JOIN vehicles v ON v.vehicle_id = t.vehicle_id
         WHERE t.trip_id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $tripId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get GPS breadcrumbs for a trip, ordered by time.
 */
function get_trip_gps_points(int $tripId, int $limit = 500): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT location_id, latitude, longitude, speed_kmh, heading_deg, recorded_at
         FROM gps_locations
         WHERE trip_id = :id
         ORDER BY recorded_at ASC
         LIMIT :lim"
    );
    $stmt->bindValue(':id',  $tripId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get violations that occurred during a specific trip.
 */
function get_trip_violations(int $tripId): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT violation_id, event_type, severity, confidence,
                latitude, longitude, description, status, occurred_at
         FROM driver_violations
         WHERE trip_id = :id
         ORDER BY occurred_at ASC"
    );
    $stmt->execute([':id' => $tripId]);
    return $stmt->fetchAll();
}

/**
 * Return trips for a specific driver.
 */
function list_driver_trips(int $driverId, array $opts = []): array
{
    $status  = (string) ($opts['status'] ?? '');
    $page    = max(1, (int) ($opts['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 10)));

    $where  = ['t.driver_id = :driver'];
    $params = [':driver' => $driverId];

    if (in_array($status, ['active', 'completed', 'cancelled'], true)) {
        $where[] = 't.status = :status';
        $params[':status'] = $status;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips t {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $meta = paginate($total, $page, $perPage);

    $sql = "SELECT t.trip_id, t.start_time, t.end_time, t.distance_km,
                   t.violation_count, t.safety_score, t.status,
                   v.registration_number
            FROM trips t
            JOIN vehicles v ON v.vehicle_id = t.vehicle_id
            {$whereSql}
            ORDER BY t.start_time DESC
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $meta['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $meta['offset'],   PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'meta' => $meta];
}

/**
 * Get the currently active trip for a driver, if any.
 * A driver can only have one active trip at a time.
 */
function get_active_trip_for_driver(int $driverId): ?array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT t.*, v.registration_number
         FROM trips t
         JOIN vehicles v ON v.vehicle_id = t.vehicle_id
         WHERE t.driver_id = :id AND t.status = 'active'
         ORDER BY t.start_time DESC
         LIMIT 1"
    );
    $stmt->execute([':id' => $driverId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Check if a vehicle already has an active trip.
 */
function vehicle_has_active_trip(int $vehicleId): bool
{
    $stmt = Database::getConnection()->prepare(
        "SELECT COUNT(*) FROM trips WHERE vehicle_id = :id AND status = 'active'"
    );
    $stmt->execute([':id' => $vehicleId]);
    return (int) $stmt->fetchColumn() > 0;
}

/* ------------------------------------------------------------
 * WRITE
 * ------------------------------------------------------------ */

/**
 * Start a new trip. Returns the new trip_id.
 * Refuses if driver already has an active trip, or vehicle is in use.
 */
function start_trip(int $driverId, int $vehicleId, ?float $lat = null, ?float $lng = null): array
{
    // Guard: driver already active
    if (get_active_trip_for_driver($driverId)) {
        return ['ok' => false, 'message' => 'You already have an active trip.'];
    }

    // Guard: vehicle in use
    if (vehicle_has_active_trip($vehicleId)) {
        return ['ok' => false, 'message' => 'This vehicle is already on an active trip.'];
    }

    // Guard: vehicle status must be active
    $veh = get_vehicle($vehicleId);
    if (!$veh) {
        return ['ok' => false, 'message' => 'Vehicle not found.'];
    }
    if ($veh['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Vehicle is not available (status: ' . $veh['status'] . ').'];
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO trips
            (driver_id, vehicle_id, start_time, start_latitude, start_longitude, status, distance_km)
         VALUES
            (:d, :v, NOW(), :lat, :lng, 'active', 0.00)"
    );
    $stmt->execute([
        ':d'   => $driverId,
        ':v'   => $vehicleId,
        ':lat' => $lat,
        ':lng' => $lng,
    ]);

    return ['ok' => true, 'trip_id' => (int) $pdo->lastInsertId()];
}

/**
 * End an active trip. Calculates distance from GPS trail,
 * counts violations, computes a simple safety score.
 */
function end_trip(int $tripId): array
{
    $pdo = Database::getConnection();

    // Load trip
    $trip = get_trip($tripId);
    if (!$trip) {
        return ['ok' => false, 'message' => 'Trip not found.'];
    }
    if ($trip['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Only active trips can be ended.'];
    }

    // Distance from GPS trail (haversine sum)
    $points = get_trip_gps_points($tripId, 2000);
    $distanceKm = 0.0;
    for ($i = 1; $i < count($points); $i++) {
        $distanceKm += haversine_km(
            (float) $points[$i - 1]['latitude'],  (float) $points[$i - 1]['longitude'],
            (float) $points[$i]['latitude'],      (float) $points[$i]['longitude']
        );
    }

    // Violations during trip
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM driver_violations WHERE trip_id = :id");
    $stmt->execute([':id' => $tripId]);
    $violationCount = (int) $stmt->fetchColumn();

    // Compute safety score using configured penalties
    $score = SCORE_START;
    $stmt = $pdo->prepare("SELECT event_type FROM driver_violations WHERE trip_id = :id");
    $stmt->execute([':id' => $tripId]);
    foreach ($stmt->fetchAll() as $row) {
        $penalty = $GLOBALS['SCORE_PENALTIES'][$row['event_type']] ?? 5;
        $score -= $penalty;
    }
    $score = max(0, min(100, $score));

    // Final GPS point → end coordinates
    $last = end($points) ?: null;

    // Update trip
    $stmt = $pdo->prepare(
        "UPDATE trips SET
            end_time        = NOW(),
            end_latitude    = :lat,
            end_longitude   = :lng,
            distance_km     = :dist,
            violation_count = :vcount,
            safety_score    = :score,
            status          = 'completed'
         WHERE trip_id = :id"
    );
    $stmt->execute([
        ':lat'   => $last['latitude']  ?? null,
        ':lng'   => $last['longitude'] ?? null,
        ':dist'  => round($distanceKm, 2),
        ':vcount'=> $violationCount,
        ':score' => $score,
        ':id'    => $tripId,
    ]);

    // Update driver's current_score & log a safety score history entry
    $stmt = $pdo->prepare(
        "UPDATE drivers SET current_score = :s WHERE driver_id = :d"
    );
    $stmt->execute([':s' => $score, ':d' => $trip['driver_id']]);

    $stmt = $pdo->prepare(
        "INSERT INTO driver_safety_scores
            (driver_id, trip_id, score, previous_score, violation_count, reason, calculated_at)
         VALUES
            (:d, :t, :s, :prev, :vc, :reason, NOW())"
    );
    $stmt->execute([
        ':d'      => $trip['driver_id'],
        ':t'      => $tripId,
        ':s'      => $score,
        ':prev'   => $trip['driver_score'],
        ':vc'     => $violationCount,
        ':reason' => "Trip #{$tripId} ended — {$violationCount} violation(s)",
    ]);

    return [
        'ok'          => true,
        'distance_km' => round($distanceKm, 2),
        'violations'  => $violationCount,
        'score'       => $score,
    ];
}

/**
 * Admin-only: cancel an active trip without scoring.
 */
function cancel_trip(int $tripId): array
{
    $trip = get_trip($tripId);
    if (!$trip) {
        return ['ok' => false, 'message' => 'Trip not found.'];
    }
    if ($trip['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Only active trips can be cancelled.'];
    }

    $stmt = Database::getConnection()->prepare(
        "UPDATE trips SET status = 'cancelled', end_time = NOW() WHERE trip_id = :id"
    );
    $stmt->execute([':id' => $tripId]);
    return ['ok' => true, 'message' => 'Trip cancelled.'];
}

/**
 * Admin-only: delete a trip (only completed/cancelled).
 * GPS locations cascade-delete; violations have trip_id set to NULL.
 */
function delete_trip(int $tripId): array
{
    $trip = get_trip($tripId);
    if (!$trip) {
        return ['ok' => false, 'message' => 'Trip not found.'];
    }
    if ($trip['status'] === 'active') {
        return ['ok' => false, 'message' => 'Cannot delete an active trip. Cancel it first.'];
    }

    $stmt = Database::getConnection()->prepare("DELETE FROM trips WHERE trip_id = :id");
    $stmt->execute([':id' => $tripId]);
    return ['ok' => true, 'message' => 'Trip deleted.'];
}

/* ------------------------------------------------------------
 * UTILITIES
 * ------------------------------------------------------------ */

/**
 * Great-circle distance in km.
 */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Human-readable duration from two timestamps.
 */
function format_duration(?string $start, ?string $end): string
{
    if (!$start) return '—';
    if (!$end)   return 'In progress';

    $s = strtotime($start);
    $e = strtotime($end);
    $diff = max(0, $e - $s);

    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}