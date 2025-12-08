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
} elseif (strpos($current_page, 'inventory') !== false && strpos($current_page, 'ict_') === false) {
    $current_theme = 'inventory';
} elseif (strpos($current_page, 'file_management') !== false) {
    $current_theme = 'file';
} elseif (strpos($current_page, 'ict_') !== false) {
    $current_theme = 'ict';
} elseif (strpos($current_page, 'ia_profile') !== false || strpos($current_page, 'ia_profiles') !== false) {
    $current_theme = 'ia';
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
                        <a href="queue.php" class="app-item" data-theme="queue">
                            <div class="app-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <span class="app-name">Queue Management</span>
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
                    <div class="col-6">
                        <a href="ict_inventory.php" class="app-item" data-theme="ict">
                            <div class="app-icon">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <span class="app-name">ICT Equipment Inventory</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="ia_profiles.php" class="app-item" data-theme="ia">
                            <div class="app-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="app-name">IA Profiles</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="document_dashboard.php" class="app-item" data-theme="document">
                            <div class="app-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <span class="app-name">Document Monitoring Records</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="scrum.php" class="app-item" data-theme="scrum">
                            <div class="app-icon">
                                <i class="fas fa-scroll"></i>
                            </div>
                            <span class="app-name">Scrum Board</span>
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
                // Get unread notification count for current user
                if (isset($_SESSION['emp_id'])) {
                    require_once 'leave_functions.php';
                    $leaveFunctions = new LeaveFunctions();

                    // Check if user is admin and should see all notifications
                    $user_role = $_SESSION['role'] ?? '';
                    if ($user_role === 'admin') {
                        // Admins see notifications for all leave requests
                        $unread_count = $leaveFunctions->getAdminNotificationCount();
                    } else {
                        // Regular users see only their notifications
                        $unread_count = $leaveFunctions->getUnreadNotificationCount($_SESSION['emp_id']);
                    }

                    if ($unread_count > 0) {
                        echo '<span class="notification-badge" id="notificationCount">' . $unread_count . '</span>';
                    }
                }

                if (isset($_SESSION['emp_id'])) {
                    $user_role = $_SESSION['role'] ?? '';
                    if ($user_role === 'admin') {
                        $notifications = $leaveFunctions->getAdminNotifications();
                    } else {
                        $notifications = $leaveFunctions->getUserNotifications($_SESSION['emp_id']);
                    }
                }
                ?>
            </a>
            <div class="dropdown-menu">
                <div class="notification-header">
                    <span>Notifications</span>
                    <span class="notification-count" id="notificationHeader">
                        <?php
                        if (isset($unread_count)) {
                            echo $unread_count > 0 ? $unread_count . ' New' : 'No Notifications';
                        } else {
                            echo 'No Notifications';
                        }
                        ?>
                    </span>
                </div>
                <div class="notification-list" id="notificationList">
                    <?php
                    if (isset($_SESSION['emp_id'])) {
                        $user_role = $_SESSION['role'] ?? '';

                        // Get notifications based on user role
                        if ($user_role === 'admin') {
                            $notifications = $leaveFunctions->getAdminNotifications();
                        } else {
                            $notifications = $leaveFunctions->getUserNotifications($_SESSION['emp_id']);
                        }

                        if (count($notifications) > 0) {
                            foreach ($notifications as $notification) {
                                $time_ago = time_elapsed_string($notification['created_at']);
                                $read_class = $notification['is_read'] ? '' : 'unread';
                                $link = $notification['link'] ?? '#';

                                // Make sure the link is properly set for all roles
                                if (empty($link) && strpos($notification['message'], 'leave request') !== false) {
                                    // Extract leave ID from message for fallback
                                    preg_match('/#(\d+)/', $notification['message'], $matches);
                                    if (isset($matches[1])) {
                                        $link = "leave_approval.php?leave_id=" . $matches[1];
                                    }
                                }

                                echo '<a href="' . $link . '" class="notification-link" style="text-decoration: none; color: inherit;">';
                                echo '<div class="notification-item ' . $read_class . '" data-notification-id="' . $notification['id'] . '">';
                                echo '    <div class="notification-content">';
                                echo '        <div class="notification-icon">';
                                echo '            <i class="fas fa-key"></i>';
                                echo '        </div>';
                                echo '        <div>';
                                echo '            <div class="notification-text">' . htmlspecialchars_decode($notification['message']) . '</div>';
                                echo '            <div class="notification-time">' . $time_ago . '</div>';
                                echo '        </div>';
                                echo '    </div>';
                                echo '</div>';
                                echo '</a>';
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
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="profile.php" onclick="setProfileThemeWithCurrent()"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                <!-- Backup Database Option for Administrators -->
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#" onclick="createDatabaseBackup()">
                        <i class="fas fa-database"></i> Backup Database
                    </a></li>

                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#" onclick="logoutUser()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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

<link rel="stylesheet" href="../css/mainheader.css">

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
                data: {
                    emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>
                },
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
                    data: {
                        emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>
                    },
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
                data: {
                    emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>
                },
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
                    data: {
                        emp_id: <?= $_SESSION['emp_id'] ?? 0 ?>
                    },
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
                    data: {
                        id: notificationId
                    },
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
                data: {
                    id: notificationId
                },
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
        $('#allNotificationsModal').on('show.bs.modal', function() {
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



        // Handle notification clicks - mark as read and allow navigation
        $(document).on('click', '.notification-item', function(e) {
            // Prevent default if clicking on a link inside the notification
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const notificationId = $(this).data('notification-id');
            const $notificationLink = $(this).closest('.notification-link');
            const href = $notificationLink.attr('href');

            console.log('Notification clicked:', notificationId, 'Link:', href);

            if (notificationId && href && href !== '#') {
                // Mark as read via AJAX
                $.ajax({
                    url: baseUrl + 'mark_notification_read.php',
                    type: 'POST',
                    data: {
                        id: notificationId
                    },
                    success: function(response) {
                        console.log('Mark as read response:', response);
                        if (response.success) {
                            $(this).removeClass('unread');
                            updateNotificationCount();

                            // Navigate to the link after marking as read
                            console.log('Navigating to:', href);
                            window.location.href = href;
                        } else {
                            // Still navigate even if marking as read fails
                            console.log('Mark as read failed, still navigating to:', href);
                            window.location.href = href;
                        }
                    }.bind(this),
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        // Still navigate even if AJAX fails
                        console.log('AJAX failed, navigating to:', href);
                        window.location.href = href;
                    }
                });
            } else if (href && href !== '#') {
                // If no notification ID but there's a valid href, just navigate
                console.log('No notification ID, navigating to:', href);
                window.location.href = href;
            }
        });

        // Make sure links within notifications work properly
        $(document).on('click', '.notification-text a', function(e) {
            console.log('Link in notification clicked');
            e.stopPropagation();
            // Allow default link behavior
            return true;
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
            },
            'ict': {
                header: 'linear-gradient(135deg, #17a2b8, #138496)',
                footer: 'linear-gradient(135deg, #17a2b8, #138496)',
                class: 'theme-ict'
            },
            'ia': {
                header: 'linear-gradient(135deg, #9C27B0, #7B1FA2)',
                footer: 'linear-gradient(135deg, #9C27B0, #7B1FA2)',
                class: 'theme-ia'
            },
            'document': {
                header: 'linear-gradient(135deg, #556b2f, #2b2b2b)',
                footer: 'linear-gradient(135deg, #556b2f, #2b2b2b)',
                class: 'theme-document'
            },
            'scrum': {
                header: 'linear-gradient(135deg, #8B5CF6, #7C3AED)',
                footer: 'linear-gradient(135deg, #8B5CF6, #7C3AED)',
                class: 'theme-scrum'
            },
            'queue': {
                header: 'linear-gradient(135deg, #2c3e50, #34495e)',
                footer: 'linear-gradient(135deg, #2c3e50, #34495e)',
                class: 'theme-queue'
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
                header.removeClass('theme-admin theme-service theme-inventory theme-file theme-ia');
                header.addClass(theme.class);
                console.log('Header updated');
            }

            // Update footer
            const footer = $('#mainFooter');
            if (footer.length) {
                footer.css('background', theme.footer);
                footer.removeClass('theme-admin theme-service theme-inventory theme-file theme-ia');
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

        function setThemeFromPage() {
            const currentPage = window.location.pathname;
            console.log('Current page:', currentPage);
            let theme = 'admin'; // default

            // Comprehensive theme detection
            if (currentPage.includes('ict_') || currentPage.includes('ict_inventory') || currentPage.includes('ict_equipment') || currentPage.includes('ict_my_equipment')) {
                theme = 'ict';
            } else if (currentPage.includes('service')) {
                theme = 'service';
            } else if (currentPage.includes('inventory') && !currentPage.includes('ict_')) {
                theme = 'inventory';
            } else if (currentPage.includes('file_management')) {
                theme = 'file';
            } else if (currentPage.includes('ia_profile') || currentPage.includes('ia_profiles')) {
                theme = 'ia';
            } else if (currentPage.includes('document_') || currentPage.includes('documents_')) {
                theme = 'document';
            } else if (currentPage.includes('scrum') || currentPage.includes('scrumboard')) {
                theme = 'scrum';
            } else if (currentPage.includes('dashboard')) {
                theme = 'admin';
            }

            console.log('Detected theme:', theme);
            setTheme(theme);
            return theme;
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
                'queue': 'linear-gradient(135deg, #2c3e50, #34495e)',
                'service': 'linear-gradient(135deg, #ffc107, #fd7e14)',
                'inventory': 'linear-gradient(135deg, #28a745, #20c997)',
                'file': 'linear-gradient(135deg, #800020, #5a0a1d)',
                'ict': 'linear-gradient(135deg, #17a2b8, #138496)',
                'ia': 'linear-gradient(135deg, #9C27B0, #7B1FA2)',
                'document': 'linear-gradient(135deg, #556b2f, #2b2b2b)',
                'scrum': 'linear-gradient(135deg, #8B5CF6, #7C3AED)'
            };

            if (themes[currentTheme]) {
                $('.main-header').css('background', themes[currentTheme]);
                $('#mainFooter').css('background', themes[currentTheme]);

                // Also update theme classes
                $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file theme-ict theme-document theme-scrum theme-ia')
                    .addClass('theme-' + currentTheme);
                $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file theme-ict theme-document theme-scrum theme-ia')
                    .addClass('theme-' + currentTheme);

                console.log('Theme applied:', currentTheme);
            }
        }, 200);
    });
    // Set module cookie based on current theme when profile is accessed from header
    function setModuleCookie() {
        const currentTheme = localStorage.getItem('currentTheme') || 'admin';
        document.cookie = `current_module=${currentTheme}; path=/; max-age=300`; // 5 minutes
        console.log('Module cookie set:', currentTheme);
    }

    // Update profile link to set module cookie
    $(document).ready(function() {
        $('a[href="profile.php"]').on('click', function(e) {
            setModuleCookie();
        });
    });

    // Function to set profile theme with current module
    function setProfileThemeWithCurrent() {
        const currentTheme = localStorage.getItem('currentTheme') || 'admin';
        document.cookie = `current_module=${currentTheme}; path=/; max-age=300`;
        console.log('Profile theme set to:', currentTheme);
    }
    // Enhanced logout function with SweetAlert - No redundant loading
    function logoutUser() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out of the system!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: {
                popup: 'custom-swal-popup',
                title: 'custom-swal-title',
                content: 'custom-swal-content',
                confirmButton: 'custom-swal-confirm',
                cancelButton: 'custom-swal-cancel'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Clear any theme/local storage
                localStorage.removeItem('currentTheme');
                localStorage.removeItem('sidebar-collapsed');

                // Redirect directly to logout page which has its own beautiful loader
                window.location.href = '../logout.php';
            }
        });
    }
    // Enhanced database backup function
    function createDatabaseBackup() {

        Swal.fire({
            title: 'Create Database Backup',
            text: "This will create a complete backup of the database. This may take a few moments.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Create Backup',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('backup_database.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Unknown error occurred');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Backup failed: ${error.message}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const backupData = result.value;
                Swal.fire({
                    title: 'Backup Successful!',
                    html: `
                    <div class="text-left">
                        <p><strong>File:</strong> ${backupData.filename}</p>
                        <p><strong>Size:</strong> ${backupData.filesize}</p>
                        <p><strong>Time:</strong> ${backupData.timestamp}</p>
                        <p><strong>Location:</strong> ${backupData.filepath}</p>
                    </div>
                `,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    showCancelButton: true,
                    cancelButtonText: 'Download',
                    didOpen: () => {
                        // Add download functionality to cancel button
                        const cancelButton = Swal.getCancelButton();
                        cancelButton.addEventListener('click', function() {
                            downloadBackup(backupData.filename);
                        });
                    }
                });
            }
        });
    }

    // Function to download backup file
    function downloadBackup(filename) {
        const downloadUrl = '../database_backups/' + filename;

        // Create temporary link for download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = filename;
        link.style.display = 'none';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Show success message
        Swal.fire({
            title: 'Download Started',
            text: 'Backup file download has started.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }
    // Function to download backup file
    function downloadBackup(filename) {
        const downloadUrl = '../database_backups/' + filename;
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Function to view backup history (optional)
    function viewBackupHistory() {
        Swal.fire({
            title: 'Backup History',
            html: '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading backup history...</div>',
            showConfirmButton: false,
            allowOutsideClick: false
        });

        // You can implement AJAX call to fetch backup history here
        // This would require creating a separate PHP file to fetch backup logs
    }
    // Enhanced backup function with progress tracking
    function createDatabaseBackupWithProgress() {
        let timerInterval;
        Swal.fire({
            title: 'Creating Database Backup',
            html: 'Please wait while we backup your database...<br><div class="progress mt-3"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div></div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                const progressBar = Swal.getHtmlContainer().querySelector('.progress-bar');
                let progress = 0;

                timerInterval = setInterval(() => {
                    progress += Math.random() * 10;
                    if (progress > 90) progress = 90;
                    progressBar.style.width = progress + '%';
                }, 500);

                // Start actual backup
                fetch('../views/backup_database.php')
                    .then(response => response.json())
                    .then(data => {
                        clearInterval(timerInterval);
                        progressBar.style.width = '100%';

                        setTimeout(() => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Backup Complete!',
                                    html: `
                                    <div class="text-left">
                                        <p><i class="fas fa-check-circle text-success"></i> Backup created successfully</p>
                                        <p><strong>File:</strong> ${data.filename}</p>
                                        <p><strong>Size:</strong> ${data.filesize}</p>
                                        <p><strong>Time:</strong> ${data.timestamp}</p>
                                    </div>
                                `,
                                    icon: 'success',
                                    confirmButtonText: 'OK',
                                    showCancelButton: true,
                                    cancelButtonText: 'Download',
                                    preConfirm: () => {
                                        downloadBackup(data.filename);
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: 'Backup Failed',
                                    text: data.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        }, 1000);
                    })
                    .catch(error => {
                        clearInterval(timerInterval);
                        Swal.fire({
                            title: 'Backup Failed',
                            text: 'An error occurred while creating the backup.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }
        });
    }
</script>