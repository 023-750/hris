<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Notification 1: Employee-related
createNotification($conn, 4, "Employee Portal Test", "This should only appear in the Employee Portal.", BASE_URL . "/employee/my-employment.php");

// Notification 2: HR-related
createNotification($conn, 4, "HR Portal Test", "This should only appear in the HR/Staff Portal.", BASE_URL . "/staff/evaluation-history.php");

echo "Test notifications created for User 4.\n";
?>
