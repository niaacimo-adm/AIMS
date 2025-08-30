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

          <li class="nav-item">
            <a href="service.php" class="nav-link">
              <i class="nav-icon fas fa-home"></i>
              <p>Dashboard</p>
            </a>
          </li>

        <li class="nav-item">
            <a href="service_calendar.php" class="nav-link">
                <i class="nav-icon fas fa-calendar"></i>
                <p>Service Calendar</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_vehicle.php" class="nav-link">
                <i class="nav-icon fas fa-car"></i>
                <p>Service Information</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-calendar-check"></i>
                <p>Reservation Service</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_driver.php" class="nav-link">
                <i class="nav-icon fas fa-user"></i>
                <p>Operator/Driver</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="service_request.php" class="nav-link">
                <i class="nav-icon fas fa-user"></i>
                <p>Transportation Request</p>
            </a>
        </li>
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