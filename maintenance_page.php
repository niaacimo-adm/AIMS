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
        .maintenance-section {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 80px 0 40px;
        }
        
        .maintenance-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            margin-bottom: 30px;
        }
        
        .maintenance-header {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            border-bottom: 4px solid #28a745;
        }
        
        .module-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .module-card.maintenance {
            border-left: 4px solid #dc3545;
            background-color: #fff5f5;
        }
        
        .module-card.active {
            border-left: 4px solid #28a745;
            background-color: #f8fff9;
        }
        
        .module-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .maintenance-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .back-to-dashboard {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .back-to-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: white;
            text-decoration: none;
        }
        
        .warning-card {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .danger-card {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="40" class="d-inline-block align-middle">
                <span class="align-middle">NIA Albay-Catanduanes IMO</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="views/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="maintenance_page.php">Module Maintenance</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Maintenance Section -->
    <section class="maintenance-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card maintenance-card">
                        <div class="maintenance-header text-center">
                            <div class="row align-items-center">
                                <div class="col-md-8 text-md-left text-center">
                                    <h2 class="mb-1"><i class="fas fa-tools mr-2"></i>Module Maintenance</h2>
                                    <p class="mb-0">Manage system modules and maintenance status</p>
                                </div>
                                <div class="col-md-4 text-md-right text-center mt-md-0 mt-3">
                                    <a href="views/dashboard.php" class="back-to-dashboard">
                                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stats-card text-center">
                                        <h3><?= count($modules) ?></h3>
                                        <p class="mb-0">Total Modules</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card text-center">
                                        <h3><?= count(array_filter($modules, function($m) { return !$m['is_under_maintenance']; })) ?></h3>
                                        <p class="mb-0">Active Modules</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="danger-card text-center">
                                        <h3><?= count(array_filter($modules, function($m) { return $m['is_under_maintenance']; })) ?></h3>
                                        <p class="mb-0">Under Maintenance</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="warning-card text-center">
                                        <h3>0</h3>
                                        <p class="mb-0">Other Status</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages -->
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                                    <?= $_SESSION['success_message'] ?>
                                </div>
                                <?php unset($_SESSION['success_message']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                                    <?= $_SESSION['error_message'] ?>
                                </div>
                                <?php unset($_SESSION['error_message']); ?>
                            <?php endif; ?>

                            <?php if (empty($modules)): ?>
                                <div class="alert alert-warning">
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> No Modules Found</h5>
                                    <p>The system_modules table is empty. Please run the database setup to populate the modules.</p>
                                </div>
                            <?php else: ?>
                                <!-- Modules Grid -->
                                <div class="row">
                                    <?php foreach ($modules as $module): ?>
                                        <div class="col-lg-4 col-md-6">
                                            <div class="card module-card <?= $module['is_under_maintenance'] ? 'maintenance' : 'active' ?>">
                                                <div class="card-body position-relative">
                                                    <?php if ($module['is_under_maintenance']): ?>
                                                        <span class="badge badge-danger maintenance-badge">Under Maintenance</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success maintenance-badge">Active</span>
                                                    <?php endif; ?>
                                                    
                                                    <div class="text-center mb-3">
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
                                                        <div class="module-icon text-<?= $module['is_under_maintenance'] ? 'danger' : 'success' ?>">
                                                            <i class="fas <?= $icon ?>"></i>
                                                        </div>
                                                    </div>
                                                    
                                                    <h5 class="card-title text-center"><?= htmlspecialchars($module['module_name']) ?></h5>
                                                    <p class="card-text text-muted text-center small">
                                                        <?= htmlspecialchars($module['module_description']) ?>
                                                    </p>
                                                    
                                                    <div class="text-center mt-4">
                                                        <form method="POST" class="d-inline-block">
                                                            <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                                            <label class="switch">
                                                                <input type="checkbox" name="is_under_maintenance" 
                                                                       value="1" <?= $module['is_under_maintenance'] ? 'checked' : '' ?> 
                                                                       onchange="this.form.submit()">
                                                                <span class="slider"></span>
                                                            </label>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= $module['is_under_maintenance'] ? 'Maintenance Mode' : 'Active Mode' ?>
                                                            </small>
                                                        </form>
                                                    </div>
                                                    
                                                    <div class="text-center mt-3">
                                                        <small class="text-muted">
                                                            Last updated: <?= date('M j, Y g:i A', strtotime($module['updated_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Maintenance Notice -->
                            <div class="alert alert-info mt-4">
                                <h5><i class="icon fas fa-info-circle"></i> Maintenance Mode Information</h5>
                                <p class="mb-2">
                                    <strong>When a module is in maintenance mode:</strong>
                                </p>
                                <ul class="mb-0">
                                    <li>Users will see a maintenance message instead of the module interface</li>
                                    <li>All functionality within the module will be temporarily disabled</li>
                                    <li>Administrators can still access the module for configuration</li>
                                    <li>Scheduled maintenance helps prevent errors during updates</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <div class="footer-logo">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="50">
            </div>
            <p class="footer-text mb-1">&copy; <?= date('Y') ?> National Irrigation Administration - Albay Catanduanes IMO. All rights reserved.</p>
            <p class="footer-text mb-0">Providing efficient irrigation services for sustainable agricultural development</p>
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
        $('input[name="is_under_maintenance"]').on('change', function() {
            const moduleName = $(this).closest('.card').find('.card-title').text();
            const newStatus = this.checked ? 'maintenance' : 'active';
            
            Swal.fire({
                title: 'Confirm Status Change',
                html: `Are you sure you want to set <strong>${moduleName}</strong> to <strong>${newStatus} mode</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, change it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(this).closest('form').submit();
                } else {
                    // Revert the checkbox
                    this.checked = !this.checked;
                }
            });
        });
    });
    </script>
</body>
</html>