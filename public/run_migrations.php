<?php
// Run migrations endpoint (use only in controlled environments)
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== eJustice Portal Migration Runner ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Try to include config
try {
    require_once __DIR__ . '/../config/db.php';
    echo "[OK] Database connected\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to connect to database: " . $e->getMessage() . "\n";
    echo "</pre>";
    exit;
}

// Start session
session_start();
echo "[OK] Session started\n\n";

// Check if user is logged in AND is system_admin, OR if this is the first run (no users table yet)
$isFirstRun = false;
try {
    $pdo->query("SELECT 1 FROM users LIMIT 1");
    echo "[INFO] Users table exists\n";
} catch (PDOException $e) {
    echo "[INFO] Users table not found - allowing first run\n";
    $isFirstRun = true;
}

if (!$isFirstRun && (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'system_admin')) {
    echo "[BLOCKED] Authorization failed - must be logged in as system_admin\n";
    echo "</pre>";
    exit;
}

echo "[OK] Authorization passed\n\n";

$migrations = [
    __DIR__ . "/../sql/ejustice_portal.sql",
    __DIR__ . "/../sql/002_add_audit_logs.sql",
    __DIR__ . "/../sql/003_add_barangay_module.sql",
    __DIR__ . "/../sql/004_add_barangay_case_routing.sql",
];

echo "=== Running Migrations ===\n";
foreach ($migrations as $file) {
    if (!file_exists($file)) {
        echo "[SKIP] File not found: " . basename($file) . "\n";
        continue;
    }
    echo "[RUN] " . basename($file) . "\n";
    $sql = file_get_contents($file);
    if (!$sql) {
        echo "[ERROR] Failed to read: " . basename($file) . "\n\n";
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "[OK] " . basename($file) . " executed successfully\n\n";
    } catch (PDOException $e) {
        echo "[ERROR] " . basename($file) . ": " . $e->getMessage() . "\n\n";
    }
}

echo "=== Migration Run Complete ===\n";
echo "</pre>";
?>