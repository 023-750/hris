<?php
$page_title = 'Edit Evaluation Template';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

$template_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$template_id) {
    redirectWith(BASE_URL . '/manager/templates.php', 'danger', 'Invalid template ID.');
}

// Fetch template
$tmpl = $conn->query("SELECT * FROM evaluation_templates WHERE template_id = $template_id")->fetch_assoc();
if (!$tmpl) {
    redirectWith(BASE_URL . '/manager/templates.php', 'danger', 'Template not found.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target_position = trim($_POST['target_position'] ?? '');
    $evaluation_type = $_POST['evaluation_type'] ?? 'Annual';
    $kra_weight = floatval($_POST['kra_weight'] ?? 80);
    $behavior_weight = floatval($_POST['behavior_weight'] ?? 20);
    $form_code = trim($_POST['form_code'] ?? '');
    $revision_date = $_POST['revision_date'] ?: null;
    $effective_date_form = $_POST['effective_date_form'] ?: null;

    $kra_names = $_POST['kra_name'] ?? [];
    $kra_descriptions = $_POST['kra_description'] ?? [];
    $kra_weights = $_POST['kra_weight_item'] ?? [];

    $beh_names = $_POST['behavior_name'] ?? [];
    $beh_kpis = $_POST['behavior_kpi'] ?? [];

    if (empty($template_name)) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'Template name is required.');
    }

    $kra_total_weight = array_sum(array_map('floatval', $kra_weights));
    if (!empty($kra_names) && abs($kra_total_weight - 100) > 0.01) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'KRA weights must total 100%. Current: ' . $kra_total_weight . '%');
    }

    if (abs(($kra_weight + $behavior_weight) - 100) > 0.01) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'KRA weight + Behavior weight must equal 100%.');
    }

    // Update template
    $stmt = $conn->prepare("UPDATE evaluation_templates SET template_name=?, description=?, target_position=?, evaluation_type=?, kra_weight=?, behavior_weight=?, form_code=?, revision_date=?, effective_date_form=? WHERE template_id=?");
    $stmt->bind_param("ssssddsssi", $template_name, $description, $target_position, $evaluation_type, $kra_weight, $behavior_weight, $form_code, $revision_date, $effective_date_form, $template_id);
    $stmt->execute();
    $stmt->close();

    // Delete old criteria and re-insert
    $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $template_id");

    $crit_stmt = $conn->prepare("INSERT INTO evaluation_criteria (template_id, section, criterion_name, description, weight, scoring_method, sort_order) VALUES (?, 'KRA', ?, ?, ?, 'Scale_1_4', ?)");
    for ($i = 0; $i < count($kra_names); $i++) {
        $name = trim($kra_names[$i]);
        $desc = trim($kra_descriptions[$i] ?? '');
        $weight = floatval($kra_weights[$i] ?? 0);
        $order = $i + 1;
        if (!empty($name)) {
            $crit_stmt->bind_param("issdi", $template_id, $name, $desc, $weight, $order);
            $crit_stmt->execute();
        }
    }
    $crit_stmt->close();

    $beh_stmt = $conn->prepare("INSERT INTO evaluation_criteria (template_id, section, criterion_name, kpi_description, weight, scoring_method, sort_order) VALUES (?, 'Behavior', ?, ?, 0, 'Scale_1_4', ?)");
    for ($i = 0; $i < count($beh_names); $i++) {
        $name = trim($beh_names[$i]);
        $kpi = trim($beh_kpis[$i] ?? '');
        $order = $i + 1;
        if (!empty($name)) {
            $beh_stmt->bind_param("issi", $template_id, $name, $kpi, $order);
            $beh_stmt->execute();
        }
    }
    $beh_stmt->close();

    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $template_id, "Updated template: $template_name");
    redirectWith(BASE_URL . '/manager/templates.php', 'success', "Template '$template_name' updated successfully.");
}

// Fetch existing criteria
$kra_criteria = [];
$beh_criteria = [];
$crit_q = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id ORDER BY section, sort_order");
while ($c = $crit_q->fetch_assoc()) {
    if ($c['section'] === 'Behavior') {
        $beh_criteria[] = $c;
    } else {
        $kra_criteria[] = $c;
    }
}

