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
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
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

          <?php if (hasPermission('view_dashboard')): ?>
          <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <?php endif; ?>
          
          <li class="nav-item">
            <a href="attachments_monitoring.php" class="nav-link <?= $current_page == 'attachments_monitoring.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="nav-icon fas fa-paperclip"></i>
              <p>Attachment Monitoring</p>
            </a>
          </li>
          
          <?php if (hasPermission('view_calendar')): ?>
          <li class="nav-item">
            <a href="calendar.php" class="nav-link <?= $current_page == 'calendar.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="nav-icon fas fa-calendar-alt"></i>
              <p>Calendar</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_employees')): ?>
          <li class="nav-header text-light border-bottom pb-2 mt-3">EMPLOYEE MANAGEMENT</li>
            <?php if (hasPermission('create_employees')): ?>
              <li class="nav-item">
                <a href="../views/emp.create.php" class="nav-link <?= $current_page == 'emp.create.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-user-plus nav-icon"></i>
                  <p>Create Employee</p>
                </a>
              </li>
              <?php endif; ?>

              <?php if (hasPermission('view_employees')): ?>
              <li class="nav-item">
                <a href="../views/emp.list.php" class="nav-link <?= $current_page == 'emp.list.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-users nav-icon"></i>
                  <p>Employee List</p>
                </a>
              </li>
              <?php endif; ?>
          <?php endif; ?>

          <?php if (hasPermission('manage_settings')): ?>
          <li class="nav-header text-light border-bottom pb-2 mt-3">SETTINGS</li>
          <li class="nav-item">
              <a href="content_management.php" class="nav-link <?= $current_page == 'content_management.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-tv nav-icon"></i>
                  <p>Content Management</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="appointment_status.php" class="nav-link <?= $current_page == 'appointment_status.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-briefcase nav-icon"></i>
                  <p>Appointment Settings</p>
              </a>
          </li>
          <li class="nav-item">
            <a href="position.php" class="nav-link <?= $current_page == 'position.php' ? 'active bg-primary' : 'text-white' ?>">
                <i class="fas fa-id-card-alt nav-icon"></i>
                <p>Positions</p>
            </a>
          </li>
          <li class="nav-item">
              <a href="sections.php" class="nav-link <?= $current_page == 'sections.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-sitemap nav-icon"></i>
                  <p>Sections</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="offices.php" class="nav-link <?= $current_page == 'offices.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-building nav-icon"></i>
                  <p>Offices</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="employment_status.php" class="nav-link <?= $current_page == 'employment_status.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="fas fa-user-check nav-icon"></i>
                  <p>Employment Status</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_users')): ?>
          <li class="nav-header text-light border-bottom pb-2 mt-3">USER MANAGEMENT</li>
          <li class="nav-item">
              <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="nav-icon fas fa-user-cog"></i>
                  <p>Users</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_roles')): ?>
          <li class="nav-item">
              <a href="roles.php" class="nav-link <?= $current_page == 'roles.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="nav-icon fas fa-user-shield"></i>
                  <p>Roles</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_permissions')): ?>
          <li class="nav-item">
              <a href="permissions.php" class="nav-link <?= $current_page == 'permissions.php' ? 'active bg-primary' : 'text-white' ?>">
                  <i class="nav-icon fas fa-key"></i>
                  <p>Permissions</p>
              </a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
</aside>
<style>
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
    background-color: #007bff !important;
    color: white !important;
    border-left: 4px solid #fff;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.brand-link.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%) !important;
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
    // Force set admin theme
    localStorage.setItem('currentTheme', 'admin');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('admin');
    }
});
</script>