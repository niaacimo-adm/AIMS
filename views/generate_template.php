<?php
session_start();
require_once '../config/database.php';
require_once '../includes/document_functions.php';

if (!isset($_GET['doc_id'])) {
    die("Document ID not provided");
}

$doc_id = (int)$_GET['doc_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get document details
    $stmt = $conn->prepare("SELECT d.*, dt.type_name, 
                          CONCAT(e.first_name, ' ', e.last_name) AS owner_name,
                          (SELECT CONCAT(emp.first_name, ' ', emp.last_name) 
                          FROM document_transfers dt 
                          JOIN employee emp ON dt.processed_by = emp.emp_id 
                          WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                          ORDER BY dt.processed_at DESC LIMIT 1) AS processed_by_name,
                          (SELECT s.section_name
                          FROM document_transfers dt
                          JOIN section s ON dt.to_section_id = s.section_id
                          WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                          ORDER BY dt.processed_at DESC LIMIT 1) AS processed_by_section,
                          (SELECT dt.status FROM document_transfers dt 
                          WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                          ORDER BY dt.processed_at DESC LIMIT 1) AS transfer_status,
                          (SELECT dt.processed_at FROM document_transfers dt 
                          WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                          ORDER BY dt.processed_at DESC LIMIT 1) AS processed_at
                          FROM documents d
                          JOIN document_types dt ON d.type_id = dt.type_id
                          JOIN employee e ON d.owner_id = e.emp_id
                          WHERE d.doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $document_details = $stmt->get_result()->fetch_assoc();
    
    if (!$document_details) {
        die("Document not found");
    }
    
    generateDocumentTemplate($document_details);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>