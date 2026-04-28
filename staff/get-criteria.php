<?php
require_once '../includes/session-check.php';
checkRole(['HR Staff']);

$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
if (!$template_id) { echo json_encode([]); exit; }

$result = $conn->query("SELECT criterion_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order FROM evaluation_criteria WHERE template_id = $template_id ORDER BY section, sort_order");

$criteria = ['kra' => [], 'behavior' => []];
while ($row = $result->fetch_assoc()) {
    if ($row['section'] === 'Behavior') {
        $criteria['behavior'][] = $row;
    } else {
        $criteria['kra'][] = $row;
    }
}

// Also return template weight split
$tmpl = $conn->query("SELECT kra_weight, behavior_weight FROM evaluation_templates WHERE template_id = $template_id")->fetch_assoc();
$criteria['kra_weight'] = (float)($tmpl['kra_weight'] ?? 80);
$criteria['behavior_weight'] = (float)($tmpl['behavior_weight'] ?? 20);

header('Content-Type: application/json');
echo json_encode($criteria);
