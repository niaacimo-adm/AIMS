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
    /* Modern Profile Styles */
    .profile-container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    /* Admin Theme Colors */
    .profile-header {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
      padding: 30px 0;
      border-radius: 10px 10px 0 0;
      margin-bottom: 20px;
    }
    
    .profile-avatar-xl {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      border: 5px solid rgba(255,255,255,0.4);
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
      object-fit: cover;
      object-position: center;
    }

    @media (max-width: 768px) {
      .profile-avatar-xl {
        width: 180px;
        height: 180px;
      }
    }

    @media (min-width: 1200px) {
      .profile-avatar {
        width: 250px;
        height: 250px;
      }
    }

    .profile-info-card {
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      border: none;
      margin-bottom: 10px;
      overflow: hidden;
    }
    
    .profile-info-card .card-header {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      border-bottom: 1px solid #e3e6f0;
      font-weight: 600;
      padding: 15px 20px;
      color: white;
    }
    
    .profile-tabs .nav-link {
      border-radius: 8px 8px 0 0;
      padding: 12px 20px;
      font-weight: 500;
      color: #6c757d;
      transition: all 0.3s;
    }
    
    .profile-tabs .nav-link.active {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
      border-bottom: 3px solid rgba(255,255,255,0.5);
    }
    
    .profile-tabs .nav-link:hover:not(.active) {
      background: rgba(67, 97, 238, 0.1);
      color: #4361ee;
    }
    
    .tab-content {
      background: #fff;
      border-radius: 0 0 10px 10px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    
    .info-table {
      width: 100%;
    }
    
    .info-table th {
      width: 30%;
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      font-weight: 600;
      padding: 12px 15px;
      color: white;
    }
    
    .info-table td {
      padding: 12px 15px;
    }
    
    .file-icon {
      font-size: 1.5rem;
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
      padding: 12px;
      border-bottom: 1px solid #eee;
      transition: background-color 0.2s;
    }
    
    .file-item:hover {
      background-color: #f8f9fa;
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
      color: #4361ee;
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
      border-radius: 8px;
    }
    
    .leadership-item {
      margin-bottom: 10px;
      padding: 12px;
      background-color: white;
      border-radius: 8px;
      border-left: 4px solid #4361ee;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .leadership-title {
      font-weight: bold;
      margin-bottom: 10px;
      color: #495057;
    }
    
    .focal-person-item {
      border-left-color: #6f42c1;
    }
    
    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 500;
    }
    
    /* Modern button styles */
    .btn-modern {
      border-radius: 6px;
      font-weight: 500;
      padding: 8px 16px;
      transition: all 0.3s;
    }
    
    .btn-modern-primary {
      background: linear-gradient(135deg, #4361ee, #3f37c9) !important;
      border: none;
      color: white;
    }
    
    .btn-modern-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
      background: linear-gradient(135deg, #4361ee, #3f37c9) !important;
    }
    
    /* Card headers */
    .card-primary .card-header {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
    }
    
    .card-info .card-header {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
    }
    
    /* Content header theming */
    .content-header h1 {
      color: #4361ee;
    }
    
    /* Button theming */
    .btn-primary {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      border-color: #4361ee;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      border-color: #4361ee;
      transform: translateY(-1px);
    }
    
    .btn-info {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      border-color: #4361ee;
    }
    
    .btn-info:hover {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      border-color: #4361ee;
    }
    
    /* Badge theming */
    .badge-warning {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
    }
    
    /* Icon theming */
    .text-primary {
      color: #4361ee !important;
    }
    
    /* Modal header theming */
    .modal-header {
      background: linear-gradient(135deg, #4361ee, #3f37c9);
      color: white;
    }
    
    /* Sidebar Navigation Styles */
    .sidebar-nav-container {
    margin-bottom: 20px;
    }

    .sidebar-nav-pills {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .sidebar-nav-pills .nav-link {
    border-radius: 6px;
    padding: 12px 15px;
    font-weight: 500;
    color: #495057;
    margin-bottom: 5px;
    transition: all 0.3s;
    border: 1px solid transparent;
    background-color: #ffffff; /* Add white background for inactive pills */
    }

    .sidebar-nav-pills .nav-link.active {
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    color: white;
    border-color: #4361ee;
    box-shadow: 0 2px 5px rgba(67, 97, 238, 0.3);
    }

    .sidebar-nav-pills .nav-link:not(.active) {
    background: rgba(67, 98, 238, 0.36);
    color: #4361ee;
    border-color: rgba(67, 97, 238, 0.2);
    }

    .sidebar-nav-pills .nav-link i {
    margin-right: 8px;
    width: 20px;
    text-align: center;
    }
    
    .sidebar-tab-content {
      background: #fff;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      min-height: 300px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .profile-header {
        padding: 20px 0;
      }
      
      .profile-avatar {
        width: 100px;
        height: 100px;
      }
      
      .info-table th, .info-table td {
        display: block;
        width: 100%;
      }
      
      .info-table th {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
        margin-top: 10px;
        color: white;
      }
      
      .sidebar-nav-pills .nav-link {
        padding: 10px 12px;
        font-size: 0.9rem;
      }
    }

    /* Fix for table overlapping footer */
    .tab-content {
        min-height: auto;
        overflow: visible;
    }

    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    #filesTable {
        margin-bottom: 0 !important;
    }

    .main-tabs{
      background-color: #4362eeb6 !important;
      border-radius: 8px;
      overflow: hidden;
    }
    
    .main-tabs .nav-link {
      color: rgba(255,255,255,0.8) !important;
      border-radius: 0;
      padding: 12px 20px;
      transition: all 0.3s;
    }
    
    .main-tabs .nav-link.active {
      background: #4361ee !important;
      color: white !important;
      border-bottom: 3px solid rgba(255,255,255,0.5);
    }
    
    .main-tabs .nav-link:hover:not(.active) {
      background: rgba(255,255,255,0.1) !important;
      color: white !important;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Employee Profile</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="emp.list.php">Employees</a></li>
              <li class="breadcrumb-item active">Employee Profile</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid profile-container">
        <div class="row">
          <div class="col-md-12">
            
        <div class="profile-header text-center mb-4">
          <div class="row justify-content-center">
            <div class="col-md-10">
              <div class="d-flex align-items-center justify-content-center flex-column flex-md-row">
                <div class="mr-md-4 mb-3 mb-md-0">
                  <?php 
                  $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                  if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                    <img class="profile-avatar-xl"
                        src="<?= $imagePath ?>"
                        alt="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>">
                  <?php else: ?>
                    <img class="profile-avatar-xl"
                        src="../dist/img/user-default.png"
                        alt="Default user image">
                  <?php endif; ?>
                </div>
                <div class="text-center text-md-left text-white">
                  <h2 class="mb-2 display-5 font-weight-bold"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                  <p class="mb-1 h4"><?= htmlspecialchars($employee['position_name']) ?></p>
                  <p class="mb-0 h5"><?= htmlspecialchars($employee['id_number']) ?></p>  
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-4">
            <!-- Sidebar Navigation Pills -->
            <div class="sidebar-nav-container">
              <div class="sidebar-nav-pills">
                <ul class="nav nav-pills flex-column" id="sidebarTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="profile-summary-tab" data-toggle="pill" href="#profile-summary" role="tab" aria-controls="profile-summary" aria-selected="true">
                      <i class="fas fa-user-circle mr-2"></i> Profile Summary
                    </a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link" id="employment-status-tab" data-toggle="pill" href="#employment-status" role="tab" aria-controls="employment-status" aria-selected="false">
                      <i class="fas fa-briefcase mr-2"></i> Employment Status
                    </a>
                  </li>
                  <?php if ($employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary)): ?>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link" id="leadership-roles-tab" data-toggle="pill" href="#leadership-roles" role="tab" aria-controls="leadership-roles" aria-selected="false">
                      <i class="fas fa-user-shield mr-2"></i> Leadership Roles
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </div>
              
              <!-- Sidebar Tab Content -->
              <div class="tab-content sidebar-tab-content mt-3" id="sidebarTabContent">
                <!-- Profile Summary Tab -->
                <div class="tab-pane fade show active" id="profile-summary" role="tabpanel" aria-labelledby="profile-summary-tab">
                  <div class="mb-3">
                    <strong><i class="fas fa-envelope mr-2 text-primary"></i> Email</strong>
                    <p class="text-muted mb-2"><?= htmlspecialchars($employee['email']) ?></p>
                  </div>
                  
                  <div class="mb-3">
                    <strong><i class="fas fa-phone mr-2 text-primary"></i> Phone</strong>
                    <p class="text-muted mb-2"><?= htmlspecialchars($employee['phone_number']) ?></p>
                  </div>
                  
                  <div class="mb-3">
                    <strong><i class="fas fa-briefcase mr-2 text-primary"></i> Position</strong>
                    <p class="text-muted mb-2"><?= htmlspecialchars($employee['position_name']) ?></p>
                  </div>
                  
                  <div class="mb-3">
                    <strong><i class="fas fa-building mr-2 text-primary"></i> Office</strong>
                    <p class="text-muted mb-0"><?= htmlspecialchars($employee['office_name']) ?></p>
                    <?php if (!empty($current_assignment['section_name'])): ?>
                      <small class="text-muted d-block">Section: <?= htmlspecialchars($current_assignment['section_name']) ?></small>
                      <?php if (!empty($current_assignment['unit_name'])): ?>
                        <small class="text-muted d-block">Unit: <?= htmlspecialchars($current_assignment['unit_name']) ?></small>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                  
                  <?php if ($is_manager_office_staff): ?>
                    <div class="text-center mt-3">
                      <span class="badge badge-warning p-2">
                        <i class="fas fa-star mr-1"></i> Manager's Office Staff
                      </span>
                    </div>
                  <?php endif; ?>
                  
                  <hr>
                  
                  <div class="text-center">
                    <a href="emp.edit.php?emp_id=<?= $emp_id ?>" class="btn btn-modern btn-modern-primary">
                      <i class="fas fa-edit mr-1"></i> Edit Profile
                    </a>
                  </div>
                </div>
                
                <!-- Employment Status Tab -->
                <div class="tab-pane fade" id="employment-status" role="tabpanel" aria-labelledby="employment-status-tab">
                  <div class="mb-3">
                    <strong><i class="fas fa-user-tag mr-2 text-primary"></i> Employment Status</strong>
                    <p class="mt-1">
                      <span class="status-badge" style="background-color: <?= htmlspecialchars($employee['employment_color']) ?>; 
                        color: <?= (hexdec(substr($employee['employment_color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                        <?= htmlspecialchars($employee['employment_status']) ?>
                      </span>
                    </p>
                  </div>
                  
                  <div class="mb-3">
                    <strong><i class="fas fa-file-signature mr-2 text-primary"></i> Appointment Status</strong>
                    <p class="mt-1">
                      <span class="status-badge" style="background-color: <?= htmlspecialchars($employee['appointment_color']) ?>; 
                        color: <?= (hexdec(substr($employee['appointment_color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                        <?= htmlspecialchars($employee['appointment_status']) ?>
                      </span>
                    </p>
                  </div>
                </div>
                
                <!-- Leadership Roles Tab -->
                <?php if ($employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary)): ?>
                <div class="tab-pane fade" id="leadership-roles" role="tabpanel" aria-labelledby="leadership-roles-tab">
                  <?php if ($employee['is_office_manager']): ?>
                    <div class="leadership-item mb-3">
                      <div class="leadership-title">
                        <i class="fas fa-building mr-2"></i> Division Manager
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
                      <div class="leadership-item mb-2">
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
                      <div class="leadership-item mb-2">
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
                      <div class="leadership-item mb-2">
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
              </div>
            </div>
          </div>
          
          <div class="col-md-8">
            <!-- Main Content Tabs -->
            <div class="card">
              <div class="sidebar-nav-pills">
                <div class="main-tabs">
                    <ul class="nav nav-pills" id="mainTabs">
                    <li class="nav-item"><a class="nav-link active" href="#about" data-toggle="tab">About Me</a></li>
                    <li class="nav-item"><a class="nav-link" href="#file" data-toggle="tab">My Files</a></li>
                    </ul>
                </div>
              </div>
              
              <div class="card-body">
                <div class="tab-content">
                  <div class="active tab-pane" id="about">
                    <h4 class="mb-4">Personal Information</h4>
                    <div class="row">
                      <div class="col-md-6">
                        <table class="table table-bordered info-table">
                          <tr>
                            <th>Full Name</th>
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
                        <table class="table table-bordered info-table">
                          <tr>
                            <th>Email</th>
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
                    <div class="card mt-4">
                      <div class="card-header">
                        <h3 class="card-title">Uploaded Files</h3>
                      </div>
                      <div class="card-body">
                        <?php if (empty($uploadedFiles)): ?>
                          <div class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                            <p>No files uploaded yet.</p>
                          </div>
                        <?php else: ?>
                          <div class="table-responsive">
                            <table id="filesTable" class="table table-hover">
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
                                      <span class="file-name" style="max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($file['name']) ?>
                                      </span>
                                    </td>
                                    <td><?= $fileType ?></td>
                                    <td><?= formatSizeUnits($file['size']) ?></td>
                                    <td><?= date('M d, Y H:i', $file['modified']) ?></td>
                                    <td>
                                      <div class="btn-group btn-group-sm">
                                        <a href="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>" 
                                           class="btn btn-info" target="_blank" download>
                                          <i class="fas fa-download"></i>
                                        </a>
                                        <a href="emp.profile.php?emp_id=<?= $emp_id ?>&delete_file=<?= urlencode($file['name']) ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this file?')">
                                          <i class="fas fa-trash"></i>
                                        </a>
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
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
    <?php include '../includes/mainfooter.php'; ?>

  <?php include '../includes/footer.php'; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables for files table
    if ($('#filesTable').length) {
        $('#filesTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "pageLength": 10,
            "language": {
                "search": "Search files:",
                "lengthMenu": "Show _MENU_ files per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ files",
                "infoEmpty": "No files available",
                "infoFiltered": "(filtered from _MAX_ total files)"
            }
        });
    }
    
    // File input label update
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // Toast notification
    <?php if (isset($_SESSION['toast'])): ?>
        $(document).Toasts('create', {
            class: 'bg-<?= $_SESSION['toast']['type'] ?>',
            title: '<?= ucfirst($_SESSION['toast']['type']) ?>',
            body: '<?= $_SESSION['toast']['message'] ?>',
            autohide: true,
            delay: 3000
        });
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>