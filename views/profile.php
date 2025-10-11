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


// Enhanced theme detection
$current_module = 'admin'; // default

// Priority 1: Check cookie (set by sidebar)
if (isset($_COOKIE['current_module'])) {
    $current_module = $_COOKIE['current_module'];
} 
// Priority 2: Check session theme
elseif (isset($_SESSION['current_theme'])) {
    $current_module = $_SESSION['current_theme'];
}
// Priority 3: Fallback to referer detection
elseif (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    if (strpos($referer, 'service') !== false) $current_module = 'service';
    if (strpos($referer, 'inventory') !== false) $current_module = 'inventory';
    if (strpos($referer, 'file_management') !== false) $current_module = 'file';
}

// Store in session for consistency
$_SESSION['current_theme'] = $current_module;

// Load the appropriate sidebar
switch ($current_module) {
    case 'service':
        include '../includes/sidebar_service.php';
        break;
    case 'inventory':
        include '../includes/sidebar_inventory.php';
        break;
    case 'file':
        include '../includes/sidebar_file.php';
        break;
    default:
        include '../includes/sidebar.php';
        break;
}
$current_theme = $current_module;

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
        echo "<script>window.location.href = 'profile.php';</script>";
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password and confirmation do not match.'
        ];
        echo "<script>window.location.href = 'profile.php';</script>";
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password must be at least 8 characters long.'
        ];
        echo "<script>window.location.href = 'profile.php';</script>";
        exit();
    }
    
    // Get current password hash from users table (not employee table)
    $query = "SELECT u.password 
              FROM users u 
              WHERE u.employee_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'User account not found.'
        ];
        echo "<script>window.location.href = 'profile.php';</script>";
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Current password is incorrect.'
        ];
        echo "<script>window.location.href = 'profile.php';</script>";
        exit();
    }
    
    // Hash new password and update database
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_query = "UPDATE users SET password = ? WHERE employee_id = ?";
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
    
    echo "<script>window.location.href = 'profile.php';</script>";
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
    
    // Get all administrators - FIXED: Changed r.role_id to r.id
    $query = "SELECT e.emp_id, e.email, e.first_name, e.last_name 
              FROM employee e 
              JOIN users u ON e.emp_id = u.employee_id 
              JOIN user_roles r ON u.role_id = r.id  -- CHANGED: r.role_id to r.id
              WHERE r.name = 'Administrator' 
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['employee_files'])) {
    $uploadSuccess = 0;
    $uploadErrors = [];
    
    try {
        $targetDir = "../dist/files/employees/{$emp_id}/";
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception("Could not create upload directory.");
            }
        }
        
        // Check if directory is writable
        if (!is_writable($targetDir)) {
            throw new Exception("Upload directory is not writable.");
        }
        
        // Process each file
        foreach ($_FILES['employee_files']['name'] as $key => $name) {
            if ($_FILES['employee_files']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = basename($name);
                $targetFile = $targetDir . $fileName;
                $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                
                // Check if file already exists
                if (file_exists($targetFile)) {
                    $uploadErrors[] = "File '{$fileName}' already exists.";
                    continue;
                }
                
                if ($_FILES['employee_files']["size"][$key] > 200 * 1024 * 1024) {
                    $uploadErrors[] = "File '{$fileName}' is too large (max 200MB).";
                    continue;
                }
                
                // Allow certain file formats
                $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    $uploadErrors[] = "File '{$fileName}' type not allowed. Only PDF, DOC, XLS, PPT, JPG, PNG files are allowed.";
                    continue;
                }
                
                if (move_uploaded_file($_FILES["employee_files"]["tmp_name"][$key], $targetFile)) {
                    $uploadSuccess++;
                } else {
                    $uploadErrors[] = "Error uploading file '{$fileName}'. Please try again.";
                }
            } else {
                $uploadErrors[] = "Error with file '{$name}'. Upload error code: " . $_FILES['employee_files']['error'][$key];
            }
        }
        
        // Prepare toast message
        if ($uploadSuccess > 0) {
            $message = "{$uploadSuccess} file(s) uploaded successfully!";
            if (!empty($uploadErrors)) {
                $message .= " " . count($uploadErrors) . " file(s) failed to upload.";
            }
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => $message
            ];
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => "No files were uploaded. " . implode(" ", $uploadErrors)
            ];
        }
        
    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    echo "<script>window.location.href = 'profile.php';</script>";
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
    echo "<script>window.location.href = 'profile.php';</script>";
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
    /* Modern Profile Styles */
    .profile-container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    /* Dynamic Theme Colors */
    <?php
    $theme_colors = [
        'admin' => [
            'primary' => 'linear-gradient(135deg, #4361ee, #3f37c9)',
            'secondary' => 'linear-gradient(135deg, #007bff, #6610f2)',
            'sidebar' => '#2a528eff',
            'tabs' => '#2a528eff',
            'header' => 'linear-gradient(135deg, #007bff, #6610f2)',
            'button' => 'linear-gradient(135deg, #007bff, #6610f2)',
            'accent' => '#007bff'
        ],
        'service' => [
            'primary' => 'linear-gradient(135deg, #ffc107, #fd7e14)',
            'secondary' => 'linear-gradient(135deg, #ffc107, #fd7e14)',
            'sidebar' => '#5a3e00',
            'tabs' => '#5a3e00',
            'header' => 'linear-gradient(135deg, #ffc107, #fd7e14)',
            'button' => 'linear-gradient(135deg, #ffc107, #fd7e14)',
            'accent' => '#ffc107'
        ],
        'inventory' => [
            'primary' => 'linear-gradient(135deg, #28a745, #20c997)',
            'secondary' => 'linear-gradient(135deg, #28a745, #20c997)',
            'sidebar' => '#153021',
            'tabs' => '#153021',
            'header' => 'linear-gradient(135deg, #28a745, #20c997)',
            'button' => 'linear-gradient(135deg, #28a745, #20c997)',
            'accent' => '#28a745'
        ],
        'file' => [
            'primary' => 'linear-gradient(135deg, #800020, #5a0a1d)',
            'secondary' => 'linear-gradient(135deg, #800020, #5a0a1d)',
            'sidebar' => '#3a0a17',
            'tabs' => '#3a0a17',
            'header' => 'linear-gradient(135deg, #800020, #5a0a1d)',
            'button' => 'linear-gradient(135deg, #800020, #5a0a1d)',
            'accent' => '#800020'
        ]
    ];
    
    $theme = $theme_colors[$current_module] ?? $theme_colors['admin'];
    ?>
    
    .profile-header {
      background: <?= $theme['header'] ?>;
      color: white;
      padding: 30px 0;
      border-radius: 10px 10px 0 0;
      margin-bottom: 20px;
    }
    
    /* Extra Large Avatar Option */
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

    /* For extra large screens */
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
      background: <?= $theme['primary'] ?>;
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
      background: <?= $theme['primary'] ?>;
      color: white;
      border-bottom: 3px solid rgba(255,255,255,0.5);
    }
    
    .profile-tabs .nav-link:hover:not(.active) {
      background: rgba(2, 119, 243, 0.1);
      color: <?= $theme['accent'] ?>;
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
      background: <?= $theme['primary'] ?>;
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
      color: <?= $theme['accent'] ?>;
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
      border-left: 4px solid <?= $theme['accent'] ?>;
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
    
    .password-toggle {
      cursor: pointer;
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
      background: <?= $theme['button'] ?> !important;
      border: none;
      color: white;
    }
    
    .btn-modern-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0,123,255,0.3);
      background: <?= $theme['primary'] ?> !important;
    }
    
    /* Card headers */
    .card-primary .card-header {
      background: <?= $theme['primary'] ?>;
      color: white;
    }
    
    .card-info .card-header {
      background: <?= $theme['primary'] ?>;
      color: white;
    }
    
    /* Breadcrumb theming */
    .breadcrumb {
      background-color: transparent;
    }
    
    .breadcrumb-item.active {
      color: <?= $theme['accent'] ?>;
    }
    
    .breadcrumb-item a {
      color: #6c757d;
      text-decoration: none;
    }
    
    .breadcrumb-item a:hover {
      color: <?= $theme['accent'] ?>;
    }
    
    /* Content header theming */
    .content-header h1 {
      color: <?= $theme['accent'] ?>;
    }
    
    /* Sidebar navigation theming */
    .sidebar-nav{
      background-color: <?= $theme['sidebar'] ?> !important;
      border-radius: 8px;
      overflow: hidden;
    }
    
    .sidebar-nav .nav-link {
      color: rgba(255,255,255,0.8) !important;
      border-radius: 0;
      padding: 12px 20px;
      transition: all 0.3s;
    }
    
    .sidebar-nav .nav-link.active {
      background: <?= $theme['primary'] ?> !important;
      color: white !important;
      border-left: 4px solid rgba(255,255,255,0.5);
    }
    
    .sidebar-nav .nav-link:hover:not(.active) {
      background: rgba(255,255,255,0.1) !important;
      color: white !important;
    }
    
    .sidebar-nav .nav-link i {
      margin-right: 8px;
      width: 20px;
      text-align: center;
    }
    
    /* Main tabs theming */
    .main-tabs{
      background-color: <?= $theme['tabs'] ?> !important;
      border-radius: 8px 8px 0 0;
      overflow: hidden;
    }
    
    .main-tabs .nav-link {
      color: rgba(255,255,255,0.8) !important;
      border-radius: 0;
      padding: 12px 20px;
      transition: all 0.3s;
    }
    
    .main-tabs .nav-link.active {
      background: <?= $theme['primary'] ?> !important;
      color: white !important;
      border-bottom: 3px solid rgba(255,255,255,0.5);
    }
    
    .main-tabs .nav-link:hover:not(.active) {
      background: rgba(255,255,255,0.1) !important;
      color: white !important;
    }
    
    /* Button theming */
    .btn-primary {
      background: <?= $theme['button'] ?>;
      border-color: <?= $theme['accent'] ?>;
    }
    
    .btn-primary:hover {
      background: <?= $theme['primary'] ?>;
      border-color: <?= $theme['accent'] ?>;
      transform: translateY(-1px);
    }
    
    .btn-info {
      background: <?= $theme['primary'] ?>;
      border-color: <?= $theme['accent'] ?>;
    }
    
    .btn-info:hover {
      background: <?= $theme['button'] ?>;
      border-color: <?= $theme['accent'] ?>;
    }
    
    /* Badge theming */
    .badge-warning {
      background: <?= $theme['primary'] ?>;
      color: white;
    }
    
    /* Icon theming */
    .text-primary {
      color: <?= $theme['accent'] ?> !important;
    }
    
    /* Modal header theming */
    .modal-header {
      background: <?= $theme['primary'] ?>;
      color: white;
    }
    
    /* Table action buttons */
    .btn-group .btn {
      border-radius: 4px;
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
        background: <?= $theme['primary'] ?>;
        margin-top: 10px;
        color: white;
      }
      
      .sidebar-nav, .main-tabs {
        border-radius: 8px;
        margin-bottom: 15px;
      }
    }
    
    /* Animation for theme transitions */
    .profile-header, .sidebar-nav, .main-tabs, .btn-modern-primary {
      transition: all 0.5s ease-in-out;
    }

    /* Add this to your existing CSS section */

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
.password-match {
    font-size: 0.9rem;
    font-weight: 500;
}

