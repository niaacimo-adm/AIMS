<?php
require_once '../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get employee ID from URL
$emp_id = $_GET['emp_id'] ?? null;
if (!$emp_id) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Employee ID is required'
    ];
    header("Location: emp.list.php");
    exit();
}

// Main employee query
$query = "SELECT 
            e.*,
            es.status_name as employment_status,
            es.color as employment_color,
            o.office_name,
            o.manager_emp_id as office_manager_id,
            m.first_name as office_manager_first_name,
            m.last_name as office_manager_last_name,
            p.position_name,
            ap.status_name as appointment_status,
            ap.color as appointment_color,
            (SELECT COUNT(*) FROM office WHERE manager_emp_id = e.emp_id) as is_office_manager
          FROM employee e
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN employee m ON o.manager_emp_id = m.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          WHERE e.emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Employee not found'
    ];
    header("Location: emp.list.php");
    exit();
}

// Get all sections where this employee is head
$query = "SELECT 
            s.section_id, 
            s.section_name, 
            s.section_code,
            o.office_name
          FROM section s
          LEFT JOIN office o ON s.office_id = o.office_id
          WHERE s.head_emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$section_result = $stmt->get_result();
$sections_as_head = [];
while ($row = $section_result->fetch_assoc()) {
    $sections_as_head[] = $row;
}

// Get all unit sections where this employee is head
$query = "SELECT 
            us.unit_id, 
            us.unit_name, 
            us.unit_code,
            s.section_name,
            s.section_id
          FROM unit_section us
          LEFT JOIN section s ON us.section_id = s.section_id
          WHERE us.head_emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$unit_result = $stmt->get_result();
$units_as_head = [];
while ($row = $unit_result->fetch_assoc()) {
    $units_as_head[] = $row;
}

// Get current section/unit assignment (if any)
$query = "SELECT 
            s.section_name,
            s.section_id,
            s.head_emp_id as section_head_id,
            sh.first_name as section_head_first_name,
            sh.last_name as section_head_last_name,
            us.unit_name,
            us.unit_id,
            us.head_emp_id as unit_head_id,
            uh.first_name as unit_head_first_name,
            uh.last_name as unit_head_last_name
          FROM employee e
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN employee sh ON s.head_emp_id = sh.emp_id
          LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
          LEFT JOIN employee uh ON us.head_emp_id = uh.emp_id
          WHERE e.emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$current_assignment = $stmt->get_result()->fetch_assoc();

// Handle file upload for "My Files" section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['employee_file'])) {
    try {
        $targetDir = "../dist/files/employees/{$emp_id}/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $fileName = basename($_FILES["employee_file"]["name"]);
        $targetFile = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Check if file already exists
        if (file_exists($targetFile)) {
            throw new Exception("File already exists.");
        }
        
        if ($_FILES["employee_file"]["size"] > 200 * 1024 * 1024) {
            throw new Exception("File is too large (max 200MB).");
        }
        
        // Allow certain file formats
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Only PDF, DOC, XLS, PPT, JPG, PNG files are allowed.");
        }
        
        if (move_uploaded_file($_FILES["employee_file"]["tmp_name"], $targetFile)) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'File uploaded successfully!'
            ];
        } else {
            throw new Exception("Error uploading file.");
        }
    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => $e->getMessage()
        ];
    }
    header("Location: emp.profile.php?emp_id=" . $emp_id);
    exit();
}

// Handle file deletion
if (isset($_GET['delete_file'])) {
    $filePath = "../dist/files/employees/{$emp_id}/" . basename($_GET['delete_file']);
    if (file_exists($filePath)) {
        unlink($filePath);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'File deleted successfully!'
        ];
    } else {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'File not found!'
        ];
    }
    header("Location: emp.profile.php?emp_id=" . $emp_id);
    exit();
}

// Get list of uploaded files
$uploadDir = "../dist/files/employees/{$emp_id}/";
$uploadedFiles = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $uploadDir . $file;
            $uploadedFiles[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'type' => mime_content_type($filePath)
            ];
        }
    }
}

// Format file size
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

