<?php
$page_title = 'My Employment';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$employee_id = (int)($_SESSION['employee_id'] ?? 0);

$emp_stmt = $conn->prepare("
    SELECT e.*,
           d.department_name,
           b.branch_name,
           ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
           ec.mobile_number, ec.telephone_number, ec.personal_email,
           eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    WHERE e.employee_id = ?
    LIMIT 1
");
$emp_stmt->bind_param("i", $employee_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc() ?? [];
$emp_stmt->close();

if (!$emp) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'No employment record was found for your account.');
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">My Employment</h4>
        <small class="text-muted">View your official employment and profile information</small>
    </div>
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="pds-profile-header fadeup">
    <img src="<?php echo getEmployeeAvatar($emp['profile_picture'] ?? ''); ?>?v=<?php echo time(); ?>" alt="Profile" class="avatar-large">
    <div class="info">
        <h2><?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></h2>
        <p><?php echo e($emp['job_title'] ?? '—'); ?> &bull; <?php echo e($emp['department_name'] ?? '—'); ?></p>
        <div class="mt-2 d-flex flex-wrap gap-2">
            <span class="badge bg-white text-dark py-1 px-2"><i class="fas fa-building me-1 text-primary"></i><?php echo e($emp['branch_name'] ?? 'N/A'); ?></span>
            <span class="badge bg-white text-dark py-1 px-2"><i class="fas fa-calendar-alt me-1 text-primary"></i>Hired: <?php echo formatDate($emp['hire_date'] ?? ''); ?></span>
            <span class="badge bg-white text-dark py-1 px-2"><i class="fas fa-user-check me-1 text-primary"></i><?php echo e($emp['employment_status'] ?? '—'); ?></span>
        </div>
    </div>
</div>

<div class="alert alert-info py-2 mb-4" style="font-size:0.9rem;">
    <i class="fas fa-info-circle me-2"></i>
    This page is read-only. If any official employment detail is incorrect, please contact HR.
</div>

<div class="pds-info-grid">
    <div class="pds-card fadeup-1">
        <div class="pds-card-title"><i class="fas fa-briefcase"></i>Employment Details</div>
        <div class="pds-data-row"><span class="label">Employee ID</span><span class="value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></div>
        <div class="pds-data-row"><span class="label">Full Name</span><span class="value"><?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?></span></div>
        <div class="pds-data-row"><span class="label">Position</span><span class="value"><?php echo e($emp['job_title'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Department</span><span class="value"><?php echo e($emp['department_name'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Branch</span><span class="value"><?php echo e($emp['branch_name'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Hire Date</span><span class="value"><?php echo formatDate($emp['hire_date'] ?? ''); ?></span></div>
        <div class="pds-data-row"><span class="label">Employment Status</span><span class="value"><?php echo e($emp['employment_status'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Employment Type</span><span class="value"><?php echo e($emp['employment_type'] ?? '—'); ?></span></div>
    </div>

    <div class="pds-card fadeup-2">
        <div class="pds-card-title"><i class="fas fa-phone"></i>Contact Information</div>
        <div class="pds-data-row"><span class="label">Mobile Number</span><span class="value"><?php echo e($emp['mobile_number'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Telephone</span><span class="value"><?php echo e($emp['telephone_number'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Personal Email</span><span class="value"><?php echo e($emp['personal_email'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Citizenship</span><span class="value"><?php echo e($emp['citizenship'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Civil Status</span><span class="value"><?php echo e($emp['civil_status'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Date of Birth</span><span class="value"><?php echo formatDate($emp['date_of_birth'] ?? ''); ?></span></div>
    </div>

    <div class="pds-card fadeup-3">
        <div class="pds-card-title"><i class="fas fa-id-badge"></i>Government IDs</div>
        <div class="pds-data-row"><span class="label">SSS Number</span><span class="value"><?php echo e($emp['sss_number'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">PhilHealth Number</span><span class="value"><?php echo e($emp['philhealth_number'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Pag-IBIG Number</span><span class="value"><?php echo e($emp['pagibig_number'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">TIN Number</span><span class="value"><?php echo e($emp['tin_number'] ?? '—'); ?></span></div>
    </div>

    <div class="pds-card fadeup-4">
        <div class="pds-card-title"><i class="fas fa-user"></i>Profile Summary</div>
        <div class="pds-data-row"><span class="label">Gender</span><span class="value"><?php echo e($emp['gender'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Place of Birth</span><span class="value"><?php echo e($emp['place_of_birth'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Height</span><span class="value"><?php echo !empty($emp['height_m']) ? e($emp['height_m']) . ' m' : '—'; ?></span></div>
        <div class="pds-data-row"><span class="label">Weight</span><span class="value"><?php echo !empty($emp['weight_kg']) ? e($emp['weight_kg']) . ' kg' : '—'; ?></span></div>
        <div class="pds-data-row"><span class="label">Blood Type</span><span class="value"><?php echo e($emp['blood_type'] ?? '—'); ?></span></div>
        <div class="pds-data-row"><span class="label">Account Status</span><span class="value"><?php echo !empty($emp['is_active']) ? 'Active' : 'Inactive'; ?></span></div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
