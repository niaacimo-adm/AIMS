<?php
// get_interns.php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM intern ORDER BY created_at DESC";
$result = $db->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'intern_id' => $row['intern_id'],
        'name' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name']),
        'email' => htmlspecialchars($row['email']),
        'school' => htmlspecialchars($row['school']),
        'department_assigned' => htmlspecialchars($row['department_assigned'] ?? 'N/A'),
        'start_date' => date('M d, Y', strtotime($row['start_date'])),
        'status' => $row['status'],
        'actions' => $row['intern_id'] // Will be processed by JavaScript
    ];
}

echo json_encode(['data' => $data]);
?>