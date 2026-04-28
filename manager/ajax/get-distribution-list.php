<?php
require_once '../../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$level = $_GET['level'] ?? 'Outstanding';
$branch_id = $_GET['branch_id'] ?? '';

$query = "
    SELECT ev.total_score, ev.performance_level, ev.approved_date, e.employee_id,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, b.branch_name
    FROM evaluations ev
    JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE ev.status = 'Approved' AND ev.performance_level = ?
";

if (!empty($branch_id)) {
    $query .= " AND e.branch_id = " . (int)$branch_id;
}

$query .= " ORDER BY ev.total_score DESC, ev.approved_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $level);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<tr><td colspan="4" class="text-center text-muted py-4">No employees found in this category.</td></tr>';
    exit;
}

while ($row = $result->fetch_assoc()) {
    $level_class = getPerformanceBadgeClass($row['performance_level']);
    echo '<tr class="animate-fade-in">';
    echo '<td>
            <div class="d-flex align-items-center">
                <div style="width: 30px; height: 30px; border-radius: 8px; background: rgba(13, 110, 253, 0.1); color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; margin-right: 12px; flex-shrink: 0;">
                    ' . strtoupper(substr($row['employee_name'], 0, 1)) . '
                </div>
                <div class="text-truncate">
                    <strong style="font-size: 0.85rem;">
                        <a href="view-employee.php?id=' . $row['employee_id'] . '" class="text-decoration-none text-dark hover-primary-text">
                            ' . e($row['employee_name']) . '
                        </a>
                    </strong><br>
                    <small class="text-muted" style="font-size: 0.7rem;">' . e($row['job_title']) . '</small>
                </div>
            </div>
          </td>';
    echo '<td style="font-size: 0.8rem;">' . e($row['branch_name'] ?? 'N/A') . '</td>';
    echo '<td><span class="badge ' . $level_class . '" style="font-size: 0.7rem; border-radius: 20px;">' . $row['total_score'] . '%</span></td>';
    echo '<td style="font-size: 0.8rem; color: #888;">' . formatDate($row['approved_date']) . '</td>';
    echo '</tr>';
}
?>
