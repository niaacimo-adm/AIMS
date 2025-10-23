<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON first to prevent any output issues
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if employee ID is provided
if (!isset($_POST['emp_id']) || empty($_POST['emp_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

$empId = (int)$_POST['emp_id'];

// Enable error reporting for debugging (remove in production)
error_reporting(0); // Turn off error reporting to prevent JSON corruption
ini_set('display_errors', 0);

try {
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();

    // Check connection
    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }

    // First, check if employee exists
    $checkStmt = $db->prepare("SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $checkStmt->bind_param("i", $empId);
    
    if (!$checkStmt->execute()) {
        throw new Exception("Execute failed: " . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }
    
    $employee = $result->fetch_assoc();
    $employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
    $pictureFile = $employee['picture'];

    // Prepare delete statement
    $deleteStmt = $db->prepare("DELETE FROM employee WHERE emp_id = ?");
    if (!$deleteStmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $deleteStmt->bind_param("i", $empId);

    // Execute deletion
    if ($deleteStmt->execute()) {
        // Check if any row was affected
        if ($deleteStmt->affected_rows > 0) {
            // Delete the employee's picture file if it exists
            if (!empty($pictureFile)) {
                $picturePath = "../dist/img/employees/" . $pictureFile;
                if (file_exists($picturePath) && is_file($picturePath)) {
                    unlink($picturePath);
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Employee {$employeeName} has been deleted successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No employee was deleted']);
        }
    } else {
        throw new Exception("Database error: " . $deleteStmt->error);
    }

} catch (Exception $e) {
    error_log("Delete employee error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete employee: ' . $e->getMessage()]);
} finally {
    // Close connections
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($deleteStmt)) $deleteStmt->close();
    if (isset($db)) $db->close();
}
?>