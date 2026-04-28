<?php
$page_title = 'PDS Submissions';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Stats
$stats = [];
foreach (['Submitted','Under Review','Approved','Rejected','Changes Requested'] as $s) {
    $r = $conn->query("SELECT COUNT(*) c FROM employee_pds_submissions WHERE status='$s'");
    $stats[$s] = $r->fetch_assoc()['c'];
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_dept   = (int)($_GET['dept'] ?? 0);
$search        = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = []; $types = '';
if ($filter_status) { $where[] = 'ps.status=?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_dept)   { $where[] = 'e.department_id=?'; $params[] = $filter_dept; $types .= 'i'; }
if ($search) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR CAST(e.employee_id AS CHAR) LIKE ?)";
    $like = '%'.$search.'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$whereSQL = implode(' AND ', $where);

$q = $conn->prepare("
    SELECT ps.*, e.first_name, e.last_name, e.employee_id AS emp_id, e.job_title, e.profile_picture,
           d.department_name, b.branch_name,
           u.full_name AS reviewer_name
    FROM employee_pds_submissions ps
    JOIN employees e ON ps.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b    ON e.branch_id = b.branch_id
    LEFT JOIN users u       ON ps.reviewed_by = u.user_id
    WHERE $whereSQL
    ORDER BY ps.updated_at DESC
");
if ($params) { $q->bind_param($types, ...$params); }
$q->execute();
$submissions = $q->get_result();
$q->close();

$departments = $conn->query("SELECT * FROM departments WHERE is_active=1 ORDER BY department_name");

require_once '../includes/header.php';

$statusColors = ['Draft'=>'secondary','Submitted'=>'info','Under Review'=>'warning','Approved'=>'success','Rejected'=>'danger','Changes Requested'=>'danger'];
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <?php
  $statDef = [
    'Submitted'          => ['fas fa-paper-plane','#3949ab','Submitted'],
    'Under Review'       => ['fas fa-search','#f57c00','Under Review'],
    'Approved'           => ['fas fa-check-circle','#2e7d32','Approved'],
    'Rejected'           => ['fas fa-times-circle','#c62828','Rejected'],
    'Changes Requested'  => ['fas fa-exclamation-circle','#ad1457','Changes Req.'],
  ];
  foreach ($statDef as $key => [$icon, $color, $label]):
  ?>
  <div class="col-6 col-lg-2">
    <a href="?status=<?php echo urlencode($key); ?>" class="text-decoration-none">
      <div class="content-card text-center p-3" style="border-left:4px solid <?php echo $color; ?>;">
        <i class="<?php echo $icon; ?> fa-2x mb-2" style="color:<?php echo $color; ?>;"></i>
        <div class="fs-4 fw-bold"><?php echo $stats[$key]??0; ?></div>
        <small class="text-muted"><?php echo $label; ?></small>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="content-card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search by name or Employee ID..."
               value="<?php echo e($search); ?>">
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach (['Submitted','Under Review','Approved','Rejected','Changes Requested'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $filter_status===$s?'selected':''; ?>><?php echo $s; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="dept" class="form-select">
          <option value="">All Departments</option>
          <?php while($d=$departments->fetch_assoc()): ?>
            <option value="<?php echo $d['department_id']; ?>" <?php echo $filter_dept==$d['department_id']?'selected':''; ?>><?php echo e($d['department_name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search me-1"></i>Filter</button>
        <a href="?" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Submissions Table -->
<div class="content-card">
  <div class="card-header">
    <h5><i class="fas fa-inbox me-2"></i>PDS Submissions</h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Branch</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Reviewed By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row=$submissions->fetch_assoc()): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?php echo getEmployeeAvatar($row['profile_picture']??''); ?>"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <div>
                  <strong><?php echo e($row['last_name'].', '.$row['first_name']); ?></strong>
                  <small class="d-block text-muted">ID: <?php echo e($row['emp_id']); ?> &bull; <?php echo e($row['job_title']??'—'); ?></small>
                </div>
              </div>
            </td>
            <td><?php echo e($row['department_name']??'—'); ?></td>
            <td><?php echo e($row['branch_name']??'—'); ?></td>
            <td>
              <?php if($row['submitted_at']): ?>
                <?php echo formatDateTime($row['submitted_at']); ?>
              <?php else: ?>
                <span class="text-muted">Not yet submitted</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-<?php echo $statusColors[$row['status']]??'secondary'; ?>">
                <?php echo e($row['status']); ?>
              </span>
            </td>
            <td><?php echo e($row['reviewer_name']??'—'); ?></td>
            <td>
              <?php if(in_array($row['status'],['Submitted','Under Review','Rejected','Changes Requested'])): ?>
                <a href="<?php echo BASE_URL; ?>/manager/review-pds.php?id=<?php echo $row['submission_id']; ?>"
                   class="btn btn-sm btn-primary">
                  <i class="fas fa-search me-1"></i>Review
                </a>
              <?php elseif($row['status']==='Approved'): ?>
                <a href="<?php echo BASE_URL; ?>/manager/review-pds.php?id=<?php echo $row['submission_id']; ?>"
                   class="btn btn-sm btn-outline-success">
                  <i class="fas fa-eye me-1"></i>View
                </a>
              <?php else: ?>
                <span class="text-muted small">Draft</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
