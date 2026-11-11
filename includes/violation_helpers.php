<?php
/**
 * ============================================================
 * VIOLATION / SAFETY EVENT HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/db_queries.php';

/* ------------------------------------------------------------
 * STATUS CONSTANTS
 * ------------------------------------------------------------ */

/**
 * The lifecycle of a violation.
 *   open         → just detected, not yet reviewed
 *   acknowledged → admin has seen it, action pending
 *   resolved     → handled / acted upon
 *   dismissed    → reviewed and judged a false positive or non-issue
 */
function violation_statuses(): array
{
    return ['open', 'acknowledged', 'resolved', 'dismissed'];
}

function violation_event_types(): array
{
    return [
        'seatbelt_violation',
        'phone_usage',
        'drowsiness',
        'distraction',
        'speeding',
        'blackspot_warning',
        'other',
    ];
}

function violation_severities(): array
{
    return ['low', 'medium', 'high', 'critical'];
}

/* ------------------------------------------------------------
 * READ
 * ------------------------------------------------------------ */

/**
 * Get a single violation with joined driver, vehicle, trip info.
 */
function get_violation(int $violationId): ?array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT
            v.*,
            d.driver_id,  d.full_name AS driver_name, d.license_number AS driver_license,
            veh.vehicle_id, veh.registration_number, veh.make, veh.model, veh.vehicle_type,
            t.trip_id,    t.start_time AS trip_start, t.end_time AS trip_end, t.status AS trip_status
         FROM driver_violations v
         JOIN drivers d        ON d.driver_id   = v.driver_id
         LEFT JOIN vehicles veh ON veh.vehicle_id = v.vehicle_id
         LEFT JOIN trips t      ON t.trip_id      = v.trip_id
         WHERE v.violation_id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $violationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List violations for a specific driver.
 */
function list_driver_violations(int $driverId, array $opts = []): array
{
    return list_violations(array_merge($opts, ['driver_id' => $driverId]));
}

/* ------------------------------------------------------------
 * WRITE
 * ------------------------------------------------------------ */

/**
 * Change a single violation's status.
 */
function set_violation_status(int $violationId, string $newStatus): array
{
    if (!in_array($newStatus, violation_statuses(), true)) {
        return ['ok' => false, 'message' => 'Invalid status.'];
    }

    $v = get_violation($violationId);
    if (!$v) {
        return ['ok' => false, 'message' => 'Violation not found.'];
    }

    $stmt = Database::getConnection()->prepare(
        "UPDATE driver_violations SET status = :s WHERE violation_id = :id"
    );
    $stmt->execute([':s' => $newStatus, ':id' => $violationId]);

    return ['ok' => true, 'message' => 'Status updated.'];
}

/**
 * Change status for a batch of violations at once.
 * Returns [updated_count, errors].
 */
function bulk_set_violation_status(array $violationIds, string $newStatus): array
{
    if (!in_array($newStatus, violation_statuses(), true)) {
        return ['updated' => 0, 'error' => 'Invalid status.'];
    }

    $violationIds = array_values(array_filter(array_map('intval', $violationIds), fn($x) => $x > 0));
    if (empty($violationIds)) {
        return ['updated' => 0, 'error' => 'No violations selected.'];
    }

    // Build a safe IN() clause with the correct number of placeholders
    $placeholders = implode(',', array_fill(0, count($violationIds), '?'));
    $sql = "UPDATE driver_violations SET status = ? WHERE violation_id IN ({$placeholders})";

    $params = array_merge([$newStatus], $violationIds);
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->execute($params);

    return ['updated' => $stmt->rowCount(), 'error' => null];
}

/* ------------------------------------------------------------
 * COOLDOWN / DEBOUNCE
 * ------------------------------------------------------------ */

/**
 * Check if a recent violation of the same type exists for the same
 * driver+trip, within the configured cooldown window.
 *
 * Used by the AI detection API to prevent one continuous unsafe
 * moment from creating dozens of duplicate database rows.
 *
 * Returns true if we should SKIP creating a new violation.
 */
function is_within_cooldown(
    int $driverId,
    string $eventType,
    ?int $tripId = null,
    ?int $overrideSeconds = null
): bool {
    $cooldown = $overrideSeconds
        ?? ($GLOBALS['EVENT_COOLDOWNS'][$eventType] ?? 30);

    $sql = "SELECT COUNT(*) FROM driver_violations
            WHERE driver_id = :driver
              AND event_type = :type
              AND occurred_at >= (NOW() - INTERVAL :secs SECOND)";
    $params = [
        ':driver' => $driverId,
        ':type'   => $eventType,
        ':secs'   => $cooldown,
    ];

    if ($tripId !== null) {
        $sql .= " AND trip_id = :trip";
        $params[':trip'] = $tripId;
    }

    $stmt = Database::getConnection()->prepare($sql);
    $stmt->execute($params);
    return ((int) $stmt->fetchColumn()) > 0;
}

/* ------------------------------------------------------------
 * STATS (used by admin dashboard / driver page)
 * ------------------------------------------------------------ */

function count_violations_by_status(?int $driverId = null): array
{
    $sql = "SELECT status, COUNT(*) AS total FROM driver_violations";
    $params = [];
    if ($driverId !== null) {
        $sql .= " WHERE driver_id = :driver";
        $params[':driver'] = $driverId;
    }
    $sql .= " GROUP BY status";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = array_fill_keys(violation_statuses(), 0);
    foreach ($rows as $r) {
        $out[$r['status']] = (int) $r['total'];
    }
    return $out;
}

function count_violations_by_severity(?int $driverId = null): array
{
    $sql = "SELECT severity, COUNT(*) AS total FROM driver_violations";
    $params = [];
    if ($driverId !== null) {
        $sql .= " WHERE driver_id = :driver";
        $params[':driver'] = $driverId;
    }
    $sql .= " GROUP BY severity";
    $stmt = Database::getConnection()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = array_fill_keys(violation_severities(), 0);
    foreach ($rows as $r) {
        $out[$r['severity']] = (int) $r['total'];
    }
    return $out;
}