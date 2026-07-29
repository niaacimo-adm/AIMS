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

// Handle module actions: toggle maintenance, add module, delete module
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'toggle';

    // --- ADD MODULE ---
    if ($action === 'add_module') {
        $module_name = trim($_POST['module_name'] ?? '');
        $module_description = trim($_POST['module_description'] ?? '');

        if ($module_name === '') {
            $_SESSION['error_message'] = "Module name is required.";
        } else {
            $insert_stmt = $db->prepare("INSERT INTO system_modules (module_name, module_description, is_under_maintenance, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())");
            $insert_stmt->bind_param("ss", $module_name, $module_description);

            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Module \"$module_name\" added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding module. It may already exist.";
            }
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- DELETE MODULE ---
    if ($action === 'delete_module' && isset($_POST['module_id'])) {
        $module_id = $_POST['module_id'];

        $delete_stmt = $db->prepare("DELETE FROM system_modules WHERE id = ?");
        $delete_stmt->bind_param("i", $module_id);

        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Module deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting module.";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- TOGGLE MAINTENANCE STATUS (existing behavior) ---
    if (isset($_POST['module_id'])) {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        if (localStorage.getItem('darkMode') === '1') {
            document.documentElement.classList.add('dark-mode-preload');
        }
    </script>
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
        /* ===== Shared theme variables — mirrors includes/mainheader.php ===== */
        :root {
            --green:              #24e78f;
            --green-dark:         #2a9863;
            --green-mid:          #1a5c38;

            --header-bg:          #ffffff;
            --header-color:       #0f2d1e;
            --header-border:      rgba(42,152,99,0.15);

            --footer-bg:          #ffffff;
            --footer-color:       #0f2d1e;
            --footer-border:      rgba(42,152,99,0.15);

            --body-bg:            #eef7f2;
            --card-bg:            #ffffff;
            --card-border:        rgba(42,152,99,0.15);

            --input-bg:           #ffffff;
            --input-color:        #0f2d1e;
            --input-border:       rgba(42,152,99,0.25);

            --text-primary:       #0f2d1e;
            --text-muted:         #4a7a5e;

            --table-bg:           #ffffff;
            --table-stripe:       #f0faf5;
            --table-border:       rgba(42,152,99,0.18);

            --modal-bg:           #ffffff;
            --modal-header-bg:    #1c4d38;
            --modal-header-color: #ffffff;

            --badge-bg:           #24e78f;

            /* Status accents, kept distinct from the green brand color */
            --danger:  #e63946;
            --warning: #f0a500;

            --card-shadow: 0 4px 20px rgba(15, 45, 30, 0.08);
            --hover-shadow: 0 8px 30px rgba(15, 45, 30, 0.14);
        }

        body.dark-mode {
            --header-bg:          #0f2d1e;
            --header-color:       #d4f5e5;
            --header-border:      rgba(36,231,143,0.12);

            --footer-bg:          #0f2d1e;
            --footer-color:       #d4f5e5;
            --footer-border:      rgba(36,231,143,0.12);

            --body-bg:            #0b1f17;
            --card-bg:            #102f22;
            --card-border:        rgba(36,231,143,0.10);

            --input-bg:           #0e2619;
            --input-color:        #d4f5e5;
            --input-border:       rgba(36,231,143,0.18);

            --text-primary:       #d4f5e5;
            --text-muted:         #6aad8a;

            --table-bg:           #102f22;
            --table-stripe:       #122b1d;
            --table-border:       rgba(36,231,143,0.12);

            --modal-bg:           #102f22;
            --modal-header-bg:    #091d14;
            --modal-header-color: #d4f5e5;

            --badge-bg:           #2a9863;

            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
            --hover-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
        }

        html.dark-mode-preload body { background: #0b1f17 !important; }

        body {
            background: var(--body-bg);
            color: var(--text-primary);
            font-family: 'Source Sans Pro', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .glass-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .glass-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }

        .nav-glass {
            background: var(--header-bg) !important;
            border-bottom: 1px solid var(--header-border);
            box-shadow: 0 2px 20px rgba(15, 45, 30, 0.08);
        }

        .nav-glass .navbar-brand span,
        .nav-glass .nav-link {
            color: var(--header-color) !important;
        }

        .nav-glass .nav-link.active {
            color: var(--green-dark) !important;
        }

        #darkModeToggle {
            cursor: pointer;
        }

        .main-container {
            padding: 100px 0 40px;
            min-height: 100vh;
        }

        .header-section {
            background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
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
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid;
            border-color: var(--card-border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card.total     { border-left-color: var(--green-dark); }
        .stat-card.active    { border-left-color: var(--green); }
        .stat-card.maintenance { border-left-color: var(--danger); }
        .stat-card.other     { border-left-color: var(--warning); }

        body.dark-mode .stat-card.total       { background: linear-gradient(135deg, var(--card-bg), #0e2619); }
        body.dark-mode .stat-card.active      { background: linear-gradient(135deg, var(--card-bg), #0e2619); }
        body.dark-mode .stat-card.maintenance { background: linear-gradient(135deg, var(--card-bg), #2a1416); }
        body.dark-mode .stat-card.other       { background: linear-gradient(135deg, var(--card-bg), #2a2210); }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .stat-card p {
            color: var(--text-muted);
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
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--card-border);
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
            background: linear-gradient(135deg, var(--green), var(--green-dark));
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
            flex-wrap: wrap;
            align-items: flex-start;
            row-gap: 8px;
            margin-bottom: 15px;
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
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
        }

        .module-icon.maintenance {
            background: linear-gradient(135deg, var(--danger), var(--warning));
            color: white;
        }

        .module-info {
            flex: 1;
            min-width: 0;
        }

        .module-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
            line-height: 1.3;
            word-wrap: break-word;
        }

        .module-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0;
        }

        .module-status {
            margin-left: auto;
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
            background: rgba(36, 231, 143, 0.15);
            color: var(--green-dark);
            border: 1px solid rgba(36, 231, 143, 0.35);
        }

        body.dark-mode .status-badge.active {
            color: var(--green);
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
            border-top: 1px solid var(--card-border);
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
            color: var(--text-muted);
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
            background: linear-gradient(135deg, var(--danger), var(--warning));
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .last-updated {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 15px;
        }

        .module-footer-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .btn-delete-module {
            background: transparent;
            border: 1px solid rgba(230, 57, 70, 0.35);
            color: var(--danger);
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .btn-delete-module:hover {
            background: var(--danger);
            color: white;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(36,231,143,0.12), rgba(42,152,99,0.12));
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
            border-left: 4px solid var(--green-dark);
        }

        .info-card h5 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .info-card ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .info-card li {
            margin-bottom: 8px;
            color: var(--text-muted);
        }

        .btn-modern {
            background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 92, 56, 0.3);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 92, 56, 0.4);
            color: white;
        }

        .btn-add-module {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: #0f2d1e;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(36, 231, 143, 0.3);
        }

        .btn-add-module:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(36, 231, 143, 0.4);
            color: #0f2d1e;
        }

        .alert-modern {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
        }

        .footer-modern {
            background: var(--footer-bg);
            border-top: 1px solid var(--footer-border);
            margin-top: 40px;
        }

        .footer-modern p.text-dark { color: var(--text-primary) !important; }
        .footer-modern p.text-muted { color: var(--text-muted) !important; }

        /* Add Module modal */
        #addModuleModal .modal-content {
            background: var(--modal-bg);
            color: var(--text-primary);
            border-radius: 16px;
            border: 1px solid var(--card-border);
        }

        #addModuleModal .modal-header {
            background: var(--modal-header-bg);
            color: var(--modal-header-color);
            border-radius: 16px 16px 0 0;
        }

        #addModuleModal .form-control {
            background: var(--input-bg);
            color: var(--input-color);
            border: 1px solid var(--input-border);
        }

        #addModuleModal .form-control:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 0.2rem rgba(36,231,143,0.25);
        }

        #addModuleModal label {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* SweetAlert2 in dark mode */
        body.dark-mode .swal2-popup {
            background: var(--modal-bg) !important;
            color: var(--text-primary) !important;
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
            .main-container { padding: 80px 15px 20px; }
            .modules-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .header-section { padding: 20px; }
            .module-card { min-height: 260px; }
        }

        @media (max-width: 480px) {
            .module-status { margin-top: 6px; }
            .module-icon { margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light nav-glass fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="login.php">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="40" class="mr-2">
                <span class="font-weight-bold">NIA Albay-Catanduanes IMO</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="views/dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" href="maintenance_page.php">
                            <i class="fas fa-tools mr-1"></i> Module Maintenance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="darkModeToggle" title="Switch to Dark Mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
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
                        <button type="button" class="btn btn-add-module mr-2" data-toggle="modal" data-target="#addModuleModal">
                            <i class="fas fa-plus mr-2"></i>Add Module
                        </button>
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
                                            // Original modules
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
                                            'File Management' => 'fa-folder',
                                            'ICT Equipment Inventory' => 'fa-laptop',

                                            // HR Management extras
                                            'Leave Request' => 'fa-newspaper',
                                            'Leave Types' => 'fa-arrow-circle-right',
                                            'Personal Locator Slip' => 'fa-location-arrow',
                                            'Slip Monitoring' => 'fa-tasks',
                                            'Applicant Databank' => 'fa-clipboard-check',
                                            'HR Leave Monitoring' => 'fa-clipboard-list',
                                            'Intern Databank' => 'fa-user-graduate',
                                            'Room Reservation' => 'fa-bed',
                                            'My ICT Equipment' => 'fa-laptop',

                                            // Document Management module
                                            'Document Dashboard' => 'fa-tachometer-alt',
                                            'Document Records' => 'fa-folder-open',
                                            'Document Types' => 'fa-tags',
                                            'Document Archive' => 'fa-archive',

                                            // IA Profiles / SAHUR module
                                            'IA Dashboard' => 'fa-tachometer-alt',
                                            'IA Profiles' => 'fa-list',
                                            'IA Reports' => 'fa-file-pdf',
                                            'IA Analytics' => 'fa-chart-line',

                                            // ICT Equipment module extras
                                            'Equipment Inventory' => 'fa-desktop',
                                            'ICT Assign/Return' => 'fa-user-tag',
                                            'ICT QR Scanner' => 'fa-qrcode',
                                            'ICT Maintenance Logs' => 'fa-tools',
                                            'ICT Categories' => 'fa-sitemap',

                                            // Queue Management module
                                            'Queue Display' => 'fa-tv',
                                            'Section/IMO Queue' => 'fa-building',
                                            'Visitor Registration' => 'fa-user-plus',
                                            'Queue Reports' => 'fa-chart-bar',
                                            'Visitor History' => 'fa-history',
                                            'Queue Settings' => 'fa-cog',
                                            'Section/Unit Counters' => 'fa-desktop',
                                            'Purpose Categories' => 'fa-tags',

                                            // Scrum Board module
                                            'Scrum Dashboard' => 'fa-tachometer-alt',
                                            'Projects Monitoring' => 'fa-project-diagram',
                                            'Teams' => 'fa-project-diagram',
                                            'My Projects' => 'fa-project-diagram',
                                            'My Tasks' => 'fa-tasks',
                                            'Scrum Calendar' => 'fa-calendar-alt',
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
                                    <div class="module-footer-actions">
                                        <form method="POST" class="delete-module-form">
                                            <input type="hidden" name="action" value="delete_module">
                                            <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                            <button type="submit" class="btn btn-delete-module"
                                                    data-module-name="<?= htmlspecialchars($module['module_name']) ?>">
                                                <i class="fas fa-trash-alt mr-1"></i>Delete
                                            </button>
                                        </form>
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

    <!-- Add Module Modal -->
    <div class="modal fade" id="addModuleModal" tabindex="-1" role="dialog" aria-labelledby="addModuleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_module">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModuleModalLabel">
                            <i class="fas fa-plus-circle mr-2"></i>Add New Module
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--modal-header-color);">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="module_name">Module Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="module_name" name="module_name"
                                   placeholder="e.g. Vehicle Tracking" required maxlength="150">
                        </div>
                        <div class="form-group mb-0">
                            <label for="module_description">Description</label>
                            <textarea class="form-control" id="module_description" name="module_description"
                                      rows="3" placeholder="Short description of what this module does"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-module">
                            <i class="fas fa-plus mr-1"></i>Add Module
                        </button>
                    </div>
                </form>
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
                confirmButtonColor: '#2a9863',
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

        // Confirm module deletion
        $('.delete-module-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const moduleName = form.find('.btn-delete-module').data('module-name');

            Swal.fire({
                title: 'Delete Module?',
                html: `This will permanently remove <strong>${moduleName}</strong> from the system. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e63946',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                backdrop: 'rgba(0,0,0,0.4)'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.off('submit').submit();
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

    // ===== DARK MODE SYSTEM (mirrors includes/mainheader.php) =====
    (function() {
        function applyMode(isDark) {
            if (isDark) {
                $('body').addClass('dark-mode');
                $('#darkModeIcon').removeClass('fa-moon').addClass('fa-sun');
                $('#darkModeToggle').attr('title', 'Switch to Light Mode');
            } else {
                $('body').removeClass('dark-mode');
                $('#darkModeIcon').removeClass('fa-sun').addClass('fa-moon');
                $('#darkModeToggle').attr('title', 'Switch to Dark Mode');
            }
            localStorage.setItem('darkMode', isDark ? '1' : '0');
        }

        $(document).ready(function() {
            const isDark = localStorage.getItem('darkMode') === '1';
            applyMode(isDark);

            $('#darkModeToggle').on('click', function(e) {
                e.preventDefault();
                const nowDark = !$('body').hasClass('dark-mode');
                applyMode(nowDark);
            });
        });
    })();
    </script>
</body>
</html>