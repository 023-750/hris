<?php
require_once '../../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$branch_id = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';

$query = "
    SELECT ev.total_score, ev.performance_level, e.employee_id,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, d.department_name
    FROM evaluations ev
    JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE ev.status = 'Approved'
";

if (!empty($branch_id)) {
    $query .= " AND e.branch_id = " . (int)$branch_id;
}

$query .= " ORDER BY ev.total_score DESC, ev.submitted_date DESC LIMIT 5";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo '<div class="empty-state-card py-5">
            <i class="fas fa-medal text-muted" style="opacity: 0.1; font-size: 3rem;"></i>
            <p class="mb-0 mt-3 small">No performers found for this filter.</p>
          </div>';
    exit;
}

echo '<div class="list-group list-group-flush pt-2">';
$rank = 1;
while ($tp = $result->fetch_assoc()) {
    $medal_color = ($rank == 1) ? '#ffd700' : (($rank == 2) ? '#c0c0c0' : (($rank == 3) ? '#cd7f32' : '#adb5bd'));
    
    // Fallback rendering for avatar initials safely
    $names = explode(' ', $tp['employee_name']);
    $fn = isset($names[0]) ? substr($names[0], 0, 1) : '';
    $ln = isset($names[1]) ? substr($names[1], 0, 1) : substr($names[0], 1, 1);
    $initials = strtoupper($fn . $ln);

    echo '
    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom border-light animate-fade-in" style="background: transparent;">
        <div class="d-flex align-items-center gap-3 w-100">
            <div class="fw-bold" style="color: ' . $medal_color . '; width: 22px; text-align: center;">#' . $rank . '</div>
            <div style="width: 38px; height: 38px; border-radius: 50%; font-size: 0.85rem; background: rgba(13, 110, 253, 0.08); display:flex; align-items:center; justify-content:center; color: var(--primary-blue); font-weight: 800; flex-shrink: 0;">
                ' . $initials . '
            </div>
            <div style="min-width: 0; flex: 1;">
                <h6 class="mb-0 fw-bold text-truncate" style="font-size: 0.9rem;">
                    <a href="view-employee.php?id=' . $tp['employee_id'] . '" class="text-decoration-none text-dark hover-primary-text">
                        ' . e($tp['employee_name']) . '
                    </a>
                </h6>
                <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">' . e($tp['job_title']) . ' &bull; ' . e($tp['department_name'] ?? 'N/A') . '</small>
            </div>
            <div class="text-end ps-2">
                <div class="badge bg-success rounded-pill px-2 py-1" style="font-size: 0.8rem; box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2);">' . $tp['total_score'] . '%</div>
            </div>
        </div>
    </div>';
    $rank++;
}
echo '</div>';
?>
