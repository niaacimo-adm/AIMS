<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$response = [
    'success' => false,
    'message' => '',
    'userCount' => 0,
    'userList' => []
];

try {
    $roleId = $_POST['role_id'] ?? null;
    
    if (!$roleId) {
        throw new Exception("Role ID is required");
    }

    // Check if role is assigned to any users and get details
    $stmt = $db->prepare("
        SELECT u.id, u.user, e.first_name, e.last_name 
        FROM users u
        LEFT JOIN employee e ON u.employee_id = e.emp_id
        WHERE u.role_id = ?
    ");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignedUsers = $result->fetch_all(MYSQLI_ASSOC);
    $userCount = count($assignedUsers);

    $response['userCount'] = $userCount;
    
    // Prepare user list for display (first 5 users)
    foreach (array_slice($assignedUsers, 0, 5) as $user) {
        $name = !empty($user['first_name']) ? 
            $user['first_name'] . ' ' . $user['last_name'] : 
            $user['user'];
        $response['userList'][] = htmlspecialchars($name);
    }

    if ($userCount > 0) {
        $response['message'] = 'Cannot delete role because it is assigned to ' . $userCount . ' user(s)';
        echo json_encode($response);
        exit();
    }

    // Begin transaction
    $db->begin_transaction();
    
    // First delete role permissions
    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    
    // Then delete the role
    $stmt = $db->prepare("DELETE FROM user_roles WHERE id = ?");
    $stmt->bind_param("i", $roleId);
    
    if ($stmt->execute()) {
        $db->commit();
        $response['success'] = true;
        $response['message'] = 'Role deleted successfully';
    } else {
        throw new Exception("Failed to delete role");
    }
} catch (Exception $e) {
    $db->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>