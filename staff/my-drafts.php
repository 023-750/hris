<?php
$page_title = 'My Drafts';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

$uid = $_SESSION['user_id'];

// Handle delete (MUST be before header.php to allow redirect)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Verify ownership
    $check = $conn->query("SELECT evaluation_id FROM evaluations WHERE evaluation_id = $did AND submitted_by = $uid AND status IN ('Draft', 'Returned')");
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM evaluation_scores WHERE evaluation_id = $did");
        $conn->query("DELETE FROM evaluations WHERE evaluation_id = $did");
        logAudit($conn, $uid, 'DELETE', 'Evaluation', $did, 'Deleted draft evaluation');
        redirectWith(BASE_URL . '/staff/my-drafts.php', 'success', 'Draft deleted successfully.');
    }
}

require_once '../includes/header.php';

// Fetch drafts
$drafts = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, et.template_name,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = ev.template_id) as total_criteria,
    (SELECT COUNT(*) FROM evaluation_scores WHERE evaluation_id = ev.evaluation_id AND score_value > 0) as filled_criteria
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.submitted_by = $uid AND ev.status IN ('Draft', 'Returned')
    ORDER BY ev.updated_at DESC");
?>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-file-alt me-2"></i>My Draft Evaluations</h5>
        <a href="<?php echo BASE_URL; ?>/staff/submit-evaluation.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i>New Evaluation
        </a>
    </div>
    <div class="card-body">
        <?php if ($drafts->num_rows === 0): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt d-block"></i>
                <p>No draft evaluations. Start a new one!</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php while ($draft = $drafts->fetch_assoc()):
                    $completion = ($draft['total_criteria'] > 0) ? round(($draft['filled_criteria'] / $draft['total_criteria']) * 100) : 0;
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="draft-card">
                            <div class="draft-title"><?php echo e($draft['employee_name']); ?></div>
                            <div class="draft-meta mb-2">
                                <i class="fas fa-file-alt me-1"></i><?php echo e($draft['template_name']); ?>
                            </div>
                            <div class="draft-meta mb-2">
                                <i class="fas fa-calendar me-1"></i>Last modified: <?php echo formatDateTime($draft['updated_at']); ?>
                            </div>
                            <?php if ($draft['status'] === 'Returned'): ?>
                                <div class="mb-2">
                                    <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size:0.7rem;">
                                        <i class="fas fa-undo me-1"></i>Returned for Revision
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Completion</small>
                                    <small><?php echo $completion; ?>%</small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width:<?php echo $completion; ?>%"></div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/staff/submit-evaluation.php?edit=<?php echo $draft['evaluation_id']; ?>" class="btn btn-sm <?php echo ($draft['status'] === 'Returned') ? 'btn-warning' : 'btn-primary'; ?> flex-fill fw-bold">
                                    <i class="fas fa-<?php echo ($draft['status'] === 'Returned') ? 'redo' : 'edit'; ?> me-1"></i>
                                    <?php echo ($draft['status'] === 'Returned') ? 'Revise' : 'Continue'; ?>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="setDeleteDraft(<?php echo $draft['evaluation_id']; ?>, '<?php echo e(addslashes($draft['employee_name'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#deleteDraftModal">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Delete Draft Modal -->
<div class="modal fade" id="deleteDraftModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Draft</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Delete draft for <strong id="deleteDraftName"></strong>?</p>
                <p class="text-danger small">This cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteDraftBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</a>
            </div>
        </div>
    </div>
</div>
<script>
function setDeleteDraft(id, name) {
    document.getElementById('deleteDraftName').textContent = name;
    document.getElementById('deleteDraftBtn').href = '?delete=' + id;
}
</script>
