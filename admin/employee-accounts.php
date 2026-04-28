<?php
$page_title = 'Portal Accounts';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

require_once '../includes/header.php';

// Pagination settings
$per_page = 5;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;
$account_filter = $_GET['account_filter'] ?? 'all';
$allowed_filters = ['all', 'common_employee', 'hr_system'];
if (!in_array($account_filter, $allowed_filters, true)) {
    $account_filter = 'all';
}

$hr_exists_condition = "EXISTS (
    SELECT 1
    FROM users u_hr
    WHERE u_hr.employee_id = e.employee_id
      AND u_hr.role IN ('HR Staff', 'HR Supervisor', 'HR Manager')
)";

$base_from = "
    FROM employees e
    LEFT JOIN users u ON u.user_id = (
        SELECT u2.user_id
        FROM users u2
        WHERE u2.employee_id = e.employee_id
          AND u2.role = 'Employee'
        ORDER BY u2.user_id ASC
        LIMIT 1
    )
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN employee_contacts ec ON ec.contact_id = (
        SELECT ec2.contact_id
        FROM employee_contacts ec2
        WHERE ec2.employee_id = e.employee_id
        ORDER BY ec2.contact_id ASC
        LIMIT 1
    )
";

$base_where = "
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
";

if ($account_filter === 'common_employee') {
    $base_where .= " AND NOT $hr_exists_condition";
} elseif ($account_filter === 'hr_system') {
    $base_where .= " AND $hr_exists_condition";
}

