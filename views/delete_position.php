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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['position_id'])) {
    $positionId = $_POST['position_id'];
    
    try {
        // Check if position is being used in employee table
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM employee WHERE position_id = ?");
        $checkStmt->bind_param("i", $positionId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $employeeCount = $row['count'];
        
        if ($employeeCount == 0) {
            $deleteStmt = $db->prepare("DELETE FROM position WHERE position_id = ?");
            $deleteStmt->bind_param("i", $positionId);
            if ($deleteStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Position deleted successfully!';
            } else {
                $response['message'] = 'Failed to delete position.';
            }
        } else {
            $response['message'] = 'Cannot delete position as it is assigned to employees.';
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