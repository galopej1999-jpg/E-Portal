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
    // Check if DATABASE_URL is set
    $dbUrl = getenv('DATABASE_URL');
    if (!$dbUrl) {
        $errorMsg .= " [Note: DATABASE_URL env var not set. Using defaults: " . DB_HOST . ":" . DB_PORT . "]";
    }
    die('Database connection failed: ' . $errorMsg);
}
