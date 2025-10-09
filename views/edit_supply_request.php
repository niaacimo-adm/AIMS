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

// Get current employee ID
$employee_id = $_SESSION['emp_id'] ?? 0;

$error = $success = "";

// Get request ID from URL
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch supply request details and verify ownership
$request = [];
$request_query = "SELECT * FROM supply_requests WHERE id = ? AND employee_id = ?";
$stmt = $db->prepare($request_query);
$stmt->bind_param("ii", $request_id, $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    $error = "Supply request not found or you don't have permission to edit it.";
} elseif ($request['status'] != 'pending') {
    $error = "You can only edit requests that are still pending.";
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

// Fetch existing items
$existing_items = [];
if ($request_id > 0 && $request) {
    $items_query = "SELECT * FROM supply_request_items WHERE request_id = ? ORDER BY id";
    $stmt = $db->prepare($items_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existing_items[] = $row;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_request'])) {
    $request_date = trim($_POST['request_date'] ?? '');
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $units = $_POST['unit'] ?? [];

    // Validate required fields
    if (empty($request_date)) {
        $error = "Request date is required.";
    } elseif (empty($item_ids) || empty($quantities)) {
        $error = "At least one supply item is required.";
    } else {
        $db->begin_transaction();
        
        try {
            // Update supply request record
            $update_request_query = "UPDATE supply_requests SET request_date = ? WHERE id = ?";
            $request_stmt = $db->prepare($update_request_query);
            $request_stmt->bind_param("si", $request_date, $request_id);
            $request_stmt->execute();
            $request_stmt->close();
            
            // Delete existing items
            $delete_items_query = "DELETE FROM supply_request_items WHERE request_id = ?";
            $delete_stmt = $db->prepare($delete_items_query);
            $delete_stmt->bind_param("i", $request_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Add new items
            foreach ($item_ids as $index => $item_id) {
                $quantity = intval($quantities[$index]);
                $description = $descriptions[$index] ?? '';
                $unit = $units[$index] ?? '';
                
                if ($item_id > 0 && $quantity > 0) {
                    $item_query = "INSERT INTO supply_request_items (request_id, supply_name, description, unit, quantity) 
                                VALUES (?, ?, ?, ?, ?)";
                    $item_stmt = $db->prepare($item_query);
                    $item_stmt->bind_param("isssi", $request_id, $item_id, $description, $unit, $quantity);
                    $item_stmt->execute();
                    $item_stmt->close();
                }
            }
            
            $db->commit();
            $success = "Supply request updated successfully!";
            
            // Refresh data
            $stmt = $db->prepare($request_query);
            $stmt->bind_param("ii", $request_id, $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            
            $stmt = $db->prepare($items_query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_items = [];
            while ($row = $result->fetch_assoc()) {
                $existing_items[] = $row;
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error updating supply request: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supply Request - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(120deg, #ffc107, #e0a800);
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
                        <h1>Edit Supply Request</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="my_supply_requests.php">My Requests</a></li>
                            <li class="breadcrumb-item active">Edit Request</li>
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

                <?php if ($request && $request['status'] == 'pending'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Edit Supply Request - SR-<?= str_pad($request['id'], 4, '0', STR_PAD_LEFT) ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="supplyRequestForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="employee_name">Employee Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($request['employee_name']) ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="section">Section/Department</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($request['section']) ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="request_date">Request Date *</label>
                                        <input type="date" class="form-control" id="request_date" name="request_date" 
                                            value="<?= htmlspecialchars($request['request_date']) ?>" required>
                                    </div>
                                </div>
                            </div>

                            <h4 class="mt-4 mb-3">Requested Supplies</h4>
                            <div id="supplyItems">
                                <?php foreach ($existing_items as $index => $item): ?>
                                <div class="supply-item">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Supply Name *</label>
                                                <select class="form-control supply-select" name="item_id[]" required>
                                                    <option value="">-- Select Supply --</option>
                                                    <?php foreach ($supplies as $id => $supply): ?>
                                                        <option value="<?= $id ?>" 
                                                            <?= ($item['supply_name'] == $supply['name']) ? 'selected' : '' ?>
                                                            data-description="<?= htmlspecialchars($supply['description']) ?>" 
                                                            data-unit="<?= htmlspecialchars($supply['unit_of_measure']) ?>">
                                                            <?= htmlspecialchars($supply['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <input type="text" class="form-control item-description" name="description[]" 
                                                    value="<?= htmlspecialchars($item['description']) ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Unit *</label>
                                                <input type="text" class="form-control item-unit" name="unit[]" 
                                                    value="<?= htmlspecialchars($item['unit']) ?>" readonly required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Quantity *</label>
                                                <input type="number" class="form-control item-quantity" name="quantity[]" 
                                                    value="<?= $item['quantity'] ?>" min="1" required>
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
                                <?php endforeach; ?>
                            </div>

                            <div class="form-group">
                                <button type="button" id="addItem" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Another Item
                                </button>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" name="update_request" class="btn btn-warning btn-lg">
                                    <i class="fas fa-save"></i> Update Request
                                </button>
                                <a href="my_supply_requests.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif ($request && $request['status'] != 'pending'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You can only edit requests that are still pending. This request is already <?= $request['status'] ?>.
                    </div>
                    <div class="text-center">
                        <a href="my_supply_requests.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to My Requests
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Supply request not found or you don't have permission to edit it.
                    </div>
                    <div class="text-center">
                        <a href="my_supply_requests.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to My Requests
                        </a>
                    </div>
                <?php endif; ?>
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
        let itemCount = <?= count($existing_items) ?>;
        
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
    });

    function fillItemDetails(select) {
        const selectedOption = select.options[select.selectedIndex];
        const description = selectedOption.getAttribute('data-description');
        const unit = selectedOption.getAttribute('data-unit');
        
        const itemRow = $(select).closest('.supply-item');
        itemRow.find('.item-description').val(description || '');
        itemRow.find('.item-unit').val(unit || '');
    }

    function removeItem(button) {
        if ($('#supplyItems .supply-item').length > 1) {
            $(button).closest('.supply-item').remove();
        } else {
            alert('You must have at least one supply item.');
        }
    }

    // Initialize existing items with their details
    $(document).ready(function() {
        $('.supply-select').each(function() {
            if ($(this).val()) {
                fillItemDetails(this);
            }
        });
    });
</script>
</body>
</html>