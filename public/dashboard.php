<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$role = current_user_role();

// Redirect to role-specific dashboards
if ($role === 'system_admin') {
    header('Location: admin_dashboard.php');
    exit;
} elseif ($role === 'barangay_staff' || $role === 'punong_barangay' || $role === 'lupon_secretary') {
    header('Location: barangay_dashboard.php');
    exit;
} elseif ($role === 'police_staff') {
    header('Location: police_dashboard.php');
    exit;
} elseif (in_array($role, ['mtc_staff', 'mtc_judge'])) {
    header('Location: mtc_dashboard.php');
    exit;
} elseif (in_array($role, ['rtc_staff', 'rtc_judge'])) {
    header('Location: rtc_dashboard.php');
    exit;
}

?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h3>Dashboard</h3>
<p>Welcome to the eJustice Portal.</p>

<?php if ($role === 'complainant'): ?>
    <div class="alert alert-info">
        <p>You can file a new complaint and check the status of your cases.</p>
        <a href="file_case.php" class="btn btn-primary btn-sm">File New Case</a>
        <a href="cases.php" class="btn btn-secondary btn-sm">My Cases</a>
    </div>
<?php elseif ($role === 'system_admin'): ?>
    <div class="alert alert-info">
        <p>System admin: manage all cases and system configuration.</p>
        <a href="cases.php" class="btn btn-primary btn-sm">All Cases</a>
        <a href="audit_logs.php" class="btn btn-secondary btn-sm">Audit Logs</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
