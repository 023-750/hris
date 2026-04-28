<?php
$page_title = 'Analytics';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

/* ── Filters ────────────────────────────────────────────────────────────── */
$date_from     = $_GET['date_from']  ?? '';
$date_to       = $_GET['date_to']    ?? '';
$filter_branch = $_GET['branch']     ?? '';
$filter_dept   = $_GET['department'] ?? '';

$where  = "WHERE ev.status = 'Approved'";
$params = [];  $types = '';

if (!empty($date_from))    { $where .= " AND ev.approved_date >= ?";              $params[] = $date_from;           $types .= 's'; }
if (!empty($date_to))      { $where .= " AND ev.approved_date <= ?";              $params[] = $date_to.' 23:59:59'; $types .= 's'; }
if (!empty($filter_branch)){ $where .= " AND e.branch_id = ?";                    $params[] = (int)$filter_branch;  $types .= 'i'; }
if (!empty($filter_dept))  { $where .= " AND e.department_id = ?";                $params[] = (int)$filter_dept;    $types .= 'i'; }

/* ── Summary Stats ──────────────────────────────────────────────────────── */
$stats_q = $conn->prepare(
    "SELECT COUNT(*) AS total,
            ROUND(AVG(ev.total_score), 2) AS avg_score,
            SUM(ev.performance_level = 'Outstanding')        AS outstanding,
            SUM(ev.performance_level = 'Needs Improvement')  AS needs_imp
     FROM evaluations ev
     LEFT JOIN employees e ON ev.employee_id = e.employee_id
     $where AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)");
if (!empty($params)) $stats_q->bind_param($types, ...$params);
$stats_q->execute();
$stats = $stats_q->get_result()->fetch_assoc();
$stats_q->close();

$total_evals = (int)($stats['total']       ?? 0);
$avg_score   = (float)($stats['avg_score'] ?? 0);
$outstanding = (int)($stats['outstanding'] ?? 0);
$needs_imp   = (int)($stats['needs_imp']   ?? 0);

/* ── Performance Distribution ───────────────────────────────────────────── */
$perf_dist = ['Outstanding' => 0, 'Exceeds Expectations' => 0,
               'Meets Expectations' => 0, 'Needs Improvement' => 0];
