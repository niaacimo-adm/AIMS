<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get user employee ID
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_files') {
    $upload_dir = '../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== 'manager' ? $_POST['section_id'] : NULL;
    $folder_id = isset($_POST['folder_id']) && !empty($_POST['folder_id']) ? $_POST['folder_id'] : NULL;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    $uploaded_files = [];
    $failed_files = [];
    $errors = [];
    
    // Check if files were uploaded
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'No files selected']);
        exit();
    }
    
    // Process each file
    foreach ($_FILES['files']['name'] as $index => $name) {
        if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading {$name}: " . getUploadError($_FILES['files']['error'][$index]);
            $failed_files[] = $name;
            continue;
        }
        
        $file_name = basename($name);
        $file_tmp = $_FILES['files']['tmp_name'][$index];
        $file_size = $_FILES['files']['size'][$index];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file size (500MB limit)
        $max_file_size = 500 * 1024 * 1024; // 500MB in bytes
        if ($file_size > $max_file_size) {
            $errors[] = "File {$file_name} is too large (max 500MB)";
            $failed_files[] = $file_name;
            continue;
        }
        
        // Generate unique filename to prevent conflicts
        $unique_name = uniqid() . '_' . time() . '.' . $file_type;
        $target_path = $upload_dir . $unique_name;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $target_path)) {
            // Insert into database
            $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, description, section_id, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssisii", $file_name, $unique_name, $file_type, $file_size, $description, $section_id, $folder_id, $user_emp_id);
            
            if ($stmt->execute()) {
                $file_id = $db->insert_id;
                $uploaded_files[] = [
                    'name' => $file_name,
                    'size' => $file_size,
                    'type' => $file_type
                ];
                
                // Log file upload activity
                $log_stmt = $db->prepare("INSERT INTO file_activity_logs (file_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'uploaded', ?, ?)");
                $log_description = "File '{$file_name}' uploaded";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iiss", $file_id, $user_emp_id, $log_description, $ip);
                $log_stmt->execute();
            } else {
                // Remove the uploaded file if database insert failed
                unlink($target_path);
                $errors[] = "Database error for {$file_name}: " . $db->error;
                $failed_files[] = $file_name;
            }
        } else {
            $errors[] = "Failed to move uploaded file {$file_name}";
            $failed_files[] = $file_name;
        }
    }
    
    $response = [
        'success' => count($uploaded_files) > 0,
        'uploaded_count' => count($uploaded_files),
        'failed_count' => count($failed_files),
        'uploaded_files' => $uploaded_files,
        'failed_files' => $failed_files
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response);
    exit();
}

function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    return $errors[$error_code] ?? 'Unknown upload error';
}
?>