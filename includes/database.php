<?php
/**
 * PDO connection singleton.
 *
 * Usage:  $pdo = db();
 * Every query in the project goes through prepared statements.
 */

require_once __DIR__ . '/config.php';

/**
 * Return the shared PDO connection, creating it on first use.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never leak credentials in the error output.
            http_response_code(500);
            if (DEBUG) {
                die('Database connection failed: ' . e($e->getMessage()));
            }
            die('Adatbázis-kapcsolati hiba. Kérjük, próbáld újra később.');
        }
    }

    return $pdo;
}
