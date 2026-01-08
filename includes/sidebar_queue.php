<?php
// sidebar_queue.php
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
$employee_picture = '../dist/img/user2-160x160.jpg';
$employee_id = $_SESSION['emp_id'] ?? null;

if ($employee_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee_data = $result->fetch_assoc();
        $employee_name = htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']);
        
        if (!empty($employee_data['picture'])) {
            $picture_path = '../dist/img/employees/' . $employee_data['picture'];
            if (file_exists($picture_path)) {
                $employee_picture = $picture_path;
            }
        }
    }
}
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="queue.php" class="brand-link bg-gradient-queue">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>Queue Management</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #2c3e50 !important;">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge badge-queue mt-1">
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
            <a href="queue_display.php" class="nav-link <?= $current_page == 'queue_display.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-tv"></i>
              <p>Queue Display</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="section_queue.php" class="nav-link <?= $current_page == 'section_queue.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-building"></i>
              <p>Section/IMO Queue</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="queue.php" class="nav-link <?= $current_page == 'queue.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-user-plus"></i>
              <p>Visitor Registration</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="queue_reports.php" class="nav-link <?= $current_page == 'queue_reports.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-chart-bar"></i>
              <p>Queue Reports</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="visitor_history.php" class="nav-link <?= $current_page == 'visitor_history.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-history"></i>
              <p>Visitor History</p>
            </a>
          </li>
          
          <?php if (hasPermission('manage_settings')): ?>
          <li class="nav-header text-light border-bottom pb-2 mt-3">SETTINGS</li>
          
          <li class="nav-item">
            <a href="queue_settings.php" class="nav-link <?= $current_page == 'queue_settings.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-cog"></i>
              <p>Queue Settings</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="queue_counters.php" class="nav-link <?= $current_page == 'queue_counters.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-desktop"></i>
              <p>Section/Unit Counters</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="purpose_categories.php" class="nav-link <?= $current_page == 'purpose_categories.php' ? 'active bg-queue' : 'text-white' ?>">
              <i class="nav-icon fas fa-tags"></i>
              <p>Purpose Categories</p>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
</aside>

<style>
.bg-gradient-queue {
    background: linear-gradient(135deg, #2c3e50, #34495e) !important;
}
.bg-queue {
    background-color: #2c3e50 !important;
}
.badge-queue {
    background: linear-gradient(135deg, #2c3e50, #34495e) !important;
}
.sidebar-dark-primary {
    background-color: #2c3e50 !important;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #2c3e50 !important;
    color: white !important;
    border-left: 4px solid #fff;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
}
</style>

<script>
$(document).ready(function() {
    // Force set queue theme
    localStorage.setItem('currentTheme', 'queue');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('queue');
    }
});
</script>