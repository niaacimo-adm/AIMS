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

    // FUNCTION: Get user employee ID consistently
    function getUserEmployeeId($db, $session_user_id) {
        // First, try to get the employee record for the logged-in user
        $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
        $emp_stmt->bind_param("i", $session_user_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();

        if ($emp_result->num_rows > 0) {
            $emp_data = $emp_result->fetch_assoc();
            return $emp_data['emp_id'];
        } else {
            // If no direct match, check if the user_id exists in the users table and get the associated employee_id
            $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $session_user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                if ($user_data['employee_id']) {
                    // Verify this employee_id exists in the employee table
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
            
            // If no valid employee record found, return null
            return null;
        }
    }

    // Get user employee ID for permission checks (USE THE FUNCTION)
    $user_emp_id = getUserEmployeeId($db, $_SESSION['user_id']);

    // Add this check after getting the user_emp_id
    if (!$user_emp_id) {
        $_SESSION['error'] = "No valid employee record found. Please contact administrator.";
        header("Location: ../login.php");
        exit();
    }

    // Get section ID from URL parameter
    $section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';
    // Validate user access to this section
    if (!userBelongsToSection($db, $user_emp_id, $section_id)) {
        $_SESSION['error'] = "You do not have access to this section.";
        
        // Redirect to user's default section or file management
        $default_section_stmt = $db->prepare("SELECT section_id FROM employee WHERE emp_id = ?");
        $default_section_stmt->bind_param("i", $user_emp_id);
        $default_section_stmt->execute();
        $default_section_result = $default_section_stmt->get_result();
        
        if ($default_section_result->num_rows > 0) {
            $user_section = $default_section_result->fetch_assoc();
            header("Location: section_files.php?section_id=" . ($user_section['section_id'] ?: 'manager'));
        } else {
            header("Location: file_management.php");
        }
        exit();
    }
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

    function userBelongsToSection($db, $user_emp_id, $section_id) {
        if ($section_id === 'manager') {
            // For manager's office, check if user has access rights
            // Users with section_id NULL/0 or users with admin/manager roles can access
            $stmt = $db->prepare("SELECT e.emp_id 
                                FROM employee e 
                                LEFT JOIN users u ON e.emp_id = u.employee_id
                                LEFT JOIN user_roles ur ON u.role_id = ur.id
                                WHERE e.emp_id = ? 
                                AND (e.section_id IS NULL OR e.section_id = 0 OR ur.id IN (1, 2))");
            $stmt->bind_param("i", $user_emp_id);
        } else {
            // For regular sections, check if user belongs to the section or is admin/manager
            $stmt = $db->prepare("SELECT e.emp_id 
                                FROM employee e 
                                LEFT JOIN users u ON e.emp_id = u.employee_id
                                LEFT JOIN user_roles ur ON u.role_id = ur.id
                                WHERE e.emp_id = ? 
                                AND (e.section_id = ? OR ur.id IN (1, 2))");
            $stmt->bind_param("ii", $user_emp_id, $section_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    function hasFolderPermission($db, $folder_id, $user_emp_id, $permission_type = 'view') {
        // First, check if user is admin or manager (full access)
        $role_stmt = $db->prepare("SELECT ur.id as role_id 
                                FROM employee e 
                                LEFT JOIN users u ON e.emp_id = u.employee_id
                                LEFT JOIN user_roles ur ON u.role_id = ur.id
                                WHERE e.emp_id = ?");
        $role_stmt->bind_param("i", $user_emp_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();

        if ($role_result->num_rows > 0) {
            $user_data = $role_result->fetch_assoc();
            // Admin (1) and Manager (2) have full access
            if (in_array($user_data['role_id'], [1, 2])) {
                return true;
            }
        }
        
        // Check if user is the creator (has full access)
        $creator_stmt = $db->prepare("SELECT created_by, section_id FROM folders WHERE folder_id = ?");
        $creator_stmt->bind_param("i", $folder_id);
        $creator_stmt->execute();
        $creator_result = $creator_stmt->get_result();
        
        if ($creator_result->num_rows > 0) {
            $folder_data = $creator_result->fetch_assoc();
            
            // Creator has full access to their folders
            if ($folder_data['created_by'] == $user_emp_id) {
                return true;
            }
            
            // Check if user belongs to the folder's section
            $section_check = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ? AND section_id = ?");
            $section_check->bind_param("ii", $user_emp_id, $folder_data['section_id']);
            $section_check->execute();
            $section_result = $section_check->get_result();
            
            if ($section_result->num_rows > 0) {
                return true; // User belongs to the folder's section
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
                return true; // Creator has full access to everything
            }
        }
        
        // Define required permissions for each action
        $permission_map = [
            'view_files' => 'view',
            'upload_files' => 'upload',
            'download_files' => 'edit', // Download requires edit permission
            'edit_files' => 'edit',
            'delete_files' => 'edit',
            'create_folder' => 'manage',
            'edit_folder' => 'manage',
            'delete_folder' => 'manage',
            'share_folder' => 'manage',
            'manage_shares' => 'manage', // Add this line
            'update_share' => 'manage'   // Add this line
        ];
        
        $required_permission = $permission_map[$action] ?? 'manage';
        return hasFolderPermission($db, $folder_id, $user_emp_id, $required_permission);
    }

    function getAllFolders($db, $section_id, $user_emp_id) {
        $all_folders = [];
        
        $role_stmt = $db->prepare("SELECT ur.id as role_id 
                                FROM employee e 
                                LEFT JOIN users u ON e.emp_id = u.employee_id
                                LEFT JOIN user_roles ur ON u.role_id = ur.id
                                WHERE e.emp_id = ?");
        $role_stmt->bind_param("i", $user_emp_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $is_admin_manager = false;

        if ($role_result->num_rows > 0) {
            $user_data = $role_result->fetch_assoc();
            $is_admin_manager = in_array($user_data['role_id'], [1, 2]);
        }
        
        if ($section_id === 'manager') {
            if ($is_admin_manager) {
                // Admin/Manager can see all manager folders
                $query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL
                        ORDER BY f.folder_name";
            } else {
                // Regular users only see their own manager folders or shared ones
                $query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL
                        AND (f.created_by = ? OR EXISTS (
                            SELECT 1 FROM folder_shares fs 
                            WHERE fs.folder_id = f.folder_id 
                            AND fs.shared_with_emp_id = ? 
                            AND fs.is_active = TRUE
                        ))
                        ORDER BY f.folder_name";
            }
            $stmt = $db->prepare($query);
            if (!$is_admin_manager) {
                $stmt->bind_param("ii", $user_emp_id, $user_emp_id);
            }
        } else {
            if ($is_admin_manager) {
                // Admin/Manager can see all section folders
                $query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id = ? AND f.parent_folder_id IS NULL
                        ORDER BY f.folder_name";
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $section_id);
            } else {
                // Regular users only see folders from their section or shared folders
                $query = "SELECT f.*, 
                                CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                                (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                                (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                        FROM folders f 
                        LEFT JOIN employee e ON f.created_by = e.emp_id 
                        WHERE f.section_id = ? AND f.parent_folder_id IS NULL
                        AND (f.created_by = ? OR EXISTS (
                            SELECT 1 FROM folder_shares fs 
                            WHERE fs.folder_id = f.folder_id 
                            AND fs.shared_with_emp_id = ? 
                            AND fs.is_active = TRUE
                        ))
                        ORDER BY f.folder_name";
                $stmt = $db->prepare($query);
                $stmt->bind_param("iii", $section_id, $user_emp_id, $user_emp_id);
            }
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $all_folders[] = $row;
        }
        
        return $all_folders;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            // Use the SAME function to get user_emp_id for POST requests
            $user_emp_id = getUserEmployeeId($db, $_SESSION['user_id']);

            // If no employee ID found, show error
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
                // Check if user has permission to create folders in this section
                if ($section_id !== 'manager') {
                    // For section folders, check if user belongs to the section or has manage permissions
                    $section_check = $db->prepare("SELECT section_id FROM employee WHERE emp_id = ? AND section_id = ?");
                    $section_check->bind_param("ii", $user_emp_id, $section_id);
                    $section_check->execute();
                    $section_result = $section_check->get_result();
                    
                    if ($section_result->num_rows === 0) {
                        $_SESSION['error'] = "You don't have permission to create folders in this section.";
                        header("Location: section_files.php?section_id=" . $section_id);
                        exit();
                    }
                }
                
                $folder_name = trim($_POST['folder_name']);
                $description = trim($_POST['description']);
                $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
                $is_locked = !empty($_POST['password']) ? 1 : 0;
                
                $stmt = $db->prepare("INSERT INTO folders (folder_name, description, section_id, password, created_by, is_locked) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $section_id_value = ($section_id === 'manager') ? NULL : intval($section_id);
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

                    if (!hasFolderPermission($db, $folder_id, $user_emp_id, 'view')) {
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to access this folder.']);
                        exit();
                    }

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
                    if (!hasFolderPermission($db, $folder_id, $user_emp_id, 'manage')) {
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this folder.']);
                        exit();
                    }
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

                    $creator_stmt = $db->prepare("SELECT created_by FROM folders WHERE folder_id = ?");
                    $creator_stmt->bind_param("i", $folder_id);
                    $creator_stmt->execute();
                    $creator_result = $creator_stmt->get_result();
                    
                    if ($creator_result->num_rows > 0) {
                        $folder_data = $creator_result->fetch_assoc();
                        if ($folder_data['created_by'] != $user_emp_id && 
                            !hasFolderPermission($db, $folder_id, $user_emp_id, 'manage')) {
                            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this folder.']);
                            exit();
                        }
                    }
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

                    case 'share_folder':
                        $folder_id = $_POST['folder_id'];
                        if (!hasFolderPermission($db, $folder_id, $user_emp_id, 'manage')) {
                            echo json_encode(['success' => false, 'message' => 'You do not have permission to share this folder.']);
                            exit();
                        }
                        $employee_ids = $_POST['employee_ids'] ?? [];
                        $permission_level = $_POST['permission_level'] ?? 'view';
                        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

                        if (empty($employee_ids)) {
                            echo json_encode(['success' => false, 'message' => 'Please select at least one employee.']);
                            exit();
                        }

                        $success_count = 0;
                        $error_count = 0;
                        $results = [];

                        foreach ($employee_ids as $emp_id) {
                            // Check if share already exists
                            $check_stmt = $db->prepare("SELECT share_id FROM folder_shares WHERE folder_id = ? AND shared_with_emp_id = ?");
                            $check_stmt->bind_param("ii", $folder_id, $emp_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();

                            if ($check_result->num_rows > 0) {
                                // Update existing share
                                $update_stmt = $db->prepare("UPDATE folder_shares SET permission_level = ?, expires_at = ?, is_active = TRUE WHERE folder_id = ? AND shared_with_emp_id = ?");
                                $update_stmt->bind_param("ssii", $permission_level, $expires_at, $folder_id, $emp_id);
                                if ($update_stmt->execute()) {
                                    $success_count++;
                                    $results[] = ['emp_id' => $emp_id, 'action' => 'updated', 'success' => true];
                                } else {
                                    $error_count++;
                                    $results[] = ['emp_id' => $emp_id, 'action' => 'update_failed', 'success' => false, 'error' => $db->error];
                                }
                            } else {
                                // Create new share
                                $insert_stmt = $db->prepare("INSERT INTO folder_shares (folder_id, shared_by_emp_id, shared_with_emp_id, permission_level, expires_at) VALUES (?, ?, ?, ?, ?)");
                                $insert_stmt->bind_param("iiiss", $folder_id, $user_emp_id, $emp_id, $permission_level, $expires_at);
                                if ($insert_stmt->execute()) {
                                    $success_count++;
                                    $results[] = ['emp_id' => $emp_id, 'action' => 'shared', 'success' => true];

                                    // Log sharing activity
                                    $emp_stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employee WHERE emp_id = ?");
                                    $emp_stmt->bind_param("i", $emp_id);
                                    $emp_stmt->execute();
                                    $emp_result = $emp_stmt->get_result();
                                    $emp_name = $emp_result->fetch_assoc()['name'] ?? 'Unknown';

                                    // Check if folder_share_logs table exists
                                    $check_table = $db->query("SHOW TABLES LIKE 'folder_share_logs'");
                                    if ($check_table->num_rows > 0) {
                                        $log_stmt = $db->prepare("INSERT INTO folder_share_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'shared', ?, ?)");
                                        $log_description = "Folder shared with {$emp_name} ({$permission_level} access)";
                                        $ip = $_SERVER['REMOTE_ADDR'];
                                        $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                                        $log_stmt->execute();
                                    }
                                } else {
                                    $error_count++;
                                    $results[] = ['emp_id' => $emp_id, 'action' => 'share_failed', 'success' => false, 'error' => $db->error];
                                }
                            }
                        }

                        echo json_encode([
                            'success' => $error_count === 0,
                            'message' => "Shared with {$success_count} employee(s). " . ($error_count > 0 ? "Failed for {$error_count} employee(s)." : ""),
                            'results' => $results
                        ]);
                        exit();

                    case 'revoke_access':
                        $share_id = $_POST['share_id'];
                        
                        $stmt = $db->prepare("UPDATE folder_shares SET is_active = FALSE WHERE share_id = ?");
                        $stmt->bind_param("i", $share_id);
                        
                        if ($stmt->execute()) {
                            // Log revoke activity if table exists
                            $check_table = $db->query("SHOW TABLES LIKE 'folder_share_logs'");
                            if ($check_table->num_rows > 0) {
                                $log_stmt = $db->prepare("INSERT INTO folder_share_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'access_revoked', ?, ?)");
                                $log_description = "Folder access revoked";
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                                $log_stmt->execute();
                            }
                            
                            echo json_encode(['success' => true, 'message' => 'Access revoked successfully.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to revoke access.']);
                        }
                        exit();

                    case 'get_employees':
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
                        $employees = [];
                        while ($row = $employees_result->fetch_assoc()) {
                            $employees[] = $row;
                        }
                        
                        echo json_encode(['success' => true, 'employees' => $employees]);
                        exit();

                    case 'get_shares':
                        $folder_id = $_POST['folder_id'];
                        if (!hasFolderPermission($db, $folder_id, $user_emp_id, 'view')) {
                            echo '<div class="alert alert-danger text-center">You do not have permission to view this folder.</div>';
                            exit();
                        }
                        
                        // Check if user has permission to manage shares
                        $can_manage = canPerformAction($db, $folder_id, $user_emp_id, 'manage_shares');
                        
                        // Fetch existing shares for this folder
                        $folder_shares = [];
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
                            while ($row = $shares_result->fetch_assoc()) {
                                $folder_shares[] = $row;
                            }
                        } catch (Exception $e) {
                            error_log("Folder shares table not found: " . $e->getMessage());
                            $folder_shares = [];
                        }
                        
                        if (empty($folder_shares)) {
                            echo '<div class="alert alert-info text-center"><i class="fas fa-info-circle mr-2"></i>This folder is not shared with anyone.</div>';
                        } else {
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
                    case 'update_share':
                        $share_id = $_POST['share_id'];
                        $permission_level = $_POST['permission_level'] ?? 'view';
                        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                        
                        // Get folder_id from the share to check permissions
                        $folder_stmt = $db->prepare("SELECT folder_id FROM folder_shares WHERE share_id = ?");
                        $folder_stmt->bind_param("i", $share_id);
                        $folder_stmt->execute();
                        $folder_result = $folder_stmt->get_result();
                        
                        if ($folder_result->num_rows > 0) {
                            $share_data = $folder_result->fetch_assoc();
                            $folder_id = $share_data['folder_id'];
                            
                            // Check if user has permission to manage shares for this folder
                            if (!canPerformAction($db, $folder_id, $user_emp_id, 'manage_shares')) {
                                echo json_encode(['success' => false, 'message' => 'You do not have permission to manage shares for this folder.']);
                                exit();
                            }
                            
                            // Update the share
                            $update_stmt = $db->prepare("UPDATE folder_shares SET permission_level = ?, expires_at = ? WHERE share_id = ?");
                            $update_stmt->bind_param("ssi", $permission_level, $expires_at, $share_id);
                            
                            if ($update_stmt->execute()) {
                                // Log the activity if table exists
                                $check_table = $db->query("SHOW TABLES LIKE 'folder_share_logs'");
                                if ($check_table->num_rows > 0) {
                                    $log_stmt = $db->prepare("INSERT INTO folder_share_logs (folder_id, emp_id, activity_type, description, ip_address) VALUES (?, ?, 'share_updated', ?, ?)");
                                    $log_description = "Share permissions updated to {$permission_level}";
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $log_stmt->bind_param("iiss", $folder_id, $user_emp_id, $log_description, $ip);
                                    $log_stmt->execute();
                                }
                                
                                echo json_encode(['success' => true, 'message' => 'Share permissions updated successfully.']);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Failed to update share permissions.']);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Share not found.']);
                        }
                    exit();
                    case 'upload_file':
                    case 'upload_files':
                        // Handle file uploads at section level (not in a folder)
                        $upload_results = [];
                        $has_success = false;
                        $has_error = false;

                        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                            $file_count = count($_FILES['files']['name']);

                            for ($i = 0; $i < $file_count; $i++) {
                                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                                    $file_name = basename($_FILES['files']['name'][$i]);
                                    $file_size = $_FILES['files']['size'][$i];
                                    $file_tmp  = $_FILES['files']['tmp_name'][$i];
                                    $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                                    $description = trim($_POST['description'] ?? '');
                                    $folder_id_val = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : null;

                                    $max_size = 500 * 1024 * 1024;
                                    if ($file_size > $max_size) {
                                        $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'File exceeds 500MB limit.'];
                                        $has_error = true;
                                        continue;
                                    }

                                    $unique_name = uniqid() . '_' . time() . '_' . $i . '.' . strtolower($file_type);
                                    $upload_dir  = '../uploads/';
                                    $file_path   = $upload_dir . $unique_name;

                                    if (!is_dir($upload_dir)) {
                                        mkdir($upload_dir, 0755, true);
                                    }

                                    if (move_uploaded_file($file_tmp, $file_path)) {
                                        $section_id_value = ($section_id === 'manager') ? NULL : (is_numeric($section_id) ? intval($section_id) : NULL);

                                        // Check if description column exists
                                        $check_column = $db->query("SHOW COLUMNS FROM files LIKE 'description'");
                                        if ($check_column->num_rows > 0) {
                                            $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, description, section_id, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                            $stmt->bind_param("ssssisii", $file_name, $unique_name, $file_type, $file_size, $description, $section_id_value, $folder_id_val, $user_emp_id);
                                        } else {
                                            $stmt = $db->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, section_id, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                            $stmt->bind_param("ssssiii", $file_name, $unique_name, $file_type, $file_size, $section_id_value, $folder_id_val, $user_emp_id);
                                        }

                                        if ($stmt->execute()) {
                                            $upload_results[] = ['file' => $file_name, 'success' => true, 'message' => 'Uploaded successfully!'];
                                            $has_success = true;
                                        } else {
                                            unlink($file_path);
                                            $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'DB insert failed: ' . $db->error];
                                            $has_error = true;
                                        }
                                    } else {
                                        $upload_results[] = ['file' => $file_name, 'success' => false, 'message' => 'Failed to move uploaded file.'];
                                        $has_error = true;
                                    }
                                } else {
                                    $upload_results[] = ['file' => $_FILES['files']['name'][$i] ?? 'unknown', 'success' => false, 'message' => 'Upload error code: ' . $_FILES['files']['error'][$i]];
                                    $has_error = true;
                                }
                            }

                            $success_count = count(array_filter($upload_results, fn($r) => $r['success']));
                            $total_count   = count($upload_results);
                            $message = "Uploaded {$success_count} of {$total_count} file(s).";
                            echo json_encode(['success' => $has_success, 'message' => $message, 'results' => $upload_results, 'uploaded_count' => $success_count]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'No files were selected.']);
                        }
                    exit();

                    case 'check_folder_permission':
                        $folder_id = $_POST['folder_id'];
                        $has_permission = hasFolderPermission($db, $folder_id, $user_emp_id, 'view');
                        echo json_encode(['has_permission' => $has_permission]);
                    exit();
            }
        }
    }

    // Use the new function to get accessible folders
    $folders = getAllFolders($db, $section_id, $user_emp_id);

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

    function getPermissionBadgeClass($permission) {
        switch ($permission) {
            case 'view': return 'info';
            case 'upload': return 'primary';
            case 'edit': return 'warning';
            case 'manage': return 'success';
            default: return 'secondary';
        }
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
        /* Modern CSS Variables for theming */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            
            /* Light mode */
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
            --sidebar-bg: #ffffff;
            --toolbar-bg: #ffffff;
            
            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            /* Border radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            
            /* Transitions */
            --transition-fast: 150ms;
            --transition-normal: 250ms;
            --transition-slow: 350ms;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Dark mode */
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
            --sidebar-bg: #1e293b;
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
        .explorer-shell {
            display: flex;
            height: calc(100vh - 57px - 57px);
            overflow: hidden;
        }

        /* Right Content */
        .explorer-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Modern Toolbar */
        .explorer-toolbar {
            background: var(--toolbar-bg);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-sm) var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            flex-shrink: 0;
        }

        .breadcrumb-nav {
            flex: 1;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .breadcrumb-nav a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition-fast);
        }

        .breadcrumb-nav a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xs) var(--spacing-md);
            gap: var(--spacing-sm);
            width: 300px;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }

        .search-bar:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-bar input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.875rem;
            width: 100%;
            color: var(--text-primary);
        }

        .search-bar input::placeholder {
            color: var(--text-muted);
        }

        .search-bar i {
            color: var(--text-muted);
            font-size: 0.875rem;
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

        /* Explorer Body */
        .explorer-body {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-lg);
        }

        /* Section Title */
        .exp-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: var(--spacing-lg) 0 var(--spacing-md) 0;
            padding-bottom: var(--spacing-xs);
            border-bottom: 1px solid var(--border-color);
        }

        /* Quick Access Chips */
        .quick-access-grid {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }

        .qa-chip {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-sm) var(--spacing-lg);
            font-size: 0.875rem;
            color: var(--text-primary);
            cursor: pointer;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        .qa-chip:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .qa-chip i {
            color: var(--primary-color);
        }

        /* Folder Grid */
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }

        .folder-card {
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

        .folder-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .folder-card .fc-menu-btn {
            position: absolute;
            top: var(--spacing-xs);
            right: var(--spacing-xs);
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: var(--spacing-xs);
            border-radius: var(--radius-sm);
            cursor: pointer;
            opacity: 0;
            transition: all var(--transition-fast);
        }

        .folder-card:hover .fc-menu-btn {
            opacity: 1;
        }

        .folder-card .fc-menu-btn:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .fc-icon {
            font-size: 2.5rem;
            color: #fbbf24;
            margin-bottom: var(--spacing-sm);
        }

        .fc-icon.locked {
            color: var(--danger-color);
        }

        .fc-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
            line-height: 1.4;
            margin-bottom: var(--spacing-xs);
        }

        .fc-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Google Drive Style File List */
        .gd-file-list {
            width: 100%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .gd-header {
            display: grid;
            grid-template-columns: 40px 1fr 180px 180px 120px 120px;
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

        .gd-file-row {
            display: grid;
            grid-template-columns: 40px 1fr 180px 180px 120px 120px;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .gd-file-row:hover {
            background: var(--hover-bg);
        }

        .gd-file-row:hover .gd-col-actions {
            opacity: 1;
        }

        .gd-col-check {
            display: flex;
            align-items: center;
        }

        .gd-col-name {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            min-width: 0;
        }

        .gd-file-icon {
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .gd-file-name {
            font-size: 0.875rem;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 500;
        }

        .gd-col-owner {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .gd-owner-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .gd-col-date {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .gd-col-size {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .gd-col-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            opacity: 0;
            transition: opacity var(--transition-fast);
            position: relative;
        }

        .gd-action-btn {
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

        .gd-action-btn:hover {
            background: var(--hover-bg);
            color: var(--primary-color);
        }

        .gd-file-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            z-index: 1000;
            padding: var(--spacing-xs) 0;
        }

        .gd-file-menu.show {
            display: block;
        }

        .gd-menu-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-primary);
            transition: background var(--transition-fast);
        }

        .gd-menu-item:hover {
            background: var(--hover-bg);
        }

        .gd-menu-item i {
            width: 16px;
            text-align: center;
            color: var(--text-muted);
        }

        .gd-menu-danger {
            color: var(--danger-color) !important;
        }

        .gd-menu-danger i {
            color: var(--danger-color) !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl) var(--spacing-lg);
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            display: block;
        }

        /* Activity Panel */
        .activity-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: var(--card-bg);
            border-left: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            transition: right var(--transition-normal);
            z-index: 1050;
        }

        .activity-panel.active {
            right: 0;
        }

        .activity-panel .card {
            height: 100%;
            border: none;
            border-radius: 0;
            background: var(--card-bg);
        }

        .activity-panel .card-header {
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-md) var(--spacing-lg);
        }

        .activity-panel .card-title {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
        }

        /* Folder Actions Menu */
        .folder-actions-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            z-index: 1000;
            padding: var(--spacing-xs) 0;
        }

        .folder-actions-menu.show {
            display: block;
        }

        .folder-action-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-primary);
            transition: background var(--transition-fast);
        }

        .folder-action-item:hover {
            background: var(--hover-bg);
        }

        .folder-action-item i {
            width: 16px;
            text-align: center;
            color: var(--text-muted);
        }

        .folder-action-item.delete {
            color: var(--danger-color);
        }

        .folder-action-item.delete i {
            color: var(--danger-color);
        }

        /* File Drop Zone */
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

        .file-icon {
            font-size: 1rem;
            color: var(--primary-color);
        }

        .file-name {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .file-size {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-left: var(--spacing-xs);
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

        /* Upload Progress */
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

        /* File View Modal */
        .fv-modal .modal-dialog {
            max-width: 900px;
        }

        .fv-modal .modal-content {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            background: var(--card-bg);
        }

        .fv-modal .modal-header {
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-md) var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .fv-modal .modal-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .fv-header-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all var(--transition-fast);
        }

        .fv-header-btn:hover {
            background: var(--hover-bg);
            color: var(--primary-color);
        }

        .fv-modal .modal-body {
            padding: 0;
            min-height: 480px;
            display: flex;
        }

        .fv-preview-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-lg);
            background: var(--bg-secondary);
        }

        .fv-info-panel {
            width: 260px;
            background: var(--bg-primary);
            border-left: 1px solid var(--border-color);
            padding: var(--spacing-lg);
            overflow-y: auto;
        }

        .fv-info-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            font-size: 0.875rem;
        }

        .fv-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-sm);
            font-size: 0.8125rem;
        }

        .fv-info-label {
            color: var(--text-muted);
        }

        .fv-info-value {
            color: var(--text-primary);
            text-align: right;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .gd-header {
                grid-template-columns: 40px 1fr 150px 150px 100px 100px;
            }
            
            .gd-file-row {
                grid-template-columns: 40px 1fr 150px 150px 100px 100px;
            }
        }

        @media (max-width: 768px) {
            .explorer-toolbar {
                flex-wrap: wrap;
            }
            
            .search-bar {
                width: 100%;
                order: 1;
            }
            
            .folder-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .activity-panel {
                width: 100%;
                right: -100%;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_file.php'; ?>

    <div class="content-wrapper" style="padding:0; min-height:unset; overflow:hidden;">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['success'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= $_SESSION['error'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="explorer-shell">
            <!-- Right Content -->
            <div class="explorer-right">
                <!-- Toolbar -->
                <div class="explorer-toolbar">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb-nav" id="explorerBreadcrumb">
                        <a href="file_management.php"><i class="fas fa-layer-group mr-1"></i>File Management</a>
                        <span class="mx-1">/</span>
                        <span id="breadcrumbSection"><?= htmlspecialchars($section_name) ?></span>
                        <span id="breadcrumbFolder"></span>
                    </div>

                    <!-- Activity log -->
                    <button class="toolbar-btn" id="toggleActivityPanel">
                        <i class="fas fa-history"></i> Activity
                    </button>

                    <!-- Upload -->
                    <button class="toolbar-btn primary" data-toggle="modal" data-target="#uploadFileModal">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>

                    <!-- Search -->
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="explorerSearch" placeholder="Search files & folders…">
                    </div>
                </div>

                <!-- Body -->
                <div class="explorer-body" id="explorerBody">
                    <!-- Quick Access chips -->
                    <p class="exp-section-title">Quick Access</p>
                    <div class="quick-access-grid">
                        <div class="qa-chip" data-toggle="modal" data-target="#uploadFileModal">
                            <i class="fas fa-upload"></i> Upload File
                        </div>
                        <div class="qa-chip" data-toggle="modal" data-target="#createFolderModal">
                            <i class="fas fa-folder-plus"></i> New Folder
                        </div>
                        <div class="qa-chip">
                            <i class="fas fa-folder"></i>
                            <?= count($folders) ?> Folders
                        </div>
                        <div class="qa-chip">
                            <i class="fas fa-file"></i>
                            <?= count($files) ?> Files
                        </div>
                        <div class="qa-chip">
                            <i class="fas fa-lock"></i>
                            <?= count(array_filter($folders, fn($f) => $f['is_locked'])) ?> Protected
                        </div>
                    </div>

                    <!-- Folders grid -->
                    <?php if (!empty($folders)): ?>
                        <p class="exp-section-title">Folders</p>
                        <div class="folder-grid" id="rootFolderGrid">
                            <?php foreach ($folders as $folder):
                                $has_access = hasFolderPermission($db, $folder['folder_id'], $user_emp_id, 'view');
                                $can_edit   = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'edit_folder');
                                $can_share  = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'share_folder');
                                $can_delete = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'delete_folder');
                            ?>
                            <div class="folder-card" id="folderCard_<?= $folder['folder_id'] ?>"
                                 ondblclick="<?= $has_access ? "openFolder({$folder['folder_id']}, '" . htmlspecialchars(addslashes($folder['folder_name'])) . "', {$folder['is_locked']})" : "showNoAccess()" ?>">
                                <button class="fc-menu-btn" onclick="toggleFolderMenu(this, event)"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="folder-actions-menu">
                                    <?php if ($can_edit): ?>
                                    <button class="folder-action-item" onclick="editFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', '<?= htmlspecialchars(addslashes($folder['description'] ?? '')) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_share): ?>
                                    <button class="folder-action-item" onclick="shareFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                        <i class="fas fa-share-alt"></i> Share
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (canPerformAction($db, $folder['folder_id'], $user_emp_id, 'manage_shares')): ?>
                                    <button class="folder-action-item" onclick="manageShares(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                        <i class="fas fa-users"></i> Manage Access
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete): ?>
                                    <button class="folder-action-item delete" onclick="deleteFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', <?= $folder['is_locked'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <i class="nav icon fas fa-folder fc-icon <?= $folder['is_locked'] ? 'locked' : '' ?>"></i>
                                <div class="fc-name"><?= htmlspecialchars($folder['folder_name']) ?></div>
                                <div class="fc-meta"><?= $folder['file_count'] ?> files<?= $folder['subfolder_count'] > 0 ? ', ' . $folder['subfolder_count'] . ' sub' : '' ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Files section -->
                    <p class="exp-section-title">Files</p>
                    <?php if (empty($files)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p class="mb-1" style="font-size: 1rem; font-weight: 500;">No files yet</p>
                            <p style="font-size: 0.875rem;">Upload a file to get started</p>
                        </div>
                    <?php else: ?>
                        <!-- Google Drive style file list -->
                        <div class="gd-file-list">
                            <!-- Header row -->
                            <div class="gd-header">
                                <div class="gd-col-check"><input type="checkbox" id="selectAllFiles"></div>
                                <div class="gd-col-name">Name</div>
                                <div class="gd-col-owner">Owner</div>
                                <div class="gd-col-date">Last modified</div>
                                <div class="gd-col-size">File size</div>
                                <div class="gd-col-actions"></div>
                            </div>
                            <!-- File rows -->
                            <?php foreach ($files as $file): ?>
                            <div class="gd-file-row" ondblclick="viewFileModal(<?= $file['file_id'] ?>)">
                                <div class="gd-col-check">
                                    <input type="checkbox" class="file-checkbox" value="<?= $file['file_id'] ?>">
                                </div>
                                <div class="gd-col-name">
                                    <i class="fas fa-file-<?= getFileIcon($file['file_type']) ?> gd-file-icon"></i>
                                    <span class="gd-file-name"><?= htmlspecialchars($file['file_name']) ?></span>
                                </div>
                                <div class="gd-col-owner">
                                    <span class="gd-owner-avatar"><?= strtoupper(substr($file['uploaded_by'] ?? 'U', 0, 1)) ?></span>
                                    <span><?= htmlspecialchars($file['uploaded_by'] ?? 'me') ?></span>
                                </div>
                                <div class="gd-col-date"><?= date('M j, Y', strtotime($file['created_at'])) ?></div>
                                <div class="gd-col-size"><?= formatFileSize($file['file_size']) ?></div>
                                <div class="gd-col-actions">
                                    <button class="gd-action-btn" title="Share" onclick="event.stopPropagation()"><i class="fas fa-user-plus"></i></button>
                                    <a class="gd-action-btn" href="download_file.php?id=<?= $file['file_id'] ?>" title="Download" onclick="event.stopPropagation()"><i class="fas fa-download"></i></a>
                                    <button class="gd-action-btn" title="Edit" onclick="event.stopPropagation(); openFileEdit(<?= $file['file_id'] ?>, '<?= htmlspecialchars(addslashes($file['file_name'])) ?>', '<?= htmlspecialchars(addslashes($file['description'] ?? '')) ?>')"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="gd-action-btn gd-action-more" title="More actions"
                                            onclick="event.stopPropagation(); toggleFileMenu(this, event, <?= $file['file_id'] ?>, '<?= htmlspecialchars(addslashes($file['file_name'])) ?>')">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="gd-file-menu">
                                        <button class="gd-menu-item" onclick="viewFileModal(<?= $file['file_id'] ?>)"><i class="fas fa-eye"></i> Preview</button>
                                        <button class="gd-menu-item" onclick="openFileEdit(<?= $file['file_id'] ?>, '<?= htmlspecialchars(addslashes($file['file_name'])) ?>', '<?= htmlspecialchars(addslashes($file['description'] ?? '')) ?>')"><i class="fas fa-pencil-alt"></i> Rename</button>
                                        <a class="gd-menu-item" href="download_file.php?id=<?= $file['file_id'] ?>"><i class="fas fa-download"></i> Download</a>
                                        <button class="gd-menu-item"><i class="fas fa-share-alt"></i> Share</button>
                                        <hr style="margin:4px 0; border-color: var(--border-color);">
                                        <button class="gd-menu-item gd-menu-danger" onclick="deleteFile(<?= $file['file_id'] ?>, '<?= htmlspecialchars(addslashes($file['file_name'])) ?>')"><i class="fas fa-trash"></i> Move to Trash</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div><!-- /explorer-body -->
            </div><!-- /explorer-right -->
        </div><!-- /explorer-shell -->
    </div><!-- /content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /wrapper -->

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
                <h5 class="modal-title"><i class="fas fa-folder-plus mr-2"></i>Create New Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="folderForm" method="POST">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="folderName">Folder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folderName" name="folder_name" 
                               placeholder="Enter folder name..." required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="password">Password Protection <small class="text-muted">(optional)</small></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Leave blank for no password" autocomplete="new-password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleCreatePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">If set, users must enter the password to open this folder.</small>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cloud-upload-alt mr-2"></i>Upload Files</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data" method="POST" action="section_files.php?section_id=<?= $section_id ?>">
                <div class="modal-body">
                    <!-- Upload destination info -->
                    <div class="alert alert-info d-flex align-items-center mb-3">
                        <i class="fas fa-info-circle mr-2 fa-lg"></i>
                        <div>
                            <strong>Upload Destination:</strong> Files will be saved to the 
                            <strong><?= htmlspecialchars($section_name) ?></strong> section root.
                        </div>
                    </div>

                    <!-- File Drop Zone -->
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3" style="display:block;"></i>
                            <h5>Drag &amp; Drop files here</h5>
                            <p class="text-muted mb-2">or click to browse &mdash; max 500MB per file, up to 10 files</p>
                            <input type="file" id="fileInput" name="files[]" multiple accept="*/*" style="display: none;">
                            <button type="button" class="btn btn-primary mt-2" id="browseFilesBtn">
                                <i class="fas fa-folder-open mr-2"></i>Browse Files
                            </button>
                        </div>
                    </div>
                    
                    <!-- Selected Files List -->
                    <div class="selected-files-container mt-3" id="selectedFilesContainer" style="display: none;">
                        <h6><i class="fas fa-list mr-1"></i>Selected Files (<span id="fileCount">0</span>/10)</h6>
                        <div class="selected-files-list" id="selectedFilesList"></div>
                    </div>
                    
                    <!-- File Description -->
                    <div class="form-group mt-3">
                        <label for="fileDescription"><i class="fas fa-comment mr-1"></i>Description <small class="text-muted">(applies to all files)</small></label>
                        <textarea class="form-control" id="fileDescription" name="description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                    
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="action" value="upload_files">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadFilesBtn" disabled>
                        <i class="fas fa-upload mr-2"></i>Upload Files
                    </button>
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

<!-- Share Folder Modal -->
<div class="modal fade" id="shareFolderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Folder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="shareFolderForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="shareEmployees">Select Employees to Share With *</label>
                        <select multiple class="form-control select2" id="shareEmployees" name="employee_ids[]" required style="width: 100%;">
                            <!-- Employees will be loaded via AJAX -->
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
                    <input type="hidden" name="folder_id" id="shareFolderId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="shareFolderBtn">Share Folder</button>
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

<!-- File View Modal -->
<div class="modal fade fv-modal" id="fileViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <i id="fvFileIcon" class="fas fa-file mr-2" style="font-size:20px; flex-shrink:0;"></i>
                <h5 class="modal-title" id="fvFileName">Loading…</h5>
                <div class="fv-header-actions">
                    <a id="fvDownloadBtn" href="#" class="fv-header-btn" title="Download"><i class="fas fa-download"></i></a>
                    <button class="fv-header-btn" title="Edit" onclick="openCurrentFileEdit()"><i class="fas fa-pencil-alt"></i></button>
                    <button class="fv-header-btn" title="Share"><i class="fas fa-share-alt"></i></button>
                    <button class="fv-header-btn" title="Star"><i class="far fa-star"></i></button>
                    <button type="button" class="fv-header-btn" data-dismiss="modal" title="Close"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Preview -->
                <div class="fv-preview-area" id="fvPreviewArea">
                    <div class="fv-no-preview" id="fvLoadingSpinner">
                        <i class="fas fa-spinner fa-spin" style="font-size:40px; color:var(--primary-color);"></i>
                    </div>
                </div>
                <!-- Info panel -->
                <div class="fv-info-panel">
                    <div class="fv-info-title">File Details</div>
                    <div class="fv-info-row"><span class="fv-info-label">Type</span><span class="fv-info-value" id="fvFileType">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Size</span><span class="fv-info-value" id="fvFileSize">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Owner</span><span class="fv-info-value" id="fvOwner">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Modified</span><span class="fv-info-value" id="fvDate">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Location</span><span class="fv-info-value" id="fvLocation">—</span></div>
                    <hr style="margin:10px 0;">
                    <div class="fv-info-title">Description</div>
                    <div id="fvDescription" style="color:var(--text-muted); font-size:12px;">—</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File Edit Modal -->
<div class="modal fade" id="fileEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-color);">
                <h5 class="modal-title text-white"><i class="fas fa-pencil-alt mr-2"></i>Edit File</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>File Name</label>
                    <input type="text" class="form-control" id="editFileNameInput">
                </div>
                <input type="hidden" id="editFileIdInput">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" style="background: var(--primary-color); border-color: var(--primary-color);" onclick="saveFileEdit()">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
let folderToDelete = null;
let selectedFiles = [];

$(document).ready(function() {
    let currentFolderId = null;
    
    // Toggle password visibility
    $('#toggleCreatePassword').click(function() {
        const field = $('#password');
        field.attr('type', field.attr('type') === 'password' ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#toggleEditPassword').click(function() {
        const passwordField = $('#editPassword');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });
    
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
    });
    
    $('#closeActivityPanel').click(function() {
        $('#activityPanel').removeClass('active');
    });
    
    $(document).click(function(e) {
        if ($('#activityPanel').hasClass('active') && 
            !$(e.target).closest('#activityPanel').length && 
            !$(e.target).is('#toggleActivityPanel')) {
            $('#activityPanel').removeClass('active');
        }
    });
    
    // Explorer search
    $('#explorerSearch').on('input', function() {
        const q = this.value.toLowerCase();
        $('.folder-card').each(function() {
            const name = $(this).find('.fc-name').text().toLowerCase();
            $(this).toggle(name.includes(q));
        });
        $('.gd-file-row').each(function() {
            const name = $(this).find('.gd-file-name').text().toLowerCase();
            $(this).toggle(name.includes(q));
        });
    });

    // Folder permission check
    $(document).on('click', '.folder-card', function(e) {
        if ($(e.target).closest('.fc-menu-btn, .folder-actions-menu, .folder-action-item').length > 0) {
            return;
        }
        
        const folderId = $(this).attr('id').replace('folderCard_', '');
        const isLocked = $(this).find('.fc-icon').hasClass('locked');
        
        $.ajax({
            url: 'section_files.php?section_id=<?= $section_id ?>',
            type: 'POST',
            data: {
                action: 'check_folder_permission',
                folder_id: folderId
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.has_permission) {
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
                    console.error('Permission check error:', e);
                }
            }
        });
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
                        window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
                    } else {
                        Swal.fire('Error!', result.message || 'Invalid password', 'error');
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                }
            }
        });
    });

    // Edit folder form submission
    $('#editFolderForm').submit(function(e) {
        e.preventDefault();
        
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
                            text: result.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#editFolderModal').modal('hide');
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error!', result.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error!', 'Invalid server response', 'error');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).html(originalText);
                Swal.fire('Error!', 'Request failed', 'error');
            }
        });
    });

    // Delete folder confirmation
    $('#confirmDeleteFolder').click(function() {
        if (!folderToDelete) {
            Swal.fire('Error!', 'Folder information not found.', 'error');
            return;
        }
        
        const folderId = folderToDelete.id;
        const folderName = folderToDelete.name;
        const isLocked = folderToDelete.locked;
        const password = isLocked ? $('#deletePassword').val() : '';
        
        if (isLocked && !password) {
            Swal.fire('Error!', 'Please enter the folder password', 'error');
            return;
        }
        
        const deleteBtn = $(this);
        const originalText = deleteBtn.html();
        deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        
        $.ajax({
            url: 'section_files.php?section_id=<?= $section_id ?>',
            type: 'POST',
            data: {
                action: 'delete_folder',
                folder_id: folderId,
                password: password
            },
            success: function(response) {
                deleteBtn.prop('disabled', false).html(originalText);
                
                try {
                    const result = JSON.parse(response);
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
                        if (result.password_required) {
                            $('#passwordVerification').show();
                            $('#deleteFolderMessage').html(`Are you sure you want to delete the folder "<strong>${folderName}</strong>"? This action cannot be undone.`);
                        }
                        Swal.fire('Error!', result.message || 'Delete failed', 'error');
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    Swal.fire('Error!', 'Invalid server response', 'error');
                }
            },
            error: function() {
                deleteBtn.prop('disabled', false).html(originalText);
                Swal.fire('Error!', 'Failed to delete folder', 'error');
            }
        });
    });

    // Initialize file upload
    initFileUpload();
});