$query = "SELECT ss.section_id, s.section_name 
          FROM section_secretaries ss
          JOIN section s ON ss.section_id = s.section_id
          WHERE ss.emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$secretary_result = $stmt->get_result();
$sections_as_secretary = [];
while ($row = $secretary_result->fetch_assoc()) {
    $sections_as_secretary[] = $row;
}
// Replace the existing $is_manager_office_staff check with this:
$is_manager_office_staff = false;
$query = "SELECT COUNT(*) as is_manager_staff FROM managers_office_staff WHERE emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['is_manager_staff'] > 0) {
    $is_manager_office_staff = true;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Employee Profile</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .file-icon {
      font-size: 2rem;
      margin-right: 10px;
    }
    .pdf-icon { color: #d63031; }
    .word-icon { color: #2b579a; }
    .excel-icon { color: #217346; }
    .ppt-icon { color: #d24726; }
    .image-icon { color: #6c5ce7; }
    .file-item {
      display: flex;
      align-items: center;
      padding: 10px;
      border-bottom: 1px solid #eee;
    }
    .file-info {
      flex-grow: 1;
    }
    .file-actions {
      margin-left: 10px;
    }
    .manager-link {
        color: #495057;
        text-decoration: none;
        transition: color 0.2s;
    }
    .manager-link:hover {
        color: #007bff;
        text-decoration: underline;
    }
    .badge-you {
        font-size: 0.7em;
        vertical-align: middle;
    }
    .leadership-section {
        margin-top: 15px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }
    .leadership-item {
        margin-bottom: 10px;
        padding: 10px;
        background-color: white;
        border-radius: 5px;
        border-left: 4px solid #007bff;
    }
    .leadership-title {
        font-weight: bold;
        margin-bottom: 10px;
    }
    .focal-person-item {
        border-left-color: #6f42c1;
    }
  </style>
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
            <h1>Employee Profile</h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">
            <!-- Profile Image -->
            <div class="card card-primary card-outline">
              <div class="card-body box-profile">
                <div class="text-center">
                  <?php 
                  $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                  if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                    <img class="profile-user-img img-fluid img-circle"
                         src="<?= $imagePath ?>"
                         alt="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>">
                  <?php else: ?>
                    <img class="profile-user-img img-fluid img-circle"
                         src="../dist/img/user-default.png"
                         alt="Default user image">
                  <?php endif; ?>
                </div>

                <h3 class="profile-username text-center"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>

                <p class="text-muted text-center"><?= htmlspecialchars($employee['position_name']) ?></p>

                <ul class="list-group list-group-unbordered mb-3">
                  <li class="list-group-item">
                    <b>Employee ID</b> <a class="float-right"><?= htmlspecialchars($employee['id_number']) ?></a>
                  </li>
                  <li class="list-group-item">
                    <b>Email</b> <a class="float-right"><?= htmlspecialchars($employee['email']) ?></a>
                  </li>
                  <li class="list-group-item">
                    <b>Phone</b> <a class="float-right"><?= htmlspecialchars($employee['phone_number']) ?></a>
                  </li>
                </ul>
                <?php if ($is_manager_office_staff): ?>
                    <div class="text-center mt-2">
                        <span class="badge badge-warning">
                             Manager's Office Staff
                        </span>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="text-center">
                  <a href="emp.edit.php?emp_id=<?= $emp_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                  </a>
                </div>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
            
            <!-- Status Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Employment Status</h3>
                </div>
                <div class="card-body">
                    <strong><i class="fas fa-briefcase mr-1"></i> Position</strong>
                    <p class="text-muted"><?= htmlspecialchars($employee['position_name']) ?></p>
                    <hr>
                    
                      <strong><i class="fas fa-building mr-1"></i> Office</strong>
                      <p class="text-muted">
                          <?= htmlspecialchars($employee['office_name']) ?>
                          <?php if (!empty($current_assignment['section_name'])): ?>
                              <small class="d-block">Section: <?= htmlspecialchars($current_assignment['section_name']) ?>
                                  <?php if (!empty($current_assignment['unit_name'])): ?>
                                      <small class="d-block">Unit: <?= htmlspecialchars($current_assignment['unit_name']) ?></small>
                                  <?php endif; ?>
                              </small>
                          <?php endif; ?>
                      </p>

                    <?php if (!empty($current_assignment['section_name'])): ?>
             
                        <?php if (!empty($current_assignment['unit_name'])): ?>
                            <small class="d-block">Unit: <?= htmlspecialchars($current_assignment['unit_name']) ?>
                                <?php if (!empty($current_assignment['unit_head_first_name'])): ?>
                                    (Head: <a href="emp.profile.php?emp_id=<?= $current_assignment['unit_head_id'] ?>" class="manager-link">
                                        <?= htmlspecialchars($current_assignment['unit_head_first_name'] . ' ' . $current_assignment['unit_head_last_name']) ?>
                                        <?php if ($current_assignment['unit_head_id'] == $emp_id): ?>
                                            <span class="badge badge-warning badge-you">(You)</span>
                                        <?php endif; ?>
                                    </a>)
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head)): ?>
                    <hr>
                    <div class="leadership-section">
                        <strong><i class="fas fa-user-shield mr-1"></i> Leadership Roles</strong>
                        <?php if ($employee['is_office_manager']): ?>
                            <div class="leadership-item">
                                <div class="leadership-title">
                                    <i class="fas fa-building mr-2"></i> Office Manager
                                </div>
                                <div class="text-muted">
                                    Manages <?= htmlspecialchars($employee['office_name']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($sections_as_head)): ?>
                            <div class="leadership-title mt-3">
                                <i class="fas fa-users mr-2"></i> Section Head Of:
                            </div>
                            <?php foreach ($sections_as_head as $section): ?>
                                <div class="leadership-item">
                                    <div>
                                        <strong><?= htmlspecialchars($section['section_name']) ?></strong>
                                        <small class="text-muted">(<?= htmlspecialchars($section['section_code']) ?>)</small>
                                    </div>
                                    <div class="text-muted">
                                        Office: <?= htmlspecialchars($section['office_name']) ?>
                                    </div>
                                    <div class="mt-2">
                                        <a href="sections.php?edit=<?= $section['section_id'] ?>" class="btn btn-xs btn-info">
                                            <i class="fas fa-edit"></i> Manage Section
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($sections_as_secretary)): ?>
                          <div class="leadership-title mt-3">
                              <i class="fas fa-user-secret mr-2"></i> Focal Person Of:
                          </div>
                          <?php foreach ($sections_as_secretary as $section): ?>
                              <div class="leadership-item">
                                  <div>
                                      <strong><?= htmlspecialchars($section['section_name']) ?></strong>
                                  </div>
                                  <div class="mt-2">
                                      <a href="sections.php?edit=<?= $section['section_id'] ?>" class="btn btn-xs btn-info">
                                          <i class="fas fa-edit"></i> Manage Section
                                      </a>
                                  </div>
                              </div>
                          <?php endforeach; ?>
                      <?php endif; ?>
                      
                        <?php if (!empty($units_as_head)): ?>
                            <div class="leadership-title mt-3">
                                <i class="fas fa-users mr-2"></i> Unit Head Of:
                            </div>
                            <?php foreach ($units_as_head as $unit): ?>
                                <div class="leadership-item">
                                    <div>
                                        <strong><?= htmlspecialchars($unit['unit_name']) ?></strong>
                                        <small class="text-muted">(<?= htmlspecialchars($unit['unit_code']) ?>)</small>
                                    </div>
                                    <div class="text-muted">
                                        Parent Section: <?= htmlspecialchars($unit['section_name']) ?>
                                    </div>
                                    <div class="mt-2">
                                        <a href="sections.php?edit_unit=<?= $unit['unit_id'] ?>" class="btn btn-xs btn-info">
                                            <i class="fas fa-edit"></i> Manage Unit
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <strong><i class="fas fa-user-tag mr-1"></i> Employment Status</strong>
                    <p class="text-muted">
                        <span class="badge" style="background-color: <?= htmlspecialchars($employee['employment_color']) ?>; 
                            color: <?= (hexdec(substr($employee['employment_color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                            <?= htmlspecialchars($employee['employment_status']) ?>
                        </span>
                    </p>
                    <hr>
                    
                    <strong><i class="fas fa-file-signature mr-1"></i> Appointment Status</strong>
                    <p class="text-muted">
                        <span class="badge" style="background-color: <?= htmlspecialchars($employee['appointment_color']) ?>; 
                            color: <?= (hexdec(substr($employee['appointment_color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                            <?= htmlspecialchars($employee['appointment_status']) ?>
                        </span>
                    </p>
                    
                </div>
            </div>
          </div>
          <!-- /.col -->
          <div class="col-md-9">
            <div class="card">
              <div class="card-header p-2">
                <ul class="nav nav-pills">
                  <li class="nav-item"><a class="nav-link active" href="#about" data-toggle="tab">About Me</a></li>
                  <li class="nav-item"><a class="nav-link" href="#file" data-toggle="tab">My Files</a></li>
                </ul>
              </div><!-- /.card-header -->
              <div class="card-body">
                <div class="tab-content">
                  <div class="active tab-pane" id="about">
                    <!-- About Me Content -->
                    <div class="post">
                        <span class="username">
                          <h3>Personal Information</h3>
                        </span>
                      
                      <div class="row">
                        <div class="col-md-6">
                          <table class="table table-bordered">
                            <tr>
                              <th width="30%">Full Name</th>
                              <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name'] . ' ' . $employee['ext_name']) ?></td>
                            </tr>
                            <tr>
                              <th>Gender</th>
                              <td><?= htmlspecialchars($employee['gender']) ?></td>
                            </tr>
                            <tr>
                              <th>Birthday</th>
                              <td><?= htmlspecialchars($employee['bday']) ?></td>
                            </tr>
                          </table>
                        </div>
                        <div class="col-md-6">
                          <table class="table table-bordered">
                            <tr>
                              <th width="30%">Email</th>
                              <td><?= htmlspecialchars($employee['email']) ?></td>
                            </tr>
                            <tr>
                              <th>Phone</th>
                              <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                            </tr>
                            <tr>
                              <th>Address</th>
                              <td><?= htmlspecialchars($employee['address']) ?></td>
                            </tr>
                          </table>
                        </div>
                        
                      </div>
                    </div>
                  </div>
                  <!-- /.tab-pane -->
                  
                  <div class="tab-pane" id="file">
                    <!-- File Upload Form -->
                    <div class="card card-primary">
                      <div class="card-header">
                        <h3 class="card-title">Upload New File</h3>
                      </div>
                      <form method="post" enctype="multipart/form-data">
                        <div class="card-body">
                          <div class="form-group">
                            <label for="employee_file">Select File</label>
                            <div class="input-group">
                              <div class="custom-file">
                                <input type="file" class="custom-file-input" id="employee_file" name="employee_file" required>
                                <label class="custom-file-label" for="employee_file">Choose file</label>
                              </div>
                              <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Upload</button>
                              </div>
                            </div>
                            <small class="text-muted">Max file size: 200MB. Allowed types: PDF, DOC, XLS, PPT, JPG, PNG</small>
                          </div>
                        </div>
                      </form>
                    </div>
                    <!-- File List -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Uploaded Files</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($uploadedFiles)): ?>
                                <div class="p-3 text-center text-muted">No files uploaded yet.</div>
                            <?php else: ?>
                                <table id="filesTable" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Modified</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploadedFiles as $file): ?>
                                            <?php 
                                            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                            $iconClass = 'fa-file';
                                            $fileType = 'File';
                                            
                                            if (in_array($fileExt, ['pdf'])) {
                                                $iconClass = 'fa-file-pdf pdf-icon';
                                                $fileType = 'PDF';
                                            } elseif (in_array($fileExt, ['doc', 'docx'])) {
                                                $iconClass = 'fa-file-word word-icon';
                                                $fileType = 'Word';
                                            } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                                                $iconClass = 'fa-file-excel excel-icon';
                                                $fileType = 'Excel';
                                            } elseif (in_array($fileExt, ['ppt', 'pptx'])) {
                                                $iconClass = 'fa-file-powerpoint ppt-icon';
                                                $fileType = 'PowerPoint';
                                            } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $iconClass = 'fa-file-image image-icon';
                                                $fileType = 'Image';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="fas <?= $iconClass ?> mr-2"></i>
                                                    <?= htmlspecialchars($file['name']) ?>
                                                </td>
                                                <td><?= $fileType ?></td>
                                                <td><?= formatSizeUnits($file['size']) ?></td>
                                                <td><?= date('M d, Y H:i', $file['modified']) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>" 
                                                        class="btn btn-sm btn-primary" 
                                                        title="Download"
                                                        download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="emp.profile.php?emp_id=<?= $emp_id ?>&delete_file=<?= urlencode($file['name']) ?>" 
                                                          class="btn btn-sm btn-danger delete-file-btn" 
                                                          data-filename="<?= htmlspecialchars($file['name']) ?>" 
                                                          title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-info view-file-btn" 
                                                                title="Preview"
                                                                data-filepath="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>"
                                                                data-filetype="<?= $fileType ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preview Modal -->
                    <div class="modal fade" id="filePreviewModal" tabindex="-1" role="dialog" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="filePreviewModalLabel">File Preview</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body" id="filePreviewContent">
                                    <!-- Content will be loaded here -->
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <a id="downloadPreviewBtn" href="#" class="btn btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>
                  <!-- /.tab-pane -->
                </div>
                <!-- /.tab-content -->
              </div><!-- /.card-body -->
            </div>
            <!-- /.card -->
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- SweetAlert Toast Notification -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = <?php echo json_encode($_SESSION['toast']); ?>;
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: toast.type,
        title: toast.message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<!-- bs-custom-file-input -->
<script src="../plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script>
$(function () {
  bsCustomFileInput.init();
});
</script>
<script>
$(function () {
    // Initialize DataTable
    $('#filesTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[3, 'desc']], // Sort by modified date by default
        columnDefs: [
            { responsivePriority: 1, targets: 0 }, // File name
            { responsivePriority: 2, targets: -1 }, // Actions
            { width: '30%', targets: 0 }, // File name column width
            { width: '10%', targets: 1 }, // Type column width
            { width: '15%', targets: 2 }, // Size column width
            { width: '20%', targets: 3 }, // Modified column width
            { width: '25%', targets: -1, orderable: false } // Actions column
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search files...",
            lengthMenu: "Show _MENU_ files per page",
            zeroRecords: "No files found",
            info: "Showing _START_ to _END_ of _TOTAL_ files",
            infoEmpty: "No files available",
            infoFiltered: "(filtered from _MAX_ total files)"
        }
    });

    // File preview handler
    $('.view-file-btn').on('click', function() {
        const filePath = $(this).data('filepath');
        const fileType = $(this).data('filetype').toLowerCase();
        const modal = $('#filePreviewModal');
        const downloadBtn = $('#downloadPreviewBtn');
        
        // Set download link
        downloadBtn.attr('href', filePath);
        
        // Clear previous content
        $('#filePreviewContent').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
        
        // Show modal
        modal.modal('show');
        
        // Load content based on file type
        if (fileType === 'image') {
            $('#filePreviewContent').html(`<img src="${filePath}" class="img-fluid" alt="Preview">`);
        } else if (fileType === 'pdf') {
            $('#filePreviewContent').html(`
                <embed src="${filePath}#toolbar=0&navpanes=0&scrollbar=0" 
                       type="application/pdf" 
                       width="100%" 
                       height="600px" />
            `);
        } else {
            // For other file types, show a message with download option
            $('#filePreviewContent').html(`
                <div class="text-center py-5">
                    <i class="fas fa-file fa-5x mb-3"></i>
                    <h4>Preview not available for this file type</h4>
                    <p>Please download the file to view it</p>
                </div>
            `);
        }
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.delete-file-btn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent the default link behavior

            const href = this.getAttribute('href');
            const filename = this.getAttribute('data-filename');

            Swal.fire({
                title: 'Are you sure?',
                text: `Delete the file "${filename}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href; // Proceed with deletion
                }
            });
        });
    });
});
</script>

</body>
</html>