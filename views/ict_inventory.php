<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';


$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];
$query = "SELECT status, COUNT(*) as count FROM ict_equipment GROUP BY status";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}

// Get total equipment count
$total_equipment = array_sum($stats);
$assigned_equipment = $stats['Assigned'] ?? 0;
$available_equipment = $stats['Available'] ?? 0;

// Get recent assignments
$recent_assignments = [];
$query = "SELECT e.*, emp.first_name, emp.last_name 
          FROM ict_equipment e 
          LEFT JOIN employee emp ON e.assigned_to = emp.emp_id 
          WHERE e.assigned_to IS NOT NULL 
          ORDER BY e.assigned_date DESC 
          LIMIT 5";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_assignments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Equipment Inventory - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ict.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>ICT Equipment Inventory</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">ICT Inventory</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= $total_equipment ?></h3>
                                <p>Total Equipment</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <a href="ict_equipment.php" class="small-box-footer">View All <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $assigned_equipment ?></h3>
                                <p>Assigned Equipment</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <a href="ict_equipment.php?status=Assigned" class="small-box-footer">View Assigned <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= $available_equipment ?></h3>
                                <p>Available Equipment</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <a href="ict_equipment.php?status=Available" class="small-box-footer">View Available <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?= $stats['Under Maintenance'] ?? 0 ?></h3>
                                <p>Under Maintenance</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <a href="ict_maintenance.php" class="small-box-footer">View Maintenance <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Assignments -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Equipment Assignments</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Asset Tag</th>
                                                <th>Equipment</th>
                                                <th>Assigned To</th>
                                                <th>Date Assigned</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_assignments)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No recent assignments</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_assignments as $equipment): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($equipment['asset_tag']) ?></td>
                                                        <td><?= htmlspecialchars($equipment['equipment_name']) ?></td>
                                                        <td><?= htmlspecialchars($equipment['first_name'] . ' ' . $equipment['last_name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($equipment['assigned_date'])) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $equipment['status'] == 'Assigned' ? 'success' : 'warning' ?>">
                                                                <?= $equipment['status'] ?>
                                                            </span>
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

                    <!-- Quick Actions -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <?php if (hasPermission('manage_ict_equipment')): ?>
                                <a href="ict_equipment_add.php" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-plus mr-2"></i>Add New Equipment
                                </a>
                                <?php endif; ?>
                                <a href="ict_my_equipment.php" class="btn btn-info btn-block mb-2">
                                    <i class="fas fa-desktop mr-2"></i>View My Equipment
                                </a>
                                <a href="ict_equipment.php" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-list mr-2"></i>Browse All Equipment
                                </a>
                                <a href="ict_reports.php" class="btn btn-warning btn-block">
                                    <i class="fas fa-chart-bar mr-2"></i>View Reports
                                </a>
                            </div>
                        </div>

                        <!-- Status Overview -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Equipment Status Overview</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" width="100%" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<!-- Add this to ICT pages -->
<script>
$(document).ready(function() {
    // Set and maintain ICT theme
    const currentTheme = localStorage.getItem('currentTheme');
    if (currentTheme !== 'ict') {
        localStorage.setItem('currentTheme', 'ict');
    }
    document.cookie = 'current_module=ict; path=/; max-age=300';
    
    // Apply theme immediately
    const theme = 'linear-gradient(135deg, #17a2b8, #138496)';
    $('.main-header').css('background', theme);
    $('#mainFooter').css('background', theme);
    
    // Update theme classes
    $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
});
</script>
<script>
// Equipment Status Chart
const ctx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Assigned', 'Available', 'Under Maintenance', 'Retired', 'Lost'],
        datasets: [{
            data: [
                <?= $stats['Assigned'] ?? 0 ?>,
                <?= $stats['Available'] ?? 0 ?>,
                <?= $stats['Under Maintenance'] ?? 0 ?>,
                <?= $stats['Retired'] ?? 0 ?>,
                <?= $stats['Lost'] ?? 0 ?>
            ],
            backgroundColor: [
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d',
                '#343a40'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
            position: 'bottom'
        }
    }
});
</script>
</body>
</html>