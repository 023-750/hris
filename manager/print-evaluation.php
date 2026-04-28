<?php
require_once '../includes/session-check.php';
// Allow Manager, Supervisor, and Staff to print their own/relevant evaluations
checkRole(['HR Manager', 'HR Supervisor', 'HR Staff']);
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Invalid Evaluation ID.");

// Fetch evaluation details with all joins
$query = "SELECT ev.*, 
    CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, d.department_name,
    u.full_name as submitted_by_name, u2.full_name as endorsed_by_name, u3.full_name as approved_by_name,
    et.template_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN users u2 ON ev.endorsed_by = u2.user_id
    LEFT JOIN users u3 ON ev.approved_by = u3.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.evaluation_id = $id";

$result = $conn->query($query);
if (!$result || $result->num_rows === 0) die("Evaluation not found.");
$row = $result->fetch_assoc();

// Security check: Staff can only print their own
if ($_SESSION['role'] === 'HR Staff' && $row['submitted_by'] != $_SESSION['user_id']) {
    die("Access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HRD Form-013.01 - <?php echo e($row['employee_name']); ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    background: #e0e0e0;
    padding: 20px;
    color: #000;
  }
  .page {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto 30px auto;
    padding: 12mm 15mm 12mm 15mm;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
  }
  .no-print {
    text-align: center;
    padding-bottom: 20px;
  }
  .btn {
    padding: 8px 20px;
    cursor: pointer;
    border-radius: 4px;
    border: 1px solid #ccc;
    background: #f8f9fa;
    font-weight: bold;
    margin: 0 5px;
    text-decoration: none;
    color: #333;
    display: inline-block;
  }
  .btn-primary { background: #007bff; color: #fff; border-color: #007bff; }

  /* HEADER */
  .rating-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 0;
  }
  .rating-table th {
    background: #fff;
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 10px;
    font-weight: bold;
    text-align: left;
  }
  .rating-table td {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 10px;
  }

  .kra-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
  }
  .kra-table th {
    border: 1px solid #000;
    padding: 4px 6px;
    font-size: 10px;
    font-weight: bold;
    text-align: center;
    background: #fff;
    vertical-align: middle;
  }
  .kra-table th.left { text-align: left; }
  .kra-table td {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 10px;
    height: 19px;
  }

  .kra2-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 8px;
  }
  .kra2-table th {
    border: 1px solid #000;
    padding: 4px 6px;
    font-size: 10px;
    font-weight: bold;
    background: #fff;
    text-align: left;
  }
  .kra2-table td {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 10px;
    height: 20px;
  }

  .dev-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 8px;
  }
  .dev-table th {
    border: 1px solid #000;
    padding: 5px 6px;
    font-size: 10px;
    font-weight: bold;
    text-align: center;
    background: #fff;
  }
  .dev-table td {
    border: 1px solid #000;
    padding: 0 6px;
    font-size: 10px;
    height: 22px;
  }

  .comment-box {
    border: 1px solid #000;
    margin-bottom: 6px;
  }
  .comment-box .label {
    font-weight: bold;
    font-style: italic;
    padding: 4px 6px 2px;
    font-size: 10px;
  }
  .comment-box .content {
    min-height: 45px;
    padding: 2px 8px;
    font-size: 10px;
  }

  @media print {
    body { background: none; padding: 0; }
    .page { box-shadow: none; margin: 0; page-break-after: always; }
    .no-print { display: none; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" class="btn btn-primary">Print Now</button>
  <button onclick="window.close()" class="btn">Close</button>
</div>

<!-- PAGE 1 -->
<div class="page">

  <!-- Header -->
  <div style="border:1px solid #000; display:flex; margin-bottom:0;">
    <div style="width:155px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; padding:4px;">
      <img src="https://raquelpawnshop.com/wp-content/uploads/2023/05/png-logo.png" style="max-width:140px; max-height:55px; object-fit:contain;" alt="Logo">
    </div>
    <div style="flex:1; border-right:1px solid #000;">
      <div style="font-size:16px; font-weight:bold; text-align:center; padding:4px 0 2px;">PERFORMANCE EVALUATION FORM</div>
      <table style="width:100%; border-collapse:collapse; border-top:1px solid #000;">
        <tr>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold; width:110px;">Revision Date</td>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px;">3 January 2022</td>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Code</td>
          <td style="border-bottom:1px solid #000; padding:2px 6px; font-size:10px;">HRD Form-013.01</td>
        </tr>
        <tr>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Effective Date</td>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px;">14 January 2022</td>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Control No.</td>
          <td style="padding:2px 6px; font-size:10px;"></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Employee Info -->
  <table style="width:100%; border-collapse:collapse; border:1px solid #000; border-top:none;">
    <tr>
      <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:4px 6px; font-size:10px; width:50%;">
        Name of Employee: <span style="font-weight:bold; text-decoration:underline;"><?php echo e($row['employee_name']); ?></span>
      </td>
      <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:4px 6px; font-size:10px; width:30%;">
        Position: <span style="font-weight:bold;"><?php echo e($row['job_title']); ?></span>
      </td>
      <td style="border-bottom:1px solid #000; padding:4px 6px; font-size:10px;">
        Date: <span style="font-style:italic;"><?php echo date('m/d/Y'); ?></span>
      </td>
    </tr>
    <tr>
      <td style="border-right:1px solid #000; padding:4px 6px; font-size:11px; vertical-align:top;">
        Evaluation Period:<br>
        <table style="width:100%; border-collapse:collapse; margin-top:2px;">
          <tr>
            <td style="padding:0; border:none; width:auto; font-size:11px;">From: <span style="font-weight:bold; text-decoration:underline;"><?php echo date('m/d/Y', strtotime($row['evaluation_period_start'])); ?></span></td>
            <td style="padding:0 0 0 10px; border:none; width:auto; font-size:11px;">To: <span style="font-weight:bold; text-decoration:underline;"><?php echo date('m/d/Y', strtotime($row['evaluation_period_end'])); ?></span></td>
          </tr>
          <tr>
            <td style="padding:0 0 0 35px; border:none; font-size:8px; color:#555;">(mm/dd/yyyy)</td>
            <td style="padding:0 0 0 30px; border:none; font-size:8px; color:#555;">(mm/dd/yyyy)</td>
          </tr>
        </table>
      </td>
      <td style="border-right:1px solid #000; padding:4px 6px; font-size:10px; vertical-align:top;">
        Please check one (1):<br>
        <div style="padding-left: 45px; margin-top: 4px; line-height: 1.4;">
          <?php $et = $row['evaluation_type'] ?? 'Annual'; ?>
          <?php echo ($et === 'Initial') ? '&#9745;' : '&#9744;'; ?> Initial &nbsp;&nbsp; 
          <?php echo ($et === 'Final') ? '&#9745;' : '&#9744;'; ?> Final<br>
          <?php echo ($et === 'Quarterly') ? '&#9745;' : '&#9744;'; ?> Quarterly &nbsp;&nbsp; 
          <?php echo ($et === 'Annual') ? '&#9745;' : '&#9744;'; ?> Annual
        </div>
      </td>
      <td style="padding:4px 6px; font-size:10px; vertical-align:top;">
        Department/Branch:<br>
        <span style="font-weight:bold;"><?php echo e($row['department_name'] ?? 'N/A'); ?></span>
      </td>
    </tr>
  </table>

  <!-- Performance Rating Scale -->
  <div style="font-weight:bold; font-size:11px; border:1px solid #000; border-bottom:none; padding:3px 6px; margin-top:6px;">Performance Rating Scale</div>
  <table class="rating-table">
    <thead>
      <tr>
        <th style="width:110px;">RATING SCALE</th>
        <th style="width:150px;">DESCRIPTION</th>
        <th>DEFINITION</th>
      </tr>
    </thead>
    <tbody>
      <?php $pl = $row['performance_level'] ?? ''; ?>
      <tr <?php echo ($pl === 'Outstanding') ? 'style="background-color:#d4edda !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>><td>3.60 – 4.00</td><td>Outstanding</td><td>Performance significantly exceeds standards and expectations</td></tr>
      <tr <?php echo ($pl === 'Exceeds Expectations') ? 'style="background-color:#cce5ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>><td>2.60 – 3.59</td><td>Exceeds Expectations</td><td>Performance exceeds standards and expectations</td></tr>
      <tr <?php echo ($pl === 'Meets Expectations') ? 'style="background-color:#fff3cd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>><td>2.00 – 2.59</td><td>Meets Expectations</td><td>Performance meets standards and expectations</td></tr>
      <tr <?php echo ($pl === 'Needs Improvement') ? 'style="background-color:#f8d7da !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;"' : ''; ?>><td>1.00 – 1.99</td><td>Needs Improvement</td><td>Performance did not meet standards and expectations</td></tr>
    </tbody>
  </table>

  <!-- Performance Evaluation Summary -->
  <div style="margin-top:6px;">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000;">
      <thead>
        <tr>
          <th style="border:1px solid #000; padding:4px 6px; font-size:11px; font-weight:bold; text-align:left; background:#fff;">Performance Evaluation Summary</th>
          <th style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:70px;">Weight</th>
          <th style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:70px;">Rating</th>
          <th style="border:1px solid #000; padding:4px 12px; font-size:10px; font-weight:bold; text-align:center; background:#fff; width:90px;">Signature</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px;">I. Key Result Areas based on Strategic Programs and<br>&nbsp;&nbsp;&nbsp;Regular Job Requirements</td>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center;"><?php echo $row['kra_weight'] ?? 80; ?>%</td>
          <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold;"><?php echo $row['kra_subtotal']; ?></td>
          <td rowspan="3" style="border:1px solid #000; padding:6px; text-align:center; font-size:9px; vertical-align:middle;">
            <div style="border-bottom:1px solid #000; margin:0 8px 2px; height:30px;"></div>
            <div style="font-style:italic; margin-bottom:16px;">Employee</div>
            <div style="border-bottom:1px solid #000; margin:0 8px 2px; height:12px;"></div>
            <div style="font-style:italic;">Rater</div>
          </td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px;">II. Key Result Areas based on Behavior and Values</td>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center;"><?php echo $row['behavior_weight'] ?? 20; ?>%</td>
          <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold;"><?php echo $row['behavior_average']; ?></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px; font-weight:bold; text-align:center;">TOTAL</td>
          <td style="border:1px solid #000; padding:3px 6px; font-size:10px; text-align:center; font-weight:bold;">100%</td>
          <td style="border:1px solid #000; padding:3px 6px; text-align:center; font-weight:bold; color:blue;"><?php echo $row['total_score']; ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Performance Result -->
  <div style="font-weight:bold; font-size:12px; text-align:center; padding:6px 0 2px;">Performance Result</div>
  <table class="kra-table">
    <thead>
      <tr>
        <th class="left" colspan="2" style="text-align:left; width:70%;">I. Key Result Areas based on Strategic Programs and Job Requirements (80%)</th>
        <th style="width:10%;">Weight</th>
        <th style="width:10%;">Rating</th>
        <th style="width:10%;">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $id AND ec.section = 'KRA' ORDER BY ec.sort_order");
      $kra_count = 0;
      while ($k = $kra_q->fetch_assoc()):
          $kra_count++;
      ?>
      <tr>
        <td style="width:55px; border:1px solid #000; padding:3px 6px;">KRA <?php echo $kra_count; ?></td>
        <td style="border:1px solid #000;"><?php echo e($k['criterion_name']); ?></td>
        <td style="border:1px solid #000; text-align:center;"><?php echo $k['weight']; ?>%</td>
        <td style="border:1px solid #000; text-align:center;"><?php echo $k['score_value']; ?></td>
        <td style="border:1px solid #000; text-align:center;"><?php echo $k['weighted_score']; ?></td>
      </tr>
      <?php endwhile; ?>
      <tr>
        <td colspan="2" style="border:1px solid #000; text-align:right; font-weight:bold; padding:3px 8px; font-size:10px;">SUB TOTAL</td>
        <td style="border:1px solid #000; text-align:center; font-weight:bold; padding:3px 6px; font-size:10px;">100%</td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000; text-align:center; font-weight:bold; padding:3px 6px; font-size:10px;"><?php echo $row['kra_subtotal']; ?></td>
      </tr>
    </tbody>
  </table>

</div>

<!-- PAGE 2 -->
<div class="page">

  <!-- Header (same as page 1) -->
  <div style="border:1px solid #000; display:flex; margin-bottom:6px;">
    <div style="width:155px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; padding:4px;">
      <img src="https://raquelpawnshop.com/wp-content/uploads/2023/05/png-logo.png" style="max-width:140px; max-height:55px; object-fit:contain;" alt="Logo">
    </div>
    <div style="flex:1; border-right:1px solid #000;">
      <div style="font-size:16px; font-weight:bold; text-align:center; padding:4px 0 2px;">PERFORMANCE EVALUATION FORM</div>
      <table style="width:100%; border-collapse:collapse; border-top:1px solid #000;">
        <tr>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold; width:110px;">Revision Date</td>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px;">3 January 2022</td>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Code</td>
          <td style="border-bottom:1px solid #000; padding:2px 6px; font-size:10px;">HRD Form-013.01</td>
        </tr>
        <tr>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Effective Date</td>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px;">14 January 2022</td>
          <td style="border-right:1px solid #000; padding:2px 6px; font-size:10px; font-weight:bold;">Control No.</td>
          <td style="padding:2px 6px; font-size:10px;"></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- KRA II -->
  <div style="font-weight:bold; font-size:11px; border:1px solid #000; border-bottom:none; padding:3px 6px;">II. Key Result Areas based on Behavior and Values (20%)</div>
  <table class="kra2-table">
    <thead>
      <tr>
        <th style="width:30%;">Key Result Area</th>
        <th>Key Performance Indicator</th>
        <th style="width:80px; text-align:center;">Rating</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $id AND ec.section = 'Behavior' ORDER BY ec.sort_order");
      $behavior_map = [
          'Positive Attitude' => 'Displays positive attitude at work.',
          'Respect' => 'Shows respect to all people in the organization.',
          'Accountability' => 'Takes full responsibility of the job including special task or assignment.',
          'Commitment' => 'Demonstrates strong commitment to the job.',
          'Teamwork' => 'Works cooperatively with others in achieving the goals.',
          'Integrity' => 'Exhibits honesty and strong moral uprightness.',
          'Continuous Improvement' => 'Provides diligent effort to continuously focus on getting better.',
          'Excellent Client Experience' => 'Delivers the service beyond the expectations of the internal and external clients.'
      ];
      
      $beh_scores = [];
      while ($b = $beh_q->fetch_assoc()) {
          $beh_scores[$b['criterion_name']] = $b['score_value'];
      }
      
      $idx = 1;
      foreach ($behavior_map as $name => $kpi):
          $score = $beh_scores[$name] ?? '';
      ?>
      <tr>
        <td><?php echo $idx++; ?>. <?php echo e($name); ?></td>
        <td><?php echo e($kpi); ?></td>
        <td style="border:1px solid #000; text-align:center; font-weight:bold;"><?php echo $score; ?></td>
      </tr>
      <?php endforeach; ?>
      <tr style="font-weight:bold;">
        <td colspan="2" style="text-align:right; padding:3px 8px; border:1px solid #000;">Average</td>
        <td style="border:1px solid #000; text-align:center; padding:3px 6px;"><?php echo $row['behavior_average']; ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Developmental Plan -->
  <div style="font-weight:bold; font-size:12px; text-align:center; padding:6px 0 2px;">DEVELOPMENTAL PLAN</div>
  <table class="dev-table">
    <thead>
      <tr>
        <th style="width:45%;">Areas of improvement that the employee should<br>concentrate on (if any)</th>
        <th style="width:30%;">Support Needed</th>
        <th style="width:25%;">Time Frame</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = $id ORDER BY sort_order");
      $dev_count = 0;
      while ($dp = $dev_q->fetch_assoc()):
          $dev_count++;
      ?>
      <tr>
        <td><?php echo e($dp['improvement_area']); ?></td>
        <td><?php echo e($dp['support_needed']); ?></td>
        <td style="text-align:center;"><?php echo e($dp['time_frame']); ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- Career Growth -->
  <div style="font-weight:bold; font-size:12px; text-align:center; padding:6px 0 2px;">CAREER GROWTH</div>
  <div style="border:1px solid #000; padding:6px 8px; font-size:10px; margin-bottom:8px;">
    Is the employee better suited for another job within the company? &nbsp; 
    <?php $suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
    <?php echo ($suited == 1) ? '&#9745;' : '&#9744;'; ?> Yes &nbsp;&nbsp; <?php echo ($suited == 0) ? '&#9745;' : '&#9744;'; ?> No<br>
    If yes, specify the job function / department: <span style="display:inline-block; border-bottom:1px solid #000; min-width:300px; margin-left:4px;"><?php echo ($suited == 1) ? e($row['desired_position'] ?? '') : ''; ?></span>
  </div>

  <!-- Employee's Comments -->
  <div class="comment-box">
    <div class="label">Employee's Comments:</div>
    <div class="content"><?php echo nl2br(e($row['staff_comments'])); ?></div>
    <div style="text-align:center; padding-bottom:4px; margin-top:20px;">
      <div style="display:inline-block; border-bottom:1px solid #000; width:200px; margin-bottom:2px; font-weight:bold; font-size:11px;">
        <?php echo strtoupper(e($row['submitted_by_name'] ?? '')); ?>
      </div>
      <br>
      <div style="display:inline-block; font-style:italic; font-size:9px;">Signature over Printed Name</div>
    </div>
  </div>

  <!-- Evaluator's Comments -->
  <div class="comment-box">
    <div class="label">Evaluator's Comments:</div>
    <div class="content"><?php echo nl2br(e($row['supervisor_comments'])); ?></div>
    <div style="text-align:center; padding-bottom:4px; margin-top:20px;">
      <div style="display:inline-block; border-bottom:1px solid #000; width:200px; margin-bottom:2px; font-weight:bold; font-size:11px;">
        <?php echo strtoupper(e($row['endorsed_by_name'] ?? '')); ?>
      </div>
      <br>
      <div style="display:inline-block; font-style:italic; font-size:9px;">Signature over Printed Name</div>
    </div>
  </div>

  <!-- Executives' Signature -->
  <div style="border:1px solid #000; padding:6px 8px 14px; margin-bottom:6px;">
    <div style="font-weight:bold; font-style:italic; font-size:10px; margin-bottom:30px;">Executives' Signature</div>
    <div style="display:flex; justify-content:space-between; padding:0 30px;">
      <div style="text-align:center;">
        <div style="border-top:1px solid #000; width:140px; margin-bottom:2px;"><?php echo e($row['approved_by_name'] ?? ''); ?></div>
        <div style="font-size:9px;">Vice President / Manager</div>
      </div>
      <div style="text-align:center;">
        <div style="border-top:1px solid #000; width:140px; margin-bottom:2px;"></div>
        <div style="font-size:9px;">President and CEO</div>
      </div>
    </div>
  </div>

  <!-- HR Use Only -->
  <div style="border:1px solid #000; padding:6px 8px;">
    <div style="font-style:italic; font-weight:bold; text-align:center; font-size:10px; margin-bottom:6px;">For Human Resources Use Only</div>
    <div style="font-size:10px;">
      PMS Form received on: <span style="display:inline-block; border-bottom:1px solid #000; width:120px; margin:0 4px;"></span>
      &nbsp;&nbsp;&nbsp; Received by: <span style="display:inline-block; border-bottom:1px solid #000; width:160px; margin:0 4px;"></span>
    </div>
  </div>

</div>

</body>
</html>
