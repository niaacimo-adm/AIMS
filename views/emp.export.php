<?php
require_once '../config/database.php';
require '../vendor/autoload.php';

$mode = $_GET['mode'] ?? 'clean';
// Fetch data with names only (no IDs)
$database = new Database();
$db = $database->getConnection();

$query = "SELECT 
            e.id_number,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.ext_name,
            e.gender,
            e.address,
            e.bday,
            e.email,
            e.phone_number,
            es.status_name as employment_status,
            ap.status_name as appointment_status,
            s.section_name,
            o.office_name,
            p.position_name
          FROM employee e
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN position p ON e.position_id = p.position_id
          ORDER BY e.emp_id DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// Create Spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers (names only)
$headers = [
    'ID Number',
    'First Name',
    'Middle Name', 
    'Last Name',
    'Extension Name',
    'Gender',
    'Address',
    'Birthday',
    'Email',
    'Phone Number',
    'Employment Status',  
    'Appointment Status', 
    'Section',           
    'Office',            
    'Position'           
];
$sheet->fromArray($headers, NULL, 'A1');

// Fill data (names only)
$rowNumber = 2;
foreach ($employees as $emp) {
    $sheet->fromArray([
        $emp['id_number'],
        $emp['first_name'],
        $emp['middle_name'],
        $emp['last_name'],
        $emp['ext_name'],
        $emp['gender'],
        $emp['address'],
        $emp['bday'],
        $emp['email'],
        $emp['phone_number'],
        $emp['employment_status'],  
        $emp['appointment_status'], 
        $emp['section_name'],       
        $emp['office_name'],        
        $emp['position_name']       
    ], NULL, 'A' . $rowNumber);
    
    $rowNumber++;
}

// Auto-size columns
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Format birthday
$sheet->getStyle('H2:H' . $rowNumber)
      ->getNumberFormat()
      ->setFormatCode('yyyy-mm-dd');

// Style header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
               'startColor' => ['rgb' => '3498db']]
];
$sheet->getStyle('A1:T1')->applyFromArray($headerStyle);

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="employees_clean_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;