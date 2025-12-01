<?php
// General app configuration
// Support Railway MYSQL_URL reference, DATABASE_URL, individual env vars, or local development credentials

// Try MYSQL_URL first (Railway reference: ${{ MySQL.MYSQL_URL }})
if ($mysqlUrl = getenv('MYSQL_URL')) {
    // Parse MySQL URL: mysql://user:password@host:port/database
    $parsed = parse_url($mysqlUrl);
    define('DB_HOST', $parsed['host'] ?? 'localhost');
    define('DB_PORT', $parsed['port'] ?? 3306);
    define('DB_NAME', ltrim($parsed['path'] ?? '', '/'));
    define('DB_USER', $parsed['user'] ?? 'root');
    define('DB_PASS', $parsed['pass'] ?? '');
} elseif ($dbUrl = getenv('DATABASE_URL')) {
    // Parse Railway DATABASE_URL: mysql://user:password@host:port/database
    $parsed = parse_url($dbUrl);
    define('DB_HOST', $parsed['host'] ?? 'localhost');
    define('DB_PORT', $parsed['port'] ?? 3306);
    define('DB_NAME', ltrim($parsed['path'] ?? '', '/'));
    define('DB_USER', $parsed['user'] ?? 'root');
    define('DB_PASS', $parsed['pass'] ?? '');
} elseif (getenv('DB_HOST')) {
    // Use individual Railway environment variables
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
    define('DB_NAME', getenv('DB_NAME') ?: 'ejustice_portal');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
} else {
    // Local development defaults
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'ejustice_portal');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Encryption
define('DOC_ENC_METHOD', 'AES-256-CBC');
// CHANGE THIS TO A LONG RANDOM STRING BEFORE USING IN PRODUCTION
define('DOC_ENC_KEY', hash('sha256', 'CHANGE_ME_SUPER_SECRET_KEY'));
