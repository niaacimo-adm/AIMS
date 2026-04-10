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
        $_SESSION['swal_error'] = [
            'title' => 'Access Denied',
            'text'  => 'You do not have permission to access this section.',
            'icon'  => 'error'
        ];
        header("Location: file_management.php");
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

                    // ── Sidebar uploaded files list ───────────────────────
                    case 'get_sidebar_files':
                        $sq   = isset($_POST['query']) ? trim($_POST['query']) : '';
                        $like = '%' . $db->real_escape_string($sq) . '%';
                        if ($section_id === 'manager') {
                            $sf_st = $db->prepare(
                                "SELECT file_id, file_name, file_type FROM files
                                 WHERE (section_id IS NULL OR section_id=0)
                                   AND (is_deleted IS NULL OR is_deleted=0)
                                   AND file_name LIKE ?
                                 ORDER BY created_at DESC LIMIT 60");
                            $sf_st->bind_param('s', $like);
                        } else {
                            $sf_st = $db->prepare(
                                "SELECT file_id, file_name, file_type FROM files
                                 WHERE section_id=?
                                   AND (is_deleted IS NULL OR is_deleted=0)
                                   AND file_name LIKE ?
                                 ORDER BY created_at DESC LIMIT 60");
                            $sf_st->bind_param('is', $section_id, $like);
                        }
                        $sf_st->execute();
                        $sf_res = $sf_st->get_result();
                        $sf_arr = [];
                        while ($row = $sf_res->fetch_assoc()) $sf_arr[] = $row;
                        echo json_encode(['success' => true, 'files' => $sf_arr]);
                    exit();

                    // ── Soft-delete (move to trash) ───────────────────────
                    case 'delete_file':
                        $del_id = intval($_POST['file_id'] ?? 0);
                        $chk = $db->query("SHOW COLUMNS FROM files LIKE 'is_deleted'");
                        if ($chk && $chk->num_rows > 0) {
                            $d = $db->prepare("UPDATE files SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE file_id=?");
                            $d->bind_param('ii', $user_emp_id, $del_id);
                            $d->execute();
                        } else {
                            $d = $db->prepare("DELETE FROM files WHERE file_id=?");
                            $d->bind_param('i', $del_id);
                            $d->execute();
                        }
                        echo json_encode(['success' => true, 'message' => 'Moved to trash.']);
                    exit();

                    // ── Restore from trash ────────────────────────────────
                    case 'restore_file':
                        $rst_id = intval($_POST['file_id'] ?? 0);
                        $rst = $db->prepare("UPDATE files SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE file_id=?");
                        if ($rst) {
                            $rst->bind_param('i', $rst_id);
                            $rst->execute();
                            echo json_encode(['success' => true, 'message' => 'File restored.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Restore not supported.']);
                        }
                    exit();

                    // ── Permanent delete ──────────────────────────────────
                    case 'permanent_delete_file':
                        $pd_id  = intval($_POST['file_id'] ?? 0);
                        $pd_sel = $db->prepare("SELECT file_path FROM files WHERE file_id=?");
                        $pd_sel->bind_param('i', $pd_id);
                        $pd_sel->execute();
                        $pd_res = $pd_sel->get_result();
                        if ($pd_res->num_rows > 0) {
                            $pd_row = $pd_res->fetch_assoc();
                            $pd_del = $db->prepare("DELETE FROM files WHERE file_id=?");
                            $pd_del->bind_param('i', $pd_id);
                            $pd_del->execute();
                            $fp = '../uploads/' . $pd_row['file_path'];
                            if (file_exists($fp)) @unlink($fp);
                            echo json_encode(['success' => true, 'message' => 'File permanently deleted.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'File not found.']);
                        }
                    exit();

                    // ── Toggle star ───────────────────────────────────────
                    case 'toggle_star':
                        $ts_id   = intval($_POST['file_id'] ?? 0);
                        $ts_star = intval($_POST['starred'] ?? 1);
                        $ts_chk  = $db->query("SHOW COLUMNS FROM files LIKE 'is_starred'");
                        if ($ts_chk && $ts_chk->num_rows > 0) {
                            $ts = $db->prepare("UPDATE files SET is_starred=? WHERE file_id=?");
                            $ts->bind_param('ii', $ts_star, $ts_id);
                            $ts->execute();
                            echo json_encode(['success' => true]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Star column not found.']);
                        }
                    exit();

                    // ── Get starred files ─────────────────────────────────
                    case 'get_starred_files':
                        $star_chk = $db->query("SHOW COLUMNS FROM files LIKE 'is_starred'");
                        if ($star_chk && $star_chk->num_rows > 0) {
                            $star_q = "SELECT f.*, CONCAT(e.first_name,' ',e.last_name) AS uploaded_by_name
                                       FROM files f
                                       LEFT JOIN employee e ON f.uploaded_by = e.emp_id
                                       WHERE f.is_starred = 1
                                         AND (f.is_deleted IS NULL OR f.is_deleted=0)
                                         AND (f.uploaded_by=? OR EXISTS(
                                               SELECT 1 FROM folder_shares fs
                                               WHERE fs.folder_id=f.folder_id
                                                 AND fs.shared_with_emp_id=?
                                                 AND fs.is_active=1))
                                       ORDER BY f.file_name";
                            $star_st = $db->prepare($star_q);
                            $star_st->bind_param('ii', $user_emp_id, $user_emp_id);
                            $star_st->execute();
                            $star_res = $star_st->get_result();
                            $star_arr = [];
                            while ($row = $star_res->fetch_assoc()) $star_arr[] = $row;
                            echo json_encode(['success' => true, 'files' => $star_arr]);
                        } else {
                            echo json_encode(['success' => true, 'files' => [],
                                             'message' => 'Star feature not available. Add is_starred column to files table.']);
                        }
                    exit();

                    // ── Get shared items (folders + files) ────────────────
                    case 'get_shared_items':
                        $sh_fq = "SELECT fo.*,
                                         CONCAT(e.first_name,' ',e.last_name)  AS creator_name,
                                         CONCAT(se.first_name,' ',se.last_name) AS shared_by_name,
                                         fs.permission_level,
                                         (SELECT COUNT(*) FROM files ff
                                          WHERE ff.folder_id=fo.folder_id
                                            AND (ff.is_deleted IS NULL OR ff.is_deleted=0)) AS file_count
                                  FROM folder_shares fs
                                  JOIN folders fo  ON fs.folder_id=fo.folder_id
                                  LEFT JOIN employee e  ON fo.created_by=e.emp_id
                                  LEFT JOIN employee se ON fs.shared_by_emp_id=se.emp_id
                                  WHERE fs.shared_with_emp_id=?
                                    AND fs.is_active=1
                                    AND (fs.expires_at IS NULL OR fs.expires_at > NOW())
                                  ORDER BY fo.folder_name";
                        $sh_fst = $db->prepare($sh_fq);
                        $sh_fst->bind_param('i', $user_emp_id);
                        $sh_fst->execute();
                        $sh_folders = [];
                        $sh_fr = $sh_fst->get_result();
                        while ($row = $sh_fr->fetch_assoc()) $sh_folders[] = $row;

                        $sh_fls = "SELECT f.*, fo.folder_name,
                                          CONCAT(e.first_name,' ',e.last_name)  AS uploaded_by_name,
                                          CONCAT(se.first_name,' ',se.last_name) AS shared_by_name
                                   FROM folder_shares fs
                                   JOIN folders fo ON fs.folder_id=fo.folder_id
                                   JOIN files   f  ON f.folder_id=fo.folder_id
                                   LEFT JOIN employee e  ON f.uploaded_by=e.emp_id
                                   LEFT JOIN employee se ON fs.shared_by_emp_id=se.emp_id
                                   WHERE fs.shared_with_emp_id=?
                                     AND fs.is_active=1
                                     AND (fs.expires_at IS NULL OR fs.expires_at > NOW())
                                     AND (f.is_deleted IS NULL OR f.is_deleted=0)
                                   ORDER BY f.file_name";
                        $sh_fls_st = $db->prepare($sh_fls);
                        $sh_fls_st->bind_param('i', $user_emp_id);
                        $sh_fls_st->execute();
                        $sh_files = [];
                        $sh_flr = $sh_fls_st->get_result();
                        while ($row = $sh_flr->fetch_assoc()) $sh_files[] = $row;

                        echo json_encode(['success' => true, 'folders' => $sh_folders, 'files' => $sh_files]);
                    exit();

                    // ── Get trash ─────────────────────────────────────────
                    case 'get_trash_files':
                        $tr_chk = $db->query("SHOW COLUMNS FROM files LIKE 'is_deleted'");
                        if ($tr_chk && $tr_chk->num_rows > 0) {
                            $tr_q = "SELECT f.*,
                                            CONCAT(e.first_name,' ',e.last_name)  AS uploaded_by_name,
                                            CONCAT(de.first_name,' ',de.last_name) AS deleted_by_name
                                     FROM files f
                                     LEFT JOIN employee e  ON f.uploaded_by=e.emp_id
                                     LEFT JOIN employee de ON f.deleted_by=de.emp_id
                                     WHERE f.is_deleted=1 AND f.uploaded_by=?
                                     ORDER BY f.deleted_at DESC";
                            $tr_st = $db->prepare($tr_q);
                            $tr_st->bind_param('i', $user_emp_id);
                            $tr_st->execute();
                            $tr_arr = [];
                            $tr_r = $tr_st->get_result();
                            while ($row = $tr_r->fetch_assoc()) $tr_arr[] = $row;
                            echo json_encode(['success' => true, 'files' => $tr_arr]);
                        } else {
                            echo json_encode(['success' => true, 'files' => [],
                                'message' => 'Trash requires is_deleted column. Run: ALTER TABLE files ADD COLUMN is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL;']);
                        }
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
                        <div class="gd-file-list mb-4">
                            <!-- Header row -->
                            <div class="gd-header">
                                <div class="gd-col-check"><input type="checkbox" id="selectAllFolders"></div>
                                <div class="gd-col-name">Name</div>
                                <div class="gd-col-owner">Owner</div>
                                <div class="gd-col-date">Created</div>
                                <div class="gd-col-size">Items</div>
                                <div class="gd-col-actions"></div>
                            </div>
                            <!-- Folder rows -->
                            <?php foreach ($folders as $folder):
                                $has_access = hasFolderPermission($db, $folder['folder_id'], $user_emp_id, 'view');
                                $can_edit   = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'edit_folder');
                                $can_share  = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'share_folder');
                                $can_delete = canPerformAction($db, $folder['folder_id'], $user_emp_id, 'delete_folder');
                                $total_items = $folder['file_count'] + $folder['subfolder_count'];
                            ?>
                            <div class="gd-file-row folder-row" 
                                id="folderRow_<?= $folder['folder_id'] ?>"
                                ondblclick="<?= $has_access ? "openFolder({$folder['folder_id']}, '" . htmlspecialchars(addslashes($folder['folder_name'])) . "', {$folder['is_locked']})" : "showNoAccess()" ?>">
                                
                                <div class="gd-col-check">
                                    <input type="checkbox" class="folder-checkbox" value="<?= $folder['folder_id'] ?>">
                                </div>
                                
                                <!-- In the folder row, replace the icon line with this: -->
                                <div class="gd-col-name">
                                    <i class="fas fa-folder <?= $folder['is_locked'] ? 'fa-folder-locked' : '' ?> gd-file-icon" 
                                    style="color: <?= $folder['is_locked'] ? '#ef4444' : '#fbbf24' ?>"></i>
                                    <span class="gd-file-name"><?= htmlspecialchars($folder['folder_name']) ?></span>
                                    <?php if ($folder['is_locked']): ?>
                                        <span class="badge badge-danger ml-2" style="font-size:10px"><i class="fas fa-lock"></i> Protected</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="gd-col-owner">
                                    <span class="gd-owner-avatar"><?= strtoupper(substr($folder['creator_name'] ?? 'U', 0, 1)) ?></span>
                                    <span><?= htmlspecialchars($folder['creator_name'] ?? 'me') ?></span>
                                </div>
                                
                                <div class="gd-col-date"><?= date('M j, Y', strtotime($folder['created_at'])) ?></div>
                                
                                <div class="gd-col-size">
                                    <?= $folder['file_count'] ?> file<?= $folder['file_count'] != 1 ? 's' : '' ?>
                                    <?php if ($folder['subfolder_count'] > 0): ?>
                                        <span class="text-muted">, <?= $folder['subfolder_count'] ?> subfolder<?= $folder['subfolder_count'] != 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="gd-col-actions">
                                    <?php if ($can_edit): ?>
                                    <button class="gd-action-btn" title="Edit" onclick="event.stopPropagation(); editFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', '<?= htmlspecialchars(addslashes($folder['description'] ?? '')) ?>')">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_share): ?>
                                    <button class="gd-action-btn" title="Share" onclick="event.stopPropagation(); shareFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete): ?>
                                    <button class="gd-action-btn" title="Delete" onclick="event.stopPropagation(); deleteFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', <?= $folder['is_locked'] ?>)">
                                        <i class="fas fa-trash" style="color: #ef4444;"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="gd-action-btn gd-action-more" title="More actions"
                                            onclick="event.stopPropagation(); toggleFolderMenuModern(this, event, <?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    
                                    <!-- Modern dropdown menu -->
                                    <div class="gd-file-menu folder-menu-<?= $folder['folder_id'] ?>">
                                        <?php if ($has_access): ?>
                                        <button class="gd-menu-item" onclick="openFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', <?= $folder['is_locked'] ?>)">
                                            <i class="fas fa-folder-open"></i> Open
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_edit): ?>
                                        <button class="gd-menu-item" onclick="editFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', '<?= htmlspecialchars(addslashes($folder['description'] ?? '')) ?>')">
                                            <i class="fas fa-edit"></i> Rename/Edit
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_share): ?>
                                        <button class="gd-menu-item" onclick="shareFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                            <i class="fas fa-share-alt"></i> Share
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (canPerformAction($db, $folder['folder_id'], $user_emp_id, 'manage_shares')): ?>
                                        <button class="gd-menu-item" onclick="manageShares(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                            <i class="fas fa-users-cog"></i> Manage Access
                                        </button>
                                        <?php endif; ?>
                                        
                                        <hr style="margin:4px 0; border-color: var(--border-color);">
                                        
                                        <?php if ($can_delete): ?>
                                        <button class="gd-menu-item gd-menu-danger" onclick="deleteFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>', <?= $folder['is_locked'] ?>)">
                                            <i class="fas fa-trash"></i> Delete Folder
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <i id="fvFileIcon" class="fas fa-file mr-2" style="font-size:20px; flex-shrink:0;"></i>
                <h5 class="modal-title text-truncate" id="fvFileName" style="max-width:420px;">Loading…</h5>
                <div class="fv-header-actions">
                    <a id="fvDownloadBtn" href="#" class="fv-header-btn" title="Download"><i class="fas fa-download"></i></a>
                    <button class="fv-header-btn" title="Edit" onclick="openCurrentFileEdit()"><i class="fas fa-pencil-alt"></i></button>
                    <button class="fv-header-btn fv-star-btn" id="fvStarBtn" title="Star" onclick="toggleCurrentFileStar()"><i class="far fa-star"></i></button>
                    <button type="button" class="fv-header-btn" data-dismiss="modal" title="Close"><i class="fas fa-times"></i></button>
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
                    <div class="fv-info-title">File Details</div>
                    <div class="fv-info-row"><span class="fv-info-label">Type</span><span class="fv-info-value" id="fvFileType">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Size</span><span class="fv-info-value" id="fvFileSize">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Owner</span><span class="fv-info-value" id="fvOwner">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Modified</span><span class="fv-info-value" id="fvDate">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Location</span><span class="fv-info-value" id="fvLocation">—</span></div>
                    <hr style="margin:10px 0;">
                    <div class="fv-info-title">Description</div>
                    <div id="fvDescription" style="color:var(--text-muted); font-size:0.8rem; margin-bottom:1rem;">—</div>
                    <hr style="margin:10px 0;">
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
                $('#fvFileIcon').attr('class', iconClass + ' mr-2').css('font-size', '20px');
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

