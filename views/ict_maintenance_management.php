<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasPermission('manage_ict_maintenance')) {
    header('Location: ../unauthorized.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle AJAX requests for maintenance operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_maintenance_status':
            echo updateMaintenanceStatus($db);
            exit();
        case 'add_maintenance_note':
            echo addMaintenanceNote($db);
            exit();
        case 'assign_technician':
            echo assignTechnician($db);
            exit();
    }
}

function updateMaintenanceStatus($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $resolution_notes = $_POST['resolution_notes'] ?? '';
    
    if (empty($maintenance_id) || empty($status)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and status are required']);
    }
    
    if ($status === 'Completed') {
        $query = "UPDATE ict_maintenance SET status = ?, resolution_notes = ?, resolved_date = NOW() WHERE maintenance_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);
    } else {
        $query = "UPDATE ict_maintenance SET status = ?, resolution_notes = ? WHERE maintenance_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);
    }
    
    if ($stmt->execute()) {
        // Update equipment status if maintenance is completed
        if ($status === 'Completed') {
            $equipment_query = "UPDATE ict_equipment SET status = 'Available' WHERE equipment_id = (SELECT equipment_id FROM ict_maintenance WHERE maintenance_id = ?)";
            $equipment_stmt = $db->prepare($equipment_query);
            $equipment_stmt->bind_param("i", $maintenance_id);
            $equipment_stmt->execute();
        }
        
        return json_encode(['success' => true, 'message' => 'Maintenance status updated successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to update maintenance status: ' . $db->error]);
    }
}

function addMaintenanceNote($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $note = $_POST['note'] ?? '';
    $added_by = $_SESSION['emp_id'] ?? 0;
    
    if (empty($maintenance_id) || empty($note)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and note are required']);
    }
    
    $query = "INSERT INTO ict_maintenance_notes (maintenance_id, note, added_by) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("isi", $maintenance_id, $note, $added_by);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true, 'message' => 'Note added successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to add note: ' . $db->error]);
    }
}

function assignTechnician($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $technician_id = $_POST['technician_id'] ?? '';
    
    if (empty($maintenance_id) || empty($technician_id)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and technician are required']);
    }
    
    $query = "UPDATE ict_maintenance SET assigned_technician = ?, assigned_date = NOW(), status = 'In Progress' WHERE maintenance_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $technician_id, $maintenance_id);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true, 'message' => 'Technician assigned successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to assign technician: ' . $db->error]);
    }
}

// Get all maintenance requests for DataTables
$query = "SELECT m.maintenance_id,
                 e.equipment_name, e.asset_tag, e.serial_number,
                 c.category_name,
                 emp.first_name as reporter_first, emp.last_name as reporter_last,
                 tech.first_name as tech_first, tech.last_name as tech_last,
                 m.issue_type,
                 m.priority,
                 m.status,
                 m.report_date,
                 m.assigned_technician,
                 m.description
          FROM ict_maintenance m
          LEFT JOIN ict_equipment e ON m.equipment_id = e.equipment_id
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id
          LEFT JOIN employee emp ON m.reported_by = emp.emp_id
          LEFT JOIN employee tech ON m.assigned_technician = tech.emp_id
          ORDER BY m.report_date DESC";

$result = $db->query($query);
$maintenance_requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get technicians for assignment
$tech_query = "SELECT e.emp_id, e.first_name, e.last_name 
               FROM employee e 
               INNER JOIN users u ON e.emp_id = u.employee_id 
               WHERE u.role_id IN (1, 20)"; // Administrator (1) and Focal Person (ICT) (20)
