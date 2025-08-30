<?php
require_once '../config/database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = $_POST['emp_id'] ?? null;
    $is_manager = $_POST['is_manager'] ?? 0;
    
    if ($emp_id) {
        try {
            $stmt = $db->prepare("UPDATE employee SET is_manager = ? WHERE emp_id = ?");
            $stmt->bind_param("ii", $is_manager, $emp_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Manager status updated successfully';
                
                // Add notification
                $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                        VALUES (?, 'Role Change', ?, 'role_change')");
                $message = $is_manager ? 
                    "You have been assigned to the Manager's Office" : 
                    "You have been removed from the Manager's Office";
                $stmt_notif->execute([$emp_id, $message]);
            } else {
                $response['message'] = 'Failed to update manager status';
            }
        } catch (Exception $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Employee ID is required';
    }
} else {
    $response['message'] = 'Invalid request method';
}

header('Content-Type: application/json');
echo json_encode($response);