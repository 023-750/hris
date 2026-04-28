<?php
$page_title = 'Staff Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/header.php';

$uid = $_SESSION['user_id'];

// Fetch stats
$draft_count = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE submitted_by = $uid AND status = 'Draft'")->fetch_assoc()['c'];
$submitted_month = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE submitted_by = $uid AND MONTH(submitted_date) = MONTH(CURRENT_DATE()) AND YEAR(submitted_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];
$approved_count = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE submitted_by = $uid AND status = 'Approved'")->fetch_assoc()['c'];
$returned_count = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE submitted_by = $uid AND status = 'Returned'")->fetch_assoc()['c'];

// Recent submissions
$recent = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, et.template_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.submitted_by = $uid
    ORDER BY ev.created_at DESC LIMIT 5");
?>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <h3><?php echo $draft_count; ?></h3>
                <p>Draft Evaluations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-info">
                <h3><?php echo $submitted_month; ?></h3>
                <p>Submitted This Month</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?php echo $approved_count; ?></h3>
                <p>Approved Evaluations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-undo"></i></div>
            <div class="stat-info">
                <h3><?php echo $returned_count; ?></h3>
                <p>Returned for Revision</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-md-6">
        <a href="<?php echo BASE_URL; ?>/staff/submit-evaluation.php" class="btn btn-primary btn-lg w-100">
            <i class="fas fa-edit me-2"></i>Submit New Evaluation
        </a>
    </div>
    <div class="col-md-6">
        <a href="<?php echo BASE_URL; ?>/staff/my-drafts.php" class="btn btn-outline-primary btn-lg w-100">
            <i class="fas fa-file-alt me-2"></i>View My Drafts
        </a>
    </div>
</div>

<!-- Recent Submissions -->
<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-history me-2"></i>Recent Submissions</h5>
        <a href="<?php echo BASE_URL; ?>/staff/my-submissions.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Template</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No submissions yet. Start by submitting a new evaluation!</td></tr>
                    <?php else: ?>
                        <?php while ($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($row['employee_name']); ?></strong></td>
                                <td><small><?php echo e($row['template_name']); ?></small></td>
                                <td><small><?php echo $row['submitted_date'] ? formatDate($row['submitted_date']) : 'Draft'; ?></small></td>
                                <td><span class="badge <?php echo getStatusBadgeClass($row['status']); ?>"><?php echo e($row['status']); ?></span></td>
                                <td><?php echo $row['total_score'] ? $row['total_score'] . '%' : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
