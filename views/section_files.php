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

    // FUNCTION: Check if user has permission to access folder
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
            
            // Check permission hierarchy
            $permission_hierarchy = ['view' => 1, 'upload' => 2, 'edit' => 3, 'manage' => 4];
            $required_level = $permission_hierarchy[$permission_type] ?? 0;
            $user_level = $permission_hierarchy[$share_data['permission_level']] ?? 0;
            
            return $user_level >= $required_level;
        }
        
        return false; // No permission found
    }

    // FUNCTION: Get accessible folders for user
    function getAccessibleFolders($db, $section_id, $user_emp_id) {
        $accessible_folders = [];
        
        if ($section_id === 'manager') {
            $query = "SELECT f.*, 
                            CONCAT(e.first_name, ' ', e.last_name) as creator_name,
                            (SELECT COUNT(*) FROM files WHERE folder_id = f.folder_id) as file_count,
                            (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as subfolder_count
                    FROM folders f 
                    LEFT JOIN employee e ON f.created_by = e.emp_id 
                    WHERE f.section_id IS NULL AND f.parent_folder_id IS NULL
                    ORDER BY f.folder_name";
            $stmt = $db->prepare($query);
        } else {
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
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Check if user has view permission for this folder
            if (hasFolderPermission($db, $row['folder_id'], $user_emp_id, 'view')) {
                $accessible_folders[] = $row;
            }
        }
        
        return $accessible_folders;
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
                        
                        if (empty($folder_shares)) {
                            echo '<div class="alert alert-info text-center"><i class="fas fa-info-circle mr-2"></i>This folder is not shared with anyone.</div>';
                        } else {
                            echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Employee</th><th>Permission</th><th>Expires</th><th>Action</th></tr></thead><tbody>';
                            foreach ($folder_shares as $share) {
                                $badge_class = getPermissionBadgeClass($share['permission_level']);
                                echo '<tr>
                                    <td><div class="font-weight-bold">' . htmlspecialchars($share['employee_name']) . '</div><small class="text-muted">' . htmlspecialchars($share['employee_email']) . '</small></td>
                                    <td><span class="badge badge-' . $badge_class . '">' . ucfirst($share['permission_level']) . '</span></td>
                                    <td>' . ($share['expires_at'] ? date('M j, Y', strtotime($share['expires_at'])) : 'Never') . '</td>
                                    <td><button class="btn btn-sm btn-danger revoke-access" data-share-id="' . $share['share_id'] . '" data-employee-name="' . htmlspecialchars($share['employee_name']) . '"><i class="fas fa-times"></i> Revoke</button></td>
                                </tr>';
                            }
                            echo '</tbody></table></div>';
                        }
                        exit();
            }
        }
    }

    // Use the new function to get accessible folders
    $folders = getAccessibleFolders($db, $section_id, $user_emp_id);

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
    <link rel="stylesheet" href="../css/section_files.css">
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
                                                    <button class="folder-action-item share" 
                                                            onclick="shareFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                                        <i class="fas fa-share-alt mr-2"></i>Share
                                                    </button>
                                                    <button class="folder-action-item manage-shares" 
                                                            onclick="manageShares(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                                        <i class="fas fa-users mr-2"></i>Manage Access
                                                    </button>
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
                                                        <button class="folder-action-item share" 
                                                                onclick="shareFolder(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                                            <i class="fas fa-share-alt mr-2"></i>Share
                                                        </button>
                                                        <button class="folder-action-item manage-shares" 
                                                                onclick="manageShares(<?= $folder['folder_id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')">
                                                            <i class="fas fa-users mr-2"></i>Manage Access
                                                        </button>
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
                                <br>
                                <br>
                                <!-- Files Grid -->
                                <h5 class="mt-4">Files (<?= count($files) ?>)</h5>
                                <?php if (empty($files)): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No files in this directory.
                                    </div>
                                <?php else: ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>File Management</span>
                                                <div>
                                                    <button type="button" class="btn btn-danger btn-sm" id="deleteSelectedFiles" style="display: none;">
                                                        <i class="fas fa-trash mr-1"></i> Delete Selected
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th width="30">
                                                                <input type="checkbox" id="selectAllFiles">
                                                            </th>
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
                                                                <input type="checkbox" class="file-checkbox" value="<?= $file['file_id'] ?>">
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
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Files (Max 10 files, 500MB each)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- File Drop Zone -->
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h5>Drag & Drop files here</h5>
                            <p class="text-muted">or click to browse (Max 500MB per file)</p>
                            <input type="file" id="fileInput" name="files[]" multiple accept="*/*" style="display: none;">
                            <button type="button" class="btn btn-primary mt-2" id="browseFilesBtn">
                                <i class="fas fa-folder-open mr-2"></i>Browse Files
                            </button>
                        </div>
                    </div>
                    
                    <!-- Selected Files List -->
                    <div class="selected-files-container mt-3" id="selectedFilesContainer" style="display: none;">
                        <h6>Selected Files (<span id="fileCount">0</span>/10)</h6>
                        <div class="selected-files-list" id="selectedFilesList">
                            <!-- Files will be listed here -->
                        </div>
                    </div>
                    
                    <!-- File Description -->
                    <div class="form-group mt-3">
                        <label for="fileDescription">Description (applies to all files)</label>
                        <textarea class="form-control" id="fileDescription" name="description" rows="3" placeholder="Optional description for all uploaded files"></textarea>
                    </div>
                    
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="folder_id" value="">
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
    <div class="modal-dialog">
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
                <button type="button" class="btn btn-primary" onclick="$('#manageSharesModal').modal('hide'); $('#shareFolderModal').modal('show');">
                    <i class="fas fa-share-alt mr-1"></i> Share with More
                </button>
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
    // Share folder function
    function shareFolder(folderId, folderName) {
        $('#shareFolderId').val(folderId);
        $('#shareFolderModal .modal-title').html('Share Folder: ' + folderName);
        $('#shareEmployees').empty();
        
        // Load employees via AJAX
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
                        
                        // Initialize Select2
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
        
        // Close the menu
        document.querySelectorAll('.folder-actions-menu').forEach(m => {
                m.classList.remove('show');
            });
        }

        // Manage shares function
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
                error: function(xhr, status, error) {
                    Swal.fire('Error!', 'Failed to load shares: ' + error, 'error');
                }
            });
            
            // Close the menu
            document.querySelectorAll('.folder-actions-menu').forEach(m => {
                m.classList.remove('show');
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
                            Swal.fire({
                                title: 'Error!',
                                text: result.message,
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
                    shareBtn.prop('disabled', false).html(originalText);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to share folder: ' + error,
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
                                        // Refresh the shares list
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
                        error: function(xhr, status, error) {
                            Swal.fire('Error!', 'Failed to revoke access: ' + error, 'error');
                        }
                    });
                }
            });
        });
        function handlePermissionError(message) {
            Swal.fire({
                title: 'Access Denied',
                text: message || 'You do not have permission to perform this action.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }

        // File Upload Functionality with Progress Tracking
let selectedFiles = [];

// Initialize file upload functionality
function initFileUpload() {
    const dropZone = $('#fileDropZone');
    const fileInput = $('#fileInput');
    const browseBtn = $('#browseFilesBtn');
    const uploadBtn = $('#uploadFilesBtn');
    
    // Browse files button
    browseBtn.click(function() {
        fileInput.click();
    });
    
    // File input change
    fileInput.change(function(e) {
        handleFiles(e.target.files);
    });
    
    // Drag and drop functionality
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        dropZone.addClass('dragover');
    });
    
    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        dropZone.removeClass('dragover');
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        dropZone.removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });
    
    // Click on drop zone to browse
    dropZone.click(function() {
        fileInput.click();
    });
}

