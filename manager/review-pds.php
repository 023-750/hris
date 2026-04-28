<?php
/**
 * HR Manager: Review & approve/reject a PDS submission
 */
$page_title = 'Review PDS';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

$submission_id = (int)($_GET['id'] ?? 0);
if (!$submission_id) { header("Location: ".BASE_URL."/manager/pds-submissions.php"); exit; }

// Fetch submission + employee
$sq = $conn->prepare("
    SELECT ps.*, e.*, ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
           eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
           ec.telephone_number, ec.mobile_number, ec.personal_email,
           d.department_name, b.branch_name,
           u_sub.username AS submitter_username
    FROM employee_pds_submissions ps
    JOIN employees e ON ps.employee_id = e.employee_id
    LEFT JOIN employee_details ed ON e.employee_id=ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id=eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id=ec.employee_id
    LEFT JOIN departments d ON e.department_id=d.department_id
    LEFT JOIN branches b ON e.branch_id=b.branch_id
    LEFT JOIN users u_sub ON ps.submitted_by=u_sub.user_id
    WHERE ps.submission_id=?
");
$sq->bind_param("i",$submission_id);
$sq->execute();
$sub = $sq->get_result()->fetch_assoc();
$sq->close();

if (!$sub) { header("Location: ".BASE_URL."/manager/pds-submissions.php"); exit; }

$employee_id = (int)$sub['employee_id'];

// Handle action POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $hr_notes = trim($_POST['hr_notes'] ?? '');
    $mgr_id   = (int)$_SESSION['user_id'];

    if ($action === 'approve') {
        // Approve: update submission record
        $u = $conn->prepare("UPDATE employee_pds_submissions SET status='Approved', reviewed_by=?, reviewed_at=NOW(), hr_notes=?, updated_at=NOW() WHERE submission_id=?");
        $u->bind_param("isi",$mgr_id,$hr_notes,$submission_id);
        $u->execute(); $u->close();
        // Update first_login_completed on employee's user account
        $conn->query("UPDATE users SET first_login_completed=1 WHERE employee_id=$employee_id");
        logAudit($conn,$mgr_id,'APPROVE','PDS',$submission_id,"Approved PDS for employee $employee_id. Notes: $hr_notes");
        // Notify employee
        $emp_user_id = getPreferredLinkedUserId($conn, $employee_id, 'employee_portal');
        if ($emp_user_id) {
            createNotification($conn,$emp_user_id,'PDS Approved',
                'Your Personal Data Sheet has been approved by the HR Manager.',
                BASE_URL.'/employee/my-pds.php');
        }
        redirectWith(BASE_URL.'/manager/pds-submissions.php','success',$sub['first_name']."'s PDS has been approved.");

    } elseif ($action === 'request_changes') {
        if (empty($hr_notes)) {
            redirectWith(BASE_URL.'/manager/review-pds.php?id='.$submission_id,'danger','Please provide notes describing what changes are needed.');
        }
        $u = $conn->prepare("UPDATE employee_pds_submissions SET status='Changes Requested', reviewed_by=?, reviewed_at=NOW(), hr_notes=?, updated_at=NOW() WHERE submission_id=?");
        $u->bind_param("isi",$mgr_id,$hr_notes,$submission_id);
        $u->execute(); $u->close();
        logAudit($conn,$mgr_id,'REQUEST_CHANGES','PDS',$submission_id,"Requested changes: $hr_notes");
        $emp_user_id = getPreferredLinkedUserId($conn, $employee_id, 'employee_portal');
        if ($emp_user_id) {
            createNotification($conn,$emp_user_id,'PDS Changes Requested',
                'HR Manager has requested changes to your PDS: '.$hr_notes,
                BASE_URL.'/employee/pds-wizard.php');
        }
        redirectWith(BASE_URL.'/manager/pds-submissions.php','warning','Changes requested for '.$sub['first_name']."'s PDS.");

    } elseif ($action === 'reject') {
        if (empty($hr_notes)) {
            redirectWith(BASE_URL.'/manager/review-pds.php?id='.$submission_id,'danger','Please provide a reason for rejection.');
        }
        $u = $conn->prepare("UPDATE employee_pds_submissions SET status='Rejected', reviewed_by=?, reviewed_at=NOW(), hr_notes=?, updated_at=NOW() WHERE submission_id=?");
        $u->bind_param("isi",$mgr_id,$hr_notes,$submission_id);
        $u->execute(); $u->close();
        logAudit($conn,$mgr_id,'REJECT','PDS',$submission_id,"Rejected PDS: $hr_notes");
        $emp_user_id = getPreferredLinkedUserId($conn, $employee_id, 'employee_portal');
        if ($emp_user_id) {
            createNotification($conn,$emp_user_id,'PDS Rejected',
                'Your PDS was rejected. Reason: '.$hr_notes,
                BASE_URL.'/employee/pds-wizard.php');
        }
        redirectWith(BASE_URL.'/manager/pds-submissions.php','danger',$sub['first_name']."'s PDS has been rejected.");
    }

    // Mark as Under Review on first open if Submitted
    if ($sub['status'] === 'Submitted') {
        $conn->query("UPDATE employee_pds_submissions SET status='Under Review' WHERE submission_id=$submission_id");
    }
}

