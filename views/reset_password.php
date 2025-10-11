<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error = '';
$success = '';
$token_valid = false;
$token = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if token is provided in URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token - SIMPLIFIED QUERY FOR DEBUGGING
    $database = new Database();
    $db = $database->getConnection();
    
    // First, let's check if the token exists at all
    $check_query = "SELECT * FROM password_reset_requests WHERE reset_token = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("s", $token);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        $token_data = $check_result->fetch_assoc();
        
        // Now check all conditions
        if ($token_data['status'] !== 'approved') {
            $error = "Token status is '" . $token_data['status'] . "' but needs to be 'approved'.";
        } elseif (strtotime($token_data['token_expiry']) < time()) {
            $error = "Token expired on " . $token_data['token_expiry'];
        } else {
            // Token is valid, get user info
            $user_query = "SELECT e.first_name, e.last_name, e.email, u.id as user_id
                          FROM employee e 
                          JOIN users u ON e.emp_id = u.employee_id
                          WHERE e.emp_id = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bind_param("i", $token_data['emp_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows === 1) {
                $user_data = $user_result->fetch_assoc();
                $token_valid = true;
                $reset_request = array_merge($token_data, $user_data);
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $new_password = trim($_POST['new_password']);
                    $confirm_password = trim($_POST['confirm_password']);
                    
                    // Validate passwords
                    if (empty($new_password) || empty($confirm_password)) {
                        $error = 'Please fill in all password fields.';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'Passwords do not match.';
                    } elseif (strlen($new_password) < 8) {
                        $error = 'Password must be at least 8 characters long.';
                    } else {
                        // Update password in users table
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $update_query = "UPDATE users SET password = ? WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bind_param("si", $hashed_password, $reset_request['user_id']);
                        
                        if ($update_stmt->execute()) {
                            // Mark reset request as used
                            $update_status_query = "UPDATE password_reset_requests SET status = 'used', used_at = NOW() WHERE id = ?";
                            $update_status_stmt = $db->prepare($update_status_query);
                            $update_status_stmt->bind_param("i", $reset_request['id']);
                            $update_status_stmt->execute();
                            
                            $success = "Password has been reset successfully! You can now <a href='../login.php' class='alert-link'>login</a> with your new password.";
                            $token_valid = false;
                        } else {
                            $error = 'Error updating password. Please try again.';
                        }
                    }
                }
            } else {
                $error = 'User account not found.';
            }
        }
    } else {
        $error = 'Invalid reset token. Please request a new password reset link.';
    }
} else {
    $error = 'No reset token provided. Please use the link from your notification.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NIA Albay-Catanduanes IMO</title>
    
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
        
        .reset-password-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .reset-password-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: none;
        }
        
        .reset-password-header {
            background-color: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
            border-bottom: 4px solid #28a745;
        }
        
        .reset-password-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .reset-password-logo img {
            height: 50px;
            margin-right: 15px;
        }
        
        .reset-password-body {
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
        
        .reset-password-title {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .reset-password-subtitle {
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
        
        .password-match {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .progress-bar {
            border-radius: 5px;
        }
        
        .password-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <div class="card reset-password-card">
            <div class="reset-password-header">
                <div class="reset-password-logo">
                    <img src="../dist/img/nialogo.png" alt="NIA Logo">
                    <h4 class="mb-0">NIA Albay-Catanduanes IMO</h4>
                </div>
                <h3 class="reset-password-title">Password Reset</h3>
                <p class="reset-password-subtitle">Enter your new password below</p>
            </div>
            
            <div class="card-body reset-password-body">
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
                <?php endif; ?>
                
                <?php if ($token_valid && empty($success)): ?>
                    <form method="post" id="resetPasswordForm">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password">
                                <div class="input-group-append">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="password-hint">
                                Use at least 8 characters with a mix of letters, numbers, and symbols
                            </div>
                            <div class="password-strength mt-2">
                                <div class="progress" style="height: 8px;">
                                    <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small id="passwordStrengthText" class="text-muted">Password strength: Very weak</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password">
                                <div class="input-group-append">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div id="passwordMatch" class="password-match mt-2"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block py-2">
                            <i class="fas fa-sync-alt mr-2"></i> Reset Password
                        </button>
                    </form>
                <?php elseif (empty($success) && empty($error)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Please wait while we validate your reset token...
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="../login.php" class="back-link">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Login
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
    
    <script>
        // Password strength calculation
        const newPasswordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Check password length
                if (password.length >= 8) {
                    strength += 25;
                }
                
                // Check for numbers
                if (/\d/.test(password)) {
                    strength += 25;
                }
                
                // Check for special characters
                if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                    strength += 25;
                }
                
                // Check for uppercase and lowercase letters
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
                    strength += 25;
                }
                
                // Update strength bar
                strengthBar.style.width = strength + '%';
                
                // Update strength text and color
                if (strength === 0) {
                    strengthText.textContent = 'Password strength: Very weak';
                    strengthBar.className = 'progress-bar bg-danger';
                } else if (strength <= 25) {
                    strengthText.textContent = 'Password strength: Weak';
                    strengthBar.className = 'progress-bar bg-danger';
                } else if (strength <= 50) {
                    strengthText.textContent = 'Password strength: Fair';
                    strengthBar.className = 'progress-bar bg-warning';
                } else if (strength <= 75) {
                    strengthText.textContent = 'Password strength: Good';
                    strengthBar.className = 'progress-bar bg-info';
                } else {
                    strengthText.textContent = 'Password strength: Strong';
                    strengthBar.className = 'progress-bar bg-success';
                }
            });
        }
        
        // Password matching validation
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                
                if (this.value && newPassword) {
                    if (this.value === newPassword) {
                        passwordMatch.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match!';
                    } else {
                        passwordMatch.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Passwords do not match!';
                    }
                } else {
                    passwordMatch.innerHTML = '';
                }
            });
        }
        
        // Form validation
        const resetForm = document.getElementById('resetPasswordForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please make sure both fields are identical.');
                    return;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }
                
                // Check password strength
                const strength = calculatePasswordStrength(newPassword);
                if (strength < 50) {
                    e.preventDefault();
                    alert('Your password is too weak. Please use a stronger password with a mix of letters, numbers, and special characters.');
                    return;
                }
            });
        }
        
        // Calculate password strength
        function calculatePasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/\d/.test(password)) strength += 25;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            
            return strength;
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentNode.parentNode.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>