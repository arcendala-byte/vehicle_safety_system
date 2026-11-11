<?php
/**
 * ============================================================
 * API: Live Monitoring — Detect All
 * ============================================================
 * POST /api/monitoring/detect.php
 * Body: { trip_id?, image_base64 }
 *
 * - Forwards to Flask /api/detect/all
 * - Auto-logs violations for detected events (with cooldown)
 * - Returns full detection payload
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['image_base64'])) {
    json_response(['success' => false, 'error' => 'Missing image_base64'], 400);
}

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);
$tripId   = (int) ($input['trip_id'] ?? 0);

if ($driverId <= 0) {
    json_response(['success' => false, 'error' => 'No driver profile linked'], 403);
}

$pdo = Database::getConnection();

// Optional: get the driver's assigned vehicle
$stmt = $pdo->prepare(
    "SELECT vehicle_id FROM vehicles
     WHERE assigned_driver_id = :id AND status = 'active'
     ORDER BY vehicle_id ASC LIMIT 1"
);
$stmt->execute([':id' => $driverId]);
$vehicleId = (int) ($stmt->fetchColumn() ?: 0);
$vehicleId = $vehicleId > 0 ? $vehicleId : null;

// ---- Forward to Flask ----
$payload = [
    'driver_id'    => $driverId,
    'vehicle_id'   => $vehicleId,
    'trip_id'      => $tripId ?: null,
    'image_base64' => $input['image_base64'],
];

$ch = curl_init(AI_SERVICE_URL . '/api/detect/all');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-Key: ' . AI_API_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    json_response([
        'success' => false,
        'error'   => 'AI service unreachable',
        'detail'  => $curlErr,
    ], 502);
}

$decoded = json_decode($response, true);
if (!is_array($decoded) || empty($decoded['success'])) {
    json_response([
        'success' => false,
        'error'   => 'Invalid response from AI service',
        'detail'  => $response,
    ], 502);
}

// ---- Auto-log violations for unsafe detections ----
$logged = [];
$cooldowns = $GLOBALS['EVENT_COOLDOWNS'] ?? [];

$violationMap = [
    'seatbelt'   => ['status' => 'not_detected', 'event_type' => 'seatbelt_violation', 'severity' => 'critical'],
    'phone'      => ['status' => 'detected',     'event_type' => 'phone_usage',        'severity' => 'high'],
    'drowsiness' => ['status' => 'eyes_closed',  'event_type' => 'drowsiness',         'severity' => 'high'],
    'distraction'=> ['status' => 'distracted',   'event_type' => 'distraction',        'severity' => 'medium'],
];

foreach ($decoded['detections'] ?? [] as $d) {
    $type = $d['type'] ?? '';
    if (!isset($violationMap[$type])) continue;

    $rule = $violationMap[$type];
    $detStatus = $d['status'] ?? '';
    $confidence = (float) ($d['confidence'] ?? 0);

    // Special: drowsiness — also log for "yawning"
    $shouldLog = ($detStatus === $rule['status']);
    if ($type === 'drowsiness' && $detStatus === 'yawning') {
        $shouldLog = true;
    }

    if (!$shouldLog) continue;

    // Check cooldown
    $cooldown = $cooldowns[$rule['event_type']] ?? 30;
    if (is_within_cooldown($driverId, $rule['event_type'], $tripId ?: null, $cooldown)) {
        continue;
    }

    // Insert the violation
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO driver_violations
                (driver_id, vehicle_id, trip_id, event_type, severity,
                 confidence, latitude, longitude, description, status, occurred_at)
             VALUES
                (:did, :vid, :tid, :etype, :sev,
                 :conf, NULL, NULL, :desc, 'open', NOW())"
        );
        $stmt->execute([
            ':did'   => $driverId,
            ':vid'   => $vehicleId,
            ':tid'   => $tripId ?: null,
            ':etype' => $rule['event_type'],
            ':sev'   => $rule['severity'],
            ':conf'  => $confidence,
            ':desc'  => 'Auto-detected by live monitoring: ' . format_event_type($rule['event_type'])
                        . ' (confidence ' . round($confidence * 100) . '%)',
        ]);
        $logged[] = $rule['event_type'];
    } catch (Throwable $e) {
        error_log('[monitoring/record] ' . $e->getMessage());
    }
}

// ---- Respond with detection + logging summary ----
$decoded['logged_violations'] = $logged;
http_response_code(200);
echo json_encode($decoded);
