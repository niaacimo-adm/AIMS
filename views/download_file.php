<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get file ID
$file_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$file_id) {
    die('File ID is required');
}

// Fetch file details
$stmt = $db->prepare("SELECT * FROM files WHERE file_id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('File not found');
}

$file = $result->fetch_assoc();
$file_path = '../uploads/' . $file['file_path'];

// Check if user has download permission (edit permission or higher)
$user_emp_id = $_SESSION['user_emp_id'] ?? $_SESSION['user_id'];

if ($file['folder_id']) {
    require_once 'section_files.php'; // Include to use hasFolderPermission function
    if (!hasFolderPermission($db, $file['folder_id'], $user_emp_id, 'edit')) {
        die('You do not have permission to download this file');
    }
}

// Check if file exists
if (!file_exists($file_path)) {
    die('File not found on server');
}

// Log download activity
$log_stmt = $db->prepare("INSERT INTO file_activity_logs (file_id, emp_id, activity_type, description, ip_address) 
                         VALUES (?, ?, 'downloaded', ?, ?)");
$log_description = "File '{$file['file_name']}' downloaded";
$ip = $_SERVER['REMOTE_ADDR'];
$log_stmt->bind_param("iiss", $file_id, $user_emp_id, $log_description, $ip);
$log_stmt->execute();

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
flush(); // Flush system output buffer
readfile($file_path);
exit;
?>