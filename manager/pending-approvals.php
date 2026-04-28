<?php
$page_title = 'Pending Approvals';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Handle approval/rejection (MUST be before header.php to allow redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $eval_id = (int) $_POST['evaluation_id'];
    $action = $_POST['action'];
    $comments = trim($_POST['manager_comments'] ?? '');

    if ($action === 'approve') {
        // 1. Update status to Approved
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Approved', approved_by = ?, manager_comments = ? WHERE evaluation_id = ?");
        $stmt->bind_param("isi", $_SESSION['user_id'], $comments, $eval_id);
        $stmt->execute();
        $stmt->close();

        // 2. Fetch evaluation details for career movement and notifications
        $eval_info_q = $conn->prepare("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as emp_name, e.branch_id as emp_branch_id 
                                     FROM evaluations ev 
                                     LEFT JOIN employees e ON ev.employee_id = e.employee_id 
                                     WHERE ev.evaluation_id = ?");
        $eval_info_q->bind_param("i", $eval_id);
        $eval_info_q->execute();
        $eval_info = $eval_info_q->get_result()->fetch_assoc();
        $eval_info_q->close();

        // 3. Automatically create a Career Movement record if employee is marked as suited for another job
        if (!empty($eval_info['career_growth_suited']) && !empty($eval_info['desired_position'])) {
            $move_stmt = $conn->prepare("INSERT INTO career_movements 
                (employee_id, movement_type, previous_position, new_position, previous_branch_id, new_branch_id, effective_date, reason, approval_status, approved_by, logged_by, is_applied) 
                VALUES (?, 'Role Change', ?, ?, ?, ?, ?, ?, 'Approved', ?, ?, 0)");
            
            $reason = "Automatically generated from approved Performance Evaluation (ID: " . $eval_id . ")";
            $move_stmt->bind_param("issiissii", 
                $eval_info['employee_id'],
                $eval_info['current_position'],
                $eval_info['desired_position'],
                $eval_info['emp_branch_id'],
                $eval_info['emp_branch_id'], // Assuming same branch for role change goals
                $eval_info['target_date'],
                $reason,
                $_SESSION['user_id'],
                $eval_info['submitted_by']
            );
            $move_stmt->execute();
            $move_stmt->close();
        }

        // 4. Send Notifications
        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Evaluation Approved', "Your evaluation for {$eval_info['emp_name']} has been approved.", BASE_URL . '/staff/my-submissions.php');
        }
        if ($eval_info['endorsed_by']) {
            createNotification($conn, $eval_info['endorsed_by'], 'Evaluation Approved', "Evaluation for {$eval_info['emp_name']} has been approved.", BASE_URL . '/supervisor/pending-endorsements.php');
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Approved evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'success', 'Evaluation approved successfully.');

    } elseif ($action === 'reject') {
        if (empty($comments)) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Comments are required when rejecting an evaluation.');
        }
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Rejected', approved_by = ?, manager_comments = ? WHERE evaluation_id = ?");
        $stmt->bind_param("isi", $_SESSION['user_id'], $comments, $eval_id);
        $stmt->execute();
        $stmt->close();

        $eval_info = $conn->query("SELECT ev.submitted_by, CONCAT(e.first_name, ' ', e.last_name) as emp_name FROM evaluations ev LEFT JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.evaluation_id = $eval_id")->fetch_assoc();
        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Evaluation Rejected', "Your evaluation for {$eval_info['emp_name']} has been rejected.", BASE_URL . '/staff/my-submissions.php');
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Rejected evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'warning', 'Evaluation rejected.');

    } elseif ($action === 'revision') {
        if (empty($comments)) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Comments are required when requesting revision.');
        }
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Returned', manager_comments = ? WHERE evaluation_id = ?");
        $stmt->bind_param("si", $comments, $eval_id);
        $stmt->execute();
        $stmt->close();

        $eval_info = $conn->query("SELECT ev.submitted_by, CONCAT(e.first_name, ' ', e.last_name) as emp_name FROM evaluations ev LEFT JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.evaluation_id = $eval_id")->fetch_assoc();
        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Revision Requested', "Your evaluation for {$eval_info['emp_name']} needs revision.", BASE_URL . '/staff/my-submissions.php');
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Requested revision for evaluation of {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'info', 'Revision requested.');
    }
}

