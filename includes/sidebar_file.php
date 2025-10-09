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
<aside class="main-sidebar sidebar-dark-maroon elevation-4">
    <!-- Brand Logo -->
    <a href="file_management.php" class="brand-link bg-gradient-maroon">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #3a0a17 !important;">
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
                    <a href="file_management.php" class="nav-link <?= $current_page == 'file_management.php' ? 'active bg-maroon' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
.main-sidebar.sidebar-dark-maroon {
    background-color: #5a0a1d !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    z-index: 1030 !important;
    width: 250px !important;
}

.sidebar-dark-maroon .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}

.sidebar-dark-maroon .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #800020 !important;
    color: white !important;
    border-left: 4px solid #fff;
}

.sidebar-dark-maroon .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}

.brand-link.bg-gradient-maroon {
    background: linear-gradient(135deg, #800020 0%, #5a0a1d 100%) !important;
}

.content-wrapper {
    margin-left: 250px !important;
    min-height: 100vh;
}

@media (max-width: 768px) {
    .main-sidebar.sidebar-dark-maroon {
        transform: translateX(-100%);
    }
    
    .sidebar-open .main-sidebar.sidebar-dark-maroon {
        transform: translateX(0);
    }
    
    .content-wrapper {
        margin-left: 0 !important;
    }
}

/* Additional maroon theme enhancements */
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    color: #d4a5b5 !important;
    border-bottom-color: #800020 !important;
}

.user-panel .info .badge {
    background-color: #17a2b8 !important;
    color: #ffffffff !important;
}

/* Maroon color classes for buttons */
.bg-maroon {
    background-color: #800020 !important;
}

.btn-maroon {
    background-color: #800020;
    border-color: #800020;
    color: white;
}

.btn-maroon:hover {
    background-color: #5a0a1d;
    border-color: #5a0a1d;
    color: white;
}

.bg-gradient-maroon {
    background: linear-gradient(135deg, #800020 0%, #5a0a1d 100%) !important;
}
</style>
<script>
$(document).ready(function() {
    // Force set file theme
    localStorage.setItem('currentTheme', 'file');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('file');
    }
});
</script>