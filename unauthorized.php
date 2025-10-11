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
    <!-- DataTables -->
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="plugins/fullcalendar/main.css">

    <style>
        .unauthorized-section {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px 0 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .unauthorized-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            margin-bottom: 30px;
            background: white;
        }
        
        .unauthorized-header {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            border-bottom: 4px solid #dc3545;
            text-align: center;
        }
        
        .unauthorized-body {
            padding: 40px;
            text-align: center;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .error-content {
            font-size: 1.1rem;
            color: #000000ff;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        
        .footer {
            background: #343a40;
            color: white;
            padding: 20px 0;
            margin-top: 40px;
        }
        
        .footer-logo img {
            margin-bottom: 10px;
        }
        
        .footer-text {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .back-to-home {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 20px;
        }
        
        .back-to-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- Unauthorized Section -->
    <section class="unauthorized-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card unauthorized-card">
                        <div class="unauthorized-header">
                            <h2 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Access Restricted</h2>
                        </div>
                        
                        <div class="unauthorized-body">
                            <div class="error-icon">
                                <i class="fas fa-ban"></i>
                            </div>
                            
                            <h1 class="error-title">403 - Access Denied</h1>
                            
                            <div class="error-content">
                                <p>
                                    You don't have permission to access this page or resource.
                                    <?php if (isset($_SESSION['error'])): ?>
                                    <br><strong class="text-danger"><?= htmlspecialchars($_SESSION['error']) ?></strong>
                                    <?php unset($_SESSION['error']); endif; ?>
                                </p>
                                <p class="mb-4">
                                    This could be due to one of the following reasons:
                                </p>
                                <ul class="list-unstyled text-left">
                                    <li><i class="fas fa-times-circle text-danger mr-2"></i>Insufficient permissions for your user role</li>
                                    <li><i class="fas fa-times-circle text-danger mr-2"></i>The module is currently under maintenance</li>
                                    <li><i class="fas fa-times-circle text-danger mr-2"></i>Your session has expired</li>
                                    <li><i class="fas fa-times-circle text-danger mr-2"></i>Invalid access attempt</li>
                                </ul>
                            </div>
                            
                            <div class="action-buttons">
                                <!-- <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt mr-2"></i>Return to Dashboard
                                </a> -->
                                <br>
                                <a href="views/profile.php" class="back-to-home">
                                    <i class="fas fa-home mr-2"></i>Back to Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="dist/js/adminlte.js"></script>
    <!-- DataTables -->
    <script src="plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="plugins/sweetalert2/sweetalert2.min.js"></script>
    <!-- Toastr -->
    <script src="plugins/toastr/toastr.min.js"></script>
    <!-- Select2 -->
    <script src="plugins/select2/js/select2.full.min.js"></script>
    <!-- FullCalendar -->
    <script src="plugins/fullcalendar/main.js"></script>
    <script src="plugins/moment/moment.min.js"></script>

    <script>
    $(document).ready(function() {
        // Smooth scrolling for navigation links
        $('a[href*="#"]').not('[href="#"]').not('[href="#0"]').click(function(event) {
            if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                if (target.length) {
                    event.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 70
                    }, 1000);
                }
            }
        });

        // Add active class to nav items on scroll
        $(window).scroll(function() {
            var scrollDistance = $(window).scrollTop();
            
            $('section').each(function(i) {
                if ($(this).position().top <= scrollDistance + 100) {
                    $('.navbar-nav a.active').removeClass('active');
                    $('.navbar-nav a').eq(i).addClass('active');
                }
            });
        }).scroll();
    });
    </script>
</body>
</html>