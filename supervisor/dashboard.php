<?php
$page_title = 'Supervisor Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/header.php';

// Fetch stats
$pending_validations = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE status = 'Pending Supervisor'")->fetch_assoc()['c'];

$validated_month = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE endorsed_by = {$_SESSION['user_id']} AND MONTH(endorsed_date) = MONTH(CURRENT_DATE()) AND YEAR(endorsed_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];

$career_movements = $conn->query("SELECT COUNT(*) as c FROM career_movements WHERE logged_by = {$_SESSION['user_id']}")->fetch_assoc()['c'];

$total_employees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

// Fetch recent pending validations
$pending = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    u.full_name as submitted_by_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    WHERE ev.status = 'Pending Supervisor'
    ORDER BY ev.submitted_date DESC LIMIT 5");
?>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-info">
                <h3><?php echo $pending_validations; ?></h3>
                <p>Pending Validations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?php echo $validated_month; ?></h3>
                <p>Validated This Month</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-info">
                <h3><?php echo $career_movements; ?></h3>
                <p>Career Movements Logged</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?php echo $total_employees; ?></h3>
                <p>Total Employees</p>
            </div>
        </div>
    </div>
</div>

<!-- Pending Validations -->
<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-clipboard-check me-2"></i>Pending Validations</h5>
        <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No pending validations.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $pending->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($row['employee_name']); ?></strong></td>
                                <td><?php echo e($row['submitted_by_name']); ?></td>
                                <td><small><?php echo formatDate($row['submitted_date']); ?></small></td>
                                <td>
                                    <span class="badge <?php echo getPerformanceBadgeClass($row['performance_level']); ?>">
                                        <?php echo $row['total_score']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i>Review
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
