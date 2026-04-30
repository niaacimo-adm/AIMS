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
    <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback"> -->
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
            --nia-dark: #1a3c2e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            background: linear-gradient(135deg, #1a3c2e 0%, #0f241b 100%);
            overflow: hidden;
        }
        
        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            background: rgba(36, 231, 143, 0.1);
            border: 1px solid rgba(36, 231, 143, 0.3);
            border-radius: 10px;
            animation: float 15s infinite ease-in-out;
        }
        
        .shape:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 60%;
            left: 80%;
            animation-delay: -5s;
        }
        
        .shape:nth-child(3) {
            width: 80px;
            height: 80px;
            top: 80%;
            left: 20%;
            animation-delay: -10s;
        }
        
        .shape:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 20%;
            left: 70%;
            animation-delay: -7s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            position: relative;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1;
        }
        
        .login-left {
            background: linear-gradient(135deg, rgba(36, 231, 143, 0.7) 0%, rgba(42, 152, 99, 0.7) 100%);
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
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%);
            opacity: 0.3;
        }
        
        .logo-3d {
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.2),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1),
                inset 0 5px 15px rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }
        
        .logo-3d img {
            max-width: 140px;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
        }
        
        .login-left h2 {
            font-weight: 700;
            margin-bottom: 7px;
            font-size: 1.8rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .login-left h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            font-weight: 600;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .login-right {
            padding: 50px 40px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 500px;
            position: relative;
        }
        
        .login-right::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(36, 231, 143, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(42, 152, 99, 0.1) 0%, transparent 50%);
            z-index: 0;
        }
        
        .login-title {
            color: white;
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            font-size: 1.8rem;
            position: relative;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
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
            box-shadow: 0 2px 10px rgba(36, 231, 143, 0.5);
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }
        
        .input-container {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .input-group {
            box-shadow: 
                0 5px 15px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .input-group:focus-within {
            box-shadow: 
                0 10px 25px rgba(36, 231, 143, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border-color: var(--nia-blue);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 15px 20px;
            border: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            height: auto;
            width: 100%;
            background: transparent;
            color: #333;
        }
        
        .form-control::placeholder {
            color: rgba(0, 0, 0, 0.6);
        }
        
        .form-control:focus {
            outline: none;
            box-shadow: none;
            color: #333;
            background: transparent;
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0 20px;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .input-group-text:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--nia-blue) 0%, var(--nia-light-blue) 100%);
            border: none;
            color: white;
            padding: 15px;
            font-weight: 600;
            border-radius: 10px;
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
            box-shadow: 
                0 10px 20px rgba(36, 231, 143, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 30px rgba(36, 231, 143, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            font-weight: 500;
            margin-bottom: 25px;
            padding: 15px 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            background: rgba(220, 53, 69, 0.3);
            color: white;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        .footer-text {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-top: 30px;
            text-align: center;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .login-form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        /* Forgot Password Form Container */
        .forgot-form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
            display: none;
        }
        
        /* Forgot Password Link Styles */
        .forgot-password-container {
            text-align: center;
            margin-top: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .forgot-password-link {
            color: var(--nia-light-gold);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        
        .forgot-password-link:hover {
            color: var(--nia-gold);
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .back-to-login {
            color: var(--nia-light-gold);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            margin-top: 15px;
        }

        .back-to-login:hover {
            color: var(--nia-gold);
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: linear-gradient(135deg, var(--nia-gold) 0%, #b8941f 100%);
            border: none;
            color: white;
            padding: 15px;
            font-weight: 600;
            border-radius: 10px;
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
            box-shadow: 
                0 10px 20px rgba(212, 175, 55, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 30px rgba(212, 175, 55, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .forgot-title {
            color: white;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.6rem;
            position: relative;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .forgot-title::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(to right, var(--nia-gold), #b8941f);
            border-radius: 2px;
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.5);
        }

        .forgot-description {
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            margin-bottom: 25px;
            font-size: 0.95rem;
            line-height: 1.5;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }
        
        /* Loader Styles */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 60, 46, 0.95);
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
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.3),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1),
                inset 0 5px 15px rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 2;
            overflow: hidden;
        }
        
        .nia-loader-logo img {
            width: 100px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
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
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
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
            box-shadow: 0 0 10px var(--nia-gold);
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
                border-radius: 15px;
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
            
            .logo-3d {
                width: 140px;
                height: 140px;
            }
            
            .logo-3d img {
                max-width: 110px;
            }
        }
        
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
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
            <div class="nia-loader-text">Processing...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
    </div>
    
    <div class="login-container">
        <div class="row no-gutters">
            <div class="col-md-6 login-left">
                <div class="logo-3d">
                    <img src="dist/img/nialogo.png" alt="NIA Logo">
                </div>
                <h2>National Irrigation Administration</h2>
                <h3>Albay-Catanduanes IMO</h3>
            </div>
            <div class="col-md-6 login-right">
                <!-- Login Form Container -->
                <div class="login-form-container" id="loginFormContainer">
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
                        
                        <!-- Forgot Password Link -->
                        <div class="forgot-password-container">
                            <a class="forgot-password-link" id="forgotPasswordLink">
                                <i class="fas fa-key"></i>Forgot Password?
                            </a>
                        </div>
                    </form>
                    
                    <div class="footer-text">
                        <p class="mb-0">ACIMO Integrated Management Solution (AIMS)</p>
                    </div>
                </div>

                <!-- Forgot Password Form Container -->
                <div class="forgot-form-container" id="forgotFormContainer">
                    <h2 class="forgot-title">Reset Password</h2>
                    
                    <div class="forgot-description">
                        Enter your ID number to request a password reset. Administrators will be notified and will assist you with the reset process.
                    </div>
                    
                    <form id="forgotPasswordForm">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="id_number">ID Number</label>
                                <div class="input-container">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="id_number" name="id_number" 
                                               placeholder="Enter your ID number" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="fas fa-id-card"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-reset" id="resetButton">Request Password Reset</button>
                        
                        <!-- Back to Login Link -->
                        <div class="forgot-password-container">
                            <a class="back-to-login" id="backToLoginLink">
                                <i class="fas fa-arrow-left"></i>Back to Login
                            </a>
                        </div>
                    </form>
                    
                    <div class="footer-text">
                        <p class="mb-0">ACIMO Integrated Management Solution (AIMS)</p>
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
            
            // Form validation and loader for login
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

            // Toggle between login and forgot password forms
            $('#forgotPasswordLink').on('click', function() {
                $('#loginFormContainer').fadeOut(300, function() {
                    $('#forgotFormContainer').fadeIn(300);
                });
            });

            $('#backToLoginLink').on('click', function() {
                $('#forgotFormContainer').fadeOut(300, function() {
                    $('#loginFormContainer').fadeIn(300);
                });
            });

            // Handle forgot password form submission
            $('#forgotPasswordForm').on('submit', function(e) {
                e.preventDefault();
                
                const idNumber = $('#id_number').val().trim();
                
                if (!idNumber) {
                    showToast('error', 'Please enter your ID number.');
                    return;
                }

                // Show loader
                $('#loaderOverlay').addClass('active');
                $('#progressFill').css('width', '100%');

                // Make AJAX call to the API endpoint
                $.ajax({
                    url: 'ajax_forgot_password.php', // Use the dedicated API endpoint
                    type: 'POST',
                    data: {
                        id_number: idNumber
                    },
                    success: function(response) {
                        $('#loaderOverlay').removeClass('active');
                        
                        if (response.success) {
                            showToast('success', response.message);
                            
                            // Reset form and go back to login
                            $('#forgotPasswordForm')[0].reset();
                            $('#forgotFormContainer').fadeOut(300, function() {
                                $('#loginFormContainer').fadeIn(300);
                            });
                        } else {
                            showToast('error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loaderOverlay').removeClass('active');
                        showToast('error', 'Network error. Please try again.');
                        console.error('Forgot password error:', error);
                    }
                });
            });

            // Toast notification function
            function showToast(type, message) {
                // You can use Toastr or create custom toast
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                } else {
                    // Fallback alert
                    alert(message);
                }
            }
        });
    </script>
</body>
</html>