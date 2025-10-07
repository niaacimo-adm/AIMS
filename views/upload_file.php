<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_POST['action']) || $_POST['action'] !== 'upload_files') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
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
    
    $section_id = $_POST['section_id'] ?? null;
    $folder_id = $_POST['folder_id'] ?? null;
    $description = $_POST['description'] ?? '';
    
    $uploaded_files = [];
    $errors = [];
    
    if (!empty($_FILES['files'])) {
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $temp_name = $_FILES['files']['tmp_name'][$key];
                $file_size = $_FILES['files']['size'][$key];
                $file_type = pathinfo($name, PATHINFO_EXTENSION);
                
                // Generate unique filename
                $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name);
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($temp_name, $file_path)) {
                    // Insert into database
                    $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, description, section_id, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $section_id_value = ($section_id === 'manager' || empty($section_id)) ? NULL : $section_id;
                    $folder_id_value = empty($folder_id) ? NULL : $folder_id;
                    $stmt->bind_param("sssisiii", $name, $new_filename, $file_type, $file_size, $description, $section_id_value, $folder_id_value, $user_emp_id);
                    
                    if ($stmt->execute()) {
                        $uploaded_files[] = [
                            'name' => $name,
                            'size' => $file_size,
                            'id' => $db->insert_id
                        ];
                    } else {
                        $errors[] = "Failed to save file '$name' to database";
                        // Remove uploaded file if database insert failed
                        unlink($file_path);
                    }
                } else {
                    $errors[] = "Failed to upload file '$name'";
                }
            } else {
                $errors[] = "Error uploading file '$name': " . $_FILES['files']['error'][$key];
            }
        }
    }
    
    if (count($uploaded_files) > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Files uploaded successfully',
            'uploaded_count' => count($uploaded_files),
            'uploaded_files' => $uploaded_files,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No files were uploaded',
            'errors' => $errors
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>