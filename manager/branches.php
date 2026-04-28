<?php
$page_title = 'Branch Management';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// ─── Handle ADD ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['branch_name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '' || $location === '') {
            redirectWith(BASE_URL . '/manager/branches.php', 'danger', 'Branch name and location are required.');
        }

        $stmt = $conn->prepare("INSERT INTO branches (branch_name, location) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $location);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Branch', $new_id, "Added branch: $name");
        redirectWith(BASE_URL . '/manager/branches.php', 'success', "Branch \"$name\" added successfully.");
    }

    // ─── Handle EDIT ─────────────────────────────────────
    if ($_POST['action'] === 'edit') {
        $id = (int) ($_POST['branch_id'] ?? 0);
        $name = trim($_POST['branch_name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($id <= 0 || $name === '' || $location === '') {
            redirectWith(BASE_URL . '/manager/branches.php', 'danger', 'All fields are required.');
        }

        $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, location = ? WHERE branch_id = ?");
        $stmt->bind_param("ssi", $name, $location, $id);
        $stmt->execute();
        $stmt->close();

        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Branch', $id, "Updated branch: $name");
        redirectWith(BASE_URL . '/manager/branches.php', 'success', "Branch \"$name\" updated successfully.");
    }

    // ─── Handle IMPORT CSV ───────────────────────────────
    if ($_POST['action'] === 'import_csv') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            redirectWith(BASE_URL . '/manager/branches.php', 'danger', 'Please select a valid CSV file.');
        }

        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension !== 'csv') {
            redirectWith(BASE_URL . '/manager/branches.php', 'danger', 'Only CSV files are allowed.');
        }

        if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
            $imported_count = 0;
            $skipped_count = 0;

            // Read the first row to determine if it's a header
            $header = fgetcsv($handle, 1000, ',');
            if ($header) {
                // Determine column indices
                $header_lower = array_map('strtolower', array_map('trim', $header));
                
                // See if it looks like a header row
                $is_header = false;
                foreach ($header_lower as $col) {
                    if (strpos($col, 'branch') !== false || strpos($col, 'location') !== false || strpos($col, 'name') !== false) {
                        $is_header = true;
                        break;
                    }
                }

                $stmt = $conn->prepare("INSERT INTO branches (branch_name, location) VALUES (?, ?)");

                // If the first row wasn't a header, process it as data
                if (!$is_header) {
                    $name = trim($header[0] ?? '');
                    $location = trim($header[1] ?? '');
                    if ($name !== '' && $location !== '') {
                        $stmt->bind_param("ss", $name, $location);
                        $stmt->execute();
                        $imported_count++;
                    } else {
                        $skipped_count++;
                    }
                }

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $name = trim($data[0] ?? '');
                    $location = trim($data[1] ?? '');

                    if ($name !== '' && $location !== '') {
                        $stmt->bind_param("ss", $name, $location);
                        $stmt->execute();
                        $imported_count++;
                    } else {
                        $skipped_count++;
                    }
                }
                $stmt->close();
            }
            fclose($handle);

            if ($imported_count > 0) {
                logAudit($conn, $_SESSION['user_id'], 'IMPORT', 'Branch', null, "Imported $imported_count branches from CSV");
                $msg = "Successfully imported $imported_count branch(es).";
                if ($skipped_count > 0) $msg .= " Skipped $skipped_count invalid/empty row(s).";
                redirectWith(BASE_URL . '/manager/branches.php', 'success', $msg);
            } else {
                redirectWith(BASE_URL . '/manager/branches.php', 'warning', 'No valid branch data found in the CSV file.');
            }
        } else {
            redirectWith(BASE_URL . '/manager/branches.php', 'danger', 'Failed to read the uploaded CSV file.');
        }
    }
}

