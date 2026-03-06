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

    // Get folder ID and section ID from URL parameters FIRST (needed by everything below)
    $folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : '';
    $section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';

    // Get user employee ID
    $user_emp_id = null;
    $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $user_emp_id = $user_data['employee_id'];
    }

    // Fallback: check employee table directly
    if (!$user_emp_id) {
        $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
        $emp_stmt->bind_param("i", $_SESSION['user_id']);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        if ($emp_result->num_rows > 0) {
            $user_emp_id = $_SESSION['user_id'];
        }
    }

    if (!$user_emp_id) {
        $_SESSION['error'] = "No valid employee record found. Please contact administrator.";
        header("Location: ../login.php");
        exit();
    }

    // Store for consistent use throughout the script
    $_SESSION['user_emp_id'] = $user_emp_id;

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

    // Handle subfolder creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
        $new_folder_name = trim($_POST['folder_name'] ?? '');
        $new_description = trim($_POST['description'] ?? '');
        $parent_folder_id = isset($_POST['parent_folder_id']) ? intval($_POST['parent_folder_id']) : intval($folder_id);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        $is_locked = !empty($_POST['password']) ? 1 : 0;

        if (empty($new_folder_name)) {
            $_SESSION['error'] = 'Folder name is required.';
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }

        if (!canPerformAction($db, $parent_folder_id, $user_emp_id, 'create_folder')) {
            $_SESSION['error'] = 'You do not have permission to create folders here.';
            header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
            exit();
        }

        $section_id_value = ($section_id === 'manager') ? NULL : (is_numeric($section_id) ? intval($section_id) : NULL);
        $stmt = $db->prepare("INSERT INTO folders (folder_name, description, section_id, parent_folder_id, password, is_locked, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiisi", $new_folder_name, $new_description, $section_id_value, $parent_folder_id, $password, $is_locked, $user_emp_id);

        if ($stmt->execute()) {
            $new_folder_id = $db->insert_id;
            // Log activity
            $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'created', ?, ?)");
            $log_description = "Subfolder '{$new_folder_name}' created";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $new_folder_id, $user_emp_id, $log_description, $ip);
            $log_stmt->execute();
            $_SESSION['success'] = "Folder '{$new_folder_name}' created successfully!";
        } else {
            $_SESSION['error'] = 'Failed to create folder: ' . $db->error;
        }
        header("Location: folder_contents.php?folder_id=" . $folder_id . "&section_id=" . $section_id);
        exit();
    }

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
                    $upload_results[] = ['file' => $_FILES['files']['name'][$i] ?? 'unknown', 'success' => false, 'message' => 'Upload error code: ' . $_FILES['files']['error'][$i]];
                    $has_error = true;
                }
            }
            
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
        
        if (!is_array($employee_ids)) {
            $employee_ids = [$employee_ids];
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($employee_ids as $emp_id) {
            if (empty($emp_id) || !is_numeric($emp_id)) {
                $error_count++;
                continue;
            }
            
            $check_stmt = $db->prepare("SELECT share_id FROM folder_shares WHERE folder_id = ? AND shared_with_emp_id = ? AND is_active = TRUE");
            $check_stmt->bind_param("ii", $folder_id, $emp_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $insert_stmt = $db->prepare("INSERT INTO folder_shares (folder_id, shared_by_emp_id, shared_with_emp_id, permission_level, expires_at) VALUES (?, ?, ?, ?, ?)");
                
                if (empty($expires_at)) {
                    $insert_stmt->bind_param("iiiss", $folder_id, $user_emp_id, $emp_id, $permission_level, $expires_at);
                } else {
                    $insert_stmt->bind_param("iiiss", $folder_id, $user_emp_id, $emp_id, $permission_level, $expires_at);
                }
                
                if ($insert_stmt->execute()) {
                    $success_count++;
                    
                    try {
                        $log_stmt = $db->prepare("INSERT INTO folder_activity_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'created', ?, ?)");
                        $log_description = "Folder shared with employee ID: {$emp_id} with {$permission_level} permissions";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                        $log_stmt->execute();
                    } catch (Exception $e) {
                        error_log("Failed to log folder share activity: " . $e->getMessage());
                    }
                } else {
                    $error_count++;
                    $errors[] = "Failed to share with employee ID: {$emp_id} - " . $db->error;
                }
            } else {
                $error_count++;
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
    
    // Fetch subfolders with hierarchical data
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
            return true;
        }
    }
    
    // Check shared permissions
    $share_stmt = $db->prepare("SELECT permission_level, expires_at 
                              FROM folder_shares 
                              WHERE folder_id = ? AND shared_with_emp_id = ? AND is_active = TRUE 
                              AND (expires_at IS NULL OR expires_at > NOW())");
    $share_stmt->bind_param("ii", $folder_id, $user_emp_id);
    $share_stmt->execute();
    $share_result = $share_stmt->get_result();
    
    if ($share_result->num_rows > 0) {
        $share_data = $share_result->fetch_assoc();
        
        $permission_hierarchy = [
            'view' => 1,
            'upload' => 2,
            'edit' => 3,
            'manage' => 4
        ];
        
        $required_level = $permission_hierarchy[$permission_type] ?? 0;
        $user_level = $permission_hierarchy[$share_data['permission_level']] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    return false;
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
            return true;
        }
    }
    
    $permission_map = [
        'view_files' => 'view',
        'upload_files' => 'upload',
        'download_files' => 'edit',
        'edit_files' => 'edit',
        'delete_files' => 'manage',
        'create_folder' => 'manage',
        'edit_folder' => 'manage',
        'delete_folder' => 'manage',
        'share_folder' => 'manage',
        'manage_shares' => 'manage',
        'edit_share' => 'manage'
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
        
        $update_stmt = $db->prepare("UPDATE folder_shares SET permission_level = ?, expires_at = ? WHERE share_id = ?");
        $update_stmt->bind_param("ssi", $permission_level, $expires_at, $share_id);
        
        if ($update_stmt->execute()) {
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
    function buildFolderTree($db, $parent_id = null, $user_emp_id = null, $level = 0) {
        $tree = [];
        $query = "SELECT f.*, 
                         CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                         (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                         (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                  FROM folders f 
                  LEFT JOIN employee e ON f.created_by = e.emp_id 
                  WHERE f.parent_folder_id " . ($parent_id === null ? "IS NULL" : "= ?") . "
                  ORDER BY f.folder_name";
        
        $stmt = $db->prepare($query);
        if ($parent_id !== null) {
            $stmt->bind_param("i", $parent_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $row['level'] = $level;
            $row['children'] = buildFolderTree($db, $row['folder_id'], $user_emp_id, $level + 1);
            $tree[] = $row;
        }
        
        return $tree;
    }

    // Get the full folder tree starting from the root
    $full_folder_tree = buildFolderTree($db, null, $user_emp_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($folder_name) ?> - Contents</title>
    
    <?php include '../includes/header.php'; ?>
    
    <style>
        /* Modern CSS Variables for theming */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            
            /* Light mode - FIXED: Making sidebar light */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --hover-bg: #f1f5f9;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --header-bg: #ffffff;
            --sidebar-bg: #ffffff; /* Light sidebar background */
            --toolbar-bg: #ffffff;
            
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            
            --transition-fast: 150ms;
            --transition-normal: 250ms;
            --transition-slow: 350ms;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Dark mode - only changes colors, not the layout */
        body.dark-mode {
            --bg-primary: #1e293b;
            --bg-secondary: #0f172a;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --card-bg: #1e293b;
            --hover-bg: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --header-bg: #1e293b;
            --sidebar-bg: #1e293b; /* Dark sidebar background for dark mode */
            --toolbar-bg: #1e293b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: background-color var(--transition-normal), color var(--transition-normal);
        }

        /* Modern scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: var(--radius-sm);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* Layout */
        .file-explorer {
            display: flex;
            height: calc(100vh - 57px - 57px - 120px);
            overflow: hidden;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            margin: var(--spacing-lg);
            box-shadow: var(--shadow-lg);
        }

        /* Left Sidebar - Folder Tree - FIXED: Light background */
        .file-explorer .sidebar {
            width: 300px;
            background: var(--sidebar-bg); /* Uses variable that's light in light mode */
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            background: var(--sidebar-bg);
        }

        .sidebar-header h5 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .sidebar-header h5 i {
            color: var(--primary-color);
        }

        #folderTree {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-sm);
            background: var(--sidebar-bg);
        }

        /* Tree Structure */
        .tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tree-item {
            position: relative;
            margin: 2px 0;
        }

        .tree-item .tree-node {
            display: flex;
            align-items: center;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 1px solid transparent;
            position: relative;
            font-size: 0.875rem;
            background: var(--sidebar-bg);
        }

        .tree-item .tree-node:hover {
            background: var(--hover-bg);
            border-color: var(--border-color);
        }

        .tree-item .tree-node.active {
            background: rgba(37, 99, 235, 0.1);
            border-color: var(--primary-color);
        }

        .tree-item .tree-node.locked {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .tree-indent {
            width: calc(20px * var(--level, 0));
            flex-shrink: 0;
        }

        .tree-expander {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: all var(--transition-fast);
            flex-shrink: 0;
        }

        .tree-expander:hover {
            color: var(--primary-color);
        }

        .tree-expander i {
            font-size: 0.75rem;
        }

        .tree-icon {
            width: 20px;
            text-align: center;
            margin-right: var(--spacing-xs);
            color: #fbbf24;
            flex-shrink: 0;
        }

        .tree-icon.locked {
            color: var(--danger-color);
        }

        .tree-content {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .tree-name {
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .tree-badge {
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            background: var(--bg-tertiary);
            color: var(--text-muted);
            border-radius: var(--radius-sm);
            margin-left: auto;
        }

        .tree-badge.locked {
            background: var(--danger-color);
            color: white;
        }

        .tree-children {
            list-style: none;
            padding-left: 20px;
            margin: 0;
        }

        /* Folder Actions in Tree */
        .tree-actions {
            position: relative;
            opacity: 0;
            transition: opacity var(--transition-fast);
            margin-left: auto;
        }

        .tree-node:hover .tree-actions {
            opacity: 1;
        }

        .tree-actions-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: var(--spacing-xs);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tree-actions-btn:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .tree-actions-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            min-width: 160px;
            z-index: 1000;
            padding: var(--spacing-xs) 0;
        }

        .tree-actions-menu.show {
            display: block;
        }

        .tree-action-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.8125rem;
            color: var(--text-primary);
            transition: background var(--transition-fast);
        }

        .tree-action-item:hover {
            background: var(--hover-bg);
        }

        .tree-action-item i {
            width: 16px;
            color: var(--text-muted);
        }

        .tree-action-item.delete {
            color: var(--danger-color);
        }

        .tree-action-item.delete i {
            color: var(--danger-color);
        }

        /* Main Content - keep all your existing styles for main content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-primary);
        }

        .current-folder {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .current-folder i {
            color: var(--primary-color);
        }

        .current-folder .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background: var(--warning-color);
            color: white;
            border-radius: var(--radius-sm);
        }

        .content-toolbar {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .toolbar-btn {
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-fast);
        }

        .toolbar-btn:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
        }

        .toolbar-btn.primary {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .toolbar-btn.primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .search-container {
            flex: 1;
            max-width: 300px;
            margin-left: auto;
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: var(--spacing-sm);
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .search-container input {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-sm) var(--spacing-sm) 2rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-lg);
        }

        .section-header {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin: var(--spacing-lg) 0 var(--spacing-md) 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .section-header:first-child {
            margin-top: 0;
        }

        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }

        .folder-item-grid {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg) var(--spacing-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-normal);
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .folder-item-grid:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .folder-item-grid .folder-icon-grid {
            font-size: 2.5rem;
            color: #fbbf24;
            margin-bottom: var(--spacing-sm);
        }

        .folder-item-grid .folder-icon-grid.locked {
            color: var(--danger-color);
        }

        .folder-item-grid .folder-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
            line-height: 1.4;
            margin-bottom: var(--spacing-xs);
        }

        .folder-item-grid .folder-stats {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .folder-item-grid .folder-creator {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: var(--spacing-xs);
        }

        .file-list {
            width: 100%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .file-header {
            display: grid;
            grid-template-columns: 40px 1fr 100px 140px 140px 120px;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .file-row {
            display: grid;
            grid-template-columns: 40px 1fr 100px 140px 140px 120px;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .file-row:hover {
            background: var(--hover-bg);
        }

        .file-row:hover .file-actions-col {
            opacity: 1;
        }

        .file-check-col {
            display: flex;
            align-items: center;
        }

        .file-name-col {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            min-width: 0;
        }

        .file-icon {
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .file-name {
            font-size: 0.875rem;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 500;
        }

        .file-type-col {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .file-size-col {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .file-date-col {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .file-actions-col {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            opacity: 0;
            transition: opacity var(--transition-fast);
        }

        .file-action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }

        .file-action-btn:hover {
            background: var(--hover-bg);
            color: var(--primary-color);
        }

        .text-primary { color: #3b82f6; }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
        .text-info { color: #06b6d4; }
        .text-secondary { color: #64748b; }

        .file-drop-zone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            text-align: center;
            transition: all var(--transition-fast);
            background: var(--bg-tertiary);
            cursor: pointer;
        }

        .file-drop-zone.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .file-drop-zone:hover {
            border-color: var(--primary-color);
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-xs);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            flex: 1;
        }

        .file-info .file-icon {
            font-size: 1rem;
            color: var(--primary-color);
        }

        .file-details {
            flex: 1;
        }

        .file-details .file-name {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .file-details .file-size {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .file-remove {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: var(--spacing-xs);
            border-radius: 50%;
            transition: all var(--transition-fast);
        }

        .file-remove:hover {
            background: var(--hover-bg);
            color: var(--danger-color);
        }

        .upload-progress {
            margin-top: var(--spacing-md);
        }

        .progress {
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            height: 8px;
            overflow: hidden;
        }

        .progress-bar {
            background: var(--primary-color);
            transition: width var(--transition-normal);
        }

        .progress-text {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-xl) var(--spacing-lg);
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            display: block;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        .select-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-md);
        }

        .select-bar label {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        @media (max-width: 1024px) {
            .file-header, .file-row {
                grid-template-columns: 40px 1fr 80px 100px 100px 80px;
            }
        }

        @media (max-width: 768px) {
            .file-explorer {
                flex-direction: column;
                margin: var(--spacing-sm);
            }
            
            .file-explorer .sidebar {
                width: 100%;
                max-height: 300px;
            }
            
            .folder-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .file-header, .file-row {
                grid-template-columns: 40px 1fr 80px 100px 80px 60px;
            }
            
            .file-type-col, .file-size-col {
                display: none;
            }
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
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['success'] ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?= $_SESSION['error'] ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="file-explorer">
                    <!-- Sidebar with complete folder tree -->
                    <div class="sidebar">
                        <div class="sidebar-header">
                            <h5>
                                <i class="fas fa-folder-tree"></i>
                                Folder Tree
                            </h5>
                        </div>
                        <div id="folderTree">
                            <?php if (!empty($full_folder_tree)): ?>
                                <ul class="tree">
                                    <?php foreach ($full_folder_tree as $folder_item): 
                                        $is_creator = ($folder_item['created_by'] == $user_emp_id);
                                        $has_access = $is_creator || hasFolderPermission($db, $folder_item['folder_id'], $user_emp_id, 'view');
                                        $is_current = ($folder_item['folder_id'] == $folder_id);
                                    ?>
                                    <li class="tree-item" data-folder-id="<?= $folder_item['folder_id'] ?>">
                                        <div class="tree-node <?= $folder_item['is_locked'] ? 'locked' : '' ?> <?= !$has_access ? 'no-access' : '' ?> <?= $is_current ? 'active' : '' ?>" 
                                             data-folder-id="<?= $folder_item['folder_id'] ?>"
                                             data-locked="<?= $folder_item['is_locked'] ?>"
                                             onclick="navigateToFolder(<?= $folder_item['folder_id'] ?>, <?= $folder_item['is_locked'] ?>)">
                                            <div class="tree-expander" onclick="toggleTreeBranch(this, event)">
                                                <i class="fas fa-<?= !empty($folder_item['children']) ? 'chevron-right' : 'empty' ?>"></i>
                                            </div>
                                            <i class="fas fa-folder tree-icon <?= $folder_item['is_locked'] ? 'locked' : '' ?>"></i>
                                            <div class="tree-content">
                                                <span class="tree-name"><?= htmlspecialchars($folder_item['folder_name']) ?></span>
                                                <?php if ($folder_item['subfolder_count'] > 0): ?>
                                                    <span class="tree-badge"><?= $folder_item['subfolder_count'] ?></span>
                                                <?php endif; ?>
                                                <?php if ($folder_item['is_locked']): ?>
                                                    <span class="tree-badge locked"><i class="fas fa-lock"></i></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="tree-actions">
                                                <button class="tree-actions-btn" onclick="toggleTreeMenu(this, event)">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="tree-actions-menu">
                                                    <?php if (canPerformAction($db, $folder_item['folder_id'], $user_emp_id, 'edit_folder')): ?>
                                                    <button class="tree-action-item" 
                                                            onclick="editFolder(<?= $folder_item['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder_item['folder_name'])) ?>', '<?= htmlspecialchars(addslashes($folder_item['description'] ?? '')) ?>', <?= $folder_item['is_locked'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $folder_item['folder_id'], $user_emp_id, 'share_folder')): ?>
                                                    <button class="tree-action-item" 
                                                            onclick="shareFolder(<?= $folder_item['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder_item['folder_name'])) ?>')">
                                                        <i class="fas fa-share-alt"></i> Share
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $folder_item['folder_id'], $user_emp_id, 'manage_shares')): ?>
                                                    <button class="tree-action-item" 
                                                            onclick="manageShares(<?= $folder_item['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder_item['folder_name'])) ?>')">
                                                        <i class="fas fa-users"></i> Manage Access
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (canPerformAction($db, $folder_item['folder_id'], $user_emp_id, 'delete_folder')): ?>
                                                    <button class="tree-action-item delete" 
                                                            onclick="deleteFolder(<?= $folder_item['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder_item['folder_name'])) ?>', <?= $folder_item['is_locked'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($folder_item['children'])): ?>
                                            <?= renderTreeChildren($folder_item['children'], $db, $user_emp_id, $folder_id) ?>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No folders found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

<div class="main-content">
                        <div class="current-folder">
                            <i class="fas fa-folder-open"></i>
                            <?= htmlspecialchars($folder_name) ?>
                            <?php if ($is_locked): ?>
                                <span class="badge"><i class="fas fa-lock"></i> Protected</span>
                            <?php endif; ?>
                        </div>

                        <div class="content-toolbar">
                            <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'upload_files')): ?>
                            <button class="toolbar-btn primary" data-toggle="modal" data-target="#uploadFileModal">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <?php endif; ?>
                            
                            <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'create_folder')): ?>
                            <button class="toolbar-btn" data-toggle="modal" data-target="#createFolderModal">
                                <i class="fas fa-folder-plus"></i> New Folder
                            </button>
                            <?php endif; ?>
                            
                            <button class="toolbar-btn" onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            
                            <div class="search-container">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" placeholder="Search in this folder...">
                            </div>
                        </div>

                        <div class="content-area">
                            <?php if (!empty($subfolders)): ?>
                                <div class="section-header">
                                    <i class="fas fa-folder"></i>
                                    Subfolders (<?= count($subfolders) ?>)
                                </div>
                                <div class="folder-grid">
                                    <?php foreach ($subfolders as $subfolder): ?>
                                        <div class="folder-item-grid" 
                                            data-folder-id="<?= $subfolder['folder_id'] ?>"
                                            data-locked="<?= $subfolder['is_locked'] ?>"
                                            onclick="navigateToFolder(<?= $subfolder['folder_id'] ?>, <?= $subfolder['is_locked'] ?>)">
                                            
                                            <i class="fas fa-folder folder-icon-grid <?= $subfolder['is_locked'] ? 'locked' : '' ?>"></i>
                                            <div class="folder-name">
                                                <?= htmlspecialchars($subfolder['folder_name']) ?>
                                                <?php if ($subfolder['is_locked']): ?>
                                                    <br><span class="locked-badge">Locked</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="folder-stats">
                                                <?= $subfolder['file_count'] ?> files
                                                <?php if ($subfolder['subfolder_count'] > 0): ?>
                                                    , <?= $subfolder['subfolder_count'] ?> subfolders
                                                <?php endif; ?>
                                            </div>
                                            <div class="folder-creator">
                                                <?= htmlspecialchars($subfolder['creator_name'] ?? 'Unknown') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="section-header" style="<?= empty($subfolders) ? 'margin-top: 0;' : '' ?>">
                                <i class="fas fa-file"></i>
                                Files (<?= count($files) ?>)
                            </div>
                            
                            <?php if (empty($files)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <p>No files in this folder</p>
                                </div>
                            <?php else: ?>
                                <form id="deleteFilesForm" method="POST" action="folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>">
                                    <input type="hidden" name="action" value="delete_files">
                                    
                                    <div class="select-bar">
                                        <label>
                                            <input type="checkbox" id="selectAll" class="mr-2">
                                            Select All
                                        </label>
                                        <button type="button" id="deleteSelectedBtn" class="toolbar-btn" style="color: var(--danger-color);" disabled>
                                            <i class="fas fa-trash"></i> Delete Selected
                                        </button>
                                    </div>

                                    <div class="file-list">
                                        <div class="file-header">
                                            <div class="file-check-col"></div>
                                            <div class="file-name-col">Name</div>
                                            <div class="file-type-col">Type</div>
                                            <div class="file-size-col">Size</div>
                                            <div class="file-date-col">Uploaded</div>
                                            <div class="file-actions-col"></div>
                                        </div>
                                        
                                        <?php foreach ($files as $file): ?>
                                        <div class="file-row" onclick="viewFile(<?= $file['file_id'] ?>)">
                                            <div class="file-check-col">
                                                <input type="checkbox" name="file_ids[]" value="<?= $file['file_id'] ?>" class="file-checkbox" onclick="event.stopPropagation()">
                                            </div>
                                            <div class="file-name-col">
                                                <i class="fas fa-file-<?= getFileIcon($file['file_type']) ?> file-icon <?= getFileIconClass($file['file_type']) ?>"></i>
                                                <span class="file-name"><?= htmlspecialchars($file['file_name']) ?></span>
                                            </div>
                                            <div class="file-type-col"><?= strtoupper($file['file_type']) ?></div>
                                            <div class="file-size-col"><?= formatFileSize($file['file_size']) ?></div>
                                            <div class="file-date-col"><?= date('M j, Y', strtotime($file['created_at'])) ?></div>
                                            <div class="file-actions-col">
                                                <a href="view_file.php?id=<?= $file['file_id'] ?>" class="file-action-btn" title="View" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'download_files')): ?>
                                                <a href="download_file.php?id=<?= $file['file_id'] ?>" class="file-action-btn" title="Download" onclick="event.stopPropagation()">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (canPerformAction($db, $folder_id, $user_emp_id, 'delete_files')): ?>
                                                <button type="button" class="file-action-btn delete-single-file" 
                                                        data-file-id="<?= $file['file_id'] ?>" 
                                                        data-file-name="<?= htmlspecialchars($file['file_name']) ?>" 
                                                        title="Delete"
                                                        onclick="event.stopPropagation()">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            <?php endif; ?>
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
                <h5 class="modal-title"><i class="fas fa-cloud-upload-alt mr-2"></i>Upload Files</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--text-muted);"></i>
                            <h5>Drag & Drop files here</h5>
                            <p class="text-muted mb-2">or click to browse</p>
                            <input type="file" id="fileInput" name="files[]" multiple accept="*/*" style="display: none;">
                            <button type="button" class="toolbar-btn primary mt-2" id="browseFilesBtn">
                                <i class="fas fa-folder-open mr-2"></i>Browse Files
                            </button>
                        </div>
                    </div>
                    
                    <div class="selected-files-container mt-3" id="selectedFilesContainer" style="display: none;">
                        <h6><i class="fas fa-list mr-1"></i>Selected Files (<span id="fileCount">0</span>)</h6>
                        <div class="selected-files-list" id="selectedFilesList"></div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="fileDescription">Description (Optional)</label>
                        <textarea class="form-control" id="fileDescription" name="description" rows="2" placeholder="Optional file description"></textarea>
                    </div>
                    
                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="progress-text">0%</small>
                    </div>
                    
                    <input type="hidden" name="action" value="upload_file">
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">Upload Files</button>
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
                <h5 class="modal-title"><i class="fas fa-folder-plus mr-2"></i>Create New Subfolder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="folderForm" method="POST" action="folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <input type="hidden" name="parent_folder_id" value="<?= $folder_id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="folderName">Folder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folderName" name="folder_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="password">Password Protection <small class="text-muted">(optional)</small></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Leave blank for no password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleCreatePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
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
                        <textarea class="form-control" id="editDescription" name="description" rows="2"></textarea>
                    </div>
                    <div class="form-group">
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

<!-- Manage Shares Modal -->
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
<?php
// Helper function to render tree children recursively
function renderTreeChildren($children, $db, $user_emp_id, $current_folder_id) {
    if (empty($children)) return '';
    
    $html = '<ul class="tree-children">';
    foreach ($children as $child) {
        $is_creator = ($child['created_by'] == $user_emp_id);
        $has_access = $is_creator || hasFolderPermission($db, $child['folder_id'], $user_emp_id, 'view');
        $is_current = ($child['folder_id'] == $current_folder_id);
        
        $html .= '<li class="tree-item" data-folder-id="' . $child['folder_id'] . '">';
        $html .= '<div class="tree-node ' . ($child['is_locked'] ? 'locked' : '') . ' ' . (!$has_access ? 'no-access' : '') . ' ' . ($is_current ? 'active' : '') . '" 
                        data-folder-id="' . $child['folder_id'] . '"
                        data-locked="' . $child['is_locked'] . '"
                        onclick="navigateToFolder(' . $child['folder_id'] . ', ' . $child['is_locked'] . ')">';
        $html .= '<div class="tree-expander" onclick="toggleTreeBranch(this, event)">';
        $html .= '<i class="fas fa-' . (!empty($child['children']) ? 'chevron-right' : 'empty') . '"></i>';
        $html .= '</div>';
        $html .= '<i class="fas fa-folder tree-icon ' . ($child['is_locked'] ? 'locked' : '') . '"></i>';
        $html .= '<div class="tree-content">';
        $html .= '<span class="tree-name">' . htmlspecialchars($child['folder_name']) . '</span>';
        if ($child['subfolder_count'] > 0) {
            $html .= '<span class="tree-badge">' . $child['subfolder_count'] . '</span>';
        }
        if ($child['is_locked']) {
            $html .= '<span class="tree-badge locked"><i class="fas fa-lock"></i></span>';
        }
        $html .= '</div>';
        $html .= '<div class="tree-actions">';
        $html .= '<button class="tree-actions-btn" onclick="toggleTreeMenu(this, event)"><i class="fas fa-ellipsis-v"></i></button>';
        $html .= '<div class="tree-actions-menu">';
        
        if (canPerformAction($db, $child['folder_id'], $user_emp_id, 'edit_folder')) {
            $html .= '<button class="tree-action-item" onclick="editFolder(' . $child['folder_id'] . ', \'' . htmlspecialchars(addslashes($child['folder_name'])) . '\', \'' . htmlspecialchars(addslashes($child['description'] ?? '')) . '\', ' . $child['is_locked'] . ')"><i class="fas fa-edit"></i> Edit</button>';
        }
        if (canPerformAction($db, $child['folder_id'], $user_emp_id, 'share_folder')) {
            $html .= '<button class="tree-action-item" onclick="shareFolder(' . $child['folder_id'] . ', \'' . htmlspecialchars(addslashes($child['folder_name'])) . '\')"><i class="fas fa-share-alt"></i> Share</button>';
        }
        if (canPerformAction($db, $child['folder_id'], $user_emp_id, 'manage_shares')) {
            $html .= '<button class="tree-action-item" onclick="manageShares(' . $child['folder_id'] . ', \'' . htmlspecialchars(addslashes($child['folder_name'])) . '\')"><i class="fas fa-users"></i> Manage Access</button>';
        }
        if (canPerformAction($db, $child['folder_id'], $user_emp_id, 'delete_folder')) {
            $html .= '<button class="tree-action-item delete" onclick="deleteFolder(' . $child['folder_id'] . ', \'' . htmlspecialchars(addslashes($child['folder_name'])) . '\', ' . $child['is_locked'] . ')"><i class="fas fa-trash"></i> Delete</button>';
        }
        
        $html .= '</div></div></div>';
        
        if (!empty($child['children'])) {
            $html .= renderTreeChildren($child['children'], $db, $user_emp_id, $current_folder_id);
        }
        
        $html .= '</li>';
    }
    $html .= '</ul>';
    
    return $html;
}
?>
<script>
let selectedFiles = [];

$(document).ready(function() {
    // Initialize Select2
    if ($.fn.select2) {
        $('.select2').select2({
            placeholder: "Select employees...",
            allowClear: true,
            width: '100%'
        });
    }

    // Toggle password visibility
    $('#toggleCreatePassword').click(function() {
        const field = $('#password');
        field.attr('type', field.attr('type') === 'password' ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#toggleEditPassword').click(function() {
        const field = $('#editPassword');
        field.attr('type', field.attr('type') === 'password' ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Remove password checkbox
    $('#removePassword').change(function() {
        if ($(this).prop('checked')) {
            $('#editPassword').prop('disabled', true).val('').attr('placeholder', 'Password will be removed');
        } else {
            $('#editPassword').prop('disabled', false);
        }
    });

    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        // Filter folders
        $('.folder-item-grid').each(function() {
            const name = $(this).find('.folder-name').text().toLowerCase();
            $(this).toggle(name.includes(searchTerm));
        });
        
        // Filter files
        $('.file-row').each(function() {
            const name = $(this).find('.file-name').text().toLowerCase();
            $(this).toggle(name.includes(searchTerm));
        });
    });

    // Select all checkboxes
    $('#selectAll').change(function() {
        $('.file-checkbox').prop('checked', $(this).prop('checked'));
        updateDeleteButton();
    });

    // Individual checkbox change
    $(document).on('change', '.file-checkbox', function() {
        const totalCheckboxes = $('.file-checkbox').length;
        const checkedCheckboxes = $('.file-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
        updateDeleteButton();
    });

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
            title: 'Delete Files?',
            html: `Are you sure you want to delete <strong>${checkedCount}</strong> selected file(s)?<br><br>This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete them!'
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
            title: 'Delete File?',
            html: `Are you sure you want to delete "<strong>${fileName}</strong>"?<br><br>This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
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

    // Unlock folder form
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
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#unlockFolderModal').modal('hide');
                        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
                    } else {
                        Swal.fire({
                            title: 'Incorrect Password',
                            text: result.message || 'The password you entered is incorrect.',
                            icon: 'error'
                        });
                    }
                } catch (e) {
                    Swal.fire('Error!', 'Invalid server response', 'error');
                }
            }
        });
    });

    // Share folder form
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
            dataType: 'json',
            success: function(response) {
                shareBtn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        $('#shareFolderModal').modal('hide');
                        $('#shareFolderForm')[0].reset();
                        $('#shareEmployees').val(null).trigger('change');
                    });
                } else {
                    Swal.fire('Error!', response.message || 'Failed to share folder.', 'error');
                }
            },
            error: function() {
                shareBtn.prop('disabled', false).html(originalText);
                Swal.fire('Error!', 'Failed to share folder.', 'error');
            }
        });
    });

    // Revoke access
    $(document).on('click', '.revoke-access', function() {
        const shareId = $(this).data('share-id');
        const employeeName = $(this).data('employee-name');
        
        Swal.fire({
            title: 'Revoke Access?',
            html: `Are you sure you want to revoke <strong>${employeeName}</strong>'s access?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, revoke access!'
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
                                    icon: 'success'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error!', result.message, 'error');
                            }
                        } catch (e) {
                            Swal.fire('Error!', 'Invalid server response', 'error');
                        }
                    }
                });
            }
        });
    });

    // Initialize file upload
    initFileUpload();
});

// Global functions
function navigateToFolder(folderId, isLocked) {
    if (isLocked) {
        $('#unlockFolderId').val(folderId);
        $('#unlockFolderModal').modal('show');
    } else {
        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
    }
}

function toggleTreeBranch(expander, event) {
    event.stopPropagation();
    const treeItem = $(expander).closest('.tree-item');
    const children = treeItem.find('> .tree-children');
    const icon = $(expander).find('i');
    
    if (children.is(':visible')) {
        children.slideUp(200);
        icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
    } else {
        children.slideDown(200);
        icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        // Load children if not loaded (you can implement AJAX here)
        loadFolderChildren(treeItem, $(expander).closest('.tree-node').data('folder-id'));
    }
}

function loadFolderChildren(treeItem, folderId) {
    const childrenContainer = treeItem.find('> .tree-children');
    // You can implement AJAX loading of subfolders here
    // For now, just show a message
    if (childrenContainer.find('.tree-item').length === 1) {
        childrenContainer.html('<li class="tree-item"><div class="tree-node"><div class="tree-indent"></div><span class="tree-name">No subfolders</span></div></li>');
    }
}

function toggleTreeMenu(button, event) {
    event.stopPropagation();
    event.preventDefault();
    
    const menu = button.nextElementSibling;
    $('.tree-actions-menu').removeClass('show');
    
    if (!menu.classList.contains('show')) {
        menu.classList.add('show');
        
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && e.target !== button) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
}

function editFolder(folderId, folderName, description, isLocked) {
    $('#editFolderId').val(folderId);
    $('#editFolderName').val(folderName);
    $('#editDescription').val(description);
    $('#editPassword').val('');
    $('#removePassword').prop('checked', false);
    $('#editPassword').prop('disabled', false);
    
    if (isLocked == 1) {
        $('#editPassword').attr('placeholder', 'Enter new password to change current one');
    } else {
        $('#editPassword').attr('placeholder', 'Add password to protect folder');
    }
    
    $('#editFolderModal').modal('show');
    $('.tree-actions-menu').removeClass('show');
}

function deleteFolder(folderId, folderName, isLocked) {
    Swal.fire({
        title: 'Delete Folder?',
        html: `Are you sure you want to delete "<strong>${folderName}</strong>" and all its contents?<br><br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!',
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
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleted!',
                text: 'The folder has been deleted.',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        }
    });
    
    $('.tree-actions-menu').removeClass('show');
}

