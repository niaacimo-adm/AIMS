<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

require_once '../includes/module_guard.php';
checkModuleMaintenance($db);

if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = ''; // Default empty role
}

$module_name = 'Attachment Monitoring';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $module = $result->fetch_assoc();
    if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
        // Redirect to maintenance page or show message
        $_SESSION['error'] = "The $module_name module is currently under maintenance. Please try again later.";
        header("Location: ../unauthorized.php");
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_attachment'])) {
        // Update attachment status
        $monitoring_id = $_POST['monitoring_id'];
        $status = $_POST['status'];
        $filing_status = $_POST['filing_status'];
        $submission_date = !empty($_POST['submission_date']) ? $_POST['submission_date'] : null;
        $remarks = $_POST['remarks'];
        
        $query = "UPDATE attachments_monitoring 
                 SET status = ?, filing_status = ?, submission_date = ?, remarks = ?, updated_at = NOW() 
                 WHERE monitoring_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssi", $status, $filing_status, $submission_date, $remarks, $monitoring_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Attachment status updated successfully!'
            ];
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Failed to update attachment status.'
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
        
    } elseif (isset($_POST['add_record'])) {
        // Add new monitoring record
        $emp_id = $_POST['emp_id'];
        $period_start = $_POST['period_start'];
        $period_end = $_POST['period_end'];
        $payroll_period = date('M j', strtotime($period_start)) . ' - ' . date('M j', strtotime($period_end));
        $status = $_POST['status'];
        $filing_status = $_POST['filing_status'];
        $submission_date = !empty($_POST['submission_date']) ? $_POST['submission_date'] : null;
        $remarks = $_POST['remarks'];
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates gracefully
        $query = "INSERT INTO attachments_monitoring 
                (emp_id, payroll_period, period_start, period_end, status, filing_status, submission_date, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                filing_status = VALUES(filing_status),
                submission_date = VALUES(submission_date),
                remarks = VALUES(remarks),
                period_start = VALUES(period_start),
                period_end = VALUES(period_end),
                updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("isssssss", $emp_id, $payroll_period, $period_start, $period_end, $status, $filing_status, $submission_date, $remarks);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // New record inserted or existing record updated
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Monitoring record saved successfully!'
                ];
            } else {
                $_SESSION['toast'] = [
                    'type' => 'warning',
                    'message' => 'No changes made to the record.'
                ];
            }
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Failed to save monitoring record.'
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
        
    } elseif (isset($_POST['bulk_delete'])) {
        // Handle bulk delete
        try {
            if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
                // Delete all records
                $query = "DELETE FROM attachments_monitoring";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'All records deleted successfully!'
                ];
            } else {
                // Delete specific records
                $recordIds = $_POST['record_ids'] ?? [];
                if (empty($recordIds)) {
                    throw new Exception("No records selected for deletion.");
                }
                
                $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
                $query = "DELETE FROM attachments_monitoring WHERE monitoring_id IN ($placeholders)";
                $stmt = $db->prepare($query);
                
                // Bind parameters
                $types = str_repeat('i', count($recordIds));
                $stmt->bind_param($types, ...$recordIds);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Selected records deleted successfully!'
                    ];
                } else {
                    throw new Exception("Failed to delete records.");
                }
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => "Delete failed: " . $e->getMessage()
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
    }
}

// Get target appointment status IDs
$targetStatuses = [5, 6, 8, 9, 10, 11, 39]; // Casual - SP, Casual - PC, Regular, CARP Co-Terminus, Permanent, Temp-Regular, CARP-Contractual

// Fetch employees with target appointment statuses
$query = "SELECT 
    e.emp_id, 
    e.id_number,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.email,
    e.phone_number,
    ap.status_name as appointment_status,
    ap.color as appointment_color,
    p.position_name,
    o.office_name,
    s.section_name
FROM employee e
LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
LEFT JOIN position p ON e.position_id = p.position_id
LEFT JOIN office o ON e.office_id = o.office_id
LEFT JOIN section s ON e.section_id = s.section_id
WHERE e.appointment_status_id IN (" . implode(',', $targetStatuses) . ")
ORDER BY e.last_name, e.first_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Fetch existing monitoring records
$monitoringQuery = "SELECT 
    am.*,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.id_number
FROM attachments_monitoring am
LEFT JOIN employee e ON am.emp_id = e.emp_id
ORDER BY am.payroll_period DESC, am.updated_at DESC";
$monitoringStmt = $db->prepare($monitoringQuery);
$monitoringStmt->execute();
$monitoringResult = $monitoringStmt->get_result();
$monitoringRecords = $monitoringResult->fetch_all(MYSQLI_ASSOC);

