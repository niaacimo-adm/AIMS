<?php
session_start();
require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_vehicle'])) {
        // Create new vehicle
        try {
            $query = "INSERT INTO vehicles 
                     (property_no, plate_no, vehicle_type, model, year, capacity, status, office_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssssiisi", 
                $_POST['property_no'],
                $_POST['plate_no'],
                $_POST['vehicle_type'],
                $_POST['model'],
                $_POST['year'],
                $_POST['capacity'],
                $_POST['status'],
                $_POST['office_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Vehicle created successfully!'
                ];
            } else {
                throw new Exception("Error creating vehicle: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: service_vehicle.php");
        exit();
        
    } elseif (isset($_POST['update_vehicle'])) {
        // Update existing vehicle
        try {
            $query = "UPDATE vehicles SET 
                      property_no = ?,
                      plate_no = ?,
                      vehicle_type = ?,
                      model = ?,
                      year = ?,
                      capacity = ?,
                      status = ?,
                      office_id = ?
                      WHERE vehicle_id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssssiisii", 
                $_POST['property_no'],
                $_POST['plate_no'],
                $_POST['vehicle_type'],
                $_POST['model'],
                $_POST['year'],
                $_POST['capacity'],
                $_POST['status'],
                $_POST['office_id'],
                $_POST['vehicle_id']
            );
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Vehicle updated successfully!'
                ];
            } else {
                throw new Exception("Error updating vehicle: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: service_vehicle.php");
        exit();
        
    } elseif (isset($_POST['delete_vehicle'])) {
        // Delete vehicle
        try {
            $query = "DELETE FROM vehicles WHERE vehicle_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $_POST['vehicle_id']);
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Vehicle deleted successfully!'
                ];
            } else {
                throw new Exception("Error deleting vehicle: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: service_vehicle.php");
        exit();
    }
}

// Fetch all vehicles
$query = "SELECT v.*, o.office_name 
          FROM vehicles v
          LEFT JOIN office o ON v.office_id = o.office_id
          ORDER BY v.vehicle_type, v.property_no";
$vehicles = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Fetch offices for dropdown
$offices = $db->query("SELECT * FROM office ORDER BY office_name")->fetch_all(MYSQLI_ASSOC);

