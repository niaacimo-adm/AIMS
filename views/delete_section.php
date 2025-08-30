<?php
require_once '../config/database.php';

session_start();

header('Content-Type: application/json');

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
    exit();
}

// Verify user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get and validate input
$section_id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);

try {
    if (!$section_id || $section_id < 1) {
        throw new Exception("Invalid section ID");
    }

    // Start transaction
    $db->begin_transaction();

    // 1. Check for employees directly in this section
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        throw new Exception("Cannot delete section with $count directly assigned employees");
    }

    // 2. Check for employees in unit sections of this section
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee e 
                         JOIN unit_section us ON e.unit_section_id = us.unit_id
                         WHERE us.section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unit_employee_count = $result->fetch_assoc()['count'];
    
    if ($unit_employee_count > 0) {
        throw new Exception("Cannot delete section - $unit_employee_count employees are assigned to its unit sections");
    }

    // 3. Delete section secretaries
    $stmt = $db->prepare("DELETE FROM section_secretaries WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete section secretaries: " . $stmt->error);
    }

    // 4. Delete unit sections (now safe since we checked for employees)
    $stmt = $db->prepare("DELETE FROM unit_section WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete unit sections: " . $stmt->error);
    }

    // 5. Delete the section
    $stmt = $db->prepare("DELETE FROM section WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete section: " . $stmt->error);
    }

    // Commit transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Section and all related data deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($db && $db->begin_transaction()) {
        $db->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}