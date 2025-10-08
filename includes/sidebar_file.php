<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
<aside class="main-sidebar sidebar-light-info elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text text-white"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2 bg-white" alt="User Image" style="border: 2px solid #17a2b8;">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge <?= 
                $_SESSION['role_name'] === 'Administrator' ? 'badge-danger' : 
                ($_SESSION['role_name'] === 'Employee' ? 'badge-warning' : 'badge-secondary')
            ?>">
                <?= htmlspecialchars($_SESSION['role_name']) ?>
            </span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Add icons to the links using the .nav-icon class with font-awesome or any other icon font library -->
                <li class="nav-item">
                    <a href="file_management.php" class="nav-link text-white <?= $current_page == 'file_management.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-home text-info"></i>
                        <p class="text-dark">Dashboard</p>
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link text-white <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <p>My Profile</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link text-white">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
        <?php endif; ?>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>

<style>
/* Force sidebar positioning and styling */
.main-sidebar.sidebar-light-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    z-index: 1030 !important;
    width: 250px !important;
}

.sidebar-light-info .brand-link,
.sidebar-light-info .sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.sidebar-light-info .nav-sidebar > .nav-item > .nav-link {
    color: white !important;
}

.sidebar-light-info .nav-sidebar > .nav-item > .nav-link.active {
    background-color: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

.sidebar-light-info .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}

.sidebar-light-info .user-panel .info a {
    color: white !important;
}

/* Ensure content doesn't overlap sidebar */
.content-wrapper {
    margin-left: 250px !important;
    min-height: 100vh;
}

@media (max-width: 768px) {
    .main-sidebar.sidebar-light-info {
        transform: translateX(-100%);
    }
    
    .sidebar-open .main-sidebar.sidebar-light-info {
        transform: translateX(0);
    }
    
    .content-wrapper {
        margin-left: 0 !important;
    }
}
</style>