// Global functions
function openFolder(folderId, folderName, isLocked) {
    if (isLocked) {
        $('#unlockFolderId').val(folderId);
        $('#unlockFolderModal').modal('show');
        return;
    }
    window.location.href = 'folder_contents.php?folder_id=' + folderId + '&section_id=<?= $section_id ?>';
}

function showNoAccess() {
    Swal.fire({ title: 'Access Denied', text: 'You do not have permission to access this folder.', icon: 'warning' });
}

function showRootView() {
    document.getElementById('rootView').style.display = '';
    document.getElementById('folderView').style.display = 'none';
    document.getElementById('breadcrumbFolder').textContent = '';
    $('.sb-nav-link').removeClass('sb-active');
    $('#sbNavRootItem').addClass('sb-active');
}

// Toggle folder menu
function toggleFolderMenu(button, event) {
    event.stopPropagation();
    event.preventDefault();
    
    const menu = button.nextElementSibling;
    const isShowing = menu.classList.contains('show');
    
    $('.folder-actions-menu').removeClass('show');
    
    if (!isShowing) {
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

// Toggle file menu
function toggleFileMenu(btn, event, fileId, fileName) {
    event.stopPropagation();
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('show');
    $('.gd-file-menu').removeClass('show');
    if (!isOpen) {
        menu.classList.add('show');
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
}

// Edit folder
function editFolder(folderId, folderName, description) {
    $('#editFolderId').val(folderId);
    $('#editFolderName').val(folderName);
    $('#editDescription').val(description);
    $('#editPassword').val('');
    $('#removePasswordCheck').prop('checked', false);
    $('#passwordFields').show();
    $('#removePasswordField').remove();
    $('#editFolderModal').modal('show');
    $('.folder-actions-menu').removeClass('show');
}

// Delete folder
function deleteFolder(folderId, folderName, isLocked) {
    $('.folder-actions-menu').removeClass('show');
    
    folderToDelete = {
        id: folderId,
        name: folderName,
        locked: Boolean(isLocked)
    };
    
    $('#deletePassword').val('');
    $('#passwordVerification').toggle(isLocked == 1 || isLocked === true);
    $('#deleteFolderMessage').html(`Are you sure you want to delete the folder "<strong>${folderName}</strong>"? This action cannot be undone.`);
    
    $('#deleteFolderModal').modal('show');
}

// Share folder
function shareFolder(folderId, folderName) {
    $('#shareFolderId').val(folderId);
    $('#shareFolderModal .modal-title').html('Share Folder: ' + folderName);
    $('#shareEmployees').empty();
    
    $.ajax({
        url: 'section_files.php?section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'get_employees'
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    result.employees.forEach(function(employee) {
                        $('#shareEmployees').append(
                            $('<option>', {
                                value: employee.emp_id,
                                text: employee.full_name + ' (' + employee.department + ')'
                            })
                        );
                    });
                    
                    $('#shareEmployees').select2({
                        placeholder: "Select employees...",
                        allowClear: true
                    });
                    
                    $('#shareFolderModal').modal('show');
                }
            } catch (e) {
                console.error('Error loading employees:', e);
            }
        }
    });
    
    $('.folder-actions-menu').removeClass('show');
}