/* ================================================================
   FILE OPERATIONS
   ================================================================ */

/* Shared helpers */
function _esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#x27;');
}
function _fmtBytes(b) {
    if (!b || b==0) return '0 B';
    var k=1024, s=['B','KB','MB','GB'], i=Math.floor(Math.log(b)/Math.log(k));
    return parseFloat((b/Math.pow(k,i)).toFixed(1))+' '+s[i];
}
var _iconMap = {
    pdf:'file-pdf text-danger', doc:'file-word text-primary', docx:'file-word text-primary',
    xls:'file-excel text-success', xlsx:'file-excel text-success',
    ppt:'file-powerpoint text-warning', pptx:'file-powerpoint text-warning',
    jpg:'file-image text-info', jpeg:'file-image text-info', png:'file-image text-info',
    gif:'file-image text-info', zip:'file-archive text-secondary', rar:'file-archive text-secondary',
    txt:'file-alt text-muted', mp4:'file-video text-danger',
    avi:'file-video text-danger', mp3:'file-audio text-info', wav:'file-audio text-info'
};
function _fileIcon(ext) { return _iconMap[(ext||'').toLowerCase()] || 'file text-secondary'; }

/* Move to trash */
function deleteFile(fileId, fileName) {
    Swal.fire({
        title: 'Move to Trash?',
        html: 'Move <strong>' + _esc(fileName) + '</strong> to trash?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: '<i class="fas fa-trash mr-1"></i> Move to Trash'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.post('section_files.php?section_id=<?= $section_id ?>',
                   { action: 'delete_file', file_id: fileId },
            function(resp) {
                try {
                    var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
                    if (r.success) {
                        Swal.fire({ title:'Moved to Trash', icon:'success', timer:1400, showConfirmButton:false })
                            .then(function(){ location.reload(); });
                    } else { Swal.fire('Error', r.message||'Delete failed', 'error'); }
                } catch(e) { location.reload(); }
            });
        }
    });
}

