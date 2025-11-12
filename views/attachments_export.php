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
    $exportPeriod = $_GET['export_period'] ?? null;
    $exportAll = $_GET['export_all'] ?? null;
    
    // Build query based on period filter
    $query = "SELECT 
        am.*,
        e.id_number,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name
    FROM attachments_monitoring am
    LEFT JOIN employee e ON am.emp_id = e.emp_id";
    
    if ($exportPeriod && $exportPeriod !== 'all') {
        $query .= " WHERE am.payroll_period = ?";
    }
    
    $query .= " ORDER BY am.payroll_period DESC, e.last_name, e.first_name";
    
    $stmt = $db->prepare($query);
    
    if ($exportPeriod && $exportPeriod !== 'all') {
        $stmt->bind_param("s", $exportPeriod);
    }
    
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Load the template file
    $templatePath = "../public/templates/ATTACHMENTS-MONITORING-SHEET.xlsx";
    
    if (!file_exists($templatePath)) {
        throw new Exception("Template file not found: " . $templatePath);
    }

    // Load the template
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Convert payroll period to "November 1-15, 2025" format
    if ($exportPeriod && $exportPeriod !== 'all') {
        $formattedPeriod = formatPayrollPeriod($exportPeriod);
        $sheet->setCellValue('C6', $formattedPeriod);
    } else {
        $currentMonth = date('F');
        $currentYear = date('Y');
        $sheet->setCellValue('C6', 'All Records - ' . $currentMonth . ' ' . $currentYear);
    }

    // Get the highest row in the template to understand its structure
    $highestRow = $sheet->getHighestRow();
    
    // Clear only the data content from rows 8 onwards but keep all formatting
    for ($row = 8; $row <= $highestRow; $row++) {
        // Only clear cells if they might contain data (not headers or titles)
        if ($row >= 8) {
            $sheet->setCellValue('A' . $row, '');
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('C' . $row, '');
            $sheet->setCellValue('D' . $row, '');
            $sheet->setCellValue('E' . $row, '');
            $sheet->setCellValue('F' . $row, '');
        }
    }

    // Fill data starting from row 8
    $currentRow = 8;
    $recordNumber = 1;
    
    foreach ($records as $record) {
        // Stop if we exceed a reasonable number of rows
        if ($currentRow > 100) break;
        
        // If we need more rows than exist in template, insert new ones with proper formatting
        if ($currentRow > $highestRow) {
            $sheet->insertNewRowBefore($currentRow, 1);
            
            // Copy formatting from the previous row
            for ($col = 1; $col <= 6; $col++) {
                $colLetter = chr(64 + $col);
                $sourceCell = $colLetter . ($currentRow - 1);
                $targetCell = $colLetter . $currentRow;
                
                // Copy style
                $sheet->getStyle($targetCell)->applyFromArray(
                    $sheet->getStyle($sourceCell)->exportArray()
                );
            }
        }
        
        $sheet->setCellValue('A' . $currentRow, $recordNumber);
        $sheet->setCellValue('B' . $currentRow, $record['employee_name']);
        $sheet->setCellValue('C' . $currentRow, $record['status']);
        $sheet->setCellValue('D' . $currentRow, $record['remarks']);
        $sheet->setCellValue('E' . $currentRow, $record['filing_status']);
        
        if (!empty($record['submission_date']) && $record['submission_date'] != '0000-00-00') {
            $sheet->setCellValue('F' . $currentRow, $record['submission_date']);
        }
        
        $currentRow++;
        $recordNumber++;
    }

    // Create filename
    if ($exportPeriod) {
        $cleanPeriod = preg_replace('/[^a-zA-Z0-9-_]/', '_', $exportPeriod);
        $filename = "ATTACHMENTS-MONITORING-SHEET_" . $cleanPeriod . "_" . date('Y-m-d') . ".xlsx";
    } else if ($exportAll) {
        $filename = "ATTACHMENTS-MONITORING-SHEET_ALL_RECORDS_" . date('Y-m-d') . ".xlsx";
    } else {
        $filename = "ATTACHMENTS-MONITORING-SHEET_" . date('Y-m-d') . ".xlsx";
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

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

/**
 * Convert payroll period to "Month Day-Day, Year" format
 * Examples:
 * "Nov 1 - Nov 15" becomes "November 1-15, 2025"
 * "Jan 1 - Jan 15" becomes "January 1-15, 2025"
 * "Nov 1 - Nov 15, 2025" becomes "November 1-15, 2025"
 */
function formatPayrollPeriod($period) {
    // Pattern for "Nov 1 - Nov 15" or "Nov 1 - Nov 15, 2025"
    if (preg_match('/([A-Za-z]{3})\s+(\d+)\s*-\s*[A-Za-z]{3}\s+(\d+)(?:,\s*(\d{4}))?/', $period, $matches)) {
        $monthAbbr = ucfirst(strtolower($matches[1]));
        $startDay = $matches[2];
        $endDay = $matches[3];
        $year = isset($matches[4]) ? $matches[4] : date('Y');
        
        // Convert month abbreviation to full month name
        $monthNames = [
            'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March',
            'Apr' => 'April', 'May' => 'May', 'Jun' => 'June',
            'Jul' => 'July', 'Aug' => 'August', 'Sep' => 'September',
            'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December'
        ];
        
        $fullMonth = $monthNames[$monthAbbr] ?? $monthAbbr;
        
        return $fullMonth . ' ' . $startDay . '-' . $endDay . ', ' . $year;
    }
    
    // Pattern for "Month Year" format (convert to "Month 1-30, Year")
    if (preg_match('/([A-Za-z]+)\s+(\d{4})/', $period, $matches)) {
        $month = $matches[1];
        $year = $matches[2];
        
        // Set default day range based on month
        $dayRanges = [
            'January' => '1-31', 'February' => '1-28', 'March' => '1-31',
            'April' => '1-30', 'May' => '1-31', 'June' => '1-30',
            'July' => '1-31', 'August' => '1-31', 'September' => '1-30',
            'October' => '1-31', 'November' => '1-30', 'December' => '1-31'
        ];
        
        $dayRange = $dayRanges[$month] ?? '1-30';
        
        // Adjust February for leap year
        if ($month === 'February' && date('L', strtotime("$year-01-01"))) {
            $dayRange = '1-29';
        }
        
        return $month . ' ' . $dayRange . ', ' . $year;
    }
    
    // Return original if we can't parse it
    return $period;
}
?>