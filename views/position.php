<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new status
    if (isset($_POST['add_position'])) {
        $position_name = trim($_POST['position_name']);
        
        if (!empty($position_name)) {
            $stmt = $db->prepare("INSERT INTO position (position_name) VALUES (?)");
            if ($stmt->execute([$position_name])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status added successfully!'
                ];
                header("Location: position.php");
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
    if (isset($_POST['update_position'])) {
        $id = $_POST['id'];
        $position_name = trim($_POST['position_name']);
        
        if (!empty($position_name)) {
            $stmt = $db->prepare("UPDATE position SET position_name = ? WHERE position_id = ?");
            if ($stmt->execute([$position_name, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status updated successfully!'
                ];
                header("Location: position.php");
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

$query = "SELECT * FROM position ORDER BY position_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
// Fetch all appointment statuses for reference
$positions = [];
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Position </title>
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
            <h1>Position </h1>
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
                <h3 class="card-title">Add New Position</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label for="position_name">Position Name</label>
                    <input type="text" class="form-control" id="position_name" name="position_name" required>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_position" class="btn btn-primary">Add Position</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Position</h3>
              </div>
              <div class="card-body">
                <form method="POST">
                  <table id="sectable" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Status Name</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($positions as $status): ?>
                      <tr>
                        <td><?= htmlspecialchars($status['position_name']) ?>
                        </td>
                        <td>
                          <div class="btn-group">
                            <button type="button" class="btn btn-info" data-toggle="modal" 
                                    data-target="#editModal<?= $status['position_id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger delete-btn" 
                                    data-id="<?= $status['position_id'] ?>" 
                                    data-name="<?= htmlspecialchars($status['position_name']) ?>">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                          
                          <!-- Edit Modal -->
                          <div class="modal fade" id="editModal<?= $status['position_id'] ?>">
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
                                    <input type="hidden" name="id" value="<?= $status['position_id'] ?>">
                                    <div class="form-group">
                                      <label>Status Name</label>
                                      <input type="text" class="form-control" name="position_name" 
                                             value="<?= htmlspecialchars($status['position_name']) ?>" required>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                    <button type="submit" name="update_position" class="btn btn-primary">Save changes</button>
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
    const positionId = $(this).data('id');
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
                    return fetch('delete_position.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `position_id=${positionId}`
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
$('#sectable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 5,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]]
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