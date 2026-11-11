<?php
/**
 * ============================================================
 * VEHICLE-SPECIFIC HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_queries.php';
/* ------------------------------------------------------------
 * VALIDATION
 * ------------------------------------------------------------ */

function validate_vehicle_input(array $input, int $exceptVehicleId = 0): array
{
    $v = new Validator($input);

    $v->required('registration_number', 'Registration number')
      ->max('registration_number', 20)
      ->max('make', 50)
      ->max('model', 50)
      ->inList('vehicle_type', ['private', 'commercial', 'psv', 'truck', 'bus', 'other'], 'Vehicle type')
      ->inList('status', ['active', 'inactive', 'maintenance'], 'Status');

    // Year (optional but must be valid if present)
    $year = (string) ($input['year'] ?? '');
    if ($year !== '') {
        $y = (int) $year;
        if ($y < 1980 || $y > (int) date('Y') + 1) {
            $v->fails(); // trigger to keep chain, but we override below
            $errors = $v->errors();
            $errors['year'] = 'Year must be between 1980 and next year.';
            return ['values' => collect_vehicle_values($input), 'errors' => $errors];
        }
    }

    // Driver assignment must exist if provided
    $driverId = (int) ($input['assigned_driver_id'] ?? 0);
    if ($driverId > 0 && !get_driver($driverId)) {
        $errors = $v->errors();
        $errors['assigned_driver_id'] = 'Selected driver does not exist.';
        return ['values' => collect_vehicle_values($input), 'errors' => $errors];
    }

    // Uniqueness check
    $reg = $v->value('registration_number');
    if ($reg !== '' && !isset($v->errors()['registration_number'])) {
        if (vehicle_reg_in_use($reg, $exceptVehicleId)) {
            $errors = $v->errors();
            $errors['registration_number'] = 'This registration number is already registered.';
            return ['values' => collect_vehicle_values($input), 'errors' => $errors];
        }
    }

    return [
        'values' => collect_vehicle_values($input),
        'errors' => $v->errors(),
    ];
}

function collect_vehicle_values(array $input): array
{
    return [
        'registration_number' => strtoupper(clean((string) ($input['registration_number'] ?? ''))),
        'make'                => clean((string) ($input['make']    ?? '')),
        'model'               => clean((string) ($input['model']   ?? '')),
        'year'                => clean((string) ($input['year']    ?? '')),
        'vehicle_type'        => clean((string) ($input['vehicle_type'] ?? 'private')),
        'assigned_driver_id'  => (int) ($input['assigned_driver_id'] ?? 0),
        'status'              => clean((string) ($input['status']  ?? 'active')),
    ];
}

/* ------------------------------------------------------------
 * CRUD
 * ------------------------------------------------------------ */

function create_vehicle(array $values): int
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO vehicles
            (registration_number, make, model, year, vehicle_type, assigned_driver_id, status)
         VALUES
            (:reg, :make, :model, :year, :type, :driver, :status)"
    );
    $stmt->execute([
        ':reg'    => $values['registration_number'],
        ':make'   => $values['make']   !== '' ? $values['make']   : null,
        ':model'  => $values['model']  !== '' ? $values['model']  : null,
        ':year'   => $values['year']   !== '' ? (int) $values['year'] : null,
        ':type'   => $values['vehicle_type'],
        ':driver' => $values['assigned_driver_id'] > 0 ? $values['assigned_driver_id'] : null,
        ':status' => $values['status'],
    ]);
    return (int) $pdo->lastInsertId();
}

function update_vehicle(int $vehicleId, array $values): bool
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "UPDATE vehicles SET
            registration_number = :reg,
            make                = :make,
            model               = :model,
            year                = :year,
            vehicle_type        = :type,
            assigned_driver_id  = :driver,
            status              = :status
         WHERE vehicle_id = :id"
    );
    $stmt->execute([
        ':reg'    => $values['registration_number'],
        ':make'   => $values['make']   !== '' ? $values['make']   : null,
        ':model'  => $values['model']  !== '' ? $values['model']  : null,
        ':year'   => $values['year']   !== '' ? (int) $values['year'] : null,
        ':type'   => $values['vehicle_type'],
        ':driver' => $values['assigned_driver_id'] > 0 ? $values['assigned_driver_id'] : null,
        ':status' => $values['status'],
        ':id'     => $vehicleId,
    ]);
    return $stmt->rowCount() >= 0;
}

function deactivate_vehicle(int $vehicleId): array
{
    $pdo = Database::getConnection();

    // Block if active trip
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM trips WHERE vehicle_id = :id AND status = 'active'"
    );
    $stmt->execute([':id' => $vehicleId]);
    $activeTrips = (int) $stmt->fetchColumn();

    if ($activeTrips > 0) {
        return ['ok' => false, 'message' => "Cannot deactivate: vehicle has {$activeTrips} active trip(s)."];
    }

    $stmt = $pdo->prepare("UPDATE vehicles SET status = 'inactive' WHERE vehicle_id = :id");
    $stmt->execute([':id' => $vehicleId]);
    return ['ok' => true, 'message' => 'Vehicle deactivated successfully.'];
}

/* ------------------------------------------------------------
 * LOOKUPS
 * ------------------------------------------------------------ */

function get_vehicle(int $vehicleId): ?array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT v.*, d.full_name AS driver_name, d.license_number AS driver_license, d.current_score AS driver_score
         FROM vehicles v
         LEFT JOIN drivers d ON d.driver_id = v.assigned_driver_id
         WHERE v.vehicle_id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $vehicleId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vehicle_reg_in_use(string $reg, int $exceptVehicleId = 0): bool
{
    $stmt = Database::getConnection()->prepare(
        "SELECT COUNT(*) FROM vehicles WHERE registration_number = :r AND vehicle_id <> :id"
    );
    $stmt->execute([':r' => $reg, ':id' => $exceptVehicleId]);
    return (int) $stmt->fetchColumn() > 0;
}

function list_drivers_for_select(): array
{
    $sql = "SELECT driver_id, full_name, license_number
            FROM drivers
            WHERE status = 'active'
            ORDER BY full_name ASC";
    return Database::getConnection()->query($sql)->fetchAll();
}

/**
 * Get recent trips for a vehicle.
 */
function get_vehicle_trips(int $vehicleId, int $limit = 10): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT t.trip_id, t.start_time, t.end_time, t.distance_km,
                t.violation_count, t.safety_score, t.status,
                d.full_name AS driver_name
         FROM trips t
         LEFT JOIN drivers d ON d.driver_id = t.driver_id
         WHERE t.vehicle_id = :id
         ORDER BY t.start_time DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':id',  $vehicleId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,     PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get recent violations for a vehicle.
 */
function get_vehicle_violations(int $vehicleId, int $limit = 10): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT v.violation_id, v.event_type, v.severity, v.confidence,
                v.occurred_at, v.status,
                d.full_name AS driver_name
         FROM driver_violations v
         LEFT JOIN drivers d ON d.driver_id = v.driver_id
         WHERE v.vehicle_id = :id
         ORDER BY v.occurred_at DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':id',  $vehicleId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,     PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}