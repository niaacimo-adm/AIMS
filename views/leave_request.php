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
        :root {
            --admin-primary: #4361ee;
            --admin-secondary: #3f37c9;
            --admin-light: #f8f9fa;
            --admin-dark: #212529;
            --admin-success: #28a745;
            --admin-warning: #ffc107;
            --admin-danger: #dc3545;
        }

        .modern-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .modern-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .modern-card .card-header {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .balance-card {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            color: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .balance-card .card-body {
            padding: 1.5rem;
        }

        .leave-type-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .leave-type-card:hover {
            transform: translateY(-5px);
            border-color: var(--admin-primary);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .status-badge {
            font-size: 0.75em;
            padding: 0.35em 0.7em;
            border-radius: 20px;
            font-weight: 500;
        }

        .form-control-modern {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-modern:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .btn-modern {
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .table-modern {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .table-modern thead th {
            /* background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary)); */
            color: dark;
            border: none;
            padding: 0.5rem;
            font-weight: 600;
        }

        .table-modern tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f8f9fa;
        }

        .table-modern tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .info-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-panel h5 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .info-panel p {
            margin-bottom: 0.25rem;
            opacity: 0.9;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .nav-tabs-modern {
            border-bottom: 2px solid #e9ecef;
        }

        .nav-tabs-modern .nav-link {
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .nav-tabs-modern .nav-link.active {
            color: var(--admin-primary);
            border-bottom: 3px solid var(--admin-primary);
            background: transparent;
        }

        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-upload-area:hover {
            border-color: var(--admin-primary);
            background: rgba(67, 97, 238, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--admin-primary);
            background: rgba(67, 97, 238, 0.1);
        }

        .days-counter {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--admin-primary);
        }

        @media (max-width: 768px) {
            .modern-card .card-header {
                padding: 1rem;
            }
            
            .btn-modern {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
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
                        <h1 class="m-0 text-dark">Leave Request</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item active">Leave Request</li>
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
                        <i class="icon fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Left Column - Employee Info & Balance -->
                    <div class="col-lg-4">
                        <!-- Quick Balance Card -->
                        <div class="card modern-card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-pie mr-2"></i>Quick Leave Balance
                            </div>
                            <div class="card-body">
                                <div class="quick-stats">
                                    <?php 
                                    $common_leaves = [1, 2, 3]; // VL, MFL, SL
                                    foreach ($common_leaves as $leave_type_id): 
                                        $balance = $leaveFunctions->getLeaveBalance($emp_id, $leave_type_id);
                                        $leave_type = array_filter($leave_types, function($lt) use ($leave_type_id) {
                                            return $lt['leave_type_id'] == $leave_type_id;
                                        });
                                        $leave_type = reset($leave_type);
                                    ?>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $balance; ?></div>
                                            <div class="stat-label"><?php echo $leave_type['leave_code']; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Complete Balance Card -->
                        <div class="card modern-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-list-alt mr-2"></i>Complete Leave Balance
                                </div>
                                <a href="leave_balance.php" class="btn btn-sm btn-light">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="border-0">Leave Type</th>
                                                <th class="border-0 text-center">Total</th>
                                                <th class="border-0 text-center">Used</th>
                                                <th class="border-0 text-center">Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $balance_summary = $leaveFunctions->getEmployeeLeaveBalanceSummary($emp_id);
                                            foreach ($balance_summary as $balance): 
                                            ?>
                                                <tr>
                                                    <td class="border-0">
                                                        <small class="font-weight-bold"><?php echo $balance['leave_name']; ?></small>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <span class="badge badge-light"><?php echo $balance['total_credits']; ?></span>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <span class="badge badge-light"><?php echo $balance['used_credits']; ?></span>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <span class="badge badge-<?php echo $balance['balance'] > 0 ? 'success' : 'danger'; ?> status-badge">
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

                    <!-- Right Column - Application Form & History -->
                    <div class="col-lg-8">
                        <!-- Application Form Card -->
                        <div class="card modern-card mb-4">
                            <div class="card-header">
                                <i class="fas fa-edit mr-2"></i>Apply for Leave
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" id="leaveForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="leave_type_id" class="font-weight-bold">Type of Leave <span class="text-danger">*</span></label>
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
                                                <label class="font-weight-bold">Available Balance</label>
                                                <div id="balance_display" class="form-control-plaintext p-2 bg-light rounded">
                                                    <span class="text-muted">Select a leave type to see balance</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_date" class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-modern" id="start_date" name="start_date" required 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_date" class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-modern" id="end_date" name="end_date" required
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="font-weight-bold">Number of Days</label>
                                                <div id="days_count" class="days-counter">0 working day(s)</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="medical_certificate_group" style="display: none;">
                                                <label for="medical_certificate" class="font-weight-bold">Medical Certificate</label>
                                                <div class="file-upload-area" id="fileUploadArea">
                                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                                                    <p class="mb-2">Drag & drop your file here</p>
                                                    <p class="small text-muted mb-3">or click to browse</p>
                                                    <input type="file" class="d-none" id="medical_certificate" name="medical_certificate" 
                                                           accept=".pdf,.jpg,.jpeg,.png">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('medical_certificate').click()">
                                                        Choose File
                                                    </button>
                                                </div>
                                                <small class="form-text text-muted">Required for this leave type (PDF, JPG, PNG, max 5MB)</small>
                                                <div id="filePreview" class="mt-2"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="particulars" class="font-weight-bold">Particulars <span class="text-danger">*</span></label>
                                        <textarea class="form-control form-control-modern" id="particulars" name="particulars" rows="3" 
                                                  placeholder="Please specify the reason for your leave..." required></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="remarks" class="font-weight-bold">Remarks</label>
                                        <textarea class="form-control form-control-modern" id="remarks" name="remarks" rows="2" 
                                                  placeholder="Additional notes or information..."></textarea>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="submit" name="apply_leave" class="btn btn-primary-modern btn-modern">
                                            <i class="fas fa-paper-plane mr-2"></i> Submit Leave Request
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary btn-modern">
                                            <i class="fas fa-redo mr-2"></i> Reset Form
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Leave History Card -->
                        <div class="card modern-card">
                            <div class="card-header">
                                <i class="fas fa-history mr-2"></i>My Leave History
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-modern table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Period</th>
                                                <th class="text-center">Days</th>
                                                <th>Status</th>
                                                <th>Applied Date</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leave_requests)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                        No leave requests found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leave_requests as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-primary rounded-circle p-2 mr-3">
                                                                    <i class="fas fa-calendar-alt text-white" style="font-size: 0.8rem;"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-bold"><?php echo $request['leave_name']; ?></div>
                                                                    <small class="text-muted"><?php echo $request['leave_code']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-nowrap">
                                                                <div><?php echo date('M j, Y', strtotime($request['start_date'])); ?></div>
                                                                <div class="text-muted small">to</div>
                                                                <div><?php echo date('M j, Y', strtotime($request['end_date'])); ?></div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-primary status-badge"><?php echo $request['total_days']; ?> days</span>
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
                                                            <span class="badge badge-<?php echo $badge_class[$request['status']]; ?> status-badge">
                                                                <?php echo ucfirst($request['status']); ?>
                                                            </span>
                                                            <?php if (!$request['section_head_approved'] && $request['status'] == 'pending'): ?>
                                                                <br><small class="text-muted"><i class="fas fa-clock mr-1"></i>Waiting for section head</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="text-nowrap">
                                                                <?php echo date('M j, Y', strtotime($request['applied_date'])); ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($request['status'] == 'pending'): ?>
                                                                <button class="btn btn-sm btn-outline-warning cancel-leave" data-id="<?php echo $request['leave_id']; ?>" title="Cancel Request">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            <?php elseif ($request['status'] == 'approved'): ?>
                                                                <a href="print_leave_form.php?leave_id=<?php echo $request['leave_id']; ?>" 
                                                                   class="btn btn-sm btn-outline-success" target="_blank" title="Print Form">
                                                                    <i class="fas fa-print"></i>
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
            $('#filePreview').empty();
        }
        
        // Update balance display
        var leaveTypeId = $(this).val();
        if (leaveTypeId) {
            updateBalanceDisplay(leaveTypeId);
        }
    });

    // File upload handling
    $('#medical_certificate').change(function(e) {
        handleFileSelect(e);
    });

    // Drag and drop functionality
    $('#fileUploadArea').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $('#fileUploadArea').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $('#fileUploadArea').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#medical_certificate').prop('files', files);
            handleFileSelect(e);
        }
    });

    // Click to upload
    $('#fileUploadArea').click(function() {
        $('#medical_certificate').click();
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
            $('#days_count').text('0 working day(s)');
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
                $('#balance_display').html('<span class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Error loading balance</span>');
            }
        });
    }

    function handleFileSelect(e) {
        var file = e.target.files[0] || e.originalEvent.dataTransfer.files[0];
        if (file) {
            var fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
            if (fileSize > 5) {
                alert('File size must be less than 5MB');
                $('#medical_certificate').val('');
                $('#filePreview').empty();
                return;
            }

            var fileType = file.type;
            var validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            
            if (!validTypes.includes(fileType)) {
                alert('Please select a valid file type (PDF, JPG, PNG)');
                $('#medical_certificate').val('');
                $('#filePreview').empty();
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                var previewHtml = '';
                if (fileType === 'application/pdf') {
                    previewHtml = '<div class="d-flex align-items-center p-2 bg-light rounded">' +
                                 '<i class="fas fa-file-pdf text-danger fa-2x mr-3"></i>' +
                                 '<div>' +
                                 '<div class="font-weight-bold">' + file.name + '</div>' +
                                 '<small class="text-muted">' + fileSize + ' MB</small>' +
                                 '</div>' +
                                 '</div>';
                } else {
                    previewHtml = '<div class="d-flex align-items-center p-2 bg-light rounded">' +
                                 '<i class="fas fa-file-image text-success fa-2x mr-3"></i>' +
                                 '<div>' +
                                 '<div class="font-weight-bold">' + file.name + '</div>' +
                                 '<small class="text-muted">' + fileSize + ' MB</small>' +
                                 '</div>' +
                                 '</div>';
                }
                $('#filePreview').html(previewHtml);
            };
            reader.readAsDataURL(file);
        }
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

    // Form validation
    $('#leaveForm').on('submit', function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        
        if (startDate > endDate) {
            e.preventDefault();
            alert('End date cannot be before start date.');
            return false;
        }
    });
});
</script>
</body>
</html>