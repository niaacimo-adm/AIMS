<?php
require_once '../config/database.php';

// Start with clean output buffer
ob_start();
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

try {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Fallback to regular POST if JSON decode fails
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['id'])) {
        $userId = (int)$data['id'];
        
        // Check if user exists
        $checkQuery = $db->prepare("SELECT id FROM users WHERE id = ?");
        $checkQuery->bind_param("i", $userId);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        $userExists = $result->fetch_assoc();
        $checkQuery->close();
        
        if ($userExists) {
            // Delete the user
            $deleteQuery = $db->prepare("DELETE FROM users WHERE id = ?");
            $deleteQuery->bind_param("i", $userId);
            
            if ($deleteQuery->execute()) {
                $response = [
                    'success' => true,
                    'message' => 'User deleted successfully!'
                ];
            } else {
                $response['message'] = 'Failed to delete user';
            }
            $deleteQuery->close();
        } else {
            $response['message'] = 'User not found';
        }
    } else {
        $response['message'] = 'Invalid request';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Clean output and send JSON
ob_end_clean();
echo json_encode($response);
exit;
?>