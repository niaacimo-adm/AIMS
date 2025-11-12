<?php
// logout.php - Updated version
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set user offline in chat system BEFORE destroying session
if (isset($_SESSION['emp_id'])) {
    try {
        // Fix the path - logout.php is in root directory
        require_once 'config/database.php';
        require_once 'includes/chat_functions.php';
        
        $chat = new ChatFunctions();
        $chat->updateOnlineStatus($_SESSION['emp_id'], 0); // Set to offline
        error_log("User {$_SESSION['emp_id']} set offline during logout");
    } catch (Exception $e) {
        error_log("Error setting user offline: " . $e->getMessage());
    }
}

// Don't destroy session here - let perform_logout.php handle it
// This allows the JavaScript to show the loading animation properly
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>NIA ACIMO - Logging Out</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    
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
        
        @keyframes progressFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        @media (max-width: 576px) {
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
            <div class="nia-loader-text">Logging out...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Start the progress bar animation
            $('#progressFill').css({
                'width': '0%',
                'transition': 'width 3s ease-in-out'
            });
            
            // Trigger the animation
            setTimeout(function() {
                $('#progressFill').css('width', '100%');
            }, 100);
            
            // Wait for 3 seconds to show the loader and complete progress bar
            setTimeout(function() {
                // Perform the actual logout via AJAX
                $.ajax({
                    url: 'perform_logout.php',
                    type: 'POST',
                    success: function(response) {
                        // Redirect to login page after logout completes
                        window.location.href = 'index.php';
                    },
                    error: function() {
                        // Still redirect even if AJAX fails
                        window.location.href = 'index.php';
                    }
                });
            }, 3000);
        });
    </script>
</body>
</html>