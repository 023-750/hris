<?php
$page_title = 'Pending Endorsements';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

// Handle endorsement/return (MUST be before header.php to allow redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $eval_id = (int)$_POST['evaluation_id'];
    $action = $_POST['action'];
    $comments = trim($_POST['supervisor_comments'] ?? '');

    if ($action === 'endorse') {
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Pending Manager', endorsed_by = ?, endorsed_date = NOW(), supervisor_comments = ? WHERE evaluation_id = ?");
        $stmt->bind_param("isi", $_SESSION['user_id'], $comments, $eval_id);
        $stmt->execute();
        $stmt->close();

        // Notify all HR Managers
        $managers = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
        $eval_info = $conn->query("SELECT CONCAT(e.first_name, ' ', e.last_name) as emp_name FROM evaluations ev LEFT JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.evaluation_id = $eval_id")->fetch_assoc();
        while ($mgr = $managers->fetch_assoc()) {
            createNotification($conn, $mgr['user_id'], 'Evaluation Endorsed', "Evaluation for {$eval_info['emp_name']} has been endorsed and requires your approval.", BASE_URL . '/manager/pending-approvals.php');
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Endorsed evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/supervisor/pending-endorsements.php', 'success', 'Evaluation endorsed and forwarded to Manager.');

    } elseif ($action === 'return') {
        if (empty($comments)) {
            redirectWith(BASE_URL . '/supervisor/pending-endorsements.php', 'danger', 'Comments are required when returning an evaluation.');
        }
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Returned', supervisor_comments = ? WHERE evaluation_id = ?");
        $stmt->bind_param("si", $comments, $eval_id);
        $stmt->execute();
        $stmt->close();

        $eval_info = $conn->query("SELECT ev.submitted_by, CONCAT(e.first_name, ' ', e.last_name) as emp_name FROM evaluations ev LEFT JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.evaluation_id = $eval_id")->fetch_assoc();
        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Evaluation Returned', "Your evaluation for {$eval_info['emp_name']} has been returned for revision.", BASE_URL . '/staff/my-submissions.php');
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Returned evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/supervisor/pending-endorsements.php', 'warning', 'Evaluation returned for revision.');
    }
}

require_once '../includes/header.php';

// Fetch pending evaluations
$pending = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title,
    u.full_name as submitted_by_name, et.template_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.status = 'Pending Supervisor'
    ORDER BY ev.submitted_date DESC");

// Fetch history count for summary
$history_count = $conn->query("SELECT COUNT(*) as cnt FROM evaluations WHERE endorsed_by = {$_SESSION['user_id']} AND status IN ('Pending Manager', 'Approved', 'Rejected', 'Returned')")->fetch_assoc()['cnt'];
$pending_count = $pending->num_rows;

// Prepare results in array
$all_pending = [];
while ($row = $pending->fetch_assoc()) {
    $all_pending[] = $row;
}
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-primary shadow-sm">
            <div class="display-6 fw-bold text-primary"><?php echo $pending_count; ?></div>
            <div class="text-muted small fw-bold text-uppercase">Pending Your Endorsement</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-success shadow-sm">
            <div class="display-6 fw-bold text-success"><?php echo $history_count; ?></div>
            <div class="text-muted small fw-bold text-uppercase">Total Processed</div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="evaluation-history.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 bg-white" style="border: 2px dashed #dee2e6;">
            <i class="fas fa-history mb-2 fa-2x"></i>
            <span class="fw-bold text-uppercase small">View Evaluation History</span>
        </a>
    </div>
</div>

<div class="content-card border-0 shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>Evaluations Pending Endorsement</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="customSearchPending" placeholder="Search employee or position...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="pendingTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Employee</th>
                        <th>Position</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Level</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_pending)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 d-block opacity-25"></i>No pending endorsements.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_pending as $row): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo e($row['employee_name']); ?></div>
                                    <div class="x-small text-muted"><?php echo e($row['template_name']); ?></div>
                                </td>
                                <td><?php echo e($row['job_title']); ?></td>
                                <td><div class="small"><?php echo e($row['submitted_by_name']); ?></div></td>
                                <td><small class="text-muted"><?php echo formatDate($row['submitted_date']); ?></small></td>
                                <td>
                                    <div class="fw-bold"><?php echo $row['total_score']; ?>%</div>
                                    <div class="progress mt-1" style="height: 4px; width: 60px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo min(100, (float)$row['total_score'] / 4 * 100); ?>%;"></div>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo getPerformanceBadgeClass($row['performance_level']); ?> rounded-pill px-2" style="font-size:0.7rem;"><?php echo e($row['performance_level']); ?></span></td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                                        <i class="fas fa-clipboard-check me-1"></i>Review
                                    </button>
                                </td>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
                        <h5 class="modal-title mb-1">Review Evaluation</h5>
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
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie', 'c' => true],
                            ['l' => 'Review', 'a' => false, 'i' => 'fa-user-shield'],
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
                            <div class="emp-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded" style="width: 55px; height: 55px; font-size: 1.2rem;"><?php echo $initials; ?></div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo e($row['employee_name']); ?></h4>
                                <div class="text-muted"><?php echo e($row['job_title'] ?? 'Staff'); ?> &bull; <?php echo e($row['template_name']); ?></div>
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

                    <!-- Action Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> IV. Remarks & Decisions
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="evaluation_id" value="<?php echo $row['evaluation_id']; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Supervisor Comments / Feedback</label>
                            <textarea class="form-control bg-light" name="supervisor_comments" rows="3" placeholder="Required for returns, optional for endorsements..." required></textarea>
                            <div class="form-text x-small text-danger">* Comments are required when returning an evaluation for revision.</div>
                        </div>
                        <div class="fixed-action-bar d-flex gap-2 justify-content-end">
                            <button type="submit" name="action" value="return" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-undo me-2"></i>Return for Revision
                            </button>
                            <button type="submit" name="action" value="endorse" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-check-double me-2"></i>Validate & Forward
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.getElementById('customSearchPending')?.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#pendingTable tbody tr:not(.no-results-row)');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