/* Restore from trash */
function restoreFile(fileId, fileName) {
    Swal.fire({
        title: 'Restore File?',
        html: 'Restore <strong>' + _esc(fileName) + '</strong> from trash?',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: '<i class="fas fa-trash-restore mr-1"></i> Restore'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.post('section_files.php?section_id=<?= $section_id ?>',
                   { action: 'restore_file', file_id: fileId },
            function(resp) {
                try {
                    var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
                    if (r.success) {
                        Swal.fire({ title:'Restored!', icon:'success', timer:1400, showConfirmButton:false })
                            .then(function(){ filterTrash(); });
                    } else { Swal.fire('Error', r.message, 'error'); }
                } catch(e) { filterTrash(); }
            });
        }
    });
}

/* Permanent delete — requires checkbox confirmation */
function permanentDeleteFile(fileId, fileName) {
    Swal.fire({
        title: 'Permanently Delete?',
        html: '<p>You are about to <strong>permanently delete</strong>:</p>'
            + '<p style="margin:8px 0;font-weight:600;color:#ef4444;word-break:break-all">' + _esc(fileName) + '</p>'
            + '<p><strong style="color:#ef4444">This cannot be undone!</strong></p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete Forever',
        input: 'checkbox',
        inputValue: 0,
        inputPlaceholder: 'I understand this action is permanent and cannot be undone',
        preConfirm: function(checked) {
            if (!checked) {
                Swal.showValidationMessage('Please check the box to confirm you understand');
                return false;
            }
        }
    }).then(function(result) {
        if (result.isConfirmed) {
            $.post('section_files.php?section_id=<?= $section_id ?>',
                   { action: 'permanent_delete_file', file_id: fileId },
            function(resp) {
                try {
                    var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
                    if (r.success) {
                        Swal.fire({ title:'Permanently Deleted', icon:'success', timer:1400, showConfirmButton:false })
                            .then(function(){ filterTrash(); });
                    } else { Swal.fire('Error', r.message, 'error'); }
                } catch(e) { filterTrash(); }
            });
        }
    });
}

