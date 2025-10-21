<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Access Denied - NIA Albay-Catanduanes IMO</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- IonIcons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --card-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .unauthorized-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .unauthorized-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s infinite linear;
            z-index: 0;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-50px, -50px) rotate(360deg); }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--card-shadow);
            padding: 0;
            position: relative;
            z-index: 1;
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(135deg, var(--danger), var(--warning));
        }
        
        .error-header {
            background: linear-gradient(135deg, var(--danger), var(--warning));
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .error-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .error-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            display: block;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .error-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .error-body {
            padding: 50px 40px 40px;
            text-align: center;
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--danger), var(--warning));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            line-height: 1;
        }
        
        .error-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
        }
        
        .error-message {
            color: var(--gray);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-details {
            background: linear-gradient(135deg, #fff5f5, #ffe6e6);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--danger);
        }
        
        .error-details p {
            margin-bottom: 15px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .reason-list {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }
        
        .reason-list li {
            padding: 8px 0;
            color: var(--gray);
            display: flex;
            align-items: flex-start;
            font-size: 0.95rem;
        }
        
        .reason-list li i {
            color: var(--danger);
            margin-right: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
        
        .btn-modern {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 200px;
        }
        
        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(67, 97, 238, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 12px 35px rgba(108, 117, 125, 0.4);
        }
        
        .btn-icon {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: floatElement 15s infinite linear;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: -5s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: -10s;
        }
        
        @keyframes floatElement {
            0% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -50px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
        
        .footer-modern {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding: 25px 0;
            margin-top: 40px;
            position: relative;
            z-index: 1;
        }
        
        .footer-content {
            text-align: center;
        }
        
        .footer-logo {
            margin-bottom: 15px;
        }
        
        .footer-logo img {
            height: 45px;
            filter: brightness(0.8);
        }
        
        .footer-text {
            color: var(--gray);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 768px) {
            .unauthorized-container {
                padding: 20px 15px;
            }
            
            .glass-card {
                max-width: 100%;
            }
            
            .error-header {
                padding: 30px 20px 25px;
            }
            
            .error-body {
                padding: 40px 25px 30px;
            }
            
            .error-code {
                font-size: 3rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
            
            .btn-modern {
                min-width: 180px;
                padding: 12px 25px;
            }
        }
        
        @media (max-width: 480px) {
            .error-header h2 {
                font-size: 1.5rem;
            }
            
            .error-code {
                font-size: 2.5rem;
            }
            
            .error-title {
                font-size: 1.3rem;
            }
            
            .error-details {
                padding: 20px;
            }
            
            .action-buttons {
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <!-- Unauthorized Section -->
    <div class="unauthorized-container">
        <div class="glass-card pulse">
            <div class="error-header">
                <i class="fas fa-ban error-icon"></i>
                <h2>Access Restricted</h2>
            </div>
            
            <div class="error-body">
                <div class="error-code">403</div>
                <h1 class="error-title">Access Denied</h1>
                
                <div class="error-message">
                    <p>
                        You don't have permission to access this page or resource.
                        <?php if (isset($_SESSION['error'])): ?>
                        <br><strong class="text-danger"><?= htmlspecialchars($_SESSION['error']) ?></strong>
                        <?php unset($_SESSION['error']); endif; ?>
                    </p>
                </div>
                
                <div class="error-details">
                    <p class="mb-3"><strong>Possible reasons:</strong></p>
                    <ul class="reason-list">
                        <li>
                            <i class="fas fa-lock"></i>
                            <span>Insufficient permissions for your user role</span>
                        </li>
                        <li>
                            <i class="fas fa-tools"></i>
                            <span>The module is currently under maintenance</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Your session has expired</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt"></i>
                            <span>Invalid access attempt</span>
                        </li>
                    </ul>
                </div>
                
                <div class="action-buttons">
                    <a href="views/dashboard.php" class="btn-modern">
                        <i class="fas fa-tachometer-alt btn-icon"></i>
                        Return to Dashboard
                    </a>
                    <a href="views/profile.php" class="btn-modern btn-secondary">
                        <i class="fas fa-user btn-icon"></i>
                        Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-modern">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="dist/img/nialogo.png" alt="NIA Logo">
                </div>
                <p class="footer-text mb-1">&copy; <?= date('Y') ?> National Irrigation Administration - Albay Catanduanes IMO</p>
                <p class="footer-text mb-0">Providing efficient irrigation services for sustainable agricultural development</p>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="dist/js/adminlte.js"></script>
    <!-- SweetAlert2 -->
    <script src="plugins/sweetalert2/sweetalert2.min.js"></script>

    <script>
    $(document).ready(function() {
        // Add subtle hover effects
        $('.glass-card').hover(
            function() {
                $(this).css('transform', 'translateY(-5px)');
            },
            function() {
                $(this).css('transform', 'translateY(0)');
            }
        );

        // Add click animation to buttons
        $('.btn-modern').on('click', function() {
            $(this).css('transform', 'scale(0.95)');
            setTimeout(() => {
                $(this).css('transform', '');
            }, 150);
        });

        // Auto-redirect after 30 seconds (optional)
        setTimeout(() => {
            Swal.fire({
                title: 'Redirecting...',
                text: 'You will be redirected to your profile page',
                icon: 'info',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'views/profile.php';
            });
        }, 30000);
    });
    </script>
</body>
</html>