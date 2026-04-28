<?php
$page_title = 'Add Employee';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Check for saved form draft from previous failed attempt (Persistence)
$emp = $_SESSION['form_draft'] ?? [];
unset($_SESSION['form_draft']); // Clear it after retrieving to avoid stale data

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    require_once '../includes/functions.php';
    if (!isset($_FILES['employee_csv']) || $_FILES['employee_csv']['error'] !== UPLOAD_ERR_OK) {
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', 'Please upload a valid CSV file.');
    }

    $file = fopen($_FILES['employee_csv']['tmp_name'], 'r');
    if (!$file)
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', 'Could not read the uploaded file.');

    // Skip header
    fgetcsv($file);

    $success = 0;
    $skipped = 0;

    while (($row = fgetcsv($file)) !== false) {
        // Minimum required columns (basic info)
        if (count($row) < 10) {
            $skipped++;
            continue;
        }

        $v = array_pad(array_map('trim', $row), 74, '');

        // Basic mapping for CSV
        $first_name = $v[0];
        $last_name = $v[1];
        $middle_name = $v[2];
        $name_extension = $v[3];
        $dob = null;
        if (!empty($v[4])) {
            $d1 = DateTime::createFromFormat('m/d/Y', $v[4]);
            if ($d1)
                $dob = $d1->format('Y-m-d');
            else
                $dob = $v[4];
        }
        $pob = $v[5];
        $gender = $v[6];
        $civil_status = $v[7];
        $hd = null;
        if (!empty($v[65])) {
            $d2 = DateTime::createFromFormat('m/d/Y', $v[65]);
            if ($d2)
                $hd = $d2->format('Y-m-d');
            else
                $hd = $v[65];
        }
        $job_title = $v[66];
        $dept = $v[67];
        $emp_status = $v[69];
        $emp_type = $v[70];

        if (empty($first_name) || empty($last_name) || empty($hd) || empty($job_title)) {
            $skipped++;
            continue;
        }

        // Dup check
        $dc = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ?");
        $dc->bind_param("ss", $first_name, $last_name);
        $dc->execute();
        $dr = $dc->get_result();
        $dc->close();
        if ($dr->num_rows > 0) {
            $skipped++;
            continue;
        }

        // Department lookup/creation
        $did = null;
        if (!empty($v[67])) {
            $dc = $conn->prepare("SELECT department_id FROM departments WHERE department_name = ?");
            $dc->bind_param("s", $v[67]);
            $dc->execute();
            $dr = $dc->get_result();
            if ($d = $dr->fetch_assoc()) {
                $did = $d['department_id'];
            } else {
                $di = $conn->prepare("INSERT INTO departments (department_name, description) VALUES (?, 'Imported via CSV')");
                $di->bind_param("s", $v[67]);
                $di->execute();
                $did = $di->insert_id;
                $di->close();
            }
            $dc->close();
        }

        // Branch
        $bid = null;
        if (!empty($v[68])) {
            $bc = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
            $bc->bind_param("s", $v[68]);
            $bc->execute();
            $br = $bc->get_result();
            if ($b = $br->fetch_assoc()) {
                $bid = $b['branch_id'];
            } else {
                $bi = $conn->prepare("INSERT INTO branches (branch_name, location) VALUES (?, 'TBD')");
                $bi->bind_param("s", $v[68]);
                $bi->execute();
                $bid = $bi->insert_id;
                $bi->close();
            }
            $bc->close();
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO employees (first_name, last_name, middle_name, name_extension, date_of_birth, place_of_birth, gender, civil_status, hire_date, job_title, department_id, branch_id, employment_status, employment_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssssssiiss", $first_name, $last_name, $middle_name, $name_extension, $dob, $pob, $gender, $civil_status, $hd, $job_title, $did, $bid, $emp_status, $emp_type);
            $stmt->execute();
            $eid = $stmt->insert_id;
            $stmt->close();

            // Minimal details for CSV import
            $stmt = $conn->prepare("INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES (?,?,?,?,?)");
            $h = !empty($v[8]) ? (float) $v[8] : null;
            $w = !empty($v[9]) ? (float) $v[9] : null;
            $stmt->bind_param("iddss", $eid, $h, $w, $v[10], $v[11]);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES (?,?,?,?,?)");
            $stmt->bind_param("issss", $eid, $v[12], $v[13], $v[14], $v[15]);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO employee_contacts (employee_id, telephone_number, mobile_number, personal_email) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $eid, $v[30], $v[31], $v[32]);
            $stmt->execute();
            $stmt->close();

            // Addresses
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Residential', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $eid, $v[16], $v[17], $v[18], $v[19], $v[20], $v[21], $v[22]);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $eid, $v[23], $v[24], $v[25], $v[26], $v[27], $v[28], $v[29]);
            $stmt->execute();
            $stmt->close();

            // Emergency
            $stmt = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $eid, $v[71], $v[72], $v[73]);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Employee', $eid, "Imported employee via CSV: $first_name $last_name");
            $success++;
        } catch (Exception $e) {
            $conn->rollback();
            // error_log("CSV Import error: " . $e->getMessage());
            $skipped++;
        }
    }
    fclose($file);

    if ($success > 0) {
        $msg = "Successfully imported $success employee(s).";
        if ($skipped > 0)
            $msg .= " Skipped $skipped row(s) due to errors or duplicates.";
        redirectWith(BASE_URL . '/manager/employees.php', 'success', $msg);
    } else {
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', "Failed to import employees. ($skipped rows skipped)");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_csv'])) {
    require_once '../includes/functions.php';

    // === SECTION 1: Personal Information ===
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $name_extension = trim($_POST['name_extension'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $civil_status = !empty($_POST['civil_status']) ? $_POST['civil_status'] : null;
    $height_m = !empty($_POST['height_m']) ? (float) $_POST['height_m'] : null;
    $weight_kg = !empty($_POST['weight_kg']) ? (float) $_POST['weight_kg'] : null;
    $blood_type = !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
    $citizenship = trim($_POST['citizenship'] ?? 'Filipino');

    // Gov IDs
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

    // === SECTION 2: Family Background ===
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

    // === SECTION 10: Disclosures ===
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

    // === SECTION 12: Employment ===
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $branch_id = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;
    $employment_status = $_POST['employment_status'] ?? 'Regular';
    $employment_type = $_POST['employment_type'] ?? 'Full-time';
    $employee_code = strtoupper(trim($_POST['employee_code'] ?? ''));
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
    $emergency_contact_number = trim($_POST['emergency_contact_number'] ?? '');
    $contract_start_date = !empty($_POST['contract_start_date']) ? $_POST['contract_start_date'] : null;
    $contract_end_date = !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null;

    // Profile picture
    $profile_picture = null;
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
                    $profile_picture = $new_filename;
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
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', "Image Upload Error: " . $upload_error);
    }

    // Validate required
    if (empty($employee_code) || empty($first_name) || empty($last_name) || empty($hire_date) || empty($job_title) || empty($department_id)) {
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', 'Please fill in all required fields (Employee ID, Name, Hire Date, Job Title, Department).');
    }

    // Strictly no duplicate employee
    $dupCheck = $conn->prepare("SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ?");
    $dupCheck->bind_param("ss", $first_name, $last_name);
    $dupCheck->execute();
    if ($dupCheck->get_result()->num_rows > 0) {
        $dupCheck->close();
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', "An employee named '$first_name $last_name' already exists in the system.");
    }
    $dupCheck->close();

    $codeCheck = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
    $codeCheck->bind_param("s", $employee_code);
    $codeCheck->execute();
    if ($codeCheck->get_result()->num_rows > 0) {
        $codeCheck->close();
        redirectWith(BASE_URL . '/manager/add-employee.php', 'danger', "Employee ID '$employee_code' already exists in the system.");
    }
    $codeCheck->close();

    // Build address string for legacy column
    $address = trim("$res_house_no $res_street $res_subdivision $res_barangay $res_city $res_province $res_zip_code");

    // Use Transaction for Normalized Tables
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO employees (
            employee_code, first_name, last_name, middle_name, name_extension,
            date_of_birth, place_of_birth, gender, civil_status,
            hire_date, job_title, department_id, branch_id, 
            employment_status, employment_type, contract_start_date, contract_end_date, profile_picture
        ) VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssiisssss",
            $employee_code,
            $first_name,
            $last_name,
            $middle_name,
            $name_extension,
            $date_of_birth,
            $place_of_birth,
            $gender,
            $civil_status,
            $hire_date,
            $job_title,
            $department_id,
            $branch_id,
            $employment_status,
            $employment_type,
            $contract_start_date,
            $contract_end_date,
            $profile_picture
        );
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        // 1. Details
        $stmt = $conn->prepare("INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iddss", $new_id, $height_m, $weight_kg, $blood_type, $citizenship);
        $stmt->execute();
        $stmt->close();

        // 2. Gov IDs
        $stmt = $conn->prepare("INSERT INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $new_id, $sss_number, $philhealth_number, $pagibig_number, $tin_number);
        $stmt->execute();
        $stmt->close();

        // 3. Contacts
        $stmt = $conn->prepare("INSERT INTO employee_contacts (employee_id, telephone_number, mobile_number, personal_email) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $new_id, $telephone_number, $contact_number, $email);
        $stmt->execute();
        $stmt->close();

        // 4. Addresses (Residential)
        if (!empty($res_street) || !empty($res_city)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Residential', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $new_id, $res_house_no, $res_street, $res_subdivision, $res_barangay, $res_city, $res_province, $res_zip_code);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Addresses (Permanent)
        if (!empty($perm_street) || !empty($perm_city)) {
            $stmt = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, subdivision, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $new_id, $perm_house_no, $perm_street, $perm_subdivision, $perm_barangay, $perm_city, $perm_province, $perm_zip_code);
            $stmt->execute();
            $stmt->close();
        }

        // 6. Emergency Contact
        $stmt = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $new_id, $emergency_contact_name, $emergency_contact_relationship, $emergency_contact_number);
        $stmt->execute();
        $stmt->close();

        // 7. Disclosures
        $stmt = $conn->prepare("INSERT INTO employee_disclosures (
            employee_id, is_related_to_company, related_details, has_admin_offense, admin_offense_details,
            has_criminal_charge, criminal_charge_details, has_criminal_conviction, criminal_conviction_details,
            has_been_separated, separation_details, is_pwd, pwd_details, is_solo_parent, solo_parent_details,
            has_recent_hospital, hospital_details, has_current_treatment, treatment_details
        ) VALUES (?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?)");
        $stmt->bind_param(
            "iisssssssssssssssss",
            $new_id,
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

        // 8. Family (Spouse)
        if (!empty($spouse_surname) || !empty($spouse_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Spouse', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $new_id, $spouse_surname, $spouse_first_name, $spouse_middle_name, $spouse_name_ext, $spouse_occupation);
            $stmt->execute();
            $stmt->close();
        }

        // 9. Family (Parents)
        if (!empty($father_surname) || !empty($father_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, name_extension, occupation) VALUES (?, 'Father', ?,?,?,?,?)");
            $stmt->bind_param("isssss", $new_id, $father_surname, $father_first_name, $father_middle_name, $father_name_ext, $father_occupation);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($mother_maiden_surname) || !empty($mother_first_name)) {
            $stmt = $conn->prepare("INSERT INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES (?, 'Mother', ?,?,?,?)");
            $stmt->bind_param("issss", $new_id, $mother_maiden_surname, $mother_first_name, $mother_middle_name, $mother_occupation);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        // Save child table data
        // Children
        if (!empty($_POST['child_first_name'])) {
            $cstmt = $conn->prepare("INSERT INTO employee_children (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['child_first_name'] as $i => $cfn) {
                if (empty(trim($cfn)))
                    continue;
                $cs = trim($_POST['child_surname'][$i] ?? '');
                $cfn = trim($cfn);
                $cm = trim($_POST['child_middle_name'][$i] ?? '');
                $cd = !empty($_POST['child_dob'][$i]) ? $_POST['child_dob'][$i] : null;
                $cstmt->bind_param("issss", $new_id, $cs, $cfn, $cm, $cd);
                $cstmt->execute();
            }
            $cstmt->close();
        }

        // Siblings
        if (!empty($_POST['sibling_first_name'])) {
            $sstmt = $conn->prepare("INSERT INTO employee_siblings (employee_id, surname, first_name, middle_name, date_of_birth) VALUES (?,?,?,?,?)");
            foreach ($_POST['sibling_first_name'] as $i => $sfn) {
                if (empty(trim($sfn)))
                    continue;
                $ss = trim($_POST['sibling_surname'][$i] ?? '');
                $sfn = trim($sfn);
                $sm = trim($_POST['sibling_middle_name'][$i] ?? '');
                $sd = !empty($_POST['sibling_dob'][$i]) ? $_POST['sibling_dob'][$i] : null;
                $sstmt->bind_param("issss", $new_id, $ss, $sfn, $sm, $sd);
                $sstmt->execute();
            }
            $sstmt->close();
        }

        // Education
        if (!empty($_POST['edu_level'])) {
            $estmt = $conn->prepare("INSERT INTO employee_education (employee_id, education_level, school_name, degree_course, period_from, period_to, highest_level_units, year_graduated, honors_received) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($_POST['edu_level'] as $i => $lvl) {
                if (empty(trim($_POST['edu_school'][$i] ?? '')))
                    continue;
                $school = trim($_POST['edu_school'][$i]);
                $degree = trim($_POST['edu_degree'][$i] ?? '');
                $pfrom = !empty($_POST['edu_from'][$i]) ? $_POST['edu_from'][$i] : null;
                $pto = !empty($_POST['edu_to'][$i]) ? $_POST['edu_to'][$i] : null;
                $units = trim($_POST['edu_units'][$i] ?? '');
                $ygrad = trim($_POST['edu_year_grad'][$i] ?? '');
                $honors = trim($_POST['edu_honors'][$i] ?? '');
                $estmt->bind_param("issssssss", $new_id, $lvl, $school, $degree, $pfrom, $pto, $units, $ygrad, $honors);
                $estmt->execute();
            }
            $estmt->close();
        }

        // Work Experience
        if (!empty($_POST['work_title'])) {
            $wstmt = $conn->prepare("INSERT INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary, appointment_status, reason_for_leaving) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($_POST['work_title'] as $i => $wt) {
                if (empty(trim($wt)))
                    continue;
                $wf = !empty($_POST['work_from'][$i]) ? $_POST['work_from'][$i] : null;
                $wto = !empty($_POST['work_to'][$i]) ? $_POST['work_to'][$i] : null;
                $wt = trim($wt);
                $wc = trim($_POST['work_company'][$i] ?? '');
                $ws = !empty($_POST['work_salary'][$i]) ? (float) $_POST['work_salary'][$i] : null;
                $wa = trim($_POST['work_status'][$i] ?? '');
                $wr = trim($_POST['work_reason'][$i] ?? '');
                $wstmt->bind_param("issssdss", $new_id, $wf, $wto, $wt, $wc, $ws, $wa, $wr);
                $wstmt->execute();
            }
            $wstmt->close();
        }

        // Trainings
        if (!empty($_POST['training_title'])) {
            $tstmt = $conn->prepare("INSERT INTO employee_trainings (employee_id, date_from, date_to, training_title, training_type, no_of_hours, conducted_by) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['training_title'] as $i => $tt) {
                if (empty(trim($tt)))
                    continue;
                $tf = !empty($_POST['training_from'][$i]) ? $_POST['training_from'][$i] : null;
                $tto = !empty($_POST['training_to'][$i]) ? $_POST['training_to'][$i] : null;
                $tt = trim($tt);
                $ttype = trim($_POST['training_type'][$i] ?? '');
                $th = !empty($_POST['training_hours'][$i]) ? (float) $_POST['training_hours'][$i] : null;
                $tc = trim($_POST['training_conducted'][$i] ?? '');
                $tstmt->bind_param("issssds", $new_id, $tf, $tto, $tt, $ttype, $th, $tc);
                $tstmt->execute();
            }
            $tstmt->close();
        }

        // Voluntary Work
        if (!empty($_POST['vol_org'])) {
            $vstmt = $conn->prepare("INSERT INTO employee_voluntary_work (employee_id, date_from, date_to, organization_name, organization_address, no_of_hours, position_nature) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['vol_org'] as $i => $vo) {
                if (empty(trim($vo)))
                    continue;
                $vf = !empty($_POST['vol_from'][$i]) ? $_POST['vol_from'][$i] : null;
                $vto = !empty($_POST['vol_to'][$i]) ? $_POST['vol_to'][$i] : null;
                $vo = trim($vo);
                $va = trim($_POST['vol_address'][$i] ?? '');
                $vh = !empty($_POST['vol_hours'][$i]) ? (float) $_POST['vol_hours'][$i] : null;
                $vp = trim($_POST['vol_position'][$i] ?? '');
                $vstmt->bind_param("issssds", $new_id, $vf, $vto, $vo, $va, $vh, $vp);
                $vstmt->execute();
            }
            $vstmt->close();
        }

        // Eligibility
        if (!empty($_POST['elig_title'])) {
            $elstmt = $conn->prepare("INSERT INTO employee_eligibility (employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam) VALUES (?,?,?,?,?,?,?)");
            foreach ($_POST['elig_title'] as $i => $et) {
                if (empty(trim($et)))
                    continue;
                $ef = !empty($_POST['elig_from'][$i]) ? $_POST['elig_from'][$i] : null;
                $eto = !empty($_POST['elig_to'][$i]) ? $_POST['elig_to'][$i] : null;
                $en = trim($_POST['elig_number'][$i] ?? '');
                $ed = !empty($_POST['elig_exam_date'][$i]) ? $_POST['elig_exam_date'][$i] : null;
                $ep = trim($_POST['elig_exam_place'][$i] ?? '');
                $elstmt->bind_param("issssss", $new_id, $et, $ef, $eto, $en, $ed, $ep);
                $elstmt->execute();
            }
            $elstmt->close();
        }

        // Skills
        if (!empty($_POST['skill_name'])) {
            $skstmt = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_name) VALUES (?,?)");
            foreach ($_POST['skill_name'] as $sk) {
                if (empty(trim($sk)))
                    continue;
                $sk = trim($sk);
                $skstmt->bind_param("is", $new_id, $sk);
                $skstmt->execute();
            }
            $skstmt->close();
        }

        // Recognitions
        if (!empty($_POST['recognition_title'])) {
            $rcstmt = $conn->prepare("INSERT INTO employee_recognitions (employee_id, recognition_title) VALUES (?,?)");
            foreach ($_POST['recognition_title'] as $rc) {
                if (empty(trim($rc)))
                    continue;
                $rc = trim($rc);
                $rcstmt->bind_param("is", $new_id, $rc);
                $rcstmt->execute();
            }
            $rcstmt->close();
        }

        // Memberships
        if (!empty($_POST['membership_org'])) {
            $mbstmt = $conn->prepare("INSERT INTO employee_memberships (employee_id, organization_name) VALUES (?,?)");
            foreach ($_POST['membership_org'] as $mb) {
                if (empty(trim($mb)))
                    continue;
                $mb = trim($mb);
                $mbstmt->bind_param("is", $new_id, $mb);
                $mbstmt->execute();
            }
            $mbstmt->close();
        }

        // Real Properties
        if (!empty($_POST['rprop_desc'])) {
            $rpstmt = $conn->prepare("INSERT INTO employee_real_properties (employee_id, description, kind, exact_location, assessed_value, market_value, acquisition_year_mode, acquisition_cost) VALUES (?,?,?,?,?,?,?,?)");
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
                $rpstmt->bind_param("isssddsd", $new_id, $rd, $rk, $rl, $rav, $rmv, $ram, $rac);
                $rpstmt->execute();
            }
            $rpstmt->close();
        }

        // Personal Properties
        if (!empty($_POST['pprop_desc'])) {
            $ppstmt = $conn->prepare("INSERT INTO employee_personal_properties (employee_id, description, year_acquired, acquisition_cost) VALUES (?,?,?,?)");
            foreach ($_POST['pprop_desc'] as $i => $pd) {
                if (empty(trim($pd)))
                    continue;
                $pd = trim($pd);
                $py = trim($_POST['pprop_year'][$i] ?? '');
                $pc = !empty($_POST['pprop_cost'][$i]) ? (float) $_POST['pprop_cost'][$i] : null;
                $ppstmt->bind_param("issd", $new_id, $pd, $py, $pc);
                $ppstmt->execute();
            }
            $ppstmt->close();
        }

        // Liabilities
        if (!empty($_POST['liab_nature'])) {
            $lstmt = $conn->prepare("INSERT INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES (?,?,?,?)");
            foreach ($_POST['liab_nature'] as $i => $ln) {
                if (empty(trim($ln)))
                    continue;
                $ln = trim($ln);
                $lc = trim($_POST['liab_creditor'][$i] ?? '');
                $lb = !empty($_POST['liab_balance'][$i]) ? (float) $_POST['liab_balance'][$i] : null;
                $lstmt->bind_param("issd", $new_id, $ln, $lc, $lb);
                $lstmt->execute();
            }
            $lstmt->close();
        }

        // References
        if (!empty($_POST['ref_name'])) {
            $rfstmt = $conn->prepare("INSERT INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES (?,?,?,?)");
            foreach ($_POST['ref_name'] as $i => $rn) {
                if (empty(trim($rn)))
                    continue;
                $rn = trim($rn);
                $ra = trim($_POST['ref_address'][$i] ?? '');
                $rt = trim($_POST['ref_telephone'][$i] ?? '');
                $rfstmt->bind_param("isss", $new_id, $rn, $ra, $rt);
                $rfstmt->execute();
            }
            $rfstmt->close();
        }

        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Employee', $new_id, "Added employee: $first_name $last_name");
        redirectWith(BASE_URL . '/manager/employees.php', 'success', "Employee '$first_name $last_name' added successfully.");

    } catch (Exception $e) {
        $conn->rollback();
        // Save information to session to prevent starting over after redirect
        $_SESSION['form_draft'] = $_POST;
        redirectWith(BASE_URL . "/manager/add-employee.php", 'danger', "Failed to add employee: " . $e->getMessage());
    }
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
    <p class="text-muted mb-0">Fill out the personal data sheet below</p>
    <div>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-csv me-2"></i>Import Custom CSV
        </button>
        <!-- Back button removed as requested -->
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-user-plus me-2"></i>Add New Employee (Personal Data Sheet)</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="addEmployeeForm" enctype="multipart/form-data" data-is-edit="false">
            <!-- Wizard Header / Progress (matches Employee PDS wizard UX) -->
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

    // Step tabs
    const tabs = document.querySelectorAll('#wizardStepTabs .step-tab');
    tabs.forEach((btn, idx) => {
        const n = idx + 1;
        btn.classList.toggle('btn-primary', n === step);
        btn.classList.toggle('btn-outline-secondary', n !== step);
    });

    // Progress bar
    const bar = document.getElementById('pdsProgressBar');
    if (bar) bar.style.width = ((step / 12) * 100) + '%';

    // Nav buttons
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

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="importModalLabel"><i class="fas fa-file-csv me-2"></i>Import Employees
                        from CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Upload a CSV file to bulk import basic employee records. Ensure
                        your file matches the system's exact column format.</p>

                    <div class="mb-3">
                        <label for="employee_csv" class="form-label fw-bold">Select CSV File</label>
                        <input class="form-control" type="file" id="employee_csv" name="employee_csv" accept=".csv"
                            required>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Need the format? <a href="<?php echo BASE_URL; ?>/sample_employees.csv" download
                            class="alert-link">Download the sample template</a>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="import_csv" class="btn btn-success"><i
                            class="fas fa-upload me-2"></i>Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>
