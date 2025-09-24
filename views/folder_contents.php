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

    // Get user employee ID for permission checks - FIXED VERSION
    $user_emp_id = null;

    // First, try to get the employee ID from the users table (this is the correct approach)
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

    // Store for consistent use throughout the script
    $_SESSION['user_emp_id'] = $user_emp_id;

    // Debug logging (remove in production)
    error_log("User Emp ID determined: " . $user_emp_id . " for user ID: " . $_SESSION['user_id']);

    // Get folder ID and section ID from URL parameters
    $folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : '';
    $section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';

    if (empty($folder_id) || !is_numeric($folder_id)) {
        header("Location: section_files.php?section_id=" . $section_id);
        exit();
    }

    // Fetch folder details
    $stmt = $db->prepare("SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                s.section_name,
                                s.section_code
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        LEFT JOIN section s ON f.section_id = s.section_id
                        WHERE f.folder_id = ?");
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: section_files.php?section_id=" . $section_id);
        exit();
    }
    
    $folder = $result->fetch_assoc();
    $folder_name = $folder['folder_name'];
    $is_locked = $folder['is_locked'];
    
    // Check if folder is locked and user has access
    if ($is_locked && !isset($_SESSION['unlocked_folders'][$folder_id])) {
        header("Location: section_files.php?section_id=" . $section_id);
        exit();
    }

    // Fetch breadcrumb trail
    $breadcrumbs = [];
    $current_folder_id = $folder_id;
    
    while ($current_folder_id) {
        $stmt = $db->prepare("SELECT f.folder_id, f.folder_name, f.parent_folder_id, 
                                     COALESCE(s.section_name, 'Manager\'s Office') as location_name
                              FROM folders f 
                              LEFT JOIN section s ON f.section_id = s.section_id
                              WHERE f.folder_id = ?");
        $stmt->bind_param("i", $current_folder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $crumb = $result->fetch_assoc();
            $breadcrumbs[] = $crumb;
            $current_folder_id = $crumb['parent_folder_id'];
        } else {
            break;
        }
    }
    
    $breadcrumbs = array_reverse($breadcrumbs);

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
        // Use the already determined user_emp_id from above
        
        $upload_results = [];
        $has_success = false;
        $has_error = false;

        // Process multiple files
        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $file_count = count($_FILES['files']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = basename($_FILES['files']['name'][$i]);
                    $file_size = $_FILES['files']['size'][$i];
                    $file_tmp = $_FILES['files']['tmp_name'][$i];
                    $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                    $description = trim($_POST['description'] ?? '');
                    
                    // Validate file size (max 500MB)
                    $max_size = 500 * 1024 * 1024;
                    if ($file_size > $max_size) {
                        $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'File size exceeds 500MB limit.'];
                        $has_error = true;
                        continue;
                    }
                    
                    // Generate unique filename
                    $unique_name = uniqid() . '_' . time() . '_' . $i . '.' . $file_type;
                    $upload_dir = '../uploads/';
                    $file_path = $upload_dir . $unique_name;
                    
                    // Create uploads directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Check if description column exists in files table
                        $check_column = $db->query("SHOW COLUMNS FROM files LIKE 'description'");
                        $description_column_exists = $check_column->num_rows > 0;
                        
                        if ($description_column_exists) {
                            // Insert file record into database with description
                            $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, description, section_id, folder_id, uploaded_by) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $section_id_value = ($section_id === 'manager') ? NULL : $section_id;
                            $stmt->bind_param("ssssisii", $file_name, $unique_name, $file_type, $file_size, $description, $section_id_value, $folder_id, $user_emp_id);
                        } else {
                            // Insert file record without description column
                            $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, section_id, folder_id, uploaded_by) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $section_id_value = ($section_id === 'manager') ? NULL : $section_id;
                            $stmt->bind_param("sssiiii", $file_name, $unique_name, $file_type, $file_size, $section_id_value, $folder_id, $user_emp_id);
                        }
                        
                        if ($stmt->execute()) {
                            $file_id = $db->insert_id;
                            
                            // Log activity (check if file_activity_logs table exists)
                            $check_table = $db->query("SHOW TABLES LIKE 'file_activity_logs'");
                            if ($check_table->num_rows > 0) {
                                $log_stmt = $db->prepare("INSERT INTO file_activity_logs (file_id, emp_id, activity_type, description, ip_address) 
                                                        VALUES (?, ?, 'uploaded', ?, ?)");
                                $log_description = "File '{$file_name}' uploaded to folder '{$folder_name}'";
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $log_stmt->bind_param("iiss", $file_id, $user_emp_id, $log_description, $ip);
                                $log_stmt->execute();
                            }
                            
                            $upload_results[] = ['file' => $file_name, 'success' => true, 'message' => 'Uploaded successfully!'];
                            $has_success = true;
                        } else {
                            // Remove uploaded file if database insert fails
                            unlink($file_path);
                            $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'Failed to save file record: ' . $db->error];
                            $has_error = true;
                        }
                    } else {
                        $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'Failed to upload file.'];
                        $has_error = true;
                    }
                } else {
                    // Handle file upload errors...
                    // (keep your existing error handling code here)
                }
            }
            
            // Prepare response message
            $success_count = count(array_filter($upload_results, function($result) {
                return $result['success'];
            }));
            
            $total_count = count($upload_results);
            $message = "Uploaded {$success_count} out of {$total_count} files.";
            
            echo json_encode([
                'success' => $has_success || !$has_error,
                'message' => $message,
                'results' => $upload_results
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No files selected or upload error.']);
        }
        exit();
    }

    // Handle file deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_files') {
        if (isset($_POST['file_ids']) && is_array($_POST['file_ids'])) {
            $deleted_count = 0;
            $error_count = 0;
            
            foreach ($_POST['file_ids'] as $file_id) {
                // Get file details before deletion
                $file_stmt = $db->prepare("SELECT file_path FROM files WHERE file_id = ?");
                $file_stmt->bind_param("i", $file_id);
                $file_stmt->execute();
                $file_result = $file_stmt->get_result();
                
                if ($file_result->num_rows > 0) {
                    $file = $file_result->fetch_assoc();
                    $file_path = '../uploads/' . $file['file_path'];
                    
                    // Delete file record from database
                    $delete_stmt = $db->prepare("DELETE FROM files WHERE file_id = ?");
                    $delete_stmt->bind_param("i", $file_id);
                    
                    if ($delete_stmt->execute()) {
                        // Delete physical file
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        $deleted_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                $_SESSION['success'] = "Successfully deleted {$deleted_count} file(s).";
            }
            if ($error_count > 0) {
                $_SESSION['error'] = "Failed to delete {$error_count} file(s).";
            }
            
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
    }

    // Handle folder deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_folder') {
        $folder_to_delete = $_POST['folder_id'];
        
        // Check if folder exists and user has permission
        $check_stmt = $db->prepare("SELECT f.*, s.section_id FROM folders f 
                                LEFT JOIN section s ON f.section_id = s.section_id 
                                WHERE f.folder_id = ?");
        $check_stmt->bind_param("i", $folder_to_delete);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $folder_data = $check_result->fetch_assoc();
            
            // Recursive function to delete folder and its contents
            function deleteFolderRecursive($db, $folder_id) {
                // Delete all files in this folder
                $files_stmt = $db->prepare("SELECT file_id, file_path FROM files WHERE folder_id = ?");
                $files_stmt->bind_param("i", $folder_id);
                $files_stmt->execute();
                $files_result = $files_stmt->get_result();
                
                while ($file = $files_result->fetch_assoc()) {
                    $file_path = '../uploads/' . $file['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    $delete_file_stmt = $db->prepare("DELETE FROM files WHERE file_id = ?");
                    $delete_file_stmt->bind_param("i", $file['file_id']);
                    $delete_file_stmt->execute();
                }
                
                // Get all subfolders
                $subfolders_stmt = $db->prepare("SELECT folder_id FROM folders WHERE parent_folder_id = ?");
                $subfolders_stmt->bind_param("i", $folder_id);
                $subfolders_stmt->execute();
                $subfolders_result = $subfolders_stmt->get_result();
                
                // Recursively delete subfolders
                while ($subfolder = $subfolders_result->fetch_assoc()) {
                    deleteFolderRecursive($db, $subfolder['folder_id']);
                }
                
                // Delete the folder itself
                $delete_folder_stmt = $db->prepare("DELETE FROM folders WHERE folder_id = ?");
                $delete_folder_stmt->bind_param("i", $folder_id);
                return $delete_folder_stmt->execute();
            }
            
            if (deleteFolderRecursive($db, $folder_to_delete)) {
                $_SESSION['success'] = 'Folder deleted successfully!';
            } else {
                $_SESSION['error'] = 'Failed to delete folder.';
            }
            
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
    }

    // Handle folder edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_folder') {
        $folder_to_edit = $_POST['folder_id'];
        $new_folder_name = trim($_POST['folder_name']);
        $new_description = trim($_POST['description'] ?? '');
        $new_password = trim($_POST['password'] ?? '');
        
        if (empty($new_folder_name)) {
            $_SESSION['error'] = 'Folder name is required.';
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
        
        // Check if folder name already exists in the same parent
        $check_stmt = $db->prepare("SELECT folder_id FROM folders WHERE folder_name = ? AND parent_folder_id = ? AND folder_id != ?");
        $check_stmt->bind_param("sii", $new_folder_name, $folder_id, $folder_to_edit);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = 'A folder with this name already exists in the current location.';
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
        
        // Update folder
        $is_locked = !empty($new_password) ? 1 : 0;
        $hashed_password = !empty($new_password) ? password_hash($new_password, PASSWORD_DEFAULT) : null;
        
        if (!empty($new_password)) {
            $update_stmt = $db->prepare("UPDATE folders SET folder_name = ?, description = ?, is_locked = ?, password = ? WHERE folder_id = ?");
            $update_stmt->bind_param("ssisi", $new_folder_name, $new_description, $is_locked, $hashed_password, $folder_to_edit);
        } else {
            $update_stmt = $db->prepare("UPDATE folders SET folder_name = ?, description = ? WHERE folder_id = ?");
            $update_stmt->bind_param("ssi", $new_folder_name, $new_description, $folder_to_edit);
        }
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = 'Folder updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update folder: ' . $db->error;
        }
        
        header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
        exit();
    }
    // Fetch subfolders
    $subfolders_stmt = $db->prepare("SELECT f.*, 
                                            CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                            (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                            (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                                    FROM folders f 
                                    LEFT JOIN employee e ON f.created_by = e.emp_id 
                                    WHERE f.parent_folder_id = ? 
                                    ORDER BY f.folder_name");
    $subfolders_stmt->bind_param("i", $folder_id);
    $subfolders_stmt->execute();
    $subfolders_result = $subfolders_stmt->get_result();
    $subfolders = [];
    while ($row = $subfolders_result->fetch_assoc()) {
        $subfolders[] = $row;
    }

    // Fetch files in this folder
    $files_stmt = $db->prepare("SELECT f.*, 
                                       CONCAT(e.first_name, ' ', e.last_name) as uploaded_by
                                FROM files f
                                LEFT JOIN employee e ON f.uploaded_by = e.emp_id
                                WHERE f.folder_id = ?
                                ORDER BY f.created_at DESC");
    $files_stmt->bind_param("i", $folder_id);
    $files_stmt->execute();
    $files_result = $files_stmt->get_result();
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
    <title><?= htmlspecialchars($folder_name) ?> - Contents</title>
    
    <?php include '../includes/header.php'; ?>
    
    <style>
        .file-explorer {
            display: flex;
            min-height: 600px;
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
        
        .folder-item, .folder-item-grid {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }
        
        .folder-item:hover, .folder-item-grid:hover {
            background-color: #e9ecef;
        }
        
        .folder-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: #ffc107;
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
        
        .upload-progress {
            display: none;
            margin-top: 10px;
        }

        .file-checkbox {
            transform: scale(1.2);
            cursor: pointer;
        }

        #selectAllHeader {
            transform: scale(1.2);
            cursor: pointer;
        }

        #selectAll {
            transform: scale(1.2);
            cursor: pointer;
        }

        .btn-group .btn {
            margin: 0 2px;
        }
        .file-explorer {
            display: flex;
            min-height: 600px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }

        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #dee2e6;
            padding: 20px;
            overflow-y: auto;
        }

        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background: white;
        }

        .folder-item, .folder-item-grid {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #e9ecef;
        }

        .folder-item:hover, .folder-item-grid:hover {
            background-color: #f8f9fa;
            border-color: #4361ee;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .folder-icon {
            font-size: 1.8rem;
            margin-right: 15px;
            color: #ffc107;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .folder-item-grid {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            height: 180px;
            justify-content: center;
        }

        .folder-item-grid:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .file-icon {
            font-size: 3.5rem;
            color: #4361ee;
            margin-bottom: 15px;
        }

        .folder-icon-grid {
            font-size: 3.5rem;
            color: #ffc107;
            margin-bottom: 15px;
        }

        .locked-badge {
            background: #dc3545;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }

        .current-folder {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 25px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .upload-progress {
            display: none;
            margin-top: 15px;
        }

        .file-checkbox {
            transform: scale(1.3);
            cursor: pointer;
        }

        #selectAllHeader {
            transform: scale(1.3);
            cursor: pointer;
        }

        #selectAll {
            transform: scale(1.3);
            cursor: pointer;
        }

        .btn-group .btn {
            margin: 0 3px;
            border-radius: 6px;
        }

        .folder-actions .dropdown-toggle {
            border: none;
            background: rgba(255,255,255,0.9);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .folder-item-grid:hover .folder-actions .dropdown-toggle {
            opacity: 1;
        }

        .folder-stats {
            font-weight: 600;
            color: #495057;
            margin: 8px 0;
        }

        .folder-creator {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 25px;
        }

        .card-title {
            margin-bottom: 0;
            font-weight: 600;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .badge-warning {
            background: linear-gradient(45deg, #ffc107, #ff8c00);
            color: white;
        }

        .folder-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
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

        /* For sidebar folder items */
        .folder-item {
            position: relative;
            padding-right: 40px; /* Make space for the actions button */
        }

        .folder-item .folder-actions {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
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
                        <h1 class="m-0"><?= htmlspecialchars($folder_name) ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="file_management.php">File Management</a></li>
                            <li class="breadcrumb-item"><a href="section_files.php?section_id=<?= $section_id ?>"><?= htmlspecialchars($folder['section_name'] ?? 'Manager\'s Office') ?></a></li>
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <?php if ($index < count($breadcrumbs) - 1): ?>
                                    <li class="breadcrumb-item"><a href="folder_contents.php?folder_id=<?= $crumb['folder_id'] ?>&section_id=<?= $section_id ?>"><?= htmlspecialchars($crumb['folder_name']) ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['folder_name']) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
                            <i class="fas fa-folder-open mr-2"></i>
                            Folder Contents
                        </h3>
                        <div class="card-tools">
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#uploadFileModal">
                                <i class="fas fa-upload mr-1"></i> Upload File
                            </button>
                            <button class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#createFolderModal">
                                <i class="fas fa-folder-plus mr-1"></i> New Folder
                            </button>
                            <a href="section_files.php?section_id=<?= $section_id ?>" class="btn btn-secondary btn-sm ml-1">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="file-explorer">
                            <!-- Sidebar with folder tree -->
                            <div class="sidebar">
                                <h5><i class="fas fa-folder-tree mr-2"></i>Subfolders</h5>
                                <div id="folderTree">
                                    <?php foreach ($subfolders as $subfolder): ?>
                                        <div class="folder-item <?= $subfolder['is_locked'] ? 'locked' : '' ?>" 
                                            data-folder-id="<?= $subfolder['folder_id'] ?>"
                                            data-locked="<?= $subfolder['is_locked'] ?>">
                                            <i class="fas fa-folder folder-icon <?= $subfolder['is_locked'] ? 'locked' : '' ?>"></i>
                                            <div class="folder-info">
                                                <div class="folder-name">
                                                    <?= htmlspecialchars($subfolder['folder_name']) ?>
                                                    <?php if ($subfolder['is_locked']): ?>
                                                        <span class="locked-badge">Locked</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="folder-stats">
                                                    <?= $subfolder['file_count'] ?> files, 
                                                    <?= $subfolder['subfolder_count'] ?> subfolders
                                                </div>
                                            </div>
                                            <!-- Add folder actions dropdown -->
                                            <div class="folder-actions">
                                                <button class="folder-actions-btn" onclick="toggleFolderMenu(this, event)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="folder-actions-menu">
                                                    <button class="folder-action-item edit" 
                                                            onclick="editFolder(<?= $subfolder['folder_id'] ?>, 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['description'] ?? '')) ?>', 
                                                                            <?= $subfolder['is_locked'] ?>)">
                                                        <i class="fas fa-edit mr-2"></i>Edit
                                                    </button>
                                                    <button class="folder-action-item delete" 
                                                            onclick="deleteFolder(<?= $subfolder['folder_id'] ?>, 
                                                                                '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                                <?= $subfolder['is_locked'] ?>)">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($subfolders)): ?>
                                        <div class="text-muted text-center py-3">
                                            <i class="fas fa-folder-open fa-2x mb-2"></i>
                                            <p>No subfolders</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Main content area -->
                            <div class="main-content">
                                <div class="current-folder">
                                    <i class="fas fa-folder-open"></i> <?= htmlspecialchars($folder_name) ?>
                                    <?php if ($is_locked): ?>
                                        <span class="badge badge-warning ml-2"><i class="fas fa-lock"></i> Protected</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Subfolders Grid -->
                                <?php if (!empty($subfolders)): ?>
                                    <h5>Subfolders (<?= count($subfolders) ?>)</h5>
                                    <div class="file-grid">
                                        <?php foreach ($subfolders as $subfolder): ?>
                                            <div class="folder-item-grid position-relative" 
                                                data-folder-id="<?= $subfolder['folder_id'] ?>"
                                                data-locked="<?= $subfolder['is_locked'] ?>">
                                                
                                                <!-- Folder actions -->
                                                <div class="folder-actions position-absolute" style="top: 10px; right: 10px;">
                                                    <button class="folder-actions-btn" onclick="toggleFolderMenu(this, event)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="folder-actions-menu">
                                                        <button class="folder-action-item edit" 
                                                                onclick="editFolder(<?= $subfolder['folder_id'] ?>, 
                                                                                '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                                '<?= htmlspecialchars(addslashes($subfolder['description'] ?? '')) ?>', 
                                                                                <?= $subfolder['is_locked'] ?>)">
                                                            <i class="fas fa-edit mr-2"></i>Edit
                                                        </button>
                                                        <button class="folder-action-item delete" 
                                                                onclick="deleteFolder(<?= $subfolder['folder_id'] ?>, 
                                                                                    '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                                    <?= $subfolder['is_locked'] ?>)">
                                                            <i class="fas fa-trash mr-2"></i>Delete
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <i class="fas fa-folder folder-icon-grid <?= $subfolder['is_locked'] ? 'text-danger' : '' ?>"></i>
                                                <div class="folder-name">
                                                    <?= htmlspecialchars($subfolder['folder_name']) ?>
                                                    <?php if ($subfolder['is_locked']): ?>
                                                        <br><span class="badge badge-danger">Locked</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="folder-stats small text-muted">
                                                    <?= $subfolder['file_count'] ?> files
                                                    <?php if ($subfolder['subfolder_count'] > 0): ?>
                                                        , <?= $subfolder['subfolder_count'] ?> subfolders
                                                    <?php endif; ?>
                                                </div>
                                                <div class="folder-creator small text-muted">
                                                    Created by: <?= htmlspecialchars($subfolder['creator_name'] ?? 'Unknown') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Files Grid -->
                                <h5 class="mt-4">Files (<?= count($files) ?>)</h5>
                                <?php if (empty($files)): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No files in this folder.
                                    </div>
                                <?php else: ?>
                                    <form id="deleteFilesForm" method="POST" action="folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>">
                                        <input type="hidden" name="action" value="delete_files">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <input type="checkbox" id="selectAll" class="mr-2">
                                                <label for="selectAll">Select All</label>
                                            </div>
                                            <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>
                                                <i class="fas fa-trash mr-1"></i> Delete Selected
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th width="30px"><input type="checkbox" id="selectAllHeader"></th>
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
                                                            <input type="checkbox" name="file_ids[]" value="<?= $file['file_id'] ?>" class="file-checkbox">
                                                        </td>
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
                                                                <button type="button" class="btn btn-danger btn-sm delete-single-file" 
                                                                        data-file-id="<?= $file['file_id'] ?>" 
                                                                        data-file-name="<?= htmlspecialchars($file['file_name']) ?>" 
                                                                        title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </form>
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

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload File to <?= htmlspecialchars($folder_name) ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="fileInput">Select Files *</label>
                        <input type="file" class="form-control-file" id="fileInput" name="files[]" multiple required>
                        <small class="form-text text-muted">Maximum file size: 500MB per file. You can select multiple files.</small>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="progress-text">0%</small>
                        </div>
                        <div id="fileList" class="mt-2"></div>
                    </div>
                    <div class="form-group">
                        <label for="fileDescription">Description (Optional)</label>
                        <textarea class="form-control" id="fileDescription" name="description" rows="3" placeholder="Optional file description"></textarea>
                    </div>
                    <input type="hidden" name="action" value="upload_file">
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Subfolder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="folderForm" method="POST" action="create_folder.php">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <input type="hidden" name="parent_folder_id" value="<?= $folder_id ?>">
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

<!-- Edit Folder Modal -->
<div class="modal fade" id="editFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editFolderForm" method="POST" action="folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>">
                <input type="hidden" name="action" value="edit_folder">
                <input type="hidden" name="folder_id" id="editFolderId">
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
                        <label for="editPassword">New Password (Optional - leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editPassword" name="password" 
                               placeholder="Enter new password to change">
                        <small class="form-text text-muted">If set, this will change the folder password.</small>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="removePassword">
                        <label class="form-check-label" for="removePassword">Remove password protection</label>
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

<?php include '../includes/footer.php'; ?>

<script>
// Wait for jQuery to be loaded
function initializeUpload() {
    $(document).ready(function() {
        // File input change handler for multiple files
        $('#fileInput').change(function() {
            const files = this.files;
            const fileList = $('#fileList');
            fileList.empty();
            
            if (files.length > 0) {
                fileList.append('<strong>Selected files:</strong><br>');
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    // Validate file size (500MB)
                    const maxSize = 500 * 1024 * 1024;
                    if (file.size > maxSize) {
                        fileList.append('<span class="text-danger">' + file.name + ' (Too large)</span><br>');
                    } else {
                        fileList.append('<span class="text-success">' + file.name + ' (' + formatFileSize(file.size) + ')</span><br>');
                    }
                }
            }
        });

        // Upload multiple files functionality
        $('#uploadForm').submit(function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = $('#uploadBtn');
            const originalText = uploadBtn.html();
            const files = $('#fileInput')[0].files;
            
            if (files.length === 0) {
                Swal.fire({
                    title: 'No Files Selected',
                    text: 'Please select at least one file to upload.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading state
            uploadBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
            $('#uploadProgress').show();
            
            $.ajax({
                url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = (evt.loaded / evt.total) * 100;
                            $('.progress-bar').css('width', percentComplete + '%');
                            $('.progress-text').text(percentComplete.toFixed(1) + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    uploadBtn.prop('disabled', false).html(originalText);
                    $('#uploadProgress').hide();
                    
                    try {
                        const result = JSON.parse(response);
                        if (result.success || result.results) {
                            // Show detailed results
                            let message = result.message || 'Upload completed!';
                            let html = '<div class="text-left"><strong>' + message + '</strong><br><br>';
                            
                            if (result.results) {
                                result.results.forEach(function(fileResult) {
                                    const icon = fileResult.success ? '✓' : '✗';
                                    const color = fileResult.success ? 'text-success' : 'text-danger';
                                    html += '<span class="' + color + '">' + icon + ' ' + fileResult.file + ': ' + fileResult.message + '</span><br>';
                                });
                            }
                            
                            html += '</div>';
                            
                            Swal.fire({
                                title: result.success ? 'Success!' : 'Upload Completed',
                                html: html,
                                icon: result.success ? 'success' : 'info',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                $('#uploadFileModal').modal('hide');
                                $('#uploadForm')[0].reset();
                                $('#fileList').empty();
                                if (result.success) {
                                    location.reload();
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: result.message || 'Upload failed',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    } catch (e) {
                        console.error('Parse error:', e, 'Response:', response);
                        Swal.fire({
                            title: 'Error!',
                            text: 'Invalid server response. Please check console for details.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    uploadBtn.prop('disabled', false).html(originalText);
                    $('#uploadProgress').hide();
                    console.error('AJAX error:', error, 'Status:', status, 'xhr:', xhr);
                    Swal.fire({
                        title: 'Upload Failed!',
                        text: 'Failed to upload files: ' + error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    });
}

// Folder click handler - updated to ignore actions buttons
$(document).on('click', '.folder-item, .folder-item-grid', function(e) {
    // Check if the click was on the actions button or menu
    if ($(e.target).closest('.folder-actions, .folder-actions-btn, .folder-actions-menu').length > 0) {
        return; // Don't navigate if clicking on actions
    }
    
    const folderId = $(this).data('folder-id');
    const isLocked = $(this).data('locked') == 1;
    
    if (isLocked) {
        $('#unlockFolderId').val(folderId);
        $('#unlockFolderModal').modal('show');
    } else {
        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
    }
});

// Initialize when jQuery is available
if (window.jQuery) {
    initializeUpload();
} else {
    // Wait for jQuery to load
    var script = document.createElement('script');
    script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
    script.onload = initializeUpload;
    document.head.appendChild(script);
}
// Delete functionality
$(document).ready(function() {
    // Select all checkboxes
    $('#selectAllHeader, #selectAll').change(function() {
        const isChecked = $(this).prop('checked');
        $('.file-checkbox').prop('checked', isChecked);
        updateDeleteButton();
    });

    // Individual checkbox change
    $(document).on('change', '.file-checkbox', function() {
        updateDeleteButton();
        // Update select all checkbox state
        const totalCheckboxes = $('.file-checkbox').length;
        const checkedCheckboxes = $('.file-checkbox:checked').length;
        $('#selectAllHeader, #selectAll').prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
    });

    // Update delete button state
    function updateDeleteButton() {
        const checkedCount = $('.file-checkbox:checked').length;
        $('#deleteSelectedBtn').prop('disabled', checkedCount === 0);
        if (checkedCount > 0) {
            $('#deleteSelectedBtn').html('<i class="fas fa-trash mr-1"></i> Delete Selected (' + checkedCount + ')');
        } else {
            $('#deleteSelectedBtn').html('<i class="fas fa-trash mr-1"></i> Delete Selected');
        }
    }

    // Delete selected files
    $('#deleteSelectedBtn').click(function() {
        const checkedCount = $('.file-checkbox:checked').length;
        if (checkedCount === 0) return;

        Swal.fire({
            title: 'Are you sure?',
            text: 'You are about to delete ' + checkedCount + ' file(s). This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteFilesForm').submit();
            }
        });
    });

    // Delete single file
    $(document).on('click', '.delete-single-file', function() {
        const fileId = $(this).data('file-id');
        const fileName = $(this).data('file-name');

        Swal.fire({
            title: 'Are you sure?',
            text: 'You are about to delete "' + fileName + '". This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a temporary form for single file deletion
                const form = $('<form>').attr({
                    method: 'POST',
                    action: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>'
                });
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'action',
                    value: 'delete_files'
                }).appendTo(form);
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'file_ids[]',
                    value: fileId
                }).appendTo(form);
                
                form.appendTo('body').submit();
            }
        });
    });
});

// Folder operations
$(document).ready(function() {
    // Edit folder button click
    $(document).on('click', '.edit-folder-btn', function(e) {
        e.stopPropagation();
        
        const folderId = $(this).data('folder-id');
        const folderName = $(this).data('folder-name');
        const description = $(this).data('description');
        const isLocked = $(this).data('is-locked');
        
        $('#editFolderId').val(folderId);
        $('#editFolderName').val(folderName);
        $('#editDescription').val(description);
        $('#editPassword').val('');
        $('#removePassword').prop('checked', false);
        
        if (isLocked == 1) {
            $('#editPassword').attr('placeholder', 'Enter new password to change current one');
        } else {
            $('#editPassword').attr('placeholder', 'Add password to protect folder');
        }
        
        $('#editFolderModal').modal('show');
    });
    
    // Remove password checkbox handler
    $('#removePassword').change(function() {
        if ($(this).prop('checked')) {
            $('#editPassword').prop('disabled', true).val('').attr('placeholder', 'Password will be removed');
        } else {
            $('#editPassword').prop('disabled', false);
            if ($('#editFolderId').val()) {
                const isLocked = $('.edit-folder-btn[data-folder-id="' + $('#editFolderId').val() + '"]').data('is-locked');
                $('#editPassword').attr('placeholder', isLocked == 1 ? 
                    'Enter new password to change current one' : 'Add password to protect folder');
            }
        }
    });
    
    // Delete folder button click
    $(document).on('click', '.delete-folder-btn', function(e) {
        e.stopPropagation();
        
        const folderId = $(this).data('folder-id');
        const folderName = $(this).data('folder-name');
        
        Swal.fire({
            title: 'Are you sure?',
            html: `You are about to delete the folder "<strong>${folderName}</strong>" and all its contents (files and subfolders).<br><br>This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_folder&folder_id=${folderId}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'The folder has been deleted.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            }
        });
    });
    
    // Edit folder form submission
    $('#editFolderForm').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        if ($('#removePassword').prop('checked')) {
            formData += '&password=';
        }
        
        $.ajax({
            url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#editFolderModal').modal('hide');
                Swal.fire({
                    title: 'Success!',
                    text: 'Folder updated successfully!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to update folder: ' + error,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
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
function editFolder(folderId, folderName, description, isLocked) {
    $('#editFolderId').val(folderId);
    $('#editFolderName').val(folderName);
    $('#editDescription').val(description);
    $('#editPassword').val('');
    $('#removePassword').prop('checked', false);
    
    if (isLocked == 1) {
        $('#editPassword').attr('placeholder', 'Enter new password to change current one');
    } else {
        $('#editPassword').attr('placeholder', 'Add password to protect folder');
    }
    
    $('#editFolderModal').modal('show');
    
    // Close the menu
    document.querySelectorAll('.folder-actions-menu').forEach(m => {
        m.classList.remove('show');
    });
}

// Delete folder function
function deleteFolder(folderId, folderName, isLocked) {
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete the folder "<strong>${folderName}</strong>" and all its contents (files and subfolders).<br><br>This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_folder&folder_id=${folderId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleted!',
                text: 'The folder has been deleted.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                location.reload();
            });
        }
    });
    
    // Close the menu
    document.querySelectorAll('.folder-actions-menu').forEach(m => {
        m.classList.remove('show');
    });
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