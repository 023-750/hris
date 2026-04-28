<?php
$page_title = 'View Template';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

// Validate template ID
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tid <= 0) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Invalid template ID.');
}

// Fetch template
$stmt = $conn->prepare("SELECT * FROM evaluation_templates WHERE template_id = ? AND status = 'Active' AND deleted_at IS NULL");
$stmt->bind_param("i", $tid);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$template) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Template not found or is no longer active.');
}

// Fetch criteria
$criteria_stmt = $conn->prepare("SELECT * FROM evaluation_criteria WHERE template_id = ? ORDER BY sort_order");
$criteria_stmt->bind_param("i", $tid);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0"><i class="fas fa-lock me-1"></i> Review evaluation criteria and scoring weights for this template.</p>
    <a href="<?php echo BASE_URL; ?>/staff/templates.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Templates
    </a>
</div>

<div class="row">
    <!-- Template Information -->
    <div class="col-lg-4 mb-4">
        <div class="content-card h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold border-start border-4 border-primary ps-2">Template Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="small text-muted d-block">Template Name</label>
                    <span class="fw-bold text-dark"><?php echo e($template['template_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Target Position</label>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2"><?php echo e($template['target_position'] ?: 'General / All Positions'); ?></span>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Description</label>
                    <p class="small text-muted mb-0"><?php echo nl2br(e($template['description'] ?: 'No description provided.')); ?></p>
                </div>
                <hr class="my-3 opacity-10">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <label class="small text-muted d-block">Total Weight</label>
                        <?php 
                        $total_weight = array_sum(array_column($criteria, 'weight'));
                        $w_class = abs($total_weight - 100) < 0.01 ? 'text-success' : 'text-warning';
                        ?>
                        <span class="fw-bold <?php echo $w_class; ?>"><?php echo number_format($total_weight, 2); ?>%</span>
                    </div>
                    <div>
                        <label class="small text-muted d-block text-end">Criteria Count</label>
                        <span class="fw-bold text-dark d-block text-end"><?php echo count($criteria); ?></span>
                    </div>
                </div>

                <?php if (abs($total_weight - 100) >= 0.01): ?>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>Total weight does not equal 100%. Contact your supervisor or manager.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scoring Guide -->
        <div class="content-card mt-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold border-start border-4 border-info ps-2">Scoring Guide</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#e8f5e9;">
                        <span class="fw-semibold text-success">Excellent</span>
                        <span class="text-muted">90 – 100%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#e0f7fa;">
                        <span class="fw-semibold text-info">Above Average</span>
                        <span class="text-muted">80 – 89%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#fff9c4;">
                        <span class="fw-semibold text-warning">Average</span>
                        <span class="text-muted">70 – 79%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded" style="background:#ffebee;">
                        <span class="fw-semibold text-danger">Needs Improvement</span>
                        <span class="text-muted">Below 70%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Criteria List -->
    <div class="col-lg-8 mb-4">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-transparent">
                <h6 class="mb-0 fw-bold border-start border-4 border-primary ps-2">Evaluation Criteria</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($criteria)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-clipboard fa-2x mb-2 opacity-25 d-block"></i>
                        <p>No criteria defined for this template.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Criterion Name</th>
                                    <th class="text-center">Weight</th>
                                    <th>Scoring Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($criteria as $i => $c): ?>
                                    <tr>
                                        <td><span class="text-muted small fw-bold"><?php echo $i + 1; ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark small"><?php echo e($c['criterion_name']); ?></div>
                                            <?php if(!empty($c['description'])): ?>
                                                <div class="text-muted" style="font-size: 0.75rem; line-height: 1.2;"><?php echo e($c['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2"><?php echo (float)$c['weight']; ?>%</span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php
                                                $method = $c['scoring_method'];
                                                $icon = 'fa-star';
                                                $label = 'Scale 1-5';
                                                if ($method === 'Scale_1_10') { $icon = 'fa-list-ol'; $label = 'Scale 1-10'; }
                                                elseif ($method === 'Percentage') { $icon = 'fa-percent'; $label = 'Percentage (0-100%)'; }
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-1 text-muted opacity-50"></i> <?php echo $label; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="2" class="text-end fw-bold small">Total Weight</td>
                                    <td class="text-center">
                                        <span class="badge <?php echo abs($total_weight - 100) < 0.01 ? 'bg-success' : 'bg-warning text-dark'; ?> px-2"><?php echo number_format($total_weight, 2); ?>%</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bg-info-subtle { background-color: #e0f7fa; }
.bg-secondary-subtle { background-color: #f5f5f5; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
.table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; border-bottom: 2px solid #f1f1f1; }
.table td { padding: 14px 16px; border-bottom: 1px solid #f8f9fa; }
.table tfoot td { padding: 12px 16px; font-size: 0.85rem; }
</style>

<?php require_once '../includes/footer.php'; ?>
