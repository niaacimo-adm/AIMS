<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Load PhpSpreadsheet
try {
    $composerAutoload = '../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } else {
        $phpspreadsheetPath = '../includes/phpspreadsheet/';
        if (file_exists($phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php')) {
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Spreadsheet.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Writer/Xlsx.php';
        } else {
            throw new Exception("PhpSpreadsheet library not found. Please install via Composer: composer require phpoffice/phpspreadsheet");
        }
    }
} catch (Exception $e) {
    die("Error loading PhpSpreadsheet: " . $e->getMessage());
}

$database = new Database();
$db = $database->getConnection();

// Get RIS ID from URL
$ris_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get RIS information
$ris = null;
$ris_items = [];

if ($ris_id > 0) {
    // Get RIS details
    $query = "SELECT r.*, u.user as created_by_name, i.iar_number 
              FROM ris_records r 
              LEFT JOIN users u ON r.created_by = u.id 
              LEFT JOIN iar_records i ON r.iar_id = i.id 
              WHERE r.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $ris_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ris = $result->fetch_assoc();
    $stmt->close();

    if ($ris) {
        // Get RIS items
        $items_query = "SELECT ri.*, i.name as item_name, i.unit_of_measure 
                       FROM ris_items ri 
                       JOIN items i ON ri.item_id = i.id 
                       WHERE ri.ris_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $ris_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $ris_items = $items_result->fetch_all(MYSQLI_ASSOC);
        $items_stmt->close();
    }
}

// Process Excel generation
if (isset($_GET['generate_excel']) && $ris_id > 0) {
    generateRISExcel($db, $ris_id);
    exit();
}

