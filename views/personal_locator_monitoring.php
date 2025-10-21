<?php
// personal_locator_monitoring.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check permissions
if (!hasPermission('manage_employees')) {
    header('Location: ../unauthorized.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters - MOVED TO TOP
$status_filter = $_GET['status'] ?? 'pending';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$section_filter = $_GET['section'] ?? '';

// DEBUG: Check if form is being submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== POST REQUEST RECEIVED ===");
    error_log("POST data: " . print_r($_POST, true));
    
    if (isset($_POST['create_slip'])) {
        error_log("CREATE_SLIP form submitted!");
        error_log("Employee ID: " . $_POST['employee_id']);
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_approve'])) {
        $slip_ids = $_POST['slip_ids'] ?? [];
        if (!empty($slip_ids)) {
            $placeholders = str_repeat('?,', count($slip_ids) - 1) . '?';
            $query = "UPDATE personal_locator_slips SET status = 'approved', approved_by = ?, approved_at = NOW() 
                      WHERE id IN ($placeholders) AND status = 'pending'";
            $stmt = $db->prepare($query);
            $types = str_repeat('i', count($slip_ids) + 1);
            $params = array_merge([$_SESSION['emp_id']], $slip_ids);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $_SESSION['success_message'] = count($slip_ids) . " slip(s) approved successfully!";
        }
    }
    
    if (isset($_POST['bulk_reject'])) {
        $slip_ids = $_POST['slip_ids'] ?? [];
        if (!empty($slip_ids)) {
            $placeholders = str_repeat('?,', count($slip_ids) - 1) . '?';
            $query = "UPDATE personal_locator_slips SET status = 'rejected', approved_by = ?, approved_at = NOW() 
                      WHERE id IN ($placeholders) AND status = 'pending'";
            $stmt = $db->prepare($query);
            $types = str_repeat('i', count($slip_ids) + 1);
            $params = array_merge([$_SESSION['emp_id']], $slip_ids);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $_SESSION['success_message'] = count($slip_ids) . " slip(s) rejected successfully!";
        }
    }
    
    // Handle individual actions
    if (isset($_POST['approve_slip'])) {
        $slip_id = $_POST['slip_id'];
        $query = "UPDATE personal_locator_slips SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ii", $_SESSION['emp_id'], $slip_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Slip approved successfully!";
    }
    
    if (isset($_POST['reject_slip'])) {
        $slip_id = $_POST['slip_id'];
        $query = "UPDATE personal_locator_slips SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ii", $_SESSION['emp_id'], $slip_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Slip rejected successfully!";
    }

    // Handle manual slip creation
    if (isset($_POST['create_slip'])) {
        error_log("=== MANUAL SLIP CREATION STARTED ===");
        
        $employee_id = $_POST['employee_id'];
        $date = $_POST['date'];
        $leave_time = $_POST['leave_time'];
        $purpose_type = $_POST['purpose_type'];
        $purpose_details = $_POST['purpose_details'];
        $no_return = isset($_POST['no_return']) ? 1 : 0;
        $expected_return = $no_return ? NULL : $_POST['expected_return'];

        // Log all form data
        error_log("Employee ID: " . $employee_id);
        error_log("Date: " . $date);
        error_log("Leave Time: " . $leave_time);
        error_log("Purpose Type: " . $purpose_type);
        error_log("Purpose Details: " . $purpose_details);
        error_log("No Return: " . $no_return);
        error_log("Expected Return: " . ($expected_return ?? 'NULL'));

        // Validate required fields
        if (empty($employee_id) || empty($date) || empty($leave_time) || empty($purpose_type) || empty($purpose_details)) {
            $_SESSION['error_message'] = "Please fill in all required fields.";
            error_log("VALIDATION FAILED: Missing required fields");
        } else {
            // Validate time constraints based on purpose type
            if (!$no_return && $expected_return) {
                $leave_timestamp = strtotime($leave_time);
                $return_timestamp = strtotime($expected_return);
                $time_difference = ($return_timestamp - $leave_timestamp) / 3600; // Convert to hours
                
                if ($purpose_type === 'personal' && $time_difference > 1) {
                    $_SESSION['error_message'] = "For personal matters, the maximum allowed time is 1 hour.";
                    error_log("VALIDATION FAILED: Personal matter exceeds 1 hour limit");
                } elseif ($purpose_type === 'official' && $time_difference > 24) {
                    $_SESSION['error_message'] = "For official business, the return time must be within the same day.";
                    error_log("VALIDATION FAILED: Official business exceeds same day limit");
                } else {
                    $query = "INSERT INTO personal_locator_slips (employee_id, date, leave_time, purpose_type, purpose_details, no_return, expected_return, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt) {
                        $stmt->bind_param("issssis", $employee_id, $date, $leave_time, $purpose_type, $purpose_details, $no_return, $expected_return);
                        
                        if ($stmt->execute()) {
                            $new_slip_id = $stmt->insert_id;
                            $_SESSION['success_message'] = "Personal locator slip created successfully!";
                            $_SESSION['close_modal'] = true; // Flag to close modal
                            error_log("SLIP CREATED SUCCESSFULLY - ID: " . $new_slip_id);
                            
                            // Force immediate redirect to refresh the data and show employee image
                            header("Location: personal_locator_monitoring.php?status=pending&created=1");
                            exit();
                        } else {
                            $_SESSION['error_message'] = "Database error: " . $stmt->error;
                            error_log("DATABASE ERROR: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Preparation failed: " . $db->error;
                        error_log("PREPARATION FAILED: " . $db->error);
                    }
                }
            } else {
                // If no return or no expected return, proceed without time validation
                $query = "INSERT INTO personal_locator_slips (employee_id, date, leave_time, purpose_type, purpose_details, no_return, expected_return, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                $stmt = $db->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param("issssis", $employee_id, $date, $leave_time, $purpose_type, $purpose_details, $no_return, $expected_return);
                    
                    if ($stmt->execute()) {
                        $new_slip_id = $stmt->insert_id;
                        $_SESSION['success_message'] = "Personal locator slip created successfully!";
                        $_SESSION['close_modal'] = true; // Flag to close modal
                        error_log("SLIP CREATED SUCCESSFULLY - ID: " . $new_slip_id);
                        
                        // Force immediate redirect to refresh the data and show employee image
                        header("Location: personal_locator_monitoring.php?status=pending&created=1");
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Database error: " . $stmt->error;
                        error_log("DATABASE ERROR: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error_message'] = "Preparation failed: " . $db->error;
                    error_log("PREPARATION FAILED: " . $db->error);
                }
            }
        }
        error_log("=== MANUAL SLIP CREATION COMPLETED ===");
    }

    // Handle slip update
    if (isset($_POST['update_slip'])) {
        $slip_id = $_POST['slip_id'];
        $date = $_POST['date'];
        $leave_time = $_POST['leave_time'];
        $purpose_type = $_POST['purpose_type'];
        $purpose_details = $_POST['purpose_details'];
        $no_return = isset($_POST['no_return']) ? 1 : 0;
        $expected_return = $no_return ? NULL : $_POST['expected_return'];

        // First check if slip exists and is still pending
        $check_query = "SELECT status FROM personal_locator_slips WHERE id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("i", $slip_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $_SESSION['error_message'] = "Slip not found.";
        } elseif ($check_result->fetch_assoc()['status'] !== 'pending') {
            $_SESSION['error_message'] = "Only pending slips can be edited.";
        } else {
            // Validate time constraints based on purpose type
            $isValid = true;
            if (!$no_return && $expected_return) {
                $leave_timestamp = strtotime($leave_time);
                $return_timestamp = strtotime($expected_return);
                $time_difference = ($return_timestamp - $leave_timestamp) / 3600; // Convert to hours
                
                if ($purpose_type === 'personal' && $time_difference > 1) {
                    $isValid = false;
                    $_SESSION['error_message'] = "For personal matters, the maximum allowed time is 1 hour.";
                } elseif ($purpose_type === 'official' && $time_difference > 24) {
                    $isValid = false;
                    $_SESSION['error_message'] = "For official business, the return time must be within the same day.";
                }
            }
            
            if ($isValid) {
                $query = "UPDATE personal_locator_slips SET date = ?, leave_time = ?, purpose_type = ?, purpose_details = ?, no_return = ?, expected_return = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("ssssisi", $date, $leave_time, $purpose_type, $purpose_details, $no_return, $expected_return, $slip_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Slip updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to update slip. Please try again. Error: " . $stmt->error;
                }
            }
        }
    }

    // Handle slip deletion
    if (isset($_POST['delete_slip'])) {
        $slip_id = $_POST['slip_id'];
        
        $query = "DELETE FROM personal_locator_slips WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $slip_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Slip deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete slip. Please try again.";
        }
    }

    // Handle bulk delete
    if (isset($_POST['bulk_delete'])) {
        $slip_ids = $_POST['slip_ids'] ?? [];
        if (!empty($slip_ids)) {
            $placeholders = str_repeat('?,', count($slip_ids) - 1) . '?';
            $query = "DELETE FROM personal_locator_slips WHERE id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $types = str_repeat('i', count($slip_ids));
            $stmt->bind_param($types, ...$slip_ids);
            $stmt->execute();
            $_SESSION['success_message'] = count($slip_ids) . " slip(s) deleted successfully!";
        }
    }

    // Handle delete all for current filter
    if (isset($_POST['delete_all'])) {
        // Build the same WHERE conditions as the main query
        $where_conditions = [];
        $params = [];
        $types = '';
        
        // Add status filter - VARIABLES NOW AVAILABLE
        if ($status_filter && $status_filter != 'all') {
            $where_conditions[] = "pls.status = ?";
            $params[] = $status_filter;
            $types .= 's';
        }
        
        // Add date range filter
        if ($date_from) {
            $where_conditions[] = "pls.date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        
        if ($date_to) {
            $where_conditions[] = "pls.date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        // Add section filter
        if ($section_filter) {
            $where_conditions[] = "e.section_id = ?";
            $params[] = $section_filter;
            $types .= 'i';
        }
        
        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }
        
        // Delete slips based on current filters
        $delete_query = "DELETE pls FROM personal_locator_slips pls 
                        JOIN employee e ON pls.employee_id = e.emp_id 
                        $where_clause";
        
        $stmt = $db->prepare($delete_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $_SESSION['success_message'] = "$affected_rows slip(s) deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete slips. Please try again.";
        }
    }
}

// Get success message from session
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// Build query with filters - UPDATED TO INCLUDE EMPLOYEE PICTURE
$slips_query = "SELECT pls.*, e.picture, e.first_name, e.last_name, e.middle_name, e.ext_name,
                       e.position_id, p.position_name, s.section_name, s.section_id,
                       app.first_name as approver_first, app.last_name as approver_last
                FROM personal_locator_slips pls
                JOIN employee e ON pls.employee_id = e.emp_id
                LEFT JOIN position p ON e.position_id = p.position_id
                LEFT JOIN section s ON e.section_id = s.section_id
                LEFT JOIN employee app ON pls.approved_by = app.emp_id
                WHERE 1=1";

$params = [];
$types = '';

// Add status filter
if ($status_filter && $status_filter != 'all') {
    $slips_query .= " AND pls.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Add date range filter
if ($date_from) {
    $slips_query .= " AND pls.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $slips_query .= " AND pls.date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Add section filter
if ($section_filter) {
    $slips_query .= " AND e.section_id = ?";
    $params[] = $section_filter;
    $types .= 'i';
}

$slips_query .= " ORDER BY pls.created_at DESC";

$stmt = $db->prepare($slips_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$slips_result = $stmt->get_result();

// Get sections for filter
$sections_query = "SELECT * FROM section ORDER BY section_name";
$sections_result = $db->query($sections_query);

// Get employees for manual slip creation
$employees_query = "SELECT emp_id, first_name, last_name, middle_name, ext_name, position_id, section_id, picture
                    FROM employee 
                    ORDER BY first_name, last_name";
$employees_result = $db->query($employees_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Locator Slip Monitoring - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .modern-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .modern-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }
        
        .modern-header {
            background: linear-gradient(135deg, var(--primary), #3a56d4);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px 25px;
            border: none;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e9ecef;
        }
        
        .bulk-actions {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        
        .status-badge {
            font-size: 0.75em;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .modern-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .modern-table thead th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .modern-table tbody td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .modern-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .modern-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.002);
        }
        
        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .btn-modern {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .form-control-modern {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 0px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .alert-modern {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
            padding: 15px 20px;
        }
        
        .badge-counter {
            background: linear-gradient(135deg, var(--primary), #3a56d4);
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .checkbox-modern {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkbox-modern:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #3a56d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
            margin-right: 10px;
        }
        
        .employee-info {
            display: flex;
            align-items: center;
        }

        /* NEW STYLES FOR EMPLOYEE PICTURES */
        .employee-picture {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .employee-picture-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #3a56d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
            border: 2px solid #e9ecef;
        }

        .employee-with-picture {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .employee-position {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 2px;
        }

        /* Manual Input Section */
        .manual-input-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e9ecef;
        }

        .collapse-toggle {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            color: var(--dark);
            width: 100%;
            text-align: left;
            transition: all 0.3s ease;
        }

        .collapse-toggle:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
        }

        .collapse-toggle i {
            transition: transform 0.3s ease;
        }

        .collapse-toggle[aria-expanded="true"] i {
            transform: rotate(180deg);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
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

        /* Delete All Button */
        .delete-all-section {
            background: linear-gradient(135deg, #fff5f5, #fed7d7);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #feb2b2;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <?php include '../includes/mainheader.php'; ?>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="font-weight-bold">Personal Locator Slip Monitoring</h1>
                        <p class="text-muted">Manage and monitor employee locator slip requests</p>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item active text-primary">Slip Monitoring</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Add this button to trigger the create modal -->
                <div class="row mb-3">
                    <div class="col-12">
                        <button type="button" class="btn btn-modern btn-primary" data-toggle="modal" data-target="#createSlipModal">
                            <i class="fas fa-plus-circle mr-2"></i>Create Manual Personal Locator Slip
                        </button>
                    </div>
                </div>
                <!-- Filter Section -->
                <div class="filter-section">
                    <h5 class="font-weight-bold mb-4 text-dark">
                        <i class="fas fa-filter mr-2 text-primary"></i>Filter Requests
                    </h5>
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-semibold">Status</label>
                                    <select class="form-control form-control-modern" name="status">
                                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-semibold">Date From</label>
                                    <input type="date" class="form-control form-control-modern" name="date_from" value="<?= $date_from ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-semibold">Date To</label>
                                    <input type="date" class="form-control form-control-modern" name="date_to" value="<?= $date_to ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-semibold">Section</label>
                                    <select class="form-control form-control-modern" name="section">
                                        <option value="">All Sections</option>
                                        <?php 
                                        // Reset sections result pointer
                                        $sections_result->data_seek(0);
                                        while ($section = $sections_result->fetch_assoc()): ?>
                                            <option value="<?= $section['section_id'] ?>" <?= $section_filter == $section['section_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($section['section_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-modern btn-primary">
                                    <i class="fas fa-filter mr-2"></i>Apply Filters
                                </button>
                                <a href="personal_locator_monitoring.php" class="btn btn-modern btn-light">
                                    <i class="fas fa-redo mr-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <?php if ($status_filter == 'pending' || $status_filter == 'all'): ?>
                <div class="bulk-actions">
                    <form method="POST" action="" id="bulkForm">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input checkbox-modern" id="selectAll">
                                    <label class="form-check-label font-weight-semibold" for="selectAll">
                                        Select All Pending Requests
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" id="bulkApproveBtn" class="btn btn-modern btn-success">
                                    <i class="fas fa-check mr-2"></i> Approve Selected
                                </button>
                                <button type="button" id="bulkRejectBtn" class="btn btn-modern btn-danger ml-2">
                                    <i class="fas fa-times mr-2"></i> Reject Selected
                                </button>
                                <button type="button" id="bulkDeleteBtn" class="btn btn-modern btn-dark ml-2">
                                    <i class="fas fa-trash mr-2"></i> Delete Selected
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Delete All Section -->
                <div class="delete-all-section">
                    <form method="POST" action="" id="deleteAllForm">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="font-weight-bold text-danger mb-0">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Danger Zone
                                </h6>
                                <p class="text-muted mb-0 mt-1">
                                    Delete all <?= $status_filter ?> slips matching current filters
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <button type="button" id="deleteAllBtn" class="btn btn-modern btn-danger">
                                    <i class="fas fa-trash mr-2"></i> Delete All (<?= $slips_result->num_rows ?>)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Slips Table -->
                <div class="modern-card">
                    <div class="modern-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0 font-weight-bold">
                                <i class="fas fa-list-alt mr-2"></i>
                                <?= ucfirst($status_filter) ?> Requests
                            </h3>
                            <span class="badge-counter"><?= $slips_result->num_rows ?> requests</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <?php if ($status_filter == 'pending' || $status_filter == 'all'): ?>
                                        <th width="50" class="text-center">
                                            <input type="checkbox" class="checkbox-modern" id="selectAllHeader">
                                        </th>
                                        <?php endif; ?>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Section</th>
                                        <th>Leave Time</th>
                                        <th>Purpose</th>
                                        <th>Expected Return</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($slip = $slips_result->fetch_assoc()): 
                                        $initials = substr($slip['first_name'], 0, 1) . substr($slip['last_name'], 0, 1);
                                        $picturePath = '../dist/img/employees/' . htmlspecialchars($slip['picture']);
                                        $hasPicture = !empty($slip['picture']) && file_exists($picturePath);
                                    ?>
                                        <tr>
                                            <?php if ($status_filter == 'pending' || $status_filter == 'all'): ?>
                                            <td class="text-center">
                                                <?php if ($slip['status'] == 'pending'): ?>
                                                    <input type="checkbox" class="checkbox-modern slip-checkbox" name="slip_ids[]" value="<?= $slip['id'] ?>">
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <div class="font-weight-semibold text-dark">
                                                    <?= date('M j, Y', strtotime($slip['date'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <!-- UPDATED EMPLOYEE COLUMN WITH PICTURE -->
                                                <div class="employee-with-picture">
                                                    <?php if ($hasPicture): ?>
                                                        <img src="<?= $picturePath ?>" 
                                                             class="employee-picture" 
                                                             alt="<?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>"
                                                             title="<?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>"
                                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <div class="employee-picture-placeholder" style="<?= $hasPicture ? 'display: none;' : '' ?>" 
                                                             title="<?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>">
                                                            <?= strtoupper($initials) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="employee-picture-placeholder" title="<?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>">
                                                            <?= strtoupper($initials) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="employee-details">
                                                        <div class="employee-name">
                                                            <?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>
                                                        </div>
                                                        <div class="employee-position">
                                                            <?= htmlspecialchars($slip['position_name'] ?? 'N/A') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($slip['section_name'] ?? 'N/A') ?></span>
                                            </td>
                                            <td>
                                                <span class="font-weight-semibold text-dark">
                                                    <?= date('g:i A', strtotime($slip['leave_time'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-muted small">
                                                    <strong class="text-dark"><?= ucfirst($slip['purpose_type']) ?>:</strong><br>
                                                    <?= nl2br(htmlspecialchars(substr($slip['purpose_details'], 0, 50))) ?><?= strlen($slip['purpose_details']) > 50 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($slip['no_return']): ?>
                                                    <span class="badge badge-secondary status-badge">No Return</span>
                                                <?php else: ?>
                                                    <span class="font-weight-semibold text-dark">
                                                        <?= date('g:i A', strtotime($slip['expected_return'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $slip['status'] == 'approved' ? 'success' : 
                                                    ($slip['status'] == 'rejected' ? 'danger' : 'warning')
                                                ?> status-badge">
                                                    <?= ucfirst($slip['status']) ?>
                                                </span>
                                                <?php if ($slip['approved_by']): ?>
                                                    <br>
                                                    <small class="text-muted">by <?= htmlspecialchars($slip['approver_first'] . ' ' . $slip['approver_last']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= date('M j, g:i A', strtotime($slip['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="action-buttons justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-info action-btn" 
                                                            onclick="viewSlipDetails(<?= $slip['id'] ?>)" 
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($slip['status'] == 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-success action-btn approve-btn" 
                                                                data-slip-id="<?= $slip['id'] ?>"
                                                                title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger action-btn reject-btn"
                                                                data-slip-id="<?= $slip['id'] ?>"
                                                                title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-warning action-btn excel-btn" 
                                                            data-slip-id="<?= $slip['id'] ?>"
                                                            title="Generate Excel">
                                                        <i class="fas fa-file-excel"></i>
                                                    </button>
                                                    <!-- Edit Button - Only show for pending slips -->
                                                    <?php if ($slip['status'] == 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-primary action-btn edit-btn" 
                                                                data-slip-id="<?= $slip['id'] ?>"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <!-- Delete Button - Show for ALL statuses -->
                                                    <button type="button" class="btn btn-sm btn-dark action-btn delete-btn"
                                                            data-slip-id="<?= $slip['id'] ?>"
                                                            data-slip-status="<?= $slip['status'] ?>"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Create Slip Modal -->
<div class="modal fade" id="createSlipModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modern-card">
            <div class="modern-header">
                <h4 class="modal-title mb-0 font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i>Create Personal Locator Slip
                </h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="" id="createSlipForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Employee *</label>
                                <select class="form-control form-control-modern" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php 
                                    $employees_result->data_seek(0);
                                    while ($employee = $employees_result->fetch_assoc()): 
                                        $fullName = $employee['first_name'] . ' ' . $employee['last_name'] . 
                                                   ($employee['middle_name'] ? ' ' . $employee['middle_name'] : '') . 
                                                   ($employee['ext_name'] ? ' ' . $employee['ext_name'] : '');
                                    ?>
                                        <option value="<?= $employee['emp_id'] ?>"><?= htmlspecialchars($fullName) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Date *</label>
                                <input type="date" class="form-control form-control-modern" name="date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Leave Time *</label>
                                <input type="time" class="form-control form-control-modern" name="leave_time" id="leaveTimeInput" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Type *</label>
                                <select class="form-control form-control-modern" name="purpose_type" id="purposeTypeSelect" required>
                                    <option value="">Select Purpose</option>
                                    <option value="personal">Personal</option>
                                    <option value="official">Official Business</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Details *</label>
                                <textarea class="form-control form-control-modern" name="purpose_details" rows="3" placeholder="Enter detailed purpose..." required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="no_return" id="noReturnCheckbox">
                                    <label class="form-check-label font-weight-semibold" for="noReturnCheckbox">
                                        No Return Today
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="expectedReturnGroup">
                                <label class="font-weight-semibold">Expected Return Time *</label>
                                <input type="time" class="form-control form-control-modern" name="expected_return" id="expectedReturnInput">
                                <div id="timeValidationMessage" class="time-validation-message"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modern btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_slip" class="btn btn-modern btn-primary">
                        <i class="fas fa-save mr-2"></i>Create Slip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for viewing slip details -->
<div class="modal fade" id="slipDetailsModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modern-card">
            <div class="modern-header">
                <h4 class="modal-title mb-0 font-weight-bold">
                    <i class="fas fa-file-alt mr-2"></i>Personal Locator Slip Details
                </h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="slipDetailsContent">
                <!-- Details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-modern btn-light" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing slip -->
<div class="modal fade" id="editSlipModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modern-card">
            <div class="modern-header">
                <h4 class="modal-title mb-0 font-weight-bold">
                    <i class="fas fa-edit mr-2"></i>Edit Personal Locator Slip
                </h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="editSlipContent">
                <!-- Edit form will be loaded here via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Close modal if flag is set
    <?php if (isset($_SESSION['close_modal']) && $_SESSION['close_modal']): ?>
        $('#createSlipModal').modal('hide');
        <?php unset($_SESSION['close_modal']); ?>
    <?php endif; ?>

    // Select all functionality
    $('#selectAllHeader').change(function() {
        $('.slip-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    $('#selectAll').change(function() {
        $('.slip-checkbox').prop('checked', $(this).prop('checked'));
        $('#selectAllHeader').prop('checked', $(this).prop('checked'));
    });

    // Individual checkbox change
    $('.slip-checkbox').change(function() {
        if (!$('.slip-checkbox:checked').length) {
            $('#selectAllHeader').prop('checked', false);
            $('#selectAll').prop('checked', false);
        } else if ($('.slip-checkbox:checked').length === $('.slip-checkbox').length) {
            $('#selectAllHeader').prop('checked', true);
            $('#selectAll').prop('checked', true);
        }
    });

    // No return checkbox functionality
    $('#noReturnCheckbox').change(function() {
        if ($(this).is(':checked')) {
            $('#expectedReturnGroup').hide();
            $('#expectedReturnInput').prop('required', false);
        } else {
            $('#expectedReturnGroup').show();
            $('#expectedReturnInput').prop('required', true);
        }
    });

    // Initialize the expected return field
    if ($('#noReturnCheckbox').is(':checked')) {
        $('#expectedReturnGroup').hide();
        $('#expectedReturnInput').prop('required', false);
    }

    // Individual approve button with SweetAlert
    $('.approve-btn').click(function() {
        const slipId = $(this).data('slip-id');
        Swal.fire({
            title: 'Approve Slip?',
            text: "Are you sure you want to approve this personal locator slip?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, approve it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                submitSlipAction(slipId, 'approve_slip');
            }
        });
    });

    // Individual reject button with SweetAlert
    $('.reject-btn').click(function() {
        const slipId = $(this).data('slip-id');
        Swal.fire({
            title: 'Reject Slip?',
            text: "Are you sure you want to reject this personal locator slip?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reject it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                submitSlipAction(slipId, 'reject_slip');
            }
        });
    });

    $(document).on('click', '.edit-btn', function() {
        const slipId = $(this).data('slip-id');
        const status = $(this).closest('tr').find('.status-badge').text().toLowerCase().trim();
        
        console.log('Edit clicked - Slip ID:', slipId, 'Status:', status);
        
        // Additional safety check
        if (status !== 'pending') {
            Swal.fire({
                title: 'Cannot Edit',
                text: 'Only pending slips can be edited.',
                icon: 'warning',
                confirmButtonColor: '#4361ee'
            });
            return;
        }
        
        editSlip(slipId);
    });

    // Delete button with SweetAlert - Now works for ALL statuses
    $('.delete-btn').click(function() {
        const slipId = $(this).data('slip-id');
        const slipStatus = $(this).data('slip-status');
        
        let title = 'Delete Slip?';
        let text = "Are you sure you want to delete this personal locator slip? This action cannot be undone.";
        
        if (slipStatus === 'approved' || slipStatus === 'rejected') {
            title = `Delete ${slipStatus.charAt(0).toUpperCase() + slipStatus.slice(1)} Slip?`;
            text = `Are you sure you want to delete this ${slipStatus} slip? This action cannot be undone.`;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                submitSlipAction(slipId, 'delete_slip');
            }
        });
    });

    // Bulk approve with SweetAlert
    $('#bulkApproveBtn').click(function() {
        const selectedSlips = $('.slip-checkbox:checked');
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to approve.',
                icon: 'warning',
                confirmButtonColor: '#4361ee'
            });
            return;
        }

        Swal.fire({
            title: 'Approve Selected Slips?',
            text: `Are you sure you want to approve ${selectedSlips.length} slip(s)?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, approve ${selectedSlips.length} slip(s)!`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create hidden input for bulk action
                const bulkApproveInput = document.createElement('input');
                bulkApproveInput.type = 'hidden';
                bulkApproveInput.name = 'bulk_approve';
                bulkApproveInput.value = '1';
                
                $('#bulkForm').append(bulkApproveInput);
                $('#bulkForm').submit();
            }
        });
    });

    // Bulk reject with SweetAlert
    $('#bulkRejectBtn').click(function() {
        const selectedSlips = $('.slip-checkbox:checked');
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to reject.',
                icon: 'warning',
                confirmButtonColor: '#4361ee'
            });
            return;
        }

        Swal.fire({
            title: 'Reject Selected Slips?',
            text: `Are you sure you want to reject ${selectedSlips.length} slip(s)?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, reject ${selectedSlips.length} slip(s)!`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create hidden input for bulk action
                const bulkRejectInput = document.createElement('input');
                bulkRejectInput.type = 'hidden';
                bulkRejectInput.name = 'bulk_reject';
                bulkRejectInput.value = '1';
                
                $('#bulkForm').append(bulkRejectInput);
                $('#bulkForm').submit();
            }
        });
    });

    // Bulk delete with SweetAlert
    $('#bulkDeleteBtn').click(function() {
        const selectedSlips = $('.slip-checkbox:checked');
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to delete.',
                icon: 'warning',
                confirmButtonColor: '#4361ee'
            });
            return;
        }

        Swal.fire({
            title: 'Delete Selected Slips?',
            text: `Are you sure you want to delete ${selectedSlips.length} slip(s)? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, delete ${selectedSlips.length} slip(s)!`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create hidden input for bulk action
                const bulkDeleteInput = document.createElement('input');
                bulkDeleteInput.type = 'hidden';
                bulkDeleteInput.name = 'bulk_delete';
                bulkDeleteInput.value = '1';
                
                $('#bulkForm').append(bulkDeleteInput);
                $('#bulkForm').submit();
            }
        });
    });

    // Delete All with SweetAlert
    $('#deleteAllBtn').click(function() {
        const totalSlips = <?= $slips_result->num_rows ?>;
        const statusFilter = '<?= $status_filter ?>';
        
        if (totalSlips === 0) {
            Swal.fire({
                title: 'No Slips Found',
                text: 'There are no slips to delete with the current filters.',
                icon: 'info',
                confirmButtonColor: '#4361ee'
            });
            return;
        }

        let title = 'Delete All Slips?';
        let text = `Are you sure you want to delete all ${totalSlips} slip(s) matching the current filters? This action cannot be undone.`;
        
        if (statusFilter !== 'all') {
            title = `Delete All ${statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1)} Slips?`;
            text = `Are you sure you want to delete all ${totalSlips} ${statusFilter} slip(s)? This action cannot be undone.`;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, delete all ${totalSlips} slip(s)!`,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Create hidden input for delete all action
                const deleteAllInput = document.createElement('input');
                deleteAllInput.type = 'hidden';
                deleteAllInput.name = 'delete_all';
                deleteAllInput.value = '1';
                
                $('#deleteAllForm').append(deleteAllInput);
                $('#deleteAllForm').submit();
            }
        });
    });

    // Excel generation with loader
    $('.excel-btn').click(function() {
        const slipId = $(this).data('slip-id');
        generateExcelSlip(slipId);
    });
});

function submitSlipAction(slipId, action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const slipIdInput = document.createElement('input');
    slipIdInput.type = 'hidden';
    slipIdInput.name = 'slip_id';
    slipIdInput.value = slipId;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = action;
    actionInput.value = '1';
    
    form.appendChild(slipIdInput);
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
}

function viewSlipDetails(slipId) {
    $.ajax({
        url: 'get_slip_details.php',
        type: 'GET',
        data: { id: slipId },
        success: function(response) {
            $('#slipDetailsContent').html(response);
            $('#slipDetailsModal').modal('show');
        },
        error: function() {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to load slip details.',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
        }
    });
}

function editSlip(slipId) {
    console.log('Editing slip:', slipId);
    
    // Show loading in modal
    $('#editSlipContent').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading edit form...</p>
        </div>
    `);
    $('#editSlipModal').modal('show');
    
    $.ajax({
        url: 'get_edit_slip_form.php',
        type: 'GET',
        data: { id: slipId },
        success: function(response) {
            $('#editSlipContent').html(response);
            console.log('Edit form loaded successfully');
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $('#editSlipContent').html(`
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle text-danger fa-2x mb-3"></i>
                    <h5>Error Loading Form</h5>
                    <p>Failed to load edit form. Please try again.</p>
                    <button type="button" class="btn btn-modern btn-light" data-dismiss="modal">Close</button>
                </div>
            `);
            
            Swal.fire({
                title: 'Error!',
                text: 'Failed to load edit form. Please try again.',
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
    
    // Create a hidden iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);
    
    // Set the iframe source to trigger download
    iframe.src = `generate_excel_slip.php?id=${slipId}`;
    
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

// Handle SweetAlert messages for success and error
<?php if (isset($success_message) && !empty($success_message)): ?>
$(document).ready(function() {
    Swal.fire({
        title: 'Success!',
        text: '<?= addslashes($success_message) ?>',
        icon: 'success',
        confirmButtonColor: '#4361ee',
        timer: 3000,
        showConfirmButton: false
    });
});
<?php endif; ?>

<?php if (isset($error_message) && !empty($error_message)): ?>
$(document).ready(function() {
    Swal.fire({
        title: 'Error!',
        text: '<?= addslashes($error_message) ?>',
        icon: 'error',
        confirmButtonColor: '#4361ee'
    });
});
<?php endif; ?>

    // Time validation based on purpose type
    function validateTimeConstraints() {
        const purposeType = $('#purposeTypeSelect').val();
        const leaveTime = $('#leaveTimeInput').val();
        const expectedReturn = $('#expectedReturnInput').val();
        const noReturn = $('#noReturnCheckbox').is(':checked');
        
        if (noReturn || !leaveTime || !expectedReturn || !purposeType) {
            $('#timeValidationMessage').hide();
            return true;
        }
        
        const leaveTimestamp = new Date('1970-01-01T' + leaveTime + 'Z').getTime();
        const returnTimestamp = new Date('1970-01-01T' + expectedReturn + 'Z').getTime();
        const timeDifference = (returnTimestamp - leaveTimestamp) / (1000 * 60 * 60); // Convert to hours
        
        const messageElement = $('#timeValidationMessage');
        
        if (purposeType === 'personal') {
            if (timeDifference > 1) {
                messageElement.removeClass('valid').addClass('invalid');
                messageElement.text('For personal matters, maximum allowed time is 1 hour.');
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
    $('#purposeTypeSelect, #leaveTimeInput, #expectedReturnInput, #noReturnCheckbox').on('change input', function() {
        validateTimeConstraints();
    });

    // Enhanced form validation for create slip
    $('#createSlipForm').on('submit', function(e) {
        // Basic client-side validation
        const employeeId = $('select[name="employee_id"]').val();
        const purposeType = $('select[name="purpose_type"]').val();
        const leaveTime = $('input[name="leave_time"]').val();
        const purposeDetails = $('textarea[name="purpose_details"]').val();
        const noReturn = $('#noReturnCheckbox').is(':checked');
        const expectedReturn = $('input[name="expected_return"]').val();
        
        if (!employeeId || !purposeType || !leaveTime || !purposeDetails) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please fill in all required fields.',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
            e.preventDefault();
            return false;
        }
        
        if (!noReturn && !expectedReturn) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please provide expected return time or check "No Return Today".',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
            e.preventDefault();
            return false;
        }
        
        // Time constraint validation
        if (!noReturn && expectedReturn) {
            const isValidTime = validateTimeConstraints();
            if (!isValidTime) {
                Swal.fire({
                    title: 'Time Validation Error!',
                    text: $('#timeValidationMessage').text(),
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                e.preventDefault();
                return false;
            }
        }
        
        // Show loading message
        Swal.fire({
            title: 'Creating Slip',
            text: 'Please wait...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });

    // Enhanced form validation for edit slip
    $(document).on('submit', '#editSlipForm', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const noReturn = $('#editNoReturnCheckbox').is(':checked');
        const expectedReturn = $('#editExpectedReturnInput').val();
        
        if (!noReturn && !expectedReturn) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please provide expected return time or check "No Return Today".',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
            return false;
        }
        
        // Additional time validation can be added here for edit form
        
        Swal.fire({
            title: 'Updating Slip',
            text: 'Please wait...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            Swal.close();
            
            if (data.includes('successfully') || <?= isset($success_message) ? 'true' : 'false' ?>) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Slip updated successfully!',
                    icon: 'success',
                    confirmButtonColor: '#4361ee'
                }).then((result) => {
                    $('#editSlipModal').modal('hide');
                    location.reload();
                });
            } else {
                throw new Error('Update failed');
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to update slip. Please try again.',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
        });
    });
    
    $(document).ready(function() {
        $('tr').each(function() {
            const statusCell = $(this).find('.status-badge');
            if (statusCell.length && statusCell.text().toLowerCase() === 'rejected') {
                $(this).find('.excel-btn').hide();
            }
        });
    });
</script>
</body>
</html>