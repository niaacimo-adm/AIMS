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

// Get stock movements with additional details
$movements = [];
if ($item_id > 0) {
    $query = "SELECT sm.*, i.name as item_name, i.unit_of_measure, sm.unit_cost
              FROM stock_movements sm 
              JOIN items i ON sm.item_id = i.id 
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
        .item-header {
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                <div class="item-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?= htmlspecialchars($item['name']) ?></h4>
                            <p class="mb-1"><strong>Description:</strong> <?= htmlspecialchars($item['description'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Unit:</strong> <?= htmlspecialchars($item['unit_of_measure'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6 text-right">
                            <h4>Current Stock: <span class="badge badge-<?= 
                                $item['current_stock'] == 0 ? 'danger' : 
                                ($item['current_stock'] <= $item['min_stock_level'] ? 'warning' : 'success')
                            ?>"><?= $item['current_stock'] ?></span></h4>
                            <p class="mb-1"><strong>Minimum Level:</strong> <?= $item['min_stock_level'] ?></p>
                            <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit Item
                            </a>
                            <a href="delivery_entry.php" class="btn btn-success btn-sm">
                                <i class="fas fa-truck"></i> New Delivery
                            </a>
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
                                        <?php foreach ($movements as $movement): 
                                            $total_cost = $movement['quantity'] * ($movement['unit_cost'] ?? 0);
                                        ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($movement['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                                        <?= strtoupper($movement['movement_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $movement['quantity'] ?> <?= htmlspecialchars($movement['unit_of_measure']) ?></td>
                                                <td><?= isset($movement['unit_cost']) ? number_format($movement['unit_cost'], 2) : 'N/A' ?></td>
                                                <td><?= isset($movement['unit_cost']) ? number_format($total_cost, 2) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($movement['reference']) ?></td>
                                                <td><?= htmlspecialchars($movement['notes']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
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