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
<aside class="main-sidebar sidebar-dark-success elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link bg-gradient-success">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #153021 !important;">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge badge-info mt-1">
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
                    <a href="inventory.php" class="nav-link <?= $current_page == 'inventory.php' ? 'active bg-success' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_inventory.php" class="nav-link <?= $current_page == 'view_inventory.php' ? 'active bg-success' : 'text-white' ?>">
                        <i class="nav-icon fas fa-boxes"></i>
                        <p>Inventory</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="request_supplies.php" class="nav-link <?= $current_page == 'request_supplies.php' ? 'active bg-success' : 'text-white' ?>">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Request Supplies</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_supply_requests.php" class="nav-link <?= $current_page == 'my_supply_requests.php' ? 'active bg-success' : 'text-white' ?>">
                        <i class="nav-icon fas fa-list-check"></i>
                        <p>My Requests</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
.sidebar-dark-success {
    background-color: #1a472a !important;
}
.sidebar-dark-success .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-success .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #28a745 !important;
    color: white !important;
    border-left: 4px solid #fff;
}
.sidebar-dark-success .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.brand-link.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

/* Additional green theme enhancements */
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    color: #a8d5b2 !important;
    border-bottom-color: #28a745 !important;
}

.user-panel .info .badge {
    background-color: #17a2b8 !important;
}
</style>
<script>
$(document).ready(function() {
    // Force set inventory theme
    localStorage.setItem('currentTheme', 'inventory');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('inventory');
    }
});
</script>