// Get unique payroll periods for filter with actual years
$periodsQuery = "SELECT DISTINCT payroll_period, period_start, period_end FROM attachments_monitoring ORDER BY period_start DESC, period_end DESC";
$periodsStmt = $db->prepare($periodsQuery);
$periodsStmt->execute();
$periodsResult = $periodsStmt->get_result();
$payrollPeriods = $periodsResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attachments Monitoring</title>
  <?php include '../includes/header.php'; ?>
  
  <style>
    :root {
      --primary: #2a9863;
      --secondary: #24e78f;
      --success: #2a9863;
      --info: #0d9488;
      --warning: #e67700;
      --danger: #c92a2a;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --card-shadow: 0 2px 8px rgba(42,152,99,.07);
      --hover-shadow: 0 4px 24px rgba(42,152,99,.12);

      /* ══ TOKENS — green forest theme (aligned with HR Leave Monitoring) ══ */
      --h-bg:       #eef7f2;
      --h-card:     #ffffff;
      --h-card-alt: #f0faf5;
      --h-border:   rgba(42,152,99,0.18);
      --h-text:     #0f2d1e;
      --h-muted:    #4a7a5e;
      --h-primary:  #2a9863;
      --h-accent:   #24e78f;
      --h-success:  #2a9863;
      --h-warning:  #e67700;
      --h-danger:   #c92a2a;
      --h-shadow:   0 4px 24px rgba(42,152,99,.12);
      --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
      --h-bg:       #0b1f17;
      --h-card:     #102f22;
      --h-card-alt: #0e2619;
      --h-border:   rgba(36,231,143,0.12);
      --h-text:     #d4f5e5;
      --h-muted:    #6aad8a;
      --h-primary:  #24e78f;
      --h-accent:   #2a9863;
      --h-success:  #24e78f;
      --h-warning:  #ffd43b;
      --h-danger:   #ff6b6b;
      --h-shadow:   0 4px 24px rgba(0,0,0,.35);
      --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }

    body {
      background-color: var(--h-bg);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .content-wrapper {
      background-color: var(--h-bg);
    }

    .am-content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

    /* Stat cards — matches HR Leave Monitoring .stats-row/.stat-card */
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
    @media(max-width:1100px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
    @media(max-width:600px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
    .stat-card {
        background:var(--h-card); border:1px solid var(--h-border);
        border-radius:14px; padding:18px 20px;
        display:flex; align-items:center; gap:14px;
        box-shadow:var(--h-shadow-sm); transition:transform .2s,box-shadow .2s;
    }
    .stat-card:hover { transform:translateY(-3px); box-shadow:var(--h-shadow); }
    .stat-ico {
        width:48px; height:48px; border-radius:12px;
        display:flex; align-items:center; justify-content:center;
        font-size:18px; color:#fff; flex-shrink:0;
    }
    .si-tot  { background:linear-gradient(135deg,#2a9863,#24e78f); }
    .si-appr { background:linear-gradient(135deg,#099268,#20c997); }
    .si-pend { background:linear-gradient(135deg,#e67700,#f59f00); }
    .si-rejt { background:linear-gradient(135deg,#c92a2a,#e03131); }
    .stat-val { font-size:1.8rem; font-weight:800; color:var(--h-text); line-height:1; }
    .stat-lbl { font-size:.72rem; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }

    /* General card — matches HR Leave Monitoring .h-card */
    .h-card {
        background:var(--h-card); border:1px solid var(--h-border);
        border-radius:14px; overflow:hidden; box-shadow:var(--h-shadow-sm);
        transition:box-shadow .2s;
    }
    .h-card:hover { box-shadow:var(--h-shadow); }
    .h-card-head {
        padding:16px 22px; border-bottom:1px solid var(--h-border);
        background:var(--h-card-alt); display:flex; align-items:center;
        justify-content:space-between; flex-wrap:wrap; gap:8px;
    }
    .h-card-head-left { display:flex; align-items:center; gap:12px; }
    .h-card-ico {
        width:36px; height:36px; border-radius:9px;
        background:linear-gradient(135deg,#2a9863,#24e78f);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:14px; flex-shrink:0;
    }
    .h-card-head h5, .h-card-head h3 { margin:0; font-size:1rem; font-weight:700; color:var(--h-text); }
    .h-rec-count {
        font-size:.74rem; color:var(--h-muted);
        background:var(--h-bg); border-radius:20px;
        padding:3px 10px; border:1px solid var(--h-border);
    }

    /* Filter bar — matches HR Leave Monitoring .filter-bar */
    .filter-bar {
        background:var(--h-card); border:1px solid var(--h-border);
        border-radius:14px; padding:16px 20px; margin-bottom:20px;
        display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
        box-shadow:var(--h-shadow-sm);
    }
    .filter-bar .fg { margin:0; flex:1; min-width:140px; }
    .filter-bar .fg label {
        font-size:.7rem; font-weight:700; color:var(--h-muted);
        text-transform:uppercase; letter-spacing:.5px;
        margin-bottom:5px; display:block;
    }
    .filter-bar .h-ctrl {
        width:100%; background:var(--h-card); border:1.5px solid var(--h-border);
        border-radius:8px; padding:8px 12px; font-size:.85rem; color:var(--h-text);
        transition:border-color .18s, box-shadow .18s; box-sizing:border-box; height:calc(1.5em + 0.75rem + 2px);
    }
    .filter-bar .h-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px rgba(42,152,99,.13); }
    .btn-am-reset {
        background:var(--h-card); color:var(--h-muted);
        border:1.5px solid var(--h-border); border-radius:8px;
        padding:9px 14px; font-size:.85rem; cursor:pointer;
        text-decoration:none; display:inline-flex; align-items:center; gap:6px;
        transition:background .15s; white-space:nowrap;
    }
    .btn-am-reset:hover { background:var(--h-bg); color:var(--h-text); }

    /* Bulk actions bar */
    .bulk-bar {
        background:var(--h-card-alt); border:1px solid var(--h-border);
        border-radius:10px; padding:10px 16px; margin-bottom:16px;
    }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    .card {
      border: 1px solid var(--h-border);
      border-radius: 14px;
      box-shadow: var(--card-shadow);
      transition: box-shadow 0.2s ease;
      margin-bottom: 20px;
      background: var(--h-card);
    }
    
    .card:hover {
      box-shadow: var(--hover-shadow);
    }
    
    .card-header {
      background: var(--h-card-alt);
      color: var(--h-text);
      border-radius: 14px 14px 0 0 !important;
      padding: 16px 22px;
      border-bottom: 1px solid var(--h-border);
    }
    
    .card-header h3,
    .card-header .card-title {
      margin: 0;
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--h-text);
    }
    
    .small-box {
      border-radius: 14px;
      box-shadow: var(--card-shadow);
      transition: all 0.2s ease;
      border: 1px solid var(--h-border);
      overflow: hidden;
      background: var(--h-card);
    }
    
    .small-box:hover {
      transform: translateY(-3px);
      box-shadow: var(--hover-shadow);
    }
    
    .small-box .inner {
      padding: 20px;
      color: var(--h-text);
    }
    
    .small-box h3 {
      font-size: 1.8rem;
      font-weight: 800;
      margin: 0 0 6px 0;
    }
    
    .small-box .icon {
      position: absolute;
      top: 15px;
      right: 15px;
      z-index: 0;
      font-size: 70px;
      opacity: 0.15;
      transition: all 0.3s ease;
    }
    
    .small-box:hover .icon {
      transform: scale(1.1);
      opacity: 0.22;
    }

    .small-box.bg-info    { background:linear-gradient(135deg,#0d9488,#2a9863) !important; }
    .small-box.bg-success { background:linear-gradient(135deg,#099268,#20c997) !important; }
    .small-box.bg-warning { background:linear-gradient(135deg,#e67700,#f59f00) !important; color:#fff; }
    .small-box.bg-danger  { background:linear-gradient(135deg,#c92a2a,#e03131) !important; }
    .small-box.bg-info .inner, .small-box.bg-success .inner,
    .small-box.bg-warning .inner, .small-box.bg-danger .inner { color:#fff; }
    
    .btn {
      border-radius: 8px;
      font-weight: 500;
      padding: 8px 16px;
      transition: all 0.2s ease;
      border: none;
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(42,152,99,.18);
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 0.875rem;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #2a9863, #24e78f);
    }
    
    .btn-success {
      background: linear-gradient(135deg, #099268, #20c997);
    }
    
    .btn-info {
      background: linear-gradient(135deg, #0d9488, #2a9863);
      color: #fff;
    }
    
    .btn-warning {
      background: linear-gradient(135deg, #e67700, #f59f00);
      color:white;  
    }
    
    .btn-danger {
      background: linear-gradient(135deg, #c92a2a, #e03131);
    }
    
    .table-container {
      max-height: 600px;
      overflow-y: auto;
      border-radius: 8px;
    }
    
    .monitoring-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .monitoring-table th {
      background: var(--h-card-alt);
      color: var(--h-muted);
      font-weight: 700;
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .5px;
      padding: 12px 15px;
      border-bottom: 2px solid var(--h-border);
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .monitoring-table td {
      padding: 12px 15px;
      border-bottom: 1px solid var(--h-border);
      vertical-align: middle;
      color: var(--h-text);
      font-size: .87rem;
    }
    
    .monitoring-table tbody tr {
      transition: all 0.15s ease;
    }
    
    .monitoring-table tbody tr:hover {
      background-color: var(--h-card-alt);
    }
    
    .status-badge {
      font-size: 0.72rem;
      padding: 3px 10px;
      border-radius: 20px;
      font-weight: 600;
      display: inline-block;
    }
    
    .status-complete { 
      background: #e6fbf4; 
      color: #087f5b; 
    }
    body.dark-mode .status-complete { background:#0d3d2c; color:#63e6be; }
    
    .status-incomplete { 
      background: #fff8e1; 
      color: #b45309; 
    }
    body.dark-mode .status-incomplete { background:#3d2e00; color:#ffd43b; }
    
    .status-complete-late { 
      background: #e6f7ef; 
      color: #1c4d38; 
    }
    body.dark-mode .status-complete-late { background:#0e2619; color:#b8f0d4; }
    
    .status-not-submitted { 
      background: #fff0f0; 
      color: #c92a2a; 
    }
    body.dark-mode .status-not-submitted { background:#3d0f0f; color:#ff8787; }
    
    .filing-badge {
      font-size: 0.72rem;
      padding: 3px 10px;
      border-radius: 20px;
      font-weight: 600;
      display: inline-block;
    }
    
    .filing-forwarded { 
      background: #e6fbf4; 
      color: #087f5b; 
    }
    body.dark-mode .filing-forwarded { background:#0d3d2c; color:#63e6be; }
    
    .filing-not-forwarded { 
      background: #f1f5f9; 
      color: #64748b; 
    }
    body.dark-mode .filing-not-forwarded { background:#1e2030; color:#8892a4; }
    
    .appointment-badge {
      font-size: 0.7rem;
      padding: 4px 8px;
      border-radius: 12px;
      background-color: var(--h-card-alt);
      color: var(--h-muted);
      border: 1px solid var(--h-border);
    }
    
    .form-control {
      border-radius: 8px;
      border: 1.5px solid var(--h-border);
      padding: 0px 12px;
      transition: all 0.2s ease;
      background: var(--h-card);
      color: var(--h-text);
    }
    
    .form-control:focus {
      border-color: var(--h-primary);
      box-shadow: 0 0 0 3px rgba(42,152,99,.13);
    }
    
    .modal-content {
      border: none;
      border-radius: 14px;
      box-shadow: var(--hover-shadow);
      overflow: hidden;
    }
    
    .modal-header {
      background: linear-gradient(135deg,#0f2d1e,#2a9863);
      color: white;
      border-radius: 14px 14px 0 0;
      border-bottom: none;
      padding: 18px 24px;
    }
    .modal-header .close { color:#fff; opacity:.7; text-shadow:none; }
    .modal-header .close:hover { opacity:1; color:#fff; }
    
    .modal-title {
      font-weight: 700;
      font-size: 1rem;
    }
    
    .modal-footer {
      border-top: 1px solid var(--h-border);
      padding: 14px 24px;
      background: var(--h-card-alt);
    }
    
    .breadcrumb {
      background-color: transparent;
      padding: 0;
      margin-bottom: 0;
    }
    
    .content-header h1 {
      font-weight: 700;
      color: var(--h-text);
      margin-bottom: 5px;
    }
    
    /* .export-dropdown {
      min-width: 250px;
    } */

    /* .export-period {
      font-size: 0.9rem;
      padding: 8px 15px;
      border-radius: 6px;
      margin: 2px 0;
      transition: all 0.2s ease;
    }

    .export-period:hover {
      background-color: #f8f9fa;
      transform: translateX(5px);
    } */
    
    .bulk-actions-card {
      background: var(--h-card-alt);
      border: 1px solid var(--h-border);
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 20px;
      color: var(--h-text);
    }
    
    .table-actions {
      display: flex;
      gap: 8px;
      justify-content: center;
    }
    
    .table-actions .btn {
      border-radius: 6px;
      padding: 5px 10px;
    }
    
    @media (max-width: 768px) {
      .card-header .card-tools {
        margin-top: 10px;
        width: 100%;
      }
      
      .card-header .card-tools .btn {
        margin-bottom: 5px;
      }
      
      .table-container {
        overflow-x: auto;
      }
    }

    .status-no-attachments { 
        background: #fff0f0; 
        color: #c92a2a; 
    }
    body.dark-mode .status-no-attachments { background:#3d0f0f; color:#ff8787; }

    .status-lacking-information { 
        background: #fff8e1; 
        color: #b45309; 
    }
    body.dark-mode .status-lacking-information { background:#3d2e00; color:#ffd43b; }

    .status-for-review { 
        background: #f1f5f9; 
        color: #64748b; 
    }
    body.dark-mode .status-for-review { background:#1e2030; color:#8892a4; }

  /* Update the export dropdown styles */
  .export-dropdown {
    min-width: 300px !important;
    padding: 0;
  }

  .export-periods-container {
    max-height: 200px;
    overflow-y: auto;
  }

  .export-period {
    padding: 8px 15px;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.2s ease;
    display: block;
    color: #495057;
    text-decoration: none;
  }

  .export-period:hover {
    background-color: #f8f9fa;
    text-decoration: none;
    transform: translateX(3px);
    color: #495057;
  }

  .export-period:last-child {
    border-bottom: none;
  }

  .no-results-message {
    padding: 15px;
    text-align: center;
    color: #6c757d;
    font-style: italic;
  }

  /* Style the filter inputs */
  .export-dropdown .form-control-sm {
    border-radius: 4px;
    font-size: 0.8rem;
  }

  .export-dropdown .form-group {
    margin-bottom: 10px;
  }

  .export-dropdown .form-group:last-child {
    margin-bottom: 0;
  }

  .export-dropdown .small {
    font-size: 0.75rem;
  }

  /* Year filter section */
  .year-filter-section {
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 10px;
  }

  .year-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 5px;
  }

  .year-btn {
    padding: 4px 8px;
    font-size: 0.75rem;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    background: white;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .year-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
  }

  .year-btn.active {
    background: #2a9863;
    color: white;
    border-color: #2a9863;
  }

  .period-with-year {
    font-weight: 500;
  }

  .period-without-year {
    color: #6c757d;
    font-style: italic;
  }
  .year-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
    border-bottom: 1px solid #dee2e6;
    margin-top: 5px;
    padding: 8px 15px !important;
    font-size: 0.8rem !important;
  }

  .year-header:first-child {
    margin-top: 0;
  }

  .export-period {
    padding-left: 25px !important;
  }

  .export-period .text-muted {
    font-size: 0.75rem;
  }
  
/* =========================================================
   DARK MODE OVERRIDES — applied via body.dark-mode
   ========================================================= */
body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
body.dark-mode .table-hover tbody tr:hover { background: var(--notification-unread-bg) !important; }
body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
body.dark-mode .nav-tabs .nav-link { color: var(--text-muted) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs .nav-link.active { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs { border-color: var(--card-border) !important; }
body.dark-mode .tab-content, body.dark-mode .tab-pane { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .accordion .card { background: var(--card-bg) !important; }
body.dark-mode .accordion .card-header { background: var(--table-stripe) !important; }
body.dark-mode .list-group-item { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .alert { border-color: var(--card-border) !important; }
body.dark-mode .alert-info { background: #1e2f3e !important; color: #93c5fd !important; }
body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; }
body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .dataTables_info { color: var(--text-muted) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }

/* Select2 (theme: bootstrap4) — Employee picker in Add Monitoring Record modal.
   Matches the form-control look/rounded corners used elsewhere on this page. */
.select2-container--bootstrap4 .select2-selection--single {
  height: calc(2.25rem + 2px) !important;
  border: 1px solid var(--h-border, #ced4da) !important;
  border-radius: .25rem !important;
  display: flex !important;
  align-items: center !important;
  background: var(--h-card, #fff) !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
  padding-left: .75rem !important;
  color: var(--h-text, #495057) !important;
  line-height: normal !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
  height: calc(2.25rem) !important;
  right: 6px !important;
}
.select2-container--bootstrap4.select2-container--focus .select2-selection--single,
.select2-container--bootstrap4.select2-container--open .select2-selection--single {
  border-color: var(--h-primary) !important;
  box-shadow: 0 0 0 3px rgba(42,152,99,.13) !important;
}
.select2-container--bootstrap4 .select2-dropdown {
  border-color: var(--h-border, #ced4da) !important;
}
.select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
  background-color: var(--h-primary) !important;
  color: #fff !important;
}
/* Ensure the dropdown isn't clipped/misaligned inside the Bootstrap modal */
#addRecordModal .select2-container { width: 100% !important; }

body.dark-mode .monitoring-table th { background: var(--table-stripe) !important; color: var(--text-primary) !important; }
body.dark-mode .monitoring-table td { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .monitoring-table tbody tr:hover { background: var(--notification-unread-bg) !important; }
body.dark-mode .content-header { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item { color: var(--text-primary) !important; }

        .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0;
            display:flex; flex-wrap:wrap; gap:2px;
        }
        .pg-hero-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color:rgba(212,245,229,.45); }
        .pg-hero-bc-link   { color:rgba(212,245,229,.65); text-decoration:none; font-size:.8rem; }
        .pg-hero-bc-link:hover { color:#24e78f; }
        .pg-hero-bc-active { color:rgba(212,245,229,.9); font-size:.8rem; }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-actions {
            position:relative; z-index:2;
            display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px;
        }
        .pg-hero-date { color:rgba(212,245,229,.65); font-size:.82rem; align-self:center; }
        .pg-hero-btn {
            background:rgba(36,231,143,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(36,231,143,.3); color:#d4f5e5;
            border-radius:10px; padding:8px 16px;
            font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .2s, transform .18s, box-shadow .2s;
        }
        .pg-hero-btn:hover {
            background:rgba(36,231,143,.22); border-color:rgba(36,231,143,.55);
            transform:translateY(-2px); box-shadow:0 4px 16px rgba(36,231,143,.2);
            color:#d4f5e5; text-decoration:none;
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
        }
</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  
  <div class="content-wrapper">
    
        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-hex"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title">Attachments Monitoring</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Track and manage employee document attachments</p>
                </div>
            </div>
        </div>

    <section class="content am-content">
      <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-ico si-tot"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-val"><?= count($employees) ?></div>
                    <div class="stat-lbl">Total Employees</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-ico si-appr"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-val">
                        <?= count(array_filter($monitoringRecords, function($record) {
                            return $record['status'] === 'COMPLETE';
                        })) ?>
                    </div>
                    <div class="stat-lbl">Complete</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-ico si-pend"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-val">
                        <?= count(array_filter($monitoringRecords, function($record) {
                            return $record['status'] === 'COMPLETE AND LATE';
                        })) ?>
                    </div>
                    <div class="stat-lbl">Complete &amp; Late</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-ico si-rejt"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-val">
                        <?= count(array_filter($monitoringRecords, function($record) {
                            return $record['status'] === 'NO ATTACHMENTS';
                        })) ?>
                    </div>
                    <div class="stat-lbl">No Attachments</div>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="fg">
                <label>Payroll Period</label>
                <select id="periodFilter" class="h-ctrl">
                    <option value="">All Payroll Periods</option>
                    <?php foreach ($payrollPeriods as $period): ?>
                        <option value="<?= htmlspecialchars($period['payroll_period']) ?>">
                            <?= htmlspecialchars($period['payroll_period']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Status</label>
                <select id="statusFilter" class="h-ctrl">
                    <option value="">All Statuses</option>
                    <option value="NO ATTACHMENTS">No Attachments</option>
                    <option value="COMPLETE">Complete</option>
                    <option value="COMPLETE AND LATE">Complete & Late</option>
                    <option value="LACKING INFORMATION">Lacking Information</option>
                    <option value="FOR REVIEW">For Review</option>
                </select>
            </div>
            <div class="fg">
                <label>Filing Status</label>
                <select id="filingFilter" class="h-ctrl">
                    <option value="">All Filing Status</option>
                    <option value="FORWARDED">Forwarded</option>
                    <option value="NOT FORWARDED">Not Forwarded</option>
                </select>
            </div>
            <div class="fg">
                <label>Search</label>
                <input type="text" id="searchFilter" class="h-ctrl" placeholder="Search employees...">
            </div>
            <button type="button" class="btn-am-reset" id="resetFilters">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Attachments Monitoring</h3>
                <div class="card-tools">
                  <div class="btn-group">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addRecordModal">
                      <i class="fas fa-plus"></i> Add Record
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importModal">
                      <i class="fas fa-file-import"></i> Import
                    </button>
                    
                      <div class="btn-group">
                        <button type="button" class="btn btn-info btn-sm" id="exportBtn">
                          <i class="fas fa-file-export"></i> Export All
                        </button>
                        <button type="button" class="btn btn-info btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          <span class="sr-only">Toggle Dropdown</span>
                        </button>
                      <div class="dropdown-menu export-dropdown">
                        <h6 class="dropdown-header">Export by Payroll Period</h6>
                        
                        <!-- Year Filter Section -->
                        <div class="px-3 py-2 border-bottom year-filter-section">
                          <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Filter by Year:</label>
                            <select class="form-control form-control-sm" id="exportYearFilter">
                              <option value="">All Years</option>
                              <?php
                              // Extract unique years from period_start and period_end dates
                              $years = [];
                              
                              foreach ($payrollPeriods as $period) {
                                if (!empty($period['period_start'])) {
                                  $startYear = date('Y', strtotime($period['period_start']));
                                  $years[] = $startYear;
                                }
                                if (!empty($period['period_end'])) {
                                  $endYear = date('Y', strtotime($period['period_end']));
                                  $years[] = $endYear;
                                }
                              }
                              
                              // Remove duplicates and sort
                              if (!empty($years)) {
                                $years = array_unique($years);
                                rsort($years);
                              } else {
                                // Fallback to current year if no dates found
                                $currentYear = date('Y');
                                $years = [$currentYear];
                              }
                              
                              foreach ($years as $year) {
                                echo "<option value='{$year}'>{$year}</option>";
                              }
                              ?>
                            </select>
                          </div>
                          
                          <!-- Year Navigation Buttons -->
                          <div class="mb-2">
                            <label class="small font-weight-bold mb-1 d-block">Quick Year Filter:</label>
                            <div class="year-buttons">
                              <button type="button" class="year-btn clear-year-filter active" data-year="">All</button>
                              <?php foreach ($years as $year): ?>
                                <button type="button" class="year-btn" data-year="<?= $year ?>"><?= $year ?></button>
                              <?php endforeach; ?>
                            </div>
                          </div>
                          
                          <div class="form-group mb-0">
                            <label class="small font-weight-bold mb-1">Search Periods:</label>
                            <input type="text" class="form-control form-control-sm" id="exportSearchFilter" placeholder="Search periods...">
                          </div>
                        </div>
                        
                        <div class="export-periods-container">
                          <?php if (!empty($payrollPeriods)): ?>
                            <?php 
                            // Group periods by actual year from date fields
                            $groupedPeriods = [];
                            
                            foreach ($payrollPeriods as $period) {
                              $payrollPeriod = $period['payroll_period'];
                              
                              // Determine the year from period_start (most reliable)
                              if (!empty($period['period_start'])) {
                                $year = date('Y', strtotime($period['period_start']));
                              } elseif (!empty($period['period_end'])) {
                                $year = date('Y', strtotime($period['period_end']));
                              } else {
                                // Fallback to current year if no dates available
                                $year = date('Y');
                              }
                              
                              if (!isset($groupedPeriods[$year])) {
                                $groupedPeriods[$year] = [];
                              }
                              $groupedPeriods[$year][] = $period;
                            }
                            
                            // Sort years in descending order
                            krsort($groupedPeriods);
                            ?>
                            
                            <?php foreach ($groupedPeriods as $year => $periods): ?>
                              <!-- Year Header -->
                              <div class="dropdown-header year-header small font-weight-bold" style="color:#2a9863;" data-year="<?= $year ?>">
                                <i class="fas fa-calendar-alt mr-1"></i> <?= $year ?>
                              </div>
                              
                              <?php foreach ($periods as $period): ?>
                                <?php
                                // Get the actual year for this period
                                if (!empty($period['period_start'])) {
                                  $periodYear = date('Y', strtotime($period['period_start']));
                                } elseif (!empty($period['period_end'])) {
                                  $periodYear = date('Y', strtotime($period['period_end']));
                                } else {
                                  $periodYear = $year;
                                }
                                ?>
                                <a class="dropdown-item export-period" href="#" 
                                  data-period="<?= htmlspecialchars($period['payroll_period']) ?>" 
                                  data-year="<?= $periodYear ?>"
                                  data-period-start="<?= $period['period_start'] ?>"
                                  data-period-end="<?= $period['period_end'] ?>">
                                  <i class="fas fa-download mr-2 text-muted"></i><?= htmlspecialchars($period['payroll_period']) ?>
                                  <small class="text-muted ml-2">(<?= $periodYear ?>)</small>
                                </a>
                              <?php endforeach; ?>
                            <?php endforeach; ?>
                            
                          <?php else: ?>
                            <div class="dropdown-item disabled">No periods available</div>
                          <?php endif; ?>
                        </div>
                        
                        <div class="dropdown-item disabled no-results-message" style="display: none;">
                          No periods match your search
                        </div>
                      </div>
                    </div>
                    
                    <!-- Add Template Download Button -->
                    <button type="button" class="btn btn-warning btn-sm" id="templateBtn">
                      <i class="fas fa-download"></i> Template
                    </button>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <!-- Bulk Actions -->
                <div class="bulk-actions-card">
                  <div class="row align-items-center">
                    <div class="col-md-6">
                      <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="selectAll">
                        <label class="form-check-label" for="selectAll">Select All Records</label>
                      </div>
                    </div>
                    <div class="col-md-6 text-right">
                      <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                      </button>
                      <button type="button" class="btn btn-outline-danger btn-sm" id="deleteAllBtn">
                        <i class="fas fa-trash-alt"></i> Delete All
                      </button>
                    </div>
                  </div>
                </div>

                <div class="table-container">
                  <table id="monitoringTable" class="table table-bordered table-striped monitoring-table">
                    <thead>
                      <tr>
                        <th width="30"></th>
                        <th>Employee</th>
                        <th>ID Number</th>
                        <th>Appointment Status</th>
                        <th>Position</th>
                        <th>Payroll Period</th>
                        <th>Status</th>
                        <th>Filing Status</th>
                        <th>Submission Date</th>
                        <th>Remarks</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($monitoringRecords as $record): ?>
                        <tr>
                          <td>
                            <input type="checkbox" class="record-checkbox" name="record_ids[]" value="<?= $record['monitoring_id'] ?>">
                          </td>
                          <td><?= htmlspecialchars($record['employee_name']) ?></td>
                          <td><?= htmlspecialchars($record['id_number']) ?></td>
                          <td>
                            <?php 
                              // Find employee appointment status
                              $employeeAppointment = '';
                              foreach ($employees as $emp) {
                                if ($emp['emp_id'] == $record['emp_id']) {
                                  $employeeAppointment = $emp['appointment_status'];
                                  break;
                                }
                              }
                            ?>
                            <span class="badge appointment-badge"><?= htmlspecialchars($employeeAppointment) ?></span>
                          </td>
                          <td>
                            <?php 
                              // Find employee position
                              $employeePosition = '';
                              foreach ($employees as $emp) {
                                if ($emp['emp_id'] == $record['emp_id']) {
                                  $employeePosition = $emp['position_name'];
                                  break;
                                }
                              }
                              echo htmlspecialchars($employeePosition);
                            ?>
                          </td>
                          <td><?= htmlspecialchars($record['payroll_period']) ?></td>
                          <td width="180">
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $record['status'])) ?>">
                              <?= htmlspecialchars($record['status']) ?>
                            </span>
                          </td>
                          <td width="180">
                            <span class="filing-badge filing-<?= strtolower(str_replace(' ', '-', $record['filing_status'])) ?>">
                              <?= htmlspecialchars($record['filing_status']) ?>
                            </span>
                          </td>
                          <td><?= $record['submission_date'] ? htmlspecialchars($record['submission_date']) : 'N/A' ?></td>
                          <td><?= htmlspecialchars($record['remarks']) ?: 'N/A' ?></td>
                          <td><?= htmlspecialchars($record['updated_at']) ?></td>
                          <td>
                            <div class="table-actions">
                              <button class="btn btn-sm btn-warning edit-record" 
                                      data-id="<?= $record['monitoring_id'] ?>"
                                      data-status="<?= $record['status'] ?>"
                                      data-filing_status="<?= $record['filing_status'] ?>"
                                      data-date="<?= $record['submission_date'] ?>"
                                      data-remarks="<?= htmlspecialchars($record['remarks']) ?>">
                                <i class="fas fa-edit"></i>
                              </button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  
  <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="form-group">
            <label>Employee</label>
            <select name="emp_id" id="add_emp_id" class="form-control" style="width:100%;" required>
              <option value=""></option>
              <?php foreach ($employees as $employee): ?>
                <option value="<?= $employee['emp_id'] ?>">
                  <?= htmlspecialchars($employee['employee_name']) ?> (<?= htmlspecialchars($employee['id_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payroll Period</label>
            <div class="row">
              <div class="col-md-6">
                <input type="date" name="period_start" class="form-control" required>
              </div>
              <div class="col-md-6">
                <input type="date" name="period_end" class="form-control" required>
              </div>
            </div>
            <small class="form-text text-muted">Select start and end dates for the payroll period</small>
          </div>
          <div class="form-group">
              <label>Status</label>
              <select name="status" class="form-control" required>
                  <option value="NO ATTACHMENTS">No Attachments</option>
                  <option value="COMPLETE">Complete</option>
                  <option value="COMPLETE AND LATE">Complete and Late</option>
                  <option value="LACKING INFORMATION">Lacking Information</option>
                  <option value="FOR REVIEW">For Review</option>
              </select>
          </div>
          <div class="form-group">
            <label>Filing Status</label>
            <select name="filing_status" class="form-control" required>
              <option value="NOT FORWARDED">Not Forwarded</option>
              <option value="FORWARDED">Forwarded</option>
            </select>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_record" class="btn btn-primary">Add Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST">
        <input type="hidden" name="monitoring_id" id="edit_monitoring_id">
        <div class="modal-body">
          <div class="form-group">
              <label>Status</label>
              <select name="status" id="edit_status" class="form-control" required>
                  <option value="NO ATTACHMENTS">No Attachments</option>
                  <option value="COMPLETE">Complete</option>
                  <option value="COMPLETE AND LATE">Complete and Late</option>
                  <option value="LACKING INFORMATION">Lacking Information</option>
                  <option value="FOR REVIEW">For Review</option>
              </select>
          </div>
          <div class="form-group">
            <label>Filing Status</label>
            <select name="filing_status" id="edit_filing_status" class="form-control" required>
              <option value="NOT FORWARDED">Not Forwarded</option>
              <option value="FORWARDED">Forwarded</option>
            </select>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" id="edit_submission_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" id="edit_remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_attachment" class="btn btn-primary">Update Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- In the Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Excel File</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" id="importForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label>Select Excel File (.xlsx)</label>
            <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            <small class="form-text text-muted">
                File must use the <a href="../public/templates/ATTACHMENTS-MONITORING-SHEET.xlsx" download>template format</a>. Required columns: NO. | NAMES | STATUS | REMARKS | DATE | PAYROLL PERIOD
            </small>
            <div class="alert alert-warning mt-3" role="alert">
                <strong>Note:</strong> 
                <ul class="mb-0 pl-3">
                    <li>Valid Status values: NOT SUBMITTED, COMPLETE, INCOMPLETE, COMPLETE AND LATE</li>
                    <li>Employee names must match exactly with database records</li>
                    <li>Submission Date should be in YYYY-MM-DD format</li>
                </ul>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="importSubmitBtn">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
  // Show toast notification if exists
  <?php if (isset($_SESSION['toast'])): ?>
    const toast = <?= json_encode($_SESSION['toast']) ?>;
    showToast(toast.type, toast.message);
    <?php unset($_SESSION['toast']); ?>
  <?php endif; ?>
  
  // Employee picker (Add Monitoring Record modal) — Select2, same setup as
  // the Certificate of Employment "Issue COE" employee select.
  function initAddRecordEmployeeSelect2() {
    var $sel = $('#add_emp_id');
    if ($sel.hasClass('select2-hidden-accessible')) {
      $sel.select2('destroy');
    }
    $sel.select2({
      theme: 'bootstrap4',
      width: '100%',
      placeholder: 'Select Employee',
      allowClear: true,
      dropdownParent: $('#addRecordModal')
    });
  }

  // (Re)initialize every time the modal opens so it renders correctly even
  // though it starts hidden (Select2 can miscalculate width on a display:none element).
  $('#addRecordModal').on('show.bs.modal', function() {
    initAddRecordEmployeeSelect2();
  });

  // Table filtering
  $('#periodFilter, #statusFilter, #filingFilter, #searchFilter').on('input change', function() {
    filterTable();
  });
  
  // Reset filters
  $('#resetFilters').on('click', function() {
    $('#periodFilter, #statusFilter, #filingFilter').val('');
    $('#searchFilter').val('');
    filterTable();
  });
  
  // Edit record modal
  $('.edit-record').on('click', function() {
    const id = $(this).data('id');
    const status = $(this).data('status');
    const filingStatus = $(this).data('filing_status');
    const date = $(this).data('date');
    const remarks = $(this).data('remarks');
    
    $('#edit_monitoring_id').val(id);
    $('#edit_status').val(status);
    $('#edit_filing_status').val(filingStatus);
    $('#edit_submission_date').val(date);
    $('#edit_remarks').val(remarks);
    
    $('#editRecordModal').modal('show');
  });
  
  // Select all records
  $('#selectAll').on('change', function() {
    $('.record-checkbox').prop('checked', this.checked);
    updateBulkDeleteButton();
  });
  
  // Update bulk delete button state
  $('.record-checkbox').on('change', function() {
    updateBulkDeleteButton();
  });
  
  // Bulk delete
  $('#bulkDeleteBtn').on('click', function() {
    if (confirm('Are you sure you want to delete the selected records?')) {
      const form = $('<form>').attr({
        method: 'POST',
        action: ''
      });
      
      $('.record-checkbox:checked').each(function() {
        form.append($('<input>').attr({
          type: 'hidden',
          name: 'record_ids[]',
          value: $(this).val()
        }));
      });
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'bulk_delete',
        value: '1'
      }));
      
      $('body').append(form);
      form.submit();
    }
  });
  
  // Delete all records
  $('#deleteAllBtn').on('click', function() {
    if (confirm('Are you sure you want to delete ALL records? This action cannot be undone.')) {
      const form = $('<form>').attr({
        method: 'POST',
        action: ''
      });
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'bulk_delete',
        value: '1'
      }));
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'delete_all',
        value: '1'
      }));
      
      $('body').append(form);
      form.submit();
    }
  });
  
  // Export by period
  $('.export-period').on('click', function(e) {
    e.preventDefault();
    const period = $(this).data('period');
    
    // Create a form to submit the export request
    const form = $('<form>').attr({
      method: 'GET',
      action: 'attachments_export.php'
    });
    
    form.append($('<input>').attr({
      type: 'hidden',
      name: 'export_period',
      value: period
    }));
    
    $('body').append(form);
    form.submit();
  });

  // Template download
  $('#templateBtn').on('click', function() {
    window.location.href = '../public/templates/ATTACHMENTS-MONITORING-SHEET.xlsx';
  });

  // Export all
  $('#exportBtn').on('click', function() {
    // Create a form to export all records
    const form = $('<form>').attr({
      method: 'GET',
      action: 'attachments_export.php'
    });
    
    form.append($('<input>').attr({
      type: 'hidden',
      name: 'export_all',
      value: '1'
    }));
    
    $('body').append(form);
    form.submit();
  });
  
  function filterTable() {
    const period = $('#periodFilter').val().toLowerCase();
    const status = $('#statusFilter').val().toLowerCase();
    const filing = $('#filingFilter').val().toLowerCase();
    const search = $('#searchFilter').val().toLowerCase();
    
    $('#monitoringTable tbody tr').each(function() {
      const rowPeriod = $(this).find('td:eq(5)').text().toLowerCase();
      const rowStatus = $(this).find('td:eq(6)').text().toLowerCase();
      const rowFiling = $(this).find('td:eq(7)').text().toLowerCase();
      const rowText = $(this).text().toLowerCase();
      
      const periodMatch = !period || rowPeriod.includes(period);
      const statusMatch = !status || rowStatus.includes(status);
      const filingMatch = !filing || rowFiling.includes(filing);
      const searchMatch = !search || rowText.includes(search);
      
      $(this).toggle(periodMatch && statusMatch && filingMatch && searchMatch);
    });
  }
  
  function updateBulkDeleteButton() {
    const checkedCount = $('.record-checkbox:checked').length;
    $('#bulkDeleteBtn').prop('disabled', checkedCount === 0);
    if (checkedCount > 0) {
      $('#bulkDeleteBtn').html('<i class="fas fa-trash"></i> Delete Selected (' + checkedCount + ')');
    } else {
      $('#bulkDeleteBtn').html('<i class="fas fa-trash"></i> Delete Selected');
    }
  }
  
  function showToast(type, message) {
    // Create toast element
    const toast = $(`
      <div class="toast align-items-center text-white bg-${type} border-0 position-fixed" 
           style="top: 20px; right: 20px; z-index: 9999;" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `);
    
    $('body').append(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
    
    // Remove toast from DOM after it's hidden
    toast.on('hidden.bs.toast', function() {
      $(this).remove();
    });
  }

      // Handle import form submission with SweetAlert
    $('#importModal form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importing...');
        
        $.ajax({
            url: 'attachments_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    if (response.type === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            html: `<strong>${response.message}</strong><br><br>${response.details}`,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#3085d6'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Completed with Issues',
                            html: `<strong>${response.message}</strong><br><br>${response.details}`,
                            confirmButtonText: 'OK, Reload',
                            confirmButtonColor: '#3085d6',
                            showCancelButton: true,
                            cancelButtonText: 'View Errors'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                location.reload();
                            } else {
                                // Keep the modal open to show errors
                                $('#importModal').modal('hide');
                            }
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Import Failed',
                        html: `<strong>${response.message}</strong><br><br>${response.details}`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalText);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Error',
                    text: 'An error occurred while uploading the file. Please try again.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            }
        });
    });

    // Add file validation before submission
    $('input[name="excel_file"]').on('change', function(e) {
        const file = this.files[0];
        if (file) {
            const fileName = file.name;
            const fileExtension = fileName.split('.').pop().toLowerCase();
            
            if (fileExtension !== 'xlsx') {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File',
                    text: 'Please select an Excel file (.xlsx)',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    $(this).val('');
                });
            }
            
            // Check file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'File size must be less than 10MB',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    $(this).val('');
                });
            }
        }
    });

    // Show import instructions when modal opens
    $('#importModal').on('show.bs.modal', function() {
        // Reset form
        $(this).find('form')[0].reset();
    });
});
// Export dropdown filtering with year navigation
$(document).ready(function() {
  console.log('Export filtering initialized');
  
  // Filter export periods by year and search
  function filterExportPeriods() {
    const yearFilter = $('#exportYearFilter').val();
    const searchFilter = $('#exportSearchFilter').val().toLowerCase();
    const $periods = $('.export-period');
    const $yearHeaders = $('.year-header');
    const $noResults = $('.no-results-message');
    
    let visibleCount = 0;
    let anyYearVisible = false;
    
    console.log('Filtering - Year:', yearFilter, 'Search:', searchFilter);
    
    // Hide all periods first
    $periods.hide();
    $yearHeaders.hide();
    
    // Filter periods
    $periods.each(function() {
      const $period = $(this);
      const periodText = $period.text().toLowerCase();
      const periodYear = $period.data('year').toString();
      
      const yearMatch = !yearFilter || periodYear === yearFilter;
      const searchMatch = !searchFilter || periodText.includes(searchFilter);
      
      if (yearMatch && searchMatch) {
        $period.show();
        visibleCount++;
        
        // Show the corresponding year header
        const yearHeader = $('.year-header[data-year="' + periodYear + '"]');
        yearHeader.show();
        anyYearVisible = true;
      }
    });
    
    // Show/hide no results message
    if (visibleCount === 0) {
      $noResults.show();
    } else {
      $noResults.hide();
    }
    
    // If no year filter is applied but we have search, show relevant year headers
    if (!yearFilter && searchFilter) {
      $yearHeaders.each(function() {
        const $header = $(this);
        const headerYear = $header.data('year');
        const hasVisiblePeriods = $periods.filter(function() {
          return $(this).data('year').toString() === headerYear.toString() && 
                 $(this).is(':visible');
        }).length > 0;
        
        if (hasVisiblePeriods) {
          $header.show();
        }
      });
    }
  }
  
  // Event handlers
  $('#exportYearFilter').on('change', filterExportPeriods);
  $('#exportSearchFilter').on('keyup', filterExportPeriods);
  
  // Year button clicks
  $('.year-btn').on('click', function() {
    const year = $(this).data('year') || '';
    $('#exportYearFilter').val(year);
    
    // Update active state
    $('.year-btn').removeClass('active');
    $(this).addClass('active');
    
    filterExportPeriods();
  });
  
  // Reset filters when dropdown is opened
  $('.dropdown-toggle').on('click', function() {
    // Small delay to ensure dropdown is open before resetting
    setTimeout(() => {
      $('#exportYearFilter').val('');
      $('#exportSearchFilter').val('');
      $('.year-btn').removeClass('active');
      $('.year-btn[data-year=""]').addClass('active');
      filterExportPeriods();
    }, 100);
  });
  
  // Prevent dropdown from closing when clicking inside
  $('.export-dropdown').on('click', function(e) {
    e.stopPropagation();
  });
  
  // Handle export period clicks
  $('.export-period').on('click', function(e) {
    e.preventDefault();
    const period = $(this).data('period');
    console.log('Exporting period:', period);
    
    // Create a form to submit the export request
    const form = $('<form>').attr({
      method: 'GET',
      action: 'attachments_export.php'
    });
    
    form.append($('<input>').attr({
      type: 'hidden',
      name: 'export_period',
      value: period
    }));
    
    $('body').append(form);
    form.submit();
  });
});
</script>
</body>
</html>