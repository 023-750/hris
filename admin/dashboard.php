<?php
$page_title = 'Admin Dashboard';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/header.php';

// Fetch stats
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$active_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_active = 1")->fetch_assoc()['c'];
$total_branches = $conn->query("SELECT COUNT(*) as c FROM branches WHERE deleted_at IS NULL")->fetch_assoc()['c'];
$recent_logs = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'];

// --- SYSTEM ANALYTICS ---

// 1. User Roles Distribution
$roles_res = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_labels = [];
$role_counts = [];
while ($row = $roles_res->fetch_assoc()) {
    $role_labels[] = $row['role'];
    $role_counts[] = (int) $row['count'];
}

// 2. Account Status Breakdown
$status_res = $conn->query("SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active");
$status_labels = ['Inactive', 'Active'];
$status_counts = [0, 0];
while ($row = $status_res->fetch_assoc()) {
    $status_counts[(int)$row['is_active']] = (int)$row['count'];
}

// 3. System Activity (Last 7 Days)
$activity_res = $conn->query("SELECT DATE_FORMAT(timestamp, '%b %d') as label, COUNT(*) as count 
                              FROM audit_logs 
                              WHERE timestamp >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) 
                              GROUP BY DATE(timestamp), DATE_FORMAT(timestamp, '%b %d') 
                              ORDER BY DATE(timestamp) ASC");
$activity_labels = [];
$activity_counts = [];
if ($activity_res) {
    while ($row = $activity_res->fetch_assoc()) {
        $activity_labels[] = $row['label'];
        $activity_counts[] = (int) $row['count'];
    }
}

// Branch data for table
$branches_res = $conn->query("SELECT * FROM branches WHERE deleted_at IS NULL ORDER BY branch_name");
$branches = [];
while ($row = $branches_res->fetch_assoc()) {
    $branches[] = $row;
}
$selected_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : (count($branches) > 0 ? $branches[0]['branch_id'] : null);
$branch_active_users = [];
if ($selected_branch_id) {
    $stmt = $conn->prepare("SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.branch_id WHERE u.branch_id = ? AND u.is_active = 1 ORDER BY u.full_name ASC");
    $stmt->bind_param("i", $selected_branch_id);
    $stmt->execute();
    $branch_active_users = $stmt->get_result();
    $stmt->close();
}

$audit_logs = $conn->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.timestamp DESC LIMIT 10");
?>

<div class="dashboard-header mb-4">
    <h2 class="fw-bold">System Overview</h2>
    <p class="text-muted">Security oversight and user management control center</p>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users-cog"></i></div>
            <div class="stat-info">
                <h3><?php echo $total_users; ?></h3>
                <p>Total User Accounts</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <h3><?php echo $active_users; ?></h3>
                <p>Active Sessions</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <h3><?php echo $total_branches; ?></h3>
                <p>Registered Branches</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info">
                <h3><?php echo $recent_logs; ?></h3>
                <p>Logs (Last 24h)</p>
            </div>
        </div>
    </div>
</div>

<!-- System Analytics -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-user-tag me-2"></i>User Roles Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="rolesChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-toggle-on me-2"></i>Account Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Activity Trend (7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="activityChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Active Users by Branch -->
    <div class="col-lg-6">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-shield-alt me-2"></i>User Access by Branch</h5>
                <form method="GET" class="d-flex align-items-center" style="max-width: 200px;">
                    <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['branch_id']; ?>" <?php echo $selected_branch_id == $b['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo e($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>System User</th>
                                <th>Access Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$branch_active_users || $branch_active_users->num_rows === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No active users in selected branch.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($u = $branch_active_users->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo e($u['full_name']); ?></strong></td>
                                        <td><span class="badge bg-primary"><?php echo e($u['role']); ?></span></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Security Activity -->
    <div class="col-lg-6">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>Recent Security Activity</h5>
                <a href="<?php echo BASE_URL; ?>/admin/audit-trail.php" class="btn btn-sm btn-outline-primary">View Full Trail</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Operation</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($audit_logs->num_rows === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No security logs recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($log = $audit_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo e($log['full_name'] ?? 'System Process'); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo e($log['action_type']); ?></span></td>
                                        <td><small><?php echo formatDateTime($log['timestamp']); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
            }
        };

        // 1. Roles Chart (Pie)
        new Chart(document.getElementById('rolesChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($role_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($role_counts); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 2. Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: ['#e74a3b', '#1cc88a'],
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 3. Activity Trend (Line)
        new Chart(document.getElementById('activityChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($activity_labels); ?>,
                datasets: [{
                    label: 'Audit Events',
                    data: <?php echo json_encode($activity_counts); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                ...commonOptions,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>