/* Star / unstar (from grid/table) */
function toggleStarFile(fileId, currentStar) {
    var newStar = currentStar ? 0 : 1;
    $.post('section_files.php?section_id=<?= $section_id ?>',
           { action: 'toggle_star', file_id: fileId, starred: newStar },
    function(resp) {
        try {
            var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
            if (r.success) filterStarred();
        } catch(e) {}
    });
}

/* Star UI helper */
function _updateStarUI(starred) {
    const ic  = starred ? 'fas fa-star' : 'far fa-star';
    const col = starred ? '#f59e0b' : '';
    $('#fvStarBtn i').attr('class', ic).css('color', col);
    $('#fvStarBtn2 i').attr('class', ic + ' mr-1').css('color', col);
    $('#fvStarBtnLabel').text(starred ? 'Remove from Starred' : 'Add to Starred');
    $('#fvStarBtn2').css({ background: starred ? '#fffbeb' : 'transparent' });
}

/* Star toggle from file view modal */
function toggleCurrentFileStar() {
    if (!window._fvCurrentFile) return;
    const fileId    = window._fvCurrentFile.file_id;
    const isStarred = window._fvCurrentFile.is_starred ? true : false;
    $.post('section_files.php?section_id=<?= $section_id ?>',
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

/* ================================================================
   SIDEBAR VIEW FILTERS
   ================================================================ */

function _sbSetActive(id) {
    $('.nav-sidebar .nav-link').removeClass('sb-active active');
    if (id) $('#'+id).addClass('sb-active');
}

/* ── Shared with me ─────────────────────────────────────────── */
function filterShared() {
    _sbSetActive('sbSharedLink');
    var body = document.getElementById('explorerBody');
    if (!body) return;
    body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i>'
                   + '<p class="mt-2 text-muted">Loading shared items&hellip;</p></div>';

    $.post('section_files.php?section_id=<?= $section_id ?>', { action: 'get_shared_items' },
    function(resp) {
        try {
            var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
            var html = '';

            /* Shared folders */
            if (r.folders && r.folders.length > 0) {
                html += '<p class="exp-section-title"><i class="fas fa-folder mr-1" style="color:#fbbf24"></i> Shared Folders (' + r.folders.length + ')</p>';
                html += '<div class="folder-grid">';
                r.folders.forEach(function(f) {
                    var bc = {view:'info',upload:'primary',edit:'warning',manage:'success'}[f.permission_level] || 'secondary';
                    html += '<div class="folder-card" ondblclick="openFolder('+f.folder_id+',&apos;'+_esc(f.folder_name)+'&apos;,'+f.is_locked+')">'
                          + '<i class="fas fa-folder fc-icon"></i>'
                          + '<div class="fc-name">' + _esc(f.folder_name) + '</div>'
                          + '<div class="fc-meta">' + (f.file_count||0) + ' files'
                          + ' &bull; <span class="badge badge-' + bc + '" style="font-size:10px">' + (f.permission_level||'view') + '</span></div>'
                          + '<div class="fc-meta" style="font-size:11px">By: ' + _esc(f.shared_by_name||'—') + '</div>'
                          + '</div>';
                });
                html += '</div>';
            }

            /* Shared files */
            if (r.files && r.files.length > 0) {
                html += '<p class="exp-section-title" style="margin-top:20px"><i class="fas fa-file-alt mr-1"></i> Shared Files (' + r.files.length + ')</p>';
                html += _buildFileTable(r.files, { showFolder: true, showSharedBy: true });
            }

            if ((!r.folders||!r.folders.length) && (!r.files||!r.files.length)) {
                html = '<div class="empty-state"><i class="fas fa-share-alt"></i>'
                     + '<p class="mb-1" style="font-size:1rem;font-weight:500">Nothing shared with you yet</p>'
                     + '<p style="font-size:.875rem">Items shared by colleagues will appear here</p></div>';
            }
            body.innerHTML = html;
        } catch(e) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle text-danger"></i><p>Error loading shared items</p></div>';
        }
    });
}

/* ── Starred ─────────────────────────────────────────────────── */
function filterStarred() {
    _sbSetActive('sbStarredLink');
    var body = document.getElementById('explorerBody');
    if (!body) return;
    body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x" style="color:#f59e0b"></i>'
                   + '<p class="mt-2 text-muted">Loading starred files&hellip;</p></div>';

    $.post('section_files.php?section_id=<?= $section_id ?>', { action: 'get_starred_files' },
    function(resp) {
        try {
            var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
            var html = '';
            if (r.files && r.files.length > 0) {
                html += '<p class="exp-section-title"><i class="fas fa-star mr-1" style="color:#f59e0b"></i> Starred Files (' + r.files.length + ')</p>';
                html += _buildFileTable(r.files, { showStar: true, starIsOn: true });
            } else {
                html = '<div class="empty-state"><i class="far fa-star" style="color:#f59e0b;opacity:.6"></i>'
                     + '<p class="mb-1" style="font-size:1rem;font-weight:500">No starred files</p>'
                     + '<p style="font-size:.875rem">Star files to find them quickly here</p></div>';
                if (r.message) html += '<p class="text-center text-muted small px-3">' + _esc(r.message) + '</p>';
            }
            body.innerHTML = html;
        } catch(e) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle text-danger"></i><p>Error loading starred files</p></div>';
        }
    });
}
function filterImportant() { filterStarred(); } /* backward-compat alias */