.password-strength {
    height: 5px;
    margin-top: 5px;
    border-radius: 5px;
    transition: all 0.3s;
}

.progress-bar {
    border-radius: 5px;
}

.password-hint {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
}

  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
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
    </div>

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
                    <img class="profile-avatar"
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
            <!-- Sidebar Navigation -->
            <div class="sidebar-nav">
              <ul class="nav nav-pills flex-column" id="sidebarTabs">
                <li class="nav-item">
                  <a class="nav-link active" href="#profile-summary" data-toggle="tab">
                    <i class="fas fa-user-circle mr-2"></i> Profile Summary
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="#employment-status" data-toggle="tab">
                    <i class="fas fa-briefcase mr-2"></i> Employment Status
                  </a>
                </li>
                <?php if ($employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary)): ?>
                <li class="nav-item">
                  <a class="nav-link" href="#leadership-roles" data-toggle="tab">
                    <i class="fas fa-user-shield mr-2"></i> Leadership Roles
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </div>
            
            <!-- Sidebar Content -->
            <div class="tab-content">
              <!-- Profile Summary Tab -->
              <div class="tab-pane active" id="profile-summary">
                <div class="card profile-info-card">
                  <div class="card-body">
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
                </div>
              </div>
              
              <!-- Employment Status Tab -->
              <div class="tab-pane" id="employment-status">
                <div class="card profile-info-card">
                  <div class="card-body">
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
                </div>
              </div>
              
              <!-- Leadership Roles Tab -->
              <?php if ($employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary)): ?>
              <div class="tab-pane" id="leadership-roles">
                <div class="card profile-info-card">
                  <div class="card-body">
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
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="col-md-8">
            <!-- Main Content Tabs -->
            <div class="main-tabs-container">
              <div class="main-tabs">
                <ul class="nav nav-pills" id="mainTabs">
                  <li class="nav-item">
                    <a class="nav-link active" href="#about" data-toggle="tab">
                      <i class="fas fa-user mr-1"></i> About Me
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#file" data-toggle="tab">
                      <i class="fas fa-file mr-1"></i> My Files
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#password" data-toggle="tab">
                      <i class="fas fa-key mr-1"></i> Change Password
                    </a>
                  </li>
                </ul>
              </div>
              
              <div class="tab-content">
                <!-- About Me Tab -->
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
                
                <!-- My Files Tab -->
                <div class="tab-pane" id="file">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">My Files</h5>
                            
                            <div class="row">
                              <div class="col-md-12 text-right">
                              <?php if (!empty($uploadedFiles)): ?>
                                  <span class="badge badge-primary p-2" >
                                      <i class="fas fa-files-o mr-1 "></i> 
                                      <?= count($uploadedFiles) ?> file(s) uploaded
                                  </span>
                              <?php endif; ?>
                              </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-modern btn-modern-primary" data-toggle="modal" data-target="#uploadFileModal">
                                        <i class="fas fa-upload mr-1"></i> Upload New Files
                                    </button>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-info ml-2" data-toggle="modal" data-target="#filesTableModal">
                                        <i class="fas fa-table mr-1"></i> View Files Table
                                    </button>
                                    
                                </div>
                            </div>
                            
                            <small class="text-muted d-block mt-2">Max file size: 200MB per file. Allowed types: PDF, DOC, XLS, PPT, JPG, PNG</small>
                        </div>
                    </div>
                </div>

                <!-- Files Table Modal -->
                <div class="modal fade" id="filesTableModal" tabindex="-1" role="dialog" aria-labelledby="filesTableModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="filesTableModalLabel">My Files - Complete List</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
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
                                                                <a href="../dist/files/employees/<?= $emp_id ?>/<?= htmlspecialchars($file['name']) ?>" 
                                                                class="btn btn-primary" 
                                                                title="Download"
                                                                download="<?= htmlspecialchars($file['name']) ?>">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <a href="profile.php?delete_file=<?= htmlspecialchars($file['name']) ?>" 
                                                                    class="btn btn-danger delete-file-btn" 
                                                                    data-filename="<?= htmlspecialchars($file['name']) ?>" 
                                                                    title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                                <button class="btn btn-info view-file-btn" 
                                                                        title="Preview"
                                                                        data-filepath="../dist/files/employees/<?= $emp_id ?>/<?= htmlspecialchars($file['name']) ?>"
                                                                        data-filetype="<?= $fileType ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <?php if (!empty($uploadedFiles)): ?>
                                    <button type="button" class="btn btn-primary" onclick="$('#uploadFileModal').modal('show'); $('#filesTableModal').modal('hide');">
                                        <i class="fas fa-upload mr-1"></i> Upload More Files
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                                
                <!-- Change Password Tab -->
                <div class="tab-pane" id="password">
                  <div class="row">
                    <div class="col-md-6">
                      <!-- Change Password Form -->
                      <div class="card card-primary mb-6">
                        <div class="card-header">
                          <h5 class="card-title mb-0">Change Password</h5>
                        </div>
                        <form method="post">
                          <input type="hidden" name="change_password" value="1">
                          <div class="card-body">
                            <div class="form-group">
                              <label for="current_password">Current Password</label>
                              <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <div class="input-group-append">
                                  <span class="input-group-text password-toggle" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                  </span>
                                </div>
                              </div>
                            </div>
                            
                            <div class="form-group">
                              <label for="new_password">New Password</label>
                              <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="Enter new password">
                                <div class="input-group-append">
                                  <span class="input-group-text password-toggle" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                  </span>
                                </div>
                              </div>
                              <div class="password-hint">
                                Use at least 8 characters with a mix of letters, numbers, and symbols
                              </div>
                              <div class="password-strength mt-2">
                                <div class="progress" style="height: 8px;">
                                  <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small id="passwordStrengthText" class="text-muted">Password strength: Very weak</small>
                              </div>
                            </div>
                            
                            <div class="form-group">
                              <label for="confirm_password">Confirm New Password</label>
                              <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password">
                                <div class="input-group-append">
                                  <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                  </span>
                                </div>
                              </div>
                              <div id="passwordMatch" class="password-match mt-2"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-modern btn-modern-primary">
                              <i class="fas fa-sync-alt mr-1"></i> Change Password
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>     
                    
                    <div class="col-md-6">
                      <div class="card card-info">
                        <div class="card-header">
                          <h5 class="card-title mb-0">Forgot Password?</h5>
                        </div>
                        <div class="card-body">
                          <p>If you've forgotten your password, you can request a password reset.</p>
                          <a href="../views/forgot_password.php" class="btn btn-info">Reset Password</a>
                        </div>
                      </div>
                    </div>
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
</div>
<?php include '../includes/footer.php'; ?>

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

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" role="dialog" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadFileModalLabel">Upload Files</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="employee_files">Select Files</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="employee_files" name="employee_files[]" multiple required>
                            <label class="custom-file-label" for="employee_files" id="fileLabel">Choose files</label>
                        </div>
                        <small class="text-muted">You can select multiple files. Max 200MB per file.</small>
                    </div>
                    
                    <!-- Selected files preview -->
                    <div id="selectedFiles" class="mt-3" style="display: none;">
                        <h6>Selected Files:</h6>
                        <ul id="fileList" class="list-group"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="fas fa-upload mr-1"></i> Upload Files
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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
        
        // Set download link with proper encoding
        downloadBtn.attr('href', filePath);
        downloadBtn.attr('download', filePath.split('/').pop());
        
        // Clear previous content
        content.empty();
        
        if (fileType === 'pdf') {
            content.html(`
                <div id="pdfViewer" style="width: 100%; height: 500px; border: 1px solid #ddd;">
                    <div class="text-center p-5">
                        <i class="fas fa-file-pdf fa-3x mb-3 text-danger"></i>
                        <p>PDF preview loading...</p>
                        <p><small>If the PDF doesn't load, <a href="${filePath}" download>download it instead</a>.</small></p>
                    </div>
                </div>
                <script>
                    // Simple PDF display using object tag
                    document.getElementById('pdfViewer').innerHTML = 
                        '<object data="${filePath}" type="application/pdf" width="100%" height="500px">' +
                        '<p>Unable to display PDF file. <a href="${filePath}" download>Download</a> instead.</p>' +
                        '</object>';
                <\/script>
            `);
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
            content.html(`
                <img src="${filePath}" class="img-fluid" alt="Preview" 
                    onerror="this.src='../dist/img/default-file.png'">
            `);
        } else if (['word', 'excel', 'powerpoint'].includes(fileType)) {
            content.html(`
                <div class="text-center p-5">
                    <i class="fas fa-file-${fileType === 'word' ? 'word' : fileType === 'excel' ? 'excel' : 'powerpoint'} fa-5x mb-3 text-muted"></i>
                    <p class="lead">Office documents cannot be previewed in the browser.</p>
                    <p>Please download the file to view it.</p>
                </div>
            `);
        } else {
            content.html(`
                <div class="text-center p-5">
                    <i class="fas fa-file fa-5x mb-3 text-muted"></i>
                    <p class="lead">This file type cannot be previewed in the browser.</p>
                    <p>Please download the file to view it.</p>
                </div>
            `);
        }
        
        modal.modal('show');
    });
    
    function checkFileAccessibility(filePath) {
        return new Promise((resolve) => {
            $.ajax({
                url: filePath,
                type: 'HEAD',
                success: function() {
                    resolve(true);
                },
                error: function() {
                    resolve(false);
                }
            });
        });
    }

      // Initialize DataTables for files table in modal
      if ($('#filesTable').length) {
          $('#filesTable').DataTable({
              "responsive": true,
              "autoWidth": false,
              "order": [[3, "desc"]],
              "pageLength": 10,
              "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
              "language": {
                  "paginate": {
                      "previous": "Previous",
                      "next": "Next"
                  },
                  "search": "Search:",
                  "lengthMenu": "Show _MENU_ entries",
                  "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                  "infoEmpty": "Showing 0 to 0 of 0 entries",
                  "infoFiltered": "(filtered from _MAX_ total entries)"
              },
              "columnDefs": [
                  { 
                      "orderable": false, 
                      "targets": [4],
                      "width": "120px"
                  },
                  {
                      "targets": [0],
                      "width": "250px"
                  },
                  {
                      "targets": [1, 2],
                      "width": "80px"
                  },
                  {
                      "targets": [3],
                      "width": "150px"
                  }
              ],
              "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
              "drawCallback": function(settings) {
                  // Re-initialize event handlers after table redraw
                  initializeFileEventHandlers();
              }
          });
      }
      
      function initializeFileEventHandlers() {
          // File deletion confirmation
          $('.delete-file-btn').off('click').on('click', function(e) {
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
          $('.view-file-btn').off('click').on('click', function() {
              const filePath = $(this).data('filepath');
              const fileType = $(this).data('filetype').toLowerCase();
              const modal = $('#filePreviewModal');
              const content = $('#filePreviewContent');
              const downloadBtn = $('#downloadPreviewBtn');
              
              downloadBtn.attr('href', filePath);
              downloadBtn.attr('download', filePath.split('/').pop());
              content.empty();
              
              if (fileType === 'pdf') {
                  content.html(`
                      <div id="pdfViewer" style="width: 100%; height: 500px; border: 1px solid #ddd;">
                          <div class="text-center p-5">
                              <i class="fas fa-file-pdf fa-3x mb-3 text-danger"></i>
                              <p>PDF preview loading...</p>
                              <p><small>If the PDF doesn't load, <a href="${filePath}" download>download it instead</a>.</small></p>
                          </div>
                      </div>
                      <script>
                          document.getElementById('pdfViewer').innerHTML = 
                              '<object data="${filePath}" type="application/pdf" width="100%" height="500px">' +
                              '<p>Unable to display PDF file. <a href="${filePath}" download>Download</a> instead.</p>' +
                              '</object>';
                      <\/script>
                  `);
              } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
                  content.html(`
                      <img src="${filePath}" class="img-fluid" alt="Preview" 
                          onerror="this.src='../dist/img/default-file.png'">
                  `);
              } else if (['word', 'excel', 'powerpoint'].includes(fileType)) {
                  content.html(`
                      <div class="text-center p-5">
                          <i class="fas fa-file-${fileType === 'word' ? 'word' : fileType === 'excel' ? 'excel' : 'powerpoint'} fa-5x mb-3 text-muted"></i>
                          <p class="lead">Office documents cannot be previewed in the browser.</p>
                          <p>Please download the file to view it.</p>
                      </div>
                  `);
              } else {
                  content.html(`
                      <div class="text-center p-5">
                          <i class="fas fa-file fa-5x mb-3 text-muted"></i>
                          <p class="lead">This file type cannot be previewed in the browser.</p>
                          <p>Please download the file to view it.</p>
                      </div>
                  `);
              }
              
              modal.modal('show');
          });
      }
      
      // Initialize event handlers on page load
      initializeFileEventHandlers();
  });

  // Set theme based on current module for header and footer
  function applyProfileTheme() {
      const currentModule = '<?= $current_module ?>';
      const themes = {
          'admin': 'linear-gradient(135deg, #4361ee, #3f37c9)',
          'service': 'linear-gradient(135deg, #ffc107, #fd7e14)',
          'inventory': 'linear-gradient(135deg, #28a745, #20c997)',
          'file': 'linear-gradient(135deg, #800020, #5a0a1d)'
      };
      
      const theme = themes[currentModule] || themes['admin'];
      
      // Apply to header
      $('.main-header').css('background', theme);
      
      // Apply to footer if exists
      $('#mainFooter').css('background', theme);
      
      // Update page title with theme indicator
      document.title = `AdminLTE 3 | My Profile (${currentModule.charAt(0).toUpperCase() + currentModule.slice(1)})`;
      
      // Update localStorage for consistency
      localStorage.setItem('currentTheme', currentModule);
      
      console.log(`Applied ${currentModule} theme to profile page`);
  }

  // Set module cookie based on current theme when profile is accessed from header
  function setModuleCookie() {
      const currentTheme = localStorage.getItem('currentTheme') || 'admin';
      document.cookie = `current_module=${currentTheme}; path=/; max-age=300`; // 5 minutes
  }

  // Apply theme on document ready
  $(document).ready(function() {
      applyProfileTheme();
      
      // Set cookie for theme persistence
      setModuleCookie();
      
      // Also update the mainheader theme if in iframe/parent context
      if (window.parent && window.parent.setTheme) {
          window.parent.setTheme('<?= $current_module ?>');
      }
      
      // Add theme class to body for additional styling options
      $('body').addClass(`theme-<?= $current_module ?>`);
  });

  // Listen for theme changes from other pages
  $(window).on('storage', function(e) {
      if (e.originalEvent.key === 'currentTheme') {
          const newTheme = e.originalEvent.newValue;
          document.cookie = `current_module=${newTheme}; path=/; max-age=300`;
          location.reload();
      }
  });

  // Handle profile link clicks to maintain theme context
  $(document).on('click', '.profile-dropdown a[href="profile.php"], .user-panel a[href="profile.php"]', function(e) {
      setModuleCookie();
      // Allow normal navigation to proceed
  });

  // File upload modal functionality
  $(document).ready(function() {
      // Handle file selection and show preview
      $('#employee_files').on('change', function() {
          const files = $(this)[0].files;
          const fileList = $('#fileList');
          const selectedFiles = $('#selectedFiles');
          
          fileList.empty();
          
          if (files.length > 0) {
              selectedFiles.show();
              
              for (let i = 0; i < files.length; i++) {
                  const file = files[i];
                  const fileSize = formatFileSize(file.size);
                  
                  fileList.append(`
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                          <div>
                              <i class="fas fa-file mr-2"></i>
                              ${file.name}
                          </div>
                          <span class="badge badge-primary badge-pill">${fileSize}</span>
                      </li>
                  `);
              }
              
              // Update file label
              $('#fileLabel').text(`${files.length} file(s) selected`);
          } else {
              selectedFiles.hide();
              $('#fileLabel').text('Choose files');
          }
      });
      
      // Reset form when modal is closed
      $('#uploadFileModal').on('hidden.bs.modal', function() {
          $('#uploadForm')[0].reset();
          $('#selectedFiles').hide();
          $('#fileList').empty();
          $('#fileLabel').text('Choose files');
      });
      
      // Format file size for display
      function formatFileSize(bytes) {
          if (bytes >= 1073741824) {
              return (bytes / 1073741824).toFixed(2) + ' GB';
          } else if (bytes >= 1048576) {
              return (bytes / 1048576).toFixed(2) + ' MB';
          } else if (bytes >= 1024) {
              return (bytes / 1024).toFixed(2) + ' KB';
          } else {
              return bytes + ' bytes';
          }
      }
  });

  // Password strength calculation
