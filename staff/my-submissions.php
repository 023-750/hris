<?php
$page_title = 'My Submissions';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/header.php';

$uid = $_SESSION['user_id'];

// Fetch submissions (excluding drafts)
$submissions = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, et.template_name,
    u2.full_name as endorsed_by_name, u3.full_name as approved_by_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    LEFT JOIN users u2 ON ev.endorsed_by = u2.user_id
    LEFT JOIN users u3 ON ev.approved_by = u3.user_id
    WHERE ev.submitted_by = $uid AND ev.status != 'Draft'
    ORDER BY ev.submitted_date DESC");

// Prepare counters
$total_c = 0;
$pending_c = 0;
$approved_c = 0;
$returned_c = 0;

$all_subs = [];
while ($row = $submissions->fetch_assoc()) {
    $all_subs[] = $row;
    $total_c++;
    if ($row['status'] === 'Approved') $approved_c++;
    elseif ($row['status'] === 'Returned') $returned_c++;
    else $pending_c++;
}
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-primary">
            <div class="display-6 fw-bold text-primary"><?php echo $total_c; ?></div>
            <div class="text-muted small fw-bold">Total Submissions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-warning">
            <div class="display-6 fw-bold text-warning"><?php echo $pending_c; ?></div>
            <div class="text-muted small fw-bold">Pending Review</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-success">
            <div class="display-6 fw-bold text-success"><?php echo $approved_c; ?></div>
            <div class="text-muted small fw-bold">Approved</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="content-card text-center p-3 h-100 border-start border-4 border-danger">
            <div class="display-6 fw-bold text-danger"><?php echo $returned_c; ?></div>
            <div class="text-muted small fw-bold">Returned</div>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center">
            <h5 class="mb-0 me-3"><i class="fas fa-paper-plane me-2 text-primary"></i>My Submissions</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="filterByStatus('All', this)">All</button>
                <button type="button" class="btn btn-outline-warning" onclick="filterByStatus('Pending', this)">Pending</button>
                <button type="button" class="btn btn-outline-success" onclick="filterByStatus('Approved', this)">Approved</button>
                <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('Returned', this)">Returned</button>
            </div>
        </div>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchSubs" placeholder="Search employee..." onkeyup="filterTable('searchSubs', 'subsTable')">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="subsTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Employee & Position</th>
                        <th>Template</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Progress</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_subs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                            No submissions found.
                        </td></tr>
                    <?php else: 
                        foreach ($all_subs as $sub): 
                            $status = $sub['status'];
                            $progWidth = '25%';
                            $progClass = 'bg-warning';
                            if ($status === 'Approved') { $progWidth = '100%'; $progClass = 'bg-success'; }
                            elseif ($status === 'Pending Manager') { $progWidth = '75%'; }
                            elseif ($status === 'Pending Supervisor') { $progWidth = '50%'; }
                            elseif ($status === 'Returned') { $progWidth = '100%'; $progClass = 'bg-danger'; }
                    ?>
                            <tr data-status="<?php echo ($status === 'Approved' || $status === 'Returned') ? $status : 'Pending'; ?>">
                                <td class="ps-3"><strong><?php echo e($sub['employee_name']); ?></strong><br><small class="text-muted"><?php echo e($sub['current_position'] ?? 'N/A'); ?></small></td>
                                <td><small><?php echo e($sub['template_name']); ?></small></td>
                                <td><small><?php echo formatDate($sub['submitted_date']); ?></small></td>
                                <td><span class="badge <?php echo getStatusBadgeClass($sub['status']); ?>"><?php echo e($sub['status']); ?></span></td>
                                <td>
                                    <strong><?php echo $sub['total_score']; ?>%</strong>
                                    <div class="text-muted" style="font-size:0.65rem;"><?php echo e($sub['performance_level']); ?></div>
                                </td>
                                <td>
                                    <div class="progress" style="height: 6px; width: 80px;" title="<?php echo $progWidth; ?> complete">
                                        <div class="progress-bar <?php echo $progClass; ?>" role="progressbar" style="width: <?php echo $progWidth; ?>;"></div>
                                    </div>
                                    <small class="text-muted" style="font-size:0.65rem;"><?php echo $progWidth; ?></small>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-action btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $sub['evaluation_id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                    <?php if ($status === 'Returned'): ?>
                                        <a href="<?php echo BASE_URL; ?>/staff/submit-evaluation.php?edit=<?php echo $sub['evaluation_id']; ?>" class="btn btn-sm btn-action btn-warning">
                                            <i class="fas fa-edit me-1"></i>Fix
                                        </a>
                                    <?php endif; ?>
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
// Separate loop for modals to keep table structure valid
foreach ($all_subs as $sub): 
    $status = $sub['status'];
    $initials = strtoupper(substr($sub['employee_name'], 0, 1) . substr(explode(' ', $sub['employee_name'])[1] ?? '', 0, 1));
?>
    <!-- Detail Modal for <?php echo $sub['evaluation_id']; ?> -->
    <div class="modal fade modal-premium" id="detailModal<?php echo $sub['evaluation_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Evaluation Details</h5>
                        <p class="mb-0 opacity-75 small"><?php echo e($sub['employee_name']); ?> - <?php echo e($sub['template_name']); ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <!-- Status Stepper -->
                    <div class="status-stepper d-flex justify-content-between mb-4 py-3 border-bottom overflow-hidden">
                        <?php
                        $steps = [
                            ['l' => 'Drafted', 'a' => true, 'i' => 'fa-pencil-alt', 'c' => false],
                            ['l' => 'Supervisor', 'a' => ($status !== 'Pending Supervisor'), 'i' => 'fa-user-tie', 'c' => ($status === 'Pending Supervisor')],
                            ['l' => 'Review', 'a' => ($status === 'Approved' || $status === 'Returned'), 'i' => 'fa-user-shield', 'c' => ($status === 'Pending Manager')],
                            ['l' => 'Final', 'a' => ($status === 'Approved'), 'i' => 'fa-check-double', 'c' => ($status === 'Approved')]
                        ];
                        if ($status === 'Returned') {
                            $steps[3] = ['l' => 'Returned', 'a' => true, 'i' => 'fa-undo', 'c' => true, 'cls' => 'text-danger'];
                        }
                        
                        foreach ($steps as $st): ?>
                            <div class="step-item text-center <?php echo $st['a'] ? ($st['cls'] ?? 'text-primary') : 'text-muted'; ?>" style="flex: 1;">
                                <div class="mb-1">
                                    <i class="fas <?php echo $st['i']; ?> <?php echo $st['c'] ? 'fa-pulse' : ''; ?>"></i>
                                </div>
                                <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;"><?php echo $st['l']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert <?php echo $status === 'Approved' ? 'alert-success' : ($status === 'Returned' ? 'alert-danger' : 'alert-info'); ?> py-2 small d-flex align-items-center mb-4">
                        <i class="fas <?php echo $status === 'Approved' ? 'fa-check-circle' : ($status === 'Returned' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?> me-2"></i>
                        <span>Current Status: <strong><?php echo $status; ?></strong></span>
                    </div>

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="emp-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded" style="width: 55px; height: 55px; font-size: 1.2rem;"><?php echo $initials; ?></div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo e($sub['employee_name']); ?></h4>
                                <div class="text-muted"><?php echo e($sub['template_name']); ?></div>
                            </div>
                        </div>
                        <div class="score-circle">
                            <div class="val"><?php echo $sub['total_score']; ?>%</div>
                            <div class="lbl">Score</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end mb-4 gap-2 d-print-none text-end">
                        <a href="../manager/print-evaluation.php?id=<?php echo $sub['evaluation_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                            <i class="fas fa-print me-1"></i>Print Form
                        </a>
                    </div>

                    <!-- KRA Section -->
                    <div class="section-premium-label mb-3 mt-4">
                        <i class="fas fa-bullseye"></i> I. Strategic Programs & Job Requirements
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
                                $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.description, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$sub['evaluation_id']} AND ec.section = 'KRA' ORDER BY ec.sort_order");
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
                                    <td class="text-center text-primary"><?php echo $sub['kra_subtotal']; ?></td>
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
                                $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$sub['evaluation_id']} AND ec.section = 'Behavior' ORDER BY ec.sort_order");
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
                                    <td class="text-center text-primary"><?php echo $sub['behavior_average']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Career Growth -->
                    <?php $cg_suited = !empty($sub['career_growth_suited']) ? 1 : (!empty($sub['desired_position']) ? 1 : 0); ?>
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
                        <?php if ($cg_suited && !empty($sub['desired_position'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-briefcase me-1 text-info"></i>
                            <strong>Job Function / Department:</strong>
                            <span class="text-dark fw-semibold ms-1"><?php echo e($sub['desired_position']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Developmental Plan -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-seedling"></i> IV. Developmental Plan
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Area of Improvement</th>
                                    <th>Support Needed</th>
                                    <th>Time Frame</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = {$sub['evaluation_id']} ORDER BY sort_order");
                                if ($dev_q->num_rows > 0):
                                    while ($dp = $dev_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3"><?php echo e($dp['improvement_area']); ?></td>
                                        <td><?php echo e($dp['support_needed']); ?></td>
                                        <td class="text-center"><?php echo e($dp['time_frame']); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center text-muted small py-3">No developmental plan recorded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Comments Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> V. Comments & Decisions
                    </div>
                    <div class="row">
                        <?php if($sub['staff_comments']): ?>
                        <div class="col-sm-4 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">My Comments</strong>
                            <div class="p-3 bg-light rounded-3 border italic small" style="min-height:80px;"><?php echo nl2br(e($sub['staff_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if($sub['supervisor_comments']): ?>
                        <div class="col-sm-4 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">Supervisor Feedback</strong>
                            <div class="p-3 bg-light rounded-3 border border-primary italic small" style="min-height:80px;"><?php echo nl2br(e($sub['supervisor_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if($sub['manager_comments']): ?>
                        <div class="col-sm-4 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">Manager Remarks</strong>
                            <div class="p-3 bg-light rounded-3 border border-warning italic small" style="min-height:80px;"><?php echo nl2br(e($sub['manager_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($sub['status'] === 'Returned'): ?>
                        <div class="fixed-action-bar d-flex justify-content-end">
                            <a href="<?php echo BASE_URL; ?>/staff/submit-evaluation.php?edit=<?php echo $sub['evaluation_id']; ?>" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-edit me-2"></i>Edit & Re-submit
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
function filterByStatus(status, btn) {
    // Update active button
    const buttons = btn.parentElement.querySelectorAll('.btn');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Filter table rows
    const rows = document.querySelectorAll('#subsTable tbody tr');
    rows.forEach(row => {
        if (status === 'All') {
            row.style.display = '';
        } else {
            const rowStatus = row.getAttribute('data-status');
            row.style.display = (rowStatus === status) ? '' : 'none';
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