/* ── Trash ───────────────────────────────────────────────────── */
function filterTrash() {
    _sbSetActive('sbTrashLink');
    var body = document.getElementById('explorerBody');
    if (!body) return;
    body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i>'
                   + '<p class="mt-2 text-muted">Loading trash&hellip;</p></div>';

    $.post('section_files.php?section_id=<?= $section_id ?>', { action: 'get_trash_files' },
    function(resp) {
        try {
            var r = (typeof resp==='string') ? JSON.parse(resp) : resp;
            var html = '';
            if (r.files && r.files.length > 0) {
                html += '<div class="alert alert-warning d-flex align-items-start mb-3" style="font-size:.85rem">'
                      + '<i class="fas fa-exclamation-triangle mr-2 mt-1"></i>'
                      + '<span>Files in Trash can be <strong>restored</strong> or <strong>permanently deleted</strong>. '
                      + 'Permanent deletion <strong>cannot be undone</strong> and requires a confirmation checkbox.</span></div>';

                html += '<p class="exp-section-title"><i class="fas fa-trash mr-1"></i> Trash (' + r.files.length + ' item' + (r.files.length>1?'s':'') + ')</p>';
                html += '<div class="gd-file-list">';
                html += '<div class="gd-header">'
                      + '<div class="gd-col-check"></div>'
                      + '<div class="gd-col-name">Name</div>'
                      + '<div class="gd-col-owner">Owner</div>'
                      + '<div class="gd-col-date">Deleted On</div>'
                      + '<div class="gd-col-size">Size</div>'
                      + '<div class="gd-col-actions">Actions</div>'
                      + '</div>';

                r.files.forEach(function(f) {
                    var icon    = _fileIcon(f.file_type);
                    var delDate = f.deleted_at ? new Date(f.deleted_at).toLocaleDateString() : '—';
                    var owner   = _esc(f.uploaded_by_name || 'me');
                    html += '<div class="gd-file-row" style="opacity:.75">'
                          + '<div class="gd-col-check"></div>'
                          + '<div class="gd-col-name">'
                          + '<i class="fas fa-' + icon + ' gd-file-icon"></i>'
                          + '<span class="gd-file-name" style="text-decoration:line-through;color:var(--text-muted,#999)">' + _esc(f.file_name) + '</span>'
                          + '</div>'
                          + '<div class="gd-col-owner"><span class="gd-owner-avatar">' + owner.charAt(0).toUpperCase() + '</span>' + owner + '</div>'
                          + '<div class="gd-col-date">' + delDate + '</div>'
                          + '<div class="gd-col-size">' + _fmtBytes(f.file_size) + '</div>'
                          + '<div class="gd-col-actions" style="opacity:1">'
                          + '<button class="gd-action-btn" title="Restore" onclick="event.stopPropagation();restoreFile('+f.file_id+',&apos;'+_esc(f.file_name)+'&apos;)">'
                          + '<i class="fas fa-trash-restore" style="color:#10b981"></i></button>'
                          + '<button class="gd-action-btn" title="Delete Permanently" onclick="event.stopPropagation();permanentDeleteFile('+f.file_id+',&apos;'+_esc(f.file_name)+'&apos;)">'
                          + '<i class="fas fa-times-circle" style="color:#ef4444"></i></button>'
                          + '</div>'
                          + '</div>';
                });
                html += '</div>';
            } else {
                html = '<div class="empty-state"><i class="fas fa-trash" style="opacity:.4"></i>'
                     + '<p class="mb-1" style="font-size:1rem;font-weight:500">Trash is empty</p>'
                     + '<p style="font-size:.875rem">Deleted files will appear here</p></div>';
                if (r.message) html += '<p class="text-center text-muted small px-3">' + _esc(r.message) + '</p>';
            }
            body.innerHTML = html;
        } catch(e) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle text-danger"></i><p>Error loading trash</p></div>';
        }
    });
}

