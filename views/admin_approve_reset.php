<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Only Administrators (and Focal users, if that's a role in user_roles) may
// approve/reject password resets. Adjust the role name(s) below to match
// what's actually in your `user_roles` table.
if (!hasRole('Administrator') && !hasRole('Focal')) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header("Location: ../unauthorized.php");
    exit();
}

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($action === 'approve') {
        // Get the reset request details
        $query = "SELECT prr.*, e.first_name, e.last_name, e.email, e.emp_id, e.id_number 
                  FROM password_reset_requests prr 
                  JOIN employee e ON prr.emp_id = e.emp_id 
                  WHERE prr.id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if ($request) {
            // Generate employee number as username and temporary password
            $employee_number = $request['id_number']; // Use existing ID number
            $temporary_password = password_hash($employee_number, PASSWORD_DEFAULT);
            
            // Check if user account exists
            $user_query = "SELECT id FROM users WHERE employee_id = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bind_param("i", $request['emp_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows === 1) {
                // Update existing user with employee number as username and password
                $update_user = "UPDATE users SET user = ?, password = ? WHERE employee_id = ?";
                $update_stmt = $db->prepare($update_user);
                $update_stmt->bind_param("ssi", $employee_number, $temporary_password, $request['emp_id']);
                $update_stmt->execute();
            } else {
                // Create new user with employee number as credentials
                $default_role_id = 3; // Regular User role - adjust as needed
                $insert_user = "INSERT INTO users (user, password, role_id, employee_id) VALUES (?, ?, ?, ?)";
                $insert_stmt = $db->prepare($insert_user);
                $insert_stmt->bind_param("ssii", $employee_number, $temporary_password, $default_role_id, $request['emp_id']);
                $insert_stmt->execute();
            }
            
            // Send notification to the requester with new login credentials
            $notification_message = "Your password reset has been approved. You can now login using your ID Number: <strong>{$employee_number}</strong> as both your username and temporary password. Please change your password after logging in for security.";
            $notification_link    = null;
            $notification_type    = "password_reset_approved";
            
            // Insert notification for the requester
            $insert_query = "INSERT INTO admin_notifications (admin_emp_id, message, link, type, is_read, created_at) 
                             VALUES (?, ?, ?, ?, 0, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bind_param("isss", $request['emp_id'], $notification_message, $notification_link, $notification_type);
            $insert_stmt->execute();
            
            // Update the reset request status
            $update_query = "UPDATE password_reset_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bind_param("ii", $_SESSION['emp_id'], $request_id);
            $update_stmt->execute();
            
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Password reset approved. User can now login with ID Number as both username and password.'
            ];
        }
    } elseif ($action === 'reject') {
        // Get the employee ID for notification
        $query = "SELECT emp_id FROM password_reset_requests WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if ($request) {
            // Create rejection notification for the requester
            $notification_message = "Your password reset request has been rejected. Please contact administrators for assistance.";
            $notification_link    = null;
            $notification_type    = "password_reset_rejected";
            
            $insert_query = "INSERT INTO admin_notifications (admin_emp_id, message, link, type, is_read, created_at) 
                             VALUES (?, ?, ?, ?, 0, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bind_param("isss", $request['emp_id'], $notification_message, $notification_link, $notification_type);
            $insert_stmt->execute();
        }
        
        $update_query = "UPDATE password_reset_requests SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("ii", $_SESSION['emp_id'], $request_id);
        $update_stmt->execute();
        
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Password reset request rejected. Notification sent to the requester.'
        ];
    }
    
    header("Location: admin_approve_reset.php");
    exit();
}

// Get pending reset requests
$database = new Database();
$db = $database->getConnection();
$query = "SELECT prr.*, e.first_name, e.last_name, e.email 
          FROM password_reset_requests prr 
          JOIN employee e ON prr.emp_id = e.emp_id 
          WHERE prr.status = 'pending' 
          ORDER BY prr.created_at DESC";
$result = $db->query($query);
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Password Resets</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Approve Password Reset Requests</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="alert alert-info">No pending password reset requests.</div>
                        <?php else: ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Email</th>
                                        <th>Requested At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></td>
                                            <td><?= htmlspecialchars($request['email']) ?></td>
                                            <td><?= $request['created_at'] ?></td>
                                            <td>
                                                <form method="post" style="display: inline-block;">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" name="approve_request" class="btn btn-success btn-sm">Approve</button>
                                                </form>
                                                <form method="post" style="display: inline-block;">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" name="approve_request" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>