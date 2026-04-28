<?php
$page_title = 'Employees';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Handle activate/deactivate
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $eid = (int)$_GET['deactivate'];
    $conn->query("UPDATE employees SET is_active = 0 WHERE employee_id = $eid");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee', $eid, 'Deactivated employee');
    redirectWith(BASE_URL . '/manager/employees.php', 'success', 'Employee deactivated successfully.');
}
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $eid = (int)$_GET['activate'];
    $conn->query("UPDATE employees SET is_active = 1 WHERE employee_id = $eid");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee', $eid, 'Reactivated employee');
    redirectWith(BASE_URL . '/manager/employees.php', 'success', 'Employee reactivated successfully.');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $eid = (int)$_GET['delete'];
    // Delete the employee and all normalized sub-tables
    $tables = [
        'employee_details', 'employee_government_ids', 'employee_addresses', 
        'employee_contacts', 'employee_emergency_contacts', 'employee_disclosures', 
        'employee_family', 'employee_children', 'employee_siblings', 
        'employee_education', 'employee_work_experience', 'employee_trainings', 
        'employee_voluntary_work', 'employee_eligibility', 'employee_skills', 
        'employee_recognitions', 'employee_memberships', 'employee_real_properties', 
        'employee_personal_properties', 'employee_liabilities', 'employee_references'
    ];
    foreach ($tables as $tbl) {
        $conn->query("DELETE FROM $tbl WHERE employee_id = $eid");
    }
    
    // Delete career movements and evaluations (Evaluations have their own score sub-deletion)
    $conn->query("DELETE FROM career_movements WHERE employee_id = $eid");
    
    $conn->query("DELETE FROM employees WHERE employee_id = $eid");
    logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Employee', $eid, 'Permanently deleted employee');
    redirectWith(BASE_URL . '/manager/employees.php', 'success', 'Employee deleted permanently.');
}

require_once '../includes/header.php';

