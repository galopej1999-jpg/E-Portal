<?php
/**
 * Database Migration Runner
 * Automatically runs all SQL migration files in order
 */

require_once __DIR__ . '/../config/db.php';

echo "=== eJustice Portal Database Migration ===\n\n";

$migrations = [
    'ejustice_portal.sql' => 'Main schema (users, cases, documents)',
    '002_add_audit_logs.sql' => 'Audit logging',
    '003_add_barangay_module.sql' => 'Barangay module',
    '004_add_barangay_case_routing.sql' => 'Case routing',
    '005_add_locations.sql' => 'Location management',
];

$sqlDir = __DIR__ . '/../sql/';
$successful = 0;
$failed = 0;

foreach ($migrations as $filename => $description) {
    $filepath = $sqlDir . $filename;
    
    if (!file_exists($filepath)) {
        echo "[SKIP] $filename - File not found\n";
        continue;
    }
    
    echo "[RUNNING] $filename ($description)...\n";
    
    try {
        $sql = file_get_contents($filepath);
        
        // Split SQL by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) { return !empty($stmt); }
        );
        
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        
        echo "  ✓ Success\n";
        $successful++;
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Migration Complete ===\n";
echo "Successful: $successful\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All migrations completed successfully!\n";
    echo "You can now use the admin dashboard.\n";
} else {
    echo "\n✗ Some migrations failed. Check the errors above.\n";
}
?>
