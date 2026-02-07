<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['department_name'], $_POST['type'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $department_name = $_POST['department_name'];
    $type = $_POST['type'];
    
    if ($type === 'section') {
        // Get section head
        $query = "SELECT s.head_emp_id, e.first_name, e.middle_name, e.last_name, e.emp_id 
                  FROM section s 
                  LEFT JOIN employee e ON s.head_emp_id = e.emp_id 
                  WHERE s.section_name = ?";
    } else {
        // Get unit section head
        $query = "SELECT u.head_emp_id, e.first_name, e.middle_name, e.last_name, e.emp_id 
                  FROM unit_section u 
                  LEFT JOIN employee e ON u.head_emp_id = e.emp_id 
                  WHERE u.unit_name = ?";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $department_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $supervisor_name = $row['first_name'] . ' ' . 
                          ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . 
                          $row['last_name'];
        echo json_encode([
            'success' => true, 
            'supervisor' => $supervisor_name,
            'supervisor_id' => $row['head_emp_id'] // Add this
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No supervisor found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}