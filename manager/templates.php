<?php
$page_title = 'Evaluation Templates';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Handle archive action
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $tid = (int)$_GET['archive'];
    $conn->query("UPDATE evaluation_templates SET status = 'Archived' WHERE template_id = $tid");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $tid, 'Archived evaluation template');
    redirectWith(BASE_URL . '/manager/templates.php', 'success', 'Template archived successfully.');
}

// Handle independent delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    $usage = $conn->query("SELECT COUNT(*) as cnt FROM evaluations WHERE template_id = $tid")->fetch_assoc()['cnt'];
    if ($usage > 0) {
        redirectWith(BASE_URL . '/manager/templates.php', 'danger', "Cannot delete template. It is being used in $usage evaluation(s).");
    } else {
        $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $tid");
        $conn->query("DELETE FROM evaluation_templates WHERE template_id = $tid");
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Template', $tid, 'Deleted evaluation template');
        redirectWith(BASE_URL . '/manager/templates.php', 'success', 'Template deleted successfully.');
    }
}

// Handle batch delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_delete' && isset($_POST['template_ids'])) {
    $ids = $_POST['template_ids'];
    $success = 0;
    $failed = 0;
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $tid = (int)$id;
            $usage = $conn->query("SELECT COUNT(*) as cnt FROM evaluations WHERE template_id = $tid")->fetch_assoc()['cnt'];
            if ($usage > 0) {
                $failed++;
            } else {
                $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $tid");
                $conn->query("DELETE FROM evaluation_templates WHERE template_id = $tid");
                logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Template', $tid, 'Deleted evaluation template via batch');
                $success++;
            }
        }
    }
    $msg = "$success template(s) deleted successfully.";
    if ($failed > 0) $msg .= " $failed template(s) could not be deleted because they are in use.";
    redirectWith(BASE_URL . '/manager/templates.php', $failed > 0 ? 'warning' : 'success', $msg);
}

require_once '../includes/header.php';

