<?php
// ============================================
// Helper Functions
// ============================================

/**
 * Sanitize output to prevent XSS
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get employee profile picture URL with fallback
 */
function getEmployeeAvatar($profile_picture)
{
    $fallback = BASE_URL . '/assets/img/logo/logo.png';
    if (!empty($profile_picture)) {
        // Check standard employee folder
        $path = __DIR__ . '/../assets/img/employees/' . $profile_picture;
        if (file_exists($path)) {
            return BASE_URL . '/assets/img/employees/' . $profile_picture;
        }
        // Check sample images folder (for seed data support)
        $path_sample = __DIR__ . '/../assets/img/sample_images/' . $profile_picture;
        if (file_exists($path_sample)) {
            return BASE_URL . '/assets/img/sample_images/' . $profile_picture;
        }
    }
    return $fallback;
}

/**
 * Get the company-facing employee ID/code for display.
 */
function getEmployeeDisplayId($employee)
{
    if (is_array($employee) && !empty($employee['employee_code'])) {
        return (string) $employee['employee_code'];
    }

    if (is_array($employee) && !empty($employee['employee_id'])) {
        return (string) $employee['employee_id'];
    }

    return 'N/A';
}

/**
 * Create a notification for a user
 */
function createNotification($conn, $user_id, $title, $message, $link = null)
{
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $link);
    $stmt->execute();
    $stmt->close();
}

/**
 * Resolve the preferred linked user account for a specific portal context.
 * For the employee portal, only the explicit Employee account should be used.
 */
function getPreferredLinkedUserId($conn, $employee_id, $context = 'employee_portal')
{
    $employee_id = (int) $employee_id;
    if ($employee_id <= 0) {
        return null;
    }

    if ($context === 'employee_portal') {
        $stmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE employee_id = ? AND role = 'Employee' AND is_active = 1
            ORDER BY user_id ASC
            LIMIT 1
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE employee_id = ? AND is_active = 1
            ORDER BY user_id ASC
            LIMIT 1
        ");
    }

    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result ? (int) $result['user_id'] : null;
}

/**
 * Log an audit event
 */
