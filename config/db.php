<?php
require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    // Diagnostic info
    $dbUrl = getenv('DATABASE_URL');
    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $errorMsg .= "\n[Diagnostics]\n";
    $errorMsg .= "DATABASE_URL set: " . ($dbUrl ? "yes" : "no") . "\n";
    $errorMsg .= "DB_HOST env: " . ($dbHost ? $dbHost : "not set") . "\n";
    $errorMsg .= "DB_HOST const: " . DB_HOST . "\n";
    $errorMsg .= "DB_PORT const: " . DB_PORT . "\n";
    $errorMsg .= "DB_NAME const: " . DB_NAME . "\n";
    $errorMsg .= "DSN: " . $dsn . "\n";
    die('Database connection failed: ' . $errorMsg);
}
