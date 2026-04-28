<?php
$page_title = 'My Career History';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

// Find the linked employee record via the logged-in user's email
$employee = null;
if (isset($_SESSION['email'])) {
    $stmt = $conn->prepare("SELECT e.employee_id FROM employees e
        JOIN employee_contacts ec ON e.employee_id = ec.employee_id
        WHERE ec.personal_email = ? AND e.is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$career_history = [];
if ($employee) {
    $eid = $employee['employee_id'];
    $res = $conn->query("
        SELECT cm.*,
            b1.branch_name AS prev_branch_name,
            b2.branch_name AS new_branch_name,
            u.full_name AS logged_by_name
        FROM career_movements cm
        LEFT JOIN branches b1 ON cm.previous_branch_id = b1.branch_id
        LEFT JOIN branches b2 ON cm.new_branch_id = b2.branch_id
        LEFT JOIN users u ON cm.logged_by = u.user_id
        WHERE cm.employee_id = $eid
        ORDER BY cm.effective_date DESC
    ");
    $career_history = $res->fetch_all(MYSQLI_ASSOC);
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Your career movement history within the company.</p>
</div>

<?php if (!$employee): ?>
    <div class="content-card">
        <div class="card-body text-center py-5">
            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Employee Record Linked</h5>
            <p class="text-muted small">Your user account is not linked to an employee record. Please contact your HR
                Manager.</p>
        </div>
    </div>
<?php else: ?>

    <!-- Summary -->
    <?php
    $approved = array_filter($career_history, fn($r) => $r['approval_status'] === 'Approved');
    $pending = array_filter($career_history, fn($r) => $r['approval_status'] === 'Pending');
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="content-card text-center py-3">
                <div style="font-size:2rem;font-weight:700;color:var(--primary-blue);"><?php echo count($career_history); ?>
                </div>
                <div class="text-muted small">Total Movements</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card text-center py-3">
                <div style="font-size:2rem;font-weight:700;color:var(--success-color);"><?php echo count($approved); ?>
                </div>
                <div class="text-muted small">Approved</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card text-center py-3">
                <div style="font-size:2rem;font-weight:700;color:var(--warning-color);"><?php echo count($pending); ?></div>
                <div class="text-muted small">Pending</div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="content-card">
        <div class="card-header">
            <h5><i class="fas fa-route me-2"></i>Career Movement Timeline</h5>
        </div>
        <div class="card-body">
            <?php if (empty($career_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-route d-block"></i>
                    <p>No career movements recorded yet</p>
                </div>
            <?php else: ?>
                <div class="career-timeline">
                    <?php foreach ($career_history as $cm):
                        $movClass = 'bg-secondary';
                        $movIcon = 'fa-arrow-right';
                        switch ($cm['movement_type']) {
                            case 'Promotion':
                                $movClass = 'bg-success';
                                $movIcon = 'fa-arrow-up';
                                break;
                            case 'Transfer':
                                $movClass = 'bg-info';
                                $movIcon = 'fa-exchange-alt';
                                break;
                            case 'Demotion':
                                $movClass = 'bg-danger';
                                $movIcon = 'fa-arrow-down';
                                break;
                            case 'Role Change':
                                $movClass = 'bg-primary';
                                $movIcon = 'fa-sync-alt';
                                break;
                        }
                        $statClass = 'bg-warning text-dark';
                        if ($cm['approval_status'] === 'Approved')
                            $statClass = 'bg-success';
                        if ($cm['approval_status'] === 'Rejected')
                            $statClass = 'bg-danger';
                        ?>
                        <div class="d-flex gap-3 mb-4">
                            <div class="flex-shrink-0 d-flex flex-column align-items-center">
                                <div class="rounded-circle <?php echo $movClass; ?> text-white d-flex align-items-center justify-content-center"
                                    style="width:40px;height:40px;min-height:40px;">
                                    <i class="fas <?php echo $movIcon; ?>"></i>
                                </div>
                                <div style="width:2px;flex:1;background:#e9ecef;margin-top:4px;"></div>
                            </div>
                            <div class="flex-grow-1 pb-2">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <span
                                            class="badge <?php echo $movClass; ?> me-2"><?php echo e($cm['movement_type']); ?></span>
                                        <span class="badge <?php echo $statClass; ?>">
                                            <?php echo e($cm['approval_status']); ?>
                                            <?php if ($cm['approval_status'] === 'Approved'): ?>
                                                <?php if ($cm['is_applied']): ?>
                                                    <i class="fas fa-check ms-1"></i>
                                                <?php else: ?>
                                                    (Scheduled)
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">Logged <?php echo formatDateTime($cm['created_at']); ?></small>
                                </div>

                                <div class="mt-2">
                                    <span class="text-muted small"><?php echo e($cm['previous_position'] ?? 'N/A'); ?></span>
                                    <i class="fas fa-long-arrow-alt-right mx-2 text-primary"></i>
                                    <strong class="small"><?php echo e($cm['new_position']); ?></strong>
                                </div>

                                <?php if (!empty($cm['new_branch_id'])): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-building me-1"></i>
                                        <?php echo e($cm['prev_branch_name'] ?? '?'); ?> →
                                        <strong><?php echo e($cm['new_branch_name']); ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div class="small text-muted mt-1">
                                    <i class="fas fa-calendar-check me-1"></i>Effective:
                                    <strong><?php echo formatDate($cm['effective_date']); ?></strong>
                                </div>

                                <?php if (!empty($cm['reason'])): ?>
                                    <div class="small text-muted mt-1 p-2 rounded" style="background:#f8f9fa;">
                                        <i class="fas fa-quote-left me-1"></i><?php echo e($cm['reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>