<?php
$page_title = 'Edit Contact Information';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eid <= 0) redirectWith(BASE_URL . '/supervisor/employees.php', 'danger', 'Invalid employee ID.');

$branch_id = $_SESSION['branch_id'];

// Fetch employee details and ensure they belong to the supervisor's branch
$stmt = $conn->prepare("SELECT e.*, b.branch_name, ec.telephone_number, ec.mobile_number, ec.personal_email 
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    WHERE e.employee_id = ? AND e.branch_id = ?");
$stmt->bind_param("ii", $eid, $branch_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) redirectWith(BASE_URL . '/supervisor/employees.php', 'danger', 'Employee not found or access denied.');

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['personal_email'] ?? '');
    $mobile = trim($_POST['mobile_number'] ?? '');
    $tel = trim($_POST['telephone_number'] ?? '');

    // Residential Address
    $res_h = trim($_POST['res_house_no'] ?? '');
    $res_s = trim($_POST['res_street'] ?? '');
    $res_b = trim($_POST['res_barangay'] ?? '');
    $res_c = trim($_POST['res_city'] ?? '');
    $res_p = trim($_POST['res_province'] ?? '');
    $res_z = trim($_POST['res_zip_code'] ?? '');

    // Permanent Address
    $perm_h = trim($_POST['perm_house_no'] ?? '');
    $perm_s = trim($_POST['perm_street'] ?? '');
    $perm_b = trim($_POST['perm_barangay'] ?? '');
    $perm_c = trim($_POST['perm_city'] ?? '');
    $perm_p = trim($_POST['perm_province'] ?? '');
    $perm_z = trim($_POST['perm_zip_code'] ?? '');

    // Emergency Contact
    $en = trim($_POST['emergency_contact_name'] ?? '');
    $er = trim($_POST['emergency_contact_relationship'] ?? '');
    $ecn = trim($_POST['emergency_contact_number'] ?? '');

    $conn->begin_transaction();
    try {
        // Update Contacts
        $updContacts = $conn->prepare("INSERT INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE personal_email=?, mobile_number=?, telephone_number=?");
        $updContacts->bind_param("issssss", $eid, $email, $mobile, $tel, $email, $mobile, $tel);
        $updContacts->execute();

        // Update Residential Address
        $updRes = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, barangay, city, province, zip_code) VALUES (?, 'Residential', ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE house_no=?, street=?, barangay=?, city=?, province=?, zip_code=?");
        $updRes->bind_param("isssssssssssss", $eid, $res_h, $res_s, $res_b, $res_c, $res_p, $res_z, $res_h, $res_s, $res_b, $res_c, $res_p, $res_z);
        $updRes->execute();

        // Update Permanent Address
        $updPerm = $conn->prepare("INSERT INTO employee_addresses (employee_id, address_type, house_no, street, barangay, city, province, zip_code) VALUES (?, 'Permanent', ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE house_no=?, street=?, barangay=?, city=?, province=?, zip_code=?");
        $updPerm->bind_param("isssssssssssss", $eid, $perm_h, $perm_s, $perm_b, $perm_c, $perm_p, $perm_z, $perm_h, $perm_s, $perm_b, $perm_c, $perm_p, $perm_z);
        $updPerm->execute();

        // Update Emergency Contact
        $updEmerg = $conn->prepare("INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE contact_name=?, relationship=?, contact_number=?");
        $updEmerg->bind_param("issssss", $eid, $en, $er, $ecn, $en, $er, $ecn);
        $updEmerg->execute();

        $conn->commit();
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee Contact', $eid, 'Updated contact and address information');
        
        // Use a persistent redirect or a fresh load to show the success message
        redirectWith(BASE_URL . '/supervisor/view-employee.php?id=' . $eid, 'success', 'Contact information updated successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating records: " . $e->getMessage();
    }
}

// Load current Address and Emergency data
$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();
$emerg = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid LIMIT 1")->fetch_assoc();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="<?php echo BASE_URL; ?>/supervisor/view-employee.php?id=<?php echo $eid; ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="fas fa-arrow-left"></i></a>
        <h4 class="mb-0">Edit Contact Information - <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></h4>
    </div>
</div>

<?php if(isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="content-card">
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <h6 class="text-primary fw-bold mb-0">Contact Details</h6>
                <div class="col-md-4">
                    <label class="form-label small">Personal Email</label>
                    <input type="email" name="personal_email" class="form-control" value="<?php echo e($emp['personal_email']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Mobile Number</label>
                    <input type="text" name="mobile_number" class="form-control" value="<?php echo e($emp['mobile_number']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Telephone Number</label>
                    <input type="text" name="telephone_number" class="form-control" value="<?php echo e($emp['telephone_number']); ?>">
                </div>

                <hr class="my-4">

                <div class="col-md-6">
                    <h6 class="text-primary fw-bold mb-3">Residential Address</h6>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label small">House No.</label><input type="text" name="res_house_no" class="form-control" value="<?php echo e($res_addr['house_no'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Street</label><input type="text" name="res_street" class="form-control" value="<?php echo e($res_addr['street'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Barangay</label><input type="text" name="res_barangay" class="form-control" value="<?php echo e($res_addr['barangay'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">City</label><input type="text" name="res_city" class="form-control" value="<?php echo e($res_addr['city'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Province</label><input type="text" name="res_province" class="form-control" value="<?php echo e($res_addr['province'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Zip Code</label><input type="text" name="res_zip_code" class="form-control" value="<?php echo e($res_addr['zip_code'] ?? ''); ?>"></div>
                    </div>
                </div>

                <div class="col-md-6 border-start">
                    <h6 class="text-primary fw-bold mb-3">Permanent Address</h6>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label small">House No.</label><input type="text" name="perm_house_no" class="form-control" value="<?php echo e($perm_addr['house_no'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Street</label><input type="text" name="perm_street" class="form-control" value="<?php echo e($perm_addr['street'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Barangay</label><input type="text" name="perm_barangay" class="form-control" value="<?php echo e($perm_addr['barangay'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">City</label><input type="text" name="perm_city" class="form-control" value="<?php echo e($perm_addr['city'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Province</label><input type="text" name="perm_province" class="form-control" value="<?php echo e($perm_addr['province'] ?? ''); ?>"></div>
                        <div class="col-6"><label class="form-label small">Zip Code</label><input type="text" name="perm_zip_code" class="form-control" value="<?php echo e($perm_addr['zip_code'] ?? ''); ?>"></div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="text-primary fw-bold mb-0">Emergency Contact</h6>
                <div class="col-md-4">
                    <label class="form-label small">Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo e($emerg['contact_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Relationship</label>
                    <input type="text" name="emergency_contact_relationship" class="form-control" value="<?php echo e($emerg['relationship'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Contact Number</label>
                    <input type="text" name="emergency_contact_number" class="form-control" value="<?php echo e($emerg['contact_number'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="mt-5 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-5"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="<?php echo BASE_URL; ?>/supervisor/view-employee.php?id=<?php echo $eid; ?>" class="btn btn-outline-secondary px-4 ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
