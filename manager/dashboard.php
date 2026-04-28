<?php
$page_title = 'Manager Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/header.php';

// Fetch stats
$branch_id = $_SESSION['branch_id'];

$total_employees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];
$pending_evals = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE status = 'Pending Manager'")->fetch_assoc()['c'];
$pending_movements = $conn->query("SELECT COUNT(*) as c FROM career_movements WHERE approval_status = 'Pending'")->fetch_assoc()['c'];
$avg_score_result = $conn->query("SELECT AVG(total_score) as avg FROM evaluations WHERE status = 'Approved'");
$avg_score = round($avg_score_result->fetch_assoc()['avg'] ?? 0, 1);
$new_evals_month = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE MONTH(submitted_date) = MONTH(CURRENT_DATE()) AND YEAR(submitted_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];
$total_branches_res = $conn->query("SELECT COUNT(*) as c FROM branches");
$total_branches = $total_branches_res->fetch_assoc()['c'];

// Fetch branches with employee counts for the insights explorer
$branches_insights_res = $conn->query("
    SELECT b.branch_id, b.branch_name, b.location, 
    (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.branch_id AND e.is_active = 1 AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)) as emp_count
    FROM branches b
    ORDER BY b.branch_name ASC
");
$branches_insights = $branches_insights_res->fetch_all(MYSQLI_ASSOC);
$total_emp_calc = $total_employees > 0 ? $total_employees : 1;


