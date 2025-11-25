<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is administrator - FIXED ROLE CHECK
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied. Administrator privileges required.']);
    exit;
}

try {
    // Use database connection from config instead of hardcoded credentials
    $database = new Database();
    $db = $database->getConnection();
    
    // Get database credentials from connection (adjust based on your Database class)
    $host = 'localhost';
    $username = 'root'; // Replace with your actual username
    $password = ''; // Replace with your actual password
    $database_name = 'sahur';
    
    // Create backup directory if it doesn't exist
    $backupDir = '../database_backups/';
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Failed to create backup directory. Check directory permissions.');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($backupDir)) {
        throw new Exception('Backup directory is not writable. Please check permissions.');
    }

    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . 'backup_' . $database_name . '_' . $timestamp . '.sql';
    
    // Build mysqldump command - FIXED PATH ISSUES
    $command = "mysqldump --host={$host} --user={$username} --password={$password} {$database_name} > \"" . realpath($backupFile) . "\" 2>&1";
    
    // Execute command
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    // Check if backup was successful
    if ($returnVar === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
        // Get file size
        $fileSize = filesize($backupFile);
        $fileSizeFormatted = formatFileSize($fileSize);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Database backup created successfully!',
            'filename' => basename($backupFile),
            'filepath' => $backupFile,
            'filesize' => $fileSizeFormatted,
            'timestamp' => $timestamp
        ]);
    } else {
        $errorMessage = 'Failed to create backup file. ';
        if (!empty($output)) {
            $errorMessage .= 'Error: ' . implode(', ', $output);
        }
        
        // Check common issues
        if (!function_exists('exec')) {
            $errorMessage .= ' exec() function is disabled.';
        }
        
        if (!file_exists($backupFile)) {
            $errorMessage .= ' Backup file was not created.';
        } elseif (filesize($backupFile) === 0) {
            $errorMessage .= ' Backup file is empty.';
        }
        
        throw new Exception($errorMessage);
    }
    
} catch (Exception $e) {
    error_log('Backup Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Backup failed: ' . $e->getMessage()
    ]);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>