// Fetch employees
$employees = $conn->query("
    SELECT e.*, b.branch_name, d.department_name 
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id 
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY e.last_name, e.first_name
");

// Fetch distinct values for filter dropdowns
$job_titles_res = $conn->query("SELECT DISTINCT job_title FROM employees WHERE job_title IS NOT NULL AND job_title != '' ORDER BY job_title ASC");
$job_titles = [];
while ($r = $job_titles_res->fetch_assoc()) $job_titles[] = $r['job_title'];

$departments_res = $conn->query("SELECT d.department_name FROM departments d ORDER BY d.department_name ASC");
$departments = [];
while ($r = $departments_res->fetch_assoc()) $departments[] = $r['department_name'];

$branches_res = $conn->query("SELECT b.branch_name FROM branches b ORDER BY b.branch_name ASC");
$branches = [];
while ($r = $branches_res->fetch_assoc()) $branches[] = $r['branch_name'];

$statuses = ['Regular', 'Probationary', 'Contractual'];
$selected_branch = $_GET['branch'] ?? '';
?>

<style>
    /* Filter Toolbar */
    .filter-toolbar {
        padding: 16px 20px;
        background: linear-gradient(135deg, #f8f9fc 0%, #f1f3f8 100%);
        border-bottom: 1px solid #e8ecf1;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }
    .filter-group {
        position: relative;
        min-width: 180px;
        flex: 1;
    }
    .filter-group label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8094ae;
        margin-bottom: 4px;
    }
    .filter-group select {
        width: 100%;
        padding: 8px 32px 8px 12px;
        border: 1px solid #dce3ed;
        border-radius: 8px;
        background: #fff;
        font-size: 0.85rem;
        font-weight: 500;
        color: #344357;
        transition: all 0.2s ease;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238094ae' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        cursor: pointer;
    }
    .filter-group select:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 59,130,246), 0.1);
    }
    .filter-group select.active-filter {
        border-color: var(--primary-blue);
        background-color: #eef4ff;
        color: var(--primary-blue);
        font-weight: 600;
    }
    .filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding-bottom: 1px;
    }
    .filter-summary {
        padding: 8px 20px;
        background: #fff;
        border-bottom: 1px solid #e8ecf1;
        display: none;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .filter-summary.has-filters {
        display: flex;
    }
    .filter-summary .filter-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #8094ae;
        margin-right: 4px;
    }
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        background: #eef4ff;
        border: 1px solid #d0dfff;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary-blue);
        animation: chipIn 0.2s ease;
    }
    .filter-chip .chip-category {
        font-weight: 400;
        color: #8094ae;
    }
    .filter-chip .remove-chip {
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.15s;
        font-size: 0.65rem;
    }
    .filter-chip .remove-chip:hover {
        opacity: 1;
    }
    .btn-clear-filters {
        font-size: 0.75rem;
        font-weight: 600;
        color: #dc3545;
        background: none;
        border: none;
        padding: 3px 8px;
        cursor: pointer;
        transition: all 0.15s;
        border-radius: 6px;
    }
    .btn-clear-filters:hover {
        background: #fff5f5;
    }
    @keyframes chipIn {
        from { transform: scale(0.85); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    @media (max-width: 768px) {
        .filter-group { min-width: 140px; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage employee records</p>
    <!-- Add Employee button removed as requested -->
</div>

<div class="chart-card fadeup">
    <div class="cc-header">
        <h5><i class="fas fa-users me-2"></i>All Employees</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="customSearchEmp" placeholder="Search employees...">
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="filter-toolbar" id="filterToolbar">
        <div class="filter-group">
            <label><i class="fas fa-briefcase me-1"></i>Job Title</label>
            <select id="filterJobTitle">
                <option value="">All Titles</option>
                <?php foreach ($job_titles as $jt): ?>
                    <option value="<?php echo e($jt); ?>"><?php echo e($jt); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-sitemap me-1"></i>Department</label>
            <select id="filterDepartment">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo e($dept); ?>"><?php echo e($dept); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-building me-1"></i>Branch</label>
            <select id="filterBranch">
                <option value="">All Branches</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?php echo e($br); ?>" <?php echo ($selected_branch === $br) ? 'selected' : ''; ?>><?php echo e($br); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-user-tag me-1"></i>Status</label>
            <select id="filterStatus">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Active Filter Chips -->
    <div class="filter-summary" id="filterSummary">
        <span class="filter-label"><i class="fas fa-filter me-1"></i>Filters:</span>
        <div id="filterChips"></div>
        <button class="btn-clear-filters" id="clearAllFilters" title="Clear all filters">
            <i class="fas fa-times me-1"></i>Clear All
        </button>
    </div>
    <div class="cc-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="empTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Job Title</th>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($emp = $employees->fetch_assoc()): ?>
                        <tr data-jobtitle="<?php echo e($emp['job_title']); ?>" data-department="<?php echo e($emp['department_name'] ?? 'N/A'); ?>" data-branch="<?php echo e($emp['branch_name'] ?? 'N/A'); ?>" data-status="<?php echo e($emp['employment_status']); ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    <strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo e($emp['job_title']); ?></td>
                            <td><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                            <td><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $emp['employment_status']; ?>
                                </span>
                            </td>
                            <td><small><?php echo formatDate($emp['hire_date']); ?></small></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($emp['is_active']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Deactivate"
                                            onclick="setDeactivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Activate"
                                            onclick="setActivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#activateModal">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Permanently"
                                        onclick="setDeleteTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="paginationWrapper">
            <div id="paginationInfo" class="text-muted small"></div>
            <ul class="pagination pagination-sm mb-0" id="paginationNumbers">
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Deactivate Confirmation Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-user-slash me-2"></i>Deactivate Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Deactivate <strong id="deactivateEmpName"></strong>?</p>
                <p class="text-muted small">This will mark them as inactive. You can reactivate later.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deactivateConfirmBtn" class="btn btn-warning"><i class="fas fa-user-slash me-1"></i>Deactivate</a>
            </div>
        </div>
    </div>
</div>

<!-- Activate Confirmation Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-check me-2"></i>Activate Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Reactivate <strong id="activateEmpName"></strong>?</p>
                <p class="text-muted small">This will mark them as an active employee again.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="activateConfirmBtn" class="btn btn-success"><i class="fas fa-user-check me-1"></i>Activate</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Permanently delete <strong id="deleteEmpName"></strong>?</p>
                <p class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>This will remove all their records including evaluations. This cannot be undone!</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete Permanently</a>
            </div>
        </div>
    </div>
</div>

<script>
function setDeactivateTarget(id, name) {
    document.getElementById('deactivateEmpName').textContent = name;
    document.getElementById('deactivateConfirmBtn').href = '?deactivate=' + id;
}
function setActivateTarget(id, name) {
    document.getElementById('activateEmpName').textContent = name;
    document.getElementById('activateConfirmBtn').href = '?activate=' + id;
}
function setDeleteTarget(id, name) {
    document.getElementById('deleteEmpName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = '?delete=' + id;
}

// State Variables
let currentPage = 1;
const ITEMS_PER_PAGE = 10;

document.getElementById('customSearchEmp').addEventListener('input', function() {
    currentPage = 1;
    renderTable();
});

// --- Dropdown Filter Logic ---
const filterSelects = ['filterJobTitle', 'filterDepartment', 'filterBranch', 'filterStatus'];
const filterLabels = { filterJobTitle: 'Job Title', filterDepartment: 'Department', filterBranch: 'Branch', filterStatus: 'Status' };

filterSelects.forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        currentPage = 1;
        this.classList.toggle('active-filter', this.value !== '');
        renderTable();
        updateFilterChips();
    });
});

function updateFilterChips() {
    const chipsContainer = document.getElementById('filterChips');
    const summary = document.getElementById('filterSummary');
    let html = '';
    let hasAny = false;

    filterSelects.forEach(id => {
        const el = document.getElementById(id);
        if (el.value !== '') {
            hasAny = true;
            html += `<span class="filter-chip"><span class="chip-category">${filterLabels[id]}:</span> ${el.value} <i class="fas fa-times remove-chip" data-filter="${id}"></i></span>`;
        }
    });

    chipsContainer.innerHTML = html;
    summary.classList.toggle('has-filters', hasAny);

    // Bind remove chip clicks
    chipsContainer.querySelectorAll('.remove-chip').forEach(btn => {
        btn.addEventListener('click', function() {
            const filterId = this.dataset.filter;
            const select = document.getElementById(filterId);
            select.value = '';
            select.classList.remove('active-filter');
            currentPage = 1;
            renderTable();
            updateFilterChips();
        });
    });
}

document.getElementById('clearAllFilters').addEventListener('click', function() {
    filterSelects.forEach(id => {
        const el = document.getElementById(id);
        el.value = '';
        el.classList.remove('active-filter');
    });
    currentPage = 1;
    renderTable();
    updateFilterChips();
});

function goToPage(page) {
    currentPage = page;
    renderTable();
}

function renderTable() {
    const tbody = document.querySelector("#empTable tbody");
    const allRows = Array.from(tbody.querySelectorAll("tr:not(.no-results-row)"));
    const filterInput = document.getElementById('customSearchEmp').value.toLowerCase().trim();

    // Get dropdown filter values
    const fJobTitle = document.getElementById('filterJobTitle').value;
    const fDepartment = document.getElementById('filterDepartment').value;
    const fBranch = document.getElementById('filterBranch').value;
    const fStatus = document.getElementById('filterStatus').value;
    
    let visibleRows = [];
    
    // 1. Filter (text search + dropdown filters)
    allRows.forEach(row => {
        const cells = Array.from(row.querySelectorAll("td"));
        if (cells.length > 1) {
            // Text search
            const rowText = cells.slice(0, 6).map(td => td.textContent.trim().replace(/\s+/g, ' ')).join(' ').toLowerCase();
            const textMatch = filterInput === "" || rowText.includes(filterInput);

            // Dropdown filters (use data attributes for precise matching)
            const dropdownMatch =
                (fJobTitle === '' || row.dataset.jobtitle === fJobTitle) &&
                (fDepartment === '' || row.dataset.department === fDepartment) &&
                (fBranch === '' || row.dataset.branch === fBranch) &&
                (fStatus === '' || row.dataset.status === fStatus);

            if (textMatch && dropdownMatch) {
                visibleRows.push(row);
                row.classList.remove('filtered-out');
            } else {
                row.classList.add('filtered-out');
                row.style.display = "none";
            }
        }
    });

    // 2. Paginate
    const totalPages = Math.ceil(visibleRows.length / ITEMS_PER_PAGE);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIdx = startIdx + ITEMS_PER_PAGE;

    visibleRows.forEach((row, index) => {
        if (index >= startIdx && index < endIdx) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });

    updatePaginationUI(visibleRows.length, totalPages);
    handleNoResults(visibleRows.length, filterInput, tbody);
}

function updatePaginationUI(totalItems, totalPages) {
    const info = document.getElementById("paginationInfo");
    const digits = document.getElementById("paginationNumbers");
    if (!info || !digits) return;
    
    if (totalItems === 0) {
        info.innerHTML = "Showing 0 entries";
        digits.innerHTML = "";
        return;
    }
    
    const start = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const end = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);
    info.innerHTML = `Showing ${start} to ${end} of ${totalItems} entries`;
    
    let html = "";
    
    // Previous Button
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage - 1})">Previous</button>
             </li>`;
             
    // Page Numbers (Show max 5 pagination buttons for cleaner look if many pages)
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }
    
    if (startPage > 1) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" onclick="goToPage(${i})">${i}</button>
                 </li>`;
    }
    if (endPage < totalPages) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    
    // Next Button
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage + 1})">Next</button>
             </li>`;
             
    digits.innerHTML = html;
}

function handleNoResults(totalItems, filterInput, tbody) {
    const hasDropdownFilter = filterSelects.some(id => document.getElementById(id).value !== '');
    let noResultsRow = tbody.querySelector('.no-results-row');
    if (totalItems === 0 && (filterInput !== "" || hasDropdownFilter)) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row text-center';
            tbody.appendChild(noResultsRow);
        }
        let msg = 'No employees match the current filters.';
        if (filterInput !== '') msg = `No employees found matching "<strong>${filterInput}</strong>"`;
        noResultsRow.innerHTML = `<td colspan="7" class="py-4 text-muted"><i class="fas fa-filter fa-2x mb-3 d-block" style="opacity:0.2;"></i>${msg}</td>`;
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

// Initial Render on Load
document.addEventListener("DOMContentLoaded", function() {
    renderTable();
    updateFilterChips();
});
</script>

