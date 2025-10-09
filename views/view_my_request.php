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
    $error = "Supply request not found or you don't have permission to view it.";
}

// Fetch supply request items
$items = [];
if ($request_id > 0 && $request) {
    $items_query = "SELECT * FROM supply_request_items WHERE request_id = ? ORDER BY id";
    $stmt = $db->prepare($items_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View My Supply Request - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .item-row {
            transition: background-color 0.3s;
        }
        .item-row:hover {
            background-color: #f8f9fa;
        }
        .card-header {
            background: linear-gradient(120deg, #17a2b8, #138496);
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
                        <h1>View My Supply Request</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="my_supply_requests.php">My Requests</a></li>
                            <li class="breadcrumb-item active">View Request</li>
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

                <?php if ($request): ?>
                <div class="row">
                    <div class="col-md-12">
                        <!-- Request Information Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Request Information</h3>
                                <div class="card-tools">
                                    <span class="badge badge-<?= 
                                        $request['status'] == 'approved' ? 'success' : 
                                        ($request['status'] == 'rejected' ? 'danger' : 'warning') 
                                    ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Request ID:</strong><br>
                                        SR-<?= str_pad($request['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Employee Name:</strong><br>
                                        <?= htmlspecialchars($request['employee_name']) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Section:</strong><br>
                                        <?= htmlspecialchars($request['section']) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Request Date:</strong><br>
                                        <?= date('M j, Y', strtotime($request['request_date'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Requested Items -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Requested Items</h3>
                                <div class="card-tools">
                                    <span class="badge badge-info"><?= count($items) ?> items</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($items)): ?>
                                    <div class="alert alert-info">No items found in this request.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Supply Name</th>
                                                    <th>Description</th>
                                                    <th>Unit</th>
                                                    <th>Quantity</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $item): ?>
                                                <tr class="item-row">
                                                    <td><?= htmlspecialchars($item['supply_name']) ?></td>
                                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                                    <td><?= $item['quantity'] ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= 
                                                            $item['status'] == 'approved' ? 'success' : 
                                                            ($item['status'] == 'rejected' ? 'danger' : 'warning') 
                                                        ?> status-badge">
                                                            <?= ucfirst($item['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="card">
                            <div class="card-body text-center">
                                <a href="my_supply_requests.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to My Requests
                                </a>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <a href="edit_supply_request.php?id=<?= $request['id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit Request
                                    </a>
                                <?php endif; ?>
                                <a href="request_supplies.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Create New Request
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Supply request not found or you don't have permission to view it.
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