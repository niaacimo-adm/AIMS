<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in.']);
    exit;
}

try {
    // Use database connection from config instead of hardcoded credentials
    $database = new Database();
    $db = $database->getConnection();

    // Look up the user's role the same way dashboard.php does,
    // since role is not stored directly in the session
    $roleStmt = $db->prepare("SELECT r.name as role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
    $roleStmt->bind_param("i", $_SESSION['user_id']);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();

    if ($roleResult->num_rows === 0) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Access denied. User not found.']);
        exit;
    }

    $roleRow = $roleResult->fetch_assoc();
    if ($roleRow['role_name'] !== 'Administrator') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Access denied. Administrator privileges required.']);
        exit;
    }
    
    // Credentials matching config/database.php
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database_name = 'sahur';

    // Path to mysqldump binary. On Windows/XAMPP this is usually NOT on the
    // system PATH, so 'mysqldump' alone often fails silently. Point this at
    // your actual mysqldump.exe (typical XAMPP location shown below), or
    // leave as 'mysqldump' if it works on your system's PATH already.
    $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (!file_exists($mysqldumpPath)) {
        // Fall back to assuming it's on PATH (e.g. Linux/Mac installs)
        $mysqldumpPath = 'mysqldump';
    }

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
    $backupFilename = 'backup_' . $database_name . '_' . $timestamp . '.sql';
    // Resolve the directory (which DOES already exist) rather than the file
    // (which doesn't exist yet) - realpath() on a nonexistent file returns
    // false, which was silently breaking the backup.
    $backupFile = rtrim(realpath($backupDir), '\\/') . DIRECTORY_SEPARATOR . $backupFilename;
    $errorLogFile = rtrim(realpath($backupDir), '\\/') . DIRECTORY_SEPARATOR . 'backup_error_' . $timestamp . '.log';

    // Build mysqldump command. IMPORTANT: stderr goes to its own log file,
    // NOT into the .sql backup via 2>&1 - mixing them means any real
    // mysqldump error gets silently written inside the backup file itself
    // instead of being visible to us, and PHP's $output stays empty.
    $command = escapeshellarg($mysqldumpPath)
        . " --host=" . escapeshellarg($host)
        . " --user=" . escapeshellarg($username)
        . ($password !== '' ? " --password=" . escapeshellarg($password) : "")
        . " " . escapeshellarg($database_name)
        . " --result-file=" . escapeshellarg($backupFile)
        . " 2> " . escapeshellarg($errorLogFile);

    // Execute command
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);

    // Pull mysqldump's actual error text (if any) from the separate log
    $mysqldumpError = '';
    if (file_exists($errorLogFile)) {
        $mysqldumpError = trim(file_get_contents($errorLogFile));
        @unlink($errorLogFile); // clean up the log file after reading it
    }
    
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
        if (!empty($mysqldumpError)) {
            $errorMessage .= 'Error: ' . $mysqldumpError;
        } elseif (!empty($output)) {
            $errorMessage .= 'Error: ' . implode(', ', $output);
        } else {
            $errorMessage .= 'No output was returned by the command.';
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

        // --- TEMPORARY DEBUG INFO - remove once backups are working ---
        $debug = [
            'mysqldump_path_used' => $mysqldumpPath,
            'mysqldump_path_exists' => file_exists($mysqldumpPath) ? true : false,
            'php_os' => PHP_OS,
            'exec_function_exists' => function_exists('exec'),
            'return_code' => $returnVar,
            'raw_output' => $output,
            'mysqldump_error' => $mysqldumpError,
            'command_used' => str_replace($password !== '' ? $password : '###', '***', $command),
            'backup_dir_realpath' => realpath($backupDir),
        ];
        error_log('Backup Debug: ' . json_encode($debug));
        echo json_encode([
            'success' => false,
            'message' => 'Backup failed: ' . $errorMessage,
            'debug' => $debug
        ]);
        exit;
        // --- END TEMPORARY DEBUG INFO ---
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