// Manage shares
function manageShares(folderId, folderName) {
    $.ajax({
        url: 'section_files.php?section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'get_shares',
            folder_id: folderId
        },
        success: function(response) {
            $('#manageSharesContent').html(response);
            $('#manageSharesModal').modal('show');
        },
        error: function() {
            Swal.fire('Error!', 'Failed to load shares', 'error');
        }
    });
    
    $('.folder-actions-menu').removeClass('show');
}

// File view modal
function viewFileModal(fileId) {
    $('#fvPreviewArea').html('<div class="fv-no-preview"><i class="fas fa-spinner fa-spin" style="font-size:40px; color:var(--primary-color);"></i></div>');
    $('#fvFileName').text('Loading…');
    $('#fileViewModal').modal('show');

    $.ajax({
        url: 'view_file.php',
        type: 'GET',
        data: { id: fileId, ajax: 'true' },
        success: function(resp) {
            try {
                const d = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (!d.success) {
                    $('#fvPreviewArea').html('<div class="fv-no-preview"><i class="fas fa-exclamation-circle text-danger fa-3x"></i><p class="mt-2">Could not load file.</p></div>');
                    return;
                }
                const f = d.file;

                // Header
                const iconMap = {
                    pdf: 'fas fa-file-pdf text-danger',
                    doc: 'fas fa-file-word text-primary',
                    docx: 'fas fa-file-word text-primary',
                    xls: 'fas fa-file-excel text-success',
                    xlsx: 'fas fa-file-excel text-success',
                    ppt: 'fas fa-file-powerpoint text-warning',
                    pptx: 'fas fa-file-powerpoint text-warning',
                    jpg: 'fas fa-file-image text-info',
                    jpeg: 'fas fa-file-image text-info',
                    png: 'fas fa-file-image text-info',
                    gif: 'fas fa-file-image text-info',
                    mp4: 'fas fa-file-video text-danger',
                    avi: 'fas fa-file-video text-danger',
                    txt: 'fas fa-file-alt text-dark',
                    zip: 'fas fa-file-archive text-secondary',
                    rar: 'fas fa-file-archive text-secondary',
                    mp3: 'fas fa-file-audio text-info'
                };
                const iconClass = iconMap[f.file_type.toLowerCase()] || 'fas fa-file text-secondary';
                $('#fvFileIcon').attr('class', iconClass + ' mr-2').css('font-size', '20px');
                $('#fvFileName').text(f.file_name);
                $('#fvDownloadBtn').attr('href', 'download_file.php?id=' + f.file_id);

                // Info panel
                $('#fvFileType').text(f.file_type.toUpperCase());
                $('#fvFileSize').text(formatBytes(f.file_size));
                $('#fvOwner').text(f.uploaded_by || 'me');
                $('#fvDate').text(f.created_at ? new Date(f.created_at).toLocaleDateString() : '—');
                $('#fvLocation').text(f.folder_name || 'Root');
                $('#fvDescription').text(f.description || '—');

                // Preview
                const type = f.file_type.toLowerCase();
                let preview = '';
                if (['jpg','jpeg','png','gif'].includes(type)) {
                    preview = '<img src="../uploads/' + (f.file_path||'') + '" style="max-width:100%;max-height:440px;border-radius:var(--radius-md);box-shadow:var(--shadow-md);" onerror="this.parentNode.innerHTML=\'<div class=fv-no-preview><div class=fv-file-type-badge>'+f.file_type.toUpperCase()+'</div><p>Preview unavailable</p></div>\'">';
                } else if (type === 'pdf') {
                    preview = '<iframe src="../uploads/' + (f.file_path||'') + '" style="width:100%;height:460px;border:none;border-radius:var(--radius-md);"></iframe>';
                } else {
                    preview = '<div class="fv-no-preview"><div class="fv-file-type-badge">'+f.file_type.toUpperCase()+'</div><p style="margin-top:12px;font-size:15px;font-weight:600;">'+f.file_name+'</p><p style="font-size:13px;margin-top:4px;">No preview available</p><a href="download_file.php?id='+f.file_id+'" class="btn btn-primary mt-3"><i class="fas fa-download mr-2"></i>Download</a></div>';
                }
                $('#fvPreviewArea').html(preview);

                window._fvCurrentFile = f;
            } catch(e) {
                $('#fvPreviewArea').html('<div class="fv-no-preview"><i class="fas fa-exclamation-circle text-danger fa-3x"></i><p class="mt-2">Error loading file.</p></div>');
            }
        },
        error: function() {
            $('#fvPreviewArea').html('<div class="fv-no-preview"><i class="fas fa-exclamation-circle text-danger fa-3x"></i><p class="mt-2">Failed to load file.</p></div>');
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

function deleteFile(fileId, fileName) {
    Swal.fire({
        title: 'Move to Trash?',
        html: 'Move <strong>' + fileName + '</strong> to trash?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Move to Trash'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('section_files.php?section_id=<?= $section_id ?>', { action:'delete_file', file_id:fileId }, function(resp) {
                try {
                    const r = typeof resp === 'string' ? JSON.parse(resp) : resp;
                    if (r.success) {
                        Swal.fire({
                            title: 'Moved!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', r.message || 'Delete failed', 'error');
                    }
                } catch(e) {
                    location.reload();
                }
            });
        }
    });
}

// Share folder form submission
$('#shareFolderForm').submit(function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    const shareBtn = $('#shareFolderBtn');
    const originalText = shareBtn.html();
    
    shareBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sharing...');
    
    $.ajax({
        url: 'section_files.php?section_id=<?= $section_id ?>',
        type: 'POST',
        data: formData,
        success: function(response) {
            shareBtn.prop('disabled', false).html(originalText);
            
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: result.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#shareFolderModal').modal('hide');
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Invalid server response', 'error');
            }
        },
        error: function() {
            shareBtn.prop('disabled', false).html(originalText);
            Swal.fire('Error!', 'Failed to share folder', 'error');
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
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, revoke access!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'section_files.php?section_id=<?= $section_id ?>',
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
                                const folderId = $('#shareFolderId').val();
                                if (folderId) {
                                    manageShares(folderId, '');
                                }
                            });
                        } else {
                            Swal.fire('Error!', result.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error!', 'Invalid server response', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error!', 'Failed to revoke access', 'error');
                }
            });
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
        url: 'section_files.php?section_id=<?= $section_id ?>',
        type: 'POST',
        data: {
            action: 'update_share',
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
        error: function() {
            updateBtn.prop('disabled', false).html(originalText);
            Swal.fire('Error!', 'Failed to update permissions', 'error');
        }
    });
});

