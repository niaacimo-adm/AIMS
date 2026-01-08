<?php
// includes/get_employee_section.php
require_once '../config/database.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Check if it's an AJAX request and get the emp_id
    $emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0;
    
    // For debugging, also check GET if needed
    if ($emp_id <= 0 && isset($_GET['emp_id'])) {
        $emp_id = intval($_GET['emp_id']);
    }
    
    if ($emp_id <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid employee ID',
            'received_id' => $_POST['emp_id'] ?? 'empty'
        ]);
        exit;
    }
    
    // Debug: Log the received emp_id
    error_log("get_employee_section.php - Received emp_id: " . $emp_id . ", POST data: " . print_r($_POST, true));
    
// Update the SQL query section:
$query = "SELECT 
            e.emp_id,
            e.section_id,
            e.unit_section_id as unit_id,
            s.section_name,
            s.section_code,
            u.unit_name,
            u.unit_code,
            mos.id as is_manager_staff
          FROM employee e
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN unit_section u ON e.unit_section_id = u.unit_id 
          LEFT JOIN managers_office_staff mos ON e.emp_id = mos.emp_id
          WHERE e.emp_id = ?";
              
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $db->error);
    }
    
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    
    if ($stmt->error) {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = [
            'success' => true,
            'emp_id' => $row['emp_id'],
            'section_id' => $row['section_id'] ?? 0,
            'unit_id' => $row['unit_id'] ?? 0,
            'section_name' => $row['section_name'] ?? '',
            'section_code' => $row['section_code'] ?? '',
            'unit_name' => $row['unit_name'] ?? '',
            'unit_code' => $row['unit_code'] ?? '',
            'is_manager_office_staff' => !empty($row['is_manager_staff'])
        ];
        
        // Debug log
        error_log("Employee found: " . print_r($response, true));
        
        echo json_encode($response);
        
    } else {
        $response = [
            'success' => false, 
            'message' => 'Employee not found in database',
            'debug_emp_id' => $emp_id
        ];
        
        // Debug: Try to see if employee exists at all
        $check_query = "SELECT emp_id, first_name, last_name FROM employee WHERE emp_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("i", $emp_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $response['message'] = 'Employee ID does not exist in employee table';
            error_log("Employee ID $emp_id does not exist in employee table");
        } else {
            $employee = $check_result->fetch_assoc();
            $response['employee_name'] = $employee['last_name'] . ', ' . $employee['first_name'];
            $response['message'] = 'Employee exists but has no section/unit assignment';
            error_log("Employee found but no section/unit: " . print_r($employee, true));
        }
        
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    error_log("Exception in get_employee_section.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
exit;