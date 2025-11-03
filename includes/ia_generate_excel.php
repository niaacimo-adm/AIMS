<?php
require_once '../vendor/autoload.php'; // For PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelReportGenerator {
    private $db;
    private $templatePath;

    public function __construct($db, $templatePath = null) {
        $this->db = $db;
        $this->templatePath = $templatePath;
    }

    /**
     * Generate IA Profiles Report based on filters
     */
    public function generateIAProfilesReport($filters = []) {
        try {
            // Load template or create new spreadsheet
            $spreadsheet = null;
            
            if ($this->templatePath && file_exists($this->templatePath)) {
                try {
                    $spreadsheet = IOFactory::load($this->templatePath);
                    error_log("Template loaded successfully from: " . $this->templatePath);
                } catch (Exception $e) {
                    error_log("Template loading failed: " . $e->getMessage());
                    $spreadsheet = new Spreadsheet();
                    $this->createBasicTemplate($spreadsheet);
                }
            } else {
                error_log("Template not found at: " . $this->templatePath);
                $spreadsheet = new Spreadsheet();
                $this->createBasicTemplate($spreadsheet);
            }

            // Get filtered data
            $iaProfiles = $this->getFilteredIAProfiles($filters);

            // Fill data into template
            $this->fillTemplateWithData($spreadsheet, $iaProfiles, $filters);

            return $spreadsheet;

        } catch (Exception $e) {
            throw new Exception("Error generating report: " . $e->getMessage());
        }
    }
    
    /**
     * Get filtered IA profiles from database
     */
    private function getFilteredIAProfiles($filters) {
        $query = "SELECT * FROM ia_profiles WHERE 1=1";
        $params = [];
        $types = '';

        // Apply filters
        if (!empty($filters['congressional_district'])) {
            $query .= " AND congressional_district = ?";
            $params[] = $filters['congressional_district'];
            $types .= 's';
        }

        if (!empty($filters['region'])) {
            $query .= " AND region = ?";
            $params[] = $filters['region'];
            $types .= 's';
        }

        if (!empty($filters['province'])) {
            $query .= " AND province = ?";
            $params[] = $filters['province'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        // Date organized range
        if (!empty($filters['date_organized_from'])) {
            $query .= " AND date_organized >= ?";
            $params[] = $filters['date_organized_from'];
            $types .= 's';
        }

        if (!empty($filters['date_organized_to'])) {
            $query .= " AND date_organized <= ?";
            $params[] = $filters['date_organized_to'];
            $types .= 's';
        }

        $query .= " ORDER BY congressional_district, ia_name";

        $stmt = $this->db->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }

        return $profiles;
    }

    /**
     * Fill template with IA profile data
     */
    private function fillTemplateWithData($spreadsheet, $iaProfiles, $filters) {
        // Set basic information
        $currentSheet = $spreadsheet->getActiveSheet();
        
        // Set report date
        $monthYear = !empty($filters['report_period']) ? 
            date('F Y', strtotime($filters['report_period'] . '-01')) : 
            date('F Y');
        $currentSheet->setCellValue('A2', 'NIS IA PROFILE as of : ' . $monthYear);

        // Set region if provided
        if (!empty($filters['region'])) {
            $currentSheet->setCellValue('A3', 'Region: ' . $filters['region']);
        }

        // Group by congressional district
        $groupedProfiles = [];
        foreach ($iaProfiles as $profile) {
            $district = $profile['congressional_district'] ?? 'Unknown District';
            $groupedProfiles[$district][] = $profile;
        }

        $currentRow = 8; // Starting row for data

        foreach ($groupedProfiles as $district => $profiles) {
            // Add district header
            $currentSheet->setCellValue('A' . $currentRow, 'Congressional District: ' . $district);
            $currentSheet->mergeCells('A' . $currentRow . ':R' . $currentRow);
            $currentSheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
            $currentRow++;

            // Add column headers
            $headers = ['A' => 'Name of IA', 'B' => 'Mailing Address', 'C' => 'Name of President', 
                       'D' => 'Contact Number', 'E' => 'Date Organized', 'F' => 'Date of SEC Registration',
                       'G' => 'SEC Registration Number', 'H' => 'IA TIN', 'I' => 'Service Area (ha)',
                       'J' => 'FUSA', 'K' => 'Farmer - Beneficiaries (No.)', 'L' => 'Actual IA Members (No.)',
                       'M' => 'No. of TSAGs', 'N' => 'Existing O & M or IMT Contract (Specify)',
                       'O' => 'Latest Effectivity of Contract', 'P' => 'Length of Canal Under Contract (km)',
                       'Q' => 'Gender - M', 'R' => 'Gender - F'];
            
            foreach ($headers as $col => $header) {
                $currentSheet->setCellValue($col . $currentRow, $header);
            }
            $currentSheet->getStyle('A' . $currentRow . ':R' . $currentRow)->getFont()->setBold(true);
            $currentRow++;

            // Add data rows for this district
            foreach ($profiles as $profile) {
                $currentSheet->setCellValue('A' . $currentRow, $profile['ia_name']);
                $currentSheet->setCellValue('B' . $currentRow, $profile['mailing_address']);
                $currentSheet->setCellValue('C' . $currentRow, $profile['president_name']);
                $currentSheet->setCellValue('D' . $currentRow, $profile['contact_number']);
                $currentSheet->setCellValue('E' . $currentRow, $profile['date_organized']);
                $currentSheet->setCellValue('F' . $currentRow, $profile['sec_registration_date']);
                $currentSheet->setCellValue('G' . $currentRow, $profile['sec_registration_number']);
                $currentSheet->setCellValue('H' . $currentRow, $profile['ia_tin']);
                $currentSheet->setCellValue('I' . $currentRow, $profile['service_area_ha']);
                $currentSheet->setCellValue('J' . $currentRow, $profile['fusa_ha']);
                $currentSheet->setCellValue('K' . $currentRow, $profile['farmer_beneficiaries']);
                $currentSheet->setCellValue('L' . $currentRow, $profile['actual_ia_members']);
                $currentSheet->setCellValue('M' . $currentRow, $profile['tsags_count']);
                $currentSheet->setCellValue('N' . $currentRow, $profile['existing_contract']);
                $currentSheet->setCellValue('O' . $currentRow, $profile['contract_effectivity_date']);
                $currentSheet->setCellValue('P' . $currentRow, $profile['canal_length_km']);
                $currentSheet->setCellValue('Q' . $currentRow, $profile['male_members']);
                $currentSheet->setCellValue('R' . $currentRow, $profile['female_members']);
                
                $currentRow++;
            }

            // Add subtotal row for the district
            $startRow = $currentRow - count($profiles);
            $endRow = $currentRow - 1;
            
            $currentSheet->setCellValue('A' . $currentRow, 'Sub-total ' . $district);
            $currentSheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
            $currentSheet->setCellValue('I' . $currentRow, "=SUM(I{$startRow}:I{$endRow})");
            $currentSheet->setCellValue('J' . $currentRow, "=SUM(J{$startRow}:J{$endRow})");
            $currentSheet->setCellValue('K' . $currentRow, "=SUM(K{$startRow}:K{$endRow})");
            $currentSheet->setCellValue('L' . $currentRow, "=SUM(L{$startRow}:L{$endRow})");
            $currentSheet->setCellValue('M' . $currentRow, "=SUM(M{$startRow}:M{$endRow})");
            $currentSheet->setCellValue('P' . $currentRow, "=SUM(P{$startRow}:P{$endRow})");
            $currentSheet->setCellValue('Q' . $currentRow, "=SUM(Q{$startRow}:Q{$endRow})");
            $currentSheet->setCellValue('R' . $currentRow, "=SUM(R{$startRow}:R{$endRow})");
            
            $currentRow += 2; // Add space before next district
        }

        // Add grand totals if we have data
        if ($currentRow > 8) {
            $totalStartRow = 10; // First data row
            $totalEndRow = $currentRow - 3;
            
            $currentSheet->setCellValue('A' . $currentRow, 'Grand Total');
            $currentSheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
            $currentSheet->setCellValue('I' . $currentRow, "=SUM(I{$totalStartRow}:I{$totalEndRow})");
            $currentSheet->setCellValue('J' . $currentRow, "=SUM(J{$totalStartRow}:J{$totalEndRow})");
            $currentSheet->setCellValue('K' . $currentRow, "=SUM(K{$totalStartRow}:K{$totalEndRow})");
            $currentSheet->setCellValue('L' . $currentRow, "=SUM(L{$totalStartRow}:L{$totalEndRow})");
            $currentSheet->setCellValue('M' . $currentRow, "=SUM(M{$totalStartRow}:M{$totalEndRow})");
            $currentSheet->setCellValue('P' . $currentRow, "=SUM(P{$totalStartRow}:P{$totalEndRow})");
            $currentSheet->setCellValue('Q' . $currentRow, "=SUM(Q{$totalStartRow}:Q{$totalEndRow})");
            $currentSheet->setCellValue('R' . $currentRow, "=SUM(R{$totalStartRow}:R{$totalEndRow})");
        }
    }

    /**
     * Create basic template if no template file exists
     */
    private function createBasicTemplate($spreadsheet) {
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', 'NIA REGION V - IRRIGATORS ASSOCIATION PROFILES');
        $sheet->mergeCells('A1:R1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        // Headers
        $headers = [
            'A' => 'Name of IA',
            'B' => 'Mailing Address',
            'C' => 'Name of President',
            'D' => 'Contact Number',
            'E' => 'Date Organized',
            'F' => 'Date of SEC Registration',
            'G' => 'SEC Registration Number',
            'H' => 'IA TIN',
            'I' => 'Service Area (ha)',
            'J' => 'FUSA',
            'K' => 'Farmer - Beneficiaries (No.)',
            'L' => 'Actual IA Members (No.)',
            'M' => 'No. of TSAGs',
            'N' => 'Existing O & M or IMT Contract (Specify)',
            'O' => 'Latest Effectivity of Contract',
            'P' => 'Length of Canal Under Contract (km)',
            'Q' => 'Gender - M',
            'R' => 'Gender - F'
        ];

        // Set headers
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '7', $header);
            $sheet->getStyle($col . '7')->getFont()->setBold(true);
        }

        // Auto-size columns
        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Export spreadsheet to browser for download
     */
    public function exportToBrowser($spreadsheet, $filename) {
        // Clear any previous output
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    // ... rest of the import methods remain the same ...
    /**
     * Import IA profiles from Excel file
     */
    public function importIAProfiles($filePath) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $importedCount = 0;
            $errors = [];

            // Skip headers (assuming headers are in row 7)
            for ($i = 7; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip empty rows
                if (empty($row[0]) || $row[0] == 'Sub-total' || $row[0] == 'Grand Total') {
                    continue;
                }

                // Skip district headers
                if (strpos($row[0], 'Congressional District:') !== false) {
                    continue;
                }

                try {
                    $data = [
                        'ia_name' => $row[0] ?? '',
                        'mailing_address' => $row[1] ?? '',
                        'president_name' => $row[2] ?? '',
                        'contact_number' => $row[3] ?? '',
                        'date_organized' => $this->parseExcelDate($row[4]),
                        'sec_registration_date' => $this->parseExcelDate($row[5]),
                        'sec_registration_number' => $row[6] ?? '',
                        'ia_tin' => $row[7] ?? '',
                        'service_area_ha' => $this->parseFloat($row[8]),
                        'fusa_ha' => $this->parseFloat($row[9]),
                        'farmer_beneficiaries' => intval($row[10] ?? 0),
                        'actual_ia_members' => intval($row[11] ?? 0),
                        'tsags_count' => intval($row[12] ?? 0),
                        'existing_contract' => $row[13] ?? '',
                        'contract_effectivity_date' => $this->parseExcelDate($row[14]),
                        'canal_length_km' => $this->parseFloat($row[15]),
                        'male_members' => intval($row[16] ?? 0),
                        'female_members' => intval($row[17] ?? 0),
                        'status' => 'operational',
                        'created_by' => $_SESSION['emp_id'] ?? 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    // Insert into database
                    $columns = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    
                    $query = "INSERT INTO ia_profiles ($columns) VALUES ($placeholders)";
                    $stmt = $this->db->prepare($query);
                    
                    if ($stmt) {
                        $types = '';
                        $values = [];
                        foreach ($data as $value) {
                            if (is_float($value)) {
                                $types .= 'd';
                            } elseif (is_int($value)) {
                                $types .= 'i';
                            } else {
                                $types .= 's';
                            }
                            $values[] = $value;
                        }

                        $stmt->bind_param($types, ...$values);
                        
                        if ($stmt->execute()) {
                            $importedCount++;
                        } else {
                            $errors[] = "Error importing row " . ($i + 1) . ": " . $stmt->error;
                        }
                    }

                } catch (Exception $e) {
                    $errors[] = "Error processing row " . ($i + 1) . ": " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            throw new Exception("Error importing file: " . $e->getMessage());
        }
    }

    /**
     * Parse Excel date to MySQL date format
     */
    private function parseExcelDate($excelDate) {
        if (empty($excelDate)) {
            return null;
        }

        // If it's already a DateTime object
        if ($excelDate instanceof DateTime) {
            return $excelDate->format('Y-m-d');
        }

        // If it's a string date
        if (is_string($excelDate)) {
            $timestamp = strtotime($excelDate);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        // If it's an Excel serialized date
        if (is_numeric($excelDate)) {
            $unixTimestamp = ($excelDate - 25569) * 86400; // Convert Excel date to Unix timestamp
            return date('Y-m-d', $unixTimestamp);
        }

        return null;
    }

    /**
     * Parse float values from Excel
     */
    private function parseFloat($value) {
        if (empty($value)) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return floatval($value);
        }

        // Remove non-numeric characters except decimal point
        $cleaned = preg_replace('/[^\d.]/', '', $value);
        return floatval($cleaned);
    }
}
?>