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
            perspective: 1000px;
        }
        
        .scene {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            animation: sceneRotate 20s infinite linear;
        }
        
        @keyframes sceneRotate {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
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
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(36, 231, 143, 0.1),
                0 0 50px rgba(36, 231, 143, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateX(5deg) rotateY(-5deg);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-container:hover {
            transform: rotateX(0deg) rotateY(0deg) translateY(-10px);
            box-shadow: 
                0 35px 70px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(36, 231, 143, 0.3),
                0 0 80px rgba(36, 231, 143, 0.4);
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
            transform-style: preserve-3d;
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
        
        .login-left::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .logo-3d {
            width: 180px;
            height: 180px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.3),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1),
                inset 0 5px 15px rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
            transform: translateZ(20px);
            transition: transform 0.3s ease;
        }
        
        .logo-3d:hover {
            transform: translateZ(30px) scale(1.05);
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
            transform: translateZ(10px);
        }
        
        .login-left h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            font-weight: 600;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            transform: translateZ(10px);
        }
        
        .login-right {
            padding: 50px 40px;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 500px;
            position: relative;
            transform-style: preserve-3d;
        }
        
        .login-right::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(36, 231, 143, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(42, 152, 99, 0.05) 0%, transparent 50%);
            z-index: 0;
        }
        
        .login-title {
            color: var(--nia-blue);
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            font-size: 1.8rem;
            position: relative;
            transform: translateZ(15px);
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
            transform-style: preserve-3d;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
            transform: translateZ(10px);
        }
        
        .input-container {
            max-width: 400px;
            margin: 0 auto;
            transform-style: preserve-3d;
        }
        
        .input-group {
            box-shadow: 
                0 5px 15px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            transform-style: preserve-3d;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .input-group:focus-within {
            box-shadow: 
                0 10px 25px rgba(36, 231, 143, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transform: translateY(-5px) translateZ(10px);
            border-color: var(--nia-blue);
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
            transform: translateZ(5px);
        }
        
        .form-control:focus {
            outline: none;
            box-shadow: none;
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0 20px;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translateZ(5px);
        }
        
        .input-group-text:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
        }
        
        .password-toggle:hover {
            transform: scale(1.1) translateZ(5px);
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
            transform: translateZ(15px);
        }
        
        .btn-login:hover {
            transform: translateY(-5px) translateZ(20px);
            box-shadow: 
                0 15px 30px rgba(36, 231, 143, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0) translateZ(15px);
        }
        
        .btn-login::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover::before {
            left: 100%;
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
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
            transform: translateZ(10px);
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        .footer-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 30px;
            text-align: center;
            transform: translateZ(5px);
        }
        
        .form-section {
            margin-bottom: 30px;
            transform-style: preserve-3d;
        }
        
        .login-form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
            transform-style: preserve-3d;
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
            transform-style: preserve-3d;
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
            transform-style: preserve-3d;
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
            transform: translateZ(30px);
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
            transform-style: preserve-3d;
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
            transform: translateZ(20px);
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .progress-bar {
            width: 200px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
            transform: translateZ(15px);
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
            transform-style: preserve-3d;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg) translateZ(0); }
            100% { transform: rotate(360deg) translateZ(0); }
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
            
            .login-container {
                transform: none;
            }
            
            .login-container:hover {
                transform: translateY(-5px);
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
    <div class="scene">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
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
            <div class="nia-loader-text">Logging in...</div>
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
            
            // Add subtle mouse move effect for 3D
            $(document).on('mousemove', function(e) {
                const x = (e.clientX / window.innerWidth - 0.5) * 10;
                const y = (e.clientY / window.innerHeight - 0.5) * 10;
                
                $('.login-container').css({
                    'transform': `rotateX(${y}deg) rotateY(${x}deg) translateY(-10px)`
                });
            });
            
            // Reset transform when mouse leaves
            $('.login-container').on('mouseleave', function() {
                $(this).css({
                    'transform': 'rotateX(5deg) rotateY(-5deg)'
                });
            });
        });
    </script>
</body>
</html>