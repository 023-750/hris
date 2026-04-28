<?php
/**
 * AJAX Endpoint: Generate Report Preview
 * Returns JSON with HTML table and count for on-screen preview.
 */
header('Content-Type: application/json');

require_once '../../includes/session-check.php';
checkRole(['HR Manager']);

$report_type = $_POST['report_type'] ?? '';
$branch_id   = intval($_POST['branch_id'] ?? 0);
$department_id = intval($_POST['department'] ?? 0);
$date_from   = trim($_POST['date_from'] ?? '');
$date_to     = trim($_POST['date_to'] ?? '');

$html = '';
$count = 0;

try {
    switch ($report_type) {

        // ===========================
        // EMPLOYEE MASTERLIST
        // ===========================
        case 'employee_masterlist':
            $where = "WHERE e.is_active = 1 AND e.deleted_at IS NULL AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
            $params = [];
            $types = '';

            if ($branch_id > 0) {
                $where .= " AND e.branch_id = ?";
                $params[] = $branch_id;
                $types .= 'i';
            }
            if ($department_id > 0) {
                $where .= " AND e.department_id = ?";
                $params[] = $department_id;
                $types .= 'i';
            }

            $sql = "SELECT e.employee_id, e.first_name, e.last_name, e.middle_name,
                           e.job_title, d.department_name, e.hire_date, e.employment_status, e.employment_type,
                           b.branch_name,
                           c.mobile_number, c.personal_email
                    FROM employees e
                    LEFT JOIN branches b ON e.branch_id = b.branch_id
                    LEFT JOIN departments d ON e.department_id = d.department_id
                    LEFT JOIN employee_contacts c ON e.employee_id = c.employee_id
                    $where
                    ORDER BY e.last_name, e.first_name";

            $stmt = $conn->prepare($sql);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->num_rows;

            if ($count === 0) {
                echo json_encode(['success' => false, 'message' => 'No employees found matching your filters.']);
                exit;
            }

            $html = '<div class="table-responsive"><table class="table table-hover table-striped">';
            $html .= '<thead><tr>
                <th>#</th>
                <th>Employee Name</th>
                <th>Position</th>
                <th>Department</th>
                <th>Branch</th>
                <th>Hire Date</th>
                <th>Status</th>
                <th>Type</th>
                <th>Mobile</th>
                <th>Email</th>
            </tr></thead><tbody>';

            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $fullName = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . (!empty($row['middle_name']) ? ' ' . $row['middle_name'] : ''));
                $statusClass = $row['employment_status'] === 'Regular' ? 'bg-success' : ($row['employment_status'] === 'Probationary' ? 'bg-warning text-dark' : 'bg-secondary');
                $html .= '<tr>
                    <td>' . $i++ . '</td>
                    <td><strong>' . $fullName . '</strong></td>
                    <td>' . htmlspecialchars($row['job_title'] ?? '') . '</td>
                    <td>' . htmlspecialchars($row['department_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['branch_name'] ?? 'N/A') . '</td>
                    <td>' . ($row['hire_date'] ? date('M d, Y', strtotime($row['hire_date'])) : 'N/A') . '</td>
                    <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($row['employment_status'] ?? '') . '</span></td>
                    <td>' . htmlspecialchars($row['employment_type'] ?? '') . '</td>
                    <td>' . htmlspecialchars($row['mobile_number'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['personal_email'] ?? 'N/A') . '</td>
                </tr>';
            }
            $html .= '</tbody></table></div>';
            $stmt->close();
            break;

        // ===========================
        // PERFORMANCE SUMMARY
        // ===========================
        case 'performance_summary':
            $where = "WHERE ev.status = 'Approved' AND ev.deleted_at IS NULL AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
            $params = [];
            $types = '';

            if ($branch_id > 0) {
                $where .= " AND e.branch_id = ?";
                $params[] = $branch_id;
                $types .= 'i';
            }
            if ($department_id > 0) {
                $where .= " AND e.department_id = ?";
                $params[] = $department_id;
                $types .= 'i';
            }
            if (!empty($date_from)) {
                $where .= " AND ev.approved_date >= ?";
                $params[] = $date_from;
                $types .= 's';
            }
            if (!empty($date_to)) {
                $where .= " AND ev.approved_date <= ?";
                $params[] = $date_to . ' 23:59:59';
                $types .= 's';
            }

            $sql = "SELECT e.employee_id, 
                           CONCAT(e.last_name, ', ', e.first_name) as employee_name,
                           e.job_title, d.department_name,
                           b.branch_name,
                           et.template_name,
                           ev.total_score, ev.performance_level,
                           ev.evaluation_period_start, ev.evaluation_period_end,
                           ev.approved_date
                    FROM evaluations ev
                    LEFT JOIN employees e ON ev.employee_id = e.employee_id
                    LEFT JOIN branches b ON e.branch_id = b.branch_id
                    LEFT JOIN departments d ON e.department_id = d.department_id
                    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
                    $where
                    ORDER BY ev.approved_date DESC, e.last_name";

            $stmt = $conn->prepare($sql);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->num_rows;

            if ($count === 0) {
                echo json_encode(['success' => false, 'message' => 'No approved evaluations found matching your filters.']);
                exit;
            }

            $html = '<div class="table-responsive"><table class="table table-hover table-striped">';
            $html .= '<thead><tr>
                <th>#</th>
                <th>Employee</th>
                <th>Position</th>
                <th>Department</th>
                <th>Branch</th>
                <th>Template</th>
                <th>Eval Period</th>
                <th>Score</th>
                <th>Performance Level</th>
                <th>Approved Date</th>
            </tr></thead><tbody>';

            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $perfClass = 'bg-secondary';
                switch ($row['performance_level']) {
                    case 'Excellent': $perfClass = 'bg-success'; break;
                    case 'Above Average': $perfClass = 'bg-info'; break;
                    case 'Average': $perfClass = 'bg-warning text-dark'; break;
                    case 'Needs Improvement': $perfClass = 'bg-danger'; break;
                }
                $period = '';
                if ($row['evaluation_period_start'] && $row['evaluation_period_end']) {
                    $period = date('M Y', strtotime($row['evaluation_period_start'])) . ' - ' . date('M Y', strtotime($row['evaluation_period_end']));
                }
                $html .= '<tr>
                    <td>' . $i++ . '</td>
                    <td><strong>' . htmlspecialchars($row['employee_name']) . '</strong></td>
                    <td>' . htmlspecialchars($row['job_title'] ?? '') . '</td>
                    <td>' . htmlspecialchars($row['department_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['branch_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['template_name'] ?? '') . '</td>
                    <td>' . $period . '</td>
                    <td><strong>' . number_format($row['total_score'], 1) . '%</strong></td>
                    <td><span class="badge ' . $perfClass . '">' . htmlspecialchars($row['performance_level'] ?? 'N/A') . '</span></td>
                    <td>' . ($row['approved_date'] ? date('M d, Y', strtotime($row['approved_date'])) : 'N/A') . '</td>
                </tr>';
            }
            $html .= '</tbody></table></div>';
            $stmt->close();
            break;

        // ===========================
        // CAREER MOVEMENTS
        // ===========================
        case 'career_movements':
            $where = "WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
            $params = [];
            $types = '';

            if ($branch_id > 0) {
                $where .= " AND (cm.previous_branch_id = ? OR cm.new_branch_id = ?)";
                $params[] = $branch_id;
                $params[] = $branch_id;
                $types .= 'ii';
            }
            if ($department_id > 0) {
                $where .= " AND e.department_id = ?";
                $params[] = $department_id;
                $types .= 'i';
            }
            if (!empty($date_from)) {
                $where .= " AND cm.effective_date >= ?";
                $params[] = $date_from;
                $types .= 's';
            }
            if (!empty($date_to)) {
                $where .= " AND cm.effective_date <= ?";
                $params[] = $date_to;
                $types .= 's';
            }

            $sql = "SELECT cm.movement_id, cm.movement_type, cm.previous_position, cm.new_position,
                           cm.effective_date, cm.reason, cm.approval_status,
                           CONCAT(e.last_name, ', ', e.first_name) as employee_name,
                           d.department_name,
                           pb.branch_name as prev_branch,
                           nb.branch_name as new_branch,
                           u.full_name as logged_by_name
                    FROM career_movements cm
                    LEFT JOIN employees e ON cm.employee_id = e.employee_id
                    LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
                    LEFT JOIN branches nb ON cm.new_branch_id = nb.branch_id
                    LEFT JOIN departments d ON e.department_id = d.department_id
                    LEFT JOIN users u ON cm.logged_by = u.user_id
                    $where
                    ORDER BY cm.effective_date DESC";

            $stmt = $conn->prepare($sql);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->num_rows;

            if ($count === 0) {
                echo json_encode(['success' => false, 'message' => 'No career movements found matching your filters.']);
                exit;
            }

            $html = '<div class="table-responsive"><table class="table table-hover table-striped">';
            $html .= '<thead><tr>
                <th>#</th>
                <th>Employee</th>
                <th>Movement Type</th>
                <th>Previous Position</th>
                <th>New Position</th>
                <th>From Branch</th>
                <th>To Branch</th>
                <th>Effective Date</th>
                <th>Status</th>
                <th>Logged By</th>
            </tr></thead><tbody>';

            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $typeClass = 'bg-info';
                switch ($row['movement_type']) {
                    case 'Promotion': $typeClass = 'bg-success'; break;
                    case 'Transfer': $typeClass = 'bg-primary'; break;
                    case 'Demotion': $typeClass = 'bg-danger'; break;
                    case 'Role Change': $typeClass = 'bg-warning text-dark'; break;
                }
                $statusClass = $row['approval_status'] === 'Approved' ? 'bg-success' : ($row['approval_status'] === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark');
                $html .= '<tr>
                    <td>' . $i++ . '</td>
                    <td><strong>' . htmlspecialchars($row['employee_name'] ?? '') . '</strong></td>
                    <td><span class="badge ' . $typeClass . '">' . htmlspecialchars($row['movement_type']) . '</span></td>
                    <td>' . htmlspecialchars($row['previous_position'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['new_position'] ?? '') . '</td>
                    <td>' . htmlspecialchars($row['prev_branch'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['new_branch'] ?? 'N/A') . '</td>
                    <td>' . date('M d, Y', strtotime($row['effective_date'])) . '</td>
                    <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($row['approval_status']) . '</span></td>
                    <td>' . htmlspecialchars($row['logged_by_name'] ?? 'N/A') . '</td>
                </tr>';
            }
            $html .= '</tbody></table></div>';
            $stmt->close();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid report type selected.']);
            exit;
    }

    echo json_encode(['success' => true, 'html' => $html, 'count' => $count]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
