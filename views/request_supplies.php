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

$database = new Database();
$db = $database->getConnection();

// Get current employee details
$employee_id = $_SESSION['emp_id'] ?? 0;
$employee_details = [];

if ($employee_id) {
    $employee_query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name, 
                      s.section_name 
                      FROM employee e 
                      LEFT JOIN section s ON e.section_id = s.section_id 
                      WHERE e.emp_id = ?";
    $stmt = $db->prepare($employee_query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_details = $result->fetch_assoc();
}

// Get available supplies for dropdown
$supplies = [];
$supply_query = "SELECT id, name, description, unit_of_measure, current_stock 
                FROM items 
                WHERE current_stock > 0 
                ORDER BY name";
$supply_result = $db->query($supply_query);
if ($supply_result) {
    while ($row = $supply_result->fetch_assoc()) {
        $supplies[$row['id']] = $row;
    }
}

// Process form submission
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    // Get values from session/employee details
    $employee_name = $employee_details['full_name'] ?? '';
    $section = $employee_details['section_name'] ?? '';
    $request_date = date('Y-m-d'); // Current date
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    // Validate required fields
    if (empty($employee_name) || empty($section)) {
        $error = "Employee information is required.";
    } elseif (empty($item_ids) || empty($quantities)) {
        $error = "At least one supply item is required.";
    } else {
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Create supply request record
            $request_query = "INSERT INTO supply_requests (employee_id, employee_name, section, request_date) 
                            VALUES (?, ?, ?, ?)";
            $request_stmt = $db->prepare($request_query);
            $request_stmt->bind_param("isss", $employee_id, $employee_name, $section, $request_date);
            $request_stmt->execute();
            $request_id = $db->insert_id;
            $request_stmt->close();
            
            // Process each supply item
            foreach ($item_ids as $index => $item_id) {
                $quantity = intval($quantities[$index]);
                
                if ($item_id > 0 && $quantity > 0) {
                    $item_info = $supplies[$item_id] ?? null;
                    if ($item_info) {
                        // Add to supply_request_items
                        $item_query = "INSERT INTO supply_request_items (request_id, supply_name, description, unit, quantity) 
                                    VALUES (?, ?, ?, ?, ?)";
                        $item_stmt = $db->prepare($item_query);
                        $item_stmt->bind_param("isssi", $request_id, $item_info['name'], $item_info['description'], $item_info['unit_of_measure'], $quantity);
                        $item_stmt->execute();
                        $item_stmt->close();
                    }
                }
            }
            
            $db->commit();
            $success = "Supply request submitted successfully! Request ID: SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT);
            
            // Reset form
            $_POST = array();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error submitting supply request: " . $e->getMessage();
        }
    }
}

