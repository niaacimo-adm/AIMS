<?php
session_start();
require_once '../config/database.php';

// Set JSON content type header
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['equipment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Equipment ID is required']);
    exit;
}

$equipment_id = intval($_GET['equipment_id']);

$query = "SELECT e.*, c.category_name, 
          emp.first_name, emp.last_name, emp.picture,
          creator.first_name as creator_first_name, creator.last_name as creator_last_name
          FROM ict_equipment e 
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id 
          LEFT JOIN employee emp ON e.assigned_to = emp.emp_id 
          LEFT JOIN employee creator ON e.created_by = creator.emp_id 
          WHERE e.equipment_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $equipment = $result->fetch_assoc();
    
    // Format creator name
    if ($equipment['creator_first_name']) {
        $equipment['creator_name'] = $equipment['creator_first_name'] . ' ' . $equipment['creator_last_name'];
    }
    
    echo json_encode(['success' => true, 'data' => $equipment]);
} else {
    echo json_encode(['success' => false, 'message' => 'Equipment not found']);
}
?>