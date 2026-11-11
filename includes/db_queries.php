<?php
/**
 * ============================================================
 * DATABASE QUERY HELPERS
 * ============================================================
 * Centralized, reusable query functions using PDO prepared
 * statements. No raw SQL in page files.
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/pagination.php';

/* ------------------------------------------------------------
 * DASHBOARD KPI COUNTS
 * ------------------------------------------------------------ */

function get_total_drivers(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM drivers WHERE status = 'active'")
        ->fetchColumn();
}

function get_total_vehicles(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")
        ->fetchColumn();
}

function get_active_trips(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM trips WHERE status = 'active'")
        ->fetchColumn();
}

function get_total_violations(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM driver_violations")
        ->fetchColumn();
}

function get_open_violations(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM driver_violations WHERE status IN ('open','acknowledged')")
        ->fetchColumn();
}

function get_high_risk_blackspots(): int
{
    return (int) Database::getConnection()
        ->query("SELECT COUNT(*) FROM accident_blackspots
                 WHERE status = 'active'
                   AND risk_level IN ('HIGH','CRITICAL')")
        ->fetchColumn();
}

function get_average_safety_score(): float
{
    $avg = Database::getConnection()
        ->query("SELECT AVG(current_score) FROM drivers WHERE status = 'active'")
        ->fetchColumn();
    return $avg !== null ? round((float) $avg, 1) : 0.0;
}

/* ------------------------------------------------------------
 * CHART DATA
 * ------------------------------------------------------------ */

/**
 * Violations grouped by event_type. Returns:
 * [ ['event_type' => 'phone_usage', 'total' => 12], ... ]
 */
function get_violations_by_type(): array
{
    $sql = "SELECT event_type, COUNT(*) AS total
            FROM driver_violations
            GROUP BY event_type
            ORDER BY total DESC";
    return Database::getConnection()->query($sql)->fetchAll();
}

/**
 * Violations per day for the last N days.
 * Returns ['YYYY-MM-DD' => count, ...] — fills missing days with 0.
 */
function get_violations_last_days(int $days = 7): array
{
    $pdo = Database::getConnection();

    $sql = "SELECT DATE(occurred_at) AS day, COUNT(*) AS total
            FROM driver_violations
            WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(occurred_at)
            ORDER BY day ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Build a date-indexed map, then fill in zeros
    $map = [];
    foreach ($rows as $row) {
        $map[$row['day']] = (int) $row['total'];
    }

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $result[$d] = $map[$d] ?? 0;
    }
    return $result;
}

/**
 * Black spots grouped by risk level.
 */
function get_blackspots_by_risk(): array
{
    $sql = "SELECT risk_level, COUNT(*) AS total
            FROM accident_blackspots
            WHERE status = 'active'
            GROUP BY risk_level
            ORDER BY FIELD(risk_level,'CRITICAL','HIGH','MEDIUM','LOW')";
    return Database::getConnection()->query($sql)->fetchAll();
}

/* ------------------------------------------------------------
 * RECENT ACTIVITY TABLES
 * ------------------------------------------------------------ */

