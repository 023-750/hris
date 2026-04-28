<?php
$page_title = 'Edit Employee';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($eid <= 0) {
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Invalid employee ID.');
}

$stmt = $conn->prepare("SELECT e.*, 
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email,
    edi.is_related_to_company, edi.related_details, edi.has_admin_offense, edi.admin_offense_details,
    edi.has_criminal_charge, edi.criminal_charge_details, edi.has_criminal_conviction, edi.criminal_conviction_details,
    edi.has_been_separated, edi.separation_details, edi.is_pwd, edi.pwd_details,
    edi.is_solo_parent, edi.solo_parent_details, edi.has_recent_hospital, edi.hospital_details,
    edi.has_current_treatment, edi.treatment_details
    FROM employees e 
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN employee_disclosures edi ON e.employee_id = edi.employee_id
    WHERE e.employee_id = ?");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Employee not found.');
}

// Strictly prevent editing of System Admin profiles in the employee management module
$adminCheck = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND role = 'Admin'");
$adminCheck->bind_param("i", $eid);
$adminCheck->execute();
if ($adminCheck->get_result()->num_rows > 0) {
    $adminCheck->close();
    redirectWith(BASE_URL . '/manager/employees.php', 'danger', 'Access denied to system administrator profiles.');
}
$adminCheck->close();

// Reconstruct flattened array for UI compatibility
$emp['email'] = $emp['personal_email'];
$emp['contact_number'] = $emp['mobile_number'];

