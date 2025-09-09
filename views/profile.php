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

// Get the logged-in user's employee ID from session
$emp_id = $_SESSION['emp_id'] ?? null;

if (!$emp_id) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Employee ID not found in session. Please log in again.'
    ];
    header("Location: ../login.php");
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Handle password change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'All password fields are required.'
        ];
        header("Location: profile.php");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password and confirmation do not match.'
        ];
        header("Location: profile.php");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password must be at least 8 characters long.'
        ];
        header("Location: profile.php");
        exit();
    }
    
    // Get current password hash from database
    $query = "SELECT password FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'User not found.'
        ];
        header("Location: profile.php");
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Current password is incorrect.'
        ];
        header("Location: profile.php");
        exit();
    }
    
    // Hash new password and update database
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_query = "UPDATE employee SET password = ? WHERE emp_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bind_param("si", $hashed_password, $emp_id);
    
    if ($update_stmt->execute()) {
        // Notify administrators about password change
        notifyAdministratorsAboutPasswordChange($emp_id);
        
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Password changed successfully!'
        ];
    } else {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Failed to change password. Please try again.'
        ];
    }
    
    header("Location: profile.php");
    exit();
}

// Function to notify administrators about password change
function notifyAdministratorsAboutPasswordChange($emp_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user details
    $query = "SELECT first_name, last_name, id_number FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Get all administrators
    $query = "SELECT e.emp_id, e.email, e.first_name, e.last_name 
              FROM employee e 
              JOIN users u ON e.emp_id = u.emp_id 
              JOIN roles r ON u.role_id = r.role_id 
              WHERE r.role_name = 'Administrator' 
              AND e.email IS NOT NULL";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    
    // Store notification for each admin in database
    foreach ($admins as $admin) {
        $notification_message = "Password changed for {$user['first_name']} {$user['last_name']} (ID: {$user['id_number']})";
        $notification_type = "password_change";
        $is_read = 0;
        
        $insert_query = "INSERT INTO admin_notifications (admin_emp_id, message, type, is_read, created_at) 
                         VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bind_param("issi", $admin['emp_id'], $notification_message, $notification_type, $is_read);
        $insert_stmt->execute();
    }
    
    return count($admins) > 0;
}

// Main employee query for the logged-in user
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
        'message' => 'Employee profile not found'
    ];
    header("Location: dashboard.php");
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
    header("Location: profile.php");
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
    header("Location: profile.php");
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

// Check if user is manager office staff
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
  <title>AdminLTE 3 | My Profile</title>
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
    .password-toggle {
        cursor: pointer;
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
            <h1>My Profile</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">My Profile</li>
            </ol>
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
                  <li class="nav-item"><a class="nav-link" href="#password" data-toggle="tab">Change Password</a></li>
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
                                                        <a href="profile.php?delete_file=<?= urlencode($file['name']) ?>" 
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
                  
                  <div class="tab-pane" id="password">
                    <!-- Change Password Form -->
                    <div class="card card-primary">
                      <div class="card-header">
                        <h3 class="card-title">Change Password</h3>
                      </div>
                      <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        <div class="card-body">
                          <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="input-group">
                              <input type="password" class="form-control" id="current_password" name="current_password" required>
                              <div class="input-group-append">
                                <span class="input-group-text password-toggle" data-target="current_password">
                                  <i class="fas fa-eye"></i>
                                </span>
                              </div>
                            </div>
                          </div>
                          
                          <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-group">
                              <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                              <div class="input-group-append">
                                <span class="input-group-text password-toggle" data-target="new_password">
                                  <i class="fas fa-eye"></i>
                                </span>
                              </div>
                            </div>
                            <small class="text-muted">Password must be at least 8 characters long.</small>
                          </div>
                          
                          <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                              <div class="input-group-append">
                                <span class="input-group-text password-toggle" data-target="confirm_password">
                                  <i class="fas fa-eye"></i>
                                </span>
                              </div>
                            </div>
                          </div>
                          
                          <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                      </form>
                    </div>
                    
                    <!-- Forgot Password Link -->
                    <div class="card card-info">
                      <div class="card-header">
                        <h3 class="card-title">Forgot Password?</h3>
                      </div>
                      <div class="card-body">
                        <p>If you've forgotten your password, you can request a password reset.</p>
                        <a href="../views/forgot_password.php" class="btn btn-info">Reset Password</a>
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
  
  // Password toggle functionality
  $('.password-toggle').on('click', function() {
    const target = $(this).data('target');
    const input = $('#' + target);
    const icon = $(this).find('i');
    
    if (input.attr('type') === 'password') {
      input.attr('type', 'text');
      icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
      input.attr('type', 'password');
      icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
  });
  
  // File deletion confirmation
  $('.delete-file-btn').on('click', function(e) {
    e.preventDefault();
    const filename = $(this).data('filename');
    const deleteUrl = $(this).attr('href');
    
    Swal.fire({
      title: 'Are you sure?',
      text: `You are about to delete "${filename}". This action cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = deleteUrl;
      }
    });
  });
  
  // File preview functionality
  $('.view-file-btn').on('click', function() {
    const filePath = $(this).data('filepath');
    const fileType = $(this).data('filetype').toLowerCase();
    const modal = $('#filePreviewModal');
    const content = $('#filePreviewContent');
    const downloadBtn = $('#downloadPreviewBtn');
    
    // Set download link
    downloadBtn.attr('href', filePath);
    
    // Clear previous content
    content.empty();
    
    // Load content based on file type
    if (fileType === 'pdf') {
      content.html(`
        <embed src="${filePath}" type="application/pdf" width="100%" height="500px" />
      `);
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
      content.html(`
        <img src="${filePath}" class="img-fluid" alt="Preview">
      `);
    } else if (['word', 'excel', 'powerpoint'].includes(fileType)) {
      content.html(`
        <div class="text-center p-5">
          <i class="fas fa-file-${fileType === 'word' ? 'word' : fileType === 'excel' ? 'excel' : 'powerpoint'} fa-5x mb-3"></i>
          <p class="lead">Office documents cannot be previewed in the browser.</p>
          <p>Please download the file to view it.</p>
        </div>
      `);
    } else {
      content.html(`
        <div class="text-center p-5">
          <i class="fas fa-file fa-5x mb-3"></i>
          <p class="lead">This file type cannot be previewed in the browser.</p>
          <p>Please download the file to view it.</p>
        </div>
      `);
    }
    
    modal.modal('show');
  });
  
  // Initialize DataTables for files table
  if ($('#filesTable').length) {
    $('#filesTable').DataTable({
      "responsive": true,
      "autoWidth": false,
      "order": [[3, "desc"]],
      "columnDefs": [
        { "orderable": false, "targets": [4] }
      ]
    });
  }
});
</script>
</body>
</html>