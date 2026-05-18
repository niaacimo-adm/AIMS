<?php
// personal_locator_slip.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_locator_slip'])) {
        $employee_id = $_SESSION['emp_id'];
        $date = $_POST['date'];
        $leave_time = $_POST['leave_time'];
        $purpose_type = $_POST['purpose_type'];
        $purpose_details = $_POST['purpose_details'];
        $expected_return = $_POST['expected_return'];
        $no_return = isset($_POST['no_return']) ? 1 : 0;
        
        // Validate time constraints based on purpose type
        $isValid = true;
        $validationError = '';
        
        if (!$no_return && $expected_return) {
            $leave_timestamp = strtotime($leave_time);
            $return_timestamp = strtotime($expected_return);
            $time_difference = ($return_timestamp - $leave_timestamp) / 3600; // Convert to hours
            
            if ($purpose_type === 'personal' && $time_difference > 1) {
                $isValid = false;
                $validationError = "For personal matters, the maximum allowed time is 1 hour.";
            } elseif ($purpose_type === 'official') {
                // For official business, check if return is on the same day
                $leave_datetime = strtotime($date . ' ' . $leave_time);
                $return_datetime = strtotime($date . ' ' . $expected_return);
                
                // If return time is earlier than leave time, assume it's next day
                if ($return_datetime < $leave_datetime) {
                    $return_datetime = strtotime('+1 day', $return_datetime);
                }
                
                $time_difference_hours = ($return_datetime - $leave_datetime) / 3600;
                
                if ($time_difference_hours > 24) {
                    $isValid = false;
                    $validationError = "For official business, the return time must be within the same day.";
                }
            }
        }
        
        // Validate personal matter frequency (once per week)
        if ($purpose_type === 'personal') {
            // Get the start and end of the current week (Monday to Sunday)
            $current_date = new DateTime($date);
            $week_start = clone $current_date;
            $week_start->modify('this week'); // Gets Monday of current week
            $week_start->setTime(0, 0, 0);
            
            $week_end = clone $week_start;
            $week_end->modify('next week')->modify('-1 day'); // Gets Sunday of current week
            $week_end->setTime(23, 59, 59);
            
            // Store formatted dates in variables
            $week_start_formatted = $week_start->format('Y-m-d');
            $week_end_formatted = $week_end->format('Y-m-d');
            
            // Check if employee already has a personal matter slip this week
            $frequency_query = "SELECT COUNT(*) as slip_count 
                            FROM personal_locator_slips 
                            WHERE employee_id = ? 
                            AND purpose_type = 'personal' 
                            AND date BETWEEN ? AND ? 
                            AND status != 'rejected'";
            
            $freq_stmt = $db->prepare($frequency_query);
            $freq_stmt->bind_param("iss", $employee_id, $week_start_formatted, $week_end_formatted);
            $freq_stmt->execute();
            $freq_result = $freq_stmt->get_result();
            $slip_count = $freq_result->fetch_assoc()['slip_count'];
            
            if ($slip_count > 0) {
                $isValid = false;
                $validationError = "You can only submit one personal matter locator slip per week. You already have a personal matter slip for this week (".$week_start->format('M j')." - ".$week_end->format('M j, Y').").";
            }
        }

        if ($isValid) {
            // Insert into database
            $query = "INSERT INTO personal_locator_slips 
                      (employee_id, date, leave_time, purpose_type, purpose_details, 
                       expected_return, no_return, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("isssssi", $employee_id, $date, $leave_time, 
                             $purpose_type, $purpose_details, $expected_return, $no_return);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Personal Locator Slip submitted successfully!";
                header("Location: personal_locator_slip.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error submitting slip: " . $stmt->error;
            }
        } else {
            $_SESSION['error_message'] = $validationError;
        }
    }
    
    // Handle approval
    if (isset($_POST['approve_slip']) && hasPermission('manage_employees')) {
        $slip_id = $_POST['slip_id'];
        $query = "UPDATE personal_locator_slips SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ii", $_SESSION['emp_id'], $slip_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Slip approved successfully!";
            header("Location: personal_locator_slip.php");
            exit();
        }
    }
    
    // Handle rejection
    if (isset($_POST['reject_slip']) && hasPermission('manage_employees')) {
        $slip_id = $_POST['slip_id'];
        $query = "UPDATE personal_locator_slips SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ii", $_SESSION['emp_id'], $slip_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Slip rejected!";
            header("Location: personal_locator_slip.php");
            exit();
        }
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get employee data
$employee_name = '';
$employee_data = [];
if (isset($_SESSION['emp_id'])) {
    $query = "SELECT e.*, p.position_name, s.section_name, o.office_name 
              FROM employee e 
              LEFT JOIN position p ON e.position_id = p.position_id 
              LEFT JOIN section s ON e.section_id = s.section_id 
              LEFT JOIN office o ON e.office_id = o.office_id 
              WHERE e.emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $_SESSION['emp_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $employee_data = $result->fetch_assoc();
        $employee_name = htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']);
    }
}

