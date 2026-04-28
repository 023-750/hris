<?php
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';
redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Personal Data Sheet is no longer part of the Employee Portal.');

// ── Load existing employee data ───────────────────────────────────────────────
$emp_q = $conn->prepare("
    SELECT e.*,
           ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
           eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
           ec.telephone_number, ec.mobile_number AS contact_number, ec.personal_email AS email
    FROM employees e
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    WHERE e.employee_id = ?
");
$emp_q->bind_param("i", $employee_id);
$emp_q->execute();
$emp_raw = $emp_q->get_result()->fetch_assoc() ?? [];
$emp_q->close();

// Address flattening
$addr_q = $conn->prepare("SELECT * FROM employee_addresses WHERE employee_id=?");
$addr_q->bind_param("i", $employee_id);
$addr_q->execute();
$addr_res = $addr_q->get_result();
$addr_q->close();
$res_addr = []; $perm_addr = [];
while ($a = $addr_res->fetch_assoc()) {
    if ($a['address_type'] === 'Residential') $res_addr = $a;
    else $perm_addr = $a;
}
$emp = array_merge($emp_raw, [
    'res_house_no'     => $res_addr['house_no']    ?? '',
    'res_street'       => $res_addr['street']      ?? '',
    'res_subdivision'  => $res_addr['subdivision'] ?? '',
    'res_barangay'     => $res_addr['barangay']    ?? '',
    'res_city'         => $res_addr['city']        ?? '',
    'res_province'     => $res_addr['province']    ?? '',
    'res_zip_code'     => $res_addr['zip_code']    ?? '',
    'perm_house_no'    => $perm_addr['house_no']   ?? '',
    'perm_street'      => $perm_addr['street']     ?? '',
    'perm_subdivision' => $perm_addr['subdivision']?? '',
    'perm_barangay'    => $perm_addr['barangay']   ?? '',
    'perm_city'        => $perm_addr['city']       ?? '',
    'perm_province'    => $perm_addr['province']   ?? '',
    'perm_zip_code'    => $perm_addr['zip_code']   ?? '',
]);

// Emergency contact
$ec_q = $conn->prepare("SELECT * FROM employee_emergency_contacts WHERE employee_id=? LIMIT 1");
$ec_q->bind_param("i", $employee_id);
$ec_q->execute();
$emergencyContact = $ec_q->get_result()->fetch_assoc() ?? [];
$ec_q->close();
$emp['emergency_contact_name']   = $emergencyContact['contact_name']   ?? '';
$emp['emergency_relationship']   = $emergencyContact['relationship']   ?? '';
$emp['emergency_contact_number'] = $emergencyContact['contact_number'] ?? '';

// Family
$fam_q = $conn->prepare("SELECT * FROM employee_family WHERE employee_id=?");
$fam_q->bind_param("i", $employee_id); $fam_q->execute();
$fam_res = $fam_q->get_result(); $fam_q->close();
while ($f = $fam_res->fetch_assoc()) {
    $mt = strtolower($f['member_type']);
    $emp[$mt.'_surname']    = $f['surname']    ?? '';
    $emp[$mt.'_first_name'] = $f['first_name'] ?? '';
    if ($mt === 'spouse') {
        $emp['spouse_middle_name'] = $f['middle_name']     ?? '';
        $emp['spouse_name_ext']    = $f['name_extension']  ?? '';
        $emp['spouse_occupation']  = $f['occupation']      ?? '';
    } elseif ($mt === 'father') {
        $emp['father_middle_name'] = $f['middle_name']    ?? '';
        $emp['father_name_ext']    = $f['name_extension'] ?? '';
        $emp['father_occupation']  = $f['occupation']     ?? '';
    } elseif ($mt === 'mother') {
        $emp['mother_maiden_surname'] = $f['surname']     ?? '';
        $emp['mother_middle_name']    = $f['middle_name'] ?? '';
        $emp['mother_occupation']     = $f['occupation']  ?? '';
    }
}

// Sub-tables
function fetchRows($conn, $table, $eid) {
    $s = $conn->prepare("SELECT * FROM $table WHERE employee_id=? ORDER BY 1");
    $s->bind_param("i", $eid); $s->execute();
    $r = $s->get_result(); $s->close();
    return $r->fetch_all(MYSQLI_ASSOC);
}
$employeeChildren    = fetchRows($conn, 'employee_children',          $employee_id);
$employeeSiblings    = fetchRows($conn, 'employee_siblings',          $employee_id);
$employeeEducation   = fetchRows($conn, 'employee_education',         $employee_id);
$employeeWork        = fetchRows($conn, 'employee_work_experience',   $employee_id);
$employeeTrainings   = fetchRows($conn, 'employee_trainings',         $employee_id);
$employeeVoluntary   = fetchRows($conn, 'employee_voluntary_work',    $employee_id);
$employeeEligibility = fetchRows($conn, 'employee_eligibility',       $employee_id);
$employeeSkills      = fetchRows($conn, 'employee_skills',            $employee_id);
$employeeRecognitions= fetchRows($conn, 'employee_recognitions',      $employee_id);
$employeeMemberships = fetchRows($conn, 'employee_memberships',       $employee_id);
$employeeRealProps   = fetchRows($conn, 'employee_real_properties',   $employee_id);
$employeePersonalProps=fetchRows($conn, 'employee_personal_properties',$employee_id);
$employeeLiabilities = fetchRows($conn, 'employee_liabilities',       $employee_id);
$employeeReferences  = fetchRows($conn, 'employee_references',        $employee_id);

$disc_q = $conn->prepare("SELECT * FROM employee_disclosures WHERE employee_id=? LIMIT 1");
$disc_q->bind_param("i", $employee_id); $disc_q->execute();
$disc = $disc_q->get_result()->fetch_assoc() ?? [];
$disc_q->close();
$emp = array_merge($emp, $disc);

$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
$isEdit   = true;  // always edit mode for employee wizard

// Pre-render HR notes block for JS injection (PHP cannot run inside JS template literals)
$hr_notes_block = '';
if ($current_sub && $current_sub['status'] === 'Changes Requested' && !empty($current_sub['hr_notes'])) {
    $hr_notes_block = '<div class="alert alert-danger mb-4"><strong><i class="fas fa-exclamation-circle me-2"></i>HR has requested changes:</strong><br>' . nl2br(e($current_sub['hr_notes'])) . '</div>';
}

require_once '../includes/header.php';
?>

<!-- Wizard Header & Progress -->
<div class="content-card mb-4 shadow-sm border-0">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0 fw-bold"><i class="fas fa-file-signature me-2 text-primary"></i>Personal Data Sheet Wizard</h5>
        <div id="saveStatus" class="badge bg-light text-success border" style="display:none; font-weight: 500;">
          <i class="fas fa-cloud-upload-alt me-1"></i>Draft Saved
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="pds-progress mb-3">
      <div id="pdsProgressBar" class="pds-progress-bar" style="width: 8.33%;"></div>
    </div>

    <!-- Step Tabs -->
    <div id="wizardStepTabs" class="d-flex flex-wrap gap-1 justify-content-center mb-4">
      <?php
      $stepLabels = ['Personal','Family','Education','Work Exp.','Trainings','Voluntary','Eligibility','Skills','Assets','Disclosures','References','Submit'];
      foreach ($stepLabels as $i => $lbl):
          $n = $i+1;
      ?>
        <button type="button"
                id="tab-step<?php echo $n;?>"
                onclick="showStep(<?php echo $n;?>)"
                class="btn btn-sm step-tab px-3"
                style="min-width:70px; font-size: 0.72rem; border-radius: 20px;">
          <span class="d-none d-md-inline"><?php echo $n.'. '.$lbl;?></span>
          <span class="d-md-none"><?php echo $n;?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Navigation Buttons (Moved here below Title/Progress) -->
    <div class="d-flex justify-content-center align-items-center gap-3 pt-3 border-top">
      <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-outline-secondary px-4 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i> Back
      </button>
      <div class="d-flex gap-2">
        <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary px-4 shadow-sm">
          Next Step <i class="fas fa-arrow-right ms-2"></i>
        </button>
        <button type="button" id="submitBtn" onclick="submitPDS()" class="btn btn-success px-4 shadow-sm" style="display:none;">
          <i class="fas fa-check-double me-2"></i> Submit for Review
        </button>
      </div>
    </div>
  </div>
</div>

<div class="content-card shadow-sm border-0">
  <div class="card-body">
    <form method="POST" action="" enctype="multipart/form-data" id="pdsWizardForm">
      <input type="hidden" name="action" id="pds_action" value="">
      <?php include '../includes/employee-form-steps.php'; ?>
    </form>
  </div>
</div>

<!-- Mini Back-to-Top Button -->
<button type="button" id="backToTopBtn" class="btn btn-primary btn-sm shadow" aria-label="Go to top"
        style="position: fixed; right: 18px; bottom: 18px; z-index: 1050; border-radius: 999px; width: 36px; height: 36px; padding: 0; display: none;">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Override Step 12 with Employee-friendly Review & Submit panel -->
<script>
// ── Step navigation ───────────────────────────────────────────────────────────
let currentStep = 1;
const totalSteps = 12;

function showStep(n) {
    if (n < 1) n = 1;
    if (n > totalSteps) n = totalSteps;
    document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none');
    const target = document.getElementById('step' + n);
    if (target) target.style.display = '';
    currentStep = n;

    // Update step tabs
    document.querySelectorAll('.step-tab').forEach((btn, i) => {
        btn.classList.toggle('btn-primary', i + 1 === n);
        btn.classList.toggle('btn-outline-secondary', i + 1 !== n);
        btn.classList.remove('btn-outline-primary');
    });

    // Update progress bar
    const percent = (n / totalSteps) * 100;
    const bar = document.getElementById('pdsProgressBar');
    if (bar) bar.style.width = percent + '%';

    // Update navigation buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (prevBtn) prevBtn.style.display = (n === 1) ? 'none' : 'inline-block';
    if (nextBtn) nextBtn.style.display = (n === totalSteps) ? 'none' : 'inline-block';
    if (submitBtn) submitBtn.style.display = (n === totalSteps) ? 'inline-block' : 'none';

    window.scrollTo(0, 0);
}

function nextStep() {
    if (currentStep < totalSteps) {
        autoSaveDraft(() => {
            showStep(currentStep + 1);
        });
    }
}

function prevStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

// ── Auto-save via AJAX ────────────────────────────────────────────────────────
function autoSaveDraft(callback) {
    const saveStatus = document.getElementById('saveStatus');
    const form = document.getElementById('pdsWizardForm');
    const fd = new FormData(form);
    fd.append('employee_id', '<?php echo $employee_id; ?>');
    
    fetch('<?php echo BASE_URL; ?>/employee/ajax/save-pds-section.php', {
        method: 'POST', body: fd
    }).then(r => r.json()).then(() => {
        if (saveStatus) {
            saveStatus.style.display = 'inline-block';
            setTimeout(() => { saveStatus.style.display = 'none'; }, 2000);
        }
        if (typeof callback === 'function') callback();
    }).catch(() => { if (typeof callback === 'function') callback(); });
}

// ── Final submit ──────────────────────────────────────────────────────────────
function submitPDS() {
    if (!confirm('Submit your PDS for HR Manager review? You cannot edit it until HR responds.')) return;
    
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
    }

    // Set action before saving/submitting
    const actionInput = document.getElementById('pds_action');
    if (actionInput) actionInput.value = 'submit_pds';

    autoSaveDraft(() => {
        const form = document.getElementById('pdsWizardForm');
        if (form) {
            form.submit();
        } else {
            alert('Error: Form not found. Please refresh and try again.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-double me-2"></i> Submit for Review';
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Replace step 12 content with a read-only review + submit screen
    const step12 = document.getElementById('step12');
    if (step12) {
        step12.innerHTML = `
        <div class="text-center py-3 mb-4">
            <i class="fas fa-clipboard-check fa-3x mb-3" style="color:#3949ab;"></i>
            <h4 class="fw-bold">Review &amp; Submit</h4>
            <p class="text-muted mb-0">Your PDS has been auto-saved. Please review and submit for HR Manager approval.</p>
        </div>

        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>What happens after submission?</strong><br>
            Your HR Manager will review your Personal Data Sheet, may make corrections, and will either
            <strong>approve</strong>, <strong>request changes</strong>, or <strong>reject</strong> your submission.
            You will receive a notification with the outcome.
        </div>

        <div class="alert alert-warning mb-4">
            <i class="fas fa-lock me-2"></i>
            Once submitted, you <strong>cannot edit</strong> your PDS until HR responds.
        </div>

        ${<?php echo json_encode($hr_notes_block); ?>}
    `;
    }
    showStep(1);

    // Show the mini "Back to Top" button after scrolling down.
    const backToTopBtn = document.getElementById('backToTopBtn');
    const toggleBackToTop = () => {
        if (!backToTopBtn) return;
        backToTopBtn.style.display = window.scrollY > 200 ? 'inline-flex' : 'none';
        backToTopBtn.style.alignItems = 'center';
        backToTopBtn.style.justifyContent = 'center';
    };

    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    window.addEventListener('scroll', toggleBackToTop);
    toggleBackToTop();
});

<?php // Inline the copy/repeater functions from add-employee.php ?>
function copyResAddress() {
    const fields = ['house_no','street','subdivision','barangay','city','province','zip_code'];
    fields.forEach(f => {
        const src = document.querySelector('[name="res_'+f+'"]');
        const dst = document.getElementById('perm_'+f);
        if (src && dst) dst.value = src.value;
    });
}

function addRepeaterRow(containerId, prefix) {
    const c = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_surname[]" placeholder="Surname"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_first_name[]" placeholder="First Name"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="${prefix}_middle_name[]" placeholder="Middle Name"></div>
            <div class="col-md-3 mb-2"><input type="date" class="form-control form-control-sm" name="${prefix}_dob[]"></div>
        </div>`;
    c.appendChild(div);
}

function addSimpleRow(cid, name, ph) {
    const c = document.getElementById(cid);
    const div = document.createElement('div');
    div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <input type="text" class="form-control form-control-sm" name="${name}[]" placeholder="${ph}">`;
    c.appendChild(div);
}

function addEducationRow() {
    const c = document.getElementById('educationContainer');
    const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-2 mb-2"><select class="form-select form-select-sm" name="edu_level[]">
                <option>Elementary</option><option>Secondary</option><option>Vocational</option><option>College</option><option>Graduate Studies</option></select></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="edu_school[]" placeholder="School"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control form-control-sm" name="edu_degree[]" placeholder="Degree/Course"></div>
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="edu_from[]"></div>
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="edu_to[]"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control form-control-sm" name="edu_year_grad[]" placeholder="Year Grad"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="edu_honors[]" placeholder="Honors"></div>
        </div>`;
    c.appendChild(div);
}
function addWorkRow() {
    const c = document.getElementById('workContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="work_from[]"></div>
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="work_to[]"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="work_title[]" placeholder="Job Title"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="work_company[]" placeholder="Company"></div>
            <div class="col-md-2 mb-2"><input type="number" step="0.01" class="form-control form-control-sm" name="work_salary[]" placeholder="Salary"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="work_status[]" placeholder="Status"></div>
            <div class="col-md-4 mb-2"><input type="text" class="form-control form-control-sm" name="work_reason[]" placeholder="Reason for Leaving"></div>
        </div>`;
    c.appendChild(div);
}
function addTrainingRow() {
    const c = document.getElementById('trainingContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="training_from[]"></div>
            <div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="training_to[]"></div>
            <div class="col-md-3 mb-2"><input type="text" class="form-control form-control-sm" name="training_title[]" placeholder="Training Title"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control form-control-sm" name="training_type[]" placeholder="Type"></div>
            <div class="col-md-1 mb-2"><input type="number" class="form-control form-control-sm" name="training_hours[]" placeholder="Hrs"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control form-control-sm" name="training_conducted[]" placeholder="Conducted By"></div>
        </div>`;
    c.appendChild(div);
}
function addVoluntaryRow() {
    const c = document.getElementById('voluntaryContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="vol_from[]"></div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="vol_to[]"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="vol_org[]" placeholder="Organization"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="vol_address[]" placeholder="Address"></div>
            <div class="col-md-1"><input type="number" class="form-control form-control-sm" name="vol_hours[]" placeholder="Hrs"></div>
            <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="vol_position[]" placeholder="Position/Nature"></div>
        </div>`;
    c.appendChild(div);
}
function addEligibilityRow() {
    const c = document.getElementById('eligibilityContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="elig_title[]" placeholder="License/Cert Title"></div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="elig_from[]"></div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="elig_to[]"></div>
            <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="elig_number[]" placeholder="License No."></div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="elig_exam_date[]"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="elig_exam_place[]" placeholder="Place of Exam"></div>
        </div>`;
    c.appendChild(div);
}
function addRealPropertyRow() {
    const c = document.getElementById('realPropContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="rprop_desc[]" placeholder="Description"></div>
            <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="rprop_kind[]" placeholder="Kind"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="rprop_location[]" placeholder="Location"></div>
            <div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm" name="rprop_assessed[]" placeholder="Assessed Value"></div>
            <div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm" name="rprop_market[]" placeholder="Market Value"></div>
        </div>`;
    c.appendChild(div);
}
function addPersonalPropertyRow() {
    const c = document.getElementById('personalPropContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="pprop_desc[]" placeholder="Description"></div>
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="pprop_year[]" placeholder="Year Acquired"></div>
            <div class="col-md-4"><input type="number" step="0.01" class="form-control form-control-sm" name="pprop_cost[]" placeholder="Acquisition Cost"></div>
        </div>`;
    c.appendChild(div);
}
function addLiabilityRow() {
    const c = document.getElementById('liabilitiesContainer'); const div = document.createElement('div'); div.className = 'repeater-row';
    div.innerHTML = `<button type="button" class="btn-remove-row" onclick="this.closest('.repeater-row').remove()"><i class="fas fa-times"></i></button>
        <div class="row">
            <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="liab_nature[]" placeholder="Nature of Liability"></div>
            <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="liab_creditor[]" placeholder="Creditor"></div>
            <div class="col-md-3"><input type="number" step="0.01" class="form-control form-control-sm" name="liab_balance[]" placeholder="Balance"></div>
        </div>`;
    c.appendChild(div);
}
function toggleDetails(cb, divId) {
    const d = document.getElementById(divId);
    if (d) d.classList.toggle('show', cb.checked);
}
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('profilePreview').src = e.target.result;
            document.getElementById('profilePreviewContainer').style.display = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
