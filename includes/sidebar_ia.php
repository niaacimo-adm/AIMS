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
    <a href="dashboard_ia.php" class="brand-link">
        <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
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
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="dashboard_ia.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard_ia.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="ia_profiles.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ia_profiles.php' ? 'active' : '' ?>">
                        <i class="fas fa-list nav-icon"></i>
                        <p>All IA Profiles</p>
                    </a>
                </li>

                <!-- Reports -->
                <li class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['ia_reports.php', 'ia_analytics.php']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['ia_reports.php', 'ia_analytics.php']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>
                            Reports & Analytics
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="ia_reports.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ia_reports.php' ? 'active' : '' ?>">
                                <i class="fas fa-file-pdf nav-icon"></i>
                                <p>Generate Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="ia_analytics.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ia_analytics.php' ? 'active' : '' ?>">
                                <i class="fas fa-chart-line nav-icon"></i>
                                <p>Analytics Dashboard</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
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