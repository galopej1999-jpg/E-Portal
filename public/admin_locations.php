<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Restrict to admin only
if (current_user_role() !== 'system_admin') {
    die("Access denied. Admin only.");
}

$pdo = getDatabaseConnection();
$message = '';
$action = $_GET['action'] ?? '';
$location_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_location'])) {
        $location_name = trim($_POST['location_name'] ?? '');
        $barangay_id = (int)($_POST['barangay_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($location_name && $barangay_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO locations (location_name, barangay_id, description) VALUES (:name, :barangay_id, :description)");
                $stmt->execute([
                    ':name' => $location_name,
                    ':barangay_id' => $barangay_id,
                    ':description' => $description
                ]);
                $message = "✓ Location created successfully.";
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ Location name and barangay required.";
        }
    } elseif (isset($_POST['edit_location'])) {
        $location_id = (int)$_POST['location_id'];
        $location_name = trim($_POST['location_name'] ?? '');
        $barangay_id = (int)($_POST['barangay_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($location_name && $barangay_id) {
            try {
                $stmt = $pdo->prepare("UPDATE locations SET location_name = :name, barangay_id = :barangay_id, description = :description WHERE id = :id");
                $stmt->execute([
                    ':name' => $location_name,
                    ':barangay_id' => $barangay_id,
                    ':description' => $description,
                    ':id' => $location_id
                ]);
                $message = "✓ Location updated successfully.";
                $action = '';
            } catch (Exception $e) {
                $message = "✗ Error: " . $e->getMessage();
            }
        } else {
            $message = "✗ Location name and barangay required.";
        }
    } elseif (isset($_POST['delete_location'])) {
        $location_id = (int)$_POST['location_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM locations WHERE id = :id");
            $stmt->execute([':id' => $location_id]);
            $message = "✓ Location deleted successfully.";
        } catch (Exception $e) {
            $message = "✗ Error: " . $e->getMessage();
        }
    }
}

// Load location for edit
$edit_location = null;
if ($action === 'edit' && $location_id) {
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = :id");
    $stmt->execute([':id' => $location_id]);
    $edit_location = $stmt->fetch();
}

// Load all locations with barangay info
$stmt = $pdo->query("SELECT l.*, b.barangay_name FROM locations l LEFT JOIN barangays b ON l.barangay_id = b.id ORDER BY b.barangay_name, l.location_name");
$locations = $stmt->fetchAll();

// Load barangays for dropdown
$stmt = $pdo->query("SELECT id, barangay_name FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Location Management</h2>
  <?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($action === 'edit' && $edit_location): ?>
    <!-- Edit Location Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Edit Location: <?php echo htmlspecialchars($edit_location['location_name']); ?></h5>
        <form method="post" class="row g-3">
          <input type="hidden" name="location_id" value="<?php echo $edit_location['id']; ?>">
          
          <div class="col-md-6">
            <label>Location Name *</label>
            <input type="text" name="location_name" class="form-control" value="<?php echo htmlspecialchars($edit_location['location_name']); ?>" required>
          </div>
          
          <div class="col-md-6">
            <label>Barangay *</label>
            <select name="barangay_id" class="form-control" required>
              <option value="">-- Select Barangay --</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $edit_location['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['barangay_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-12">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_location['description'] ?? ''); ?></textarea>
          </div>

          <div class="col-12">
            <button type="submit" name="edit_location" class="btn btn-primary">Save Changes</button>
            <a href="admin_locations.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <!-- Create Location Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Create New Location</h5>
        <form method="post" class="row g-3">
          
          <div class="col-md-6">
            <label>Location Name *</label>
            <input type="text" name="location_name" class="form-control" placeholder="e.g., Main Street, Market Area" required>
          </div>
          
          <div class="col-md-6">
            <label>Barangay *</label>
            <select name="barangay_id" class="form-control" required>
              <option value="">-- Select Barangay --</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo $b['id']; ?>">
                  <?php echo htmlspecialchars($b['barangay_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-12">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Optional details about this location"></textarea>
          </div>

          <div class="col-12">
            <button type="submit" name="create_location" class="btn btn-success">Create Location</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Locations List -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">All Locations (<?php echo count($locations); ?>)</h5>
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Location Name</th>
              <th>Barangay</th>
              <th>Description</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locations as $l): ?>
            <tr>
              <td><?php echo $l['id']; ?></td>
              <td><?php echo htmlspecialchars($l['location_name']); ?></td>
              <td><?php echo $l['barangay_name'] ? htmlspecialchars($l['barangay_name']) : '--'; ?></td>
              <td><?php echo htmlspecialchars($l['description'] ?? ''); ?></td>
              <td><?php echo $l['created_at']; ?></td>
              <td>
                <a href="?action=edit&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="location_id" value="<?php echo $l['id']; ?>">
                  <button type="submit" name="delete_location" class="btn btn-sm btn-danger" onclick="return confirm('Delete this location?')">Delete</button>
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