// Fetch active templates with criteria counts
$templates = $conn->query("SELECT et.*, u.full_name as created_by_name,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id AND section='KRA') as kra_count,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id AND section='Behavior') as behavior_count,
    (SELECT SUM(weight) FROM evaluation_criteria WHERE template_id = et.template_id AND section='KRA') as kra_total_weight,
    (SELECT COUNT(*) FROM evaluations WHERE template_id = et.template_id AND deleted_at IS NULL) as usage_count
    FROM evaluation_templates et
    LEFT JOIN users u ON et.created_by = u.user_id
    WHERE et.status = 'Active'
    ORDER BY et.updated_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0"><i class="fas fa-file-alt me-1"></i>Manage performance evaluation templates</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-danger d-none shadow-sm" id="batchDeleteBtn" onclick="confirmBatchDelete()">
            <i class="fas fa-trash-alt me-1"></i>Batch Delete (<span id="deleteCount">0</span>)
        </button>
        <a href="<?php echo BASE_URL; ?>/manager/template-archive.php" class="btn btn-outline-secondary">
            <i class="fas fa-archive me-1"></i>Archive
        </a>
        <a href="<?php echo BASE_URL; ?>/manager/create-template.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Template
        </a>
    </div>
</div>

<?php if ($templates->num_rows === 0): ?>
    <div class="chart-card fadeup">
        <div class="card-body text-center py-5">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;color:#388e3c;margin-bottom:16px;">
                <i class="fas fa-file-alt"></i>
            </div>
            <h5 class="text-muted mb-2">No Active Templates</h5>
            <p class="text-muted small mb-4">Create your first evaluation template to get started.</p>
            <a href="<?php echo BASE_URL; ?>/manager/create-template.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Template
            </a>
        </div>
    </div>
<?php else: ?>
    <form method="POST" action="" id="batchDeleteForm">
        <input type="hidden" name="action" value="batch_delete">
        <div class="row g-4">
            <?php while ($t = $templates->fetch_assoc()):
            $kra_w = (float)($t['kra_total_weight'] ?? 0);
            $wclass = abs($kra_w - 100) < 0.01 ? 'bg-success' : 'bg-warning text-dark';
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="chart-card fadeup h-100 position-relative" style="transition:transform 0.2s,box-shadow 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <!-- Checkbox for Batch Delete -->
                    <div class="position-absolute" style="top: 15px; right: 15px; z-index: 10;">
                        <input class="form-check-input template-checkbox shadow-sm border-secondary cursor-pointer" type="checkbox" name="template_ids[]" value="<?php echo $t['template_id']; ?>" style="width: 1.3rem; height: 1.3rem;" onchange="toggleBatchDeleteBtn()">
                    </div>
                    <div class="card-body p-4 pt-5">
                        <!-- Top -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:flex;align-items:center;justify-content:center;color:#2e7d32;font-size:1.1rem;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="d-flex gap-1">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2" style="font-size:0.65rem;">
                                    <?php echo e($t['evaluation_type'] ?? 'Annual'); ?>
                                </span>
                                <span class="badge bg-info-subtle text-info border px-2" style="font-size:0.65rem;">
                                    <?php echo e($t['target_position'] ?: 'General'); ?>
                                </span>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3" style="line-height:1.5;">
                            <?php echo e(substr($t['description'] ?? 'No description.', 0, 80)); ?><?php echo strlen($t['description'] ?? '') > 80 ? '...' : ''; ?>
                        </p>

                        <!-- Stats -->
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <span class="badge bg-success-subtle text-success border px-2" style="font-size:0.7rem;">
                                <i class="fas fa-bullseye me-1"></i><?php echo $t['kra_count']; ?> KRA
                            </span>
                            <span class="badge bg-primary-subtle text-primary border px-2" style="font-size:0.7rem;">
                                <i class="fas fa-heart me-1"></i><?php echo $t['behavior_count']; ?> Behavior
                            </span>
                            <span class="badge <?php echo $wclass; ?> px-2" style="font-size:0.7rem;">
                                <i class="fas fa-balance-scale me-1"></i><?php echo $kra_w; ?>%
                            </span>
                            <span class="badge bg-secondary px-2" style="font-size:0.7rem;">
                                <i class="fas fa-chart-bar me-1"></i><?php echo $t['usage_count']; ?> used
                            </span>
                        </div>

                        <!-- Weight split -->
                        <div class="d-flex gap-1 mb-3" style="height:6px;">
                            <div style="flex:<?php echo $t['kra_weight'] ?? 80; ?>;background:linear-gradient(90deg,#2e7d32,#4caf50);border-radius:3px;" title="KRA <?php echo $t['kra_weight'] ?? 80; ?>%"></div>
                            <div style="flex:<?php echo $t['behavior_weight'] ?? 20; ?>;background:linear-gradient(90deg,#1565c0,#42a5f5);border-radius:3px;" title="Behavior <?php echo $t['behavior_weight'] ?? 20; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:0.65rem;color:#888;">
                            <span>KRA <?php echo $t['kra_weight'] ?? 80; ?>%</span>
                            <span>Behavior <?php echo $t['behavior_weight'] ?? 20; ?>%</span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top p-3">
                        <div class="d-flex gap-2">
                            <a href="<?php echo BASE_URL; ?>/manager/edit-template.php?id=<?php echo $t['template_id']; ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="setArchiveTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')" data-bs-toggle="modal" data-bs-target="#archiveModal" title="Archive">
                                <i class="fas fa-archive"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="setDeleteTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')" data-bs-toggle="modal" data-bs-target="#deleteModal" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    </form>
<?php endif; ?>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="fas fa-archive me-2"></i>Archive Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <div class="mb-3"><div style="width:60px;height:60px;border-radius:50%;background:#fff9c4;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-archive fa-2x text-warning"></i></div></div>
                <p>Archive <strong id="archiveTemplateName"></strong>?</p>
                <p class="text-muted small">It will no longer appear in available templates.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="archiveConfirmBtn" class="btn btn-warning"><i class="fas fa-archive me-1"></i>Archive</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Delete Template</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <div class="mb-3"><div style="width:60px;height:60px;border-radius:50%;background:#ffebee;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-trash-alt fa-2x text-danger"></i></div></div>
                <p>Are you sure you want to permanently delete <strong id="deleteTemplateName"></strong>?</p>
                <p class="text-danger small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone. Templates currently in use by evaluations cannot be deleted.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Yes, Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function setArchiveTarget(id, name) {
    document.getElementById('archiveTemplateName').textContent = name;
    document.getElementById('archiveConfirmBtn').href = '?archive=' + id;
}

function setDeleteTarget(id, name) {
    document.getElementById('deleteTemplateName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = '?delete=' + id;
}

function toggleBatchDeleteBtn() {
    const checkboxes = document.querySelectorAll('.template-checkbox:checked');
    const deleteBtn = document.getElementById('batchDeleteBtn');
    const deleteCount = document.getElementById('deleteCount');
    if (checkboxes.length > 0) {
        deleteBtn.classList.remove('d-none');
        deleteCount.textContent = checkboxes.length;
    } else {
        deleteBtn.classList.add('d-none');
    }
}

function confirmBatchDelete() {
    if (confirm("Are you sure you want to delete all selected templates? Templates currently in use by evaluations will be safely skipped.")) {
        document.getElementById('batchDeleteForm').submit();
    }
}
</script>

<style>
.bg-primary-subtle { background-color: #e3f2fd; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-info-subtle { background-color: #e0f7fa; }
</style>

<?php require_once '../includes/footer.php'; ?>
