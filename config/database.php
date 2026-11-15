<?php
/**
 * SafeMatch - Database connection (PDO singleton)
 *
 * Update the credentials below, or set the SAFEMATCH_DB_* environment variables.
 */

require_once __DIR__ . '/constants.php';

// Server-specific credentials: create config/database.local.php on the host (see
// database.local.php.sample). It is never part of the upload, so it survives re-deploys.
if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

defined('DB_HOST') || define('DB_HOST', getenv('SAFEMATCH_DB_HOST') ?: 'localhost');
defined('DB_PORT') || define('DB_PORT', getenv('SAFEMATCH_DB_PORT') ?: '3306');
defined('DB_NAME') || define('DB_NAME', getenv('SAFEMATCH_DB_NAME') ?: 'safematch_php');
defined('DB_USER') || define('DB_USER', getenv('SAFEMATCH_DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('SAFEMATCH_DB_PASS') !== false ? getenv('SAFEMATCH_DB_PASS') : '');

/**
 * Returns the shared PDO connection.
 *
 * @throws PDOException when the connection fails
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Align MySQL's clock with PHP's timezone so NOW()/TIMESTAMP columns match.
        $offset = (new DateTime('now'))->format('P');
        $pdo->exec("SET time_zone = " . $pdo->quote($offset));
    }

    return $pdo;
}
