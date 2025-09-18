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

// Get search parameters
$category_filter = $_GET['category'] ?? '';

$query = "SELECT 
            i.*, 
            c.name as category_name,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id 
          LEFT JOIN stock_movements sm ON i.id = sm.item_id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($category_filter) && $category_filter != 'all') {
    $query .= " AND i.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

// Group by item to aggregate delivery quantities
$query .= " GROUP BY i.id ORDER BY i.name";

// Get categories for filter
$categories = [];
$category_query = "SELECT id, name FROM categories ORDER BY name";
$category_result = $db->query($category_query);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
}

// Execute main query
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .stock-low {
            background-color: #ffcccc !important;
        }
        .stock-very-low {
            background-color: #ff9999 !important;
            font-weight: bold;
        }
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
                        <h1>View Inventory</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="new_stock.php" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Add New Item
                        </a>
                        <a href="delivery_entry.php" class="btn btn-primary">
                            <i class="fas fa-truck"></i> Delivery Entry
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Inventory Items</h3>
                        <div class="card-tools">
                            <form method="GET" class="form-inline">
                                <div class="input-group input-group-sm">
                                    <select class="form-control" name="category" style="margin-right: 10px;">
                                        <option value="all">All Categories</option>
                                        <?php foreach ($categories as $id => $name): ?>
                                            <option value="<?= $id ?>" <?= $category_filter == $id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="inventoryTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Current Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No items found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): 
                                            $stock_class = '';
                                            if ($item['current_stock'] == 0) {
                                                $stock_class = 'stock-very-low';
                                            } 
                                        ?>
                                            <tr class="<?= $stock_class ?>">
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td><?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($item['unit_of_measure'] ?? '') ?></td>
                                                <td><?= $item['current_stock'] ?></td>
                                                <td>
                                                    <?php if ($item['current_stock'] == 0): ?>
                                                        <span class="badge badge-danger">Out of Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="stock_movements.php?item_id=<?= $item['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-history"></i> History
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
        // Initialize DataTable for inventory
        $('#inventoryTable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "order": [[0, "asc"]],
            "buttons": ["copy", "csv", "excel", "pdf", "print"],
            "pageLength": 25,
            "dom": 'Bfrtip'
        }).buttons().container().appendTo('#inventoryTable_wrapper .col-md-6:eq(0)');
    });
</script>
</body>
</html>