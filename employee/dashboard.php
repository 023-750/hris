<?php
$page_title = 'My Dashboard';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$employee_id = (int)($_SESSION['employee_id'] ?? 0);

$emp_stmt = $conn->prepare("
    SELECT e.first_name, e.last_name, e.job_title, e.profile_picture,
           d.department_name, b.branch_name, e.hire_date,
           e.employment_status, e.employment_type
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b    ON e.branch_id    = b.branch_id
    WHERE e.employee_id = ?
");
$emp_stmt->bind_param("i", $employee_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc();
$emp_stmt->close();

require_once '../includes/header.php';
?>

<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="content-card ess-gradient" style="border:none;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
          <img src="<?php echo getEmployeeAvatar($emp['profile_picture']??''); ?>?v=<?php echo time();?>"
               style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.5);">
          <div>
            <h2 class="mb-1 fw-bold">Welcome, <?php echo e($emp['first_name']??'Employee'); ?>!</h2>
            <p class="mb-0 opacity-75">
              <i class="fas fa-briefcase me-1"></i><?php echo e($emp['job_title']??'—'); ?>
              <?php if(!empty($emp['department_name'])): ?> &nbsp;•&nbsp; <?php echo e($emp['department_name']); ?><?php endif; ?>
              <?php if(!empty($emp['branch_name'])): ?> &nbsp;•&nbsp; <?php echo e($emp['branch_name']); ?><?php endif; ?>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-star me-2"></i>360-Degree Self Rating</h5></div>
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <div class="fw-semibold mb-1">Start your own evaluation</div>
            <div class="text-muted small">Complete your self-rating here before Supervisor and HR review.</div>
          </div>
          <a href="<?php echo BASE_URL; ?>/employee/self-rating.php" class="btn btn-primary">
            <i class="fas fa-play me-2"></i>Open Self Rating
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-briefcase me-2"></i>My Employment</h5></div>
      <div class="card-body">
        <table class="table table-borderless table-sm mb-0">
          <tr><td class="text-muted">Employee ID</td><td><strong><?php echo e(getEmployeeDisplayId($emp)); ?></strong></td></tr>
          <tr><td class="text-muted">Full Name</td><td><strong><?php echo e(trim(($emp['first_name']??'').' '.($emp['last_name']??''))); ?></strong></td></tr>
          <tr><td class="text-muted">Position</td><td><?php echo e($emp['job_title']??'—'); ?></td></tr>
          <tr><td class="text-muted">Department</td><td><?php echo e($emp['department_name']??'—'); ?></td></tr>
          <tr><td class="text-muted">Branch</td><td><?php echo e($emp['branch_name']??'—'); ?></td></tr>
          <tr><td class="text-muted">Hired</td><td><?php echo formatDate($emp['hire_date']??''); ?></td></tr>
          <tr><td class="text-muted">Status</td><td><?php echo e($emp['employment_status']??'—'); ?></td></tr>
          <tr><td class="text-muted">Type</td><td><?php echo e($emp['employment_type']??'—'); ?></td></tr>
        </table>
        <div class="mt-3">
          <a href="<?php echo BASE_URL; ?>/employee/my-employment.php" class="btn btn-outline-primary w-100">
            <i class="fas fa-eye me-2"></i>View Full Employment Info
          </a>
        </div>
      </div>
    </div>

    <div class="content-card h-100">
      <div class="card-header"><h5><i class="fas fa-user-check me-2"></i>Self-Service Notes</h5></div>
      <div class="card-body">
        <div class="alert alert-light border mb-3">
          <div class="fw-semibold mb-1">Official Employment Information</div>
          <div class="small text-muted">Use <strong>My Employment</strong> to review your official employment details.</div>
        </div>
        <div class="alert alert-light border mb-0">
          <div class="fw-semibold mb-1">360-Degree Self-Rating</div>
          <div class="small text-muted">Use <strong>Self Rating</strong> to encode and submit your employee self-evaluation.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