// Handle selected files
function handleFiles(files) {
    const maxFiles = 10;
    const maxFileSize = 500 * 1024 * 1024; // 500MB in bytes
    
    // Convert FileList to Array
    const newFiles = Array.from(files);
    
    // Check if adding these files would exceed the limit
    if (selectedFiles.length + newFiles.length > maxFiles) {
        Swal.fire('Error!', `You can only upload up to ${maxFiles} files at once.`, 'error');
        return;
    }
    
    // Add new files to selection
    newFiles.forEach(file => {
        if (selectedFiles.length < maxFiles) {
            // Check if file already exists
            const fileExists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            
            // Check file size
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

// Update the file list UI
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
    
    // Show total size
    const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
    $('#totalSize').remove();
    container.append(`<div id="totalSize" class="small text-muted mt-2">Total size: ${formatFileSize(totalSize)}</div>`);
}

// Create file item element
function createFileItem(file, index) {
    const fileSize = formatFileSize(file.size);
    const fileExtension = file.name.split('.').pop().toLowerCase();
    const fileIcon = getFileIcon(fileExtension);
    
    return `
        <div class="file-item" data-index="${index}">
            <div class="file-info">
                <i class="fas fa-file-${fileIcon} file-icon"></i>
                <div class="file-details">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${fileSize}</div>
                </div>
            </div>
            <button type="button" class="file-remove" onclick="removeFile(${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
}

// Remove file from selection
function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
    updateUploadButton();
}

// Update upload button state
function updateUploadButton() {
    const uploadBtn = $('#uploadFilesBtn');
    uploadBtn.prop('disabled', selectedFiles.length === 0);
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Get file icon based on extension
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

// Upload form submission with progress tracking
$('#uploadForm').submit(function(e) {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        Swal.fire('Error!', 'Please select at least one file to upload.', 'error');
        return;
    }
    
    // Create progress UI
    createProgressUI();
    
    const formData = new FormData();
    
    // Add files
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    
    // Add other form data
    formData.append('description', $('#fileDescription').val());
    formData.append('section_id', '<?= $section_id ?>');
    formData.append('folder_id', '');
    formData.append('action', 'upload_files');
    
    const uploadBtn = $('#uploadFilesBtn');
    const originalText = uploadBtn.html();
    
    uploadBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
    
    $.ajax({
        url: 'upload_file.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            
            // Upload progress
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
                const result = JSON.parse(response);
                if (result.success) {
                    // Update progress to 100%
                    updateOverallProgress(100);
                    
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Success!',
                            html: `
                                <div class="text-left">
                                    <p>Successfully uploaded ${result.uploaded_count} file(s)</p>
                                    ${result.uploaded_files && result.uploaded_files.length > 0 ? 
                                        '<div class="mt-2"><strong>Uploaded files:</strong><ul class="pl-3">' + 
                                        result.uploaded_files.map(file => `<li>${file.name} (${formatFileSize(file.size)})</li>`).join('') + 
                                        '</ul></div>' : ''}
                                </div>
                            `,
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
                            <div class="text-left">
                                <p>Uploaded: ${result.uploaded_count || 0} files</p>
                                <p>Failed: ${result.failed_count || 0} files</p>
                                ${result.errors && result.errors.length > 0 ? 
                                    '<div class="mt-2"><strong>Errors:</strong><ul class="pl-3 text-danger">' + 
                                    result.errors.map(error => `<li>${error}</li>`).join('') + 
                                    '</ul></div>' : ''}
                            </div>
                        `,
                        icon: result.uploaded_count > 0 ? 'warning' : 'error',
                        confirmButtonText: 'OK'
                    });
                    
                    if (result.errors && result.errors.length > 0) {
                        console.error('Upload errors:', result.errors);
                    }
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
            
            // Remove progress UI
            removeProgressUI();
        },
        error: function(xhr, status, error) {
            uploadBtn.prop('disabled', false).html(originalText);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to upload files: ' + error,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            console.error('AJAX error:', error);
            
            // Remove progress UI
            removeProgressUI();
        }
    });
});