/* Reusable file list table builder */
function _buildFileTable(files, opts) {
    opts = opts || {};
    var html = '<div class="gd-file-list">';
    html += '<div class="gd-header">'
          + '<div class="gd-col-check"><input type="checkbox"></div>'
          + '<div class="gd-col-name">Name</div>'
          + '<div class="gd-col-owner">' + (opts.showSharedBy ? 'Shared by' : 'Owner') + '</div>'
          + '<div class="gd-col-date">Date</div>'
          + '<div class="gd-col-size">Size</div>'
          + '<div class="gd-col-actions"></div>'
          + '</div>';
    files.forEach(function(f) {
        var icon    = _fileIcon(f.file_type);
        var date    = f.created_at ? new Date(f.created_at).toLocaleDateString() : '—';
        var owner   = _esc(opts.showSharedBy ? (f.shared_by_name||'—') : (f.uploaded_by_name||'me'));
        var starred = f.is_starred ? 1 : 0;
        html += '<div class="gd-file-row" ondblclick="viewFileModal('+f.file_id+')">'
              + '<div class="gd-col-check"><input type="checkbox" class="file-checkbox" value="' + f.file_id + '"></div>'
              + '<div class="gd-col-name">'
              + '<i class="fas fa-' + icon + ' gd-file-icon"></i>'
              + '<span class="gd-file-name">' + _esc(f.file_name) + '</span>'
              + (opts.showFolder && f.folder_name ? '<span class="badge badge-secondary ml-2" style="font-size:10px">' + _esc(f.folder_name) + '</span>' : '')
              + '</div>'
              + '<div class="gd-col-owner"><span class="gd-owner-avatar">' + owner.charAt(0).toUpperCase() + '</span>' + owner + '</div>'
              + '<div class="gd-col-date">' + date + '</div>'
              + '<div class="gd-col-size">' + _fmtBytes(f.file_size) + '</div>'
              + '<div class="gd-col-actions">';
        if (opts.showStar) {
            html += '<button class="gd-action-btn" title="' + (starred?'Unstar':'Star') + '" onclick="event.stopPropagation();toggleStarFile(' + f.file_id + ',' + starred + ')">'
                  + '<i class="' + (starred?'fas':'far') + ' fa-star" style="color:#f59e0b"></i></button>';
        }
        html += '<a class="gd-action-btn" href="download_file.php?id=' + f.file_id + '" title="Download" onclick="event.stopPropagation()">'
              + '<i class="fas fa-download"></i></a>'
              + '<button class="gd-action-btn" title="Move to Trash" onclick="event.stopPropagation();deleteFile('+f.file_id+',&apos;'+_esc(f.file_name)+'&apos;)">'
              + '<i class="fas fa-trash" style="color:#ef4444"></i></button>'
              + '</div></div>';
    });
    html += '</div>';
    return html;
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

// Modern folder menu toggle (like file menu)
function toggleFolderMenuModern(btn, event, folderId, folderName) {
    event.stopPropagation();
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('show');
    
    // Close all other menus
    $('.gd-file-menu').removeClass('show');
    
    if (!isOpen) {
        menu.classList.add('show');
        
        // Close when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && e.target !== btn) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
}

// Select all folders
$(document).on('change', '#selectAllFolders', function() {
    $('.folder-checkbox').prop('checked', this.checked);
});

// Add hover effect for folder rows
$(document).on('mouseenter', '.folder-row', function() {
    $(this).find('.gd-col-actions').css('opacity', '1');
}).on('mouseleave', '.folder-row', function() {
    $(this).find('.gd-col-actions').css('opacity', '');
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