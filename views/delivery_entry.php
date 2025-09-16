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

// Initialize variables
$error = $success = "";
$delivery_data = [
    'po_number' => '',
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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $po_number = trim($_POST['po_number']);
    $supplier = trim($_POST['supplier']);
    $delivery_date = trim($_POST['delivery_date']);
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_costs = $_POST['unit_cost'] ?? [];
    $references = $_POST['reference'] ?? [];
    $notes = $_POST['notes'] ?? [];

    // Validate required fields
    if (empty($po_number) || empty($supplier) || empty($delivery_date)) {
        $error = "PO Number, Supplier, and Delivery Date are required.";
    } elseif (empty($item_ids) || empty($quantities)) {
        $error = "At least one item is required.";
    } else {
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Process each item
            foreach ($item_ids as $index => $item_id) {
                $quantity = intval($quantities[$index]);
                $unit_cost = floatval($unit_costs[$index]);
                $reference = trim($references[$index]);
                $note = trim($notes[$index]);
                
                if ($quantity > 0 && $item_id > 0) {
                    // Record stock movement
                    $movement_query = "INSERT INTO stock_movements (item_id, movement_type, quantity, reference, notes, unit_cost) 
                                       VALUES (?, 'in', ?, ?, ?, ?)";
                    $movement_stmt = $db->prepare($movement_query);
                    $movement_stmt->bind_param("iissd", $item_id, $quantity, $reference, $note, $unit_cost);
                    $movement_stmt->execute();
                    $movement_stmt->close();
                    
                    // Update current stock
                    $stock_query = "UPDATE items SET current_stock = current_stock + ? WHERE id = ?";
                    $stock_stmt = $db->prepare($stock_query);
                    $stock_stmt->bind_param("ii", $quantity, $item_id);
                    $stock_stmt->execute();
                    $stock_stmt->close();
                }
            }
            
            $db->commit();
            $success = "Delivery entry recorded successfully!";
            
            // Reset form
            $delivery_data = [
                'po_number' => '',
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Entry - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
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
                        <h1>Delivery Entry</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">Delivery Entry</li>
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
                        <h3 class="card-title">Delivery Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="deliveryForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="po_number">PO Number *</label>
                                        <input type="text" class="form-control" id="po_number" name="po_number" 
                                               value="<?= htmlspecialchars($delivery_data['po_number']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="supplier">Supplier *</label>
                                        <input type="text" class="form-control" id="supplier" name="supplier" 
                                               value="<?= htmlspecialchars($delivery_data['supplier']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
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
                                                <label>Reference</label>
                                                <input type="text" class="form-control" name="reference[]">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Notes</label>
                                                <input type="text" class="form-control" name="notes[]">
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

                            <div class="form-group text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Record Delivery
                                </button>
                                <a href="inventory.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
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
    $(document).ready(function() {
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
                                <label>Reference</label>
                                <input type="text" class="form-control" name="reference[]">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Notes</label>
                                <input type="text" class="form-control" name="notes[]">
                                <button type="button" class="btn btn-sm btn-remove-item mt-1" onclick="removeItem(this)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#deliveryItems').append(newItem);
        });
    });
    
    function removeItem(button) {
        $(button).closest('.delivery-item').remove();
    }
</script>
</body>
</html>