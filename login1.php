<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $database = new Database();
        $db = $database->getConnection();

        // Get user with role name
        $stmt = $db->prepare("
            SELECT u.id, u.user, u.password, u.role_id, u.employee_id, ur.name as role_name 
            FROM users u
            JOIN user_roles ur ON u.role_id = ur.id
            WHERE u.user = ? AND u.employee_id IS NOT NULL
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['emp_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['user'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];

                // Cache permissions
                $stmt = $db->prepare("
                    SELECT p.name 
                    FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->bind_param("i", $user['role_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                $permissions = [];
                while ($row = $result->fetch_assoc()) {
                    $permissions[] = $row['name'];
                }

                $_SESSION['permissions'] = $permissions;

                // Redirect to dashboard
                header("Location: views/dashboard.php");
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NIA ACIMO - Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
    
    <style>
        :root {
            --nia-blue: #24e78fff;
            --nia-light-blue: #2a9863ff;
            --nia-gold: #d4af37;
            --nia-light-gold: #f4e4a6;
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            border: none;
            position: relative;
        }
        
        .login-left {
            background: linear-gradient(135deg, var(--nia-blue) 0%, var(--nia-light-blue) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 500px;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path fill="rgba(255,255,255,0.05)" d="M100,0 C155.228,0 200,44.772 200,100 C200,155.228 155.228,200 100,200 C44.772,200 0,155.228 0,100 C0,44.772 44.772,0 100,0 Z M100,30 C72.386,30 50,52.386 50,80 C50,107.614 72.386,130 100,130 C127.614,130 150,107.614 150,80 C150,52.386 127.614,30 100,30 Z"/></svg>');
            background-size: 200px 200px;
            background-position: center;
            opacity: 0.1;
        }
        
        .login-left img {
            max-width: 230px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        
        .login-left h2 {
            font-weight: 700;
            margin-bottom: 7px;
            font-size: 1.8rem;
            position: relative;
            z-index: 1;
        }
        
        .login-left h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .login-right {
            padding: 50px 40px;
            background-color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 500px;
        }
        
        .login-title {
            color: var(--nia-blue);
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            font-size: 1.8rem;
            position: relative;
        }
        
        .login-title::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(to right, var(--nia-blue), var(--nia-light-blue));
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }
        
        .input-container {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-control {
            border-radius: 5px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            height: auto;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: var(--nia-blue);
            box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.15);
            transform: translateY(-2px);
        }
        
        .input-group {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-radius: 5px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.1);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-left: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0 15px;
            min-width: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .input-group-text:hover {
            background-color: #e9ecef;
        }
        
        .password-toggle:hover {
            transform: scale(1.05);
        }
        
        .input-group .form-control:not(:last-child) {
            border-right: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--nia-blue) 0%, var(--nia-light-blue) 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 600;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-size: 1rem;
            margin-top: 10px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            display: block;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn-login:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        .alert {
            border-radius: 5px;
            border: none;
            font-weight: 500;
            margin-bottom: 25px;
            padding: 12px 15px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        .footer-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 30px;
            text-align: center;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .login-form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Loader Styles */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(30, 60, 114, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .loader-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .nia-loader {
            width: 200px;
            height: 200px;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .nia-loader-logo {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            overflow: hidden;
        }
        
        .nia-loader-logo img {
            width: 100px;
            height: auto;
            object-fit: contain;
        }
        
        .nia-loader-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top: 3px solid var(--nia-gold);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
        }
        
        .nia-loader-ring:nth-child(2) {
            width: 120%;
            height: 120%;
            border-top: 3px solid var(--nia-light-blue);
            animation: spin 2s linear infinite;
        }
        
        .nia-loader-ring:nth-child(3) {
            width: 140%;
            height: 140%;
            border-top: 3px solid white;
            animation: spin 2.5s linear infinite;
        }
        
        .nia-loader-text {
            color: white;
            margin-top: 20px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
        }
        
        .progress-bar {
            width: 200px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            width: 0%;
            background: var(--nia-gold);
            border-radius: 2px;
            transition: width 3s linear;
        }
        
        .loader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 40px 30px;
                min-height: 400px;
            }
            
            .login-right {
                padding: 40px 30px;
                min-height: 400px;
            }
            
            .login-left h2 {
                font-size: 1.4rem;
            }
            
            .login-left h3 {
                font-size: 1.2rem;
            }
            
            .login-title {
                font-size: 1.6rem;
                margin-bottom: 30px;
            }
            
            .input-container,
            .btn-login,
            .alert {
                max-width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 20px;
            }
            
            .login-container {
                border-radius: 8px;
            }
            
            .login-left, .login-right {
                padding: 30px 20px;
                min-height: auto;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .nia-loader {
                width: 150px;
                height: 150px;
            }
            
            .nia-loader-logo {
                width: 90px;
                height: 90px;
            }
            
            .nia-loader-logo img {
                width: 75px;
            }
        }
    </style>
</head>
<body>
    <!-- Loader Overlay -->
    <div class="loader-overlay" id="loaderOverlay">
        <div class="loader-content">
            <div class="nia-loader">
                <div class="nia-loader-ring"></div>
                <div class="nia-loader-ring"></div>
                <div class="nia-loader-ring"></div>
                <div class="nia-loader-logo">
                    <img src="dist/img/nialogo.png" alt="NIA Logo">
                </div>
            </div>
            <div class="nia-loader-text">Logging in...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
    </div>
    
    <div class="login-container">
        <div class="row no-gutters">
            <div class="col-md-6 login-left">
                <img src="dist/img/nialogo.png" alt="NIA Logo">
                <h2>National Irrigation Administration</h2>
                <h3>Albay-Catanduanes IMO</h3>
            </div>
            <div class="col-md-6 login-right">
                <div class="login-form-container">
                    <h2 class="login-title">User Login</h2>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post" id="loginForm">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <div class="input-container">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="username" name="username" 
                                               placeholder="Enter your username" value="<?= htmlspecialchars($username) ?>" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-container">
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter your password" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text password-toggle" id="passwordToggle">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login" id="loginButton">Login</button>
                    </form>
                    
                    <div class="footer-text">
                        <p class="mb-0">ACIMO Intelligent Management Solution (AIMS)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Toastr -->
    <script src="plugins/toastr/toastr.min.js"></script>

    <script>
        $(document).ready(function() {
            // Password visibility toggle
            $('#passwordToggle').click(function() {
                const passwordInput = $('#password');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Form validation and loader
            $('#loginForm').on('submit', function(e) {
                const username = $('#username').val().trim();
                const password = $('#password').val();
                
                if (!username || !password) {
                    return false;
                }
                
                // Show loader
                $('#loaderOverlay').addClass('active');
                $('#progressFill').css('width', '100%');
                
                // Prevent form from submitting immediately to show loader for 3 seconds
                e.preventDefault();
                setTimeout(() => {
                    // Submit the form after 3 seconds
                    this.submit();
                }, 3000);
            });
        });
    </script>
</body>
</html>