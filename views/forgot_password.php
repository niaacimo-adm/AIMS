<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$id_number = '';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    
    // Validate ID number
    if (empty($id_number)) {
        $error = 'Please enter your ID number.';
    } else {
        // Check if ID number exists in database
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT emp_id, first_name, last_name FROM employee WHERE id_number = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate a unique reset token
            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            try {
                // Check if reset_token column exists
                $check_column = $db->query("SHOW COLUMNS FROM employee LIKE 'reset_token'");
                if ($check_column->num_rows === 0) {
                    // Column doesn't exist, create it
                    $db->query("ALTER TABLE employee ADD COLUMN reset_token VARCHAR(64) NULL");
                    $db->query("ALTER TABLE employee ADD COLUMN reset_token_expiry DATETIME NULL");
                }
                
                // Store the token in the database
                $update_query = "UPDATE employee SET reset_token = ?, reset_token_expiry = ? WHERE emp_id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bind_param("ssi", $reset_token, $expiry, $user['emp_id']);
                
                if ($update_stmt->execute()) {
                    // Send email with reset link (in a real application)
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/NIA-PROJECT/views/reset_password.php?token=" . $reset_token;
                    
                    // In a real application, you would send an email here
                    // For now, we'll just show the link (remove this in production)
                    $success = "Password reset link has been generated. In a real application, this would be emailed to you.<br>";
                    $success .= "Reset link: <a href='$reset_link'>$reset_link</a>";
                    
                    // For demo purposes, store the token in session
                    $_SESSION['demo_reset_token'] = $reset_token;
                    
                    // NEW: Notify administrators about password reset request
                    notifyAdministratorsAboutPasswordReset($user, $id_number);
                } else {
                    $error = 'Error generating reset token. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'No account found with that ID number.';
        }
    }
}

// NEW: Function to notify administrators about password reset request
function notifyAdministratorsAboutPasswordReset($user, $id_number) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all administrators
    $query = "SELECT e.emp_id, e.email, e.first_name, e.last_name 
              FROM employee e 
              JOIN users u ON e.emp_id = u.employee_id 
              JOIN user_roles r ON u.role_id = r.id 
              WHERE r.name = 'Administrator' 
              AND e.email IS NOT NULL";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    
    // Check if admin_notifications table exists, create it if not
    try {
        $check_table = $db->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($check_table->num_rows === 0) {
            $create_table = "CREATE TABLE admin_notifications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                admin_emp_id INT(11) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (admin_emp_id) REFERENCES employee(emp_id) ON DELETE CASCADE
            )";
            $db->query($create_table);
        }
        
        // Store notification for each admin in database
        foreach ($admins as $admin) {
            $notification_message = "Password reset requested for {$user['first_name']} {$user['last_name']} (ID: {$id_number})";
            $notification_type = "password_reset_request";
            $is_read = 0;
            
            $insert_query = "INSERT INTO admin_notifications (admin_emp_id, message, type, is_read, created_at) 
                             VALUES (?, ?, ?, ?, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bind_param("issi", $admin['emp_id'], $notification_message, $notification_type, $is_read);
            $insert_stmt->execute();
        }
    } catch (Exception $e) {
        // If there's an error with the notifications table, just continue
        error_log("Notification error: " . $e->getMessage());
    }
    
    return count($admins) > 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .login-box {
            margin-top: 50px;
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="index.php"><b>Admin</b>LTE</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Forgot your password? Enter your ID number to reset it.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <!-- NEW: Show notification about admin notification -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Administrators have been notified about this password reset request.
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="ID Number" name="id_number" value="<?= htmlspecialchars($id_number) ?>" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-id-card"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Request Password Reset</button>
                    </div>
                </div>
            </form>
            
            <p class="mt-3 mb-1">
                <a href="../views/profile.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>