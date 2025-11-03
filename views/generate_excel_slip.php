<?php
// generate_excel_slip.php
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // For PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (isset($_GET['id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get slip data
    $query = "SELECT pls.*, e.first_name, e.last_name, e.middle_name, e.ext_name,
                     s.section_name, s.section_code, s.section_id,
                     app.first_name as approver_first, app.last_name as approver_last
              FROM personal_locator_slips pls
              JOIN employee e ON pls.employee_id = e.emp_id
              LEFT JOIN section s ON e.section_id = s.section_id
              LEFT JOIN employee app ON pls.approved_by = app.emp_id
              WHERE pls.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $slip = $result->fetch_assoc();
        
        // Load the template
        $templatePath = '../public/templates/PERSONNAL_LOCATOR_SLIP.xlsx';
        
        if (file_exists($templatePath)) {
            $spreadsheet = IOFactory::load($templatePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Fill in the data based on the Excel template structure
            // Date (Cell L6)
            $worksheet->setCellValue('L6', date('m/d/Y', strtotime($slip['date'])));
            
            // Leave time (Cells K10 and L10)
            $leaveTime = date('g:i', strtotime($slip['leave_time']));
            $worksheet->setCellValue('K10', $leaveTime);
            $worksheet->setCellValue('L10', date('A', strtotime($slip['leave_time'])) == 'AM' ? 'AM' : 'PM');
            
            // Purpose details (Cell B12)
            $purposeText = ($slip['purpose_type'] == 'personal' ? 'Personal' : 'Official') . ': ' . $slip['purpose_details'];
            $worksheet->setCellValue('B12', $purposeText);
            
            // Return information - Check appropriate boxes
            if ($slip['no_return']) {
                // Check "I don't expect to return" box (Cell C16)
                $worksheet->setCellValue('C16', '✓');
            } else {
                // Check "I expect to return" box (Cell C15)
                $worksheet->setCellValue('C15', '✓');
                $returnTime = date('g:i', strtotime($slip['expected_return']));
                $worksheet->setCellValue('H15', $returnTime);
                $worksheet->setCellValue('I15', date('A', strtotime($slip['expected_return'])) == 'AM' ? 'AM' : 'PM');
            }
            
            // Employee signature (Cell I18)
            $employeeName = $slip['first_name'] . ' ' . 
                           ($slip['middle_name'] ? substr($slip['middle_name'], 0, 1) . '. ' : '') . 
                           $slip['last_name'] . 
                           ($slip['ext_name'] ? ' ' . $slip['ext_name'] : '');
            $worksheet->setCellValue('I18', strtoupper($employeeName));
            $worksheet->setCellValue('H10', date('m/d/Y'));

            // Section head information (Cells C22 and C23) - CORRECTED
            $sectionHeadName = '';
            if ($slip['section_id']) {
                $headQuery = "SELECT e.first_name, e.last_name, e.middle_name, e.ext_name
                              FROM section s 
                              JOIN employee e ON s.head_emp_id = e.emp_id 
                              WHERE s.section_id = ?";
                $headStmt = $db->prepare($headQuery);
                $headStmt->bind_param("i", $slip['section_id']);
                $headStmt->execute();
                $headResult = $headStmt->get_result();
                
                if ($headResult->num_rows > 0) {
                    $sectionHead = $headResult->fetch_assoc();
                    $sectionHeadName = $sectionHead['first_name'] . ' ' . 
                                      ($sectionHead['middle_name'] ? substr($sectionHead['middle_name'], 0, 1) . '. ' : '') . 
                                      $sectionHead['last_name'] . 
                                      ($sectionHead['ext_name'] ? ' ' . $sectionHead['ext_name'] : '');
                }
            }

            // CORRECTED: C22 = Section head name, C23 = Section name
            $worksheet->setCellValue('C22', strtoupper($sectionHeadName )); // Section head name
            $worksheet->setCellValue('B23', 'Acting Section Head: '. $slip['section_name'] ?? ''); // Section name
            
            // Also update the second form (C62 and C63)
            $worksheet->setCellValue('C62', strtoupper($sectionHeadName)); // Section head name in second form
            $worksheet->setCellValue('C63', $slip['section_name'] ?? ''); // Section name in second form
            
            // If approved, fill in the authorized section
            if ($slip['status'] == 'approved' && $slip['approved_by']) {
                $approverName = $slip['approver_first'] . ' ' . $slip['approver_last'];
                // You might want to add the approver's name in the authorized section
                // This depends on your template structure
            }
            
            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Personal_Locator_Slip_' . $slip['id'] . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');
            
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;
            
        } else {
            // Fallback: Create new Excel file if template doesn't exist
            createFallbackExcel($slip);
        }
    }
}

