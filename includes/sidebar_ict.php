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

// Quick badge: how many equipment items currently need attention (Under Repair)
$repair_count = 0;
if ($employee_id) {
    $rq = $db->query("SELECT COUNT(*) AS c FROM ict_equipment WHERE status = 'Under Repair'");
    if ($rq) { $repair_count = (int) $rq->fetch_assoc()['c']; }
}
?>
<style>
/* Make sidebar a flex column with fixed height */
.main-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    width: 250px !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
}

/* Brand logo stays at top */
.brand-link {
    flex-shrink: 0 !important;
}

/* Sidebar content becomes scrollable */
.sidebar {
    flex: 1 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding-bottom: 20px !important;
}

/* Keep existing styles */
.sidebar-dark-primary {
    background-color: var(--sidebar-bg) !important;
}

.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
    color: var(--sidebar-text) !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: var(--sidebar-active-bg) !important;
    color: var(--sidebar-active-text) !important;
    border-left: 4px solid rgba(255,255,255,0.5);
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: var(--sidebar-hover-bg) !important;
    color: white !important;
}
.brand-link.bg-gradient-primary {
    background: var(--sidebar-brand-bg) !important;
}
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
}

/* Custom scrollbar for better appearance */
.sidebar::-webkit-scrollbar {
    width: 5px;
}
.sidebar::-webkit-scrollbar-track {
    background: var(--sidebar-bg);
}
.sidebar::-webkit-scrollbar-thumb {
    background: #4aad7a;
    border-radius: 5px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
    background: #24e78f;
}
</style>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="ict_dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>ICT</b> Equipment</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: var(--sidebar-bg) !important;">
      <!-- Sidebar user panel -->
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

          <li class="nav-header text-light border-bottom pb-2 mt-3">ICT EQUIPMENT</li>

          <?php if (hasPermission('view_ict_equipment') || hasPermission('manage_ict_maintenance')): ?>
          <li class="nav-item">
            <a href="ict_dashboard.php" class="nav-link <?= $current_page == 'ict_dashboard.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>ICT Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="ict_equipment.php" class="nav-link <?= $current_page == 'ict_equipment.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-desktop"></i>
              <p>Equipment Inventory</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="ict_assignments.php" class="nav-link <?= $current_page == 'ict_assignments.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-user-tag"></i>
              <p>Assign / Return</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="ict_scanner.php" class="nav-link <?= $current_page == 'ict_scanner.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-qrcode"></i>
              <p>QR Scanner</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_ict_maintenance')): ?>
          <li class="nav-item">
            <a href="ict_maintenance.php" class="nav-link <?= $current_page == 'ict_maintenance.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-tools"></i>
              <p>
                Maintenance Logs
                <?php if ($repair_count > 0): ?>
                  <span class="badge badge-warning right"><?= $repair_count ?></span>
                <?php endif; ?>
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="ict_categories.php" class="nav-link <?= $current_page == 'ict_categories.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-sitemap"></i>
              <p>Categories</p>
            </a>
          </li>
          <?php endif; ?>

        </ul>
      </nav>
    </div>
</aside>
<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'ict');
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('ict');
    }
});
</script>