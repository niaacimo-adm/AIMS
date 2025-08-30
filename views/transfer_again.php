<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get the original transfer details
    $transferId = (int)$_POST['transfer_id'];
    $docId = (int)$_POST['doc_id'];
    
    // Get current user's employee_id
    $currentUserId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentEmpId = $result->fetch_assoc()['employee_id'];
    
    if (!$currentEmpId) {
        throw new Exception("User not properly linked to an employee");
    }
    
    // Get the original transfer details
    $transferQuery = $conn->prepare("
        SELECT to_section_id, to_unit_id 
        FROM document_transfers 
        WHERE transfer_id = ?
    ");
    $transferQuery->bind_param("i", $transferId);
    $transferQuery->execute();
    $originalTransfer = $transferQuery->get_result()->fetch_assoc();
    
    if (!$originalTransfer) {
        throw new Exception("Original transfer not found");
    }
    
    // Check if there's already a pending transfer for this doc to the same section/unit
    $checkPending = $conn->prepare("
        SELECT transfer_id 
        FROM document_transfers 
        WHERE doc_id = ? 
        AND to_section_id = ? 
        AND (to_unit_id = ? OR (? IS NULL AND to_unit_id IS NULL))
        AND status = 'pending'
    ");
    $checkPending->bind_param("iiii", $docId, $originalTransfer['to_section_id'], 
        $originalTransfer['to_unit_id'], $originalTransfer['to_unit_id']);
    $checkPending->execute();
    
    if ($checkPending->get_result()->num_rows > 0) {
        throw new Exception("A pending transfer already exists for this section/unit");
    }
    
    // Create new transfer
    $insertTransfer = $conn->prepare("
        INSERT INTO document_transfers 
        (doc_id, from_emp_id, to_section_id, to_unit_id, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $insertTransfer->bind_param("iiii", $docId, $currentEmpId, 
        $originalTransfer['to_section_id'], $originalTransfer['to_unit_id']);
    $insertTransfer->execute();
    
    // Add to history
    $sectionName = '';
    $sectionQuery = $conn->prepare("SELECT section_name FROM section WHERE section_id = ?");
    $sectionQuery->bind_param("i", $originalTransfer['to_section_id']);
    $sectionQuery->execute();
    $sectionResult = $sectionQuery->get_result();
    if ($sectionResult->num_rows > 0) {
        $sectionName = $sectionResult->fetch_assoc()['section_name'];
    }
    
    $details = "Sent to " . $sectionName;
    if (!empty($originalTransfer['to_unit_id'])) {
        $unitQuery = $conn->prepare("SELECT unit_name FROM unit_section WHERE unit_id = ?");
        $unitQuery->bind_param("i", $originalTransfer['to_unit_id']);
        $unitQuery->execute();
        $unitResult = $unitQuery->get_result();
        if ($unitResult->num_rows > 0) {
            $details .= " (" . $unitResult->fetch_assoc()['unit_name'] . " unit)";
        }
    }
    
    $historyStmt = $conn->prepare("
        INSERT INTO document_history 
        (doc_id, emp_id, action, details) 
        VALUES (?, ?, 'transferred', ?)
    ");
    $historyStmt->bind_param("iis", $docId, $currentEmpId, $details);
    $historyStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document transfer created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>