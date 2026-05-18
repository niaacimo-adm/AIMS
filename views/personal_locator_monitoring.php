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
                
                // Check if employee already has a personal matter slip this week
                $frequency_query = "SELECT COUNT(*) as slip_count 
                                FROM personal_locator_slips 
                                WHERE employee_id = ? 
                                AND purpose_type = 'personal' 
                                AND date BETWEEN ? AND ? 
                                AND status != 'rejected'";
                
                $freq_stmt = $db->prepare($frequency_query);
                $freq_stmt->bind_param("iss", $employee_id, $week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
                $freq_stmt->execute();
                $freq_result = $freq_stmt->get_result();
                $slip_count = $freq_result->fetch_assoc()['slip_count'];
                
                if ($slip_count > 0) {
                    $_SESSION['error_message'] = "This employee can only submit one personal matter locator slip per week. They already have a personal matter slip for this week (".$week_start->format('M j')." - ".$week_end->format('M j, Y').").";
                    error_log("VALIDATION FAILED: Employee already has personal matter slip this week");
                    // Don't proceed with insertion - use return instead of continue
                    $isValid = false;
                }
            }

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
                    // Proceed with insertion
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
            --primary: #1a5c38;
            --primary-mid: #2a9863;
            --primary-bright: #24e78f;
            --secondary: #4a7c62;
            --success: #24e78f;
            --danger: #c92a2a;
            --warning: #d4af37;
            --info: #2a9863;
            --light: #f0faf5;
            --dark: #0f2d1e;
            --card-shadow: 0 4px 24px rgba(26,92,56,.12);
            --hover-shadow: 0 10px 28px rgba(26,92,56,.18);
        }

        /* dark mode */
        body.dark-mode {
            --primary: #24e78f;
            --primary-mid: #2a9863;
            --light: #0b1f17;
            --dark: #e0f7ec;
            background-color: #0b1f17 !important;
        }
        body.dark-mode .content-wrapper { background-color: #0b1f17 !important; }
        body.dark-mode .modern-card, body.dark-mode .card { background: #102f22 !important; border-color: #1c4d38 !important; color: #e0f7ec !important; }
        body.dark-mode .card-header { background: #0d2318 !important; color: #e0f7ec !important; border-color: #1c4d38 !important; }
        body.dark-mode .table { color: #e0f7ec !important; }
        body.dark-mode .table td, body.dark-mode .table th { border-color: #1c4d38 !important; color: #e0f7ec !important; }
        body.dark-mode .form-control { background: #0d2318 !important; color: #e0f7ec !important; border-color: #1c4d38 !important; }
        body.dark-mode .modal-content { background: #102f22 !important; }
        body.dark-mode .modal-header { background: linear-gradient(135deg,#1c4d38,#0b1f17) !important; }
        body.dark-mode .modal-body, body.dark-mode .modal-footer { background: #102f22 !important; color: #e0f7ec !important; border-color: #1c4d38 !important; }
        body.dark-mode .filter-bar { background: #102f22 !important; border-color: #1c4d38 !important; }
        body.dark-mode .locator-card { background: #102f22 !important; border-color: #1c4d38 !important; }
        body.dark-mode .locator-header { background: #0d2318 !important; color: #e0f7ec !important; }

        .content { padding:0 20px; margin-top:-50px; position:relative; z-index:3; }
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
            background: linear-gradient(135deg, var(--primary), #2a9863);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px 25px;
            border: none;
        }
        
        .filter-section {
            background: dark;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid #d1f0e0;
        }
        
        .bulk-actions {
            background: linear-gradient(135deg, #f0faf5, #d1f0e0);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #c8e6d4;
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
            background: linear-gradient(135deg, #f0faf5, #d1f0e0);
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
            border-bottom: 1px solid #d1f0e0;
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .modern-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .modern-table tbody tr:hover {
            background: #f0faf5;
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
            background: linear-gradient(135deg, var(--primary), #2a9863);
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
            border: 2px solid #c8e6d4;
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
            background: linear-gradient(135deg, var(--primary), #2a9863);
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
            border: 2px solid #d1f0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .employee-picture-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #2a9863);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
            border: 2px solid #d1f0e0;
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
            border: 1px solid #d1f0e0;
        }

        .collapse-toggle {
            background: linear-gradient(135deg, #f0faf5, #d1f0e0);
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
            background: linear-gradient(135deg, #d1f0e0, #c8e6d4);
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
            color: #24e78f;
        }
        
        .time-validation-message.invalid {
            color: #c92a2a;
        }

        /* Delete All Button */
        .delete-all-section {
            background: linear-gradient(135deg, #fff5f5, #fed7d7);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #feb2b2;
        }

        /* NEW STYLES FOR MINIMALIST BULK ACTIONS */
        .bulk-actions-minimal {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .bulk-btn-minimal {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            color: white;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .bulk-btn-minimal:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .bulk-btn-minimal.approve {
            background: linear-gradient(135deg, #24e78f, #2a9863);
        }

        .bulk-btn-minimal.reject {
            background: linear-gradient(135deg, #c92a2a, #e83e8c);
        }

        .bulk-btn-minimal.delete {
            background: linear-gradient(135deg, #4a7c62, #495057);
        }

        .bulk-btn-minimal.delete-all {
            background: linear-gradient(135deg, #c92a2a, #c82333);
        }

        .bulk-actions-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-right: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-actions-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 6px;
            margin-right: 8px;
        }

        .select-all-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
         /* Select2 styling */
        .select2-container .select2-selection--single {
            height: 46px !important;
            border: 2px solid #d1f0e0 !important;
            border-radius: 10px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            padding-left: 15px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: 44px !important;
        }

        .select2-container--bootstrap4 .select2-dropdown {
            border: 2px solid #d1f0e0 !important;
            border-radius: 10px !important;
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

body.dark-mode .filter-bar { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .locator-card { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .locator-header { background: var(--table-stripe) !important; color: var(--text-primary) !important; }
body.dark-mode .badge-status { color: #fff !important; }
body.dark-mode .btn-outline-primary { color: #7aabdf !important; border-color: #7aabdf !important; }
body.dark-mode .btn-outline-primary:hover { background: #7aabdf !important; color: #000 !important; }


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
<body class="hold-transition sidebar-mini layout-fixed">
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
                    <div class="pg-hero-title"><i class="fas fa-map-marker-alt"></i>Personal Locator Slip Monitoring</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Manage and monitor employee locator slip requests</p>
                </div>
            </div>
        </div>

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

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $success_message ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-modern alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= $error_message ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Main Card -->
                <div class="modern-card">
                    <!-- Card Header -->
                    <div class="modern-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0 font-weight-bold">
                                <i class="fas fa-list-alt mr-2"></i>
                                <?= ucfirst($status_filter) ?> Requests
                            </h3>
                            <div class="header-actions-container">
                                <div class="select-all-container">
                                    <input type="checkbox" id="select-all" class="checkbox-modern">
                                    <label class="select-all-label mb-0">Select All</label>
                                </div>
                                <div class="bulk-actions-minimal">
                                    <span class="bulk-actions-label">Bulk Actions:</span>
                                    <button type="submit" name="bulk_approve" class="bulk-btn-minimal approve" title="Approve Selected">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="bulk_reject" class="bulk-btn-minimal reject" title="Reject Selected">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button type="submit" name="bulk_delete" class="bulk-btn-minimal delete" title="Delete Selected">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="submit" name="delete_all" class="bulk-btn-minimal delete-all" title="Delete All">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                <span class="badge-counter"><?= $slips_result->num_rows ?> requests</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <form method="POST" action="" id="bulkForm">
                            <div class="table-responsive">
                                <table class="table modern-table">
                                    <thead>
                                        <tr>
                                            <th width="40">#</th>
                                            <th>Employee</th>
                                            <th>Date</th>
                                            <th>Leave Time</th>
                                            <th>Expected Return</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($slips_result->num_rows > 0): ?>
                                            <?php $counter = 1; ?>
                                            <?php while ($slip = $slips_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="slip_ids[]" value="<?= $slip['id'] ?>" class="slip-checkbox checkbox-modern">
                                                    </td>
                                                    <td>
                                                        <div class="employee-with-picture">
                                                            <?php if (!empty($slip['picture'])): ?>
                                                                <img src="../dist/img/employees/<?= htmlspecialchars($slip['picture']) ?>" 
                                                                     alt="<?= htmlspecialchars($slip['first_name']) ?>" 
                                                                     class="employee-picture">
                                                            <?php else: ?>
                                                                <div class="employee-picture-placeholder">
                                                                    <?= substr($slip['first_name'], 0, 1) . substr($slip['last_name'], 0, 1) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="employee-details">
                                                                <div class="employee-name">
                                                                    <?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>
                                                                    <?= $slip['ext_name'] ? htmlspecialchars($slip['ext_name']) : '' ?>
                                                                </div>
                                                                <div class="employee-position">
                                                                    <?= htmlspecialchars($slip['position_name'] ?? 'N/A') ?> • 
                                                                    <?= htmlspecialchars($slip['section_name'] ?? 'N/A') ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($slip['date'])) ?></td>
                                                    <td><?= date('g:i A', strtotime($slip['leave_time'])) ?></td>
                                                    <td>
                                                        <?php if ($slip['no_return']): ?>
                                                            <span class="badge badge-warning status-badge">No Return</span>
                                                        <?php else: ?>
                                                            <?= $slip['expected_return'] ? date('g:i A', strtotime($slip['expected_return'])) : 'N/A' ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong class="text-capitalize"><?= $slip['purpose_type'] ?></strong>
                                                        <?php if ($slip['purpose_details']): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($slip['purpose_details']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch ($slip['status']) {
                                                            case 'approved':
                                                                $status_class = 'badge-success';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'badge-danger';
                                                                break;
                                                            default:
                                                                $status_class = 'badge-warning';
                                                        }
                                                        ?>
                                                        <span class="badge status-badge <?= $status_class ?>">
                                                            <?= ucfirst($slip['status']) ?>
                                                        </span>
                                                        <?php if ($slip['approved_by']): ?>
                                                            <br><small class="text-muted">by <?= htmlspecialchars($slip['approver_first'] . ' ' . $slip['approver_last']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <?php if ($slip['status'] == 'pending'): ?>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                                                                    <button type="submit" name="approve_slip" class="btn btn-success btn-sm action-btn" title="Approve">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                                                                    <button type="submit" name="reject_slip" class="btn btn-danger btn-sm action-btn" title="Reject">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-info btn-sm action-btn view-slip" 
                                                                    data-toggle="modal" data-target="#viewSlipModal" 
                                                                    data-slip='<?= json_encode($slip) ?>' title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <a href="generate_excel_slip.php?id=<?= $slip['id'] ?>" class="btn btn-success btn-sm action-btn" title="Export Excel">
                                                                <i class="fas fa-file-excel"></i>
                                                            </a>
                                                            <?php if ($slip['status'] == 'pending'): ?>
                                                                <button type="button" class="btn btn-warning btn-sm action-btn edit-slip" 
                                                                        data-toggle="modal" data-target="#editSlipModal" 
                                                                        data-slip='<?= json_encode($slip) ?>' title="Edit">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                                                                <button type="submit" name="delete_slip" class="btn btn-danger btn-sm action-btn" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php $counter++; ?>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5">
                                                    <div class="text-muted">
                                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                                        <h5>No personal locator slips found</h5>
                                                        <p>No requests match your current filters.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

</div>
<!-- ./wrapper -->

<!-- Create Slip Modal -->
<div class="modal fade" id="createSlipModal" tabindex="-1" role="dialog" aria-labelledby="createSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header modern-header">
                <h5 class="modal-title font-weight-bold" id="createSlipModalLabel">
                    <i class="fas fa-plus-circle mr-2"></i>Create Manual Personal Locator Slip
                </h5>
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
                                <select class="form-control form-control-modern select2" multiple="multiple" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php 
                                    // Reset employees result pointer
                                    $employees_result->data_seek(0);
                                    while ($employee = $employees_result->fetch_assoc()): ?>
                                        <option value="<?= $employee['emp_id'] ?>">
                                            <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                            <?= $employee['ext_name'] ? htmlspecialchars($employee['ext_name']) : '' ?>
                                            (<?= $employee['emp_id'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Date *</label>
                                <input type="date" class="form-control form-control-modern" name="date" required value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Leave Time *</label>
                                <input type="time" class="form-control form-control-modern" name="leave_time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Type *</label>
                                <select class="form-control form-control-modern" name="purpose_type" required>
                                    <option value="">Select Purpose</option>
                                    <option value="personal">Personal Matter</option>
                                    <option value="official">Official Business</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Details *</label>
                                <textarea class="form-control form-control-modern" name="purpose_details" rows="3" placeholder="Enter specific details about the purpose..." required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="no_return" name="no_return">
                                    <label class="form-check-label font-weight-semibold" for="no_return">No Return Expected</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Expected Return Time</label>
                                <input type="time" class="form-control form-control-modern" name="expected_return" id="expected_return">
                                <div class="time-validation-message" id="time_validation"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-modern" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_slip" class="btn btn-primary btn-modern">
                        <i class="fas fa-save mr-2"></i>Create Slip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Slip Modal -->
<div class="modal fade" id="viewSlipModal" tabindex="-1" role="dialog" aria-labelledby="viewSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header modern-header">
                <h5 class="modal-title font-weight-bold" id="viewSlipModalLabel">
                    <i class="fas fa-eye mr-2"></i>Personal Locator Slip Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="viewSlipModalBody">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-modern" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Slip Modal -->
<div class="modal fade" id="editSlipModal" tabindex="-1" role="dialog" aria-labelledby="editSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header modern-header">
                <h5 class="modal-title font-weight-bold" id="editSlipModalLabel">
                    <i class="fas fa-edit mr-2"></i>Edit Personal Locator Slip
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="" id="editSlipForm">
                <input type="hidden" name="slip_id" id="edit_slip_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Date *</label>
                                <input type="date" class="form-control form-control-modern" name="date" id="edit_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Leave Time *</label>
                                <input type="time" class="form-control form-control-modern" name="leave_time" id="edit_leave_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Type *</label>
                                <select class="form-control form-control-modern" name="purpose_type" id="edit_purpose_type" required>
                                    <option value="personal">Personal Matter</option>
                                    <option value="official">Official Business</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Purpose Details *</label>
                                <textarea class="form-control form-control-modern" name="purpose_details" id="edit_purpose_details" rows="2" required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_no_return" name="no_return">
                                    <label class="form-check-label font-weight-semibold" for="edit_no_return">No Return Expected</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-semibold">Expected Return Time</label>
                                <input type="time" class="form-control form-control-modern" name="expected_return" id="edit_expected_return">
                                <div class="time-validation-message" id="edit_time_validation"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-modern" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_slip" class="btn btn-primary btn-modern">
                        <i class="fas fa-save mr-2"></i>Update Slip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<?php include '../includes/footer.php'; ?>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Handle no return checkbox
    $('#no_return').change(function() {
        if ($(this).is(':checked')) {
            $('#expected_return').prop('disabled', true).val('');
        } else {
            $('#expected_return').prop('disabled', false);
        }
    });

    // Handle edit no return checkbox
    $('#edit_no_return').change(function() {
        if ($(this).is(':checked')) {
            $('#edit_expected_return').prop('disabled', true).val('');
        } else {
            $('#edit_expected_return').prop('disabled', false);
        }
    });

    // Time validation for create form
    function validateTime() {
        const leaveTime = $('input[name="leave_time"]').val();
        const returnTime = $('input[name="expected_return"]').val();
        const purposeType = $('select[name="purpose_type"]').val();
        const noReturn = $('#no_return').is(':checked');
        
        if (noReturn || !leaveTime || !returnTime) {
            $('#time_validation').hide();
            return;
        }
        
        const leave = new Date(`2000-01-01T${leaveTime}`);
        const return_ = new Date(`2000-01-01T${returnTime}`);
        const diffHours = (return_ - leave) / (1000 * 60 * 60);
        
        let isValid = true;
        let message = '';
        
        if (purposeType === 'personal') {
            if (diffHours > 1) {
                isValid = false;
                message = 'Personal matters are limited to 1 hour maximum.';
            } else {
                message = 'Time within 1 hour limit ✓';
            }
        } else if (purposeType === 'official') {
            if (diffHours > 24) {
                isValid = false;
                message = 'Official business must be within the same day.';
            } else {
                message = 'Time within same day limit ✓';
            }
        }
        
        const validationDiv = $('#time_validation');
        validationDiv.text(message);
        validationDiv.removeClass('valid invalid');
        validationDiv.addClass(isValid ? 'valid' : 'invalid');
        validationDiv.show();
    }
    
    $('input[name="leave_time"], input[name="expected_return"], select[name="purpose_type"]').on('change input', validateTime);

    // Time validation for edit form
    function validateEditTime() {
        const leaveTime = $('#edit_leave_time').val();
        const returnTime = $('#edit_expected_return').val();
        const purposeType = $('#edit_purpose_type').val();
        const noReturn = $('#edit_no_return').is(':checked');
        
        if (noReturn || !leaveTime || !returnTime) {
            $('#edit_time_validation').hide();
            return;
        }
        
        const leave = new Date(`2000-01-01T${leaveTime}`);
        const return_ = new Date(`2000-01-01T${returnTime}`);
        const diffHours = (return_ - leave) / (1000 * 60 * 60);
        
        let isValid = true;
        let message = '';
        
        if (purposeType === 'personal') {
            if (diffHours > 1) {
                isValid = false;
                message = 'Personal matters are limited to 1 hour maximum.';
            } else {
                message = 'Time within 1 hour limit ✓';
            }
        } else if (purposeType === 'official') {
            if (diffHours > 24) {
                isValid = false;
                message = 'Official business must be within the same day.';
            } else {
                message = 'Time within same day limit ✓';
            }
        }
        
        const validationDiv = $('#edit_time_validation');
        validationDiv.text(message);
        validationDiv.removeClass('valid invalid');
        validationDiv.addClass(isValid ? 'valid' : 'invalid');
        validationDiv.show();
    }
    
    $('#edit_leave_time, #edit_expected_return, #edit_purpose_type').on('change input', validateEditTime);

    // View slip modal
    $('.view-slip').click(function() {
        const slip = $(this).data('slip');
        const modalBody = $('#viewSlipModalBody');
        
        // Format dates and times
        const date = new Date(slip.date).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        const leaveTime = new Date(`2000-01-01T${slip.leave_time}`).toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
        const expectedReturn = slip.expected_return ? 
            new Date(`2000-01-01T${slip.expected_return}`).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            }) : 'No Return';
        
        const statusClass = slip.status === 'approved' ? 'badge-success' : 
                          slip.status === 'rejected' ? 'badge-danger' : 'badge-warning';
        
        modalBody.html(`
            <div class="row">
                <div class="col-md-4">
                    <div class="employee-picture-container text-center mb-3">
                        ${slip.picture ? 
                            `<img src="../dist/img/employees/${slip.picture}" alt="${slip.first_name}" class="employee-picture" style="width: 120px; height: 120px;">` :
                            `<div class="employee-picture-placeholder mx-auto" style="width: 120px; height: 120px; font-size: 2em;">
                                ${slip.first_name.charAt(0)}${slip.last_name.charAt(0)}
                            </div>`
                        }
                    </div>
                </div>
                <div class="col-md-8">
                    <h6 class="font-weight-bold text-primary">Employee Information</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td width="30%"><strong>Name:</strong></td>
                            <td>${slip.first_name} ${slip.last_name} ${slip.ext_name || ''}</td>
                        </tr>
                        <tr>
                            <td><strong>Position:</strong></td>
                            <td>${slip.position_name || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Section:</strong></td>
                            <td>${slip.section_name || 'N/A'}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <hr>
            
            <h6 class="font-weight-bold text-primary">Slip Details</h6>
            <table class="table table-borderless table-sm">
                <tr>
                    <td width="30%"><strong>Date:</strong></td>
                    <td>${date}</td>
                </tr>
                <tr>
                    <td><strong>Leave Time:</strong></td>
                    <td>${leaveTime}</td>
                </tr>
                <tr>
                    <td><strong>Expected Return:</strong></td>
                    <td>${expectedReturn}</td>
                </tr>
                <tr>
                    <td><strong>Purpose Type:</strong></td>
                    <td class="text-capitalize">${slip.purpose_type}</td>
                </tr>
                <tr>
                    <td><strong>Purpose Details:</strong></td>
                    <td>${slip.purpose_details || 'N/A'}</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span class="badge ${statusClass} status-badge">${slip.status.charAt(0).toUpperCase() + slip.status.slice(1)}</span></td>
                </tr>
                ${slip.approved_by ? `
                <tr>
                    <td><strong>Approved By:</strong></td>
                    <td>${slip.approver_first} ${slip.approver_last}</td>
                </tr>
                ` : ''}
                <tr>
                    <td><strong>Created At:</strong></td>
                    <td>${new Date(slip.created_at).toLocaleString()}</td>
                </tr>
            </table>
        `);
    });

    // Edit slip modal
    $('.edit-slip').click(function() {
        const slip = $(this).data('slip');
        
        $('#edit_slip_id').val(slip.id);
        $('#edit_date').val(slip.date);
        $('#edit_leave_time').val(slip.leave_time);
        $('#edit_purpose_type').val(slip.purpose_type);
        $('#edit_purpose_details').val(slip.purpose_details);
        
        if (slip.no_return) {
            $('#edit_no_return').prop('checked', true);
            $('#edit_expected_return').prop('disabled', true).val('');
        } else {
            $('#edit_no_return').prop('checked', false);
            $('#edit_expected_return').prop('disabled', false).val(slip.expected_return || '');
        }
        
        // Trigger validation
        validateEditTime();
    });

    // Select all functionality
    $('#select-all').change(function() {
        $('.slip-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Individual checkbox change
    $('.slip-checkbox').change(function() {
        if ($('.slip-checkbox:checked').length === $('.slip-checkbox').length) {
            $('#select-all').prop('checked', true);
        } else {
            $('#select-all').prop('checked', false);
        }
    });

    // Auto-close alerts after 5 seconds
    $('.alert').delay(5000).fadeOut(400);

    // Handle modal close flag from session
    <?php if (isset($_SESSION['close_modal']) && $_SESSION['close_modal']): ?>
        $('#createSlipModal').modal('hide');
        <?php unset($_SESSION['close_modal']); ?>
    <?php endif; ?>

    // Form submission handling
    $('#createSlipForm').submit(function(e) {
        const noReturn = $('#no_return').is(':checked');
        const expectedReturn = $('input[name="expected_return"]').val();
        const purposeType = $('select[name="purpose_type"]').val();
        
        if (!noReturn && !expectedReturn) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please either set an expected return time or check "No Return Expected".',
                confirmButtonColor: '#1a5c38'
            });
            return false;
        }
        
        // Additional time validation
        if (!noReturn && expectedReturn) {
            const leaveTime = $('input[name="leave_time"]').val();
            const leave = new Date(`2000-01-01T${leaveTime}`);
            const return_ = new Date(`2000-01-01T${expectedReturn}`);
            const diffHours = (return_ - leave) / (1000 * 60 * 60);
            
            if (purposeType === 'personal' && diffHours > 1) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Time Limit Exceeded',
                    text: 'For personal matters, the maximum allowed time is 1 hour.',
                    confirmButtonColor: '#1a5c38'
                });
                return false;
            }
            
            if (purposeType === 'official' && diffHours > 24) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Time Limit Exceeded',
                    text: 'For official business, the return time must be within the same day.',
                    confirmButtonColor: '#1a5c38'
                });
                return false;
            }
        }
    });

    // Edit form submission handling
    $('#editSlipForm').submit(function(e) {
        const noReturn = $('#edit_no_return').is(':checked');
        const expectedReturn = $('#edit_expected_return').val();
        const purposeType = $('#edit_purpose_type').val();
        
        if (!noReturn && !expectedReturn) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please either set an expected return time or check "No Return Expected".',
                confirmButtonColor: '#1a5c38'
            });
            return false;
        }
        
        // Additional time validation
        if (!noReturn && expectedReturn) {
            const leaveTime = $('#edit_leave_time').val();
            const leave = new Date(`2000-01-01T${leaveTime}`);
            const return_ = new Date(`2000-01-01T${expectedReturn}`);
            const diffHours = (return_ - leave) / (1000 * 60 * 60);
            
            if (purposeType === 'personal' && diffHours > 1) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Time Limit Exceeded',
                    text: 'For personal matters, the maximum allowed time is 1 hour.',
                    confirmButtonColor: '#1a5c38'
                });
                return false;
            }
            
            if (purposeType === 'official' && diffHours > 24) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Time Limit Exceeded',
                    text: 'For official business, the return time must be within the same day.',
                    confirmButtonColor: '#1a5c38'
                });
                return false;
            }
        }
    });
});
    // SweetAlert for success messages
    <?php if ($success_message): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?= addslashes($success_message) ?>',
            icon: 'success',
            confirmButtonColor: '#1a5c38',
            timer: 3000,
            showConfirmButton: false
        });
    <?php endif; ?>

    // SweetAlert for error messages
    <?php if ($error_message): ?>
        Swal.fire({
            title: 'Error!',
            text: '<?= addslashes($error_message) ?>',
            icon: 'error',
            confirmButtonColor: '#1a5c38'
        });
    <?php endif; ?>
    // SweetAlert for individual delete actions - MORE SPECIFIC
    $(document).on('click', 'button[name="delete_slip"]', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        
        Swal.fire({
            title: 'Delete Slip?',
            text: "Are you sure you want to delete this personal locator slip? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#4a7c62',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // SweetAlert for bulk approve
    $(document).on('click', 'button[name="bulk_approve"]', function(e) {
        e.preventDefault();
        const selectedSlips = $('.slip-checkbox:checked');
        
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to approve.',
                icon: 'warning',
                confirmButtonColor: '#1a5c38'
            });
            return;
        }

        Swal.fire({
            title: 'Approve Selected Slips?',
            text: `Are you sure you want to approve ${selectedSlips.length} slip(s)?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#24e78f',
            cancelButtonColor: '#4a7c62',
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

    // SweetAlert for bulk reject
    $(document).on('click', 'button[name="bulk_reject"]', function(e) {
        e.preventDefault();
        const selectedSlips = $('.slip-checkbox:checked');
        
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to reject.',
                icon: 'warning',
                confirmButtonColor: '#1a5c38'
            });
            return;
        }

        Swal.fire({
            title: 'Reject Selected Slips?',
            text: `Are you sure you want to reject ${selectedSlips.length} slip(s)?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#4a7c62',
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

    // SweetAlert for bulk delete
    $(document).on('click', 'button[name="bulk_delete"]', function(e) {
        e.preventDefault();
        const selectedSlips = $('.slip-checkbox:checked');
        
        if (selectedSlips.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one slip to delete.',
                icon: 'warning',
                confirmButtonColor: '#1a5c38'
            });
            return;
        }

        Swal.fire({
            title: 'Delete Selected Slips?',
            text: `Are you sure you want to delete ${selectedSlips.length} slip(s)? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#4a7c62',
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

    // SweetAlert for delete all
    $(document).on('click', 'button[name="delete_all"]', function(e) {
        e.preventDefault();
        const totalSlips = <?= $slips_result->num_rows ?>;
        const statusFilter = '<?= $status_filter ?>';
        
        if (totalSlips === 0) {
            Swal.fire({
                title: 'No Slips Found',
                text: 'There are no slips to delete with the current filters.',
                icon: 'info',
                confirmButtonColor: '#1a5c38'
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
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#4a7c62',
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
                
                $('#bulkForm').append(deleteAllInput);
                $('#bulkForm').submit();
            }
        });
    });

    // SweetAlert for individual approve/reject actions - FIXED VERSION
    $(document).on('click', 'button[name="approve_slip"]but, ton[name="reject_slip"]', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        const action = $(this).attr('name');
        const actionText = action === 'approve_slip' ? 'approve' : 'reject';
        
        Swal.fire({
            title: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Slip?`,
            text: `Are you sure you want to ${actionText} this personal locator slip?`,
            icon: action === 'approve_slip' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: action === 'approve_slip' ? '#24e78f' : '#c92a2a',
            cancelButtonColor: '#4a7c62',
            confirmButtonText: `Yes, ${actionText} it!`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a hidden input to ensure the action is submitted
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = action;
                actionInput.value = '1';
                form.append(actionInput);
                
                // Submit the form
                form.submit();
            }
        });
    });

    // Remove the old confirm dialogs since we're using SweetAlert now
    $(document).on('click', 'button[onclick*="confirm"]', function(e) {
        e.preventDefault();
        return false;
    });
     $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: 'Select Employee',
        allowClear: true,
        maximumSelectionLength: 1
    });
</script>
</body>
</html>