function logAudit($conn, $user_id, $action_type, $entity_type, $entity_id = null, $details = null)
{
    // Ensure user_id actually exists to avoid Foreign Key constraint errors (e.g. after DB reset)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$user_exists) {
        $user_id = null; // Record as system/unknown if user doesn't exist
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action_type, $entity_type, $entity_id, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get unread notification count for current user, filtered by portal context
 * @param string $context 'employee' or 'hr'
 */
function getUnreadNotificationCount($conn, $user_id, $context = null)
{
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    if ($context === 'employee') {
        $sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";
    } elseif ($context === 'hr') {
        $sql .= " AND (link NOT LIKE '%/employee/%' OR link IS NULL OR link = '')";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'];
}

/**
 * Get recent notifications for a user, filtered by portal context
 * @param string $context 'employee' or 'hr'
 */
function getRecentNotifications($conn, $user_id, $limit = 5, $context = null)
{
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($context === 'employee') {
        $sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";
    } elseif ($context === 'hr') {
        $sql .= " AND (link NOT LIKE '%/employee/%' OR link IS NULL OR link = '')";
    }
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    return $notifications;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y')
{
    if (empty($date))
        return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A')
{
    if (empty($datetime))
        return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Get performance level badge class
 */
function getPerformanceBadgeClass($level)
{
    switch ($level) {
        case 'Outstanding':
            return 'bg-success';
        case 'Exceeds Expectations':
            return 'bg-info';
        case 'Meets Expectations':
            return 'bg-warning text-dark';
        case 'Needs Improvement':
            return 'bg-danger';
        // Legacy support
        case 'Excellent':
            return 'bg-success';
        case 'Above Average':
            return 'bg-info';
        case 'Average':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'Draft':
            return 'bg-secondary';
        case 'Pending Supervisor':
            return 'bg-warning text-dark';
        case 'Pending Manager':
            return 'bg-info';
        case 'Approved':
            return 'bg-success';
        case 'Rejected':
            return 'bg-danger';
        case 'Returned':
            return 'bg-purple';
        default:
            return 'bg-secondary';
    }
}

/**
 * Calculate performance level based on score
 */
function getPerformanceLevel($score)
{
    // HRD Form-013.01 rating scale (1.00-4.00)
    if ($score >= 3.60)
        return 'Outstanding';
    if ($score >= 2.60)
        return 'Exceeds Expectations';
    if ($score >= 2.00)
        return 'Meets Expectations';
    return 'Needs Improvement';
}

/**
 *  ================================================================================
 * Calculate evaluation total using: weight × rating × average
 *
 * Formula: total = (kra_subtotal × behavior_average) / 4.0
 *
 *   kra_subtotal    = Σ(criterion_weight/100 × rating)  ← encodes weight × rating
 *   behavior_average= avg of all behavior ratings        ← the "average" factor
 *   ÷ 4.0           = normalises the product to 1–4 scale
 *
 * Examples (with weights summing correctly):
 *   Perfect KRA (4.0) × Perfect behavior (4.0) / 4 = 4.00  → Outstanding
 *   Perfect KRA (4.0) × Avg behavior    (2.0) / 4 = 2.00  → Meets Expectations
 * ================================================================================
 */
function calculateEvalTotal($kra_subtotal, $behavior_average, $kra_weight = 80, $behavior_weight = 20)
{
    // ── ORIGINAL FORMULA (additive 80/20 weighted sum) ── COMMENTED OUT ──────────
    // To revert: uncomment the line below and remove / comment the NEW formula line.
    // return round(($kra_subtotal * $kra_weight / 100) + ($behavior_average * $behavior_weight / 100), 2);
    // ─────────────────────────────────────────────────────────────────────────────

    // NEW FORMULA — weight × rating × average  (÷ 4 keeps result on the 1–4 scale)
    return round(($kra_subtotal * $behavior_average) / 4.0, 2);
}

/**
 * Redirect with a flash message
 */
function redirectWith($url, $type, $message)
{
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
    header("Location: " . $url);
    exit();
}

/**
 * Apply approved career movements whose effective_date has arrived
 * Call this on any page load where employee status matters.
 */
function applyPendingCareerMovements($conn)
{
    $today = date('Y-m-d');
    $result = $conn->query("SELECT * FROM career_movements WHERE approval_status = 'Approved' AND is_applied = 0 AND effective_date <= '$today'");
    if (!$result || $result->num_rows === 0)
        return;
    while ($m = $result->fetch_assoc()) {
        $emp_id = $m['employee_id'];
        $new_pos = $conn->real_escape_string($m['new_position']);
        if (!empty($m['new_branch_id'])) {
            $new_branch = (int) $m['new_branch_id'];
            $conn->query("UPDATE employees SET job_title='$new_pos', branch_id=$new_branch WHERE employee_id=$emp_id");
        } else {
            $conn->query("UPDATE employees SET job_title='$new_pos' WHERE employee_id=$emp_id");
        }
        $conn->query("UPDATE career_movements SET is_applied=1 WHERE movement_id={$m['movement_id']}");
    }
}

/**
 * Display flash message if exists
 */
function displayFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type'], $_SESSION['flash_message']);
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">';
        echo e($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Get a single system setting by key
 */
function getSetting($conn, $key, $default = null)
{
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['setting_value'] : $default;
}

/**
 * Update a system setting
 */
function updateSetting($conn, $key, $value)
{
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
/**
 * Check if the login attempt should be blocked due to brute force
 */
function checkLoginBruteForce($conn, $identifier, $ip)
{
    $lockout_time = 5; // minutes
    $max_attempts = 5;
    $max_ip_attempts = 10;

    // Check by Identifier (Username/Email)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE identifier = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $identifier, $lockout_time);
    $stmt->execute();
    $id_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    if ($id_count >= $max_attempts) {
        return true;
    }

    // Check by IP
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $ip, $lockout_time);
    $stmt->execute();
    $ip_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    if ($ip_count >= $max_ip_attempts) {
        return true;
    }

    return false;
}

/**
 * Register a failed login attempt
 */
function registerLoginAttempt($conn, $identifier, $ip)
{
    $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)");
    $stmt->bind_param("ss", $identifier, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clear login attempts for a successful login
 */
function clearLoginAttempts($conn, $identifier, $ip)
{
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ? OR ip_address = ?");
    $stmt->bind_param("ss", $identifier, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