function generateRISExcel($db, $ris_id) {
    try {
        // Fetch RIS details
        $query = "SELECT r.*, u.user as created_by_name, i.iar_number, 
                         CONCAT(e.first_name, ' ', e.last_name) as requested_by_fullname,
                         e.position_id, p.position_name
                  FROM ris_records r 
                  LEFT JOIN users u ON r.created_by = u.id 
                  LEFT JOIN iar_records i ON r.iar_id = i.id 
                  LEFT JOIN employee e ON r.requested_by_id = e.emp_id
                  LEFT JOIN position p ON e.position_id = p.position_id
                  WHERE r.id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $ris_id);
        $stmt->execute();
        $ris = $stmt->get_result()->fetch_assoc();
        
        if (!$ris) {
            throw new Exception("RIS record not found");
        }
        
        // Fetch RIS items
        $items_query = "SELECT ri.*, i.name as item_name, i.unit_of_measure 
                       FROM ris_items ri 
                       JOIN items i ON ri.item_id = i.id 
                       WHERE ri.ris_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $ris_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Load the RIS template
        $template_path = "../public/templates/RIS-FORM.xlsx";
        if (file_exists($template_path)) {
            $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        } else {
            // Create a basic RIS format if template doesn't exist
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set basic headers for RIS form
            $sheet->setCellValue('D1', 'REQUISITION AND ISSUE SLIP');
            $sheet->setCellValue('A3', 'Entity Name:');
            $sheet->setCellValue('A4', 'Division:');
            $sheet->setCellValue('A5', 'Office:');
            $sheet->setCellValue('F3', 'Fund Cluster:');
            $sheet->setCellValue('F4', 'Responsibility Center Code:');
            $sheet->setCellValue('F5', 'RIS No.:');
            $sheet->setCellValue('I3', 'Date:');
            
            // Item table headers
            $sheet->setCellValue('A7', 'REQUISITION');
            $sheet->setCellValue('A8', 'STOCK NO.');
            $sheet->setCellValue('B8', 'UNIT');
            $sheet->setCellValue('C8', 'DESCRIPTION');
            $sheet->setCellValue('E8', 'QUANTITY');
            $sheet->setCellValue('F8', 'STOCK AVAILABLE?');
            $sheet->setCellValue('G8', 'ISSUANCE');
            $sheet->setCellValue('H8', 'QUANTITY');
            $sheet->setCellValue('I8', 'REMARKS');
            
            $sheet->setCellValue('A10', 'Purpose:');
            
            // Signatories
            $sheet->setCellValue('B12', 'REQUESTED BY');
            $sheet->setCellValue('D12', 'APPROVED BY');
            $sheet->setCellValue('F12', 'ISSUED BY');
            $sheet->setCellValue('I12', 'RECEIVED BY');
            
            $sheet->setCellValue('A13', 'Signature:');
            $sheet->setCellValue('A14', 'Printed Name:');
            $sheet->setCellValue('A15', 'Designation:');
            $sheet->setCellValue('A16', 'Date:');
        }
        
        $sheet = $spreadsheet->getActiveSheet();
        
        // Fill in the header information
        $sheet->setCellValue('B3', 'National Irrigation Administration - ACIMO');
        $sheet->setCellValue('B4', $ris['requisition_office'] ?? '');
        $sheet->setCellValue('B5', 'Interim Albay-Catanduanes IMO, Tuburan, Ligao City');
        $sheet->setCellValue('I5', $ris['ris_number'] ?? '');
        $sheet->setCellValue('I3', $ris['created_at'] ? date('m/d/Y', strtotime($ris['created_at'])) : date('m/d/Y'));
        
        // Fill purpose
        $sheet->setCellValue('B10', $ris['purpose'] ?? '');
        
        // Fill requested by information
        $sheet->setCellValue('B14', $ris['requested_by'] ?? '');
        $sheet->setCellValue('B15', $ris['designation'] ?? '');
        $sheet->setCellValue('B16', $ris['created_at'] ? date('m/d/Y', strtotime($ris['created_at'])) : '');
        
        // Fill approved by (fixed values based on template)
        $sheet->setCellValue('D14', 'ENGR. MARK CLOYD G. SO, MPA');
        $sheet->setCellValue('D15', 'Acting Division Manager');
        
        // Fill issued by (fixed values based on template)
        $sheet->setCellValue('F14', 'LUISITO O. DEDASE');
        $sheet->setCellValue('F15', 'Property Officer B');
        
        // Start filling items from row 9 (similar to delivery_entry.php approach)
        $start_row = 9;
        
        // Count items and insert rows if needed (like in delivery_entry.php)
        $item_count = count($items);
        if ($item_count > 1) {
            $rows_to_insert = $item_count - 1;
            $sheet->insertNewRowBefore($start_row + 1, $rows_to_insert);
        }
        
        // Fill in item details (using the same pattern as delivery_entry.php)
        $row = $start_row;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item['unit_of_measure'] ?? '');
            $sheet->setCellValue('C' . $row, $item['description'] ?? $item['item_name']);
            $sheet->mergeCells('C' . $row . ':D' . $row);
            $sheet->setCellValue('E' . $row, $item['quantity'] ?? '');
            $sheet->setCellValue('H' . $row, $item['quantity'] ?? '');
            $row++;
        }
        
        // Set filename
        $filename = "RIS_" . ($ris['ris_number'] ?? $ris_id) . ".xlsx";
        
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        // Output the file
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Error generating RIS Excel: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS Details - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modal-xl {
            max-width: 90%;
        }
        .ris-header {
            background: linear-gradient(120deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .item-table th {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(120deg, #28a745, #20c997);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .btn-excel {
            background: linear-gradient(120deg, #28a745, #20c997);
            color: white;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>RIS Details</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="ris_records.php">RIS Records</a></li>
                            <li class="breadcrumb-item active">RIS Details</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if ($ris): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">RIS: <?= htmlspecialchars($ris['ris_number']) ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- RIS Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">RIS Number</span>
                                        <span class="info-box-number"><?= htmlspecialchars($ris['ris_number']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Purpose</span>
                                        <span class="info-box-number"><?= htmlspecialchars($ris['purpose']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Requisition Office</span>
                                        <span class="info-box-number"><?= htmlspecialchars($ris['requisition_office']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Requested By</span>
                                        <span class="info-box-number"><?= htmlspecialchars($ris['requested_by']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Designation</span>
                                        <span class="info-box-number"><?= htmlspecialchars($ris['designation']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped item-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item Name</th>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    $total_quantity = 0;
                                    foreach ($ris_items as $item): 
                                        $total_quantity += $item['quantity'];
                                    ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
                                        <td><?= number_format($item['quantity']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="4" class="text-right"><strong>Total Quantity:</strong></td>
                                        <td><strong><?= number_format($total_quantity) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Reference Information</h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>IAR Reference:</strong> <?= htmlspecialchars($ris['iar_number'] ?? 'N/A') ?></p>
                                        <p><strong>RIS Number:</strong> <?= htmlspecialchars($ris['ris_number']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">System Information</h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Date Created:</strong> <?= date('M j, Y g:i A', strtotime($ris['created_at'])) ?></p>
                                        <p><strong>Created By:</strong> <?= htmlspecialchars($ris['created_by_name']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="text-right">
                            <a href="ris_view.php?id=<?= $ris_id ?>&generate_excel=1" class="btn btn-excel">
                                <i class="fas fa-file-excel"></i> Generate RIS Excel
                            </a>
                            <a href="ris_records.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4>RIS not found</h4>
                        <p>Please select a valid RIS from the records.</p>
                        <a href="ris_records.php" class="btn btn-primary">Back to RIS Records</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>