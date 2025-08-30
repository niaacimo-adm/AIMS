<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_driver'])) {
        // Create new driver
        try {
            $query = "INSERT INTO employee 
                     (id_number, first_name, last_name, email, phone_number, position_id, employment_status_id, appointment_status_id, office_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssssiiii", 
                $_POST['id_number'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone_number'],
                $_POST['position_id'],
                $_POST['employment_status_id'],
                $_POST['appointment_status_id'],
                $_POST['office_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Driver created successfully!'
                ];
            } else {
                throw new Exception("Error creating driver: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: driver.php");
        exit();
        
    } elseif (isset($_POST['update_driver'])) {
        // Update existing driver
        try {
            $query = "UPDATE employee SET 
                      id_number = ?,
                      first_name = ?,
                      last_name = ?,
                      email = ?,
                      phone_number = ?,
                      position_id = ?,
                      employment_status_id = ?,
                      appointment_status_id = ?,
                      office_id = ?
                      WHERE emp_id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssssiiiii", 
                $_POST['id_number'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone_number'],
                $_POST['position_id'],
                $_POST['employment_status_id'],
                $_POST['appointment_status_id'],
                $_POST['office_id'],
                $_POST['emp_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Driver updated successfully!'
                ];
            } else {
                throw new Exception("Error updating driver: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: driver.php");
        exit();
        
    } elseif (isset($_POST['delete_driver'])) {
        // Delete driver
        try {
            $query = "DELETE FROM employee WHERE emp_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $_POST['emp_id']);
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Driver deleted successfully!'
                ];
            } else {
                throw new Exception("Error deleting driver: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: driver.php");
        exit();
    }
}

// Fetch all drivers (positions 21 and 23)
$query = "SELECT e.*, p.position_name, o.office_name, 
          es.status_name as employment_status, 
          ap.status_name as appointment_status
          FROM employee e
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          WHERE e.position_id IN (21, 23)
          ORDER BY e.last_name, e.first_name";

$stmt = $db->prepare($query);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch reference data for forms
$positions = $db->query("SELECT * FROM position WHERE position_id IN (21, 23)")->fetch_all(MYSQLI_ASSOC);
$offices = $db->query("SELECT * FROM office")->fetch_all(MYSQLI_ASSOC);
$employment_statuses = $db->query("SELECT * FROM employment_status")->fetch_all(MYSQLI_ASSOC);
$appointment_statuses = $db->query("SELECT * FROM appointment_status")->fetch_all(MYSQLI_ASSOC);

// Get driver details for edit (if requested)
$edit_driver = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $query = "SELECT * FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_driver = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Driver/Operators Management</title>
  <?php include '../includes/header.php'; ?>
  
  <style>
    .driver-photo {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
    }
    .action-btns .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_service.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Driver/Operators Management</h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Driver/Operators List</h3>
                <button class="btn btn-primary float-right" data-toggle="modal" data-target="#addDriverModal">
                  <i class="fas fa-plus"></i> Add New Driver
                </button>
              </div>
              <div class="card-body">
                <?php if (isset($_SESSION['toast'])): ?>
                  <div class="alert alert-<?= $_SESSION['toast']['type'] ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?= $_SESSION['toast']['message'] ?>
                  </div>
                  <?php unset($_SESSION['toast']); ?>
                <?php endif; ?>
                
                <table id="driversTable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Photo</th>
                      <th>ID Number</th>
                      <th>Name</th>
                      <th>Position</th>
                      <th>Office</th>
                      <th>Status</th>
                      <th>Appointment</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($drivers as $driver): ?>
                    <tr>
                      <td>
                        <?php if (!empty($driver['picture'])): ?>
                          <img src="../dist/img/employees/<?= htmlspecialchars($driver['picture']) ?>" 
                               class="driver-photo" 
                               alt="<?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?>">
                        <?php else: ?>
                          <i class="fas fa-user-circle fa-2x text-muted"></i>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($driver['id_number']) ?></td>
                      <td><?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?></td>
                      <td><?= htmlspecialchars($driver['position_name']) ?></td>
                      <td><?= htmlspecialchars($driver['office_name']) ?></td>
                    <td>
                        <?php 
                        $statusInfo = null;
                        foreach ($employment_statuses as $status) {
                            if ($status['status_id'] == $driver['employment_status_id']) {
                                $statusInfo = $status;
                                break;
                            }
                        }
                        
                        if ($statusInfo): ?>
                            <span class="badge" style="background-color: <?= htmlspecialchars($statusInfo['color'] ?? '#007bff') ?>">
                                <?= htmlspecialchars($statusInfo['status_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Unknown Status</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $appointmentInfo = null;
                        foreach ($appointment_statuses as $status) {
                            if ($status['appointment_id'] == $driver['appointment_status_id']) {
                                $appointmentInfo = $status;
                                break;
                            }
                        }
                        
                        if ($appointmentInfo): ?>
                            <span class="badge" style="background-color: <?= htmlspecialchars($appointmentInfo['color'] ?? '#007bff') ?>">
                                <?= htmlspecialchars($appointmentInfo['status_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Unknown Status</span>
                        <?php endif; ?>
                    </td>
                      <td class="action-btns">
                        <button class="btn btn-warning btn-sm edit-btn" 
                                data-id="<?= $driver['emp_id'] ?>"
                                data-toggle="modal" 
                                data-target="#editDriverModal">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-btn" 
                                data-id="<?= $driver['emp_id'] ?>"
                                data-name="<?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?>"
                                data-toggle="modal" 
                                data-target="#deleteDriverModal">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  
  <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add Driver Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1" role="dialog" aria-labelledby="addDriverModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="driver.php">
        <div class="modal-header">
          <h5 class="modal-title" id="addDriverModalLabel">Add New Driver/Operator</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>ID Number</label>
            <input type="text" class="form-control" name="id_number" required>
          </div>
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" name="first_name" required>
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" name="last_name" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="text" class="form-control" name="phone_number">
          </div>
          <div class="form-group">
            <label>Position</label>
            <select class="form-control" name="position_id" required>
              <?php foreach ($positions as $position): ?>
                <option value="<?= $position['position_id'] ?>"><?= $position['position_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Employment Status</label>
            <select class="form-control" name="employment_status_id" required>
              <?php foreach ($employment_statuses as $status): ?>
                <option value="<?= $status['status_id'] ?>"><?= $status['status_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Appointment Status</label>
            <select class="form-control" name="appointment_status_id" required>
              <?php foreach ($appointment_statuses as $status): ?>
                <option value="<?= $status['appointment_id'] ?>"><?= $status['status_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Office</label>
            <select class="form-control" name="office_id" required>
              <?php foreach ($offices as $office): ?>
                <option value="<?= $office['office_id'] ?>"><?= $office['office_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" name="create_driver" class="btn btn-primary">Save Driver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Driver Modal -->
<div class="modal fade" id="editDriverModal" tabindex="-1" role="dialog" aria-labelledby="editDriverModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="driver.php">
        <input type="hidden" name="emp_id" id="edit_emp_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editDriverModalLabel">Edit Driver/Operator</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>ID Number</label>
            <input type="text" class="form-control" name="id_number" id="edit_id_number" required>
          </div>
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" id="edit_email">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="text" class="form-control" name="phone_number" id="edit_phone_number">
          </div>
          <div class="form-group">
            <label>Position</label>
            <select class="form-control" name="position_id" id="edit_position_id" required>
              <?php foreach ($positions as $position): ?>
                <option value="<?= $position['position_id'] ?>"><?= $position['position_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Employment Status</label>
            <select class="form-control" name="employment_status_id" id="edit_employment_status_id" required>
              <?php foreach ($employment_statuses as $status): ?>
                <option value="<?= $status['status_id'] ?>"><?= $status['status_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Appointment Status</label>
            <select class="form-control" name="appointment_status_id" id="edit_appointment_status_id" required>
              <?php foreach ($appointment_statuses as $status): ?>
                <option value="<?= $status['appointment_id'] ?>"><?= $status['status_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Office</label>
            <select class="form-control" name="office_id" id="edit_office_id" required>
              <?php foreach ($offices as $office): ?>
                <option value="<?= $office['office_id'] ?>"><?= $office['office_name'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" name="update_driver" class="btn btn-primary">Update Driver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Driver Modal -->
<div class="modal fade" id="deleteDriverModal" tabindex="-1" role="dialog" aria-labelledby="deleteDriverModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="driver.php">
        <input type="hidden" name="emp_id" id="delete_emp_id">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteDriverModalLabel">Confirm Delete</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong id="delete_driver_name"></strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_driver" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#driversTable').DataTable({
        responsive: true,
        autoWidth: false,
        columnDefs: [
            { responsivePriority: 1, targets: 2 }, // Name
            { responsivePriority: 2, targets: 1 }, // ID Number
            { responsivePriority: 3, targets: -1 } // Actions
        ]
    });
    
    // Handle edit button click
    $('.edit-btn').click(function() {
        const driverId = $(this).data('id');
        
        // Fetch driver data via AJAX
        $.ajax({
            url: 'driver.php?edit=' + driverId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response) {
                    $('#edit_emp_id').val(response.emp_id);
                    $('#edit_id_number').val(response.id_number);
                    $('#edit_first_name').val(response.first_name);
                    $('#edit_last_name').val(response.last_name);
                    $('#edit_email').val(response.email);
                    $('#edit_phone_number').val(response.phone_number);
                    $('#edit_position_id').val(response.position_id);
                    $('#edit_employment_status_id').val(response.employment_status_id);
                    $('#edit_appointment_status_id').val(response.appointment_status_id);
                    $('#edit_office_id').val(response.office_id);
                }
            }
        });
    });
    
    // Handle delete button click
    $('.delete-btn').click(function() {
        $('#delete_emp_id').val($(this).data('id'));
        $('#delete_driver_name').text($(this).data('name'));
    });
    
    // If we're opening the edit modal directly (from URL parameter)
    <?php if ($edit_driver): ?>
    $(window).on('load', function() {
        $('#edit_emp_id').val('<?= $edit_driver["emp_id"] ?>');
        $('#edit_id_number').val('<?= $edit_driver["id_number"] ?>');
        $('#edit_first_name').val('<?= $edit_driver["first_name"] ?>');
        $('#edit_last_name').val('<?= $edit_driver["last_name"] ?>');
        $('#edit_email').val('<?= $edit_driver["email"] ?>');
        $('#edit_phone_number').val('<?= $edit_driver["phone_number"] ?>');
        $('#edit_position_id').val('<?= $edit_driver["position_id"] ?>');
        $('#edit_employment_status_id').val('<?= $edit_driver["employment_status_id"] ?>');
        $('#edit_appointment_status_id').val('<?= $edit_driver["appointment_status_id"] ?>');
        $('#edit_office_id').val('<?= $edit_driver["office_id"] ?>');
        
        $('#editDriverModal').modal('show');
    });
    <?php endif; ?>
});
</script>
</body>
</html>