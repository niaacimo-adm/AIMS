<?php
require_once '../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $emp_id = $_POST['emp_id'] ?? null;
    $unit_id = $_POST['unit_id'] ?? null;

    if (!$emp_id || !$unit_id) {
        echo json_encode(['success' => false, 'message' => 'Employee ID and Unit ID are required']);
        exit();
    }

    try {
        // Start transaction
        $db->begin_transaction();

        // Get the section_id for this unit
        $stmt = $db->prepare("SELECT section_id FROM unit_section WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unit = $result->fetch_assoc();
        $section_id = $unit['section_id'] ?? null;

        if ($action === 'remove') {
            // Remove from unit but keep section assignment
            $stmt = $db->prepare("UPDATE employee SET unit_section_id = NULL WHERE emp_id = ?");
            $stmt->bind_param("i", $emp_id);
            
            if ($stmt->execute()) {
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Employee removed from unit']);
            } else {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to remove employee']);
            }
        } elseif ($action === 'add') {
            // Add to unit and update section
            $stmt = $db->prepare("UPDATE employee SET unit_section_id = ?, section_id = ? WHERE emp_id = ?");
            $stmt->bind_param("iii", $unit_id, $section_id, $emp_id);
            
            if ($stmt->execute()) {
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Employee added to unit and section updated']);
            } else {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to add employee to unit']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        if (isset($db) && $db->in_transaction) {
            $db->rollback();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit();
?>