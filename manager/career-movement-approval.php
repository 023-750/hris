<?php
$page_title = 'Career Movement Approval';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Apply any pending movements whose effective_date has arrived
applyPendingCareerMovements($conn);

// Fetch all movements with employee and user info
$movements = $conn->query("
    SELECT cm.*,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.job_title AS current_job_title,
        b1.branch_name AS prev_branch_name,
        b2.branch_name AS new_branch_name,
        u1.full_name AS logged_by_name,
        u2.full_name AS approved_by_name
    FROM career_movements cm
    LEFT JOIN employees e ON cm.employee_id = e.employee_id
    LEFT JOIN branches b1 ON cm.previous_branch_id = b1.branch_id
    LEFT JOIN branches b2 ON cm.new_branch_id = b2.branch_id
    LEFT JOIN users u1 ON cm.logged_by = u1.user_id
    LEFT JOIN users u2 ON cm.approved_by = u2.user_id
    ORDER BY cm.created_at DESC
");

// Counters
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$all = [];
while ($row = $movements->fetch_assoc()) {
    $all[] = $row;
    if ($row['approval_status'] === 'Pending')
        $pending_count++;
    elseif ($row['approval_status'] === 'Approved')
        $approved_count++;
    else
        $rejected_count++;
}

require_once '../includes/header.php';

function movTypeClass($type)
{
    switch ($type) {
        case 'Promotion':
            return 'bg-success';
        case 'Transfer':
            return 'bg-info';
        case 'Demotion':
            return 'bg-danger';
        case 'Role Change':
            return 'bg-primary';
        default:
            return 'bg-secondary';
    }
}
function statusClass($s)
{
    if ($s === 'Approved')
        return 'bg-success';
    if ($s === 'Rejected')
        return 'bg-danger';
    return 'bg-warning text-dark';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Review and approve or reject career movements submitted by HR Supervisors.</p>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="content-card text-center py-3">
            <div style="font-size:2rem;font-weight:700;color:var(--warning-color);"><?php echo $pending_count; ?></div>
            <div class="text-muted small">Pending Approval</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card text-center py-3">
            <div style="font-size:2rem;font-weight:700;color:var(--success-color);"><?php echo $approved_count; ?></div>
            <div class="text-muted small">Approved</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card text-center py-3">
            <div style="font-size:2rem;font-weight:700;color:var(--danger-color);"><?php echo $rejected_count; ?></div>
            <div class="text-muted small">Rejected</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="content-card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="movTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-pending"
                    type="button">
                    <i class="fas fa-clock me-1"></i>Pending
                    <?php if ($pending_count > 0): ?><span
                            class="badge bg-warning text-dark ms-1"><?php echo $pending_count; ?></span><?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-approved"
                    type="button">
                    <i class="fas fa-check me-1"></i>Approved
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-rejected"
                    type="button">
                    <i class="fas fa-times me-1"></i>Rejected
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content">
            <?php
            $tabs = [
                ['id' => 'tab-pending', 'status' => 'Pending', 'show' => true],
                ['id' => 'tab-approved', 'status' => 'Approved', 'show' => false],
                ['id' => 'tab-rejected', 'status' => 'Rejected', 'show' => false],
            ];
            foreach ($tabs as $tab):
                $rows = array_filter($all, fn($r) => $r['approval_status'] === $tab['status']);
                ?>
                <div class="tab-pane fade <?php echo $tab['show'] ? 'show active' : ''; ?>" id="<?php echo $tab['id']; ?>">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>From → To</th>
                                    <th>Branch Change</th>
                                    <th>Effective Date</th>
                                    <th>Logged By</th>
                                    <?php if ($tab['status'] === 'Pending'): ?>
                                        <th>Actions</th><?php else: ?>
                                        <th><?php echo $tab['status'] === 'Approved' ? 'Approved By' : 'Rejected By'; ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No
                                            <?php echo strtolower($tab['status']); ?> movements.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $m): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($m['employee_name']); ?></strong>
                                                <div class="small text-muted"><?php echo e($m['current_job_title']); ?></div>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo movTypeClass($m['movement_type']); ?>"><?php echo e($m['movement_type']); ?></span>
                                            </td>
                                            <td class="small">
                                                <span class="text-muted"><?php echo e($m['previous_position'] ?? 'N/A'); ?></span>
                                                <i class="fas fa-arrow-right mx-1 text-primary" style="font-size:0.65rem;"></i>
                                                <strong><?php echo e($m['new_position']); ?></strong>
                                            </td>
                                            <td class="small">
                                                <?php if (!empty($m['new_branch_id'])): ?>
                                                    <?php echo e($m['prev_branch_name'] ?? 'N/A'); ?> →
                                                    <strong><?php echo e($m['new_branch_name']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">No change</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo formatDate($m['effective_date']); ?></small>
                                                <?php if ($tab['status'] === 'Approved' && !$m['is_applied']): ?>
                                                    <span class="badge bg-secondary ms-1"
                                                        title="Effective date has not arrived yet">Scheduled</span>
                                                <?php elseif ($tab['status'] === 'Approved' && $m['is_applied']): ?>
                                                    <span class="badge bg-success ms-1">Applied</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?php echo e($m['logged_by_name'] ?? 'N/A'); ?></td>
                                            <?php if ($tab['status'] === 'Pending'): ?>
                                                <td>
                                                    <button class="btn btn-sm btn-success me-1" onclick="confirmAction(<?php echo $m['movement_id']; ?>, 'Approve',
                                        '<?php echo addslashes($m['employee_name']); ?>',
                                        '<?php echo addslashes($m['movement_type']); ?>',
                                        '<?php echo addslashes($m['new_position']); ?>')">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="confirmAction(<?php echo $m['movement_id']; ?>, 'Reject',
                                        '<?php echo addslashes($m['employee_name']); ?>',
                                        '<?php echo addslashes($m['movement_type']); ?>',
                                        '<?php echo addslashes($m['new_position']); ?>')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </td>
                                            <?php else: ?>
                                                <td class="small"><?php echo e($m['approved_by_name'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                        <tr class="bg-light border-bottom">
                                            <td colspan="7" class="small text-muted ps-3">
                                                <i class="fas fa-comment-alt me-1"></i><strong>Reason:</strong>
                                                <?php echo e($m['reason'] ?? '—'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?php echo BASE_URL; ?>/manager/process-career-movement.php"
                    id="confirmForm">
                    <input type="hidden" name="movement_id" id="confirmMovementId">
                    <input type="hidden" name="action" id="confirmActionInput">
                    <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs.card-header-tabs {
        border-bottom: none;
    }

    .nav-tabs.card-header-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: var(--text-muted);
        padding: 0.7rem 1rem;
    }

    .nav-tabs.card-header-tabs .nav-link.active {
        color: var(--primary-blue);
        border-bottom-color: var(--primary-blue);
        background: transparent;
    }
</style>

<script>
    function confirmAction(movementId, action, empName, movType, newPos) {
        document.getElementById('confirmMovementId').value = movementId;
        document.getElementById('confirmActionInput').value = action;
        const isApprove = action === 'Approve';
        document.getElementById('confirmTitle').textContent = (isApprove ? 'Approve' : 'Reject') + ' Career Movement';
        document.getElementById('confirmBody').innerHTML =
            `<p>You are about to <strong>${action.toLowerCase()}</strong> this career movement:</p>
         <ul class="list-group list-group-flush">
             <li class="list-group-item"><strong>Employee:</strong> ${empName}</li>
             <li class="list-group-item"><strong>Type:</strong> ${movType}</li>
             <li class="list-group-item"><strong>New Position:</strong> ${newPos}</li>
         </ul>
         <p class="mt-3 mb-0 text-${isApprove ? 'success' : 'danger'}">
             <i class="fas fa-${isApprove ? 'check-circle' : 'exclamation-triangle'} me-1"></i>
             ${isApprove ? 'Employee record will be updated on the effective date.' : 'This action cannot be undone.'}
         </p>`;
        const btn = document.getElementById('confirmBtn');
        btn.textContent = action;
        btn.className = 'btn btn-' + (isApprove ? 'success' : 'danger');
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }
</script>

<?php require_once '../includes/footer.php'; ?>