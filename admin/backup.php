<?php
$page_title = 'System Backup';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

// Handle Download
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = dirname(__DIR__) . '/backups/' . $file;
    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = dirname(__DIR__) . '/backups/' . $file;
    if (file_exists($path)) {
        unlink($path);
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Backup', 0, 'Deleted backup file: ' . $file);
        redirectWith(BASE_URL . '/admin/backup.php', 'success', 'Backup deleted successfully.');
    }
}

require_once '../includes/header.php';

// Get all backups
$backup_dir = dirname(__DIR__) . '/backups/';
$files = array_diff(scandir($backup_dir), array('.', '..', '.htaccess'));
$backups = [];
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        $backups[] = [
            'name' => $file,
            'size' => filesize($backup_dir . $file),
            'date' => filemtime($backup_dir . $file)
        ];
    }
}

// Sort by date desc
usort($backups, function($a, $b) { return $b['date'] - $a['date']; });
?>

<div class="backup-module">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">System Backup & Recovery</h2>
            <p class="text-muted mb-0">Manage database snapshots and system exports</p>
        </div>
        <button class="btn btn-primary btn-lg" id="btnCreateBackup">
            <i class="fas fa-database me-2"></i>Create New Backup
        </button>
    </div>

    <!-- Stats Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-archive"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($backups); ?></h3>
                    <p>Stored Backups</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-hdd"></i></div>
                <div class="stat-info">
                    <?php 
                    $total_size = array_sum(array_column($backups, 'size')); 
                    ?>
                    <h3><?php echo formatSize($total_size); ?></h3>
                    <p>Total Backup Size</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-info">
                    <h3>Encrypted</h3>
                    <p>Storage Security</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup List -->
    <div class="content-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-history me-2"></i>Backup History</h5>
            <span class="badge bg-info">Auto-cleanup: Not Active</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="backupTable">
                    <thead class="table-light">
                        <tr>
                            <th>Backup Filename</th>
                            <th>Created Date</th>
                            <th>File Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="fas fa-box-open fa-3x mb-3 d-block opacity-25"></i>
                                    No backups found. Click "Create New Backup" to secure your data.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td><strong><i class="fas fa-file-code text-primary me-2"></i><?php echo e($b['name']); ?></strong></td>
                                    <td><?php echo formatDateTime(date('Y-m-d H:i:s', $b['date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo formatSize($b['size']); ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="?download=<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete('<?php echo e($b['name']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="backupProgressModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                <h4 class="fw-bold">Generating Backup...</h4>
                <p class="text-muted mb-0">Please wait while the system exports the database. This may take a few seconds.</p>
            </div>
        </div>
    </div>
</div>

<script>
const AJAX_URL = '<?php echo BASE_URL; ?>/includes/ajax/system-backup.php';

document.getElementById('btnCreateBackup').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('backupProgressModal'));
    modal.show();

    fetch(AJAX_URL, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            modal.hide();
            if (data.success) {
                location.reload();
            } else {
                alert('Backup Error: ' + (data.error || 'Unknown error occurred.'));
            }
        })
        .catch(err => {
            modal.hide();
            alert('A system error occurred while generating the backup.');
            console.error(err);
        });
});

function confirmDelete(filename) {
    if (confirm(`Are you sure you want to delete backup "${filename}"? This cannot be undone.`)) {
        window.location.href = `?delete=${encodeURIComponent(filename)}`;
    }
}
</script>

<?php 
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}
require_once '../includes/footer.php'; 
?>