// ─── Handle DELETE (GET with confirmation) ───────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bid = (int) $_GET['delete'];

    // Check if employees or users are assigned
    $emp_check = $conn->query("SELECT COUNT(*) as cnt FROM employees WHERE branch_id = $bid AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['cnt'];
    $usr_check = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE branch_id = $bid")->fetch_assoc()['cnt'];

    if ($emp_check > 0 || $usr_check > 0) {
        $msg = "Cannot delete branch — it still has ";
        $parts = [];
        if ($emp_check > 0)
            $parts[] = "$emp_check employee(s)";
        if ($usr_check > 0)
            $parts[] = "$usr_check user(s)";
        $msg .= implode(' and ', $parts) . " assigned to it.";
        redirectWith(BASE_URL . '/manager/branches.php', 'danger', $msg);
    }

    // Check career movements
    $cm_check = $conn->query("SELECT COUNT(*) as cnt FROM career_movements WHERE previous_branch_id = $bid OR new_branch_id = $bid")->fetch_assoc()['cnt'];
    if ($cm_check > 0) {
        redirectWith(BASE_URL . '/manager/branches.php', 'danger', "Cannot delete branch — it is referenced by $cm_check career movement(s).");
    }

    // Safe to delete
    $branch_name_row = $conn->query("SELECT branch_name FROM branches WHERE branch_id = $bid")->fetch_assoc();
    $conn->query("DELETE FROM branches WHERE branch_id = $bid");
    logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Branch', $bid, "Deleted branch: " . ($branch_name_row['branch_name'] ?? 'Unknown'));
    redirectWith(BASE_URL . '/manager/branches.php', 'success', 'Branch deleted successfully.');
}

require_once '../includes/header.php';

// Fetch branches with counts
$branches = $conn->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM employees WHERE branch_id = b.branch_id AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)) as employee_count,
           (SELECT COUNT(*) FROM users WHERE branch_id = b.branch_id) as user_count
    FROM branches b
    ORDER BY b.branch_name
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage company branches and locations</p>
    <div>
        <button class="btn btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#importBranchModal">
            <i class="fas fa-file-csv me-2"></i>Import CSV
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus me-2"></i>Add Branch
        </button>
    </div>
</div>

