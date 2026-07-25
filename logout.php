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
        
        .loader-content,
        .nia-loader-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }

        /* ── NIA CSS Loader ── */
        .nia-loader {
            position: relative;
            width: 120px;
            height: 120px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* rotating dashed ring */
        .nia-loader::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 4px solid transparent;
            border-top-color: var(--nia-gold);
            border-right-color: var(--nia-gold);
            animation: nia-spin 1.1s linear infinite;
        }

        /* soft pulsing halo */
        .nia-loader::after {
            content: "";
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px solid var(--nia-gold);
            opacity: 0;
            animation: nia-pulse 1.8s ease-out infinite;
        }

        /* Logo — bare <img class="nia-logo"> inside .nia-loader (no wrapper div) */
        .nia-loader .nia-logo,
        img.nia-logo {
            display: block !important;
            width: 74px !important;
            height: 74px !important;
            max-width: 74px !important;
            max-height: 74px !important;
            min-width: 74px !important;
            min-height: 74px !important;
            border-radius: 50% !important;
            object-fit: contain;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
            animation: nia-breathe 1.8s ease-in-out infinite;
        }

        /* Optional wrapper-div variant kept for compatibility */
        .nia-loader-logo {
            width: 94px !important;
            height: 94px !important;
            max-width: 94px !important;
            max-height: 94px !important;
            background: white;
            border-radius: 50% !important;
            display: flex !important;
            justify-content: center;
            align-items: center;
            box-shadow:
                0 15px 35px rgba(0, 0, 0, 0.3),
                inset 0 -5px 15px rgba(0, 0, 0, 0.1),
                inset 0 5px 15px rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 2;
            overflow: hidden;
            box-sizing: border-box;
            animation: nia-breathe 1.8s ease-in-out infinite;
        }

        .nia-loader-logo img {
            width: 74px !important;
            height: 74px !important;
            max-width: 74px !important;
            max-height: 74px !important;
            object-fit: contain;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
            animation: none;
        }

        .nia-loader-text,
        .nia-loader-label {
            color: white;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .nia-loader-label::after {
            content: "";
            display: inline-block;
            width: 1em;
            text-align: left;
            animation: nia-dots 1.4s steps(4, end) infinite;
        }

        @keyframes nia-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes nia-breathe {
            0%, 100% { transform: scale(.94); }
            50%      { transform: scale(1.02); }
        }

        @keyframes nia-pulse {
            0%   { transform: scale(.85); opacity: .6; }
            100% { transform: scale(1.25); opacity: 0; }
        }

        @keyframes nia-dots {
            0%   { content: ""; }
            25%  { content: "."; }
            50%  { content: ".."; }
            75%  { content: "..."; }
            100% { content: ""; }
        }

        @media (max-width: 576px) {
            .nia-loader {
                width: 96px;
                height: 96px;
            }

            .nia-loader .nia-logo,
            img.nia-logo {
                width: 58px !important;
                height: 58px !important;
                max-width: 58px !important;
                max-height: 58px !important;
            }

            .nia-loader-logo {
                width: 76px !important;
                height: 76px !important;
                max-width: 76px !important;
                max-height: 76px !important;
            }

            .nia-loader-logo img {
                width: 58px !important;
                height: 58px !important;
                max-width: 58px !important;
                max-height: 58px !important;
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
        <div class="nia-loader-wrap">
            <div class="nia-loader">
                <img class="nia-logo" src="dist/img/nialogo.png" alt="Loading"
                     style="width:74px;height:74px;max-width:74px;max-height:74px;border-radius:50%;object-fit:contain;display:block;">
            </div>
            <div class="nia-loader-label">Logging out</div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Show the loader for a few seconds before logging out
            setTimeout(function() {
                // Perform the actual logout via AJAX
                $.ajax({
                    url: 'perform_logout.php',
                    type: 'POST',
                    success: function(response) {
                        // Redirect to login page after logout completes
                        window.location.href = 'login.php';
                    },
                    error: function() {
                        // Still redirect even if AJAX fails
                        window.location.href = 'login.php';
                    }
                });
            }, 3000);
        });
    </script>
</body>
</html>