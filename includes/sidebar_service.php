<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get employee data if user is logged in
$employee_name = '';
$employee_picture = '../dist/img/user2-160x160.jpg'; // Default image
$employee_id = $_SESSION['emp_id'] ?? null;

if ($employee_id) {
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Query to get employee name and picture
    $query = "SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee_data = $result->fetch_assoc();
        $employee_name = htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']);
        
        // Check if picture exists
        if (!empty($employee_data['picture'])) {
            $picture_path = '../dist/img/employees/' . $employee_data['picture'];
            if (file_exists($picture_path)) {
                $employee_picture = $picture_path;
            }
        }
    }
}
?>
<aside class="main-sidebar sidebar-dark-warning elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link bg-gradient-warning">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #5a3e00 !important;">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge badge-primary mt-1">
                <?= htmlspecialchars($_SESSION['role_name']) ?>
            </span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="service.php" class="nav-link <?= $current_page == 'service.php' ? 'active bg-warning' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>

        <li class="nav-item">
            <a href="service_calendar.php" class="nav-link <?= $current_page == 'service_calendar.php' ? 'active bg-warning' : 'text-white' ?>">
                <i class="nav-icon fas fa-calendar-alt"></i>
                <p>Service Calendar</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_vehicle.php" class="nav-link <?= $current_page == 'service_vehicle.php' ? 'active bg-warning' : 'text-white' ?>">
                <i class="nav-icon fas fa-truck"></i>
                <p>Service Information</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_driver.php" class="nav-link <?= $current_page == 'service_driver.php' ? 'active bg-warning' : 'text-white' ?>">
                <i class="nav-icon fas fa-id-card"></i>
                <p>Operator/Driver</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_request.php" class="nav-link <?= $current_page == 'service_request.php' ? 'active bg-warning' : 'text-white' ?>">
                <i class="nav-icon fas fa-file-alt"></i>
                <p>Transportation Request</p>
            </a>
        </li>
        </ul>
      </nav>
    </div>
</aside>

<style>
.sidebar-dark-warning {
    background-color: #7c5800 !important;
}
.sidebar-dark-warning .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-warning .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #ffc107 !important;
    color: #212529 !important;
    border-left: 4px solid #fff;
}
.sidebar-dark-warning .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.brand-link.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
}

/* Additional orange theme enhancements */
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    color: #ffeaa7 !important;
    border-bottom-color: #ffc107 !important;
}

.user-panel .info .badge {
    background-color: #17a2b8 !important;
}
</style>
<script>
$(document).ready(function() {
    // Force set service theme
    localStorage.setItem('currentTheme', 'service');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('service');
    }
});
// Force set admin theme and update profile if open
localStorage.setItem('currentTheme', 'admin');
// Set cookie for profile detection
document.cookie = "current_module=admin; path=/; max-age=300";

// Update header theme if we're in a parent window
if (window.parent && window.parent.setTheme) {
    window.parent.setTheme('admin');
}
</script>