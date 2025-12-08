<?php
// queue_counters.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set theme for this module
$_SESSION['current_theme'] = 'queue';

// Get database connection
$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Section/Unit Counters | Queue Management System</title>
    
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_queue.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Section/Unit Counters Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="queue.php">Queue</a></li>
                            <li class="breadcrumb-item active">Counters</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Sections</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Section</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $query = "SELECT s.*, 
                                                 (SELECT COUNT(*) FROM visitor_queue 
                                                  WHERE section_id = s.section_id 
                                                  AND DATE(time_in) = CURDATE()) as today_count
                                                 FROM section s 
                                                 WHERE s.office_id = 1 
                                                 ORDER BY s.section_name";
                                        $result = $db->query($query);
                                        
                                        while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['section_name']) ?></td>
                                            <td><span class="badge badge-dark"><?= $row['section_code'] ?></span></td>
                                            <td>
                                                <span class="badge badge-info">Active</span>
                                                <small class="text-muted d-block">Today: <?= $row['today_count'] ?> visitors</small>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Units</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Code</th>
                                            <th>Section</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $query = "SELECT u.*, s.section_name,
                                                 (SELECT COUNT(*) FROM visitor_queue 
                                                  WHERE unit_id = u.unit_id 
                                                  AND DATE(time_in) = CURDATE()) as today_count
                                                 FROM unit_section u
                                                 LEFT JOIN section s ON u.section_id = s.section_id
                                                 ORDER BY u.unit_name";
                                        $result = $db->query($query);
                                        
                                        while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['unit_name']) ?></td>
                                            <td><span class="badge badge-dark"><?= $row['unit_code'] ?></span></td>
                                            <td><?= htmlspecialchars($row['section_name']) ?></td>
                                            <td>
                                                <span class="badge badge-info">Active</span>
                                                <small class="text-muted d-block">Today: <?= $row['today_count'] ?> visitors</small>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Counter Statistics</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Total Visitors Today</span>
                                                <span class="info-box-number" id="totalToday">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Waiting</span>
                                                <span class="info-box-number" id="totalWaiting">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-primary"><i class="fas fa-user-check"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Serving</span>
                                                <span class="info-box-number" id="totalServing">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Completed</span>
                                                <span class="info-box-number" id="totalCompleted">0</span>
                                            </div>
                                        </div>
                                    </div>
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
    updateStatistics();
    setInterval(updateStatistics, 30000); // Update every 30 seconds
    
    function updateStatistics() {
        $.ajax({
            url: '../includes/queue_ajax.php?action=get_queue_status',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    // Get additional statistics
                    $.ajax({
                        url: '../includes/queue_stats.php?action=get_daily_stats',
                        type: 'GET',
                        success: function(stats) {
                            if (stats.success) {
                                $('#totalToday').text(stats.total_today);
                                $('#totalWaiting').text(stats.waiting);
                                $('#totalServing').text(stats.serving);
                                $('#totalCompleted').text(stats.completed);
                            }
                        }
                    });
                }
            }
        });
    }
});
</script>
</body>
</html>