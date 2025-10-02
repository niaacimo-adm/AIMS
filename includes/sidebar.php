<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

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
<aside class="main-sidebar sidebar-dark-primary elevation-4">
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
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge <?= 
                $_SESSION['role_name'] === 'Administrator' ? 'badge-danger' : 
                ($_SESSION['role_name'] === 'Employee' ? 'badge-warning' : 'badge-primary')
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

          <?php if (hasPermission('view_dashboard')): ?>
          <li class="nav-item">
            <a href="dashboard.php" class="nav-link active">
              <i class="nav-icon fas fa-home"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('view_calendar')): ?>
          <li class="nav-item">
            <a href="calendar.php" class="nav-link">
              <i class="nav-icon fas fa-calendar"></i>
              <p>Calendar</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_employees')): ?>
          <li class="nav-header">EMPLOYEE MANAGEMENT</li>
            <?php if (hasPermission('create_employees')): ?>
              <li class="nav-item">
                <a href="../views/emp.create.php" class="nav-link">
                  <i class="fas fa-plus nav-icon"></i>
                  <p>Create</p>
                </a>
              </li>
              <?php endif; ?>

              <?php if (hasPermission('view_employees')): ?>
              <li class="nav-item">
                <a href="../views/emp.list.php" class="nav-link">
                  <i class="fas fa-list nav-icon"></i>
                  <p>List</p>
                </a>
              </li>
              <?php endif; ?>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_settings')): ?>
          <li class="nav-header">SETTINGS</li>
          <li class="nav-item">
              <a href="content_management.php" class="nav-link">
                  <i class="fas fa-tv nav-icon"></i>
                  <p>Appointment Settings</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="appointment_status.php" class="nav-link">
                  <i class="fas fa-circle nav-icon"></i>
                  <p>Appointment Settings</p>
              </a>
          </li>
          <li class="nav-item">
            <a href="position.php" class="nav-link">
                <i class="fas fa-circle nav-icon"></i>
                <p>Positions</p>
            </a>
          </li>
          <li class="nav-item">
              <a href="sections.php" class="nav-link">
                  <i class="fas fa-circle nav-icon"></i>
                  <p>Sections</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="offices.php" class="nav-link">
                  <i class="fas fa-circle nav-icon"></i>
                  <p>Offices</p>
              </a>
          </li>
          <li class="nav-item">
              <a href="employment_status.php" class="nav-link">
                  <i class="fas fa-circle nav-icon"></i>
                  <p>Employment Status</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_users')): ?>
          <li class="nav-item">
              <a href="users.php" class="nav-link">
                  <i class="nav-icon fas fa-users"></i>
                  <p>Users</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_roles')): ?>
          <li class="nav-item">
              <a href="roles.php" class="nav-link">
                  <i class="nav-icon fas fa-user-shield"></i>
                  <p>Roles</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_permissions')): ?>
          <li class="nav-item">
              <a href="permissions.php" class="nav-link">
                  <i class="nav-icon fas fa-key"></i>
                  <p>Permissions</p>
              </a>
          </li>
          <?php endif; ?>

          <!-- Common menu items for all users -->
          <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a href="profile.php" class="nav-link">
              <i class="nav-icon fas fa-user"></i>
              <p>My Profile</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../logout.php" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Logout</p>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
</aside>