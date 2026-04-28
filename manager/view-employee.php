<?php
$page_title = 'View Employee';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'System Administrator']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($eid <= 0)
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Invalid employee ID.');

$stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name,
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email,
    edi.is_related_to_company, edi.related_details, edi.has_admin_offense, edi.admin_offense_details,
    edi.has_criminal_charge, edi.criminal_charge_details, edi.has_criminal_conviction, edi.criminal_conviction_details,
    edi.has_been_separated, edi.separation_details, edi.is_pwd, edi.pwd_details,
    edi.is_solo_parent, edi.solo_parent_details, edi.has_recent_hospital, edi.hospital_details,
    edi.has_current_treatment, edi.treatment_details
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN employee_disclosures edi ON e.employee_id = edi.employee_id
    WHERE e.employee_id = ?");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$emp)
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Employee not found.');

// Strictly prevent viewing of System Admin profiles in the employee management module
$adminCheck = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND role = 'Admin'");
$adminCheck->bind_param("i", $eid);
$adminCheck->execute();
if ($adminCheck->get_result()->num_rows > 0) {
    $adminCheck->close();
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Access denied to system administrator profiles.');
}
$adminCheck->close();

// Map contacts/email to legacy fields for UI compatibility
$emp['email'] = $emp['personal_email'];
$emp['contact_number'] = $emp['mobile_number'];

// Load Residential and Permanent Addresses
$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();

// Flatten address fields into $emp for legacy UI compatibility
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

// Initialize family fields to avoid warnings
$family_presets = ['spouse', 'father', 'mother_maiden'];
foreach ($family_presets as $pf) {
    $emp[$pf . '_surname'] = $emp[$pf . '_first_name'] = $emp[$pf . '_middle_name'] = $emp[$pf . '_occupation'] = '';
    if ($pf !== 'mother_maiden')
        $emp[$pf . '_name_ext'] = '';
}

