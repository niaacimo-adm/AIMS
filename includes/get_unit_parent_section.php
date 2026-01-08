<?php
// includes/get_unit_parent_section.php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$unit_id = $_POST['unit_id'] ?? 0;

if ($unit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit ID']);
    exit;
}

// Get the parent section of the unit
$query = "SELECT section_id FROM unit_section WHERE unit_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $response = [
        'success' => true,
        'section_id' => $row['section_id']
    ];
} else {
    $response = ['success' => false, 'message' => 'Unit not found'];
}

echo json_encode($response);
exit;