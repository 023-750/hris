<?php
require_once '../../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$employee_id = (int)($_POST['employee_id'] ?? 0);
$movement_type = $_POST['movement_type'] ?? 'Promotion';
$previous_position = $_POST['previous_position'] ?? '';
$new_position = $_POST['new_position'] ?? '';
$effective_date = $_POST['effective_date'] ?? date('Y-m-d');
$reason = $_POST['reason'] ?? '';
$new_branch_id = (int)($_POST['new_branch_id'] ?? 0);

if ($employee_id <= 0 || empty($new_position)) {
    echo json_encode(['success' => false, 'message' => 'Employee and target position are required.']);
    exit;
}

// Check if a similar movement is pending
$check = $conn->prepare("SELECT movement_id FROM career_movements WHERE employee_id = ? AND new_position = ? AND approval_status = 'Pending'");
$check->bind_param("is", $employee_id, $new_position);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A similar movement is already pending approval.']);
    exit;
}
$check->close();

// Insert movement (Directly Approve since HR Manager is doing it)
$stmt = $conn->prepare("INSERT INTO career_movements 
    (employee_id, movement_type, previous_position, new_position, new_branch_id, effective_date, reason, approval_status, approved_by, logged_by, is_applied) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Approved', ?, ?, 0)");

// If new_branch_id is 0, we should fetch current branch?
if ($new_branch_id <= 0) {
    $emp_q = $conn->query("SELECT branch_id FROM employees WHERE employee_id = $employee_id");
    if ($row = $emp_q->fetch_assoc()) {
        $new_branch_id = $row['branch_id'];
    }
}

$user_id = $_SESSION['user_id'];
$stmt->bind_param("isssissii", 
    $employee_id, 
    $movement_type, 
    $previous_position, 
    $new_position, 
    $new_branch_id,
    $effective_date, 
    $reason,
    $user_id,
    $user_id
);

if ($stmt->execute()) {
    $movement_id = $stmt->insert_id;
    
    // Trigger immediate apply if effective today
    applyPendingCareerMovements($conn);
    
    // Check if applied
    $check_applied = $conn->query("SELECT is_applied FROM career_movements WHERE movement_id = $movement_id")->fetch_assoc();
    $applied = $check_applied['is_applied'] ?? 0;

    logAudit($conn, $user_id, 'IMPLEMENT', 'Career Movement', $movement_id, "Implemented {$movement_type} to {$new_position}");

    echo json_encode(['success' => true, 'message' => $applied ? 'Movement implemented and applied immediately.' : 'Movement logged and scheduled for ' . $effective_date]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
