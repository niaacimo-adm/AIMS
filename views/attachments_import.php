<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'type' => 'error',
        'message' => '',
        'details' => ''
    ];

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

        // Validate header row matches template format
        $expectedHeaders = ['NO.', 'NAMES', 'STATUS', 'REMARKS', 'DATE', 'PAYROLL PERIOD'];
        $actualHeaders = array_slice($rows[0], 0, 6);

        // Check if headers match (case insensitive)
        $headerMatch = true;
        $headerErrors = [];
        for ($i = 0; $i < count($expectedHeaders); $i++) {
            $expected = strtolower(trim($expectedHeaders[$i]));
            $actual = strtolower(trim($actualHeaders[$i] ?? ''));
            if ($actual !== $expected) {
                $headerMatch = false;
                $headerErrors[] = "Column " . ($i + 1) . ": Expected '$expectedHeaders[$i]', found '" . ($actualHeaders[$i] ?? 'Empty') . "'";
            }
        }

        if (!$headerMatch) {
            $errorMessage = "Invalid file format. Please download and use the template file.";
            $errorMessage .= "\n\nColumn errors:\n" . implode("\n", $headerErrors);
            throw new Exception($errorMessage);
        }

        // Skip header row
        array_shift($rows);

        $database = new Database();
        $db = $database->getConnection();

        $successCount = 0;
        $errorCount = 0;
        $errorDetails = [];

        foreach ($rows as $rowIndex => $row) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $employeeName = $row[1] ?? '';
                $status = $row[2] ?? 'NOT SUBMITTED';
                $remarks = $row[3] ?? '';
                $submissionDate = !empty($row[4]) ? date('Y-m-d', strtotime($row[4])) : null;
                $payrollPeriod = $row[5] ?? '';
                $filingStatus = 'NOT FORWARDED'; // Default value since it's not in the template

                // Validate required fields
                if (empty($employeeName)) {
                    throw new Exception("Employee name is required");
                }

                if (empty($payrollPeriod)) {
                    throw new Exception("Payroll Period is required");
                }

                // Validate status values
                $validStatuses = ['NO ATTACHMENTS', 'COMPLETE', 'COMPLETE AND LATE', 'LACKING INFORMATION', 'FOR REVIEW'];
                if (!in_array($status, $validStatuses)) {
                    throw new Exception("Invalid status '$status'. Must be one of: " . implode(', ', $validStatuses));
                }

                // Look up employee by name - IMPROVED VERSION
                $checkQuery = "SELECT emp_id, id_number, first_name, last_name 
                               FROM employee 
                               WHERE CONCAT(first_name, ' ', last_name) = ?
                                  OR last_name = ?
                               LIMIT 1";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bind_param("ss", $employeeName, $employeeName);
                $checkStmt->execute();
                $result = $checkStmt->get_result();

                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    $emp_id = $employee['emp_id'];
                    $idNumber = $employee['id_number'];

                    // Insert or update record
                    $query = "INSERT INTO attachments_monitoring 
                             (emp_id, payroll_period, status, filing_status, submission_date, remarks) 
                             VALUES (?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                             status = VALUES(status), 
                             filing_status = VALUES(filing_status),
                             submission_date = VALUES(submission_date), 
                             remarks = VALUES(remarks),
                             updated_at = NOW()";

                    $stmt = $db->prepare($query);
                    $stmt->bind_param("isssss", $emp_id, $payrollPeriod, $status, $filingStatus, $submissionDate, $remarks);
                    
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        throw new Exception("Database error: " . $stmt->error);
                    }
                } else {
                    // If exact match fails, try a more flexible search
                    $flexibleQuery = "SELECT emp_id, id_number, first_name, last_name 
                                     FROM employee 
                                     WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
                                        OR last_name LIKE ?
                                     LIMIT 1";
                    $flexibleStmt = $db->prepare($flexibleQuery);
                    $searchPattern = '%' . $employeeName . '%';
                    $flexibleStmt->bind_param("ss", $searchPattern, $searchPattern);
                    $flexibleStmt->execute();
                    $flexibleResult = $flexibleStmt->get_result();
                    
                    if ($flexibleResult->num_rows > 0) {
                        $employee = $flexibleResult->fetch_assoc();
                        $emp_id = $employee['emp_id'];
                        
                        // Use the same insert/update logic as above
                        $query = "INSERT INTO attachments_monitoring 
                                 (emp_id, payroll_period, status, filing_status, submission_date, remarks) 
                                 VALUES (?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE 
                                 status = VALUES(status), 
                                 filing_status = VALUES(filing_status),
                                 submission_date = VALUES(submission_date), 
                                 remarks = VALUES(remarks),
                                 updated_at = NOW()";

                        $stmt = $db->prepare($query);
                        $stmt->bind_param("isssss", $emp_id, $payrollPeriod, $status, $filingStatus, $submissionDate, $remarks);
                        
                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            throw new Exception("Database error: " . $stmt->error);
                        }
                    } else {
                        throw new Exception("Employee '$employeeName' not found in database");
                    }
                }
            } catch (Exception $e) {
                $errorCount++;
                $errorDetails[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
            }
        }

        // Prepare response
        if ($successCount > 0 && $errorCount === 0) {
            $response['success'] = true;
            $response['type'] = 'success';
            $response['message'] = "Import completed successfully!";
            $response['details'] = "$successCount records imported";
        } elseif ($successCount > 0 && $errorCount > 0) {
            $response['success'] = true;
            $response['type'] = 'warning';
            $response['message'] = "Import completed with some errors";
            $response['details'] = "$successCount records imported, $errorCount failed";
            if (count($errorDetails) > 0) {
                $response['details'] .= "\n\nFirst few errors:\n" . implode("\n", array_slice($errorDetails, 0, 3));
                if (count($errorDetails) > 3) {
                    $response['details'] .= "\n... and " . (count($errorDetails) - 3) . " more errors";
                }
            }
        } else {
            $response['message'] = "Import failed";
            $response['details'] = "No records were imported. All $errorCount rows had errors";
            if (count($errorDetails) > 0) {
                $response['details'] .= "\n\nFirst few errors:\n" . implode("\n", array_slice($errorDetails, 0, 3));
            }
        }

    } catch (Exception $e) {
        $response['message'] = "Import failed";
        $response['details'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Handle bulk delete (keep existing functionality)
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