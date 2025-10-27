<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <style>
        .theme-ict {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
        }
        .card-primary.ict-theme {
            border-top: 3px solid #17a2b8;
        }
        .card-primary.ict-theme .card-header {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
            color: white;
        }
        .bg-ict {
            background-color: #17a2b8 !important;
        }
        .text-ict {
            color: #17a2b8 !important;
        }
        .btn-ict {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        .btn-ict:hover {
            background-color: #138496;
            border-color: #138496;
            color: white;
        }
        .badge-ict {
            background-color: #17a2b8;
            color: white;
        }
        .widget-user-header.bg-ict {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
            color: white;
        }
        .nav-link.active {
            background-color: rgba(23, 162, 184, 0.2) !important;
            border-left: 3px solid #17a2b8 !important;
        }
        /* Fix for text visibility */
        .nav-link {
            color: #495057 !important;
        }
        .nav-link strong {
            color: #025967ff;
        }
        .widget-user-username {
            color: white !important;
            font-weight: bold;
        }
        .widget-user-desc {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        /* DataTable styling */
        .table-ict thead {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
            color: white;
        }
        .table-ict thead th {
            border-bottom: none;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #495057;
        }
        .page-item.active .page-link {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .page-link {
            color: #17a2b8;
        }
        /* View toggle buttons */
        .view-toggle-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 8px 15px;
            margin-right: 5px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .view-toggle-btn.active {
            background: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        .view-toggle-btn:hover {
            background: #138496;
            border-color: #138496;
            color: white;
        }
    </style>
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
                        <h1 style="color: #17a2b8;">My ICT Equipment</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php" style="color: #17a2b8;">ICT Inventory</a></li>
                            <li class="breadcrumb-item active" style="color: #138496;">My Equipment</li>
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
                        <div class="card card-primary ict-theme">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user-circle mr-2"></i>Employee Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-user mr-2 text-ict"></i>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                                        <p><strong><i class="fas fa-id-card mr-2 text-ict"></i>Employee ID:</strong> <span class="badge badge-ict"><?= $_SESSION['emp_id'] ?></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-laptop mr-2 text-ict"></i>Total Assigned Equipment:</strong> <span class="badge badge-ict"><?= count($my_equipment) ?></span></p>
                                        <p><strong><i class="fas fa-building mr-2 text-ict"></i>Department:</strong> <span class="badge badge-info"><?= $_SESSION['role_name'] ?? 'N/A' ?></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- View Toggle -->
                        <div class="card ict-theme mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-end">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="view-toggle-btn active" id="cardViewBtn">
                                            <i class="fas fa-th-large mr-1"></i> Card View
                                        </button>
                                        <button type="button" class="view-toggle-btn" id="tableViewBtn">
                                            <i class="fas fa-table mr-1"></i> Table View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card View -->
                        <div id="cardView">
                            <div class="card ict-theme">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-desktop mr-2"></i>Assigned Equipment</h3>
                                    <div class="card-tools">
                                        <span class="badge badge-ict"><?= count($my_equipment) ?> items</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($my_equipment)): ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-laptop fa-3x mb-3" style="color: #17a2b8;"></i>
                                            <h4><i class="icon fas fa-info"></i> No Equipment Assigned</h4>
                                            <p class="mb-0">You don't have any ICT equipment assigned to you currently.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($my_equipment as $equipment): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card card-widget widget-user-2 shadow-sm">
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
                                                                        <strong><i class="fas fa-tag mr-2 text-ict"></i>Asset Tag:</strong> 
                                                                        <span class="float-right badge badge-light"><?= htmlspecialchars($equipment['asset_tag']) ?></span>
                                                                    </span>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <span class="nav-link">
                                                                        <strong><i class="fas fa-barcode mr-2 text-ict"></i>Serial No:</strong> 
                                                                        <span class="float-right"><strong class="mr-2 text-ict"><?= htmlspecialchars($equipment['serial_number']) ?></strong></span>
                                                                    </span>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <span class="nav-link">
                                                                        <strong><i class="fas fa-cube mr-2 text-ict"></i>Brand/Model:</strong> 
                                                                        <span class="float-right"><strong class="mr-2 text-ict"><?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></strong></span>
                                                                    </span>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <span class="nav-link">
                                                                        <strong><i class="fas fa-calendar-alt mr-2 text-ict"></i>Assigned Date:</strong> 
                                                                        <span class="float-right"><strong class="mr-2 text-ict"><?= date('M d, Y', strtotime($equipment['assigned_date'])) ?></strong></span>
                                                                    </span>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <span class="nav-link">
                                                                        <strong><i class="fas fa-heartbeat mr-2 text-ict"></i>Condition:</strong> 
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
                                                                        <strong><i class="fas fa-list-alt mr-2 text-ict"></i>Specifications:</strong> 
                                                                        <span class="float-right">
                                                                            <button class="btn btn-sm btn-ict" data-toggle="modal" data-target="#specsModal<?= $equipment['equipment_id'] ?>">
                                                                                <i class="fas fa-eye"></i> View
                                                                            </button>
                                                                        </span>
                                                                    </span>
                                                                </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                        <div class="card-footer">
                                                            <div class="row">
                                                                <div class="col-sm-12">
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
                                                                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                                                                    <h4 class="modal-title"><i class="fas fa-list-alt mr-2"></i>Specifications - <?= htmlspecialchars($equipment['equipment_name']) ?></h4>
                                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <pre style="white-space: pre-wrap; font-family: inherit; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8;"><?= htmlspecialchars($equipment['specifications']) ?></pre>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-ict" data-dismiss="modal">Close</button>
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

                        <!-- Table View -->
                        <div id="tableView" style="display: none;">
                            <div class="card ict-theme">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-table mr-2"></i>Assigned Equipment - Table View</h3>
                                    <div class="card-tools">
                                        <span class="badge badge-ict"><?= count($my_equipment) ?> items</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($my_equipment)): ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-laptop fa-3x mb-3" style="color: #17a2b8;"></i>
                                            <h4><i class="icon fas fa-info"></i> No Equipment Assigned</h4>
                                            <p class="mb-0">You don't have any ICT equipment assigned to you currently.</p>
                                        </div>
                                    <?php else: ?>
                                        <table id="equipmentTable" class="table table-bordered table-striped table-hover table-ict">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Equipment Name</th>
                                                    <th>Category</th>
                                                    <th>Asset Tag</th>
                                                    <th>Serial Number</th>
                                                    <th>Brand/Model</th>
                                                    <th>Assigned Date</th>
                                                    <th>Condition</th>
                                                    <th>Specifications</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($my_equipment as $index => $equipment): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($equipment['equipment_name']) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($equipment['category_name']) ?></td>
                                                    <td>
                                                        <span class="badge badge-light"><?= htmlspecialchars($equipment['asset_tag']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($equipment['serial_number']) ?></td>
                                                    <td><?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></td>
                                                    <td><?= date('M d, Y', strtotime($equipment['assigned_date'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= 
                                                            $equipment['condition'] == 'Excellent' ? 'success' : 
                                                            ($equipment['condition'] == 'Good' ? 'primary' : 
                                                            ($equipment['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                                                            <?= $equipment['condition'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($equipment['specifications'])): ?>
                                                            <button class="btn btn-sm btn-ict" data-toggle="modal" data-target="#tableSpecsModal<?= $equipment['equipment_id'] ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="ict_maintenance.php?equipment_id=<?= $equipment['equipment_id'] ?>" class="btn btn-warning btn-sm" title="Maintenance">
                                                            <i class="fas fa-tools"></i>
                                                        </a>
                                                    </td>
                                                </tr>

                                                <!-- Table Specifications Modal -->
                                                <?php if (!empty($equipment['specifications'])): ?>
                                                <div class="modal fade" id="tableSpecsModal<?= $equipment['equipment_id'] ?>">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                                                                <h4 class="modal-title"><i class="fas fa-list-alt mr-2"></i>Specifications - <?= htmlspecialchars($equipment['equipment_name']) ?></h4>
                                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre style="white-space: pre-wrap; font-family: inherit; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8;"><?= htmlspecialchars($equipment['specifications']) ?></pre>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-ict" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
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
<!-- DataTables JS -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../plugins/jszip/jszip.min.js"></script>
<script src="../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<!-- ICT Theme Script -->
<script>
$(document).ready(function() {
    // Set and maintain ICT theme
    const currentTheme = localStorage.getItem('currentTheme');
    if (currentTheme !== 'ict') {
        localStorage.setItem('currentTheme', 'ict');
    }
    document.cookie = 'current_module=ict; path=/; max-age=300';
    
    // Apply ICT theme colors
    const theme = 'linear-gradient(135deg, #17a2b8, #138496)';
    $('.main-header').css('background', theme);
    $('#mainFooter').css('background', theme);
    
    // Update theme classes
    $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    
    // Add ICT styling to page elements
    $('.content-header h1').css('color', '#17a2b8');
    $('.breadcrumb-item a').css('color', '#17a2b8');
    $('.breadcrumb-item.active').css('color', '#138496');
    
    // Initialize DataTable
    $('#equipmentTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "language": {
            "search": "Search equipment:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ items",
            "infoEmpty": "Showing 0 to 0 of 0 items",
            "infoFiltered": "(filtered from _MAX_ total items)",
            "paginate": {
                "previous": "Previous",
                "next": "Next"
            }
        },
        "columnDefs": [
            { "orderable": false, "targets": [0, 8, 9] }, // Disable sorting for #, Specifications, and Actions columns
            { "searchable": false, "targets": [0, 8, 9] } // Disable searching for #, Specifications, and Actions columns
        ],
        "order": [[1, 'asc']] // Default sort by Equipment Name
    });

    // View toggle functionality
    $('#cardViewBtn').on('click', function() {
        $('#cardView').show();
        $('#tableView').hide();
        $(this).addClass('active');
        $('#tableViewBtn').removeClass('active');
    });

    $('#tableViewBtn').on('click', function() {
        $('#cardView').hide();
        $('#tableView').show();
        $(this).addClass('active');
        $('#cardViewBtn').removeClass('active');
        // Redraw DataTable to ensure proper rendering
        $('#equipmentTable').DataTable().draw();
    });

    console.log('ICT theme and DataTable applied to My Equipment page');
});
</script>
</body>
</html>