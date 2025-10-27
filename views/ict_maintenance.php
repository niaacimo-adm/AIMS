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

// Initialize variables
$equipment_id = isset($_GET['equipment_id']) ? $_GET['equipment_id'] : null;
$equipment = null;
$maintenance_history = [];

// Get equipment details if equipment_id is provided
if ($equipment_id) {
    // Verify the equipment belongs to the current employee
    $equipment_query = "SELECT e.*, c.category_name 
                       FROM ict_equipment e 
                       LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id 
                       WHERE e.equipment_id = ? AND e.assigned_to = ?";
    $equipment_stmt = $db->prepare($equipment_query);
    $equipment_stmt->bind_param("ii", $equipment_id, $employee_id);
    $equipment_stmt->execute();
    $equipment_result = $equipment_stmt->get_result();
    $equipment = $equipment_result->fetch_assoc();

    if ($equipment) {
        // Get maintenance history for this equipment
        $maintenance_query = "SELECT m.*, e.first_name, e.last_name, m.assigned_technician
                            FROM ict_maintenance m 
                            LEFT JOIN employee e ON m.reported_by = e.emp_id 
                            WHERE m.equipment_id = ? 
                            ORDER BY m.report_date DESC";
        $maintenance_stmt = $db->prepare($maintenance_query);
        $maintenance_stmt->bind_param("i", $equipment_id);
        $maintenance_stmt->execute();
        $maintenance_result = $maintenance_stmt->get_result();
        $maintenance_history = $maintenance_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle maintenance request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_maintenance'])) {
    $issue_type = $_POST['issue_type'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $equipment_id = $_POST['equipment_id'];

    // Verify equipment belongs to employee
    $verify_query = "SELECT equipment_id FROM ict_equipment WHERE equipment_id = ? AND assigned_to = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bind_param("ii", $equipment_id, $employee_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows > 0) {
        $insert_query = "INSERT INTO ict_maintenance (equipment_id, issue_type, description, priority, reported_by, status) 
                        VALUES (?, ?, ?, ?, ?, 'Pending')";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bind_param("isssi", $equipment_id, $issue_type, $description, $priority, $employee_id);

        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Maintenance request submitted successfully!";
            header("Location: ict_maintenance.php?equipment_id=" . $equipment_id);
            exit();
        } else {
            $_SESSION['error_message'] = "Error submitting maintenance request. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid equipment selection.";
    }
}

// Get employee's assigned equipment for dropdown
$equipment_query = "SELECT equipment_id, equipment_name, asset_tag 
                   FROM ict_equipment 
                   WHERE assigned_to = ? 
                   ORDER BY equipment_name";
$equipment_stmt = $db->prepare($equipment_query);
$equipment_stmt->bind_param("i", $employee_id);
$equipment_stmt->execute();
$equipment_list = $equipment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Maintenance - NIA ACIMO</title>
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
                        <h1>ICT Maintenance</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item"><a href="ict_my_equipment.php">My Equipment</a></li>
                            <li class="breadcrumb-item active">Maintenance</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <!-- Success/Error Messages -->
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

                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">Submit Maintenance Request</h3>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="equipment_id">Select Equipment *</label>
                                                        <select class="form-control" id="equipment_id" name="equipment_id" required>
                                                            <option value="">-- Select Equipment --</option>
                                                            <?php foreach ($equipment_list as $eq): ?>
                                                                <option value="<?= $eq['equipment_id'] ?>" 
                                                                    <?= ($equipment_id == $eq['equipment_id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($eq['equipment_name'] . ' (' . $eq['asset_tag'] . ')') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="issue_type">Issue Type *</label>
                                                        <select class="form-control" id="issue_type" name="issue_type" required>
                                                            <option value="">-- Select Issue Type --</option>
                                                            <option value="Hardware">Hardware Issue</option>
                                                            <option value="Software">Software Issue</option>
                                                            <option value="Network">Network Issue</option>
                                                            <option value="Performance">Performance Issue</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="priority">Priority *</label>
                                                        <select class="form-control" id="priority" name="priority" required>
                                                            <option value="Low">Low</option>
                                                            <option value="Medium" selected>Medium</option>
                                                            <option value="High">High</option>
                                                            <option value="Critical">Critical</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="description">Issue Description *</label>
                                                <textarea class="form-control" id="description" name="description" rows="5" 
                                                        placeholder="Please describe the issue in detail..." required></textarea>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" name="submit_maintenance" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Submit Request
                                            </button>
                                            <button type="reset" class="btn btn-default">Clear</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-info" id="equipmentDetailsSection">
                                    <div class="card-header">
                                        <h3 class="card-title">Equipment Details</h3>
                                    </div>
                                    <div class="card-body" id="equipmentDetailsContent">
                                        <?php if ($equipment): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Equipment:</strong> <?= htmlspecialchars($equipment['equipment_name']) ?></p>
                                                    <p><strong>Category:</strong> <?= htmlspecialchars($equipment['category_name']) ?></p>
                                                    <p><strong>Asset Tag:</strong> <?= htmlspecialchars($equipment['asset_tag']) ?></p>
                                                    <p><strong>Serial No:</strong> <?= htmlspecialchars($equipment['serial_number']) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Brand/Model:</strong> <?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></p>
                                                    <p><strong>Condition:</strong> 
                                                        <span class="badge badge-<?= 
                                                            $equipment['condition'] == 'Excellent' ? 'success' : 
                                                            ($equipment['condition'] == 'Good' ? 'primary' : 
                                                            ($equipment['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                                                            <?= $equipment['condition'] ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Status:</strong> 
                                                        <span class="badge badge-<?= 
                                                            $equipment['status'] == 'Available' ? 'success' : 
                                                            ($equipment['status'] == 'Assigned' ? 'primary' : 
                                                            ($equipment['status'] == 'Under Maintenance' ? 'warning' : 
                                                            ($equipment['status'] == 'Retired' ? 'secondary' : 'danger'))) ?>">
                                                            <?= $equipment['status'] ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <h5><i class="icon fas fa-info"></i> No Equipment Selected</h5>
                                                Please select an equipment from the dropdown above to view details.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Maintenance History Section - Always Visible -->
                        <div class="card" id="maintenanceHistorySection">
                            <div class="card-header">
                                <h3 class="card-title">Maintenance History</h3>
                            </div>
                            <div class="card-body" id="maintenanceHistoryContent">
                                <?php if ($equipment && !empty($maintenance_history)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date Reported</th>
                                                    <th>Issue Type</th>
                                                    <th>Description</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Assigned Technician</th>
                                                    <th>Reported By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($maintenance_history as $record): ?>
                                                    <tr>
                                                        <td><?= date('M d, Y', strtotime($record['report_date'])) ?></td>
                                                        <td><?= htmlspecialchars($record['issue_type']) ?></td>
                                                        <td><?= htmlspecialchars($record['description']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= 
                                                                $record['priority'] == 'Low' ? 'secondary' : 
                                                                ($record['priority'] == 'Medium' ? 'info' : 
                                                                ($record['priority'] == 'High' ? 'warning' : 'danger')) ?>">
                                                                <?= $record['priority'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?= 
                                                                $record['status'] == 'Completed' ? 'success' : 
                                                                ($record['status'] == 'In Progress' ? 'primary' : 
                                                                ($record['status'] == 'Pending' ? 'warning' : 'secondary')) ?>">
                                                                <?= $record['status'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($record['assigned_technician'])): ?>
                                                                <?php 
                                                                // Get technician name if assigned
                                                                $tech_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
                                                                $tech_stmt = $db->prepare($tech_query);
                                                                $tech_stmt->bind_param("i", $record['assigned_technician']);
                                                                $tech_stmt->execute();
                                                                $tech_result = $tech_stmt->get_result();
                                                                $technician = $tech_result->fetch_assoc();
                                                                echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']);
                                                                ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not assigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php elseif ($equipment && empty($maintenance_history)): ?>
                                    <div class="alert alert-info">
                                        <h5><i class="icon fas fa-info"></i> No Maintenance History</h5>
                                        No maintenance requests have been submitted for this equipment.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <h5><i class="icon fas fa-info"></i> No Equipment Selected</h5>
                                        Please select an equipment from the dropdown above to view maintenance history.
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

    // Auto-select equipment when coming from My Equipment page
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('equipment_id')) {
        $('#equipment_id').val(urlParams.get('equipment_id'));
    }

    // Load equipment details when selection changes
    $('#equipment_id').on('change', function() {
        const equipmentId = $(this).val();
        if (equipmentId) {
            // Update URL without page reload
            const newUrl = window.location.pathname + '?equipment_id=' + equipmentId;
            window.history.replaceState({}, '', newUrl);
            
            // Load equipment details via AJAX
            loadEquipmentDetails(equipmentId);
            loadMaintenanceHistory(equipmentId);
        } else {
            // Clear details if no equipment selected
            $('#equipmentDetailsContent').html('<div class="alert alert-info"><h5><i class="icon fas fa-info"></i> No Equipment Selected</h5>Please select an equipment from the dropdown above to view details.</div>');
            $('#maintenanceHistoryContent').html('<div class="alert alert-info"><h5><i class="icon fas fa-info"></i> No Equipment Selected</h5>Please select an equipment from the dropdown above to view maintenance history.</div>');
        }
    });

    function loadEquipmentDetails(equipmentId) {
        $.ajax({
            url: 'get_equipment_details_html.php',
            type: 'GET',
            data: { equipment_id: equipmentId },
            success: function(response) {
                $('#equipmentDetailsContent').html(response);
            },
            error: function(xhr, status, error) {
                console.error('Error loading equipment details:', error);
                $('#equipmentDetailsContent').html('<div class="alert alert-danger">Error loading equipment details. Please try again.</div>');
            }
        });
    }

    function loadMaintenanceHistory(equipmentId) {
        $.ajax({
            url: 'get_maintenance_history.php',
            type: 'GET',
            data: { equipment_id: equipmentId },
            success: function(response) {
                $('#maintenanceHistoryContent').html(response);
            },
            error: function(xhr, status, error) {
                console.error('Error loading maintenance history:', error);
                $('#maintenanceHistoryContent').html('<div class="alert alert-danger">Error loading maintenance history. Please try again.</div>');
            }
        });
    }

    // Load initial data if equipment is selected
    const initialEquipmentId = $('#equipment_id').val();
    if (initialEquipmentId) {
        loadEquipmentDetails(initialEquipmentId);
        loadMaintenanceHistory(initialEquipmentId);
    }
});
</script>
</body>
</html>