// Count all employees shown in this page
$total_accounts_result = $conn->query("SELECT COUNT(*) AS total " . $base_from . $base_where);
$total_accounts = (int)($total_accounts_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_accounts / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

// Fetch all employees and show the dedicated Employee Portal account when it exists.
$employees = $conn->query("
    SELECT 
        e.employee_id, 
        e.employee_code,
        e.first_name, 
        e.last_name, 
        e.middle_name, 
        e.job_title, 
        b.branch_name, 
        e.profile_picture, 
        e.is_active as emp_active,
        u.user_id,
        u.username,
        u.role,
        u.is_active as user_active,
        (
            SELECT u_hr.role
            FROM users u_hr
            WHERE u_hr.employee_id = e.employee_id
              AND u_hr.role IN ('HR Staff', 'HR Supervisor', 'HR Manager')
            ORDER BY u_hr.user_id ASC
            LIMIT 1
        ) AS hr_role,
        (
            SELECT u_hr.username
            FROM users u_hr
            WHERE u_hr.employee_id = e.employee_id
              AND u_hr.role IN ('HR Staff', 'HR Supervisor', 'HR Manager')
            ORDER BY u_hr.user_id ASC
            LIMIT 1
        ) AS hr_username,
        ec.personal_email
    " . $base_from . "
    " . $base_where . "
    ORDER BY e.last_name, e.first_name
    LIMIT $per_page OFFSET $offset
");

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);
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
        <p class="mb-1">Account created for:</p>
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage employee access to the self-service portal</p>
</div>

<div class="alert alert-info py-2" style="font-size:0.9rem;">
    <i class="fas fa-info-circle me-2"></i>
    Employee Portal accounts are separate from HR system accounts. Employee ID is suggested as the portal username, but you can use a custom one.
</div>

<div class="content-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="fas fa-user-lock me-2"></i>Portal Account Status</h5>
        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap ms-auto" style="flex: 1 1 420px;">
            <form method="GET" class="mb-0">
                <select class="form-select form-select-sm" id="account_filter" name="account_filter" onchange="this.form.submit()" aria-label="Filter portal accounts" style="width: 220px;">
                    <option value="all" <?php echo $account_filter === 'all' ? 'selected' : ''; ?>>All Employees</option>
                    <option value="common_employee" <?php echo $account_filter === 'common_employee' ? 'selected' : ''; ?>>Common Employees</option>
                    <option value="hr_system" <?php echo $account_filter === 'hr_system' ? 'selected' : ''; ?>>Using HR System</option>
                </select>
            </form>
            <div class="search-box" style="min-width: 240px; flex: 1 1 240px; max-width: 320px;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control form-control-sm" id="searchPortal" placeholder="Search employees..." onkeyup="filterTable('searchPortal', 'portalTable')">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="portalTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Employee Name</th>
                        <th>Branch</th>
                        <th>Account Status</th>
                        <th>Portal Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = 1; ?>
                    <?php while ($emp = $employees->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $row_number++; ?></strong></td>
                            <td>
                                <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                            </td>
                            <td>
                                <div><strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong></div>
                                <small class="text-muted"><?php echo e($emp['job_title']); ?> (ID: <?php echo e(getEmployeeDisplayId($emp)); ?>)</small>
                            </td>
                            <td><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($emp['user_id']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Has Account</span>
                                    <div class="mt-1 small fw-bold text-primary"><?php echo e($emp['role']); ?></div>
                                    <?php if (!$emp['user_active']): ?>
                                        <span class="badge bg-danger ms-1">Inactive</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-times-circle me-1"></i>No Account</span>
                                <?php endif; ?>
                                <?php if (!empty($emp['hr_role'])): ?>
                                    <div class="mt-1 small text-muted">HR Account: <?php echo e($emp['hr_role']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $emp['username'] ? '<code>' . e($emp['username']) . '</code>' : '<span class="text-muted small">Not Set</span>'; ?>
                            </td>
                            <td>
                                <?php if (!$emp['user_id']): ?>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="openCreateAccountModal(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>', '<?php echo e(addslashes($emp['personal_email'] ?? '')); ?>', '<?php echo e(addslashes(getEmployeeDisplayId($emp))); ?>')">
                                        <i class="fas fa-plus me-1"></i>Create Account
                                    </button>
                                <?php else: ?>
                                    <?php if (($emp['role'] ?? '') === 'Employee'): ?>
                                        <a href="employee-portal-user.php?user_id=<?php echo (int)$emp['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-id-card me-1"></i>Manage Portal
                                        </a>
                                    <?php else: ?>
                                        <a href="users.php?search=<?php echo urlencode($emp['username']); ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-user-cog me-1"></i>Manage (HR)
                                        </a>
                                    <?php endif; ?>
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
            <nav aria-label="Portal accounts pagination">
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

<!-- Create Account Modal -->
<div class="modal fade" id="createAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Create Portal Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add-user.php">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    <input type="hidden" name="full_name" id="modal_full_name">
                    <input type="hidden" name="email" id="modal_email">
                    <input type="hidden" name="role" value="Employee">
                    <input type="hidden" name="redirect" value="employee-accounts.php">
                    
                    <div class="alert alert-info py-2" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-2"></i>Creating a portal account for: <strong id="display_emp_name"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="modal_username" required>
                        <div class="form-text">Employee ID is suggested, but you can change it.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="modal_password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">
                                <i class="fas fa-random"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 6 characters. Use the random button to generate one.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateAccountModal(id, name, email, employeeCode) {
    document.getElementById('modal_employee_id').value = id;
    document.getElementById('modal_full_name').value = name;
    document.getElementById('modal_email').value = email;
    document.getElementById('modal_username').value = employeeCode || '';
    document.getElementById('display_emp_name').textContent = name;
    
    // Clear password
    document.getElementById('modal_password').value = '';
    
    new bootstrap.Modal(document.getElementById('createAccountModal')).show();
}

function generatePassword() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let pass = "";
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('modal_password').value = pass;
    document.getElementById('modal_password').type = 'text';
}

function filterTable(inputId, tableId) {
    let input = document.getElementById(inputId);
    let filter = input.value.toLowerCase();
    let table = document.getElementById(tableId);
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let text = tr[i].textContent.toLowerCase();
        tr[i].style.display = text.includes(filter) ? "" : "none";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
