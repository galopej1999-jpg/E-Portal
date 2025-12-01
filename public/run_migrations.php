<?php
// Run migrations endpoint (use only in controlled environments)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Only allow system_admin users to run migrations
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'system_admin') {
    http_response_code(403);
    echo "Unauthorized. Must be logged in as system_admin.";
    exit;
}

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
    echo "Running: $file\n";
    $sql = file_get_contents($file);
    try {
        // Split by delimiter if needed; try to execute whole file
        $pdo->exec($sql);
        echo "✓ Executed: $file\n\n";
    } catch (PDOException $e) {
        echo "✗ Error executing $file: " . $e->getMessage() . "\n\n";
    }
}

echo "Migrations finished.</pre>";
?>