function get_recent_violations(int $limit = 8): array
{
    $sql = "SELECT v.violation_id, v.event_type, v.severity, v.confidence,
                   v.occurred_at, v.status,
                   d.full_name AS driver_name,
                   veh.registration_number
            FROM driver_violations v
            JOIN drivers d       ON d.driver_id   = v.driver_id
            LEFT JOIN vehicles veh ON veh.vehicle_id = v.vehicle_id
            ORDER BY v.occurred_at DESC
            LIMIT :lim";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_recent_trips(int $limit = 8): array
{
    $sql = "SELECT t.trip_id, t.start_time, t.end_time, t.distance_km,
                   t.violation_count, t.safety_score, t.status,
                   d.full_name AS driver_name,
                   veh.registration_number
            FROM trips t
            JOIN drivers d        ON d.driver_id    = t.driver_id
            JOIN vehicles veh     ON veh.vehicle_id = t.vehicle_id
            ORDER BY t.start_time DESC
            LIMIT :lim";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_top_drivers(int $limit = 5): array
{
    $sql = "SELECT driver_id, full_name, current_score, license_number
            FROM drivers
            WHERE status = 'active'
            ORDER BY current_score DESC
            LIMIT :lim";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_top_blackspots(int $limit = 5): array
{
    $sql = "SELECT blackspot_id, road_name, risk_level, risk_score,
                   accident_count, latitude, longitude
            FROM accident_blackspots
            WHERE status = 'active'
            ORDER BY risk_score DESC
            LIMIT :lim";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * FORMATTING HELPERS
 * ------------------------------------------------------------ */

/**
 * Human-friendly label for an event type.
 * seatbelt_violation → "Seatbelt Violation"
 */
function format_event_type(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

/**
 * Human-friendly label for a severity or status.
 */
function format_label(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

/**
 * Map a severity/status to a CSS badge class.
 */
function badge_class(string $value): string
{
    return match ($value) {
        'critical', 'CRITICAL', 'fatal', 'high', 'HIGH', 'suspended' => 'badge--danger',
        'medium',   'MEDIUM',   'moderate', 'warning', 'open'        => 'badge--warning',
        'low',      'LOW',      'minor', 'resolved', 'active'        => 'badge--success',
        'inactive', 'dismissed','cancelled', 'acknowledged'           => 'badge--neutral',
        default                                                       => 'badge--info',
    };
}
/* ------------------------------------------------------------
 * DRIVERS — LISTING, SEARCH, FILTER, PAGINATION
 * ------------------------------------------------------------ */

/**
 * Return a paginated, filtered list of drivers.
 *
 * @param array $opts [
 *     'q'        => search term (name / phone / license / email),
 *     'status'   => 'active'|'inactive'|'suspended'|'' (all),
 *     'sort'     => 'name'|'score'|'created'|'status',
 *     'dir'      => 'asc'|'desc',
 *     'page'     => int,
 *     'per_page' => int,
 * ]
 * @return array ['rows'=>[], 'total'=>int, 'meta'=>paginate()]
 */
function list_drivers(array $opts = []): array
{
    $q       = trim((string) ($opts['q'] ?? ''));
    $status  = (string) ($opts['status'] ?? '');
    $sort    = (string) ($opts['sort']   ?? 'created');
    $dir     = strtolower((string) ($opts['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $page    = max(1, (int) ($opts['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 10)));

    // Whitelist sortable columns
    $sortMap = [
        'name'    => 'd.full_name',
        'score'   => 'd.current_score',
        'created' => 'd.created_at',
        'status'  => 'd.status',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['created'];

    $where  = [];
    $params = [];

   if ($q !== '') {
    $where[] = '(d.full_name LIKE :q_name OR d.phone LIKE :q_phone OR d.license_number LIKE :q_lic OR d.email LIKE :q_email)';
    $params[':q_name']  = '%' . $q . '%';
    $params[':q_phone'] = '%' . $q . '%';
    $params[':q_lic']   = '%' . $q . '%';
    $params[':q_email'] = '%' . $q . '%';
}

    if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $where[] = 'd.status = :status';
        $params[':status'] = $status;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $pdo = Database::getConnection();

    // Count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM drivers d {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $meta = paginate($total, $page, $perPage);

    // Fetch the page
    $sql = "SELECT d.driver_id, d.user_id, d.full_name, d.phone, d.email,
                   d.license_number, d.license_expiry, d.status,
                   d.current_score, d.created_at,
                   (SELECT COUNT(*) FROM vehicles v WHERE v.assigned_driver_id = d.driver_id) AS vehicle_count
            FROM drivers d
            {$whereSql}
            ORDER BY {$orderBy} {$dir}
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $meta['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $meta['offset'],   PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'meta'  => $meta,
    ];
}

/**
 * Get a single driver by ID with joined vehicle info.
 */
function get_driver(int $driverId): ?array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT d.*, u.username, u.last_login
         FROM drivers d
         LEFT JOIN users u ON u.user_id = d.user_id
         WHERE d.driver_id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $driverId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Check if a license number is already used by another driver.
 */
function license_in_use(string $license, int $exceptDriverId = 0): bool
{
    $stmt = Database::getConnection()->prepare(
        "SELECT COUNT(*) FROM drivers WHERE license_number = :l AND driver_id <> :id"
    );
    $stmt->execute([':l' => $license, ':id' => $exceptDriverId]);
    return (int) $stmt->fetchColumn() > 0;
}

/* ------------------------------------------------------------
 * VEHICLES — LISTING, SEARCH, FILTER, PAGINATION
 * ------------------------------------------------------------ */

function list_vehicles(array $opts = []): array
{
    $q       = trim((string) ($opts['q'] ?? ''));
    $status  = (string) ($opts['status'] ?? '');
    $type    = (string) ($opts['type']   ?? '');
    $sort    = (string) ($opts['sort']   ?? 'created');
    $dir     = strtolower((string) ($opts['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $page    = max(1, (int) ($opts['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 10)));

    $sortMap = [
        'created' => 'v.created_at',
        'reg'     => 'v.registration_number',
        'make'    => 'v.make',
        'type'    => 'v.vehicle_type',
        'status'  => 'v.status',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['created'];

    $where  = [];
    $params = [];

    if ($q !== '') {
    $where[] = '(d.full_name LIKE :q_driver OR veh.registration_number LIKE :q_reg OR v.description LIKE :q_desc)';
    $params[':q_driver'] = '%' . $q . '%';
    $params[':q_reg']    = '%' . $q . '%';
    $params[':q_desc']   = '%' . $q . '%';
}

    if (in_array($status, ['active', 'inactive', 'maintenance'], true)) {
        $where[] = 'v.status = :status';
        $params[':status'] = $status;
    }

    if (in_array($type, ['private', 'commercial', 'psv', 'truck', 'bus', 'other'], true)) {
        $where[] = 'v.vehicle_type = :type';
        $params[':type'] = $type;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles v {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $meta = paginate($total, $page, $perPage);

    $sql = "SELECT v.vehicle_id, v.registration_number, v.make, v.model, v.year,
                   v.vehicle_type, v.status, v.created_at,
                   d.driver_id, d.full_name AS driver_name
            FROM vehicles v
            LEFT JOIN drivers d ON d.driver_id = v.assigned_driver_id
            {$whereSql}
            ORDER BY {$orderBy} {$dir}
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $meta['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $meta['offset'],   PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows'  => $stmt->fetchAll(),
        'total' => $total,
        'meta'  => $meta,
    ];
}

/* ------------------------------------------------------------
 * TRIPS — LISTING, SEARCH, FILTER, PAGINATION
 * ------------------------------------------------------------ */

function list_trips(array $opts = []): array
{
    $driverId = (int) ($opts['driver_id'] ?? 0);
    $vehicleId= (int) ($opts['vehicle_id'] ?? 0);
    $status   = (string) ($opts['status'] ?? '');
    $dateFrom = (string) ($opts['date_from'] ?? '');
    $dateTo   = (string) ($opts['date_to']   ?? '');
    $sort     = (string) ($opts['sort'] ?? 'started');
    $dir      = strtolower((string) ($opts['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $page     = max(1, (int) ($opts['page'] ?? 1));
    $perPage  = max(1, min(100, (int) ($opts['per_page'] ?? 10)));

    $sortMap = [
        'started' => 't.start_time',
        'distance'=> 't.distance_km',
        'score'   => 't.safety_score',
        'status'  => 't.status',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['started'];

    $where  = [];
    $params = [];

    if ($driverId > 0) {
        $where[] = 't.driver_id = :driver_id';
        $params[':driver_id'] = $driverId;
    }
    if ($vehicleId > 0) {
        $where[] = 't.vehicle_id = :vehicle_id';
        $params[':vehicle_id'] = $vehicleId;
    }
    if (in_array($status, ['active', 'completed', 'cancelled'], true)) {
        $where[] = 't.status = :status';
        $params[':status'] = $status;
    }
    if ($dateFrom !== '') {
        $where[] = 'DATE(t.start_time) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'DATE(t.start_time) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips t {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $meta = paginate($total, $page, $perPage);

    $sql = "SELECT t.trip_id, t.start_time, t.end_time, t.distance_km,
                   t.violation_count, t.safety_score, t.status,
                   d.driver_id, d.full_name AS driver_name,
                   v.vehicle_id, v.registration_number
            FROM trips t
            JOIN drivers  d ON d.driver_id  = t.driver_id
            JOIN vehicles v ON v.vehicle_id = t.vehicle_id
            {$whereSql}
            ORDER BY {$orderBy} {$dir}
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
 * Lightweight driver + vehicle lookup lists for filter dropdowns.
 */
function list_drivers_minimal(): array
{
    return Database::getConnection()->query(
        "SELECT driver_id, full_name FROM drivers ORDER BY full_name ASC"
    )->fetchAll();
}

function list_vehicles_minimal(): array
{
    return Database::getConnection()->query(
        "SELECT vehicle_id, registration_number FROM vehicles ORDER BY registration_number ASC"
    )->fetchAll();
}
/* ------------------------------------------------------------
 * VIOLATIONS � LISTING, SEARCH, FILTER, PAGINATION
 * ------------------------------------------------------------ */

function list_violations(array $opts = []): array
{
    $driverId  = (int)    ($opts['driver_id']  ?? 0);
    $vehicleId = (int)    ($opts['vehicle_id'] ?? 0);
    $eventType = (string) ($opts['event_type'] ?? '');
    $severity  = (string) ($opts['severity']   ?? '');
    $status    = (string) ($opts['status']     ?? '');
    $dateFrom  = (string) ($opts['date_from']  ?? '');
    $dateTo    = (string) ($opts['date_to']    ?? '');
    $q         = trim((string) ($opts['q']     ?? ''));
    $sort      = (string) ($opts['sort']       ?? 'occurred');
    $dir       = strtolower((string) ($opts['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $page      = max(1, (int) ($opts['page'] ?? 1));
    $perPage   = max(1, min(100, (int) ($opts['per_page'] ?? 15)));

    $sortMap = [
        'occurred' => 'v.occurred_at',
        'severity' => "FIELD(v.severity,'critical','high','medium','low')",
        'status'   => 'v.status',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['occurred'];

    $where  = [];
    $params = [];

    if ($driverId > 0) {
        $where[] = 'v.driver_id = :driver_id';
        $params[':driver_id'] = $driverId;
    }
    if ($vehicleId > 0) {
        $where[] = 'v.vehicle_id = :vehicle_id';
        $params[':vehicle_id'] = $vehicleId;
    }
    if ($eventType !== '') {
        $where[] = 'v.event_type = :event_type';
        $params[':event_type'] = $eventType;
    }
    if ($severity !== '') {
        $where[] = 'v.severity = :severity';
        $params[':severity'] = $severity;
    }
    if ($status !== '') {
        $where[] = 'v.status = :status';
        $params[':status'] = $status;
    }
    if ($dateFrom !== '') {
        $where[] = 'DATE(v.occurred_at) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'DATE(v.occurred_at) <= :date_to';
        $params[':date_to'] = $dateTo;
    }
    if ($q !== '') {
        $where[] = '(d.full_name LIKE :q_driver OR veh.registration_number LIKE :q_reg OR v.description LIKE :q_desc)';
        $params[':q_driver'] = '%' . $q . '%';
        $params[':q_reg']    = '%' . $q . '%';
        $params[':q_desc']   = '%' . $q . '%';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $pdo = Database::getConnection();

    $countSql = "SELECT COUNT(*)
                 FROM driver_violations v
                 LEFT JOIN drivers  d   ON d.driver_id   = v.driver_id
                 LEFT JOIN vehicles veh ON veh.vehicle_id = v.vehicle_id
                 {$whereSql}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $meta = paginate($total, $page, $perPage);

    $sql = "SELECT
                v.violation_id, v.event_type, v.severity, v.confidence,
                v.latitude, v.longitude, v.description, v.status, v.occurred_at,
                d.driver_id,  d.full_name AS driver_name,
                veh.vehicle_id, veh.registration_number,
                v.trip_id
            FROM driver_violations v
            LEFT JOIN drivers  d   ON d.driver_id   = v.driver_id
            LEFT JOIN vehicles veh ON veh.vehicle_id = v.vehicle_id
            {$whereSql}
            ORDER BY {$orderBy} {$dir}
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