<?php
// InCart PDO Database Connection Manager
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'incart_db');

$ports = [3307, 3306];
$pdo = null;
$db_error = null;

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        break; // Connected successfully!
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

if (!$pdo) {
    die("Database connection failed: " . htmlspecialchars($db_error));
}

function getDB() {
    global $pdo;
    return $pdo;
}
