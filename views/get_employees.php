<?php
session_start();
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

// Initialize response
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Test if database class exists
    if (!class_exists('Database')) {
        throw new Exception("Database class not found");
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Test connection
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Debug: Log received parameters
    error_log("=== GET_EMPLOYEES.PHP STARTED ===");
    error_log("Received POST data: " . print_r($_POST, true));
    error_log("Session ID: " . session_id());
    
    $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : null;
    $unit_id = isset($_POST['unit_id']) ? $_POST['unit_id'] : null;
    
    // Validate parameters
    if ($section_id === '' || $section_id === 'null' || $section_id === null) {
        $section_id = null;
    } elseif (is_numeric($section_id)) {
        $section_id = intval($section_id);
    } else {
        throw new Exception("Invalid section_id: " . $section_id);
    }
    
    if ($unit_id === '' || $unit_id === 'null' || $unit_id === null) {
        $unit_id = null;
    } elseif (is_numeric($unit_id)) {
        $unit_id = intval($unit_id);
    } else {
        throw new Exception("Invalid unit_id: " . $unit_id);
    }
    
    // Debug log
    error_log("Processed params - section_id: " . ($section_id ?? 'null') . ", unit_id: " . ($unit_id ?? 'null'));
    
    // ==================== CRITICAL DEBUGGING ====================
    // Check if section exists
    if ($section_id !== null) {
        $checkSectionQuery = "SELECT section_name FROM section WHERE section_id = ?";
        $checkSectionStmt = $db->prepare($checkSectionQuery);
        $checkSectionStmt->bind_param('i', $section_id);
        $checkSectionStmt->execute();
        $sectionResult = $checkSectionStmt->get_result();
        
        if ($sectionResult->num_rows > 0) {
            $sectionData = $sectionResult->fetch_assoc();
            error_log("Section found: " . $sectionData['section_name']);
        } else {
            error_log("WARNING: Section ID {$section_id} not found in database!");
        }
        $checkSectionStmt->close();
        
        // Check total employees in this section
        $countQuery = "SELECT COUNT(*) as total FROM employee WHERE section_id = ?";
        $countStmt = $db->prepare($countQuery);
        $countStmt->bind_param('i', $section_id);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countData = $countResult->fetch_assoc();
        error_log("Total employees in section {$section_id}: " . $countData['total']);
        $countStmt->close();
    }
    // ============================================================
    
    // Build query
    $query = "SELECT e.*, 
                     p.position_name,
                     es.status_name,
                     s.section_name,
                     us.unit_name,
                     o.office_name
              FROM employee e
              LEFT JOIN position p ON e.position_id = p.position_id
              LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
              LEFT JOIN section s ON e.section_id = s.section_id
              LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
              LEFT JOIN office o ON e.office_id = o.office_id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($section_id !== null) {
        $query .= " AND e.section_id = ?";
        $params[] = $section_id;
        $types .= 'i';
    }
    
    if ($unit_id !== null) {
        $query .= " AND e.unit_section_id = ?";
        $params[] = $unit_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY e.first_name, e.last_name";
    
    error_log("Final query: " . $query);
    error_log("Params: " . print_r($params, true));
    error_log("Params types: " . $types);
    
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        $error = $db->error;
        error_log("Prepare error: " . $error);
        throw new Exception("Prepare failed: " . $error);
    }
    
    if (!empty($params)) {
        // Debug binding
        error_log("Binding params: types='$types', values=" . implode(', ', $params));
        
        // Special handling for binding
        if (count($params) === 1) {
            $stmt->bind_param($types, $params[0]);
        } elseif (count($params) === 2) {
            $stmt->bind_param($types, $params[0], $params[1]);
        }
    }
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        error_log("Execute error: " . $error);
        throw new Exception("Execute failed: " . $error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        $error = $stmt->error;
        error_log("Get result error: " . $error);
        throw new Exception("Get result failed: " . $error);
    }
    
    $employees = [];
    $rowCount = 0;
    while ($row = $result->fetch_assoc()) {
        $rowCount++;
        // Handle null dates
        if (isset($row['bday']) && $row['bday'] == '0000-00-00') {
            $row['bday'] = null;
        }
        $employees[] = $row;
        
        // Log first few rows for debugging
        if ($rowCount <= 3) {
            error_log("Row {$rowCount}: " . json_encode([
                'emp_id' => $row['emp_id'] ?? 'N/A',
                'first_name' => $row['first_name'] ?? 'N/A',
                'last_name' => $row['last_name'] ?? 'N/A',
                'section_id' => $row['section_id'] ?? 'N/A',
                'section_name' => $row['section_name'] ?? 'N/A'
            ]));
        }
    }
    
    $stmt->close();
    
    error_log("Found " . count($employees) . " employees (rowCount: {$rowCount})");
    
    // ALWAYS return valid JSON even if no employees
    $response = [
        'success' => true,
        'employees' => $employees,
        'count' => count($employees),
        'debug_info' => [
            'section_id' => $section_id,
            'unit_id' => $unit_id,
            'employee_count' => count($employees),
            'has_employees' => count($employees) > 0,
            'query_executed' => true,
            'rows_processed' => $rowCount
        ]
    ];
    
    error_log("=== GET_EMPLOYEES.PHP COMPLETED SUCCESSFULLY ===");

} catch (Exception $e) {
    error_log("=== GET_EMPLOYEES.PHP ERROR ===");
    error_log("Error message: " . $e->getMessage());
    error_log("Error file: " . $e->getFile() . ":" . $e->getLine());
    error_log("Error trace: " . $e->getTraceAsString());
    
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
} finally {
    // Get any buffered output
    $output = ob_get_clean();
    
    // If there was output in the buffer, include it in response for debugging
    if (!empty($output)) {
        error_log("Output buffer content: " . $output);
        if (!isset($response['debug_output'])) {
            $response['debug_output'] = $output;
        }
    }
    
    // Clear any remaining output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set header FIRST
    header('Content-Type: application/json; charset=utf-8');
    
    // Output JSON - use JSON_PARTIAL_OUTPUT_ON_ERROR to see what's wrong
    $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($jsonOutput === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        // Fallback response
        echo json_encode([
            'success' => false,
            'message' => 'JSON encoding error',
            'json_error' => json_last_error_msg()
        ]);
    } else {
        echo $jsonOutput;
    }
    
    exit();
}