// If status is Submitted, mark as Under Review
if ($sub['status'] === 'Submitted') {
    $conn->query("UPDATE employee_pds_submissions SET status='Under Review' WHERE submission_id=$submission_id");
    $sub['status'] = 'Under Review';
}

// Fetch sub-tables for display
function fetchAll($conn,$table,$eid) {
    $r = $conn->query("SELECT * FROM $table WHERE employee_id=$eid ORDER BY 1");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
$addr_r = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$employee_id");
$res_a=[]; $perm_a=[];
while($a=$addr_r->fetch_assoc()) { if($a['address_type']==='Residential') $res_a=$a; else $perm_a=$a; }

$family    = fetchAll($conn,'employee_family',$employee_id);
$children  = fetchAll($conn,'employee_children',$employee_id);
$siblings  = fetchAll($conn,'employee_siblings',$employee_id);
$education = fetchAll($conn,'employee_education',$employee_id);
$work      = fetchAll($conn,'employee_work_experience',$employee_id);
$trainings = fetchAll($conn,'employee_trainings',$employee_id);
$voluntary = fetchAll($conn,'employee_voluntary_work',$employee_id);
$elig      = fetchAll($conn,'employee_eligibility',$employee_id);
$skills    = fetchAll($conn,'employee_skills',$employee_id);
$recog     = fetchAll($conn,'employee_recognitions',$employee_id);
$member    = fetchAll($conn,'employee_memberships',$employee_id);
$disc_r    = $conn->query("SELECT * FROM employee_disclosures WHERE employee_id=$employee_id LIMIT 1");
$disc      = $disc_r ? $disc_r->fetch_assoc() : [];
$refs      = fetchAll($conn,'employee_references',$employee_id);
$emcon_r   = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$employee_id LIMIT 1");
$emcon     = $emcon_r ? $emcon_r->fetch_assoc() : [];

$statusColors=['Draft'=>'secondary','Submitted'=>'info','Under Review'=>'warning','Approved'=>'success','Rejected'=>'danger','Changes Requested'=>'danger'];

require_once '../includes/header.php';
?>

<!-- Header bar -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="fas fa-search me-2 text-primary"></i>Review PDS —
      <strong><?php echo e($sub['first_name'].' '.$sub['last_name']); ?></strong>
    </h4>
    <small class="text-muted">Employee ID: <?php echo e(getEmployeeDisplayId($sub)); ?> &bull; Submitted: <?php echo formatDateTime($sub['submitted_at']??''); ?></small>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge bg-<?php echo $statusColors[$sub['status']]??'secondary'; ?> fs-6 px-3 py-2"><?php echo e($sub['status']); ?></span>
    <a href="<?php echo BASE_URL; ?>/manager/pds-submissions.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to Queue
    </a>
  </div>
</div>

<?php displayFlashMessage(); ?>

<div class="row g-4">
  <!-- LEFT: PDS Data Display -->
  <div class="col-lg-8">

    <!-- Personal Info -->
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-user me-2"></i>Personal Information</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-borderless table-sm">
              <tr><td class="text-muted fw-semibold" style="width:40%">Surname</td><td><?php echo e($sub['last_name']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">First Name</td><td><?php echo e($sub['first_name']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Middle Name</td><td><?php echo e($sub['middle_name']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Extension</td><td><?php echo e($sub['name_extension']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Date of Birth</td><td><?php echo formatDate($sub['date_of_birth']??''); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Place of Birth</td><td><?php echo e($sub['place_of_birth']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Gender</td><td><?php echo e($sub['gender']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Civil Status</td><td><?php echo e($sub['civil_status']??'—'); ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-borderless table-sm">
              <tr><td class="text-muted fw-semibold" style="width:40%">Height</td><td><?php echo e($sub['height_m']??'—'); ?> m</td></tr>
              <tr><td class="text-muted fw-semibold">Weight</td><td><?php echo e($sub['weight_kg']??'—'); ?> kg</td></tr>
              <tr><td class="text-muted fw-semibold">Blood Type</td><td><?php echo e($sub['blood_type']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Citizenship</td><td><?php echo e($sub['citizenship']??'Filipino'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">SSS No.</td><td><?php echo e($sub['sss_number']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">PhilHealth</td><td><?php echo e($sub['philhealth_number']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">Pag-IBIG</td><td><?php echo e($sub['pagibig_number']??'—'); ?></td></tr>
              <tr><td class="text-muted fw-semibold">TIN</td><td><?php echo e($sub['tin_number']??'—'); ?></td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact -->
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-phone me-2"></i>Contact Information</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4"><small class="text-muted">Telephone</small><p><?php echo e($sub['telephone_number']??'—'); ?></p></div>
          <div class="col-md-4"><small class="text-muted">Mobile</small><p><?php echo e($sub['mobile_number']??'—'); ?></p></div>
          <div class="col-md-4"><small class="text-muted">Email</small><p><?php echo e($sub['personal_email']??'—'); ?></p></div>
        </div>
        <?php if($emcon): ?>
        <hr>
        <small class="text-muted">Emergency Contact:</small>
        <p class="mb-0"><strong><?php echo e($emcon['contact_name']??'—'); ?></strong>
          (<?php echo e($emcon['relationship']??'—'); ?>) — <?php echo e($emcon['contact_number']??'—'); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Addresses -->
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-map-marker-alt me-2"></i>Addresses</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <strong>Residential</strong>
            <p class="mt-1 mb-0"><?php echo e(implode(', ', array_filter([
              $res_a['house_no']??'', $res_a['street']??'', $res_a['barangay']??'',
              $res_a['city']??'', $res_a['province']??'', $res_a['zip_code']??''
            ]))); ?></p>
          </div>
          <div class="col-md-6">
            <strong>Permanent</strong>
            <p class="mt-1 mb-0"><?php echo e(implode(', ', array_filter([
              $perm_a['house_no']??'', $perm_a['street']??'', $perm_a['barangay']??'',
              $perm_a['city']??'', $perm_a['province']??'', $perm_a['zip_code']??''
            ]))); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Education -->
    <?php if($education): ?>
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-graduation-cap me-2"></i>Educational Background</h5></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Level</th><th>School</th><th>Degree</th><th>Year Grad</th><th>Honors</th></tr></thead>
          <tbody>
            <?php foreach($education as $e): ?>
            <tr>
              <td><?php echo e($e['education_level']); ?></td>
              <td><?php echo e($e['school_name']??'—'); ?></td>
              <td><?php echo e($e['degree_course']??'—'); ?></td>
              <td><?php echo e($e['year_graduated']??'—'); ?></td>
              <td><?php echo e($e['honors_received']??'—'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Work Experience -->
    <?php if($work): ?>
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-briefcase me-2"></i>Work Experience</h5></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>From</th><th>To</th><th>Title</th><th>Company</th><th>Salary</th></tr></thead>
          <tbody>
            <?php foreach($work as $w): ?>
            <tr>
              <td><?php echo formatDate($w['date_from']??''); ?></td>
              <td><?php echo formatDate($w['date_to']??''); ?></td>
              <td><?php echo e($w['job_title']??'—'); ?></td>
              <td><?php echo e($w['company_name']??'—'); ?></td>
              <td><?php echo number_format($w['monthly_salary']??0,2); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Skills -->
    <?php if($skills || $recog || $member): ?>
    <div class="content-card mb-4">
      <div class="card-header"><h5><i class="fas fa-star me-2"></i>Skills, Recognition & Memberships</h5></div>
      <div class="card-body">
        <?php if($skills): ?>
          <strong>Skills:</strong>
          <div class="d-flex flex-wrap gap-1 mb-2">
            <?php foreach($skills as $sk): ?><span class="badge bg-light text-dark border"><?php echo e($sk['skill_name']); ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if($recog): ?>
          <strong>Recognitions:</strong>
          <ul class="mb-2"><?php foreach($recog as $r): ?><li><?php echo e($r['recognition_title']); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if($member): ?>
          <strong>Memberships:</strong>
          <ul><?php foreach($member as $m): ?><li><?php echo e($m['organization_name']); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-8 -->

  <!-- RIGHT: HR Action Panel -->
  <div class="col-lg-4">
    <div class="content-card sticky-top" style="top:80px;">
      <div class="card-header" style="background:linear-gradient(135deg,#1a237e,#3949ab);color:#fff;">
        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>HR Decision</h5>
      </div>
      <div class="card-body">

        <?php if($sub['status']==='Approved'): ?>
          <div class="alert alert-success text-center">
            <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
            <strong>This PDS has been Approved</strong><br>
            <small>on <?php echo formatDateTime($sub['reviewed_at']??''); ?></small>
          </div>

        <?php elseif(in_array($sub['status'],['Submitted','Under Review','Rejected','Changes Requested'])): ?>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label fw-semibold">HR Notes / Feedback</label>
              <textarea name="hr_notes" id="hr_notes" class="form-control" rows="5"
                placeholder="Leave notes for the employee (required for rejection/changes)..."><?php echo e($sub['hr_notes']??''); ?></textarea>
            </div>
            <div class="d-grid gap-2">
              <button type="submit" name="action" value="approve" class="btn btn-success"
                onclick="return confirm('Approve this PDS? This will be saved to the employee record.')">
                <i class="fas fa-check-circle me-2"></i>Approve PDS
              </button>
              <button type="submit" name="action" value="request_changes" class="btn btn-warning"
                onclick="return confirm('Request changes? The employee will be notified to edit and resubmit.')">
                <i class="fas fa-exclamation-circle me-2"></i>Request Changes
              </button>
              <button type="submit" name="action" value="reject" class="btn btn-danger"
                onclick="return confirm('Reject this PDS? Please ensure HR notes explain the reason.')">
                <i class="fas fa-times-circle me-2"></i>Reject
              </button>
            </div>
          </form>
        <?php else: ?>
          <p class="text-muted text-center">This submission is a Draft and has not been submitted for review yet.</p>
        <?php endif; ?>

        <?php if(!empty($sub['hr_notes']) && $sub['status']!=='Approved'): ?>
          <hr>
          <small class="text-muted">Previous Notes:</small>
          <p class="mt-1 mb-0 small"><?php echo nl2br(e($sub['hr_notes'])); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick employee info -->
    <div class="content-card mt-4">
      <div class="card-header"><h5><i class="fas fa-id-badge me-2"></i>Employee Details</h5></div>
      <div class="card-body">
        <div class="text-center mb-3">
          <img src="<?php echo getEmployeeAvatar($sub['profile_picture']??''); ?>?v=<?php echo time();?>"
               style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid #dee2e6;">
          <div class="mt-2 fw-bold"><?php echo e($sub['first_name'].' '.$sub['last_name']); ?></div>
          <small class="text-muted"><?php echo e($sub['job_title']??'—'); ?></small>
        </div>
        <table class="table table-borderless table-sm">
          <tr><td class="text-muted">Emp. ID</td><td><?php echo e($employee_id); ?></td></tr>
          <tr><td class="text-muted">Department</td><td><?php echo e($sub['department_name']??'—'); ?></td></tr>
          <tr><td class="text-muted">Branch</td><td><?php echo e($sub['branch_name']??'—'); ?></td></tr>
          <tr><td class="text-muted">Hired</td><td><?php echo formatDate($sub['hire_date']??''); ?></td></tr>
        </table>
        <a href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $employee_id; ?>"
           class="btn btn-outline-primary btn-sm w-100">
          <i class="fas fa-external-link-alt me-1"></i>View Full Profile
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
