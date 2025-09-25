<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// FUNCTION: Get user employee ID consistently
function getUserEmployeeId($db, $session_user_id) {
    $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
    $emp_stmt->bind_param("i", $session_user_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();

    if ($emp_result->num_rows > 0) {
        $emp_data = $emp_result->fetch_assoc();
        return $emp_data['emp_id'];
    } else {
        $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $session_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            if ($user_data['employee_id']) {
                $verify_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
                $verify_stmt->bind_param("i", $user_data['employee_id']);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows > 0) {
                    $verify_data = $verify_result->fetch_assoc();
                    return $verify_data['emp_id'];
                }
            }
        }
        return null;
    }
}

$user_emp_id = getUserEmployeeId($db, $_SESSION['user_id']);

if (!$user_emp_id) {
    echo json_encode(['success' => false, 'message' => 'No valid employee record found']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_files') {
    $file_ids = $_POST['file_ids'] ?? [];
    
    if (empty($file_ids)) {
        echo json_encode(['success' => false, 'message' => 'No files selected']);
        exit();
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Convert to integers for safety
    $file_ids = array_map('intval', $file_ids);
    $placeholders = str_repeat('?,', count($file_ids) - 1) . '?';
    
    // Check if user has permission to delete these files
    $check_stmt = $db->prepare("SELECT file_id, file_name, uploaded_by FROM files WHERE file_id IN ($placeholders)");
    $check_stmt->bind_param(str_repeat('i', count($file_ids)), ...$file_ids);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    $allowed_files = [];
    
    while ($file = $check_result->fetch_assoc()) {
        // Only allow deletion if user uploaded the file or has admin privileges
        if ($file['uploaded_by'] == $user_emp_id) {
            $allowed_files[] = $file['file_id'];
        } else {
            $errors[] = "No permission to delete: " . $file['file_name'];
            $error_count++;
        }
    }
    
    if (!empty($allowed_files)) {
        $delete_placeholders = str_repeat('?,', count($allowed_files) - 1) . '?';
        
        // Delete files from database
        $delete_stmt = $db->prepare("DELETE FROM files WHERE file_id IN ($delete_placeholders)");
        $delete_stmt->bind_param(str_repeat('i', count($allowed_files)), ...$allowed_files);
        
        if ($delete_stmt->execute()) {
            $success_count = $delete_stmt->affected_rows;
            
            // Log deletion activity
            foreach ($allowed_files as $file_id) {
                $log_stmt = $db->prepare("INSERT INTO file_activity_logs (file_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'deleted', ?, ?)");
                $log_description = "File deleted";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iiss", $file_id, $user_emp_id, $log_description, $ip);
                $log_stmt->execute();
            }
        } else {
            $error_count += count($allowed_files);
            $errors[] = "Database error: " . $db->error;
        }
    }
    
    if ($error_count > 0) {
        echo json_encode([
            'success' => $success_count > 0,
            'message' => "Deleted {$success_count} file(s). Failed to delete {$error_count} file(s).",
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted {$success_count} file(s)."
        ]);
    }
    exit();
}
?>