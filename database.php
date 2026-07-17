<?php
/**
 * config/database.php
 *
 * Immutable PDO initialization layer.
 *
 * Security principles applied:
 *  - utf8mb4 charset is set at the connection string (DSN) level, not via a
 *    separate SET NAMES query, which closes off charset-based SQL injection
 *    vectors that exploited older MySQL client encodings (e.g. GBK).
 *  - PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION makes every database error
 *    throw a catchable PDOException instead of silently failing or emitting
 *    a warning that could leak schema details to the page.
 *  - PDO::ATTR_EMULATE_PREPARES => false forces the underlying MySQL driver
 *    to use *real* server-side prepared statements. This is the setting
 *    that actually gives prepared statements their SQL-injection immunity;
 *    with emulation left on, PDO builds the final query string itself and
 *    can be tricked in some edge cases (e.g. multi-byte charset issues).
 *  - Raw exception details (which can include table/column names, DSN
 *    fragments, etc.) are only ever written to the server-side error log.
 *    The end user / API consumer always receives a generic message.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Connection credentials.
// In a real deployment these should come from environment variables
// (e.g. via getenv() or a .env loader) rather than being hard-coded.
// Placeholders are used here so the file is safe to commit as a template.
// ---------------------------------------------------------------------
$dbHost    = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort    = getenv('DB_PORT') ?: '3306';
$dbName    = getenv('DB_NAME') ?: 'tu_portfolio_db';
$dbUser    = getenv('DB_USER') ?: 'portfolio_app_user';
$dbPass    = getenv('DB_PASS') ?: 'change_me_in_env';

// DSN explicitly requests utf8mb4 so multi-byte characters (emoji, etc.)
// are stored correctly and the connection cannot be downgraded to a
// charset that re-opens injection vectors.
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $exception) {
    // Log the full technical detail server-side only. Never echo
    // $exception->getMessage() to the client — it can reveal
    // credentials, host names, or schema structure.
    error_log('[Database Connection Error] ' . $exception->getMessage());

    // If this file is included from an API endpoint that already sent
    // a Content-Type: application/json header, respond in kind;
    // otherwise fall back to a plain generic message.
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred. Please try again later.',
    ]);
    exit;
}

// $pdo is now available to any file that includes this one.
return $pdo;
