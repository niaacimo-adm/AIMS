<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT COUNT(*) as unread_count FROM admin_notifications 
        WHERE admin_emp_id = ? AND is_read = 0";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $_SESSION['emp_id']);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['unread_count'];

echo json_encode(['count' => $count]);