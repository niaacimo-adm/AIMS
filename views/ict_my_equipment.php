<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$employee_id = $_SESSION['emp_id'];

// Get employee's assigned equipment
$query = "SELECT e.*, c.category_name 
          FROM ict_equipment e 
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id 
          WHERE e.assigned_to = ? 
          ORDER BY e.assigned_date DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$my_equipment = $result->fetch_all(MYSQLI_ASSOC);

// Get employee info
$emp_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
$emp_stmt = $db->prepare($emp_query);
$emp_stmt->bind_param("i", $employee_id);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
$employee = $emp_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My ICT Equipment - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ict.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>My ICT Equipment</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item active">My Equipment</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <!-- Employee Info Card -->
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Employee Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                                        <p><strong>Employee ID:</strong> <?= $_SESSION['emp_id'] ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Assigned Equipment:</strong> <?= count($my_equipment) ?></p>
                                        <p><strong>Department:</strong> <?= $_SESSION['role_name'] ?? 'N/A' ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment List -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Assigned Equipment</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_equipment)): ?>
                                    <div class="alert alert-info">
                                        <h5><i class="icon fas fa-info"></i> No Equipment Assigned</h5>
                                        You don't have any ICT equipment assigned to you currently.
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($my_equipment as $equipment): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card card-widget widget-user-2">
                                                    <div class="widget-user-header bg-<?= 
                                                        $equipment['condition'] == 'Excellent' ? 'success' : 
                                                        ($equipment['condition'] == 'Good' ? 'primary' : 
                                                        ($equipment['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                                                        <div class="widget-user-image">
                                                            <i class="fas fa-laptop fa-2x"></i>
                                                        </div>
                                                        <h3 class="widget-user-username"><?= htmlspecialchars($equipment['equipment_name']) ?></h3>
                                                        <h5 class="widget-user-desc"><?= htmlspecialchars($equipment['category_name']) ?></h5>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <ul class="nav flex-column">
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Asset Tag:</strong> 
                                                                    <span class="float-right"><?= htmlspecialchars($equipment['asset_tag']) ?></span>
                                                                </span>
                                                            </li>
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Serial No:</strong> 
                                                                    <span class="float-right"><?= htmlspecialchars($equipment['serial_number']) ?></span>
                                                                </span>
                                                            </li>
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Brand/Model:</strong> 
                                                                    <span class="float-right"><?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></span>
                                                                </span>
                                                            </li>
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Assigned Date:</strong> 
                                                                    <span class="float-right"><?= date('M d, Y', strtotime($equipment['assigned_date'])) ?></span>
                                                                </span>
                                                            </li>
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Condition:</strong> 
                                                                    <span class="float-right badge badge-<?= 
                                                                        $equipment['condition'] == 'Excellent' ? 'success' : 
                                                                        ($equipment['condition'] == 'Good' ? 'primary' : 
                                                                        ($equipment['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                                                                        <?= $equipment['condition'] ?>
                                                                    </span>
                                                                </span>
                                                            </li>
                                                            <?php if (!empty($equipment['specifications'])): ?>
                                                            <li class="nav-item">
                                                                <span class="nav-link">
                                                                    <strong>Specifications:</strong> 
                                                                    <span class="float-right">
                                                                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#specsModal<?= $equipment['equipment_id'] ?>">
                                                                            View
                                                                        </button>
                                                                    </span>
                                                                </span>
                                                            </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    <div class="card-footer">
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <a href="ict_equipment_view.php?id=<?= $equipment['equipment_id'] ?>" class="btn btn-primary btn-sm btn-block">
                                                                    <i class="fas fa-eye"></i> Details
                                                                </a>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <a href="ict_maintenance.php?equipment_id=<?= $equipment['equipment_id'] ?>" class="btn btn-warning btn-sm btn-block">
                                                                    <i class="fas fa-tools"></i> Maintenance
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Specifications Modal -->
                                                <?php if (!empty($equipment['specifications'])): ?>
                                                <div class="modal fade" id="specsModal<?= $equipment['equipment_id'] ?>">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">Specifications - <?= htmlspecialchars($equipment['equipment_name']) ?></h4>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($equipment['specifications']) ?></pre>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<!-- Add this to ICT pages -->
<script>
$(document).ready(function() {
    // Set and maintain ICT theme
    const currentTheme = localStorage.getItem('currentTheme');
    if (currentTheme !== 'ict') {
        localStorage.setItem('currentTheme', 'ict');
    }
    document.cookie = 'current_module=ict; path=/; max-age=300';
    
    // Apply theme immediately
    const theme = 'linear-gradient(135deg, #17a2b8, #138496)';
    $('.main-header').css('background', theme);
    $('#mainFooter').css('background', theme);
    
    // Update theme classes
    $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
});
</script>
</body>
</html>