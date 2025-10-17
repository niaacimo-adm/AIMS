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
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .bulk-actions {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .employee-balances {
            max-height: 400px;
            overflow-y: auto;
        }
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
                        <h1 class="m-0">HR Leave Monitoring</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">HR Leave Monitoring</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-check"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?php echo $stats['total_leaves']; ?></h3>
                                <p class="card-text">Total Leaves</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-warning"><?php echo $stats['pending_leaves']; ?></h3>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-success"><?php echo $stats['approved_leaves']; ?></h3>
                                <p class="card-text">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-danger"><?php echo $stats['rejected_leaves']; ?></h3>
                                <p class="card-text">Rejected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="text-secondary"><?php echo $stats['cancelled_leaves']; ?></h3>
                                <p class="card-text">Cancelled</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-primary">
                            <div class="card-body text-center text-white">
                                <h3><?php echo date('Y'); ?></h3>
                                <p class="card-text">Current Year</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="filter-section">
                            <h5><i class="fas fa-filter"></i> Filter Leaves</h5>
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label for="year" class="mr-2">Year:</label>
                                    <select class="form-control" id="year" name="year">
                                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label for="status" class="mr-2">Status:</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label for="section_id" class="mr-2">Section:</label>
                                    <select class="form-control" id="section_id" name="section_id">
                                        <option value="">All Sections</option>
                                        <?php while ($section = $sections_result->fetch_assoc()): ?>
                                            <option value="<?php echo $section['section_id']; ?>" 
                                                <?php echo $section_id == $section['section_id'] ? 'selected' : ''; ?>>
                                                <?php echo $section['section_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label for="leave_type_id" class="mr-2">Leave Type:</label>
                                    <select class="form-control" id="leave_type_id" name="leave_type_id">
                                        <option value="">All Types</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <option value="<?php echo $type['leave_type_id']; ?>" 
                                                <?php echo $leave_type_id == $type['leave_type_id'] ? 'selected' : ''; ?>>
                                                <?php echo $type['leave_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="hr_leave_monitoring.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card bulk-actions">
                            <div class="card-body">
                                <form method="POST" id="bulkForm">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <select class="form-control" name="bulk_action" required>
                                                <option value="">Bulk Action</option>
                                                <option value="approve">Approve Selected</option>
                                                <option value="reject">Reject Selected</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="bulk_remarks" 
                                                   placeholder="Remarks for bulk action (optional)">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-warning btn-block">
                                                <i class="fas fa-cogs"></i> Process Selected
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
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">All Employee Leaves</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="leavesTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="30">
                                                    <input type="checkbox" id="selectAll">
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
                                                    <td colspan="9" class="text-center">No leave requests found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($all_leaves as $leave): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($leave['status'] == 'pending'): ?>
                                                                <input type="checkbox" name="leave_ids[]" value="<?php echo $leave['leave_id']; ?>" class="leave-checkbox">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $leave['position_name'] ?? 'N/A'; ?></small>
                                                        </td>
                                                        <td><?php echo $leave['section_name'] ?? 'N/A'; ?></td>
                                                        <td>
                                                            <span class="badge badge-info"><?php echo $leave['leave_code']; ?></span>
                                                            <br>
                                                            <small><?php echo $leave['leave_name']; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($leave['start_date'])); ?>
                                                            <br>
                                                            <small>to</small>
                                                            <br>
                                                            <?php echo date('M j, Y', strtotime($leave['end_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary"><?php echo $leave['total_days']; ?> days</span>
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
                                                            <span class="badge badge-<?php echo $badge_class[$leave['status']]; ?>">
                                                                <?php echo ucfirst($leave['status']); ?>
                                                            </span>
                                                            <?php if ($leave['status'] == 'pending'): ?>
                                                                <br>
                                                                <small class="text-muted">Waiting approval</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($leave['applied_date'])); ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo time_elapsed_string($leave['applied_date']); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-info btn-sm view-details" 
                                                                        data-toggle="modal" data-target="#detailsModal"
                                                                        data-leave='<?php echo json_encode($leave); ?>'>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <?php if ($leave['status'] == 'pending'): ?>
                                                                    <button type="button" class="btn btn-success btn-sm" 
                                                                            data-toggle="modal" data-target="#approveModal" 
                                                                            data-leaveid="<?php echo $leave['leave_id']; ?>"
                                                                            data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                                            data-toggle="modal" data-target="#rejectModal" 
                                                                            data-leaveid="<?php echo $leave['leave_id']; ?>"
                                                                            data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-warning btn-sm adjust-balance"
                                                                        data-toggle="modal" data-target="#adjustBalanceModal"
                                                                        data-empid="<?php echo $leave['emp_id']; ?>"
                                                                        data-employee="<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>">
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
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Quick Balance Management</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="leave_balance_management.php" class="btn btn-primary btn-lg btn-block mb-3">
                                            <i class="fas fa-cogs"></i> Manage All Balances
                                        </a>
                                        <p class="text-muted">Update, reset, or adjust balances for all employees</p>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="leave_balance_management.php?action=annual_reset" class="btn btn-warning btn-lg btn-block mb-3">
                                            <i class="fas fa-redo"></i> Annual Balance Reset
                                        </a>
                                        <p class="text-muted">Reset all balances for the new year</p>
                                    </div>
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

<!-- Modals -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Leave Request Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Leave Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                        <textarea class="form-control" id="approveRemarks" name="remarks" rows="3"></textarea>
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
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Leave Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                        <textarea class="form-control" id="rejectRemarks" name="remarks" rows="3" required></textarea>
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
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" role="dialog" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustBalanceModalLabel">Adjust Leave Balance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                                <select class="form-control" id="adjustLeaveType" name="leave_type_id" required>
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
                                <input type="number" class="form-control" id="adjustYear" name="year" 
                                       value="<?php echo date('Y'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="newBalance">New Balance</label>
                                <input type="number" class="form-control" id="newBalance" name="new_balance" 
                                       step="0.5" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="adjustRemarks">Remarks</label>
                                <input type="text" class="form-control" id="adjustRemarks" name="remarks">
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
        "order": [[7, 'desc']]
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
                    <div class="border p-3 bg-light">
                        ${leaveData.particulars ? leaveData.particulars.replace(/\n/g, '<br>') : 'No particulars provided'}
                    </div>
                </div>
            </div>
            ${leaveData.remarks ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Employee Remarks</h6>
                    <div class="border p-3 bg-light">
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