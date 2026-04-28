<?php
$page_title = 'System Configuration';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

// Handle Post Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settings_to_save = [
        'company_name' => $_POST['company_name'],
        'contact_email' => $_POST['contact_email'],
        'session_timeout' => $_POST['session_timeout'],
        'pwd_min_length' => $_POST['pwd_min_length'],
        'pwd_require_special' => isset($_POST['pwd_require_special']) ? '1' : '0',
        'pwd_require_number' => isset($_POST['pwd_require_number']) ? '1' : '0',
        'pwd_require_upper' => isset($_POST['pwd_require_upper']) ? '1' : '0',
        'system_logo' => $_POST['system_logo']
    ];

    $conn->begin_transaction();
    try {
        foreach ($settings_to_save as $key => $value) {
            updateSetting($conn, $key, $value);
        }
        $conn->commit();
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Settings', 0, 'Updated global system settings');
        redirectWith(BASE_URL . '/admin/config.php', 'success', 'System settings updated successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        redirectWith(BASE_URL . '/admin/config.php', 'danger', 'Failed to update settings: ' . $e->getMessage());
    }
}

require_once '../includes/header.php';

// Fetch all current settings
$settings_res = $conn->query("SELECT * FROM system_settings");
$current_settings = [];
while ($row = $settings_res->fetch_assoc()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="config-module">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">System Configuration</h2>
            <p class="text-muted mb-0">Manage global variables and security protocols</p>
        </div>
    </div>

    <form method="POST" action="">
        <div class="row g-4">
            <!-- General Settings -->
            <div class="col-lg-6">
                <div class="content-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-globe me-2"></i>General Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" 
                                   value="<?php echo e($current_settings['company_name'] ?? ''); ?>" required>
                            <div class="form-text">Used across the system in headers and reports.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">System Support Email</label>
                            <input type="email" class="form-control" name="contact_email" 
                                   value="<?php echo e($current_settings['contact_email'] ?? ''); ?>" required>
                            <div class="form-text">Primary contact for technical issues.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">System Logo</label>
                            <div class="d-flex align-items-center gap-3">
                                <?php 
                                $logo_src = BASE_URL . '/' . (isset($current_settings['system_logo']) ? $current_settings['system_logo'] : 'assets/img/logo/logo.png');
                                ?>
                                <img src="<?php echo $logo_src; ?>" 
                                     alt="Logo" class="img-thumbnail" style="height: 50px;">
                                <input type="text" class="form-control" name="system_logo" 
                                       value="<?php echo e($current_settings['system_logo'] ?? 'assets/img/logo/logo.png'); ?>">
                            </div>
                            <div class="form-text mt-1">Logo path relative to root directory.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Policies -->
            <div class="col-lg-6">
                <div class="content-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt me-2"></i>Security & Authentication</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Session Timeout (Minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" 
                                   value="<?php echo e($current_settings['session_timeout'] ?? '240'); ?>" min="5" max="1440">
                            <div class="form-text">Idle time before automatic logout.</div>
                        </div>

                        <h6 class="fw-bold mb-3"><i class="fas fa-key me-2"></i>Password Complexity</h6>
                        <div class="mb-3">
                            <label class="form-label">Minimum Character Length</label>
                            <input type="number" class="form-control" name="pwd_min_length" 
                                   value="<?php echo e($current_settings['pwd_min_length'] ?? '8'); ?>" min="6" max="32">
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_upper" id="reqUpper" 
                                   <?php echo ($current_settings['pwd_require_upper'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqUpper">Require Uppercase Letters</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_number" id="reqNumber" 
                                   <?php echo ($current_settings['pwd_require_number'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqNumber">Require Numbers</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_special" id="reqSpecial" 
                                   <?php echo ($current_settings['pwd_require_special'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqSpecial">Require Special Characters (#?!@$%^&*)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="col-12 mt-4 text-center">
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i>Save Global Configuration
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
