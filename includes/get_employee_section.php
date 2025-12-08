<?php
// includes/get_employee_section.php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$emp_id = $_POST['emp_id'] ?? 0;

if ($emp_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit;
}

// Check if employee exists in manager's office staff
$query = "SELECT 
            e.emp_id,
            e.section_id,
            e.unit_section_id,
            mos.id as is_manager_staff
          FROM employee e
          LEFT JOIN managers_office_staff mos ON e.emp_id = mos.emp_id
          WHERE e.emp_id = ?";
          
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $response = [
        'success' => true,
        'emp_id' => $row['emp_id'],
        'section_id' => $row['section_id'],
        'unit_section_id' => $row['unit_section_id'],
        'is_manager_office_staff' => !empty($row['is_manager_staff'])
    ];
} else {
    $response = ['success' => false, 'message' => 'Employee not found'];
}

echo json_encode($response);
exit;