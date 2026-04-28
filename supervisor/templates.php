<?php
$page_title = 'Evaluation Templates';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
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
    <p class="text-muted mb-0">Browse and review active evaluation templates used for performance assessments.</p>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-file-alt me-2"></i>Active Templates</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Template Name</th>
                        <th>Target Position</th>
                        <th>Description</th>
                        <th class="text-center">Criteria</th>
                        <th class="text-center">Weight</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($templates->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No active templates found.</td></tr>
                    <?php else: ?>
                        <?php while ($t = $templates->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($t['template_name']); ?></strong></td>
                                <td><span class="badge bg-info-subtle text-info border border-info-subtle px-2"><?php echo e($t['target_position'] ?: 'General'); ?></span></td>
                                <td><small class="text-muted"><?php echo e(substr($t['description'] ?? '', 0, 80)); ?><?php echo strlen($t['description'] ?? '') > 80 ? '...' : ''; ?></small></td>
                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2"><?php echo $t['criteria_count']; ?> criteria</span></td>
                                <td class="text-center">
                                    <?php
                                    $tw = (float)($t['total_weight'] ?? 0);
                                    $wclass = ($tw == 100) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle';
                                    ?>
                                    <span class="badge <?php echo $wclass; ?> px-2"><?php echo $tw; ?>%</span>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo BASE_URL; ?>/supervisor/view-template.php?id=<?php echo $t['template_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; }
.table td { padding: 16px; border-bottom: 1px solid #f8f9fa; }
.bg-info-subtle { background-color: #e0f7fa; }
.bg-secondary-subtle { background-color: #f5f5f5; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-warning-subtle { background-color: #fff9c4; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
</style>

<?php require_once '../includes/footer.php'; ?>
