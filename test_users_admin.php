<?php
/**
 * Sigma SMS A2P — Test Users Management (Admin Only)
 */
require_once __DIR__ . '/functions.php';
requireRole('admin');
$pageTitle = 'Test Users Management';
$user = getCurrentUser();

$pdo = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $limit = (int)($_POST['number_limit'] ?? 10);
        
        if (!empty($username) && !empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO test_users (username, password, number_limit, created_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$username, $hashedPassword, $limit, $user['id']]);
                flashMessage('success', 'Test user created successfully');
            } catch (Exception $e) {
                flashMessage('danger', 'Failed to create test user: Username may already exist');
            }
        }
    } elseif ($action === 'update_limit') {
        $testUserId = (int)($_POST['test_user_id'] ?? 0);
        $newLimit = (int)($_POST['new_limit'] ?? 10);
        
        $stmt = $pdo->prepare("UPDATE test_users SET number_limit = ? WHERE id = ?");
        $stmt->execute([$newLimit, $testUserId]);
        flashMessage('success', 'Limit updated successfully');
    } elseif ($action === 'toggle_status') {
        $testUserId = (int)($_POST['test_user_id'] ?? 0);
        $stmt = $pdo->prepare("
            UPDATE test_users 
            SET status = IF(status = 'active', 'blocked', 'active') 
            WHERE id = ?
        ");
        $stmt->execute([$testUserId]);
        flashMessage('success', 'Status updated successfully');
    } elseif ($action === 'delete') {
        $testUserId = (int)($_POST['test_user_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM test_users WHERE id = ?");
        $stmt->execute([$testUserId]);
        flashMessage('success', 'Test user deleted successfully');
    }
    
    redirect(APP_URL . '/test_users_admin.php');
}

// Get all test users
$testUsers = $pdo->query("
    SELECT 
        tu.*,
        u.username as created_by_username,
        (SELECT COUNT(*) FROM test_user_numbers WHERE test_username = tu.username) as allocated_count
    FROM test_users tu
    LEFT JOIN users u ON tu.created_by = u.id
    ORDER BY tu.created_at DESC
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="animate-in"><i class="ri-test-tube-line me-2"></i>Test Users Management</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Test Users</li>
      </ol>
    </nav>
  </div>
  <div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
      <i class="ri-add-line me-1"></i>Create Test User
    </button>
  </div>
</div>

<div class="card animate-in">
  <div class="card-header">
    <i class="ri-team-line me-2"></i>Test Users
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Username</th>
            <th>Number Limit</th>
            <th>Allocated</th>
            <th>Available</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($testUsers as $tu): ?>
          <tr>
            <td><strong><?= h($tu['username']) ?></strong></td>
            <td>
              <span class="badge bg-primary"><?= $tu['number_limit'] ?></span>
            </td>
            <td><?= $tu['allocated_count'] ?></td>
            <td><?= $tu['number_limit'] - $tu['allocated_count'] ?></td>
            <td>
              <?php if ($tu['status'] === 'active'): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-danger">Blocked</span>
              <?php endif; ?>
            </td>
            <td><?= h($tu['created_by_username'] ?: 'System') ?></td>
            <td><?= h($tu['created_at']) ?></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="editLimit(<?= $tu['id'] ?>, <?= $tu['number_limit'] ?>)">
                  <i class="ri-edit-line"></i>
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="test_user_id" value="<?= $tu['id'] ?>">
                  <button type="submit" class="btn btn-outline-warning">
                    <i class="ri-shield-line"></i>
                  </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this test user?')">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="test_user_id" value="<?= $tu['id'] ?>">
                  <button type="submit" class="btn btn-outline-danger">
                    <i class="ri-delete-bin-line"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Create Test User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="text" class="form-control" name="password" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Number Limit</label>
            <input type="number" class="form-control" name="number_limit" value="10" min="1" max="100" required>
            <small class="text-muted">Maximum numbers this user can allocate</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Limit Modal -->
<div class="modal fade" id="editLimitModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="update_limit">
        <input type="hidden" name="test_user_id" id="editUserId">
        <div class="modal-header">
          <h5 class="modal-title">Update Number Limit</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New Limit</label>
            <input type="number" class="form-control" name="new_limit" id="editLimit" min="1" max="100" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editLimit(userId, currentLimit) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editLimit').value = currentLimit;
    new bootstrap.Modal(document.getElementById('editLimitModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
