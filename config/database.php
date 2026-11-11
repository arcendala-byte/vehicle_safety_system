<?php
/**
 * ============================================================
 * DATABASE CONNECTION (PDO)
 * ============================================================
 * Singleton PDO connection using prepared statements.
 * Never expose raw errors to the user in production.
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    // --------------------------------------------------------
    // Connection credentials
    // --------------------------------------------------------
    // Update these to match your XAMPP2 MySQL setup.
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = 3306;
    private const DB_NAME = 'vehicle_safety';
    private const DB_USER = 'root';
    private const DB_PASS = '';        // Default XAMPP: empty
    private const DB_CHARSET = 'utf8mb4';

    /**
     * Get the singleton PDO connection.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // Log internally, show generic message to user
                error_log('[DB ERROR] ' . $e->getMessage());

                if (VS_ENV === 'development') {
                    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
                } else {
                    die('Service temporarily unavailable. Please try again later.');
                }
            }
        }

        return self::$instance;
    }

    /**
     * Test if the database is reachable and the schema exists.
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
            return [
                'connected' => true,
                'schema'    => count($tables) > 0,
                'message'   => count($tables) > 0
                    ? 'Database and schema OK.'
                    : 'Connected, but schema not yet imported.',
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'schema'    => false,
                'message'   => $e->getMessage(),
            ];
        }
    }
}