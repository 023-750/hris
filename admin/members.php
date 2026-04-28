<?php
$page_title = 'Member List';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

require_once '../includes/header.php';

// Pagination settings
$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// Count all employees (excluding strictly Admin accounts)
$total_members_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM employees e
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
");
$total_members = (int)($total_members_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_members / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

// Fetch paginated employees with branch info (excluding strictly Admin accounts)
$employees = $conn->query("
    SELECT e.employee_id, e.first_name, e.last_name, e.middle_name, e.job_title, b.branch_name, e.profile_picture, e.is_active
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY e.last_name, e.first_name
    LIMIT $per_page OFFSET $offset
");

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Full list of registered employees in the HRIS system</p>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-id-card me-2"></i>Employee Members</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchMembers" placeholder="Search members..." onkeyup="filterTable('searchMembers', 'membersTable')">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="membersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Branch</th>
                        <th>Position</th>
                        <th>Status</th>
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
                            <td><strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']); ?></strong></td>
                            <td><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                            <td><?php echo e($emp['job_title'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-0 pt-0">
            <nav aria-label="Members pagination">
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

<?php require_once '../includes/footer.php'; ?>