function shareFolder(folderId, folderName) {
    $('#shareFolderForm input[name="folder_id"]').val(folderId);
    $('#shareFolderModal .modal-title').html('Share Folder: ' + folderName);
    $('#shareEmployees').val(null).trigger('change');
    $('#permissionLevel').val('view');
    $('#expiresAt').val('');
    $('#shareFolderModal').modal('show');
    $('.tree-actions-menu').removeClass('show');
}

function manageShares(folderId, folderName) {
    const modal = $('#manageSharesModal');
    const content = $('#manageSharesContent');
    
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
        error: function() {
            content.html(`
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Failed to load shared access information.
                </div>
            `);
        }
    });
    
    $('.tree-actions-menu').removeClass('show');
}

function viewFile(fileId) {
    window.location.href = 'view_file.php?id=' + fileId;
}

// File Upload Functions
function initFileUpload() {
    const dropZone = $('#fileDropZone');
    const fileInput = $('#fileInput');
    const browseBtn = $('#browseFilesBtn');
    
    browseBtn.off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    fileInput.off('change').on('change', function(e) {
        handleFiles(e.target.files);
    });
    
    dropZone.off('dragover dragleave drop click').on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.addClass('dragover');
    }).on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.removeClass('dragover');
    }).on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    }).on('click', function(e) {
        if (e.target === this || $(e.target).hasClass('drop-zone-content')) {
            fileInput.trigger('click');
        }
    });
}

