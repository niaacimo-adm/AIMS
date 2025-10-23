<?php
require_once '../config/database.php';
require_once '../includes/document_functions.php';

header('Content-Type: application/json');

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    $documentFunctions = new DocumentFunctions();
    
    $tracking_no = $documentFunctions->generateTrackingNumber($_POST['section_id'], $_POST['document_type']);
    
    echo json_encode([
        'success' => true,
        'tracking_no' => $tracking_no
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>