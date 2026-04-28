<?php
$page_title = 'Employee Portal Account';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php', 'danger', 'Invalid portal user ID.');
}

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);

// Fetch user + employee details
$stmt = $conn->prepare("
    SELECT
        u.user_id, u.employee_id, u.username, u.email, u.full_name, u.role, u.is_active, u.created_at,
        e.employee_code, e.first_name, e.last_name, e.job_title, e.profile_picture,
        b.branch_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php', 'danger', 'Portal user not found.');
}

// This page is exclusively for non-HR employees using a dedicated Employee role account
if (($user['role'] ?? '') !== 'Employee') {
    redirectWith(
        BASE_URL . '/admin/users.php?search=' . urlencode($user['username'] ?? ''),
        'info',
        'This employee uses an HR account for portal access. Manage it in User Management.'
    );
}

if (empty($user['employee_id'])) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php', 'danger', 'This portal account is not linked to an employee record.');
}

// ── Handle delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_portal_user') {
    $confirm = (string)($_POST['confirm_delete'] ?? '');
    if ($confirm !== 'DELETE') {
        redirectWith(
            BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id",
            'danger',
            'Deletion not confirmed. Type DELETE to confirm.'
        );
    }

    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'Employee' LIMIT 1");
    $del->bind_param("i", $user_id);

    if ($del->execute()) {
        $del->close();
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'User', $user_id, "Deleted Employee Portal account: {$user['username']}");
        redirectWith(BASE_URL . '/admin/employee-accounts.php', 'success', "Employee Portal account '{$user['username']}' deleted successfully.");
    }

    $del->close();
    redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id", 'danger', 'Failed to delete portal account. Please try again.');
}

// ── Handle update ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = (string)($_POST['password'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if ($new_username === '') {
        $errors[] = 'Username is required.';
    }

    if ($new_password !== '' && strlen($new_password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    // Unique username (global)
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
        $dup->bind_param("si", $new_username, $user_id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists.';
        }
        $dup->close();
    }

    if (!empty($errors)) {
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id", 'danger', implode(' ', $errors));
    }

    if ($new_password !== '') {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET username=?, is_active=?, password_hash=? WHERE user_id=?");
        $upd->bind_param("sisi", $new_username, $is_active, $hash, $user_id);
    } else {
        $upd = $conn->prepare("UPDATE users SET username=?, is_active=? WHERE user_id=?");
        $upd->bind_param("sii", $new_username, $is_active, $user_id);
    }

    if ($upd->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $user_id, "Updated Employee Portal account: {$user['username']} → {$new_username}");
        if ($new_password !== '') {
            $_SESSION['new_employee_credentials'] = [
                'username'  => $new_username,
                'password'  => $new_password,
                'full_name' => $user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            ];
        }
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id", 'success', 'Employee Portal account updated successfully.');
    } else {
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id", 'danger', 'Failed to update portal account. Please try again.');
    }
}

require_once '../includes/header.php';
?>

<?php if ($new_creds): ?>
<!-- Credential Slip Modal -->
<div class="modal fade" id="credentialSlipModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-key me-2"></i>Employee Credentials</h5>
      </div>
      <div class="modal-body text-center">
        <p class="mb-1">Account updated for:</p>
        <h5 class="fw-bold"><?php echo e($new_creds['full_name']); ?></h5>
        <hr>
        <p class="mb-1"><small class="text-muted">Username</small></p>
        <div class="alert alert-light border fs-5 fw-bold py-2"><?php echo e($new_creds['username']); ?></div>
        <p class="mb-1"><small class="text-muted">Password</small></p>
        <div class="alert alert-light border fs-5 fw-bold py-2"><?php echo e($new_creds['password']); ?></div>
        <div class="alert alert-warning py-2 mt-2" style="font-size:.82rem;">
          <i class="fas fa-exclamation-triangle me-1"></i>Save and hand these credentials to the employee.
        </div>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">I've noted the credentials</button>
      </div>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('credentialSlipModal')).show());</script>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Employee Portal Account</h4>
        <small class="text-muted">Manage credentials for non-HR employees</small>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Portal Accounts
    </a>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-id-card me-2"></i><?php echo e($user['last_name'] . ', ' . $user['first_name']); ?></h5>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-auto">
                <img src="<?php echo getEmployeeAvatar($user['profile_picture']); ?>?v=<?php echo time(); ?>"
                     alt="Profile" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
            </div>
            <div class="col">
                <div class="fw-bold"><?php echo e($user['job_title'] ?? ''); ?></div>
                <div class="text-muted small"><?php echo e($user['branch_name'] ?? ''); ?> • Employee ID: <?php echo e(getEmployeeDisplayId($user)); ?></div>
            </div>
            <div class="col-auto">
                <?php if (!empty($user['is_active'])): ?>
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Portal Username</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="username" id="portal_username"
                               value="<?php echo e($user['username']); ?>" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="setUsernameToEmployeeId()">
                            Use <?php echo e(getEmployeeDisplayId($user)); ?>
                        </button>
                    </div>
                    <div class="form-text">You can use a custom Employee Portal username. Employee ID is suggested.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Reset Password <small class="text-muted">(optional)</small></label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="portal_password" minlength="6" placeholder="Leave blank to keep current">
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                            <i class="fas fa-random"></i>
                        </button>
                    </div>
                    <div class="form-text">Minimum 6 characters.</div>
                </div>
            </div>

            <div class="mt-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="isActive" name="is_active" <?php echo !empty($user['is_active']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="isActive">Account is active</label>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>

        <hr class="my-4">

        <div class="alert alert-warning py-2" style="font-size:0.9rem;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Deleting this account may also delete related portal records (e.g., PDS submissions) due to database constraints.
        </div>

        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePortalModal">
            <i class="fas fa-trash me-2"></i>Delete Portal Account
        </button>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deletePortalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-trash me-2 text-danger"></i>Delete Portal Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">This will permanently delete the Employee Portal account:</p>
        <div class="alert alert-light border mb-3">
          <div><strong><?php echo e($user['last_name'] . ', ' . $user['first_name']); ?></strong></div>
          <div class="text-muted small">Username: <code><?php echo e($user['username']); ?></code></div>
        </div>

        <div class="mb-2">
          <label class="form-label">Type <code>DELETE</code> to confirm</label>
          <input type="text" class="form-control" id="confirm_delete" form="deletePortalForm" name="confirm_delete" autocomplete="off">
        </div>
        <div class="form-text">This cannot be undone.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" id="deletePortalForm" class="d-inline">
          <input type="hidden" name="action" value="delete_portal_user">
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash me-2"></i>Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function setUsernameToEmployeeId() {
    document.getElementById('portal_username').value = '<?php echo e(getEmployeeDisplayId($user)); ?>';
}

function generatePassword() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let pass = "";
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('portal_password');
    input.value = pass;
    input.type = 'text';
}
</script>

<?php require_once '../includes/footer.php'; ?>