// Create progress UI
function createProgressUI() {
    // Remove existing progress UI if any
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
                    <span class="progress-text">0%</span>
                </div>
            </div>
            <div class="progress-info text-center">
                <small class="text-muted" id="progressStatus">Preparing upload...</small>
            </div>
        </div>
    `;
    
    $('#selectedFilesContainer').after(progressHTML);
}

// Update overall progress
function updateOverallProgress(percent) {
    const progressBar = $('#uploadProgress .progress-bar');
    const progressText = $('#uploadProgress .progress-text');
    const progressStatus = $('#progressStatus');
    
    progressBar.css('width', percent + '%');
    progressBar.attr('aria-valuenow', percent);
    progressText.text(Math.round(percent) + '%');
    
    if (percent < 100) {
        progressStatus.text(`Uploading... ${Math.round(percent)}%`);
    } else {
        progressStatus.text('Processing...');
    }
}

// Remove progress UI
function removeProgressUI() {
    $('#uploadProgress').remove();
}

// Reset upload form
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
    initFileUpload();
});

// File selection and deletion functionality
$(document).ready(function() {
    // Select All functionality
    $('#selectAllFiles').change(function() {
        $('.file-checkbox').prop('checked', this.checked);
        toggleDeleteButton();
    });
    
    // Individual checkbox functionality
    $(document).on('change', '.file-checkbox', function() {
        // If all checkboxes are checked, check the select all checkbox
        const totalCheckboxes = $('.file-checkbox').length;
        const checkedCheckboxes = $('.file-checkbox:checked').length;
        $('#selectAllFiles').prop('checked', totalCheckboxes === checkedCheckboxes);
        
        toggleDeleteButton();
    });
    
    // Toggle delete button visibility
    function toggleDeleteButton() {
        const checkedCount = $('.file-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#deleteSelectedFiles').show().html(`<i class="fas fa-trash mr-1"></i> Delete Selected (${checkedCount})`);
        } else {
            $('#deleteSelectedFiles').hide();
        }
    }
    
    // Delete selected files
    $('#deleteSelectedFiles').click(function() {
        const selectedFiles = [];
        $('.file-checkbox:checked').each(function() {
            selectedFiles.push($(this).val());
        });
        
        if (selectedFiles.length === 0) {
            Swal.fire('Error!', 'Please select at least one file to delete.', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Delete Files?',
            html: `Are you sure you want to delete <strong>${selectedFiles.length}</strong> selected file(s)?<br><small class="text-muted">This action cannot be undone.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteSelectedFiles(selectedFiles);
            }
        });
    });
    
    // Function to delete selected files
    function deleteSelectedFiles(fileIds) {
        const deleteBtn = $('#deleteSelectedFiles');
        const originalText = deleteBtn.html();
        deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        
        $.ajax({
            url: 'delete_files.php',
            type: 'POST',
            data: {
                action: 'delete_files',
                file_ids: fileIds,
                section_id: '<?= $section_id ?>'
            },
            success: function(response) {
                deleteBtn.prop('disabled', false).html(originalText);
                
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: result.message || 'Selected files have been deleted.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error!', result.message || 'Failed to delete files.', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error!', 'Invalid server response', 'error');
                    console.error('Parse error:', e, 'Response:', response);
                }
            },
            error: function(xhr, status, error) {
                deleteBtn.prop('disabled', false).html(originalText);
                Swal.fire('Error!', 'Failed to delete files: ' + error, 'error');
                console.error('AJAX error:', error);
            }
        });
    }
});

// Initialize file upload when modal is shown
$('#uploadFileModal').on('show.bs.modal', function() {
    resetUploadForm();
    initFileUpload();
});

// Fix for browse files button
function initFileUpload() {
    const dropZone = $('#fileDropZone');
    const fileInput = $('#fileInput');
    const browseBtn = $('#browseFilesBtn');
    
    // Browse files button - FIXED
    browseBtn.click(function(e) {
        e.stopPropagation(); // Prevent triggering dropZone click
        fileInput.click();
    });
    
    // File input change
    fileInput.change(function(e) {
        handleFiles(e.target.files);
    });
    
    // Drag and drop functionality
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        dropZone.addClass('dragover');
    });
    
    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        dropZone.removeClass('dragover');
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        dropZone.removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });
    
    // Click on drop zone to browse
    dropZone.click(function() {
        fileInput.click();
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