<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Process request actions (approve, reject, delete, etc.)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['approve_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check if there are any pending items
        $check_pending_query = "SELECT COUNT(*) as pending_count FROM supply_request_items WHERE request_id = ? AND status = 'pending'";
        $stmt = $db->prepare($check_pending_query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_count = $result->fetch_assoc()['pending_count'];
        
        if ($pending_count > 0) {
            $error = "Cannot approve request. There are $pending_count item(s) still pending. Please review individual items first.";
        } else {
            $update_query = "UPDATE supply_requests SET status = 'approved' WHERE id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("i", $request_id);
            
            if ($stmt->execute()) {
                $success = "Supply request approved successfully!";
            } else {
                $error = "Error approving supply request.";
            }
        }
    }
    
    if (isset($_POST['reject_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check if there are any pending items
        $check_pending_query = "SELECT COUNT(*) as pending_count FROM supply_request_items WHERE request_id = ? AND status = 'pending'";
        $stmt = $db->prepare($check_pending_query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_count = $result->fetch_assoc()['pending_count'];
        
        if ($pending_count > 0) {
            $error = "Cannot reject request. There are $pending_count item(s) still pending. Please review individual items first.";
        } else {
            $update_query = "UPDATE supply_requests SET status = 'rejected' WHERE id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("i", $request_id);
            
            if ($stmt->execute()) {
                $success = "Supply request rejected successfully!";
            } else {
                $error = "Error rejecting supply request.";
            }
        }
    }
    
    if (isset($_POST['delete_request'])) {
        $request_id = intval($_POST['request_id']);
        
        $db->begin_transaction();
        try {
            // First delete the request items
            $delete_items_query = "DELETE FROM supply_request_items WHERE request_id = ?";
            $stmt = $db->prepare($delete_items_query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            // Then delete the request
            $delete_request_query = "DELETE FROM supply_requests WHERE id = ?";
            $stmt = $db->prepare($delete_request_query);
            $stmt->bind_param("i", $request_id);
            
            if ($stmt->execute()) {
                $db->commit();
                $success = "Supply request deleted successfully!";
            } else {
                throw new Exception("Error deleting request");
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error deleting supply request: " . $e->getMessage();
        }
    }
}

// Fetch all supply requests
$requests = [];
$query = "SELECT sr.*, COUNT(sri.id) as item_count,
          SUM(CASE WHEN sri.status = 'pending' THEN 1 ELSE 0 END) as pending_count
          FROM supply_requests sr 
          LEFT JOIN supply_request_items sri ON sr.id = sri.request_id 
          GROUP BY sr.id 
          ORDER BY sr.created_at DESC";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Supply Requests - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .table-actions {
            min-width: 250px;
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
                        <h1>Manage Supply Requests</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">Manage Requests</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Supply Requests</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Employee Name</th>
                                        <th>Section</th>
                                        <th>Request Date</th>
                                        <th>Total Items</th>
                                        <th>Pending Items</th>
                                        <th>Status</th>
                                        <th>Date Created</th>
                                        <th class="table-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>SR-<?= str_pad($request['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($request['employee_name']) ?></td>
                                        <td><?= htmlspecialchars($request['section']) ?></td>
                                        <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                        <td class="text-center"><?= $request['item_count'] ?></td>
                                        <td class="text-center">
                                            <?php if ($request['pending_count'] > 0): ?>
                                                <span class="badge badge-pending"><?= $request['pending_count'] ?> pending</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">All reviewed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= 
                                                $request['status'] == 'approved' ? 'success' : 
                                                ($request['status'] == 'rejected' ? 'danger' : 'warning') 
                                            ?>">
                                                <?= ucfirst($request['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y g:i A', strtotime($request['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_supply_request.php?id=<?= $request['id'] ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                <?php if ($request['status'] == 'pending'): ?>
                                                    <button type="button" class="btn btn-success btn-sm approve-btn" 
                                                            data-request-id="<?= $request['id'] ?>" 
                                                            data-pending-count="<?= $request['pending_count'] ?>">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-danger btn-sm reject-btn" 
                                                            data-request-id="<?= $request['id'] ?>" 
                                                            data-pending-count="<?= $request['pending_count'] ?>">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-outline-danger btn-sm delete-btn" 
                                                        data-request-id="<?= $request['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
<?php include '../includes/footer.php'; ?>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Approve button click handler
        $('.approve-btn').click(function() {
            const requestId = $(this).data('request-id');
            const pendingCount = $(this).data('pending-count');
            
            if (pendingCount > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pending Items Found',
                    html: `There are <strong>${pendingCount}</strong> item(s) still pending.<br>Please review individual items before approving the entire request.`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            Swal.fire({
                title: 'Approve Request?',
                text: "Are you sure you want to approve this supply request?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, approve it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'request_id';
                    input.value = requestId;
                    form.appendChild(input);
                    
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'approve_request';
                    submitInput.value = '1';
                    form.appendChild(submitInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
        
        // Reject button click handler
        $('.reject-btn').click(function() {
            const requestId = $(this).data('request-id');
            const pendingCount = $(this).data('pending-count');
            
            if (pendingCount > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pending Items Found',
                    html: `There are <strong>${pendingCount}</strong> item(s) still pending.<br>Please review individual items before rejecting the entire request.`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            Swal.fire({
                title: 'Reject Request?',
                text: "Are you sure you want to reject this supply request?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'request_id';
                    input.value = requestId;
                    form.appendChild(input);
                    
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'reject_request';
                    submitInput.value = '1';
                    form.appendChild(submitInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
        
        // Delete button click handler
        $('.delete-btn').click(function() {
            const requestId = $(this).data('request-id');
            
            Swal.fire({
                title: 'Delete Request?',
                text: "This action cannot be undone! All items in this request will also be deleted.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve) => {
                        // Create and submit form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'request_id';
                        input.value = requestId;
                        form.appendChild(input);
                        
                        const submitInput = document.createElement('input');
                        submitInput.type = 'hidden';
                        submitInput.name = 'delete_request';
                        submitInput.value = '1';
                        form.appendChild(submitInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                        
                        resolve();
                    });
                }
            });
        });
        
        // Show success message with SweetAlert if there's a success message
        <?php if (isset($success)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= addslashes($success) ?>',
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
        
        // Show error message with SweetAlert if there's an error message
        <?php if (isset($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?= addslashes($error) ?>',
                confirmButtonColor: '#dc3545'
            });
        <?php endif; ?>
    });
</script>
</body>
</html>