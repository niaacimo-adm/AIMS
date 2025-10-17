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
<aside class="main-sidebar sidebar-dark-info elevation-4">
    <!-- Brand Logo -->
    <a href="ict_inventory.php" class="brand-link bg-gradient-info">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #1c3b5e !important;">
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
                    <a href="ict_inventory.php" class="nav-link <?= $current_page == 'ict_inventory.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <?php if (hasPermission('manage_ict_equipment')): ?>
                <li class="nav-header text-light border-bottom pb-2 mt-3">EQUIPMENT MANAGEMENT</li>
                <li class="nav-item">
                    <a href="ict_equipment.php" class="nav-link <?= $current_page == 'ict_equipment.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-laptop"></i>
                        <p>All Equipment</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="ict_categories.php" class="nav-link <?= $current_page == 'ict_categories.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- MAINTENANCE MANAGEMENT SECTION -->
                <li class="nav-header text-light border-bottom pb-2 mt-3">MAINTENANCE</li>
                
                <!-- Employee Maintenance (Visible to all employees) -->
                <li class="nav-item">
                    <a href="ict_maintenance.php" class="nav-link <?= $current_page == 'ict_maintenance.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tools"></i>
                        <p>My Maintenance Requests</p>
                    </a>
                </li>

                <!-- Maintenance Management (Visible to ICT staff) -->
                <?php if (hasPermission('manage_ict_maintenance')): ?>
                <li class="nav-item">
                    <a href="ict_maintenance_management.php" class="nav-link <?= $current_page == 'ict_maintenance_management.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Maintenance Management</p>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-header text-light border-bottom pb-2 mt-3">MY EQUIPMENT</li>
                <li class="nav-item">
                    <a href="ict_my_equipment.php" class="nav-link <?= $current_page == 'ict_my_equipment.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-desktop"></i>
                        <p>Assigned Equipment</p>
                    </a>
                </li>

                <?php if (hasPermission('view_ict_reports')): ?>
                <li class="nav-header text-light border-bottom pb-2 mt-3">REPORTS</li>
                <li class="nav-item">
                    <a href="ict_reports.php" class="nav-link <?= $current_page == 'ict_reports.php' ? 'active bg-info' : 'text-white' ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Reports & Analytics</p>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>

<style>
.sidebar-dark-info {
    background-color: #1c3b5e !important;
}
.sidebar-dark-info .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-info .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #17a2b8 !important;
    color: white !important;
    border-left: 4px solid #fff;
}
.sidebar-dark-info .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.brand-link.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
}

.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    color: #a8d5e5 !important;
    border-bottom-color: #17a2b8 !important;
}

.user-panel .info .badge {
    background-color: #6f42c1 !important;
}
</style>
<script>
$(document).ready(function() {
    // Force set ICT theme
    localStorage.setItem('currentTheme', 'ict');
    
    // Apply theme immediately
    const themes = {
        'ict': 'linear-gradient(135deg, #17a2b8, #138496)'
    };
    
    // Update header directly
    $('.main-header').css('background', themes['ict']);
    $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    
    // Update footer directly  
    $('#mainFooter').css('background', themes['ict']);
    $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    
    console.log('ICT theme applied from sidebar');
    
    // Also trigger theme update in mainheader if function exists
    if (window.setTheme) {
        window.setTheme('ict');
    }
});
</script>