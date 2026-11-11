<?php
/**
 * ============================================================
 * DRIVER-SPECIFIC HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation.php';

/**
 * Validate driver form input. Returns [values, errors].
 * The same rules are used by create.php and edit.php.
 */
function validate_driver_input(array $input, int $exceptDriverId = 0): array
{
    $v = new Validator($input);

    $v->required('full_name', 'Full name')
      ->max('full_name', 100)
      ->required('license_number', 'License number')
      ->max('license_number', 50)
      ->phone('phone')
      ->max('phone', 20)
      ->email('email')
      ->max('email', 120)
      ->date('license_expiry', 'License expiry')
      ->inList('status', ['active', 'inactive', 'suspended'], 'Status');

    // Uniqueness check for license
    $license = $v->value('license_number');
    if ($license !== '' && !isset($v->errors()['license_number'])) {
        if (license_in_use($license, $exceptDriverId)) {
            $errors = $v->errors();
            $errors['license_number'] = 'This license number is already registered.';
            // Re-inject by returning manually — see below
            return [
                'values' => collect_driver_values($input),
                'errors' => $errors,
            ];
        }
    }

    return [
        'values' => collect_driver_values($input),
        'errors' => $v->errors(),
    ];
}

/**
 * Extract and clean just the driver fields from raw input.
 */
function collect_driver_values(array $input): array
{
    return [
        'full_name'      => clean((string) ($input['full_name']      ?? '')),
        'phone'          => clean((string) ($input['phone']          ?? '')),
        'email'          => clean((string) ($input['email']          ?? '')),
        'license_number' => clean((string) ($input['license_number'] ?? '')),
        'license_expiry' => clean((string) ($input['license_expiry'] ?? '')),
        'status'         => clean((string) ($input['status']         ?? 'active')),
    ];
}

/**
 * Insert a new driver. Returns the new driver_id.
 * Throws on failure.
 */
function create_driver(array $values): int
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO drivers
            (user_id, full_name, phone, email, license_number, license_expiry, status, current_score)
         VALUES
            (NULL, :full_name, :phone, :email, :license_number, :license_expiry, :status, 100)"
    );
    $stmt->execute([
        ':full_name'      => $values['full_name'],
        ':phone'          => $values['phone']          !== '' ? $values['phone']          : null,
        ':email'          => $values['email']          !== '' ? $values['email']          : null,
        ':license_number' => $values['license_number'],
        ':license_expiry' => $values['license_expiry'] !== '' ? $values['license_expiry'] : null,
        ':status'         => $values['status'],
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing driver. Returns true if a row was affected.
 */
function update_driver(int $driverId, array $values): bool
{
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "UPDATE drivers SET
            full_name      = :full_name,
            phone          = :phone,
            email          = :email,
            license_number = :license_number,
            license_expiry = :license_expiry,
            status         = :status
         WHERE driver_id = :id"
    );
    $stmt->execute([
        ':full_name'      => $values['full_name'],
        ':phone'          => $values['phone']          !== '' ? $values['phone']          : null,
        ':email'          => $values['email']          !== '' ? $values['email']          : null,
        ':license_number' => $values['license_number'],
        ':license_expiry' => $values['license_expiry'] !== '' ? $values['license_expiry'] : null,
        ':status'         => $values['status'],
        ':id'             => $driverId,
    ]);
    return $stmt->rowCount() >= 0; // rowCount can be 0 if no changes
}

/**
 * Soft-deactivate a driver (status = 'inactive').
 * Refuses if the driver has active trips.
 */
function deactivate_driver(int $driverId): array
{
    $pdo = Database::getConnection();

    // Block if active trips exist
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM trips WHERE driver_id = :id AND status = 'active'"
    );
    $stmt->execute([':id' => $driverId]);
    $activeTrips = (int) $stmt->fetchColumn();

    if ($activeTrips > 0) {
        return [
            'ok'      => false,
            'message' => "Cannot deactivate: driver has {$activeTrips} active trip(s).",
        ];
    }

    $stmt = $pdo->prepare("UPDATE drivers SET status = 'inactive' WHERE driver_id = :id");
    $stmt->execute([':id' => $driverId]);

    return ['ok' => true, 'message' => 'Driver deactivated successfully.'];
}

/**
 * Return vehicles assigned to a driver.
 */
function get_driver_vehicles(int $driverId): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT vehicle_id, registration_number, make, model, year, vehicle_type, status
         FROM vehicles
         WHERE assigned_driver_id = :id
         ORDER BY vehicle_id ASC"
    );
    $stmt->execute([':id' => $driverId]);
    return $stmt->fetchAll();
}

/**
 * Return recent violations for a driver (with vehicle reg).
 */
function get_driver_violations(int $driverId, int $limit = 10): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT v.violation_id, v.event_type, v.severity, v.confidence,
                v.occurred_at, v.status, v.description,
                veh.registration_number
         FROM driver_violations v
         LEFT JOIN vehicles veh ON veh.vehicle_id = v.vehicle_id
         WHERE v.driver_id = :id
         ORDER BY v.occurred_at DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':id',  $driverId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Return recent trips for a driver.
 */
function get_driver_trips(int $driverId, int $limit = 10): array
{
    $stmt = Database::getConnection()->prepare(
        "SELECT t.trip_id, t.start_time, t.end_time, t.distance_km,
                t.violation_count, t.safety_score, t.status,
                veh.registration_number
         FROM trips t
         LEFT JOIN vehicles veh ON veh.vehicle_id = t.vehicle_id
         WHERE t.driver_id = :id
         ORDER BY t.start_time DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':id',  $driverId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}