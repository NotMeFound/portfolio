<?php
/**
 * KARAN OLI PORTFOLIO — config.php
 * Central database configuration.
 * ⚠️  CHANGE THESE values before deploying!
 * ⚠️  Never commit real credentials to Git.
 */

define('DB_HOST',   'localhost');
define('DB_NAME',   'karan_portfolio');   // ← your database name
define('DB_USER',   'root');           // ← your MySQL username
define('DB_PASS',   '');               // ← your MySQL password
define('DB_CHARSET','utf8mb4');

// Optional: site owner email for contact notifications
define('OWNER_EMAIL', 'karan.oli@example.com');

/**
 * Returns a PDO instance (singleton pattern).
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
