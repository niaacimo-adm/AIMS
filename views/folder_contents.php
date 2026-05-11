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

    $section_tree_mode = empty($folder_id) || !is_numeric($folder_id);

    // Fetch section name for section tree mode
    $section_name = "Manager's Office";
    if ($section_id !== 'manager' && is_numeric($section_id)) {
        $sn_stmt = $db->prepare("SELECT section_name FROM section WHERE section_id = ?");
        $sn_stmt->bind_param("i", $section_id);
        $sn_stmt->execute();
        $sn_res = $sn_stmt->get_result();
        if ($sn_res->num_rows > 0) $section_name = $sn_res->fetch_assoc()['section_name'];
    }

    if (!$section_tree_mode) {
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
    } // end !section_tree_mode

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_star') {
    $ts_file_id = intval($_POST['file_id'] ?? 0);
    $ts_star    = intval($_POST['starred'] ?? 1);
    $ts_chk     = $db->query("SHOW COLUMNS FROM files LIKE 'is_starred'");
    if ($ts_chk && $ts_chk->num_rows > 0) {
        $ts_stmt = $db->prepare("UPDATE files SET is_starred = ? WHERE file_id = ?");
        $ts_stmt->bind_param('ii', $ts_star, $ts_file_id);
        $ts_stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Star column missing. Run: ALTER TABLE files ADD COLUMN is_starred TINYINT(1) DEFAULT 0;']);
    }
    exit();
}

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
// Build folder tree function
function buildFolderTree($db, $parent_id = null, $user_emp_id = null, $level = 0) {
    $tree = [];
    
    if ($parent_id === null) {
        $query = "SELECT f.*, 
                         CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                         (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                         (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                  FROM folders f 
                  LEFT JOIN employee e ON f.created_by = e.emp_id 
                  WHERE f.parent_folder_id IS NULL
                  ORDER BY f.folder_name";
        $stmt = $db->prepare($query);
    } else {
        $query = "SELECT f.*, 
                         CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                         (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                         (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                  FROM folders f 
                  LEFT JOIN employee e ON f.created_by = e.emp_id 
                  WHERE f.parent_folder_id = ?
                  ORDER BY f.folder_name";
        $stmt = $db->prepare($query);
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

// Debug: Check if we have folders
if (empty($full_folder_tree)) {
    // If no root folders, try to get all folders
    $all_folders_stmt = $db->query("SELECT COUNT(*) as total FROM folders");
    $total_folders = $all_folders_stmt->fetch_assoc()['total'];
    error_log("Total folders in database: " . $total_folders);
}

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

// Alias so both names work
function renderChildren($children, $db, $user_emp_id, $current_folder_id) {
    return renderTreeChildren($children, $db, $user_emp_id, $current_folder_id);
}

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
    $i = (int) floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $section_tree_mode ? htmlspecialchars($section_name) . ' - Folders' : htmlspecialchars($folder_name) . ' - Contents' ?></title>
    
    <?php include '../includes/header.php'; ?>
    
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php
    // section_name already set above
    $sb_role_stmt = $db->prepare("SELECT ur.id as role_id FROM employee e LEFT JOIN users u ON e.emp_id = u.employee_id LEFT JOIN user_roles ur ON u.role_id = ur.id WHERE e.emp_id = ?");
    $sb_role_stmt->bind_param("i", $user_emp_id);
    $sb_role_stmt->execute();
    $sb_role_result = $sb_role_stmt->get_result();
    $sb_is_admin = false;
    if ($sb_role_result->num_rows > 0) {
        $sb_role_data = $sb_role_result->fetch_assoc();
        $sb_is_admin = in_array($sb_role_data['role_id'], [1, 2]);
    }

    if ($section_id === 'manager') {
        if ($sb_is_admin) {
            $sb_q = "SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count FROM folders f WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL ORDER BY f.folder_name";
            $sb_stmt = $db->prepare($sb_q);
        } else {
            $sb_q = "SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count FROM folders f WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL AND (f.created_by = ? OR EXISTS (SELECT 1 FROM folder_shares fs WHERE fs.folder_id = f.folder_id AND fs.shared_with_emp_id = ? AND fs.is_active = TRUE)) ORDER BY f.folder_name";
            $sb_stmt = $db->prepare($sb_q);
            $sb_stmt->bind_param("ii", $user_emp_id, $user_emp_id);
        }
    } else {
        if ($sb_is_admin) {
            $sb_q = "SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count FROM folders f WHERE f.section_id = ? AND f.parent_folder_id IS NULL ORDER BY f.folder_name";
            $sb_stmt = $db->prepare($sb_q);
            $sb_stmt->bind_param("i", $section_id);
        } else {
            $sb_q = "SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count FROM folders f WHERE f.section_id = ? AND f.parent_folder_id IS NULL AND (f.created_by = ? OR EXISTS (SELECT 1 FROM folder_shares fs WHERE fs.folder_id = f.folder_id AND fs.shared_with_emp_id = ? AND fs.is_active = TRUE)) ORDER BY f.folder_name";
            $sb_stmt = $db->prepare($sb_q);
            $sb_stmt->bind_param("iii", $section_id, $user_emp_id, $user_emp_id);
        }
    }
    $sb_stmt->execute();
    $sb_result = $sb_stmt->get_result();
    $folders = [];
    while ($sb_row = $sb_result->fetch_assoc()) { $folders[] = $sb_row; }
    ?>
    <?php include '../includes/sidebar_file.php'; ?>
    
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?= $section_tree_mode ? htmlspecialchars($section_name) : htmlspecialchars($folder_name) ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="file_management.php">File Management</a></li>
                            <?php if ($section_tree_mode): ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($section_name) ?></li>
                            <?php else: ?>
                            <li class="breadcrumb-item"><a href="section_files.php?section_id=<?= $section_id ?>"><?= htmlspecialchars($folder['section_name'] ?? 'Manager\'s Office') ?></a></li>
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <?php if ($index < count($breadcrumbs) - 1): ?>
                                    <li class="breadcrumb-item"><a href="folder_contents.php?folder_id=<?= $crumb['folder_id'] ?>&section_id=<?= $section_id ?>"><?= htmlspecialchars($crumb['folder_name']) ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['folder_name']) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
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
                    <?php if ($section_tree_mode): ?>
                    <!-- ── Section Folder Tree View ───────────────── -->
                    <div class="sidebar1">
                        <div class="sidebar-header1">
                            <h5><i class="fas fa-folder-tree"></i> <?= htmlspecialchars($section_name) ?></h5>
                        </div>
                        <div id="folderTree">
                            <?php if (!empty($full_folder_tree)): ?>
                                <ul class="tree">
                                    <?php foreach ($full_folder_tree as $folder_item):
                                        $has_access = hasFolderPermission($db, $folder_item['folder_id'], $user_emp_id, 'view');
                                    ?>
                                    <li class="tree-item" data-folder-id="<?= $folder_item['folder_id'] ?>">
                                        <div class="tree-node <?= $folder_item['is_locked'] ? 'locked' : '' ?> <?= !$has_access ? 'no-access' : '' ?>"
                                            onclick="navigateToFolder(<?= $folder_item['folder_id'] ?>, <?= $folder_item['is_locked'] ?>)">
                                            <div class="tree-expander" onclick="toggleTreeBranch(this, event)">
                                                <i class="fas fa-<?= !empty($folder_item['children']) ? 'chevron-right' : 'minus' ?>"></i>
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
                                        </div>
                                        <?php if (!empty($folder_item['children'])): ?>
                                            <?php echo renderChildren($folder_item['children'], $db, $user_emp_id, null); ?>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open fa-2x mb-2"></i><br>No folders yet
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- ── Normal folder contents ─────────────────── -->
                    <div class="sidebar1">
                        <div class="sidebar-header1">
                            <h5>
                                <i class="fas fa-folder-tree"></i>
                                My Files
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
                                            <?php echo renderChildren($folder_item['children'], $db, $user_emp_id, $folder_id); ?>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No folders found</p>
                                    <?php if (isset($total_folders) && $total_folders > 0): ?>
                                        <small class="text-muted">Database has <?= $total_folders ?> folders but none are root folders.</small>
                                    <?php endif; ?>
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
                                    
                                   

                                    <div class="file-list">
                                        <div class="file-header">
                                            <div class="file-check-col"><input type="checkbox" id="selectAll" class="mr-2"></div>
                                            <div class="file-name-col">Name</div>
                                            <div class="file-type-col">Type</div>
                                            <div class="file-size-col">Size</div>
                                            <div class="file-date-col">Uploaded</div>
                                            <div class="file-action-col">
                                                <button type="button" id="deleteSelectedBtn" class="toolbar-btn" style="color: var(--danger-color);" disabled>
                                                    <i class="fas fa-trash"></i> Delete Selected
                                                </button>
                                            </div>
                                                
                                        </div>
                                        
                                        <?php foreach ($files as $file): ?>
                                        <div class="file-row" ondblclick="viewFileModal(<?= $file['file_id'] ?>)">
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
                                                <button type="button" class="file-action-btn" title="View" onclick="event.stopPropagation(); viewFileModal(<?= $file['file_id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
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
                <?php endif; // end section_tree_mode else ?>
            </div>
        </section>
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
<!-- File View Modal (similar to section_files.php) -->
<div class="modal fade fv-modal" id="fileViewModal" tabindex="-1" role="dialog" aria-labelledby="fvFileName" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center" style="flex:1;min-width:0;">
                    <i id="fvFileIcon" class="fas fa-file mr-2" style="font-size:20px;flex-shrink:0;"></i>
                    <h5 class="modal-title mb-0 text-truncate" id="fvFileName" style="max-width:420px;">Loading...</h5>
                </div>
                <div class="fv-header-actions">
                    <a id="fvDownloadBtn" href="#" class="fv-header-btn" title="Download" target="_blank">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="fv-header-btn" title="Edit" onclick="openCurrentFileEdit()">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button class="fv-header-btn fv-star-btn" id="fvStarBtn" title="Star" onclick="toggleCurrentFileStar()">
                        <i class="far fa-star"></i>
                    </button>
                    <button type="button" class="fv-header-btn" data-dismiss="modal" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 d-flex" style="min-height:340px;">
                <!-- Icon display (no preview) -->
                <div class="fv-icon-area d-flex flex-column align-items-center justify-content-center flex-grow-1">
                    <i id="fvBigIcon" class="fas fa-file fv-big-icon"></i>
                    <span id="fvTypeBadge" class="fv-type-badge">—</span>
                    <p id="fvFileNameSub" class="fv-file-name-sub text-truncate">—</p>
                    <a id="fvDownloadBtn2" href="#" class="btn btn-primary btn-sm mt-1" target="_blank">
                        <i class="fas fa-download mr-1"></i> Download File
                    </a>
                </div>
                <!-- Info panel -->
                <div class="fv-info-panel">
                    <h6 class="fv-info-title">File Details</h6>
                    <div class="fv-info-row">
                        <span class="fv-info-label"><i class="fas fa-tag"></i> Type</span>
                        <span class="fv-info-value" id="fvFileType">—</span>
                    </div>
                    <div class="fv-info-row">
                        <span class="fv-info-label"><i class="fas fa-weight-hanging"></i> Size</span>
                        <span class="fv-info-value" id="fvFileSize">—</span>
                    </div>
                    <div class="fv-info-row">
                        <span class="fv-info-label"><i class="fas fa-user"></i> Owner</span>
                        <span class="fv-info-value" id="fvOwner">—</span>
                    </div>
                    <div class="fv-info-row">
                        <span class="fv-info-label"><i class="fas fa-calendar"></i> Modified</span>
                        <span class="fv-info-value" id="fvDate">—</span>
                    </div>
                    <div class="fv-info-row">
                        <span class="fv-info-label"><i class="fas fa-folder"></i> Location</span>
                        <span class="fv-info-value" id="fvLocation">—</span>
                    </div>
                    <hr>
                    <h6 class="fv-info-title">Description</h6>
                    <div id="fvDescription" class="text-muted mb-3" style="font-size:0.8rem;">—</div>
                    <hr>
                    <button class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="openCurrentFileEdit()">
                        <i class="fas fa-pencil-alt mr-1"></i> Edit File
                    </button>
                    <button class="btn btn-sm w-100" id="fvStarBtn2" onclick="toggleCurrentFileStar()" style="border:1px solid #f59e0b; color:#b45309; background:transparent;">
                        <i class="far fa-star mr-1"></i> <span id="fvStarBtnLabel">Add to Starred</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File Edit Modal -->
<div class="modal fade" id="fileEditModal" tabindex="-1" role="dialog" aria-labelledby="fileEditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-color);">
                <h5 class="modal-title text-white" id="fileEditModalLabel">
                    <i class="fas fa-pencil-alt mr-2"></i> Edit File
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="editFileNameInput" class="font-weight-bold">File Name</label>
                    <input type="text" class="form-control" id="editFileNameInput" placeholder="Enter file name">
                    <small class="form-text text-muted">Change the file name (extension will be preserved)</small>
                </div>
                <input type="hidden" id="editFileIdInput">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveFileEdit()">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>


    <?php include '../includes/mainfooter.php'; ?>
</div>


<?php include '../includes/footer.php'; ?>
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

    // Navigate back to the section root view from inside a folder
    function showRootView() {
        window.location.href = 'section_files.php?section_id=<?= $section_id ?>';
    }

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
    // File view modal functions
function viewFileModal(fileId) {
    $('#fvFileName').text('Loading…');
    $('#fvBigIcon').attr('class', 'fas fa-spinner fa-spin fv-big-icon');
    $('#fileViewModal').modal('show');

    $.ajax({
        url: 'view_file.php',
        type: 'GET',
        data: { id: fileId, ajax: 'true' },
        success: function(resp) {
            try {
                const d = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (!d.success) {
                    $('#fvBigIcon').attr('class', 'fas fa-exclamation-circle text-danger fv-big-icon');
                    $('#fvFileName').text('Could not load file');
                    return;
                }
                const f = d.file;

                const iconMap = {
                    pdf: 'fas fa-file-pdf text-danger',
                    doc: 'fas fa-file-word text-primary',   docx: 'fas fa-file-word text-primary',
                    xls: 'fas fa-file-excel text-success',  xlsx: 'fas fa-file-excel text-success',
                    ppt: 'fas fa-file-powerpoint text-warning', pptx: 'fas fa-file-powerpoint text-warning',
                    jpg: 'fas fa-file-image text-info',     jpeg: 'fas fa-file-image text-info',
                    png: 'fas fa-file-image text-info',     gif:  'fas fa-file-image text-info',
                    mp4: 'fas fa-file-video text-danger',   avi:  'fas fa-file-video text-danger',
                    txt: 'fas fa-file-alt text-dark',
                    zip: 'fas fa-file-archive text-secondary', rar: 'fas fa-file-archive text-secondary',
                    mp3: 'fas fa-file-audio text-info'
                };
                const iconClass = iconMap[f.file_type.toLowerCase()] || 'fas fa-file text-secondary';

                // Header
                $('#fvFileIcon').attr('class', iconClass + ' mr-2');
                $('#fvFileName').text(f.file_name);

                // Icon display area
                $('#fvBigIcon').attr('class', iconClass + ' fv-big-icon');
                $('#fvTypeBadge').text(f.file_type.toUpperCase());
                $('#fvFileNameSub').text(f.file_name);
                $('#fvDownloadBtn').attr('href', 'download_file.php?id=' + f.file_id);
                $('#fvDownloadBtn2').attr('href', 'download_file.php?id=' + f.file_id);

                // Info panel
                $('#fvFileType').text(f.file_type.toUpperCase());
                $('#fvFileSize').text(formatBytes(f.file_size));
                $('#fvOwner').text(f.uploaded_by || 'me');
                $('#fvDate').text(f.created_at ? new Date(f.created_at).toLocaleDateString() : '—');
                $('#fvLocation').text(f.folder_name || 'Root');
                $('#fvDescription').text(f.description || '—');

                // Star state
                const starred = f.is_starred ? true : false;
                _updateStarUI(starred);

                window._fvCurrentFile = f;
            } catch(e) {
                $('#fvBigIcon').attr('class', 'fas fa-exclamation-circle text-danger fv-big-icon');
                $('#fvFileName').text('Error loading file');
            }
        },
        error: function() {
            $('#fvBigIcon').attr('class', 'fas fa-exclamation-circle text-danger fv-big-icon');
            $('#fvFileName').text('Failed to load file');
        }
    });
}

function formatBytes(bytes) {
    if (!bytes || bytes == 0) return '0 B';
    const k = 1024, sizes = ['B','KB','MB','GB'];
    const i = Math.floor(Math.log(bytes)/Math.log(k));
    return parseFloat((bytes/Math.pow(k,i)).toFixed(2)) + ' ' + sizes[i];
}

function openCurrentFileEdit() {
    if (window._fvCurrentFile) {
        $('#fileViewModal').modal('hide');
        setTimeout(() => openFileEdit(window._fvCurrentFile.file_id, window._fvCurrentFile.file_name, window._fvCurrentFile.description || ''), 300);
    }
}

function openFileEdit(fileId, fileName, description) {
    $('#editFileIdInput').val(fileId);
    $('#editFileNameInput').val(fileName);
    $('#fileEditModal').modal('show');
}

function saveFileEdit() {
    const fileId = $('#editFileIdInput').val();
    const fileName = $('#editFileNameInput').val().trim();
    if (!fileName) {
        Swal.fire('Validation', 'File name cannot be empty.', 'warning');
        return;
    }

    $.ajax({
        url: 'view_file.php',
        type: 'POST',
        data: { action:'update_file', file_id:fileId, file_name:fileName },
        success: function(resp) {
            try {
                const r = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (r.success) {
                    $('#fileEditModal').modal('hide');
                    Swal.fire({
                        title: 'Saved!',
                        text: r.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', r.message, 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Invalid server response', 'error');
            }
        }
    });
}
// Star UI helper
function _updateStarUI(starred) {
    const ic = starred ? 'fas fa-star' : 'far fa-star';
    const col = starred ? '#f59e0b' : '';
    $('#fvStarBtn i').attr('class', ic).css('color', col);
    $('#fvStarBtn2 i').attr('class', ic + ' mr-1').css('color', col);
    $('#fvStarBtnLabel').text(starred ? 'Remove from Starred' : 'Add to Starred');
    $('#fvStarBtn2').css({
        'border-color': starred ? '#f59e0b' : '#f59e0b',
        'background': starred ? '#fffbeb' : 'transparent',
        'color': starred ? '#b45309' : '#b45309'
    });
}

function toggleCurrentFileStar() {
    if (!window._fvCurrentFile) return;
    const fileId    = window._fvCurrentFile.file_id;
    const isStarred = window._fvCurrentFile.is_starred ? true : false;
    $.post('folder_contents.php?folder_id=<?= (int)$folder_id ?>&section_id=<?= (int)$section_id ?>',
        { action: 'toggle_star', file_id: fileId, starred: isStarred ? 0 : 1 },
        function(resp) {
            try {
                const r = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (r.success) {
                    window._fvCurrentFile.is_starred = !isStarred;
                    _updateStarUI(!isStarred);
                    Swal.fire({
                        title: !isStarred ? 'Added to Starred' : 'Removed from Starred',
                        icon: 'success', timer: 1200, showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', r.message || 'Could not update star.', 'error');
                }
            } catch(e) { console.error('Star error:', e); }
        }
    );
}

function shareCurrentFile() {
    if (!window._fvCurrentFile) return;
    Swal.fire({ title: 'Share File', text: 'Sharing functionality will be implemented here', icon: 'info' });
}
</script>
</body>
</html>