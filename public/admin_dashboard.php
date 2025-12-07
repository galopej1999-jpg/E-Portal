<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Restrict to admin only
if (current_user_role() !== 'system_admin') {
    die("Access denied. Admin only.");
}

$pdo = getDatabaseConnection();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$total_users = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM barangays");
$total_barangays = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM locations");
$total_locations = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM cases");
$total_cases = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM case_documents");
$total_documents = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs");
$total_audit_logs = $stmt->fetch()['count'];

// Get user role distribution
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC");
$user_roles = $stmt->fetchAll();

// Get recent users
$stmt = $pdo->query("SELECT id, email, full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();

// Get recent cases
$stmt = $pdo->query("SELECT c.id, c.case_number, c.status, c.stage, u.full_name as complainant FROM cases c JOIN users u ON c.complainant_id = u.id ORDER BY c.created_at DESC LIMIT 5");
$recent_cases = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h1>Admin Dashboard</h1>
  <p class="text-muted">System administration and management</p>

  <!-- Statistics Cards -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-bg-primary">
        <div class="card-body">
          <h5 class="card-title">Total Users</h5>
          <h2><?php echo $total_users; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-success">
        <div class="card-body">
          <h5 class="card-title">Barangays</h5>
          <h2><?php echo $total_barangays; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-info">
        <div class="card-body">
          <h5 class="card-title">Locations</h5>
          <h2><?php echo $total_locations; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-warning">
        <div class="card-body">
          <h5 class="card-title">Cases</h5>
          <h2><?php echo $total_cases; ?></h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-bg-secondary">
        <div class="card-body">
          <h5 class="card-title">Documents</h5>
          <h2><?php echo $total_documents; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-dark">
        <div class="card-body">
          <h5 class="card-title">Audit Logs</h5>
          <h2><?php echo $total_audit_logs; ?></h2>
        </div>
      </div>
    </div>
  </div>

  <!-- Management Sections -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5>User Management</h5>
        </div>
        <div class="card-body">
          <p>Create, edit, and manage all user accounts (complainants, staff, judges, admin).</p>
          <a href="admin_users.php" class="btn btn-primary">Manage Users</a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5>Barangay Management</h5>
        </div>
        <div class="card-body">
          <p>Create, edit, and manage barangay information and jurisdiction areas.</p>
          <a href="admin_barangays.php" class="btn btn-success">Manage Barangays</a>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5>Location Management</h5>
        </div>
        <div class="card-body">
          <p>Manage barangay locations, areas, and geographic zones for cases.</p>
          <a href="admin_locations.php" class="btn btn-info">Manage Locations</a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5>System Logs</h5>
        </div>
        <div class="card-body">
          <p>View audit logs for document access, case updates, and user actions.</p>
          <a href="audit_logs.php" class="btn btn-secondary">View Audit Logs</a>
        </div>
      </div>
    </div>
  </div>

  <!-- User Role Distribution -->
  <div class="card mb-4">
    <div class="card-header">
      <h5>User Role Distribution</h5>
    </div>
    <div class="card-body">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Role</th>
            <th>Count</th>
            <th>Percentage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($user_roles as $ur): ?>
          <tr>
            <td><?php echo ucfirst(str_replace('_', ' ', $ur['role'])); ?></td>
            <td><?php echo $ur['count']; ?></td>
            <td><?php echo round(($ur['count'] / $total_users) * 100, 1); ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    <!-- Recent Users -->
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>Recent Users</h5>
        </div>
        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_users as $u): ?>
              <tr>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></span></td>
                <td><?php echo $u['created_at']; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Recent Cases -->
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>Recent Cases</h5>
        </div>
        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Case #</th>
                <th>Status</th>
                <th>Stage</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_cases as $c): ?>
              <tr>
                <td><a href="case_view.php?id=<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['case_number']); ?></a></td>
                <td><span class="badge bg-warning"><?php echo htmlspecialchars($c['status']); ?></span></td>
                <td><?php echo htmlspecialchars($c['stage']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
