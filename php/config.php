<?php
/**
 * KARAN OLI PORTFOLIO — config.php
 * Central database configuration.
 * ⚠️  CHANGE THESE values before deploying!
 * ⚠️  Never commit real credentials to Git.
 */

// Database credentials
define('DB_HOST',   'localhost');
define('DB_NAME',   'karan_portfolio');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_CHARSET','utf8mb4');

// Site owner email for contact notifications
define('OWNER_EMAIL', 'chhetrikaran.147@gmail.com');

// Security settings
define('SALT_ROUNDS', 12);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_WINDOW', 900); // 15 minutes
define('MAX_CONTACT_ATTEMPTS', 3);
define('CONTACT_WINDOW', 3600); // 1 hour

// Environment
define('ENVIRONMENT', 'production'); // 'development' or 'production'
define('FORCE_HTTPS', true);

/**
 * Returns a PDO instance (singleton pattern) with secure defaults
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Generate a secure random token
 */
function generate_secure_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely using Argon2ID
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

/**
 * Verify password against hash
 */
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Sanitize input for output (XSS prevention)
 */
function sanitize_output(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate email address
 */
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Get client IP address (with proxy support)
 */
function get_client_ip(): string {
    $ip = '0.0.0.0';
    
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Validate IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    
    return $ip;
}