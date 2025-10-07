<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $database = new Database();
    $db = $database->getConnection();
    
    $folder_name = trim($_POST['folder_name']);
    $description = trim($_POST['description'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $parent_folder_id = $_POST['parent_folder_id'];
    $section_id = $_POST['section_id'];
    
    // FIXED: Get the actual employee ID for the current user using the same logic as folder_contents.php
    $user_emp_id = null;
    
    // First, try to get the employee ID from the users table (correct approach)
    $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $user_emp_id = $user_data['employee_id'];
    } else {
        // If no user record found, try direct employee table lookup as fallback
        $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
        $emp_stmt->bind_param("i", $_SESSION['user_id']);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        
        if ($emp_result->num_rows > 0) {
            $emp_data = $emp_result->fetch_assoc();
            $user_emp_id = $emp_data['emp_id'];
        } else {
            // Last resort: use session user_emp_id if previously set
            if (isset($_SESSION['user_emp_id']) && $_SESSION['user_emp_id']) {
                $user_emp_id = $_SESSION['user_emp_id'];
            } else {
                // Final fallback: use the session user_id itself
                $user_emp_id = $_SESSION['user_id'];
                error_log("Using session user_id as fallback employee ID: " . $user_emp_id);
            }
        }
    }

    if (!$user_emp_id) {
        $_SESSION['error'] = 'No valid employee record found.';
        header("Location: folder_contents.php?folder_id=" . $parent_folder_id . "&section_id=" . $section_id);
        exit();
    }
    
    // Store for consistent use
    $_SESSION['user_emp_id'] = $user_emp_id;
    
    // Validate folder name
    if (empty($folder_name)) {
        $_SESSION['error'] = 'Folder name is required.';
        header("Location: folder_contents.php?folder_id=" . $parent_folder_id . "&section_id=" . $section_id);
        exit();
    }
    
    // Check if folder already exists in the same parent
    $check_stmt = $db->prepare("SELECT folder_id FROM folders WHERE folder_name = ? AND parent_folder_id = ?");
    $check_stmt->bind_param("si", $folder_name, $parent_folder_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = 'A folder with this name already exists in the current location.';
        header("Location: folder_contents.php?folder_id=" . $parent_folder_id . "&section_id=" . $section_id);
        exit();
    }
    
    // Insert new folder
    $is_locked = !empty($password) ? 1 : 0;
    $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    $insert_stmt = $db->prepare("INSERT INTO folders (folder_name, description, parent_folder_id, section_id, created_by, is_locked, password) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
    $section_id_value = ($section_id === 'manager') ? NULL : $section_id;
    $insert_stmt->bind_param("ssiiiss", $folder_name, $description, $parent_folder_id, $section_id_value, $user_emp_id, $is_locked, $hashed_password);
    
    if ($insert_stmt->execute()) {
        $_SESSION['success'] = 'Folder created successfully!';
    } else {
        $_SESSION['error'] = 'Failed to create folder: ' . $db->error;
    }
    
    header("Location: folder_contents.php?folder_id=" . $parent_folder_id . "&section_id=" . $section_id);
    exit();
} else {
    header("Location: file_management.php");
    exit();
}
?>