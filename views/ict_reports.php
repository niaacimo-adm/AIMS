<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasPermission('view_ict_reports')) {
    header('Location: ../unauthorized.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get statistics for reports
$stats = [];

// Equipment statistics
$equipment_stats_query = "
    SELECT 
        status,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ict_equipment)), 2) as percentage
    FROM ict_equipment 
    GROUP BY status
    ORDER BY count DESC";
$equipment_stats_result = $db->query($equipment_stats_query);
$equipment_stats = $equipment_stats_result->fetch_all(MYSQLI_ASSOC);

// Category statistics
$category_stats_query = "
    SELECT 
        c.category_name,
        COUNT(e.equipment_id) as equipment_count,
        ROUND((COUNT(e.equipment_id) * 100.0 / (SELECT COUNT(*) FROM ict_equipment)), 2) as percentage
    FROM ict_equipment_categories c
    LEFT JOIN ict_equipment e ON c.category_id = e.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY equipment_count DESC";
$category_stats_result = $db->query($category_stats_query);
$category_stats = $category_stats_result->fetch_all(MYSQLI_ASSOC);

// Maintenance statistics
$maintenance_stats_query = "
    SELECT 
        status,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ict_maintenance)), 2) as percentage
    FROM ict_maintenance 
    GROUP BY status
    ORDER BY count DESC";
$maintenance_stats_result = $db->query($maintenance_stats_query);
$maintenance_stats = $maintenance_stats_result ? $maintenance_stats_result->fetch_all(MYSQLI_ASSOC) : [];

// Monthly assignment trends
$monthly_trends_query = "
    SELECT 
        DATE_FORMAT(assigned_date, '%Y-%m') as month,
        COUNT(*) as assignments
    FROM ict_equipment 
    WHERE assigned_date IS NOT NULL 
    AND assigned_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(assigned_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12";
$monthly_trends_result = $db->query($monthly_trends_query);
$monthly_trends = $monthly_trends_result->fetch_all(MYSQLI_ASSOC);

// Top assigned employees
$top_employees_query = "
    SELECT 
        e.first_name,
        e.last_name,
        COUNT(ie.equipment_id) as equipment_count
    FROM employee e
    JOIN ict_equipment ie ON e.emp_id = ie.assigned_to
    WHERE ie.assigned_to IS NOT NULL
    GROUP BY e.emp_id, e.first_name, e.last_name
    ORDER BY equipment_count DESC
    LIMIT 10";
$top_employees_result = $db->query($top_employees_query);
$top_employees = $top_employees_result->fetch_all(MYSQLI_ASSOC);

// Maintenance completion time
$completion_time_query = "
    SELECT 
        AVG(DATEDIFF(resolved_date, report_date)) as avg_days,
        MIN(DATEDIFF(resolved_date, report_date)) as min_days,
        MAX(DATEDIFF(resolved_date, report_date)) as max_days
    FROM ict_maintenance 
    WHERE status = 'Completed' 
    AND resolved_date IS NOT NULL";
$completion_time_result = $db->query($completion_time_query);
$completion_time = $completion_time_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Reports & Analytics - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ict.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>ICT Reports & Analytics</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item active">Reports</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= array_sum(array_column($equipment_stats, 'count')) ?></h3>
                                <p>Total Equipment</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $equipment_stats[array_search('Assigned', array_column($equipment_stats, 'status'))]['count'] ?? 0 ?></h3>
                                <p>Assigned Equipment</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= array_sum(array_column($maintenance_stats, 'count')) ?></h3>
                                <p>Total Maintenance Requests</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-tools"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?= round($completion_time['avg_days'] ?? 0, 1) ?> days</h3>
                                <p>Avg. Maintenance Time</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Equipment Status Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Equipment Status Distribution</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="equipmentStatusChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Category Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Equipment by Category</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Maintenance Status -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Maintenance Request Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceStatusChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Assignments -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Monthly Equipment Assignments</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyTrendsChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics Tables -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top Employees with Most Equipment</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Equipment Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_employees as $employee): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                                                    <td><?= $employee['equipment_count'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Maintenance Performance</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Metric</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Average Completion Time</td>
                                                <td><?= round($completion_time['avg_days'] ?? 0, 1) ?> days</td>
                                            </tr>
                                            <tr>
                                                <td>Fastest Completion</td>
                                                <td><?= $completion_time['min_days'] ?? 0 ?> days</td>
                                            </tr>
                                            <tr>
                                                <td>Longest Completion</td>
                                                <td><?= $completion_time['max_days'] ?? 0 ?> days</td>
                                            </tr>
                                            <tr>
                                                <td>Completed Requests</td>
                                                <td><?= $maintenance_stats[array_search('Completed', array_column($maintenance_stats, 'status'))]['count'] ?? 0 ?></td>
                                            </tr>
                                            <tr>
                                                <td>Pending Requests</td>
                                                <td><?= $maintenance_stats[array_search('Pending', array_column($maintenance_stats, 'status'))]['count'] ?? 0 ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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

<script>
$(document).ready(function() {


    // Equipment Status Chart
    const equipmentStatusCtx = document.getElementById('equipmentStatusChart').getContext('2d');
    const equipmentStatusChart = new Chart(equipmentStatusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($equipment_stats, 'status')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($equipment_stats, 'count')) ?>,
                backgroundColor: [
                    '#28a745', // Available - Green
                    '#007bff', // Assigned - Blue
                    '#6c757d', // Maintenance - Gray
                    '#ffc107', // Reserved - Yellow
                    '#dc3545'  // Retired - Red
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Category Distribution Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($category_stats, 'category_name')) ?>,
            datasets: [{
                label: 'Equipment Count',
                data: <?= json_encode(array_column($category_stats, 'equipment_count')) ?>,
                backgroundColor: '#17a2b8',
                borderColor: '#138496',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Maintenance Status Chart
    const maintenanceStatusCtx = document.getElementById('maintenanceStatusChart').getContext('2d');
    const maintenanceStatusChart = new Chart(maintenanceStatusCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($maintenance_stats, 'status')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($maintenance_stats, 'count')) ?>,
                backgroundColor: [
                    '#ffc107', // Pending - Yellow
                    '#007bff', // In Progress - Blue
                    '#28a745', // Completed - Green
                    '#dc3545'  // Cancelled - Red
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Monthly Trends Chart
    const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    const monthlyTrendsChart = new Chart(monthlyTrendsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthly_trends, 'month')) ?>,
            datasets: [{
                label: 'Assignments',
                data: <?= json_encode(array_column($monthly_trends, 'assignments')) ?>,
                backgroundColor: 'rgba(23, 162, 184, 0.2)',
                borderColor: '#17a2b8',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>