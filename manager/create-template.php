<?php
$page_title = 'Create Evaluation Template';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/functions.php';

    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target_position = trim($_POST['target_position'] ?? '');
    $evaluation_type = $_POST['evaluation_type'] ?? 'Annual';
    $kra_weight = floatval($_POST['kra_weight'] ?? 80);
    $behavior_weight = floatval($_POST['behavior_weight'] ?? 20);
    $form_code = trim($_POST['form_code'] ?? '');
    $revision_date = $_POST['revision_date'] ?: null;
    $effective_date_form = $_POST['effective_date_form'] ?: null;

    // KRA criteria
    $kra_names = $_POST['kra_name'] ?? [];
    $kra_descriptions = $_POST['kra_description'] ?? [];
    $kra_weights = $_POST['kra_weight_item'] ?? [];

    // Behavior criteria
    $beh_names = $_POST['behavior_name'] ?? [];
    $beh_kpis = $_POST['behavior_kpi'] ?? [];

    if (empty($template_name)) {
        redirectWith(BASE_URL . '/manager/create-template.php', 'danger', 'Template name is required.');
    }
    if (empty($kra_names) && empty($beh_names)) {
        redirectWith(BASE_URL . '/manager/create-template.php', 'danger', 'At least one criterion is required.');
    }

    // Validate KRA weights sum to 100
    $kra_total_weight = array_sum(array_map('floatval', $kra_weights));
    if (!empty($kra_names) && abs($kra_total_weight - 100) > 0.01) {
        redirectWith(BASE_URL . '/manager/create-template.php', 'danger', 'KRA weights must total 100%. Current: ' . $kra_total_weight . '%');
    }

    // Validate weight split
    if (abs(($kra_weight + $behavior_weight) - 100) > 0.01) {
        redirectWith(BASE_URL . '/manager/create-template.php', 'danger', 'KRA weight + Behavior weight must equal 100%.');
    }

    // Insert template
    $stmt = $conn->prepare("INSERT INTO evaluation_templates (template_name, description, target_position, evaluation_type, kra_weight, behavior_weight, form_code, revision_date, effective_date_form, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
    $stmt->bind_param("ssssddsssi", $template_name, $description, $target_position, $evaluation_type, $kra_weight, $behavior_weight, $form_code, $revision_date, $effective_date_form, $_SESSION['user_id']);
    $stmt->execute();
    $template_id = $stmt->insert_id;
    $stmt->close();

    // Insert KRA criteria
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

    // Insert Behavior criteria
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

    logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Template', $template_id, "Created evaluation template: $template_name");
    redirectWith(BASE_URL . '/manager/templates.php', 'success', "Template '$template_name' created successfully.");
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
        <h4 class="fw-bold mb-1"><i class="fas fa-magic text-primary me-2"></i>Create New Template</h4>
        <p class="text-muted mb-0">Construct a standardized evaluation matrix ensuring weight distribution equals 100%.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/manager/templates.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<!-- Draft Restored Banner (hidden by default, shown by JS) -->
<div id="draftRestoredBanner" class="alert mb-4 d-none" role="alert"
    style="background: linear-gradient(135deg,#fff8e1,#fff3e0); border:1.5px solid #ffa000; border-radius:12px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-history fa-lg text-warning"></i>
            <div>
                <div class="fw-bold text-dark">Draft Restored</div>
                <div class="small text-muted" id="draftTimestamp"></div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="discardDraft()">
                <i class="fas fa-trash me-1"></i>Discard Draft
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="document.getElementById('draftRestoredBanner').classList.add('d-none')">
                <i class="fas fa-times me-1"></i>Dismiss
            </button>
        </div>
    </div>
</div>

<!-- Setup Guide Alert -->
<div class="alert alert-info border-info shadow-sm mb-4 d-flex align-items-center" role="alert">
    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
    <div>
        <h6 class="alert-heading fw-bold mb-1">Template Creation Guide</h6>
        <p class="mb-0 small">Follow the numbered sections below: <strong>1.</strong> Enter basic details, <strong>2.</strong> Define the scoring split, <strong>3.</strong> Add measurable KPIs with percentage weights, and <strong>4.</strong> Include expected behaviors. All weights must exactly total 100%.</p>
    </div>
</div>

<form method="POST" action="" id="templateForm">

<!-- Template Info Card -->
<div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-primary">
    <div class="card-header bg-white border-bottom pb-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-info-circle me-2"></i>1. Template Information</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6 mb-3">
                <label class="form-label">Template Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="template_name" required placeholder="e.g., Annual Performance Review 2026">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Evaluation Type</label>
                <select class="form-select" name="evaluation_type">
                    <option value="Annual" selected>Annual</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Initial">Initial</option>
                    <option value="Final">Final</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Target Department <small class="text-muted">(optional)</small></label>
                <select class="form-select" name="target_position">
                    <option value="All Positions">All Positions</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo e($dept); ?>"><?php echo e($dept); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12 mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this evaluation template..."></textarea>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Form Code</label>
                <input type="text" class="form-control" name="form_code" value="" placeholder="e.g., HRD Form-013.01">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Revision Date</label>
                <input type="date" class="form-control" name="revision_date">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Effective Date</label>
                <input type="date" class="form-control" name="effective_date_form">
            </div>
        </div>
    </div>
</div>

<!-- Rating Scale Reference -->
<div class="content-card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pb-3">
        <h5 class="mb-0 text-secondary fw-bold"><i class="fas fa-star text-warning me-2"></i>Performance Rating Scale Guide</h5>
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
                <tr><td><span class="badge bg-success px-3">3.60 – 4.00</span></td><td><strong>Outstanding</strong></td><td>Performance significantly exceeds standards and expectations</td></tr>
                <tr><td><span class="badge bg-info px-3">2.60 – 3.59</span></td><td><strong>Exceeds Expectations</strong></td><td>Performance exceeds standards and expectations</td></tr>
                <tr><td><span class="badge bg-warning text-dark px-3">2.00 – 2.59</span></td><td><strong>Meets Expectations</strong></td><td>Performance meets standards and expectations</td></tr>
                <tr><td><span class="badge bg-danger px-3">1.00 – 1.99</span></td><td><strong>Needs Improvement</strong></td><td>Performance did not meet standards and expectations</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Weight Split -->
<div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white border-bottom pb-3">
        <h5 class="mb-0 text-success fw-bold"><i class="fas fa-balance-scale me-2"></i>2. Master Weight Configuration</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">I. KRA Weight (%)</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="kra_weight" id="kraWeight" value="80" min="0" max="100" step="1" oninput="syncWeights('kra')">
                    <span class="input-group-text">%</span>
                </div>
                <small class="text-muted d-block mt-1">Strategic Programs & Job Requirements</small>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">II. Behavior & Values Weight (%)</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="behavior_weight" id="behaviorWeight" value="20" min="0" max="100" step="1" oninput="syncWeights('behavior')">
                    <span class="input-group-text">%</span>
                </div>
                <small class="text-muted d-block mt-1">Behavior and Values</small>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label d-none d-md-block text-muted">&nbsp;</label>
                <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded border shadow-sm" id="weightSplitStatus" style="background:#e8f5e9;">
                    <strong class="text-dark">Total:</strong>
                    <div class="d-flex align-items-center gap-2">
                        <span id="weightSplitBadge" class="badge bg-success" style="font-size:1rem;">100%</span>
                        <strong id="weightSplitMsg" class="text-success mb-0" style="font-size:0.9rem;"><i class="fas fa-check-circle me-1"></i>Valid</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section I: KRA -->
<div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center">
        <h5 class="mb-0 text-success fw-bold"><i class="fas fa-bullseye me-2"></i>3. Key Result Areas (KRA)</h5>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <span class="badge bg-primary px-3 py-2 shadow-sm me-1" id="kraWeightBadge" style="font-size:0.9rem;">Total: 0%</span>
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" onclick="addKRA()">
                <i class="fas fa-plus me-1"></i>Add KRA
            </button>
        </div>
    </div>
    <div class="card-body" id="kraContainer">
        <!-- KRA rows inserted by JS -->
    </div>
</div>

<!-- Section II: Behavior & Values -->
<div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-info">
    <div class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center">
        <h5 class="mb-0 text-info fw-bold"><i class="fas fa-heart me-2"></i>4. Core Behaviors & Values</h5>
        <button type="button" class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm mt-2 mt-md-0" onclick="addBehavior()">
            <i class="fas fa-plus me-1"></i>Add Behavior Item
        </button>
    </div>
    <div class="card-body" id="behaviorContainer">
        <!-- Behavior rows inserted by JS -->
    </div>
</div>

<!-- Submit -->
<div class="content-card mb-4 border-0 shadow-sm bg-light">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="<?php echo BASE_URL; ?>/manager/templates.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>Cancel
                </a>
                <span id="autosaveIndicator" class="text-muted small d-none" style="transition:opacity 0.5s;">
                    <i class="fas fa-cloud me-1 text-success"></i><span id="autosaveText">Draft saved</span>
                </span>
            </div>
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow" id="submitBtn"
                onclick="clearDraftOnSubmit()">
                <i class="fas fa-save me-2"></i>Finalize &amp; Create Template
            </button>
        </div>
    </div>
</div>

</form>

<script>
let kraCount = 0;
let behaviorCount = 0;

// Default behavior items matching HRD Form-013.01
const defaultBehaviors = [
    { name: 'Positive Attitude', kpi: 'Displays positive attitude at work.' },
    { name: 'Respect', kpi: 'Shows respect to all people in the organization.' },
    { name: 'Accountability', kpi: 'Takes full responsibility of the job including special task or assignment.' },
    { name: 'Commitment', kpi: 'Demonstrates strong commitment to the job.' },
    { name: 'Teamwork', kpi: 'Works cooperatively with others in achieving the goals.' },
    { name: 'Integrity', kpi: 'Exhibits honesty and strong moral uprightness.' },
    { name: 'Continuous Improvement', kpi: 'Provides diligent effort to continuously focus on getting better.' },
    { name: 'Excellent Client Experience', kpi: 'Delivers the service beyond the expectations of the internal and external clients.' }
];

function addKRA(name = '', desc = '', weight = '') {
    kraCount++;
    const container = document.getElementById('kraContainer');
    const html = `
        <div class="kra-criterion-row border border-success rounded p-3 mb-3 position-relative bg-white shadow-sm" id="kra_${kraCount}" style="border-left: 4px solid var(--bs-success) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-success fw-bold"><i class="fas fa-bullseye me-2"></i>KRA ${container.children.length + 1}</strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeKRA(${kraCount})" title="Remove Item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">KRA Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="kra_name[]" value="${escAttr(name)}" required placeholder="e.g., Sales Target Achievement">
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="kra_description[]" value="${escAttr(desc)}" placeholder="Detailed description of this KRA">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Weight (%) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control kra-weight-input" name="kra_weight_item[]" value="${weight}" required min="1" max="100" step="0.01" placeholder="e.g., 10" oninput="updateKRAWeight()">
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    updateKRAWeight();
}

function removeKRA(id) {
    const el = document.getElementById('kra_' + id);
    if (el) { el.remove(); renumberKRA(); updateKRAWeight(); }
}

function renumberKRA() {
    document.querySelectorAll('#kraContainer .kra-criterion-row').forEach((row, idx) => {
        const label = row.querySelector('strong');
        if (label) label.innerHTML = '<i class="fas fa-bullseye me-2"></i>KRA ' + (idx + 1);
    });
}

function updateKRAWeight() {
    let total = 0;
    document.querySelectorAll('.kra-weight-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const badge = document.getElementById('kraWeightBadge');
    badge.textContent = 'Total: ' + total.toFixed(1) + '%';
    badge.className = 'badge me-1 ' + (Math.abs(total - 100) < 0.01 ? 'bg-success' : 'bg-danger');
}

function addBehavior(name = '', kpi = '') {
    behaviorCount++;
    const container = document.getElementById('behaviorContainer');
    const num = container.children.length + 1;
    const html = `
        <div class="behavior-criterion-row border border-info rounded p-3 mb-3 position-relative bg-white shadow-sm" id="behavior_${behaviorCount}" style="border-left: 4px solid var(--bs-info) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-info fw-bold"><i class="fas fa-heart me-2"></i>${num}. <span class="behavior-title-display">${name || 'Behavior Item'}</span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeBehavior(${behaviorCount})" title="Remove Item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Behavior Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="behavior_name[]" value="${escAttr(name)}" required placeholder="e.g., Positive Attitude" oninput="updateBehaviorTitle(this, ${behaviorCount})">
                </div>
                <div class="col-md-8 mb-2">
                    <label class="form-label">Key Performance Indicator (KPI)</label>
                    <input type="text" class="form-control" name="behavior_kpi[]" value="${escAttr(kpi)}" placeholder="e.g., Displays positive attitude at work.">
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function removeBehavior(id) {
    const el = document.getElementById('behavior_' + id);
    if (el) { el.remove(); renumberBehavior(); }
}

function renumberBehavior() {
    document.querySelectorAll('#behaviorContainer .behavior-criterion-row').forEach((row, idx) => {
        const label = row.querySelector('strong');
        const nameInput = row.querySelector('input[name="behavior_name[]"]');
        if (label) label.innerHTML = '<i class="fas fa-heart me-2"></i>' + (idx + 1) + '. <span class="behavior-title-display">' + (nameInput?.value || 'Behavior Item') + '</span>';
    });
}

function updateBehaviorTitle(input, id) {
    const row = document.getElementById('behavior_' + id);
    if(row) {
        const display = row.querySelector('.behavior-title-display');
        if(display) display.textContent = input.value || 'Behavior Item';
    }
}

function syncWeights(source) {
    const kraInput = document.getElementById('kraWeight');
    const behInput = document.getElementById('behaviorWeight');
    if (source === 'kra') {
        behInput.value = 100 - (parseFloat(kraInput.value) || 0);
    } else {
        kraInput.value = 100 - (parseFloat(behInput.value) || 0);
    }
    updateWeightSplit();
}

function updateWeightSplit() {
    const kra = parseFloat(document.getElementById('kraWeight').value) || 0;
    const beh = parseFloat(document.getElementById('behaviorWeight').value) || 0;
    const total = kra + beh;
    const badge = document.getElementById('weightSplitBadge');
    const msg = document.getElementById('weightSplitMsg');
    const status = document.getElementById('weightSplitStatus');
    badge.textContent = total + '%';
    if (Math.abs(total - 100) < 0.01) {
        badge.className = 'badge bg-success shadow-sm'; badge.style.fontSize = '1rem';
        msg.innerHTML = '<i class="fas fa-check-circle me-1"></i>Valid'; msg.className = 'text-success mb-0';
        status.style.background = '#e8f5e9';
    } else {
        badge.className = 'badge bg-danger shadow-sm'; badge.style.fontSize = '1rem';
        msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Invalid'; msg.className = 'text-danger mb-0';
        status.style.background = '#ffebee';
    }
}

function escAttr(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML.replace(/"/g, '&quot;');
}

// ============================================================
// AUTO-SAVE / DRAFT PERSISTENCE (localStorage)
// ============================================================
const DRAFT_KEY = 'hris_template_draft';
let autosaveTimer = null;

function collectDraft() {
    const kras = [];
    document.querySelectorAll('#kraContainer .kra-criterion-row').forEach(row => {
        kras.push({
            name: row.querySelector('input[name="kra_name[]"]')?.value || '',
            desc: row.querySelector('input[name="kra_description[]"]')?.value || '',
            weight: row.querySelector('input[name="kra_weight_item[]"]')?.value || ''
        });
    });

    const behaviors = [];
    document.querySelectorAll('#behaviorContainer .behavior-criterion-row').forEach(row => {
        behaviors.push({
            name: row.querySelector('input[name="behavior_name[]"]')?.value || '',
            kpi: row.querySelector('input[name="behavior_kpi[]"]')?.value || ''
        });
    });

    return {
        template_name: document.querySelector('[name="template_name"]')?.value || '',
        description: document.querySelector('[name="description"]')?.value || '',
        target_position: document.querySelector('[name="target_position"]')?.value || '',
        evaluation_type: document.querySelector('[name="evaluation_type"]')?.value || '',
        kra_weight: document.getElementById('kraWeight')?.value || '80',
        behavior_weight: document.getElementById('behaviorWeight')?.value || '20',
        form_code: document.querySelector('[name="form_code"]')?.value || '',
        revision_date: document.querySelector('[name="revision_date"]')?.value || '',
        effective_date_form: document.querySelector('[name="effective_date_form"]')?.value || '',
        kras,
        behaviors,
        savedAt: new Date().toISOString()
    };
}

function saveDraft() {
    try {
        const draft = collectDraft();
        // Only save if something meaningful has been entered
        const hasContent = draft.template_name || draft.kras.some(k => k.name) || draft.behaviors.some(b => b.name !== (defaultBehaviors.find(d => d.name === b.name)?.name || ''));
        if (!hasContent) return;
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        // Show autosave indicator
        const indicator = document.getElementById('autosaveIndicator');
        const txt = document.getElementById('autosaveText');
        if (indicator) {
            indicator.classList.remove('d-none');
            txt.textContent = 'Draft saved · ' + new Date().toLocaleTimeString();
            indicator.style.opacity = '1';
            setTimeout(() => { indicator.style.opacity = '0.5'; }, 2000);
        }
    } catch(e) {}
}

function restoreDraft(draft) {
    // Template Info
    const setVal = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    setVal('[name="template_name"]', draft.template_name);
    setVal('[name="description"]', draft.description);
    setVal('[name="target_position"]', draft.target_position);
    setVal('[name="evaluation_type"]', draft.evaluation_type);
    setVal('[name="form_code"]', draft.form_code);
    setVal('[name="revision_date"]', draft.revision_date);
    setVal('[name="effective_date_form"]', draft.effective_date_form);

    // Weight split
    const kw = document.getElementById('kraWeight');
    const bw = document.getElementById('behaviorWeight');
    if (kw) kw.value = draft.kra_weight;
    if (bw) bw.value = draft.behavior_weight;
    updateWeightSplit();

    // KRA rows — clear defaults, restore saved
    document.getElementById('kraContainer').innerHTML = '';
    kraCount = 0;
    if (draft.kras && draft.kras.length) {
        draft.kras.forEach(k => addKRA(k.name, k.desc, k.weight));
    } else {
        addKRA('', '', ''); addKRA('', '', ''); addKRA('', '', '');
    }

    // Behavior rows — clear defaults, restore saved
    document.getElementById('behaviorContainer').innerHTML = '';
    behaviorCount = 0;
    if (draft.behaviors && draft.behaviors.length) {
        draft.behaviors.forEach(b => addBehavior(b.name, b.kpi));
    } else {
        defaultBehaviors.forEach(b => addBehavior(b.name, b.kpi));
    }

    // Show banner
    const banner = document.getElementById('draftRestoredBanner');
    const ts = document.getElementById('draftTimestamp');
    if (banner && ts) {
        const d = new Date(draft.savedAt);
        ts.textContent = 'Last saved: ' + d.toLocaleDateString() + ' at ' + d.toLocaleTimeString();
        banner.classList.remove('d-none');
    }
}

function discardDraft() {
    localStorage.removeItem(DRAFT_KEY);
    document.getElementById('draftRestoredBanner').classList.add('d-none');
    // Reset to defaults
    document.getElementById('kraContainer').innerHTML = '';
    document.getElementById('behaviorContainer').innerHTML = '';
    kraCount = 0; behaviorCount = 0;
    document.querySelector('[name="template_name"]').value = '';
    document.querySelector('[name="description"]').value = '';
    document.querySelector('[name="form_code"]').value = '';
    document.querySelector('[name="revision_date"]').value = '';
    document.querySelector('[name="effective_date_form"]').value = '';
    addKRA('', '', ''); addKRA('', '', ''); addKRA('', '', '');
    defaultBehaviors.forEach(b => addBehavior(b.name, b.kpi));
    document.getElementById('autosaveIndicator')?.classList.add('d-none');
}

function clearDraftOnSubmit() {
    localStorage.removeItem(DRAFT_KEY);
}

// Hook auto-save on any input/change inside the form
function attachAutosaveListeners() {
    const form = document.getElementById('templateForm');
    if (!form) return;
    form.addEventListener('input', () => {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(saveDraft, 2000);
    });
    form.addEventListener('change', () => {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(saveDraft, 2000);
    });
}

// Initialize with 3 KRA rows and all 8 default behavior items, then check for draft
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem(DRAFT_KEY);
    if (saved) {
        try {
            const draft = JSON.parse(saved);
            restoreDraft(draft);
        } catch(e) {
            localStorage.removeItem(DRAFT_KEY);
            addKRA('', '', ''); addKRA('', '', ''); addKRA('', '', '');
            defaultBehaviors.forEach(b => addBehavior(b.name, b.kpi));
        }
    } else {
        addKRA('', '', ''); addKRA('', '', ''); addKRA('', '', '');
        defaultBehaviors.forEach(b => addBehavior(b.name, b.kpi));
    }
    attachAutosaveListeners();
});
</script>

<?php require_once '../includes/footer.php'; ?>
