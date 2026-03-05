<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
$message = $_GET['message'] ?? '';
$message_type = $_GET['message_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_equipment'])) {
        $result = addEquipment($db);
        if ($result['success']) {
            header('Location: ict_equipment.php?message=' . urlencode($result['message']) . '&message_type=success');
            exit;
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
    elseif (isset($_POST['edit_equipment'])) {
        $result = editEquipment($db);
        if ($result['success']) {
            header('Location: ict_equipment.php?message=' . urlencode($result['message']) . '&message_type=success');
            exit;
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
    elseif (isset($_POST['delete_equipment'])) {
        $result = deleteEquipment($db);
        if ($result['success']) {
            header('Location: ict_equipment.php?message=' . urlencode($result['message']) . '&message_type=success');
            exit;
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
    elseif (isset($_POST['assign_equipment'])) {
        $result = assignEquipment($db);
        if ($result['success']) {
            header('Location: ict_equipment.php?message=' . urlencode($result['message']) . '&message_type=success');
            exit;
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
    elseif (isset($_POST['unassign_equipment'])) {
        $result = unassignEquipment($db);
        if ($result['success']) {
            header('Location: ict_equipment.php?message=' . urlencode($result['message']) . '&message_type=success');
            exit;
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
}

function addEquipment($db) {
    if (!hasPermission('manage_ict_equipment')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $asset_tag = $_POST['asset_tag'] ?? '';
    $equipment_name = $_POST['equipment_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $condition = $_POST['condition'] ?? 'Good';
    $status = $_POST['status'] ?? 'Available';
    $created_by = $_SESSION['emp_id'] ?? 0; // Get the logged-in user's ID
    
    // Validate required fields
    if (empty($asset_tag) || empty($equipment_name) || empty($category_id) || empty($serial_number)) {
        return ['success' => false, 'message' => 'All required fields must be filled'];
    }
    
    // Check if asset tag or serial number already exists
    $check_query = "SELECT equipment_id FROM ict_equipment WHERE asset_tag = ? OR serial_number = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("ss", $asset_tag, $serial_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return ['success' => false, 'message' => 'Asset tag or serial number already exists'];
    }
    
    // Check if created_by employee exists
    $check_emp_query = "SELECT emp_id FROM employee WHERE emp_id = ?";
    $check_emp_stmt = $db->prepare($check_emp_query);
    $check_emp_stmt->bind_param("i", $created_by);
    $check_emp_stmt->execute();
    $check_emp_result = $check_emp_stmt->get_result();
    
    if ($check_emp_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid user session. Please log in again.'];
    }
    
    // Updated query without the problematic fields
    $query = "INSERT INTO ict_equipment (asset_tag, equipment_name, category_id, brand, model, serial_number, specifications, `condition`, status, created_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ssissssssi", $asset_tag, $equipment_name, $category_id, $brand, $model, $serial_number, $specifications, $condition, $status, $created_by);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Equipment added successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to add equipment: ' . $db->error];
    }
}

function editEquipment($db) {
    if (!hasPermission('manage_ict_equipment')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $equipment_id = $_POST['equipment_id'] ?? '';
    $asset_tag = $_POST['asset_tag'] ?? '';
    $equipment_name = $_POST['equipment_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $condition = $_POST['condition'] ?? 'Good';
    $status = $_POST['status'] ?? 'Available';
    
    // Validate required fields
    if (empty($asset_tag) || empty($equipment_name) || empty($category_id) || empty($serial_number)) {
        return ['success' => false, 'message' => 'All required fields must be filled'];
    }
    
    // Check if asset tag or serial number already exists (excluding current equipment)
    $check_query = "SELECT equipment_id FROM ict_equipment WHERE (asset_tag = ? OR serial_number = ?) AND equipment_id != ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("ssi", $asset_tag, $serial_number, $equipment_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return ['success' => false, 'message' => 'Asset tag or serial number already exists'];
    }
    
    // Updated query without the problematic fields
    $query = "UPDATE ict_equipment SET asset_tag = ?, equipment_name = ?, category_id = ?, brand = ?, model = ?, serial_number = ?, specifications = ?, `condition` = ?, status = ? WHERE equipment_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ssissssssi", $asset_tag, $equipment_name, $category_id, $brand, $model, $serial_number, $specifications, $condition, $status, $equipment_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Equipment updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update equipment: ' . $db->error];
    }
}

function deleteEquipment($db) {
    if (!hasPermission('manage_ict_equipment')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $equipment_id = $_POST['equipment_id'] ?? '';
    
    if (empty($equipment_id)) {
        return ['success' => false, 'message' => 'Equipment ID is required'];
    }
    
    // Check if equipment exists
    $check_query = "SELECT equipment_name, asset_tag FROM ict_equipment WHERE equipment_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("i", $equipment_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Equipment not found'];
    }
    
    $equipment = $check_result->fetch_assoc();
    
    // First, unassign the equipment if it's assigned
    $unassign_query = "UPDATE ict_equipment SET assigned_to = NULL, status = 'Available', assigned_date = NULL WHERE equipment_id = ?";
    $unassign_stmt = $db->prepare($unassign_query);
    $unassign_stmt->bind_param("i", $equipment_id);
    $unassign_stmt->execute();
    
    // Then delete the equipment
    $query = "DELETE FROM ict_equipment WHERE equipment_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $equipment_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Equipment deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete equipment: ' . $db->error];
    }
}

// Rest of your code remains the same...
// Handle filters and get equipment list
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT e.*, c.category_name, emp.first_name, emp.last_name, emp.picture 
          FROM ict_equipment e 
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id 
          LEFT JOIN employee emp ON e.assigned_to = emp.emp_id 
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($category_filter)) {
    $query .= " AND e.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($search)) {
    $query .= " AND (e.asset_tag LIKE ? OR e.serial_number LIKE ? OR e.equipment_name LIKE ? OR e.brand LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$equipment = $result->fetch_all(MYSQLI_ASSOC);

// Get categories for filter and forms
$categories_result = $db->query("SELECT * FROM ict_equipment_categories ORDER BY category_name");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

function assignEquipment($db) {
    if (!hasPermission('manage_ict_equipment')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $equipment_id = $_POST['equipment_id'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $assignment_notes = $_POST['assignment_notes'] ?? '';
    
    if (empty($equipment_id) || empty($assigned_to)) {
        return ['success' => false, 'message' => 'Equipment ID and employee are required'];
    }
    
    // Check if equipment exists and get current status
    $check_equipment_query = "SELECT equipment_id, status, asset_tag FROM ict_equipment WHERE equipment_id = ?";
    $check_equipment_stmt = $db->prepare($check_equipment_query);
    $check_equipment_stmt->bind_param("i", $equipment_id);
    $check_equipment_stmt->execute();
    $check_equipment_result = $check_equipment_stmt->get_result();
    
    if ($check_equipment_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Equipment not found'];
    }
    
    $equipment = $check_equipment_result->fetch_assoc();
    
    // Allow assignment only if status is Available
    if ($equipment['status'] !== 'Available') {
        return ['success' => false, 'message' => "Equipment is not available for assignment. Current status: {$equipment['status']}"];
    }
    
    // Check if employee exists
    $check_emp_query = "SELECT emp_id, first_name, last_name FROM employee WHERE emp_id = ?";
    $check_emp_stmt = $db->prepare($check_emp_query);
    $check_emp_stmt->bind_param("i", $assigned_to);
    $check_emp_stmt->execute();
    $check_emp_result = $check_emp_stmt->get_result();
    
    if ($check_emp_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Employee not found'];
    }
    
    // Update equipment assignment
    $query = "UPDATE ict_equipment SET assigned_to = ?, status = 'Assigned', assigned_date = NOW() WHERE equipment_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $assigned_to, $equipment_id);
    
    if ($stmt->execute()) {
        // Log the assignment (with error handling for missing table)
        try {
            $log_query = "INSERT INTO ict_equipment_logs (equipment_id, action, action_by, notes) VALUES (?, 'Assigned', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            if ($log_stmt) {
                $log_stmt->bind_param("iis", $equipment_id, $_SESSION['emp_id'], $assignment_notes);
                $log_stmt->execute();
            }
        } catch (Exception $e) {
            // Log table doesn't exist, but continue with the assignment
            error_log("ICT Equipment Logs table not found: " . $e->getMessage());
        }
        
        return ['success' => true, 'message' => 'Equipment assigned successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to assign equipment: ' . $db->error];
    }
}

function unassignEquipment($db) {
    if (!hasPermission('manage_ict_equipment')) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $equipment_id = $_POST['equipment_id'] ?? '';
    
    if (empty($equipment_id)) {
        return ['success' => false, 'message' => 'Equipment ID is required'];
    }
    
    // Check if equipment exists and is assigned
    $check_equipment_query = "SELECT equipment_id, status, assigned_to FROM ict_equipment WHERE equipment_id = ?";
    $check_equipment_stmt = $db->prepare($check_equipment_query);
    $check_equipment_stmt->bind_param("i", $equipment_id);
    $check_equipment_stmt->execute();
    $check_equipment_result = $check_equipment_stmt->get_result();
    
    if ($check_equipment_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Equipment not found'];
    }
    
    $equipment = $check_equipment_result->fetch_assoc();
    if ($equipment['status'] !== 'Assigned') {
        return ['success' => false, 'message' => 'Equipment is not currently assigned'];
    }
    
    // Get assigned employee info for logging
    $emp_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->bind_param("i", $equipment['assigned_to']);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $employee = $emp_result->fetch_assoc();
    
    // Update equipment to unassign
    $query = "UPDATE ict_equipment SET assigned_to = NULL, status = 'Available', assigned_date = NULL WHERE equipment_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $equipment_id);
    
    if ($stmt->execute()) {
        // Log the unassignment
try {
    $employee_name = $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'Unknown';
    $log_notes = "Unassigned from " . $employee_name;

    $log_query = "INSERT INTO ict_equipment_logs (equipment_id, action, action_by, notes) VALUES (?, 'Unassigned', ?, ?)";
    $log_stmt = $db->prepare($log_query);
    if ($log_stmt) {
        $log_stmt->bind_param("iis", $equipment_id, $_SESSION['emp_id'], $log_notes);
        $log_stmt->execute();
    }
} catch (Exception $e) {
    // Log table doesn't exist, but continue with the unassignment
    error_log("ICT Equipment Logs table not found: " . $e->getMessage());
}
        
        return ['success' => true, 'message' => 'Equipment unassigned successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to unassign equipment: ' . $db->error];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Equipment - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* Ensure buttons are properly styled and clickable */
        .btn-sm {
            margin: 2px;
        }

        .edit-equipment, 
        .assign-equipment, 
        .unassign-equipment {
            cursor: pointer;
        }

        /* Ensure modals are properly positioned */
        .modal {
            z-index: 1050;
        }

        .modal-backdrop {
            z-index: 1040;
        }
        .specifications-content {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .table-borderless td {
            border: none !important;
            padding: 4px 8px;
        }

        .table-borderless td:first-child {
            width: 40%;
            font-weight: 500;
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
                        <h1>ICT Equipment</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item active">Equipment</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                                        <option value="Assigned" <?= $status_filter == 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                                        <option value="Under Maintenance" <?= $status_filter == 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                        <option value="Retired" <?= $status_filter == 'Retired' ? 'selected' : '' ?>>Retired</option>
                                        <option value="Lost" <?= $status_filter == 'Lost' ? 'selected' : '' ?>>Lost</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category" class="form-control">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>" <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by asset tag, serial, name..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Equipment List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Equipment List</h3>
                        <?php if (hasPermission('manage_ict_equipment')): ?>
                        <div class="card-tools">
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addEquipmentModal">
                                <i class="fas fa-plus"></i> Add Equipment
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Equipment</th>
                                        <th>Category</th>
                                        <th>Serial Number</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Condition</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($equipment)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No equipment found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($equipment as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['asset_tag']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['equipment_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($item['category_name']) ?></td>
                                                <td><?= htmlspecialchars($item['serial_number']) ?></td>
                                                <td>
                                                    <?php if ($item['assigned_to']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($item['picture'])): ?>
                                                                <img src="../dist/img/employees/<?= $item['picture'] ?>" class="img-circle elevation-2" width="30" height="30" style="margin-right: 10px;">
                                                            <?php else: ?>
                                                                <img src="../dist/img/nialogo.png" class="img-circle elevation-2" width="30" height="30" style="margin-right: 10px;">
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= 
                                                        $item['status'] == 'Available' ? 'success' : 
                                                        ($item['status'] == 'Assigned' ? 'primary' : 
                                                        ($item['status'] == 'Under Maintenance' ? 'warning' : 
                                                        ($item['status'] == 'Retired' ? 'secondary' : 'danger'))) ?>">
                                                        <?= $item['status'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= 
                                                        $item['condition'] == 'Excellent' ? 'success' : 
                                                        ($item['condition'] == 'Good' ? 'primary' : 
                                                        ($item['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                                                        <?= $item['condition'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-sm view-equipment" 
                                                            data-id="<?= $item['equipment_id'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Assignment buttons based on current status -->
                                                    <?php if ($item['status'] == 'Available'): ?>
                                                        <button type="button" class="btn btn-success btn-sm assign-equipment" 
                                                                data-id="<?= $item['equipment_id'] ?>"
                                                                data-equipment-name="<?= htmlspecialchars($item['equipment_name']) ?>"
                                                                data-asset-tag="<?= htmlspecialchars($item['asset_tag']) ?>">
                                                            <i class="fas fa-user-check"></i> Assign
                                                        </button>
                                                    <?php elseif ($item['status'] == 'Assigned'): ?>
                                                        <button type="button" class="btn btn-warning btn-sm unassign-equipment" 
                                                                data-id="<?= $item['equipment_id'] ?>"
                                                                data-equipment-name="<?= htmlspecialchars($item['equipment_name']) ?>"
                                                                data-asset-tag="<?= htmlspecialchars($item['asset_tag']) ?>"
                                                                data-assigned-to="<?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>">
                                                            <i class="fas fa-user-times"></i> Unassign
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-danger btn-sm delete-equipment" 
                                                            data-id="<?= $item['equipment_id'] ?>"
                                                            data-name="<?= htmlspecialchars($item['equipment_name']) ?>"
                                                            data-asset-tag="<?= htmlspecialchars($item['asset_tag']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<script>
    $(document).ready(function() {
        // View Equipment - Populate modal
        $(document).on('click', '.view-equipment', function() {
            const id = $(this).data('id');
            
            // Show loading state
            // $('#viewEquipmentModal .modal-body').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading equipment details...</div>');
            $('#viewEquipmentModal').modal('show');
            
            // AJAX call to get equipment details
            $.ajax({
                url: 'get_equipment_details.php',
                type: 'GET',
                data: { equipment_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const equipment = response.data;
                        
                        // Populate basic information
                        $('#view_asset_tag').text(equipment.asset_tag);
                        $('#view_equipment_name').text(equipment.equipment_name);
                        $('#view_category').text(equipment.category_name);
                        $('#view_brand').text(equipment.brand || 'N/A');
                        $('#view_model').text(equipment.model || 'N/A');
                        $('#view_serial_number').text(equipment.serial_number);
                        
                        // Populate status and condition with badges
                        $('#view_status').text(equipment.status).removeClass().addClass('badge badge-' + 
                            (equipment.status == 'Available' ? 'success' : 
                            equipment.status == 'Assigned' ? 'primary' : 
                            equipment.status == 'Under Maintenance' ? 'warning' : 
                            equipment.status == 'Retired' ? 'secondary' : 'danger'));
                        
                        $('#view_condition').text(equipment.condition).removeClass().addClass('badge badge-' + 
                            (equipment.condition == 'Excellent' ? 'success' : 
                            equipment.condition == 'Good' ? 'primary' : 
                            equipment.condition == 'Fair' ? 'warning' : 'danger'));
                        
                        // Populate assignment info
                        if (equipment.assigned_to) {
                            $('#view_assigned_to').html(
                                '<div class="d-flex align-items-center">' +
                                (equipment.picture ? '<img src="../dist/img/employees/' + equipment.picture + '" class="img-circle elevation-2" width="30" height="30" style="margin-right: 10px;">' : '') +
                                equipment.first_name + ' ' + equipment.last_name +
                                '</div>'
                            );
                            $('#view_assigned_date').text(equipment.assigned_date ? new Date(equipment.assigned_date).toLocaleDateString() : 'N/A');
                        } else {
                            $('#view_assigned_to').html('<span class="text-muted">Not assigned</span>');
                            $('#view_assigned_date').text('N/A');
                        }
                        
                        // Populate creation info
                        $('#view_created_by').text(equipment.creator_name || 'System');
                        $('#view_created_date').text(new Date(equipment.created_at).toLocaleDateString());
                        
                        // Populate specifications
                        if (equipment.specifications) {
                            $('#view_specifications').html(equipment.specifications.replace(/\n/g, '<br>'));
                        } else {
                            $('#view_specifications').html('<span class="text-muted">No specifications provided</span>');
                        }
                        
                        // Load equipment history
                        loadEquipmentHistory(id);
                        
                        // Set up edit button
                        $('#editFromViewBtn').off('click').on('click', function() {
                            $('#viewEquipmentModal').modal('hide');
                            
                            // Populate edit modal with current data
                            $('#edit_equipment_id').val(equipment.equipment_id);
                            $('#edit_asset_tag').val(equipment.asset_tag);
                            $('#edit_equipment_name').val(equipment.equipment_name);
                            $('#edit_category_id').val(equipment.category_id);
                            $('#edit_brand').val(equipment.brand || '');
                            $('#edit_model').val(equipment.model || '');
                            $('#edit_serial_number').val(equipment.serial_number);
                            $('#edit_specifications').val(equipment.specifications || '');
                            $('#edit_condition').val(equipment.condition);
                            $('#edit_status').val(equipment.status);
                            
                            $('#editEquipmentModal').modal('show');
                        });
                        
                    } else {
                        $('#viewEquipmentModal .modal-body').html(
                            '<div class="alert alert-danger">Error loading equipment details: ' + response.message + '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $('#viewEquipmentModal .modal-body').html(
                        '<div class="alert alert-danger">Error loading equipment details. Please try again.</div>'
                    );
                    console.error('Error loading equipment details:', error);
                }
            });
        });
        
        // Function to load equipment history
        function loadEquipmentHistory(equipmentId) {
            $.ajax({
                url: 'get_equipment_history.php',
                type: 'GET',
                data: { equipment_id: equipmentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let historyHtml = '<table class="table table-sm">' +
                            '<thead><tr><th>Date</th><th>Action</th><th>By</th><th>Notes</th></tr></thead>' +
                            '<tbody>';
                        
                        response.data.forEach(function(log) {
                            historyHtml += '<tr>' +
                                '<td>' + new Date(log.action_date).toLocaleString() + '</td>' +
                                '<td><span class="badge badge-' + 
                                    (log.action === 'Assigned' ? 'primary' : 
                                    log.action === 'Unassigned' ? 'warning' : 
                                    log.action === 'Created' ? 'success' : 
                                    log.action === 'Updated' ? 'info' : 'secondary') + '">' +
                                    log.action + '</span></td>' +
                                '<td>' + (log.action_by_name || 'System') + '</td>' +
                                '<td>' + (log.notes || 'N/A') + '</td>' +
                                '</tr>';
                        });
                        
                        historyHtml += '</tbody></table>';
                        $('#equipment_history').html(historyHtml);
                    } else {
                        $('#equipment_history').html('<p class="text-muted">No history available for this equipment.</p>');
                    }
                },
                error: function() {
                    $('#equipment_history').html('<p class="text-muted">Error loading history.</p>');
                }
            });
        }
        
        // Reset modal content when hidden
        $('#viewEquipmentModal').on('hidden.bs.modal', function() {
            $('#equipment_history').html('');
        });
    });

    $(document).ready(function() {
        // SweetAlert for messages
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const messageType = urlParams.get('message_type');
        
        if (message) {
            if (messageType === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: message,
                    confirmButtonColor: '#28a745',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    // Remove message parameters from URL without reloading
                    removeMessageParamsFromURL();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#dc3545'
                }).then(() => {
                    // Remove message parameters from URL without reloading
                    removeMessageParamsFromURL();
                });
            }
        }

        // Function to remove message parameters from URL
        function removeMessageParamsFromURL() {
            const url = new URL(window.location);
            url.searchParams.delete('message');
            url.searchParams.delete('message_type');
            window.history.replaceState({}, '', url.toString());
        }

        

        // View Equipment - Populate modal
        $(document).on('click', '.view-equipment', function() {
            const id = $(this).data('id');
            
            // Show loading state
            // $('#viewEquipmentModal .modal-body').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading equipment details...</div>');
            $('#viewEquipmentModal').modal('show');
            
            // AJAX call to get equipment details - Handle both JSON and HTML responses
            $.ajax({
                url: 'get_equipment_details.php',
                type: 'GET',
                data: { equipment_id: id },
                dataType: 'text', // Change to text to handle both JSON and HTML
                success: function(response) {
                    try {
                        // Try to parse as JSON first
                        const jsonResponse = JSON.parse(response);
                        if (jsonResponse.success) {
                            const equipment = jsonResponse.data;
                            populateEquipmentDetails(equipment);
                            loadEquipmentHistory(id);
                        } else {
                            $('#viewEquipmentModal .modal-body').html(
                                '<div class="alert alert-danger">Error loading equipment details: ' + jsonResponse.message + '</div>'
                            );
                        }
                    } catch (e) {
                        // If parsing fails, assume it's HTML and display directly
                        $('#viewEquipmentModal .modal-body').html(response);
                    }
                },
                error: function(xhr, status, error) {
                    $('#viewEquipmentModal .modal-body').html(
                        '<div class="alert alert-danger">Error loading equipment details. Please try again.</div>'
                    );
                    console.error('Error loading equipment details:', error);
                }
            });
        });

        // Function to populate equipment details from JSON data
        function populateEquipmentDetails(equipment) {
            // Populate basic information
            $('#view_asset_tag').text(equipment.asset_tag);
            $('#view_equipment_name').text(equipment.equipment_name);
            $('#view_category').text(equipment.category_name);
            $('#view_brand').text(equipment.brand || 'N/A');
            $('#view_model').text(equipment.model || 'N/A');
            $('#view_serial_number').text(equipment.serial_number);
            
            // Populate status and condition with badges
            $('#view_status').text(equipment.status).removeClass().addClass('badge badge-' + 
                (equipment.status == 'Available' ? 'success' : 
                equipment.status == 'Assigned' ? 'primary' : 
                equipment.status == 'Under Maintenance' ? 'warning' : 
                equipment.status == 'Retired' ? 'secondary' : 'danger'));
            
            $('#view_condition').text(equipment.condition).removeClass().addClass('badge badge-' + 
                (equipment.condition == 'Excellent' ? 'success' : 
                equipment.condition == 'Good' ? 'primary' : 
                equipment.condition == 'Fair' ? 'warning' : 'danger'));
            
            // Populate assignment info
            if (equipment.assigned_to) {
                $('#view_assigned_to').html(
                    '<div class="d-flex align-items-center">' +
                    (equipment.picture ? '<img src="../dist/img/employees/' + equipment.picture + '" class="img-circle elevation-2" width="30" height="30" style="margin-right: 10px;">' : '') +
                    equipment.first_name + ' ' + equipment.last_name +
                    '</div>'
                );
                $('#view_assigned_date').text(equipment.assigned_date ? new Date(equipment.assigned_date).toLocaleDateString() : 'N/A');
            } else {
                $('#view_assigned_to').html('<span class="text-muted">Not assigned</span>');
                $('#view_assigned_date').text('N/A');
            }
            
            // Populate creation info
            $('#view_created_by').text(equipment.creator_name || 'System');
            $('#view_created_date').text(new Date(equipment.created_at).toLocaleDateString());
            
            // Populate specifications
            if (equipment.specifications) {
                $('#view_specifications').html(equipment.specifications.replace(/\n/g, '<br>'));
            } else {
                $('#view_specifications').html('<span class="text-muted">No specifications provided</span>');
            }
            
            // Set up edit button
            $('#editFromViewBtn').off('click').on('click', function() {
                $('#viewEquipmentModal').modal('hide');
                
                // Populate edit modal with current data
                $('#edit_equipment_id').val(equipment.equipment_id);
                $('#edit_asset_tag').val(equipment.asset_tag);
                $('#edit_equipment_name').val(equipment.equipment_name);
                $('#edit_category_id').val(equipment.category_id);
                $('#edit_brand').val(equipment.brand || '');
                $('#edit_model').val(equipment.model || '');
                $('#edit_serial_number').val(equipment.serial_number);
                $('#edit_specifications').val(equipment.specifications || '');
                $('#edit_condition').val(equipment.condition);
                $('#edit_status').val(equipment.status);
                
                $('#editEquipmentModal').modal('show');
            });
        }

        // Function to load equipment history
        function loadEquipmentHistory(equipmentId) {
            $.ajax({
                url: 'get_equipment_history.php',
                type: 'GET',
                data: { equipment_id: equipmentId },
                dataType: 'text', // Change to text to handle both JSON and HTML
                success: function(response) {
                    try {
                        // Try to parse as JSON first
                        const jsonResponse = JSON.parse(response);
                        if (jsonResponse.success && jsonResponse.data.length > 0) {
                            let historyHtml = '<table class="table table-sm">' +
                                '<thead><tr><th>Date</th><th>Action</th><th>By</th><th>Notes</th></tr></thead>' +
                                '<tbody>';
                            
                            jsonResponse.data.forEach(function(log) {
                                historyHtml += '<tr>' +
                                    '<td>' + new Date(log.action_date).toLocaleString() + '</td>' +
                                    '<td><span class="badge badge-' + 
                                        (log.action === 'Assigned' ? 'primary' : 
                                        log.action === 'Unassigned' ? 'warning' : 
                                        log.action === 'Created' ? 'success' : 
                                        log.action === 'Updated' ? 'info' : 'secondary') + '">' +
                                        log.action + '</span></td>' +
                                    '<td>' + (log.action_by_name || 'System') + '</td>' +
                                    '<td>' + (log.notes || 'N/A') + '</td>' +
                                    '</tr>';
                            });
                            
                            historyHtml += '</tbody></table>';
                            $('#equipment_history').html(historyHtml);
                        } else {
                            $('#equipment_history').html('<p class="text-muted">No history available for this equipment.</p>');
                        }
                    } catch (e) {
                        // If parsing fails, assume it's HTML and display directly
                        $('#equipment_history').html(response);
                    }
                },
                error: function() {
                    $('#equipment_history').html('<p class="text-muted">Error loading history.</p>');
                }
            });
        }
        
        // Reset modal content when hidden
        $('#viewEquipmentModal').on('hidden.bs.modal', function() {
            $('#equipment_history').html('');
        });

        // Assign Equipment - Populate modal
        $(document).on('click', '.assign-equipment', function() {
            console.log('Assign button clicked');
            
            const id = $(this).data('id');
            const equipmentName = $(this).data('equipment-name');
            const assetTag = $(this).data('asset-tag');
            
            console.log('Assigning equipment ID:', id);
            
            $('#assign_equipment_id').val(id);
            $('#assign_employee').val('');
            $('#assignEquipmentModal .modal-title').text('Assign Equipment: ' + equipmentName + ' (' + assetTag + ')');
            
            $('#assignEquipmentModal').modal('show');
        });

        // Unassign Equipment - Populate modal
        $(document).on('click', '.unassign-equipment', function() {
            console.log('Unassign button clicked');
            
            const id = $(this).data('id');
            const equipmentName = $(this).data('equipment-name');
            const assetTag = $(this).data('asset-tag');
            const assignedTo = $(this).data('assigned-to');
            
            console.log('Unassigning equipment ID:', id);
            
            $('#unassign_equipment_id').val(id);
            $('#unassignEquipmentModal .modal-title').text('Unassign Equipment: ' + equipmentName + ' (' + assetTag + ')');
            $('#unassign_equipment_info').html(
                '<strong>Equipment:</strong> ' + equipmentName + ' (' + assetTag + ')<br>' +
                '<strong>Currently assigned to:</strong> ' + assignedTo
            );
            
            $('#unassignEquipmentModal').modal('show');
        });

        // Delete equipment with SweetAlert confirmation
        $(document).on('click', '.delete-equipment', function() {
            const equipmentId = $(this).data('id');
            const equipmentName = $(this).data('name');
            const assetTag = $(this).data('asset-tag');
            
            Swal.fire({
                title: 'Delete Equipment?',
                html: `<div class="text-left">
                    <p>You are about to delete <strong>${equipmentName}</strong> (${assetTag}).</p>
                    <div class="alert alert-warning text-sm">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Equipment can only be deleted if it has no maintenance records or history logs. 
                        Consider retiring the equipment instead.
                    </div>
                    <p>This action cannot be undone!</p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve) => {
                        // Create and submit delete form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        
                        const equipmentIdInput = document.createElement('input');
                        equipmentIdInput.type = 'hidden';
                        equipmentIdInput.name = 'equipment_id';
                        equipmentIdInput.value = equipmentId;
                        
                        const deleteInput = document.createElement('input');
                        deleteInput.type = 'hidden';
                        deleteInput.name = 'delete_equipment';
                        deleteInput.value = '1';
                        
                        form.appendChild(equipmentIdInput);
                        form.appendChild(deleteInput);
                        document.body.appendChild(form);
                        form.submit();
                        
                        resolve();
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // The form submission will handle the redirect
                }
            });
        });

        // Auto-refresh page after successful form submissions
        $('form').not('.no-refresh').on('submit', function(e) {
            const form = this;
            
            // Use setTimeout to allow the form to submit first
            setTimeout(function() {
                // Only refresh if we're still on the same page
                if (window.location.href.indexOf('ict_equipment.php') > -1) {
                    window.location.reload();
                }
            }, 1000);
        });
    });
</script>
<?php include '../includes/footer.php'; ?>

<!-- View Equipment Modal -->
<div class="modal fade" id="viewEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Equipment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info">
                                <h6 class="card-title">Basic Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Asset Tag:</strong></td>
                                        <td id="view_asset_tag"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Equipment Name:</strong></td>
                                        <td id="view_equipment_name"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Category:</strong></td>
                                        <td id="view_category"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Brand:</strong></td>
                                        <td id="view_brand"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Model:</strong></td>
                                        <td id="view_model"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Serial Number:</strong></td>
                                        <td id="view_serial_number"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info">
                                <h6 class="card-title">Status & Assignment</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span id="view_status" class="badge"></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Condition:</strong></td>
                                        <td><span id="view_condition" class="badge"></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Assigned To:</strong></td>
                                        <td id="view_assigned_to"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Assigned Date:</strong></td>
                                        <td id="view_assigned_date"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created By:</strong></td>
                                        <td id="view_created_by"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created Date:</strong></td>
                                        <td id="view_created_date"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Specifications</h6>
                            </div>
                            <div class="card-body">
                                <div id="view_specifications" class="specifications-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Equipment History -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Equipment History</h6>
                            </div>
                            <div class="card-body">
                                <div id="equipment_history" class="table-responsive">
                                    <!-- History will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if (hasPermission('manage_ict_equipment')): ?>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="fas fa-edit"></i> Edit Equipment
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Equipment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Asset Tag *</label>
                                <input type="text" name="asset_tag" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Equipment Name *</label>
                                <input type="text" name="equipment_name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Brand</label>
                                <input type="text" name="brand" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Model</label>
                                <input type="text" name="model" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Serial Number *</label>
                                <input type="text" name="serial_number" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Condition</label>
                                <select name="condition" class="form-control">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="Available" selected>Available</option>
                                    <option value="Assigned">Assigned</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                    <option value="Retired">Retired</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Specifications</label>
                        <textarea name="specifications" class="form-control" rows="4" placeholder="Enter technical specifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_equipment" class="btn btn-primary">Add Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Equipment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="equipment_id" id="edit_equipment_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Asset Tag *</label>
                                <input type="text" name="asset_tag" id="edit_asset_tag" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Equipment Name *</label>
                                <input type="text" name="equipment_name" id="edit_equipment_name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category_id" id="edit_category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Brand</label>
                                <input type="text" name="brand" id="edit_brand" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Model</label>
                                <input type="text" name="model" id="edit_model" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Serial Number *</label>
                                <input type="text" name="serial_number" id="edit_serial_number" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Condition</label>
                                <select name="condition" id="edit_condition" class="form-control">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="Available">Available</option>
                                    <option value="Assigned">Assigned</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                    <option value="Retired">Retired</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Specifications</label>
                        <textarea name="specifications" id="edit_specifications" class="form-control" rows="4" placeholder="Enter technical specifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_equipment" class="btn btn-primary">Update Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Equipment Modal - Simplified Version -->
<div class="modal fade" id="assignEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Equipment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="equipment_id" id="assign_equipment_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select name="assigned_to" id="assign_employee" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php
                            // Simple query to get active employees
                            $employees_query = "SELECT emp_id, first_name, last_name 
                                               FROM employee 
                                               WHERE employment_status_id = 1 
                                               ORDER BY first_name, last_name";
                            $employees_result = $db->query($employees_query);
                            
                            if ($employees_result && $employees_result->num_rows > 0) {
                                while ($emp = $employees_result->fetch_assoc()): ?>
                                    <option value="<?= $emp['emp_id'] ?>">
                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                    </option>
                                <?php endwhile;
                            } else {
                                echo '<option value="">No active employees found</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assignment Notes</label>
                        <textarea name="assignment_notes" class="form-control" rows="3" placeholder="Optional notes about this assignment..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="icon fas fa-info"></i> 
                        This will change the equipment status to "Assigned" and record the assignment in the equipment history.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_equipment" class="btn btn-primary">Assign Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unassign Equipment Modal -->
<div class="modal fade" id="unassignEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Unassign Equipment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="equipment_id" id="unassign_equipment_id">
                <div class="modal-body">
                    <p>Are you sure you want to unassign this equipment?</p>
                    <div id="unassign_equipment_info" class="alert alert-warning">
                        <!-- Equipment info will be populated here -->
                    </div>
                    <div class="alert alert-info">
                        <i class="icon fas fa-info"></i> 
                        This will change the equipment status to "Available" and record the unassignment in the equipment history.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="unassign_equipment" class="btn btn-warning">Unassign Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>