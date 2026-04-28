<?php
/**
 * AJAX: Auto-save PDS section data as Draft.
 * Called on every step navigation in pds-wizard.php.
 */
require_once '../../includes/session-check.php';
checkRole(['Employee']);
require_once '../../includes/functions.php';
header('Content-Type: application/json');

$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$user_id     = (int)$_SESSION['user_id'];

if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'No employee linked to account.']);
    exit;
}

$conn->begin_transaction();
try {
    // ── 1. Core employee fields ───────────────────────────────────────────────
    $fields = ['first_name','last_name','middle_name','name_extension',
               'date_of_birth','place_of_birth','gender','civil_status'];
    $updates = [];
    $params  = [];
    $types   = '';
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $updates[] = "$f = ?";
            $params[]  = $_POST[$f] !== '' ? $_POST[$f] : null;
            $types    .= 's';
        }
    }
    if ($updates) {
        $params[] = $employee_id;
        $stmt = $conn->prepare("UPDATE employees SET ".implode(',',$updates)." WHERE employee_id=?");
        $stmt->bind_param($types.'i', ...$params);
        $stmt->execute(); $stmt->close();
    }

    // ── 2. Employee details (physical) ────────────────────────────────────────
    $det_fields = ['height_m','weight_kg','blood_type','citizenship'];
    $det_vals   = [];
    foreach ($det_fields as $f) $det_vals[$f] = isset($_POST[$f]) && $_POST[$f]!=='' ? $_POST[$f] : null;
    $existing = $conn->query("SELECT detail_id FROM employee_details WHERE employee_id=$employee_id")->fetch_assoc();
    if ($existing) {
        $d = $conn->prepare("UPDATE employee_details SET height_m=?,weight_kg=?,blood_type=?,citizenship=? WHERE employee_id=?");
        $d->bind_param("ddssi",$det_vals['height_m'],$det_vals['weight_kg'],$det_vals['blood_type'],$det_vals['citizenship'],$employee_id);
        $d->execute(); $d->close();
    } else {
        $d = $conn->prepare("INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship) VALUES (?,?,?,?,?)");
        $d->bind_param("iddss",$employee_id,$det_vals['height_m'],$det_vals['weight_kg'],$det_vals['blood_type'],$det_vals['citizenship']);
        $d->execute(); $d->close();
    }

    // ── 3. Government IDs ─────────────────────────────────────────────────────
    $gov = ['sss_number','philhealth_number','pagibig_number','tin_number'];
    $gv  = [];
    foreach ($gov as $f) $gv[$f] = isset($_POST[$f]) && $_POST[$f]!=='' ? $_POST[$f] : null;
    $eg = $conn->query("SELECT id_entry_id FROM employee_government_ids WHERE employee_id=$employee_id")->fetch_assoc();
    if ($eg) {
        $g = $conn->prepare("UPDATE employee_government_ids SET sss_number=?,philhealth_number=?,pagibig_number=?,tin_number=? WHERE employee_id=?");
        $g->bind_param("ssssi",$gv['sss_number'],$gv['philhealth_number'],$gv['pagibig_number'],$gv['tin_number'],$employee_id);
        $g->execute(); $g->close();
    } else {
        $g = $conn->prepare("INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number) VALUES (?,?,?,?,?)");
        $g->bind_param("issss",$employee_id,$gv['sss_number'],$gv['philhealth_number'],$gv['pagibig_number'],$gv['tin_number']);
        $g->execute(); $g->close();
    }

    // ── 4. Addresses ──────────────────────────────────────────────────────────
    $addrTypes = ['Residential'=>'res_','Permanent'=>'perm_'];
    foreach ($addrTypes as $type => $px) {
        $addr_fields = ['house_no','street','subdivision','barangay','city','province','zip_code'];
        $av = [];
        foreach ($addr_fields as $f) $av[$f] = $_POST[$px.$f] ?? null;
        $ea_stmt = $conn->prepare("SELECT address_id FROM employee_addresses WHERE employee_id=? AND address_type=? LIMIT 1");
        $ea_stmt->bind_param("is", $employee_id, $type);
        $ea_stmt->execute();
        $ea = $ea_stmt->get_result()->fetch_assoc();
        $ea_stmt->close();
        if ($ea) {
            $a = $conn->prepare("UPDATE employee_addresses SET house_no=?,street=?,subdivision=?,barangay=?,city=?,province=?,zip_code=? WHERE employee_id=? AND address_type=?");
            $a->bind_param(
                "sssssssis",
                $av['house_no'],
                $av['street'],
                $av['subdivision'],
                $av['barangay'],
                $av['city'],
                $av['province'],
                $av['zip_code'],
                $employee_id,
                $type
            );
            $a->execute();
            $a->close();
        } else {
            $a = $conn->prepare("INSERT INTO employee_addresses (employee_id,address_type,house_no,street,subdivision,barangay,city,province,zip_code) VALUES (?,?,?,?,?,?,?,?,?)");
            $a->bind_param(
                "issssssss",
                $employee_id,
                $type,
                $av['house_no'],
                $av['street'],
                $av['subdivision'],
                $av['barangay'],
                $av['city'],
                $av['province'],
                $av['zip_code']
            );
            $a->execute();
            $a->close();
        }
    }

    // ── 5. Contacts ───────────────────────────────────────────────────────────
    $phone = $_POST['telephone_number'] ?? null;
    $mobile= $_POST['contact_number']   ?? null;
    $email = $_POST['email']            ?? null;
    $ec_ex = $conn->query("SELECT contact_id FROM employee_contacts WHERE employee_id=$employee_id")->fetch_assoc();
    if ($ec_ex) {
        $c = $conn->prepare("UPDATE employee_contacts SET telephone_number=?,mobile_number=?,personal_email=? WHERE employee_id=?");
        $c->bind_param("sssi",$phone,$mobile,$email,$employee_id); $c->execute(); $c->close();
    } else {
        $c = $conn->prepare("INSERT INTO employee_contacts (employee_id,telephone_number,mobile_number,personal_email) VALUES (?,?,?,?)");
        $c->bind_param("isss",$employee_id,$phone,$mobile,$email); $c->execute(); $c->close();
    }

    // ── 6. Emergency contact ──────────────────────────────────────────────────
    $ecn = $_POST['emergency_contact_name']   ?? null;
    $ecr = $_POST['emergency_relationship']   ?? null;
    $ecp = $_POST['emergency_contact_number'] ?? null;
    if ($ecn) {
        $em_ex = $conn->query("SELECT emergency_id FROM employee_emergency_contacts WHERE employee_id=$employee_id LIMIT 1")->fetch_assoc();
        if ($em_ex) {
            $em = $conn->prepare("UPDATE employee_emergency_contacts SET contact_name=?,relationship=?,contact_number=? WHERE employee_id=? LIMIT 1");
            $em->bind_param("sssi",$ecn,$ecr,$ecp,$employee_id); $em->execute(); $em->close();
        } else {
            $em = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number) VALUES (?,?,?,?)");
            $em->bind_param("isss",$employee_id,$ecn,$ecr,$ecp); $em->execute(); $em->close();
        }
    }

    // ── 7. Family ─────────────────────────────────────────────────────────────
    $familyTypes = [
        'Spouse' => ['spouse_surname','spouse_first_name','spouse_middle_name','spouse_name_ext','spouse_occupation'],
        'Father' => ['father_surname','father_first_name','father_middle_name','father_name_ext','father_occupation'],
        'Mother' => ['mother_maiden_surname','mother_first_name','mother_middle_name',null,'mother_occupation'],
    ];
    foreach ($familyTypes as $mtype => $keys) {
        $sn = isset($keys[0]) ? ($_POST[$keys[0]] ?? null) : null;
        $fn = isset($keys[1]) ? ($_POST[$keys[1]] ?? null) : null;
        $mn = isset($keys[2]) ? ($_POST[$keys[2]] ?? null) : null;
        $ne = isset($keys[3]) ? ($_POST[$keys[3]] ?? null) : null;
        $oc = isset($keys[4]) ? ($_POST[$keys[4]] ?? null) : null;
        $ex = $conn->query("SELECT family_id FROM employee_family WHERE employee_id=$employee_id AND member_type='$mtype'")->fetch_assoc();
        if ($ex) {
            $fm = $conn->prepare("UPDATE employee_family SET surname=?,first_name=?,middle_name=?,name_extension=?,occupation=? WHERE employee_id=? AND member_type=?");
            $fm->bind_param("sssssiss",$sn,$fn,$mn,$ne,$oc,$employee_id,$mtype); $fm->execute(); $fm->close();
        } else {
            $fm = $conn->prepare("INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,name_extension,occupation) VALUES (?,?,?,?,?,?,?)");
            $fm->bind_param("issssss",$employee_id,$mtype,$sn,$fn,$mn,$ne,$oc); $fm->execute(); $fm->close();
        }
    }

    // ── 8. Repeater helpers ───────────────────────────────────────────────────
    function saveRepeater($conn, $table, $eid, $cols, $postKeys) {
        $conn->query("DELETE FROM $table WHERE employee_id=$eid");
        $counts = isset($_POST[$postKeys[0]]) ? count((array)$_POST[$postKeys[0]]) : 0;
        if (!$counts) return;
        $placeholders = '?' . str_repeat(',?', count($cols));
        $types = 'i' . str_repeat('s', count($cols));
        $colList = implode(',', $cols);
        $stmt = $conn->prepare("INSERT INTO $table (employee_id,$colList) VALUES ($placeholders)");
        for ($i = 0; $i < $counts; $i++) {
            $binds = [$eid];
            foreach ($postKeys as $pk) $binds[] = $_POST[$pk][$i] ?? null;
            $stmt->bind_param($types, ...$binds);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Children
    if (isset($_POST['child_surname'])) {
        saveRepeater($conn,'employee_children',$employee_id,
            ['surname','first_name','middle_name','date_of_birth'],
            ['child_surname','child_first_name','child_middle_name','child_dob']);
    }
    // Siblings
    if (isset($_POST['sibling_surname'])) {
        saveRepeater($conn,'employee_siblings',$employee_id,
            ['surname','first_name','middle_name','date_of_birth'],
            ['sibling_surname','sibling_first_name','sibling_middle_name','sibling_dob']);
    }
    // Education
    if (isset($_POST['edu_level'])) {
        saveRepeater($conn,'employee_education',$employee_id,
            ['education_level','school_name','degree_course','period_from','period_to','highest_level_units','year_graduated','honors_received'],
            ['edu_level','edu_school','edu_degree','edu_from','edu_to','edu_units','edu_year_grad','edu_honors']);
    }
    // Work
    if (isset($_POST['work_from'])) {
        saveRepeater($conn,'employee_work_experience',$employee_id,
            ['date_from','date_to','job_title','company_name','monthly_salary','appointment_status','reason_for_leaving'],
            ['work_from','work_to','work_title','work_company','work_salary','work_status','work_reason']);
    }
    // Trainings
    if (isset($_POST['training_from'])) {
        saveRepeater($conn,'employee_trainings',$employee_id,
            ['date_from','date_to','training_title','training_type','no_of_hours','conducted_by'],
            ['training_from','training_to','training_title','training_type','training_hours','training_conducted']);
    }
    // Voluntary
    if (isset($_POST['vol_from'])) {
        saveRepeater($conn,'employee_voluntary_work',$employee_id,
            ['date_from','date_to','organization_name','organization_address','no_of_hours','position_nature'],
            ['vol_from','vol_to','vol_org','vol_address','vol_hours','vol_position']);
    }
    // Eligibility
    if (isset($_POST['elig_title'])) {
        saveRepeater($conn,'employee_eligibility',$employee_id,
            ['license_title','date_from','date_to','license_number','date_of_exam','place_of_exam'],
            ['elig_title','elig_from','elig_to','elig_number','elig_exam_date','elig_exam_place']);
    }
    // Skills
    if (isset($_POST['skill_name'])) {
        $conn->query("DELETE FROM employee_skills WHERE employee_id=$employee_id");
        foreach ((array)$_POST['skill_name'] as $sk) {
            if (trim($sk)) {
                $s = $conn->prepare("INSERT INTO employee_skills (employee_id,skill_name) VALUES (?,?)");
                $s->bind_param("is",$employee_id,$sk); $s->execute(); $s->close();
            }
        }
    }
    // Recognitions
    if (isset($_POST['recognition_title'])) {
        $conn->query("DELETE FROM employee_recognitions WHERE employee_id=$employee_id");
        foreach ((array)$_POST['recognition_title'] as $r) {
            if (trim($r)) {
                $st = $conn->prepare("INSERT INTO employee_recognitions (employee_id,recognition_title) VALUES (?,?)");
                $st->bind_param("is",$employee_id,$r); $st->execute(); $st->close();
            }
        }
    }
    // Memberships
    if (isset($_POST['membership_org'])) {
        $conn->query("DELETE FROM employee_memberships WHERE employee_id=$employee_id");
        foreach ((array)$_POST['membership_org'] as $m) {
            if (trim($m)) {
                $st = $conn->prepare("INSERT INTO employee_memberships (employee_id,organization_name) VALUES (?,?)");
                $st->bind_param("is",$employee_id,$m); $st->execute(); $st->close();
            }
        }
    }
    // Real properties
    if (isset($_POST['rprop_desc'])) {
        saveRepeater($conn,'employee_real_properties',$employee_id,
            ['description','kind','exact_location','assessed_value','market_value'],
            ['rprop_desc','rprop_kind','rprop_location','rprop_assessed','rprop_market']);
    }
    // Personal properties
    if (isset($_POST['pprop_desc'])) {
        saveRepeater($conn,'employee_personal_properties',$employee_id,
            ['description','year_acquired','acquisition_cost'],
            ['pprop_desc','pprop_year','pprop_cost']);
    }
    // Liabilities
    if (isset($_POST['liab_nature'])) {
        saveRepeater($conn,'employee_liabilities',$employee_id,
            ['nature_of_liability','creditor_name','outstanding_balance'],
            ['liab_nature','liab_creditor','liab_balance']);
    }
    // References
    if (isset($_POST['ref_name'])) {
        saveRepeater($conn,'employee_references',$employee_id,
            ['reference_name','reference_address','reference_telephone'],
            ['ref_name','ref_address','ref_telephone']);
    }

    // ── 9. Disclosures ────────────────────────────────────────────────────────
    $disc_keys = ['is_related_to_company','related_details','has_admin_offense','admin_offense_details',
                  'has_criminal_charge','criminal_charge_details','has_criminal_conviction','criminal_conviction_details',
                  'has_been_separated','separation_details','is_pwd','pwd_details','is_solo_parent','solo_parent_details'];
    $disc_vals = [];
    foreach ($disc_keys as $k) $disc_vals[$k] = isset($_POST[$k]) ? ($_POST[$k] ?: null) : null;
    $disc_ex = $conn->query("SELECT disclosure_id FROM employee_disclosures WHERE employee_id=$employee_id")->fetch_assoc();
    if ($disc_ex) {
        $dp = $conn->prepare("UPDATE employee_disclosures SET is_related_to_company=?,related_details=?,has_admin_offense=?,admin_offense_details=?,has_criminal_charge=?,criminal_charge_details=?,has_criminal_conviction=?,criminal_conviction_details=?,has_been_separated=?,separation_details=?,is_pwd=?,pwd_details=?,is_solo_parent=?,solo_parent_details=? WHERE employee_id=?");
        $dp->bind_param("sissississississi",
            $disc_vals['is_related_to_company'],$disc_vals['related_details'],
            $disc_vals['has_admin_offense'],$disc_vals['admin_offense_details'],
            $disc_vals['has_criminal_charge'],$disc_vals['criminal_charge_details'],
            $disc_vals['has_criminal_conviction'],$disc_vals['criminal_conviction_details'],
            $disc_vals['has_been_separated'],$disc_vals['separation_details'],
            $disc_vals['is_pwd'],$disc_vals['pwd_details'],
            $disc_vals['is_solo_parent'],$disc_vals['solo_parent_details'],
            $employee_id);
        $dp->execute(); $dp->close();
    } else {
        $conn->query("INSERT INTO employee_disclosures (employee_id) VALUES ($employee_id)");
    }

    // ── 10. Ensure Draft submission row exists ────────────────────────────────
    $sub_ex = $conn->query("SELECT submission_id,status FROM employee_pds_submissions WHERE employee_id=$employee_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
    if (!$sub_ex) {
        $si = $conn->prepare("INSERT INTO employee_pds_submissions (employee_id,submitted_by,status) VALUES (?,?,'Draft')");
        $si->bind_param("ii",$employee_id,$user_id); $si->execute(); $si->close();
    } elseif (in_array($sub_ex['status'],['Rejected','Changes Requested'])) {
        // Allow employee to re-save as draft before resubmitting
        $conn->query("UPDATE employee_pds_submissions SET status='Draft',hr_notes=NULL,reviewed_at=NULL,reviewed_by=NULL WHERE submission_id={$sub_ex['submission_id']}");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Saved as draft.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
