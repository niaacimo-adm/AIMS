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
        // If no user record found, use session user_id as fallback
        $user_emp_id = $_SESSION['user_id'];
        error_log("Using session user_id as fallback employee ID: " . $user_emp_id);
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
        if (!canPerformAction($db, $folder_id, $user_emp_id, 'upload_files')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to upload files to this folder.']);
            exit();
        }
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
         if (!canPerformAction($db, $folder_id, $user_emp_id, 'delete_files')) {
                $_SESSION['error'] = "You do not have permission to delete files from this folder.";
                header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
                exit();
            }
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
        
        if (!canPerformAction($db, $folder_to_delete, $user_emp_id, 'delete_folder')) {
            $_SESSION['error'] = "You do not have permission to delete this folder.";
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
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
        if (!canPerformAction($db, $folder_to_edit, $user_emp_id, 'edit_folder')) {
            $_SESSION['error'] = "You do not have permission to edit this folder.";
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }
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

    // Handle getting shares for management
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_shares') {
        $folder_id = $_POST['folder_id'];
        
        if (!hasFolderPermission($db, $folder_id, $user_emp_id, 'view')) {
            echo '<div class="alert alert-danger text-center">You do not have permission to view this folder.</div>';
            exit();
        }
        
        // Fetch existing shares for this folder
        try {
            $shares_stmt = $db->prepare("SELECT fs.*, 
                                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                                                e.email as employee_email
                                        FROM folder_shares fs
                                        JOIN employee e ON fs.shared_with_emp_id = e.emp_id
                                        WHERE fs.folder_id = ? AND fs.is_active = TRUE
                                        ORDER BY fs.created_at DESC");
            $shares_stmt->bind_param("i", $folder_id);
            $shares_stmt->execute();
            $shares_result = $shares_stmt->get_result();
            $folder_shares = [];
            while ($row = $shares_result->fetch_assoc()) {
                $folder_shares[] = $row;
            }
        } catch (Exception $e) {
            error_log("Folder shares query error: " . $e->getMessage());
            $folder_shares = [];
        }
        
        if (empty($folder_shares)) {
            echo '<div class="alert alert-info text-center"><i class="fas fa-info-circle mr-2"></i>This folder is not shared with anyone.</div>';
        } else {
            $can_manage = canPerformAction($db, $folder_id, $user_emp_id, 'manage_shares');
            echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Employee</th><th>Permission</th><th>Expires</th><th>Actions</th></tr></thead><tbody>';
            foreach ($folder_shares as $share) {
                $badge_class = getPermissionBadgeClass($share['permission_level']);
                echo '<tr>
                    <td>
                        <div class="font-weight-bold">' . htmlspecialchars($share['employee_name']) . '</div>
                        <small class="text-muted">' . htmlspecialchars($share['employee_email'] ?? '') . '</small>
                    </td>
                    <td>';
                
                if ($can_manage) {
                    echo '<select class="form-control form-control-sm permission-select" data-share-id="' . $share['share_id'] . '">
                        <option value="view" ' . ($share['permission_level'] == 'view' ? 'selected' : '') . '>View Only</option>
                        <option value="upload" ' . ($share['permission_level'] == 'upload' ? 'selected' : '') . '>Upload</option>
                        <option value="edit" ' . ($share['permission_level'] == 'edit' ? 'selected' : '') . '>Edit</option>
                        <option value="manage" ' . ($share['permission_level'] == 'manage' ? 'selected' : '') . '>Manage</option>
                    </select>';
                } else {
                    echo '<span class="badge badge-' . $badge_class . '">' . ucfirst($share['permission_level']) . '</span>';
                }
                
                echo '</td>
                    <td>';
                
                if ($can_manage) {
                    $expires_value = $share['expires_at'] ? date('Y-m-d\TH:i', strtotime($share['expires_at'])) : '';
                    echo '<input type="datetime-local" class="form-control form-control-sm expiry-input" 
                        data-share-id="' . $share['share_id'] . '" value="' . $expires_value . '">';
                } else {
                    echo $share['expires_at'] ? date('M j, Y H:i', strtotime($share['expires_at'])) : 'Never';
                }
                
                echo '</td>
                    <td>';
                
                if ($can_manage) {
                    echo '<button class="btn btn-success btn-sm update-share mr-1" 
                            data-share-id="' . $share['share_id'] . '" 
                            data-employee-name="' . htmlspecialchars($share['employee_name']) . '">
                        <i class="fas fa-save"></i> Update
                    </button>';
                }
                
                echo '<button class="btn btn-danger btn-sm revoke-access" 
                        data-share-id="' . $share['share_id'] . '" 
                        data-employee-name="' . htmlspecialchars($share['employee_name']) . '">
                    <i class="fas fa-times"></i> ' . ($can_manage ? 'Revoke' : 'Remove') . '
                </button>
                </td>
                </tr>';
            }
            echo '</tbody></table></div>';
            
            if ($can_manage) {
                echo '<div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i>
                    You can update permissions and expiry dates for shared access. Changes are saved immediately when you click "Update".
                </div>';
            }
        }
        exit();
    }

    // Handle folder sharing - create new share
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_folder') {
        $folder_id = $_POST['folder_id'];
        $employee_ids = $_POST['employee_ids'] ?? [];
        $permission_level = $_POST['permission_level'] ?? 'view';
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        // Validate inputs
        if (empty($folder_id) || !is_numeric($folder_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid folder ID.']);
            exit();
        }
        
        if (!canPerformAction($db, $folder_id, $user_emp_id, 'share_folder')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to share this folder.']);
            exit();
        }
        
        if (empty($employee_ids)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one employee to share with.']);
            exit();
        }
        
        // Ensure employee_ids is an array
        if (!is_array($employee_ids)) {
            $employee_ids = [$employee_ids];
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($employee_ids as $emp_id) {
            // Validate employee ID
            if (empty($emp_id) || !is_numeric($emp_id)) {
                $error_count++;
                continue;
            }
            
            // Check if share already exists and is active
            $check_stmt = $db->prepare("SELECT share_id FROM folder_shares WHERE folder_id = ? AND shared_with_emp_id = ? AND is_active = TRUE");
            $check_stmt->bind_param("ii", $folder_id, $emp_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Insert new share - use correct column name shared_by_emp_id
                $insert_stmt = $db->prepare("INSERT INTO folder_shares (folder_id, shared_by_emp_id, shared_with_emp_id, permission_level, expires_at) VALUES (?, ?, ?, ?, ?)");
                
                // Handle null expiry date
                if (empty($expires_at)) {
                    $insert_stmt->bind_param("iiiss", $folder_id, $user_emp_id, $emp_id, $permission_level, $expires_at);
                } else {
                    $insert_stmt->bind_param("iiiss", $folder_id, $user_emp_id, $emp_id, $permission_level, $expires_at);
                }
                
                if ($insert_stmt->execute()) {
                    $success_count++;
                    
                    // Log sharing activity
                    try {
                        $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'created', ?, ?)");
                        $log_description = "Folder shared with employee ID: {$emp_id} with {$permission_level} permissions";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                        $log_stmt->execute();
                    } catch (Exception $e) {
                        // Log error but don't fail the main operation
                        error_log("Failed to log folder share activity: " . $e->getMessage());
                    }
                } else {
                    $error_count++;
                    $errors[] = "Failed to share with employee ID: {$emp_id} - " . $db->error;
                }
            } else {
                $error_count++; // Share already exists
            }
        }
        
        if ($success_count > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "Successfully shared folder with {$success_count} employee(s)." . ($error_count > 0 ? " Failed for {$error_count} employee(s)." : "")
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "Failed to share folder. It may already be shared with the selected employees or there was an error."
            ]);
        }
        exit();
    }

    // Handle revoking access
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_access') {
        $share_id = $_POST['share_id'];
        
        $stmt = $db->prepare("UPDATE folder_shares SET is_active = FALSE WHERE share_id = ?");
        $stmt->bind_param("i", $share_id);
        
        if ($stmt->execute()) {
            // Log revoke activity
            $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'deleted', ?, ?)");
            $log_description = "Folder access revoked";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
            $log_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Access revoked successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to revoke access.']);
        }
        exit();
    }

    // Fetch existing shares for this folder
    $shares_stmt = $db->prepare("SELECT fs.*, 
                                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                                        e.email as employee_email
                                FROM folder_shares fs
                                JOIN employee e ON fs.shared_with_emp_id = e.emp_id
                                WHERE fs.folder_id = ? AND fs.is_active = TRUE
                                ORDER BY fs.created_at DESC");
    $shares_stmt->bind_param("i", $folder_id);
    $shares_stmt->execute();
    $shares_result = $shares_stmt->get_result();
    $folder_shares = [];
    while ($row = $shares_result->fetch_assoc()) {
        $folder_shares[] = $row;
    }
    // Fetch all employees for sharing (excluding current user)
    $employees_stmt = $db->prepare("SELECT e.emp_id, 
                                    CONCAT(e.first_name, ' ', e.last_name) as full_name, 
                                    s.section_name as department
                            FROM employee e 
                            LEFT JOIN section s ON e.section_id = s.section_id
                            WHERE e.emp_id != ? 
                            ORDER BY e.first_name, e.last_name");
    $employees_stmt->bind_param("i", $user_emp_id);
    $employees_stmt->execute();
    $employees_result = $employees_stmt->get_result();
    $all_employees = [];
    while ($row = $employees_result->fetch_assoc()) {
        $all_employees[] = $row;
    }
    // Fetch employees from the same section for easier sharing
    $section_employees_stmt = $db->prepare("SELECT e.emp_id, 
                                        CONCAT(e.first_name, ' ', e.last_name) as full_name, 
                                        e.email,
                                        s.section_name as department
                                    FROM employee e 
                                    LEFT JOIN section s ON e.section_id = s.section_id
                                    WHERE e.section_id = ? AND e.emp_id != ?
                                    ORDER BY e.first_name, e.last_name");
    $section_id_value = ($section_id === 'manager') ? NULL : $section_id;
    $section_employees_stmt->bind_param("ii", $section_id_value, $user_emp_id);
    $section_employees_stmt->execute();
    $section_employees_result = $section_employees_stmt->get_result();
    $section_employees = [];
    while ($row = $section_employees_result->fetch_assoc()) {
        $section_employees[] = $row;
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

    function getPermissionBadgeClass($permission) {
        switch ($permission) {
            case 'view': return 'info';
            case 'upload': return 'primary';
            case 'edit': return 'warning';
            case 'manage': return 'success';
            default: return 'secondary';
        }
    }

// Updated permission checking function with proper hierarchy
function hasFolderPermission($db, $folder_id, $user_emp_id, $permission_type = 'view') {
    // Check if user is the creator (has full access)
    $creator_stmt = $db->prepare("SELECT created_by FROM folders WHERE folder_id = ?");
    $creator_stmt->bind_param("i", $folder_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    
    if ($creator_result->num_rows > 0) {
        $folder_data = $creator_result->fetch_assoc();
        if ($folder_data['created_by'] == $user_emp_id) {
            return true; // Creator has full access
        }
    }
    
    // Check shared permissions - use correct column name shared_with_emp_id
    $share_stmt = $db->prepare("SELECT permission_level, expires_at 
                              FROM folder_shares 
                              WHERE folder_id = ? AND shared_with_emp_id = ? AND is_active = TRUE 
                              AND (expires_at IS NULL OR expires_at > NOW())");
    $share_stmt->bind_param("ii", $folder_id, $user_emp_id);
    $share_stmt->execute();
    $share_result = $share_stmt->get_result();
    
    if ($share_result->num_rows > 0) {
        $share_data = $share_result->fetch_assoc();
        
        // Fixed permission hierarchy according to requirements
        $permission_hierarchy = [
            'view' => 1,      // Can only view files
            'upload' => 2,    // Can view and upload files
            'edit' => 3,      // Can view, upload, edit files, and download
            'manage' => 4     // Full access (create/edit/delete folders, manage shares, etc.)
        ];
        
        $required_level = $permission_hierarchy[$permission_type] ?? 0;
        $user_level = $permission_hierarchy[$share_data['permission_level']] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    return false; // No permission found
}

// Helper function to check specific actions based on permission level
function canPerformAction($db, $folder_id, $user_emp_id, $action) {
    // First check if user is creator (full access)
    $creator_stmt = $db->prepare("SELECT created_by FROM folders WHERE folder_id = ?");
    $creator_stmt->bind_param("i", $folder_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    
    if ($creator_result->num_rows > 0) {
        $folder_data = $creator_result->fetch_assoc();
        if ($folder_data['created_by'] == $user_emp_id) {
            return true; // Creator has full access to everything
        }
    }
    
    // Define required permissions for each action according to your requirements
    $permission_map = [
        'view_files' => 'view',           // View files only
        'upload_files' => 'upload',       // Upload files
        'download_files' => 'edit',       // Download requires edit permission
        'edit_files' => 'edit',           // Edit files
        'delete_files' => 'manage',       // Delete files requires manage permission
        'create_folder' => 'manage',      // Create folder requires manage permission
        'edit_folder' => 'manage',        // Edit folder requires manage permission
        'delete_folder' => 'manage',      // Delete folder requires manage permission
        'share_folder' => 'manage',       // Share folder requires manage permission
        'manage_shares' => 'manage',      // Manage shares requires manage permission
        'edit_share' => 'manage'          // Edit share requires manage permission
    ];
    
    $required_permission = $permission_map[$action] ?? 'manage';
    return hasFolderPermission($db, $folder_id, $user_emp_id, $required_permission);
}

// Handle folder access check via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_folder_access') {
    $check_folder_id = $_POST['folder_id'];
    $has_access = hasFolderPermission($db, $check_folder_id, $user_emp_id, 'view');
    echo json_encode(['has_access' => $has_access]);
    exit();
}

// Handle editing share permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_share') {
    $share_id = $_POST['share_id'];
    $permission_level = $_POST['permission_level'] ?? 'view';
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    // Check if user has manage permission for this folder
    $share_stmt = $db->prepare("SELECT folder_id FROM folder_shares WHERE share_id = ?");
    $share_stmt->bind_param("i", $share_id);
    $share_stmt->execute();
    $share_result = $share_stmt->get_result();
    
    if ($share_result->num_rows > 0) {
        $share_data = $share_result->fetch_assoc();
        $folder_id_for_share = $share_data['folder_id'];
        
        if (!canPerformAction($db, $folder_id_for_share, $user_emp_id, 'edit_share')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit shares for this folder.']);
            exit();
        }
        
        // Update the share
        $update_stmt = $db->prepare("UPDATE folder_shares SET permission_level = ?, expires_at = ? WHERE share_id = ?");
        $update_stmt->bind_param("ssi", $permission_level, $expires_at, $share_id);
        
        if ($update_stmt->execute()) {
            // Log the activity
            $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'modified', ?, ?)");
            $log_description = "Share permissions updated to {$permission_level}";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $folder_id_for_share, $user_emp_id, $log_description, $ip);
            $log_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Share permissions updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update share permissions.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Share not found.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($folder_name) ?> - Contents</title>
    
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/folder_content.css">
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
                            <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'upload_files')): ?>
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#uploadFileModal">
                                <i class="fas fa-upload mr-1"></i> Upload File
                            </button>
                            <?php endif; ?>
                            
                            <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'create_folder')): ?>
                            <button class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#createFolderModal">
                                <i class="fas fa-folder-plus mr-1"></i> New Folder
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary btn-sm ml-1" onclick="window.history.back()">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="file-explorer">
                            <!-- Sidebar with folder tree -->
                            <div class="sidebar">
                                <h5><i class="fas fa-folder-tree mr-2"></i>Subfolders</h5>
                                <div id="folderTree">
                                    <?php foreach ($subfolders as $subfolder): 
                                            $is_creator = ($subfolder['created_by'] == $user_emp_id);
                                            $has_access = $is_creator || hasFolderPermission($db, $subfolder['folder_id'], $user_emp_id, 'view');
                                        ?>
                                            <div class="folder-item <?= $subfolder['is_locked'] ? 'locked' : '' ?> <?= !$has_access ? 'no-access' : '' ?>" 
                                                data-folder-id="<?= $subfolder['folder_id'] ?>"
                                                data-locked="<?= $subfolder['is_locked'] ?>"
                                                data-has-access="<?= $has_access ? '1' : '0' ?>"
                                                data-is-creator="<?= $is_creator ? '1' : '0' ?>">
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
                                                    <?php if (canPerformAction($db, $subfolder['folder_id'], $user_emp_id, 'edit_folder')): ?>
                                                    <button class="folder-action-item edit" 
                                                            onclick="editFolder(<?= $subfolder['folder_id'] ?>, 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['description'] ?? '')) ?>', 
                                                                            <?= $subfolder['is_locked'] ?>)">
                                                        <i class="fas fa-edit mr-2"></i>Edit
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $subfolder['folder_id'], $user_emp_id, 'share_folder')): ?>
                                                    <button class="folder-action-item share" 
                                                            onclick="shareFolder(<?= $subfolder['folder_id'] ?>, 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>')">
                                                        <i class="fas fa-share-alt mr-2"></i>Share
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $subfolder['folder_id'], $user_emp_id, 'manage_shares')): ?>
                                                    <button class="folder-action-item manage-shares" 
                                                            onclick="manageShares(<?= $subfolder['folder_id'] ?>, 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>')">
                                                        <i class="fas fa-users mr-2"></i>Manage Access
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $subfolder['folder_id'], $user_emp_id, 'delete_folder')): ?>
                                                    <button class="folder-action-item delete" 
                                                            onclick="deleteFolder(<?= $subfolder['folder_id'] ?>, 
                                                                            '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>', 
                                                                            <?= $subfolder['is_locked'] ?>)">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                    <?php endif; ?>
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
                                                        <button class="folder-action-item share" 
                                                                onclick="shareFolder(<?= $subfolder['folder_id'] ?>, 
                                                                                '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>')">
                                                            <i class="fas fa-share-alt mr-2"></i>Share
                                                        </button>
                                                        <?php if (canPerformAction($db, $subfolder['folder_id'], $user_emp_id, 'manage_shares')): ?>
                                                        <button class="folder-action-item manage-shares" 
                                                                onclick="manageShares(<?= $subfolder['folder_id'] ?>, 
                                                                                '<?= htmlspecialchars(addslashes($subfolder['folder_name'])) ?>')">
                                                            <i class="fas fa-users mr-2"></i>Manage Access
                                                        </button>
                                                        <?php endif; ?>
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
                                                                
                                                                <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'download_files')): ?>
                                                                <a href="download_file.php?id=<?= $file['file_id'] ?>" 
                                                                class="btn btn-success btn-sm" title="Download">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'delete_files')): ?>
                                                                <button type="button" class="btn btn-danger btn-sm delete-single-file" 
                                                                        data-file-id="<?= $file['file_id'] ?>" 
                                                                        data-file-name="<?= htmlspecialchars($file['file_name']) ?>" 
                                                                        title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                                <?php endif; ?>
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


