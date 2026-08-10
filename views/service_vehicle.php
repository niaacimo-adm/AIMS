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
  /* ============================================================
     NIA-ACIMO Service Module — Modern UI Layer
     Built on top of the app's existing --variables (light/dark
     mode values are supplied globally in includes/header.php).
     ============================================================ */
  .service-ui{ --su-accent:#24e78f; --su-accent-rgb:36,231,143; --su-radius:16px; --su-radius-sm:10px; }

  /* ---------- Page header ---------- */
  .service-ui .content-header{ padding:18px 0 6px; }
  .service-ui .content-header .container-fluid{
      background:linear-gradient(135deg, rgba(var(--su-accent-rgb),.10), rgba(var(--su-accent-rgb),.02));
      border:1px solid var(--card-border);
      border-radius:var(--su-radius);
      padding:20px 24px;
  }
  .service-ui .content-header h1{
      display:flex; align-items:center; gap:12px;
      font-size:1.45rem; font-weight:800; color:var(--text-primary); margin:0;
  }
  .service-ui .content-header h1 .page-icon{
      width:42px; height:42px; border-radius:12px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      background:var(--su-accent); color:#04160e; font-size:1.05rem;
      box-shadow:0 6px 16px -4px rgba(var(--su-accent-rgb),.5);
  }
  .service-ui .page-subtitle{ color:var(--text-muted); font-size:.85rem; margin:6px 0 0 54px; }
  .service-ui .content-header .breadcrumb{ background:transparent; margin:0; padding:0; }

  /* ---------- Cards ---------- */
  .service-ui .card{
      border:1px solid var(--card-border); border-radius:var(--su-radius);
      box-shadow:0 1px 2px rgba(0,0,0,.04), 0 10px 24px -18px rgba(0,0,0,.18);
      overflow:hidden;
  }
  .service-ui .card-header{
      background:var(--card-bg); border-bottom:1px solid var(--card-border);
      display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
      padding:16px 20px;
  }
  .service-ui .card-title{
      font-size:1.05rem; font-weight:700; color:var(--text-primary);
      display:flex; align-items:center; gap:8px; margin:0;
  }
  .service-ui .card-title i{ color:var(--su-accent); }
  .service-ui .card-body{ padding:20px; }

  /* ---------- Buttons ---------- */
  .service-ui .btn{ border-radius:var(--su-radius-sm); font-weight:600; font-size:.85rem; letter-spacing:.01em; transition:all .18s ease; }
  .service-ui .btn-primary{ background:var(--su-accent); border-color:var(--su-accent); color:#04160e; }
  .service-ui .btn-primary:hover, .service-ui .btn-primary:focus{ background:#1fcf80; border-color:#1fcf80; color:#04160e; transform:translateY(-1px); box-shadow:0 6px 14px -6px rgba(var(--su-accent-rgb),.6); }
  .service-ui .btn-sm{ border-radius:8px; }
  .service-ui .action-btns .btn{ padding:.3rem .55rem; font-size:.8rem; margin-right:3px; }
  .service-ui .action-btns .btn:last-child{ margin-right:0; }
  .service-ui .btn-group .btn{ border-radius:8px !important; margin-right:3px; }

  /* ---------- Table ---------- */
  .service-ui .table{ margin-bottom:0; font-size:.9rem; }
  .service-ui .table thead th{
      background:var(--table-stripe); color:var(--text-muted); border-bottom:2px solid var(--table-border);
      font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; white-space:nowrap;
  }
  .service-ui .table td{ vertical-align:middle; border-color:var(--table-border); }
  .service-ui .table-hover tbody tr{ transition:background .15s ease; }
  .service-ui .table-hover tbody tr:hover{ background:rgba(var(--su-accent-rgb),.07) !important; }

  /* ---------- Badges ---------- */
  .service-ui .badge{ font-weight:600; padding:.42em .8em; border-radius:20px; letter-spacing:.02em; }
  .service-ui .status-badge{ font-size:.72rem; }

  /* ---------- Forms ---------- */
  .service-ui .form-control{ border-radius:var(--su-radius-sm); border-color:var(--input-border); }
  .service-ui .form-control:focus{ border-color:var(--su-accent); box-shadow:0 0 0 .2rem rgba(var(--su-accent-rgb),.2); }
  .service-ui .form-group label{ font-weight:600; font-size:.85rem; color:var(--text-primary); }

  /* ---------- Modals ---------- */
  .service-ui .modal-content{ border:none; border-radius:18px; overflow:hidden; box-shadow:0 24px 60px -20px rgba(0,0,0,.4); }
  .service-ui .modal-header{ background:var(--modal-header-bg); color:var(--modal-header-color); border-bottom:none; padding:18px 22px; }
  .service-ui .modal-header .close{ color:var(--modal-header-color); opacity:.8; text-shadow:none; }
  .service-ui .modal-header .close:hover{ opacity:1; }
  .service-ui .modal-title{ font-weight:700; }
  .service-ui .modal-body{ padding:22px; }
  .service-ui .modal-footer{ border-top:1px solid var(--card-border); padding:14px 22px; }

  /* ---------- Alerts ---------- */
  .service-ui .alert{ border:none; border-left:4px solid; border-radius:10px; }
  .service-ui .alert-warning{ border-left-color:#f59e0b; }
  .service-ui .alert-danger{ border-left-color:#ef4444; }
  .service-ui .alert-success{ border-left-color:var(--su-accent); }
  .service-ui .alert-info{ border-left-color:#3b82f6; }

  /* ---------- Empty state ---------- */
  .service-ui .empty-state{ text-align:center; padding:40px 15px; color:var(--text-muted); }
  .service-ui .empty-state i{ font-size:2.6rem; margin-bottom:12px; opacity:.4; }

  /* ---------- Photo thumbnails ---------- */
  .service-ui .driver-photo, .service-ui .request-photo{
      box-shadow:0 0 0 2px var(--card-bg), 0 0 0 3px var(--card-border);
  }

/* =========================================================
   DARK MODE OVERRIDES — applied via body.dark-mode
   ========================================================= */
body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .card-header { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
body.dark-mode .table-hover tbody tr:hover { background: rgba(36,231,143,.10) !important; }
body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .alert-info { background: #1e2f3e !important; color: #93c5fd !important; }
body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; }
body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .dataTables_info, body.dark-mode .dataTables_paginate { color: var(--text-muted) !important; }

  /* ---------- Vehicle module extras ---------- */
  body.dark-mode .service-ui .status-badge{ color:#04160e; }
  </style>

</head>
<body class="hold-transition sidebar-mini service-ui">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_service.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><span class="page-icon"><i class="fas fa-car-side"></i></span>Vehicle Management</h1>
            <p class="page-subtitle">Manage the office motor pool — property records, plate numbers, and status.</p>
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
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
      <div class="modal-content">
        <form method="POST" action="service_vehicle.php">
          <div class="modal-header">
            <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
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
              </div>
              <div class="col-md-6">
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