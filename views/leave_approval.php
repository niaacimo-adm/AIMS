<?php
// Start session first
session_start();
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user role from database based on user_roles table
$user_role = '';
$role_name = '';

if (isset($_SESSION['user_id'])) {
    $role_query = "SELECT ur.name as role_name 
                  FROM users u 
                  JOIN user_roles ur ON u.role_id = ur.id 
                  WHERE u.id = ?";
    $stmt = $db->prepare($role_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $role_result = $stmt->get_result();
    
    if ($role_result->num_rows > 0) {
        $role_data = $role_result->fetch_assoc();
        $role_name = $role_data['role_name'];
        
        // Map database role names to application role names
        $role_mapping = [
            'Administrator' => 'admin',
            'Manager' => 'manager',
            'Unit Head' => 'section_head',
            'Heads' => 'section_head',
            'Focal Person' => 'employee',
            'Employee' => 'employee'
        ];
        
        $user_role = $role_mapping[$role_name] ?? '';
        
        // Store in session for future use
        $_SESSION['role'] = $user_role;
        $_SESSION['role_name'] = $role_name;
    }
}

// DEBUG: Check session and role data
error_log("Session emp_id: " . ($_SESSION['emp_id'] ?? 'NOT SET'));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Database role name: " . $role_name);
error_log("Mapped user role: " . $user_role);

// Initialize LeaveFunctions
$leaveFunctions = new LeaveFunctions();

// Get pending approvals based on user role
$pending_approvals = [];
if ($user_role === 'section_head') {
    $pending_approvals = $leaveFunctions->getPendingSectionHeadApprovals($_SESSION['emp_id']);
} elseif ($user_role === 'manager') {
    $pending_approvals = $leaveFunctions->getPendingManagerApprovals($_SESSION['emp_id']);
} elseif ($user_role === 'admin') {
    $pending_approvals = $leaveFunctions->getPendingAdminApprovals();
}

// Get statistics based on user role
$stats = ['total_pending' => 0, 'total_approved' => 0, 'total_rejected' => 0];

if ($user_role === 'section_head') {
    $stats_query = "SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN lr.section_head_approved = 1 THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as total_rejected
        FROM leave_requests lr
        WHERE lr.section_head_id = ?";
    $stmt = $db->prepare($stats_query);
    $stmt->bind_param("i", $_SESSION['emp_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} elseif ($user_role === 'manager') {
    $stats_query = "SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN lr.manager_approved = 1 THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as total_rejected
        FROM leave_requests lr
        WHERE lr.manager_id = ?";
    $stmt = $db->prepare($stats_query);
    $stmt->bind_param("i", $_SESSION['emp_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} elseif ($user_role === 'admin') {
    $stats_query = "SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as total_rejected
        FROM leave_requests lr
        WHERE lr.status IN ('pending', 'approved', 'rejected')";
    $stmt = $db->prepare($stats_query);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
}

// In leave_approval.php - Handle approval/rejection
if ($_POST && isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];
    $remarks = $_POST['remarks'] ?? '';
    
    // Check if leave request is still pending
    $check_query = "SELECT status FROM leave_requests WHERE leave_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("i", $leave_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $leave_status = $check_result->fetch_assoc();
    
    if ($leave_status['status'] !== 'pending') {
        $_SESSION['error'] = "This leave request has already been processed.";
        header("Location: leave_approval.php");
        exit;
    }
    
    if ($user_role === 'section_head') {
        // Section head approval
        $result = $leaveFunctions->sectionHeadAction($leave_id, $_SESSION['emp_id'], $action, $remarks);
    } elseif ($user_role === 'admin') {
        // Admin approval
        $result = $leaveFunctions->adminAction($leave_id, $_SESSION['emp_id'], $action, $remarks);
    } else {
        $_SESSION['error'] = "You do not have permission to approve/reject leave requests.";
        header("Location: leave_approval.php");
        exit;
    }
    
    if ($result) {
        // Send notification to employee
        sendNotificationToEmployee($leave_id, $action, $remarks);
        
        $_SESSION['success'] = "Leave request " . $action . "d successfully!";
    } else {
        $_SESSION['error'] = "Failed to process request. It may have been processed by someone else.";
    }
    header("Location: leave_approval.php");
    exit;
}

// Function to send notification to employee
function sendNotificationToEmployee($leave_id, $action, $remarks) {
    global $db, $user_role;
    
    $query = "SELECT lr.emp_id, e.first_name, e.last_name, e.email, 
                     lt.leave_name, lr.start_date, lr.end_date
              FROM leave_requests lr
              JOIN employee e ON lr.emp_id = e.emp_id
              JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
              WHERE lr.leave_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $leave_data = $result->fetch_assoc();
    
    $action_text = ($action == 'approve') ? 'approved' : 'rejected';
    
    // Role-specific notification messages
    $approver = '';
    if ($user_role === 'section_head') {
        $approver = 'section head';
    } elseif ($user_role === 'manager') {
        $approver = 'manager';
    } elseif ($user_role === 'admin') {
        $approver = 'administrator';
    }
    
    $message = "Your {$leave_data['leave_name']} request for " . 
               date('M j, Y', strtotime($leave_data['start_date'])) . " to " . 
               date('M j, Y', strtotime($leave_data['end_date'])) . 
               " has been $action_text by your $approver.";
    
    if (!empty($remarks)) {
        $message .= " Remarks: $remarks";
    }
    
    // Insert notification
    $notification_query = "INSERT INTO admin_notifications 
                          (admin_emp_id, message, link, created_at) 
                          VALUES (?, ?, ?, NOW())";
    
    $link = "leave_request.php";
    $stmt = $db->prepare($notification_query);
    $stmt->bind_param("iss", $leave_data['emp_id'], $message, $link);
    $stmt->execute();
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
    <title>Leave Approval - Section Head - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/leaveapproval.css">
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
                        <h1 class="m-0">Leave Approval</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Leave Approval</li>
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

                <!-- Section Information -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="employee-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4><i class="fas fa-user-tie"></i> 
                                        <?php 
                                        if ($user_role === 'section_head') {
                                            echo 'Section Head Dashboard - Leave Approval';
                                        } elseif ($user_role === 'manager') {
                                            echo 'Manager Dashboard - Leave Monitoring';
                                        } elseif ($user_role === 'admin') {
                                            echo 'Administrator Dashboard - Leave Monitoring';
                                        } else {
                                            echo 'Leave Approval Dashboard';
                                        }
                                        ?>
                                    </h4>
                                    <?php if ($user_role === 'section_head' && !empty($section_data)): ?>
                                        <p class="mb-1"><strong>Section:</strong> <?php echo $section_data['section_name']; ?> (<?php echo $section_data['section_code']; ?>)</p>
                                    <?php endif; ?>
                                    <p class="mb-0"><strong>Employee ID:</strong> <?php echo $_SESSION['emp_id']; ?></p>
                                    <p class="mb-0"><strong>Role:</strong> <?php echo !empty($user_role) ? ucfirst(str_replace('_', ' ', $user_role)) : 'Not Set'; ?></p>
                                    <?php if ($user_role === 'manager' || $user_role === 'admin'): ?>
                                        <p class="mb-0"><strong>Permission:</strong> 
                                            <?php echo ($user_role === 'admin' || $user_role === 'section_head') ? 'Approval Authority' : 'View and Monitor Only'; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-right">
                                    <h5>Leave Statistics</h5>
                                    <div class="btn-group">
                                        <span class="btn btn-warning btn-sm">Pending: <?php echo $stats['total_pending']; ?></span>
                                        <span class="btn btn-success btn-sm">Approved: <?php echo $stats['total_approved']; ?></span>
                                        <span class="btn btn-danger btn-sm">Rejected: <?php echo $stats['total_rejected']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($user_role)): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Role Not Assigned</h5>
                    <p>Your user account does not have a role assigned. Please contact administrator to assign you a role (Section Head, Manager, or Admin).</p>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card stats-card pending">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="text-warning"><?php echo $stats['total_pending']; ?></h3>
                                        <p class="card-text">Pending Approvals</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card approved">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="text-success"><?php echo $stats['total_approved']; ?></h3>
                                        <p class="card-text">Approved Leaves</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card rejected">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="text-danger"><?php echo $stats['total_rejected']; ?></h3>
                                        <p class="card-text">Rejected Leaves</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals Table -->
                <?php if (!empty($user_role)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php 
                                    if ($user_role === 'section_head') {
                                        echo 'Pending Leave Approvals';
                                    } elseif ($user_role === 'manager' || $user_role === 'admin') {
                                        echo 'Leave Requests for Monitoring';
                                    }
                                    ?>
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_approvals)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h4>No Pending Approvals</h4>
                                        <p class="text-muted">All leave requests have been processed.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Position</th>
                                                    <th>Leave Type</th>
                                                    <th>Period</th>
                                                    <th>Days</th>
                                                    <th>Particulars</th>
                                                    <th>Applied Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_approvals as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?php echo $request['emp_id']; ?></small>
                                                        </td>
                                                        <td><?php echo $request['position_name'] ?? 'N/A'; ?></td>
                                                        <td>
                                                            <span class="badge badge-info"><?php echo $request['leave_code']; ?></span>
                                                            <br>
                                                            <small><?php echo $request['leave_name']; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($request['start_date'])); ?>
                                                            <br>
                                                            <small>to</small>
                                                            <br>
                                                            <?php echo date('M j, Y', strtotime($request['end_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary"><?php echo $request['total_days']; ?> days</span>
                                                        </td>
                                                        <td>
                                                            <small><?php echo nl2br(htmlspecialchars($request['particulars'])); ?></small>
                                                            <?php if (!empty($request['remarks'])): ?>
                                                                <br>
                                                                <small class="text-muted"><strong>Remarks:</strong> <?php echo htmlspecialchars($request['remarks']); ?></small>
                                                            <?php endif; ?>
                                                            <?php if (!empty($request['medical_certificate'])): ?>
                                                                <br>
                                                                <small>
                                                                    <a href="../uploads/medical_certificates/<?php echo $request['medical_certificate']; ?>" 
                                                                       target="_blank" class="text-info">
                                                                        <i class="fas fa-file-medical"></i> View Medical Certificate
                                                                    </a>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($request['applied_date'])); ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo time_elapsed_string($request['applied_date']); ?>
                                                            </small>
                                                        </td>
                                                        <td class="action-buttons">
                                                            <?php if (in_array($user_role, ['section_head', 'manager', 'admin'])): ?>
                                                                <!-- All approver roles can see approve/reject buttons -->
                                                                <button type="button" class="btn btn-success btn-sm" 
                                                                        data-toggle="modal" data-target="#approveModal" 
                                                                        data-leaveid="<?php echo $request['leave_id']; ?>"
                                                                        data-employee="<?php echo $request['first_name'] . ' ' . $request['last_name']; ?>"
                                                                        data-leavetype="<?php echo $request['leave_name']; ?>">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                                <button type="button" class="btn btn-danger btn-sm" 
                                                                        data-toggle="modal" data-target="#rejectModal" 
                                                                        data-leaveid="<?php echo $request['leave_id']; ?>"
                                                                        data-employee="<?php echo $request['first_name'] . ' ' . $request['last_name']; ?>"
                                                                        data-leavetype="<?php echo $request['leave_name']; ?>">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <!-- All roles can view details -->
                                                            <button type="button" class="btn btn-info btn-sm view-details" 
                                                                    data-toggle="modal" data-target="#detailsModal"
                                                                    data-request='<?php echo json_encode($request); ?>'>
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Actions -->
                <?php if (!empty($user_role)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Actions</h3>
                            </div>
                            <div class="card-body">
                                <?php
                                // Build query based on user role
                                $recent_actions = false;
                                
                                if ($user_role === 'section_head') {
                                    $recent_actions_query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                                                            e.first_name, e.last_name,
                                                            CASE 
                                                                WHEN lr.status = 'approved' THEN 'approved'
                                                                WHEN lr.status = 'rejected' THEN 'rejected'
                                                                ELSE 'pending'
                                                            END as action_status,
                                                            lr.section_head_date as action_date,
                                                            lr.section_head_remarks as remarks
                                                            FROM leave_requests lr
                                                            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                                                            JOIN employee e ON lr.emp_id = e.emp_id
                                                            WHERE lr.section_head_id = ?
                                                            AND lr.status IN ('approved', 'rejected')
                                                            ORDER BY lr.section_head_date DESC, lr.applied_date DESC
                                                            LIMIT 10";
                                    
                                    $stmt = $db->prepare($recent_actions_query);
                                    $stmt->bind_param("i", $_SESSION['emp_id']);
                                    $stmt->execute();
                                    $recent_actions = $stmt->get_result();
                                    
                                } elseif ($user_role === 'admin') {
                                    // FIXED: Admin query to show all approved/rejected leaves (not just those they approved)
                                    $recent_actions_query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                                                            e.first_name, e.last_name,
                                                            CASE 
                                                                WHEN lr.status = 'approved' THEN 'approved'
                                                                WHEN lr.status = 'rejected' THEN 'rejected'
                                                                ELSE 'pending'
                                                            END as action_status,
                                                            lr.applied_date as action_date,
                                                            lr.admin_remarks as remarks,
                                                            a.first_name as approver_first_name, 
                                                            a.last_name as approver_last_name
                                                            FROM leave_requests lr
                                                            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                                                            JOIN employee e ON lr.emp_id = e.emp_id
                                                            LEFT JOIN employee a ON lr.approved_by = a.emp_id
                                                            WHERE lr.status IN ('approved', 'rejected')
                                                            ORDER BY lr.applied_date DESC
                                                            LIMIT 10";
                                    
                                    $stmt = $db->prepare($recent_actions_query);
                                    $stmt->execute();
                                    $recent_actions = $stmt->get_result();
                                    
                                } elseif ($user_role === 'manager') {
                                    // Add manager query if needed
                                    $recent_actions_query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                                                            e.first_name, e.last_name,
                                                            CASE 
                                                                WHEN lr.status = 'approved' THEN 'approved'
                                                                WHEN lr.status = 'rejected' THEN 'rejected'
                                                                ELSE 'pending'
                                                            END as action_status,
                                                            lr.applied_date as action_date,
                                                            lr.manager_remarks as remarks
                                                            FROM leave_requests lr
                                                            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                                                            JOIN employee e ON lr.emp_id = e.emp_id
                                                            WHERE lr.manager_id = ?
                                                            AND lr.status IN ('approved', 'rejected')
                                                            ORDER BY lr.applied_date DESC
                                                            LIMIT 10";
                                    
                                    $stmt = $db->prepare($recent_actions_query);
                                    $stmt->bind_param("i", $_SESSION['emp_id']);
                                    $stmt->execute();
                                    $recent_actions = $stmt->get_result();
                                }
                                ?>
                                
                                <div class="timeline">
                                    <?php if (!$recent_actions || $recent_actions->num_rows === 0): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-history fa-2x mb-2"></i>
                                            <p>No recent actions</p>
                                        </div>
                                    <?php else: ?>
                                        <?php while ($action = $recent_actions->fetch_assoc()): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-point <?php echo $action['action_status'] == 'approved' ? 'timeline-point-success' : 'timeline-point-danger'; ?>">
                                                    <i class="fas fa-<?php echo $action['action_status'] == 'approved' ? 'check' : 'times'; ?>"></i>
                                                </div>
                                                <div class="timeline-event">
                                                    <div class="timeline-header">
                                                        <strong><?php echo $action['first_name'] . ' ' . $action['last_name']; ?></strong>
                                                        <?php if ($user_role === 'admin' && !empty($action['approver_first_name'])): ?>
                                                            <small class="text-muted">
                                                                by <?php echo $action['approver_first_name'] . ' ' . $action['approver_last_name']; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <span class="badge badge-<?php echo $action['action_status'] == 'approved' ? 'success' : 'danger'; ?> float-right">
                                                            <?php echo ucfirst($action['action_status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-body">
                                                        <p class="mb-1">
                                                            <strong><?php echo $action['leave_name']; ?></strong> - 
                                                            <?php echo $action['total_days']; ?> days
                                                        </p>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y', strtotime($action['start_date'])); ?> to 
                                                            <?php echo date('M j, Y', strtotime($action['end_date'])); ?>
                                                        </small>
                                                        <?php 
                                                        // Show appropriate remarks based on role
                                                        $remarks_field = '';
                                                        if ($user_role === 'section_head') {
                                                            $remarks_field = $action['section_head_remarks'] ?? '';
                                                        } elseif ($user_role === 'manager') {
                                                            $remarks_field = $action['manager_remarks'] ?? '';
                                                        } elseif ($user_role === 'admin') {
                                                            $remarks_field = $action['admin_remarks'] ?? '';
                                                        }
                                                        
                                                        if (!empty($remarks_field)): ?>
                                                            <p class="mt-1 mb-0"><small><strong>Remarks:</strong> <?php echo htmlspecialchars($remarks_field); ?></small></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- In the recent actions timeline, add print option -->
<?php if ($action['action_status'] == 'approved'): ?>
    <div style="margin-top: 5px;">
        <a href="print_leave_form.php?leave_id=<?php echo $action['leave_id']; ?>" 
           class="btn btn-xs btn-outline-success" target="_blank">
            <i class="fas fa-print"></i> Print Form
        </a>
    </div>
<?php endif; ?>
                                                    <div class="timeline-footer">
                                                        <small class="text-muted">
                                                            <i class="far fa-clock"></i> 
                                                            <?php echo $action['action_date'] ? time_elapsed_string($action['action_date']) : 'Pending'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php if (in_array($user_role, ['section_head', 'manager', 'admin'])): ?>
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
                    <p>Are you sure you want to approve the leave request for <strong id="approveEmployeeName"></strong>?</p>
                    <p><strong>Leave Type:</strong> <span id="approveLeaveType"></span></p>
                    <input type="hidden" name="leave_id" id="approveLeaveId">
                    <input type="hidden" name="action" value="approve">
                    <div class="form-group">
                        <label for="approveRemarks">Remarks (Optional):</label>
                        <textarea class="form-control" id="approveRemarks" name="remarks" rows="3" placeholder="Add any remarks or comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Leave</button>
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
                    <p>Are you sure you want to reject the leave request for <strong id="rejectEmployeeName"></strong>?</p>
                    <p><strong>Leave Type:</strong> <span id="rejectLeaveType"></span></p>
                    <input type="hidden" name="leave_id" id="rejectLeaveId">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label for="rejectRemarks">Reason for Rejection (Required):</label>
                        <textarea class="form-control" id="rejectRemarks" name="remarks" rows="3" placeholder="Please provide the reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Details Modal (Available for all roles) -->
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

<?php include '../includes/footer.php'; ?>
<script>
// Wait for jQuery to be loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded!');
        return;
    }
    
    $(document).ready(function() {
        <?php if (in_array($user_role, ['section_head', 'manager', 'admin'])): ?>
        // Approve Modal
        $('#approveModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var leaveId = button.data('leaveid');
            var employeeName = button.data('employee');
            var leaveType = button.data('leavetype');
            
            var modal = $(this);
            modal.find('#approveEmployeeName').text(employeeName);
            modal.find('#approveLeaveType').text(leaveType);
            modal.find('#approveLeaveId').val(leaveId);
        });

        // Reject Modal
        $('#rejectModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var leaveId = button.data('leaveid');
            var employeeName = button.data('employee');
            var leaveType = button.data('leavetype');
            
            var modal = $(this);
            modal.find('#rejectEmployeeName').text(employeeName);
            modal.find('#rejectLeaveType').text(leaveType);
            modal.find('#rejectLeaveId').val(leaveId);
        });
        <?php endif; ?>

        // Details Modal
        $('.view-details').on('click', function() {
            var requestData = $(this).data('request');
            var detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Employee Information</h6>
                        <p><strong>Name:</strong> ${requestData.first_name} ${requestData.last_name}</p>
                        <p><strong>Position:</strong> ${requestData.position_name || 'N/A'}</p>
                        <p><strong>Employee ID:</strong> ${requestData.emp_id}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Leave Information</h6>
                        <p><strong>Type:</strong> ${requestData.leave_name} (${requestData.leave_code})</p>
                        <p><strong>Period:</strong> ${new Date(requestData.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} to ${new Date(requestData.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                        <p><strong>Duration:</strong> ${requestData.total_days} working days</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Particulars</h6>
                        <div class="border p-3 bg-light">
                            ${requestData.particulars ? requestData.particulars.replace(/\n/g, '<br>') : 'No particulars provided'}
                        </div>
                    </div>
                </div>
                ${requestData.remarks ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Employee Remarks</h6>
                        <div class="border p-3 bg-light">
                            ${requestData.remarks.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
                ` : ''}
                ${requestData.medical_certificate ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Medical Certificate</h6>
                        <a href="../uploads/medical_certificates/${requestData.medical_certificate}" target="_blank" class="btn btn-info btn-sm">
                            <i class="fas fa-file-medical"></i> View Medical Certificate
                        </a>
                    </div>
                </div>
                ` : ''}
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Application Details</h6>
                        <p><strong>Applied Date:</strong> ${new Date(requestData.applied_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
                        <p><strong>Status:</strong> <span class="badge badge-warning">Pending Approval</span></p>
                    </div>
                </div>
            `;
            
            $('#detailsModalBody').html(detailsHtml);
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
});
</script>
</body>
</html>