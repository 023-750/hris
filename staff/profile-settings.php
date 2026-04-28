<?php
$page_title = 'Profile & Settings';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/profile-settings-shared.php';
?>