require_once '../includes/header.php';

// Fetch counts for summary
$finalized_count_q = $conn->prepare("SELECT COUNT(*) as cnt FROM evaluations WHERE approved_by = ? AND status IN ('Approved', 'Rejected')");
$finalized_count_q->bind_param("i", $_SESSION['user_id']);
$finalized_count_q->execute();
$finalized_count = $finalized_count_q->get_result()->fetch_assoc()['cnt'];
$finalized_count_q->close();

// Fetch pending evaluations
$pending = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title,
    u.full_name as submitted_by_name, et.template_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.status = 'Pending Manager'
    ORDER BY ev.submitted_date DESC");

$pending_count = $pending->num_rows;

// Prepare results in array
$all_pending = [];
while ($row = $pending->fetch_assoc()) {
    $all_pending[] = $row;
}

// Fetch pending career movements
$pending_cm = $conn->query("SELECT cm.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, u.full_name as logged_by_name
    FROM career_movements cm
    LEFT JOIN employees e ON cm.employee_id = e.employee_id
    LEFT JOIN users u ON cm.logged_by = u.user_id
    WHERE cm.approval_status = 'Pending'
    ORDER BY cm.created_at DESC");

$cm_count = $pending_cm->num_rows;
$all_cm = [];
while ($row = $pending_cm->fetch_assoc()) {
    $all_cm[] = $row;
}

$total_pending_all = $pending_count + $cm_count;
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="chart-card fadeup text-center p-3 h-100 border-start border-4 border-primary shadow-sm glass-card">
            <div class="display-6 fw-bold text-primary"><?php echo $total_pending_all; ?></div>
            <div class="text-muted small fw-bold text-uppercase">Total Pending Actions</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card fadeup text-center p-3 h-100 border-start border-4 border-success shadow-sm">
            <div class="display-6 fw-bold text-success"><?php echo $finalized_count; ?></div>
            <div class="text-muted small fw-bold text-uppercase">Total Finalized</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card fadeup p-3 h-100 bg-light border-0 shadow-sm d-flex flex-column justify-content-center">
            <div class="small text-muted mb-1 italic"><i class="fas fa-info-circle me-1"></i>Quick Tip</div>
            <div class="x-small text-muted">Approved evaluations with growth aspirations automatically create career movement records for tracking.</div>
        </div>
    </div>
</div>


<style>
    .approval-center-tabs {
        background: #fff;
        border-radius: 12px 12px 0 0;
        border: 1px solid #f0f0f0;
        border-bottom: none;
        padding: 5px 15px 0;
    }
    .approval-center-tabs .nav-link {
        border: none;
        padding: 15px 25px;
        font-weight: 600;
        color: var(--text-muted);
        position: relative;
        transition: all 0.3s;
    }
    .approval-center-tabs .nav-link.active {
        color: var(--primary-blue) !important;
        background: transparent !important;
    }
    .approval-center-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 20px;
        right: 20px;
        height: 4px;
        background: var(--primary-blue);
        border-radius: 10px;
    }
    .approval-card-list {
        background: #fff;
        border-radius: 0 0 12px 12px;
        border: 1px solid #f0f0f0;
        min-height: 400px;
    }
    .modern-table thead th {
        background: rgba(41, 67, 6, 0.03);
        color: var(--primary-blue);
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: none;
        padding: 15px 20px;
    }
    .modern-table tbody td {
        padding: 18px 20px;
        border-bottom: 1px solid #f8f9fa;
    }
    .emp-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--primary-blue);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
    }

</style>

