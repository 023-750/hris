<?php
/**
 * Employee Portal — Change Password
 */
$page_title = 'Change Password';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$user_id = (int)$_SESSION['user_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        // Fetch current hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd  = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
            $upd->bind_param("si", $hash, $user_id);
            if ($upd->execute()) {
                logAudit($conn, $user_id, 'CHANGE_PASSWORD', 'User', $user_id, 'Employee changed their password.');
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
            $upd->close();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="content-card">
      <div class="card-header">
        <h5><i class="fas fa-key me-2 text-primary"></i>Change Password</h5>
      </div>
      <div class="card-body">
        <?php if ($success): ?>
          <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Current Password <span class="text-danger">*</span></label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password <span class="text-danger">*</span></label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
            <div class="form-text">Minimum 6 characters.</div>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-2"></i>Update Password
          </button>
        </form>

        <div class="mt-3 text-center">
          <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
