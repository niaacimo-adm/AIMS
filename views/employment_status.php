<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update colors
    if (isset($_POST['update_colors'])) {
        foreach ($_POST['colors'] as $id => $color) {
            $stmt = $db->prepare("UPDATE employment_status SET color = ? WHERE status_id = ?");
            $stmt->execute([$color, $id]);
        }
        $_SESSION['swal'] = [
            'type' => 'success',
            'title' => 'Success!',
            'text' => 'Status colors updated successfully!'
        ];
        header("Location: employment_status.php");
        exit();
    }
    
    // Add new status
    if (isset($_POST['add_status'])) {
        $status_name = trim($_POST['status_name']);
        $color = $_POST['color'];
        
        if (!empty($status_name)) {
            $stmt = $db->prepare("INSERT INTO employment_status (status_name, color) VALUES (?, ?)");
            if ($stmt->execute([$status_name, $color])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status added successfully!'
                ];
                header("Location: employment_status.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add status.'
                ];
            }
        }
    }
    
    // Update status name
    if (isset($_POST['update_status'])) {
        $id = $_POST['id'];
        $status_name = trim($_POST['status_name']);
        $color = $_POST['color'];
        
        if (!empty($status_name)) {
            $stmt = $db->prepare("UPDATE employment_status SET status_name = ?, color = ? WHERE status_id = ?");
            if ($stmt->execute([$status_name, $color, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status updated successfully!'
                ];
                header("Location: employment_status.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update status.'
                ];
            }
        }
    }
}
$query = "SELECT * FROM employment_status ORDER BY status_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$statuses = [];
while ($row = $result->fetch_assoc()) {
    $statuses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Appointment Statuses</title>
  <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Appointment Statuses</h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Add New Status</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label for="status_name">Status Name</label>
                    <input type="text" class="form-control" id="status_name" name="status_name" required>
                  </div>
                  <div class="form-group">
                    <label for="color">Color</label>
                    <input type="color" class="form-control" id="color" name="color" value="#007bff" required>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_status" class="btn btn-primary">Add Status</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Statuses</h3>
              </div>
              <div class="card-body">
                <form method="POST">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Status Name</th>
                        <th>Color</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($statuses as $status): ?>
                      <tr>
                        <td><?= htmlspecialchars($status['status_name']) ?></td>
                        <td>
                          <input type="color" name="colors[<?= $status['status_id'] ?>]" 
                                value="<?= htmlspecialchars($status['color']) ?>">
                        </td>
                        <td>
                          <div class="btn-group">
                            <button type="button" class="btn btn-info" data-toggle="modal" 
                                    data-target="#editModal<?= $status['status_id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger delete-btn" 
                                    data-id="<?= $status['status_id'] ?>" 
                                    data-name="<?= htmlspecialchars($status['status_name']) ?>">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                          
                          <!-- Edit Modal -->
                          <div class="modal fade" id="editModal<?= $status['status_id'] ?>">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h4 class="modal-title">Edit Status</h4>
                                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                  </button>
                                </div>
                                <form method="POST">
                                  <div class="modal-body">
                                    <input type="hidden" name="id" value="<?= $status['status_id'] ?>">
                                    <div class="form-group">
                                      <label>Status Name</label>
                                      <input type="text" class="form-control" name="status_name" 
                                             value="<?= htmlspecialchars($status['status_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                      <label>Color</label>
                                      <input type="color" class="form-control" name="color" 
                                             value="<?= htmlspecialchars($status['color']) ?>" required>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                    <button type="submit" name="update_status" class="btn btn-primary">Save changes</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <div class="mt-3">
                    <button type="submit" name="update_colors" class="btn btn-primary">Save All Changes</button>
                  </div>
                </form>
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

<!-- SweetAlert Notifications -->
<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $_SESSION['swal']['type'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        showConfirmButton: true,
        timer: 3000
    });
});
</script>
<?php 
    unset($_SESSION['swal']);
endif; 
?>

<!-- Delete Confirmation Script -->
<script>
$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const statusId = $(this).data('id');
    const statusName = $(this).data('name');
    
    // First clear any existing session messages
    fetch('clear_session.php')
        .then(() => {
            // Then show the delete confirmation
            Swal.fire({
                title: 'Delete Status?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_estatus.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `status_id=${statusId}`
                    })
                    .then(response => response.json())
                    .catch(error => {
                        Swal.showValidationMessage('Request failed');
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const response = result.value;
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Delete',
                            html: `
                                <p>${response.message}</p>
                                ${response.employeeCount > 0 ? 
                                  '<div class="mt-3"><i class="fas fa-users mr-2"></i>Assigned to: ' + 
                                  response.employeeCount + ' employee(s)</div>' : ''}
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        });
                    }
                }
            });
        });
});
</script>
<!-- DataTables Initialization -->
<script>
$(function () {
    $("#example1").DataTable({
        responsive: true,
        lengthChange: true, // Changed to true to show length menu
        autoWidth: false,
        pageLength: 5, // Default number of rows per page
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]], // Pagination options
        columnDefs: [
            { responsivePriority: 1, targets: 1 }, // Picture
            { responsivePriority: 2, targets: 2 }, // Name
            { responsivePriority: 3, targets: -1 } // Actions
        ],
        dom: '<"top"lf>rt<"bottom"ip>', // Layout control
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: {
                previous: "&laquo;",
                next: "&raquo;"
            }
        }
    });
});
</script>
<!-- SweetAlert Notifications -->
<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $_SESSION['swal']['type'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        showConfirmButton: true,
        timer: 3000
    });
    
    // Immediately clear the session after showing
    fetch('clear_session.php')
        .then(response => response.text())
        .then(data => console.log('Session cleared'))
        .catch(error => console.error('Error clearing session:', error));
});
</script>
<?php endif; ?>
</body>
</html>