<div class="chart-card fadeup">
    <div class="cc-header">
        <h5><i class="fas fa-building me-2"></i>All Branches</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchBranch" placeholder="Search branches...">
        </div>
    </div>
    <div class="cc-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="branchTable">
                <thead>
                    <tr>
                        <th style="cursor:pointer;" onclick="sortTable(0)">Branch Name <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(1)">Location <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(2)">Employees <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(3)">Users <i class="fas fa-sort text-muted ms-1"
                                style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(4)">Created <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($branches->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4"><i
                                    class="fas fa-building fa-2x mb-2 d-block" style="opacity:0.3;"></i>No branches found.
                                Add your first branch above.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($b = $branches->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle me-2 text-white d-flex align-items-center justify-content-center"
                                            style="width:32px;height:32px;background:var(--primary-light);font-size:0.8rem;font-weight:bold;">
                                            <i class="fas fa-building" style="font-size:0.75rem;"></i>
                                        </div>
                                        <strong><?php echo e($b['branch_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo e($b['location']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $b['employee_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $b['user_count']; ?></span>
                                </td>
                                <td><small><?php echo formatDate($b['created_at']); ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" title="Edit"
                                        onclick="openEditModal(<?php echo $b['branch_id']; ?>, '<?php echo e(addslashes($b['branch_name'])); ?>', '<?php echo e(addslashes($b['location'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#editBranchModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"
                                        onclick="setDeleteTarget(<?php echo $b['branch_id']; ?>, '<?php echo e(addslashes($b['branch_name'])); ?>', <?php echo $b['employee_count'] + $b['user_count']; ?>)"
                                        data-bs-toggle="modal" data-bs-target="#deleteBranchModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<?php require_once '../includes/footer.php'; ?>

<!-- ─── Import Branch Modal ─────────────────────────────────── -->
<div class="modal fade" id="importBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-csv me-2"></i>Import Branches via CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <p class="mb-1"><strong>Expected CSV Format:</strong></p>
                        <ul class="mb-2">
                            <li>Column 1: <strong>Branch Name</strong></li>
                            <li>Column 2: <strong>Location</strong></li>
                        </ul>
                        <p class="mb-0 text-muted">The first row will be considered a header and skipped if it contains column labels.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i>Import CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── Add Branch Modal ─────────────────────────────────── -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Branch</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="branch_name" required placeholder="e.g. Branch 3"
                            id="addBranchName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="location" required placeholder="e.g. Tayabas"
                            id="addBranchLocation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── Edit Branch Modal ────────────────────────────────── -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="branch_id" id="editBranchId">
                <div class="modal-header" style="background:var(--primary);color:#fff;">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Branch</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="branch_name" required id="editBranchName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="location" required id="editBranchLocation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── Delete Confirmation Modal ────────────────────────── -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Permanently delete <strong id="deleteBranchName"></strong>?</p>
                <p class="text-danger small" id="deleteWarning"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    // ─── Edit Modal Prefill ──────────────────────────────────
    function openEditModal(id, name, location) {
        document.getElementById('editBranchId').value = id;
        document.getElementById('editBranchName').value = name;
        document.getElementById('editBranchLocation').value = location;
    }

    // ─── Delete Modal ────────────────────────────────────────
    function setDeleteTarget(id, name, assignedCount) {
        document.getElementById('deleteBranchName').textContent = name;
        const warning = document.getElementById('deleteWarning');
        if (assignedCount > 0) {
            warning.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>This branch has assigned employees/users. Deletion will be blocked.';
        } else {
            warning.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>This action cannot be undone!';
        }
        document.getElementById('deleteConfirmBtn').href = '?delete=' + id;
    }

    // ─── Search, Sort & Pagination ───────────────────────────
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

        const ths = document.querySelectorAll("#branchTable thead th");
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

    document.getElementById('searchBranch').addEventListener('input', function () {
        currentPage = 1;
        renderTable();
    });

    function goToPage(page) {
        currentPage = page;
        renderTable();
    }

    function renderTable() {
        const tbody = document.querySelector("#branchTable tbody");
        const allRows = Array.from(tbody.querySelectorAll("tr:not(.no-results-row)"));
        const filterInput = document.getElementById('searchBranch').value.toLowerCase().trim();

        let visibleRows = [];

        allRows.forEach(row => {
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

        if (currentSortColumn !== -1) {
            visibleRows.sort((a, b) => {
                let valA = a.querySelectorAll("td")[currentSortColumn].textContent.trim();
                let valB = b.querySelectorAll("td")[currentSortColumn].textContent.trim();

                if (currentSortColumn === 2 || currentSortColumn === 3) {
                    valA = parseInt(valA) || 0;
                    valB = parseInt(valB) || 0;
                }
                if (currentSortColumn === 4) {
                    valA = new Date(valA).getTime() || valA;
                    valB = new Date(valB).getTime() || valB;
                }

                if (valA < valB) return sortDirection ? -1 : 1;
                if (valA > valB) return sortDirection ? 1 : -1;
                return 0;
            });

            visibleRows.forEach(row => tbody.appendChild(row));
        }

        const totalPages = Math.ceil(visibleRows.length / ITEMS_PER_PAGE);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
        const endIdx = startIdx + ITEMS_PER_PAGE;

        visibleRows.forEach((row, index) => {
            row.style.display = (index >= startIdx && index < endIdx) ? "" : "none";
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
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage - 1})">Previous</button>
             </li>`;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        if (startPage > 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" onclick="goToPage(${i})">${i}</button>
                 </li>`;
        }
        if (endPage < totalPages) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;

        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage + 1})">Next</button>
             </li>`;

        digits.innerHTML = html;
    }

    function handleNoResults(totalItems, filterInput, tbody) {
        let noResultsRow = tbody.querySelector('.no-results-row');
        if (totalItems === 0 && filterInput !== "") {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row text-center';
                tbody.appendChild(noResultsRow);
            }
            noResultsRow.innerHTML = `<td colspan="6" class="py-4 text-muted"><i class="fas fa-search fa-2x mb-3 d-block"></i>No branches found matching "<strong>${filterInput}</strong>"</td>`;
            noResultsRow.style.display = '';
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    }

    document.addEventListener("DOMContentLoaded", renderTable);
</script>