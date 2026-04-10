<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $database = new Database();
    $db = $database->getConnection();

    // FIX: table is `notifications`, PK is `notification_id`, owner column is `emp_id`
    $query = "DELETE FROM notifications WHERE notification_id = ? AND emp_id = ?";
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