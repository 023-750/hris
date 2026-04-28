<?php
$page_title = 'View Employee';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eid <= 0) redirectWith(BASE_URL . '/staff/search-employees.php', 'danger', 'Invalid employee ID.');

// Fetch employee details (no branch restriction for staff — read-only access to all)
$stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name,
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    WHERE e.employee_id = ? AND e.is_active = 1");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) redirectWith(BASE_URL . '/staff/search-employees.php', 'danger', 'Employee not found.');

// Load Addresses
$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();

$addr_fields = ['house_no', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip_code'];
foreach ($addr_fields as $f) {
    $emp['res_' . $f] = $res_addr[$f] ?? '';
    $emp['perm_' . $f] = $perm_addr[$f] ?? '';
}

// Load Emergency Contacts
$emerg = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid LIMIT 1")->fetch_assoc();
if ($emerg) {
    $emp['emergency_contact_name'] = $emerg['contact_name'];
    $emp['emergency_contact_relationship'] = $emerg['relationship'];
    $emp['emergency_contact_number'] = $emerg['contact_number'];
} else {
    $emp['emergency_contact_name'] = $emp['emergency_contact_relationship'] = $emp['emergency_contact_number'] = '';
}

// Load Education, Work, Skills, Trainings (Read Only)
$education = $conn->query("SELECT * FROM employee_education WHERE employee_id=$eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$work = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id=$eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$skills = $conn->query("SELECT * FROM employee_skills WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$trainings = $conn->query("SELECT * FROM employee_trainings WHERE employee_id=$eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';

// Helper for UI
function field($label, $value) {
    $val = !empty($value) ? e($value) : '<span class="text-muted">N/A</span>';
    return "<div class='row mb-2'><div class='col-sm-4 text-muted small'>$label</div><div class='col-sm-8 fw-semibold small'>$val</div></div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0"><i class="fas fa-lock me-1"></i> Employee Profile (Read Only)</p>
    <a href="<?php echo BASE_URL; ?>/staff/search-employees.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Search</a>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-3 mb-4">
        <div class="content-card h-100 text-center">
            <div class="card-body py-4">
                <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" class="rounded-circle img-thumbnail shadow-sm mb-3" style="width:120px;height:120px;object-fit:cover;">
                <h5 class="mb-1"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></h5>
                <p class="text-muted mb-2"><?php echo e($emp['job_title']); ?></p>
                <span class="badge bg-success px-3 py-2">Active</span>
                <hr class="my-3">
                <div class="text-start small">
                    <p class="mb-1"><i class="fas fa-envelope text-muted me-2" style="width:16px;"></i><?php echo e($emp['personal_email'] ?: 'N/A'); ?></p>
                    <p class="mb-1"><i class="fas fa-phone text-muted me-2" style="width:16px;"></i><?php echo e($emp['mobile_number'] ?: 'N/A'); ?></p>
                    <p class="mb-1"><i class="fas fa-building text-muted me-2" style="width:16px;"></i><?php echo e($emp['branch_name'] ?: 'N/A'); ?></p>
                    <p class="mb-0"><i class="fas fa-sitemap text-muted me-2" style="width:16px;"></i><?php echo e($emp['department_name'] ?: 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Tabs -->
    <div class="col-md-9 mb-4">
        <div class="content-card h-100">
            <div class="card-body p-4">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item"><button class="nav-link active fw-bold small" data-bs-toggle="tab" data-bs-target="#t1" type="button"><i class="fas fa-user me-1"></i>Personal</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#t2" type="button"><i class="fas fa-briefcase me-1"></i>Employment</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#t3" type="button"><i class="fas fa-graduation-cap me-1"></i>Education</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#t4" type="button"><i class="fas fa-history me-1"></i>Work History</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#t5" type="button"><i class="fas fa-certificate me-1"></i>Skills & Training</button></li>
                </ul>
                <div class="tab-content">
                    <!-- Personal Tab -->
                    <div class="tab-pane fade show active" id="t1">
                        <?php
                        echo field('Full Name', $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']);
                        echo field('Date of Birth', formatDate($emp['date_of_birth']));
                        echo field('Place of Birth', $emp['place_of_birth']);
                        echo field('Gender', $emp['gender']);
                        echo field('Civil Status', $emp['civil_status']);
                        echo field('Citizenship', $emp['citizenship']);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold">Contact & Address</h6>
                        <?php
                        echo field('Email', $emp['personal_email']);
                        echo field('Mobile', $emp['mobile_number']);
                        echo field('Telephone', $emp['telephone_number']);
                        $resAddr = trim(implode(', ', array_filter([$emp['res_house_no'], $emp['res_street'], $emp['res_barangay'], $emp['res_city'], $emp['res_province']])));
                        echo field('Residential Address', $resAddr);
                        $permAddr = trim(implode(', ', array_filter([$emp['perm_house_no'], $emp['perm_street'], $emp['perm_barangay'], $emp['perm_city'], $emp['perm_province']])));
                        echo field('Permanent Address', $permAddr);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold">Emergency Contact</h6>
                        <?php
                        echo field('Name', $emp['emergency_contact_name']);
                        echo field('Relationship', $emp['emergency_contact_relationship']);
                        echo field('Number', $emp['emergency_contact_number']);
                        ?>
                    </div>

                    <!-- Employment Tab -->
                    <div class="tab-pane fade" id="t2">
                        <?php
                        echo field('Employee ID', $emp['employee_id']);
                        echo field('Job Title', $emp['job_title']);
                        echo field('Department', $emp['department_name']);
                        echo field('Branch', $emp['branch_name']);
                        echo field('Employment Status', $emp['employment_status']);
                        echo field('Employment Type', $emp['employment_type']);
                        echo field('Date Hired', formatDate($emp['hire_date']));
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold">Government IDs</h6>
                        <?php
                        echo field('SSS Number', $emp['sss_number'] ?? '');
                        echo field('PhilHealth Number', $emp['philhealth_number'] ?? '');
                        echo field('Pag-IBIG Number', $emp['pagibig_number'] ?? '');
                        echo field('TIN Number', $emp['tin_number'] ?? '');
                        ?>
                    </div>

                    <!-- Education Tab -->
                    <div class="tab-pane fade" id="t3">
                        <?php if (empty($education)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-graduation-cap fa-2x mb-2 opacity-25 d-block"></i>
                                <p>No education records available.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-sm table-bordered small">
                                <thead><tr><th>Level</th><th>School</th><th>Degree</th><th>Year</th></tr></thead>
                                <tbody>
                                    <?php foreach($education as $ed): ?>
                                        <tr><td><?php echo e($ed['education_level']); ?></td><td><?php echo e($ed['school_name']); ?></td><td><?php echo e($ed['degree_course']); ?></td><td><?php echo e($ed['year_graduated']); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Work History Tab -->
                    <div class="tab-pane fade" id="t4">
                        <?php if (empty($work)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-briefcase fa-2x mb-2 opacity-25 d-block"></i>
                                <p>No work history records available.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-sm table-bordered small">
                                <thead><tr><th>Period</th><th>Position</th><th>Company</th></tr></thead>
                                <tbody>
                                    <?php foreach($work as $w): ?>
                                        <tr><td><?php echo formatDate($w['date_from'], 'Y') . " - " . ($w['date_to'] ? formatDate($w['date_to'], 'Y') : 'Present'); ?></td><td><?php echo e($w['job_title']); ?></td><td><?php echo e($w['company_name']); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Skills & Training Tab -->
                    <div class="tab-pane fade" id="t5">
                        <h6 class="text-primary small fw-bold">Skills</h6>
                        <div class="mb-3">
                            <?php if (empty($skills)): ?>
                                <span class="text-muted small">No skills recorded.</span>
                            <?php else: ?>
                                <?php foreach($skills as $sk) echo '<span class="badge bg-info me-1 mb-1">'.e($sk['skill_name']).'</span>'; ?>
                            <?php endif; ?>
                        </div>
                        <h6 class="text-primary small fw-bold">Trainings & Seminars</h6>
                        <?php if (empty($trainings)): ?>
                            <span class="text-muted small">No training records available.</span>
                        <?php else: ?>
                            <ul class="small">
                                <?php foreach($trainings as $tr) echo "<li>" . e($tr['training_title']) . " (" . formatDate($tr['date_from']) . ")</li>"; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link { color: var(--text-muted); border: none; border-bottom: 2px solid transparent; }
.nav-tabs .nav-link.active { color: var(--primary-blue); background: transparent; border-bottom: 2px solid var(--primary-blue); }
</style>

<?php require_once '../includes/footer.php'; ?>
