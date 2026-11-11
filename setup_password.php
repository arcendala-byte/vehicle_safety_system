<?php
/**
 * ============================================================
 * TEMPORARY UTILITY — DELETE AFTER USE
 * ============================================================
 * Generates bcrypt hashes for demo passwords and updates the DB.
 * Visit: http://localhost/vehicle_safety_system/setup_password.php
 * DELETE THIS FILE immediately after it runs successfully.
 * ============================================================
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== VEHISAFE — Password Setup Utility ===\n\n";

try {
    $pdo = Database::getConnection();

    // ---- Admin password ----
    $adminPassword = 'Admin@123';
    $adminHash = password_hash($adminPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE username = :u");
    $stmt->execute([':hash' => $adminHash, ':u' => 'admin']);
    echo "Admin password set.\n";
    echo "  Username: admin\n";
    echo "  Password: {$adminPassword}\n";
    echo "  Hash:     {$adminHash}\n\n";

    // ---- Driver password ----
    $driverPassword = 'Driver@123';
    $driverHash = password_hash($driverPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE username = :u");
    $stmt->execute([':hash' => $driverHash, ':u' => 'driver1']);
    echo "Driver password set.\n";
    echo "  Username: driver1\n";
    echo "  Password: {$driverPassword}\n";
    echo "  Hash:     {$driverHash}\n\n";

    // ---- Verify ----
    echo "=== Verification ===\n";
    $row = $pdo->query("SELECT password_hash FROM users WHERE username = 'admin'")->fetch();
    echo "Admin verify:  " . (password_verify($adminPassword, $row['password_hash']) ? "OK" : "FAIL") . "\n";

    $row = $pdo->query("SELECT password_hash FROM users WHERE username = 'driver1'")->fetch();
    echo "Driver verify: " . (password_verify($driverPassword, $row['password_hash']) ? "OK" : "FAIL") . "\n\n";

    echo "✅ Setup complete. DELETE THIS FILE NOW.\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}