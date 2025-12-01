<?php
// Run migrations endpoint (use only in controlled environments)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';
session_start();

echo "Starting migration runner...\n";

// Check if user is logged in AND is system_admin, OR if this is the first run (no users table yet)
$isFirstRun = false;
try {
    $pdo->query("SELECT 1 FROM users LIMIT 1");
    echo "Users table exists.\n";
} catch (PDOException $e) {
    // users table doesn't exist yet - allow first run
    echo "Users table not found - allowing first run.\n";
    $isFirstRun = true;
}

if (!$isFirstRun && (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'system_admin')) {
    http_response_code(403);
    echo "Unauthorized. Must be logged in as system_admin.";
    exit;
}

echo "Authorization passed.\n";

$migrations = [
    __DIR__ . "/../sql/ejustice_portal.sql",
    __DIR__ . "/../sql/002_add_audit_logs.sql",
    __DIR__ . "/../sql/003_add_barangay_module.sql",
    __DIR__ . "/../sql/004_add_barangay_case_routing.sql",
];

echo "<pre>Starting migrations...\n";
foreach ($migrations as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    echo "Running: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    if (!$sql) {
        echo "✗ Failed to read: $file\n\n";
        continue;
    }
    try {
        // Split by delimiter if needed; try to execute whole file
        $pdo->exec($sql);
        echo "✓ Executed: " . basename($file) . "\n\n";
    } catch (PDOException $e) {
        echo "✗ Error executing " . basename($file) . ": " . $e->getMessage() . "\n\n";
    }
}

echo "Migrations finished.</pre>";
?>