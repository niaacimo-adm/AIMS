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

$current_module = $_COOKIE['current_module'] ?? 'admin';
$current_theme = $current_module;

// Priority 1: Check URL parameter (for direct navigation)
if (isset($_GET['theme'])) {
    $current_module = $_GET['theme'];
}
// Priority 2: Check cookie (set by sidebar)
elseif (isset($_COOKIE['current_module'])) {
    $current_module = $_COOKIE['current_module'];
} 
// Priority 3: Check session theme
elseif (isset($_SESSION['current_theme'])) {
    $current_module = $_SESSION['current_theme'];
}
// Priority 4: Fallback to referer detection with ICT support
elseif (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    if (strpos($referer, 'service') !== false) {
        $current_module = 'service';
    } elseif (strpos($referer, 'ict') !== false) {
        $current_module = 'ict';
    } elseif (strpos($referer, 'inventory') !== false && strpos($referer, 'ict') === false) {
        $current_module = 'inventory';
    } elseif (strpos($referer, 'file_management') !== false) {
        $current_module = 'file';
    }
}

// Store in session for consistency
$_SESSION['current_theme'] = $current_module;

// ══════════════════════════════════════════════════════════════════════════
// ALL POST/GET HANDLERS MUST RUN BEFORE ANY OUTPUT (before sidebar include)
// ══════════════════════════════════════════════════════════════════════════

// Function to notify administrators about password change
function notifyAdministratorsAboutPasswordChange($emp_id) {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT first_name, last_name, id_number FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $query2 = "SELECT e.emp_id FROM employee e
               JOIN users u ON e.emp_id = u.employee_id
               JOIN user_roles r ON u.role_id = r.id
               WHERE r.name = 'Administrator' AND e.email IS NOT NULL";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();
    $admins = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
    foreach ($admins as $admin) {
        $msg = "Password changed for {$user['first_name']} {$user['last_name']} (ID: {$user['id_number']})";
        $ins = $db->prepare("INSERT INTO admin_notifications (admin_emp_id, message, type, is_read, created_at) VALUES (?, ?, 'password_change', 0, NOW())");
        $ins->bind_param("is", $admin['emp_id'], $msg);
        $ins->execute();
        $ins->close();
    }
    return count($admins) > 0;
}

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
        header('Location: profile.php'); exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password and confirmation do not match.'
        ];
        header('Location: profile.php'); exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'New password must be at least 8 characters long.'
        ];
        header('Location: profile.php'); exit();
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
        header('Location: profile.php'); exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Current password is incorrect.'
        ];
        header('Location: profile.php'); exit();
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
    
    header('Location: profile.php'); exit();
}

// Handle username change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $confirm_password_for_username = $_POST['confirm_password_username'] ?? '';

    if (empty($new_username) || empty($confirm_password_for_username)) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'All fields are required to change username.'
        ];
        header('Location: profile.php#password'); exit();
    }

    // Username: alphanumeric, underscores, dots, 3-30 chars
    if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $new_username)) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Username must be 3–30 characters and contain only letters, numbers, dots, or underscores.'
        ];
        header('Location: profile.php#password'); exit();
    }

    // Check if username is already taken
    $check_query = "SELECT id FROM users WHERE user = ? AND employee_id != ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("si", $new_username, $emp_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'That username is already taken. Please choose another.'
        ];
        header('Location: profile.php#password'); exit();
    }

    // Verify password before allowing username change
    $pw_query = "SELECT password FROM users WHERE employee_id = ?";
    $pw_stmt = $db->prepare($pw_query);
    $pw_stmt->bind_param("i", $emp_id);
    $pw_stmt->execute();
    $pw_result = $pw_stmt->get_result()->fetch_assoc();

    if (!$pw_result || !password_verify($confirm_password_for_username, $pw_result['password'])) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Incorrect password. Username was not changed.'
        ];
        header('Location: profile.php#password'); exit();
    }

    // Update username
    $update_query = "UPDATE users SET user = ? WHERE employee_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bind_param("si", $new_username, $emp_id);

    if ($update_stmt->execute()) {
        $_SESSION['user'] = $new_username;
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Username changed successfully!'
        ];
    } else {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Failed to change username. Please try again.'
        ];
    }

    header('Location: profile.php#password'); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// All header() calls done — safe to include sidebar (outputs HTML) now
// ══════════════════════════════════════════════════════════════════════════
// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['employee_files'])) {
    $uploadSuccess = 0; $uploadErrors = [];
    try {
        $targetDir = "../dist/files/employees/{$emp_id}/";
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true))
            throw new Exception("Could not create upload directory.");
        if (!is_writable($targetDir)) throw new Exception("Upload directory is not writable.");
        $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif'];
        foreach ($_FILES['employee_files']['name'] as $key => $name) {
            if ($_FILES['employee_files']['error'][$key] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Error with '$name'."; continue;
            }
            $fileName   = basename($name);
            $targetFile = $targetDir . $fileName;
            $ext        = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            if (file_exists($targetFile))                                  { $uploadErrors[] = "'$fileName' already exists."; continue; }
            if ($_FILES['employee_files']['size'][$key] > 200*1024*1024)   { $uploadErrors[] = "'$fileName' too large."; continue; }
            if (!in_array($ext, $allowed))                                  { $uploadErrors[] = "'$fileName' type not allowed."; continue; }
            if (move_uploaded_file($_FILES['employee_files']['tmp_name'][$key], $targetFile)) $uploadSuccess++;
            else $uploadErrors[] = "Error saving '$fileName'.";
        }
        $msg = $uploadSuccess > 0 ? "$uploadSuccess file(s) uploaded!" : "No files uploaded.";
        if (!empty($uploadErrors)) $msg .= ' ' . implode(' ', $uploadErrors);
        $_SESSION['toast'] = ['type' => $uploadSuccess > 0 ? 'success' : 'error', 'message' => $msg];
    } catch (Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
    header('Location: profile.php'); exit();
}

// Handle single file deletion
if (isset($_GET['delete_file'])) {
    $fp = "../dist/files/employees/{$emp_id}/" . basename($_GET['delete_file']);
    if (file_exists($fp)) { unlink($fp); $_SESSION['toast'] = ['type'=>'success','message'=>'File deleted!']; }
    else                  { $_SESSION['toast'] = ['type'=>'error','message'=>'File not found!']; }
    header('Location: profile.php'); exit();
}

// Handle bulk delete with password confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_files'])) {
    $confirm_pw = $_POST['delete_all_password'] ?? '';
    if (empty($confirm_pw)) {
        $_SESSION['toast'] = ['type'=>'error','message'=>'Password required.'];
        header('Location: profile.php#file'); exit();
    }
    $pw_s = $db->prepare("SELECT password FROM users WHERE employee_id = ?");
    $pw_s->bind_param("i", $emp_id); $pw_s->execute();
    $pw_r = $pw_s->get_result()->fetch_assoc(); $pw_s->close();
    if (!$pw_r || !password_verify($confirm_pw, $pw_r['password'])) {
        $_SESSION['toast'] = ['type'=>'error','message'=>'Incorrect password. No files deleted.'];
        header('Location: profile.php#file'); exit();
    }
    $selected = $_POST['selected_files'] ?? [];
    if (empty($selected)) {
        $_SESSION['toast'] = ['type'=>'warning','message'=>'No files selected.']; header('Location: profile.php#file'); exit();
    }
    $deleted = 0; $dir = "../dist/files/employees/{$emp_id}/";
    foreach ($selected as $fn) { $p = $dir.basename($fn); if(file_exists($p)){unlink($p);$deleted++;} }
    $_SESSION['toast'] = ['type'=>'success','message'=>"$deleted file(s) deleted."];
    header('Location: profile.php#file'); exit();
}

