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
    :root {
      --primary: #007bff;
      --secondary: #6c757d;
      --success: #28a745;
      --info: #17a2b8;
      --warning: #ffc107;
      --danger: #dc3545;
      --light: #f8f9fa;
      --dark: #343a40;
    }
    
    .dashboard-card {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.125);
      transition: all 0.3s ease;
    }
    
    .dashboard-card:hover {
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      transform: translateY(-2px);
    }
    
    .dashboard-card .card-header {
      background-color: #f8f9fa;
      border-bottom: 1px solid rgba(0, 0, 0, 0.125);
      padding: 0.75rem 1.25rem;
      border-radius: 0.5rem 0.5rem 0 0;
    }
    
    .dashboard-card .card-body {
      padding: 1.25rem;
    }
    
    .stat-card {
      border-radius: 0.5rem;
      padding: 1.5rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .stat-card .stat-icon {
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
      opacity: 0.8;
    }
    
    .stat-card .stat-number {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    
    .stat-card .stat-text {
      font-size: 0.9rem;
      opacity: 0.9;
    }
    
    .quick-action {
      display: block;
      text-align: center;
      padding: 1.5rem 0.5rem;
      border-radius: 0.5rem;
      background-color: #f8f9fa;
      transition: all 0.3s ease;
      text-decoration: none;
      margin-bottom: 1rem;
    }
    
    .quick-action:hover {
      background-color: #e9ecef;
      transform: translateY(-3px);
      text-decoration: none;
    }
    
    .quick-action i {
      font-size: 2rem;
      margin-bottom: 0.5rem;
      display: block;
    }
    
    .quick-action div {
      font-weight: 600;
    }
    
    .upcoming-request {
      padding: 1rem;
      border-left: 4px solid var(--primary);
      background-color: #f8f9fa;
      border-radius: 0.25rem;
      margin-bottom: 1rem;
    }
    
    .upcoming-request:last-child {
      margin-bottom: 0;
    }
    
    .request-date {
      font-weight: 600;
      color: var(--dark);
    }
    
    .request-destination {
      color: var(--secondary);
      margin: 0.25rem 0;
    }
    
    .request-requester {
      font-size: 0.875rem;
      color: var(--secondary);
    }
    
    .status-badge {
      font-size: 0.75rem;
    }
    
    .recent-activity {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .recent-activity li {
      display: flex;
      align-items: flex-start;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    .recent-activity li:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .activity-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      flex-shrink: 0;
    }
    
    .activity-content {
      flex: 1;
    }
    
    .activity-time {
      font-size: 0.75rem;
      color: var(--secondary);
      margin-top: 0.25rem;
    }
    
    .dashboard-header {
      background: linear-gradient(135deg, var(--primary) 0%, #0056b3 100%);
      color: white;
      padding: 2rem 0;
      margin-bottom: 1.5rem;
      border-radius: 0.5rem;
    }
    
    .dashboard-header h1 {
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    .dashboard-header .lead {
      opacity: 0.9;
      margin-bottom: 0;
    }
    
    .progress-sm {
      height: 0.5rem;
    }
    
    .bg-gradient-primary {
      background: linear-gradient(135deg, var(--primary) 0%, #0056b3 100%) !important;
    }
    
    .bg-gradient-success {
      background: linear-gradient(135deg, var(--success) 0%, #1e7e34 100%) !important;
    }
    
    .bg-gradient-warning {
      background: linear-gradient(135deg, var(--warning) 0%, #e0a800 100%) !important;
    }
    
    .bg-gradient-info {
      background: linear-gradient(135deg, var(--info) 0%, #138496 100%) !important;
    }
    
    @media (max-width: 768px) {
      .dashboard-header .text-right {
        text-align: left !important;
        margin-top: 1rem;
      }
      
      .quick-action {
        margin-bottom: 1rem;
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
          <!-- Statistics Cards -->
          <div class="row">
            <div class="col-lg-3 col-md-6">
              <div class="dashboard-card">
                <div class="stat-card bg-gradient-primary text-white">
                  <div class="stat-icon">
                    <i class="fas fa-car"></i>
                  </div>
                  <div class="stat-number"><?php echo $stats['total_vehicles']; ?></div>
                  <div class="stat-text">Total Vehicles</div>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="dashboard-card">
                <div class="stat-card bg-gradient-success text-white">
                  <div class="stat-icon">
                    <i class="fas fa-users"></i>
                  </div>
                  <div class="stat-number"><?php echo $stats['active_drivers']; ?></div>
                  <div class="stat-text">Active Drivers</div>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="dashboard-card">
                <div class="stat-card bg-gradient-warning text-white">
                  <div class="stat-icon">
                    <i class="fas fa-list"></i>
                  </div>
                  <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                  <div class="stat-text">Pending Requests</div>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="dashboard-card">
                <div class="stat-card bg-gradient-info text-white">
                  <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                  </div>
                  <div class="stat-number"><?php echo $stats['completed_trips']; ?></div>
                  <div class="stat-text">Completed Trips</div>
                </div>
              </div>
            </div>
          </div>

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
                    <div class="text-center text-muted py-3">
                      <i class="fas fa-calendar-times fa-2x mb-2"></i>
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
                    <div class="col-md-4">
                      <div class="d-flex justify-content-between">
                        <span>Available Vehicles</span>
                        <span class="font-weight-bold"><?php echo $stats['available_vehicles']; ?></span>
                      </div>
                      <div class="progress progress-sm">
                        <div class="progress-bar bg-success" style="width: <?php echo $available_percent; ?>%"></div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex justify-content-between">
                        <span>In Maintenance</span>
                        <span class="font-weight-bold"><?php echo $stats['maintenance_vehicles']; ?></span>
                      </div>
                      <div class="progress progress-sm">
                        <div class="progress-bar bg-warning" style="width: <?php echo $maintenance_percent; ?>%"></div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex justify-content-between">
                        <span>Unavailable</span>
                        <span class="font-weight-bold"><?php echo $stats['unavailable_vehicles']; ?></span>
                      </div>
                      <div class="progress progress-sm">
                        <div class="progress-bar bg-danger" style="width: <?php echo $unavailable_percent; ?>%"></div>
                      </div>
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
                      <li class="text-center text-muted">
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
                  <div class="progress progress-sm mb-3">
                    <div class="progress-bar bg-success" style="width: <?php echo $available_drivers_percent; ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between mb-2">
                    <span>On Leave</span>
                    <span class="font-weight-bold"><?php echo $on_leave_drivers; ?></span>
                  </div>
                  <div class="progress progress-sm mb-3">
                    <div class="progress-bar bg-warning" style="width: <?php echo $on_leave_percent; ?>%"></div>
                  </div>
                  
                  <div class="d-flex justify-content-between mb-2">
                    <span>On Trip</span>
                    <span class="font-weight-bold"><?php echo $on_trip_drivers; ?></span>
                  </div>
                  <div class="progress progress-sm">
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
                  <div class="row text-center">
                    <div class="col-6 border-right">
                      <div class="text-primary font-weight-bold" style="font-size: 1.5rem;"><?php echo $stats['month_trips']; ?></div>
                      <div class="text-muted">Trips</div>
                    </div>
                    <div class="col-6">
                      <div class="text-success font-weight-bold" style="font-size: 1.5rem;"><?php echo $stats['month_completed']; ?></div>
                      <div class="text-muted">Completed</div>
                    </div>
                  </div>
                  <hr>
                  <div class="row text-center">
                    <div class="col-6 border-right">
                      <div class="text-info font-weight-bold" style="font-size: 1.5rem;"><?php echo $stats['month_trips'] * 50; ?></div>
                      <div class="text-muted">Km Traveled</div>
                    </div>
                    <div class="col-6">
                      <div class="text-warning font-weight-bold" style="font-size: 1.5rem;"><?php echo $stats['month_efficiency']; ?>%</div>
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

    <!-- Main Footer -->
    <footer class="main-footer">
      <strong>Copyright &copy; 2023 <a href="#">NIA-ACIMO</a>.</strong>
      All rights reserved.
      <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0.0
      </div>
    </footer>
  </div>
  <!-- ./wrapper -->

  <?php include '../includes/footer.php'; ?>
</body>
</html>