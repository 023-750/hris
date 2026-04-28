<?php
$page_title = 'Audit Trail';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/header.php';

// Fetch all audit logs with user name
$audit_logs = $conn->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.timestamp DESC LIMIT 100");
?>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-clipboard-list me-2"></i>System Audit Trail</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchAudit" placeholder="Search logs..." onkeyup="filterTable('searchAudit', 'auditTable')">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="auditTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($audit_logs->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No audit logs found.</td></tr>
                    <?php else: ?>
                        <?php while ($log = $audit_logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $log['log_id']; ?></td>
                                <td><small><?php echo formatDateTime($log['timestamp']); ?></small></td>
                                <td><?php echo e($log['full_name'] ?? 'System'); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'bg-secondary';
                                    switch ($log['action_type']) {
                                        case 'CREATE': $badge_class = 'bg-success'; break;
                                        case 'UPDATE': $badge_class = 'bg-info'; break;
                                        case 'DELETE': $badge_class = 'bg-danger'; break;
                                        case 'LOGIN': $badge_class = 'bg-primary'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo e($log['action_type']); ?></span>
                                </td>
                                <td><?php echo e($log['entity_type']); ?></td>
                                <td><small><?php echo e($log['details']); ?></small></td>
                                <td><small class="text-muted"><?php echo e($log['ip_address']); ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
