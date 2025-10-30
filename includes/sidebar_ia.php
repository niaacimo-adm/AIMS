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
/* IA Profile Theme Colors */
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #9C27B0;
    border-color: #9C27B0;
}

.brand-link {
    background: linear-gradient(135deg, #9C27B0, #7B1FA2);
}

.brand-link .brand-text {
    color: rgba(255, 255, 255, 0.9);
}

/* Custom styles for IA Profile sidebar */
.main-sidebar {
    background-color: #343a40 !important;
}

.nav-sidebar .nav-item > .nav-link {
    border-left: 3px solid transparent;
}

.nav-sidebar .nav-item > .nav-link.active {
    border-left-color: #9C27B0;
}

.nav-sidebar .nav-treeview .nav-item > .nav-link.active {
    border-left-color: #E1BEE7;
    background-color: rgba(156, 39, 176, 0.1);
}
</style>

<script>
// Set IA theme when sidebar loads
$(document).ready(function() {
    // Set theme to IA
    localStorage.setItem('currentTheme', 'ia');
    
    // Apply theme to header and footer
    const theme = {
        header: 'linear-gradient(135deg, #9C27B0, #7B1FA2)',
        footer: 'linear-gradient(135deg, #9C27B0, #7B1FA2)',
        class: 'theme-ia'
    };
    
    // Update header
    const header = $('.main-header');
    if (header.length) {
        header.css('background', theme.header);
        header.removeClass('theme-admin theme-service theme-inventory theme-file theme-ict theme-document theme-scrum');
        header.addClass(theme.class);
    }

    // Update footer
    const footer = $('#mainFooter');
    if (footer.length) {
        footer.css('background', theme.footer);
        footer.removeClass('theme-admin theme-service theme-inventory theme-file theme-ict theme-document theme-scrum');
        footer.addClass(theme.class);
    }
    
    console.log('IA Profile theme applied from sidebar');
});
</script>