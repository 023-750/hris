<?php
$page_title = 'User Management';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

// ── Handle toggle active status ──────────────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $conn->query("UPDATE users SET is_active = NOT is_active WHERE user_id = $uid");
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $uid, 'Toggled user active status');
        redirectWith(BASE_URL . '/admin/users.php', 'success', 'User status updated successfully.');
    }
}

// ── Handle delete ─────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE user_id = $uid");
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'User', $uid, 'Deleted user account');
        redirectWith(BASE_URL . '/admin/users.php', 'success', 'User deleted successfully.');
    } else {
        redirectWith(BASE_URL . '/admin/users.php', 'danger', 'You cannot delete your own account.');
    }
}

require_once '../includes/header.php';

// Pagination settings
$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// Count all non-Employee users shown in this page
$total_users_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users u
    WHERE u.role != 'Employee'
");
$total_users = (int)($total_users_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_users / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}



// Fetch all users except those with 'Employee' role (which are managed in Portal Accounts)
$users = $conn->query("
    SELECT u.*, b.branch_name, e.profile_picture 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.branch_id 
    LEFT JOIN employees e ON u.employee_id = e.employee_id 
    WHERE u.role != 'Employee'
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// Fetch branches for the add form
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");

// Fetch active employees who don't have an HR/admin account yet
$eligible_employees = $conn->query("
    SELECT e.employee_id, e.first_name, e.last_name, ec.personal_email 
    FROM employees e 
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN users u ON e.employee_id = u.employee_id AND u.role != 'Employee'
    WHERE u.user_id IS NULL AND e.is_active = 1 
    ORDER BY e.last_name, e.first_name
");

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);
?>

<?php if ($new_creds): ?>
<!-- Credential Slip Modal (shown once after creating an Employee account) -->
<div class="modal fade" id="credentialSlipModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-key me-2"></i>Employee Credentials</h5>
      </div>
      <div class="modal-body text-center">
        <p class="mb-1">Account created for:</p>
        <h5 class="fw-bold"><?php echo e($new_creds['full_name']); ?></h5>
        <hr>
        <p class="mb-1"><small class="text-muted">Username (Employee ID)</small></p>
        <div class="alert alert-light border fs-5 fw-bold py-2"><?php echo e($new_creds['username']); ?></div>
        <p class="mb-1"><small class="text-muted">Password</small></p>
        <div class="alert alert-light border fs-5 fw-bold py-2"><?php echo e($new_creds['password']); ?></div>
        <div class="alert alert-warning py-2 mt-2" style="font-size:.82rem;">
          <i class="fas fa-exclamation-triangle me-1"></i>Save and hand these credentials to the employee. This is shown only once.
        </div>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Got it, I've noted the credentials</button>
      </div>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('credentialSlipModal')).show());</script>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage system user accounts and roles</p>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-user-shield me-2"></i>Add New Admin
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-2"></i>Add New User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>All Users</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchUsers" placeholder="Search users..." onkeyup="filterTable('searchUsers', 'usersTable')">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = 1; ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $row_number++; ?></strong></td>
                            <td>
                                <img src="<?php echo getEmployeeAvatar($user['profile_picture']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                            </td>
                            <td><strong><?php echo e($user['username']); ?></strong></td>
                            <td><?php echo e($user['full_name']); ?></td>
                            <td><?php echo e($user['email']); ?></td>
                            <td><span class="badge bg-primary"><?php echo e($user['role']); ?></span></td>
                            <td><?php echo e($user['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <!-- Edit -->
                                    <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo $user['user_id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- Toggle Status -->
                                    <a href="?toggle=<?php echo $user['user_id']; ?>"
                                       class="btn btn-sm btn-outline-warning" title="Toggle Active/Inactive">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <!-- Delete — uses Bootstrap modal, no native confirm() -->
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            title="Delete User"
                                            onclick="setDeleteTarget(<?php echo $user['user_id']; ?>, '<?php echo e(addslashes($user['username'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">Current User</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-0 pt-0">
            <nav aria-label="Users pagination">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <?php $query_params = $_GET; unset($query_params['page']); ?>
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page - 1])); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo $p === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $p])); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ── Add User Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/add-user.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required onchange="prefillUserInfo(this)">
                            <option value="">Select Employee</option>
                            <?php while ($emp = $eligible_employees->fetch_assoc()): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" 
                                        data-name="<?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>"
                                        data-email="<?php echo e($emp['personal_email']); ?>">
                                    <?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="new_username" required>
                        <div class="form-text">Use a custom HR username. Do not use the Employee ID.</div>
                    </div>
                    <input type="hidden" name="full_name" id="new_full_name">
                    <input type="hidden" name="email" id="new_email">
                    <input type="hidden" name="redirect" value="users.php">
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="">Select Role</option>
                            <option value="HR Staff">HR Staff</option>
                            <option value="HR Supervisor">HR Supervisor</option>
                            <option value="HR Manager">HR Manager</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" name="branch_id">
                            <option value="">None (Admin)</option>
                            <?php $branches->data_seek(0); while ($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['branch_id']; ?>"><?php echo e($branch['branch_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                        <div class="form-text">Optional. Max 2MB (JPG, PNG, WebP).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Admin Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i>Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/add-user.php">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle me-1"></i>Admin accounts are standalone and are not linked to employee records.
                    </div>
                    <input type="hidden" name="role" value="Admin">
                    <input type="hidden" name="redirect" value="users.php">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                        <div class="form-text">Use a custom admin username.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Confirmation Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function setDeleteTarget(userId, username) {
    document.getElementById('deleteUserName').textContent = username;
    document.getElementById('deleteConfirmBtn').href = '?delete=' + userId;
}

function prefillUserInfo(select) {
    const option = select.options[select.selectedIndex];
    if (!option.value) return;

    document.getElementById('new_full_name').value = option.getAttribute('data-name');
    document.getElementById('new_email').value     = option.getAttribute('data-email') || '';

    const roleSelect    = document.querySelector('[name="role"]');
    const usernameField = document.getElementById('new_username');

    if (!usernameField.value || usernameField.value === option.value) {
        const name = option.getAttribute('data-name').toLowerCase().replace(/\s+/g, '.');
        usernameField.value = name;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const empSelect  = document.querySelector('#addUserModal [name="employee_id"]');
    const usernameField = document.getElementById('new_username');
    if (empSelect && usernameField) {
        empSelect.addEventListener('change', function() {
            const opt = empSelect.options[empSelect.selectedIndex];
            if (!opt || !opt.value) return;
            if (!usernameField.value || usernameField.value === opt.value || /^\d+$/.test(usernameField.value)) {
                usernameField.value = opt.getAttribute('data-name')?.toLowerCase().replace(/\s+/g, '.') || '';
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
