<?php
require 'vendor/autoload.php';

// Test if ZipArchive is available
if (!class_exists('ZipArchive')) {
    die("ERROR: ZipArchive not enabled. Edit php.ini and enable extension=zip.");
}

// Test Excel file creation
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Test Passed!');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('test_output.xlsx');

echo "Success! Check 'test_output.xlsx' in your project root.";