const newPasswordInput = document.getElementById('new_password');
const strengthBar = document.getElementById('passwordStrengthBar');
const strengthText = document.getElementById('passwordStrengthText');

if (newPasswordInput) {
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check password length
        if (password.length >= 8) {
            strength += 25;
        }
        
        // Check for numbers
        if (/\d/.test(password)) {
            strength += 25;
        }
        
        // Check for special characters
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            strength += 25;
        }
        
        // Check for uppercase and lowercase letters
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            strength += 25;
        }
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Update strength text and color
        if (strength === 0) {
            strengthText.textContent = 'Password strength: Very weak';
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength <= 25) {
            strengthText.textContent = 'Password strength: Weak';
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength <= 50) {
            strengthText.textContent = 'Password strength: Fair';
            strengthBar.className = 'progress-bar bg-warning';
        } else if (strength <= 75) {
            strengthText.textContent = 'Password strength: Good';
            strengthBar.className = 'progress-bar bg-info';
        } else {
            strengthText.textContent = 'Password strength: Strong';
            strengthBar.className = 'progress-bar bg-success';
        }
    });
}

// Password matching validation
const confirmPassword = document.getElementById('confirm_password');
const passwordMatch = document.getElementById('passwordMatch');

if (confirmPassword) {
    confirmPassword.addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        
        if (this.value && newPassword) {
            if (this.value === newPassword) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match!';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Passwords do not match!';
            }
        } else {
            passwordMatch.innerHTML = '';
        }
    });
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentNode.querySelector('.password-toggle i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Form validation for password change
const changePasswordForm = document.querySelector('form[method="post"]');
if (changePasswordForm && changePasswordForm.querySelector('input[name="change_password"]')) {
    changePasswordForm.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match. Please make sure both fields are identical.');
            return;
        }
        
        if (newPassword.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long.');
            return;
        }
        
        // Check password strength
        const strength = calculatePasswordStrength(newPassword);
        if (strength < 50) {
            e.preventDefault();
            alert('Your password is too weak. Please use a stronger password with a mix of letters, numbers, and special characters.');
            return;
        }
    });
}

// Calculate password strength
function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength += 25;
    if (/\d/.test(password)) strength += 25;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 25;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
    
    return strength;
}
</script>
</body>
</html>