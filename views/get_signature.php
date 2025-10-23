<?php
require_once '../config/database.php';
require_once '../includes/document_functions.php';

header('Content-Type: application/json');

if ($_POST && isset($_POST['section_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    $documentFunctions = new DocumentFunctions();
    
    $signature = $documentFunctions->getToSectionHeadInitials($_POST['section_id']);
    
    echo json_encode([
        'success' => true,
        'signature' => $signature
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'No section ID provided']);
}
?>