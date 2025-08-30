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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['office_id'])) {
    $officeId = $_POST['office_id'];
    
    try {
        // Check if office is being used in employee table
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM employee WHERE office_id = ?");
        $checkStmt->bind_param("i", $officeId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $employeeCount = $row['count'];
        
        if ($employeeCount == 0) {
            $deleteStmt = $db->prepare("DELETE FROM office WHERE office_id = ?");
            $deleteStmt->bind_param("i", $officeId);
            if ($deleteStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Office deleted successfully!';
            } else {
                $response['message'] = 'Failed to delete office.';
            }
        } else {
            $response['message'] = 'Cannot delete office as it is assigned to employees.';
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