switch ($current_module) {
    case 'service':   include '../includes/sidebar_service.php';   break;
    case 'inventory': include '../includes/sidebar_inventory.php'; break;
    case 'file':      include '../includes/sidebar_file.php';      break;
    case 'ict':       include '../includes/sidebar_ict.php';       break;
    default:          include '../includes/sidebar.php';           break;
}
$current_theme = $current_module;

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

// Fetch current username
$uname_query = "SELECT user FROM users WHERE employee_id = ?";
$uname_stmt = $db->prepare($uname_query);
$uname_stmt->bind_param("i", $emp_id);
$uname_stmt->execute();
$uname_row = $uname_stmt->get_result()->fetch_assoc();
$current_username = $uname_row['user'] ?? '';

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

<?php $has_leadership = $employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | My Profile</title>
  <?php include '../includes/header.php'; ?>
  <style>

    /* ── Login Green Theme Tokens ── */
    <?php
    // Unified login green theme — all modules use the same palette
    $theme = [
      'primary'    => 'linear-gradient(158deg,#1c4d38 0%,#1a5c38 52%,#2a9863 100%)',
      'solid'      => '#1a5c38',
      'solid_dark' => '#2a9863',
      'sidebar'    => '#1a5c38',
      'header'     => 'linear-gradient(158deg,#1c4d38 0%,#1a5c38 52%,#2a9863 100%)',
      'button'     => 'linear-gradient(135deg,#1a5c38,#2a9863)',
      'accent'     => '#2a9863',
      'light'      => '#f0faf5',
      'text_on'    => '#ffffff',
    ];
    ?>

    :root {
      --clr-accent:      <?= $theme['solid'] ?>;
      --clr-accent-dark: <?= $theme['solid_dark'] ?>;
      --clr-accent-light:<?= $theme['light'] ?>;
      --clr-gradient:    <?= $theme['primary'] ?>;
      --clr-header:      <?= $theme['header'] ?>;
      --clr-sidebar:     <?= $theme['sidebar'] ?>;
      --clr-button:      <?= $theme['button'] ?>;
      --text-on-accent:  <?= $theme['text_on'] ?>;
      --radius-lg:14px; --radius-md:10px; --radius-sm:6px;
      --font-body:'DM Sans',sans-serif; --font-display:'Syne',sans-serif;
      --pf-bg-page:#f4f6fb; --pf-bg-card:#fff; --pf-bg-tile:#f8fafc;
      --pf-bg-form:#f8fafc; --pf-bg-guide:var(--clr-accent-light); --pf-bg-upload:#fafafa;
      --pf-border:#e8edf4; --pf-border-input:#e2e8f0;
      --pf-text-primary:#1e293b; --pf-text-body:#374151; --pf-text-muted:#64748b;
      --pf-text-hint:#94a3b8; --pf-text-label:#475569;
      --pf-info-row-border:#f0f0f5; --pf-leadership-bg:#f8fafc; --pf-table-border:#e2e8f0;
      --pf-scrollbar-track:#f1f5f9; --pf-scrollbar-thumb:#cbd5e1;
      --pf-shadow-card:0 2px 12px rgba(0,0,0,.07); --pf-shadow-modal:0 20px 60px rgba(0,0,0,.18);
    }
    body.dark-mode {
      --pf-bg-page:var(--body-bg); --pf-bg-card:var(--card-bg); --pf-bg-tile:var(--table-stripe);
      --pf-bg-form:var(--table-stripe); --pf-bg-guide:rgba(255,255,255,.04); --pf-bg-upload:var(--table-stripe);
      --pf-border:var(--card-border); --pf-border-input:var(--input-border);
      --pf-text-primary:var(--text-primary); --pf-text-body:var(--text-primary); --pf-text-muted:var(--text-muted);
      --pf-text-hint:var(--text-muted); --pf-text-label:var(--text-muted);
      --pf-info-row-border:var(--card-border); --pf-leadership-bg:var(--table-stripe); --pf-table-border:var(--table-border);
      --pf-scrollbar-track:var(--card-bg); --pf-scrollbar-thumb:#4a5068;
      --pf-shadow-card:0 2px 16px rgba(0,0,0,.35); --pf-shadow-modal:0 20px 60px rgba(0,0,0,.55);
    }

    body,.content-wrapper { font-family:var(--font-body)!important; background:var(--pf-bg-page)!important; }
    .content-header h1 { font-family:var(--font-display); color:var(--clr-accent); font-weight:700; letter-spacing:-.5px; }
    .breadcrumb { background:transparent; }
    .breadcrumb-item.active { color:var(--clr-accent); }
    .breadcrumb-item a { color:var(--pf-text-muted); text-decoration:none; }
    .breadcrumb-item a:hover { color:var(--clr-accent); }

    /* ── Hero ── */
    .profile-hero {
      background:var(--clr-header); border-radius:var(--radius-lg);
      padding:32px 40px; display:flex; align-items:center; gap:28px;
      margin-bottom:24px; position:relative; overflow:hidden;
    }
    .profile-hero::before {
      content:''; position:absolute; inset:0; pointer-events:none;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .profile-hero-avatar {
      width:110px; height:110px; border-radius:50%;
      border:4px solid rgba(255,255,255,.45); box-shadow:0 4px 20px rgba(0,0,0,.25);
      object-fit:cover; flex-shrink:0; position:relative; z-index:1;
    }
    .profile-hero-initials {
      width:110px; height:110px; border-radius:50%;
      background:rgba(255,255,255,.2); border:4px solid rgba(255,255,255,.45);
      display:flex; align-items:center; justify-content:center;
      font-family:var(--font-display); font-size:2.2rem; font-weight:700; color:#fff;
      flex-shrink:0; position:relative; z-index:1;
    }
    .profile-hero-info { position:relative; z-index:1; flex:1; }
    .profile-hero-info h2 {
      font-family:var(--font-display); font-size:1.7rem; font-weight:700;
      color:#fff; margin:0 0 4px; letter-spacing:-.3px;
    }
    .hero-sub { color:rgba(255,255,255,.82); font-size:.95rem; font-weight:500; margin:0 0 2px; }
    .hero-id  { color:rgba(255,255,255,.6);  font-size:.82rem; margin:0; letter-spacing:.5px; }
    .hero-badges { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; position:relative; z-index:1; }
    .hero-status-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:4px 12px; border-radius:20px;
      font-size:.75rem; font-weight:700; letter-spacing:.3px;
      border:1.5px solid rgba(255,255,255,.3); backdrop-filter:blur(4px);
    }
    @media(max-width:640px) {
      .profile-hero { flex-direction:column; text-align:center; padding:24px 20px; gap:16px; }
      .profile-hero-avatar,.profile-hero-initials { width:90px; height:90px; font-size:1.8rem; }
      .profile-hero-info h2 { font-size:1.35rem; }
      .hero-badges { justify-content:center; }
    }

    /* ── Tab bar ── */
    .pf-tabs-bar {
      background:var(--clr-sidebar); border-radius:var(--radius-lg) var(--radius-lg) 0 0;
      display:flex; overflow:hidden;
    }
    .pf-tabs-bar .nav-link {
      flex:1; text-align:center; padding:14px 10px;
      color:rgba(255,255,255,.6)!important; font-size:.85rem; font-weight:500;
      border-radius:0!important; border-bottom:3px solid transparent; transition:all .22s;
    }
    .pf-tabs-bar .nav-link i { display:block; font-size:1.05rem; margin-bottom:3px; }
    .pf-tabs-bar .nav-link.active {
      color:#fff!important; background:rgba(255,255,255,.13)!important;
      border-bottom-color:rgba(255,255,255,.7); font-weight:600;
    }
    .pf-tabs-bar .nav-link:hover:not(.active) { background:rgba(255,255,255,.08)!important; color:rgba(255,255,255,.9)!important; }

    /* ── Tab content ── */
    .pf-tab-content {
      background:var(--pf-bg-card); border-radius:0 0 var(--radius-lg) var(--radius-lg);
      box-shadow:var(--pf-shadow-card); padding:28px 32px; min-height:420px;
    }
    body.dark-mode .pf-tab-content { background:var(--card-bg)!important; }
    .pf-tab-content .card { border:none!important; box-shadow:none!important; background:transparent!important; }

    /* ── Section title ── */
    .pf-section-title {
      font-family:var(--font-display); font-size:.95rem; font-weight:700;
      color:var(--pf-text-primary); margin-bottom:16px; padding-bottom:10px;
      border-bottom:2px solid var(--pf-border); display:flex; align-items:center; gap:8px;
    }
    .pf-section-title::before { content:''; width:4px; height:17px; background:var(--clr-gradient); border-radius:2px; flex-shrink:0; }
    .pf-section-title.mt-2 { margin-top:28px; }
    body.dark-mode .pf-section-title { color:var(--text-primary)!important; border-color:var(--card-border)!important; }

    /* ── Info tile grid ── */
    .pf-info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:4px; }
    .pf-info-grid.cols-2 { grid-template-columns:repeat(2,1fr); }
    .pf-info-grid.cols-4 { grid-template-columns:repeat(4,1fr); }
    @media(max-width:900px) { .pf-info-grid,.pf-info-grid.cols-4 { grid-template-columns:repeat(2,1fr); } }
    @media(max-width:520px) { .pf-info-grid,.pf-info-grid.cols-2,.pf-info-grid.cols-4 { grid-template-columns:1fr; } }
    .pf-info-tile {
      background:var(--pf-bg-tile); border-radius:var(--radius-md);
      padding:13px 15px; border-left:3px solid var(--clr-accent);
    }
    .tile-label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--clr-accent); margin-bottom:4px; }
    .tile-value { font-size:.88rem; font-weight:500; color:var(--pf-text-primary); word-break:break-word; }
    .tile-value a { color:var(--clr-accent); text-decoration:none; }
    .tile-value a:hover { text-decoration:underline; }
    body.dark-mode .pf-info-tile { background:var(--table-stripe)!important; }
    body.dark-mode .tile-value { color:var(--text-primary)!important; }

    /* ── Status badge ── */
    .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-weight:700; font-size:.78rem; letter-spacing:.3px; }

    /* ── Leadership grid ── */
    .leadership-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
    .leadership-item { background:var(--pf-leadership-bg); border-radius:var(--radius-md); padding:14px 16px; border-left:3px solid var(--clr-accent); }
    .leadership-item.focal { border-left-color:#7c3aed; }
    .l-title { font-weight:700; font-size:.87rem; color:var(--pf-text-primary); margin-bottom:3px; }
    .l-sub   { font-size:.77rem; color:var(--pf-text-muted); margin-bottom:8px; line-height:1.4; }
    body.dark-mode .leadership-item { background:var(--table-stripe)!important; }
    body.dark-mode .l-title { color:var(--text-primary)!important; }
    body.dark-mode .l-sub   { color:var(--text-muted)!important; }

    /* ── Inline file upload (drop area) ── */
    .file-drop-area {
      border: 2px dashed var(--pf-border);
      border-radius: var(--radius-lg);
      background: var(--pf-bg-tile);
      cursor: pointer;
      transition: all .22s;
      margin-bottom: 0;
      position: relative;
    }
    .file-drop-area:hover, .file-drop-area.drag-over {
      border-color: var(--clr-accent);
      background: var(--clr-accent-light);
    }
    /* .file-drop-input removed — input is now display:none outside the drop area */
    .file-drop-inner {
      padding: 28px 20px; text-align: center;
    }
    .file-drop-icon { font-size: 2rem; color: var(--pf-text-muted); margin-bottom: 10px; display: block; }
    .file-drop-title { font-size: .9rem; font-weight: 600; color: var(--pf-text-body); margin: 0 0 4px; }
    .file-drop-browse { color: var(--clr-accent); text-decoration: underline; }
    .file-drop-hint { font-size: .78rem; color: var(--pf-text-muted); margin: 0; }
    body.dark-mode .file-drop-area { background: var(--table-stripe)!important; border-color: var(--card-border)!important; }
    body.dark-mode .file-drop-area:hover, body.dark-mode .file-drop-area.drag-over { border-color: var(--clr-accent)!important; background: rgba(255,255,255,.06)!important; }
    body.dark-mode .file-drop-title { color: var(--text-primary)!important; }

    /* Selected files preview (inside drop area) */
    .file-preview-list { list-style: none; padding: 16px 20px 0; margin: 0; }
    .file-preview-item {
      display: flex; align-items: center; gap: 8px;
      padding: 7px 0; border-bottom: 1px solid var(--pf-border); font-size: .84rem;
    }
    .file-preview-item:last-child { border-bottom: none; }
    .fpi-name { flex: 1; color: var(--pf-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fpi-size { color: var(--pf-text-muted); font-size: .75rem; white-space: nowrap; }
    body.dark-mode .file-preview-item { border-color: var(--card-border)!important; }
    body.dark-mode .fpi-name { color: var(--text-primary)!important; }
    body.dark-mode .fpi-size { color: var(--text-muted)!important; }

    .file-drop-actions {
      display: flex; justify-content: flex-end; gap: 10px;
      padding: 10px 14px; margin-top: 10px;
    }
    body.dark-mode .file-drop-actions { border-color: var(--card-border)!important; }
    .btn-file-clear {
      background: transparent; border: 1.5px solid var(--pf-border);
      color: var(--pf-text-muted); border-radius: var(--radius-sm);
      padding: 7px 16px; font-size: .84rem; font-weight: 600; cursor: pointer; transition: all .2s;
    }
    .btn-file-clear:hover { border-color: #ef4444; color: #ef4444; }
    .btn-file-upload {
      background: var(--clr-button); color: #fff; border: none;
      border-radius: var(--radius-sm); padding: 7px 20px;
      font-size: .84rem; font-weight: 600; cursor: pointer; transition: all .2s;
    }
    .btn-file-upload:hover { opacity: .88; transform: translateY(-1px); }

    /* ── Uploaded files list ── */
    .files-empty {
      text-align: center; padding: 48px 20px; color: var(--pf-text-muted);
    }
    .files-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: .4; }
    .files-empty p { font-size: .9rem; margin: 0; }

    .files-list-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 10px;
    }
    .files-count {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .5px; color: var(--clr-accent);
    }

    .files-list {
      border: 1px solid var(--pf-border); border-radius: var(--radius-md); overflow: hidden;
    }
    body.dark-mode .files-list { border-color: var(--card-border)!important; }

    .file-row {
      display: flex; align-items: center; gap: 12px;
      padding: 11px 16px; border-bottom: 1px solid var(--pf-border);
      background: var(--pf-bg-card); transition: background .15s;
    }
    .file-row:last-child { border-bottom: none; }
    .file-row:hover { background: var(--pf-bg-tile); }
    body.dark-mode .file-row { background: var(--card-bg)!important; border-color: var(--card-border)!important; }
    body.dark-mode .file-row:hover { background: var(--table-stripe)!important; }

    .file-row-icon { font-size: 1.4rem; flex-shrink: 0; width: 28px; text-align: center; }
    .file-row-info { flex: 1; min-width: 0; }
    .file-row-name {
      display: block; font-size: .88rem; font-weight: 600;
      color: var(--pf-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .file-row-meta { font-size: .74rem; color: var(--pf-text-muted); }
    body.dark-mode .file-row-name { color: var(--text-primary)!important; }
    body.dark-mode .file-row-meta { color: var(--text-muted)!important; }

    .file-row-actions { display: flex; gap: 4px; flex-shrink: 0; }
    .file-action-btn {
      width: 32px; height: 32px; border-radius: var(--radius-sm);
      display: inline-flex; align-items: center; justify-content: center;
      font-size: .82rem; border: none; cursor: pointer;
      transition: all .15s; text-decoration: none;
    }
    .file-action-btn.download { background: var(--clr-accent-light); color: var(--clr-accent); }
    .file-action-btn.download:hover { background: var(--clr-accent); color: #fff; }
    .file-action-btn.preview  { background: #ecfdf5; color: #16a34a; }
    .file-action-btn.preview:hover  { background: #16a34a; color: #fff; }
    .file-action-btn.delete   { background: #fff1f2; color: #ef4444; }
    .file-action-btn.delete:hover   { background: #ef4444; color: #fff; }
    body.dark-mode .file-action-btn.download { background: rgba(255,255,255,.07)!important; color: var(--clr-accent)!important; }
    body.dark-mode .file-action-btn.preview  { background: rgba(22,163,74,.12)!important; color: #4ade80!important; }
    body.dark-mode .file-action-btn.delete   { background: rgba(239,68,68,.12)!important; color: #f87171!important; }
    /* ── File toolbar (select-all + delete selected) ── */
    .files-toolbar {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 14px; margin-bottom: 2px;
      background: var(--pf-bg-tile); border-radius: var(--radius-md);
      border: 1px solid var(--pf-border);
    }
    body.dark-mode .files-toolbar { background: var(--table-stripe)!important; border-color: var(--card-border)!important; }
    .files-select-all-label {
      display: flex; align-items: center; gap: 7px;
      font-size: .82rem; font-weight: 600; color: var(--pf-text-body); cursor: pointer; margin: 0;
    }
    body.dark-mode .files-select-all-label { color: var(--text-primary)!important; }
    .files-count { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--clr-accent); margin-left: auto; }
    .btn-delete-selected {
      background: #fff1f2; color: #ef4444; border: 1.5px solid #fecdd3;
      border-radius: var(--radius-sm); padding: 5px 14px; font-size: .82rem; font-weight: 700;
      cursor: pointer; transition: all .18s; display: flex; align-items: center; gap: 5px;
    }
    .btn-delete-selected:not(:disabled):hover { background: #ef4444; color: #fff; border-color: #ef4444; }
    .btn-delete-selected:disabled { opacity: .45; cursor: not-allowed; }
    body.dark-mode .btn-delete-selected { background: rgba(239,68,68,.12)!important; border-color: rgba(239,68,68,.3)!important; color: #f87171!important; }
    body.dark-mode .btn-delete-selected:not(:disabled):hover { background: #ef4444!important; color: #fff!important; }

    /* ── Checkbox style ── */
    .file-check-input {
      width: 16px; height: 16px; cursor: pointer;
      accent-color: var(--clr-accent);
    }
    .file-row-check {
      flex-shrink: 0; display: flex; align-items: center; padding-right: 4px; cursor: pointer;
    }
    .file-row.selected { background: var(--clr-accent-light)!important; }
    body.dark-mode .file-row.selected { background: rgba(255,255,255,.06)!important; }

    /* ── PDF/file preview in modal ── */
    #filePreviewContent iframe {
      border: none; width: 100%; height: 500px; display: block;
    }

    /* ── Security / password forms ── */
    .pf-form-card {
      background:var(--pf-bg-form); border-radius:var(--radius-lg);
      padding:22px; border:1px solid var(--pf-border); margin-bottom:16px;
    }
    .pf-form-card .pf-form-title {
      font-family:var(--font-display); font-size:.95rem; font-weight:700;
      color:var(--pf-text-primary); margin-bottom:18px; display:flex; align-items:center; gap:8px;
    }
    .pf-form-card .pf-form-title i { color:var(--clr-accent); }
    .form-control {
      border-radius:var(--radius-sm)!important; border:1.5px solid var(--pf-border-input)!important;
      font-size:.88rem!important; padding:9px 13px!important; transition:border-color .2s,box-shadow .2s!important;
    }
    .form-control:focus { border-color:var(--clr-accent)!important; box-shadow:0 0 0 3px rgba(0,0,0,.06)!important; }
    .form-group label { font-size:.82rem; font-weight:600; color:var(--pf-text-label); margin-bottom:5px; }
    .input-group-text { border-radius:0 var(--radius-sm) var(--radius-sm) 0!important; cursor:pointer; }
    .pf-guide-box {
      background:var(--pf-bg-guide); border-radius:var(--radius-md);
      padding:18px 20px; border-left:4px solid var(--clr-accent); height:100%;
    }
    .pf-guide-box .guide-title { font-size:.82rem; font-weight:700; color:var(--clr-accent); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px; }
    .pf-guide-box ul { padding-left:18px; margin:0; }
    .pf-guide-box ul li { font-size:.83rem; color:var(--pf-text-body); margin-bottom:6px; line-height:1.4; }
    .pf-guide-box p { color:var(--pf-text-body); }
    body.dark-mode .pf-form-card { background:var(--table-stripe)!important; border-color:var(--card-border)!important; }
    body.dark-mode .pf-guide-box { background:rgba(255,255,255,.04)!important; border-left-color:var(--clr-accent)!important; }
    body.dark-mode .pf-guide-box .guide-title { color:var(--clr-accent)!important; }
    body.dark-mode .pf-form-card .pf-form-title { color:var(--text-primary)!important; }
    body.dark-mode .form-group label { color:var(--text-muted)!important; }

    /* ── Password strength ── */
    .password-strength { margin-top:8px; }
    .progress { height:6px; border-radius:4px; background:var(--pf-border); }
    .progress-bar { border-radius:4px; transition:width .35s ease; }
    #passwordStrengthText { font-size:.78rem; color:var(--pf-text-muted); margin-top:4px; display:block; }
    .password-match { font-size:.82rem; font-weight:500; margin-top:5px; }
    .password-hint { font-size:.78rem; color:var(--pf-text-muted); margin-top:4px; }
    body.dark-mode .progress { background:var(--card-border)!important; }
    body.dark-mode #passwordStrengthText { color:var(--text-muted)!important; }
    body.dark-mode .password-hint { color:var(--text-muted)!important; }

    /* ── Buttons ── */
    .btn-accent { background:var(--clr-button); color:var(--text-on-accent)!important; border:none; border-radius:var(--radius-sm); padding:8px 18px; font-size:.87rem; font-weight:600; transition:all .22s; cursor:pointer; }
    .btn-accent:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(0,0,0,.22); opacity:.92; }
    .btn-accent-outline { background:transparent; color:var(--clr-accent)!important; border:1.5px solid var(--clr-accent); border-radius:var(--radius-sm); padding:7px 16px; font-size:.87rem; font-weight:600; transition:all .22s; }
    .btn-accent-outline:hover { background:var(--clr-accent-light); }
    .btn-primary,.btn-modern-primary { background:var(--clr-button)!important; border-color:var(--clr-accent)!important; color:#fff!important; border-radius:var(--radius-sm)!important; font-weight:600!important; font-size:.87rem!important; }
    .btn-primary:hover { transform:translateY(-1px); opacity:.9; }
    .btn-info { background:var(--clr-accent)!important; border-color:var(--clr-accent-dark)!important; color:#fff!important; border-radius:var(--radius-sm)!important; font-weight:600!important; }

    /* ── Modal ── */
    .modal-header { background:var(--clr-header)!important; color:#fff!important; border-radius:var(--radius-md) var(--radius-md) 0 0; }
    .modal-header .close { color:#fff!important; opacity:.8; }
    .modal-content { border-radius:var(--radius-lg)!important; border:none!important; box-shadow:var(--pf-shadow-modal)!important; overflow:hidden; }

    /* ── Misc ── */
    .manager-link { color:var(--pf-text-body); text-decoration:none; transition:color .2s; }
    .manager-link:hover { color:var(--clr-accent); text-decoration:underline; }
    .password-toggle { cursor:pointer; }
    .table-responsive { border:1px solid var(--pf-table-border); border-radius:var(--radius-sm); }
    #filesTable { margin-bottom:0!important; }
    .badge-primary { background:var(--clr-accent)!important; }
    .text-primary  { color:var(--clr-accent)!important; }

    /* ── Footer fix ── */
    .wrapper { display:flex; flex-direction:column; min-height:100vh; }
    .content-wrapper { flex:1 0 auto; }
    .main-footer { flex-shrink:0; position:relative!important; bottom:auto!important; margin-left:250px!important; width:calc(100% - 250px)!important; }
    body.sidebar-collapse .main-footer { margin-left:0!important; width:100%!important; }
    @media(max-width:768px) { .main-footer { margin-left:0!important; width:100%!important; } }

    /* ── Dark mode extra ── */
    body.dark-mode .content-header h1 { color:var(--clr-accent)!important; }
    body.dark-mode .breadcrumb-item a { color:var(--text-muted)!important; }
    body.dark-mode .breadcrumb-item.active { color:var(--clr-accent)!important; }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar{width:6px;height:6px} ::-webkit-scrollbar-track{background:var(--pf-scrollbar-track)}
    ::-webkit-scrollbar-thumb{background:var(--pf-scrollbar-thumb);border-radius:3px}
    ::-webkit-scrollbar-thumb:hover{background:var(--clr-accent)}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1>My Profile</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">My Profile</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid" style="max-width:1100px;">

        <!-- ── Hero Banner ── -->
        <div class="profile-hero">
          <?php
          $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture'] ?? '');
          $initials  = strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1));
          if (!empty($employee['picture']) && file_exists($imagePath)): ?>
            <img class="profile-hero-avatar" src="<?= $imagePath ?>"
                 alt="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>">
          <?php else: ?>
            <div class="profile-hero-initials"><?= $initials ?></div>
          <?php endif; ?>

          <div class="profile-hero-info">
            <h2><?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></h2>
            <p class="hero-sub"><?= htmlspecialchars($employee['position_name'] ?? '—') ?></p>
            <p class="hero-id"><?= htmlspecialchars($employee['id_number'] ?? '') ?></p>
            <div class="hero-badges">
              <?php if (!empty($employee['employment_color'])): ?>
                <span class="hero-status-badge"
                      style="background-color:<?= htmlspecialchars($employee['employment_color']) ?>;color:<?= (hexdec(substr($employee['employment_color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                  <i class="fas fa-circle" style="font-size:.55rem;"></i>
                  <?= htmlspecialchars($employee['employment_status']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($employee['appointment_color'])): ?>
                <span class="hero-status-badge"
                      style="background-color:<?= htmlspecialchars($employee['appointment_color']) ?>;color:<?= (hexdec(substr($employee['appointment_color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                  <i class="fas fa-file-signature" style="font-size:.7rem;"></i>
                  <?= htmlspecialchars($employee['appointment_status']) ?>
                </span>
              <?php endif; ?>
              <?php if ($is_manager_office_staff): ?>
                <span class="hero-status-badge" style="background:rgba(255,255,255,.2);color:#fff;">
                  <i class="fas fa-star" style="font-size:.7rem;"></i> Manager's Office Staff
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div><!-- /.profile-hero -->

        <!-- ── Tab bar ── -->
        <div class="pf-tabs-bar">
          <ul class="nav w-100" id="mainTabs">
            <li class="nav-item flex-fill">
              <a class="nav-link active" href="#about" data-toggle="tab">
                <i class="fas fa-user"></i> About Me
              </a>
            </li>
            <li class="nav-item flex-fill">
              <a class="nav-link" href="#file" data-toggle="tab">
                <i class="fas fa-folder"></i> My Files
                <?php if (!empty($uploadedFiles)): ?>
                  <span style="font-size:.7rem;background:rgba(255,255,255,.25);border-radius:10px;padding:1px 7px;margin-left:4px;">
                    <?= count($uploadedFiles) ?>
                  </span>
                <?php endif; ?>
              </a>
            </li>
            <li class="nav-item flex-fill">
              <a class="nav-link" href="#password" data-toggle="tab">
                <i class="fas fa-key"></i> Security
              </a>
            </li>
          </ul>
        </div>

        <!-- ── Tab content ── -->
        <div class="pf-tab-content">
          <div class="tab-content" style="background:transparent;padding:0;box-shadow:none;border-radius:0;min-height:unset;">

            <!-- ════════════════════ ABOUT ME ════════════════════ -->
            <div class="active tab-pane" id="about">

              <!-- 1. Personal Information -->
              <div class="pf-section-title">Personal Information</div>
              <div class="pf-info-grid">
                <div class="pf-info-tile">
                  <div class="tile-label">Full Name</div>
                  <div class="tile-value"><?= htmlspecialchars(trim($employee['first_name'].' '.($employee['middle_name']??'').' '.$employee['last_name'].' '.($employee['ext_name']??''))) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Gender</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['gender'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Birthday</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['bday'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Email</div>
                  <div class="tile-value" style="word-break:break-all;"><?= htmlspecialchars($employee['email']) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Phone</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['phone_number']) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Address</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['address'] ?? '—') ?></div>
                </div>
              </div>

              <!-- 2. Assignment -->
              <div class="pf-section-title mt-2">Assignment</div>
              <div class="pf-info-grid cols-4">
                <div class="pf-info-tile">
                  <div class="tile-label">Position</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['position_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Office</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['office_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Section</div>
                  <div class="tile-value"><?= htmlspecialchars($current_assignment['section_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Unit Section</div>
                  <div class="tile-value"><?= htmlspecialchars($current_assignment['unit_name'] ?? '—') ?></div>
                </div>
              </div>

              <?php if (!empty($employee['office_manager_first_name'])): ?>
              <!-- 3. Office Manager -->
              <div class="pf-section-title mt-2">Office Manager</div>
              <div class="pf-info-grid cols-2">
                <div class="pf-info-tile">
                  <div class="tile-label">Manager</div>
                  <div class="tile-value">
                    <a class="manager-link" href="emp.profile.php?emp_id=<?= $employee['office_manager_id'] ?>">
                      <?= htmlspecialchars($employee['office_manager_first_name'].' '.$employee['office_manager_last_name']) ?>
                    </a>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($has_leadership): ?>
              <!-- 4. Leadership Roles -->
              <div class="pf-section-title mt-2">Leadership Roles</div>
              <div class="leadership-grid">
                <?php if ($employee['is_office_manager']): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-building mr-1" style="color:var(--clr-accent)"></i> Division Manager</div>
                  <div class="l-sub">Manages: <?= htmlspecialchars($employee['office_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php foreach ($sections_as_head as $s): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-users mr-1" style="color:var(--clr-accent)"></i> Section Head</div>
                  <div class="l-sub">
                    <?= htmlspecialchars($s['section_name']) ?>
                    <?php if (!empty($s['section_code'])): ?><span style="opacity:.6">(<?= htmlspecialchars($s['section_code']) ?>)</span><?php endif; ?>
                    <br><?= htmlspecialchars($s['office_name']) ?>
                  </div>
                  <a href="sections.php?edit=<?= $s['section_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;"><i class="fas fa-edit"></i> Manage</a>
                </div>
                <?php endforeach; ?>
                <?php foreach ($sections_as_secretary as $s): ?>
                <div class="leadership-item focal">
                  <div class="l-title"><i class="fas fa-user-secret mr-1" style="color:#7c3aed"></i> Focal Person</div>
                  <div class="l-sub"><?= htmlspecialchars($s['section_name']) ?></div>
                  <a href="sections.php?edit=<?= $s['section_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;"><i class="fas fa-edit"></i> Manage</a>
                </div>
                <?php endforeach; ?>
                <?php foreach ($units_as_head as $u): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-layer-group mr-1" style="color:var(--clr-accent)"></i> Unit Head</div>
                  <div class="l-sub">
                    <?= htmlspecialchars($u['unit_name']) ?>
                    <?php if (!empty($u['unit_code'])): ?><span style="opacity:.6">(<?= htmlspecialchars($u['unit_code']) ?>)</span><?php endif; ?>
                    <br>Parent: <?= htmlspecialchars($u['section_name']) ?>
                  </div>
                  <a href="sections.php?edit_unit=<?= $u['unit_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;"><i class="fas fa-edit"></i> Manage</a>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

            </div><!-- /#about -->

            <!-- ════════════════════ MY FILES ════════════════════ -->
            <div class="tab-pane" id="file">
              <div class="pf-section-title">My Files</div>

              <!-- ── Inline Upload Form ── -->
              <form method="post" enctype="multipart/form-data" id="uploadForm">

                <!-- File input OUTSIDE drop area — hidden, no overlay intercepting clicks -->
                <input type="file" id="employee_files" name="employee_files[]" multiple
                       style="display:none; position:static;">

                <!-- Drop zone — clicking anywhere on it triggers fileInput.click() via JS -->
                <div class="file-drop-area" id="fileDropArea">
                  <div class="file-drop-inner" id="fileDropInner">
                    <i class="fas fa-cloud-upload-alt file-drop-icon"></i>
                    <p class="file-drop-title">Drag &amp; drop files here, or <span class="file-drop-browse">browse</span></p>
                    <p class="file-drop-hint">PDF, DOC, XLS, PPT, JPG, PNG &mdash; Max 200MB each</p>
                  </div>
                  <div id="selectedFilesPreview" style="display:none;">
                    <ul id="filePreviewList" class="file-preview-list"></ul>
                  </div>
                </div>

                <!-- Action buttons OUTSIDE drop area — nothing intercepts their clicks -->
                <div class="file-drop-actions" id="uploadActions" style="display:none;">
                  <button type="button" class="btn-file-clear" id="clearFilesBtn">
                    <i class="fas fa-times mr-1"></i> Clear
                  </button>
                  <button type="submit" class="btn-file-upload" id="uploadSubmitBtn">
                    <i class="fas fa-upload mr-1"></i> Upload
                  </button>
                </div>

              </form>

              <!-- ── File List ── -->
              <?php if (empty($uploadedFiles)): ?>
                <div class="files-empty">
                  <i class="fas fa-folder-open"></i>
                  <p>No files uploaded yet.</p>
                </div>
              <?php else: ?>

                <!-- Toolbar: select-all + delete selected -->
                <form method="post" id="bulkDeleteForm">
                  <input type="hidden" name="delete_all_files" value="1">

                  <div class="files-toolbar">
                    <label class="files-select-all-label">
                      <input type="checkbox" id="selectAllFiles" class="file-check-input">
                      <span>Select all</span>
                    </label>
                    <span class="files-count"><i class="fas fa-file mr-1"></i><?= count($uploadedFiles) ?> file<?= count($uploadedFiles) !== 1 ? 's' : '' ?></span>
                    <button type="button" class="btn-delete-selected" id="deleteSelectedBtn" disabled>
                      <i class="fas fa-trash mr-1"></i> Delete Selected
                    </button>
                  </div>

                  <div class="files-list" id="filesList">
                    <?php foreach ($uploadedFiles as $file):
                      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                      $ic = 'fa-file'; $ft = 'File'; $ic_color = '#6b7280';
                      if ($ext==='pdf')                            { $ic='fa-file-pdf';        $ft='PDF';        $ic_color='#ef4444'; }
                      elseif (in_array($ext,['doc','docx']))       { $ic='fa-file-word';       $ft='Word';       $ic_color='#2563eb'; }
                      elseif (in_array($ext,['xls','xlsx']))       { $ic='fa-file-excel';      $ft='Excel';      $ic_color='#16a34a'; }
                      elseif (in_array($ext,['ppt','pptx']))       { $ic='fa-file-powerpoint'; $ft='PowerPoint'; $ic_color='#ea580c'; }
                      elseif (in_array($ext,['jpg','jpeg','png','gif'])) { $ic='fa-file-image'; $ft='Image';     $ic_color='#7c3aed'; }
                    ?>
                    <div class="file-row">
                      <label class="file-row-check">
                        <input type="checkbox" name="selected_files[]"
                               value="<?= htmlspecialchars($file['name']) ?>"
                               class="file-check-input file-checkbox">
                      </label>
                      <div class="file-row-icon" style="color:<?= $ic_color ?>">
                        <i class="fas <?= $ic ?>"></i>
                      </div>
                      <div class="file-row-info">
                        <span class="file-row-name" title="<?= htmlspecialchars($file['name']) ?>">
                          <?= htmlspecialchars($file['name']) ?>
                        </span>
                        <span class="file-row-meta"><?= $ft ?> &middot; <?= formatSizeUnits($file['size']) ?> &middot; <?= date('M d, Y', $file['modified']) ?></span>
                      </div>
                      <div class="file-row-actions">
                        <a href="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>"
                           class="file-action-btn download"
                           download="<?= htmlspecialchars($file['name']) ?>" title="Download">
                          <i class="fas fa-download"></i>
                        </a>
                        <button class="file-action-btn preview view-file-btn" title="Preview"
                                data-filepath="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>"
                                data-filetype="<?= $ft ?>"
                                data-filename="<?= htmlspecialchars($file['name']) ?>">
                          <i class="fas fa-eye"></i>
                        </button>
                        <a href="profile.php?delete_file=<?= urlencode($file['name']) ?>"
                           class="file-action-btn delete delete-file-btn"
                           data-filename="<?= htmlspecialchars($file['name']) ?>" title="Delete">
                          <i class="fas fa-trash"></i>
                        </a>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>

                </form><!-- /#bulkDeleteForm -->

                <!-- Delete-selected password modal -->
                <div class="modal fade" id="deleteSelectedModal" tabindex="-1" role="dialog" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash mr-2"></i>Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                      </div>
                      <div class="modal-body">
                        <p id="deleteSelectedSummary" class="mb-3" style="font-size:.9rem;color:var(--pf-text-body);"></p>
                        <div class="form-group mb-0">
                          <label for="delete_all_password" style="font-size:.82rem;font-weight:600;">Enter your password to confirm</label>
                          <div class="input-group">
                            <input type="password" class="form-control" id="delete_all_password"
                                   name="delete_all_password_display"
                                   placeholder="Your current password"
                                   autocomplete="current-password">
                            <div class="input-group-append">
                              <span class="input-group-text password-toggle"
                                    onclick="togglePassword('delete_all_password')"
                                    style="cursor:pointer;">
                                <i class="fas fa-eye"></i>
                              </span>
                            </div>
                          </div>
                          <div id="deletePasswordError" class="mt-1" style="font-size:.8rem;color:#ef4444;display:none;"></div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteSelectedBtn">
                          <i class="fas fa-trash mr-1"></i> Delete Files
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

              <?php endif; ?>

            </div><!-- /#file -->

            <!-- ════════════════════ SECURITY ════════════════════ -->
            <div class="tab-pane" id="password">

              <!-- Change Username -->
              <div class="pf-section-title">Change Username</div>
              <div class="row mb-4">
                <div class="col-md-7">
                  <div class="pf-form-card">
                    <div class="pf-form-title"><i class="fas fa-user-edit"></i> Update Username</div>
                    <form method="post">
                      <input type="hidden" name="change_username" value="1">
                      <div class="form-group">
                        <label>Current Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($current_username) ?>" disabled>
                      </div>
                      <div class="form-group">
                        <label for="new_username">New Username</label>
                        <input type="text" class="form-control" id="new_username" name="new_username"
                               required minlength="3" maxlength="30" placeholder="Enter new username"
                               pattern="[a-zA-Z0-9._]{3,30}">
                        <small class="password-hint">3–30 characters. Letters, numbers, dots (.) and underscores (_) only.</small>
                        <div id="usernameAvailability" class="mt-1" style="font-size:.82rem;font-weight:500;"></div>
                      </div>
                      <div class="form-group">
                        <label for="confirm_password_username">Confirm Your Password</label>
                        <div class="input-group">
                          <input type="password" class="form-control" id="confirm_password_username" name="confirm_password_username" required placeholder="Enter current password to confirm" autocomplete="current-password">
                          <div class="input-group-append">
                            <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password_username')"><i class="fas fa-eye"></i></span>
                          </div>
                        </div>
                      </div>
                      <button type="submit" class="btn btn-accent"><i class="fas fa-user-check mr-1"></i> Update Username</button>
                    </form>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="pf-guide-box">
                    <div class="guide-title"><i class="fas fa-info-circle mr-1"></i> Username Rules</div>
                    <ul>
                      <li>Must be between <strong>3 and 30 characters</strong>.</li>
                      <li>Only <strong>letters, numbers, dots (.)</strong> and <strong>underscores (_)</strong> are allowed.</li>
                      <li>Usernames are <strong>case-sensitive</strong>.</li>
                      <li>Your current password is required to confirm the change.</li>
                      <li>Choose a username that is easy to remember but hard to guess.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Change Password -->
              <div class="pf-section-title">Change Password</div>
              <div class="row">
                <div class="col-md-7">
                  <div class="pf-form-card">
                    <div class="pf-form-title"><i class="fas fa-lock"></i> Set New Password</div>
                    <form method="post">
                      <input type="hidden" name="change_password" value="1">
                      <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-group">
                          <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                          <div class="input-group-append">
                            <span class="input-group-text password-toggle" onclick="togglePassword('current_password')"><i class="fas fa-eye"></i></span>
                          </div>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-group">
                          <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
                          <div class="input-group-append">
                            <span class="input-group-text password-toggle" onclick="togglePassword('new_password')"><i class="fas fa-eye"></i></span>
                          </div>
                        </div>
                        <div class="password-hint">Use at least 8 characters with letters, numbers, and symbols</div>
                        <div class="password-strength mt-2">
                          <div class="progress"><div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
                          <small id="passwordStrengthText" class="text-muted">Password strength: Very weak</small>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-group">
                          <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password" autocomplete="new-password">
                          <div class="input-group-append">
                            <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password')"><i class="fas fa-eye"></i></span>
                          </div>
                        </div>
                        <div id="passwordMatch" class="password-match mt-2"></div>
                      </div>
                      <button type="submit" class="btn btn-accent"><i class="fas fa-sync-alt mr-1"></i> Change Password</button>
                    </form>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="pf-guide-box">
                    <div class="guide-title"><i class="fas fa-question-circle mr-1"></i> Forgot Password?</div>
                    <p style="font-size:.85rem;margin-bottom:14px;">If you've forgotten your password, you can request a password reset.</p>
                    <a href="../views/forgot_password.php" class="btn btn-accent-outline btn-sm"><i class="fas fa-key mr-1"></i> Reset Password</a>
                  </div>
                </div>
              </div>

            </div><!-- /#password -->

          </div><!-- /.tab-content -->
        </div><!-- /.pf-tab-content -->

      </div><!-- /.container-fluid -->
    </section>
  </div><!-- /.content-wrapper -->

  <?php include '../includes/mainfooter.php'; ?>
</div><!-- ./wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- ── File Preview Modal ── -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">File Preview</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="filePreviewContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <a id="downloadPreviewBtn" href="#" class="btn btn-primary" download><i class="fas fa-download"></i> Download</a>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const t = <?php echo json_encode($_SESSION['toast']); ?>;
    Swal.fire({ toast:true, position:'top-end', icon:t.type, title:t.message,
                showConfirmButton:false, timer:3000, timerProgressBar:true,
                didOpen:(el)=>{ el.addEventListener('mouseenter',Swal.stopTimer); el.addEventListener('mouseleave',Swal.resumeTimer); }});
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script src="../plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script>
/* ── Wait for jQuery (loaded by footer.php above) ── */
document.addEventListener('DOMContentLoaded', function () {

    /* ── Drag-and-drop upload area ── */
    const dropArea      = document.getElementById('fileDropArea');
    const fileInput     = document.getElementById('employee_files');
    const inner         = document.getElementById('fileDropInner');
    const selPreview    = document.getElementById('selectedFilesPreview');
    const previewList   = document.getElementById('filePreviewList');
    const clearBtn      = document.getElementById('clearFilesBtn');
    const uploadActions = document.getElementById('uploadActions');

    function fmtSize(b) {
        return b>=1048576?(b/1048576).toFixed(1)+' MB':b>=1024?(b/1024).toFixed(1)+' KB':b+' B';
    }
    function renderPicked(files) {
        if (!previewList) return;
        previewList.innerHTML = '';
        for (let f of files) {
            previewList.innerHTML += `<li class="file-preview-item">
              <i class="fas fa-file mr-2" style="color:var(--clr-accent)"></i>
              <span class="fpi-name">${f.name}</span>
              <span class="fpi-size">${fmtSize(f.size)}</span></li>`;
        }
        if (inner)         inner.style.display         = 'none';
        if (selPreview)    selPreview.style.display     = 'block';
        if (uploadActions) uploadActions.style.display  = 'flex';
    }
    function resetUpload() {
        if (fileInput)     fileInput.value              = '';
        if (inner)         inner.style.display          = 'block';
        if (selPreview)    selPreview.style.display      = 'none';
        if (previewList)   previewList.innerHTML         = '';
        if (uploadActions) uploadActions.style.display   = 'none';
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files.length) renderPicked(this.files);
            else resetUpload();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { resetUpload(); });
    }
    // Drop area click → open file picker directly (no overlay, no guard needed)
    if (dropArea && fileInput) {
        dropArea.addEventListener('click', function () {
            fileInput.click();
        });
        ['dragenter','dragover'].forEach(ev => dropArea.addEventListener(ev, function(e){
            e.preventDefault(); e.stopPropagation(); dropArea.classList.add('drag-over');
        }));
        ['dragleave','drop'].forEach(ev => dropArea.addEventListener(ev, function(e){
            e.preventDefault(); e.stopPropagation(); dropArea.classList.remove('drag-over');
        }));
        dropArea.addEventListener('drop', function(e){
            const dt = e.dataTransfer;
            if (dt && dt.files.length) { fileInput.files = dt.files; renderPicked(dt.files); }
        });
    }

    /* ── Checkbox / select-all / delete-selected ── */
    const selectAllCb    = document.getElementById('selectAllFiles');
    const deleteSelBtn   = document.getElementById('deleteSelectedBtn');
    const confirmDelBtn  = document.getElementById('confirmDeleteSelectedBtn');
    const delPwInput     = document.getElementById('delete_all_password');
    const delPwErr       = document.getElementById('deletePasswordError');
    const bulkForm       = document.getElementById('bulkDeleteForm');

    function updateDeleteBtn() {
        if (!deleteSelBtn) return;
        const checked = document.querySelectorAll('.file-checkbox:checked').length;
        deleteSelBtn.disabled = checked === 0;
        // Highlight selected rows
        document.querySelectorAll('.file-checkbox').forEach(cb => {
            cb.closest('.file-row').classList.toggle('selected', cb.checked);
        });
        // Update select-all indeterminate state
        if (selectAllCb) {
            const total = document.querySelectorAll('.file-checkbox').length;
            selectAllCb.indeterminate = checked > 0 && checked < total;
            selectAllCb.checked = checked === total && total > 0;
        }
    }

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function () {
            document.querySelectorAll('.file-checkbox').forEach(cb => { cb.checked = this.checked; });
            updateDeleteBtn();
        });
    }
    document.querySelectorAll('.file-checkbox').forEach(cb => {
        cb.addEventListener('change', updateDeleteBtn);
    });

    if (deleteSelBtn) {
        deleteSelBtn.addEventListener('click', function () {
            const checked = document.querySelectorAll('.file-checkbox:checked');
            const count   = checked.length;
            if (count === 0) return;
            const summary = document.getElementById('deleteSelectedSummary');
            if (summary) summary.textContent = `You are about to permanently delete ${count} file${count!==1?'s':''}. This cannot be undone.`;
            if (delPwInput)  delPwInput.value  = '';
            if (delPwErr)    { delPwErr.style.display='none'; delPwErr.textContent=''; }
            if (typeof $!=='undefined') $('#deleteSelectedModal').modal('show');
        else { const m=document.getElementById('deleteSelectedModal'); if(m && window.bootstrap) new window.bootstrap.Modal(m).show(); else if(m) $(m).modal('show'); }
        });
    }

    if (confirmDelBtn && bulkForm) {
        confirmDelBtn.addEventListener('click', function () {
            const pw = delPwInput ? delPwInput.value.trim() : '';
            if (!pw) {
                if (delPwErr) { delPwErr.textContent = 'Password is required.'; delPwErr.style.display = 'block'; }
                return;
            }
            // Inject the password into a hidden field in the bulk form
            let hiddenPw = bulkForm.querySelector('input[name="delete_all_password"]');
            if (!hiddenPw) {
                hiddenPw = document.createElement('input');
                hiddenPw.type  = 'hidden';
                hiddenPw.name  = 'delete_all_password';
                bulkForm.appendChild(hiddenPw);
            }
            hiddenPw.value = pw;
            bulkForm.submit();
        });
    }

    /* ── Single-file delete confirmation ── */
    document.querySelectorAll('.delete-file-btn').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            const url  = this.getAttribute('href');
            const name = this.dataset.filename;
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title:'Delete File?', text:`"${name}" will be permanently deleted.`,
                            icon:'warning', showCancelButton:true,
                            confirmButtonColor:'#d33', cancelButtonColor:'#3085d6',
                            confirmButtonText:'Yes, delete it!'
                }).then(r => { if (r.isConfirmed) window.location.href = url; });
            } else {
                if (confirm(`Delete "${name}"? This cannot be undone.`)) window.location.href = url;
            }
        });
    });

    /* ── File preview (PDF via iframe, images inline) ── */
    document.querySelectorAll('.view-file-btn').forEach(function (el) {
        el.addEventListener('click', function () {
            const fp = this.dataset.filepath;
            const ft = (this.dataset.filetype || '').toLowerCase();
            const con = document.getElementById('filePreviewContent');
            const dlBtn = document.getElementById('downloadPreviewBtn');
            if (!con) return;
            if (dlBtn) { dlBtn.href = fp; dlBtn.download = fp.split('/').pop(); }
            con.innerHTML = '';
            if (ft === 'pdf') {
                // Use iframe — works in all modern browsers without CSP issues
                con.innerHTML = `<iframe src="${fp}" width="100%" height="520" style="border:none;display:block;"
                    title="PDF Preview">
                    <p>Your browser cannot display this PDF inline.
                       <a href="${fp}" download>Download it</a> instead.</p>
                  </iframe>`;
            } else if (['jpg','jpeg','png','gif','image'].includes(ft)) {
                con.innerHTML = `<img src="${fp}" class="img-fluid" style="display:block;max-height:520px;margin:0 auto;" alt="Preview">`;
            } else {
                con.innerHTML = `<div class="text-center p-5">
                  <i class="fas fa-file fa-5x mb-3 text-muted"></i>
                  <p class="lead">This file type cannot be previewed.</p>
                  <p>Please download the file to view it.</p></div>`;
            }
            if (typeof $ !== 'undefined') $('#filePreviewModal').modal('show');
            else { const m=document.getElementById('filePreviewModal'); if(m && window.bootstrap) new window.bootstrap.Modal(m).show(); }
        });
    });

    /* ── Password toggle ── */
    // (also exposed globally as window.togglePassword for inline onclick)

    /* ── Password strength meter ── */
    const newPwIn  = document.getElementById('new_password');
    const strBar   = document.getElementById('passwordStrengthBar');
    const strText  = document.getElementById('passwordStrengthText');
    if (newPwIn && strBar && strText) {
        newPwIn.addEventListener('input', function () {
            let s = 0, p = this.value;
            if (p.length >= 8) s += 25;
            if (/\d/.test(p)) s += 25;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(p)) s += 25;
            if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s += 25;
            strBar.style.width = s + '%';
            const states = [[0,'Very weak','bg-danger'],[25,'Weak','bg-danger'],[50,'Fair','bg-warning'],[75,'Good','bg-info'],[100,'Strong','bg-success']];
            for (const [threshold, label, cls] of states.reverse()) {
                if (s >= threshold) { strText.textContent = 'Password strength: '+label; strBar.className = 'progress-bar '+cls; break; }
            }
        });
    }

    /* ── Password match indicator ── */
    const confPwIn = document.getElementById('confirm_password');
    const pwMatch  = document.getElementById('passwordMatch');
    if (confPwIn && pwMatch) {
        confPwIn.addEventListener('input', function () {
            const np = document.getElementById('new_password').value;
            if (this.value && np) {
                pwMatch.innerHTML = this.value === np
                    ? '<i class="fas fa-check-circle text-success"></i> Passwords match!'
                    : '<i class="fas fa-times-circle text-danger"></i> Passwords do not match!';
            } else { pwMatch.innerHTML = ''; }
        });
    }

    /* ── Username live validation ── */
    const newUnIn  = document.getElementById('new_username');
    const unAvail  = document.getElementById('usernameAvailability');
    if (newUnIn && unAvail) {
        newUnIn.addEventListener('input', function () {
            const val = this.value.trim();
            if (!val) { unAvail.innerHTML = ''; return; }
            unAvail.innerHTML = /^[a-zA-Z0-9._]{3,30}$/.test(val)
                ? '<i class="fas fa-check-circle text-success"></i> Format looks good!'
                : '<i class="fas fa-times-circle text-danger"></i> Invalid format.';
        });
    }

    /* ── Auto-open Security tab if URL hash is #password ── */
    if (window.location.hash === '#password') {
        const pwTab = document.querySelector('#mainTabs a[href="#password"]');
        if (pwTab) {
            // Use Bootstrap tab vanilla trigger
            const tabTrigger = new Event('click', { bubbles: true });
            pwTab.dispatchEvent(tabTrigger);
        }
    }

    /* ── Theme application ── */
    const themes = {
        admin:'linear-gradient(135deg,#4361ee,#3f37c9)',
        service:'linear-gradient(135deg,#ffc107,#fd7e14)',
        inventory:'linear-gradient(135deg,#28a745,#20c997)',
        file:'linear-gradient(135deg,#800020,#5a0a1d)',
        ict:'linear-gradient(135deg,#17a2b8,#138496)'
    };
    const cm = '<?= $current_module ?>';
    const themeGrad = themes[cm] || themes.admin;
    // Apply theme colours using vanilla JS (no jQuery dependency)
    document.querySelectorAll('.main-header').forEach(el => el.style.background = themeGrad);
    const footerEl = document.getElementById('mainFooter');
    if (footerEl) footerEl.style.background = themeGrad;
    document.body.classList.add('theme-' + cm);
    // Profile-link cookie sync (vanilla)
    document.querySelectorAll('.profile-dropdown a[href="profile.php"], .user-panel a[href="profile.php"]').forEach(function(el) {
        el.addEventListener('click', function() {
            const t = localStorage.getItem('currentTheme') || 'admin';
            document.cookie = 'current_module=' + t + '; path=/; max-age=300';
        });
    });
    // Storage sync (vanilla)
    window.addEventListener('storage', function(e) {
        if (e.key === 'currentTheme') {
            document.cookie = 'current_module=' + e.newValue + '; path=/; max-age=300';
            location.reload();
        }
    });
    document.title = 'HR System | My Profile (' + cm.charAt(0).toUpperCase() + cm.slice(1) + ')';
    localStorage.setItem('currentTheme', cm);

});/* end DOMContentLoaded */

/* ── Global password toggle (called from inline onclick) ── */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = input.parentNode.querySelector('.password-toggle i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        input.type = 'password';
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}
</script>
</body>
</html>