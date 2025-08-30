<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php'; // Include auth functions

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed");
}

// Get document ID from POST (for QR code scans) or GET (for direct requests)
$doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : (isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0);
$security_hash = isset($_POST['security_hash']) ? $_POST['security_hash'] : '';

// Verify document exists and get owner info
$stmt = $conn->prepare("SELECT d.*, u.id as user_id 
                       FROM documents d
                       JOIN users u ON d.owner_id = u.employee_id
                       WHERE d.doc_id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();

if (!$document) {
    die("Document not found");
}

// For QR code requests, verify the security hash
if (!empty($security_hash)) {
    $expected_hash = md5($doc_id . $document['title'] . 'your_secret_salt');
    if ($security_hash !== $expected_hash) {
        die("Invalid security token");
    }
} 
// For direct requests, verify user has permission
else {
    // Check if user is owner or has view/download permission
    $hasAccess = isDocumentOwner($conn, $doc_id) || 
                hasPermission('view_any_document') || 
                hasPermission('download_document');
    
    if (!$hasAccess) {
        die("You don't have permission to access this document");
    }
}

// Check if file exists
if (!file_exists($document['file_path'])) {
    die("File not found");
}

// Determine if we're viewing or downloading
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// Set appropriate headers
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $document['file_path']);
finfo_close($finfo);

if ($action === 'download') {
    // Additional check for download permission if not owner
    if (!isDocumentOwner($conn, $doc_id) && !hasPermission('download_document')) {
        die("You don't have permission to download this document");
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($document['file_path']) . '"');
} else {
    // For viewing - check view permission if not owner
    if (!isDocumentOwner($conn, $doc_id) && !hasPermission('view_any_document')) {
        die("You don't have permission to view this document");
    }

    // For PDFs, show inline, for others force download
    if (strpos($mime_type, 'pdf') !== false) {
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . basename($document['file_path']) . '"');
    } else {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($document['file_path']) . '"');
    }
}

header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($document['file_path']));

// Clear output buffer
ob_clean();
flush();

readfile($document['file_path']);
exit;
?>