<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if specific period is requested
    $selectedPeriod = $_GET['period'] ?? null;
    
    // Build query based on period filter
    $query = "SELECT 
        am.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.id_number
    FROM attachments_monitoring am
    LEFT JOIN employee e ON am.emp_id = e.emp_id";
    
    // Add WHERE clause if period is specified
    if ($selectedPeriod) {
        $query .= " WHERE am.payroll_period = ?";
    }
    
    $query .= " ORDER BY am.payroll_period DESC, e.last_name, e.first_name";
    
    $stmt = $db->prepare($query);
    
    if ($selectedPeriod) {
        $stmt->bind_param("s", $selectedPeriod);
    }
    
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Load the existing template file
    $templatePath = "../public/templates/ATTACHMENTS-MONITORING-SHEET.xlsx";
    
    if (!file_exists($templatePath)) {
        throw new Exception("Template file not found: " . $templatePath);
    }

    // Load the template - this preserves all existing formatting
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Simply add data starting from row 7 without clearing anything
    $row = 7;
    $number = 1;
    
    foreach ($records as $record) {
        $sheet->setCellValue('A' . $row, $number); // NO.
        $sheet->setCellValue('B' . $row, $record['employee_name']); // NAMES
        $sheet->setCellValue('C' . $row, $record['status']); // STATUS
        $sheet->setCellValue('D' . $row, $record['remarks']); // REMARKS
        $sheet->setCellValue('E' . $row, $record['submission_date']); // DATE
        $sheet->setCellValue('F' . $row, $record['payroll_period']); // PAYROLL PERIOD
        
        $row++;
        $number++;
        
        // Optional: Limit to 50 records as per template structure
        if ($number > 50) {
            break;
        }
    }

    // Create filename based on period
    $filename = "ATTACHMENTS_MONITORING_SHEET";
    if ($selectedPeriod) {
        // Clean the period for filename
        $cleanPeriod = preg_replace('/[^a-zA-Z0-9-_]/', '_', $selectedPeriod);
        $filename .= "_" . $cleanPeriod;
    }
    $filename .= "_" . date('Y-m-d') . ".xlsx";

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => "Export failed: " . $e->getMessage()
    ];
    header("Location: attachments_monitoring.php");
    exit();
}
?>