function handleFiles(files) {
    const maxFiles = 10;
    const maxFileSize = 500 * 1024 * 1024;
    
    if (!files || files.length === 0) return;
    
    const newFiles = Array.from(files);
    
    if (selectedFiles.length + newFiles.length > maxFiles) {
        Swal.fire('Error!', `You can only upload up to ${maxFiles} files at once.`, 'error');
        return;
    }
    
    newFiles.forEach(file => {
        if (selectedFiles.length < maxFiles) {
            const fileExists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            
            if (file.size > maxFileSize) {
                Swal.fire('Error!', `File "${file.name}" exceeds the 500MB size limit.`, 'error');
                return;
            }
            
            if (!fileExists) {
                selectedFiles.push(file);
            }
        }
    });
    
    updateFileList();
}

function updateFileList() {
    const container = $('#selectedFilesContainer');
    const list = $('#selectedFilesList');
    const count = $('#fileCount');
    
    list.empty();
    
    if (selectedFiles.length === 0) {
        container.hide();
        return;
    }
    
    selectedFiles.forEach((file, index) => {
        const fileSize = formatFileSize(file.size);
        
        const fileItem = `
            <div class="file-item" data-index="${index}">
                <div class="file-info">
                    <i class="fas fa-file file-icon"></i>
                    <div class="file-details">
                        <div class="file-name">${escapeHtml(file.name)}</div>
                        <div class="file-size">${fileSize}</div>
                    </div>
                </div>
                <button type="button" class="file-remove" onclick="removeFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        list.append(fileItem);
    });
    
    count.text(selectedFiles.length);
    container.show();
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Upload form submission
$('#uploadForm').submit(function(e) {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        Swal.fire('Error!', 'Please select at least one file to upload.', 'error');
        return;
    }
    
    const formData = new FormData(this);
    
    formData.delete('files[]');
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    
    const uploadBtn = $('#uploadBtn');
    const originalText = uploadBtn.html();
    const progress = $('#uploadProgress');
    
    uploadBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
    progress.show();
    
    $.ajax({
        url: 'folder_contents.php?folder_id=<?= $folder_id ?>&section_id=<?= $section_id ?>',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    $('.progress-bar').css('width', percentComplete + '%');
                    $('.progress-text').text(Math.round(percentComplete) + '%');
                }
            });
            return xhr;
        },
        success: function(response) {
            uploadBtn.prop('disabled', false).html(originalText);
            progress.hide();
            
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: result.message,
                        icon: 'success'
                    }).then(() => {
                        $('#uploadFileModal').modal('hide');
                        $('#uploadForm')[0].reset();
                        selectedFiles = [];
                        updateFileList();
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', result.message || 'Upload failed', 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Upload completed but server response was invalid.', 'warning');
                setTimeout(() => location.reload(), 2000);
            }
        },
        error: function() {
            uploadBtn.prop('disabled', false).html(originalText);
            progress.hide();
            Swal.fire('Error!', 'Failed to upload files.', 'error');
        }
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
                        icon: 'success'
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Invalid server response', 'error');
            }
        },
        error: function() {
            updateBtn.prop('disabled', false).html(originalText);
            Swal.fire('Error!', 'Failed to update permissions.', 'error');
        }
    });
});
</script>
</body>
</html>

<?php
function getFileIcon($fileType) {
    $type = strtolower($fileType);
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
        'rar' => 'archive',
        'txt' => 'alt',
        'mp4' => 'video',
        'avi' => 'video',
        'mov' => 'video',
        'mp3' => 'audio',
        'wav' => 'audio'
    ];
    
    return $icons[$type] ?? 'file';
}

function getFileIconClass($fileType) {
    $type = strtolower($fileType);
    $classes = [
        'pdf' => 'text-danger',
        'doc' => 'text-primary',
        'docx' => 'text-primary',
        'xls' => 'text-success',
        'xlsx' => 'text-success',
        'ppt' => 'text-warning',
        'pptx' => 'text-warning',
        'jpg' => 'text-info',
        'jpeg' => 'text-info',
        'png' => 'text-info',
        'gif' => 'text-info',
        'zip' => 'text-secondary',
        'rar' => 'text-secondary',
        'txt' => 'text-dark',
        'mp4' => 'text-danger',
        'avi' => 'text-danger',
        'mov' => 'text-danger',
        'mp3' => 'text-info',
        'wav' => 'text-info'
    ];
    
    return $classes[$type] ?? 'text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>