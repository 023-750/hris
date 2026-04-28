<?php
$page_title = 'Career Movements';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/header.php';

// Fetch career movements
$movements = $conn->query("SELECT cm.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title,
    u.full_name as logged_by_name, u2.full_name as approved_by_name
    FROM career_movements cm
    LEFT JOIN employees e ON cm.employee_id = e.employee_id
    LEFT JOIN users u ON cm.logged_by = u.user_id
    LEFT JOIN users u2 ON cm.approved_by = u2.user_id
    ORDER BY cm.created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Career movement history</p>
    <a href="<?php echo BASE_URL; ?>/supervisor/log-movement.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Log Movement
    </a>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-exchange-alt me-2"></i>Movement History</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchMovements" placeholder="Search..." onkeyup="filterTable('searchMovements', 'movementsTable')">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="movementsTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Previous Position</th>
                        <th>New Position</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th>Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($movements->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No career movements logged yet.</td></tr>
                    <?php else: ?>
                        <?php while ($m = $movements->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($m['employee_name']); ?></strong></td>
                                <td>
                                    <?php
                                    $type_class = 'bg-secondary';
                                    switch ($m['movement_type']) {
                                        case 'Promotion': $type_class = 'bg-success'; break;
                                        case 'Transfer': $type_class = 'bg-info'; break;
                                        case 'Demotion': $type_class = 'bg-danger'; break;
                                        case 'Role Change': $type_class = 'bg-primary'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_class; ?>"><?php echo e($m['movement_type']); ?></span>
                                </td>
                                <td><?php echo e($m['previous_position'] ?? 'N/A'); ?></td>
                                <td><?php echo e($m['new_position']); ?></td>
                                <td><small><?php echo formatDate($m['effective_date']); ?></small></td>
                                <td>
                                    <?php
                                    $status_class = 'bg-warning text-dark';
                                    if ($m['approval_status'] === 'Approved') $status_class = 'bg-success';
                                    elseif ($m['approval_status'] === 'Rejected') $status_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo e($m['approval_status']); ?></span>
                                </td>
                                <td><?php echo e($m['logged_by_name'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
