<?php
$page_title = 'Template Viewing';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch active templates with criteria count and total weight
$templates = $conn->query("SELECT et.*, u.full_name as created_by_name,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id) as criteria_count,
    (SELECT SUM(weight) FROM evaluation_criteria WHERE template_id = et.template_id) as total_weight
    FROM evaluation_templates et
    LEFT JOIN users u ON et.created_by = u.user_id
    WHERE et.status = 'Active' AND et.deleted_at IS NULL
    ORDER BY et.template_name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0"><i class="fas fa-lock me-1"></i> Browse evaluation templates and review scoring criteria. Read-only access.</p>
</div>

<?php if ($templates->num_rows === 0): ?>
    <div class="content-card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-alt fa-3x mb-3 text-muted opacity-25"></i>
            <h5 class="text-muted">No Active Templates</h5>
            <p class="text-muted small">There are currently no active evaluation templates available for viewing.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php while ($t = $templates->fetch_assoc()): 
            $tw = (float)($t['total_weight'] ?? 0);
            $wclass = abs($tw - 100) < 0.01 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-warning-subtle text-warning border-warning-subtle';
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="content-card h-100 template-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="template-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2"><?php echo e($t['target_position'] ?: 'General'); ?></span>
                        </div>
                        <h6 class="fw-bold text-dark mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3" style="line-height: 1.5;"><?php echo e(substr($t['description'] ?? 'No description provided.', 0, 120)); ?><?php echo strlen($t['description'] ?? '') > 120 ? '...' : ''; ?></p>
                        
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2">
                                <i class="fas fa-list-ul me-1"></i><?php echo $t['criteria_count']; ?> criteria
                            </span>
                            <span class="badge <?php echo $wclass; ?> border px-2">
                                <i class="fas fa-balance-scale me-1"></i><?php echo $tw; ?>%
                            </span>
                        </div>

                        <?php if (!empty($t['created_by_name'])): ?>
                            <div class="text-muted small mb-3">
                                <i class="fas fa-user-edit me-1 opacity-50"></i>Created by <?php echo e($t['created_by_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 p-4 pt-0">
                        <a href="<?php echo BASE_URL; ?>/staff/view-template.php?id=<?php echo $t['template_id']; ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<style>
.template-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border: 1.5px solid #f0f0f0; }
.template-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
.template-icon { 
    width: 44px; height: 44px; border-radius: 12px; 
    background: linear-gradient(135deg, #e7f1ff, #c9dfff); 
    display: flex; align-items: center; justify-content: center; 
    color: var(--primary-blue, #0d6efd); font-size: 1.1rem; 
}
.bg-info-subtle { background-color: #e0f7fa; }
.bg-secondary-subtle { background-color: #f5f5f5; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-warning-subtle { background-color: #fff9c4; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
</style>

<?php require_once '../includes/footer.php'; ?>