// Gender Counts (Excluding Admins)
$male_count = $conn->query("SELECT COUNT(*) as c FROM employees WHERE gender = 'Male' AND is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];
$female_count = $conn->query("SELECT COUNT(*) as c FROM employees WHERE gender = 'Female' AND is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

// Fetch pending evaluations (5 most recent)
$pending_evals_list = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, u.full_name as submitted_by_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    WHERE ev.status = 'Pending Manager'
    ORDER BY ev.submitted_date DESC LIMIT 5");

// Fetch pending career movements (5 most recent)
$pending_cm_list = $conn->query("SELECT cm.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, u.full_name as logged_by_name
    FROM career_movements cm
    LEFT JOIN employees e ON cm.employee_id = e.employee_id
    LEFT JOIN users u ON cm.logged_by = u.user_id
    WHERE cm.approval_status = 'Pending'
    ORDER BY cm.created_at DESC LIMIT 5");

// 1. Performance Distribution Data
$perf_dist = $conn->query("SELECT performance_level, COUNT(*) as count FROM evaluations WHERE status = 'Approved' AND performance_level IS NOT NULL GROUP BY performance_level");
$perf_data = ['Outstanding' => 0, 'Exceeds Expectations' => 0, 'Meets Expectations' => 0, 'Needs Improvement' => 0];
while ($row = $perf_dist->fetch_assoc()) {
    if (isset($perf_data[$row['performance_level']])) {
        $perf_data[$row['performance_level']] = (int) $row['count'];
    }
}

// 2. Evaluation Status Data
$status_dist = $conn->query("SELECT status, COUNT(*) as count FROM evaluations GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = $status_dist->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_counts[] = (int) $row['count'];
}

// 3. Top Performers Data
$top_performers = $conn->query("
    SELECT ev.total_score, ev.performance_level, e.employee_id,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, d.department_name
    FROM evaluations ev
    JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE ev.status = 'Approved'
    ORDER BY ev.total_score DESC, ev.submitted_date DESC
    LIMIT 5
");

// 4. Monthly Trends (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_label = date('M Y', strtotime("-$i months"));
    $month_num = date('n', strtotime("-$i months"));
    $year_num = date('Y', strtotime("-$i months"));
    $avg_q = $conn->query("SELECT AVG(total_score) as avg_score FROM evaluations WHERE status = 'Approved' AND MONTH(approved_date) = $month_num AND YEAR(approved_date) = $year_num");
    $avg_val = round($avg_q->fetch_assoc()['avg_score'] ?? 0, 1);
    $monthly_data[] = ['label' => $month_label, 'value' => $avg_val];
}

// 5. Branch Comparison
$branch_comp_data = [];
$branch_q = $conn->query("SELECT b.branch_name, AVG(ev.total_score) as avg_score
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE ev.status = 'Approved' AND b.branch_name IS NOT NULL
    GROUP BY b.branch_id, b.branch_name
    ORDER BY avg_score DESC LIMIT 10");
while ($row = $branch_q->fetch_assoc()) {
    $branch_comp_data[] = ['label' => $row['branch_name'], 'value' => round($row['avg_score'], 1)];
}
?>


<style>
    /* Premium Approval Tabs */
    .approval-tabs .nav-link {
        border: none;
        padding: 12px 20px;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.9rem;
        position: relative;
        transition: all 0.3s;
    }
    .approval-tabs .nav-link.active {
        color: var(--primary-blue) !important;
        background: transparent !important;
    }
    .approval-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 20px;
        right: 20px;
        height: 3px;
        background: var(--primary-blue);
        border-radius: 10px;
    }

    /* Approval List Cards */
    .approval-list {
        padding: 15px;
    }
    .approval-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: #fff;
        border-radius: 12px;
        margin-bottom: 12px;
        border: 1px solid #f0f0f0;
        transition: all 0.2s ease;
    }
    .approval-item:hover {
        transform: translateX(5px);
        border-color: var(--primary-light);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .approval-item .emp-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }
    .approval-item .avatar-circle {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(41, 67, 6, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: var(--primary-blue);
        flex-shrink: 0;
    }
    .approval-item .details {
        min-width: 0;
    }
    .approval-item .details h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .approval-item .details span {
        color: var(--text-muted);
        font-size: 0.75rem;
        display: block;
    }
    .approval-item .score-meter {
        width: 140px;
        padding: 0 20px;
        flex-shrink: 0;
    }
    .approval-item .score-val {
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
        font-size: 0.85rem;
    }
    .approval-item .status-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-align: right;
        flex-shrink: 0;
        padding-right: 15px;
    }
    .approval-item .btn-review {
        border-radius: 20px;
        padding: 6px 18px;
        font-size: 0.75rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    .empty-state-card {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }
    .empty-state-card i {
        font-size: 2.5rem;
        opacity: 0.2;
        margin-bottom: 15px;
        display: block;
    }
    .premium-branch-bg {
        background: linear-gradient(135deg, rgba(9, 32, 63, 0.85), rgba(83, 120, 149, 0.85)), url("<?php echo BASE_URL; ?>/assets/img/logo/621580631_2109586223124918_6598389711140444032_n.jpg") no-repeat center center;
        background-size: cover;
        color: #ffffff !important;
        transition: all 0.5s ease;
    }
    .premium-branch-bg h4,
    .premium-branch-bg p,
    .premium-branch-bg .insight-label,
    .premium-branch-bg #brNameDisplay,
    .premium-branch-bg #brLocationDisplay {
        color: #ffffff !important;
        text-shadow: 0px 2px 5px rgba(0, 0, 0, 0.8) !important;
    }
    .premium-branch-bg .text-muted {
        color: rgba(255, 255, 255, 0.85) !important;
        text-shadow: 0px 1px 3px rgba(0, 0, 0, 0.8) !important;
    }
    .premium-branch-bg .text-primary {
        color: #00d2ff !important;
    }
    .premium-branch-bg .bg-primary {
        background-color: #00d2ff !important;
    }
    .premium-branch-bg .card-bg-icon {
        color: rgba(255, 255, 255, 0.1) !important;
    }
    .premium-branch-bg .btn-explore {
        background-color: #00d2ff !important;
        border-color: #00d2ff !important;
        color: #000 !important;
    }
    .premium-branch-bg .btn-explore:hover {
        background-color: #00c0eb !important;
    }

    /* Distribution Table Styles */
    .perf-filter-btn {
        border: none;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
        background: #f8f9fa;
        color: var(--text-muted);
    }
    .perf-filter-btn:hover {
        background: #eee;
    }
    .perf-filter-btn.active {
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transform: translateY(-1px);
    }
    .perf-filter-btn.active.outstanding { background: #28a745; color: #fff; }
    .perf-filter-btn.active.exceeds { background: #17a2b8; color: #fff; }
    .perf-filter-btn.active.meets { background: #ffc107; color: #000; }
    .perf-filter-btn.active.needs { background: #dc3545; color: #fff; }

    .chart-label-premium {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 15px;
        display: block;
    }
</style>

<!-- Statistics Cards (Premium Hero) -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Dashboard</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-tachometer-alt me-2" style="color:#BD9414;"></i>System Overview</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $total_employees; ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    <i class="fas fa-users stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $male_count; ?></div>
                        <div class="stat-label">Male Employees</div>
                    </div>
                    <i class="fas fa-mars stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $female_count; ?></div>
                        <div class="stat-label">Female Employees</div>
                    </div>
                    <i class="fas fa-venus stat-icon" style="color:#e83e8c;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="branches.php" class="text-decoration-none">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?php echo $total_branches; ?></div>
                            <div class="stat-label">Total Branches</div>
                        </div>
                        <i class="fas fa-building stat-icon text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $pending_evals; ?></div>
                        <div class="stat-label">Pending Evals</div>
                    </div>
                    <i class="fas fa-clock stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $pending_movements; ?></div>
                        <div class="stat-label">Pending Movements</div>
                    </div>
                    <i class="fas fa-exchange-alt stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $new_evals_month; ?></div>
                        <div class="stat-label">Evals This Month</div>
                    </div>
                    <i class="fas fa-file-alt stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $avg_score; ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <i class="fas fa-star stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Branch Distribution Insights (Professional Section) -->
<div class="row g-4 mb-4 branch-insights-section">
    <div class="col-lg-4">
        <div class="chart-card allow-overflow h-100">
            <div class="cc-header">
                <h5><i class="fas fa-map-marker-alt me-2"></i>Select Branch</h5>
            </div>
            <div class="cc-body">
                <p class="text-muted small mb-3">Choose a location to view detailed workforce distribution.</p>

                <div id="branchSelector">
                    <div class="custom-select-wrapper">
                        <div class="select-trigger" id="customSelectTrigger">
                            <span class="selected-text text-muted">Pick a branch...</span>
                            <i class="fas fa-chevron-down ms-2"></i>
                        </div>
                        <div class="select-dropdown" id="customSelectDropdown">
                            <div class="search-container">
                                <input type="text" placeholder="Search branches..." id="branchSearchInput">
                            </div>
                            <div class="results-container" id="branchResultsList">
                                <?php foreach ($branches_insights as $br): ?>
                                    <div class="select-option" data-id="<?php echo $br['branch_id']; ?>"
                                        data-name="<?php echo e($br['branch_name']); ?>"
                                        data-location="<?php echo e($br['location']); ?>"
                                        data-count="<?php echo $br['emp_count']; ?>"
                                        data-percent="<?php echo round(($br['emp_count'] / $total_emp_calc) * 100, 1); ?>">
                                        <div class="branch-name"><?php echo e($br['branch_name']); ?></div>
                                        <div class="emp-badge"><?php echo $br['emp_count']; ?> Staff</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="insight-card" id="branchInsightCard">
            <i class="fas fa-building card-bg-icon"></i>
            <span class="insight-label">Workforce Insight</span>

            <div id="insightPlaceholder" class="text-center py-5">
                <i class="fas fa-mouse-pointer fa-3x mb-3 text-muted" style="opacity: 0.3;"></i>
                <p class="text-muted">Select a branch to see detailed statistics.</p>
            </div>

            <div id="insightContent" style="display: none;">
                <div class="branch-identity">
                    <h4 id="brNameDisplay">Branch Name</h4>
                    <p id="brLocationDisplay"><i class="fas fa-map-marker-alt me-1"></i> Location Address</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <small>ASSIGNED EMPLOYEES</small>
                        <div class="stat-val" id="brCountDisplay">0</div>
                    </div>
                    <div class="stat-box">
                        <small>WORKFORCE PERCENTAGE</small>
                        <div class="stat-val"><span id="brPercentDisplay">0</span>%</div>
                    </div>
                </div>

                <div class="distribution-bar">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-600 text-muted">Density Overview</span>
                        <span class="small fw-600 text-primary"><span id="brDensityText">0</span>% vs Total</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" id="brProgressBar" role="progressbar" style="width: 0%">
                        </div>
                    </div>
                </div>

                <a href="employees.php" class="btn btn-primary btn-explore" id="viewBranchEmployeesLink">
                    <i class="fas fa-users me-2"></i>View Branch Staff
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Performance Distribution -->
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Performance Distribution</h5>
            </div>
            <div class="cc-body">
                <div class="chart-container" style="height:300px;">
                    <canvas id="perfPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Top Performers</h5>
                <select id="topPerformerBranchFilter" class="form-select form-select-sm" style="width: 150px; font-size: 0.75rem; border-radius: 20px; border-color: #eee;">
                    <option value="">All Branches</option>
                    <?php foreach ($branches_insights as $br): ?>
                        <option value="<?php echo $br['branch_id']; ?>"><?php echo e($br['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cc-body p-0" style="height: 330px; overflow-y: auto;" id="topPerformerContainer">
                <?php if ($top_performers->num_rows === 0): ?>
                    <div class="empty-state-card py-5">
                        <i class="fas fa-medal text-muted" style="opacity: 0.1; font-size: 3rem;"></i>
                        <p class="mb-0 mt-3 small">No approved evaluations yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush pt-2">
                        <?php 
                        $rank = 1;
                        while ($tp = $top_performers->fetch_assoc()): 
                            $medal_color = ($rank == 1) ? '#ffd700' : (($rank == 2) ? '#c0c0c0' : (($rank == 3) ? '#cd7f32' : '#adb5bd'));
                            // Fallback rendering for avatar initials safely
                            $names = explode(' ', $tp['employee_name']);
                            $fn = isset($names[0]) ? substr($names[0], 0, 1) : '';
                            $ln = isset($names[1]) ? substr($names[1], 0, 1) : substr($names[0], 1, 1);
                            $initials = strtoupper($fn . $ln);
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom border-light" style="background: transparent;">
                                <div class="d-flex align-items-center gap-3 w-100">
                                    <div class="fw-bold" style="color: <?php echo $medal_color; ?>; width: 22px; text-align: center;">#<?php echo $rank; ?></div>
                                    <div style="width: 38px; height: 38px; border-radius: 50%; font-size: 0.85rem; background: rgba(13, 110, 253, 0.08); display:flex; align-items:center; justify-content:center; color: var(--primary-blue); font-weight: 800; flex-shrink: 0;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <h6 class="mb-0 fw-bold text-truncate" style="font-size: 0.9rem;">
                                            <a href="view-employee.php?id=<?php echo $tp['employee_id']; ?>" class="text-decoration-none text-dark hover-primary-text">
                                                <?php echo e($tp['employee_name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;"><?php echo e($tp['job_title']); ?> &bull; <?php echo e($tp['department_name'] ?? 'N/A'); ?></small>
                                    </div>
                                    <div class="text-end ps-2">
                                        <div class="badge bg-success rounded-pill px-2 py-1" style="font-size: 0.8rem; box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2);"><?php echo $tp['total_score']; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        <?php $rank++; endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Evaluation Status -->
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-tasks me-2"></i>Status Overview</h5>
            </div>
            <div class="cc-body">
                <div class="chart-container" style="height:300px;">
                    <canvas id="statusDonutChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COMPREHENSIVE GRAPHS ROW -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-chart-line me-2 text-primary"></i>Performance Trends</h5>
                <span class="badge bg-light text-dark fw-normal" style="font-size: 0.7rem;">Average Score (Last 6 Months)</span>
            </div>
            <div class="cc-body">
                <div style="height: 300px;">
                    <canvas id="trendLineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-chart-bar me-2 text-info"></i>Branch Comparison</h5>
            </div>
            <div class="cc-body">
                <span class="chart-label-premium">Top 10 Performing Branches (Avg %)</span>
                <div style="height: 270px;">
                    <canvas id="branchComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PERFORMANCE DISTRIBUTION TABLE ROW -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="cc-header d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-th-list me-2 text-success"></i>Performance Distribution Directory</h5>
                <div class="d-flex gap-2 filter-group">
                    <button class="perf-filter-btn outstanding active" onclick="filterDistribution('Outstanding', this)">Outstanding</button>
                    <button class="perf-filter-btn exceeds" onclick="filterDistribution('Exceeds Expectations', this)">Exceeds</button>
                    <button class="perf-filter-btn meets" onclick="filterDistribution('Meets Expectations', this)">Meets</button>
                    <button class="perf-filter-btn needs" onclick="filterDistribution('Needs Improvement', this)">Needs Imp.</button>
                </div>
            </div>
            <div class="cc-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th style="font-size: 0.75rem; font-weight: 700;">EMPLOYEE</th>
                                <th style="font-size: 0.75rem; font-weight: 700;">BRANCH</th>
                                <th style="font-size: 0.75rem; font-weight: 700;">SCORE</th>
                                <th style="font-size: 0.75rem; font-weight: 700;">DATE APPROVED</th>
                            </tr>
                        </thead>
                        <tbody id="distributionTableBody">
                            <!-- AJAX Content -->
                            <tr><td colspan="4" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pending Approvals -->
    <div class="col-12">
        <div class="chart-card">
            <div class="cc-header d-flex justify-content-between align-items-center py-2">
                <ul class="nav nav-tabs cc-header-tabs approval-tabs" id="pendingTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="eval-tab" data-bs-toggle="tab" data-bs-target="#evals"
                            type="button" role="tab">
                            Evaluations
                            <?php if ($pending_evals > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $pending_evals; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="cm-tab" data-bs-toggle="tab" data-bs-target="#movements"
                            type="button" role="tab">
                            Career Movements
                            <?php if ($pending_movements > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $pending_movements; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php" class="btn btn-sm btn-link text-decoration-none small">
                    View Center <i class="fas fa-external-link-alt ms-1" style="font-size: 0.7rem;"></i>
                </a>
            </div>
            <div class="cc-body p-0">
                <div class="tab-content" id="pendingTabsContent">
                    <!-- Evaluations Tab -->
                    <div class="tab-pane fade show active" id="evals" role="tabpanel">
                        <div class="approval-list">
                            <?php if ($pending_evals_list->num_rows === 0): ?>
                                <div class="empty-state-card">
                                    <i class="fas fa-clipboard-check"></i>
                                    <p class="mb-0">All evaluations have been processed.</p>
                                </div>
                            <?php else: ?>
                                <?php while ($row = $pending_evals_list->fetch_assoc()): 
                                    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
                                ?>
                                    <div class="approval-item">
                                        <div class="emp-info">
                                            <div class="avatar-circle"><?php echo $initials; ?></div>
                                            <div class="details">
                                                <h6><?php echo e($row['employee_name']); ?></h6>
                                                <span>Submitted by <?php echo e($row['submitted_by_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="score-meter d-none d-md-block">
                                            <span class="score-val"><?php echo $row['total_score']; ?>% Score</span>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar <?php echo ($row['total_score'] >= 80) ? 'bg-success' : (($row['total_score'] >= 60) ? 'bg-primary' : 'bg-warning'); ?>" 
                                                     style="width: <?php echo $row['total_score']; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="status-meta d-none d-sm-block">
                                            <div class="fw-bold text-dark"><?php echo formatDate($row['submitted_date']); ?></div>
                                            <div class="x-small">Pending Manager</div>
                                        </div>
                                        <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php?review=<?php echo $row['evaluation_id']; ?>"
                                           class="btn btn-primary btn-review">Review</a>
                                    </div>
                                <?php endwhile; ?>
                                <div class="text-center pb-3">
                                    <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php" class="text-decoration-none small text-muted hover-primary">
                                        View all pending evaluations <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Career Movements Tab -->
                    <div class="tab-pane fade" id="movements" role="tabpanel">
                        <div class="approval-list">
                            <?php if ($pending_cm_list->num_rows === 0): ?>
                                <div class="empty-state-card">
                                    <i class="fas fa-exchange-alt"></i>
                                    <p class="mb-0">No pending career movements at this time.</p>
                                </div>
                            <?php else: ?>
                                <?php while ($row = $pending_cm_list->fetch_assoc()): 
                                    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
                                ?>
                                    <div class="approval-item">
                                        <div class="emp-info">
                                            <div class="avatar-circle" style="background: rgba(23, 162, 184, 0.1); color: var(--info);"><?php echo $initials; ?></div>
                                            <div class="details">
                                                <h6><?php echo e($row['employee_name']); ?></h6>
                                                <span>Type: <span class="badge bg-info text-dark x-small py-1"><?php echo e($row['movement_type']); ?></span></span>
                                            </div>
                                        </div>
                                        <div class="score-meter d-none d-md-block">
                                            <span class="score-val">Effective Date</span>
                                            <div class="small fw-600"><?php echo formatDate($row['effective_date']); ?></div>
                                        </div>
                                        <div class="status-meta d-none d-sm-block">
                                            <div class="fw-bold text-dark">Logged By</div>
                                            <div class="x-small"><?php echo e($row['logged_by_name']); ?></div>
                                        </div>
                                        <a href="<?php echo BASE_URL; ?>/manager/career-movement-approval.php"
                                           class="btn btn-primary btn-review">Review</a>
                                    </div>
                                <?php endwhile; ?>
                                <div class="text-center pb-3">
                                    <a href="<?php echo BASE_URL; ?>/manager/career-movement-approval.php" class="text-decoration-none small text-muted hover-primary">
                                        View all pending movements <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Professional Branch Selector Logic ---
        const trigger = document.getElementById('customSelectTrigger');
        const dropdown = document.getElementById('customSelectDropdown');
        const searchInput = document.getElementById('branchSearchInput');
        const options = document.querySelectorAll('.select-option');
        const selectedText = trigger.querySelector('.selected-text');

        const insightCard = document.getElementById('branchInsightCard');
        const insightPlaceholder = document.getElementById('insightPlaceholder');
        const insightContent = document.getElementById('insightContent');

        // Toggle Dropdown
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            trigger.classList.toggle('active');
            if (dropdown.classList.contains('show')) searchInput.focus();
        });

        // Search Filter
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            options.forEach(opt => {
                const name = opt.dataset.name.toLowerCase();
                opt.style.display = name.includes(term) ? 'flex' : 'none';
            });
        });

        // Selection Handler
        options.forEach(opt => {
            opt.addEventListener('click', function () {
                // Update UI
                options.forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedText.innerText = this.dataset.name;
                selectedText.classList.remove('text-muted');
                dropdown.classList.remove('show');
                trigger.classList.remove('active');

                // Animate Insight Card
                updateInsightCard(this.dataset);
            });
        });

        function updateInsightCard(data) {
            insightPlaceholder.style.display = 'none';
            insightContent.style.display = 'block';
            insightCard.classList.add('updated-pulse');
            setTimeout(() => insightCard.classList.remove('updated-pulse'), 500);

            // Exclusive background logic for Raquel Pawnshop Main Office
            if (data.name === 'Raquel Pawnshop Main Office') {
                insightCard.classList.add('premium-branch-bg');
            } else {
                insightCard.classList.remove('premium-branch-bg');
            }

            document.getElementById('brNameDisplay').innerText = data.name;
            document.getElementById('brLocationDisplay').innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${data.location}`;
            document.getElementById('brCountDisplay').innerText = data.count;
            document.getElementById('brPercentDisplay').innerText = data.percent;
            document.getElementById('brDensityText').innerText = data.percent;
            document.getElementById('brProgressBar').style.width = data.percent + '%';
            document.getElementById('viewBranchEmployeesLink').href = `employees.php?branch=${encodeURIComponent(data.name)}`;
        }

        // --- Top Performer Branch Filter ---
        const topPerformerFilter = document.getElementById('topPerformerBranchFilter');
        const topPerformerContainer = document.getElementById('topPerformerContainer');

        if (topPerformerFilter) {
            topPerformerFilter.addEventListener('change', function() {
                const branchId = this.value;
                
                // Visual feedback
                topPerformerContainer.style.transition = 'opacity 0.2s ease';
                topPerformerContainer.style.opacity = '0.4';
                
                fetch(`ajax/get-top-performers.php?branch_id=${branchId}`)
                    .then(response => response.text())
                    .then(html => {
                        topPerformerContainer.innerHTML = html;
                        topPerformerContainer.style.opacity = '1';
                    })
                    .catch(err => {
                        console.error('Error fetching top performers:', err);
                        topPerformerContainer.style.opacity = '1';
                    });
            });
        }

        // Close dropdown on click outside
        document.addEventListener('click', () => {
            dropdown.classList.remove('show');
            trigger.classList.remove('active');
        });

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
            }
        };

        // 1. Performance Distribution (Pie)
        new Chart(document.getElementById('perfPieChart'), {
            type: 'pie',
            data: {
                labels: ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement'],
                datasets: [{
                    data: [<?php echo $perf_data['Outstanding']; ?>, <?php echo $perf_data['Exceeds Expectations']; ?>, <?php echo $perf_data['Meets Expectations']; ?>, <?php echo $perf_data['Needs Improvement']; ?>],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: commonOptions
        });

        // 2. Evaluation Status (Doughnut)
        new Chart(document.getElementById('statusDonutChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: ['#6c757d', '#ffc107', '#17a2b8', '#28a745', '#dc3545', '#007bff'],
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 3. Performance Trends (Line Chart)
        new Chart(document.getElementById('trendLineChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'label')); ?>,
                datasets: [{
                    label: 'Average Performance Score',
                    data: <?php echo json_encode(array_column($monthly_data, 'value')); ?>,
                    borderColor: '#294306',
                    backgroundColor: 'rgba(41, 67, 6, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#294306',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: false, 
                        min: 40, max: 100, 
                        grid: { display: true, drawBorder: false, color: '#f0f0f0' },
                        ticks: { callback: value => value + '%' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // 4. Branch Comparison (Horizontal Bar Chart)
        new Chart(document.getElementById('branchComparisonChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($branch_comp_data, 'label')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($branch_comp_data, 'value')); ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.7)',
                    borderColor: '#17a2b8',
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 12
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { max: 100, grid: { display: true, color: '#f8f9fa' } },
                    y: { grid: { display: false } }
                }
            }
        });

        // Load initial distribution table
        filterDistribution('Outstanding', document.querySelector('.perf-filter-btn.outstanding'));
    });

    function filterDistribution(level, btn) {
        // Update Buttons
        document.querySelectorAll('.perf-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const tbody = document.getElementById('distributionTableBody');
        tbody.style.opacity = '0.4';

        fetch(`ajax/get-distribution-list.php?level=${encodeURIComponent(level)}`)
            .then(response => response.text())
            .then(html => {
                tbody.innerHTML = html;
                tbody.style.opacity = '1';
            })
            .catch(err => {
                console.error(err);
                tbody.style.opacity = '1';
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load data.</td></tr>';
            });
    }
</script>

<?php require_once '../includes/footer.php'; ?>