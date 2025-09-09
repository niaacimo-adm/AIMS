<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header('Location: login.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['mark_all_read'])) {
        // Mark all notifications as read
        $query = "UPDATE admin_notifications SET is_read = 1 WHERE admin_emp_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $_SESSION['emp_id']);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'All notifications marked as read';
    } 
    elseif (isset($_POST['delete_all'])) {
        // Delete all notifications
        $query = "DELETE FROM admin_notifications WHERE admin_emp_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $_SESSION['emp_id']);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'All notifications deleted';
    }
    elseif (isset($_POST['delete_selected'])) {
        // Delete selected notifications
        if (!empty($_POST['notification_ids'])) {
            $placeholders = implode(',', array_fill(0, count($_POST['notification_ids']), '?'));
            $query = "DELETE FROM admin_notifications WHERE id IN ($placeholders) AND admin_emp_id = ?";
            
            $stmt = $db->prepare($query);
            $types = str_repeat('i', count($_POST['notification_ids'])) . 'i';
            $params = array_merge($_POST['notification_ids'], [$_SESSION['emp_id']]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            $_SESSION['success_message'] = 'Selected notifications deleted';
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: admin_notifications.php');
    exit();
}

// Get all notifications
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM admin_notifications WHERE admin_emp_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $_SESSION['emp_id']);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>
    <!-- Bootstrap CSS -->
    <?php include '../includes/header.php'; ?>
    <style>
        .notification-item.unread {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        .notification-item:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <?php include '../includes/mainheader.php'; ?>
    
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Notifications</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> Success!</h5>
                        <?= $_SESSION['success_message'] ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Notifications</h3>
                        <div class="card-tools">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary mr-2">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                                <button type="submit" name="delete_all" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete all notifications?')">
                                    <i class="fas fa-trash"></i> Delete All
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form method="POST" id="notificationsForm">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll">
                                            </th>
                                            <th>Message</th>
                                            <th width="150">Status</th>
                                            <th width="150">Date</th>
                                            <th width="100">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($notifications) > 0): ?>
                                            <?php foreach ($notifications as $notification): ?>
                                                <tr class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                                    <td>
                                                        <input type="checkbox" name="notification_ids[]" value="<?= $notification['id'] ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars($notification['message']) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= $notification['is_read'] ? 'success' : 'warning' ?>">
                                                            <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= time_elapsed_string($notification['created_at']) ?></td>
                                                    <td>
                                                        <a href="view_notification.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="delete_notification.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No notifications found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($notifications) > 0): ?>
                                <div class="card-footer">
                                    <button type="submit" name="delete_selected" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete selected notifications?')">
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    $(document).ready(function() {
        // Select all checkboxes
        $('#selectAll').change(function() {
            $('input[name="notification_ids[]"]').prop('checked', this.checked);
        });
        
        // Individual checkbox change
        $('input[name="notification_ids[]"]').change(function() {
            if (!this.checked) {
                $('#selectAll').prop('checked', false);
            } else {
                if ($('input[name="notification_ids[]"]:checked').length === $('input[name="notification_ids[]"]').length) {
                    $('#selectAll').prop('checked', true);
                }
            }
        });
    });
    </script>
</body>
</html>