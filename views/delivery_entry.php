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

// Load PhpSpreadsheet - Use the same approach as service_request.php
try {
    // Try Composer autoloader first
    $composerAutoload = '../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } 
    // If not found, try manual include
    else {
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
$db = $database->getConnection();;

// Initialize variables
$error = $success = "";
$delivery_data = [
    'po_number' => '',
    'po_date' => date('Y-m-d'),
    'supplier' => '',
    'delivery_date' => date('Y-m-d'),
    'items' => []
];

// Get items for dropdown
$items = [];
$item_query = "SELECT id, name, unit_of_measure FROM items ORDER BY name";
$item_result = $db->query($item_query);
if ($item_result) {
    while ($row = $item_result->fetch_assoc()) {
        $items[$row['id']] = $row;
    }
}

// Get sections for dropdown
$sections = [];
$section_query = "SELECT section_id, section_name FROM section ORDER BY section_name";
$section_result = $db->query($section_query);
if ($section_result) {
    while ($row = $section_result->fetch_assoc()) {
        $sections[$row['section_id']] = $row['section_name'];
    }
}

// Generate next IAR number
$current_year = date('Y');
$current_month = date('m');
$iar_query = "SELECT iar_number FROM iar_records WHERE iar_number LIKE 'IAR-$current_year-$current_month-%' ORDER BY id DESC LIMIT 1";
$iar_result = $db->query($iar_query);
$next_iar_number = "IAR-$current_year-$current_month-0001";

if ($iar_result && $iar_result->num_rows > 0) {
    $last_iar = $iar_result->fetch_assoc();
    $last_number = intval(substr($last_iar['iar_number'], -4));
    $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    $next_iar_number = "IAR-$current_year-$current_month-$next_number";
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle delete action
    if (isset($_POST['delete_iar'])) {
        $iar_id = intval($_POST['iar_id']);
        
        $db->begin_transaction();
        try {
            // Get delivery_item_ids and movement_ids for cleanup
            $get_items_query = "SELECT ii.delivery_item_id, di.delivery_id 
                               FROM iar_items ii 
                               JOIN delivery_items di ON ii.delivery_item_id = di.id 
                               WHERE ii.iar_id = ?";
            $get_items_stmt = $db->prepare($get_items_query);
            $get_items_stmt->bind_param("i", $iar_id);
            $get_items_stmt->execute();
            $items_result = $get_items_stmt->get_result();
            
            // Reverse stock movements and update inventory
            while ($item = $items_result->fetch_assoc()) {
                $delivery_item_id = $item['delivery_item_id'];
                $movement_id = $item['delivery_id'];
                
                // Get item details to reverse stock
                $get_item_details = "SELECT di.item_id, di.quantity 
                                    FROM delivery_items di 
                                    WHERE di.id = ?";
                $details_stmt = $db->prepare($get_item_details);
                $details_stmt->bind_param("i", $delivery_item_id);
                $details_stmt->execute();
                $details = $details_stmt->get_result()->fetch_assoc();
                $details_stmt->close();
                
                // Reverse stock
                $reverse_stock = "UPDATE items SET current_stock = current_stock - ? WHERE id = ?";
                $reverse_stmt = $db->prepare($reverse_stock);
                $reverse_stmt->bind_param("ii", $details['quantity'], $details['item_id']);
                $reverse_stmt->execute();
                $reverse_stmt->close();
                
                // Delete movement record
                $delete_movement = "DELETE FROM stock_movements WHERE id = ?";
                $movement_stmt = $db->prepare($delete_movement);
                $movement_stmt->bind_param("i", $movement_id);
                $movement_stmt->execute();
                $movement_stmt->close();
            }
            $get_items_stmt->close();
            
            // Delete iar_items records
            $delete_items = "DELETE FROM iar_items WHERE iar_id = ?";
            $items_stmt = $db->prepare($delete_items);
            $items_stmt->bind_param("i", $iar_id);
            $items_stmt->execute();
            $items_stmt->close();
            
            // Delete delivery_items records
            $delete_delivery_items = "DELETE di FROM delivery_items di 
                                     JOIN iar_items ii ON di.id = ii.delivery_item_id 
                                     WHERE ii.iar_id = ?";
            $delivery_items_stmt = $db->prepare($delete_delivery_items);
            $delivery_items_stmt->bind_param("i", $iar_id);
            $delivery_items_stmt->execute();
            $delivery_items_stmt->close();
            
            // Delete IAR record
            $delete_iar = "DELETE FROM iar_records WHERE id = ?";
            $iar_stmt = $db->prepare($delete_iar);
            $iar_stmt->bind_param("i", $iar_id);
            $iar_stmt->execute();
            $iar_stmt->close();
            
            $db->commit();
            $success = "IAR record deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error deleting IAR record: " . $e->getMessage();
        }
    }
    // Handle save delivery action
    elseif (isset($_POST['save_delivery'])) {
        $po_number = trim($_POST['po_number']); 
        $po_date = trim($_POST['po_date']); 
        $supplier = trim($_POST['supplier']);
        $delivery_date = trim($_POST['delivery_date']);
        $requisition_office = trim($_POST['requisition_office']);
        $invoice_number = trim($_POST['invoice_number']);
        $invoice_date = trim($_POST['invoice_date']);
        $dr_number = trim($_POST['dr_number']);
        $dr_date = trim($_POST['dr_date']);
        $item_ids = $_POST['item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_costs = $_POST['unit_cost'] ?? [];

        // Validate required fields
        if (empty($po_number) || empty($supplier) || empty($delivery_date) || empty($requisition_office)) {
            $error = "PO Number, Supplier, Delivery Date, and Requisition Office are required.";
        } elseif (empty($item_ids) || empty($quantities)) {
            $error = "At least one item is required.";
        } else {
            // Begin transaction
            $db->begin_transaction();
            
            try {
                // Create IAR record
                $iar_query = "INSERT INTO iar_records (iar_number, po_number, po_date, supplier, requisition_office, invoice_number, invoice_date, dr_number, dr_date, delivery_date, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $iar_stmt = $db->prepare($iar_query);
                $iar_stmt->bind_param("ssssssssssi", $next_iar_number, $po_number, $po_date, $supplier, $requisition_office, $invoice_number, $invoice_date, $dr_number, $dr_date, $delivery_date, $_SESSION['user_id']);
                $iar_stmt->execute();
                $iar_id = $db->insert_id;
                $iar_stmt->close();
                
                $total_amount = 0;
                
                // Process each item
                foreach ($item_ids as $index => $item_id) {
                    $quantity = intval($quantities[$index]);
                    $unit_cost = floatval($unit_costs[$index]);
                    $total_cost = $quantity * $unit_cost;
                    $total_amount += $total_cost;
                    
                    if ($quantity > 0 && $item_id > 0) {
                        // Record stock movement
                        $movement_query = "INSERT INTO stock_movements (item_id, movement_type, quantity, reference, unit_cost) 
                                           VALUES (?, 'in', ?, ?, ?)";
                        $movement_stmt = $db->prepare($movement_query);
                        $movement_stmt->bind_param("iisd", $item_id, $quantity, $po_number, $unit_cost);
                        $movement_stmt->execute();
                        $movement_id = $db->insert_id;
                        $movement_stmt->close();
                        
                        // Add to delivery_items
                        $delivery_item_query = "INSERT INTO delivery_items (delivery_id, item_id, quantity, unit_cost, total_cost) 
                                               VALUES (?, ?, ?, ?, ?)";
                        $delivery_item_stmt = $db->prepare($delivery_item_query);
                        $delivery_item_stmt->bind_param("iiidd", $movement_id, $item_id, $quantity, $unit_cost, $total_cost);
                        $delivery_item_stmt->execute();
                        $delivery_item_id = $db->insert_id;
                        $delivery_item_stmt->close();
                        
                        // Add to iar_items
                        $item_info = $items[$item_id];
                        $iar_item_query = "INSERT INTO iar_items (iar_id, delivery_item_id, description, unit, quantity, unit_price, total_price) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $iar_item_stmt = $db->prepare($iar_item_query);
                        $iar_item_stmt->bind_param("iissidd", $iar_id, $delivery_item_id, $item_info['name'], $item_info['unit_of_measure'], $quantity, $unit_cost, $total_cost);
                        $iar_item_stmt->execute();
                        $iar_item_stmt->close();
                        
                        // Update current stock
                        $stock_query = "UPDATE items SET current_stock = current_stock + ? WHERE id = ?";
                        $stock_stmt = $db->prepare($stock_query);
                        $stock_stmt->bind_param("ii", $quantity, $item_id);
                        $stock_stmt->execute();
                        $stock_stmt->close();
                    }
                }
                
                // Update total amount in IAR record
                $update_iar_query = "UPDATE iar_records SET total_amount = ? WHERE id = ?";
                $update_iar_stmt = $db->prepare($update_iar_query);
                $update_iar_stmt->bind_param("di", $total_amount, $iar_id);
                $update_iar_stmt->execute();
                $update_iar_stmt->close();
                
                $db->commit();
                $success = "Delivery entry recorded successfully! IAR Number: $next_iar_number";
                
                // Reset form
                $delivery_data = [
                    'po_number' => '',
                    'po_date' => date('Y-m-d'),
                    'supplier' => '',
                    'delivery_date' => date('Y-m-d'),
                    'items' => []
                ];
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error recording delivery: " . $e->getMessage();
            }
        }
    }

    elseif (isset($_POST['print_iar'])) {
        $iar_id = $_POST['iar_id'];
        generateIARExcel($db, $iar_id);
        exit();
    }
}

function generateIARExcel($db, $iar_id) {
    try {
        // Fetch IAR details with related information
        $query = "SELECT i.*, u.user as created_by_name
                 FROM iar_records i 
                 LEFT JOIN users u ON i.created_by = u.id 
                 WHERE i.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $iar_id);
        $stmt->execute();
        $iar = $stmt->get_result()->fetch_assoc();
        
        if (!$iar) {
            throw new Exception("IAR record not found");
        }
        
        // Fetch IAR items
         $items_query = "SELECT ii.*, di.item_id, i.name as item_name, i.description as item_description, i.unit_of_measure as unit 
               FROM iar_items ii
               LEFT JOIN delivery_items di ON ii.delivery_item_id = di.id
               LEFT JOIN items i ON di.item_id = i.id
               WHERE ii.iar_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $iar_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Load the IAR template (you'll need to create this template file)
        $template_path = "../public/templates/IAR-2025.xlsx";
        if (file_exists($template_path)) {
            $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        } else {
            // Create a basic IAR format if template doesn't exist
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set basic headers
            $sheet->setCellValue('A1', 'INSPECTION AND ACCEPTANCE REPORT');
            $sheet->setCellValue('A2', 'Entity Name:');
            $sheet->setCellValue('A3', 'Supplier:');
            $sheet->setCellValue('A4', 'Address:');
            $sheet->setCellValue('A5', 'TIN:');
            $sheet->setCellValue('E2', 'PO Number:');
            $sheet->setCellValue('E3', 'PO Date:');
            $sheet->setCellValue('E4', 'Invoice No:');
            $sheet->setCellValue('E5', 'Invoice Date:');
            $sheet->setCellValue('E6', 'DR No:');
            $sheet->setCellValue('E7', 'DR Date:');
            
            // Item table headers
            $sheet->setCellValue('A9', 'Stock/Property No.');
            $sheet->setCellValue('B9', 'Description');
            $sheet->setCellValue('C9', 'Unit');
            $sheet->setCellValue('D9', 'Quantity');
            $sheet->setCellValue('E9', 'Unit Price');
            $sheet->setCellValue('F9', 'Total Price');
        }
        
        $sheet = $spreadsheet->getActiveSheet();
        
        // Fill in the header information
        $sheet->setCellValue('C5', $iar['supplier'] ?? '');
        $sheet->setCellValue('F7', $iar['requisition_office'] ?? '');
        $sheet->setCellValue('C6', $iar['po_number'] ?? '');
        $sheet->setCellValue('F6', $iar['po_date'] ? date('m/d/Y', strtotime($iar['po_date'])) : '');
        $sheet->setCellValue('M5', $iar['iar_number'] ?? '');
        $sheet->setCellValue('M6', $iar['delivery_date'] ? date('m/d/Y', strtotime($iar['delivery_date'])) : '');
        $sheet->setCellValue('M8', $iar['invoice_date'] ? date('m/d/Y', strtotime($iar['invoice_date'])) : '');
        $sheet->setCellValue('M7', $iar['invoice_number'] ?? '');
        $sheet->setCellValue('M9', $iar['dr_number'] ?? '');

        // FIXED: Proper DR Date handling - check if date is valid before formatting
        $dr_date = '';
        if (!empty($iar['dr_date']) && $iar['dr_date'] != '0000-00-00') {
            $dr_date = date('m/d/Y', strtotime($iar['dr_date']));
        }
        $sheet->setCellValue('M10', $dr_date);
        
        $start_row = 13;
        
        $item_count = count($items);
        if ($item_count > 1) {
            $rows_to_insert = $item_count - 1;
            
            $sheet->insertNewRowBefore($start_row + 1, $rows_to_insert);
        }
        
        // Fill in item details
        $row = $start_row;
        foreach ($items as $item) {
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->mergeCells('C' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, $item['stock_property_no'] ?? '');
            $sheet->setCellValue('F' . $row, $item['description'] ?? $item['item_name']);
            $sheet->setCellValue('G' . $row, $item['unit'] ?? '');
            $sheet->mergeCells('G' . $row . ':H' . $row);
            $sheet->setCellValue('I' . $row, $item['quantity'] ?? '');
            $sheet->mergeCells('I' . $row . ':K' . $row);
            $sheet->setCellValue('L' . $row, number_format($item['unit_price'], 2));
            $sheet->setCellValue('M' . $row, number_format($item['total_price'], 2));
            $row++;
        }
        
        // Calculate the row where totals should be (after all items)
        $total_row = $row + 1;
        
        // Fill in totals
        $total = $iar['total_amount'] ?? array_sum(array_column($items, 'total_price'));
        $sheet->setCellValue('M' . $total_row, number_format($total, 2));
        
        // Adjust the inspection and acceptance details rows based on the number of items
        $inspection_row = $total_row + 4;
        
        // Fill in inspection and acceptance details
        $sheet->setCellValue('B' . $inspection_row, '');
        $sheet->setCellValue('B' . ($inspection_row + 1), '');
        $sheet->setCellValue('B' . ($inspection_row + 2), '');
        
        $sheet->setCellValue('D' . $inspection_row, '');
        $sheet->setCellValue('D' . ($inspection_row + 1), '');
        $sheet->setCellValue('D' . ($inspection_row + 2), '');
        
        // Set filename
        $filename = "IAR_" . ($iar['iar_number'] ?? $iar_id) . ".xlsx";
        
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
        echo "Error generating IAR Excel: " . $e->getMessage();
    }
}

// Get all IAR records for datatable
$iar_records = [];
$records_query = "SELECT i.*, u.user as created_by_name, COUNT(ii.id) as item_count 
                  FROM iar_records i 
                  LEFT JOIN users u ON i.created_by = u.id 
                  LEFT JOIN iar_items ii ON i.id = ii.iar_id 
                  GROUP BY i.id 
                  ORDER BY i.created_at DESC";
$records_result = $db->query($records_query);
if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $iar_records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
    <style>
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(120deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .delivery-item {
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .btn-add-item {
            background: linear-gradient(120deg, #28a745, #20c997);
            border: none;
        }
        .btn-remove-item {
            background: linear-gradient(120deg, #dc3545, #c82333);
            border: none;
        }
        .iar-info {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
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
                        <h1>Delivery Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class='breadcrumb-item active'>Delivery Management</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Delivery Records</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#deliveryModal">
                                <i class="fas fa-plus"></i> New Delivery Entry
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="deliveryTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>IAR Number</th>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Requisition Office</th>
                                        <th>Delivery Date</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($iar_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['iar_number']) ?></td>
                                        <td><?= htmlspecialchars($record['po_number']) ?></td>
                                        <td><?= htmlspecialchars($record['supplier']) ?></td>
                                        <td><?= htmlspecialchars($record['requisition_office']) ?></td>
                                        <td><?= date('M j, Y', strtotime($record['delivery_date'])) ?></td>
                                        <td class="text-center"><?= $record['item_count'] ?></td>
                                        <td>₱<?= number_format($record['total_amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($record['created_by_name']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($record['created_at'])) ?></td>
                                        <td class="action-buttons">
                                            <a href="delivery_view.php?id=<?= $record['id'] ?>" class="btn btn-info btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-secondary btn-sm print-iar" data-id="<?= $record['id'] ?>" title="Print IAR">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this IAR record? This action cannot be undone.');">
                                                <input type="hidden" name="iar_id" value="<?= $record['id'] ?>">
                                                <button type="submit" name="delete_iar" class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Delivery Entry Modal -->
    <div class="modal fade" id="deliveryModal" tabindex="-1" role="dialog" aria-labelledby="deliveryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title" id="deliveryModalLabel">New Delivery Entry</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" id="deliveryForm">
                    <div class="modal-body">
                        <!-- IAR Information Section -->
                        <div class="iar-info mb-4">
                            <h4 class="mb-3">IAR Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="iar_number">IAR Number</label>
                                        <input type="text" class="form-control" id="iar_number" value="<?= $next_iar_number ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requisition_office">Requisition Office/Dept *</label>
                                        <select class="form-control" id="requisition_office" name="requisition_office" required>
                                            <option value="">-- Select Section --</option>
                                            <?php foreach ($sections as $id => $name): ?>
                                                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_number">Invoice No.</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_date">Invoice Date</label>
                                        <input type="date" class="form-control" id="invoice_date" name="invoice_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dr_number">D.R. No.</label>
                                        <input type="text" class="form-control" id="dr_number" name="dr_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dr_date">D.R. Date</label>
                                        <input type="date" class="form-control" id="dr_date" name="dr_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Information Section -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="po_number">PO Number *</label>
                                    <input type="text" class="form-control" id="po_number" name="po_number" 
                                           value="<?= htmlspecialchars($delivery_data['po_number']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="po_date">PO Date *</label>
                                    <input type="date" class="form-control" id="po_date" name="po_date">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="supplier">Supplier *</label>
                                    <input type="text" class="form-control" id="supplier" name="supplier" 
                                           value="<?= htmlspecialchars($delivery_data['supplier']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="delivery_date">Delivery Date *</label>
                                    <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                           value="<?= htmlspecialchars($delivery_data['delivery_date']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-4 mb-3">Items Delivered</h4>
                        <div id="deliveryItems">
                            <div class="delivery-item">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Item *</label>
                                            <select class="form-control" name="item_id[]" required>
                                                <option value="">-- Select Item --</option>
                                                <?php foreach ($items as $id => $item): ?>
                                                    <option value="<?= $id ?>"><?= htmlspecialchars($item['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Quantity *</label>
                                            <input type="number" class="form-control" name="quantity[]" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Unit Cost</label>
                                            <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Total Cost</label>
                                            <input type="text" class="form-control total-cost" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Actions</label>
                                            <button type="button" class="form-control btn btn-sm btn-remove-item text-white" onclick="removeItem(this)">
                                                <i class="fas fa-trash text-white"></i>Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" id="addItem" class="btn btn-add-item">
                                <i class="fas fa-plus"></i> Add Another Item
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_delivery" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Delivery & Generate IAR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Initialize DataTable with export buttons
        $('#deliveryTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[8, 'desc']]
        });
        
        let itemCount = 1;
        
        // Add new item row
        $('#addItem').click(function() {
            itemCount++;
            const newItem = `
                <div class="delivery-item">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control" name="item_id[]" required>
                                    <option value="">-- Select Item --</option>
                                    <?php foreach ($items as $id => $item): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($item['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit Cost</label>
                                <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Cost</label>
                                <input type="text" class="form-control total-cost" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-remove-item text-white"">
                                    <i class="fas fa-trash text-white"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#deliveryItems').append(newItem);
            attachCalculationEvents();
        });
        
        // Attach calculation events to existing elements
        attachCalculationEvents();
        
        function attachCalculationEvents() {
            // Calculate total cost when quantity or unit cost changes
            $('input[name="quantity[]"], input[name="unit_cost[]"]').on('input', function() {
                const row = $(this).closest('.row');
                const quantity = parseFloat(row.find('input[name="quantity[]"]').val()) || 0;
                const unitCost = parseFloat(row.find('input[name="unit_cost[]"]').val()) || 0;
                const totalCost = quantity * unitCost;
                row.find('.total-cost').val(totalCost.toFixed(2));
            });
        }
        
        // Use event delegation for remove buttons
        $(document).on('click', '.btn-remove-item', function() {
            removeItem(this);
        });
        
        // Reset form when modal is closed
        $('#deliveryModal').on('hidden.bs.modal', function () {
            document.getElementById("deliveryForm").reset();
            $('#deliveryItems').html(`
                <div class="delivery-item">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control" name="item_id[]" required>
                                    <option value="">-- Select Item --</option>
                                    <?php foreach ($items as $id => $item): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($item['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit Cost</label>
                                <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Cost</label>
                                <input type="text" class="form-control total-cost" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="btn btn-sm btn-remove-item mt-1">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            attachCalculationEvents();
        });

       // Handle IAR print button click
        $('.print-iar').click(function() {
            var iarId = $(this).data('id'); // Changed from deliveryId to iarId
            Swal.fire({
                title: 'Generating IAR Document',
                html: 'Please wait while we generate the Inspection and Acceptance Report...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    $('<form>').attr({
                        method: 'POST',
                        action: 'delivery_entry.php'
                    }).append(
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'iar_id', // Changed from delivery_id to iar_id
                            value: iarId
                        }),
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'print_iar',
                            value: '1'
                        })
                    ).appendTo('body').submit();
                },
                timer: 1500
            });
        });
    });
    
    // Move removeItem function outside the document ready scope
    function removeItem(button) {
        if ($('.delivery-item').length > 1) {
            $(button).closest('.delivery-item').remove();
        } else {
            alert('You must have at least one item.');
        }
    }
</script>
</body>
</html>