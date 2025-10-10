<?php
    require_once '../config/database.php';
    require_once 'helpers.php';
?>
<?php
// Determine current theme based on page
$current_page = basename($_SERVER['PHP_SELF']);
$current_theme = 'admin'; // default

if (strpos($current_page, 'service') !== false) {
    $current_theme = 'service';
} elseif (strpos($current_page, 'inventory') !== false) {
    $current_theme = 'inventory';
} elseif (strpos($current_page, 'file_management') !== false) {
    $current_theme = 'file';
}

// Store in session for persistence
$_SESSION['current_theme'] = $current_theme;

// Get employee data directly
$employee_name = '';
$employee_initials = 'JD';
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
        
        // Generate initials
        $names = explode(' ', $employee_name);
        if (count($names) >= 2) {
            $employee_initials = strtoupper(substr($names[0], 0, 1) . substr($names[1], 0, 1));
        } else {
            $employee_initials = strtoupper(substr($employee_name, 0, 2));
        }
    }
}
?>
<nav class="main-header navbar navbar-expand">
    <ul class="navbar-nav me-auto">
        <li class="nav-item">
            <a class="nav-link sidebar-toggle" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>
        <li class="nav-item dropdown apps-dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-th me-1"></i> Apps
            </a>
            <div class="dropdown-menu">
                <div class="dropdown-header">Application Pages</div>
                <div class="row g-2 p-2">
                    <div class="col-6">
                        <a href="dashboard.php" class="app-item" data-theme="admin">
                            <div class="app-icon">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <span class="app-name">Admin Section</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="service.php" class="app-item" data-theme="service">
                            <div class="app-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <span class="app-name">Reserve Service</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="inventory.php" class="app-item" data-theme="inventory">
                            <div class="app-icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <span class="app-name">Procurement Inventory</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="file_management.php" class="app-item" data-theme="file">
                            <div class="app-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <span class="app-name">File Management</span>
                        </a>
                    </div>
                </div>
            </div>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <!-- Notifications Dropdown -->
        <li class="nav-item dropdown notification-dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false" id="notificationDropdown">
                <i class="far fa-bell"></i>
                <?php
                // Get unread notification count for current admin - ORIGINAL PHP CODE
                if (isset($_SESSION['emp_id'])) {
                    $database = new Database();
                    $db = $database->getConnection();
                    
                    $query = "SELECT COUNT(*) as unread_count FROM admin_notifications 
                            WHERE admin_emp_id = ? AND is_read = 0";
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("i", $_SESSION['emp_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['unread_count'];
                    
                    if ($count > 0) {
                        echo '<span class="notification-badge" id="notificationCount">' . $count . '</span>';
                    }
                }
                ?>
            </a>
            <div class="dropdown-menu">
                <div class="notification-header">
                    <span>Notifications</span>
                    <span class="notification-count" id="notificationHeader">
                        <?= isset($count) && $count > 0 ? $count . ' New' : 'No Notifications' ?>
                    </span>
                </div>
                <div class="notification-list" id="notificationList">
                    <?php
                    // ORIGINAL PHP CODE for notifications
                    if (isset($_SESSION['emp_id'])) {
                        $query = "SELECT * FROM admin_notifications 
                                WHERE admin_emp_id = ? 
                                ORDER BY created_at DESC 
                                LIMIT 10";
                        $stmt = $db->prepare($query);
                        $stmt->bind_param("i", $_SESSION['emp_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $time_ago = time_elapsed_string($row['created_at']);
                                $read_class = $row['is_read'] ? '' : 'unread';
                                
                                echo '<div class="notification-item ' . $read_class . '" data-notification-id="' . $row['id'] . '">
                                        <div class="notification-content">
                                            <div class="notification-icon">
                                                <i class="fas fa-key"></i>
                                            </div>
                                            <div>
                                                <div class="notification-text">' . htmlspecialchars_decode($row['message']) . '</div>
                                                <div class="notification-time">' . $time_ago . '</div>
                                            </div>
                                        </div>
                                        </div>';
                            }
                        } else {
                            echo '<div class="text-center py-4 text-muted">No notifications</div>';
                        }
                    }
                    ?>
                </div>
                <div class="notification-actions">
                    <button class="btn btn-sm btn-outline-primary btn-notification mark-all-read-btn">
                        <i class="fas fa-check-double me-1"></i> Mark All Read
                    </button>
                    <button class="btn btn-sm btn-outline-danger btn-notification delete-all-btn">
                        <i class="fas fa-trash me-1"></i> Delete All
                    </button>
                </div>
                <a href="#" class="dropdown-item text-center py-2" data-toggle="modal" data-target="#allNotificationsModal">
                    See All Notifications
                </a>
            </div>
        </li>
        
        <!-- Fullscreen Toggle -->
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
        
            <!-- Profile Dropdown -->
            <li class="nav-item dropdown profile-dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
                    <div class="profile-avatar">
                        <?php if (!empty($employee_data['picture']) && file_exists('../dist/img/employees/' . $employee_data['picture'])): ?>
                            <img src="../dist/img/employees/<?= $employee_data['picture'] ?>" alt="<?= $employee_name ?>" class="profile-avatar-img">
                        <?php else: ?>
                            <span><?= $employee_initials ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="profile-name d-none d-md-inline"><?= $employee_name ?: 'User' ?></span>
                    <i class="fas fa-chevron-down d-none d-md-inline"></i>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="admin_approve_reset.php"><i class="fas fa-lock"></i> Change Password</a></li>
                    <li><a class="dropdown-item" href="../index.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </li>
    </ul>
</nav>

<!-- All Notifications Modal -->
<div class="modal fade" id="allNotificationsModal" tabindex="-1" aria-labelledby="allNotificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allNotificationsModalLabel">All Notifications</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                // ORIGINAL PHP CODE for all notifications modal
                if (isset($_SESSION['emp_id'])) {
                    $query = "SELECT * FROM admin_notifications 
                            WHERE admin_emp_id = ? 
                            ORDER BY created_at DESC";
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("i", $_SESSION['emp_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $all_notifications = $result->fetch_all(MYSQLI_ASSOC);
                ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Message</th>
                                <th width="120">Status</th>
                                <th width="150">Date</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_notifications) > 0): ?>
                                <?php foreach ($all_notifications as $notification): ?>
                                    <tr class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                        <td><?= htmlspecialchars_decode($notification['message']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $notification['is_read'] ? 'success' : 'warning' ?>">
                                                <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
                                            </span>
                                        </td>
                                        <td><?= time_elapsed_string($notification['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-notification" data-id="<?= $notification['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-notification" data-id="<?= $notification['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No notifications found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="modalMarkAllRead">
                    <i class="fas fa-check-double me-1"></i> Mark All as Read
                </button>
                <button type="button" class="btn btn-outline-danger" id="modalDeleteAll">
                    <i class="fas fa-trash me-1"></i> Delete All
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --accent-color: #4895ef;
        --light-color: #f8f9fa;
        --dark-color: #212529;
        --success-color: #4cc9f0;
        --warning-color: #f72585;
        --gray-color: #6c757d;
        --light-gray: #e9ecef;
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-hover: 0 10px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Profile Picture Styles */
    .profile-avatar-img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .profile-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-color), var(--success-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        margin-right: 0.5rem;
        overflow: hidden;
        position: relative;
    }

    .profile-avatar span {
        font-size: 0.8rem;
        z-index: 2;
    }
    /* Fix dropdown z-index issues */
    .navbar-nav .dropdown-menu {
        z-index: 1030 !important;
    }

    /* Fix modal backdrop */
    .modal-backdrop {
        z-index: 1029 !important;
    }

    .modal {
        z-index: 1030 !important;
    }

    /* Ensure proper sidebar functionality */
    .sidebar-collapse .main-sidebar {
        margin-left: -250px;
    }

    .main-sidebar {
        transition: margin-left 0.3s ease-in-out;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f7fb;
    }

    /* Modern Navbar */
    .main-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        box-shadow: var(--shadow);
        padding: 0.5rem 1rem;
        transition: var(--transition);
        border: none;
    }
    .main-header.theme-admin {
        background: linear-gradient(135deg, #4361ee, #3f37c9) !important;
    }

    .main-header.theme-service {
        background: linear-gradient(135deg, #ffc107, #fd7e14) !important;
    }

    .main-header.theme-inventory {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
    }

    .main-header.theme-file {
        background: linear-gradient(135deg, #800020, #5a0a1d) !important;
    }

    /* Apps dropdown theming */
    .apps-dropdown .app-item[data-theme="admin"] .app-icon {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
    }

    .apps-dropdown .app-item[data-theme="service"] .app-icon {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }

    .apps-dropdown .app-item[data-theme="inventory"] .app-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .apps-dropdown .app-item[data-theme="file"] .app-icon {
        background: linear-gradient(135deg, #800020, #5a0a1d);
    }
    .navbar-brand {
        color: white;
        font-weight: 700;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
    }

    .navbar-brand i {
        margin-right: 0.5rem;
        font-size: 1.8rem;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.85) !important;
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        transition: var(--transition);
        position: relative;
    }

    .nav-link:hover, .nav-link:focus {
        color: white !important;
        background-color: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    .nav-link.active {
        color: white !important;
        background-color: rgba(255, 255, 255, 0.15);
    }

    /* Apps Dropdown */
    .apps-dropdown .dropdown-toggle {
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        padding: 0.5rem 1rem;
    }

    .apps-dropdown .dropdown-menu {
        border: none;
        border-radius: 0.75rem;
        box-shadow: var(--shadow-hover);
        padding: 0.5rem;
        width: 380px;
        margin-top: 0.5rem;
    }

    .apps-dropdown .dropdown-header {
        font-weight: 600;
        color: var(--dark-color);
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--light-gray);
    }

    .app-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem 0.5rem;
        border-radius: 0.5rem;
        transition: var(--transition);
        text-decoration: none;
        color: var(--dark-color);
    }

    .app-item:hover {
        background-color: var(--light-gray);
        transform: translateY(-3px);
        box-shadow: var(--shadow);
    }

    .app-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
    }

    .app-name {
        font-weight: 500;
        font-size: 0.85rem;
        text-align: center;
    }

    /* Notification Styles */
    .notification-dropdown .dropdown-toggle {
        position: relative;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: linear-gradient(135deg, var(--warning-color), #b5179e);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .notification-dropdown .dropdown-menu {
        border: none;
        border-radius: 0.75rem;
        box-shadow: var(--shadow-hover);
        width: 380px;
        padding: 0;
        overflow: hidden;
    }

    .notification-header {
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-count {
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        padding: 0.25rem 0.75rem;
        font-size: 0.8rem;
    }

    .notification-list {
        max-height: 350px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .notification-item {
        padding: 0.75rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        transition: var(--transition);
        border-left: 3px solid transparent;
        cursor: pointer;
    }

    .notification-item.unread {
        background-color: rgba(67, 97, 238, 0.05);
        border-left-color: var(--primary-color);
        font-weight: 500;
    }

    .notification-item:hover {
        background-color: var(--light-gray);
    }

    .notification-content {
        display: flex;
        align-items: flex-start;
    }

    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--light-gray);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        flex-shrink: 0;
        color: var(--primary-color);
    }

    .notification-text {
        flex: 1;
        font-size: 0.9rem;
        line-height: 1.4;
        word-wrap: break-word;
        white-space: normal;
    }

    .notification-time {
        font-size: 0.75rem;
        color: var(--gray-color);
        margin-top: 0.25rem;
    }

    .notification-actions {
        padding: 0.75rem;
        border-top: 1px solid var(--light-gray);
        display: flex;
        justify-content: space-between;
    }

    .btn-notification {
        border-radius: 0.5rem;
        font-size: 0.85rem;
        padding: 0.4rem 0.75rem;
        font-weight: 500;
    }

    /* Profile Dropdown */
    .profile-dropdown .dropdown-toggle {
        display: flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border-radius: 2rem;
        background-color: rgba(255, 255, 255, 0.1);
        transition: var(--transition);
    }

    .profile-dropdown .dropdown-toggle:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .profile-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-color), var(--success-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        margin-right: 0.5rem;
    }

    .profile-name {
        color: white;
        font-weight: 500;
        margin-right: 0.5rem;
    }

    .profile-dropdown .dropdown-menu {
        border: none;
        border-radius: 0.75rem;
        box-shadow: var(--shadow-hover);
        padding: 0.5rem;
        min-width: 200px;
    }

    .profile-dropdown .dropdown-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        color: var(--dark-color);
        transition: var(--transition);
    }

    .profile-dropdown .dropdown-item i {
        margin-right: 0.75rem;
        width: 20px;
        text-align: center;
        color: var(--gray-color);
    }

    .profile-dropdown .dropdown-item:hover {
        background-color: var(--light-gray);
    }

    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 0.75rem;
        box-shadow: var(--shadow-hover);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1rem 1.5rem;
    }

    .modal-title {
        font-weight: 600;
    }

    .modal-footer {
        border-top: 1px solid var(--light-gray);
        padding: 1rem 1.5rem;
    }

    /* Ensure links in notifications are clickable */
    .notification-text a {
        color: #007bff !important;
        text-decoration: underline !important;
        display: inline !important;
        font-weight: 500;
        pointer-events: auto !important;
        z-index: 1000;
        position: relative;
    }

    .notification-text a:hover {
        color: #0056b3 !important;
        text-decoration: none !important;
        cursor: pointer !important;
    }

    /* Sidebar Toggle Animation */
    .sidebar-collapse .main-sidebar {
        margin-left: -250px;
        transition: margin-left 0.3s ease-in-out;
    }

    .sidebar-collapse .content-wrapper,
    .sidebar-collapse .main-footer {
        margin-left: 0;
        transition: margin-left 0.3s ease-in-out;
    }

    .main-sidebar {
        margin-left: 0;
        transition: margin-left 0.3s ease-in-out;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .apps-dropdown .dropdown-menu {
            width: 300px;
        }
        
        .notification-dropdown .dropdown-menu {
            width: 320px;
        }
        
        .navbar-nav {
            flex-direction: row;
        }
        
        .sidebar-collapse .main-sidebar {
            margin-left: -250px;
        }
    }
</style>

<script>
    $(document).ready(function() {
        // Get base URL for AJAX calls - ORIGINAL JAVASCRIPT
        const baseUrl = window.location.origin + '/NIA-PROJECT/views/';
        console.log('Base URL:', baseUrl);
        
        // Fix pushmenu functionality for Bootstrap 4
        $('[data-widget="pushmenu"]').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Use AdminLTE if available
            if (typeof $ !== 'undefined' && $.fn.pushMenu) {
                $('body').pushMenu('toggle');
            } else {
                // Manual toggle for sidebar
                $('body').toggleClass('sidebar-collapse');
                $('body').toggleClass('sidebar-open');
            }
            
            // Update localStorage for persistence
            const isCollapsed = $('body').hasClass('sidebar-collapse');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        });

        // Check for saved sidebar state on page load
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            $('body').addClass('sidebar-collapse');
        }

        // Fullscreen toggle
        $('[data-widget="fullscreen"]').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Error attempting to enable fullscreen: ${err.message}`);
                });
                $(this).html('<i class="fas fa-compress"></i>');
                toastr.info('Entered fullscreen mode');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    $(this).html('<i class="fas fa-expand-arrows-alt"></i>');
                    toastr.info('Exited fullscreen mode');
                }
            }
        });


        // Mark all notifications as read (dropdown)
        $('.mark-all-read-btn').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            $.ajax({
                url: baseUrl + 'mark_all_notifications_read.php',
                type: 'POST',
                data: {emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>},
                success: function(response) {
                    if (response.success) {
                        // Update UI with modern classes
                        $('.notification-item').removeClass('unread');
                        $('#notificationCount').remove();
                        $('#notificationHeader').text('No Notifications');
                        $('.notification-item').removeClass('unread');
                        $('.badge-warning').removeClass('badge-warning').addClass('badge-success').text('Read');
                        
                        // Show success message
                        toastr.success('All notifications marked as read');
                    } else {
                        toastr.error('Error marking notifications as read');
                    }
                },
                error: function() {
                    toastr.error('Error marking notifications as read');
                }
            });
        });
        
        // Delete all notifications (dropdown)
        $('.delete-all-btn').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (confirm('Are you sure you want to delete all notifications?')) {
                $.ajax({
                    url: baseUrl + 'delete_all_notifications.php',
                    type: 'POST',
                    data: {emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>},
                    success: function(response) {
                        if (response.success) {
                            // Update UI
                            $('#notificationList').html('<div class="text-center py-4 text-muted">No notifications</div>');
                            $('#notificationCount').remove();
                            $('#notificationHeader').text('No Notifications');
                            $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4 text-muted">No notifications found</td></tr>');
                            
                            // Show success message
                            toastr.success('All notifications deleted');
                        } else {
                            toastr.error('Error deleting notifications');
                        }
                    },
                    error: function() {
                        toastr.error('Error deleting notifications');
                    }
                });
            }
        });
        
        // Mark all notifications as read (modal)
        $('#modalMarkAllRead').click(function() {
            $.ajax({
                url: baseUrl + 'mark_all_notifications_read.php',
                type: 'POST',
                data: {emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>},
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $('.notification-item').removeClass('unread');
                        $('.badge-warning').removeClass('badge-warning').addClass('badge-success').text('Read');
                        $('.notification-item').removeClass('unread');
                        $('#notificationCount').remove();
                        $('#notificationHeader').text('No Notifications');
                        
                        // Show success message
                        toastr.success('All notifications marked as read');
                    } else {
                        toastr.error('Error marking notifications as read');
                    }
                },
                error: function() {
                    toastr.error('Error marking notifications as read');
                }
            });
        });
        
        // Delete all notifications (modal)
        $('#modalDeleteAll').click(function() {
            if (confirm('Are you sure you want to delete all notifications?')) {
                $.ajax({
                    url: baseUrl + 'delete_all_notifications.php',
                    type: 'POST',
                    data: {emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>},
                    success: function(response) {
                        if (response.success) {
                            // Update UI
                            $('#notificationList').html('<div class="text-center py-4 text-muted">No notifications</div>');
                            $('#notificationCount').remove();
                            $('#notificationHeader').text('No Notifications');
                            $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4 text-muted">No notifications found</td></tr>');
                            
                            // Show success message
                            toastr.success('All notifications deleted');
                        } else {
                            toastr.error('Error deleting notifications');
                        }
                    },
                    error: function() {
                        toastr.error('Error deleting notifications');
                    }
                });
            }
        });
        
        // Delete single notification
        $(document).on('click', '.delete-notification', function() {
            const notificationId = $(this).data('id');
            const $row = $(this).closest('tr');
            
            if (confirm('Are you sure you want to delete this notification?')) {
                $.ajax({
                    url: '../views/delete_notification.php',
                    type: 'POST',
                    data: {id: notificationId},
                    success: function(response) {
                        if (response.success) {
                            // Remove the row from the table
                            $row.remove();
                            
                            // Check if table is empty
                            if ($('#allNotificationsModal tbody tr').length === 0) {
                                $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4 text-muted">No notifications found</td></tr>');
                            }
                            
                            // Update dropdown count
                            updateNotificationCount();
                            
                            toastr.success('Notification deleted');
                        } else {
                            toastr.error('Error deleting notification');
                        }
                    },
                    error: function() {
                        toastr.error('Error deleting notification');
                    }
                });
            }
        });
        
        // View notification (mark as read)
        $(document).on('click', '.view-notification', function() {
            const notificationId = $(this).data('id');
            const $row = $(this).closest('tr');
            
            $.ajax({
                url: baseUrl + 'mark_notification_read.php',
                type: 'POST',
                data: {id: notificationId},
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $row.removeClass('unread');
                        $row.find('.badge').removeClass('badge-warning').addClass('badge-success').text('Read');
                        
                        // Update dropdown if this notification is there
                        $('div[data-notification-id="' + notificationId + '"]').removeClass('unread');
                        
                        // Update count
                        updateNotificationCount();
                        
                        toastr.success('Notification marked as read');
                    } else {
                        toastr.error('Error marking notification as read');
                    }
                },
                error: function() {
                    toastr.error('Error marking notification as read');
                }
            });
        });
        
        // Function to update notification count - ORIGINAL FUNCTION
        function updateNotificationCount() {
            $.ajax({
                url: baseUrl + 'get_notification_count.php',
                type: 'GET',
                success: function(response) {
                    if (response.count > 0) {
                        if ($('#notificationCount').length) {
                            $('#notificationCount').text(response.count);
                        } else {
                            $('#notificationDropdown').append('<span class="notification-badge" id="notificationCount">' + response.count + '</span>');
                        }
                        $('#notificationHeader').text(response.count + ' New');
                    } else {
                        $('#notificationCount').remove();
                        $('#notificationHeader').text('No Notifications');
                    }
                }
            });
        }
        
        // Refresh modal content when opened - ORIGINAL FUNCTION
        $('#allNotificationsModal').on('show.bs.modal', function () {
            $.ajax({
                url: baseUrl + 'get_all_notifications.php',
                type: 'GET',
                success: function(response) {
                    $('#allNotificationsModal tbody').html(response);
                }
            });
        });

        // ORIGINAL NOTIFICATION CLICK HANDLERS

        // Handle clicks on links within notifications - FIXED
        $(document).on('click', '.notification-text a', function(e) {
            console.log('Link clicked:', $(this).attr('href'));
            e.stopPropagation();
            
            // Allow default link behavior (navigation)
            const href = $(this).attr('href');
            
            if (href && href !== '#') {
                console.log('Allowing navigation to:', href);
                return true;
            }
        });

        // Update the notification click handler - FIXED
        $(document).on('click', '.notification-item[data-notification-id]', function(e) {
            // If click was on a link or within a link, do nothing
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
                return;
            }
            
            // Handle notification click for marking as read
            const notificationId = $(this).data('notification-id');
            if (notificationId) {
                $.ajax({
                    url: baseUrl + 'mark_notification_read.php',
                    type: 'POST',
                    data: {id: notificationId},
                    success: function(response) {
                        if (response.success) {
                            $(this).removeClass('unread');
                            updateNotificationCount();
                        }
                    }.bind(this)
                });
            }
        });

        // Initialize toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        // Fix dropdown positioning issues
        $(document).on('show.bs.dropdown', function(e) {
            var $dropdown = $(e.target).find('.dropdown-menu');
            if ($dropdown.length) {
                var $parent = $dropdown.parent();
                var $window = $(window);
                var rect = $parent[0].getBoundingClientRect();
                
                // Check if dropdown would go off screen
                if (rect.right + $dropdown.outerWidth() > $window.width()) {
                    $dropdown.addClass('dropdown-menu-right');
                }
            }
        });
        // Force close other dropdowns when one opens

        
    });
</script>
<script>
    $(document).ready(function() {
        // Theme configuration
        const themes = {
            'admin': {
                header: 'linear-gradient(135deg, #4361ee, #3f37c9)',
                footer: 'linear-gradient(135deg, #4361ee, #3f37c9)',
                class: 'theme-admin'
            },
            'service': {
                header: 'linear-gradient(135deg, #ffc107, #fd7e14)',
                footer: 'linear-gradient(135deg, #ffc107, #fd7e14)',
                class: 'theme-service'
            },
            'inventory': {
                header: 'linear-gradient(135deg, #28a745, #20c997)',
                footer: 'linear-gradient(135deg, #28a745, #20c997)',
                class: 'theme-inventory'
            },
            'file': {
                header: 'linear-gradient(135deg, #800020, #5a0a1d)',
                footer: 'linear-gradient(135deg, #800020, #5a0a1d)',
                class: 'theme-file'
            }
        };

        // Function to set theme
        function setTheme(themeName) {
            console.log('Setting theme:', themeName);
            const theme = themes[themeName];
            if (!theme) return;

            // Update header
            const header = $('.main-header');
            if (header.length) {
                header.css('background', theme.header);
                header.removeClass('theme-admin theme-service theme-inventory theme-file');
                header.addClass(theme.class);
                console.log('Header updated');
            }

            // Update footer
            const footer = $('#mainFooter');
            if (footer.length) {
                footer.css('background', theme.footer);
                footer.removeClass('theme-admin theme-service theme-inventory theme-file');
                footer.addClass(theme.class);
                console.log('Footer updated');
            }

            // Save theme to localStorage
            localStorage.setItem('currentTheme', themeName);
            
            // Update notification header background to match theme
            $('.notification-header').css('background', theme.header);
        }

        // Handle app clicks
        $(document).on('click', '.app-item', function(e) {
            const theme = $(this).data('theme');
            console.log('App clicked, theme:', theme);
            if (theme) {
                // Set theme immediately before navigation
                setTheme(theme);
                // Allow navigation to proceed
            }
        });

        // Set theme based on current page
        function setThemeFromPage() {
            const currentPage = window.location.pathname;
            console.log('Current page:', currentPage);
            let theme = 'admin'; // default
            
            if (currentPage.includes('service')) {
                theme = 'service';
            } else if (currentPage.includes('inventory')) {
                theme = 'inventory';
            } else if (currentPage.includes('file_management')) {
                theme = 'file';
            }
            
            console.log('Detected theme:', theme);
            setTheme(theme);
        }

        // Set theme on page load with delay to ensure DOM is ready
        setTimeout(function() {
            // Check if theme is already set by sidebar
            const sidebarTheme = localStorage.getItem('currentTheme');
            if (!sidebarTheme) {
                setThemeFromPage();
            } else {
                setTheme(sidebarTheme);
            }
        }, 100);

        // Update notification dropdown header to match current theme
        function updateNotificationHeaderTheme() {
            const currentTheme = localStorage.getItem('currentTheme') || 'admin';
            const theme = themes[currentTheme];
            if (theme) {
                $('.notification-header').css('background', theme.header);
            }
        }

        // Update notification header when dropdown is shown
        $('.notification-dropdown').on('show.bs.dropdown', function() {
            updateNotificationHeaderTheme();
        });

        // Listen for theme changes from other pages
        $(window).on('storage', function(e) {
            if (e.originalEvent.key === 'currentTheme') {
                setTheme(e.originalEvent.newValue);
            }
        });
    });
</script>
<script>
// Force theme application on load
$(window).on('load', function() {
    setTimeout(function() {
        const currentTheme = localStorage.getItem('currentTheme') || 'admin';
        const themes = {
            'admin': 'linear-gradient(135deg, #4361ee, #3f37c9)',
            'service': 'linear-gradient(135deg, #ffc107, #fd7e14)',
            'inventory': 'linear-gradient(135deg, #28a745, #20c997)',
            'file': 'linear-gradient(135deg, #800020, #5a0a1d)'
        };
        
        $('.main-header').css('background', themes[currentTheme]);
        $('#mainFooter').css('background', themes[currentTheme]);
    }, 200);
});
// Set module cookie based on current theme when profile is accessed from header
function setModuleCookie() {
    const currentTheme = localStorage.getItem('currentTheme') || 'admin';
    document.cookie = `current_module=${currentTheme}; path=/; max-age=300`; // 5 minutes
}

// Call this when profile link is clicked in header
$(document).ready(function() {
    $('.profile-dropdown a[href="profile.php"]').on('click', function(e) {
        setModuleCookie();
        // Allow normal navigation to proceed
    });
    
    // Also set cookie on page load if we're on profile page
    if (window.location.pathname.includes('profile.php')) {
        setModuleCookie();
    }
});
// Sync theme when profile page is loaded
function syncProfileTheme() {
    if (window.location.pathname.includes('profile.php')) {
        const urlParams = new URLSearchParams(window.location.search);
        const theme = urlParams.get('theme') || localStorage.getItem('currentTheme') || 'admin';
        setTheme(theme);
    }
}

// Call this on page load
$(document).ready(function() {
    syncProfileTheme();
});
</script>
<?php include '../includes/footer.php'; ?>