<?php
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWith(BASE_URL . '/manager/career-movement-approval.php', 'danger', 'Invalid request method.');
}

$movement_id = isset($_POST['movement_id']) ? (int) $_POST['movement_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($movement_id <= 0 || !in_array($action, ['Approve', 'Reject'])) {
    redirectWith(BASE_URL . '/manager/career-movement-approval.php', 'danger', 'Invalid movement data.');
}

// Fetch the movement
$stmt = $conn->prepare("SELECT cm.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM career_movements cm
    JOIN employees e ON cm.employee_id = e.employee_id
    WHERE cm.movement_id = ? AND cm.approval_status = 'Pending'");
$stmt->bind_param("i", $movement_id);
$stmt->execute();
$movement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$movement) {
    redirectWith(BASE_URL . '/manager/career-movement-approval.php', 'danger', 'Movement not found or already processed.');
}

$status = ($action === 'Approve') ? 'Approved' : 'Rejected';

// Update movement status
$updateStmt = $conn->prepare("UPDATE career_movements SET approval_status = ?, approved_by = ?, is_applied = 0 WHERE movement_id = ?");
$updateStmt->bind_param("sii", $status, $_SESSION['user_id'], $movement_id);

if ($updateStmt->execute()) {
    // If Approved and effective_date is today or past, apply immediately
    if ($status === 'Approved') {
        $today = date('Y-m-d');
        $eff_date = $movement['effective_date'];
        $emp_id = $movement['employee_id'];
        $new_pos = $conn->real_escape_string($movement['new_position']);

        if ($eff_date <= $today) {
            if (!empty($movement['new_branch_id'])) {
                $new_branch = (int) $movement['new_branch_id'];
                $conn->query("UPDATE employees SET job_title='$new_pos', branch_id=$new_branch WHERE employee_id=$emp_id");
            } else {
                $conn->query("UPDATE employees SET job_title='$new_pos' WHERE employee_id=$emp_id");
            }
            $conn->query("UPDATE career_movements SET is_applied=1 WHERE movement_id=$movement_id");
        }
        // If future effective_date, applyPendingCareerMovements() in functions.php will handle it later
    }

    // Audit log
    $emp_name = $movement['employee_name'];
    logAudit(
        $conn,
        $_SESSION['user_id'],
        strtoupper($action),
        'Career Movement',
        $movement_id,
        "{$action}d {$movement['movement_type']} for {$emp_name} (effective: {$movement['effective_date']})"
    );

    // Notify the supervisor who logged it
    if (!empty($movement['logged_by'])) {
        createNotification(
            $conn,
            $movement['logged_by'],
            "Career Movement {$status}",
            "The {$movement['movement_type']} you logged for {$emp_name} has been {$status} by the HR Manager.",
            BASE_URL . '/supervisor/career-movements.php'
        );
    }

    // Notify the affected employee — strictly via their Employee-role account only.
    // getPreferredLinkedUserId with 'employee_portal' returns only role='Employee' accounts,
    // so HR Staff / Supervisor accounts linked to the same person are never targeted.
    $emp_notif_user_id = getPreferredLinkedUserId($conn, $movement['employee_id'], 'employee_portal');
    if ($emp_notif_user_id) {
        createNotification(
            $conn,
            $emp_notif_user_id,
            "Career Movement " . ($status === 'Approved' ? 'Approved' : 'Rejected'),
            "Your career movement ({$movement['movement_type']}) has been {$status} by the HR Manager.",
            BASE_URL . '/employee/notifications.php'
        );
    }

    $msg = $status === 'Approved'
        ? "Career movement approved. Employee record will be updated on or after {$movement['effective_date']}."
        : "Career movement has been rejected.";

    redirectWith(BASE_URL . '/manager/career-movement-approval.php', 'success', $msg);
} else {
    redirectWith(BASE_URL . '/manager/career-movement-approval.php', 'danger', 'Error processing career movement: ' . $conn->error);
}

$updateStmt->close();
