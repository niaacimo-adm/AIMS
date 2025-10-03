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
<aside class="main-sidebar sidebar-light-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link bg-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text text-white"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar bg-primary">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2 bg-white" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge <?= 
                $_SESSION['role_name'] === 'Administrator' ? 'badge-danger' : 
                ($_SESSION['role_name'] === 'Employee' ? 'badge-info' : 'badge-primary')
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

          <li class="nav-item">
            <a href="service.php" class="nav-link bg-dark <?= $current_page == 'service.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-home text-primary"></i>
              <p class="text-white">Dashboard</p>
            </a>
          </li>

        <li class="nav-item">
            <a href="service_calendar.php" class="nav-link text-white <?= $current_page == 'service_calendar.php' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-calendar"></i>
                <p>Service Calendar</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_vehicle.php" class="nav-link text-white <?= $current_page == 'service_vehicle.php' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-car"></i>
                <p>Service Information</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_driver.php" class="nav-link text-white <?= $current_page == 'service_driver.php' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-user"></i>
                <p>Operator/Driver</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_request.php" class="nav-link text-white <?= $current_page == 'service_request.php' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-user"></i>
                <p>Transportation Request</p>
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
    </div>
</aside>

<style>
.sidebar-light-primary {
    background-color: #007bff !important;
}
.nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.3);
}
.nav-sidebar .nav-item > .nav-link {
    margin: 0.2rem 0.5rem;
    border-radius: 5px;
}
.brand-link.bg-primary {
    border-bottom: 1px solid #e0a800;
}
</style>