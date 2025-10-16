<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_POST['leave_id'])) {
    echo json_encode(['success' => false, 'message' => 'No leave ID provided']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$leave_id = $_POST['leave_id'];
$emp_id = $_SESSION['emp_id'];

// Check if the leave request belongs to the employee and is still pending
$check_query = "SELECT status FROM leave_requests WHERE leave_id = ? AND emp_id = ?";
$stmt = $db->prepare($check_query);
$stmt->bind_param("ii", $leave_id, $emp_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found']);
    exit;
}

$leave = $result->fetch_assoc();

if ($leave['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending leave requests can be cancelled']);
    exit;
}

// Cancel the leave request
$update_query = "UPDATE leave_requests SET status = 'cancelled' WHERE leave_id = ?";
$stmt = $db->prepare($update_query);
$stmt->bind_param("i", $leave_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error cancelling leave request']);
}
?>