<?php
// ============================================
// Session Validation
// ============================================

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If accessing employee folder, redirect to employee login
    if (strpos($_SERVER['REQUEST_URI'], '/employee/') !== false) {
        header("Location: " . BASE_URL . "/employee/index.php");
    } else {
        header("Location: " . BASE_URL . "/index.php");
    }
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Automatically apply scheduled career movements
require_once __DIR__ . '/functions.php';
applyPendingCareerMovements($conn);

/**
 * Check if current user has the required role.
 * 
 * @param array $allowed_roles Array of allowed role strings
 */
function checkRole($allowed_roles)
{
    $current_role = $_SESSION['role'] ?? '';

    if (!in_array($current_role, $allowed_roles, true)) {
        if (in_array('Employee', $allowed_roles, true)) {
            header("Location: " . BASE_URL . "/employee/index.php");
        } else {
            header("Location: " . BASE_URL . "/index.php");
        }
        exit();
    }
}
?>