<div class="modal fade" id="manageSharesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Shared Access</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="manageSharesContent">
                <!-- Shares content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- Share Folder Modal -->
<div class="modal fade" id="shareFolderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Folder: <?= htmlspecialchars($folder_name) ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="shareFolderForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="shareEmployees">Select Employees to Share With *</label>
                        <select multiple class="form-control select2" id="shareEmployees" name="employee_ids[]" required style="width: 100%;">
                            <?php foreach ($all_employees as $employee): ?>
                                <option value="<?= $employee['emp_id'] ?>">
                                    <?= htmlspecialchars($employee['full_name']) ?> (<?= htmlspecialchars($employee['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Hold Ctrl to select multiple employees</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="permissionLevel">Permission Level *</label>
                        <select class="form-control" id="permissionLevel" name="permission_level" required>
                            <option value="view">View Only - Can view files</option>
                            <option value="upload">Upload - Can view and upload files</option>
                            <option value="edit">Edit - Can view, upload, and edit files</option>
                            <option value="manage">Manage - Full access including sharing</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiresAt">Expiry Date (Optional)</label>
                        <input type="datetime-local" class="form-control" id="expiresAt" name="expires_at">
                        <small class="form-text text-muted">Leave blank for permanent access</small>
                    </div>
                    
                    <input type="hidden" name="action" value="share_folder">
                    <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="shareFolderBtn">Share Folder</button>
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
                <h5 class="modal-title">Unlock Protected Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="unlockFolderForm">
                <div class="modal-body">
                    <p>This folder is password protected. Please enter the password to continue.</p>
                    <div class="form-group">
                        <label for="folderPassword">Password</label>
                        <input type="password" class="form-control" id="folderPassword" name="password" required>
                    </div>
                    <input type="hidden" name="action" value="unlock_folder">
                    <input type="hidden" name="folder_id" id="unlockFolderId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Unlock Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>

<script>
// Replace the entire initializeUpload function with this:
function initializeUpload() {
    $(document).ready(function() {
        // Initialize Select2
        if ($.fn.select2) {
            $('.select2').select2({
                placeholder: "Select employees...",
                allowClear: true,
                width: '100%'
            });
        }

        // File input change handler for multiple files
        $('#fileInput').on('change', function() {
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
                        fileList.append('<span class="text-danger">' + file.name + ' (Too large - exceeds 500MB limit)</span><br>');
                    } else {
                        fileList.append('<span class="text-success">' + file.name + ' (' + formatFileSize(file.size) + ')</span><br>');
                    }
                }
            }
        });

        // Upload multiple files functionality
        $('#uploadForm').on('submit', function(e) {
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
            
            // Validate file sizes before upload
            let hasOversizedFile = false;
            const maxSize = 500 * 1024 * 1024;
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    hasOversizedFile = true;
                    break;
                }
            }
            
            if (hasOversizedFile) {
                Swal.fire({
                    title: 'File Too Large',
                    text: 'One or more files exceed the 500MB size limit. Please remove them and try again.',
                    icon: 'error',
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
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
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

// Update folder click handler - ensure it checks view permission
$(document).on('click', '.folder-item, .folder-item-grid', function(e) {
    // Check if the click was on the actions button or menu
    if ($(e.target).closest('.folder-actions, .folder-actions-btn, .folder-actions-menu, .folder-action-item').length > 0) {
        return; // Don't navigate if clicking on actions
    }
    
    const folderId = $(this).data('folder-id');
    const isLocked = $(this).data('locked') == 1;
    
    // Check if user has at least view permission for this folder
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'check_folder_access',
            folder_id: folderId
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.has_access) {
                    if (isLocked) {
                        $('#unlockFolderId').val(folderId);
                        $('#unlockFolderModal').modal('show');
                    } else {
                        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
                    }
                } else {
                    Swal.fire({
                        title: 'Access Denied',
                        text: 'You do not have permission to access this folder.',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to check folder access.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error',
                text: 'Failed to check folder access.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
});

// Unlock folder form submission
$('#unlockFolderForm').submit(function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    const folderId = $('#unlockFolderId').val();
    
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: formData,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    $('#unlockFolderModal').modal('hide');
                    // Store unlocked state in session
                    if (typeof result.unlocked_folders !== 'undefined') {
                        // Redirect to the unlocked folder
                        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
                    }
                } else {
                    Swal.fire({
                        title: 'Incorrect Password',
                        text: result.message || 'The password you entered is incorrect.',
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
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to unlock folder: ' + error,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
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

// Share folder function
function shareFolder(folderId, folderName) {
    $('#shareFolderForm input[name="folder_id"]').val(folderId);
    $('#shareFolderModal .modal-title').html('Share Folder: ' + folderName);
    $('#shareEmployees').val(null).trigger('change');
    $('#permissionLevel').val('view');
    $('#expiresAt').val('');
    $('#shareFolderModal').modal('show');
    
    // Close the menu
    document.querySelectorAll('.folder-actions-menu').forEach(m => {
        m.classList.remove('show');
    });
}


function validateShareForm() {
    const employeeIds = $('#shareEmployees').val();
    
    if (!employeeIds || employeeIds.length === 0) {
        Swal.fire({
            title: 'Selection Required',
            text: 'Please select at least one employee to share with.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    const permissionLevel = $('#permissionLevel').val();
    if (!permissionLevel) {
        Swal.fire({
            title: 'Permission Required',
            text: 'Please select a permission level.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    return true;
}
// Share folder form submission
$('#shareFolderForm').submit(function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    const shareBtn = $('#shareFolderBtn');
    const originalText = shareBtn.html();
    
    shareBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sharing...');
    
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: formData,
        dataType: 'json', // Explicitly expect JSON response
        success: function(response) {
            shareBtn.prop('disabled', false).html(originalText);
            
            // Response should already be parsed as JSON due to dataType: 'json'
            if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#shareFolderModal').modal('hide');
                    // Reset form
                    $('#shareFolderForm')[0].reset();
                    $('#shareEmployees').val(null).trigger('change');
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to share folder.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr, status, error) {
            shareBtn.prop('disabled', false).html(originalText);
            
            console.error('AJAX Error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            
            let errorMessage = 'Failed to share folder: ';
            
            if (xhr.status === 0) {
                errorMessage += 'Network error. Please check your connection.';
            } else if (xhr.status === 500) {
                errorMessage += 'Server error. Please try again later.';
            } else {
                errorMessage += error || 'Unknown error occurred.';
            }
            
            // Try to parse response text for more details
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // If not JSON, use the generic error message
            }
            
            Swal.fire({
                title: 'Error!',
                text: errorMessage,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
});


// Revoke access handler
$(document).on('click', '.revoke-access', function() {
    const shareId = $(this).data('share-id');
    const employeeName = $(this).data('employee-name');
    
    Swal.fire({
        title: 'Revoke Access?',
        html: `Are you sure you want to revoke <strong>${employeeName}</strong>'s access to this folder?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, revoke access!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
                type: 'POST',
                data: {
                    action: 'revoke_access',
                    share_id: shareId
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Access Revoked!',
                                text: result.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error!', result.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error!', 'Invalid server response', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error!', 'Failed to revoke access: ' + error, 'error');
                }
            });
        }
    });
});

// Initialize Select2 for employee selection
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select employees...",
        allowClear: true
    });
});

// Update share permissions
$(document).on('click', '.update-share', function() {
    const shareId = $(this).data('share-id');
    const employeeName = $(this).data('employee-name');
    const permissionLevel = $(this).closest('tr').find('.permission-select').val();
    const expiresAt = $(this).closest('tr').find('.expiry-input').val();
    
    const updateBtn = $(this);
    const originalText = updateBtn.html();
    updateBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'edit_share',
            share_id: shareId,
            permission_level: permissionLevel,
            expires_at: expiresAt
        },
        success: function(response) {
            updateBtn.prop('disabled', false).html(originalText);
            
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: `Permissions updated for ${employeeName}`,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Invalid server response', 'error');
            }
        },
        error: function(xhr, status, error) {
            updateBtn.prop('disabled', false).html(originalText);
            Swal.fire('Error!', 'Failed to update permissions: ' + error, 'error');
        }
    });
});

// Auto-save when permission level changes (optional)
$(document).on('change', '.permission-select', function() {
    const shareId = $(this).data('share-id');
    const employeeName = $(this).closest('tr').find('.employee-name').text();
    const permissionLevel = $(this).val();
    const expiresAt = $(this).closest('tr').find('.expiry-input').val();
    
    // You can add auto-save functionality here if desired
    console.log('Permission changed for share:', shareId, 'to:', permissionLevel);
});

// Manage shares function
function manageShares(folderId, folderName) {
    const modal = $('#manageSharesModal');
    const content = $('#manageSharesContent');
    
    // Show loading state
    content.html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading shared access information...</p>
        </div>
    `);
    
    modal.find('.modal-title').text('Manage Shared Access: ' + folderName);
    modal.modal('show');
    
    // Load shares content via AJAX
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'get_shares',
            folder_id: folderId
        },
        success: function(response) {
            content.html(response);
        },
        error: function(xhr, status, error) {
            content.html(`
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Failed to load shared access information: ${error}
                </div>
            `);
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
    $type = strtolower($fileType);
    $icons = [
        'pdf' => 'pdf text-danger',
        'doc' => 'word text-primary',
        'docx' => 'word text-primary',
        'xls' => 'excel text-success',
        'xlsx' => 'excel text-success',
        'ppt' => 'powerpoint text-warning',
        'pptx' => 'powerpoint text-warning',
        'jpg' => 'image text-info',
        'jpeg' => 'image text-info',
        'png' => 'image text-info',
        'gif' => 'image text-info',
        'zip' => 'archive text-secondary',
        'rar' => 'archive text-secondary',
        'txt' => 'text text-dark',
        'mp4' => 'video text-danger',
        'avi' => 'video text-danger',
        'mov' => 'video text-danger',
        'mp3' => 'audio text-info',
        'wav' => 'audio text-info'
    ];
    
    return $icons[$type] ?? 'file text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>