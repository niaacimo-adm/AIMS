<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['equipment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Equipment ID is required']);
    exit;
}

$equipment_id = intval($_GET['equipment_id']);

$query = "SELECT l.*, e.first_name, e.last_name 
          FROM ict_equipment_logs l 
          LEFT JOIN employee e ON l.action_by = e.emp_id 
          WHERE l.equipment_id = ? 
          ORDER BY l.action_date DESC 
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    if ($row['first_name']) {
        $row['action_by_name'] = $row['first_name'] . ' ' . $row['last_name'];
    }
    $history[] = $row;
}

echo json_encode(['success' => true, 'data' => $history]);
?>