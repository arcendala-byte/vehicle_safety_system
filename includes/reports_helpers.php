<?php
/**
 * ============================================================
 * REPORTS HELPERS — Shared query functions for admin reports
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_queries.php';

/* ------------------------------------------------------------
 * DRIVER SAFETY REPORT
 * ------------------------------------------------------------ */

function report_driver_safety(?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = Database::getConnection();
    $where = [];
    $params = [];

    if ($dateFrom) { $where[] = "d.created_at >= :df"; $params[':df'] = $dateFrom . ' 00:00:00'; }
    if ($dateTo)   { $where[] = "d.created_at <= :dt"; $params[':dt'] = $dateTo   . ' 23:59:59'; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT
                d.driver_id,
                d.full_name,
                d.license_number,
                d.current_score,
                d.status,
                (SELECT COUNT(*) FROM trips t WHERE t.driver_id = d.driver_id AND t.status = 'completed') AS completed_trips,
                (SELECT COUNT(*) FROM driver_violations v WHERE v.driver_id = d.driver_id) AS total_violations,
                (SELECT COALESCE(SUM(t.distance_km),0) FROM trips t WHERE t.driver_id = d.driver_id AND t.status = 'completed') AS total_km,
                (SELECT COUNT(*) FROM driver_violations v WHERE v.driver_id = d.driver_id AND v.severity IN ('critical','high')) AS severe_violations
            FROM drivers d
            {$whereSql}
            ORDER BY d.current_score DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * VIOLATIONS REPORT
 * ------------------------------------------------------------ */

function report_violations(array $filters = []): array
{
    return list_violations(array_merge($filters, ['per_page' => 500]))['rows'];
}

function report_violations_summary(?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = Database::getConnection();
    $where = [];
    $params = [];
    if ($dateFrom) { $where[] = "DATE(occurred_at) >= :df"; $params[':df'] = $dateFrom; }
    if ($dateTo)   { $where[] = "DATE(occurred_at) <= :dt"; $params[':dt'] = $dateTo; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT event_type, severity, COUNT(*) AS total
        FROM driver_violations
        {$whereSql}
        GROUP BY event_type, severity
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * TRIPS REPORT
 * ------------------------------------------------------------ */

function report_trips(?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = Database::getConnection();
    $where = [];
    $params = [];
    if ($dateFrom) { $where[] = "DATE(t.start_time) >= :df"; $params[':df'] = $dateFrom; }
    if ($dateTo)   { $where[] = "DATE(t.start_time) <= :dt"; $params[':dt'] = $dateTo; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT t.trip_id, t.driver_id, t.vehicle_id,
               t.start_time, t.end_time, t.distance_km,
               t.violation_count, t.safety_score, t.status,
               d.full_name AS driver_name,
               v.registration_number
        FROM trips t
        JOIN drivers d ON d.driver_id = t.driver_id
        JOIN vehicles v ON v.vehicle_id = t.vehicle_id
        {$whereSql}
        ORDER BY t.start_time DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function report_fleet_utilization(): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->query("
        SELECT
            v.vehicle_id,
            v.registration_number,
            v.make, v.model, v.vehicle_type, v.status,
            d.full_name AS driver_name,
            (SELECT COUNT(*) FROM trips t WHERE t.vehicle_id = v.vehicle_id AND t.status='completed') AS trip_count,
            (SELECT COALESCE(SUM(t.distance_km),0) FROM trips t WHERE t.vehicle_id = v.vehicle_id) AS total_km
        FROM vehicles v
        LEFT JOIN drivers d ON d.driver_id = v.assigned_driver_id
        ORDER BY total_km DESC
    ");
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * BLACK-SPOT REPORT
 * ------------------------------------------------------------ */

function report_blackspots(): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->query("
        SELECT
            blackspot_id, road_name, description,
            latitude, longitude, risk_level, risk_score,
            accident_count, road_condition, traffic_density,
            weather_typical, last_updated
        FROM accident_blackspots
        WHERE status = 'active'
        ORDER BY risk_score DESC
    ");
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * ANALYTICS — Trend data
 * ------------------------------------------------------------ */

function report_violation_trend(int $days = 30): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT DATE(occurred_at) AS day, COUNT(*) AS total
        FROM driver_violations
        WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
        GROUP BY DATE(occurred_at)
        ORDER BY day ASC
    ");
    $stmt->bindValue(':d', $days, PDO::PARAM_INT);
    $stmt->execute();

    $map = [];
    foreach ($stmt->fetchAll() as $r) { $map[$r['day']] = (int) $r['total']; }

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $result[$d] = $map[$d] ?? 0;
    }
    return $result;
}

function report_trip_trend(int $days = 30): array
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT DATE(start_time) AS day, COUNT(*) AS total, COALESCE(SUM(distance_km),0) AS km
        FROM trips
        WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
          AND status = 'completed'
        GROUP BY DATE(start_time)
        ORDER BY day ASC
    ");
    $stmt->bindValue(':d', $days, PDO::PARAM_INT);
    $stmt->execute();

    $map = [];
    foreach ($stmt->fetchAll() as $r) { $map[$r['day']] = $r; }

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $result[$d] = [
            'trips' => isset($map[$d]) ? (int) $map[$d]['total'] : 0,
            'km'    => isset($map[$d]) ? (float) $map[$d]['km'] : 0,
        ];
    }
    return $result;
}

/* ------------------------------------------------------------
 * SYSTEM SUMMARY
 * ------------------------------------------------------------ */

function report_system_summary(): array
{
    $pdo = Database::getConnection();
    return [
        'total_drivers'       => (int) $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='active'")->fetchColumn(),
        'total_vehicles'      => (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='active'")->fetchColumn(),
        'total_trips'         => (int) $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
        'completed_trips'     => (int) $pdo->query("SELECT COUNT(*) FROM trips WHERE status='completed'")->fetchColumn(),
        'active_trips'        => (int) $pdo->query("SELECT COUNT(*) FROM trips WHERE status='active'")->fetchColumn(),
        'total_violations'    => (int) $pdo->query("SELECT COUNT(*) FROM driver_violations")->fetchColumn(),
        'critical_violations' => (int) $pdo->query("SELECT COUNT(*) FROM driver_violations WHERE severity='critical'")->fetchColumn(),
        'blackspots'          => (int) $pdo->query("SELECT COUNT(*) FROM accident_blackspots WHERE status='active'")->fetchColumn(),
        'avg_score'           => (float) $pdo->query("SELECT COALESCE(AVG(current_score),0) FROM drivers WHERE status='active'")->fetchColumn(),
        'total_km'            => (float) $pdo->query("SELECT COALESCE(SUM(distance_km),0) FROM trips WHERE status='completed'")->fetchColumn(),
    ];
}
