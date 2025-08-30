<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">NIA-ACIMO (ADM)</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="../dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['username']) ?></a>
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
            <a href="dashboard.php" class="nav-link">
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