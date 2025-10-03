<?php
    require_once '../config/database.php';
    require_once 'helpers.php';
?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light ">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-th"></i> Apps
        </a>
        <div class="dropdown-menu dropdown-menu-lg">
          <div class="dropdown-header">Application Pages</div>
          <div class="dropdown-divider"></div>
          <div class="d-flex flex-wrap" style="width: 300px;">
            <a href="dashboard.php" class="dropdown-item text-center p-3">
              <i class="fas fa-tachometer-alt fa-2x mb-2 text-primary"></i>
              <br>
              <span class="d-block">Admin Section</span>
            </a>
            <a href="service.php" class="dropdown-item text-center p-3">
              <i class="fas fa-car fa-2x mb-2 text-success"></i>
              <br>
              <span class="d-block">Reserve Service</span>
            </a>
            <a href="inventory.php" class="dropdown-item text-center p-3">
              <i class="fas fa-boxes fa-2x mb-2 text-warning"></i>
              <br>
              <span class="d-block">Procurement Inventory</span>
            </a>
            <a href="file_management.php" class="dropdown-item text-center p-3">
              <i class="fas fa-folder fa-2x mb-2 text-info"></i>
              <br>
              <span class="d-block">File Management</span>
            </a>
          </div>
        </div>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" id="notificationDropdown">
                <i class="far fa-bell"></i>
                <?php
                // Get unread notification count for current admin
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
                            echo '<span class="badge badge-warning navbar-badge" id="notificationCount">' . $count . '</span>';
                        }
                    }
                ?>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="notificationMenu" style="max-height: 400px; overflow-y: auto; width: 350px;">
                <span class="dropdown-item dropdown-header" id="notificationHeader">
                    <?= isset($count) && $count > 0 ? $count . ' Notifications' : 'No Notifications' ?>
                </span>
                <div class="dropdown-divider"></div>
                <div id="notificationList">
                     <?php
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
                                  $read_class = $row['is_read'] ? '' : 'font-weight-bold';
                                  
                                  echo '<div class="dropdown-item ' . $read_class . '" data-notification-id="' . $row['id'] . '">
                                        <div class="d-flex">
                                            <div class="mr-2" style="min-width: 20px;">
                                                <i class="fas fa-key text-muted"></i>
                                            </div>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="notification-text mb-1">' . htmlspecialchars_decode($row['message']) . '</div>
                                                <small class="text-muted">' . $time_ago . '</small>
                                            </div>
                                        </div>
                                      </div>
                                      <div class="dropdown-divider my-1"></div>';
                              }
                          } else {
                              echo '<span class="dropdown-item dropdown-header">No notifications</span>';
                          }
                      }
                    ?>
                </div>
                <div class="dropdown-divider"></div>
                <div class="d-flex justify-content-around p-2">
                    <button class="btn btn-sm btn-outline-primary mark-all-read-btn">Mark All Read</button>
                    <button class="btn btn-sm btn-outline-danger delete-all-btn">Delete All</button>
                </div>
                <a href="#" class="dropdown-item dropdown-footer text-center" data-toggle="modal" data-target="#allNotificationsModal">See All Notifications</a>
            </div>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-widget="fullscreen" href="#" role="button">
            <i class="fas fa-expand-arrows-alt"></i>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
            <i class="fas fa-th-large"></i>
          </a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="admin_approve_reset.php" class="nav-link"><i class="fas fa-lock"></i></a>
        </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- All Notifications Modal -->
  <div class="modal fade" id="allNotificationsModal" tabindex="-1" role="dialog" aria-labelledby="allNotificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="allNotificationsModalLabel">All Notifications</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <?php
          // Get all notifications for the modal
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
                    <td colspan="4" class="text-center py-4">No notifications found</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php } ?>
        </div>
        <div class="modal-footer">
          <form method="POST" id="modalActionsForm">
            <button type="button" class="btn btn-outline-primary" id="modalMarkAllRead">
              <i class="fas fa-check-double"></i> Mark All as Read
            </button>
            <button type="button" class="btn btn-outline-danger" id="modalDeleteAll">
              <i class="fas fa-trash"></i> Delete All
            </button>
          </form>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

