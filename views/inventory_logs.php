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

// Get activity logs with filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$action_type = $_GET['action_type'] ?? '';

$query = "SELECT sm.*, i.name as item_name, sm.created_at as action_date 
          FROM stock_movements sm 
          JOIN items i ON sm.item_id = i.id 
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($date_from)) {
    $query .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($action_type) && $action_type != 'all') {
    $query .= " AND sm.movement_type = ?";
    $params[] = $action_type;
    $types .= 's';
}

$query .= " ORDER BY sm.created_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Activity Logs - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
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
                        <h1>Inventory Activity Logs</h1>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Activity Logs</h3>
                        <div class="card-tools">
                            <form method="GET" class="form-inline">
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
                                        <a href="inventory_logs.php" class="btn btn-secondary">
                                            <i class="fas fa-sync"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Item</th>
                                        <th>Action Type</th>
                                        <th>Quantity</th>
                                        <th>Reference</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No activity logs found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($log['action_date'])) ?></td>
                                                <td><?= htmlspecialchars($log['item_name']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $log['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                                        <?= strtoupper($log['movement_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $log['quantity'] ?></td>
                                                <td><?= htmlspecialchars($log['reference']) ?></td>
                                                <td><?= htmlspecialchars($log['notes']) ?></td>
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
</body>
</html>