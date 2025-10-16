<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_equipment'])) {
        $result = addEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    elseif (isset($_POST['edit_equipment'])) {
        $result = editEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    elseif (isset($_POST['delete_equipment'])) {
        $result = deleteEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    elseif (isset($_POST['assign_equipment'])) {
        $result = assignEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    elseif (isset($_POST['unassign_equipment'])) {
        $result = unassignEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
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
    
    // Check if equipment exists and is available
    $check_equipment_query = "SELECT equipment_id, status FROM ict_equipment WHERE equipment_id = ?";
    $check_equipment_stmt = $db->prepare($check_equipment_query);
    $check_equipment_stmt->bind_param("i", $equipment_id);
    $check_equipment_stmt->execute();
    $check_equipment_result = $check_equipment_stmt->get_result();
    
    if ($check_equipment_result->num_rows === 0) {
        return ['success' => false, 'message' => 'Equipment not found'];
    }
    
    $equipment = $check_equipment_result->fetch_assoc();
    if ($equipment['status'] !== 'Available') {
        return ['success' => false, 'message' => 'Equipment is not available for assignment'];
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
        // Log the assignment
        $log_query = "INSERT INTO ict_equipment_logs (equipment_id, action, action_by, notes) VALUES (?, 'Assigned', ?, ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bind_param("iis", $equipment_id, $_SESSION['emp_id'], $assignment_notes);
        $log_stmt->execute();
        
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
        $employee_name = $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'Unknown';
        $log_notes = "Unassigned from " . $employee_name;
        
        $log_query = "INSERT INTO ict_equipment_logs (equipment_id, action, action_by, notes) VALUES (?, 'Unassigned', ?, ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bind_param("iis", $equipment_id, $_SESSION['emp_id'], $log_notes);
        $log_stmt->execute();
        
        return ['success' => true, 'message' => 'Equipment unassigned successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to unassign equipment: ' . $db->error];
    }
}

// Add this to handle the assignment form submission in the main POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... your existing POST handlers ...
    
    if (isset($_POST['assign_equipment'])) {
        $result = assignEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
    elseif (isset($_POST['unassign_equipment'])) {
        $result = unassignEquipment($db);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
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
                <!-- Display Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
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
                                                    <a href="ict_equipment_view.php?id=<?= $item['equipment_id'] ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($item['status'] == 'Available'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="equipment_id" value="<?= $item['equipment_id'] ?>">
                                                            <input type="hidden" name="assigned_to" value="YOUR_EMP_ID_HERE"> <!-- You'll need to get this dynamically -->
                                                            <button type="submit" name="assign_equipment" class="btn btn-success btn-sm" 
                                                                    onclick="return confirm('Assign this equipment?')">
                                                                <i class="fas fa-user-check"></i> Assign
                                                            </button>
                                                        </form>
                                                    <?php elseif ($item['status'] == 'Assigned'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="equipment_id" value="<?= $item['equipment_id'] ?>">
                                                            <button type="submit" name="unassign_equipment" class="btn btn-warning btn-sm" 
                                                                    onclick="return confirm('Unassign this equipment?')">
                                                                <i class="fas fa-user-times"></i> Unassign
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-primary btn-sm edit-equipment" 
                                                            data-id="<?= $item['equipment_id'] ?>"
                                                            data-asset-tag="<?= htmlspecialchars($item['asset_tag']) ?>"
                                                            data-equipment-name="<?= htmlspecialchars($item['equipment_name']) ?>"
                                                            data-category-id="<?= $item['category_id'] ?>"
                                                            data-brand="<?= htmlspecialchars($item['brand']) ?>"
                                                            data-model="<?= htmlspecialchars($item['model']) ?>"
                                                            data-serial-number="<?= htmlspecialchars($item['serial_number']) ?>"
                                                            data-specifications="<?= htmlspecialchars($item['specifications']) ?>"
                                                            data-condition="<?= $item['condition'] ?>"
                                                            data-status="<?= $item['status'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this equipment?')">
                                                        <input type="hidden" name="equipment_id" value="<?= $item['equipment_id'] ?>">
                                                        <button type="submit" name="delete_equipment" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
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
    <div class="modal-dialog modal-lg" role="document">
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
<!-- Assign Equipment Modal -->
<!-- Assign Equipment Modal (without Select2) -->
<div class="modal fade" id="assignEquipmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
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
                            // Get all employees for assignment
                            $employees_query = "SELECT emp_id, first_name, last_name, department FROM employee WHERE status = 'Active' ORDER BY first_name, last_name";
                            $employees_result = $db->query($employees_query);
                            $employees = $employees_result->fetch_all(MYSQLI_ASSOC);
                            
                            foreach ($employees as $emp): ?>
                                <option value="<?= $emp['emp_id'] ?>">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['department'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
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
    <div class="modal-dialog" role="document">
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

    // Edit Equipment - Populate modal
    $(document).on('click', '.edit-equipment', function() {
        console.log('Edit button clicked'); // Debug log
        
        const id = $(this).data('id');
        const assetTag = $(this).data('asset-tag');
        const equipmentName = $(this).data('equipment-name');
        const categoryId = $(this).data('category-id');
        const brand = $(this).data('brand');
        const model = $(this).data('model');
        const serialNumber = $(this).data('serial-number');
        const specifications = $(this).data('specifications');
        const condition = $(this).data('condition');
        const status = $(this).data('status');
        
        console.log('Populating edit modal with ID:', id); // Debug log
        
        $('#edit_equipment_id').val(id);
        $('#edit_asset_tag').val(assetTag);
        $('#edit_equipment_name').val(equipmentName);
        $('#edit_category_id').val(categoryId);
        $('#edit_brand').val(brand || '');
        $('#edit_model').val(model || '');
        $('#edit_serial_number').val(serialNumber);
        $('#edit_specifications').val(specifications || '');
        $('#edit_condition').val(condition || 'Good');
        $('#edit_status').val(status || 'Available');
        
        $('#editEquipmentModal').modal('show');
    });

    // Assign Equipment - Populate modal
    $(document).on('click', '.assign-equipment', function() {
        console.log('Assign button clicked'); // Debug log
        
        const id = $(this).data('id');
        const equipmentName = $(this).data('equipment-name');
        const assetTag = $(this).data('asset-tag');
        
        console.log('Assigning equipment ID:', id); // Debug log
        
        $('#assign_equipment_id').val(id);
        $('#assign_employee').val(''); // Reset employee selection
        $('#assignEquipmentModal .modal-title').text('Assign Equipment: ' + equipmentName + ' (' + assetTag + ')');
        
        $('#assignEquipmentModal').modal('show');
    });

    // Unassign Equipment - Populate modal
    $(document).on('click', '.unassign-equipment', function() {
        console.log('Unassign button clicked'); // Debug log
        
        const id = $(this).data('id');
        const equipmentName = $(this).data('equipment-name');
        const assetTag = $(this).data('asset-tag');
        const assignedTo = $(this).data('assigned-to');
        
        console.log('Unassigning equipment ID:', id); // Debug log
        
        $('#unassign_equipment_id').val(id);
        $('#unassignEquipmentModal .modal-title').text('Unassign Equipment: ' + equipmentName + ' (' + assetTag + ')');
        $('#unassign_equipment_info').html(
            '<strong>Equipment:</strong> ' + equipmentName + ' (' + assetTag + ')<br>' +
            '<strong>Currently assigned to:</strong> ' + assignedTo
        );
        
        $('#unassignEquipmentModal').modal('show');
    });

    // Handle modal form submissions
    $('form').on('submit', function() {
        console.log('Form submitted:', this); // Debug log
        return true; // Allow form submission
    });

    // Debug: Log all button clicks
    $(document).on('click', 'button', function() {
        console.log('Button clicked:', $(this).text(), $(this).attr('name'));
    });
});
</script>
</body>
</html>