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

// Get item ID from URL
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

// Get item information
$item = null;
if ($item_id > 0) {
    $query = "SELECT * FROM items WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
}

// Get stock movements with additional details including RIS information
$movements = [];
if ($item_id > 0) {
    $query = "SELECT sm.*, i.name as item_name, i.unit_of_measure, sm.unit_cost,
                     COALESCE(r.ris_number, iar.iar_number, sm.reference) as reference_display,
                     CASE 
                         WHEN r.ris_number IS NOT NULL THEN CONCAT('RIS: ', r.ris_number)
                         WHEN iar.iar_number IS NOT NULL THEN CONCAT('IAR: ', iar.iar_number)
                         ELSE sm.reference
                     END as display_reference
              FROM stock_movements sm 
              JOIN items i ON sm.item_id = i.id 
              LEFT JOIN ris_records r ON sm.reference = r.ris_number
              LEFT JOIN iar_records iar ON sm.reference = iar.po_number
              WHERE sm.item_id = ? 
              ORDER BY sm.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movements = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$action_type = $_GET['action_type'] ?? '';

// Build filtered query if filters are applied
if (!empty($date_from) || !empty($date_to) || !empty($action_type)) {
    $query = "SELECT sm.*, i.name as item_name, i.unit_of_measure
              FROM stock_movements sm 
              JOIN items i ON sm.item_id = i.id 
              WHERE sm.item_id = ?";
    
    $params = [$item_id];
    $types = "i";
    
    if (!empty($date_from)) {
        $query .= " AND DATE(sm.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(sm.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    if (!empty($action_type) && $action_type != 'all') {
        $query .= " AND sm.movement_type = ?";
        $params[] = $action_type;
        $types .= "s";
    }
    
    $query .= " ORDER BY sm.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $movements = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modal-xl {
            max-width: 90%;
        }
        .item-header {
            background: linear-gradient(120deg, #17a2b8, #138496);
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
            background: linear-gradient(120deg, #17a2b8, #138496);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .badge-in {
            background: linear-gradient(120deg, #28a745, #20c997);
        }
        .badge-out {
            background: linear-gradient(120deg, #dc3545, #c82333);
        }
        .filter-form .input-group {
            width: auto;
        }
        .filter-form .form-control {
            margin-right: 5px;
        }
        .info-box {
            cursor: default;
            margin-bottom: 15px;
        }
        .stock-status {
            font-size: 1.2rem;
            font-weight: bold;
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
                        <h1>Stock Movements</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="view_inventory.php">View Inventory</a></li>
                            <li class="breadcrumb-item active">Stock Movements</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if ($item): ?>
                <!-- Item Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Item: <?= htmlspecialchars($item['name']) ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Item Name</span>
                                        <span class="info-box-number"><?= htmlspecialchars($item['name']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Description</span>
                                        <span class="info-box-number"><?= htmlspecialchars($item['description'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Unit of Measure</span>
                                        <span class="info-box-number"><?= htmlspecialchars($item['unit_of_measure'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Current Stock</span>
                                        <span class="info-box-number stock-status">
                                            <span class="badge badge-<?= 
                                                $item['current_stock'] == 0 ? 'danger' : 'success'
                                            ?>"><?= $item['current_stock'] ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Actions</span>
                                        <div class="info-box-number">
                                            <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit Item
                                            </a>
                                            <a href="delivery_entry.php" class="btn btn-success btn-sm">
                                                <i class="fas fa-truck"></i> New Delivery
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Stock Movement History</h3>
                        <div class="card-tools">
                            <form method="GET" class="form-inline filter-form">
                                <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                <div class="input-group input-group-sm">
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                                    <select class="form-control" name="action_type">
                                        <option value="all">All Actions</option>
                                        <option value="in" <?= $action_type == 'in' ? 'selected' : '' ?>>Stock In</option>
                                        <option value="out" <?= $action_type == 'out' ? 'selected' : '' ?>>Stock Out</option>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="stock_movements.php?item_id=<?= $item_id ?>" class="btn btn-secondary">
                                            <i class="fas fa-sync"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="movementsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Action Type</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                        <th>Reference</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($movements)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No stock movements found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $total_in = 0;
                                        $total_out = 0;
                                        $total_cost = 0;
                                        foreach ($movements as $movement): 
                                            $item_cost = $movement['quantity'] * ($movement['unit_cost'] ?? 0);
                                            if ($movement['movement_type'] == 'in') {
                                                $total_in += $movement['quantity'];
                                                $total_cost += $item_cost;
                                            } else {
                                                $total_out += $movement['quantity'];
                                            }
                                        ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($movement['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                                        <?= strtoupper($movement['movement_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $movement['quantity'] ?> <?= htmlspecialchars($movement['unit_of_measure']) ?></td>
                                                <td class="text-right"><?= isset($movement['unit_cost']) ? number_format($movement['unit_cost'], 2) : 'N/A' ?></td>
                                                <td class="text-right"><?= isset($movement['unit_cost']) ? number_format($item_cost, 2) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($movement['reference']) ?></td>
                                                <td><?= htmlspecialchars($movement['notes']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($movements)): ?>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="2" class="text-right"><strong>Totals:</strong></td>
                                        <td><strong>IN: <?= number_format($total_in) ?> | OUT: <?= number_format($total_out) ?></strong></td>
                                        <td colspan="2" class="text-right"><strong>Total Cost: ₱<?= number_format($total_cost, 2) ?></strong></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4>Item not found</h4>
                        <p>Please select a valid item from the inventory.</p>
                        <a href="view_inventory.php" class="btn btn-primary">Back to Inventory</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Initialize DataTable for movements
        $('#movementsTable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "order": [[0, "desc"]],
            "buttons": ["copy", "csv", "excel", "pdf", "print"],
            "pageLength": 25,
            "dom": 'Bfrtip',
            "columns": [
                null,
                null,
                null,
                { "className": "text-right" },
                { "className": "text-right" },
                null,
                null
            ]
        }).buttons().container().appendTo('#movementsTable_wrapper .col-md-6:eq(0)');
    });
</script>
</body>
</html>