<?php
$page_title = 'Log Career Movement';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/functions.php';

    $employee_id = (int)$_POST['employee_id'];
    $movement_type = $_POST['movement_type'] ?? '';
    $new_position = trim($_POST['new_position'] ?? '');
    $new_branch_id = !empty($_POST['new_branch_id']) ? (int)$_POST['new_branch_id'] : null;
    $effective_date = $_POST['effective_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Validate
    if (empty($employee_id) || empty($movement_type) || empty($new_position) || empty($effective_date) || empty($reason)) {
        redirectWith(BASE_URL . '/supervisor/log-movement.php', 'danger', 'All fields are required.');
    }

    // Get current employee info
    $emp = $conn->query("SELECT job_title, branch_id FROM employees WHERE employee_id = $employee_id")->fetch_assoc();
    $previous_position = $emp['job_title'] ?? '';
    $previous_branch_id = $emp['branch_id'] ?? null;

    $stmt = $conn->prepare("INSERT INTO career_movements (employee_id, movement_type, previous_position, new_position, previous_branch_id, new_branch_id, effective_date, reason, logged_by, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isssssssi", $employee_id, $movement_type, $previous_position, $new_position, $previous_branch_id, $new_branch_id, $effective_date, $reason, $_SESSION['user_id']);

    if ($stmt->execute()) {
        $movement_id = $stmt->insert_id;

        // Notify all HR Managers
        $managers = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
        $emp_name_q = $conn->query("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE employee_id = $employee_id")->fetch_assoc();
        while ($mgr = $managers->fetch_assoc()) {
            createNotification($conn, $mgr['user_id'], 'Career Movement Logged', "A {$movement_type} has been logged for {$emp_name_q['name']}.", BASE_URL . '/manager/career-movement-approval.php');
        }

        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Career Movement', $movement_id, "Logged {$movement_type} for {$emp_name_q['name']}");
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Career movement logged successfully.');
    } else {
        redirectWith(BASE_URL . '/supervisor/log-movement.php', 'danger', 'Failed to log movement. Try again.');
    }
    $stmt->close();
}

require_once '../includes/header.php';

// Fetch employees and branches
$employees = $conn->query("SELECT employee_id, first_name, last_name, job_title, branch_id FROM employees WHERE is_active = 1 ORDER BY last_name, first_name");
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
?>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-exchange-alt me-2"></i>Log Career Movement</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <!-- Select Employee -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                    <select class="form-select" name="employee_id" id="employeeSelect" required onchange="loadEmployeeInfo()">
                        <option value="">-- Choose Employee --</option>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <option value="<?php echo $emp['employee_id']; ?>"
                                    data-jobtitle="<?php echo e($emp['job_title']); ?>"
                                    data-branch="<?php echo $emp['branch_id']; ?>">
                                <?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div id="employeeInfoCard" style="display:none;" class="p-3 rounded" style="background:#f8f9fa;">
                        <p class="mb-1"><strong>Current Position:</strong> <span id="currentPosition"></span></p>
                        <p class="mb-0"><strong>Current Branch:</strong> <span id="currentBranch"></span></p>
                    </div>
                </div>
            </div>

            <!-- Movement Details -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Movement Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="movement_type" required>
                        <option value="">Select Type</option>
                        <option value="Promotion">Promotion</option>
                        <option value="Transfer">Transfer</option>
                        <option value="Demotion">Demotion</option>
                        <option value="Role Change">Role Change</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">New Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="new_position" required placeholder="e.g., Senior Appraiser">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">New Branch</label>
                    <select class="form-select" name="new_branch_id">
                        <option value="">Same Branch</option>
                        <?php while ($b = $branches->fetch_assoc()): ?>
                            <option value="<?php echo $b['branch_id']; ?>"><?php echo e($b['branch_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Effective Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="effective_date" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Reason / Justification <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="reason" rows="2" required placeholder="Explain the reason for this career movement..."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>/supervisor/career-movements.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Log Movement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Branch name lookup
const branchNames = {
    <?php
    $branches->data_seek(0);
    $bn = [];
    while ($b = $branches->fetch_assoc()) {
        $bn[] = $b['branch_id'] . ": '" . addslashes($b['branch_name']) . "'";
    }
    echo implode(', ', $bn);
    ?>
};

function loadEmployeeInfo() {
    const select = document.getElementById('employeeSelect');
    const option = select.options[select.selectedIndex];
    const card = document.getElementById('employeeInfoCard');

    if (option.value) {
        document.getElementById('currentPosition').textContent = option.dataset.jobtitle;
        document.getElementById('currentBranch').textContent = branchNames[option.dataset.branch] || 'N/A';
        card.style.display = 'block';
        card.style.background = '#f8f9fa';
    } else {
        card.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