$tech_result = $db->query($tech_query);
$technicians = $tech_result ? $tech_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Maintenance Management - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css"/>
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
                        <h1>ICT Maintenance Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item active">Maintenance Management</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Maintenance Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($maintenance_requests)): ?>
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> No Maintenance Requests</h5>
                                There are no maintenance requests in the system yet.
                            </div>
                        <?php else: ?>
                            <table id="maintenanceTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Equipment</th>
                                        <th>Issue Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Reported By</th>
                                        <th>Report Date</th>
                                        <th>Assigned Technician</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_requests as $request): ?>
                                        <tr>
                                            <td><?= $request['maintenance_id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($request['equipment_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($request['asset_tag']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($request['issue_type']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $request['priority'] == 'Low' ? 'secondary' : 
                                                    ($request['priority'] == 'Medium' ? 'info' : 
                                                    ($request['priority'] == 'High' ? 'warning' : 'danger')) ?>">
                                                    <?= $request['priority'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $request['status'] == 'Completed' ? 'success' : 
                                                    ($request['status'] == 'In Progress' ? 'primary' : 
                                                    ($request['status'] == 'Pending' ? 'warning' : 'secondary')) ?>">
                                                    <?= $request['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($request['reporter_first'] . ' ' . $request['reporter_last']) ?></td>
                                            <td><?= date('M d, Y', strtotime($request['report_date'])) ?></td>
                                            <td>
                                                <?php if ($request['assigned_technician']): ?>
                                                    <?= htmlspecialchars($request['tech_first'] . ' ' . $request['tech_last']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm view-maintenance" 
                                                        data-id="<?= $request['maintenance_id'] ?>"
                                                        data-toggle="tooltip" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($request['status'] !== 'Completed' && $request['status'] !== 'Cancelled'): ?>
                                                    <button class="btn btn-warning btn-sm assign-technician" 
                                                            data-id="<?= $request['maintenance_id'] ?>"
                                                            data-toggle="tooltip" title="Assign Technician">
                                                        <i class="fas fa-user-cog"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm update-status" 
                                                            data-id="<?= $request['maintenance_id'] ?>"
                                                            data-toggle="tooltip" title="Update Status">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- View Maintenance Modal -->
<div class="modal fade" id="viewMaintenanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Maintenance Request Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="maintenanceDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Assign Technician Modal -->
<div class="modal fade" id="assignTechnicianModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Technician</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignTechnicianForm">
                <input type="hidden" name="maintenance_id" id="assign_maintenance_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Technician</label>
                        <select name="technician_id" class="form-control" required>
                            <option value="">Select Technician</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['emp_id'] ?>">
                                    <?= htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Technician</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Maintenance Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="updateStatusForm">
                <input type="hidden" name="maintenance_id" id="status_maintenance_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="resolution_notes" class="form-control" rows="4" placeholder="Enter resolution details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable if there are records
    <?php if (!empty($maintenance_requests)): ?>
    $('#maintenanceTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "buttons": ['copy', 'csv', 'excel', 'pdf', 'print'],
        "order": [[0, 'desc']]
    }).buttons().container().appendTo('#maintenanceTable_wrapper .col-md-6:eq(0)');
    <?php endif; ?>

    // Set ICT theme
    const currentTheme = localStorage.getItem('currentTheme');
    if (currentTheme !== 'ict') {
        localStorage.setItem('currentTheme', 'ict');
    }
    document.cookie = 'current_module=ict; path=/; max-age=300';
    
    const theme = 'linear-gradient(135deg, #17a2b8, #138496)';
    $('.main-header').css('background', theme);
    $('#mainFooter').css('background', theme);
    
    $('.main-header').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');
    $('#mainFooter').removeClass('theme-admin theme-service theme-inventory theme-file').addClass('theme-ict');

    // View Maintenance Details
    $(document).on('click', '.view-maintenance', function() {
        const maintenanceId = $(this).data('id');
        
        $.ajax({
            url: 'get_maintenance_details.php',
            type: 'GET',
            data: { maintenance_id: maintenanceId },
            success: function(response) {
                $('#maintenanceDetails').html(response);
                $('#viewMaintenanceModal').modal('show');
            },
            error: function() {
                $('#maintenanceDetails').html('<div class="alert alert-danger">Error loading maintenance details. Please try again.</div>');
                $('#viewMaintenanceModal').modal('show');
            }
        });
    });

    // Assign Technician
    $(document).on('click', '.assign-technician', function() {
        const maintenanceId = $(this).data('id');
        $('#assign_maintenance_id').val(maintenanceId);
        $('#assignTechnicianModal').modal('show');
    });

    // Update Status
    $(document).on('click', '.update-status', function() {
        const maintenanceId = $(this).data('id');
        $('#status_maintenance_id').val(maintenanceId);
        $('#updateStatusModal').modal('show');
    });

    // Assign Technician Form
    $('#assignTechnicianForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'assign_technician');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#assignTechnicianModal').modal('hide');
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', result.message);
                }
            },
            error: function() {
                showAlert('error', 'Error assigning technician. Please try again.');
            }
        });
    });

    // Update Status Form
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_maintenance_status');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#updateStatusModal').modal('hide');
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', result.message);
                }
            },
            error: function() {
                showAlert('error', 'Error updating status. Please try again.');
            }
        });
    });

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `<div class="alert ${alertClass} alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            ${message}
        </div>`;
        $('.content-wrapper').prepend(alertHtml);
        setTimeout(() => $('.alert').alert('close'), 5000);
    }
});
</script>
</body>
</html>