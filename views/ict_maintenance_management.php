<?php
// Start output buffering at the very beginning
if (ob_get_level() == 0) {
    ob_start();
}

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Enable error reporting for debugging (remove in production)
// ini_set('display_errors', 0);
// error_reporting(0);

if (!hasPermission('manage_ict_maintenance')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Clean all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    } else {
        // Clean buffers before redirect
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Location: ../unauthorized.php');
        exit();
    }
}

$database = new Database();
$db = $database->getConnection();

// Check database connection
if ($db->connect_error) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
        exit();
    }
    // Clean buffers before dying
    while (ob_get_level()) {
        ob_end_clean();
    }
    die('Database connection failed');
}

// Handle AJAX requests for maintenance operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean all output buffers completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header
    header('Content-Type: application/json');
    
    try {
        $response = '';
        switch ($_POST['action']) {
            case 'update_maintenance_status':
                $response = updateMaintenanceStatus($db);
                break;
            case 'add_maintenance_note':
                $response = addMaintenanceNote($db);
                break;
            case 'assign_technician':
                $response = assignTechnician($db);
                break;
            case 'delete_maintenance':
                $response = deleteMaintenance($db);
                break;
            default:
                $response = json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
        
        // Ensure response is valid JSON
        if ($response === false || $response === null) {
            $response = json_encode(['success' => false, 'message' => 'Invalid response from server function']);
        }
        
        echo $response;
        
    } catch (Exception $e) {
        // Ensure clean output before error response
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    
    exit();
}

function updateMaintenanceStatus($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $resolution_notes = $_POST['resolution_notes'] ?? '';
    
    if (empty($maintenance_id) || empty($status)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and status are required']);
    }
    
    try {
        if ($status === 'Completed') {
            $query = "UPDATE ict_maintenance SET status = ?, resolution_notes = ?, resolved_date = NOW() WHERE maintenance_id = ?";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                return json_encode(['success' => false, 'message' => 'Database prepare error: ' . $db->error]);
            }
            $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);
        } else {
            $query = "UPDATE ict_maintenance SET status = ?, resolution_notes = ? WHERE maintenance_id = ?";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                return json_encode(['success' => false, 'message' => 'Database prepare error: ' . $db->error]);
            }
            $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);
        }
        
        if ($stmt->execute()) {
            // Update equipment status if maintenance is completed
            if ($status === 'Completed') {
                $equipment_query = "UPDATE ict_equipment SET status = 'Available' WHERE equipment_id = (SELECT equipment_id FROM ict_maintenance WHERE maintenance_id = ?)";
                $equipment_stmt = $db->prepare($equipment_query);
                if ($equipment_stmt) {
                    $equipment_stmt->bind_param("i", $maintenance_id);
                    $equipment_stmt->execute();
                }
            }
            
            return json_encode(['success' => true, 'message' => 'Maintenance status updated successfully']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to update maintenance status: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        return json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
    }
}

