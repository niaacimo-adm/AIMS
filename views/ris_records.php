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

// Fetch RIS records
$ris_records = [];
$ris_query = "SELECT r.*, u.user as created_by_name, COUNT(ri.id) as item_count 
             FROM ris_records r 
             LEFT JOIN users u ON r.created_by = u.id 
             LEFT JOIN ris_items ri ON r.id = ri.ris_id 
             GROUP BY r.id 
             ORDER BY r.created_at DESC";
$ris_result = $db->query($ris_query);
if ($ris_result) {
    while ($row = $ris_result->fetch_assoc()) {
        $ris_records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS Records - Inventory Management</title>
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
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
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
                        <h1>RIS Records</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">RIS Records</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success">RIS created successfully!</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Requisition and Issue Slip Records</h3>
                        <div class="card-tools">
                            <a href="delivery_entry.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Delivery Management
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="risTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>RIS Number</th>
                                        <th>Requisition Office</th>
                                        <th>Purpose</th>
                                        <th>Requested By</th>
                                        <th>Items</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ris_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['ris_number']) ?></td>
                                        <td><?= htmlspecialchars($record['requisition_office']) ?></td>
                                        <td><?= htmlspecialchars($record['purpose']) ?></td>
                                        <td><?= htmlspecialchars($record['requested_by']) ?></td>
                                        <td class="text-center"><?= $record['item_count'] ?></td>
                                        <td><?= htmlspecialchars($record['created_by_name']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($record['created_at'])) ?></td>
                                        <td>
                                            <a href="ris_view.php?id=<?= $record['id'] ?>" class="btn btn-info btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
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

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('#risTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[6, 'desc']]
        });
    });
</script>
</body>
</html>