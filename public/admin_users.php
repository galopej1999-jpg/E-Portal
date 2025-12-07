<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Restrict to admin only
if (current_user_role() !== 'system_admin') {
    die("Access denied. Admin only.");
}

$message = '';
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;

        if ($email && $full_name && $role && $password) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            try {
                    $stmt = $pdo->prepare("INSERT INTO users (email, full_name, password, role, barangay_id) VALUES (:email, :full_name, :password, :role, :barangay_id)");
                $stmt->execute([
                    ':email' => $email,
                    ':full_name' => $full_name,
                    ':password' => $hashed,
                    ':role' => $role,
                    ':barangay_id' => $barangay_id
                ]);
                $message = "✓ User created successfully.";
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ All fields required.";
        }
    } elseif (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
        $password = trim($_POST['password'] ?? '');

        if ($full_name && $role) {
            try {
                if ($password) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, role = :role, barangay_id = :barangay_id, password = :password WHERE id = :id");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':role' => $role,
                        ':barangay_id' => $barangay_id,
                        ':password' => $hashed,
                        ':id' => $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, role = :role, barangay_id = :barangay_id WHERE id = :id");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':role' => $role,
                        ':barangay_id' => $barangay_id,
                        ':id' => $user_id
                    ]);
                }
                $message = "✓ User updated successfully.";
                $action = '';
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ Name and role required.";
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND id != :admin_id");
            $stmt->execute([':id' => $user_id, ':admin_id' => $_SESSION['user_id']]);
            $message = "✓ User deleted successfully.";
        } catch (Exception $e) {
            $message = "✗ Error: " . $e->getMessage();
        }
    }
}

// Load user for edit
$edit_user = null;
if ($action === 'edit' && $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $edit_user = $stmt->fetch();
}

// Load all users
$stmt = $pdo->query("SELECT u.*, b.barangay_name FROM users u LEFT JOIN barangay_info b ON u.barangay_id = b.id ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

// Load barangays for dropdown
$stmt = $pdo->query("SELECT id, barangay_name FROM barangay_info ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

$roles = ['complainant', 'police_staff', 'mtc_staff', 'mtc_judge', 'rtc_staff', 'rtc_judge', 'barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>User Management</h2>
  <?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($action === 'edit' && $edit_user): ?>
    <!-- Edit User Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Edit User: <?php echo htmlspecialchars($edit_user['full_name']); ?></h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
          
          <div class="col-md-6">
            <label>Email</label>
            <input type="email" class="form-control" value="<?php echo htmlspecialchars($edit_user['email']); ?>" disabled>
          </div>
          
          <div class="col-md-6">
            <label>Full Name *</label>
            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
          </div>
          
          <div class="col-md-6">
            <label>Role *</label>
            <select name="role" class="form-control" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r; ?>" <?php echo $edit_user['role'] === $r ? 'selected' : ''; ?>>
                  <?php echo ucfirst(str_replace('_', ' ', $r)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label>Barangay (for staff)</label>
            <select name="barangay_id" class="form-control">
              <option value="">-- None --</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $edit_user['barangay_id'] === $b['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['barangay_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-6">
            <label>New Password (leave blank to keep)</label>
            <input type="password" name="password" class="form-control" placeholder="Optional">
          </div>

          <div class="col-12">
            <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
            <a href="admin_users.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <!-- Create User Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Create New User</h5>
        <form method="post" class="row g-3">
          
          <div class="col-md-6">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          
          <div class="col-md-6">
            <label>Full Name *</label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          
          <div class="col-md-6">
            <label>Role *</label>
            <select name="role" class="form-control" required>
              <option value="">-- Select Role --</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r; ?>">
                  <?php echo ucfirst(str_replace('_', ' ', $r)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label>Barangay (for staff)</label>
            <select name="barangay_id" class="form-control">
              <option value="">-- None --</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo $b['id']; ?>">
                  <?php echo htmlspecialchars($b['barangay_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-6">
            <label>Password *</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <div class="col-12">
            <button type="submit" name="create_user" class="btn btn-success">Create User</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Users List -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">All Users (<?php echo count($users); ?>)</h5>
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Full Name</th>
              <th>Role</th>
              <th>Barangay</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?php echo $u['id']; ?></td>
              <td><?php echo htmlspecialchars($u['email']); ?></td>
              <td><?php echo htmlspecialchars($u['full_name']); ?></td>
              <td>
                <span class="badge bg-info">
                  <?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?>
                </span>
              </td>
              <td><?php echo $u['barangay_name'] ? htmlspecialchars($u['barangay_name']) : '--'; ?></td>
              <td><?php echo $u['created_at']; ?></td>
              <td>
                <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                  <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
