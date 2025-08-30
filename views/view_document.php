<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (isset($_GET['doc_id'])) {
    $doc_id = (int)$_GET['doc_id'];
    
    // Get document details
    $stmt = $conn->prepare("SELECT d.*, dt.type_name FROM documents d 
                           JOIN document_types dt ON d.type_id = dt.type_id
                           WHERE d.doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $filePath = $row['file_path'];
        $type_name = $row['type_name'];
        
        // Check if file exists
        if (file_exists($filePath)) {
            // Set appropriate headers based on file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            // For PDFs, show inline, for others offer download
            if (strpos($mime_type, 'pdf') !== false || strpos($type_name, 'PDF') !== false) {
                header('Content-Type: ' . $mime_type);
                header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            } else {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            }
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            exit;
        }
    }
}

// If we get here, something went wrong
header("HTTP/1.0 404 Not Found");
echo "Document not found or you don't have permission to view it.";
?>