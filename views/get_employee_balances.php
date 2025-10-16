<?php
// get_employee_balances.php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

if ($_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

if ($_POST && isset($_POST['emp_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    $leaveFunctions = new LeaveFunctions();
    
    $emp_id = $_POST['emp_id'];
    $current_year = date('Y');
    
    $balances = $leaveFunctions->getEmployeeLeaveBalanceSummary($emp_id, $current_year);
    
    if (empty($balances)) {
        echo '<div class="alert alert-info">No balance records found for this employee.</div>';
    } else {
        echo '<table class="table table-sm table-bordered">';
        echo '<thead><tr><th>Leave Type</th><th>Total</th><th>Used</th><th>Balance</th></tr></thead>';
        echo '<tbody>';
        foreach ($balances as $balance) {
            echo '<tr>';
            echo '<td>' . $balance['leave_name'] . '</td>';
            echo '<td>' . $balance['total_credits'] . '</td>';
            echo '<td>' . $balance['used_credits'] . '</td>';
            echo '<td><span class="badge badge-' . ($balance['balance'] > 0 ? 'success' : 'danger') . '">' . $balance['balance'] . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
?>