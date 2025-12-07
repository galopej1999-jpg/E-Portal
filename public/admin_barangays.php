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
$barangay_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_barangay'])) {
        $barangay_name = trim($_POST['barangay_name'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($barangay_name && $municipality && $province) {
            try {
                $stmt = $pdo->prepare("INSERT INTO barangay_info (barangay_name, municipality, province, address) VALUES (:name, :municipality, :province, :address)");
                $stmt->execute([
                    ':name' => $barangay_name,
                    ':municipality' => $municipality,
                    ':province' => $province,
                    ':address' => $address
                ]);
                $message = "✓ Barangay created successfully.";
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ Barangay name, municipality, and province required.";
        }
    } elseif (isset($_POST['edit_barangay'])) {
        $barangay_id = (int)$_POST['barangay_id'];
        $barangay_name = trim($_POST['barangay_name'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($barangay_name && $municipality && $province) {
            try {
                $stmt = $pdo->prepare("UPDATE barangay_info SET barangay_name = :name, municipality = :municipality, province = :province, address = :address WHERE id = :id");
                $stmt->execute([
                    ':name' => $barangay_name,
                    ':municipality' => $municipality,
                    ':province' => $province,
                    ':address' => $address,
                    ':id' => $barangay_id
                ]);
                $message = "✓ Barangay updated successfully.";
                $action = '';
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ Barangay name, municipality, and province required.";
        }
    } elseif (isset($_POST['delete_barangay'])) {
        $barangay_id = (int)$_POST['barangay_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM barangay_info WHERE id = :id");
            $stmt->execute([':id' => $barangay_id]);
            $message = "✓ Barangay deleted successfully.";
        } catch (Exception $e) {
            $message = "✗ Error: " . $e->getMessage();
        }
    }
}

// Load barangay for edit
$edit_barangay = null;
if ($action === 'edit' && $barangay_id) {
    $stmt = $pdo->prepare("SELECT * FROM barangay_info WHERE id = :id");
    $stmt->execute([':id' => $barangay_id]);
    $edit_barangay = $stmt->fetch();
}

// Load all barangays
$stmt = $pdo->query("SELECT * FROM barangay_info ORDER BY barangay_name");
$barangays = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Barangay Management</h2>
  <?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($action === 'edit' && $edit_barangay): ?>
    <!-- Edit Barangay Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Edit Barangay: <?php echo htmlspecialchars($edit_barangay['barangay_name']); ?></h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="barangay_id" value="<?php echo $edit_barangay['id']; ?>">
          
          <div class="col-md-6">
            <label>Barangay Name *</label>
            <input type="text" name="barangay_name" class="form-control" value="<?php echo htmlspecialchars($edit_barangay['barangay_name']); ?>" required>
          </div>
          
          <div class="col-md-6">
            <label>Municipality *</label>
            <input type="text" name="municipality" class="form-control" value="<?php echo htmlspecialchars($edit_barangay['municipality']); ?>" required>
          </div>
          
          <div class="col-md-6">
            <label>Province *</label>
            <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($edit_barangay['province']); ?>" required>
          </div>

          <div class="col-md-6">
            <label>Address</label>
            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($edit_barangay['address'] ?? ''); ?>">
          </div>

          <div class="col-12">
            <button type="submit" name="edit_barangay" class="btn btn-primary">Save Changes</button>
            <a href="admin_barangays.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <!-- Create Barangay Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Create New Barangay</h5>
        <form method="post" class="row g-3">
          
          <div class="col-md-6">
            <label>Barangay Name *</label>
            <input type="text" name="barangay_name" class="form-control" required>
          </div>
          
          <div class="col-md-6">
            <label>Municipality *</label>
            <input type="text" name="municipality" class="form-control" required>
          </div>
          
          <div class="col-md-6">
            <label>Province *</label>
            <input type="text" name="province" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label>Address</label>
            <input type="text" name="address" class="form-control">
          </div>

          <div class="col-12">
            <button type="submit" name="create_barangay" class="btn btn-success">Create Barangay</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Barangays List -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">All Barangays (<?php echo count($barangays); ?>)</h5>
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Barangay Name</th>
              <th>Municipality</th>
              <th>Province</th>
              <th>Address</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($barangays as $b): ?>
            <tr>
              <td><?php echo $b['id']; ?></td>
              <td><?php echo htmlspecialchars($b['barangay_name']); ?></td>
              <td><?php echo htmlspecialchars($b['municipality']); ?></td>
              <td><?php echo htmlspecialchars($b['province']); ?></td>
              <td><?php echo htmlspecialchars($b['address'] ?? ''); ?></td>
              <td><?php echo $b['created_at']; ?></td>
              <td>
                <a href="?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="barangay_id" value="<?php echo $b['id']; ?>">
                  <button type="submit" name="delete_barangay" class="btn btn-sm btn-danger" onclick="return confirm('Delete this barangay?')">Delete</button>
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
