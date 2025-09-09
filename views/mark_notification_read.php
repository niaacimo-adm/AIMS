<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json'); // Add this line

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE admin_notifications SET is_read = 1 WHERE id = ? AND admin_emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $_POST['id'], $_SESSION['emp_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>