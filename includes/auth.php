<?php

function isAdmin($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT r.name 
              FROM users u 
              JOIN user_roles r ON u.role_id = r.id 
              WHERE u.employee_id = ? AND r.id = 01";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
function hasPermission($permissionName) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Admins have all permissions
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Administrator') {
        return true;
    }
    
    return isset($_SESSION['permissions']) && in_array($permissionName, $_SESSION['permissions']);
}

function checkPermission($permissionName) {
    if (!hasPermission($permissionName)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header("Location: /unauthorized.php");
        exit();
    }
}

function hasRole($roleName) {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $roleName;
}

function checkRole($roleName) {
    if (!hasRole($roleName)) {
        $_SESSION['error'] = 'You do not have the required role for this action.';
        header("Location: /unauthorized.php");
        exit();
    }
}

function isDocumentOwner($conn, $document_id) {
    if (!isset($_SESSION['user_id'])) return false;
    
    $stmt = $conn->prepare("
        SELECT owner_id 
        FROM documents 
        WHERE doc_id = ?
    ");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $document = $result->fetch_assoc();
        $current_emp_id = getEmployeeId($conn, $_SESSION['user_id']);
        return $document['owner_id'] == $current_emp_id;
    }
    
    return false;
}

// Helper function to get employee_id from user_id
function getEmployeeId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['employee_id'];
    }
    return null;
}



// Function to get all permissions for select boxes
function getAllPermissions() {
    global $db;
    $result = $db->query("SELECT * FROM permissions ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all roles
function getAllRoles() {
    global $db;
    $result = $db->query("SELECT * FROM user_roles ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get permissions for a specific role
function getRolePermissions($roleId) {
    global $db;
    $stmt = $db->prepare("
        SELECT p.id 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['id'];
    }
    
    return $permissions;
}

// Add this function to auth.php or a helper file
function getEmployeeIdFromUserId($db, $user_id) {
    $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE user_id = ? OR emp_id = ?");
    $emp_stmt->bind_param("ii", $user_id, $user_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();

    if ($emp_result->num_rows > 0) {
        $emp_data = $emp_result->fetch_assoc();
        return $emp_data['emp_id'];
    }
    
    // Try manager lookup
    $manager_stmt = $db->prepare("SELECT emp_id FROM employee WHERE is_manager = 1 AND user_id = ?");
    $manager_stmt->bind_param("i", $user_id);
    $manager_stmt->execute();
    $manager_result = $manager_stmt->get_result();
    
    if ($manager_result->num_rows > 0) {
        $manager_data = $manager_result->fetch_assoc();
        return $manager_data['emp_id'];
    }
    
    // Final fallback
    $default_stmt = $db->prepare("SELECT emp_id FROM employee WHERE is_manager = 1 LIMIT 1");
    $default_stmt->execute();
    $default_result = $default_stmt->get_result();
    
    if ($default_result->num_rows > 0) {
        $default_emp = $default_result->fetch_assoc();
        return $default_emp['emp_id'];
    }
    
    return null;
}
?>