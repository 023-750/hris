<?php
$page_title = 'Self Rating';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);

$employee_stmt = $conn->prepare("
    SELECT e.employee_id, e.first_name, e.last_name, e.job_title, e.department_id, e.branch_id,
           d.department_name, b.branch_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE e.employee_id = ?
    LIMIT 1
");
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();
$employee_stmt->close();

if (!$employee) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'No employee record found for self-rating.');
}

$edit_eval = null;
$edit_scores = [];
$selected_template_id = isset($_GET['template']) ? (int)$_GET['template'] : 0;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("
        SELECT *
        FROM evaluations
        WHERE evaluation_id = ?
          AND employee_id = ?
          AND submitted_by = ?
          AND status IN ('Draft', 'Returned')
        LIMIT 1
    ");
    $stmt->bind_param("iii", $edit_id, $employee_id, $user_id);
    $stmt->execute();
    $edit_eval = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($edit_eval) {
        $selected_template_id = (int)$edit_eval['template_id'];
        $score_rs = $conn->query("SELECT criterion_id, score_value FROM evaluation_scores WHERE evaluation_id = " . (int)$edit_eval['evaluation_id']);
        while ($score = $score_rs->fetch_assoc()) {
            $edit_scores[(int)$score['criterion_id']] = $score['score_value'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = (int)($_POST['template_id'] ?? 0);
    $evaluation_type = trim($_POST['evaluation_type'] ?? 'Annual');
    $period_start = $_POST['period_start'] ?: null;
    $period_end = $_POST['period_end'] ?: null;
    $self_comments = trim($_POST['self_comments'] ?? '');
    $action = $_POST['submit_action'] ?? 'draft';
    $kra_scores = $_POST['kra_scores'] ?? [];
    $beh_scores = $_POST['beh_scores'] ?? [];
    $editing_id = (int)($_POST['edit_id'] ?? 0);

    $errors = [];
    if ($template_id <= 0) {
        $errors[] = 'Please select an evaluation template.';
    }

    $template_stmt = $conn->prepare("SELECT template_id, template_name, kra_weight, behavior_weight FROM evaluation_templates WHERE template_id = ? AND status = 'Active' LIMIT 1");
    $template_stmt->bind_param("i", $template_id);
    $template_stmt->execute();
    $template = $template_stmt->get_result()->fetch_assoc();
    $template_stmt->close();

    if (!$template) {
        $errors[] = 'Selected template is not available.';
    }

    if ($action === 'submit') {
        $criteria_count_rs = $conn->query("SELECT COUNT(*) AS total FROM evaluation_criteria WHERE template_id = $template_id");
        $criteria_total = (int)($criteria_count_rs->fetch_assoc()['total'] ?? 0);
        if ($criteria_total <= 0) {
            $errors[] = 'This template has no criteria yet.';
        }
    }

    if (!empty($errors)) {
        redirectWith(BASE_URL . '/employee/self-rating.php' . ($editing_id ? '?edit=' . $editing_id : '?template=' . $template_id), 'danger', implode(' ', $errors));
    }

    $kra_weight_pct = (float)($template['kra_weight'] ?? 80);
    $beh_weight_pct = (float)($template['behavior_weight'] ?? 20);

    $kra_subtotal = 0;
    $kra_score_data = [];
    $kra_criteria = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id AND section='KRA' ORDER BY sort_order");
    while ($criterion = $kra_criteria->fetch_assoc()) {
        $criterion_id = (int)$criterion['criterion_id'];
        $rating = (float)($kra_scores[$criterion_id] ?? 0);
        if ($rating > 4.00) $rating = 4.00;
        if ($rating < 0) $rating = 0;
        $weight = (float)$criterion['weight'];
        $weighted = round(($weight / 100) * $rating, 2);
        $kra_subtotal += $weighted;
        $kra_score_data[] = ['criterion_id' => $criterion_id, 'score_value' => $rating, 'weighted_score' => $weighted];
    }
    $kra_subtotal = round($kra_subtotal, 2);

    $beh_score_data = [];
    $behavior_total = 0;
    $behavior_count = 0;
    $beh_criteria = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id AND section='Behavior' ORDER BY sort_order");
    while ($criterion = $beh_criteria->fetch_assoc()) {
        $criterion_id = (int)$criterion['criterion_id'];
        $rating = (float)($beh_scores[$criterion_id] ?? 0);
        if ($rating > 4.00) $rating = 4.00;
        if ($rating < 0) $rating = 0;
        $behavior_total += $rating;
        $behavior_count++;
        $beh_score_data[] = ['criterion_id' => $criterion_id, 'score_value' => $rating, 'weighted_score' => $rating];
    }
    $behavior_average = $behavior_count > 0 ? round($behavior_total / $behavior_count, 2) : 0;

    $total_score = calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight_pct, $beh_weight_pct);
    $performance_level = getPerformanceLevel($total_score);
    $status = ($action === 'submit') ? 'Pending Supervisor' : 'Draft';
    $submitted_date = ($action === 'submit') ? date('Y-m-d H:i:s') : null;

    if ($editing_id > 0) {
        $stmt = $conn->prepare("
            UPDATE evaluations
            SET template_id=?, evaluation_type=?, evaluation_period_start=?, evaluation_period_end=?,
                status=?, total_score=?, kra_subtotal=?, behavior_average=?, performance_level=?,
                submitted_date=?, staff_comments=?, current_position=?, months_in_position=?,
                desired_position=?, target_date=?, career_growth_suited=?, career_growth_details=?
            WHERE evaluation_id=? AND employee_id=? AND submitted_by=?
        ");
        $current_position = (string)($employee['job_title'] ?? '');
        $months_in_position = 0;
        $desired_position = '';
        $target_date = null;
        $career_growth_suited = 0;
        $career_growth_details = '';
        $stmt->bind_param(
            "issssdddsssissisiii",
            $template_id,
            $evaluation_type,
            $period_start,
            $period_end,
            $status,
            $total_score,
            $kra_subtotal,
            $behavior_average,
            $performance_level,
            $submitted_date,
            $self_comments,
            $current_position,
            $months_in_position,
            $desired_position,
            $target_date,
            $career_growth_suited,
            $career_growth_details,
            $editing_id,
            $employee_id,
            $user_id
        );
        $stmt->execute();
        $stmt->close();

        $conn->query("DELETE FROM evaluation_scores WHERE evaluation_id = $editing_id");
        $eval_id = $editing_id;
    } else {
        $stmt = $conn->prepare("
            INSERT INTO evaluations (
                employee_id, template_id, evaluation_type, evaluation_period_start, evaluation_period_end,
                submitted_by, status, total_score, kra_subtotal, behavior_average, performance_level,
                submitted_date, staff_comments, current_position, months_in_position,
                desired_position, target_date, career_growth_suited, career_growth_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $current_position = (string)($employee['job_title'] ?? '');
        $months_in_position = 0;
        $desired_position = '';
        $target_date = null;
        $career_growth_suited = 0;
        $career_growth_details = '';
        $stmt->bind_param(
            "iisssisdddssssissis",
            $employee_id,
            $template_id,
            $evaluation_type,
            $period_start,
            $period_end,
            $user_id,
            $status,
            $total_score,
            $kra_subtotal,
            $behavior_average,
            $performance_level,
            $submitted_date,
            $self_comments,
            $current_position,
            $months_in_position,
            $desired_position,
            $target_date,
            $career_growth_suited,
            $career_growth_details
        );
        $stmt->execute();
        $eval_id = (int)$stmt->insert_id;
        $stmt->close();
    }

    $score_stmt = $conn->prepare("INSERT INTO evaluation_scores (evaluation_id, criterion_id, score_value, weighted_score) VALUES (?, ?, ?, ?)");
    foreach (array_merge($kra_score_data, $beh_score_data) as $score_data) {
        $score_stmt->bind_param("iidd", $eval_id, $score_data['criterion_id'], $score_data['score_value'], $score_data['weighted_score']);
        $score_stmt->execute();
    }
    $score_stmt->close();

    if ($action === 'submit') {
        $employee_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $supervisors = $conn->query("SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1");
        while ($supervisor = $supervisors->fetch_assoc()) {
            createNotification(
                $conn,
                (int)$supervisor['user_id'],
                'Employee Self-Rating Submitted',
                $employee_name . ' submitted a self-rating for review.',
                BASE_URL . '/supervisor/pending-endorsements.php'
            );
        }
        logAudit($conn, $user_id, 'CREATE', 'Evaluation', $eval_id, 'Submitted employee self-rating');
        redirectWith(BASE_URL . '/employee/self-rating.php', 'success', 'Your self-rating was submitted successfully.');
    }

    logAudit($conn, $user_id, 'CREATE', 'Evaluation', $eval_id, 'Saved employee self-rating draft');
    redirectWith(BASE_URL . '/employee/self-rating.php?edit=' . $eval_id, 'success', 'Your self-rating draft was saved.');
}

$templates = $conn->query("SELECT template_id, template_name, kra_weight, behavior_weight FROM evaluation_templates WHERE status = 'Active' ORDER BY template_name");
$criteria_kra = [];
$criteria_behavior = [];
if ($selected_template_id > 0) {
    $criteria_query = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = " . $selected_template_id . " ORDER BY section, sort_order");
    while ($criterion = $criteria_query->fetch_assoc()) {
        if (($criterion['section'] ?? '') === 'Behavior') {
            $criteria_behavior[] = $criterion;
        } else {
            $criteria_kra[] = $criterion;
        }
    }
}

$history = $conn->query("
    SELECT ev.evaluation_id, ev.evaluation_type, ev.status, ev.total_score, ev.performance_level, ev.submitted_date, ev.updated_at,
           et.template_name
    FROM evaluations ev
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.employee_id = $employee_id AND ev.submitted_by = $user_id
    ORDER BY COALESCE(ev.submitted_date, ev.updated_at) DESC, ev.evaluation_id DESC
    LIMIT 10
");

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Self Rating</h4>
        <small class="text-muted">Complete your own evaluation before Supervisor and HR review</small>
    </div>
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="alert alert-info py-2 mb-4" style="font-size:0.9rem;">
    <i class="fas fa-info-circle me-2"></i>
    This page is for your self-rating only. HR Staff, HR Supervisor, and HR Manager will continue the official review workflow after you submit.
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="content-card">
            <div class="card-header">
                <h5><i class="fas fa-star me-2"></i><?php echo $edit_eval ? 'Continue Self Rating' : 'New Self Rating'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($edit_eval): ?>
                        <input type="hidden" name="edit_id" value="<?php echo (int)$edit_eval['evaluation_id']; ?>">
                    <?php endif; ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" value="<?php echo e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" value="<?php echo e($employee['job_title'] ?? '—'); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Evaluation Type</label>
                            <select class="form-select" name="evaluation_type">
                                <?php $selected_type = $edit_eval['evaluation_type'] ?? 'Annual'; ?>
                                <option value="Annual" <?php echo $selected_type === 'Annual' ? 'selected' : ''; ?>>Annual</option>
                                <option value="Quarterly" <?php echo $selected_type === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="Probationary" <?php echo $selected_type === 'Probationary' ? 'selected' : ''; ?>>Probationary</option>
                                <option value="Special" <?php echo $selected_type === 'Special' ? 'selected' : ''; ?>>Special</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period Start</label>
                            <input type="date" class="form-control" name="period_start" value="<?php echo e($edit_eval['evaluation_period_start'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period End</label>
                            <input type="date" class="form-control" name="period_end" value="<?php echo e($edit_eval['evaluation_period_end'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Template</label>
                            <select class="form-select" name="template_id" onchange="if(this.value){ window.location='?template=' + this.value <?php echo $edit_eval ? " + '&edit=" . (int)$edit_eval['evaluation_id'] . "'" : ''; ?>; } else { window.location='self-rating.php'; }" required>
                                <option value="">Select Template</option>
                                <?php while ($template = $templates->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$template['template_id']; ?>" <?php echo $selected_template_id === (int)$template['template_id'] ? 'selected' : ''; ?>>
                                        <?php echo e($template['template_name']); ?> (<?php echo (float)$template['kra_weight']; ?>% KRA / <?php echo (float)$template['behavior_weight']; ?>% Behavior)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($selected_template_id > 0 && (!empty($criteria_kra) || !empty($criteria_behavior))): ?>
                        <div class="section-premium-label mb-3">
                            <i class="fas fa-bullseye"></i>KRA Self Rating
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th style="width:110px;">Weight</th>
                                        <th style="width:160px;">Your Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($criteria_kra as $criterion): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($criterion['criterion_name']); ?></div>
                                                <?php if (!empty($criterion['description'])): ?>
                                                    <div class="small text-muted"><?php echo e($criterion['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e($criterion['weight']); ?>%</td>
                                            <td>
                                                <input type="number" class="form-control" name="kra_scores[<?php echo (int)$criterion['criterion_id']; ?>]"
                                                       min="0" max="4" step="0.01"
                                                       value="<?php echo e($edit_scores[(int)$criterion['criterion_id']] ?? ''); ?>"
                                                       placeholder="0.00 - 4.00">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="section-premium-label mb-3">
                            <i class="fas fa-heart"></i>Behavior Self Rating
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th style="width:160px;">Your Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($criteria_behavior as $criterion): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($criterion['criterion_name']); ?></div>
                                                <?php if (!empty($criterion['description'])): ?>
                                                    <div class="small text-muted"><?php echo e($criterion['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="beh_scores[<?php echo (int)$criterion['criterion_id']; ?>]"
                                                       min="0" max="4" step="0.01"
                                                       value="<?php echo e($edit_scores[(int)$criterion['criterion_id']] ?? ''); ?>"
                                                       placeholder="0.00 - 4.00">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Self Comments</label>
                            <textarea class="form-control" name="self_comments" rows="4" placeholder="Share any notes about your self-rating..."><?php echo e($edit_eval['staff_comments'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary">
                                <i class="fas fa-save me-2"></i>Save Draft
                            </button>
                            <button type="submit" name="submit_action" value="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Self Rating
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-signature d-block"></i>
                            <p class="mb-0">Select an active template to start your self-rating.</p>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="content-card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-route me-2"></i>How It Works</h5>
            </div>
            <div class="card-body">
                <div class="small text-muted">
                    <p class="mb-2"><strong>1.</strong> Choose the active evaluation template.</p>
                    <p class="mb-2"><strong>2.</strong> Encode your self-rating and save a draft if needed.</p>
                    <p class="mb-2"><strong>3.</strong> Submit your self-rating for Supervisor review.</p>
                    <p class="mb-0"><strong>4.</strong> HR and management continue the approval workflow.</p>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>Recent Self Ratings</h5>
            </div>
            <div class="card-body">
                <?php if ($history->num_rows === 0): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-inbox d-block"></i>
                        <p class="mb-0">No self-ratings yet.</p>
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php while ($item = $history->fetch_assoc()): ?>
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo e($item['template_name'] ?? 'Template'); ?></div>
                                        <div class="small text-muted"><?php echo e($item['evaluation_type'] ?? 'Evaluation'); ?></div>
                                    </div>
                                    <span class="badge <?php echo getStatusBadgeClass($item['status']); ?>"><?php echo e($item['status']); ?></span>
                                </div>
                                <div class="small text-muted mt-2">
                                    Updated: <?php echo formatDateTime($item['updated_at'] ?? ''); ?>
                                </div>
                                <div class="small mt-1">
                                    Score: <strong><?php echo e($item['total_score'] ?? '0.00'); ?></strong>
                                    <?php if (!empty($item['performance_level'])): ?>
                                        <span class="text-muted">• <?php echo e($item['performance_level']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (in_array($item['status'], ['Draft', 'Returned'], true)): ?>
                                    <div class="mt-3">
                                        <a href="<?php echo BASE_URL; ?>/employee/self-rating.php?edit=<?php echo (int)$item['evaluation_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Continue
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
