<?php
$page_title = 'Search Employees';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch departments for the filter dropdown
$departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <!-- Advanced Filters Sidebar -->
    <div class="col-lg-3 mb-4">
        <div class="content-card sticky-top" style="top: 100px; z-index: 10;">
            <div class="card-header border-0 bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2 text-primary"></i>Advanced Filters</h6>
            </div>
            <div class="card-body pt-0">
                <form id="advancedSearchForm" class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">General Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Name or employee ID...">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['department_id']; ?>"><?php echo e($d['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Job Position</label>
                        <input type="text" name="position" class="form-control" placeholder="e.g. Accountant">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Employment Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Regular">Regular</option>
                            <option value="Probationary">Probationary</option>
                            <option value="Contractual">Contractual</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Employment Type</label>
                        <select name="type" class="form-select">
                            <option value="">Any Type</option>
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                        </select>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync-alt me-2"></i>Apply Filters</button>
                        <button type="reset" id="resetBtn" class="btn btn-outline-secondary w-100 mt-2">Reset All</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Search Results -->
    <div class="col-lg-9">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-th-list me-2"></i>Search Results</h6>
                <div id="resultCount" class="badge bg-light text-muted fw-normal">0 results</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="searchResultsTable">
                        <thead class="bg-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department & Position</th>
                                <th>Employment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <!-- Results will be loaded here via AJAX -->
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-search-plus fa-3x mb-3 opacity-25"></i>
                                        <p>Use the filters on the left to start searching.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('advancedSearchForm');
    const resetBtn = document.getElementById('resetBtn');
    const resultsBody = document.getElementById('searchResultsBody');
    const resultCount = document.getElementById('resultCount');

    // Trigger initial search if needed, or leave empty
    // performSearch();

    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    resetBtn.addEventListener('click', function() {
        setTimeout(performSearch, 50); // Small delay to allow reset to finish
    });

    function performSearch() {
        const formData = new FormData(searchForm);
        const params = new URLSearchParams(formData).toString();
        
        resultsBody.innerHTML = '<tr><td colspan="4" class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        fetch(`ajax/search-employees-handler.php?${params}`)
            .then(response => response.json())
            .then(data => {
                resultsBody.innerHTML = '';
                resultCount.textContent = `${data.length} results`;

                if (data.length === 0) {
                    resultsBody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-user-slash fa-2x mb-2 d-block opacity-25"></i>No employees found matching those criteria.</td></tr>';
                    return;
                }

                data.forEach(emp => {
                    const row = `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar-sm me-3 bg-secondary-subtle rounded-circle d-flex align-items-center justify-content-center text-secondary fw-bold" style="width:40px; height:40px;">
                                        ${emp.pic_url ? 
                                            `<img src="${emp.pic_url}" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;">` : 
                                            emp.first_name.charAt(0) + emp.last_name.charAt(0)}
                                    </div>
                                    <div>
                                        <div class="fw-bold mb-0 text-dark">${emp.full_name}</div>
                                        <small class="text-muted">ID: ${emp.employee_id}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">${emp.job_title}</div>
                                <div class="small text-muted">${emp.department_name || 'N/A'}</div>
                            </td>
                            <td>
                                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 mb-1">${emp.employment_status}</div>
                                <div class="small text-muted">${emp.employment_type}</div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="view-employee.php?id=${emp.employee_id}" class="btn btn-sm btn-outline-info" title="View Profile"><i class="fas fa-eye"></i></a>
                                    <a href="edit-employee.php?id=${emp.employee_id}" class="btn btn-sm btn-outline-primary" title="Edit Contact Info"><i class="fas fa-address-card"></i></a>
                                </div>
                            </td>
                        </tr>
                    `;
                    resultsBody.innerHTML += row;
                });
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                resultsBody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>An error occurred while searching. Please try again.</td></tr>';
            });
    }
});
</script>

<style>
.user-avatar-sm { font-size: 0.8rem; }
.sticky-top { top: 1.5rem !important; }
.table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; border-bottom: 2px solid #f1f1f1; }
.table td { padding: 16px; border-bottom: 1px solid #f8f9fa; }
.form-select, .form-control { border-radius: 8px; border: 1.5px solid #eee; padding: 0.6rem 0.8rem; }
.form-select:focus, .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.05); border-color: #0d6efd; }
.bg-primary-subtle { background-color: #e7f1ff; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; letter-spacing: 0.3px; }
</style>

<?php require_once '../includes/footer.php'; ?>
