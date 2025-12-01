<?php
// General app configuration
// Support both Railway DATABASE_URL and local development credentials

if ($dbUrl = getenv('DATABASE_URL')) {
    // Parse Railway DATABASE_URL: mysql://user:password@host:port/database
    $parsed = parse_url($dbUrl);
    define('DB_HOST', $parsed['host'] ?? 'localhost');
    define('DB_PORT', $parsed['port'] ?? 3306);
    define('DB_NAME', ltrim($parsed['path'] ?? '', '/'));
    define('DB_USER', $parsed['user'] ?? 'root');
    define('DB_PASS', $parsed['pass'] ?? '');
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
