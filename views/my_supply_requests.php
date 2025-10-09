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

// Get current employee ID
$employee_id = $_SESSION['emp_id'] ?? 0;

if (!$employee_id) {
    header("Location: ../login.php");
    exit();
}

// Get current employee details
$employee_details = [];
$employee_query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name, 
                  s.section_name 
                  FROM employee e 
                  LEFT JOIN section s ON e.section_id = s.section_id 
                  WHERE e.emp_id = ?";
$stmt = $db->prepare($employee_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee_details = $result->fetch_assoc();

// Fetch user's supply requests
$requests = [];
$query = "SELECT sr.*, COUNT(sri.id) as item_count,
          SUM(CASE WHEN sri.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
          SUM(CASE WHEN sri.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
          SUM(CASE WHEN sri.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
          FROM supply_requests sr 
          LEFT JOIN supply_request_items sri ON sr.id = sri.request_id 
          WHERE sr.employee_id = ?
          GROUP BY sr.id 
          ORDER BY sr.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

// Process delete request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_request'])) {
    $request_id = intval($_POST['request_id']);
    
    // Verify that the request belongs to the current user
    $verify_query = "SELECT id FROM supply_requests WHERE id = ? AND employee_id = ?";
    $stmt = $db->prepare($verify_query);
    $stmt->bind_param("ii", $request_id, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    
    if ($request) {
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
                
                // Refresh the requests list
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $requests = [];
                while ($row = $result->fetch_assoc()) {
                    $requests[] = $row;
                }
            } else {
                throw new Exception("Error deleting request");
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error deleting supply request: " . $e->getMessage();
        }
    } else {
        $error = "You are not authorized to delete this request.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Supply Requests - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .table-actions {
            min-width: 200px;
        }
        .status-summary {
            font-size: 0.8rem;
        }
        .card-header {
            background: linear-gradient(120deg, #17a2b8, #138496);
            color: white;
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
                        <h1>My Supply Requests</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">My Requests</li>
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

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="callout callout-info">
                            <h5><i class="fas fa-info-circle"></i> My Requests</h5>
                            <p>View and manage all your supply requests. You can only edit requests that are still pending.</p>
                        </div>
                        
                            <div class="form-group">
                            <a href="request_supplies.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> Create New Request
                            </a>
                            </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Supply Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> You haven't made any supply requests yet.
                                <br>
                                <a href="request_supplies.php" class="btn btn-success mt-2">
                                    <i class="fas fa-plus"></i> Create Your First Request
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Request Date</th>
                                            <th>Total Items</th>
                                            <th>Status Summary</th>
                                            <th>Request Status</th>
                                            <th>Date Created</th>
                                            <th class="table-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>SR-<?= str_pad($request['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                            <td class="text-center"><?= $request['item_count'] ?></td>
                                            <td>
                                                <div class="status-summary">
                                                    <?php if ($request['approved_count'] > 0): ?>
                                                        <span class="badge badge-success"><?= $request['approved_count'] ?> approved</span>
                                                    <?php endif; ?>
                                                    <?php if ($request['pending_count'] > 0): ?>
                                                        <span class="badge badge-warning"><?= $request['pending_count'] ?> pending</span>
                                                    <?php endif; ?>
                                                    <?php if ($request['rejected_count'] > 0): ?>
                                                        <span class="badge badge-danger"><?= $request['rejected_count'] ?> rejected</span>
                                                    <?php endif; ?>
                                                </div>
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
                                                    <a href="view_my_request.php?id=<?= $request['id'] ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <a href="edit_supply_request.php?id=<?= $request['id'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm delete-btn" 
                                                                data-request-id="<?= $request['id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
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