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
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link">Home</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="service.php" class="nav-link">Reserve Service</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="inventory.php" class="nav-link">Procurement Inventory</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="file_management.php" class="nav-link">File Management</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="fas fa-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>

      
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
                                echo '<a href="#" class="dropdown-item ' . $read_class . '" data-notification-id="' . $row['id'] . '" style="white-space: normal; line-height: 1.4;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-key mr-2"></i>
                                            <div class="flex-grow-1">
                                                <div class="notification-text">' . $row['message'] . '</div>
                                                <small class="text-muted">' . $time_ago . '</small>
                                            </div>
                                        </div>
                                      </a>
                                      <div class="dropdown-divider"></div>';
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
                      <td><?= htmlspecialchars($notification['message']) ?></td>
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
  }
  
  .notification-text {
    word-wrap: break-word;
    white-space: normal;
  }
  
  .dropdown-item {
    padding: 0.5rem 1rem;
  }
  
  /* Ensure proper z-index to prevent overlapping */
  .navbar-nav > .nav-item > .dropdown-menu {
    z-index: 1030; /* Higher than default to prevent overlapping */
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
  </style>

  <script>
  $(document).ready(function() {
    // Get base URL for AJAX calls
    const baseUrl = window.location.origin + '/NIA-PROJECT/views/';
    
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
            $('a[data-notification-id="' + notificationId + '"]').removeClass('font-weight-bold');
            
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
  </script>