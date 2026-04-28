<?php
/**
 * Common Header - includes navbar, sidebar, and CDN links
 * Usage: include this file at the top of every dashboard page
 * Requires: $page_title (string), session must be active
 */

require_once __DIR__ . '/functions.php';

// Get dynamic branding settings
$sys_pawnshop_name = getSetting($conn, 'company_name', 'Raquel Pawnshop');
$sys_logo = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png');

// Determine current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$effective_role = $_SESSION['role'] ?? '';

// Notifications are strictly account-based but now isolated by portal context.
$notif_context = ($current_dir === 'employee') ? 'employee' : 'hr';
$notif_count = getUnreadNotificationCount($conn, (int)$_SESSION['user_id'], $notif_context);
$notifications = getRecentNotifications($conn, (int)$_SESSION['user_id'], 5, $notif_context);

// 1. Get profile picture from the linked EMPLOYEE account
$stmt = $conn->prepare("
    SELECT e.profile_picture 
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.employee_id 
    WHERE u.user_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$display_avatar = getEmployeeAvatar(''); // Default
if ($row = $res->fetch_assoc()) {
    $display_avatar = getEmployeeAvatar($row['profile_picture']);
}
$stmt->close();

// Define sidebar menus per role
$sidebar_menus = [];

switch ($effective_role) {
    case 'Admin':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/admin/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'MANAGEMENT' => [
                ['icon' => 'fas fa-id-badge', 'label' => 'Member List', 'url' => BASE_URL . '/admin/members.php', 'page' => 'members.php'],
                ['icon' => 'fas fa-user-lock', 'label' => 'Portal Accounts', 'url' => BASE_URL . '/admin/employee-accounts.php', 'page' => 'employee-accounts.php'],
                ['icon' => 'fas fa-users', 'label' => 'User Management', 'url' => BASE_URL . '/admin/users.php', 'page' => 'users.php'],
                ['icon' => 'fas fa-clipboard-list', 'label' => 'Audit Trail', 'url' => BASE_URL . '/admin/audit-trail.php', 'page' => 'audit-trail.php'],
            ],
            'SYSTEM' => [
                ['icon' => 'fas fa-database', 'label' => 'System Backup', 'url' => BASE_URL . '/admin/backup.php', 'page' => 'backup.php'],
                ['icon' => 'fas fa-cogs', 'label' => 'System Config', 'url' => BASE_URL . '/admin/config.php', 'page' => 'config.php'],
            ],
        ];
        break;

    case 'HR Manager':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/manager/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EMPLOYEES' => [
                ['icon' => 'fas fa-users', 'label' => 'Employees', 'url' => BASE_URL . '/manager/employees.php', 'page' => 'employees.php'],
                ['icon' => 'fas fa-user-plus', 'label' => 'Add Employee', 'url' => BASE_URL . '/manager/add-employee.php', 'page' => 'add-employee.php'],
                ['icon' => 'fas fa-inbox', 'label' => 'PDS Submissions', 'url' => BASE_URL . '/manager/pds-submissions.php', 'page' => 'pds-submissions.php'],
            ],
            'ORGANIZATION' => [
                ['icon' => 'fas fa-building', 'label' => 'Branches', 'url' => BASE_URL . '/manager/branches.php', 'page' => 'branches.php'],
                ['icon' => 'fas fa-sitemap', 'label' => 'Departments', 'url' => BASE_URL . '/manager/departments.php', 'page' => 'departments.php'],
            ],
            'EVALUATIONS' => [
                ['icon' => 'fas fa-file-alt', 'label' => 'Templates', 'url' => BASE_URL . '/manager/templates.php', 'page' => 'templates.php'],
                ['icon' => 'fas fa-plus-circle', 'label' => 'Create Template', 'url' => BASE_URL . '/manager/create-template.php', 'page' => 'create-template.php'],
                ['icon' => 'fas fa-check-double', 'label' => 'Pending Approvals', 'url' => BASE_URL . '/manager/pending-approvals.php', 'page' => 'pending-approvals.php'],
                ['icon' => 'fas fa-history', 'label' => 'Evaluation History', 'url' => BASE_URL . '/manager/evaluation-history.php', 'page' => 'evaluation-history.php'],
            ],
            'APPROVALS' => [
                ['icon' => 'fas fa-route', 'label' => 'Career Movement', 'url' => BASE_URL . '/manager/career-movement-approval.php', 'page' => 'career-movement-approval.php'],
            ],
            'ANALYTICS' => [
                ['icon' => 'fas fa-chart-bar', 'label' => 'Analytics', 'url' => BASE_URL . '/manager/analytics.php', 'page' => 'analytics.php'],
                ['icon' => 'fas fa-file-pdf', 'label' => 'Reports', 'url' => BASE_URL . '/manager/reports.php', 'page' => 'reports.php'],
            ],
            'SETTINGS' => [
                ['icon' => 'fas fa-user-cog', 'label' => 'Profile & Settings', 'url' => BASE_URL . '/manager/profile-settings.php', 'page' => 'profile-settings.php'],
            ],
        ];
        break;

    case 'HR Supervisor':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/supervisor/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EMPLOYEES' => [
                ['icon' => 'fas fa-address-book', 'label' => 'Employee Info', 'url' => BASE_URL . '/supervisor/employees.php', 'page' => 'employees.php'],
                ['icon' => 'fas fa-search', 'label' => 'Search Employees', 'url' => BASE_URL . '/supervisor/search-employees.php', 'page' => 'search-employees.php'],
            ],
            'EVALUATIONS' => [
                ['icon' => 'fas fa-clipboard-check', 'label' => 'Pending Validations', 'url' => BASE_URL . '/supervisor/pending-endorsements.php', 'page' => 'pending-endorsements.php'],
                ['icon' => 'fas fa-history', 'label' => 'Evaluation History', 'url' => BASE_URL . '/supervisor/evaluation-history.php', 'page' => 'evaluation-history.php'],
                ['icon' => 'fas fa-file-alt', 'label' => 'Template Viewing', 'url' => BASE_URL . '/supervisor/templates.php', 'page' => 'templates.php'],
            ],
            'CAREER' => [
                ['icon' => 'fas fa-exchange-alt', 'label' => 'Career Movements', 'url' => BASE_URL . '/supervisor/career-movements.php', 'page' => 'career-movements.php'],
                ['icon' => 'fas fa-plus-circle', 'label' => 'Log Movement', 'url' => BASE_URL . '/supervisor/log-movement.php', 'page' => 'log-movement.php'],
            ],
            'SETTINGS' => [
                ['icon' => 'fas fa-user-cog', 'label' => 'Profile & Settings', 'url' => BASE_URL . '/supervisor/profile-settings.php', 'page' => 'profile-settings.php'],
            ],
        ];
        break;

    case 'HR Staff':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/staff/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'EVALUATIONS' => [
                ['icon' => 'fas fa-edit', 'label' => 'Submit Evaluation', 'url' => BASE_URL . '/staff/submit-evaluation.php', 'page' => 'submit-evaluation.php'],
                ['icon' => 'fas fa-file-alt', 'label' => 'My Drafts', 'url' => BASE_URL . '/staff/my-drafts.php', 'page' => 'my-drafts.php'],
                ['icon' => 'fas fa-paper-plane', 'label' => 'My Submissions', 'url' => BASE_URL . '/staff/my-submissions.php', 'page' => 'my-submissions.php'],
            ],
            'SEARCH' => [
                ['icon' => 'fas fa-search', 'label' => 'Employee Search', 'url' => BASE_URL . '/staff/search-employees.php', 'page' => 'search-employees.php'],
            ],
            'VIEWING' => [
                ['icon' => 'fas fa-file-alt', 'label' => 'Template Viewing', 'url' => BASE_URL . '/staff/templates.php', 'page' => 'templates.php'],
                ['icon' => 'fas fa-route', 'label' => 'Career History', 'url' => BASE_URL . '/staff/career-history.php', 'page' => 'career-history.php'],
            ],
            'SETTINGS' => [
                ['icon' => 'fas fa-user-cog', 'label' => 'Profile & Settings', 'url' => BASE_URL . '/staff/profile-settings.php', 'page' => 'profile-settings.php'],
            ],
        ];
        break;

    case 'Employee':
        $sidebar_menus = [
            'MAIN' => [
                ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/employee/dashboard.php', 'page' => 'dashboard.php'],
            ],
            'SELF SERVICE' => [
                ['icon' => 'fas fa-briefcase', 'label' => 'My Employment', 'url' => BASE_URL . '/employee/my-employment.php', 'page' => 'my-employment.php'],
                ['icon' => 'fas fa-star', 'label' => 'Self Rating', 'url' => BASE_URL . '/employee/self-rating.php', 'page' => 'self-rating.php'],
                ['icon' => 'fas fa-bell', 'label' => 'Notifications', 'url' => BASE_URL . '/employee/notifications.php', 'page' => 'notifications.php'],
            ],
            'SETTINGS' => [
                ['icon' => 'fas fa-user-cog', 'label' => 'Change Password', 'url' => BASE_URL . '/employee/profile-settings.php', 'page' => 'profile-settings.php'],
            ],
        ];
        break;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title ?? 'Dashboard'); ?> - Raquel Pawnshop HRIS</title>
    <meta name="description" content="Raquel Pawnshop Human Resource Information System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/pjax.js?v=<?php echo time(); ?>"></script>
    <script>
        // Prevent FOUC for collapsed sidebar
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
        // Expose app base URL for shared JS utilities.
        window.APP_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
        window.NOTIF_CONTEXT = <?php echo json_encode($notif_context === 'employee' ? 'employee' : 'hr'); ?>;
    </script>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo BASE_URL . '/' . e($sys_logo); ?>" alt="Logo"
                style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; margin-bottom: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); background: white; border: 2px solid rgba(255,255,255,0.1);">
            <h2><?php echo e($sys_pawnshop_name); ?></h2>
            <?php if ($effective_role === 'Employee'): ?>
                <small>Your HRIS Employee Portal</small>
            <?php else: ?>
                <small>HRIS • <?php echo e($effective_role); ?></small>
            <?php endif; ?>
        </div>

        <nav class="sidebar-nav" id="sidebar-nav">
            <?php foreach ($sidebar_menus as $label => $items): ?>
                <div class="nav-label"><?php echo e($label); ?></div>
                <?php foreach ($items as $item): ?>
                    <?php
                    $classes = ($current_page === $item['page']) ? 'active' : '';
                    if (!empty($item['class']))
                        $classes .= ($classes ? ' ' : '') . $item['class'];
                    ?>
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $classes; ?>"
                        title="<?php echo e($item['label']); ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span class="nav-text"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="nav-label">ACCOUNT</div>
            <a href="<?php echo BASE_URL; ?>/logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Top Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="navbar-logo d-flex align-items-center gap-2">
                <img src="<?php echo BASE_URL . '/' . e($sys_logo); ?>" alt="Logo"
                    style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                <h1 class="page-title mb-0"><?php echo e($page_title ?? 'Dashboard'); ?></h1>
            </div>
        </div>

        <div class="nav-right">
            <!-- Notification Bell -->
            <div class="dropdown">
                <button class="notification-btn" data-bs-toggle="dropdown" aria-expanded="false" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notification-badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <div class="dropdown-header">
                        Notifications
                        <?php if ($notif_count > 0): ?>
                            <a href="#" onclick="markAllRead(); return false;" style="font-size:0.75rem;font-weight:400;">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <div class="p-3 text-center text-muted" style="font-size:0.85rem;">
                            <i class="fas fa-bell-slash d-block mb-2" style="font-size:1.5rem;opacity:0.3;"></i>
                            No notifications
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <a href="<?php echo e($notif['link'] ?? '#'); ?>"
                                class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <div class="notif-title"><?php echo e($notif['title']); ?></div>
                                <div class="notif-message"><?php echo e($notif['message']); ?></div>
                                <div class="notif-time"><?php echo formatDateTime($notif['created_at']); ?></div>
                            </a>
                        <?php endforeach; ?>

                        <?php 
                        $current_portal = basename(dirname($_SERVER['SCRIPT_NAME']));
                        // Use portal name as URL part, fallback to session role for others
                        if (in_array($current_portal, ['employee', 'staff', 'manager', 'supervisor', 'admin'])) {
                            $notif_url = BASE_URL . '/' . $current_portal . '/notifications.php';
                        } else {
                            $role_map = [
                                'Admin' => 'admin',
                                'HR Manager' => 'manager',
                                'HR Supervisor' => 'supervisor',
                                'HR Staff' => 'staff',
                                'Employee' => 'employee'
                            ];
                            $portal_name = $role_map[$_SESSION['role'] ?? 'Employee'] ?? 'employee';
                            $notif_url = BASE_URL . '/' . $portal_name . '/notifications.php';
                        }
                        ?>
                        <div class="dropdown-footer text-center p-2 border-top mt-1" style="background: var(--bg-gray);">
                            <a href="<?php echo $notif_url; ?>" class="text-decoration-none"
                                style="font-size: 0.85rem; font-weight: 600; color: var(--primary-blue);">
                                View All Notifications
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown user-dropdown">
                <button class="btn dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <img src="<?php echo $display_avatar . '?v=' . time(); ?>" alt="Avatar">
                    </div>
                    <span class="d-none d-md-inline"><?php echo e($_SESSION['full_name']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text"><small
                                class="text-muted"><?php echo e($_SESSION['role']); ?></small></span></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i
                                class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="main-content">
        <?php displayFlashMessage(); ?>
