<?php
$page_title = 'Report Generation';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/header.php';

// Fetch branches and departments for filter dropdowns
$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active = 1 AND deleted_at IS NULL ORDER BY branch_name");
$departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
?>

<!-- Report Generation Module -->
<div class="reports-module">

    <!-- Page Header -->
    <div class="report-page-header mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="report-header-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <h2 class="mb-0" style="font-weight:700;color:var(--text-dark);">Report Generation</h2>
                <p class="mb-0 text-muted" style="font-size:0.9rem;">Generate, preview, and export comprehensive HR reports</p>
            </div>
        </div>
    </div>

    <!-- Report Type Selection Cards -->
    <div class="row mb-4" id="reportTypeCards">
        <div class="col-md-4">
            <div class="report-type-card active" data-type="employee_masterlist" id="card-employee_masterlist">
                <div class="rtc-icon"><i class="fas fa-address-book"></i></div>
                <div class="rtc-info">
                    <h6>Employee Masterlist</h6>
                    <p>Complete roster with employment details, contacts, and branch assignments.</p>
                </div>
                <div class="rtc-check"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="report-type-card" data-type="performance_summary" id="card-performance_summary">
                <div class="rtc-icon"><i class="fas fa-chart-line"></i></div>
                <div class="rtc-info">
                    <h6>Performance Summary</h6>
                    <p>Evaluation scores, performance levels, and trend analysis per employee.</p>
                </div>
                <div class="rtc-check"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="report-type-card" data-type="career_movements" id="card-career_movements">
                <div class="rtc-icon"><i class="fas fa-route"></i></div>
                <div class="rtc-info">
                    <h6>Career Movements</h6>
                    <p>Promotions, transfers, demotions, and role changes with approval status.</p>
                </div>
                <div class="rtc-check"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="content-card mb-4" id="filterCard">
        <div class="card-header">
            <h5><i class="fas fa-sliders-h me-2"></i>Report Filters</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnResetFilters">
                <i class="fas fa-undo me-1"></i>Reset
            </button>
        </div>
        <div class="card-body">
            <form id="reportForm" class="row align-items-end g-3">
                <input type="hidden" name="report_type" id="reportType" value="employee_masterlist">
                
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <select class="form-select" name="branch_id" id="filterBranch">
                        <option value="">All Branches</option>
                        <?php while ($b = $branches->fetch_assoc()): ?>
                            <option value="<?php echo $b['branch_id']; ?>"><?php echo e($b['branch_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department" id="filterDepartment">
                        <option value="">All Departments</option>
                        <?php while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?php echo $d['department_id']; ?>"><?php echo e($d['department_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-2" id="dateFromGroup">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" id="filterDateFrom">
                </div>

                <div class="col-md-2" id="dateToGroup">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" id="filterDateTo">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100" id="btnGeneratePreview">
                        <i class="fas fa-search me-1"></i>Generate Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="report-action-bar mb-3" id="reportActionBar" style="display:none;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="report-result-badge" id="resultCount">
                    <i class="fas fa-table me-1"></i><span id="rowCount">0</span> records found
                </span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" id="btnExportCSV">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </button>
                <button class="btn btn-danger btn-sm" id="btnExportPDF">
                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Preview Area -->
    <div class="content-card" id="previewCard" style="display:none;">
        <div class="card-header">
            <h5><i class="fas fa-eye me-2"></i>Report Preview</h5>
            <span class="badge bg-info" id="reportTypeBadge">Employee Masterlist</span>
        </div>
        <div class="card-body p-0">
            <div id="reportPreviewArea">
                <!-- AJAX content loads here -->
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="report-loading-overlay" id="loadingOverlay" style="display:none;">
        <div class="report-spinner">
            <div class="spinner-border text-success" role="status"></div>
            <p class="mt-3 mb-0 text-muted">Generating report...</p>
        </div>
    </div>

    <!-- Empty State (initial) -->
    <div class="content-card" id="emptyState">
        <div class="card-body text-center py-5">
            <div class="empty-state-icon mb-3">
                <i class="fas fa-file-alt"></i>
            </div>
            <h5 class="text-muted">Select a Report Type &amp; Click Generate</h5>
            <p class="text-muted mb-0" style="font-size:0.9rem;">Choose a report type above, configure your filters, then click <strong>Generate Preview</strong> to see results.</p>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE = '<?php echo BASE_URL; ?>';
    const reportTypeCards = document.querySelectorAll('.report-type-card');
    const reportTypeInput = document.getElementById('reportType');
    const reportForm = document.getElementById('reportForm');
    const previewCard = document.getElementById('previewCard');
    const previewArea = document.getElementById('reportPreviewArea');
    const actionBar = document.getElementById('reportActionBar');
    const emptyState = document.getElementById('emptyState');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const reportTypeBadge = document.getElementById('reportTypeBadge');
    const rowCountEl = document.getElementById('rowCount');
    const dateFromGroup = document.getElementById('dateFromGroup');
    const dateToGroup = document.getElementById('dateToGroup');

    const typeLabels = {
        'employee_masterlist': 'Employee Masterlist',
        'performance_summary': 'Performance Summary',
        'career_movements': 'Career Movements'
    };

    // Report type card selection
    reportTypeCards.forEach(card => {
        card.addEventListener('click', function() {
            reportTypeCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            reportTypeInput.value = this.dataset.type;
            
            // Toggle date fields visibility
            if (this.dataset.type === 'employee_masterlist') {
                dateFromGroup.style.opacity = '0.4';
                dateFromGroup.querySelector('input').disabled = true;
                dateToGroup.style.opacity = '0.4';
                dateToGroup.querySelector('input').disabled = true;
            } else {
                dateFromGroup.style.opacity = '1';
                dateFromGroup.querySelector('input').disabled = false;
                dateToGroup.style.opacity = '1';
                dateToGroup.querySelector('input').disabled = false;
            }
        });
    });

    // Initial state: disable date fields for employee_masterlist
    dateFromGroup.style.opacity = '0.4';
    dateFromGroup.querySelector('input').disabled = true;
    dateToGroup.style.opacity = '0.4';
    dateToGroup.querySelector('input').disabled = true;

    // Generate Preview
    reportForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        emptyState.style.display = 'none';
        loadingOverlay.style.display = 'flex';
        previewCard.style.display = 'none';
        actionBar.style.display = 'none';

        fetch(BASE + '/manager/ajax/generate-report.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            loadingOverlay.style.display = 'none';
            if (data.success) {
                previewArea.innerHTML = data.html;
                previewCard.style.display = 'block';
                actionBar.style.display = 'block';
                reportTypeBadge.textContent = typeLabels[reportTypeInput.value] || reportTypeInput.value;
                rowCountEl.textContent = data.count || 0;
                
                // Animate in
                previewCard.style.animation = 'fadeSlideUp 0.4s ease';
                actionBar.style.animation = 'fadeSlideUp 0.3s ease';
            } else {
                previewArea.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-exclamation-triangle fa-2x mb-3 d-block" style="opacity:.4"></i>' + (data.message || 'No results found.') + '</div>';
                previewCard.style.display = 'block';
                actionBar.style.display = 'none';
            }
        })
        .catch(err => {
            loadingOverlay.style.display = 'none';
            previewArea.innerHTML = '<div class="text-center py-5 text-danger"><i class="fas fa-times-circle fa-2x mb-3 d-block"></i>An error occurred while generating the report.</div>';
            previewCard.style.display = 'block';
        });
    });

    // Export CSV
    document.getElementById('btnExportCSV').addEventListener('click', function() {
        exportReport('csv');
    });

    // Export PDF
    document.getElementById('btnExportPDF').addEventListener('click', function() {
        exportReport('pdf');
    });

    function exportReport(exportType) {
        const formData = new FormData(reportForm);
        formData.append('export_type', exportType);
        
        // Build query string
        const params = new URLSearchParams(formData).toString();
        window.location.href = BASE + '/manager/export-report.php?' + params;
    }

    // Reset Filters
    document.getElementById('btnResetFilters').addEventListener('click', function() {
        reportForm.reset();
        reportTypeInput.value = 'employee_masterlist';
        reportTypeCards.forEach(c => c.classList.remove('active'));
        document.getElementById('card-employee_masterlist').classList.add('active');
        dateFromGroup.style.opacity = '0.4';
        dateFromGroup.querySelector('input').disabled = true;
        dateToGroup.style.opacity = '0.4';
        dateToGroup.querySelector('input').disabled = true;
        previewCard.style.display = 'none';
        actionBar.style.display = 'none';
        emptyState.style.display = 'block';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
