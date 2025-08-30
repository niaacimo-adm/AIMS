<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$response = [
    'success' => false,
    'message' => '',
    'employeeCount' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_id'])) {
    $statusId = $_POST['status_id'];
    
    try {
        // Check if status is being used in employee table
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM employee WHERE appointment_status_id = ?");
        $checkStmt->bind_param("i", $statusId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $employeeCount = $row['count'];
        
        if ($employeeCount == 0) {
            $deleteStmt = $db->prepare("DELETE FROM appointment_status WHERE appointment_id = ?");
            $deleteStmt->bind_param("i", $statusId);
            if ($deleteStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Appointment status deleted successfully!';
            } else {
                $response['message'] = 'Failed to delete appointment status.';
            }
        } else {
            $response['message'] = 'Cannot delete appointment status as it is assigned to employees.';
            $response['employeeCount'] = $employeeCount;
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);
?>