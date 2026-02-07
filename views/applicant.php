<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

$module_name = 'Applicant Databank';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
  $module = $result->fetch_assoc();
  if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
    $_SESSION['error'] = "The $module_name module is currently under maintenance. Please try again later.";
    header("Location: ../unauthorized.php");
    exit();
  }
}

// Fetch positions for dropdown
$positions_query = "SELECT position_id, position_name FROM position ORDER BY position_name ASC";
$positions_stmt = $db->prepare($positions_query);
$positions_stmt->execute();
$positions_result = $positions_stmt->get_result();
$positions = [];
while ($row = $positions_result->fetch_assoc()) {
  $positions[] = $row;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  
  // Create uploads directory if it doesn't exist
  $upload_dir = '../uploads/applicants/';
  if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
  }
  
  if ($_POST['action'] === 'create') {
    try {
      $first_name = $_POST['first_name'];
      $middle_name = $_POST['middle_name'] ?? null;
      $last_name = $_POST['last_name'];
      $email = $_POST['email'];
      $phone_number = $_POST['phone_number'];
      $address = $_POST['address'];
      $position_applied = $_POST['position_applied'];
      $application_date = $_POST['application_date'];
      $status = $_POST['status'];
      $remarks = $_POST['remarks'] ?? null;
      
      // Handle file uploads
      $resume_file = null;
      $cover_letter_file = null;
      $other_documents = null;
      
      if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
        $file_ext = pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION);
        $resume_file = 'resume_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['resume_file']['tmp_name'], $upload_dir . $resume_file);
      }
      
      if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] === 0) {
        $file_ext = pathinfo($_FILES['cover_letter_file']['name'], PATHINFO_EXTENSION);
        $cover_letter_file = 'cover_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['cover_letter_file']['tmp_name'], $upload_dir . $cover_letter_file);
      }
      
      if (isset($_FILES['other_documents']) && $_FILES['other_documents']['error'] === 0) {
        $file_ext = pathinfo($_FILES['other_documents']['name'], PATHINFO_EXTENSION);
        $other_documents = 'other_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['other_documents']['tmp_name'], $upload_dir . $other_documents);
      }
      
      $stmt = $db->prepare("INSERT INTO applicant (first_name, middle_name, last_name, email, phone_number, address, position_applied, application_date, status, remarks, resume_file, cover_letter_file, other_documents) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sssssssssssss", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $position_applied, $application_date, $status, $remarks, $resume_file, $cover_letter_file, $other_documents);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Applicant added successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add applicant.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'update') {
    try {
      $applicant_id = $_POST['applicant_id'];
      $first_name = $_POST['first_name'];
      $middle_name = $_POST['middle_name'] ?? null;
      $last_name = $_POST['last_name'];
      $email = $_POST['email'];
      $phone_number = $_POST['phone_number'];
      $address = $_POST['address'];
      $position_applied = $_POST['position_applied'];
      $application_date = $_POST['application_date'];
      $status = $_POST['status'];
      $remarks = $_POST['remarks'] ?? null;
      
      // Get existing files
      $stmt = $db->prepare("SELECT resume_file, cover_letter_file, other_documents FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      
      $resume_file = $existing['resume_file'];
      $cover_letter_file = $existing['cover_letter_file'];
      $other_documents = $existing['other_documents'];
      
      // Handle file uploads
      if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
        if ($resume_file && file_exists($upload_dir . $resume_file)) {
          unlink($upload_dir . $resume_file);
        }
        $file_ext = pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION);
        $resume_file = 'resume_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['resume_file']['tmp_name'], $upload_dir . $resume_file);
      }
      
      if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] === 0) {
        if ($cover_letter_file && file_exists($upload_dir . $cover_letter_file)) {
          unlink($upload_dir . $cover_letter_file);
        }
        $file_ext = pathinfo($_FILES['cover_letter_file']['name'], PATHINFO_EXTENSION);
        $cover_letter_file = 'cover_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['cover_letter_file']['tmp_name'], $upload_dir . $cover_letter_file);
      }
      
      if (isset($_FILES['other_documents']) && $_FILES['other_documents']['error'] === 0) {
        if ($other_documents && file_exists($upload_dir . $other_documents)) {
          unlink($upload_dir . $other_documents);
        }
        $file_ext = pathinfo($_FILES['other_documents']['name'], PATHINFO_EXTENSION);
        $other_documents = 'other_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['other_documents']['tmp_name'], $upload_dir . $other_documents);
      }
      
      $stmt = $db->prepare("UPDATE applicant SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, position_applied=?, application_date=?, status=?, remarks=?, resume_file=?, cover_letter_file=?, other_documents=? WHERE applicant_id=?");
      $stmt->bind_param("sssssssssssssi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $position_applied, $application_date, $status, $remarks, $resume_file, $cover_letter_file, $other_documents, $applicant_id);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Applicant updated successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update applicant.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'delete') {
    try {
      $applicant_id = $_POST['applicant_id'];
      
      // Get file names before deleting
      $stmt = $db->prepare("SELECT resume_file, cover_letter_file, other_documents FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      $stmt->execute();
      $files = $stmt->get_result()->fetch_assoc();
      
      // Delete files
      if ($files['resume_file'] && file_exists($upload_dir . $files['resume_file'])) {
        unlink($upload_dir . $files['resume_file']);
      }
      if ($files['cover_letter_file'] && file_exists($upload_dir . $files['cover_letter_file'])) {
        unlink($upload_dir . $files['cover_letter_file']);
      }
      if ($files['other_documents'] && file_exists($upload_dir . $files['other_documents'])) {
        unlink($upload_dir . $files['other_documents']);
      }
      
      // Delete record
      $stmt = $db->prepare("DELETE FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Applicant deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete applicant.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'get') {
    try {
      $applicant_id = $_POST['applicant_id'];
      $stmt = $db->prepare("SELECT * FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
      } else {
        echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
}

// Fetch all applicants
$query = "SELECT * FROM applicant ORDER BY application_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$applicants = [];
while ($row = $result->fetch_assoc()) {
  $applicants[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Applicant Databank</title>
  <?php include '../includes/header.php'; ?>
  <style>
    /* CRITICAL Z-INDEX FIX FOR MODAL OVERLAP */
    .main-header.navbar, .main-header, nav.main-header, header.main-header {
        z-index: 1000 !important;
    }
    .main-sidebar, aside.main-sidebar {
        z-index: 999 !important;
    }
    .modal-backdrop {
        z-index: 1040 !important;
    }
    .modal {
        z-index: 1050 !important;
    }
    .modal-dialog {
        z-index: 1051 !important;
    }
    .modal-content {
        z-index: 1052 !important;
    }
    .select2-container, .select2-dropdown {
        z-index: 1055 !important;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Applicant Databank</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Applicant Databank</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Applicants</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addApplicantModal">
                  <i class="fas fa-plus"></i> Add Applicant
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="applicantsTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Position Applied</th>
                    <th>Application Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($applicants as $applicant): ?>
                  <tr>
                    <td><?= $applicant['applicant_id'] ?></td>
                    <td><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['middle_name'] . ' ' . $applicant['last_name']) ?></td>
                    <td><?= htmlspecialchars($applicant['email']) ?></td>
                    <td><?= htmlspecialchars($applicant['phone_number']) ?></td>
                    <td><?= htmlspecialchars($applicant['position_applied']) ?></td>
                    <td><?= date('M d, Y', strtotime($applicant['application_date'])) ?></td>
                    <td>
                      <?php
                      $status_colors = [
                        'Pending' => 'warning',
                        'For Review' => 'info',
                        'For Interview' => 'primary',
                        'Accepted' => 'success',
                        'Rejected' => 'danger'
                      ];
                      $color = $status_colors[$applicant['status']] ?? 'secondary';
                      ?>
                      <span class="badge badge-<?= $color ?>"><?= $applicant['status'] ?></span>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-info view-btn" data-id="<?= $applicant['applicant_id'] ?>">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $applicant['applicant_id'] ?>">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $applicant['applicant_id'] ?>">
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

    <?php include '../includes/mainfooter.php'; ?>
  </div>

  <!-- Add Applicant Modal -->
  <div class="modal fade" id="addApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addApplicantForm" enctype="multipart/form-data">
          <div class="modal-header bg-primary">
            <h5 class="modal-title">Add New Applicant</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>First Name <span class="text-danger">*</span></label>
                  <input type="text" name="first_name" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Middle Name</label>
                  <input type="text" name="middle_name" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Last Name <span class="text-danger">*</span></label>
                  <input type="text" name="last_name" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Phone Number <span class="text-danger">*</span></label>
                  <input type="text" name="phone_number" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Address <span class="text-danger">*</span></label>
              <textarea name="address" class="form-control" rows="2" required></textarea>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Position Applied <span class="text-danger">*</span></label>
                  <select name="position_applied" class="form-control select2" multiple="multiple" required>
                    <option value="">-- Select Position --</option>
                    <?php foreach ($positions as $position): ?>
                      <option value="<?= htmlspecialchars($position['position_name']) ?>">
                        <?= htmlspecialchars($position['position_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Application Date <span class="text-danger">*</span></label>
                  <input type="date" name="application_date" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Status <span class="text-danger">*</span></label>
              <select name="status" class="form-control" required>
                <option value="Pending">Pending</option>
                <option value="For Review">For Review</option>
                <option value="For Interview">For Interview</option>
                <option value="Accepted">Accepted</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            <hr>
            <h6>Upload Documents</h6>
            <div class="form-group">
              <label>Resume (PDF, DOC, DOCX - Max 5MB)</label>
              <div class="custom-file">
                <input type="file" name="resume_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
            </div>
            <div class="form-group">
              <label>Cover Letter (PDF, DOC, DOCX - Max 5MB)</label>
              <div class="custom-file">
                <input type="file" name="cover_letter_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
            </div>
            <div class="form-group">
              <label>Other Documents (PDF, DOC, DOCX - Max 5MB)</label>
              <div class="custom-file">
                <input type="file" name="other_documents" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Applicant</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Applicant Modal -->
  <div class="modal fade" id="editApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="editApplicantForm" enctype="multipart/form-data">
          <input type="hidden" name="applicant_id" id="edit_applicant_id">
          <div class="modal-header bg-warning">
            <h5 class="modal-title">Edit Applicant</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>First Name <span class="text-danger">*</span></label>
                  <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Middle Name</label>
                  <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Last Name <span class="text-danger">*</span></label>
                  <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Phone Number <span class="text-danger">*</span></label>
                  <input type="text" name="phone_number" id="edit_phone_number" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Address <span class="text-danger">*</span></label>
              <textarea name="address" id="edit_address" class="form-control" rows="2" required></textarea>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Position Applied <span class="text-danger">*</span></label>
                  <select name="position_applied" id="edit_position_applied" class="form-control select2" multiple="multiple" required>
                    <option value="">-- Select Position --</option>
                    <?php foreach ($positions as $position): ?>
                      <option value="<?= htmlspecialchars($position['position_name']) ?>">
                        <?= htmlspecialchars($position['position_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Application Date <span class="text-danger">*</span></label>
                  <input type="date" name="application_date" id="edit_application_date" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Status <span class="text-danger">*</span></label>
              <select name="status" id="edit_status" class="form-control" required>
                <option value="Pending">Pending</option>
                <option value="For Review">For Review</option>
                <option value="For Interview">For Interview</option>
                <option value="Accepted">Accepted</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
            </div>
            <hr>
            <h6>Upload Documents (Leave blank to keep existing files)</h6>
            <div class="form-group">
              <label>Resume</label>
              <div class="custom-file">
                <input type="file" name="resume_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
              <small class="form-text text-muted" id="current_resume"></small>
            </div>
            <div class="form-group">
              <label>Cover Letter</label>
              <div class="custom-file">
                <input type="file" name="cover_letter_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
              <small class="form-text text-muted" id="current_cover"></small>
            </div>
            <div class="form-group">
              <label>Other Documents</label>
              <div class="custom-file">
                <input type="file" name="other_documents" class="custom-file-input" accept=".pdf,.doc,.docx">
                <label class="custom-file-label">Choose file</label>
              </div>
              <small class="form-text text-muted" id="current_other"></small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-warning">Update Applicant</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Applicant Modal -->
  <div class="modal fade" id="viewApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info">
          <h5 class="modal-title">Applicant Details</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div id="viewApplicantContent"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
  $(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
      placeholder: "-- Please Select --",
           maximumSelectionLength: 1
    });
    
    // Initialize DataTable
    $('#applicantsTable').DataTable({
      responsive: true,
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });

    // Custom file input label
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').html(fileName);
    });

    // Add Applicant
    $('#addApplicantForm').on('submit', function(e) {
      e.preventDefault();
      let formData = new FormData(this);
      formData.append('action', 'create');

      $.ajax({
        url: 'applicant.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(() => {
              location.reload();
            });
          } else {
            Swal.fire('Error!', response.message, 'error');
          }
        },
        error: function() {
          Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
        }
      });
    });

    // Edit Button Click
    $('.edit-btn').on('click', function() {
      let applicantId = $(this).data('id');
      
      $.ajax({
        url: 'applicant.php',
        type: 'POST',
        data: { action: 'get', applicant_id: applicantId },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            let data = response.data;
            $('#edit_applicant_id').val(data.applicant_id);
            $('#edit_first_name').val(data.first_name);
            $('#edit_middle_name').val(data.middle_name);
            $('#edit_last_name').val(data.last_name);
            $('#edit_email').val(data.email);
            $('#edit_phone_number').val(data.phone_number);
            $('#edit_address').val(data.address);
            $('#edit_position_applied').val(data.position_applied).trigger('change');
            $('#edit_application_date').val(data.application_date);
            $('#edit_status').val(data.status);
            $('#edit_remarks').val(data.remarks);
            
            $('#current_resume').text(data.resume_file ? 'Current: ' + data.resume_file : 'No file uploaded');
            $('#current_cover').text(data.cover_letter_file ? 'Current: ' + data.cover_letter_file : 'No file uploaded');
            $('#current_other').text(data.other_documents ? 'Current: ' + data.other_documents : 'No file uploaded');
            
            $('#editApplicantModal').modal('show');
          }
        }
      });
    });

    // Update Applicant
    $('#editApplicantForm').on('submit', function(e) {
      e.preventDefault();
      let formData = new FormData(this);
      formData.append('action', 'update');

      $.ajax({
        url: 'applicant.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(() => {
              location.reload();
            });
          } else {
            Swal.fire('Error!', response.message, 'error');
          }
        }
      });
    });

    // View Button Click
    $('.view-btn').on('click', function() {
      let applicantId = $(this).data('id');
      
      $.ajax({
        url: 'applicant.php',
        type: 'POST',
        data: { action: 'get', applicant_id: applicantId },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            let data = response.data;
            let content = `
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Name:</strong> ${data.first_name} ${data.middle_name || ''} ${data.last_name}</p>
                  <p><strong>Email:</strong> ${data.email}</p>
                  <p><strong>Phone:</strong> ${data.phone_number}</p>
                  <p><strong>Address:</strong> ${data.address}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>Position Applied:</strong> ${data.position_applied}</p>
                  <p><strong>Application Date:</strong> ${new Date(data.application_date).toLocaleDateString()}</p>
                  <p><strong>Status:</strong> <span class="badge badge-info">${data.status}</span></p>
                  <p><strong>Remarks:</strong> ${data.remarks || 'N/A'}</p>
                </div>
              </div>
              <hr>
              <h6>Uploaded Documents:</h6>
              <ul>
                <li><strong>Resume:</strong> ${data.resume_file ? '<a href="../uploads/applicants/' + data.resume_file + '" target="_blank">' + data.resume_file + '</a>' : 'Not uploaded'}</li>
                <li><strong>Cover Letter:</strong> ${data.cover_letter_file ? '<a href="../uploads/applicants/' + data.cover_letter_file + '" target="_blank">' + data.cover_letter_file + '</a>' : 'Not uploaded'}</li>
                <li><strong>Other Documents:</strong> ${data.other_documents ? '<a href="../uploads/applicants/' + data.other_documents + '" target="_blank">' + data.other_documents + '</a>' : 'Not uploaded'}</li>
              </ul>
            `;
            $('#viewApplicantContent').html(content);
            $('#viewApplicantModal').modal('show');
          }
        }
      });
    });

    // Delete Button Click
    $('.delete-btn').on('click', function() {
      let applicantId = $(this).data('id');
      
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: 'applicant.php',
            type: 'POST',
            data: { action: 'delete', applicant_id: applicantId },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                Swal.fire('Deleted!', response.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', response.message, 'error');
              }
            }
          });
        }
      });
    });
  });
  </script>
</body>
</html>