// File Upload Functionality
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
        if (e.target === this || $(e.target).hasClass('file-drop-zone')) {
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
    updateUploadButton();
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
        const fileItem = createFileItem(file, index);
        list.append(fileItem);
    });
    
    count.text(selectedFiles.length);
    container.show();
    
    const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
    $('#totalSize').remove();
    container.append(`<div id="totalSize" class="small text-muted mt-2">Total size: ${formatFileSize(totalSize)}</div>`);
}

function createFileItem(file, index) {
    const fileSize = formatFileSize(file.size);
    const fileExtension = file.name.split('.').pop().toLowerCase();
    const fileIcon = getFileIcon(fileExtension);
    
    return `
        <div class="file-item" data-index="${index}">
            <div class="file-info">
                <i class="fas fa-file-${fileIcon} file-icon"></i>
                <div>
                    <div class="file-name">${escapeHtml(file.name)}</div>
                    <div class="file-size">${fileSize}</div>
                </div>
            </div>
            <button type="button" class="file-remove" onclick="removeFile(${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
    updateUploadButton();
}

function updateUploadButton() {
    $('#uploadFilesBtn').prop('disabled', selectedFiles.length === 0);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileIcon(extension) {
    const icons = {
        'pdf': 'pdf',
        'doc': 'word',
        'docx': 'word',
        'xls': 'excel',
        'xlsx': 'excel',
        'ppt': 'powerpoint',
        'pptx': 'powerpoint',
        'jpg': 'image',
        'jpeg': 'image',
        'png': 'image',
        'gif': 'image',
        'zip': 'archive',
        'rar': 'archive',
        'txt': 'alt',
        'mp4': 'video',
        'avi': 'video',
        'mov': 'video',
        'mp3': 'audio',
        'wav': 'audio'
    };
    return icons[extension] || 'file';
}

// Upload form submission
$('#uploadForm').off('submit').on('submit', function(e) {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        Swal.fire('Error!', 'Please select at least one file to upload.', 'error');
        return false;
    }
    
    const uploadBtn = $('#uploadFilesBtn');
    if (uploadBtn.prop('disabled')) {
        return false;
    }
    
    uploadFiles();
    return false;
});

function uploadFiles() {
    createProgressUI();
    
    const formData = new FormData();
    
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    
    formData.append('description', $('#fileDescription').val());
    formData.append('section_id', '<?= $section_id ?>');
    formData.append('folder_id', '');
    formData.append('action', 'upload_files');
    
    const uploadBtn = $('#uploadFilesBtn');
    const originalText = uploadBtn.html();
    
    uploadBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
    
    $.ajax({
        url: 'section_files.php?section_id=<?= $section_id ?>',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    updateOverallProgress(percentComplete);
                }
            }, false);
            return xhr;
        },
        success: function(response) {
            uploadBtn.prop('disabled', false).html(originalText);
            
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    updateOverallProgress(100);
                    
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Success!',
                            html: `<p>Successfully uploaded ${result.uploaded_count} file(s)</p>`,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#uploadFileModal').modal('hide');
                            resetUploadForm();
                            location.reload();
                        });
                    }, 500);
                } else {
                    Swal.fire({
                        title: 'Upload Complete',
                        html: `
                            <p>Uploaded: ${result.uploaded_count || 0} files</p>
                            <p>Failed: ${result.failed_count || 0} files</p>
                        `,
                        icon: result.uploaded_count > 0 ? 'warning' : 'error',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (e) {
                console.error('Parse error:', e);
                Swal.fire('Error!', 'Invalid server response', 'error');
            }
            
            removeProgressUI();
        },
        error: function(xhr, status, error) {
            uploadBtn.prop('disabled', false).html(originalText);
            Swal.fire('Error!', 'Failed to upload files: ' + error, 'error');
            removeProgressUI();
        }
    });
}

function createProgressUI() {
    removeProgressUI();
    
    const progressHTML = `
        <div class="upload-progress mt-3" id="uploadProgress">
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: 0%" 
                     aria-valuenow="0" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            <div class="progress-info text-center">
                <small class="text-muted" id="progressStatus">Preparing upload...</small>
            </div>
        </div>
    `;
    
    $('#selectedFilesContainer').after(progressHTML);
}

function updateOverallProgress(percent) {
    const progressBar = $('#uploadProgress .progress-bar');
    const progressStatus = $('#progressStatus');
    
    progressBar.css('width', percent + '%');
    progressBar.attr('aria-valuenow', percent);
    
    if (percent < 100) {
        progressStatus.text(`Uploading... ${Math.round(percent)}%`);
    } else {
        progressStatus.text('Processing...');
    }
}

function removeProgressUI() {
    $('#uploadProgress').remove();
}

function resetUploadForm() {
    selectedFiles = [];
    $('#fileInput').val('');
    $('#fileDescription').val('');
    $('#selectedFilesContainer').hide();
    $('#selectedFilesList').empty();
    $('#fileCount').text('0');
    $('#uploadFilesBtn').prop('disabled', true);
    removeProgressUI();
}

// Initialize file upload when modal is shown
$('#uploadFileModal').on('show.bs.modal', function() {
    resetUploadForm();
    setTimeout(function() {
        initFileUpload();
    }, 50);
});

$('#uploadFileModal').on('hide.bs.modal', function() {
    removeProgressUI();
    resetUploadForm();
});

// Select all files
$('#selectAllFiles').change(function() {
    $('.file-checkbox').prop('checked', this.checked);
});
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