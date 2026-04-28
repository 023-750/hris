<?php
require_once '../../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$search = trim($_GET['search'] ?? '');
$dept = trim($_GET['department'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');
$position = trim($_GET['position'] ?? '');
$branch = trim($_GET['branch'] ?? '');

// Staff can search all active employees (no branch restriction)
$query = "SELECT e.*, b.branch_name, d.department_name FROM employees e 
          LEFT JOIN branches b ON e.branch_id = b.branch_id 
          LEFT JOIN departments d ON e.department_id = d.department_id
          WHERE e.is_active = 1 AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $sterm = "%$search%";
    $params[] = $sterm;
    $params[] = $sterm;
    $params[] = $sterm;
    $types .= "sss";
}

if (!empty($branch)) {
    $query .= " AND e.branch_id = ?";
    $params[] = (int)$branch;
    $types .= "i";
}

if (!empty($dept)) {
    $query .= " AND e.department_id = ?";
    $params[] = (int)$dept;
    $types .= "i";
}

if (!empty($status)) {
    $query .= " AND e.employment_status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($type)) {
    $query .= " AND e.employment_type = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($position)) {
    $query .= " AND e.job_title LIKE ?";
    $pterm = "%$position%";
    $params[] = $pterm;
    $types .= "s";
}

$query .= " ORDER BY e.last_name, e.first_name LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Prepare data for JSON
foreach ($employees as &$emp) {
    $emp['full_name'] = $emp['last_name'] . ', ' . $emp['first_name'];
    $emp['hire_date_fmt'] = formatDate($emp['hire_date']);
    $emp['base_url'] = BASE_URL;
    $emp['avatar_url'] = getEmployeeAvatar($emp['profile_picture']);
}

echo json_encode($employees);
?>