<style>
  /* Custom styles for notification dropdown */
    #notificationMenu {
        max-height: 400px;
        overflow-y: auto;
        width: 350px;
        padding: 0;
    }

    .notification-text {
        word-wrap: break-word;
        white-space: normal;
        line-height: 1.3 !important;
        margin-bottom: 2px;
        font-size: 0.9rem;
    }

    .dropdown-item {
        padding: 8px 12px !important;
        line-height: 1.3 !important;
        border: none !important;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa !important;
    }

    /* Ensure proper z-index to prevent overlapping */
    .navbar-nav > .nav-item > .dropdown-menu {
        z-index: 1030;
    }

    .mark-all-read-btn, .delete-all-btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .notification-item.unread {
        background-color: #f8f9fa;
        font-weight: 500;
    }

    .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }

    .notification-text a {
        color: #007bff !important;
        text-decoration: underline !important;
        display: inline !important;
        font-weight: 500;
        pointer-events: auto !important; /* Ensure links are clickable */
        z-index: 1000; /* Ensure links are above other elements */
        position: relative; /* Ensure proper stacking context */
    }

    .notification-text a:hover {
        color: #0056b3 !important;
        text-decoration: none !important;
        cursor: pointer !important;
    }
    .flex-grow-1 {
        flex: 1;
        min-width: 0; /* Prevent flex item from overflowing */
    }

    .d-flex {
        align-items: flex-start !important;
    }

    .d-flex .fas {
        margin-top: 2px;
        font-size: 0.8rem;
    }

    .dropdown-divider {
        margin: 3px 0 !important;
    }

    .dropdown-header {
        padding: 8px 12px;
        font-weight: 600;
    }

    /* Remove extra spacing in notification container */
    #notificationList {
        padding: 0;
    }

    /* Fix text overflow */
    .notification-text {
        overflow-wrap: break-word;
        word-break: break-word;
    }

    /* Compact time display */
    .text-muted {
        font-size: 0.75rem;
        margin-top: 2px;
        display: block;
    }

    /* Apps dropdown styles */
    .dropdown-menu-lg .d-flex {
        flex-wrap: wrap;
    }
    
    .dropdown-menu-lg .dropdown-item {
        width: 50%;
        border: none;
        text-align: center;
    }
    
    .dropdown-menu-lg .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .dropdown-menu-lg .dropdown-item span {
        font-size: 0.85rem;
        font-weight: 500;
    }
</style>

<script>
  $(document).ready(function() {
    // Get base URL for AJAX calls
    const baseUrl = window.location.origin + '/NIA-PROJECT/views/';
    console.log('Base URL:', baseUrl); // Debugging
    
    // Fix pushmenu functionality
    $('[data-widget="pushmenu"]').click(function(e) {
      e.preventDefault();
      $('body').toggleClass('sidebar-collapse');
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
            // Update UI
            $('.dropdown-item').removeClass('font-weight-bold');
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
              $('#notificationList').html('<span class="dropdown-item dropdown-header">No notifications</span>');
              $('#notificationCount').remove();
              $('#notificationHeader').text('No Notifications');
              $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4">No notifications found</td></tr>');
              
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
            $('.dropdown-item').removeClass('font-weight-bold');
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
              $('#notificationList').html('<span class="dropdown-item dropdown-header">No notifications</span>');
              $('#notificationCount').remove();
              $('#notificationHeader').text('No Notifications');
              $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4">No notifications found</td></tr>');
              
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
                $('#allNotificationsModal tbody').html('<tr><td colspan="4" class="text-center py-4">No notifications found</td></tr>');
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
            $('div[data-notification-id="' + notificationId + '"]').removeClass('font-weight-bold');
            
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
    
    // Function to update notification count
    function updateNotificationCount() {
        $.ajax({
          url: baseUrl + 'get_notification_count.php',
          type: 'GET',
          success: function(response) {
            if (response.count > 0) {
              if ($('#notificationCount').length) {
                $('#notificationCount').text(response.count);
              } else {
                $('#notificationDropdown').append('<span class="badge badge-warning navbar-badge" id="notificationCount">' + response.count + '</span>');
              }
              $('#notificationHeader').text(response.count + ' Notifications');
            } else {
              $('#notificationCount').remove();
              $('#notificationHeader').text('No Notifications');
            }
          }
        });
      }
      
      // Refresh modal content when opened
      $('#allNotificationsModal').on('show.bs.modal', function () {
        $.ajax({
          url: baseUrl + 'get_all_notifications.php',
          type: 'GET',
          success: function(response) {
            $('#allNotificationsModal tbody').html(response);
          }
        });
      });
  });

  // Handle clicks on links within notifications - FIXED
  $(document).on('click', '.notification-text a', function(e) {
      console.log('Link clicked:', $(this).attr('href'));
      e.stopPropagation();
      
      // Allow default link behavior (navigation)
      // Remove e.preventDefault() to allow the link to work
      const href = $(this).attr('href');
      
      if (href && href !== '#') {
          console.log('Allowing navigation to:', href);
          // The browser will handle the navigation naturally
          return true;
      }
  });

  // Update the notification click handler - FIXED
  $(document).on('click', '.dropdown-item[data-notification-id]', function(e) {
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
                      $(this).removeClass('font-weight-bold');
                      updateNotificationCount();
                  }
              }.bind(this)
          });
      }
  });
</script>