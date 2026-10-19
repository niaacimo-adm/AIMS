<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/attachments_helpers.php';

$database = new Database();
$db = $database->getConnection();
$is_admin = hasPermission('manage_appointment');

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
        $monitoring_id = (int)$_POST['monitoring_id'];
        $status = am_post_status($_POST['status'] ?? '', 'FOR REVIEW');
        $filing_status = am_post_filing($_POST['filing_status'] ?? '');
        $submission_date = am_post_date($_POST['submission_date'] ?? '');
        $remarks = $_POST['remarks'] ?? '';
        $date_informed = am_post_date($_POST['date_informed'] ?? '');
        $compliance_status = am_post_compliance($_POST['compliance_status'] ?? '');
        $date_complied = am_post_date($_POST['date_complied'] ?? '');
        $payroll_status = am_post_status($_POST['payroll_status'] ?? '', null);   // empty = same as Status
        
        $query = "UPDATE attachments_monitoring 
                 SET status = ?, filing_status = ?, submission_date = ?, remarks = ?,
                     date_informed = ?, compliance_status = ?, date_complied = ?, payroll_status = ?,
                     updated_at = NOW() 
                 WHERE monitoring_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssssssi", $status, $filing_status, $submission_date, $remarks,
                          $date_informed, $compliance_status, $date_complied, $payroll_status, $monitoring_id);
        
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
        // Add new monitoring record (or update the existing one for the same employee + period)
        $emp_id = (int)$_POST['emp_id'];
        $period_start = am_post_date($_POST['period_start'] ?? '');
        $period_end = am_post_date($_POST['period_end'] ?? '');
        
        try {
            if (!$emp_id || !$period_start || !$period_end || $period_end < $period_start) {
                throw new Exception('Please choose an employee and a valid payroll period.');
            }
            $result = am_upsert_record($db, $emp_id, $period_start, $period_end, [
                'status'            => am_post_status($_POST['status'] ?? '', 'FOR REVIEW'),
                'filing_status'     => am_post_filing($_POST['filing_status'] ?? ''),
                'submission_date'   => am_post_date($_POST['submission_date'] ?? ''),
                'remarks'           => $_POST['remarks'] ?? '',
                'date_informed'     => am_post_date($_POST['date_informed'] ?? ''),
                'compliance_status' => am_post_compliance($_POST['compliance_status'] ?? ''),
                'date_complied'     => am_post_date($_POST['date_complied'] ?? ''),
                'payroll_status'    => am_post_status($_POST['payroll_status'] ?? '', null),
            ]);
            $_SESSION['toast'] = ($result === 'unchanged')
                ? ['type' => 'warning', 'message' => 'No changes made to the record.']
                : ['type' => 'success', 'message' => 'Monitoring record saved successfully!'];
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to save monitoring record. ' . $e->getMessage()];
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
  
  <link rel="stylesheet" href="../css/module-theme.css">
  <style>
    /* Page-specific styles — tokens, hero, cards, modals, tables and form controls
       come from assets/css/module-theme.css */
    .am-content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

    /* Stat cards */
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
    @media(max-width:1100px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
    .stat-card {
        background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; padding:18px 20px;
        display:flex; align-items:center; gap:14px; box-shadow:var(--h-shadow-sm);
        transition:transform .2s, box-shadow .2s;
    }
    .stat-card:hover { transform:translateY(-3px); box-shadow:var(--h-shadow); }
    .stat-ico { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; flex-shrink:0; }
    .si-tot  { background:linear-gradient(135deg,#2a9863,#24e78f); }
    .si-appr { background:linear-gradient(135deg,#099268,#20c997); }
    .si-pend { background:linear-gradient(135deg,#e67700,#f59f00); }
    .si-rejt { background:linear-gradient(135deg,#c92a2a,#e03131); }
    .stat-val { font-size:1.8rem; font-weight:800; color:var(--h-text); line-height:1; }
    .stat-lbl { font-size:.72rem; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }

    /* Filter bar */
    .filter-bar {
        background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; padding:16px 20px;
        margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; box-shadow:var(--h-shadow-sm);
    }
    .filter-bar .fg { margin:0; flex:1; min-width:140px; }
    .filter-bar .fg label { font-size:.7rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; display:block; }
    .filter-bar .h-ctrl {
        width:100%; background:var(--h-card-alt); border:1.5px solid var(--h-border); border-radius:8px;
        padding:8px 12px; font-size:.85rem; color:var(--h-text); height:calc(1.5em + .75rem + 2px); box-sizing:border-box;
        transition:border-color .18s, box-shadow .18s;
    }
    .filter-bar .h-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px var(--h-primary-dim); }
    .filter-bar select.h-ctrl option { background:var(--h-card); color:var(--h-text); }
    .btn-am-reset {
        background:var(--h-card-alt); color:var(--h-muted); border:1.5px solid var(--h-border); border-radius:8px;
        padding:9px 14px; font-size:.85rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
    }
    .btn-am-reset:hover { color:var(--h-text); filter:brightness(.97); }

    /* Card toolbar buttons */
    .card-header .card-tools .btn { border:none; border-radius:8px; font-weight:600; font-size:.8rem; }
    .card-header .card-tools .btn-success { background:#099268; color:#fff; }
    .card-header .card-tools .btn-info    { background:#0d9488; color:#fff; }
    .card-header .card-tools .btn-warning { background:#e67700; color:#fff; }
    .card-header .card-tools .btn:hover { filter:brightness(1.1); }

    /* Bulk actions */
    .bulk-actions-card { background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:10px; padding:12px 16px; margin-bottom:16px; color:var(--h-text); }
    .bulk-actions-card .form-check-label { color:var(--h-text); font-weight:600; font-size:.85rem; }
    .bulk-actions-card .btn { border-radius:8px; font-weight:600; font-size:.8rem; }
    .bulk-actions-card .btn-danger { background:var(--h-danger); border:none; color:#fff; }
    .bulk-actions-card .btn-outline-danger { color:var(--h-danger); border:1.5px solid var(--h-danger); background:transparent; }
    .bulk-actions-card .btn-outline-danger:hover { background:var(--h-danger); color:#fff; }
    .form-check-input, .record-checkbox, #selectAll { accent-color:var(--h-primary); cursor:pointer; }

    /* Table */
    .table-container { max-height:600px; overflow-y:auto; border-radius:8px; }
    .monitoring-table thead th { position:sticky; top:0; z-index:10; white-space:nowrap; }
    .table-actions { display:flex; gap:6px; justify-content:center; }
    @media (max-width:768px) { .table-container { overflow-x:auto; } }

    /* Status / filing / appointment badges */
    .status-badge, .filing-badge { font-size:.72rem; padding:3px 10px; border-radius:20px; font-weight:600; display:inline-block; }
    .appointment-badge { font-size:.7rem; padding:4px 8px; border-radius:12px; background:var(--h-surface3); color:var(--h-muted); border:1px solid var(--h-border); }
    .status-complete, .filing-forwarded                { background:#e6fbf4; color:#087f5b; }
    .status-complete-and-late, .status-complete-late   { background:#e6f7ef; color:#1c4d38; }
    .status-incomplete, .status-lacking-information    { background:#fff8e1; color:#b45309; }
    .status-not-submitted, .status-no-attachments, .status-late-compliance { background:#fff0f0; color:#c92a2a; }
    .status-for-review, .filing-not-forwarded          { background:#f1f5f9; color:#64748b; }
    .status-on-time-compliance                         { background:#e6f9ec; color:#1e7e34; }
    body.dark-mode .status-complete, body.dark-mode .filing-forwarded { background:#0d3d2c; color:#63e6be; }
    body.dark-mode .status-complete-and-late, body.dark-mode .status-complete-late { background:#0e2619; color:#b8f0d4; }
    body.dark-mode .status-incomplete, body.dark-mode .status-lacking-information { background:#3d2e00; color:#ffd43b; }
    body.dark-mode .status-not-submitted, body.dark-mode .status-no-attachments, body.dark-mode .status-late-compliance { background:#3d0f0f; color:#ff8787; }
    body.dark-mode .status-for-review, body.dark-mode .filing-not-forwarded { background:#1e2030; color:#8892a4; }
    body.dark-mode .status-on-time-compliance { background:#0f2d1a; color:#69db7c; }

    /* View record modal */
    .view-head { margin-bottom:18px; padding-bottom:12px; border-bottom:1px solid var(--h-border); }
    .view-name { font-size:18px; font-weight:700; color:var(--h-text); }
    .view-sub { font-size:13px; color:var(--h-muted); margin-top:2px; }
    .view-item { margin-bottom:14px; }
    .view-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--h-muted); margin-bottom:3px; }
    .view-value { font-size:14px; color:var(--h-text); word-break:break-word; }
    .view-remarks { white-space:pre-wrap; background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:8px; padding:10px 12px; min-height:42px; }
    .scrum-style-modal .btn-warning { background:var(--h-warning) !important; color:#fff !important; border:none !important; border-radius:8px !important; font-weight:700 !important; }
    .scrum-style-modal .alert-warning { background:rgba(230,119,0,.10); border:1px solid rgba(230,119,0,.25); color:var(--h-text); border-radius:8px; font-size:.82rem; }
    .scrum-style-modal a { color:var(--h-primary); }
    .scrum-style-modal input[type="file"].form-control { height:auto; padding:6px 10px; }
    .scrum-style-modal .form-text, .scrum-style-modal small.text-muted { color:var(--h-muted) !important; }

    /* Export dropdown */
    .dropdown-menu.export-dropdown {
        min-width:300px !important; padding:0; background:var(--h-card); border:1px solid var(--h-border);
        border-radius:12px; box-shadow:var(--h-shadow); overflow:hidden;
    }
    .export-dropdown .dropdown-header { color:var(--h-muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; }
    .export-dropdown label { color:var(--h-muted); font-size:.7rem; text-transform:uppercase; letter-spacing:.4px; }
    .export-dropdown .form-control-sm { font-size:.8rem; }
    .year-filter-section { border-bottom:1px solid var(--h-border); }
    .export-periods-container { max-height:200px; overflow-y:auto; }
    .export-period { padding:8px 15px 8px 25px !important; display:block; color:var(--h-text); text-decoration:none; border-bottom:1px solid var(--h-border); transition:background .15s; }
    .export-period:hover { background:var(--h-primary-dim); color:var(--h-text); text-decoration:none; }
    .export-period:last-child { border-bottom:none; }
    .export-dropdown .year-header { background:var(--h-card-alt) !important; border-bottom:1px solid var(--h-border); padding:8px 15px !important; font-size:.8rem !important; color:var(--h-primary) !important; }
    .no-results-message { padding:15px; text-align:center; color:var(--h-muted); font-style:italic; }
    .year-buttons { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; }
    .year-btn { padding:4px 8px; font-size:.75rem; border-radius:6px; border:1px solid var(--h-border); background:var(--h-card-alt); color:var(--h-text); cursor:pointer; transition:all .2s; }
    .year-btn:hover { border-color:var(--h-primary); }
    .year-btn.active { background:var(--h-primary); color:#fff; border-color:var(--h-primary); }

    /* Select2 (bootstrap4 theme) employee picker */
    .select2-container--bootstrap4 .select2-selection--single {
        height:calc(2.25rem + 2px) !important; border:1.5px solid var(--h-border) !important; border-radius:8px !important;
        display:flex !important; align-items:center !important; background:var(--h-card-alt) !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { padding-left:.75rem !important; color:var(--h-text) !important; line-height:normal !important; }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow { height:calc(2.25rem) !important; right:6px !important; }
    .select2-container--bootstrap4.select2-container--focus .select2-selection--single,
    .select2-container--bootstrap4.select2-container--open .select2-selection--single { border-color:var(--h-primary) !important; box-shadow:0 0 0 3px var(--h-primary-dim) !important; }
    .select2-container--bootstrap4 .select2-dropdown { background:var(--h-card-alt) !important; border:1px solid var(--h-border) !important; border-radius:8px !important; box-shadow:var(--h-shadow) !important; }
    .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field { background:var(--h-card) !important; border:1px solid var(--h-border) !important; color:var(--h-text) !important; border-radius:6px !important; }
    .select2-container--bootstrap4 .select2-results__option { color:var(--h-text) !important; background:transparent !important; }
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] { background:var(--h-primary) !important; color:#fff !important; }
    #addRecordModal .select2-container { width:100% !important; }
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
                    <div class="pg-hero-title"><i class="fas fa-paperclip"></i>Attachments Monitoring</div>
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
                <h3 class="card-title">Monitoring Records</h3>
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
                  <table id="monitoringTable" class="table table-striped mod-table monitoring-table">
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
                          <td><?= htmlspecialchars($record['remarks']) ?: 'N/A' ?></td>
                          <td><?= htmlspecialchars($record['updated_at']) ?></td>
                          <td>
                            <div class="table-actions">
                              <?php
                                $viewCompliance = !empty($record['compliance_status'])
                                    ? (AM_COMPLIANCE_LABELS[$record['compliance_status']] ?? $record['compliance_status']) : '';
                                $viewPayroll = !empty($record['payroll_status']) ? $record['payroll_status'] : $record['status'];
                              ?>
                              <button type="button" class="btn btn-sm btn-info view-record" title="View details"
                                      data-id="<?= (int)$record['monitoring_id'] ?>"
                                      data-employee="<?= htmlspecialchars((string)$record['employee_name']) ?>"
                                      data-id_number="<?= htmlspecialchars((string)$record['id_number']) ?>"
                                      data-appointment="<?= htmlspecialchars((string)$employeeAppointment) ?>"
                                      data-position="<?= htmlspecialchars((string)$employeePosition) ?>"
                                      data-period="<?= htmlspecialchars((string)$record['payroll_period']) ?>"
                                      data-status="<?= htmlspecialchars((string)$record['status']) ?>"
                                      data-filing="<?= htmlspecialchars((string)$record['filing_status']) ?>"
                                      data-submitted="<?= htmlspecialchars((string)($record['submission_date'] ?? '')) ?>"
                                      data-informed="<?= htmlspecialchars((string)($record['date_informed'] ?? '')) ?>"
                                      data-compliance="<?= htmlspecialchars((string)($record['compliance_status'] ?? '')) ?>"
                                      data-compliance_label="<?= htmlspecialchars((string)$viewCompliance) ?>"
                                      data-complied="<?= htmlspecialchars((string)($record['date_complied'] ?? '')) ?>"
                                      data-payroll="<?= htmlspecialchars((string)$viewPayroll) ?>"
                                      data-remarks="<?= htmlspecialchars((string)$record['remarks']) ?>"
                                      data-updated="<?= htmlspecialchars((string)$record['updated_at']) ?>">
                                <i class="fas fa-eye"></i>
                              </button>
                              <button class="btn btn-sm btn-warning edit-record" 
                                      data-id="<?= $record['monitoring_id'] ?>"
                                      data-status="<?= $record['status'] ?>"
                                      data-filing_status="<?= $record['filing_status'] ?>"
                                      data-date="<?= $record['submission_date'] ?>"
                                      data-date_informed="<?= htmlspecialchars((string)($record['date_informed'] ?? '')) ?>"
                                      data-compliance_status="<?= htmlspecialchars((string)($record['compliance_status'] ?? '')) ?>"
                                      data-date_complied="<?= htmlspecialchars((string)($record['date_complied'] ?? '')) ?>"
                                      data-payroll_status="<?= htmlspecialchars((string)($record['payroll_status'] ?? '')) ?>"
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
<div class="modal fade scrum-style-modal" id="addRecordModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-clipboard-list"></i>Add Monitoring Record</h5>
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
          <div class="row">
            <div class="col-md-6">
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
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Filing Status</label>
                <select name="filing_status" class="form-control" required>
                  <option value="NOT FORWARDED">Not Forwarded</option>
                  <option value="FORWARDED">Forwarded</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" class="form-control">
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Date Informed</label>
                <input type="date" name="date_informed" id="add_date_informed" class="form-control">
                <small class="form-text text-muted">Date the employee was informed of the missing/incomplete attachments</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status of Compliance</label>
                <select name="compliance_status" id="add_compliance_status" class="form-control">
                  <option value="">— None —</option>
                  <option value="ON-TIME COMPLIANCE">On-Time Compliance</option>
                  <option value="LATE COMPLIANCE">Late Compliance</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Date Complied</label>
                <input type="date" name="date_complied" id="add_date_complied" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>For Payroll (final status)</label>
                <select name="payroll_status" id="add_payroll_status" class="form-control">
                  <option value="">Same as Status</option>
                  <option value="COMPLETE">Complete</option>
                  <option value="COMPLETE AND LATE">Complete and Late</option>
                  <option value="LACKING INFORMATION">Lacking Information</option>
                  <option value="FOR REVIEW">For Review</option>
                  <option value="NO ATTACHMENTS">No Attachments</option>
                </select>
                <small class="form-text text-muted">Status after compliance, shown in the FOR PAYROLL column</small>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
          <div class="info-box">
            <i class="fas fa-info-circle"></i>Saving a record for an employee/period that already exists will update that existing record instead of creating a duplicate.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_record" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Add Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Record Modal -->
<div class="modal fade scrum-style-modal" id="viewRecordModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-eye"></i>View Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="view-head">
          <div class="view-name" id="view_employee"></div>
          <div class="view-sub" id="view_sub"></div>
        </div>
        <div class="row">
          <div class="col-md-6 view-item"><div class="view-label">Payroll Period</div><div class="view-value" id="view_period"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Last Updated</div><div class="view-value" id="view_updated"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Status</div><div class="view-value" id="view_status"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Filing Status</div><div class="view-value" id="view_filing"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Date Submitted</div><div class="view-value" id="view_submitted"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Date Informed</div><div class="view-value" id="view_informed"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Status of Compliance</div><div class="view-value" id="view_compliance"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">Date Complied</div><div class="view-value" id="view_complied"></div></div>
          <div class="col-md-6 view-item"><div class="view-label">For Payroll (final status)</div><div class="view-value" id="view_payroll"></div></div>
          <div class="col-12 view-item"><div class="view-label">Remarks</div><div class="view-value view-remarks" id="view_remarks"></div></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning" id="viewEditBtn"><i class="fas fa-edit mr-1"></i>Edit</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade scrum-style-modal" id="editRecordModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pen"></i>Edit Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST">
        <input type="hidden" name="monitoring_id" id="edit_monitoring_id">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
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
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Filing Status</label>
                <select name="filing_status" id="edit_filing_status" class="form-control" required>
                  <option value="NOT FORWARDED">Not Forwarded</option>
                  <option value="FORWARDED">Forwarded</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" id="edit_submission_date" class="form-control">
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Date Informed</label>
                <input type="date" name="date_informed" id="edit_date_informed" class="form-control">
                <small class="form-text text-muted">Date the employee was informed of the missing/incomplete attachments</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status of Compliance</label>
                <select name="compliance_status" id="edit_compliance_status" class="form-control">
                  <option value="">— None —</option>
                  <option value="ON-TIME COMPLIANCE">On-Time Compliance</option>
                  <option value="LATE COMPLIANCE">Late Compliance</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Date Complied</label>
                <input type="date" name="date_complied" id="edit_date_complied" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>For Payroll (final status)</label>
                <select name="payroll_status" id="edit_payroll_status" class="form-control">
                  <option value="">Same as Status</option>
                  <option value="COMPLETE">Complete</option>
                  <option value="COMPLETE AND LATE">Complete and Late</option>
                  <option value="LACKING INFORMATION">Lacking Information</option>
                  <option value="FOR REVIEW">For Review</option>
                  <option value="NO ATTACHMENTS">No Attachments</option>
                </select>
                <small class="form-text text-muted">Status after compliance, shown in the FOR PAYROLL column</small>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" id="edit_remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_attachment" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Update Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- In the Import Modal -->
<div class="modal fade scrum-style-modal" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-excel"></i>Import Excel File</h5>
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
                File must use the <a href="../public/templates/STATUS_OF_ATTACHMENTS_2026.xlsx" download>template format</a>
                (NO. | NAMES | DATE SUBMITTED | STATUS | REMARKS | DATE INFORMED | STATUS OF COMPLIANCE | DATE COMPLIED | FOR PAYROLL).
            </small>
          </div>
          <div class="form-group">
            <label>Payroll Period <small class="text-muted">(optional)</small></label>
            <div class="row">
              <div class="col-6"><input type="date" name="period_start" class="form-control"></div>
              <div class="col-6"><input type="date" name="period_end" class="form-control"></div>
            </div>
            <small class="form-text text-muted">
              Leave empty to read the period from the FOR PAYROLL header (e.g. 10/1-15/2026) or the title. If filled in, it is used for every sheet in the file.
            </small>
            <div class="alert alert-warning mt-3" role="alert">
                <strong>Note:</strong> 
                <ul class="mb-0 pl-3">
                    <li>Status values: Complete, Lacking Information, For Review, No Attachment, Complete and Late</li>
                    <li>Names are matched as <em>LASTNAME, First M.</em> (the template format)</li>
                    <li>Rows with only a name are skipped; existing records for the same employee and period are updated</li>
                    <li>Filing Status is not part of the template and is kept as is</li>
                </ul>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="importSubmitBtn"><i class="fas fa-upload mr-1"></i>Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// ---- Attachments Monitoring: DataTable ----------------------------------
// Uses DataTables if the layout already loads it, otherwise loads it from the CDN.
var monitoringDT = null;

(function() {
  function loadCss(href) {
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = href;
    document.head.appendChild(l);
  }
  function loadScript(src, done) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = done;
    s.onerror = function() { console.warn('Could not load ' + src); };
    document.head.appendChild(s);
  }
  function ensureDataTables(cb) {
    if ($.fn && $.fn.DataTable) { cb(); return; }
    loadCss('https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css');
    loadScript('https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', function() {
      loadScript('https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js', cb);
    });
  }

  function initMonitoringDataTable() {
    var $t = $('#monitoringTable');
    if (!$t.length || !$.fn.DataTable || $.fn.DataTable.isDataTable($t)) { return; }
    monitoringDT = $t.DataTable({
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
      order: [],                                   // keep the server's order until a header is clicked
      autoWidth: false,
      // no built-in search box: the filter bar above the table is used instead
      dom: "<'row mb-2'<'col-sm-6'l>>rt<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
      columnDefs: [
        { targets: [0, -1], orderable: false, searchable: false }   // checkbox + actions
      ],
      language: {
        lengthMenu: 'Show _MENU_ records',
        info: 'Showing _START_ to _END_ of _TOTAL_ records',
        infoEmpty: 'No records to show',
        infoFiltered: '(filtered from _MAX_ records)',
        zeroRecords: 'No matching records found',
        emptyTable: 'No monitoring records yet'
      }
    });
    // the "Select all" box follows the current page/filter
    monitoringDT.on('draw', function() { $('#selectAll').prop('checked', false); });
  }

  $(function() { ensureDataTables(initMonitoringDataTable); });
})();
</script>

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
  $('#monitoringTable').on('click', '.edit-record', function() {
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
    $('#edit_date_informed').val($(this).data('date_informed'));
    $('#edit_compliance_status').val($(this).data('compliance_status'));
    $('#edit_date_complied').val($(this).data('date_complied'));
    $('#edit_payroll_status').val($(this).data('payroll_status'));
    
    $('#editRecordModal').modal('show');
  });
  
  // View record modal
  $('#monitoringTable').on('click', '.view-record', function() {
    const b = $(this);
    const fmtDate = function(v) {
      if (!v) { return '<span class="text-muted">N/A</span>'; }
      const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v);
      if (!m) { return $('<div>').text(v).html(); }
      const names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return names[parseInt(m[2], 10) - 1] + ' ' + parseInt(m[3], 10) + ', ' + m[1];
    };
    const badge = function(value, cls) {
      if (!value) { return '<span class="text-muted">N/A</span>'; }
      const c = 'status-' + String(cls || value).toLowerCase().replace(/ /g, '-');
      return $('<span>').addClass('status-badge ' + c).text(value).prop('outerHTML');
    };
    const filingBadge = function(value) {
      if (!value) { return '<span class="text-muted">N/A</span>'; }
      return $('<span>').addClass('filing-badge filing-' + String(value).toLowerCase().replace(/ /g, '-')).text(value).prop('outerHTML');
    };
    const sub = [b.attr('data-id_number'), b.attr('data-appointment'), b.attr('data-position')]
      .filter(function(x) { return x; }).join('  •  ');

    $('#view_employee').text(b.attr('data-employee'));
    $('#view_sub').text(sub);
    $('#view_period').text(b.attr('data-period'));
    $('#view_updated').text(b.attr('data-updated') || 'N/A');
    $('#view_status').html(badge(b.attr('data-status')));
    $('#view_filing').html(filingBadge(b.attr('data-filing')));
    $('#view_submitted').html(fmtDate(b.attr('data-submitted')));
    $('#view_informed').html(fmtDate(b.attr('data-informed')));
    $('#view_compliance').html(badge(b.attr('data-compliance_label'), b.attr('data-compliance')));
    $('#view_complied').html(fmtDate(b.attr('data-complied')));
    $('#view_payroll').html(badge(b.attr('data-payroll')));
    const remarks = b.attr('data-remarks');
    $('#view_remarks').text(remarks ? remarks : 'N/A');
    $('#viewEditBtn').data('id', b.attr('data-id'));

    $('#viewRecordModal').modal('show');
  });

  // Edit from inside the View modal
  $('#viewEditBtn').on('click', function() {
    const id = $(this).data('id');
    $('#viewRecordModal').one('hidden.bs.modal', function() {
      $('#monitoringTable .edit-record[data-id="' + id + '"]').first().trigger('click');
    }).modal('hide');
  });

  // Select all records (every page of the current filter, not just the visible page)
  $('#selectAll').on('change', function() {
    const checked = this.checked;
    if (monitoringDT) {
      $(monitoringDT.rows({ search: 'applied' }).nodes()).find('.record-checkbox').prop('checked', checked);
    } else {
      $('.record-checkbox').prop('checked', checked);
    }
    updateBulkDeleteButton();
  });
  
  // Update bulk delete button state
  $('#monitoringTable').on('change', '.record-checkbox', function() {
    updateBulkDeleteButton();
  });
  
  // Bulk delete
  $('#bulkDeleteBtn').on('click', function() {
    if (confirm('Are you sure you want to delete the selected records?')) {
      const form = $('<form>').attr({
        method: 'POST',
        action: ''
      });
      
      checkedRecordBoxes().each(function() {
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
    window.location.href = '../public/templates/STATUS_OF_ATTACHMENTS_2026.xlsx';
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
  
  // Checked record boxes on ALL pages (DataTables keeps the other pages' rows out of the DOM)
  function checkedRecordBoxes() {
    return monitoringDT ? monitoringDT.$('.record-checkbox:checked') : $('.record-checkbox:checked');
  }

  function filterTable() {
    const period = $('#periodFilter').val();
    const status = $('#statusFilter').val();
    const filing = $('#filingFilter').val();
    const search = $('#searchFilter').val();

    if (monitoringDT) {
      // exact match, so "COMPLETE" no longer matches "COMPLETE AND LATE"
      // and "FORWARDED" no longer matches "NOT FORWARDED"
      const exact = function(v) {
        return v ? '^\\s*' + $.fn.dataTable.util.escapeRegex(v) + '\\s*$' : '';
      };
      monitoringDT.column(5).search(exact(period), true, false, true);
      monitoringDT.column(6).search(exact(status), true, false, true);
      monitoringDT.column(7).search(exact(filing), true, false, true);
      monitoringDT.search(search).draw();
      return;
    }

    // Fallback when DataTables could not be loaded (plain table filtering)
    const p = period.toLowerCase(), st = status.toLowerCase(), f = filing.toLowerCase(), q = search.toLowerCase();
    $('#monitoringTable tbody tr').each(function() {
      const rowPeriod = $(this).find('td:eq(5)').text().trim().toLowerCase();
      const rowStatus = $(this).find('td:eq(6)').text().trim().toLowerCase();
      const rowFiling = $(this).find('td:eq(7)').text().trim().toLowerCase();
      const rowText = $(this).text().toLowerCase();
      $(this).toggle((!p || rowPeriod === p) && (!st || rowStatus === st) && (!f || rowFiling === f) && (!q || rowText.includes(q)));
    });
  }
  
  function updateBulkDeleteButton() {
    const checkedCount = checkedRecordBoxes().length;
    $('#bulkDeleteBtn').prop('disabled', checkedCount === 0);
    if (checkedCount > 0) {
      $('#bulkDeleteBtn').html('<i class="fas fa-trash"></i> Delete Selected (' + checkedCount + ')');
    } else {
      $('#bulkDeleteBtn').html('<i class="fas fa-trash"></i> Delete Selected');
    }
  }
  
  function showToast(type, message) {
    // Themed SweetAlert toast (the page uses Bootstrap 4, which has no bootstrap.Toast)
    var icon = ['success', 'error', 'warning', 'info'].indexOf(type) !== -1 ? type : 'info';
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: message,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true
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
                            confirmButtonColor: '#2a9863'
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
                            confirmButtonColor: '#2a9863',
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