$perf_q = $conn->prepare(
    "SELECT ev.performance_level, COUNT(*) AS cnt
     FROM evaluations ev
     LEFT JOIN employees e ON ev.employee_id = e.employee_id
     $where AND ev.performance_level IS NOT NULL
     AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     GROUP BY ev.performance_level");
if (!empty($params)) $perf_q->bind_param($types, ...$params);
$perf_q->execute();
$pr = $perf_q->get_result();
while ($row = $pr->fetch_assoc())
    if (isset($perf_dist[$row['performance_level']])) $perf_dist[$row['performance_level']] = (int)$row['cnt'];
$perf_q->close();

/* ── Score Trend — per-year 12-month data (built-in year filter) ────────── */
// Discover all years that have approved evaluations
$yr_q = $conn->query("SELECT DISTINCT YEAR(approved_date) AS yr FROM evaluations WHERE status='Approved' ORDER BY yr DESC");
$available_years = [];
while ($row = $yr_q->fetch_assoc()) $available_years[] = (int)$row['yr'];
if (empty($available_years)) $available_years = [(int)date('Y')];
$default_trend_year = (int)date('Y');
// If the current year has no data yet, use the most recent year that does
if (!in_array($default_trend_year, $available_years)) $default_trend_year = $available_years[0];

// Build 12-month arrays for every available year (respects branch/dept filter)
$all_years_trend = [];
foreach ($available_years as $yr) {
    $yr_months = [];
    for ($m = 1; $m <= 12; $m++) {
        $t_where  = "WHERE status='Approved' AND MONTH(approved_date)=$m AND YEAR(approved_date)=$yr AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
        $t_params = []; $t_types = '';
        if (!empty($filter_branch)) { $t_where .= ' AND employee_id IN (SELECT employee_id FROM employees WHERE branch_id=?)';     $t_params[] = (int)$filter_branch; $t_types .= 'i'; }
        if (!empty($filter_dept))   { $t_where .= ' AND employee_id IN (SELECT employee_id FROM employees WHERE department_id=?)'; $t_params[] = (int)$filter_dept;   $t_types .= 'i'; }
        $tq2 = $conn->prepare("SELECT ROUND(AVG(total_score),2) AS v, COUNT(*) AS cnt FROM evaluations $t_where");
        if (!empty($t_params)) $tq2->bind_param($t_types, ...$t_params);
        $tq2->execute();
        $r = $tq2->get_result()->fetch_assoc();
        $tq2->close();
        $yr_months[] = ['value' => (float)($r['v'] ?? 0), 'count' => (int)($r['cnt'] ?? 0)];
    }
    $all_years_trend[$yr] = $yr_months;
}
$trend_label = 'By Year';

/* ── Branch Comparison  ──────────────────────────────────────────────────
   Always shows ALL branches; date filter respected but branch/dept filters
   are intentionally excluded so users can compare branches side-by-side.   */
$br_where = "WHERE ev.status = 'Approved'";
$br_params = []; $br_types = '';
if (!empty($date_from)) { $br_where .= " AND ev.approved_date >= ?"; $br_params[] = $date_from;            $br_types .= 's'; }
if (!empty($date_to))   { $br_where .= " AND ev.approved_date <= ?"; $br_params[] = $date_to.' 23:59:59'; $br_types .= 's'; }

$branch_data = [];
$bq = $conn->prepare(
    "SELECT b.branch_name,
            ROUND(AVG(ev.total_score), 2) AS avg_score,
            COUNT(ev.evaluation_id)        AS eval_count
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN branches  b ON e.branch_id    = b.branch_id
     $br_where AND b.branch_name IS NOT NULL
     AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     GROUP BY b.branch_id, b.branch_name
     ORDER BY avg_score DESC");
if (!empty($br_params)) $bq->bind_param($br_types, ...$br_params);
$bq->execute();
$br = $bq->get_result();
while ($row = $br->fetch_assoc())
    $branch_data[] = ['label' => $row['branch_name'], 'value' => (float)$row['avg_score'], 'count' => (int)$row['eval_count']];
$bq->close();

$top_branch     = count($branch_data) ? $branch_data[0]['label'] : 'N/A';
$top_branch_avg = count($branch_data) ? $branch_data[0]['value'] : 0;

/* ── Department Breakdown ────────────────────────────────────────────────── */
$dept_data = [];
$dq = $conn->prepare(
    "SELECT d.department_name,
            ROUND(AVG(ev.total_score), 2) AS avg_score,
            COUNT(*) AS cnt
     FROM evaluations ev
     INNER JOIN employees   e ON ev.employee_id  = e.employee_id
     INNER JOIN departments d ON e.department_id = d.department_id
     $where AND d.department_name IS NOT NULL
     AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     GROUP BY d.department_id, d.department_name ORDER BY avg_score DESC");
if (!empty($params)) $dq->bind_param($types, ...$params);
$dq->execute();
$dept_data = $dq->get_result()->fetch_all(MYSQLI_ASSOC);
$dq->close();

/* ── Top Performers ──────────────────────────────────────────────────────── */
$tq = $conn->prepare(
    "SELECT CONCAT(e.first_name,' ',e.last_name) AS name, e.job_title,
            b.branch_name, ev.total_score, ev.performance_level
     FROM evaluations ev
     LEFT JOIN employees e ON ev.employee_id = e.employee_id
     LEFT JOIN branches  b ON e.branch_id    = b.branch_id
     $where 
     AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY ev.total_score DESC LIMIT 10");
if (!empty($params)) $tq->bind_param($types, ...$params);
$tq->execute();
$top_performers = $tq->get_result()->fetch_all(MYSQLI_ASSOC);
$tq->close();

/* ── Branch color palette (26 brand-harmonious colors) ─────────────────── */
$palette = ['#294306','#BD9414','#D71920','#2E86AB','#3BB273',
            '#7B2D8B','#F18F01','#44BBA4','#E94F37','#386FA4',
            '#59A608','#9B2226','#0077B6','#F77F00','#5C4033',
            '#00B4D8','#80B918','#E63946','#457B9D','#6A0572',
            '#C77DFF','#52B788','#F4A261','#2D6A4F','#E9C46A','#A8DADC'];
$branch_colors = [];
foreach ($branch_data as $i => $_) $branch_colors[] = $palette[$i % count($palette)];

/* ── Filter dropdowns ───────────────────────────────────────────────────── */
$branches_dd    = $conn->query("SELECT * FROM branches    ORDER BY branch_name");
$departments_dd = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active=1 ORDER BY department_name");

/* ── Badge helpers ──────────────────────────────────────────────────────── */
$level_meta = [
    'Outstanding'          => ['icon' => 'fa-star',           'color' => '#28a745', 'bg' => 'rgba(40,167,69,.12)'],
    'Exceeds Expectations' => ['icon' => 'fa-thumbs-up',      'color' => '#17a2b8', 'bg' => 'rgba(23,162,184,.12)'],
    'Meets Expectations'   => ['icon' => 'fa-check-circle',   'color' => '#ffc107', 'bg' => 'rgba(255,193,7,.12)'],
    'Needs Improvement'    => ['icon' => 'fa-exclamation-circle','color'=>'#dc3545', 'bg' => 'rgba(220,53,69,.12)'],
];
?>



<!-- ═══════════════════════  HERO  ═══════════════════════ -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Analytics</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-chart-bar me-2" style="color:#BD9414;"></i>Performance Analytics</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_evals); ?></div>
                        <div class="stat-label">Total Evaluations</div>
                    </div>
                    <i class="fas fa-file-alt stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($avg_score, 2); ?></div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <i class="fas fa-chart-line stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($outstanding); ?></div>
                        <div class="stat-label">Outstanding</div>
                    </div>
                    <i class="fas fa-star stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="font-size:1.1rem;padding-top:4px;"><?php echo e($top_branch); ?></div>
                        <div class="stat-label">Top Branch · <?php echo number_format($top_branch_avg,2); ?></div>
                    </div>
                    <i class="fas fa-trophy stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════  FILTERS  ═══════════════════════ -->
<div class="filter-card fadeup fadeup-1">
    <form method="GET" action="" class="row align-items-end g-3">
        <div class="col-md-2 col-6">
            <label class="form-label fw-semibold" style="font-size:.78rem;">Date From</label>
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo e($date_from); ?>">
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label fw-semibold" style="font-size:.78rem;">Date To</label>
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo e($date_to); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.78rem;">Branch</label>
            <select class="form-select form-select-sm" name="branch">
                <option value="">All Branches</option>
                <?php while ($b = $branches_dd->fetch_assoc()): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo ($filter_branch == $b['branch_id']) ? 'selected' : ''; ?>>
                        <?php echo e($b['branch_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.78rem;">Department</label>
            <select class="form-select form-select-sm" name="department">
                <option value="">All Departments</option>
                <?php while ($d = $departments_dd->fetch_assoc()): ?>
                    <option value="<?php echo $d['department_id']; ?>" <?php echo ($filter_dept == $d['department_id']) ? 'selected' : ''; ?>>
                        <?php echo e($d['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-apply flex-fill"><i class="fas fa-filter me-1"></i>Apply</button>
            <a href="analytics.php" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
        </div>
    </form>
</div>

<!-- ═══════════════════════  ROW 1: PIE + BRANCH  ═══════════════════════ -->
<div class="row g-3 mb-3 fadeup fadeup-2">
    <!-- Performance Distribution -->
    <div class="col-lg-5">
        <div class="chart-card">
            <div class="cc-header">
                <h6><i class="fas fa-chart-pie me-2" style="color:#294306;"></i>Performance Distribution</h6>
                <span class="badge bg-light text-muted" style="font-size:.7rem;"><?php echo number_format($total_evals); ?> evals</span>
            </div>
            <div class="cc-body">
                <div class="chart-wrap" style="height:240px;">
                    <canvas id="perfPieChart"></canvas>
                </div>
                <!-- Legend pills -->
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php foreach ([
                        'Outstanding'          => ['#28a745', $perf_dist['Outstanding']],
                        'Exceeds Exp.'         => ['#17a2b8', $perf_dist['Exceeds Expectations']],
                        'Meets Exp.'           => ['#ffc107', $perf_dist['Meets Expectations']],
                        'Needs Impr.'          => ['#dc3545', $perf_dist['Needs Improvement']],
                    ] as $lbl => [$col, $cnt]): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;color:#555;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $col; ?>;display:inline-block;flex-shrink:0;"></span>
                        <?php echo e($lbl); ?> <span style="color:#aaa;">(<?php echo $cnt; ?>)</span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Comparison (now col-lg-7) -->
    <div class="col-lg-7">
        <div class="chart-card">
            <div class="cc-header">
                <h6><i class="fas fa-code-branch me-2" style="color:#294306;"></i>Branch Comparison</h6>
                <span style="font-size:.7rem;color:#aaa;"><?php echo count($branch_data); ?> branches</span>
            </div>
            <div class="cc-body" style="overflow-y:auto;max-height:290px;padding-right:4px;">
                <?php if (empty($branch_data)): ?>
                <div class="empty-state"><i class="fas fa-building" style="color:#ddd;"></i><div>No branch data yet.</div></div>
                <?php else:
                    $max_b = max(array_column($branch_data,'value'));
                    foreach ($branch_data as $idx => $b):
                        $pct = $max_b > 0 ? round($b['value'] / $max_b * 100) : 0;
                        $color = $palette[$idx % count($palette)];
                        $rankClass = $idx === 0 ? 'gold' : ($idx === 1 ? 'silver' : ($idx === 2 ? 'bronze' : 'plain'));
                ?>
                <div class="branch-row">
                    <div class="branch-rank <?php echo $rankClass; ?>" style="<?php echo $idx > 2 ? "background:$color;" : ''; ?>"><?php echo $idx+1; ?></div>
                    <div class="branch-name" title="<?php echo e($b['label']); ?>"><?php echo e($b['label']); ?></div>
                    <div class="branch-bar-wrap">
                        <div class="branch-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;"></div>
                    </div>
                    <div class="branch-score"><?php echo number_format($b['value'],2); ?></div>
                    <div class="branch-count"><?php echo $b['count']; ?> <span style="font-size:.62rem;">eval<?php echo $b['count']!=1?'s':''; ?></span></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════  ROW 1.5: TREND  ═══════════════════════ -->
<div class="row g-3 mb-3 fadeup fadeup-3">
    <div class="col-12">
        <div class="chart-card">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <div>
                    <h6><i class="fas fa-chart-line me-2" style="color:#294306;"></i>Score Trend</h6>
                    <span style="font-size:.7rem;color:#aaa;" id="trendSubtitle"><?php echo $default_trend_year; ?> Monthly Averages</span>
                </div>
                <!-- Year Pill Buttons -->
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ($available_years as $yr): ?>
                    <button type="button" class="btn btn-outline-secondary trend-year-btn <?php echo $yr === $default_trend_year ? 'active btn-secondary text-white' : ''; ?>" data-year="<?php echo $yr; ?>">
                        <?php echo $yr; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cc-body">
                <div class="chart-wrap" style="height:340px;">
                    <canvas id="trendLineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════  ROW 2: TOP PERFORMERS + DEPT  ═══════════════════════ -->
<div class="row g-3 fadeup fadeup-4">
    <!-- Top Performers -->
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="cc-header">
                <h6><i class="fas fa-trophy me-2" style="color:#BD9414;"></i>Top Performers</h6>
                <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-sm btn-outline-success" style="font-size:.72rem;padding:3px 10px;">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
            </div>
            <?php if (empty($top_performers)): ?>
            <div class="cc-body"><div class="empty-state"><i class="fas fa-users"></i><div>No performer data yet.</div></div></div>
            <?php else: ?>
            <?php foreach ($top_performers as $idx => $tp):
                $rkClass = $idx === 0 ? 'gold' : ($idx === 1 ? 'silver' : ($idx === 2 ? 'bronze' : 'plain'));
                $lvl = $tp['performance_level'] ?? getPerformanceLevel($tp['total_score']);
                $meta = $level_meta[$lvl] ?? ['icon'=>'fa-circle','color'=>'#888','bg'=>'#f0f0f0'];
            ?>
            <div class="performer-row">
                <div class="performer-rank <?php echo $rkClass; ?>"><?php echo $idx+1; ?></div>
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?php echo $meta['bg']; ?>;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas <?php echo $meta['icon']; ?>" style="color:<?php echo $meta['color']; ?>;font-size:.85rem;"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="performer-name text-truncate"><?php echo e($tp['name']); ?></div>
                        <div class="performer-meta text-truncate">
                            <?php echo e($tp['job_title'] ?? ''); ?>
                            <?php if (!empty($tp['branch_name'])): ?> · <span style="color:#294306;"><?php echo e($tp['branch_name']); ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="performer-score">
                    <div class="ps-val"><?php echo number_format($tp['total_score'],2); ?></div>
                    <div class="lvl-pill ps-level" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                        <?php echo e($lvl); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Department Breakdown -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="cc-header">
                <h6><i class="fas fa-sitemap me-2" style="color:#294306;"></i>Department Breakdown</h6>
            </div>
            <div class="cc-body p-0">
                <?php if (empty($dept_data)): ?>
                <div class="empty-state"><i class="fas fa-building"></i><div>No department data.</div></div>
                <?php else: ?>
                <table class="dept-table">
                    <thead><tr>
                        <th>Department</th>
                        <th>Avg</th>
                        <th>Score</th>
                    </tr></thead>
                    <tbody>
                    <?php
                    $max_dept = max(array_column($dept_data,'avg_score'));
                    foreach ($dept_data as $d):
                        $dpct = $max_dept > 0 ? round($d['avg_score']/$max_dept*100) : 0;
                    ?>
                    <tr>
                        <td><span class="fw-semibold" style="font-size:.8rem;"><?php echo e($d['department_name']); ?></span>
                            <br><span style="font-size:.68rem;color:#aaa;"><?php echo $d['cnt']; ?> eval<?php echo $d['cnt']!=1?'s':''; ?></span>
                        </td>
                        <td><div class="dept-bar-wrap"><div class="dept-bar-fill" style="width:<?php echo $dpct; ?>%;"></div></div></td>
                        <td><strong style="color:#294306;"><?php echo number_format($d['avg_score'],2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const SPINE = '#294306', GOLD = '#BD9414', RED = '#D71920';
    const gridColor = 'rgba(0,0,0,.04)';

    /* ── shared defaults ── */
    Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
    Chart.defaults.font.size   = 11;

    /* ── 1. Performance Distribution (Doughnut) ── */
    new Chart(document.getElementById('perfPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Outstanding','Exceeds Expectations','Meets Expectations','Needs Improvement'],
            datasets: [{
                data: [<?php echo implode(',', array_values($perf_dist)); ?>],
                backgroundColor: ['#28a745','#17a2b8','#ffc107','#dc3545'],
                borderWidth: 3, borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
            }
        }
    });

    /* ── 2. Score Trend (Bar) — built-in year filter ── */
    const allYearsTrend = <?php echo json_encode($all_years_trend); ?>;
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    let currentTrendYear = <?php echo $default_trend_year; ?>;
    
    function getTrendDataForYear(yr) {
        const data = allYearsTrend[yr] || [];
        const vals = data.map(d => d.value || 0);
        return {
            labels: monthNames.map(m => m + ' ' + yr),
            vals: vals
        };
    }
    
    let initialTrend = getTrendDataForYear(currentTrendYear);

    // Color each bar by the performance level of its avg score
    function scoreLevelColor(v) {
        if (v <= 0)   return 'rgba(200,200,200,0.35)';  // no data — grey
        if (v >= 3.60) return 'rgba(40,167,69,0.82)';   // Outstanding
        if (v >= 2.60) return 'rgba(23,162,184,0.82)';  // Exceeds Expectations
        if (v >= 2.00) return 'rgba(255,193,7,0.82)';   // Meets Expectations
        return 'rgba(220,53,69,0.82)';                   // Needs Improvement
    }
    function scoreLevelBorder(v) {
        if (v <= 0)   return 'rgba(180,180,180,0.5)';
        if (v >= 3.60) return '#28a745';
        if (v >= 2.60) return '#17a2b8';
        if (v >= 2.00) return '#e0a800';
        return '#dc3545';
    }

    const trendChart = new Chart(document.getElementById('trendLineChart'), {
        type: 'bar',
        data: {
            labels: initialTrend.labels,
            datasets: [{
                label: 'Avg Score',
                data: initialTrend.vals,
                backgroundColor: initialTrend.vals.map(v => scoreLevelColor(v)),
                borderColor:     initialTrend.vals.map(v => scoreLevelBorder(v)),
                borderWidth: 1.5,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#888' }
                },
                y: {
                    min: 0, max: 4,
                    ticks: {
                        color: '#888', stepSize: 1,
                        callback: v => v === 0 ? '0' : v.toFixed(0)
                    },
                    grid: { color: gridColor }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const v = ctx.parsed.y;
                            if (!v) return ' No data this month';
                            let lvl = v >= 3.60 ? 'Outstanding'
                                    : v >= 2.60 ? 'Exceeds Expectations'
                                    : v >= 2.00 ? 'Meets Expectations'
                                    : 'Needs Improvement';
                            return ` Avg: ${v.toFixed(2)}  (${lvl})`;
                        }
                    }
                }
            }
        }
    });

    // Year filter click logic
    document.querySelectorAll('.trend-year-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Un-style all buttons
            document.querySelectorAll('.trend-year-btn').forEach(b => {
                b.classList.remove('active', 'btn-secondary', 'text-white');
                if (!b.classList.contains('btn-outline-secondary')) {
                    b.classList.add('btn-outline-secondary');
                }
            });
            // Style active button
            this.classList.remove('btn-outline-secondary');
            this.classList.add('active', 'btn-secondary', 'text-white');
            
            const yr = parseInt(this.dataset.year);
            const newData = getTrendDataForYear(yr);
            
            // Update chart data
            trendChart.data.labels = newData.labels;
            trendChart.data.datasets[0].data = newData.vals;
            trendChart.data.datasets[0].backgroundColor = newData.vals.map(v => scoreLevelColor(v));
            trendChart.data.datasets[0].borderColor = newData.vals.map(v => scoreLevelBorder(v));
            trendChart.update();
            
            document.getElementById('trendSubtitle').textContent = yr + ' Monthly Averages';
        });
    });

    /* ── 3. Branch Comparison (Horizontal Bar via Chart.js) ──
       NOTE: The visual branch comparison above (HTML rows) is the primary display.
       The canvas chart below is hidden by default; kept for potential future use.   */
    // The HTML branch bar rows above serve as the primary visual.
    // Chart.js horizontal bar would be cramped with 26 branches — HTML rows are cleaner.
});
</script>

<?php require_once '../includes/footer.php'; ?>