<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_permission' => false]);
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get user employee ID
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

$user_emp_id = getUserEmployeeId($db, $_SESSION['user_id']);

if (!$user_emp_id) {
    echo json_encode(['has_permission' => false]);
    exit();
}

$folder_id = $_POST['folder_id'] ?? 0;
$action = $_POST['action'] ?? '';

// Your existing canPerformAction function
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
        
        // Fixed permission hierarchy - properly ordered from lowest to highest
        $permission_hierarchy = [
            'view' => 1,      // Can only view
            'upload' => 2,    // Can view and upload
            'edit' => 3,      // Can view, upload, edit files, and download
            'manage' => 4     // Full access including folder management
        ];
        
        $required_level = $permission_hierarchy[$permission_type] ?? 0;
        $user_level = $permission_hierarchy[$share_data['permission_level']] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    return false; // No permission found
}

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
            'manage_shares' => 'manage'
        ];
        
        $required_permission = $permission_map[$action] ?? 'manage';
        return hasFolderPermission($db, $folder_id, $user_emp_id, $required_permission);
    }

$has_permission = canPerformAction($db, $folder_id, $user_emp_id, $action);

echo json_encode(['has_permission' => $has_permission]);
?>