// Handle Excel file upload and processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['xlsx', 'xls'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            try {
                // Load PhpSpreadsheet
                require_once '../vendor/autoload.php';
                
                $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp_path);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Extract data from Excel (based on your template format)
                $employee_name = '';
                $section = '';
                $request_date = date('Y-m-d'); // Current date for Excel imports too
                $items = [];
                
                // Read Excel data (adjust cell references based on your actual Excel structure)
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(FALSE);
                    $rowData = [];
                    
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getCalculatedValue();
                    }
                    
                    // Extract employee name (adjust based on your Excel structure)
                    if (isset($rowData[0]) && strpos(strtolower($rowData[0]), 'name:') !== false) {
                        $employee_name = trim(str_replace('NAME:', '', $rowData[0]));
                    }
                    
                    // Extract section (adjust based on your Excel structure)
                    if (isset($rowData[1]) && strpos(strtolower($rowData[1]), 'section:') !== false) {
                        $section = trim(str_replace('SECTION:', '', $rowData[1]));
                    }
                    
                    // Extract items (assuming they start from row with numeric first cell)
                    if (is_numeric($rowData[0]) && !empty($rowData[1])) {
                        $items[] = [
                            'supply_name' => $rowData[1] ?? '',
                            'description' => $rowData[2] ?? '',
                            'unit' => $rowData[3] ?? '',
                            'quantity' => $rowData[4] ?? 0
                        ];
                    }
                }
                
                // Validate extracted data
                if (empty($employee_name) || empty($section) || empty($items)) {
                    $error = "Could not extract all required data from the Excel file. Please check the file format.";
                } else {
                    // Begin transaction
                    $db->begin_transaction();
                    
                    try {
                        // Create supply request record
                        $request_query = "INSERT INTO supply_requests (employee_id, employee_name, section, request_date) 
                                        VALUES (?, ?, ?, ?)";
                        $request_stmt = $db->prepare($request_query);
                        $request_stmt->bind_param("isss", $employee_id, $employee_name, $section, $request_date);
                        $request_stmt->execute();
                        $request_id = $db->insert_id;
                        $request_stmt->close();
                        
                        // Process each supply item
                        foreach ($items as $item) {
                            if (!empty($item['supply_name']) && $item['quantity'] > 0) {
                                // Add to supply_request_items
                                $item_query = "INSERT INTO supply_request_items (request_id, supply_name, description, unit, quantity) 
                                              VALUES (?, ?, ?, ?, ?)";
                                $item_stmt = $db->prepare($item_query);
                                $item_stmt->bind_param("isssi", $request_id, $item['supply_name'], $item['description'], $item['unit'], $item['quantity']);
                                $item_stmt->execute();
                                $item_stmt->close();
                            }
                        }
                        
                        $db->commit();
                        $success = "Supply request imported successfully from Excel! Request ID: SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT);
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Error importing supply request: " . $e->getMessage();
                    }
                }
                
            } catch (Exception $e) {
                $error = "Error reading Excel file: " . $e->getMessage();
            }
        } else {
            $error = "Invalid file format. Please upload an Excel file (.xlsx or .xls).";
        }
    } else {
        $error = "Please select a valid Excel file to upload.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Supplies - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
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
        .supply-item {
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .btn-add-item {
            background: linear-gradient(120deg, #007bff, #0056b3);
            border: none;
        }
        .btn-remove-item {
            background: linear-gradient(120deg, #dc3545, #c82333);
            border: none;
        }
        .upload-section {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stock-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .user-info-card {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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
                        <h1>Request Supplies</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">Request Supplies</li>
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
                        <h3 class="card-title">Supply Request Form</h3>
                    </div>
                    <div class="card-body">
                        <!-- User Information Display -->
                        <div class="user-info-card mb-4">
                            <h5><i class="fas fa-user"></i> Requestor Information</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Employee Name:</strong><br>
                                    <?= htmlspecialchars($employee_details['full_name'] ?? '') ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Section/Department:</strong><br>
                                    <?= htmlspecialchars($employee_details['section_name'] ?? '') ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Request Date:</strong><br>
                                    <?= date('F j, Y') ?>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group">
                            <a href="my_supply_requests.php" class="btn btn-info">
                                <i class="fas fa-list"></i> My Supply Requests
                            </a>
                            <?php 
                            // Only show "Manage Supply Requests" to administrators or managers
                            if (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], ['Administrator', 'Manager'])): 
                            ?>
                                <a href="manage_supply_requests.php" class="btn btn-primary">
                                    <i class="fas fa-cog"></i> Manage All Requests
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="" id="supplyRequestForm">
                            <!-- Hidden inputs for employee data and request date -->
                            <input type="hidden" name="employee_name" value="<?= htmlspecialchars($employee_details['full_name'] ?? '') ?>">
                            <input type="hidden" name="section" value="<?= htmlspecialchars($employee_details['section_name'] ?? '') ?>">
                            
                            <!-- No form fields needed - everything is automatic -->

                            <h4 class="mt-4 mb-3">Requested Supplies</h4>
                            <div id="supplyItems">
                                <div class="supply-item">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Supply Name *</label>
                                                <select class="form-control supply-select" name="item_id[]" required onchange="fillItemDetails(this)">
                                                    <option value="">-- Select Supply --</option>
                                                    <?php foreach ($supplies as $id => $item): ?>
                                                        <option value="<?= $id ?>" 
                                                                data-description="<?= htmlspecialchars($item['description']) ?>" 
                                                                data-unit="<?= htmlspecialchars($item['unit_of_measure']) ?>">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <input type="text" class="form-control item-description" name="description[]" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Unit *</label>
                                                <input type="text" class="form-control item-unit" name="unit[]" readonly required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Quantity *</label>
                                                <input type="number" class="form-control item-quantity" name="quantity[]" min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Actions</label>
                                                <button type="button" class="form-control btn btn-sm btn-remove-item text-white" onclick="removeItem(this)">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="button" id="addItem" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Another Item
                                </button>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" name="submit_request" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-redo"></i> Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
    // Supply items data for JavaScript access
    const supplyItems = <?= json_encode($supplies) ?>;

    $(document).ready(function() {
        let itemCount = 1;
        
        // Add new item row
        $('#addItem').click(function() {
            itemCount++;
            const newItem = `
                <div class="supply-item">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Supply Name *</label>
                                <select class="form-control supply-select" name="item_id[]" required onchange="fillItemDetails(this)">
                                    <option value="">-- Select Supply --</option>
                                    <?php foreach ($supplies as $id => $item): ?>
                                        <option value="<?= $id ?>" 
                                                data-description="<?= htmlspecialchars($item['description']) ?>" 
                                                data-unit="<?= htmlspecialchars($item['unit_of_measure']) ?>">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" class="form-control item-description" name="description[]" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit *</label>
                                <input type="text" class="form-control item-unit" name="unit[]" readonly required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control item-quantity" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-remove-item text-white" onclick="removeItem(this)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#supplyItems').append(newItem);
        });
        
        // Reset form when reset button is clicked
        $('button[type="reset"]').click(function() {
            // Keep only one item row
            $('#supplyItems').html(`
                <div class="supply-item">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Supply Name *</label>
                                <select class="form-control supply-select" name="item_id[]" required onchange="fillItemDetails(this)">
                                    <option value="">-- Select Supply --</option>
                                    <?php foreach ($supplies as $id => $item): ?>
                                        <option value="<?= $id ?>" 
                                                data-description="<?= htmlspecialchars($item['description']) ?>" 
                                                data-unit="<?= htmlspecialchars($item['unit_of_measure']) ?>">
                                            <?= htmlspecialchars($item['name']) ?> 
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" class="form-control item-description" name="description[]" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit *</label>
                                <input type="text" class="form-control item-unit" name="unit[]" readonly required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control item-quantity" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-remove-item text-white" onclick="removeItem(this)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    });
    
    // Fill item details when supply is selected
    function fillItemDetails(selectElement) {
        const row = $(selectElement).closest('.row');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const description = selectedOption.getAttribute('data-description') || '';
        const unit = selectedOption.getAttribute('data-unit') || '';
        
        // Update description and unit fields
        row.find('.item-description').val(description);
        row.find('.item-unit').val(unit);
    }
    
    // Remove item row
    function removeItem(button) {
        if ($('.supply-item').length > 1) {
            $(button).closest('.supply-item').remove();
        } else {
            alert('You must have at least one supply item.');
        }
    }
    
    // Form validation before submission
    $('#supplyRequestForm').on('submit', function(e) {
        let hasErrors = false;
        
        // Check each item for required fields
        $('.supply-item').each(function() {
            const selectElement = $(this).find('.supply-select')[0];
            const quantityInput = $(this).find('.item-quantity');
            const quantity = parseInt(quantityInput.val()) || 0;
            
            if (quantity <= 0) {
                hasErrors = true;
                quantityInput.addClass('is-invalid');
            } else {
                quantityInput.removeClass('is-invalid');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Please fill in all required fields with valid values.');
            return false;
        }
        
        return true;
    });
</script>
</body>
</html>