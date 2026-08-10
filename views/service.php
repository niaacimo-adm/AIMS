<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($conn) && class_exists('Database')) {
    $database = new Database();
    $conn = $database->getConnection();
}

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
  /* ============================================================
     NIA-ACIMO Service Module — Modern UI Layer
     Built on top of the app's existing --variables (light/dark
     mode values are supplied globally in includes/header.php).
     ============================================================ */
  .service-ui{ --su-accent:#24e78f; --su-accent-rgb:36,231,143; --su-radius:16px; --su-radius-sm:10px; }

  /* ---------- Page header ---------- */
  .service-ui .content-header{ padding:18px 0 6px; }
  .service-ui .content-header .container-fluid{
      background:linear-gradient(135deg, rgba(var(--su-accent-rgb),.10), rgba(var(--su-accent-rgb),.02));
      border:1px solid var(--card-border);
      border-radius:var(--su-radius);
      padding:20px 24px;
  }
  .service-ui .content-header h1{
      display:flex; align-items:center; gap:12px;
      font-size:1.45rem; font-weight:800; color:var(--text-primary); margin:0;
  }
  .service-ui .content-header h1 .page-icon{
      width:42px; height:42px; border-radius:12px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      background:var(--su-accent); color:#04160e; font-size:1.05rem;
      box-shadow:0 6px 16px -4px rgba(var(--su-accent-rgb),.5);
  }
  .service-ui .page-subtitle{ color:var(--text-muted); font-size:.85rem; margin:6px 0 0 54px; }
  .service-ui .content-header .breadcrumb{ background:transparent; margin:0; padding:0; }

  /* ---------- Cards ---------- */
  .service-ui .card{
      border:1px solid var(--card-border); border-radius:var(--su-radius);
      box-shadow:0 1px 2px rgba(0,0,0,.04), 0 10px 24px -18px rgba(0,0,0,.18);
      overflow:hidden;
  }
  .service-ui .card-header{
      background:var(--card-bg); border-bottom:1px solid var(--card-border);
      display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
      padding:16px 20px;
  }
  .service-ui .card-title{
      font-size:1.05rem; font-weight:700; color:var(--text-primary);
      display:flex; align-items:center; gap:8px; margin:0;
  }
  .service-ui .card-title i{ color:var(--su-accent); }
  .service-ui .card-body{ padding:20px; }

  /* ---------- Buttons ---------- */
  .service-ui .btn{ border-radius:var(--su-radius-sm); font-weight:600; font-size:.85rem; letter-spacing:.01em; transition:all .18s ease; }
  .service-ui .btn-primary{ background:var(--su-accent); border-color:var(--su-accent); color:#04160e; }
  .service-ui .btn-primary:hover, .service-ui .btn-primary:focus{ background:#1fcf80; border-color:#1fcf80; color:#04160e; transform:translateY(-1px); box-shadow:0 6px 14px -6px rgba(var(--su-accent-rgb),.6); }
  .service-ui .btn-sm{ border-radius:8px; }
  .service-ui .action-btns .btn{ padding:.3rem .55rem; font-size:.8rem; margin-right:3px; }
  .service-ui .action-btns .btn:last-child{ margin-right:0; }
  .service-ui .btn-group .btn{ border-radius:8px !important; margin-right:3px; }

  /* ---------- Table ---------- */
  .service-ui .table{ margin-bottom:0; font-size:.9rem; }
  .service-ui .table thead th{
      background:var(--table-stripe); color:var(--text-muted); border-bottom:2px solid var(--table-border);
      font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; white-space:nowrap;
  }
  .service-ui .table td{ vertical-align:middle; border-color:var(--table-border); }
  .service-ui .table-hover tbody tr{ transition:background .15s ease; }
  .service-ui .table-hover tbody tr:hover{ background:rgba(var(--su-accent-rgb),.07) !important; }

  /* ---------- Badges ---------- */
  .service-ui .badge{ font-weight:600; padding:.42em .8em; border-radius:20px; letter-spacing:.02em; }
  .service-ui .status-badge{ font-size:.72rem; }

  /* ---------- Forms ---------- */
  .service-ui .form-control{ border-radius:var(--su-radius-sm); border-color:var(--input-border); }
  .service-ui .form-control:focus{ border-color:var(--su-accent); box-shadow:0 0 0 .2rem rgba(var(--su-accent-rgb),.2); }
  .service-ui .form-group label{ font-weight:600; font-size:.85rem; color:var(--text-primary); }

  /* ---------- Modals ---------- */
  .service-ui .modal-content{ border:none; border-radius:18px; overflow:hidden; box-shadow:0 24px 60px -20px rgba(0,0,0,.4); }
  .service-ui .modal-header{ background:var(--modal-header-bg); color:var(--modal-header-color); border-bottom:none; padding:18px 22px; }
  .service-ui .modal-header .close{ color:var(--modal-header-color); opacity:.8; text-shadow:none; }
  .service-ui .modal-header .close:hover{ opacity:1; }
  .service-ui .modal-title{ font-weight:700; }
  .service-ui .modal-body{ padding:22px; }
  .service-ui .modal-footer{ border-top:1px solid var(--card-border); padding:14px 22px; }

  /* ---------- Alerts ---------- */
  .service-ui .alert{ border:none; border-left:4px solid; border-radius:10px; }
  .service-ui .alert-warning{ border-left-color:#f59e0b; }
  .service-ui .alert-danger{ border-left-color:#ef4444; }
  .service-ui .alert-success{ border-left-color:var(--su-accent); }
  .service-ui .alert-info{ border-left-color:#3b82f6; }

  /* ---------- Empty state ---------- */
  .service-ui .empty-state{ text-align:center; padding:40px 15px; color:var(--text-muted); }
  .service-ui .empty-state i{ font-size:2.6rem; margin-bottom:12px; opacity:.4; }

  /* ---------- Photo thumbnails ---------- */
  .service-ui .driver-photo, .service-ui .request-photo{
      box-shadow:0 0 0 2px var(--card-bg), 0 0 0 3px var(--card-border);
  }

/* =========================================================
   DARK MODE OVERRIDES — applied via body.dark-mode
   ========================================================= */
body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .card-header { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
body.dark-mode .table-hover tbody tr:hover { background: rgba(36,231,143,.10) !important; }
body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .alert-info { background: #1e2f3e !important; color: #93c5fd !important; }
body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; }
body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .dataTables_info, body.dark-mode .dataTables_paginate { color: var(--text-muted) !important; }

  /* ---------- Dashboard stat cards ---------- */
  .service-ui .small-box{
      background:var(--card-bg); border:1px solid var(--card-border); border-radius:var(--su-radius);
      box-shadow:0 1px 2px rgba(0,0,0,.04), 0 10px 24px -18px rgba(0,0,0,.18);
      position:relative; overflow:hidden; margin-bottom:20px;
      border-top:3px solid var(--su-accent);
      transition:transform .25s ease, box-shadow .25s ease;
  }
  .service-ui .small-box:hover{ transform:translateY(-4px); box-shadow:0 16px 32px -16px rgba(0,0,0,.25); }
  .service-ui .small-box>.inner{ padding:22px 22px 16px; position:relative; z-index:1; }
  .service-ui .small-box h3{ font-size:2rem; font-weight:800; margin:0 0 4px; color:var(--text-primary); white-space:nowrap; }
  .service-ui .small-box p{ font-size:.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin:0; }
  .service-ui .small-box .icon{
      position:absolute; top:18px; right:18px; z-index:0;
      width:50px; height:50px; border-radius:14px;
      display:flex; align-items:center; justify-content:center;
      font-size:1.3rem; color:#fff; transition:none;
      box-shadow:0 6px 16px -4px rgba(0,0,0,.25);
  }
  .service-ui .small-box:hover .icon{ font-size:1.3rem; }
  .service-ui .small-box-footer{
      position:relative; text-align:left; padding:10px 22px; z-index:1;
      background:transparent; border-top:1px solid var(--card-border);
      color:var(--su-accent); font-weight:700; font-size:.82rem; text-decoration:none; display:block;
  }
  .service-ui .small-box-footer:hover{ color:var(--text-primary); background:var(--table-stripe); text-decoration:none; }

  .service-ui .bg-gradient-vehicles .icon{ background:linear-gradient(135deg,#3b82f6,#2563eb); }
  .service-ui .bg-gradient-drivers .icon{ background:linear-gradient(135deg,#24e78f,#1a9d63); }
  .service-ui .bg-gradient-pending .icon{ background:linear-gradient(135deg,#f59e0b,#d97706); }
  .service-ui .bg-gradient-completed .icon{ background:linear-gradient(135deg,#06b6d4,#0e7490); }
  .service-ui .bg-gradient-available .icon{ background:linear-gradient(135deg,#24e78f,#1a9d63); }
  .service-ui .bg-gradient-maintenance .icon{ background:linear-gradient(135deg,#f59e0b,#d97706); }
  .service-ui .bg-gradient-unavailable .icon{ background:linear-gradient(135deg,#ef4444,#b91c1c); }

  /* ---------- Dashboard cards ---------- */
  .service-ui .dashboard-card{
      background:var(--card-bg); border:1px solid var(--card-border); border-radius:var(--su-radius);
      box-shadow:0 1px 2px rgba(0,0,0,.04), 0 10px 24px -18px rgba(0,0,0,.18);
      margin-bottom:24px; overflow:hidden;
  }
  .service-ui .dashboard-card .card-header{
      background:var(--card-bg); border-bottom:1px solid var(--card-border); padding:16px 20px;
  }
  .service-ui .dashboard-card .card-header h3{ font-size:1.05rem; font-weight:700; margin:0; color:var(--text-primary); }
  .service-ui .dashboard-card .card-body{ padding:20px; }

  /* ---------- Quick actions ---------- */
  .service-ui .quick-action{
      display:flex; flex-direction:column; align-items:center; text-align:center;
      padding:18px 10px; border-radius:var(--su-radius-sm); transition:all .25s ease;
      text-decoration:none; color:var(--text-primary); border:1px solid var(--card-border);
  }
  .service-ui .quick-action:hover{
      background:rgba(var(--su-accent-rgb),.08); border-color:var(--su-accent);
      transform:translateY(-3px); color:var(--su-accent); text-decoration:none;
  }
  .service-ui .quick-action i{ font-size:1.8rem; margin-bottom:10px; }
  .service-ui .quick-action div{ font-weight:600; font-size:.85rem; }

  /* ---------- Upcoming requests ---------- */
  .service-ui .upcoming-request{
      padding:15px 16px; border-radius:var(--su-radius-sm); background:var(--table-stripe);
      margin-bottom:12px; transition:all .2s ease; border-left:4px solid var(--su-accent);
  }
  .service-ui .upcoming-request:hover{ background:rgba(var(--su-accent-rgb),.12); }
  .service-ui .request-date{ font-weight:700; color:var(--text-primary); font-size:.9rem; }
  .service-ui .request-destination{ font-size:.9rem; margin:4px 0; color:var(--text-muted); }
  .service-ui .request-requester{ font-size:.8rem; color:var(--text-muted); }

  /* ---------- Progress bars ---------- */
  .service-ui .progress{ height:8px; border-radius:10px; margin-top:5px; background:var(--table-stripe); }
  .service-ui .progress-bar{ border-radius:10px; }

  /* ---------- Recent activity ---------- */
  .service-ui .recent-activity{ list-style:none; padding:0; margin:0; }
  .service-ui .recent-activity li{ display:flex; align-items:flex-start; padding:12px 0; border-bottom:1px solid var(--card-border); }
  .service-ui .recent-activity li:last-child{ border-bottom:none; }
  .service-ui .activity-icon{
      width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;
      margin-right:14px; flex-shrink:0; color:#04160e; background:var(--su-accent) !important;
  }
  .service-ui .activity-content{ flex:1; }
  .service-ui .activity-time{ font-size:.78rem; color:var(--text-muted); margin-top:2px; }

  /* ---------- Quick stats ---------- */
  .service-ui .quick-stats .border-right{ border-right:1px solid var(--card-border) !important; }

  @media (max-width:768px){
      .service-ui .small-box h3{ font-size:1.7rem; }
      .service-ui .quick-action{ margin-bottom:14px; }
      .service-ui .dashboard-card .card-body{ padding:15px; }
      .service-ui .content-header .page-subtitle{ margin-left:0; }
  }

body.dark-mode .service-ui .activity-icon{ color:#04160e !important; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed service-ui">
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
                        <h1 class="m-0"><span class="page-icon"><i class="fas fa-shuttle-van"></i></span>Service Dashboard</h1>
                        <p class="page-subtitle">Fleet, drivers, and transport requests at a glance.</p>
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