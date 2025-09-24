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

    // Database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get user employee ID for permission checks
    $user_emp_id = $_SESSION['user_id'];

    // First, try to get the employee record for the logged-in user
    // Since there's no user_id column, we'll use emp_id directly from session
    $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
    $emp_stmt->bind_param("i", $_SESSION['user_id']);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();

    if ($emp_result->num_rows > 0) {
        $emp_data = $emp_result->fetch_assoc();
        $user_emp_id = $emp_data['emp_id'];
    } else {
        // If no direct match, check if user is a manager
        $manager_stmt = $db->prepare("SELECT emp_id FROM employee WHERE is_manager = 1 LIMIT 1");
        $manager_stmt->execute();
        $manager_result = $manager_stmt->get_result();
        
        if ($manager_result->num_rows > 0) {
            $manager_data = $manager_result->fetch_assoc();
            $user_emp_id = $manager_data['emp_id'];
        } else {
            // Last resort: get any employee ID
            $default_stmt = $db->prepare("SELECT emp_id FROM employee LIMIT 1");
            $default_stmt->execute();
            $default_result = $default_stmt->get_result();
            
            if ($default_result->num_rows > 0) {
                $default_emp = $default_result->fetch_assoc();
                $user_emp_id = $default_emp['emp_id'];
            } else {
                // Fallback to session user_id if no employees exist
                $user_emp_id = $_SESSION['user_id'];
            }
        }
    }

    // Get section ID from URL parameter
    $section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';

    // Fetch section details
    $section_name = "Manager's Office";
    $section_code = "MGR";

    if ($section_id !== 'manager' && is_numeric($section_id)) {
        $stmt = $db->prepare("SELECT s.*, o.office_name, CONCAT(e.first_name, ' ', e.last_name) as head_name 
                            FROM section s 
                            LEFT JOIN office o ON s.office_id = o.office_id 
                            LEFT JOIN employee e ON s.head_emp_id = e.emp_id 
                            WHERE s.section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $section = $result->fetch_assoc();
            $section_name = $section['section_name'];
            $section_code = $section['section_code'];
        } else {
            header("Location: file_management.php");
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            // Get user employee ID for permission checks
            $user_emp_id = null;

            // First, try to get the employee ID from the users table
            $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $_SESSION['user_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();

            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $user_emp_id = $user_data['employee_id'];
            } else {
                // If no user record found, try to get any manager's employee ID as fallback
                $manager_stmt = $db->prepare("SELECT emp_id FROM employee WHERE is_manager = 1 LIMIT 1");
                $manager_stmt->execute();
                $manager_result = $manager_stmt->get_result();
                
                if ($manager_result->num_rows > 0) {
                    $manager_data = $manager_result->fetch_assoc();
                    $user_emp_id = $manager_data['emp_id'];
                } else {
                    // Last resort: get any employee ID
                    $default_stmt = $db->prepare("SELECT emp_id FROM employee LIMIT 1");
                    $default_stmt->execute();
                    $default_result = $default_stmt->get_result();
                    
                    if ($default_result->num_rows > 0) {
                        $default_emp = $default_result->fetch_assoc();
                        $user_emp_id = $default_emp['emp_id'];
                    }
                }
            }

            // If still no employee ID found, set a default value to prevent errors
            if (!$user_emp_id) {
                $user_emp_id = 0; // Default fallback
            }

            // Store for use in POST actions
            $_SESSION['user_emp_id'] = $user_emp_id;
            // If still no employee ID found, show error
            if (!$user_emp_id) {
                if ($_POST['action'] === 'create_folder') {
                    $_SESSION['error'] = "No valid employee record found for folder creation.";
                    header("Location: section_files.php?section_id=" . $section_id);
                    exit();
                } else {
                    echo json_encode(['success' => false, 'message' => 'No valid employee record found.']);
                    exit();
                }
            }

            switch ($_POST['action']) {
                case 'create_folder':
                    $folder_name = trim($_POST['folder_name']);
                    $description = trim($_POST['description']);
                    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
                    $is_locked = !empty($_POST['password']) ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO folders (folder_name, description, section_id, password, created_by, is_locked) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                    $section_id_value = ($section_id === 'manager') ? NULL : $section_id;
                    $stmt->bind_param("ssisii", $folder_name, $description, $section_id_value, $password, $user_emp_id, $is_locked);
                    
                    if ($stmt->execute()) {
                        $folder_id = $db->insert_id;
                        // Log activity
                        $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) 
                                                VALUES (?, ?, 'created', ?, ?)");
                        $log_description = "Folder '{$folder_name}' created";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                        $log_stmt->execute();
                        
                        $_SESSION['success'] = "Folder created successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to create folder: " . $db->error;
                    }
                    header("Location: section_files.php?section_id=" . $section_id);
                    exit();
                    
                case 'unlock_folder':
                    $folder_id = $_POST['folder_id'];
                    $password = $_POST['password'];
                    
                    $stmt = $db->prepare("SELECT password FROM folders WHERE folder_id = ?");
                    $stmt->bind_param("i", $folder_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $folder = $result->fetch_assoc();
                    
                    if ($folder && password_verify($password, $folder['password'])) {
                        $_SESSION['unlocked_folders'][$folder_id] = true;
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid password']);
                    }
                    exit();
                
                case 'edit_folder':
                    $folder_id = $_POST['folder_id'];
                    $folder_name = trim($_POST['folder_name']);
                    $description = trim($_POST['description']);
                    
                    // Debug: Check what user_emp_id we're using
                    error_log("Edit Folder - User Emp ID: " . $user_emp_id . ", Folder ID: " . $folder_id);
                    
                    // Check if folder exists and get creator info
                    $check_stmt = $db->prepare("SELECT folder_id, folder_name, created_by, is_locked FROM folders WHERE folder_id = ?");
                    $check_stmt->bind_param("i", $folder_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        error_log("Folder not found: " . $folder_id);
                        echo json_encode(['success' => false, 'message' => 'Folder not found.']);
                        exit();
                    }
                    
                    $folder_data = $check_result->fetch_assoc();
                    error_log("Folder creator: " . $folder_data['created_by'] . ", Current user: " . $user_emp_id);
                    
                    // Only allow creator to edit
                    if ($folder_data['created_by'] != $user_emp_id) {
                        error_log("Permission denied for folder edit");
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this folder.']);
                        exit();
                    }
                    
                    // Handle password removal (if remove_password is set)
                    if (isset($_POST['remove_password']) && $_POST['remove_password'] == '1') {
                        $password = null;
                        $is_locked = 0;
                        $stmt = $db->prepare("UPDATE folders SET folder_name = ?, description = ?, password = NULL, is_locked = ?, updated_at = NOW() WHERE folder_id = ?");
                        $stmt->bind_param("ssii", $folder_name, $description, $is_locked, $folder_id);
                    }
                    // Handle new password
                    else if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $is_locked = 1;
                        $stmt = $db->prepare("UPDATE folders SET folder_name = ?, description = ?, password = ?, is_locked = ?, updated_at = NOW() WHERE folder_id = ?");
                        $stmt->bind_param("sssii", $folder_name, $description, $password, $is_locked, $folder_id);
                    }
                    // No password change
                    else {
                        $stmt = $db->prepare("UPDATE folders SET folder_name = ?, description = ?, updated_at = NOW() WHERE folder_id = ?");
                        $stmt->bind_param("ssi", $folder_name, $description, $folder_id);
                    }
                    
                    if ($stmt->execute()) {
                        // Log activity
                        $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) 
                                                VALUES (?, ?, 'updated', ?, ?)");
                        $log_description = "Folder '{$folder_name}' updated";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                        $log_stmt->execute();
                        
                        echo json_encode(['success' => true, 'message' => 'Folder updated successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update folder: ' . $db->error]);
                    }
                exit();

                case 'delete_folder':
                    $folder_id = $_POST['folder_id'];
                    $password = isset($_POST['password']) ? $_POST['password'] : '';
                    
                    error_log("DELETE FOLDER REQUEST - Folder ID: $folder_id, Password provided: " . (!empty($password) ? 'YES' : 'NO'));
                    
                    // Check if folder exists and get creator info
                    $check_stmt = $db->prepare("SELECT folder_id, folder_name, created_by, password, is_locked FROM folders WHERE folder_id = ?");
                    $check_stmt->bind_param("i", $folder_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        error_log("Folder not found: " . $folder_id);
                        echo json_encode(['success' => false, 'message' => 'Folder not found.']);
                        exit();
                    }
                    
                    $folder_data = $check_result->fetch_assoc();
                    error_log("Folder data - Name: {$folder_data['folder_name']}, Locked: {$folder_data['is_locked']}, Creator: {$folder_data['created_by']}, Current User: $user_emp_id");
                    
                    // Only allow creator to delete
                    if ($folder_data['created_by'] != $user_emp_id) {
                        error_log("Permission denied for folder delete");
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this folder.']);
                        exit();
                    }
                    
                    // Check if folder is locked and verify password
                    if ($folder_data['is_locked'] == 1) {
                        if (empty($password)) {
                            echo json_encode(['success' => false, 'message' => 'Password required to delete locked folder.', 'password_required' => true]);
                            exit();
                        }
                        
                        if (!password_verify($password, $folder_data['password'])) {
                            echo json_encode(['success' => false, 'message' => 'Invalid password.', 'password_required' => true]);
                            exit();
                        }
                    }
                    
                    // Log activity BEFORE deleting the folder
                    $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) 
                                            VALUES (?, ?, 'deleted', ?, ?)");
                    $log_description = "Folder '{$folder_data['folder_name']}' deleted";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                    $log_success = $log_stmt->execute();
                    
                    if (!$log_success) {
                        error_log("Failed to log folder deletion activity: " . $log_stmt->error);
                        // Continue with deletion even if logging fails
                    }
                    
                    // Now delete the folder
                    $stmt = $db->prepare("DELETE FROM folders WHERE folder_id = ?");
                    $stmt->bind_param("i", $folder_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Folder deleted successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete folder: ' . $db->error]);
                    }
                    exit();
            }
        }
    }

    // Fetch root folders for the section
    if ($section_id === 'manager') {
        $folder_query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL 
                        ORDER BY f.folder_name";
        $folder_stmt = $db->prepare($folder_query);
    } else {
        $folder_query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id = ? AND f.parent_folder_id IS NULL 
                        ORDER BY f.folder_name";
        $folder_stmt = $db->prepare($folder_query);
        $folder_stmt->bind_param("i", $section_id);
    }

    $folder_stmt->execute();
    $folders_result = $folder_stmt->get_result();
    $folders = [];
    while ($row = $folders_result->fetch_assoc()) {
        $folders[] = $row;
    }

    // Fetch files not in any folder
    if ($section_id === 'manager') {
        $query = "SELECT f.*, 
                        CONCAT(e.first_name, ' ', e.last_name) as uploaded_by
                FROM files f
                LEFT JOIN employee e ON f.uploaded_by = e.emp_id
                WHERE (f.section_id IS NULL OR f.section_id = 0) AND f.folder_id IS NULL
                ORDER BY f.created_at DESC";
        $stmt = $db->prepare($query);
    } else {
        $query = "SELECT f.*, 
                        CONCAT(e.first_name, ' ', e.last_name) as uploaded_by
                FROM files f
                LEFT JOIN employee e ON f.uploaded_by = e.emp_id
                WHERE f.section_id = ? AND f.folder_id IS NULL
                ORDER BY f.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $section_id);
    }

    $stmt->execute();
    $files_result = $stmt->get_result();
    $files = [];
    while ($row = $files_result->fetch_assoc()) {
        $files[] = $row;
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($section_name) ?> Files</title>
    
    <?php include '../includes/header.php'; ?>
    
    <style>
        .file-explorer {
            display: flex;
            height: calc(100vh - 200px);
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #dee2e6;
            padding: 20px;
            overflow-y: auto;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .activity-panel {
            width: 400px;
            background: white;
            border-left: 1px solid #dee2e6;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        }
        
        .activity-panel.active {
            transform: translateX(0);
        }
        
        .folder-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }
        
        .folder-item:hover {
            background-color: #e9ecef;
        }
        
        .folder-item.locked {
            background-color: #fff3cd;
        }
        
        .folder-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: #ffc107;
        }
        
        .folder-icon.locked {
            color: #dc3545;
        }
        
        .folder-info {
            flex: 1;
        }
        
        .folder-stats {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .file-item, .folder-item-grid {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            position: relative;
        }
        
        .file-item:hover, .folder-item-grid:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .file-icon {
            font-size: 3rem;
            color: #4361ee;
            margin-bottom: 10px;
        }
        
        .folder-icon-grid {
            font-size: 3rem;
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .locked-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .current-folder {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #343a40;
        }
        
        .folder-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .folder-actions .dropdown-toggle {
            border: none;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 0.9rem;
        }

        .folder-actions .dropdown-toggle:hover {
            opacity: 1;
            background: white;
            transform: scale(1.1);
        }

        .folder-item-grid:hover .folder-actions .dropdown-toggle {
            opacity: 0.9;
        }

        /* Ensure dropdown menu is properly positioned */
        .folder-actions .dropdown-menu {
            min-width: 120px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .folder-actions .dropdown-item {
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }

        .folder-actions .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .folder-actions .dropdown-item i {
            width: 16px;
            text-align: center;
            margin-right: 8px;
        }

        .folder-actions-btn {
            background: none;
            border: none;
            padding: 5px 8px;
            border-radius: 3px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s, background-color 0.2s;
            font-size: 14px;
            z-index: 101;
            position: relative;
        }

        .folder-actions-btn:hover {
            opacity: 1;
            background-color: rgba(0,0,0,0.1);
        }

        .folder-item:hover .folder-actions-btn,
        .folder-item-grid:hover .folder-actions-btn {
            opacity: 0.7;
        }

        .folder-actions-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 120px;
            display: none;
        }

        .folder-actions-menu.show {
            display: block;
        }

        .folder-action-item {
            padding: 8px 12px;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 0.9rem;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
        }

        .folder-action-item:hover {
            background-color: #f8f9fa;
        }

        .folder-action-item i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .folder-action-item.edit {
            color: #007bff;
        }

        .folder-action-item.delete {
            color: #dc3545;
        }
        
        /* Fix for grid folder items */
        .folder-item-grid {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .folder-header {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .card-header {
            background: linear-gradient(135deg, #4c8de7ff 0%, #1890dbff 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 25px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_file.php'; ?>
    
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?= htmlspecialchars($section_name) ?> Files</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="file_management.php">File Management</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($section_name) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= $_SESSION['success'] ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= $_SESSION['error'] ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-folder mr-2"></i>
                            File Explorer
                        </h3>
                        <div class="card-tools">
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#uploadFileModal">
                                <i class="fas fa-upload mr-1"></i> Upload File
                            </button>
                            <button class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#createFolderModal">
                                <i class="fas fa-folder-plus mr-1"></i> New Folder
                            </button>
                            <button class="btn btn-info btn-sm ml-1" id="toggleActivityPanel">
                                <i class="fas fa-history mr-1"></i> Activity Log
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="file-explorer">
                            <!-- Sidebar with folder tree -->
                            <div class="sidebar">
                                <h5><i class="fas fa-folder-tree mr-2"></i>Folders</h5>
                                <div id="folderTree">
                                    <?php foreach ($folders as $folder): ?>
                                        <div class="folder-item <?= $folder['is_locked'] ? 'locked' : '' ?>" 
                                            data-folder-id="<?= $folder['folder_id'] ?>"
                                            data-locked="<?= $folder['is_locked'] ?>">
                                            <i class="fas fa-folder folder-icon <?= $folder['is_locked'] ? 'locked' : '' ?>"></i>
                                            <div class="folder-info">
                                                <div class="folder-name">
                                                    <?= htmlspecialchars($folder['folder_name']) ?>
                                                    <?php if ($folder['is_locked']): ?>
                                                        <span class="locked-badge">Locked</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="folder-stats">
                                                    <?= $folder['file_count'] ?> files, 
                                                    <?= $folder['subfolder_count'] ?> subfolders
                                                </div>
                                            </div>
                                            <div class="folder-actions">
                                                <button class="folder-actions-btn" onclick="toggleFolderMenu(this, event)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="folder-actions-menu">
                                                    <button class="folder-action-item edit" onclick="editFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars($folder['folder_name']) ?>', '<?= htmlspecialchars($folder['description']) ?>')">
                                                        <i class="fas fa-edit mr-2"></i>Edit
                                                    </button>
                                                    <!-- In both sidebar and grid folder items -->
                                                    <button class="folder-action-item delete" 
                                                            onclick="deleteFolder(<?= $folder['folder_id'] ?>, 
                                                                                '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', 
                                                                                <?= $folder['is_locked'] ?>)">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Main content area -->
                            <div class="main-content">
                                <div class="current-folder">
                                    <i class="fas fa-folder"></i> Root Directory
                                </div>
                                
                                <div class="file-grid">
                                    <?php foreach ($folders as $folder): ?>
                                        <div class="folder-item-grid" 
                                            data-folder-id="<?= $folder['folder_id'] ?>"
                                            data-locked="<?= $folder['is_locked'] ?>">
                                            <div class="folder-header" style="position: relative;">
                                                <i class="fas fa-folder folder-icon-grid <?= $folder['is_locked'] ? 'text-danger' : '' ?>"></i>
                                                <div class="folder-actions" style="position: absolute; top: 5px; right: 5px;">
                                                    <button class="folder-actions-btn" onclick="toggleFolderMenu(this, event)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="folder-actions-menu">
                                                        <button class="folder-action-item edit" onclick="editFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars($folder['folder_name']) ?>', '<?= htmlspecialchars($folder['description']) ?>')">
                                                            <i class="fas fa-edit mr-2"></i>Edit
                                                        </button>
                                                        <!-- In both sidebar and grid folder items -->
                                                        <button class="folder-action-item delete" 
                                                                onclick="deleteFolder(<?= $folder['folder_id'] ?>, 
                                                                                    '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', 
                                                                                    <?= $folder['is_locked'] ?>)">
                                                            <i class="fas fa-trash mr-2"></i>Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="folder-name">
                                                <?= htmlspecialchars($folder['folder_name']) ?>
                                                <?php if ($folder['is_locked']): ?>
                                                    <br><span class="locked-badge">Locked</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="folder-stats small text-muted">
                                                <?= $folder['file_count'] ?> files
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Files Grid -->
                                <h5 class="mt-4">Files (<?= count($files) ?>)</h5>
                                <?php if (empty($files)): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No files in this directory.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>File Name</th>
                                                    <th>Type</th>
                                                    <th>Size</th>
                                                    <th>Uploaded By</th>
                                                    <th>Date Uploaded</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($files as $file): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-file-<?= getFileIcon($file['file_type']) ?> text-primary mr-2"></i>
                                                        <?= htmlspecialchars($file['file_name']) ?>
                                                    </td>
                                                    <td><?= strtoupper($file['file_type']) ?></td>
                                                    <td><?= formatFileSize($file['file_size']) ?></td>
                                                    <td><?= htmlspecialchars($file['uploaded_by'] ?? 'Unknown') ?></td>
                                                    <td><?= date('M j, Y H:i', strtotime($file['created_at'])) ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="view_file.php?id=<?= $file['file_id'] ?>" 
                                                               class="btn btn-info btn-sm" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="download_file.php?id=<?= $file['file_id'] ?>" 
                                                               class="btn btn-success btn-sm" title="Download">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Activity Panel -->
<div class="activity-panel" id="activityPanel">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-history mr-2"></i>
                Activity Log
            </h5>
            <button type="button" class="close" id="closeActivityPanel">
                <span>&times;</span>
            </button>
        </div>
        <div class="card-body" id="activityLogs">
            <!-- Activity logs will be loaded here -->
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="folderForm" method="POST">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="folderName">Folder Name *</label>
                        <input type="text" class="form-control" id="folderName" name="folder_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="password">Password (Optional - for folder protection)</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Leave blank for no password">
                        <small class="form-text text-muted">If set, users will need to enter password to access this folder.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unlock Folder Modal -->
<div class="modal fade" id="unlockFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Unlock Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>This folder is password protected. Please enter the password to continue.</p>
                <div class="form-group">
                    <label for="unlockPassword">Password</label>
                    <input type="password" class="form-control" id="unlockPassword" required>
                    <input type="hidden" id="unlockFolderId">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="unlockFolderBtn">Unlock</button>
            </div>
        </div>
    </div>
</div>
<!-- Edit Folder Modal -->
<div class="modal fade" id="editFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editFolderForm" method="POST">
                <input type="hidden" name="action" value="edit_folder">
                <input type="hidden" name="folder_id" id="editFolderId">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editFolderName">Folder Name *</label>
                        <input type="text" class="form-control" id="editFolderName" name="folder_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Password Settings</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="removePasswordCheck">
                            <label class="form-check-label" for="removePasswordCheck">
                                Remove password protection
                            </label>
                        </div>
                        <div id="passwordFields">
                            <label for="editPassword">New Password (Optional - leave blank to keep current)</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="editPassword" name="password" 
                                       placeholder="Enter new password to change">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="toggleEditPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Leave blank to keep current password unchanged.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload File</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="fileInput">Select File *</label>
                        <input type="file" class="form-control-file" id="fileInput" name="file" required>
                    </div>
                    <div class="form-group">
                        <label for="fileDescription">Description</label>
                        <textarea class="form-control" id="fileDescription" name="description" rows="3" placeholder="Optional file description"></textarea>
                    </div>
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="folder_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Delete Folder Modal -->
<div class="modal fade" id="deleteFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteFolderMessage">Are you sure you want to delete this folder? This action cannot be undone.</p>
                <div id="passwordVerification" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="fas fa-lock mr-2"></i>This folder is password protected. Please enter the password to continue.
                    </div>
                    <div class="form-group">
                        <label for="deletePassword">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="deletePassword" placeholder="Enter folder password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleDeletePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteFolder">Delete Folder</button>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>

<script>
    let folderToDelete = null;
    $(document).ready(function() {
        let currentFolderId = null;
        
        // Toggle password visibility for edit modal
        $('#toggleEditPassword').click(function() {
            const passwordField = $('#editPassword');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        
        // Toggle password visibility for delete modal
        $('#toggleDeletePassword').click(function() {
            const passwordField = $('#deletePassword');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        
        // Handle remove password checkbox
        $('#removePasswordCheck').change(function() {
            if ($(this).is(':checked')) {
                $('#passwordFields').hide();
                // Add hidden field to indicate password removal
                if ($('#removePasswordField').length === 0) {
                    $('#editFolderForm').append('<input type="hidden" name="remove_password" id="removePasswordField" value="1">');
                }
            } else {
                $('#passwordFields').show();
                $('#removePasswordField').remove();
            }
        });

        // Toggle activity panel
        $('#toggleActivityPanel').click(function() {
            $('#activityPanel').toggleClass('active');
            if ($('#activityPanel').hasClass('active')) {
                loadActivityLogs();
            }
        });
        
        // Close activity panel
        $('#closeActivityPanel').click(function() {
            $('#activityPanel').removeClass('active');
        });
        
        // Close activity panel when clicking outside
        $(document).click(function(e) {
            if ($('#activityPanel').hasClass('active') && 
                !$(e.target).closest('#activityPanel').length && 
                !$(e.target).is('#toggleActivityPanel')) {
                $('#activityPanel').removeClass('active');
            }
        });
        
        // Folder click handler
        $(document).on('click', '.folder-item, .folder-item-grid', function(e) {
            if ($(e.target).closest('.folder-actions').length || 
                $(e.target).hasClass('folder-actions-btn') ||
                $(e.target).hasClass('folder-action-item') ||
                $(e.target).closest('.folder-action-item').length) {
                return;
            }
            
            const folderId = $(this).data('folder-id');
            const isLocked = $(this).data('locked') == 1;
            
            if (isLocked) {
                $('#unlockFolderId').val(folderId);
                $('#unlockFolderModal').modal('show');
            } else {
                openFolder(folderId);
            }
        });
        
        // Unlock folder
        $('#unlockFolderBtn').click(function() {
            const folderId = $('#unlockFolderId').val();
            const password = $('#unlockPassword').val();
            
            if (!password) {
                Swal.fire('Error!', 'Please enter a password', 'error');
                return;
            }
            
            $.ajax({
                url: 'section_files.php?section_id=<?= $section_id ?>',
                type: 'POST',
                data: {
                    action: 'unlock_folder',
                    folder_id: folderId,
                    password: password
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#unlockFolderModal').modal('hide');
                            $('#unlockPassword').val('');
                            openFolder(folderId);
                        } else {
                            Swal.fire('Error!', result.message || 'Invalid password', 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error!', 'Error parsing server response', 'error');
                        console.error('Parse error:', e, 'Response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error!', 'Server error occurred: ' + error, 'error');
                    console.error('AJAX error:', error);
                }
            });
        });
        
        function openFolder(folderId) {
            window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
        }
        
        function loadActivityLogs() {
            $.ajax({
                url: 'get_activity_logs.php',
                type: 'GET',
                data: {
                    section_id: '<?= $section_id ?>',
                    folder_id: currentFolderId
                },
                success: function(response) {
                    $('#activityLogs').html(response);
                }
            });
        }
        
        // Upload file functionality
        $('#uploadForm').submit(function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'upload_file');
            
            $.ajax({
                url: 'upload_file.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'File uploaded successfully!',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                            $('#uploadFileModal').modal('hide');
                            location.reload();
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: result.message || 'Upload failed',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    } catch (e) {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Invalid server response',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        console.error('Parse error:', e, 'Response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to upload file: ' + error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    console.error('AJAX error:', error);
                }
            });
        });

        // Handle edit folder form submission
        $('#editFolderForm').submit(function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
            
            $.ajax({
                url: 'section_files.php?section_id=<?= $section_id ?>',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: result.message || 'Folder updated successfully!',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $('#editFolderModal').modal('hide');
                                    location.reload();
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: result.message || 'Update failed',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                            console.error('Edit folder error:', result);
                        }
                    } catch (e) {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Invalid server response',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        console.error('Parse error:', e, 'Response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).html(originalText);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update folder: ' + error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    console.error('AJAX error:', error);
                }
            });
        });

       // Confirm delete folder - DEBUG VERSION
        $('#confirmDeleteFolder').click(function() {
            if (!folderToDelete) {
                console.error('folderToDelete is not set!');
                Swal.fire('Error!', 'Folder information not found.', 'error');
                return;
            }
            
            const folderId = folderToDelete.id;
            const folderName = folderToDelete.name;
            const isLocked = folderToDelete.locked;
            const password = isLocked ? $('#deletePassword').val() : '';
            
            console.log('Confirm delete clicked:', {folderId, folderName, isLocked, password});
            
            if (isLocked && !password) {
                Swal.fire('Error!', 'Please enter the folder password', 'error');
                return;
            }
            
            const deleteBtn = $(this);
            const originalText = deleteBtn.html();
            deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
            
            console.log('Sending AJAX request...');
            
            $.ajax({
                url: 'section_files.php?section_id=<?= $section_id ?>',
                type: 'POST',
                data: {
                    action: 'delete_folder',
                    folder_id: folderId,
                    password: password
                },
                success: function(response) {
                    console.log('Server response:', response);
                    deleteBtn.prop('disabled', false).html(originalText);
                    
                    try {
                        const result = JSON.parse(response);
                        console.log('Parsed result:', result);
                        
                        if (result.success) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: result.message || 'Folder has been deleted.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                $('#deleteFolderModal').modal('hide');
                                location.reload();
                            });
                        } else {
                            console.log('Delete failed:', result);
                            if (result.password_required) {
                                $('#passwordVerification').show();
                                $('#deleteFolderMessage').html(`Are you sure you want to delete the folder "<strong>${folderName}</strong>"? This action cannot be undone.`);
                                Swal.fire('Error!', result.message, 'error');
                            } else {
                                Swal.fire('Error!', result.message || 'Delete failed', 'error');
                            }
                        }
                    } catch (e) {
                        console.error('Parse error:', e, 'Response:', response);
                        Swal.fire('Error!', 'Invalid server response: ' + response, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error, 'Status:', status, 'xhr:', xhr);
                    deleteBtn.prop('disabled', false).html(originalText);
                    Swal.fire('Error!', 'Failed to delete folder: ' + error, 'error');
                }
            });
        });
    });

    // Toggle folder actions menu
    function toggleFolderMenu(button, event) {
        event.stopPropagation();
        event.preventDefault();
        
        const menu = button.nextElementSibling;
        const isShowing = menu.classList.contains('show');
        
        // Close all other menus
        document.querySelectorAll('.folder-actions-menu').forEach(m => {
            m.classList.remove('show');
        });
        
        if (!isShowing) {
            menu.classList.add('show');
        } else {
            menu.classList.remove('show');
        }
        
        // Close menu when clicking elsewhere
        const closeMenu = (e) => {
            if (!menu.contains(e.target) && e.target !== button) {
                menu.classList.remove('show');
                document.removeEventListener('click', closeMenu);
            }
        };
        
        setTimeout(() => {
            document.addEventListener('click', closeMenu);
        }, 10);
    }

    // Edit folder function
    function editFolder(folderId, folderName, description) {
        $('#editFolderId').val(folderId);
        $('#editFolderName').val(folderName);
        $('#editDescription').val(description);
        $('#editPassword').val('');
        $('#removePasswordCheck').prop('checked', false);
        $('#passwordFields').show();
        $('#removePasswordField').remove();
        $('#editFolderModal').modal('show');
        
        // Close the menu
        document.querySelectorAll('.folder-actions-menu').forEach(m => {
            m.classList.remove('show');
        });
    }

    // Delete folder function
    function deleteFolder(folderId, folderName, isLocked) {
        console.log('deleteFolder called with:', {folderId, folderName, isLocked});
        
        // Close any open menus
        $('.folder-actions-menu').removeClass('show');
        
        folderToDelete = {
            id: folderId,
            name: folderName,
            locked: Boolean(isLocked)
        };
        
        console.log('folderToDelete set to:', folderToDelete);
        
        // Reset and show modal
        $('#deletePassword').val('');
        $('#passwordVerification').toggle(isLocked == 1 || isLocked === true);
        $('#deleteFolderMessage').html(`Are you sure you want to delete the folder "<strong>${folderName}</strong>"? This action cannot be undone.`);
        
        $('#deleteFolderModal').modal('show');
    }

</script>
</body>
</html>

<?php
// Helper functions
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'pdf',
        'doc' => 'word',
        'docx' => 'word',
        'xls' => 'excel',
        'xlsx' => 'excel',
        'ppt' => 'powerpoint',
        'pptx' => 'powerpoint',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'zip' => 'archive',
        'rar' => 'archive'
    ];
    
    return $icons[strtolower($fileType)] ?? 'file';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>