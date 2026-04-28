<?php
/**
 * Employee Self-Service Portal - Dedicated Login
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

// If already logged in as an Employee account, skip to dashboard
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Employee' && (int)($_SESSION['employee_id'] ?? 0) > 0) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, employee_id, username, email, password_hash, full_name, role, branch_id, is_active, first_login_completed
            FROM users
            WHERE username = ?
              AND employee_id IS NOT NULL
              AND role = 'Employee'
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated.';
            } elseif (password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_login_completed'] = (bool)($user['first_login_completed'] ?? false);

                logAudit($conn, $user['user_id'], 'LOGIN', 'User', $user['user_id'], 'Employee logged into ESS portal.');
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid credentials.';
            }
        } else {
            $error = 'Only Employee accounts can access the Employee Portal.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - Raquel Pawnshop HRIS</title>
    <meta name="description" content="Employee Self-Service login to Raquel Pawnshop Human Resource Information System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper ess-login">
        <div class="login-card">
            <div class="logo-section">
                <img src="<?php echo BASE_URL; ?>/assets/img/logo/logo.png" alt="Raquel Pawnshop Logo"
                    style="width:100px;height:100px;border-radius:14px;display:inline-block;object-fit:cover;box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 5px;">
                <h1>Raquel Pawnshop</h1>
                <p>Employee Self-Service Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"
                    style="border-radius:8px;font-size:0.9rem;">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Employee ID / Username</label>
                    <div class="input-group">
                        <span class="input-group-text"
                            style="border-radius:8px 0 0 8px;border:1.5px solid #dee2e6;border-right:none;background:#f8f9fa;">
                            <i class="fas fa-user" style="color:#6c757d;font-size:0.85rem;"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="e.g. 024-001"
                            value="<?php echo e($_POST['username'] ?? ''); ?>" required
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

                <div class="mb-4"></div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>

                <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary btn-sm w-100" style="border-radius:10px;">
                        <i class="fas fa-arrow-left me-2"></i>Admin / HR Login
                    </a>
                </div>
            </form>

            <div class="text-center mt-4">
                <small style="color:#adb5bd;">&copy; <?php echo date('Y'); ?> Raquel Pawnshop. All rights reserved.</small>
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
