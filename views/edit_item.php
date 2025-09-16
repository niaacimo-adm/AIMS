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
$item = null;

// Get item ID from URL
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get categories for dropdown
$categories = [];
$category_query = "SELECT id, name FROM categories ORDER BY name";
$category_result = $db->query($category_query);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
}

// Fetch item data
if ($item_id > 0) {
    $query = "SELECT * FROM items WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        $error = "Item not found.";
    }
} else {
    $error = "Invalid item ID.";
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $item) {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $unit_of_measure = trim($_POST['unit_of_measure']);
    $min_stock_level = intval($_POST['min_stock_level']);
    $category_id = intval($_POST['category_id']);
    
    // Stock adjustment
    $adjustment_type = $_POST['adjustment_type'] ?? '';
    $adjustment_quantity = intval($_POST['adjustment_quantity'] ?? 0);
    $adjustment_reference = trim($_POST['adjustment_reference'] ?? '');
    $adjustment_notes = trim($_POST['adjustment_notes'] ?? '');

    // Validate required fields
    if (empty($name)) {
        $error = "Item name is required.";
    } elseif ($min_stock_level < 0) {
        $error = "Minimum stock level cannot be negative.";
    } elseif ($adjustment_quantity < 0) {
        $error = "Adjustment quantity cannot be negative.";
    } else {
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Update item details
            $update_query = "UPDATE items SET category_id = ?, name = ?, description = ?, 
                           unit_of_measure = ?, min_stock_level = ?, updated_at = CURRENT_TIMESTAMP 
                           WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bind_param("isssii", $category_id, $name, $description, 
                                   $unit_of_measure, $min_stock_level, $item_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Process stock adjustment if provided
            if (!empty($adjustment_type) && $adjustment_quantity > 0) {
                // Record stock movement
                $movement_query = "INSERT INTO stock_movements (item_id, movement_type, quantity, 
                                 reference, notes) VALUES (?, ?, ?, ?, ?)";
                $movement_stmt = $db->prepare($movement_query);
                $movement_stmt->bind_param("isiss", $item_id, $adjustment_type, $adjustment_quantity,
                                         $adjustment_reference, $adjustment_notes);
                $movement_stmt->execute();
                $movement_stmt->close();
                
                // Update current stock
                $new_stock = $item['current_stock'];
                if ($adjustment_type == 'in') {
                    $new_stock += $adjustment_quantity;
                } elseif ($adjustment_type == 'out') {
                    $new_stock -= $adjustment_quantity;
                    if ($new_stock < 0) $new_stock = 0;
                }
                
                $stock_query = "UPDATE items SET current_stock = ? WHERE id = ?";
                $stock_stmt = $db->prepare($stock_query);
                $stock_stmt->bind_param("ii", $new_stock, $item_id);
                $stock_stmt->execute();
                $stock_stmt->close();
                
                // Refresh item data
                $refresh_query = "SELECT * FROM items WHERE id = ?";
                $refresh_stmt = $db->prepare($refresh_query);
                $refresh_stmt->bind_param("i", $item_id);
                $refresh_stmt->execute();
                $result = $refresh_stmt->get_result();
                $item = $result->fetch_assoc();
                $refresh_stmt->close();
            }
            
            $db->commit();
            $success = "Item updated successfully!";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error updating item: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - Inventory Management</title>
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
        .stock-info {
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .adjustment-section {
            background: linear-gradient(120deg, #fff3cd, #ffeaa7);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
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
                        <h1>Edit Item</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="view_inventory.php">View Inventory</a></li>
                            <li class="breadcrumb-item active">Edit Item</li>
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

                <?php if ($item): ?>
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Edit Item Information</h3>
                            </div>
                            <div class="card-body">
                                <!-- Stock Information -->
                                <div class="stock-info text-center">
                                    <h4>Current Stock: <span class="badge badge-<?= 
                                        $item['current_stock'] == 0 ? 'danger' : 
                                        ($item['current_stock'] <= $item['min_stock_level'] ? 'warning' : 'success')
                                    ?>"><?= $item['current_stock'] ?></span></h4>
                                    <p class="text-muted">Minimum Stock Level: <?= $item['min_stock_level'] ?></p>
                                </div>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">Item Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($item['name']) ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="category_id">Category</label>
                                        <select class="form-control" id="category_id" name="category_id">
                                            <option value="0">-- Select Category --</option>
                                            <?php foreach ($categories as $id => $category_name): ?>
                                                <option value="<?= $id ?>" <?= $item['category_id'] == $id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category_name) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3"><?= htmlspecialchars($item['description']) ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="unit_of_measure">Unit of Measure</label>
                                                <input type="text" class="form-control" id="unit_of_measure" 
                                                       name="unit_of_measure" value="<?= htmlspecialchars($item['unit_of_measure']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="min_stock_level">Minimum Stock Level</label>
                                                <input type="number" class="form-control" id="min_stock_level" 
                                                       name="min_stock_level" value="<?= $item['min_stock_level'] ?>" min="0">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stock Adjustment Section -->
                                    <div class="adjustment-section">
                                        <h5>Stock Adjustment</h5>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="adjustment_type">Type</label>
                                                    <select class="form-control" id="adjustment_type" name="adjustment_type">
                                                        <option value="">-- Select --</option>
                                                        <option value="in">Stock In</option>
                                                        <option value="out">Stock Out</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="adjustment_quantity">Quantity</label>
                                                    <input type="number" class="form-control" id="adjustment_quantity" 
                                                           name="adjustment_quantity" value="0" min="0">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="adjustment_reference">Reference</label>
                                                    <input type="text" class="form-control" id="adjustment_reference" 
                                                           name="adjustment_reference" placeholder="Reference number">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="adjustment_notes">Notes</label>
                                            <textarea class="form-control" id="adjustment_notes" name="adjustment_notes" 
                                                      rows="2" placeholder="Adjustment notes"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Update Item
                                        </button>
                                        <a href="view_inventory.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-arrow-left"></i> Back to Inventory
                                        </a>
                                        <a href="stock_movements.php?item_id=<?= $item['id'] ?>" class="btn btn-info btn-lg">
                                            <i class="fas fa-history"></i> View History
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4>Item not found</h4>
                        <a href="view_inventory.php" class="btn btn-primary">Back to Inventory</a>
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