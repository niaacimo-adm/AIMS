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

$module_name = 'Intern Databank';
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

// Fetch sections for dropdown
$sections_query = "SELECT section_id, section_name, section_code FROM section ORDER BY section_name ASC";
$sections_stmt = $db->prepare($sections_query);
$sections_stmt->execute();
$sections_result = $sections_stmt->get_result();
$sections = [];
while ($row = $sections_result->fetch_assoc()) {
  $sections[] = $row;
}

// Fetch unit sections for dropdown
$units_query = "SELECT u.unit_id, u.unit_name, u.unit_code, s.section_name 
                FROM unit_section u 
                LEFT JOIN section s ON u.section_id = s.section_id 
                ORDER BY u.unit_name ASC";
$units_stmt = $db->prepare($units_query);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
$unit_sections = [];
while ($row = $units_result->fetch_assoc()) {
  $unit_sections[] = $row;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  
  // Create uploads directory if it doesn't exist
  $upload_dir = '../uploads/interns/';
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
      $school = $_POST['school'];
      $course = $_POST['course'];
      $year_level = $_POST['year_level'];
      $department_assigned = $_POST['department_assigned'] ?? null;
      $supervisor_name = $_POST['supervisor_name'] ?? null;
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'] ?? null;
      $status = $_POST['status'];
      $performance_rating = $_POST['performance_rating'] ?? null;
      $number_of_hours = $_POST['number_of_hours'] ?? 500;
      $remarks = $_POST['remarks'] ?? null;
      
      // Handle file uploads
      $resume_file = null;
      $recommendation_letter = null;
      $school_endorsement = null;
      $other_documents = null;
      
      if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
        $file_ext = pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION);
        $resume_file = 'resume_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['resume_file']['tmp_name'], $upload_dir . $resume_file);
      }
      
      if (isset($_FILES['recommendation_letter']) && $_FILES['recommendation_letter']['error'] === 0) {
        $file_ext = pathinfo($_FILES['recommendation_letter']['name'], PATHINFO_EXTENSION);
        $recommendation_letter = 'recommendation_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['recommendation_letter']['tmp_name'], $upload_dir . $recommendation_letter);
      }
      
      if (isset($_FILES['school_endorsement']) && $_FILES['school_endorsement']['error'] === 0) {
        $file_ext = pathinfo($_FILES['school_endorsement']['name'], PATHINFO_EXTENSION);
        $school_endorsement = 'endorsement_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['school_endorsement']['tmp_name'], $upload_dir . $school_endorsement);
      }
      
      if (isset($_FILES['other_documents']) && $_FILES['other_documents']['error'] === 0) {
        $file_ext = pathinfo($_FILES['other_documents']['name'], PATHINFO_EXTENSION);
        $other_documents = 'other_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['other_documents']['tmp_name'], $upload_dir . $other_documents);
      }
      
      $stmt = $db->prepare("INSERT INTO intern (first_name, middle_name, last_name, email, phone_number, address, school, course, year_level, department_assigned, supervisor_name, start_date, end_date, status, performance_rating, number_of_hours, remarks, resume_file, recommendation_letter, school_endorsement, other_documents) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssssssssssssdisssss", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $school, $course, $year_level, $department_assigned, $supervisor_name, $start_date, $end_date, $status, $performance_rating, $number_of_hours, $remarks, $resume_file, $recommendation_letter, $school_endorsement, $other_documents);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Intern added successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add intern.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'update') {
    try {
      $intern_id = $_POST['intern_id'];
      $first_name = $_POST['first_name'];
      $middle_name = $_POST['middle_name'] ?? null;
      $last_name = $_POST['last_name'];
      $email = $_POST['email'];
      $phone_number = $_POST['phone_number'];
      $address = $_POST['address'];
      $school = $_POST['school'];
      $course = $_POST['course'];
      $year_level = $_POST['year_level'];
      $department_assigned = $_POST['department_assigned'] ?? null;
      $supervisor_name = $_POST['supervisor_name'] ?? null;
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'] ?? null;
      $status = $_POST['status'];
      $performance_rating = $_POST['performance_rating'] ?? null;
      $number_of_hours = $_POST['number_of_hours'] ?? 500;
      $remarks = $_POST['remarks'] ?? null;
      
      // Get existing files
      $stmt = $db->prepare("SELECT resume_file, recommendation_letter, school_endorsement, other_documents FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      
      $resume_file = $existing['resume_file'];
      $recommendation_letter = $existing['recommendation_letter'];
      $school_endorsement = $existing['school_endorsement'];
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
      
      if (isset($_FILES['recommendation_letter']) && $_FILES['recommendation_letter']['error'] === 0) {
        if ($recommendation_letter && file_exists($upload_dir . $recommendation_letter)) {
          unlink($upload_dir . $recommendation_letter);
        }
        $file_ext = pathinfo($_FILES['recommendation_letter']['name'], PATHINFO_EXTENSION);
        $recommendation_letter = 'recommendation_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['recommendation_letter']['tmp_name'], $upload_dir . $recommendation_letter);
      }
      
      if (isset($_FILES['school_endorsement']) && $_FILES['school_endorsement']['error'] === 0) {
        if ($school_endorsement && file_exists($upload_dir . $school_endorsement)) {
          unlink($upload_dir . $school_endorsement);
        }
        $file_ext = pathinfo($_FILES['school_endorsement']['name'], PATHINFO_EXTENSION);
        $school_endorsement = 'endorsement_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['school_endorsement']['tmp_name'], $upload_dir . $school_endorsement);
      }
      
      if (isset($_FILES['other_documents']) && $_FILES['other_documents']['error'] === 0) {
        if ($other_documents && file_exists($upload_dir . $other_documents)) {
          unlink($upload_dir . $other_documents);
        }
        $file_ext = pathinfo($_FILES['other_documents']['name'], PATHINFO_EXTENSION);
        $other_documents = 'other_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['other_documents']['tmp_name'], $upload_dir . $other_documents);
      }
      
      $stmt = $db->prepare("UPDATE intern SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, school=?, course=?, year_level=?, department_assigned=?, supervisor_name=?, start_date=?, end_date=?, status=?, performance_rating=?, number_of_hours=?, remarks=?, resume_file=?, recommendation_letter=?, school_endorsement=?, other_documents=? WHERE intern_id=?");
      $stmt->bind_param("ssssssssssssssdissksssi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $school, $course, $year_level, $department_assigned, $supervisor_name, $start_date, $end_date, $status, $performance_rating, $number_of_hours, $remarks, $resume_file, $recommendation_letter, $school_endorsement, $other_documents, $intern_id);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Intern updated successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update intern.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'delete') {
    try {
      $intern_id = $_POST['intern_id'];
      
      // Get file names before deleting
      $stmt = $db->prepare("SELECT resume_file, recommendation_letter, school_endorsement, other_documents FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      $stmt->execute();
      $files = $stmt->get_result()->fetch_assoc();
      
      // Delete files
      if ($files['resume_file'] && file_exists($upload_dir . $files['resume_file'])) {
        unlink($upload_dir . $files['resume_file']);
      }
      if ($files['recommendation_letter'] && file_exists($upload_dir . $files['recommendation_letter'])) {
        unlink($upload_dir . $files['recommendation_letter']);
      }
      if ($files['school_endorsement'] && file_exists($upload_dir . $files['school_endorsement'])) {
        unlink($upload_dir . $files['school_endorsement']);
      }
      if ($files['other_documents'] && file_exists($upload_dir . $files['other_documents'])) {
        unlink($upload_dir . $files['other_documents']);
      }
      
      // Delete record
      $stmt = $db->prepare("DELETE FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Intern deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete intern.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'get') {
    try {
      $intern_id = $_POST['intern_id'];
      $stmt = $db->prepare("SELECT * FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
      } else {
        echo json_encode(['success' => false, 'message' => 'Intern not found.']);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
}

// Fetch all interns
$query = "SELECT * FROM intern ORDER BY start_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$interns = [];
while ($row = $result->fetch_assoc()) {
  $interns[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Intern Databank</title>
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
              <h1 class="m-0">Intern Databank</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Intern Databank</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Interns</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addInternModal">
                  <i class="fas fa-plus"></i> Add Intern
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="internsTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>School</th>
                    <th>Course</th>
                    <th>Department</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($interns as $intern): ?>
                  <tr>
                    <td><?= $intern['intern_id'] ?></td>
                    <td><?= htmlspecialchars($intern['first_name'] . ' ' . $intern['middle_name'] . ' ' . $intern['last_name']) ?></td>
                    <td><?= htmlspecialchars($intern['email']) ?></td>
                    <td><?= htmlspecialchars($intern['school']) ?></td>
                    <td><?= htmlspecialchars($intern['course']) ?></td>
                    <td><?= htmlspecialchars($intern['department_assigned'] ?? 'N/A') ?></td>
                    <td><?= date('M d, Y', strtotime($intern['start_date'])) ?></td>
                    <td>
                      <?php
                      $status_colors = [
                        'Active' => 'success',
                        'Completed' => 'info',
                        'Terminated' => 'danger',
                        'On Hold' => 'warning'
                      ];
                      $color = $status_colors[$intern['status']] ?? 'secondary';
                      ?>
                      <span class="badge badge-<?= $color ?>"><?= $intern['status'] ?></span>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-info view-btn" data-id="<?= $intern['intern_id'] ?>" title="View Details">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $intern['intern_id'] ?>" title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $intern['intern_id'] ?>" title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                      <a href="generate_certificate.php?intern_id=<?= $intern['intern_id'] ?>" class="btn btn-sm btn-success" title="Generate Certificate" target="_blank">
                        <i class="fas fa-certificate"></i>
                      </a>
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

  <!-- Add Intern Modal -->
  <div class="modal fade" id="addInternModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addInternForm" enctype="multipart/form-data">
          <div class="modal-header bg-primary">
            <h5 class="modal-title">Add New Intern</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <h6 class="border-bottom pb-2">Personal Information</h6>
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
            
            <h6 class="border-bottom pb-2 mt-3">Academic Information</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>School <span class="text-danger">*</span></label>
                  <input type="text" name="school" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Course <span class="text-danger">*</span></label>
                  <input type="text" name="course" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Year Level <span class="text-danger">*</span></label>
              <select name="year_level" class="form-control" required>
                <option value="">Select Year Level</option>
                <option value="Senior HighSchool">Senior HighSchool</option>
                <option value="4th Year">4th Year</option>
                <option value="Graduate">Graduate</option>
              </select>
            </div>
            
            <h6 class="border-bottom pb-2 mt-3">Internship Details</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Department/Section Assigned</label>
                  <select name="department_assigned" class="form-control select2">
                    <option value="">-- Select Department --</option>
                    <optgroup label="Sections">
                      <?php foreach ($sections as $section): ?>
                        <option value="<?= htmlspecialchars($section['section_name']) ?>">
                          <?= htmlspecialchars($section['section_name']) ?> (<?= htmlspecialchars($section['section_code']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Unit Sections">
                      <?php foreach ($unit_sections as $unit): ?>
                        <option value="<?= htmlspecialchars($unit['unit_name']) ?>">
                          <?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_code']) ?>)
                          <?php if ($unit['section_name']): ?>
                            - <?= htmlspecialchars($unit['section_name']) ?>
                          <?php endif; ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Supervisor Name</label>
                  <input type="text" name="supervisor_name" class="form-control">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>Start Date <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>End Date</label>
                  <input type="date" name="end_date" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Status <span class="text-danger">*</span></label>
                  <select name="status" class="form-control" required>
                    <option value="Active">Active</option>
                    <option value="Completed">Completed</option>
                    <option value="Terminated">Terminated</option>
                    <option value="On Hold">On Hold</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Performance Rating (0-5)</label>
                  <input type="number" name="performance_rating" class="form-control" step="0.01" min="0" max="5">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Number of Hours</label>
                  <input type="number" name="number_of_hours" class="form-control" min="0" placeholder="500">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            
            <h6 class="border-bottom pb-2 mt-3">Upload Documents</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Resume (PDF, DOC, DOCX - Max 5MB)</label>
                  <div class="custom-file">
                    <input type="file" name="resume_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Recommendation Letter (PDF, DOC, DOCX - Max 5MB)</label>
                  <div class="custom-file">
                    <input type="file" name="recommendation_letter" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>School Endorsement (PDF, DOC, DOCX - Max 5MB)</label>
                  <div class="custom-file">
                    <input type="file" name="school_endorsement" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Other Documents (PDF, DOC, DOCX - Max 5MB)</label>
                  <div class="custom-file">
                    <input type="file" name="other_documents" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Intern</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Intern Modal -->
  <div class="modal fade" id="editInternModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="editInternForm" enctype="multipart/form-data">
          <input type="hidden" name="intern_id" id="edit_intern_id">
          <div class="modal-header bg-warning">
            <h5 class="modal-title">Edit Intern</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <h6 class="border-bottom pb-2">Personal Information</h6>
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
            
            <h6 class="border-bottom pb-2 mt-3">Academic Information</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>School <span class="text-danger">*</span></label>
                  <input type="text" name="school" id="edit_school" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Course <span class="text-danger">*</span></label>
                  <input type="text" name="course" id="edit_course" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Year Level <span class="text-danger">*</span></label>
              <select name="year_level" id="edit_year_level" class="form-control" required>
                <option value="">Select Year Level</option>
                <option value="Senior HighSchool">Senior HighSchool</option>
                <option value="4th Year">4th Year</option>
                <option value="Graduate">Graduate</option>
              </select>
            </div>
            
            <h6 class="border-bottom pb-2 mt-3">Internship Details</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Department/Section Assigned</label>
                  <select name="department_assigned" id="edit_department_assigned" class="form-control select2">
                    <option value="">-- Select Department --</option>
                    <optgroup label="Sections">
                      <?php foreach ($sections as $section): ?>
                        <option value="<?= htmlspecialchars($section['section_name']) ?>">
                          <?= htmlspecialchars($section['section_name']) ?> (<?= htmlspecialchars($section['section_code']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Unit Sections">
                      <?php foreach ($unit_sections as $unit): ?>
                        <option value="<?= htmlspecialchars($unit['unit_name']) ?>">
                          <?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_code']) ?>)
                          <?php if ($unit['section_name']): ?>
                            - <?= htmlspecialchars($unit['section_name']) ?>
                          <?php endif; ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Supervisor Name</label>
                  <input type="text" name="supervisor_name" id="edit_supervisor_name" class="form-control">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>Start Date <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>End Date</label>
                  <input type="date" name="end_date" id="edit_end_date" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Status <span class="text-danger">*</span></label>
                  <select name="status" id="edit_status" class="form-control" required>
                    <option value="Active">Active</option>
                    <option value="Completed">Completed</option>
                    <option value="Terminated">Terminated</option>
                    <option value="On Hold">On Hold</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Performance Rating (0-5)</label>
                  <input type="number" name="performance_rating" id="edit_performance_rating" class="form-control" step="0.01" min="0" max="5">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Number of Hours</label>
                  <input type="number" name="number_of_hours" id="edit_number_of_hours" class="form-control" min="0" placeholder="500">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Remarks</label>
              <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
            </div>
            
            <h6 class="border-bottom pb-2 mt-3">Upload Documents (Leave blank to keep existing files)</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Resume</label>
                  <div class="custom-file">
                    <input type="file" name="resume_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                  <small class="form-text text-muted" id="current_resume"></small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Recommendation Letter</label>
                  <div class="custom-file">
                    <input type="file" name="recommendation_letter" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                  <small class="form-text text-muted" id="current_recommendation"></small>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>School Endorsement</label>
                  <div class="custom-file">
                    <input type="file" name="school_endorsement" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                  <small class="form-text text-muted" id="current_endorsement"></small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Other Documents</label>
                  <div class="custom-file">
                    <input type="file" name="other_documents" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                  <small class="form-text text-muted" id="current_other"></small>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-warning">Update Intern</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Intern Modal -->
  <div class="modal fade" id="viewInternModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info">
          <h5 class="modal-title">Intern Details</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div id="viewInternContent"></div>
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
      theme: 'bootstrap4',
      width: '100%'
    });
    
    // Initialize DataTable
    $('#internsTable').DataTable({
      responsive: true,
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });

    // Custom file input label
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').html(fileName);
    });

    // Add Intern
    $('#addInternForm').on('submit', function(e) {
      e.preventDefault();
      let formData = new FormData(this);
      formData.append('action', 'create');

      $.ajax({
        url: 'intern.php',
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
      let internId = $(this).data('id');
      
      $.ajax({
        url: 'intern.php',
        type: 'POST',
        data: { action: 'get', intern_id: internId },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            let data = response.data;
            $('#edit_intern_id').val(data.intern_id);
            $('#edit_first_name').val(data.first_name);
            $('#edit_middle_name').val(data.middle_name);
            $('#edit_last_name').val(data.last_name);
            $('#edit_email').val(data.email);
            $('#edit_phone_number').val(data.phone_number);
            $('#edit_address').val(data.address);
            $('#edit_school').val(data.school);
            $('#edit_course').val(data.course);
            $('#edit_year_level').val(data.year_level);
            $('#edit_department_assigned').val(data.department_assigned).trigger('change');
            $('#edit_supervisor_name').val(data.supervisor_name);
            $('#edit_start_date').val(data.start_date);
            $('#edit_end_date').val(data.end_date);
            $('#edit_status').val(data.status);
            $('#edit_performance_rating').val(data.performance_rating);
            $('#edit_number_of_hours').val(data.number_of_hours);
            $('#edit_remarks').val(data.remarks);
            
            $('#current_resume').text(data.resume_file ? 'Current: ' + data.resume_file : 'No file uploaded');
            $('#current_recommendation').text(data.recommendation_letter ? 'Current: ' + data.recommendation_letter : 'No file uploaded');
            $('#current_endorsement').text(data.school_endorsement ? 'Current: ' + data.school_endorsement : 'No file uploaded');
            $('#current_other').text(data.other_documents ? 'Current: ' + data.other_documents : 'No file uploaded');
            
            $('#editInternModal').modal('show');
          }
        }
      });
    });

    // Update Intern
    $('#editInternForm').on('submit', function(e) {
      e.preventDefault();
      let formData = new FormData(this);
      formData.append('action', 'update');

      $.ajax({
        url: 'intern.php',
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
      let internId = $(this).data('id');
      
      $.ajax({
        url: 'intern.php',
        type: 'POST',
        data: { action: 'get', intern_id: internId },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            let data = response.data;
            let content = `
              <h6 class="border-bottom pb-2">Personal Information</h6>
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Name:</strong> ${data.first_name} ${data.middle_name || ''} ${data.last_name}</p>
                  <p><strong>Email:</strong> ${data.email}</p>
                  <p><strong>Phone:</strong> ${data.phone_number}</p>
                  <p><strong>Address:</strong> ${data.address}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>School:</strong> ${data.school}</p>
                  <p><strong>Course:</strong> ${data.course}</p>
                  <p><strong>Year Level:</strong> ${data.year_level}</p>
                </div>
              </div>
              
              <h6 class="border-bottom pb-2 mt-3">Internship Details</h6>
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Department:</strong> ${data.department_assigned || 'N/A'}</p>
                  <p><strong>Supervisor:</strong> ${data.supervisor_name || 'N/A'}</p>
                  <p><strong>Start Date:</strong> ${new Date(data.start_date).toLocaleDateString()}</p>
                  <p><strong>End Date:</strong> ${data.end_date ? new Date(data.end_date).toLocaleDateString() : 'N/A'}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>Status:</strong> <span class="badge badge-info">${data.status}</span></p>
                  <p><strong>Performance Rating:</strong> ${data.performance_rating || 'N/A'}</p>
                  <p><strong>Remarks:</strong> ${data.remarks || 'N/A'}</p>
                </div>
              </div>
              
              <h6 class="border-bottom pb-2 mt-3">Uploaded Documents</h6>
              <ul>
                <li><strong>Resume:</strong> ${data.resume_file ? '<a href="../uploads/interns/' + data.resume_file + '" target="_blank">' + data.resume_file + '</a>' : 'Not uploaded'}</li>
                <li><strong>Recommendation Letter:</strong> ${data.recommendation_letter ? '<a href="../uploads/interns/' + data.recommendation_letter + '" target="_blank">' + data.recommendation_letter + '</a>' : 'Not uploaded'}</li>
                <li><strong>School Endorsement:</strong> ${data.school_endorsement ? '<a href="../uploads/interns/' + data.school_endorsement + '" target="_blank">' + data.school_endorsement + '</a>' : 'Not uploaded'}</li>
                <li><strong>Other Documents:</strong> ${data.other_documents ? '<a href="../uploads/interns/' + data.other_documents + '" target="_blank">' + data.other_documents + '</a>' : 'Not uploaded'}</li>
              </ul>
            `;
            $('#viewInternContent').html(content);
            $('#viewInternModal').modal('show');
          }
        }
      });
    });

    // Delete Button Click
    $('.delete-btn').on('click', function() {
      let internId = $(this).data('id');
      
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
            url: 'intern.php',
            type: 'POST',
            data: { action: 'delete', intern_id: internId },
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

  $(document).on('change', 'select[name="department_assigned"], #edit_department_assigned', function() {
      var selectedDepartment = $(this).val();
      var targetInput = $(this).closest('form').find('input[name="supervisor_name"], #edit_supervisor_name');
      
      if (!selectedDepartment) {
          targetInput.val('');
          return;
      }
      
      // AJAX request to get supervisor
      $.ajax({
          url: 'get_supervisor.php', // Create this file
          type: 'POST',
          data: { 
              department_name: selectedDepartment,
              type: $(this).find('option:selected').closest('optgroup').attr('label') === 'Sections' ? 'section' : 'unit'
          },
          dataType: 'json',
          success: function(response) {
              if (response.success && response.supervisor) {
                  targetInput.val(response.supervisor);
              } else {
                  targetInput.val('');
              }
          },
          error: function() {
              targetInput.val('');
          }
      });
  });
  </script>
</body>
</html>