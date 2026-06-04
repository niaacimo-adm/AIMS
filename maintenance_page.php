<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !hasPermission('manage_settings')) {
    header("Location: login.php");
    exit();
}

// Get modules status
$database = new Database();
$db = $database->getConnection();

// Fetch modules from database
$modules_stmt = $db->prepare("SELECT * FROM system_modules ORDER BY module_name");
$modules_stmt->execute();
$modules = $modules_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle module status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_id'])) {
    $module_id = $_POST['module_id'];
    $is_under_maintenance = isset($_POST['is_under_maintenance']) ? 1 : 0;
    
    $update_stmt = $db->prepare("UPDATE system_modules SET is_under_maintenance = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("ii", $is_under_maintenance, $module_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Module status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating module status.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Module Maintenance - NIA Albay-Catanduanes IMO</title>
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
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }
        
        .nav-glass {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            color:white;
        }
        
        .main-container {
            padding: 100px 0 40px;
            min-height: 100vh;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            transform: rotate(30deg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-card.total {
            border-left-color: var(--primary);
            background: linear-gradient(135deg, #f8f9ff, #eef1ff);
        }
        
        .stat-card.active {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #f0fffd, #e6fffb);
        }
        
        .stat-card.maintenance {
            border-left-color: var(--danger);
            background: linear-gradient(135deg, #fff5f5, #ffe6e6);
        }
        
        .stat-card.other {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fff0f7, #ffe6f2);
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--gray);
            font-weight: 500;
            margin: 0;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #f0f0f0;
            min-height: 280px;
            display: flex;
            flex-direction: column;
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--success), var(--info));
        }
        
        .module-card.maintenance::before {
            background: linear-gradient(135deg, var(--danger), var(--warning));
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .module-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            position: relative;
            padding-right: 80px; /* Space for status badge */
        }
        
        .module-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .module-icon.active {
            background: linear-gradient(135deg, var(--success), var(--info));
            color: white;
        }
        
        .module-icon.maintenance {
            background: linear-gradient(135deg, var(--danger), var(--warning));
            color: white;
        }
        
        .module-info {
            flex: 1;
            min-width: 0; /* Prevent text overflow */
        }
        
        .module-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
            line-height: 1.3;
            word-wrap: break-word;
        }
        
        .module-description {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0;
        }
        
        .module-status {
            position: absolute;
            top: 0;
            right: 0;
            z-index: 2;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .status-badge.active {
            background: rgba(76, 201, 240, 0.15);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
        
        .status-badge.maintenance {
            background: rgba(230, 57, 70, 0.15);
            color: var(--danger);
            border: 1px solid rgba(230, 57, 70, 0.3);
        }
        
        .module-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .toggle-section {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .toggle-text {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray);
        }
        
        /* Modern Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            flex-shrink: 0;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #ccc, #999);
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--success), var(--info));
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .last-updated {
            font-size: 0.8rem;
            color: #aaa;
            text-align: center;
            margin-top: 15px;
        }
        
        .info-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
            border-left: 4px solid var(--info);
        }
        
        .info-card h5 {
            color: var(--dark);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-card ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .info-card li {
            margin-bottom: 8px;
            color: var(--gray);
        }
        
        .btn-modern {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
            color: white;
        }
        
        .alert-modern {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .footer-modern {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 40px;
        }
        
        /* Ensure text doesn't overflow */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .text-truncate-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 80px 15px 20px;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-section {
                padding: 20px;
            }
            
            .module-header {
                padding-right: 70px;
            }
            
            .module-card {
                min-height: 260px;
            }
        }
        
        @media (max-width: 480px) {
            .module-header {
                flex-direction: column;
                align-items: flex-start;
                padding-right: 0;
            }
            
            .module-icon {
                margin-bottom: 10px;
            }
            
            .module-status {
                position: static;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light nav-glass fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="login.php">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="40" class="mr-2">
                <span class="font-weight-bold text-dark">NIA Albay-Catanduanes IMO</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="views/dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active text-dark font-weight-bold" href="maintenance_page.php">
                            <i class="fas fa-tools mr-1"></i> Module Maintenance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container">
            <!-- Header Section -->
            <div class="header-section glass-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-tools mr-2"></i>Module Maintenance</h1>
                        <p class="mb-0 opacity-75">Manage system modules and maintenance status with real-time controls</p>
                    </div>
                    <div class="col-md-4 text-md-right text-center mt-md-0 mt-3">
                        <a href="views/dashboard.php" class="btn btn-modern">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <h3><?= count($modules) ?></h3>
                    <p>Total Modules</p>
                </div>
                <div class="stat-card active">
                    <h3><?= count(array_filter($modules, function($m) { return !$m['is_under_maintenance']; })) ?></h3>
                    <p>Active Modules</p>
                </div>
                <div class="stat-card maintenance">
                    <h3><?= count(array_filter($modules, function($m) { return $m['is_under_maintenance']; })) ?></h3>
                    <p>Under Maintenance</p>
                </div>
                <div class="stat-card other">
                    <h3>0</h3>
                    <p>Other Status</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-modern alert-dismissible fade show">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-lg mr-3"></i>
                        <div>
                            <h5 class="mb-1">Success!</h5>
                            <p class="mb-0"><?= $_SESSION['success_message'] ?></p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-modern alert-dismissible fade show">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-lg mr-3"></i>
                        <div>
                            <h5 class="mb-1">Error!</h5>
                            <p class="mb-0"><?= $_SESSION['error_message'] ?></p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (empty($modules)): ?>
                <div class="alert alert-warning alert-modern">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-lg mr-3"></i>
                        <div>
                            <h5 class="mb-1">No Modules Found</h5>
                            <p class="mb-0">The system_modules table is empty. Please run the database setup to populate the modules.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Modules Grid -->
                <div class="modules-grid">
                    <?php foreach ($modules as $module): ?>
                        <div class="module-card glass-card <?= $module['is_under_maintenance'] ? 'maintenance' : 'active' ?>">
                            <div class="module-content">
                                <div class="module-header">
                                    <div class="d-flex align-items-start w-100">
                                        <?php
                                        $icons = [
                                            'Admin Dashboard' => 'fa-tachometer-alt',
                                            'Attachment Monitoring' => 'fa-paperclip',
                                            'Calendar System' => 'fa-calendar-alt',
                                            'Employee Management' => 'fa-users',
                                            'Employee Creation' => 'fa-user-plus',
                                            'Employee Directory' => 'fa-users',
                                            'Module Maintenance' => 'fa-tools',
                                            'Content Management' => 'fa-tv',
                                            'Appointment Settings' => 'fa-briefcase',
                                            'Position Management' => 'fa-id-card-alt',
                                            'Section Management' => 'fa-sitemap',
                                            'Office Management' => 'fa-building',
                                            'Employment Status' => 'fa-user-check',
                                            'User Management' => 'fa-user-cog',
                                            'Role Management' => 'fa-user-shield',
                                            'Permission Management' => 'fa-key',
                                            'Service Dashboard' => 'fa-tachometer-alt',
                                            'Service Calendar' => 'fa-calendar-alt',
                                            'Service Information' => 'fa-truck',
                                            'Operator/Driver Management' => 'fa-id-card',
                                            'Transportation Request' => 'fa-file-alt',
                                            'Inventory Dashboard' => 'fa-tachometer-alt',
                                            'Inventory Management' => 'fa-boxes',
                                            'Supply Requests' => 'fa-clipboard-check',
                                            'My Supply Requests' => 'fa-list-ol',
                                            'File Management' => 'fa-folder'
                                        ];
                                        $icon = $icons[$module['module_name']] ?? 'fa-cube';
                                        ?>
                                        <div class="module-icon <?= $module['is_under_maintenance'] ? 'maintenance' : 'active' ?>">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div class="module-info">
                                            <h5 class="module-title text-truncate-2"><?= htmlspecialchars($module['module_name']) ?></h5>
                                            <p class="module-description text-truncate-3"><?= htmlspecialchars($module['module_description']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="module-status">
                                        <span class="status-badge <?= $module['is_under_maintenance'] ? 'maintenance' : 'active' ?>">
                                            <?= $module['is_under_maintenance'] ? 'Maintenance' : 'Active' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="toggle-section">
                                    <div class="toggle-label">
                                        <span class="toggle-text">Maintenance Mode</span>
                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="is_under_maintenance" 
                                                       value="1" <?= $module['is_under_maintenance'] ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </form>
                                    </div>
                                    <div class="last-updated">
                                        Updated: <?= date('M j, Y g:i A', strtotime($module['updated_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Information Card -->
            <div class="info-card glass-card">
                <h5><i class="fas fa-info-circle mr-2"></i>Maintenance Mode Information</h5>
                <p class="mb-3"><strong>When a module is in maintenance mode:</strong></p>
                <ul>
                    <li>Users will see a maintenance message instead of the module interface</li>
                    <li>All functionality within the module will be temporarily disabled</li>
                    <li>Administrators can still access the module for configuration</li>
                    <li>Scheduled maintenance helps prevent errors during updates</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-modern py-4">
        <div class="container text-center">
            <div class="footer-logo mb-3">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="50">
            </div>
            <p class="mb-1 text-dark">&copy; <?= date('Y') ?> National Irrigation Administration - Albay Catanduanes IMO. All rights reserved.</p>
            <p class="mb-0 text-muted">Providing efficient irrigation services for sustainable agricultural development</p>
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
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);

        // Confirm maintenance mode changes
        $('.toggle-switch input').on('change', function() {
            const moduleName = $(this).closest('.module-card').find('.module-title').text();
            const newStatus = this.checked ? 'maintenance' : 'active';
            const form = $(this).closest('form');
            
            Swal.fire({
                title: 'Confirm Status Change',
                html: `Are you sure you want to set <strong>${moduleName}</strong> to <strong>${newStatus} mode</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4361ee',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, change it!',
                cancelButtonText: 'Cancel',
                backdrop: 'rgba(0,0,0,0.4)'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                } else {
                    // Revert the checkbox
                    this.checked = !this.checked;
                }
            });
        });
        
        // Add animation to cards on load
        $('.module-card').each(function(index) {
            $(this).css('opacity', '0').css('transform', 'translateY(20px)');
            setTimeout(() => {
                $(this).animate({
                    opacity: 1,
                    transform: 'translateY(0)'
                }, 400);
            }, index * 100);
        });
    });
    </script>
</body>
</html>