<div class="row mb-5">
    <div class="col-12">
        <div class="approval-center-tabs d-flex justify-content-between align-items-center">
            <ul class="nav nav-tabs border-0" id="approvalTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="evaluations-tab" data-bs-toggle="tab" data-bs-target="#evaluations-pane" type="button" role="tab">
                        <i class="fas fa-file-signature me-2"></i>Evaluations
                        <span class="badge rounded-pill bg-primary ms-1"><?php echo $pending_count; ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements-pane" type="button" role="tab">
                        <i class="fas fa-route me-2"></i>Career Movements
                        <span class="badge rounded-pill bg-info text-dark ms-1"><?php echo $cm_count; ?></span>
                    </button>
                </li>
            </ul>
            <div class="search-box me-3">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control form-control-sm border-0 bg-light" id="unifiedSearch" placeholder="Search approvals...">
            </div>
        </div>

        <div class="tab-content approval-card-list shadow-sm" id="approvalTabsContent">
            <!-- Evaluations Tab -->
            <div class="tab-pane fade show active" id="evaluations-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table modern-table align-middle mb-0" id="evalTable">
                        <thead>
                            <tr>
                                <th>Employee & Template</th>
                                <th>Submitted By</th>
                                <th>Date</th>
                                <th>Performance Score</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_pending)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-light mb-3"></i>
                                        <p class="text-muted">No pending evaluations for review.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_pending as $row): 
                                    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="emp-avatar"><?php echo $initials; ?></div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo e($row['employee_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($row['template_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="small text-muted"><?php echo e($row['submitted_by_name']); ?></span></td>
                                        <td><span class="small text-muted"><?php echo formatDate($row['submitted_date']); ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="fw-bold" style="min-width: 40px;"><?php echo $row['total_score']; ?>%</div>
                                                <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                                    <div class="progress-bar <?php echo ($row['total_score'] >= 80) ? 'bg-success' : (($row['total_score'] >= 60) ? 'bg-primary' : 'bg-warning'); ?>" 
                                                         style="width: <?php echo $row['total_score']; ?>%"></div>
                                                </div>
                                                <span class="badge glass-badge rounded-pill"><?php echo e($row['performance_level']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                                                Review Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Career Movements Tab -->
            <div class="tab-pane fade" id="movements-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table modern-table align-middle mb-0" id="moveTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Movement Type</th>
                                <th>Logged By</th>
                                <th>Effective Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_cm)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-route fa-3x text-light mb-3"></i>
                                        <p class="text-muted">No pending career movements for approval.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_cm as $row): 
                                    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="emp-avatar bg-info text-dark"><?php echo $initials; ?></div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo e($row['employee_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($row['job_title'] ?? 'Staff'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info text-dark rounded-pill px-3"><?php echo e($row['movement_type']); ?></span></td>
                                        <td><span class="small text-muted"><?php echo e($row['logged_by_name']); ?></span></td>
                                        <td><div class="fw-bold small"><?php echo formatDate($row['effective_date']); ?></div></td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/manager/career-movement-approval.php" class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm">
                                                Review Movement
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Render Modals at the end of the file
foreach ($all_pending as $row): 
    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
?>
    <div class="modal fade modal-premium" id="reviewModal<?php echo $row['evaluation_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Performance Review</h5>
                        <p class="mb-0 opacity-75 small">Reviewing evaluation for <?php echo e($row['employee_name']); ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <!-- Status Stepper -->
                    <div class="status-stepper d-flex justify-content-between mb-4 py-3 border-bottom overflow-hidden">
                        <?php
                        $steps = [
                            ['l' => 'Drafted', 'a' => true, 'i' => 'fa-pencil-alt'],
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie'],
                            ['l' => 'Review', 'a' => true, 'i' => 'fa-user-shield', 'c' => true],
                            ['l' => 'Final', 'a' => false, 'i' => 'fa-check-double']
                        ];
                        foreach ($steps as $st): ?>
                            <div class="step-item text-center <?php echo $st['a'] ? 'text-primary' : 'text-muted'; ?>" style="flex: 1;">
                                <div class="mb-1">
                                    <i class="fas <?php echo $st['i']; ?> <?php echo isset($st['c']) ? 'fa-pulse' : ''; ?>"></i>
                                </div>
                                <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;"><?php echo $st['l']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="emp-avatar" style="width: 55px; height: 55px; font-size: 1.2rem;"><?php echo $initials; ?></div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo e($row['employee_name']); ?></h4>
                                <div class="text-muted"><?php echo e($row['job_title']); ?> &bull; <?php echo e($row['template_name']); ?></div>
                            </div>
                        </div>
                        <div class="score-circle">
                            <div class="val"><?php echo $row['total_score']; ?>%</div>
                            <div class="lbl">Score</div>
                        </div>
                    </div>

                    <!-- KRA Section -->
                    <div class="section-premium-label mb-3 mt-4">
                        <i class="fas fa-bullseye"></i> I. Strategic Programs & Requirements
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Criterion</th>
                                    <th class="text-center" style="width: 80px;">Weight</th>
                                    <th class="text-center" style="width: 80px;">Rating</th>
                                    <th class="text-center" style="width: 80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.description, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'KRA' ORDER BY ec.sort_order");
                                $kra_num = 1;
                                while ($k = $kra_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold">KRA <?php echo $kra_num++; ?>: <?php echo e($k['criterion_name']); ?></div>
                                            <?php if($k['description']): ?><div class="text-muted x-small"><?php echo e($k['description']); ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $k['weight']; ?>%</td>
                                        <td class="text-center fw-bold"><?php echo $k['score_value']; ?></td>
                                        <td class="text-center text-primary fw-bold"><?php echo $k['weighted_score']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="bg-light fw-bold border-top">
                                    <td class="ps-3">KRA Sub-total</td>
                                    <td class="text-center">100%</td>
                                    <td></td>
                                    <td class="text-center text-primary"><?php echo $row['kra_subtotal']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Behavior Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-heart"></i> II. Behavior & Values
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Behavior KPI</th>
                                    <th class="text-center" style="width: 100px;">Rating (1-4)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'Behavior' ORDER BY ec.sort_order");
                                while ($b = $beh_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?php echo e($b['criterion_name']); ?></div>
                                            <div class="text-muted x-small"><?php echo e($b['kpi_description']); ?></div>
                                        </td>
                                        <td class="text-center text-primary fw-bold"><?php echo $b['score_value']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="bg-light fw-bold border-top">
                                    <td class="ps-3">Behavior Average</td>
                                    <td class="text-center text-primary"><?php echo $row['behavior_average']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Career Growth -->
                    <?php $cg_suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-chart-line"></i> III. Career Growth
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <div class="mb-2 fw-semibold" style="font-size:0.9rem;">
                            Is the employee better suited for another job within the company?
                            <span class="badge ms-2 <?php echo $cg_suited ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $cg_suited ? '&#9745; Yes' : '&#9744; No'; ?>
                            </span>
                        </div>
                        <?php if ($cg_suited && !empty($row['desired_position'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-briefcase me-1 text-info"></i>
                            <strong>Job Function / Department:</strong>
                            <span class="text-dark fw-semibold ms-1"><?php echo e($row['desired_position']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Remarks -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> IV. Remarks & Decisions
                    </div>
                    <?php if($row['supervisor_comments']): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1">Supervisor Remarks</label>
                            <div class="p-3 bg-light rounded-3 border italic small"><?php echo nl2br(e($row['supervisor_comments'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="evaluation_id" value="<?php echo $row['evaluation_id']; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Manager's Final Comments</label>
                            <textarea class="form-control bg-light" name="manager_comments" rows="3" placeholder="Enter findings, recommendations, or reasons for rejection..."></textarea>
                        </div>
                        <div class="fixed-action-bar d-flex gap-2 justify-content-end">
                            <button type="submit" name="action" value="revision" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-undo me-2"></i>Provision
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger rounded-pill px-4 fw-bold">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-check-double me-2"></i>Approve Evaluation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality for unified center
    document.getElementById('unifiedSearch')?.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const activePane = document.querySelector('.tab-pane.active');
        const rows = activePane.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    // Handle deep linking for review
    const urlParams = new URLSearchParams(window.location.search);
    const reviewId = urlParams.get('review');
    if (reviewId) {
        const modal = new bootstrap.Modal(document.getElementById('reviewModal' + reviewId));
        modal.show();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>