<?php
/**
 * ============================================================
 * API: Black-Spot Risk Prediction Proxy
 * ============================================================
 * POST /api/blackspots/predict.php
 * Body: { latitude, longitude, road_condition?, weather?, traffic_density?, accident_count? }
 *
 * Forwards to Flask /api/predict/blackspot, returns JSON.
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}
if (current_role() !== 'admin' && current_role() !== 'driver') {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

$lat = isset($input['latitude'])  ? (float) $input['latitude']  : null;
$lng = isset($input['longitude']) ? (float) $input['longitude'] : null;

if ($lat === null || $lng === null) {
    json_response(['success' => false, 'error' => 'Missing latitude or longitude'], 400);
}

$payload = [
    'latitude'        => $lat,
    'longitude'       => $lng,
    'road_condition'  => $input['road_condition']  ?? 'fair',
    'weather'         => $input['weather']         ?? 'clear',
    'traffic_density' => $input['traffic_density'] ?? 'medium',
    'accident_count'  => (int) ($input['accident_count'] ?? 0),
];

// ---- Forward to Flask ----
$ch = curl_init(AI_SERVICE_URL . '/api/predict/blackspot');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
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
    json_response(['success' => false, 'error' => 'AI service unreachable', 'detail' => $curlErr], 502);
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    json_response(['success' => false, 'error' => 'Invalid response from AI service'], 502);
}

http_response_code($httpCode);
echo json_encode($decoded);
