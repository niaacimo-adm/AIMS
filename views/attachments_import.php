<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['excel_file']['error']);
        }

        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($_FILES['excel_file']['type'], $allowedTypes)) {
            throw new Exception("Only .xlsx files are allowed.");
        }

        $inputFileName = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Skip header row
        array_shift($rows);

        $database = new Database();
        $db = $database->getConnection();

        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            try {
                // Map Excel columns to database fields
                $employeeId = $row[0] ?? '';
                $payrollPeriod = $row[1] ?? '';
                $status = $row[2] ?? 'NOT SUBMITTED';
                $submissionDate = !empty($row[3]) ? date('Y-m-d', strtotime($row[3])) : null;
                $remarks = $row[4] ?? '';

                // Validate employee exists
                $checkQuery = "SELECT emp_id FROM employee WHERE id_number = ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bind_param("s", $employeeId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    $emp_id = $employee['emp_id'];

                    // Insert or update record
                    $query = "INSERT INTO attachments_monitoring 
                             (emp_id, payroll_period, status, submission_date, remarks) 
                             VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                             status = VALUES(status), 
                             submission_date = VALUES(submission_date), 
                             remarks = VALUES(remarks),
                             updated_at = NOW()";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("issss", $emp_id, $payrollPeriod, $status, $submissionDate, $remarks);
                    
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
            }
        }

        $_SESSION['toast'] = [
            'type' => $errorCount > 0 ? 'warning' : 'success',
            'message' => "Import completed: $successCount successful, $errorCount failed"
        ];

    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Import failed: " . $e->getMessage()
        ];
    }
    
    header("Location: attachments_monitoring.php");
    exit();
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
            // Delete all records
            $query = "DELETE FROM attachments_monitoring";
            $stmt = $db->prepare($query);
        } else {
            // Delete specific records
            $recordIds = $_POST['record_ids'] ?? [];
            if (empty($recordIds)) {
                throw new Exception("No records selected for deletion.");
            }
            
            $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
            $query = "DELETE FROM attachments_monitoring WHERE monitoring_id IN ($placeholders)";
            $stmt = $db->prepare($query);
            
            // Bind parameters
            $types = str_repeat('i', count($recordIds));
            $stmt->bind_param($types, ...$recordIds);
        }
        
        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Records deleted successfully!'
            ];
        } else {
            throw new Exception("Failed to delete records.");
        }
        
    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Delete failed: " . $e->getMessage()
        ];
    }
    
    header("Location: attachments_monitoring.php");
    exit();
}
?>