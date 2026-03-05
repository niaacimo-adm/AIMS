<?php
// hr_leave_monitoring.php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

$database = new Database();
$db = $database->getConnection();
$leaveFunctions = new LeaveFunctions();

// Handle filters
$year = $_GET['year'] ?? date('Y');
$status = $_GET['status'] ?? '';
$section_id = $_GET['section_id'] ?? '';
$leave_type_id = $_GET['leave_type_id'] ?? '';

// Build filter conditions
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($status)) {
    $where_conditions[] = "lr.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

if (!empty($section_id)) {
    $where_conditions[] = "e.section_id = ?";
    $params[] = $section_id;
    $param_types .= "i";
}

if (!empty($leave_type_id)) {
    $where_conditions[] = "lr.leave_type_id = ?";
    $params[] = $leave_type_id;
    $param_types .= "i";
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get all leave requests with filters
$query = "SELECT lr.*, lt.leave_name, lt.leave_code,
          e.first_name, e.last_name, e.position_id, e.section_id,
          p.position_name, s.section_name,
          sh.first_name as sh_first_name, sh.last_name as sh_last_name,
          a.first_name as approver_first_name, a.last_name as approver_last_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
          JOIN employee e ON lr.emp_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN employee sh ON lr.section_head_id = sh.emp_id
          LEFT JOIN employee a ON lr.approved_by = a.emp_id
          $where_clause
          ORDER BY lr.applied_date DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$all_leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sections for filter
$sections_query = "SELECT section_id, section_name FROM section ORDER BY section_name";
$sections_result = $db->query($sections_query);

// Get leave types for filter
$leave_types = $leaveFunctions->getLeaveTypes();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_leaves,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_leaves,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_leaves
    FROM leave_requests WHERE YEAR(applied_date) = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bind_param("i", $year);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Handle manual balance adjustment
if ($_POST && isset($_POST['adjust_balance'])) {
    $emp_id = $_POST['emp_id'];
    $leave_type_id = $_POST['leave_type_id'];
    $new_balance = $_POST['new_balance'];
    $adjustment_year = $_POST['year'];
    $remarks = $_POST['remarks'];
    
    try {
        if ($leaveFunctions->manuallyAdjustLeaveBalance($emp_id, $leave_type_id, $new_balance, $adjustment_year, $remarks)) {
            $_SESSION['success'] = "Successfully adjusted leave balance for employee.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: hr_leave_monitoring.php");
    exit;
}

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action'])) {
    $leave_ids = $_POST['leave_ids'] ?? [];
    $action = $_POST['bulk_action'];
    $bulk_remarks = $_POST['bulk_remarks'] ?? '';
    
    if (!empty($leave_ids)) {
        $success_count = 0;
        foreach ($leave_ids as $leave_id) {
            if ($leaveFunctions->adminAction($leave_id, $_SESSION['emp_id'], $action, $bulk_remarks)) {
                $success_count++;
            }
        }
        $_SESSION['success'] = "Successfully processed $success_count leave requests.";
    }
    header("Location: hr_leave_monitoring.php");
    exit;
}

// Check for session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HR Leave Monitoring - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --secondary: #6b7280;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --hover-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .modern-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
            margin-bottom: 24px;
        }

        .modern-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }

        .stats-card {
            border-radius: 12px;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
        }

        .form-control-modern {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn-modern {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .section-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #eef2ff;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }

        .bulk-actions {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 12px;
            border-left: 4px solid var(--warning);
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .table-modern thead th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 16px 12px;
            font-weight: 600;
        }

        .table-modern tbody tr {
            transition: all 0.3s ease;
        }

        .table-modern tbody tr:hover {
            background-color: #f8fafc;
            transform: scale(1.01);
        }

        .badge-modern {
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .action-buttons .btn {
            border-radius: 6px;
            margin: 2px;
            transition: all 0.3s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-2px);
        }

        .quick-action-card {
            background: dark;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .quick-action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .employee-balances {
            max-height: 400px;
            overflow-y: auto;
            background: var(--light-bg);
            border-radius: 8px;
            padding: 16px;
        }

        .modal-modern .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: var(--hover-shadow);
        }

        .modal-modern .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            border: none;
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

body.dark-mode .modern-card { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .stats-card { color: #fff !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .filter-section { background: var(--card-bg) !important; }
body.dark-mode .leave-type-label { background: var(--table-stripe) !important; color: var(--text-primary) !important; }
body.dark-mode .leave-balance-card { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .summary-row { background: var(--table-stripe) !important; color: var(--text-primary) !important; }

</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="color: var(--primary); font-weight: 700;">HR Leave Monitoring</h1>
                        <p class="text-muted mt-1">Comprehensive leave management and monitoring system</p>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--secondary);">Home</a></li>
                            <li class="breadcrumb-item active" style="color: var(--primary); font-weight: 600;">HR Leave Monitoring</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible modern-card">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible modern-card">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card modern-card">
                            <div class="card-body text-center">
                                <div class="stats-number text-primary"><?php echo $stats['total_leaves']; ?></div>
                                <p class="card-text text-muted">Total Leaves</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card modern-card">
                            <div class="card-body text-center">
                                <div class="stats-number text-warning"><?php echo $stats['pending_leaves']; ?></div>
                                <p class="card-text text-muted">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card modern-card">
                            <div class="card-body text-center">
                                <div class="stats-number text-success"><?php echo $stats['approved_leaves']; ?></div>
                                <p class="card-text text-muted">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card modern-card">
                            <div class="card-body text-center">
                                <div class="stats-number text-danger"><?php echo $stats['rejected_leaves']; ?></div>
                                <p class="card-text text-muted">Rejected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card modern-card">
                            <div class="card-body text-center">
                                <div class="stats-number text-secondary"><?php echo $stats['cancelled_leaves']; ?></div>
                                <p class="card-text text-muted">Cancelled</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card modern-card" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                            <div class="card-body text-center text-white">
                                <div class="stats-number"><?php echo date('Y'); ?></div>
                                <p class="card-text">Current Year</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="filter-section modern-card">
                            <h5 class="section-title"><i class="fas fa-filter mr-2"></i>Filter Leaves</h5>
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="year" class="form-label">Year</label>
                                    <select class="form-control form-control-modern" id="year" name="year">
                                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control form-control-modern" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="section_id" class="form-label">Section</label>
                                    <select class="form-control form-control-modern" id="section_id" name="section_id">
                                        <option value="">All Sections</option>
                                        <?php while ($section = $sections_result->fetch_assoc()): ?>
                                            <option value="<?php echo $section['section_id']; ?>" 
                                                <?php echo $section_id == $section['section_id'] ? 'selected' : ''; ?>>
                                                <?php echo $section['section_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="leave_type_id" class="form-label">Leave Type</label>
                                    <select class="form-control form-control-modern" id="leave_type_id" name="leave_type_id">
                                        <option value="">All Types</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <option value="<?php echo $type['leave_type_id']; ?>" 
                                                <?php echo $leave_type_id == $type['leave_type_id'] ? 'selected' : ''; ?>>
                                                <?php echo $type['leave_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn btn-modern mr-2">
                                        <i class="fas fa-search mr-2"></i> Apply Filters
                                    </button>
                                    <a href="hr_leave_monitoring.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo mr-2"></i> Reset Filters
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bulk-actions modern-card">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="fas fa-cogs mr-2"></i>Bulk Actions</h6>
                                <form method="POST" id="bulkForm">
                                    <div class="row align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Action</label>
                                            <select class="form-control form-control-modern" name="bulk_action" required>
                                                <option value="">Select Action</option>
                                                <option value="approve">Approve Selected</option>
                                                <option value="reject">Reject Selected</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Remarks</label>
                                            <input type="text" class="form-control form-control-modern" name="bulk_remarks" 
                                                   placeholder="Enter remarks for bulk action (optional)">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-warning btn-modern btn-block">
                                                <i class="fas fa-cogs mr-2"></i> Process Selected
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leaves Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card modern-card">
                            <div class="card-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: 12px 12px 0 0;">
                                <h3 class="card-title text-white mb-0">
                                    <i class="fas fa-list-alt mr-2"></i>All Employee Leaves
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover table-modern" id="leavesTable">
                                        <thead>
                                            <tr>
                                                <th width="30">
                                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                                </th>
                                                <th>Employee</th>
                                                <th>Section</th>
                                                <th>Leave Type</th>
                                                <th>Period</th>
                                                <th>Days</th>
                                                <th>Status</th>
                                                <th>Applied Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($all_leaves)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">No leave requests found</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($all_leaves as $leave): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($leave['status'] == 'pending'): ?>
                                                                <input type="checkbox" name="leave_ids[]" value="<?php echo $leave['leave_id']; ?>" class="form-check-input leave-checkbox">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong class="d-block"><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></strong>
                                                            <small class="text-muted"><?php echo $leave['position_name'] ?? 'N/A'; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-light border"><?php echo $leave['section_name'] ?? 'N/A'; ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info badge-modern"><?php echo $leave['leave_code']; ?></span>
                                                            <small class="d-block text-muted"><?php echo $leave['leave_name']; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="text-sm">
                                                                <div><?php echo date('M j, Y', strtotime($leave['start_date'])); ?></div>
                                                                <div class="text-muted">to</div>
                                                                <div><?php echo date('M j, Y', strtotime($leave['end_date'])); ?></div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary badge-modern"><?php echo $leave['total_days']; ?> days</span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $badge_class = [
                                                                'pending' => 'warning',
                                                                'approved' => 'success',
                                                                'rejected' => 'danger',
                                                                'cancelled' => 'secondary'
                                                            ];
                                                            ?>
                                                            <span class="status-badge badge-<?php echo $badge_class[$leave['status']]; ?>">
                                                                <?php echo ucfirst($leave['status']); ?>
                                                            </span>
                                                            <?php if ($leave['status'] == 'pending'): ?>
                                                                <br>
                                                                <small class="text-muted">Waiting approval</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="text-sm">
                                                                <div><?php echo date('M j, Y', strtotime($leave['applied_date'])); ?></div>
                                                                <small class="text-muted">
                                                                    <?php echo time_elapsed_string($leave['applied_date']); ?>
                                                                </small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button type="button" class="btn btn-info btn-sm view-details" 
                                                                        data-toggle="modal" data-target="#detailsModal"
                                                                        data-leave='<?php echo json_encode($leave); ?>'
                                                                        title="View Details">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <?php if ($leave['status'] == 'pending'): ?>
                                                                    <button type="button" class="btn btn-success btn-sm" 
                                                                            data-toggle="modal" data-target="#approveModal" 
                                                                            data-leaveid="<?php echo $leave['leave_id']; ?>"
                                                                            data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>"
                                                                            title="Approve">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                                            data-toggle="modal" data-target="#rejectModal" 
                                                                            data-leaveid="<?php echo $leave['leave_id']; ?>"
                                                                            data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>"
                                                                            title="Reject">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-warning btn-sm adjust-balance"
                                                                        data-toggle="modal" data-target="#adjustBalanceModal"
                                                                        data-empid="<?php echo $leave['emp_id']; ?>"
                                                                        data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>"
                                                                        title="Adjust Balance">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Balance Management -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h5>Manage All Balances</h5>
                            <p class="text-muted mb-3">Update, reset, or adjust balances for all employees</p>
                            <a href="leave_balance_management.php" class="btn btn-modern">
                                <i class="fas fa-cogs mr-2"></i> Manage Balances
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-redo"></i>
                            </div>
                            <h5>Annual Balance Reset</h5>
                            <p class="text-muted mb-3">Reset all balances for the new year</p>
                            <a href="leave_balance_management.php?action=annual_reset" class="btn btn-warning btn-modern">
                                <i class="fas fa-redo mr-2"></i> Reset Balances
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Modals -->
<div class="modal fade modal-modern" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Leave Request Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Details will be loaded here via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade modal-modern" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Leave Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Approve leave request for <strong id="approveEmployeeName"></strong>?</p>
                    <input type="hidden" name="leave_id" id="approveLeaveId">
                    <input type="hidden" name="action" value="approve">
                    <div class="form-group">
                        <label for="approveRemarks">Remarks (Optional):</label>
                        <textarea class="form-control form-control-modern" id="approveRemarks" name="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade modal-modern" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Leave Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Reject leave request for <strong id="rejectEmployeeName"></strong>?</p>
                    <input type="hidden" name="leave_id" id="rejectLeaveId">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label for="rejectRemarks">Reason for Rejection:</label>
                        <textarea class="form-control form-control-modern" id="rejectRemarks" name="remarks" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Balance Modal -->
<div class="modal fade modal-modern" id="adjustBalanceModal" tabindex="-1" role="dialog" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustBalanceModalLabel">Adjust Leave Balance</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Adjusting balance for: <strong id="adjustEmployeeName"></strong></p>
                    
                    <!-- Current Balances -->
                    <div class="mb-3">
                        <h6>Current Balances</h6>
                        <div id="currentBalances" class="employee-balances">
                            Loading current balances...
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="adjustLeaveType">Leave Type</label>
                                <select class="form-control form-control-modern" id="adjustLeaveType" name="leave_type_id" required>
                                    <option value="">Select Leave Type</option>
                                    <?php foreach ($leave_types as $type): ?>
                                        <option value="<?php echo $type['leave_type_id']; ?>">
                                            <?php echo $type['leave_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="adjustYear">Year</label>
                                <input type="number" class="form-control form-control-modern" id="adjustYear" name="year" 
                                       value="<?php echo date('Y'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="newBalance">New Balance</label>
                                <input type="number" class="form-control form-control-modern" id="newBalance" name="new_balance" 
                                       step="0.5" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="adjustRemarks">Remarks</label>
                                <input type="text" class="form-control form-control-modern" id="adjustRemarks" name="remarks">
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="emp_id" id="adjustEmpId">
                    <input type="hidden" name="adjust_balance" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Adjust Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal handlers
$('#approveModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var leaveId = button.data('leaveid');
    var employeeName = button.data('employee');
    
    var modal = $(this);
    modal.find('#approveEmployeeName').text(employeeName);
    modal.find('#approveLeaveId').val(leaveId);
});

$('#rejectModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var leaveId = button.data('leaveid');
    var employeeName = button.data('employee');
    
    var modal = $(this);
    modal.find('#rejectEmployeeName').text(employeeName);
    modal.find('#rejectLeaveId').val(leaveId);
});
</script>
<?php include '../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#leavesTable').DataTable({
        "pageLength": 25,
        "order": [[7, 'desc']],
        "language": {
            "search": "Search leaves:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });

    // Select All functionality
    $('#selectAll').click(function() {
        $('.leave-checkbox').prop('checked', this.checked);
    });

    // View details modal
    $('.view-details').click(function() {
        var leaveData = $(this).data('leave');
        var detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Employee Information</h6>
                    <p><strong>Name:</strong> ${leaveData.first_name} ${leaveData.last_name}</p>
                    <p><strong>Position:</strong> ${leaveData.position_name || 'N/A'}</p>
                    <p><strong>Section:</strong> ${leaveData.section_name || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Leave Information</h6>
                    <p><strong>Type:</strong> ${leaveData.leave_name} (${leaveData.leave_code})</p>
                    <p><strong>Period:</strong> ${new Date(leaveData.start_date).toLocaleDateString()} to ${new Date(leaveData.end_date).toLocaleDateString()}</p>
                    <p><strong>Duration:</strong> ${leaveData.total_days} working days</p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Particulars</h6>
                    <div class="border p-3 bg-light rounded">
                        ${leaveData.particulars ? leaveData.particulars.replace(/\n/g, '<br>') : 'No particulars provided'}
                    </div>
                </div>
            </div>
            ${leaveData.remarks ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Employee Remarks</h6>
                    <div class="border p-3 bg-light rounded">
                        ${leaveData.remarks.replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
            ` : ''}
            ${leaveData.medical_certificate ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Medical Certificate</h6>
                    <a href="../uploads/medical_certificates/${leaveData.medical_certificate}" target="_blank" class="btn btn-info btn-sm">
                        <i class="fas fa-file-medical"></i> View Medical Certificate
                    </a>
                </div>
            </div>
            ` : ''}
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Approval Information</h6>
                    <p><strong>Status:</strong> <span class="badge badge-${getStatusBadgeClass(leaveData.status)}">${leaveData.status.charAt(0).toUpperCase() + leaveData.status.slice(1)}</span></p>
                    <p><strong>Applied Date:</strong> ${new Date(leaveData.applied_date).toLocaleDateString()}</p>
                    ${leaveData.approver_first_name ? `
                    <p><strong>Approved By:</strong> ${leaveData.approver_first_name} ${leaveData.approver_last_name}</p>
                    ` : ''}
                </div>
            </div>
        `;
        $('#detailsModalBody').html(detailsHtml);
    });

    function getStatusBadgeClass(status) {
        const classes = {
            'pending': 'warning',
            'approved': 'success',
            'rejected': 'danger',
            'cancelled': 'secondary'
        };
        return classes[status] || 'secondary';
    }

    // Adjust balance modal
    $('.adjust-balance').click(function() {
        var empId = $(this).data('empid');
        var employeeName = $(this).data('employee');
        $('#adjustEmployeeName').text(employeeName);
        $('#adjustEmpId').val(empId);
        
        // Load employee's current balances
        $.ajax({
            url: 'get_employee_balances.php',
            type: 'POST',
            data: { emp_id: empId },
            success: function(response) {
                $('#currentBalances').html(response);
            }
        });
    });
});
</script>
</body>
</html>