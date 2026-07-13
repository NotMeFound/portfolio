<?php
define('DB_HOST',   'localhost');
define('DB_NAME',   'karan_portfolio');
define('DB_USER',   'root');
define('DB_PASS',   ''); // Add your password if you have one
define('DB_CHARSET','utf8mb4');

define('OWNER_EMAIL', 'chhetrikaran.147@gmail.com');

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}