function addMaintenanceNote($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $note = $_POST['note'] ?? '';
    $added_by = $_SESSION['emp_id'] ?? 0;
    
    if (empty($maintenance_id) || empty($note)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and note are required']);
    }
    
    try {
        $query = "INSERT INTO ict_maintenance_notes (maintenance_id, note, added_by) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        if (!$stmt) {
            return json_encode(['success' => false, 'message' => 'Database prepare error: ' . $db->error]);
        }
        $stmt->bind_param("isi", $maintenance_id, $note, $added_by);
        
        if ($stmt->execute()) {
            return json_encode(['success' => true, 'message' => 'Note added successfully']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to add note: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        return json_encode(['success' => false, 'message' => 'Error adding note: ' . $e->getMessage()]);
    }
}

function assignTechnician($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    $technician_id = $_POST['technician_id'] ?? '';
    
    if (empty($maintenance_id) || empty($technician_id)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID and technician are required']);
    }
    
    try {
        $query = "UPDATE ict_maintenance SET assigned_technician = ?, assigned_date = NOW(), status = 'In Progress' WHERE maintenance_id = ?";
        $stmt = $db->prepare($query);
        if (!$stmt) {
            return json_encode(['success' => false, 'message' => 'Database prepare error: ' . $db->error]);
        }
        $stmt->bind_param("ii", $technician_id, $maintenance_id);
        
        if ($stmt->execute()) {
            return json_encode(['success' => true, 'message' => 'Technician assigned successfully']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to assign technician: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        return json_encode(['success' => false, 'message' => 'Error assigning technician: ' . $e->getMessage()]);
    }
}

function deleteMaintenance($db) {
    $maintenance_id = $_POST['maintenance_id'] ?? '';
    
    if (empty($maintenance_id)) {
        return json_encode(['success' => false, 'message' => 'Maintenance ID is required']);
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // First delete related notes
        $delete_notes_query = "DELETE FROM ict_maintenance_notes WHERE maintenance_id = ?";
        $delete_notes_stmt = $db->prepare($delete_notes_query);
        if (!$delete_notes_stmt) {
            throw new Exception('Failed to prepare notes deletion: ' . $db->error);
        }
        $delete_notes_stmt->bind_param("i", $maintenance_id);
        $delete_notes_stmt->execute();
        
        // Then delete the maintenance record
        $delete_query = "DELETE FROM ict_maintenance WHERE maintenance_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        if (!$delete_stmt) {
            throw new Exception('Failed to prepare maintenance deletion: ' . $db->error);
        }
        $delete_stmt->bind_param("i", $maintenance_id);
        
        if ($delete_stmt->execute()) {
            $db->commit();
            return json_encode(['success' => true, 'message' => 'Maintenance record deleted successfully']);
        } else {
            throw new Exception('Failed to execute deletion: ' . $delete_stmt->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        return json_encode(['success' => false, 'message' => 'Error deleting maintenance record: ' . $e->getMessage()]);
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
                 m.description,
                 m.resolution_notes,
                 m.resolved_date
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

// Clean any output buffers before HTML output
while (ob_get_level() > 1) {
    ob_end_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Maintenance Management - NIA ACIMO</title>
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
                                                <button class="btn btn-danger btn-sm delete-maintenance" 
                                                        data-id="<?= $request['maintenance_id'] ?>"
                                                        data-equipment="<?= htmlspecialchars($request['equipment_name']) ?>"
                                                        data-toggle="tooltip" title="Delete Request">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    // Check if SweetAlert2 is loaded
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 is not loaded properly');
        // Fallback to basic alerts
        window.showSweetAlert = function(icon, message) {
            alert(message);
        };
    } else {
        console.log('SweetAlert2 loaded successfully');
    }

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
            error: function(xhr, status, error) {
                console.error('Error loading maintenance details:', error);
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

    // Update Status with SweetAlert Confirmation
    $(document).on('click', '.update-status', function() {
        const maintenanceId = $(this).data('id');
        const equipmentName = $(this).closest('tr').find('td:eq(1) strong').text().trim();
        const currentStatus = $(this).closest('tr').find('td:eq(4) .badge').text().trim();
        
        // Set the maintenance ID and pre-select current status
        $('#status_maintenance_id').val(maintenanceId);
        
        // Pre-select the current status in the dropdown
        $('#updateStatusModal select[name="status"]').val(currentStatus);
        
        // Store equipment name for reference (optional)
        $('#updateStatusModal').data('equipment-name', equipmentName);
        
        $('#updateStatusModal').modal('show');
    });

    // Delete Maintenance
    $(document).on('click', '.delete-maintenance', function() {
        const maintenanceId = $(this).data('id');
        const equipmentName = $(this).data('equipment');
        
        Swal.fire({
            title: 'Are you sure?',
            html: `You are about to delete the maintenance request for <strong>"${equipmentName}"</strong>. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                deleteMaintenanceRequest(maintenanceId);
            }
        });
    });

    // Assign Technician Form
    $('#assignTechnicianForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'assign_technician');
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Assigning...').prop('disabled', true);
        
        $.ajax({
            url: 'ict_maintenance_management.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Assign Technician Response:', response);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        $('#assignTechnicianModal').modal('hide');
                        showSweetAlert('success', result.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showSweetAlert('error', result.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response was:', response);
                    showSweetAlert('error', 'Invalid response from server. Please check console for details.');
                }
                submitBtn.html(originalText).prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                showSweetAlert('error', 'Error assigning technician: ' + error);
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Update Status Form with SweetAlert on submission
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const newStatus = formData.get('status');
        const resolutionNotes = formData.get('resolution_notes');
        const equipmentName = $('#updateStatusModal').data('equipment-name') || 'the equipment';
        
        // Show confirmation SweetAlert before submitting
        Swal.fire({
            title: 'Confirm Status Update',
            html: `Are you sure you want to update the status for <strong>"${equipmentName}"</strong> to <span class="badge badge-info">${newStatus}</span>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#17a2b8',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Update Status',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Proceed with the AJAX request
                submitStatusUpdate(formData);
            }
        });
    });

 // Function to handle status update submission
    function submitStatusUpdate(formData) {
        // Show loading state
        const submitBtn = $('#updateStatusForm').find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
        
        $.ajax({
            url: 'ict_maintenance_management.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Update Status Response:', response);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        $('#updateStatusModal').modal('hide');
                        showSweetAlert('success', result.message, 'Status Updated Successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showSweetAlert('error', result.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response was:', response);
                    showSweetAlert('error', 'Invalid response from server. Please check console for details.');
                }
                submitBtn.html(originalText).prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                showSweetAlert('error', 'Error updating status: ' + error);
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    }
    // Delete Maintenance Function
    function deleteMaintenanceRequest(maintenanceId) {
        // Show loading SweetAlert
        Swal.fire({
            title: 'Deleting...',
            text: 'Please wait while we delete the maintenance request.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const formData = new FormData();
        formData.append('action', 'delete_maintenance');
        formData.append('maintenance_id', maintenanceId);
        
        $.ajax({
            url: 'ict_maintenance_management.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.close();
                console.log('Delete Response:', response);
                
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showSweetAlert('success', result.message, 'Deleted Successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showSweetAlert('error', result.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response was:', response);
                    showSweetAlert('error', 'Invalid response from server. Please check console for details.');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                showSweetAlert('error', 'Error deleting maintenance request: ' + error);
            }
        });
    }

    // SweetAlert function for success/error messages
    function showSweetAlert(icon, message, title = '') {
        // Fallback if SweetAlert2 is not loaded
        if (typeof Swal === 'undefined') {
            alert((title || (icon === 'success' ? 'Success!' : 'Error!')) + ': ' + message);
            return;
        }

        const alertTitle = title || (icon === 'success' ? 'Success!' : 'Error!');
        const confirmButtonColor = icon === 'success' ? '#28a745' : '#dc3545';
        
        Swal.fire({
            title: alertTitle,
            text: message,
            icon: icon,
            confirmButtonColor: confirmButtonColor,
            confirmButtonText: 'OK',
            timer: icon === 'success' ? 2000 : 4000,
            timerProgressBar: true,
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
    }

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Reset form and clear resolution notes when modal is closed
    $('#updateStatusModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        // Clear resolution notes specifically
        $(this).find('textarea[name="resolution_notes"]').val('');
    });

    // Clear resolution notes when status changes from "Completed" to something else
    $('#updateStatusModal select[name="status"]').on('change', function() {
        if ($(this).val() !== 'Completed') {
            $('#updateStatusModal textarea[name="resolution_notes"]').val('');
        }
    });

    $('#assignTechnicianModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
});
</script>
</body>
</html>