// Load Family (Spouse, Father, Mother)
$family = $conn->query("SELECT * FROM employee_family WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
foreach ($family as $member) {
    $pre = strtolower($member['member_type']);
    if ($pre === 'mother')
        $pre = 'mother_maiden'; // legacy field name in UI

    $emp[$pre . '_surname'] = $member['surname'];
    $emp[$pre . '_first_name'] = $member['first_name'];
    $emp[$pre . '_middle_name'] = $member['middle_name'];
    if (isset($member['name_extension']))
        $emp[$pre . '_name_ext'] = $member['name_extension'];
    $emp[$pre . '_occupation'] = $member['occupation'];
}

// Load child data
$children = $conn->query("SELECT * FROM employee_children WHERE employee_id=$eid ORDER BY child_id")->fetch_all(MYSQLI_ASSOC);
$siblings = $conn->query("SELECT * FROM employee_siblings WHERE employee_id=$eid ORDER BY sibling_id")->fetch_all(MYSQLI_ASSOC);
$education = $conn->query("SELECT * FROM employee_education WHERE employee_id=$eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$work = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id=$eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$trainings = $conn->query("SELECT * FROM employee_trainings WHERE employee_id=$eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);
$voluntary = $conn->query("SELECT * FROM employee_voluntary_work WHERE employee_id=$eid ORDER BY voluntary_id")->fetch_all(MYSQLI_ASSOC);
$eligibility = $conn->query("SELECT * FROM employee_eligibility WHERE employee_id=$eid ORDER BY eligibility_id")->fetch_all(MYSQLI_ASSOC);
$skills = $conn->query("SELECT * FROM employee_skills WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$recognitions = $conn->query("SELECT * FROM employee_recognitions WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$memberships = $conn->query("SELECT * FROM employee_memberships WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$refs = $conn->query("SELECT * FROM employee_references WHERE employee_id=$eid ORDER BY reference_id")->fetch_all(MYSQLI_ASSOC);

// Load Career Movements
$career_movements = $conn->query("
    SELECT cm.*,
        b1.branch_name AS prev_branch_name,
        b2.branch_name AS new_branch_name,
        u1.full_name AS logged_by_name,
        u2.full_name AS approved_by_name
    FROM career_movements cm
    LEFT JOIN branches b1 ON cm.previous_branch_id = b1.branch_id
    LEFT JOIN branches b2 ON cm.new_branch_id = b2.branch_id
    LEFT JOIN users u1 ON cm.logged_by = u1.user_id
    LEFT JOIN users u2 ON cm.approved_by = u2.user_id
    WHERE cm.employee_id = $eid
    ORDER BY cm.effective_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Load Latest Career Goals from Evaluations
$career_goals = $conn->query("
    SELECT current_position, months_in_position, desired_position, target_date, career_growth_details, approved_date
    FROM evaluations
    WHERE employee_id = $eid AND status = 'Approved'
    ORDER BY approved_date DESC LIMIT 1
")->fetch_assoc();

require_once '../includes/header.php';

// Helper
function field($label, $value, $escape = true)
{
    $val = !empty($value) ? ($escape ? e($value) : $value) : '<span class="text-muted">N/A</span>';
    return "<div class='row mb-2'><div class='col-sm-4 text-muted small'>$label</div><div class='col-sm-8 fw-semibold small'>$val</div></div>";
}
function yn($v)
{
    return $v ? '<span class="badge bg-warning text-dark">Yes</span>' : '<span class="badge bg-secondary">No</span>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Employee Personal Data Sheet</p>
    <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="btn btn-secondary"><i
            class="fas fa-arrow-left me-2"></i>Back</a>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-3 mb-4">
        <div class="content-card h-100 text-center">
            <div class="card-body py-4">
                <div class="position-relative d-inline-block cursor-pointer"
                    onclick="viewFullImage('<?php echo getEmployeeAvatar($emp['profile_picture']); ?>', '<?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>')">
                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>"
                        class="rounded-circle img-thumbnail shadow-sm mb-3 hover-zoom"
                        style="width:120px;height:120px;object-fit:cover; transition: transform 0.2s;">
                    <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-1 mb-3 me-1 border border-white"
                        style="width: 25px; height: 25px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
                <h5 class="mb-1">
                    <?php echo e($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'] . ($emp['name_extension'] ? ' ' . $emp['name_extension'] : '')); ?>
                </h5>
                <p class="text-muted mb-2"><?php echo e($emp['job_title']); ?></p>
                <p class="text-muted small mb-2">Employee ID: <?php echo e(getEmployeeDisplayId($emp)); ?></p>
                <div class="d-flex justify-content-center gap-2 mb-2">
                    <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?> px-2 py-1"><?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    <span class="badge bg-primary px-2 py-1"><?php echo e($emp['employment_status']); ?></span>
                </div>
                <?php if (in_array($emp['employment_status'], ['Probationary', 'Contractual'])): ?>
                    <div class="alert alert-info py-2 px-2 mt-2 mb-0 border-start border-4 border-info shadow-sm" style="font-size: 0.75rem;">
                        <div class="fw-bold text-info mb-1"><i class="fas fa-clock me-1"></i>Contract Period</div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Start:</span>
                            <span class="fw-bold"><?php echo formatDate($emp['contract_start_date']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">End:</span>
                            <span class="fw-bold"><?php echo formatDate($emp['contract_end_date']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <hr class="my-3">
                <div class="text-start small">
                    <p class="mb-1"><i class="fas fa-envelope text-muted me-2"
                            style="width:16px;"></i><?php echo e($emp['email'] ?: 'N/A'); ?></p>
                    <p class="mb-1"><i class="fas fa-phone text-muted me-2"
                            style="width:16px;"></i><?php echo e($emp['contact_number'] ?: 'N/A'); ?></p>
                    <p class="mb-1"><i class="fas fa-building text-muted me-2"
                            style="width:16px;"></i><?php echo e($emp['branch_name'] ?: 'N/A'); ?></p>
                    <p class="mb-0"><i class="fas fa-calendar text-muted me-2"
                            style="width:16px;"></i><?php echo formatDate($emp['hire_date']); ?></p>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <a href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $eid; ?>"
                    class="btn btn-primary w-100"><i class="fas fa-edit me-2"></i>Edit</a>
            </div>
        </div>
    </div>

    <!-- Detail Tabs -->
    <div class="col-md-9 mb-4">
        <div class="content-card h-100">
            <div class="card-body p-4">
                <ul class="nav nav-tabs mb-4" role="tablist" style="flex-wrap:wrap;">
                    <li class="nav-item"><button class="nav-link active fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t1" type="button"><i class="fas fa-user me-1"></i>Personal</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t2" type="button"><i class="fas fa-heart me-1"></i>Family</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t3" type="button"><i
                                class="fas fa-graduation-cap me-1"></i>Education</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t4" type="button"><i class="fas fa-briefcase me-1"></i>Work</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t5" type="button"><i class="fas fa-certificate me-1"></i>Training &
                            More</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t6" type="button"><i
                                class="fas fa-clipboard-list me-1"></i>Disclosures</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t7" type="button"><i class="fas fa-id-card me-1"></i>Gov IDs</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#t8" type="button"><i class="fas fa-route me-1"></i>Career
                            <?php if (!empty($career_movements)): ?><span
                                    class="badge bg-primary ms-1"><?php echo count($career_movements); ?></span><?php endif; ?>
                        </button></li>
                </ul>
                <div class="tab-content">

                    <!-- Personal Tab -->
                    <div class="tab-pane fade show active" id="t1">
                        <?php
                        echo field('Surname', $emp['last_name']);
                        echo field('First Name', $emp['first_name']);
                        echo field('Middle Name', $emp['middle_name']);
                        echo field('Name Extension', $emp['name_extension']);
                        echo field('Date of Birth', $emp['date_of_birth'] ? formatDate($emp['date_of_birth']) : '');
                        echo field('Place of Birth', $emp['place_of_birth']);
                        echo field('Gender', $emp['gender']);
                        echo field('Civil Status', $emp['civil_status']);
                        echo field('Height', $emp['height_m'] ? $emp['height_m'] . ' m' : '');
                        echo field('Weight', $emp['weight_kg'] ? $emp['weight_kg'] . ' kg' : '');
                        echo field('Blood Type', $emp['blood_type']);
                        echo field('Citizenship', $emp['citizenship']);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold"><i
                                class="fas fa-map-marker-alt me-1"></i>Residential Address</h6>
                        <?php
                        $resAddr = trim(implode(', ', array_filter([$emp['res_house_no'], $emp['res_street'], $emp['res_subdivision'], $emp['res_barangay'], $emp['res_city'], $emp['res_province'], $emp['res_zip_code']])));
                        echo field('Address', $resAddr);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-home me-1"></i>Permanent
                            Address</h6>
                        <?php
                        $permAddr = trim(implode(', ', array_filter([$emp['perm_house_no'], $emp['perm_street'], $emp['perm_subdivision'], $emp['perm_barangay'], $emp['perm_city'], $emp['perm_province'], $emp['perm_zip_code']])));
                        echo field('Address', $permAddr);
                        echo field('Telephone', $emp['telephone_number']);
                        echo field('Mobile', $emp['contact_number']);
                        echo field('Email', $emp['email']);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-heartbeat me-1"></i>Emergency
                            Contact</h6>
                        <?php
                        echo field('Name', $emp['emergency_contact_name']);
                        echo field('Relationship', $emp['emergency_contact_relationship']);
                        echo field('Number', $emp['emergency_contact_number']);
                        ?>
                    </div>

                    <!-- Family Tab -->
                    <div class="tab-pane fade" id="t2">
                        <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-heart me-1"></i>Spouse</h6>
                        <?php
                        $spouseName = trim(($emp['spouse_first_name'] ?? '') . ' ' . ($emp['spouse_middle_name'] ?? '') . ' ' . ($emp['spouse_surname'] ?? '') . ($emp['spouse_name_ext'] ? ' ' . $emp['spouse_name_ext'] : ''));
                        echo field('Name', $spouseName);
                        echo field('Occupation', $emp['spouse_occupation']);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-male me-1"></i>Father</h6>
                        <?php
                        $fatherName = trim(($emp['father_first_name'] ?? '') . ' ' . ($emp['father_middle_name'] ?? '') . ' ' . ($emp['father_surname'] ?? '') . ($emp['father_name_ext'] ? ' ' . $emp['father_name_ext'] : ''));
                        echo field('Name', $fatherName);
                        echo field('Occupation', $emp['father_occupation']);
                        ?>
                        <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-female me-1"></i>Mother
                            (Maiden)</h6>
                        <?php
                        $motherName = trim(($emp['mother_first_name'] ?? '') . ' ' . ($emp['mother_middle_name'] ?? '') . ' ' . ($emp['mother_maiden_surname'] ?? ''));
                        echo field('Name', $motherName);
                        echo field('Occupation', $emp['mother_maiden_occupation'] ?? '');
                        ?>

                        <?php if (!empty($children)): ?>
                            <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-child me-1"></i>Children
                                (<?php echo count($children); ?>)</h6>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Date of Birth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($children as $ch): ?>
                                        <tr>
                                            <td><?php echo e(trim($ch['first_name'] . ' ' . $ch['middle_name'] . ' ' . $ch['surname'])); ?>
                                            </td>
                                            <td><?php echo $ch['date_of_birth'] ? formatDate($ch['date_of_birth']) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($siblings)): ?>
                            <h6 class="mt-3 mb-2 text-primary small fw-bold"><i class="fas fa-users me-1"></i>Siblings
                                (<?php echo count($siblings); ?>)</h6>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Date of Birth</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siblings as $sb): ?>
                                        <tr>
                                            <td><?php echo e(trim($sb['first_name'] . ' ' . $sb['middle_name'] . ' ' . $sb['surname'])); ?>
                                            </td>
                                            <td><?php echo $sb['date_of_birth'] ? formatDate($sb['date_of_birth']) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Education Tab -->
                    <div class="tab-pane fade" id="t3">
                        <?php if (!empty($education)): ?>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>School</th>
                                        <th>Degree</th>
                                        <th>Period</th>
                                        <th>Year Grad</th>
                                        <th>Honors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($education as $ed): ?>
                                        <tr>
                                            <td><span class="badge bg-info"><?php echo e($ed['education_level']); ?></span></td>
                                            <td><?php echo e($ed['school_name']); ?></td>
                                            <td><?php echo e($ed['degree_course'] ?: '—'); ?></td>
                                            <td><?php echo ($ed['period_from'] ? formatDate($ed['period_from'], 'Y') : '') . ' - ' . ($ed['period_to'] ? formatDate($ed['period_to'], 'Y') : ''); ?>
                                            </td>
                                            <td><?php echo e($ed['year_graduated'] ?: '—'); ?></td>
                                            <td><?php echo e($ed['honors_received'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-graduation-cap d-block"></i>
                                <p>No education records</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Work Tab -->
                    <div class="tab-pane fade" id="t4">
                        <?php if (!empty($work)): ?>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Title</th>
                                        <th>Company</th>
                                        <th>Salary</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($work as $w): ?>
                                        <tr>
                                            <td><?php echo ($w['date_from'] ? formatDate($w['date_from'], 'M Y') : '') . ' - ' . ($w['date_to'] ? formatDate($w['date_to'], 'M Y') : 'Present'); ?>
                                            </td>
                                            <td><?php echo e($w['job_title']); ?></td>
                                            <td><?php echo e($w['company_name']); ?></td>
                                            <td><?php echo $w['monthly_salary'] ? '₱' . number_format($w['monthly_salary'], 2) : '—'; ?>
                                            </td>
                                            <td><?php echo e($w['appointment_status'] ?: '—'); ?></td>
                                            <td><?php echo e($w['reason_for_leaving'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-briefcase d-block"></i>
                                <p>No work experience records</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Training, Voluntary, Eligibility, Skills Tab -->
                    <div class="tab-pane fade" id="t5">
                        <?php if (!empty($trainings)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i
                                    class="fas fa-chalkboard-teacher me-1"></i>Training Programs</h6>
                            <table class="table table-sm table-bordered mb-4">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Hours</th>
                                        <th>Conducted By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trainings as $t): ?>
                                        <tr>
                                            <td><?php echo ($t['date_from'] ? formatDate($t['date_from']) : '') . ' - ' . ($t['date_to'] ? formatDate($t['date_to']) : ''); ?>
                                            </td>
                                            <td><?php echo e($t['training_title']); ?></td>
                                            <td><?php echo e($t['training_type'] ?: '—'); ?></td>
                                            <td><?php echo e($t['no_of_hours'] ?: '—'); ?></td>
                                            <td><?php echo e($t['conducted_by'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($voluntary)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-hands-helping me-1"></i>Voluntary
                                Work</h6>
                            <table class="table table-sm table-bordered mb-4">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Organization</th>
                                        <th>Hours</th>
                                        <th>Position</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voluntary as $vol): ?>
                                        <tr>
                                            <td><?php echo ($vol['date_from'] ? formatDate($vol['date_from']) : '') . ' - ' . ($vol['date_to'] ? formatDate($vol['date_to']) : ''); ?>
                                            </td>
                                            <td><?php echo e($vol['organization_name']); ?></td>
                                            <td><?php echo e($vol['no_of_hours'] ?: '—'); ?></td>
                                            <td><?php echo e($vol['position_nature'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($eligibility)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-certificate me-1"></i>Eligibility /
                                Licenses</h6>
                            <table class="table table-sm table-bordered mb-4">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>License No.</th>
                                        <th>Validity</th>
                                        <th>Exam Date</th>
                                        <th>Place</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eligibility as $el): ?>
                                        <tr>
                                            <td><?php echo e($el['license_title']); ?></td>
                                            <td><?php echo e($el['license_number'] ?: '—'); ?></td>
                                            <td><?php echo ($el['date_from'] ? formatDate($el['date_from']) : '') . ' - ' . ($el['date_to'] ? formatDate($el['date_to']) : ''); ?>
                                            </td>
                                            <td><?php echo $el['date_of_exam'] ? formatDate($el['date_of_exam']) : '—'; ?></td>
                                            <td><?php echo e($el['place_of_exam'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($skills)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-star me-1"></i>Skills & Hobbies
                            </h6>
                            <div class="mb-3"><?php foreach ($skills as $sk): ?><span
                                        class="badge bg-info me-1 mb-1"><?php echo e($sk['skill_name']); ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($recognitions)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-award me-1"></i>Recognitions</h6>
                            <div class="mb-3"><?php foreach ($recognitions as $rc): ?><span
                                        class="badge bg-warning text-dark me-1 mb-1"><?php echo e($rc['recognition_title']); ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($memberships)): ?>
                            <h6 class="mb-2 text-primary small fw-bold"><i class="fas fa-users-cog me-1"></i>Memberships
                            </h6>
                            <div class="mb-3"><?php foreach ($memberships as $mb): ?><span
                                        class="badge bg-secondary me-1 mb-1"><?php echo e($mb['organization_name']); ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($trainings) && empty($voluntary) && empty($eligibility) && empty($skills) && empty($recognitions) && empty($memberships)): ?>
                            <div class="empty-state"><i class="fas fa-certificate d-block"></i>
                                <p>No training, eligibility, or skills records</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Disclosures Tab -->
                    <div class="tab-pane fade" id="t6">
                        <?php
                        $discList = [
                            ['is_related_to_company', 'related_details', 'Related to company employee (3rd degree)'],
                            ['has_admin_offense', 'admin_offense_details', 'Found guilty of admin offense'],
                            ['has_criminal_charge', 'criminal_charge_details', 'Criminally charged before court'],
                            ['has_criminal_conviction', 'criminal_conviction_details', 'Convicted of crime'],
                            ['has_been_separated', 'separation_details', 'Separated from service'],
                            ['is_pwd', 'pwd_details', 'Person with disability'],
                            ['is_solo_parent', 'solo_parent_details', 'Solo parent'],
                            ['has_recent_hospital', 'hospital_details', 'Hospitalized in last 6 months'],
                            ['has_current_treatment', 'treatment_details', 'Currently under treatment'],
                        ];
                        foreach ($discList as $d):
                            ?>
                            <div class="disclosure-item mb-2">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <?php echo yn($emp[$d[0]]); ?>
                                    <span class="small fw-semibold"><?php echo $d[2]; ?></span>
                                </div>
                                <?php if (!empty($emp[$d[1]])): ?>
                                    <div class="small text-muted ms-4"><?php echo e($emp[$d[1]]); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($refs)): ?>
                            <h6 class="mt-4 mb-2 text-primary small fw-bold"><i
                                    class="fas fa-address-book me-1"></i>Character References</h6>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Telephone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($refs as $rf): ?>
                                        <tr>
                                            <td><?php echo e($rf['reference_name']); ?></td>
                                            <td><?php echo e($rf['reference_address'] ?: '—'); ?></td>
                                            <td><?php echo e($rf['reference_telephone'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Government IDs Tab -->
                    <div class="tab-pane fade" id="t7">
                        <?php
                        echo field('SSS Number', $emp['sss_number']);
                        echo field('PhilHealth Number', $emp['philhealth_number']);
                        echo field('Pag-IBIG Number', $emp['pagibig_number']);
                        echo field('TIN Number', $emp['tin_number']);
                        ?>
                        <hr>
                        <?php
                        echo field('Job Title', $emp['job_title']);
                        echo field('Department', $emp['department_name']);
                        echo field('Branch', $emp['branch_name']);
                        echo field('Hire Date', formatDate($emp['hire_date']));
                        echo field('Employment Status', $emp['employment_status']);
                        echo field('Employment Type', $emp['employment_type']);
                        ?>
                    </div>

                    <!-- Career Movements Tab -->
                    <div class="tab-pane fade" id="t8">
                        <!-- Career Goals Section -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary small fw-bold mb-0"><i class="fas fa-bullseye me-2"></i>Career Aspirations & Goals (Current)</h6>
                            <?php if ($career_goals): ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="openPromoteModal()">
                                    <i class="fas fa-rocket me-1"></i>Implement Promotion / Movement
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($career_goals): ?>
                            <div class="p-3 border rounded bg-light mb-4 shadow-sm" id="careerAspirationsBox">
                                <div class="row align-items-center text-center text-sm-start">
                                    <div class="col-md-5">
                                        <div class="small text-muted mb-1">Target Position</div>
                                        <div class="fw-bold text-primary" style="font-size:1.1rem;"><?php echo e($career_goals['desired_position'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-3 border-start">
                                        <div class="small text-muted mb-1">Target Date</div>
                                        <div class="fw-bold small"><?php echo $career_goals['target_date'] ? formatDate($career_goals['target_date']) : 'N/A'; ?></div>
                                    </div>
                                    <div class="col-md-4 border-start">
                                        <div class="small text-muted mb-1">Last Evaluation Date</div>
                                        <div class="fw-bold small"><?php echo formatDate($career_goals['approved_date']); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($career_goals['career_growth_details'])): ?>
                                    <div class="mt-2 pt-2 border-top small">
                                        <span class="text-muted italic">"<?php echo e($career_goals['career_growth_details']); ?>"</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info py-2 small mb-4">
                                <span class="d-block mb-1"><i class="fas fa-info-circle me-2"></i>No career goals recorded from recent evaluations.</span>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="openPromoteModal(true)">
                                    <i class="fas fa-plus me-1"></i>Log Manual Movement
                                </button>
                            </div>
                        <?php endif; ?>

                        <h6 class="mb-3 text-primary small fw-bold border-top pt-4"><i class="fas fa-history me-2"></i>Official Career Movements (History)</h6>
                        <?php if (!empty($career_movements)): ?>
                            <div class="timeline-career">
                                <?php foreach ($career_movements as $cm):
                                    $movClass = 'bg-secondary';
                                    switch ($cm['movement_type']) {
                                        case 'Promotion':
                                            $movClass = 'bg-success';
                                            break;
                                        case 'Transfer':
                                            $movClass = 'bg-info';
                                            break;
                                        case 'Demotion':
                                            $movClass = 'bg-danger';
                                            break;
                                        case 'Role Change':
                                            $movClass = 'bg-primary';
                                            break;
                                    }
                                    $statClass = $cm['approval_status'] === 'Approved' ? 'bg-success' : ($cm['approval_status'] === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <div class="career-item d-flex gap-3 mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <span class="badge <?php echo $movClass; ?> rounded-pill px-3 py-2"
                                                style="font-size:0.7rem;"><?php echo e($cm['movement_type']); ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span
                                                        class="fw-semibold small"><?php echo e($cm['previous_position'] ?? 'N/A'); ?></span>
                                                    <i class="fas fa-arrow-right mx-2 text-muted"
                                                        style="font-size:0.65rem;"></i>
                                                    <span class="fw-bold small"><?php echo e($cm['new_position']); ?></span>
                                                    <?php if (!empty($cm['new_branch_id'])): ?>
                                                        <div class="small text-muted"><i
                                                                class="fas fa-building me-1"></i><?php echo e($cm['prev_branch_name'] ?? '?'); ?>
                                                            → <strong><?php echo e($cm['new_branch_name']); ?></strong></div>
                                                    <?php endif; ?>
                                                </div>
                                                <span
                                                    class="badge <?php echo $statClass; ?> ms-2"><?php echo e($cm['approval_status']); ?></span>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-calendar-alt me-1"></i>Effective:
                                                <?php echo formatDate($cm['effective_date']); ?>
                                                <?php if ($cm['is_applied']): ?><span class="badge bg-success ms-1"
                                                        style="font-size:0.6rem;">Applied</span><?php elseif ($cm['approval_status'] === 'Approved'): ?><span
                                                        class="badge bg-secondary ms-1"
                                                        style="font-size:0.6rem;">Scheduled</span><?php endif; ?>
                                            </div>
                                            <?php if (!empty($cm['reason'])): ?>
                                                <div class="small text-muted"><i
                                                        class="fas fa-comment me-1"></i><?php echo e($cm['reason']); ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1">
                                                Logged by: <?php echo e($cm['logged_by_name'] ?? 'N/A'); ?>
                                                <?php if (!empty($cm['approved_by_name'])): ?> &bull;
                                                    <?php echo e($cm['approval_status']); ?> by:
                                                    <?php echo e($cm['approved_by_name']); ?>         <?php endif; ?>
                                                &bull; <?php echo formatDateTime($cm['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-route d-block"></i>
                                <p>No career movements recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs {
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
    }

    .nav-tabs .nav-link {
        color: var(--text-muted);
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.6rem 0.8rem;
        transition: all 0.3s;
    }

    .nav-tabs .nav-link:hover {
        color: var(--text-dark);
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-blue);
        background: transparent;
        border-bottom: 2px solid var(--primary-blue);
    }

    .tab-content .row {
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        padding-bottom: 4px;
    }

    .tab-content .row:last-child {
        border-bottom: none;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .hover-zoom:hover {
        transform: scale(1.05);
    }
</style>

<!-- Full Image View Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-lg">
            <div class="modal-header border-0 p-0 position-absolute top-0 end-0 z-index-100">
                <button type="button" class="btn-close btn-close-white p-3 m-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 text-center">
                <img id="fullImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh;">
                <h6 id="fullImageName" class="text-white mt-3 fw-bold"></h6>
            </div>
        </div>
    </div>
</div>

<script>
    function viewFullImage(src, name) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        document.getElementById('fullImage').src = src;
        document.getElementById('fullImageName').textContent = name;
        modal.show();
    }

    function openPromoteModal(manual = false) {
        const modal = new bootstrap.Modal(document.getElementById('promoteModal'));
        if (!manual) {
            const goalBox = document.getElementById('careerAspirationsBox');
            if (goalBox) {
                document.getElementById('moveNewPosition').value = "<?php echo e($career_goals['desired_position'] ?? ''); ?>";
                document.getElementById('moveEffectiveDate').value = "<?php echo $career_goals['target_date'] ?? date('Y-m-d'); ?>";
                document.getElementById('moveReason').value = "Evaluation Goal from <?php echo formatDate($career_goals['approved_date'] ?? ''); ?>: <?php echo e(addslashes($career_goals['career_growth_details'] ?? '')); ?>";
            }
        }
        modal.show();
    }

    function submitPromotion() {
        const btn = document.getElementById('promoteBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

        const formData = new FormData(document.getElementById('promoteForm'));
        
        fetch('<?php echo BASE_URL; ?>/manager/ajax/add-career-movement.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
</script>

<?php 
$branches_opt = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
?>
<!-- Promote Modal -->
<div class="modal fade" id="promoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-rocket me-2"></i>Log Career Movement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="promoteForm">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" value="<?php echo $eid; ?>">
                    <input type="hidden" name="previous_position" value="<?php echo e($emp['job_title']); ?>">
                    
                    <div class="mb-3">
                        <label class="small fw-bold mb-1">Movement Type</label>
                        <select class="form-select form-select-sm" name="movement_type" required>
                            <option value="Promotion" selected>Promotion</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Role Change">Role Change</option>
                            <option value="Demotion">Demotion</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">New Position / Job Title</label>
                        <input type="text" class="form-control form-control-sm border-primary" name="new_position" id="moveNewPosition" required>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">Target Branch (Stay current if empty)</label>
                        <select class="form-select form-select-sm" name="new_branch_id">
                            <option value="">-- No Change (<?php echo e($emp['branch_name']); ?>) --</option>
                            <?php while($b = $branches_opt->fetch_assoc()): ?>
                                <option value="<?php echo $b['branch_id']; ?>" <?php echo $b['branch_id'] == $emp['branch_id'] ? 'disabled' : ''; ?>>
                                    <?php echo e($b['branch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">Effective Date</label>
                        <input type="date" class="form-control form-control-sm border-info" name="effective_date" id="moveEffectiveDate" required>
                        <div class="form-text x-small text-info"><i class="fas fa-info-circle me-1"></i>System will auto-apply title update on this date.</div>
                    </div>

                    <div class="mb-0">
                        <label class="small fw-bold mb-1">Remarks / Reason</label>
                        <textarea class="form-control form-control-sm" name="reason" id="moveReason" rows="3" placeholder="Enter reason for movement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" id="promoteBtn" onclick="submitPromotion()">
                        <i class="fas fa-save me-1"></i>Confirm Movement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
