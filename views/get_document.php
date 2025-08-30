<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!isset($_GET['doc_id'])) {
        throw new Exception("Document ID not provided");
    }
    
    $doc_id = (int)$_GET['doc_id'];
    
    $query = "SELECT d.*, dt.type_name, 
              CONCAT(e.first_name, ' ', e.last_name) AS owner_name
              FROM documents d
              JOIN document_types dt ON d.type_id = dt.type_id
              JOIN employee e ON d.owner_id = e.emp_id
              WHERE d.doc_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Document not found");
    }
    
    $document = $result->fetch_assoc();
    
    // Get file size
    $document['file_size'] = @filesize($document['file_path']);
    
    echo json_encode([
        'success' => true,
        'data' => $document
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}