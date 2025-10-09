<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = $success = "";

// Get request ID from URL
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch supply request details
$request = [];
$request_query = "SELECT * FROM supply_requests WHERE id = ?";
$stmt = $db->prepare($request_query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    $error = "Supply request not found.";
}

// Fetch supply request items
$items = [];
if ($request_id > 0) {
    $items_query = "SELECT * FROM supply_request_items WHERE request_id = ? ORDER BY id";
    $stmt = $db->prepare($items_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Bulk approve selected items
    if (isset($_POST['bulk_approve'])) {
        $selected_items = $_POST['selected_items'] ?? [];
        
        if (!empty($selected_items)) {
            $db->begin_transaction();
            try {
                $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
                $update_query = "UPDATE supply_request_items SET status = 'approved' WHERE id IN ($placeholders) AND request_id = ?";
                $stmt = $db->prepare($update_query);
                
                // Bind parameters
                $types = str_repeat('i', count($selected_items)) . 'i';
                $params = array_merge($selected_items, [$request_id]);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $db->commit();
                    $success = count($selected_items) . " item(s) approved successfully!";
                    
                    // Refresh items data
                    $stmt = $db->prepare($items_query);
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $items = [];
                    while ($row = $result->fetch_assoc()) {
                        $items[] = $row;
                    }
                } else {
                    throw new Exception("Error updating items");
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error approving selected items: " . $e->getMessage();
            }
        } else {
            $error = "Please select at least one item to approve.";
        }
    }
    
    // Bulk remove selected items
    if (isset($_POST['bulk_remove'])) {
        $selected_items = $_POST['selected_items'] ?? [];
        
        if (!empty($selected_items)) {
            $db->begin_transaction();
            try {
                $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
                $delete_query = "DELETE FROM supply_request_items WHERE id IN ($placeholders) AND request_id = ?";
                $stmt = $db->prepare($delete_query);
                
                // Bind parameters
                $types = str_repeat('i', count($selected_items)) . 'i';
                $params = array_merge($selected_items, [$request_id]);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $db->commit();
                    $success = count($selected_items) . " item(s) removed successfully!";
                    
                    // Refresh items data
                    $stmt = $db->prepare($items_query);
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $items = [];
                    while ($row = $result->fetch_assoc()) {
                        $items[] = $row;
                    }
                } else {
                    throw new Exception("Error deleting items");
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error removing selected items: " . $e->getMessage();
            }
        } else {
            $error = "Please select at least one item to remove.";
        }
    }
    
    // Update individual item status
    if (isset($_POST['update_item_status'])) {
        $item_id = intval($_POST['item_id']);
        $status = $_POST['status'];
        
        $update_query = "UPDATE supply_request_items SET status = ? WHERE id = ? AND request_id = ?";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param("sii", $status, $item_id, $request_id);
        
        if ($stmt->execute()) {
            $success = "Item status updated successfully!";
            
            // Refresh items data
            $stmt = $db->prepare($items_query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        } else {
            $error = "Error updating item status.";
        }
    }
    
    // Approve entire request
    if (isset($_POST['approve_request'])) {
        $request_id_post = intval($_POST['request_id']);
        
        // Update all items in the request to approved
        $update_items_query = "UPDATE supply_request_items SET status = 'approved' WHERE request_id = ?";
        $stmt = $db->prepare($update_items_query);
        $stmt->bind_param("i", $request_id_post);
        
        // Update request status
        $update_request_query = "UPDATE supply_requests SET status = 'approved' WHERE id = ?";
        $stmt2 = $db->prepare($update_request_query);
        $stmt2->bind_param("i", $request_id_post);
        
        $db->begin_transaction();
        try {
            if ($stmt->execute() && $stmt2->execute()) {
                $db->commit();
                $success = "Entire request approved successfully!";
                
                // Refresh data
                $stmt = $db->prepare($request_query);
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                
                $stmt = $db->prepare($items_query);
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $items = [];
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }
            } else {
                throw new Exception("Error approving request");
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error approving entire request: " . $e->getMessage();
        }
    }
    
    // Reject entire request
    if (isset($_POST['reject_request'])) {
        $request_id_post = intval($_POST['request_id']);
        
        // Update all items in the request to rejected
        $update_items_query = "UPDATE supply_request_items SET status = 'rejected' WHERE request_id = ?";
        $stmt = $db->prepare($update_items_query);
        $stmt->bind_param("i", $request_id_post);
        
        // Update request status
        $update_request_query = "UPDATE supply_requests SET status = 'rejected' WHERE id = ?";
        $stmt2 = $db->prepare($update_request_query);
        $stmt2->bind_param("i", $request_id_post);
        
        $db->begin_transaction();
        try {
            if ($stmt->execute() && $stmt2->execute()) {
                $db->commit();
                $success = "Entire request rejected successfully!";
                
                // Refresh data
                $stmt = $db->prepare($request_query);
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                
                $stmt = $db->prepare($items_query);
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $items = [];
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }
            } else {
                throw new Exception("Error rejecting request");
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error rejecting entire request: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Supply Request - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .item-row {
            transition: background-color 0.3s;
        }
        .item-row:hover {
            background-color: #f8f9fa;
        }
        .bulk-actions {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .select-all-container {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .table-checkbox {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>View Supply Request</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="manage_supply_requests.php">Manage Requests</a></li>
                            <li class="breadcrumb-item active">View Request</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <?php if ($request): ?>
                <div class="row">
                    <div class="col-md-12">
                        <!-- Request Information Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Request Information</h3>
                                <div class="card-tools">
                                    <span class="badge badge-<?= 
                                        $request['status'] == 'approved' ? 'success' : 
                                        ($request['status'] == 'rejected' ? 'danger' : 'warning') 
                                    ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Request ID:</strong><br>
                                        SR-<?= str_pad($request['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Employee Name:</strong><br>
                                        <?= htmlspecialchars($request['employee_name']) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Section:</strong><br>
                                        <?= htmlspecialchars($request['section']) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Request Date:</strong><br>
                                        <?= date('M j, Y', strtotime($request['request_date'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <form method="POST" id="bulkActionForm">
                            <div class="bulk-actions">
                                <h4><i class="fas fa-tasks"></i> Bulk Actions</h4>
                                <div class="select-all-container">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input table-checkbox" id="selectAll">
                                        <label class="form-check-label" for="selectAll">
                                            <strong>Select All Items</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <button type="submit" name="bulk_approve" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve Selected
                                    </button>
                                    <button type="submit" name="bulk_remove" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove the selected items?')">
                                        <i class="fas fa-trash"></i> Remove Selected
                                    </button>
                                </div>
                            </div>

                            <!-- Requested Items -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Requested Items</h3>
                                    <div class="card-tools">
                                        <span class="badge badge-info"><?= count($items) ?> items</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($items)): ?>
                                        <div class="alert alert-info">No items found in this request.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th width="50px" class="text-center">
                                                            Select
                                                        </th>
                                                        <th>Supply Name</th>
                                                        <th>Description</th>
                                                        <th>Unit</th>
                                                        <th>Quantity</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): ?>
                                                    <tr class="item-row">
                                                        <td class="text-center">
                                                            <input type="checkbox" class="form-check-input table-checkbox item-checkbox" name="selected_items[]" value="<?= $item['id'] ?>">
                                                        </td>
                                                        <td><?= htmlspecialchars($item['supply_name']) ?></td>
                                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                                        <td><?= htmlspecialchars($item['unit']) ?></td>
                                                        <td><?= $item['quantity'] ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= 
                                                                $item['status'] == 'approved' ? 'success' : 
                                                                ($item['status'] == 'rejected' ? 'danger' : 'warning') 
                                                            ?> status-badge">
                                                                <?= ucfirst($item['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                                                        <option value="pending" <?= $item['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                        <option value="approved" <?= $item['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                                        <option value="rejected" <?= $item['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                                    </select>
                                                                    <input type="hidden" name="update_item_status" value="1">
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- Action Buttons -->
                        <div class="card">
                            <div class="card-body text-center">
                                <a href="manage_supply_requests.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Requests
                                </a>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="approve_request" class="btn btn-success" onclick="return confirm('Are you sure you want to approve the entire request?')">
                                            <i class="fas fa-check"></i> Approve Entire Request
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="reject_request" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject the entire request?')">
                                            <i class="fas fa-times"></i> Reject Entire Request
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Supply request not found.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Select all functionality
        $('#selectAll').change(function() {
            $('.item-checkbox').prop('checked', this.checked);
        });

        // Individual checkbox functionality
        $('.item-checkbox').change(function() {
            if (!this.checked) {
                $('#selectAll').prop('checked', false);
            } else {
                // Check if all checkboxes are checked
                if ($('.item-checkbox:checked').length === $('.item-checkbox').length) {
                    $('#selectAll').prop('checked', true);
                }
            }
        });

        // Bulk form submission confirmation
        $('#bulkActionForm').on('submit', function(e) {
            const selectedCount = $('.item-checkbox:checked').length;
            
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return false;
            }
            
            if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'bulk_approve') {
                if (!confirm(`Are you sure you want to approve ${selectedCount} item(s)?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
</script>
</body>
</html>