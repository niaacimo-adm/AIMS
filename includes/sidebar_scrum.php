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

<!-- Scrumboard Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
    </a>
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

         
          <li class="nav-item">
            <a href="scrum_dashboard.php" class="nav-link <?= $current_page == 'scrum_dashboard.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="scrum_project.php" class="nav-link <?= $current_page == 'scrum_project.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="fas fa-project-diagram"></i>
              <span>Projects Monitoring</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="scrum_team.php" class="nav-link <?= $current_page == 'scrum_team.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="fas fa-project-diagram"></i>
              <span>Teams</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="my_scrum_project.php" class="nav-link <?= $current_page == 'my_scrum_project.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="fas fa-project-diagram"></i>
              <span>My Projects</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="my_scrum_task.php" class="nav-link <?= $current_page == 'my_scrum_task.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="fas fa-tasks"></i>
              <span>My Tasks</span>
            </a>
          </li>
          <li class="nav-item"> 
            <a href="scrum_calendar.php" class="nav-link <?= $current_page == 'scrum_calendar.php' ? 'active bg-primary' : 'text-white' ?>">
              <i class="fas fa-calendar-alt"></i>
              <span>Calendar</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
    <div class="sidebar-footer">
        <button class="btn btn-primary btn-block" id="newBoardBtn">
            <i class="fas fa-plus mr-2"></i>New Board
        </button>
        <button class="btn btn-outline-secondary btn-block mt-2" id="mobileToggle">
            <i class="fas fa-bars mr-2"></i>Menu
        </button>
    </div>
</aside>
<style>
/*
 * Sidebar styles are driven by CSS variables defined in mainheader.php.
 * Light / dark mode is toggled globally — no per-module colours.
 */
</style>
<script>
$(document).ready(function() {
    // Sidebar loads — dark mode already applied by mainheader CSS variables.
    // Re-apply dark mode class in case this page loaded fresh.
    if (localStorage.getItem('darkMode') === '1') {
        $('body').addClass('dark-mode');
    }
});
</script>