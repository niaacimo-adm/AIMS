<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

if (isset($_POST['leave_type_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $leaveFunctions = new LeaveFunctions();
    $emp_id = $_SESSION['emp_id'] ?? null;
    
    if (!$emp_id) {
        echo '<span class="text-danger">Not logged in</span>';
        exit;
    }
    
    $leave_type_id = $_POST['leave_type_id'];
    $balance = $leaveFunctions->getLeaveBalance($emp_id, $leave_type_id);
    
    // Get leave type details
    $leave_type_query = "SELECT leave_name, max_days_per_year FROM leave_types WHERE leave_type_id = ?";
    $stmt = $db->prepare($leave_type_query);
    $stmt->bind_param("i", $leave_type_id);
    $stmt->execute();
    $leave_type = $stmt->get_result()->fetch_assoc();
    
    $max_days = $leave_type['max_days_per_year'] ?? 0;
    
    if ($balance >= 0) {
        echo '<span class="badge badge-success">' . $balance . ' days available</span>';
        if ($max_days > 0) {
            echo '<br><small class="text-muted">Max: ' . $max_days . ' days per year</small>';
        }
    } else {
        echo '<span class="badge badge-danger">Insufficient balance</span>';
    }
}
?>