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
        body {
            background-color: #ffffff;
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
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            border: 1px solid #e0e0e0;
        }
        
        .login-left {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 500px;
        }
        
        .login-left img {
            max-width: 120px;
            margin-bottom: 25px;
        }
        
        .login-left h2 {
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.6rem;
        }
        
        .login-left h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .login-left p {
            opacity: 0.9;
            font-size: 1rem;
            line-height: 1.5;
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
            color: #2a5298;
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            font-size: 1.8rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            display: block;
            font-size: 1rem;
        }
        
        .form-control {
            border-radius: 5px;
            padding: 14px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            font-size: 1rem;
            height: auto;
        }
        
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.15);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-left: none;
            cursor: pointer;
            transition: all 0.3s;
            padding: 0 15px;
        }
        
        .input-group-text:hover {
            background-color: #e9ecef;
        }
        
        .input-group .form-control:not(:last-child) {
            border-right: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            color: white;
            padding: 14px;
            font-weight: 600;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 82, 152, 0.3);
        }
        
        .alert {
            border-radius: 5px;
            border: none;
            font-weight: 500;
            margin-bottom: 25px;
            padding: 12px 15px;
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
        
        .input-group {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row no-gutters">
            <div class="col-md-6 login-left">
                <img src="dist/img/nialogo.png" alt="NIA Logo">
                <h2>National Irrigation Administration</h2>
                <h3>Albay-Catanduanes IMO</h3>
                <p class="mt-3">Providing efficient irrigation services for sustainable agriculture</p>
            </div>
            <div class="col-md-6 login-right">
                <h2 class="login-title">User Login</h2>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form action="login.php" method="post">
                    <div class="form-section">
                        <div class="form-group">
                            <label for="username">Username</label>
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
                        
                        <div class="form-group">
                            <label for="password">Password</label>
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
                    
                    <button type="submit" class="btn btn-login btn-block">Login</button>
                </form>
                
                <div class="footer-text">
                    <p class="mb-0">ACIMO Intelligent Management Solution (AIMS)</p>
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
            
            // Form validation
            $('form').on('submit', function() {
                const username = $('#username').val().trim();
                const password = $('#password').val();
                
                if (!username || !password) {
                    return false;
                }
            });
        });
    </script>
</body>
</html>