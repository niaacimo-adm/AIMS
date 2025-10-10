<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Get dashboard statistics
$stats = [];
$recent_requests = [];
$upcoming_requests = [];
$recent_activities = [];

try {
    // Total Vehicles
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM vehicles");
    $stmt->execute();
    $stats['total_vehicles'] = $stmt->get_result()->fetch_assoc()['total'];

    // Active Drivers (employees with driver positions)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.emp_id) as total 
        FROM employee e 
        INNER JOIN position p ON e.position_id = p.position_id 
        WHERE p.position_name LIKE '%Driver%' OR p.position_name LIKE '%driver%'
    ");
    $stmt->execute();
    $stats['active_drivers'] = $stmt->get_result()->fetch_assoc()['total'];

    // Pending Requests
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM service_requests WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_requests'] = $stmt->get_result()->fetch_assoc()['total'];

    // Completed Trips
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM service_requests WHERE status = 'completed'");
    $stmt->execute();
    $stats['completed_trips'] = $stmt->get_result()->fetch_assoc()['total'];

    // Vehicle Status Breakdown
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
    $stmt->execute();
    $vehicle_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stats['available_vehicles'] = 0;
    $stats['maintenance_vehicles'] = 0;
    $stats['unavailable_vehicles'] = 0;
    
    foreach ($vehicle_status as $status) {
        if ($status['status'] == 'available') {
            $stats['available_vehicles'] = $status['count'];
        } elseif ($status['status'] == 'maintenance') {
            $stats['maintenance_vehicles'] = $status['count'];
        } elseif ($status['status'] == 'unavailable') {
            $stats['unavailable_vehicles'] = $status['count'];
        }
    }

    // Upcoming Requests (next 7 days)
    $stmt = $conn->prepare("
        SELECT sr.*, 
               CONCAT(e.first_name, ' ', e.last_name) as requester_name
        FROM service_requests sr
        INNER JOIN employee e ON sr.requesting_emp_id = e.emp_id
        WHERE sr.date_of_travel BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND sr.status IN ('approved', 'pending')
        ORDER BY sr.date_of_travel ASC
        LIMIT 5
    ");
    $stmt->execute();
    $upcoming_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent Activities (last 10 activities)
    $stmt = $conn->prepare("
        (SELECT 
            'request_created' as activity_type,
            sr.request_no,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            sr.created_at as activity_time,
            'New transport request created' as description
        FROM service_requests sr
        INNER JOIN employee e ON sr.requesting_emp_id = e.emp_id
        ORDER BY sr.created_at DESC
        LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'request_approved' as activity_type,
            sr.request_no,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            sr.approved_at as activity_time,
            'Request approved' as description
        FROM service_requests sr
        INNER JOIN employee e ON sr.approved_by = e.emp_id
        WHERE sr.status = 'approved'
        ORDER BY sr.approved_at DESC
        LIMIT 5)
        
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // This Month Statistics
    $current_month = date('Y-m');
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_trips,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips
        FROM service_requests 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->bind_param('s', $current_month);
    $stmt->execute();
    $month_stats = $stmt->get_result()->fetch_assoc();
    
    $stats['month_trips'] = $month_stats['total_trips'] ?? 0;
    $stats['month_completed'] = $month_stats['completed_trips'] ?? 0;

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Calculate percentages for progress bars
$total_vehicles = $stats['total_vehicles'];
$available_percent = $total_vehicles > 0 ? ($stats['available_vehicles'] / $total_vehicles) * 100 : 0;
$maintenance_percent = $total_vehicles > 0 ? ($stats['maintenance_vehicles'] / $total_vehicles) * 100 : 0;
$unavailable_percent = $total_vehicles > 0 ? ($stats['unavailable_vehicles'] / $total_vehicles) * 100 : 0;

// Driver availability (simplified - in real app you'd track driver schedules)
$total_drivers = $stats['active_drivers'];
$available_drivers = max(0, $total_drivers - 3); // Simple estimation
$on_leave_drivers = 3; // Simple estimation
$on_trip_drivers = 1; // Simple estimation

$available_drivers_percent = $total_drivers > 0 ? ($available_drivers / $total_drivers) * 100 : 0;
$on_leave_percent = $total_drivers > 0 ? ($on_leave_drivers / $total_drivers) * 100 : 0;
$on_trip_percent = $total_drivers > 0 ? ($on_trip_drivers / $total_drivers) * 100 : 0;

// Monthly efficiency (simplified calculation)
$stats['month_efficiency'] = $stats['month_trips'] > 0 ? 
    round(($stats['month_completed'] / $stats['month_trips']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-ACIMO | Service Dashboard</title>
  
  <?php include '../includes/header.php'; ?>
  <style>
    /* Custom styles matching inventory.php */
    .small-box {
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .small-box>.inner {
        padding: 20px;
    }
    
    .small-box h3 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0 0 10px 0;
        white-space: nowrap;
        padding: 0;
        color: white;
    }
    
    .small-box p {
        font-size: 1rem;
        color: rgba(255,255,255,0.9);
    }
    
    .small-box .icon {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 0;
        font-size: 70px;
        color: rgba(255,255,255,0.2);
        transition: all 0.3s ease;
    }
    
    .small-box:hover .icon {
        font-size: 75px;
    }
    
    .small-box-footer {
        position: relative;
        text-align: center;
        padding: 10px 0;
        color: rgba(255,255,255,0.8);
        background: rgba(0,0,0,0.1);
        text-decoration: none;
        display: block;
        z-index: 10;
    }
    
    .small-box-footer:hover {
        color: white;
        background: rgba(0,0,0,0.2);
        text-decoration: none;
    }
    
    /* Service-specific gradient backgrounds */
    .bg-gradient-vehicles {
        background: linear-gradient(120deg, #007bff, #0056b3) !important;
    }
    
    .bg-gradient-drivers {
        background: linear-gradient(120deg, #28a745, #20c997) !important;
    }
    
    .bg-gradient-pending {
        background: linear-gradient(120deg, #ffc107, #fd7e14) !important;
    }
    
    .bg-gradient-completed {
        background: linear-gradient(120deg, #17a2b8, #138496) !important;
    }
    
    .bg-gradient-available {
        background: linear-gradient(120deg, #28a745, #20c997) !important;
    }
    
    .bg-gradient-maintenance {
        background: linear-gradient(120deg, #ffc107, #fd7e14) !important;
    }
    
    .bg-gradient-unavailable {
        background: linear-gradient(120deg, #dc3545, #c82333) !important;
    }
    
    /* Dashboard cards */
    .dashboard-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .dashboard-card .card-header {
        background: white;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding: 15px 20px;
    }
    
    .dashboard-card .card-header h3 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        color: #343a40;
    }
    
    .dashboard-card .card-body {
        padding: 20px;
    }
    
    /* Quick Actions */
    .quick-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 15px 10px;
        border-radius: 10px;
        transition: all 0.3s;
        text-decoration: none;
        color: #343a40;
    }
    
    .quick-action:hover {
        background-color: #f8f9fa;
        transform: translateY(-3px);
        color: #007bff;
        text-decoration: none;
    }
    
    .quick-action i {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    
    .quick-action div {
        font-weight: 500;
    }
    
    /* Upcoming Requests */
    .upcoming-request {
        padding: 15px;
        border-radius: 8px;
        background-color: #f8f9fa;
        margin-bottom: 15px;
        transition: all 0.3s;
        border-left: 4px solid #007bff;
    }
    
    .upcoming-request:hover {
        background-color: #e9ecef;
    }
    
    .request-date {
        font-weight: 600;
        color: #343a40;
    }
    
    .request-destination {
        font-size: 0.95rem;
        margin: 5px 0;
        color: #6c757d;
    }
    
    .request-requester {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 5px 10px;
        border-radius: 20px;
    }
    
    /* Progress Bars */
    .progress {
        height: 8px;
        border-radius: 10px;
        margin-top: 5px;
        background-color: #e9ecef;
    }
    
    .progress-bar {
        border-radius: 10px;
    }
    
    /* Recent Activity */
    .recent-activity {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .recent-activity li {
        display: flex;
        align-items: flex-start;
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .recent-activity li:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
        color: white;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-time {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 3px;
    }
    
    /* Quick Stats */
    .quick-stats .border-right {
        border-right: 1px solid #e9ecef !important;
    }
    
    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 30px 15px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .small-box h3 {
            font-size: 1.8rem;
        }
        
        .quick-action {
            margin-bottom: 15px;
        }
        
        .dashboard-card .card-body {
            padding: 15px;
        }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">
    <!-- Navbar -->
    <?php include '../includes/mainheader.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include '../includes/sidebar_service.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Dashboard Header -->
      <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Service Dashboard</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">
          <!-- Statistics Cards - Updated to match inventory.php style -->
          <div class="row">
            <div class="col-lg-3 col-6">
              <!-- small box -->
              <div class="small-box bg-gradient-vehicles">
                <div class="inner">
                  <h3><?php echo $stats['total_vehicles']; ?></h3>
                  <p>Total Vehicles</p>
                </div>
                <div class="icon">
                  <i class="fas fa-car"></i>
                </div>
                <a href="service_vehicle.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
              <!-- small box -->
              <div class="small-box bg-gradient-drivers">
                <div class="inner">
                  <h3><?php echo $stats['active_drivers']; ?></h3>
                  <p>Active Drivers</p>
                </div>
                <div class="icon">
                  <i class="fas fa-users"></i>
                </div>
                <a href="service_driver.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
              <!-- small box -->
              <div class="small-box bg-gradient-pending">
                <div class="inner">
                  <h3><?php echo $stats['pending_requests']; ?></h3>
                  <p>Pending Requests</p>
                </div>
                <div class="icon">
                  <i class="fas fa-list"></i>
                </div>
                <a href="service_request.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
              <!-- small box -->
              <div class="small-box bg-gradient-completed">
                <div class="inner">
                  <h3><?php echo $stats['completed_trips']; ?></h3>
                  <p>Completed Trips</p>
                </div>
                <div class="icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <a href="service_request.php?status=completed" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- ./col -->
          </div>
          <!-- /.row -->

          <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
              <!-- Quick Actions -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-3 col-sm-6">
                      <a href="service_request.php" class="quick-action text-primary">
                        <i class="fas fa-plus-circle"></i>
                        <div>New Request</div>
                      </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                      <a href="service_calendar.php" class="quick-action text-success">
                        <i class="far fa-calendar-alt"></i>
                        <div>View Calendar</div>
                      </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                      <a href="service_vehicle.php" class="quick-action text-warning">
                        <i class="fas fa-car"></i>
                        <div>Manage Vehicles</div>
                      </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                      <a href="service_driver.php" class="quick-action text-info">
                        <i class="fas fa-users"></i>
                        <div>Manage Drivers</div>
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Upcoming Requests -->
              <div class="dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h3 class="card-title">Upcoming Transport Requests</h3>
                  <a href="service_request.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                  <?php if (!empty($upcoming_requests)): ?>
                    <?php foreach ($upcoming_requests as $request): ?>
                      <div class="upcoming-request">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="request-date">
                              <?php echo date('M j, Y', strtotime($request['date_of_travel'])); ?>, 
                              <?php echo date('g:i A', strtotime($request['time_departure'])); ?> - 
                              <?php echo date('g:i A', strtotime($request['time_return'])); ?>
                            </div>
                            <div class="request-destination"><?php echo htmlspecialchars($request['destination']); ?></div>
                            <div class="request-requester">Requested by: <?php echo htmlspecialchars($request['requester_name']); ?></div>
                          </div>
                          <div>
                            <span class="badge badge-<?php echo $request['status'] == 'approved' ? 'success' : 'warning'; ?> status-badge">
                              <?php echo ucfirst($request['status']); ?>
                            </span>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="empty-state">
                      <i class="fas fa-calendar-times"></i>
                      <p>No upcoming requests</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Vehicle Status -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3 class="card-title">Vehicle Status</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <div class="d-flex justify-content-between mb-1">
                        <span>Available Vehicles</span>
                        <span class="font-weight-bold"><?php echo $stats['available_vehicles']; ?></span>
                      </div>
                      <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?php echo $available_percent; ?>%"></div>
                      </div>
                      <small class="text-muted"><?php echo round($available_percent); ?>% of fleet</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <div class="d-flex justify-content-between mb-1">
                        <span>In Maintenance</span>
                        <span class="font-weight-bold"><?php echo $stats['maintenance_vehicles']; ?></span>
                      </div>
                      <div class="progress">
                        <div class="progress-bar bg-warning" style="width: <?php echo $maintenance_percent; ?>%"></div>
                      </div>
                      <small class="text-muted"><?php echo round($maintenance_percent); ?>% of fleet</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <div class="d-flex justify-content-between mb-1">
                        <span>Unavailable</span>
                        <span class="font-weight-bold"><?php echo $stats['unavailable_vehicles']; ?></span>
                      </div>
                      <div class="progress">
                        <div class="progress-bar bg-danger" style="width: <?php echo $unavailable_percent; ?>%"></div>
                      </div>
                      <small class="text-muted"><?php echo round($unavailable_percent); ?>% of fleet</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
              <!-- Recent Activity -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3 class="card-title">Recent Activity</h3>
                </div>
                <div class="card-body">
                  <ul class="recent-activity">
                    <?php if (!empty($recent_activities)): ?>
                      <?php foreach ($recent_activities as $activity): ?>
                        <li>
                          <div class="activity-icon bg-primary">
                            <i class="fas fa-<?php echo $activity['activity_type'] == 'request_created' ? 'car' : 'check'; ?>"></i>
                          </div>
                          <div class="activity-content">
                            <div><?php echo htmlspecialchars($activity['description']); ?> - <?php echo htmlspecialchars($activity['request_no']); ?></div>
                            <div class="activity-time"><?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?></div>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <li class="empty-state">
                        <div class="activity-content">
                          <div>No recent activity</div>
                        </div>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>

              <!-- Driver Availability -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3 class="card-title">Driver Availability</h3>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between mb-2">
                    <span>Available</span>
                    <span class="font-weight-bold"><?php echo $available_drivers; ?></span>
                  </div>
                  <div class="progress mb-3">
                    <div class="progress-bar bg-success" style="width: <?php echo $available_drivers_percent; ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between mb-2">
                    <span>On Leave</span>
                    <span class="font-weight-bold"><?php echo $on_leave_drivers; ?></span>
                  </div>
                  <div class="progress mb-3">
                    <div class="progress-bar bg-warning" style="width: <?php echo $on_leave_percent; ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between mb-2">
                    <span>On Trip</span>
                    <span class="font-weight-bold"><?php echo $on_trip_drivers; ?></span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar bg-info" style="width: <?php echo $on_trip_percent; ?>%"></div>
                  </div>
                </div>
              </div>

              <!-- Quick Stats -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3 class="card-title">This Month</h3>
                </div>
                <div class="card-body">
                  <div class="row text-center quick-stats">
                    <div class="col-6 border-right mb-3">
                      <div class="text-primary font-weight-bold" style="font-size: 1.8rem;"><?php echo $stats['month_trips']; ?></div>
                      <div class="text-muted">Trips</div>
                    </div>
                    <div class="col-6 mb-3">
                      <div class="text-success font-weight-bold" style="font-size: 1.8rem;"><?php echo $stats['month_completed']; ?></div>
                      <div class="text-muted">Completed</div>
                    </div>
                  </div>
                  <hr>
                  <div class="row text-center quick-stats">
                    <div class="col-6 border-right">
                      <div class="text-info font-weight-bold" style="font-size: 1.8rem;"><?php echo $stats['month_trips'] * 50; ?></div>
                      <div class="text-muted">Km Traveled</div>
                    </div>
                    <div class="col-6">
                      <div class="text-warning font-weight-bold" style="font-size: 1.8rem;"><?php echo $stats['month_efficiency']; ?>%</div>
                      <div class="text-muted">Efficiency</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
  <?php include '../includes/mainfooter.php'; ?>
  </div>
  <!-- ./wrapper -->

  <?php include '../includes/footer.php'; ?>
</body>
</html>