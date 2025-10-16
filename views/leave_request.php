<?php
// Start session first
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php'; // Add database config
require_once 'leave_functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$leaveFunctions = new LeaveFunctions();
$emp_id = $_SESSION['emp_id'] ?? null;

// Redirect if not logged in
if (!$emp_id) {
    header('Location: ../login.php');
    exit;
}

// Get employee details
$employee_query = "SELECT e.*, s.section_name, p.position_name 
                  FROM employee e 
                  LEFT JOIN section s ON e.section_id = s.section_id 
                  LEFT JOIN position p ON e.position_id = p.position_id 
                  WHERE e.emp_id = ?";
$stmt = $db->prepare($employee_query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Get leave types
$leave_types = $leaveFunctions->getLeaveTypes();

// Get employee's leave requests
$leave_requests = $leaveFunctions->getEmployeeLeaveRequests($emp_id);

// Handle form submission
if ($_POST && isset($_POST['apply_leave'])) {
    $data = [
        'emp_id' => $emp_id,
        'leave_type_id' => $_POST['leave_type_id'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'particulars' => $_POST['particulars'],
        'remarks' => $_POST['remarks'],
        'medical_certificate' => null
    ];
    
    // Handle file upload
    if (isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] == 0) {
        $upload_dir = '../uploads/medical_certificates/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['medical_certificate']['name'], PATHINFO_EXTENSION);
        $filename = 'med_cert_' . $emp_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $upload_path)) {
            $data['medical_certificate'] = $filename;
        }
    }
    
    // Check for overlapping leaves
    if ($leaveFunctions->hasOverlappingLeave($emp_id, $data['start_date'], $data['end_date'])) {
        $error = "You already have a pending or approved leave during this period.";
    } else {
        $leave_id = $leaveFunctions->submitLeaveRequest($data);
        if ($leave_id) {
            $success = "Leave request submitted successfully! Waiting for section head approval.";
        } else {
            $error = "Failed to submit leave request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Request - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .leave-type-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .leave-type-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
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
                        <h1 class="m-0">Leave Request</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Leave Request</li>
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

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Employee Information</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></p>
                                <p><strong>Position:</strong> <?php echo $employee['position_name'] ?? 'N/A'; ?></p>
                                <p><strong>Section:</strong> <?php echo $employee['section_name'] ?? 'N/A'; ?></p>
                                <p><strong>Appointment:</strong> 
                                    <?php 
                                    $appointment_query = "SELECT status_name FROM appointment_status WHERE appointment_id = ?";
                                    $stmt = $db->prepare($appointment_query);
                                    $stmt->bind_param("i", $employee['appointment_status_id']);
                                    $stmt->execute();
                                    $appointment = $stmt->get_result()->fetch_assoc();
                                    echo $appointment['status_name'] ?? 'N/A';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Quick Leave Balance</h3>
                            </div>
                            <div class="card-body">
                                <?php 
                                $common_leaves = [1, 2, 3]; // VL, MFL, SL
                                foreach ($common_leaves as $leave_type_id): 
                                    $balance = $leaveFunctions->getLeaveBalance($emp_id, $leave_type_id);
                                    $leave_type = array_filter($leave_types, function($lt) use ($leave_type_id) {
                                        return $lt['leave_type_id'] == $leave_type_id;
                                    });
                                    $leave_type = reset($leave_type);
                                ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo $leave_type['leave_code']; ?></span>
                                        <span class="badge badge-info"><?php echo $balance; ?> days</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Complete Leave Balance</h3>
                                <div class="card-tools">
                                    <a href="leave_balance.php" class="btn btn-sm btn-primary">View Details</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Total</th>
                                                <th>Used</th>
                                                <th>Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $balance_summary = $leaveFunctions->getEmployeeLeaveBalanceSummary($emp_id);
                                            foreach ($balance_summary as $balance): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $balance['leave_name']; ?></td>
                                                    <td><?php echo $balance['total_credits']; ?></td>
                                                    <td><?php echo $balance['used_credits']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $balance['balance'] > 0 ? 'success' : 'danger'; ?>">
                                                            <?php echo $balance['balance']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Apply for Leave</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="leave_type_id">Type of Leave *</label>
                                                <select class="form-control" id="leave_type_id" name="leave_type_id" required>
                                                    <option value="">Select Leave Type</option>
                                                    <?php foreach ($leave_types as $type): ?>
                                                        <option value="<?php echo $type['leave_type_id']; ?>" 
                                                                data-requires-medical="<?php echo $type['requires_medical_certificate']; ?>">
                                                            <?php echo $type['leave_name'] . ' (' . $type['leave_code'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Available Balance</label>
                                                <div id="balance_display" class="form-control-plaintext">
                                                    Select a leave type to see balance
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_date">Start Date *</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" required 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_date">End Date *</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" required
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Number of Days</label>
                                                <div id="days_count" class="form-control-plaintext">0 days</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="medical_certificate_group" style="display: none;">
                                                <label for="medical_certificate">Medical Certificate</label>
                                                <input type="file" class="form-control-file" id="medical_certificate" name="medical_certificate" 
                                                       accept=".pdf,.jpg,.jpeg,.png">
                                                <small class="form-text text-muted">Required for this leave type (PDF, JPG, PNG)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="particulars">Particulars *</label>
                                        <textarea class="form-control" id="particulars" name="particulars" rows="3" 
                                                  placeholder="Please specify the reason for your leave..." required></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="remarks">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                                  placeholder="Additional notes or information..."></textarea>
                                    </div>

                                    <button type="submit" name="apply_leave" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Leave Request
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">My Leave History</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Period</th>
                                                <th>Days</th>
                                                <th>Status</th>
                                                <th>Applied Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leave_requests)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No leave requests found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leave_requests as $request): ?>
                                                    <tr>
                                                        <td><?php echo $request['leave_name']; ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?> - <?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                                        <td><?php echo $request['total_days']; ?></td>
                                                        <td>
                                                            <?php 
                                                            $badge_class = [
                                                                'pending' => 'warning',
                                                                'approved' => 'success',
                                                                'rejected' => 'danger',
                                                                'cancelled' => 'secondary'
                                                            ];
                                                            ?>
                                                            <span class="badge badge-<?php echo $badge_class[$request['status']]; ?>">
                                                                <?php echo ucfirst($request['status']); ?>
                                                            </span>
                                                            <?php if (!$request['section_head_approved'] && $request['status'] == 'pending'): ?>
                                                                <br><small class="text-muted">Waiting for section head</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($request['applied_date'])); ?></td>
                                                        <td>
    <?php if ($request['status'] == 'pending'): ?>
        <button class="btn btn-sm btn-warning cancel-leave" data-id="<?php echo $request['leave_id']; ?>">
            <i class="fas fa-times"></i> Cancel
        </button>
    <?php elseif ($request['status'] == 'approved'): ?>
        <a href="print_leave_form.php?leave_id=<?php echo $request['leave_id']; ?>" 
           class="btn btn-sm btn-success" target="_blank">
            <i class="fas fa-print"></i> Print Form
        </a>
    <?php endif; ?>
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
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Set minimum dates to today
    var today = new Date().toISOString().split('T')[0];
    $('#start_date').attr('min', today);
    $('#end_date').attr('min', today);

    // Calculate days when dates change
    $('#start_date, #end_date').change(function() {
        calculateDays();
    });

    // Show/hide medical certificate field based on leave type
    $('#leave_type_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        var requiresMedical = selectedOption.data('requires-medical');
        
        if (requiresMedical == 1) {
            $('#medical_certificate_group').show();
            $('#medical_certificate').prop('required', true);
        } else {
            $('#medical_certificate_group').hide();
            $('#medical_certificate').prop('required', false);
        }
        
        // Update balance display
        var leaveTypeId = $(this).val();
        if (leaveTypeId) {
            updateBalanceDisplay(leaveTypeId);
        }
    });

    function calculateDays() {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        
        if (startDate && endDate && startDate <= endDate) {
            // Calculate working days (exclude weekends)
            var count = 0;
            var current = new Date(startDate);
            
            while (current <= endDate) {
                var day = current.getDay();
                if (day !== 0 && day !== 6) { // Not Sunday (0) or Saturday (6)
                    count++;
                }
                current.setDate(current.getDate() + 1);
            }
            
            $('#days_count').text(count + ' working day(s)');
        } else {
            $('#days_count').text('0 days');
        }
    }

    function updateBalanceDisplay(leaveTypeId) {
        $.ajax({
            url: 'get_leave_balance.php',
            type: 'POST',
            data: { leave_type_id: leaveTypeId },
            success: function(response) {
                $('#balance_display').html(response);
            },
            error: function() {
                $('#balance_display').html('<span class="text-danger">Error loading balance</span>');
            }
        });
    }

    // Cancel leave request
    $('.cancel-leave').click(function() {
        var leaveId = $(this).data('id');
        if (confirm('Are you sure you want to cancel this leave request?')) {
            $.ajax({
                url: 'cancel_leave_request.php',
                type: 'POST',
                data: { leave_id: leaveId },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error cancelling leave request: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error cancelling leave request');
                }
            });
        }
    });
});
</script>
</body>
</html>