// If no valid ID or error
header('Location: personal_locator_slip.php');
exit;

function createFallbackExcel($slip) {
    $database = new Database();
    $db = $database->getConnection();
    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Create basic structure similar to template
    $worksheet->mergeCells('E1:H4');
    $worksheet->setCellValue('E1', "Republic of the Philippines\nOFFICE OF THE PRESIDENT\nNATIONAL IRRIGATION ADMINISTRATION\nALBAY-CATANDUANES IRRIGATION MANAGEMENT OFFICE");
    $worksheet->getStyle('E1')->getAlignment()->setWrapText(true);
    
    $worksheet->setCellValue('L6', date('m/d/Y', strtotime($slip['date'])));
    $worksheet->setCellValue('E8', 'PERSONNEL LOCATOR SLIP');
    
    // Fill data for first form
    $worksheet->setCellValue('B10', 'Undersigned hereby request permission to leave this office on');
    $worksheet->setCellValue('K10', date('g:i', strtotime($slip['leave_time'])));
    $worksheet->setCellValue('L10', date('A', strtotime($slip['leave_time'])) == 'AM' ? 'AM' : 'PM');
    
    $purposeText = ($slip['purpose_type'] == 'personal' ? 'Personal' : 'Official') . ': ' . $slip['purpose_details'];
    $worksheet->setCellValue('B12', $purposeText);
    
    if ($slip['no_return']) {
        $worksheet->setCellValue('C16', '✓');
    } else {
        $worksheet->setCellValue('C15', '✓');
        $worksheet->setCellValue('H15', date('g:i', strtotime($slip['expected_return'])));
        $worksheet->setCellValue('I15', date('A', strtotime($slip['expected_return'])) == 'AM' ? 'AM' : 'PM');
    }
    
    $employeeName = $slip['first_name'] . ' ' . 
                   ($slip['middle_name'] ? substr($slip['middle_name'], 0, 1) . '. ' : '') . 
                   $slip['last_name'] . 
                   ($slip['ext_name'] ? ' ' . $slip['ext_name'] : '');
    $worksheet->setCellValue('I18', strtoupper($employeeName));
    
// Section head information (Cells C22 and C23) - CORRECTED
$sectionHeadName = '';
if ($slip['section_id']) {
    $headQuery = "SELECT e.first_name, e.last_name, e.middle_name, e.ext_name
                  FROM section s 
                  JOIN employee e ON s.head_emp_id = e.emp_id 
                  WHERE s.section_id = ?";
    $headStmt = $db->prepare($headQuery);
    $headStmt->bind_param("i", $slip['section_id']);
    $headStmt->execute();
    $headResult = $headStmt->get_result();
    
    if ($headResult->num_rows > 0) {
        $sectionHead = $headResult->fetch_assoc();
        $sectionHeadName = $sectionHead['first_name'] . ' ' . 
                          ($sectionHead['middle_name'] ? substr($sectionHead['middle_name'], 0, 1) . '. ' : '') . 
                          $sectionHead['last_name'] . 
                          ($sectionHead['ext_name'] ? ' ' . $sectionHead['ext_name'] : '');
    }
}

// CORRECTED: C22 = Section head name, C23 = Section name
$worksheet->setCellValue('C22', strtoupper($sectionHeadName)); // Section head name
$worksheet->setCellValue('C23', $slip['section_name'] ?? ''); // Section name
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Personal_Locator_Slip_' . $slip['id'] . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>