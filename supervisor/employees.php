<?php
$page_title = 'Employee Information';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch employees in the supervisor's branch
$branch_id = $_SESSION['branch_id'] ?? 0;
$stmt = $conn->prepare("
    SELECT e.*, b.branch_name, d.department_name 
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id 
    WHERE e.branch_id = ? AND e.is_active = 1 
    ORDER BY e.last_name, e.first_name
");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$employees = $stmt->get_result();
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">View profiles and update contact information for employees in your branch.</p>
</div>

<?php displayFlashMessage(); ?>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>Branch Employees</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="customSearchEmp" placeholder="Search employees...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="empTable">
                <thead>
                    <tr>
                        <th style="cursor: pointer;" onclick="sortTable(0)">Name <i class="fas fa-sort text-muted ms-1" style="font-size: 0.8rem;"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(1)">Job Title <i class="fas fa-sort text-muted ms-1" style="font-size: 0.8rem;"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(2)">Department <i class="fas fa-sort text-muted ms-1" style="font-size: 0.8rem;"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(3)">Branch <i class="fas fa-sort text-muted ms-1" style="font-size: 0.8rem;"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(4)">Hire Date <i class="fas fa-sort text-muted ms-1" style="font-size: 0.8rem;"></i></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employees->num_rows === 0): ?>
                        <tr class="no-results-row"><td colspan="6" class="text-center py-4 text-muted">No active employees found in your branch.</td></tr>
                    <?php else: ?>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                        <strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo e($emp['job_title']); ?></td>
                                <td><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                                <td><small><?php echo formatDate($emp['hire_date']); ?></small></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/supervisor/view-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/supervisor/edit-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Contact Info">
                                        <i class="fas fa-address-card"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
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

<script>
// State Variables
let sortDirection = false;
let currentSortColumn = -1;
let currentPage = 1;
const ITEMS_PER_PAGE = 10;

function sortTable(columnIndex) {
    if (currentSortColumn === columnIndex) {
        sortDirection = !sortDirection;
    } else {
        currentSortColumn = columnIndex;
        sortDirection = false;
    }
    
    // Update icons
    const ths = document.querySelectorAll("#empTable thead th");
    ths.forEach((th, idx) => {
        const icon = th.querySelector("i.fas");
        if (icon) {
            if (idx === columnIndex) {
                icon.className = sortDirection ? "fas fa-sort-up ms-1" : "fas fa-sort-down ms-1";
                icon.classList.remove("text-muted");
                icon.classList.add("text-primary");
            } else {
                icon.className = "fas fa-sort text-muted ms-1";
                icon.classList.remove("text-primary");
            }
        }
    });

    renderTable();
}

document.getElementById('customSearchEmp').addEventListener('input', function() {
    currentPage = 1; // Reset to page 1 on search
    renderTable();
});

function goToPage(page) {
    currentPage = page;
    renderTable();
}

function renderTable() {
    const tbody = document.querySelector("#empTable tbody");
    const allRowsArr = Array.from(tbody.querySelectorAll("tr:not(.no-results-row)"));
    const filterInput = document.getElementById('customSearchEmp').value.toLowerCase().trim();
    
    let visibleRows = [];
    
    // 1. Filter
    allRowsArr.forEach(row => {
        const cells = Array.from(row.querySelectorAll("td"));
        if (cells.length > 1) {
            const rowText = cells.slice(0, 5).map(td => td.textContent.trim().replace(/\s+/g, ' ')).join(' ').toLowerCase();
            if (filterInput === "" || rowText.includes(filterInput)) {
                visibleRows.push(row);
                row.classList.remove('filtered-out');
            } else {
                row.classList.add('filtered-out');
                row.style.display = "none";
            }
        }
    });

    // 2. Sort
    if (currentSortColumn !== -1) {
        visibleRows.sort((a, b) => {
            let valA = a.querySelectorAll("td")[currentSortColumn].textContent.trim();
            let valB = b.querySelectorAll("td")[currentSortColumn].textContent.trim();
            
            if (currentSortColumn === 4) { // Hire Date
                valA = new Date(valA).getTime() || valA;
                valB = new Date(valB).getTime() || valB;
            }

            if (valA < valB) return sortDirection ? -1 : 1;
            if (valA > valB) return sortDirection ? 1 : -1;
            return 0;
        });
        
        // Re-append sorted rows to DOM
        visibleRows.forEach(row => tbody.appendChild(row));
    }

    // 3. Paginate
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
             
    // Page Numbers
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
    let noResultsRow = tbody.querySelector('.no-results-row.search-empty');
    if (totalItems === 0 && filterInput !== "") {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row search-empty text-center';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.innerHTML = `<td colspan="6" class="py-4 text-muted"><i class="fas fa-search fa-2x mb-3 d-block"></i>No employees found matching "<strong>${filterInput}</strong>"</td>`;
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

// Initial Render on Load
document.addEventListener("DOMContentLoaded", renderTable);
</script>

<?php require_once '../includes/footer.php'; ?>