$slips_query = "SELECT pls.*, e.first_name, e.last_name, e.position_id, p.position_name, 
                       s.section_name, app.first_name as approver_first, app.last_name as approver_last
                FROM personal_locator_slips pls
                JOIN employee e ON pls.employee_id = e.emp_id
                LEFT JOIN position p ON e.position_id = p.position_id
                LEFT JOIN section s ON e.section_id = s.section_id
                LEFT JOIN employee app ON pls.approved_by = app.emp_id";
                
$slips_query .= " WHERE pls.employee_id = ? ORDER BY pls.created_at DESC";
$stmt = $db->prepare($slips_query);
$stmt->bind_param("i", $_SESSION['emp_id']);
$stmt->execute();
$slips_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Locator Slip - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
        .time-validation-message {
            font-size: 0.8rem;
            margin-top: 5px;
            display: none;
        }
        
        .time-validation-message.valid {
            color: #28a745;
        }
        
        .time-validation-message.invalid {
            color: #dc3545;
        }
        
        .excel-btn.hidden {
            display: none !important;
        }
    
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

    <!-- Navbar -->
    <?php include '../includes/mainheader.php'; ?>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        
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
                    <div class="pg-hero-title"><i class="fas fa-location-arrow"></i>Personal Locator Slip</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Submit and track your locator slip</p>
                </div>
            </div>
                <nav style="position:relative;z-index:2;margin-top:10px;">
                    <ol class="breadcrumb pg-hero-breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="pg-hero-bc-link">Dashboard</a></li><li class="breadcrumb-item active pg-hero-bc-active">Personal Locator Slip</li>
                    </ol>
                </nav>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Success/Error Messages will be handled by SweetAlert -->
                
                <div class="row">
                    <div class="col-md-6">
                        <!-- Personal Locator Slip Form -->
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Request Permission to Leave</h3>
                            </div>
                            <!-- /.card-header -->
                            <!-- form start -->
                            <form method="POST" action="" id="locatorForm">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" required 
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Undersigned hereby request permission to leave this office on</label>
                                        <input type="time" class="form-control" name="leave_time" id="leave_time" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>I intend to go to:</label>
                                        <select class="form-control" name="purpose_type" id="purpose_type" required>
                                            <option value="">Select Purpose</option>
                                            <option value="personal">Personal Matter</option>
                                            <option value="official">Official Business</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Purpose Details</label>
                                        <textarea class="form-control" name="purpose_details" rows="3" 
                                                  placeholder="State briefly the nature of your purpose..." required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="no_return" id="no_return">
                                            <label class="form-check-label" for="no_return">
                                                I don't expect to return to the office on the above date.
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" id="return_time_group">
                                        <label>I expect to return to the office at about</label>
                                        <input type="time" class="form-control" name="expected_return" id="expected_return">
                                        <div id="timeValidationMessage" class="time-validation-message"></div>
                                    </div>
                                </div>
                                <!-- /.card-body -->

                                <div class="card-footer">
                                    <button type="submit" name="submit_locator_slip" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                        <!-- /.card -->
                    </div>

                    <div class="col-md-6">
                        <!-- Recent Slips -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">My Recent Requests</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Leave Time</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($slip = $slips_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($slip['date'])) ?></td>
                                                <td><?= date('g:i A', strtotime($slip['leave_time'])) ?></td>
                                                <td><?= ucfirst($slip['purpose_type']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= 
                                                        $slip['status'] == 'approved' ? 'success' : 
                                                        ($slip['status'] == 'rejected' ? 'danger' : 'warning')
                                                    ?>">
                                                        <?= ucfirst($slip['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="viewSlipDetails(<?= $slip['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (hasPermission('manage_employees') && $slip['status'] == 'pending'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                                                            <button type="submit" name="approve_slip" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                                                            <button type="submit" name="reject_slip" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($slip['status'] != 'rejected'): ?>
                                                        <button type="button" class="btn btn-sm btn-warning excel-btn" 
                                                                onclick="generateExcelSlip(<?= $slip['id'] ?>)">
                                                            <i class="fas fa-file-excel"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <?php include '../includes/mainfooter.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Modal for viewing slip details -->
<div class="modal fade" id="slipDetailsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Personal Locator Slip Details</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="slipDetailsContent">
                <!-- Details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Show SweetAlert messages for success and error
    <?php if (!empty($success_message)): ?>
    Swal.fire({
        title: 'Success!',
        text: '<?= $success_message ?>',
        icon: 'success',
        confirmButtonColor: '#4361ee',
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    Swal.fire({
        title: 'Error!',
        text: '<?= $error_message ?>',
        icon: 'error',
        confirmButtonColor: '#4361ee'
    });
    <?php endif; ?>

    // Toggle return time based on checkbox
    $('#no_return').change(function() {
        if ($(this).is(':checked')) {
            $('#return_time_group').hide();
            $('#expected_return').prop('required', false);
            $('#timeValidationMessage').hide();
        } else {
            $('#return_time_group').show();
            $('#expected_return').prop('required', true);
            validateTimeConstraints();
        }
    });
    
    // Time validation based on purpose type
    function validateTimeConstraints() {
        const purposeType = $('#purpose_type').val();
        const leaveTime = $('#leave_time').val();
        const expectedReturn = $('#expected_return').val();
        const noReturn = $('#no_return').is(':checked');
        const selectedDate = $('#date').val();
        
        if (noReturn || !leaveTime || !expectedReturn || !purposeType || !selectedDate) {
            $('#timeValidationMessage').hide();
            return true;
        }
        
        const leaveDateTime = new Date(selectedDate + 'T' + leaveTime);
        const returnDateTime = new Date(selectedDate + 'T' + expectedReturn);
        
        // If return time is earlier than leave time, assume it's next day for official business
        if (returnDateTime < leaveDateTime && purposeType === 'official') {
            returnDateTime.setDate(returnDateTime.getDate() + 1);
        }
        
        const timeDifference = (returnDateTime - leaveDateTime) / (1000 * 60 * 60); // Convert to hours
        
        const messageElement = $('#timeValidationMessage');
        
        if (purposeType === 'personal') {
            if (timeDifference > 1) {
                messageElement.removeClass('valid').addClass('invalid');
                messageElement.text('For personal matters, maximum allowed time is 1 hour.');
                messageElement.show();
                return false;
            } else if (timeDifference <= 0) {
                messageElement.removeClass('valid').addClass('invalid');
                messageElement.text('Return time must be after leave time.');
                messageElement.show();
                return false;
            } else {
                messageElement.removeClass('invalid').addClass('valid');
                messageElement.text('Time duration is within 1 hour limit.');
                messageElement.show();
                return true;
            }
        } else if (purposeType === 'official') {
            if (timeDifference > 24) {
                messageElement.removeClass('valid').addClass('invalid');
                messageElement.text('For official business, return time must be within the same day.');
                messageElement.show();
                return false;
            } else if (timeDifference <= 0) {
                messageElement.removeClass('valid').addClass('invalid');
                messageElement.text('Return time must be after leave time.');
                messageElement.show();
                return false;
            } else {
                messageElement.removeClass('invalid').addClass('valid');
                messageElement.text('Return time is within same day.');
                messageElement.show();
                return true;
            }
        }
        
        messageElement.hide();
        return true;
    }

    // Add event listeners for time validation
    $('#purpose_type, #leave_time, #expected_return, #no_return, #date').on('change input', function() {
        if (!$('#no_return').is(':checked')) {
            validateTimeConstraints();
        }
    });
    
    // Form submission validation with SweetAlert
    $('#locatorForm').on('submit', function(e) {
        const noReturn = $('#no_return').is(':checked');
        const expectedReturn = $('#expected_return').val();
        const purposeType = $('#purpose_type').val();
        const leaveTime = $('#leave_time').val();
        
        // Basic validation
        if (!noReturn && !expectedReturn) {
            e.preventDefault();
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please provide expected return time or check "No Return Today".',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
            return false;
        }
        
        // Time constraint validation
        if (!noReturn && expectedReturn) {
            const isValidTime = validateTimeConstraints();
            if (!isValidTime) {
                e.preventDefault();
                Swal.fire({
                    title: 'Time Validation Error!',
                    text: $('#timeValidationMessage').text(),
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                return false;
            }
        }
        
        // Additional validation for personal matters
        if (!noReturn && expectedReturn && purposeType === 'personal') {
            const leaveDateTime = new Date($('#date').val() + 'T' + leaveTime);
            const returnDateTime = new Date($('#date').val() + 'T' + expectedReturn);
            const timeDifference = (returnDateTime - leaveDateTime) / (1000 * 60 * 60);
            
            if (timeDifference > 1) {
                e.preventDefault();
                Swal.fire({
                    title: 'Time Validation Error!',
                    text: 'For personal matters, maximum allowed time is 1 hour.',
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                return false;
            }
        }

        // Show loading message
        Swal.fire({
            title: 'Submitting Request',
            text: 'Please wait...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });

    // Hide Excel buttons for rejected slips
    $('tr').each(function() {
        const statusBadge = $(this).find('.badge');
        if (statusBadge.length && statusBadge.text().toLowerCase() === 'rejected') {
            $(this).find('.excel-btn').hide();
        }
    });
    
    // Initialize state
    $('#no_return').trigger('change');
});

function viewSlipDetails(slipId) {
    // Show loading state
    $('#slipDetailsContent').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading slip details...</p>
        </div>
    `);
    $('#slipDetailsModal').modal('show');
    
    $.ajax({
        url: 'get_slip_details.php',
        type: 'GET',
        data: { id: slipId },
        success: function(response) {
            $('#slipDetailsContent').html(response);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $('#slipDetailsContent').html(`
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle text-danger fa-2x mb-3"></i>
                    <h5>Error Loading Details</h5>
                    <p>Failed to load slip details. Please try again.</p>
                </div>
            `);
            
            Swal.fire({
                title: 'Error!',
                text: 'Failed to load slip details.',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
        }
    });
}

function generateExcelSlip(slipId) {
    // Show SweetAlert loader
    Swal.fire({
        title: 'Generating Excel File',
        text: 'Please wait while we prepare your download...',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Generate Excel file using the template
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);
    
    iframe.src = 'generate_excel_slip.php?id=' + slipId;
    
    // Hide loader after 2 seconds and show success message
    setTimeout(() => {
        Swal.close();
        document.body.removeChild(iframe);
        
        Swal.fire({
            title: 'Success!',
            text: 'Excel file download started.',
            icon: 'success',
            confirmButtonColor: '#4361ee',
            timer: 1500,
            showConfirmButton: false
        });
    }, 1500);
    
    // Error handling
    iframe.onerror = function() {
        Swal.close();
        document.body.removeChild(iframe);
        
        Swal.fire({
            title: 'Error!',
            text: 'Failed to generate Excel file.',
            icon: 'error',
            confirmButtonColor: '#4361ee'
        });
    };
}
</script>
</body>
</html>