$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();
$addr_f = ['house_no', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip_code'];
foreach ($addr_f as $f) {
    $emp['res_' . $f] = $res_addr[$f] ?? '';
    $emp['perm_' . $f] = $perm_addr[$f] ?? '';
}

$emerg = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid LIMIT 1")->fetch_assoc();
if ($emerg) {
    $emp['emergency_contact_name'] = $emerg['contact_name'];
    $emp['emergency_contact_relationship'] = $emerg['relationship'];
    $emp['emergency_contact_number'] = $emerg['contact_number'];
}

$family = $conn->query("SELECT * FROM employee_family WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
foreach ($family as $m) {
    $pre = strtolower($m['member_type']);
    if ($pre === 'mother')
        $pre = 'mother_maiden';
    $emp[$pre . '_surname'] = $m['surname'];
    $emp[$pre . '_first_name'] = $m['first_name'];
    $emp[$pre . '_middle_name'] = $m['middle_name'];
    if (isset($m['name_extension']))
        $emp[$pre . '_name_ext'] = $m['name_extension'];
    $emp[$pre . '_occupation'] = $m['occupation'];
}

// Load child table data for edit mode
$employeeChildren = $conn->query("SELECT * FROM employee_children WHERE employee_id = $eid ORDER BY child_id")->fetch_all(MYSQLI_ASSOC);
$employeeSiblings = $conn->query("SELECT * FROM employee_siblings WHERE employee_id = $eid ORDER BY sibling_id")->fetch_all(MYSQLI_ASSOC);
$employeeEducation = $conn->query("SELECT * FROM employee_education WHERE employee_id = $eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$employeeWork = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id = $eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$employeeTrainings = $conn->query("SELECT * FROM employee_trainings WHERE employee_id = $eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);
$employeeVoluntary = $conn->query("SELECT * FROM employee_voluntary_work WHERE employee_id = $eid ORDER BY voluntary_id")->fetch_all(MYSQLI_ASSOC);
$employeeEligibility = $conn->query("SELECT * FROM employee_eligibility WHERE employee_id = $eid ORDER BY eligibility_id")->fetch_all(MYSQLI_ASSOC);
$employeeSkills = $conn->query("SELECT * FROM employee_skills WHERE employee_id = $eid ORDER BY skill_id")->fetch_all(MYSQLI_ASSOC);
$employeeRecognitions = $conn->query("SELECT * FROM employee_recognitions WHERE employee_id = $eid ORDER BY recognition_id")->fetch_all(MYSQLI_ASSOC);
$employeeMemberships = $conn->query("SELECT * FROM employee_memberships WHERE employee_id = $eid ORDER BY membership_id")->fetch_all(MYSQLI_ASSOC);
$employeeRefs = $conn->query("SELECT * FROM employee_references WHERE employee_id = $eid ORDER BY reference_id")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Section 1
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $name_extension = trim($_POST['name_extension'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $civil_status = !empty($_POST['civil_status']) ? $_POST['civil_status'] : null;
    $height_m = !empty($_POST['height_m']) ? $_POST['height_m'] : null;
    $weight_kg = !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null;
    $blood_type = !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
    $citizenship = trim($_POST['citizenship'] ?? 'Filipino');
    $sss_number = trim($_POST['sss_number'] ?? '');
    $philhealth_number = trim($_POST['philhealth_number'] ?? '');
    $pagibig_number = trim($_POST['pagibig_number'] ?? '');
    $tin_number = trim($_POST['tin_number'] ?? '');

    // Addresses
    $res_house_no = trim($_POST['res_house_no'] ?? '');
    $res_street = trim($_POST['res_street'] ?? '');
    $res_subdivision = trim($_POST['res_subdivision'] ?? '');
    $res_barangay = trim($_POST['res_barangay'] ?? '');
    $res_city = trim($_POST['res_city'] ?? '');
    $res_province = trim($_POST['res_province'] ?? '');
    $res_zip_code = trim($_POST['res_zip_code'] ?? '');
    $perm_house_no = trim($_POST['perm_house_no'] ?? '');
    $perm_street = trim($_POST['perm_street'] ?? '');
    $perm_subdivision = trim($_POST['perm_subdivision'] ?? '');
    $perm_barangay = trim($_POST['perm_barangay'] ?? '');
    $perm_city = trim($_POST['perm_city'] ?? '');
    $perm_province = trim($_POST['perm_province'] ?? '');
    $perm_zip_code = trim($_POST['perm_zip_code'] ?? '');

    // Contact
    $telephone_number = trim($_POST['telephone_number'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;

    // Family
    $spouse_surname = trim($_POST['spouse_surname'] ?? '');
    $spouse_first_name = trim($_POST['spouse_first_name'] ?? '');
    $spouse_middle_name = trim($_POST['spouse_middle_name'] ?? '');
    $spouse_name_ext = trim($_POST['spouse_name_ext'] ?? '');
    $spouse_occupation = trim($_POST['spouse_occupation'] ?? '');
    $father_surname = trim($_POST['father_surname'] ?? '');
    $father_first_name = trim($_POST['father_first_name'] ?? '');
    $father_middle_name = trim($_POST['father_middle_name'] ?? '');
    $father_name_ext = trim($_POST['father_name_ext'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $mother_maiden_surname = trim($_POST['mother_maiden_surname'] ?? '');
    $mother_first_name = trim($_POST['mother_first_name'] ?? '');
    $mother_middle_name = trim($_POST['mother_middle_name'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');

    // Disclosures
    $is_related_to_company = isset($_POST['is_related_to_company']) ? 1 : 0;
    $related_details = trim($_POST['related_details'] ?? '');
    $has_admin_offense = isset($_POST['has_admin_offense']) ? 1 : 0;
    $admin_offense_details = trim($_POST['admin_offense_details'] ?? '');
    $has_criminal_charge = isset($_POST['has_criminal_charge']) ? 1 : 0;
    $criminal_charge_details = trim($_POST['criminal_charge_details'] ?? '');
    $has_criminal_conviction = isset($_POST['has_criminal_conviction']) ? 1 : 0;
    $criminal_conviction_details = trim($_POST['criminal_conviction_details'] ?? '');
    $has_been_separated = isset($_POST['has_been_separated']) ? 1 : 0;
    $separation_details = trim($_POST['separation_details'] ?? '');
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $pwd_details = trim($_POST['pwd_details'] ?? '');
    $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
    $solo_parent_details = trim($_POST['solo_parent_details'] ?? '');
    $has_recent_hospital = isset($_POST['has_recent_hospital']) ? 1 : 0;
    $hospital_details = trim($_POST['hospital_details'] ?? '');
    $has_current_treatment = isset($_POST['has_current_treatment']) ? 1 : 0;
    $treatment_details = trim($_POST['treatment_details'] ?? '');

    // Employment
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $branch_id = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;
    $employment_status = $_POST['employment_status'] ?? 'Regular';
    $employment_type = $_POST['employment_type'] ?? 'Full-time';
    $employee_code = strtoupper(trim($_POST['employee_code'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
    $emergency_contact_number = trim($_POST['emergency_contact_number'] ?? '');
    $contract_start_date = !empty($_POST['contract_start_date']) ? $_POST['contract_start_date'] : null;
    $contract_end_date = !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null;

    if (empty($employee_code) || empty($first_name) || empty($last_name) || empty($hire_date) || empty($job_title) || empty($department_id)) {
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', 'Please fill in all required fields.');
    }

    // Strictly no duplicate employee
    $dupCheck = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ? AND employee_id != ?");
    $dupCheck->bind_param("ssi", $first_name, $last_name, $eid);
    $dupCheck->execute();
    if ($dupCheck->get_result()->num_rows > 0) {
        $dupCheck->close();
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', "An employee named '$first_name $last_name' already exists in the system.");
    }
    $dupCheck->close();

    $codeCheck = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? AND employee_id != ?");
    $codeCheck->bind_param("si", $employee_code, $eid);
    $codeCheck->execute();
    if ($codeCheck->get_result()->num_rows > 0) {
        $codeCheck->close();
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', "Employee ID '$employee_code' already exists in the system.");
    }
    $codeCheck->close();

    // Profile picture
    $new_filename = null;
    $upload_error = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['name'] !== '') {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/img/employees/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_filename = uniqid('emp_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                    if (!empty($emp['profile_picture']) && file_exists($upload_dir . $emp['profile_picture'])) {
                        unlink($upload_dir . $emp['profile_picture']);
                    }
                } else {
                    $upload_error = "Could not save the uploaded image to the server.";
                }
            } else {
                $upload_error = "Invalid image format. Only JPG, PNG, and GIF are allowed.";
            }
        } else {
            $upload_error = "File upload error code: " . $_FILES['profile_picture']['error'];
        }
    }

    if ($upload_error) {
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', "Image Upload Error: " . $upload_error);
    }

    $conn->begin_transaction();
    try {
        $sql = "UPDATE employees SET
            employee_code=?, first_name=?, last_name=?, middle_name=?, name_extension=?,
            date_of_birth=?, place_of_birth=?, gender=?, civil_status=?,
            hire_date=?, job_title=?, department_id=?, branch_id=?, 
            employment_status=?, employment_type=?, contract_start_date=?, contract_end_date=?, is_active=?";

        if ($new_filename)
            $sql .= ", profile_picture=?";
        $sql .= " WHERE employee_id=?";

        $stmt = $conn->prepare($sql);
        $types = "sssssssssssiissssi" . ($new_filename ? "s" : "") . "i";
        $params = [$employee_code, $first_name, $last_name, $middle_name, $name_extension, $date_of_birth, $place_of_birth, $gender, $civil_status, $hire_date, $job_title, $department_id, $branch_id, $employment_status, $employment_type, $contract_start_date, $contract_end_date, $is_active];
        if ($new_filename)
            $params[] = $new_filename;
        $params[] = $eid;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        // Update 1:1 Tables (REPLACE INTO is safe for 1:1 extension tables)

        // 1. Details
        $stmt = $conn->prepare("REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iddss", $eid, $height_m, $weight_kg, $blood_type, $citizenship);
        $stmt->execute();
        $stmt->close();

        // 2. Gov IDs
        $stmt = $conn->prepare("REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $eid, $sss_number, $philhealth_number, $pagibig_number, $tin_number);
        $stmt->execute();
        $stmt->close();

        // 3. Contacts
        $stmt = $conn->prepare("REPLACE INTO employee_contacts (employee_id, telephone_number, mobile_number, personal_email) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $eid, $telephone_number, $contact_number, $email);
        $stmt->execute();
        $stmt->close();

        // 4. Addresses
        $conn->query("DELETE FROM employee_addresses WHERE employee_id = $eid");
        if (!empty($res_street) || !empty($res_city)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Residential', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $eid, $res_house_no, $res_street, $res_subdivision, $res_barangay, $res_city, $res_province, $res_zip_code);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($perm_street) || !empty($perm_city)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $eid, $perm_house_no, $perm_street, $perm_subdivision, $perm_barangay, $perm_city, $perm_province, $perm_zip_code);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Emergency
        $stmt = $conn->prepare("REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $eid, $emergency_contact_name, $emergency_contact_relationship, $emergency_contact_number);
        $stmt->execute();
        $stmt->close();

        // 6. Disclosures
        $stmt = $conn->prepare("REPLACE INTO employee_disclosures (
            employee_id, is_related_to_company, related_details, has_admin_offense, admin_offense_details,
            has_criminal_charge, criminal_charge_details, has_criminal_conviction, criminal_conviction_details,
            has_been_separated, separation_details, is_pwd, pwd_details, is_solo_parent, solo_parent_details,
            has_recent_hospital, hospital_details, has_current_treatment, treatment_details
        ) VALUES (?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?)");
        $stmt->bind_param(
            "iisssssssssssssssss",
            $eid,
            $is_related_to_company,
            $related_details,
            $has_admin_offense,
            $admin_offense_details,
            $has_criminal_charge,
            $criminal_charge_details,
            $has_criminal_conviction,
            $criminal_conviction_details,
            $has_been_separated,
            $separation_details,
            $is_pwd,
            $pwd_details,
            $is_solo_parent,
            $solo_parent_details,
            $has_recent_hospital,
            $hospital_details,
            $has_current_treatment,
            $treatment_details
        );
        $stmt->execute();
        $stmt->close();

        // 7. Family (Parents/Spouse - Delete/Reload style like child tables)
        $conn->query("DELETE FROM employee_family WHERE employee_id = $eid");
        if (!empty($spouse_surname) || !empty($spouse_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Spouse', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $eid, $spouse_surname, $spouse_first_name, $spouse_middle_name, $spouse_name_ext, $spouse_occupation);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($father_surname) || !empty($father_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Father', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $eid, $father_surname, $father_first_name, $father_middle_name, $father_name_ext, $father_occupation);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($mother_maiden_surname) || !empty($mother_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES (?, 'Mother', ?,?,?,?)");
            $stmt->bind_param("issss", $eid, $mother_maiden_surname, $mother_first_name, $mother_middle_name, $mother_occupation);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', "Failed to update: " . $e->getMessage());
    }

    if (true) { // Legacy wrapper to keep the rest of the file logic
        // Delete and re-insert child tables
        $childTables = [
            'employee_children',
            'employee_siblings',
            'employee_education',
            'employee_work_experience',
            'employee_trainings',
            'employee_voluntary_work',
            'employee_eligibility',
            'employee_skills',
            'employee_recognitions',
            'employee_memberships',
            'employee_real_properties',
            'employee_personal_properties',
            'employee_liabilities',
            'employee_references'
        ];
        foreach ($childTables as $tbl) {
            $conn->query("DELETE FROM $tbl WHERE employee_id = $eid");
        }

        // Re-insert children
        if (!empty($_POST['child_first_name'])) {
            $cs = $conn->prepare("INSERT INTO employee_children (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['child_first_name'] as $i => $cfn) {
                if (empty(trim($cfn)))
                    continue;
                $a = $eid;
                $b = trim($_POST['child_surname'][$i] ?? '');
                $c = trim($cfn);
                $d = trim($_POST['child_middle_name'][$i] ?? '');
                $f = !empty($_POST['child_dob'][$i]) ? $_POST['child_dob'][$i] : null;
                $cs->bind_param("issss", $a, $b, $c, $d, $f);
                $cs->execute();
            }
            $cs->close();
        }

        // Re-insert siblings
        if (!empty($_POST['sibling_first_name'])) {
            $ss = $conn->prepare("INSERT INTO employee_siblings (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['sibling_first_name'] as $i => $sfn) {
                if (empty(trim($sfn)))
                    continue;
                $a = $eid;
                $b = trim($_POST['sibling_surname'][$i] ?? '');
                $c = trim($sfn);
                $d = trim($_POST['sibling_middle_name'][$i] ?? '');
                $f = !empty($_POST['sibling_dob'][$i]) ? $_POST['sibling_dob'][$i] : null;
                $ss->bind_param("issss", $a, $b, $c, $d, $f);
                $ss->execute();
            }
            $ss->close();
        }

        // Re-insert education
        if (!empty($_POST['edu_level'])) {
            $es = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($_POST['edu_level'] as $i => $lvl) {
                if (empty(trim($_POST['edu_school'][$i] ?? '')))
                    continue;
                $a = $eid;
                $b = trim($_POST['edu_school'][$i]);
                $c = trim($_POST['edu_degree'][$i] ?? '');
                $d = !empty($_POST['edu_from'][$i]) ? $_POST['edu_from'][$i] : null;
                $f = !empty($_POST['edu_to'][$i]) ? $_POST['edu_to'][$i] : null;
                $g = trim($_POST['edu_units'][$i] ?? '');
                $h = trim($_POST['edu_year_grad'][$i] ?? '');
                $j = trim($_POST['edu_honors'][$i] ?? '');
                $es->bind_param("issssssss", $a, $lvl, $b, $c, $d, $f, $g, $h, $j);
                $es->execute();
            }
            $es->close();
        }

        // Re-insert work experience
        if (!empty($_POST['work_title'])) {
            $ws = $conn->prepare("INSERT INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary, appointment_status, reason_for_leaving) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($_POST['work_title'] as $i => $wt) {
                if (empty(trim($wt)))
                    continue;
                $a = $eid;
                $b = !empty($_POST['work_from'][$i]) ? $_POST['work_from'][$i] : null;
                $c = !empty($_POST['work_to'][$i]) ? $_POST['work_to'][$i] : null;
                $d = trim($wt);
                $f = trim($_POST['work_company'][$i] ?? '');
                $g = !empty($_POST['work_salary'][$i]) ? (float) $_POST['work_salary'][$i] : null;
                $h = trim($_POST['work_status'][$i] ?? '');
                $j = trim($_POST['work_reason'][$i] ?? '');
                $ws->bind_param("issssdss", $a, $b, $c, $d, $f, $g, $h, $j);
                $ws->execute();
            }
            $ws->close();
        }

        // Re-insert trainings
        if (!empty($_POST['training_title'])) {
            $ts = $conn->prepare("INSERT INTO employee_trainings (employee_id, date_from, date_to, training_title, training_type, no_of_hours, conducted_by) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['training_title'] as $i => $tt) {
                if (empty(trim($tt)))
                    continue;
                $a = $eid;
                $b = !empty($_POST['training_from'][$i]) ? $_POST['training_from'][$i] : null;
                $c = !empty($_POST['training_to'][$i]) ? $_POST['training_to'][$i] : null;
                $d = trim($tt);
                $f = trim($_POST['training_type'][$i] ?? '');
                $g = !empty($_POST['training_hours'][$i]) ? (float) $_POST['training_hours'][$i] : null;
                $h = trim($_POST['training_conducted'][$i] ?? '');
                $ts->bind_param("issssds", $a, $b, $c, $d, $f, $g, $h);
                $ts->execute();
            }
            $ts->close();
        }

        // Re-insert voluntary work
        if (!empty($_POST['vol_org'])) {
            $vs = $conn->prepare("INSERT INTO employee_voluntary_work (employee_id, date_from, date_to, organization_name, organization_address, no_of_hours, position_nature) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['vol_org'] as $i => $vo) {
                if (empty(trim($vo)))
                    continue;
                $a = $eid;
                $b = !empty($_POST['vol_from'][$i]) ? $_POST['vol_from'][$i] : null;
                $c = !empty($_POST['vol_to'][$i]) ? $_POST['vol_to'][$i] : null;
                $d = trim($vo);
                $f = trim($_POST['vol_address'][$i] ?? '');
                $g = !empty($_POST['vol_hours'][$i]) ? (float) $_POST['vol_hours'][$i] : null;
                $h = trim($_POST['vol_position'][$i] ?? '');
                $vs->bind_param("issssds", $a, $b, $c, $d, $f, $g, $h);
                $vs->execute();
            }
            $vs->close();
        }

        // Re-insert eligibility
        if (!empty($_POST['elig_title'])) {
            $el = $conn->prepare("INSERT INTO employee_eligibility (employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['elig_title'] as $i => $et) {
                if (empty(trim($et)))
                    continue;
                $a = $eid;
                $b = !empty($_POST['elig_from'][$i]) ? $_POST['elig_from'][$i] : null;
                $c = !empty($_POST['elig_to'][$i]) ? $_POST['elig_to'][$i] : null;
                $d = trim($_POST['elig_number'][$i] ?? '');
                $f = !empty($_POST['elig_exam_date'][$i]) ? $_POST['elig_exam_date'][$i] : null;
                $g = trim($_POST['elig_exam_place'][$i] ?? '');
                $el->bind_param("issssss", $a, $et, $b, $c, $d, $f, $g);
                $el->execute();
            }
            $el->close();
        }

        // Re-insert skills, recognitions, memberships
        if (!empty($_POST['skill_name'])) {
            $sk = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_name) VALUES (?,?)");
            foreach ($_POST['skill_name'] as $s) {
                if (empty(trim($s)))
                    continue;
                $a = $eid;
                $b = trim($s);
                $sk->bind_param("is", $a, $b);
                $sk->execute();
            }
            $sk->close();
        }
        if (!empty($_POST['recognition_title'])) {
            $rc = $conn->prepare("INSERT INTO employee_recognitions (employee_id, recognition_title) VALUES (?,?)");
            foreach ($_POST['recognition_title'] as $r) {
                if (empty(trim($r)))
                    continue;
                $a = $eid;
                $b = trim($r);
                $rc->bind_param("is", $a, $b);
                $rc->execute();
            }
            $rc->close();
        }
        if (!empty($_POST['membership_org'])) {
            $mb = $conn->prepare("INSERT INTO employee_memberships (employee_id, organization_name) VALUES (?,?)");
            foreach ($_POST['membership_org'] as $m) {
                if (empty(trim($m)))
                    continue;
                $a = $eid;
                $b = trim($m);
                $mb->bind_param("is", $a, $b);
                $mb->execute();
            }
            $mb->close();
        }

        // Re-insert real properties
        if (!empty($_POST['rprop_desc'])) {
            $rps = $conn->prepare("INSERT INTO employee_real_properties (employee_id, description, kind, exact_location, assessed_value, market_value, acquisition_year_mode, acquisition_cost) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($_POST['rprop_desc'] as $i => $rd) {
                if (empty(trim($rd)))
                    continue;
                $rk = trim($_POST['rprop_kind'][$i] ?? '');
                $rl = trim($_POST['rprop_location'][$i] ?? '');
                $rav = !empty($_POST['rprop_assessed'][$i]) ? (float) $_POST['rprop_assessed'][$i] : null;
                $rmv = !empty($_POST['rprop_market'][$i]) ? (float) $_POST['rprop_market'][$i] : null;
                $ram = trim($_POST['rprop_acq_mode'][$i] ?? '');
                $rac = !empty($_POST['rprop_acq_cost'][$i]) ? (float) $_POST['rprop_acq_cost'][$i] : null;
                $rd = trim($rd);
                $rps->bind_param("isssddsd", $eid, $rd, $rk, $rl, $rav, $rmv, $ram, $rac);
                $rps->execute();
            }
            $rps->close();
        }

        // Re-insert personal properties
        if (!empty($_POST['pprop_desc'])) {
            $pps = $conn->prepare("INSERT INTO employee_personal_properties (employee_id, description, year_acquired, acquisition_cost) VALUES (?,?,?,?)");
            foreach ($_POST['pprop_desc'] as $i => $pd) {
                if (empty(trim($pd)))
                    continue;
                $py = trim($_POST['pprop_year'][$i] ?? '');
                $pc = !empty($_POST['pprop_cost'][$i]) ? (float) $_POST['pprop_cost'][$i] : null;
                $pd = trim($pd);
                $pps->bind_param("issd", $eid, $pd, $py, $pc);
                $pps->execute();
            }
            $pps->close();
        }

        // Re-insert liabilities
        if (!empty($_POST['liab_nature'])) {
            $lbs = $conn->prepare("INSERT INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES (?,?,?,?)");
            foreach ($_POST['liab_nature'] as $i => $ln) {
                if (empty(trim($ln)))
                    continue;
                $lc = trim($_POST['liab_creditor'][$i] ?? '');
                $lb = !empty($_POST['liab_balance'][$i]) ? (float) $_POST['liab_balance'][$i] : null;
                $ln = trim($ln);
                $lbs->bind_param("issd", $eid, $ln, $lc, $lb);
                $lbs->execute();
            }
            $lbs->close();
        }

        // Re-insert references
        if (!empty($_POST['ref_name'])) {
            $rf = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
            foreach ($_POST['ref_name'] as $i => $rn) {
                if (empty(trim($rn)))
                    continue;
                $a = $eid;
                $b = trim($rn);
                $c = trim($_POST['ref_address'][$i] ?? '');
                $d = trim($_POST['ref_telephone'][$i] ?? '');
                $rf->bind_param("isss", $a, $b, $c, $d);
                $rf->execute();
            }
            $rf->close();
        }

        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee', $eid, "Updated employee: $first_name $last_name");
        
        $step = $_POST['current_step'] ?? 1;
        if (isset($_POST['quick_save'])) {
            redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'success', "Changes saved successfully.");
        } else {
            redirectWith(BASE_URL . '/manager/employees.php', 'success', "Employee '$first_name $last_name' updated successfully.");
        }
    } else {
        $step = $_POST['current_step'] ?? 1;
        redirectWith(BASE_URL . "/manager/edit-employee.php?id=$eid&step=$step", 'danger', "Failed to update: " . $stmt->error);
    }
    $stmt->close();
}

require_once '../includes/header.php';
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
$departments_result = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];

$stepLabels = [
    '1' => 'Personal Info',
    '2' => 'Family',
    '3' => 'Education',
    '4' => 'Work Exp.',
    '5' => 'Training',
    '6' => 'Voluntary',
    '7' => 'Eligibility',
    '8' => 'Skills',
    '9' => 'Assets',
    '10' => 'Disclosures',
    '11' => 'References',
    '12' => 'Employment',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Edit employee information</p>
    <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Employees
    </a>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-user-edit me-2"></i>Edit: <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="editEmployeeForm" enctype="multipart/form-data" data-is-edit="true">
            <div class="pds-progress mb-3">
                <div id="pdsProgressBar" class="pds-progress-bar" style="width: 8.33%;"></div>
            </div>

            <div id="wizardStepTabs" class="d-flex flex-wrap gap-1 justify-content-center mb-4">
                <?php foreach ($stepLabels as $num => $label): ?>
                    <button type="button"
                        id="tab-step<?php echo (int)$num; ?>"
                        onclick="showStep(<?php echo (int)$num; ?>)"
                        class="btn btn-sm step-tab px-3 btn-outline-secondary"
                        style="min-width:70px; font-size: 0.72rem; border-radius: 20px;">
                        <span class="d-none d-md-inline"><?php echo (int)$num . '. ' . e($label); ?></span>
                        <span class="d-md-none"><?php echo (int)$num; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-center align-items-center gap-3 pt-3 border-top mb-4">
                <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-outline-secondary px-4 shadow-sm" style="display:none;">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </button>
                <div class="d-flex gap-2">
                    <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary px-4 shadow-sm">
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-success px-4 shadow-sm" style="display:none;">
                        <i class="fas fa-save me-2"></i>Save Employee
                    </button>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/employee-form-steps.php'; ?>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/employee-form.js?v=<?php echo time(); ?>"></script>
<script>
function updateWizardUI(step) {
    step = parseInt(step, 10) || 1;

    const tabs = document.querySelectorAll('#wizardStepTabs .step-tab');
    tabs.forEach((btn, idx) => {
        const n = idx + 1;
        btn.classList.toggle('btn-primary', n === step);
        btn.classList.toggle('btn-outline-secondary', n !== step);
    });

    const bar = document.getElementById('pdsProgressBar');
    if (bar) bar.style.width = ((step / 12) * 100) + '%';

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    if (prevBtn) prevBtn.style.display = (step === 1) ? 'none' : 'inline-block';
    if (nextBtn) nextBtn.style.display = (step === 12) ? 'none' : 'inline-block';
    if (submitBtn) submitBtn.style.display = (step === 12) ? 'inline-block' : 'none';
}

function getCurrentStep() {
    const input = document.getElementById('currentStepInput');
    const val = input ? parseInt(input.value, 10) : 1;
    return isNaN(val) ? 1 : val;
}

function nextStep() {
    const s = getCurrentStep();
    if (s < 12) showStep(s + 1);
}

function prevStep() {
    const s = getCurrentStep();
    if (s > 1) showStep(s - 1);
}

document.addEventListener('DOMContentLoaded', () => {
    const originalShowStep = window.showStep;
    if (typeof originalShowStep === 'function') {
        window.showStep = function(step) {
            originalShowStep(step);
            updateWizardUI(step);
        };
    }
    updateWizardUI(getCurrentStep());
});
</script>

<?php require_once '../includes/footer.php'; ?>
