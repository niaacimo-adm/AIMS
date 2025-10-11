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
                    // Notify administrators about password reset request
                    $notified = notifyAdministratorsAboutPasswordReset($user, $id_number, $reset_token, $expiry);
                    
                    if ($notified) {
                        $success = "Your password reset request has been sent to administrators for approval. You will receive a notification on your account with reset instructions once approved.";
                    } else {
                        $success = "Password reset request submitted. Please contact administrators for further instructions.";
                    }
                    
                    // For demo purposes, store the token in session (remove in production)
                    $_SESSION['demo_reset_token'] = $reset_token;
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
function notifyAdministratorsAboutPasswordReset($user, $id_number, $reset_token, $expiry) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Store the reset request
    $insert_query = "INSERT INTO password_reset_requests (emp_id, reset_token, token_expiry) VALUES (?, ?, ?)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bind_param("iss", $user['emp_id'], $reset_token, $expiry);
    $insert_stmt->execute();
    $request_id = $db->insert_id;

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
            // In the notifyAdministratorsAboutPasswordReset function
            // Use button-style link
$notification_message = "Password reset requested for {$user['first_name']} {$user['last_name']} (ID: {$id_number}). <button onclick=\"window.location.href='admin_approve_reset.php'\" style='color: #007bff; background: none; border: none; text-decoration: underline; cursor: pointer; padding: 0;'>Click to review</button>";
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
    
    // FIX: Remove the duplicate return statement and return the request_id
    return $request_id;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NIA Albay-Catanduanes IMO</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <!-- IonIcons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            font-family: 'Source Sans Pro', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px 0;
        }
        
        .forgot-password-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .forgot-password-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: none;
        }
        
        .forgot-password-header {
            background-color: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
            border-bottom: 4px solid #28a745;
        }
        
        .forgot-password-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .forgot-password-logo img {
            height: 50px;
            margin-right: 15px;
        }
        
        .forgot-password-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .back-link {
            color: #28a745;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: #218838;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 8px;
        }
        
        .forgot-password-title {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .forgot-password-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .footer-logo {
            margin-bottom: 10px;
        }
        
        .footer-logo img {
            height: 40px;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="card forgot-password-card">
            <div class="forgot-password-header">
                <div class="forgot-password-logo">
                    <img src="../dist/img/nialogo.png" alt="NIA Logo">
                    <h4 class="mb-0">NIA Albay-Catanduanes IMO</h4>
                </div>
                <h3 class="forgot-password-title">Password Reset</h3>
                <p class="forgot-password-subtitle">Enter your ID number to request a password reset</p>
            </div>
            
            <div class="card-body forgot-password-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> Error</h5>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> Success</h5>
                        <?= $success ?>
                    </div>
                    <!-- NEW: Show notification about admin notification -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Administrators have been notified about this password reset request.
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="input-group mb-4">
                        <input type="text" class="form-control" placeholder="ID Number" name="id_number" value="<?= htmlspecialchars($id_number) ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-id-card"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-key mr-2"></i>Request Password Reset
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="../views/profile.php" class="back-link">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Profile
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-logo">
                <img src="../dist/img/nialogo.png" alt="NIA Logo">
            </div>
            <p class="mb-1">&copy; <?= date('Y') ?> National Irrigation Administration - Albay Catanduanes IMO</p>
            <p class="mb-0">Providing efficient irrigation services for sustainable agriculture</p>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="../dist/js/adminlte.js"></script>
    <!-- SweetAlert2 -->
    <script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
</body>
</html>