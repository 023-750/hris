<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: /raquel-hris/admin/dashboard.php");
            break;
        case 'HR Manager':
            header("Location: /raquel-hris/manager/dashboard.php");
            break;
        case 'HR Supervisor':
            header("Location: /raquel-hris/supervisor/dashboard.php");
            break;
        case 'HR Staff':
            header("Location: /raquel-hris/staff/dashboard.php");
            break;
        case 'Employee':
            header("Location: /raquel-hris/employee/dashboard.php");
            break;
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    require_once 'includes/functions.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (checkLoginBruteForce($conn, $username, $ip)) {
        $error = 'Too many failed login attempts. Please try again in 15 minutes.';
    } elseif (empty($username)) {
        $error = 'Please enter your username.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } else {
        // Query user by username
        $stmt = $conn->prepare("SELECT user_id, employee_id, username, email, password_hash, full_name, role, branch_id, is_active, first_login_completed FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check if account is active
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } elseif (password_verify($password, $user['password_hash'])) {
                if (($user['role'] ?? '') === 'Employee') {
                    $error = 'Employee accounts must sign in through the Employee Self-Service Portal.';
                    $stmt->close();
                    goto render_login;
                }

                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['first_login_completed'] = (bool)($user['first_login_completed'] ?? false);

                // Clear brute force attempts on successful login
                clearLoginAttempts($conn, $username, $ip);

                // Log the login
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details, ip_address) VALUES (?, 'LOGIN', 'User', ?, 'User logged in successfully.', ?)");
                $logStmt->bind_param("iis", $user['user_id'], $user['user_id'], $ip);
                $logStmt->execute();
                $logStmt->close();
                
                // Notify Admins of successful login
                $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
                $adminStmt->execute();
                $admins = $adminStmt->get_result();
                while ($admin = $admins->fetch_assoc()) {
                    createNotification($conn, $admin['user_id'], 'Successful Login', $user['full_name'] . ' (' . $user['role'] . ') has logged in successfully from IP ' . $ip);
                }
                $adminStmt->close();

                // Redirect based on role
                switch ($user['role']) {
                    case 'Admin':
                        header("Location: /raquel-hris/admin/dashboard.php");
                        break;
                    case 'HR Manager':
                        header("Location: /raquel-hris/manager/dashboard.php");
                        break;
                    case 'HR Supervisor':
                        header("Location: /raquel-hris/supervisor/dashboard.php");
                        break;
                    case 'HR Staff':
                        header("Location: /raquel-hris/staff/dashboard.php");
                        break;
                    case 'Employee':
                        header("Location: /raquel-hris/employee/dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = 'Invalid username or password.';
                
                // Register failed attempt
                registerLoginAttempt($conn, $username, $ip);
                
                // Notify Admins of failed login (wrong password)
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
                $adminStmt->execute();
                $admins = $adminStmt->get_result();
                while ($admin = $admins->fetch_assoc()) {
                    createNotification($conn, $admin['user_id'], 'Security Alert: Failed Login', 'Failed login attempt for ' . $user['username'] . ' (' . $user['role'] . '). Incorrect password entry. IP: ' . $ip);
                }
                $adminStmt->close();
            }
        } else {
            $error = 'Invalid username or password.';
            
            // Register failed attempt
            registerLoginAttempt($conn, $username, $ip);
            
            // Notify Admins of failed login (invalid account)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
            $adminStmt->execute();
            $admins = $adminStmt->get_result();
            $failed_username = htmlspecialchars($_POST['username'] ?? 'Unknown');
            while ($admin = $admins->fetch_assoc()) {
                createNotification($conn, $admin['user_id'], 'Security Alert: Failed Login', 'Failed login attempt for ' . $failed_username . ' (Unknown Account). IP: ' . $ip);
            }
            $adminStmt->close();
        }
        $stmt->close();
    }
}

render_login:
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Raquel Pawnshop HRIS</title>
    <meta name="description" content="Login to Raquel Pawnshop Human Resource Information System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/raquel-hris/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-section">
                <img src="/raquel-hris/assets/img/logo/logo.png" alt="Raquel Pawnshop Logo"
                    style="width:100px;height:100px;border-radius:14px;display:inline-block;object-fit:cover;box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 5px;">
                <h1>Raquel Pawnshop</h1>
                <p>Human Resource Information System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"
                    style="border-radius:8px;font-size:0.9rem;">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"
                            style="border-radius:8px 0 0 8px;border:1.5px solid #dee2e6;border-right:none;background:#f8f9fa;">
                            <i class="fas fa-user" style="color:#6c757d;font-size:0.85rem;"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required
                            style="border-left:none;border-radius:0 8px 8px 0;">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group" style="position:relative;">
                        <span class="input-group-text"
                            style="border-radius:8px 0 0 8px;border:1.5px solid #dee2e6;border-right:none;background:#f8f9fa;">
                            <i class="fas fa-lock" style="color:#6c757d;font-size:0.85rem;"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter your password" required
                            style="border-left:none;border-radius:0 8px 8px 0;padding-right:40px;">
                        <button type="button" class="password-toggle" onclick="togglePassword()"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);z-index:5;">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
                <div class="text-center">
                    <a href="/raquel-hris/employee/index.php" class="btn btn-outline-secondary btn-sm w-100" style="border-radius:10px;">
                        <i class="fas fa-id-card me-2"></i>Employee Self-Service Login
                    </a>
                </div>
            </form>

            <div class="text-center mt-4">
                <small style="color:#adb5bd;">&copy; <?php echo date('Y'); ?> Raquel Pawnshop. All rights
                    reserved.</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
            }
        });
    </script>
</body>

</html>
