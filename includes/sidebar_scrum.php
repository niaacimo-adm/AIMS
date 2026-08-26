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

<!-- Scrumboard Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
    </a>
    <div class="sidebar" style="background-color: var(--sidebar-bg) !important;">
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
            <a href="scrum_dashboard.php" class="nav-link <?= $current_page == 'scrum_dashboard.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="scrum_project.php" class="nav-link <?= $current_page == 'scrum_project.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-project-diagram"></i>
              <p>Projects Monitoring</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="my_scrum_project.php" class="nav-link <?= $current_page == 'my_scrum_project.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-folder-open"></i>
              <p>My Projects</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="my_scrum_task.php" class="nav-link <?= $current_page == 'my_scrum_task.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-tasks"></i>
              <p>My Tasks</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
</aside>
<style>
/*
 * Matches the layout/typography conventions of the app's standard
 * sidebar.php so the scrum module doesn't look or behave differently
 * from the rest of AIMS. Colors still come entirely from the CSS
 * variables defined in mainheader.php — nothing here is hardcoded
 * per light/dark mode.
 */

/* Fixed, full-height sidebar with a scrollable nav area (same as sidebar.php) */
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
.brand-link {
    flex-shrink: 0 !important;
}
.sidebar {
    flex: 1 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding-bottom: 10px !important;
}
.sidebar-dark-primary {
    background-color: var(--sidebar-bg) !important;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
    color: var(--sidebar-text) !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
    transition: background-color .15s ease, color .15s ease;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: var(--sidebar-active-bg) !important;
    color: var(--sidebar-active-text) !important;
    border-left: 4px solid rgba(255,255,255,0.5);
    font-weight: 700;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: var(--sidebar-hover-bg) !important;
    color: white !important;
}
.brand-link.bg-gradient-primary {
    background: var(--sidebar-brand-bg) !important;
}
.main-sidebar.sidebar-dark-primary .nav-sidebar .nav-icon {
    width: 20px;
    text-align: center;
    margin-right: 8px;
}
.main-sidebar.sidebar-dark-primary .user-panel img {
    border: 2px solid var(--sidebar-active-bg, #24e78f);
}

/* Custom scrollbar to match sidebar.php */
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

/* Scrum-specific footer (New Board / Menu) — pinned below the scrollable nav,
   themed with the same sidebar accent variables as everything else. */
.sidebar-footer {
    flex-shrink: 0 !important;
    padding: 10px 12px;
}
.sidebar-footer #newBoardBtn {
    background: var(--sidebar-active-bg, #24e78f) !important;
    color: var(--sidebar-active-text, #0f2d1e) !important;
    border: none !important;
    font-weight: 700;
    border-radius: 6px !important;
    transition: filter .15s ease;
}
.sidebar-footer #newBoardBtn:hover { filter: brightness(1.08); }
.sidebar-footer #mobileToggle {
    border-radius: 6px !important;
    color: var(--sidebar-text, rgba(255,255,255,.8)) !important;
    border-color: var(--sidebar-text, rgba(255,255,255,.3)) !important;
}
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