// Fetch departments for dropdown
$dept_result = $conn->query("SELECT department_name FROM departments WHERE deleted_at IS NULL AND is_active = 1 ORDER BY department_name");
$departments = [];
while ($d = $dept_result->fetch_assoc()) {
    $departments[] = $d['department_name'];
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-edit text-primary me-2"></i>Edit Evaluation Template</h4>
        <p class="text-muted mb-0">Construct a standardized evaluation matrix ensuring weight distribution equals 100%.
        </p>
    </div>
    <a href="<?php echo BASE_URL; ?>/manager/templates.php"
        class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<!-- Setup Guide Alert -->
<div class="alert alert-info border-info shadow-sm mb-4 d-flex align-items-center" role="alert">
    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
    <div>
        <h6 class="alert-heading fw-bold mb-1">Template Modification Guide</h6>
        <p class="mb-0 small">Follow the numbered sections below: <strong>1.</strong> Review basic details,
            <strong>2.</strong> Define the scoring split, <strong>3.</strong> Administer measurable KPIs with percentage
            weights, and <strong>4.</strong> Adjust expected behaviors. All weights must exactly total 100%.
        </p>
    </div>
</div>

<form method="POST" action="" id="templateForm">

    <!-- Template Info -->
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-primary">
        <div class="card-header bg-white border-bottom pb-3">
            <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-info-circle me-2"></i>1. Template Information</h5>
            <span class="badge bg-primary px-3 py-2 shadow-sm"
                style="font-size:0.8rem;"><?php echo e($tmpl['form_code'] ?? ''); ?></span>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="template_name" required
                        value="<?php echo e($tmpl['template_name']); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Evaluation Type</label>
                    <select class="form-select" name="evaluation_type">
                            <?php foreach (['Annual', 'Quarterly', 'Initial', 'Final'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($tmpl['evaluation_type'] ?? 'Annual') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Target Department</label>
                    <select class="form-select" name="target_position">
                        <option value="All Positions" <?php echo ($tmpl['target_position'] ?? '') === 'All Positions' ? 'selected' : ''; ?>>All Positions</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo e($dept); ?>" <?php echo ($tmpl['target_position'] ?? '') === $dept ? 'selected' : ''; ?>><?php echo e($dept); ?></option>
                            <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description"
                        rows="2"><?php echo e($tmpl['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Form Code</label>
                    <input type="text" class="form-control" name="form_code"
                        value="<?php echo e($tmpl['form_code'] ?? ''); ?>" placeholder="e.g., HRD Form-013.01">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Revision Date</label>
                    <input type="date" class="form-control" name="revision_date"
                        value="<?php echo e($tmpl['revision_date'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Effective Date</label>
                    <input type="date" class="form-control" name="effective_date_form"
                        value="<?php echo e($tmpl['effective_date_form'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Rating Scale Reference -->
    <div class="content-card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pb-3">
            <h5 class="mb-0 text-secondary fw-bold"><i class="fas fa-star text-warning me-2"></i>Performance Rating
                Scale Guide</h5>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:120px;">Rating Scale</th>
                        <th style="width:180px;">Description</th>
                        <th>Definition</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-success px-3">3.60 – 4.00</span></td>
                        <td><strong>Outstanding</strong></td>
                        <td>Performance significantly exceeds standards and expectations</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-info px-3">2.60 – 3.59</span></td>
                        <td><strong>Exceeds Expectations</strong></td>
                        <td>Performance exceeds standards and expectations</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-warning text-dark px-3">2.00 – 2.59</span></td>
                        <td><strong>Meets Expectations</strong></td>
                        <td>Performance meets standards and expectations</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-danger px-3">1.00 – 1.99</span></td>
                        <td><strong>Needs Improvement</strong></td>
                        <td>Performance did not meet standards and expectations</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weight Split -->
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
        <div class="card-header bg-white border-bottom pb-3">
            <h5 class="mb-0 text-success fw-bold"><i class="fas fa-balance-scale me-2"></i>2. Master Weight
                Configuration</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">I. KRA Weight (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="kra_weight" id="kraWeight"
                            value="<?php echo $tmpl['kra_weight'] ?? 80; ?>" min="0" max="100" step="1"
                            oninput="syncWeights('kra')">
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted d-block mt-1">Strategic Programs & Job Requirements</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">II. Behavior & Values Weight (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="behavior_weight" id="behaviorWeight"
                            value="<?php echo $tmpl['behavior_weight'] ?? 20; ?>" min="0" max="100" step="1"
                            oninput="syncWeights('behavior')">
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted d-block mt-1">Behavior and Values</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label d-none d-md-block text-muted">&nbsp;</label>
                    <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded border shadow-sm"
                        id="weightSplitStatus" style="background:#e8f5e9;">
                        <strong class="text-dark">Total:</strong>
                        <div class="d-flex align-items-center gap-2">
                            <span id="weightSplitBadge" class="badge bg-success" style="font-size:1rem;">100%</span>
                            <strong id="weightSplitMsg" class="text-success mb-0" style="font-size:0.9rem;"><i
                                    class="fas fa-check-circle me-1"></i>Valid</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section I: KRA -->
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
        <div
            class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 text-success fw-bold"><i class="fas fa-bullseye me-2"></i>3. Key Result Areas (KRA)</h5>
            <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                <span class="badge bg-primary px-3 py-2 shadow-sm me-1" id="kraWeightBadge"
                    style="font-size:0.9rem;">Total: 0%</span>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" onclick="addKRA()"><i
                        class="fas fa-plus me-1"></i>Add KRA</button>
            </div>
        </div>
        <div class="card-body" id="kraContainer"></div>
    </div>

    <!-- Section II: Behavior -->
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-info">
        <div
            class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 text-info fw-bold"><i class="fas fa-heart me-2"></i>4. Core Behaviors & Values</h5>
            <button type="button" class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm mt-2 mt-md-0"
                onclick="addBehavior()"><i class="fas fa-plus me-1"></i>Add Behavior Item</button>
        </div>
        <div class="card-body" id="behaviorContainer"></div>
    </div>

    <!-- Submit -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <a href="<?php echo BASE_URL; ?>/manager/templates.php"
                    class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow"><i
                        class="fas fa-save me-2"></i>Save Final Changes</button>
            </div>
        </div>
    </div>

</form>

<script>
    let kraCount = 0;
    let behaviorCount = 0;

    function addKRA(name = '', desc = '', weight = '') {
        kraCount++;
        const container = document.getElementById('kraContainer');
        const num = container.children.length + 1;
        const html = `
        <div class="kra-criterion-row border border-success rounded p-3 mb-3 position-relative bg-white shadow-sm" id="kra_${kraCount}" style="border-left: 4px solid var(--bs-success) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-success fw-bold"><i class="fas fa-bullseye me-2"></i>KRA ${num}</strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeKRA(${kraCount})" title="Remove Item"><i class="fas fa-times"></i></button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">KRA Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="kra_name[]" value="${escAttr(name)}" required placeholder="e.g., Sales Target Achievement">
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="kra_description[]" value="${escAttr(desc)}" placeholder="Detailed description">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Weight (%) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control kra-weight-input" name="kra_weight_item[]" value="${weight}" required min="1" max="100" step="0.01" oninput="updateKRAWeight()">
                </div>
            </div>
        </div>`;
        container.insertAdjacentHTML('beforeend', html);
        updateKRAWeight();
    }

    function removeKRA(id) {
        document.getElementById('kra_' + id)?.remove();
        renumberKRA(); updateKRAWeight();
    }

    function renumberKRA() {
        document.querySelectorAll('#kraContainer .kra-criterion-row').forEach((row, idx) => {
            row.querySelector('strong').innerHTML = '<i class="fas fa-bullseye me-2"></i>KRA ' + (idx + 1);
        });
    }

    function updateKRAWeight() {
        let total = 0;
        document.querySelectorAll('.kra-weight-input').forEach(i => total += parseFloat(i.value) || 0);
        const b = document.getElementById('kraWeightBadge');
        b.textContent = 'Total: ' + total.toFixed(1) + '%';
        b.className = 'badge me-1 ' + (Math.abs(total - 100) < 0.01 ? 'bg-success' : 'bg-danger');
    }

    function addBehavior(name = '', kpi = '') {
        behaviorCount++;
        const container = document.getElementById('behaviorContainer');
        const num = container.children.length + 1;
        const html = `
        <div class="behavior-criterion-row border border-info rounded p-3 mb-3 position-relative bg-white shadow-sm" id="behavior_${behaviorCount}" style="border-left: 4px solid var(--bs-info) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-info fw-bold"><i class="fas fa-heart me-2"></i>${num}. <span class="behavior-title-display">${name || 'Behavior Item'}</span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeBehavior(${behaviorCount})" title="Remove Item"><i class="fas fa-times"></i></button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Behavior Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="behavior_name[]" value="${escAttr(name)}" required oninput="updateBehaviorTitle(this, ${behaviorCount})">
                </div>
                <div class="col-md-8 mb-2">
                    <label class="form-label">Key Performance Indicator (KPI)</label>
                    <input type="text" class="form-control" name="behavior_kpi[]" value="${escAttr(kpi)}">
                </div>
            </div>
        </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    function removeBehavior(id) {
        document.getElementById('behavior_' + id)?.remove();
        renumberBehavior();
    }

    function renumberBehavior() {
        document.querySelectorAll('#behaviorContainer .behavior-criterion-row').forEach((row, idx) => {
            const label = row.querySelector('strong');
            const nameInput = row.querySelector('input[name="behavior_name[]"]');
            label.innerHTML = '<i class="fas fa-heart me-2"></i>' + (idx + 1) + '. <span class="behavior-title-display">' + (nameInput?.value || 'Behavior Item') + '</span>';
        });
    }

    function updateBehaviorTitle(input, id) {
        const row = document.getElementById('behavior_' + id);
        if (row) {
            const display = row.querySelector('.behavior-title-display');
            if (display) display.textContent = input.value || 'Behavior Item';
        }
    }

    function syncWeights(source) {
        const k = document.getElementById('kraWeight'), b = document.getElementById('behaviorWeight');
        if (source === 'kra') b.value = 100 - (parseFloat(k.value) || 0);
        else k.value = 100 - (parseFloat(b.value) || 0);
        updateWeightSplit();
    }

    function updateWeightSplit() {
        const total = (parseFloat(document.getElementById('kraWeight').value) || 0) + (parseFloat(document.getElementById('behaviorWeight').value) || 0);
        const badge = document.getElementById('weightSplitBadge'), msg = document.getElementById('weightSplitMsg'), status = document.getElementById('weightSplitStatus');
        badge.textContent = total + '%';
        if (Math.abs(total - 100) < 0.01) {
            badge.className = 'badge bg-success shadow-sm'; badge.style.fontSize = '1rem';
            msg.innerHTML = '<i class="fas fa-check-circle me-1"></i>Valid'; msg.className = 'text-success mb-0'; status.style.background = '#e8f5e9';
        } else {
            badge.className = 'badge bg-danger shadow-sm'; badge.style.fontSize = '1rem';
            msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Invalid'; msg.className = 'text-danger mb-0'; status.style.background = '#ffebee';
        }
    }

    function escAttr(str) {
        const d = document.createElement('div'); d.textContent = str || '';
        return d.innerHTML.replace(/"/g, '&quot;');
    }

    // Load existing data
    document.addEventListener('DOMContentLoaded', function () {
            <?php foreach ($kra_criteria as $k): ?>
            addKRA(<?php echo json_encode($k['criterion_name']); ?>, <?php echo json_encode($k['description'] ?? ''); ?>, '<?php echo $k['weight']; ?>');
            <?php endforeach; ?>
            <?php if (empty($kra_criteria)): ?>
            addKRA(); addKRA(); addKRA();
            <?php endif; ?>

            <?php foreach ($beh_criteria as $b): ?>
            addBehavior(<?php echo json_encode($b['criterion_name']); ?>, <?php echo json_encode($b['kpi_description'] ?? ''); ?>);
            <?php endforeach; ?>

        updateWeightSplit();
    });
</script>

<?php require_once '../includes/footer.php'; ?>