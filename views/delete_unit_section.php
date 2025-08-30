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
$unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);

try {
    if (!$unit_id || $unit_id < 1) {
        throw new Exception("Invalid unit section ID");
    }

    // Start transaction
    $db->begin_transaction();

    // 1. Check for employees in this unit
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee WHERE unit_section_id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        throw new Exception("Cannot delete unit section with $count assigned employees");
    }

    // 2. Delete the unit section
    $stmt = $db->prepare("DELETE FROM unit_section WHERE unit_id = ?");
    $stmt->bind_param("i", $unit_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete unit section: " . $stmt->error);
    }

    // Commit transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Unit section deleted successfully'
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