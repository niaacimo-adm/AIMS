<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $transfer_id = (int)$_POST['transfer_id'];
    $doc_id = (int)$_POST['doc_id'];
    $section_data = explode('_', $_POST['section_id']);
    $section_type = $section_data[0];
    $section_id = (int)$section_data[1];
    
    // Get current user's employee_id
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_emp_id = $result->fetch_assoc()['employee_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get the original transfer details
        $transfer_stmt = $conn->prepare("SELECT * FROM document_transfers WHERE transfer_id = ?");
        $transfer_stmt->bind_param("i", $transfer_id);
        $transfer_stmt->execute();
        $transfer = $transfer_stmt->get_result()->fetch_assoc();
        
        if (!$transfer) {
            throw new Exception("Transfer record not found");
        }
        
        // Create new transfer record
        $new_transfer_stmt = $conn->prepare("INSERT INTO document_transfers 
                                          (doc_id, from_emp_id, to_section_id, to_unit_id, status) 
                                          VALUES (?, ?, ?, ?, 'pending')");
        
        $to_section_id = $section_id;
        $to_unit_id = ($section_type === 'unit') ? $section_id : null;
        
        $new_transfer_stmt->bind_param("iiii", $doc_id, $current_emp_id, $to_section_id, $to_unit_id);
        $new_transfer_stmt->execute();
        
        // Add to history
        $history_stmt = $conn->prepare("INSERT INTO document_history 
                                      (doc_id, emp_id, action, details) 
                                      VALUES (?, ?, 'forwarded', ?)");
        
        $details = "Forwarded document to new section/unit";
        $history_stmt->bind_param("iis", $doc_id, $current_emp_id, $details);
        $history_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Document forwarded successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}