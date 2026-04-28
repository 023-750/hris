<?php
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Personal Data Sheet is no longer part of the Employee Portal.');