// Get vehicle details for edit (if requested)
$edit_vehicle = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $query = "SELECT * FROM vehicles WHERE vehicle_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_vehicle = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-Albay | Vehicle Management</title>
  <?php include '../includes/header.php'; ?>
  
  <style>
    .status-badge {
        font-size: 0.8rem;
        padding: 0.35rem 0.5rem;
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
            <h1>Vehicle Management</h1>
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
                <h3 class="card-title">Vehicle List</h3>
                <button class="btn btn-primary float-right" data-toggle="modal" data-target="#addVehicleModal">
                  <i class="fas fa-plus"></i> Add New Vehicle
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
                
                <table id="vehiclesTable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Property No.</th>
                      <th>Plate No.</th>
                      <th>Type</th>
                      <th>Model</th>
                      <th>Year</th>
                      <th>Capacity</th>
                      <th>Status</th>
                      <th>Office</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                      <td><?= htmlspecialchars($vehicle['property_no']) ?></td>
                      <td><?= htmlspecialchars($vehicle['plate_no']) ?></td>
                      <td><?= htmlspecialchars($vehicle['vehicle_type']) ?></td>
                      <td><?= htmlspecialchars($vehicle['model']) ?></td>
                      <td><?= $vehicle['year'] ?></td>
                      <td><?= $vehicle['capacity'] ?></td>
                      <td>
                        <?php
                        $badge_class = '';
                        switch ($vehicle['status']) {
                            case 'available':
                                $badge_class = 'badge-success';
                                break;
                            case 'maintenance':
                                $badge_class = 'badge-warning';
                                break;
                            case 'unavailable':
                                $badge_class = 'badge-danger';
                                break;
                            default:
                                $badge_class = 'badge-secondary';
                        }
                        ?>
                        <span class="badge status-badge <?= $badge_class ?>"><?= ucfirst($vehicle['status']) ?></span>
                      </td>
                      <td><?= $vehicle['office_name'] ?? 'N/A' ?></td>
                      <td class="action-btns">
                        <button class="btn btn-primary btn-sm edit-btn" 
                                data-id="<?= $vehicle['vehicle_id'] ?>"
                                data-toggle="modal" 
                                data-target="#editVehicleModal">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-btn" 
                                data-id="<?= $vehicle['vehicle_id'] ?>"
                                data-name="<?= htmlspecialchars($vehicle['property_no'] . ' - ' . htmlspecialchars($vehicle['vehicle_type'])) ?>"
                                data-toggle="modal" 
                                data-target="#deleteVehicleModal">
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
  
  <!-- Add Vehicle Modal -->
  <div class="modal fade" id="addVehicleModal" tabindex="-1" role="dialog" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="service_vehicle.php">
          <div class="modal-header">
            <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Property Number</label>
              <input type="text" class="form-control" name="property_no" required>
            </div>
            <div class="form-group">
              <label>Plate Number</label>
              <input type="text" class="form-control" name="plate_no" required>
            </div>
            <div class="form-group">
              <label>Vehicle Type</label>
              <input type="text" class="form-control" name="vehicle_type" required>
            </div>
            <div class="form-group">
              <label>Model</label>
              <input type="text" class="form-control" name="model">
            </div>
            <div class="form-group">
              <label>Year</label>
              <input type="number" class="form-control" name="year" min="1900" max="<?= date('Y') + 1 ?>">
            </div>
            <div class="form-group">
              <label>Capacity</label>
              <input type="number" class="form-control" name="capacity" min="1">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select class="form-control" name="status" required>
                <option value="available">Available</option>
                <option value="maintenance">Maintenance</option>
                <option value="unavailable">Unavailable</option>
              </select>
            </div>
            <div class="form-group">
              <label>Office</label>
              <select class="form-control" name="office_id">
                <option value="">Select Office</option>
                <?php foreach ($offices as $office): ?>
                  <option value="<?= $office['office_id'] ?>"><?= $office['office_name'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name="create_vehicle" class="btn btn-primary">Save Vehicle</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Edit Vehicle Modal -->
  <div class="modal fade" id="editVehicleModal" tabindex="-1" role="dialog" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="service_vehicle.php">
          <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
          <div class="modal-header">
            <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Property Number</label>
              <input type="text" class="form-control" name="property_no" id="edit_property_no" required>
            </div>
            <div class="form-group">
              <label>Plate Number</label>
              <input type="text" class="form-control" name="plate_no" id="edit_plate_no" required>
            </div>
            <div class="form-group">
              <label>Vehicle Type</label>
              <input type="text" class="form-control" name="vehicle_type" id="edit_vehicle_type" required>
            </div>
            <div class="form-group">
              <label>Model</label>
              <input type="text" class="form-control" name="model" id="edit_model">
            </div>
            <div class="form-group">
              <label>Year</label>
              <input type="number" class="form-control" name="year" id="edit_year" min="1900" max="<?= date('Y') + 1 ?>">
            </div>
            <div class="form-group">
              <label>Capacity</label>
              <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select class="form-control" name="status" id="edit_status" required>
                <option value="available">Available</option>
                <option value="maintenance">Maintenance</option>
                <option value="unavailable">Unavailable</option>
              </select>
            </div>
            <div class="form-group">
              <label>Office</label>
              <select class="form-control" name="office_id" id="edit_office_id">
                <option value="">Select Office</option>
                <?php foreach ($offices as $office): ?>
                  <option value="<?= $office['office_id'] ?>"><?= $office['office_name'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name="update_vehicle" class="btn btn-primary">Update Vehicle</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Delete Vehicle Modal -->
  <div class="modal fade" id="deleteVehicleModal" tabindex="-1" role="dialog" aria-labelledby="deleteVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="service_vehicle.php">
          <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteVehicleModalLabel">Confirm Delete</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="delete_vehicle_name"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <?php include '../includes/footer.php'; ?>

  <script>
  $(document).ready(function() {
      // Initialize DataTable
      $('#vehiclesTable').DataTable({
          responsive: true,
          autoWidth: false,
          columnDefs: [
              { responsivePriority: 1, targets: 0 }, // Property No
              { responsivePriority: 2, targets: 2 }, // Vehicle Type
              { responsivePriority: 3, targets: -1 } // Actions
          ]
      });
      
      // Handle edit button click
      $('.edit-btn').click(function() {
          const vehicleId = $(this).data('id');
          
          // Fetch vehicle data via AJAX
          $.ajax({
              url: 'service_vehicle.php?edit=' + vehicleId,
              type: 'GET',
              dataType: 'json',
              success: function(response) {
                  if (response) {
                      $('#edit_vehicle_id').val(response.vehicle_id);
                      $('#edit_property_no').val(response.property_no);
                      $('#edit_plate_no').val(response.plate_no);
                      $('#edit_vehicle_type').val(response.vehicle_type);
                      $('#edit_model').val(response.model);
                      $('#edit_year').val(response.year);
                      $('#edit_capacity').val(response.capacity);
                      $('#edit_status').val(response.status);
                      $('#edit_office_id').val(response.office_id);
                  }
              }
          });
      });
      
      // Handle delete button click
      $('.delete-btn').click(function() {
          $('#delete_vehicle_id').val($(this).data('id'));
          $('#delete_vehicle_name').text($(this).data('name'));
      });
      
      // If we're opening the edit modal directly (from URL parameter)
      <?php if ($edit_vehicle): ?>
      $(window).on('load', function() {
          $('#edit_vehicle_id').val('<?= $edit_vehicle["vehicle_id"] ?>');
          $('#edit_property_no').val('<?= $edit_vehicle["property_no"] ?>');
          $('#edit_plate_no').val('<?= $edit_vehicle["plate_no"] ?>');
          $('#edit_vehicle_type').val('<?= $edit_vehicle["vehicle_type"] ?>');
          $('#edit_model').val('<?= $edit_vehicle["model"] ?>');
          $('#edit_year').val('<?= $edit_vehicle["year"] ?>');
          $('#edit_capacity').val('<?= $edit_vehicle["capacity"] ?>');
          $('#edit_status').val('<?= $edit_vehicle["status"] ?>');
          $('#edit_office_id').val('<?= $edit_vehicle["office_id"] ?>');
          
          $('#editVehicleModal').modal('show');
      });
      <?php endif; ?>
  });
  </script>
</body>
</html>