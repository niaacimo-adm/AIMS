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

// Lookup lists used by the "Hire as Employee" form
function fetchLookup($db, $sql) {
  $rows = [];
  try {
    $res = $db->query($sql);
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
  } catch (Exception $e) { /* table missing - leave list empty */ }
  return $rows;
}
$employmentStatuses  = fetchLookup($db, "SELECT status_id, status_name FROM employment_status ORDER BY status_name");
$appointmentStatuses = fetchLookup($db, "SELECT appointment_id, status_name FROM appointment_status ORDER BY status_name");
$sections            = fetchLookup($db, "SELECT section_id, section_name FROM section ORDER BY section_name");
$offices             = fetchLookup($db, "SELECT office_id, office_name FROM office ORDER BY office_name");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  
  // Create uploads directory if it doesn't exist
  $upload_dir = '../uploads/applicants/';
  if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
  }

  // Saves an uploaded file KEEPING ITS ORIGINAL NAME. The name is only cleaned of
  // path characters/commas (commas separate multiple files in the DB). If a file with
  // the same name already exists, " (1)", " (2)"... is added so nothing is overwritten,
  // unless the name is listed in $reuse (an old file about to be replaced).
  function saveOriginalName($tmp, $original, $upload_dir, $reuse = []) {
    $name = basename(str_replace('\\', '/', $original));
    $name = preg_replace('/[\x00-\x1F\/\\:*?"<>|,]/', '_', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') $name = 'file';
    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $final = $name;
    $n = 1;
    while (file_exists($upload_dir . $final) && !in_array($final, $reuse, true)) {
      $final = $base . ' (' . $n++ . ')' . ($ext !== '' ? '.' . $ext : '');
    }
    move_uploaded_file($tmp, $upload_dir . $final);
    return $final;
  }

  // Saves every file from a multi-file input (name="field[]") and returns
  // their generated filenames as a comma-separated string (or null if none).
  function handleMultiUpload($fieldName, $prefix, $upload_dir, $reuse = []) {
    if (!isset($_FILES[$fieldName])) return null;
    $names = $_FILES[$fieldName]['name'];
    if (!is_array($names)) return null; // no files selected
    $saved = [];
    foreach ($names as $i => $name) {
      if ($_FILES[$fieldName]['error'][$i] === 0 && $name !== '') {
        $saved[] = saveOriginalName($_FILES[$fieldName]['tmp_name'][$i], $name, $upload_dir, $reuse);
      }
    }
    return count($saved) ? implode(',', $saved) : null;
  }
  
  if ($_POST['action'] === 'create') {
    try {
      $first_name = $_POST['first_name'];
      $middle_name = $_POST['middle_name'] ?? null;
      $last_name = $_POST['last_name'];
      $email = $_POST['email'];
      $phone_number = $_POST['phone_number'];
      $address = $_POST['address'];
      $position_applied = (isset($_POST['position_applied']) && is_string($_POST['position_applied'])) ? trim($_POST['position_applied']) : '';
      $application_date = $_POST['application_date'];
      $gender = $_POST['gender'] ?? null;
      $status = $_POST['status'];
      $remarks = $_POST['remarks'] ?? null;
      
      // Handle file uploads
      $resume_file = null;
      $cover_letter_file = null;
      $other_documents = null;
      $pds_file = null;
      
      if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
        $resume_file = saveOriginalName($_FILES['resume_file']['tmp_name'], $_FILES['resume_file']['name'], $upload_dir);
      }
      
      if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] === 0) {
        $cover_letter_file = saveOriginalName($_FILES['cover_letter_file']['tmp_name'], $_FILES['cover_letter_file']['name'], $upload_dir);
      }
      
      if (isset($_FILES['pds_file']) && $_FILES['pds_file']['error'] === 0) {
        $pds_file = saveOriginalName($_FILES['pds_file']['tmp_name'], $_FILES['pds_file']['name'], $upload_dir);
      }

      if (isset($_FILES['other_documents']) && is_array($_FILES['other_documents']['name'])) {
        $other_documents = handleMultiUpload('other_documents', 'other', $upload_dir);
      }
      
      $stmt = $db->prepare("INSERT INTO applicant (first_name, middle_name, last_name, email, phone_number, address, position_applied, gender, application_date, status, remarks, resume_file, cover_letter_file, other_documents, pds_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sssssssssssssss", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $position_applied, $gender, $application_date, $status, $remarks, $resume_file, $cover_letter_file, $other_documents, $pds_file);
      
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
      $position_applied = (isset($_POST['position_applied']) && is_string($_POST['position_applied'])) ? trim($_POST['position_applied']) : '';
      $application_date = $_POST['application_date'];
      $gender = $_POST['gender'] ?? null;
      $status = $_POST['status'];
      $remarks = $_POST['remarks'] ?? null;
      
      // Get existing files
      $stmt = $db->prepare("SELECT resume_file, cover_letter_file, other_documents, pds_file FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      
      $resume_file = $existing['resume_file'];
      $cover_letter_file = $existing['cover_letter_file'];
      $other_documents = $existing['other_documents'];
      $pds_file = $existing['pds_file'];
      
      // Handle file uploads
      if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
        if ($resume_file && file_exists($upload_dir . $resume_file)) {
          unlink($upload_dir . $resume_file);
        }
        $resume_file = saveOriginalName($_FILES['resume_file']['tmp_name'], $_FILES['resume_file']['name'], $upload_dir);
      }
      
      if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] === 0) {
        if ($cover_letter_file && file_exists($upload_dir . $cover_letter_file)) {
          unlink($upload_dir . $cover_letter_file);
        }
        $cover_letter_file = saveOriginalName($_FILES['cover_letter_file']['tmp_name'], $_FILES['cover_letter_file']['name'], $upload_dir);
      }
      
      if (isset($_FILES['pds_file']) && $_FILES['pds_file']['error'] === 0) {
        if ($pds_file && file_exists($upload_dir . $pds_file)) {
          unlink($upload_dir . $pds_file);
        }
        $pds_file = saveOriginalName($_FILES['pds_file']['tmp_name'], $_FILES['pds_file']['name'], $upload_dir);
      }

      // Other documents: remove the ones the user clicked "x" on, then ADD any new uploads
      $current_list = $other_documents ? array_values(array_filter(explode(',', $other_documents))) : [];
      $remove_list  = (isset($_POST['remove_other']) && is_array($_POST['remove_other'])) ? $_POST['remove_other'] : [];
      if ($remove_list) {
        $kept = [];
        foreach ($current_list as $old_file) {
          if (in_array($old_file, $remove_list, true)) {
            if (file_exists($upload_dir . $old_file)) unlink($upload_dir . $old_file);
          } else {
            $kept[] = $old_file;
          }
        }
        $current_list = $kept;
      }
      if (isset($_FILES['other_documents']) && is_array($_FILES['other_documents']['name'])) {
        $new_other = handleMultiUpload('other_documents', 'other', $upload_dir);
        if ($new_other !== null) {
          $current_list = array_merge($current_list, explode(',', $new_other));
        }
      }
      $other_documents = count($current_list) ? implode(',', $current_list) : null;

      $stmt = $db->prepare("UPDATE applicant SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, position_applied=?, gender=?, application_date=?, status=?, remarks=?, resume_file=?, cover_letter_file=?, other_documents=?, pds_file=? WHERE applicant_id=?");
      $stmt->bind_param("sssssssssssssssi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $position_applied, $gender, $application_date, $status, $remarks, $resume_file, $cover_letter_file, $other_documents, $pds_file, $applicant_id);
      
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
      $stmt = $db->prepare("SELECT resume_file, cover_letter_file, other_documents, pds_file FROM applicant WHERE applicant_id = ?");
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
      if ($files['pds_file'] && file_exists($upload_dir . $files['pds_file'])) {
        unlink($upload_dir . $files['pds_file']);
      }
      if ($files['other_documents']) {
        foreach (explode(',', $files['other_documents']) as $other_file) {
          if ($other_file && file_exists($upload_dir . $other_file)) {
            unlink($upload_dir . $other_file);
          }
        }
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
  
  // Hire an ACCEPTED applicant: create the employee record and remove the applicant (one transaction)
  if ($_POST['action'] === 'hire') {
    $new_picture = '';
    $picture_path = '';
    try {
      $applicant_id = (int)($_POST['applicant_id'] ?? 0);

      $stmt = $db->prepare("SELECT * FROM applicant WHERE applicant_id = ?");
      $stmt->bind_param("i", $applicant_id);
      $stmt->execute();
      $app = $stmt->get_result()->fetch_assoc();
      if (!$app) { echo json_encode(['success' => false, 'message' => 'Applicant not found. They may have already been hired.']); exit(); }
      if ($app['status'] !== 'Accepted') { echo json_encode(['success' => false, 'message' => 'Only Accepted applicants can be hired.']); exit(); }

      $first_name  = trim($_POST['first_name'] ?? '');
      $middle_name = trim($_POST['middle_name'] ?? '');
      $last_name   = trim($_POST['last_name'] ?? '');
      $ext_name    = trim($_POST['ext_name'] ?? '');
      $gender      = $_POST['gender'] ?? '';
      $bday        = $_POST['bday'] ?? '';
      $email       = trim($_POST['email'] ?? '');
      $phone       = trim($_POST['phone_number'] ?? '');
      $address     = trim($_POST['address'] ?? '');
      $emp_status  = (int)($_POST['employment_status_id'] ?? 0);
      $appt_status = (int)($_POST['appointment_status_id'] ?? 0);
      $position_id = (int)($_POST['position_id'] ?? 0);
      $section_id  = (int)($_POST['section_id'] ?? 0);
      $office_id   = (int)($_POST['office_id'] ?? 0);
      $id_number   = trim($_POST['id_number'] ?? '');

      if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $address === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']); exit();
      }
      if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        echo json_encode(['success' => false, 'message' => 'Please select a gender.']); exit();
      }
      $bd = DateTime::createFromFormat('Y-m-d', $bday);
      if (!$bd || $bd->format('Y-m-d') !== $bday || $bday > date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid birthday.']); exit();
      }
      if (!$emp_status || !$appt_status || !$position_id || !$section_id || !$office_id) {
        echo json_encode(['success' => false, 'message' => 'Please select the employment status, appointment status, position, section and office.']); exit();
      }

      // Employee ID comes from the Regional Office and is typed in by the user; it must be unique
      if ($id_number === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter the Employee ID issued by the Regional Office.']); exit();
      }
      {
        $chk = $db->prepare("SELECT 1 FROM employee WHERE TRIM(id_number) = ? LIMIT 1");
        $chk->bind_param("s", $id_number);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
          echo json_encode(['success' => false, 'message' => 'That Employee ID is already used by another employee.']); exit();
        }
      }

      // Optional profile picture (same folder/naming as the employee module)
      if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
          echo json_encode(['success' => false, 'message' => 'Profile picture must be JPG, PNG, GIF or WEBP.']); exit();
        }
        $pic_dir = '../assets/images/employees/';
        if (!file_exists($pic_dir)) { mkdir($pic_dir, 0777, true); }
        $safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($_FILES['picture']['name']));
        $new_picture = uniqid() . '_' . $safe;
        $picture_path = $pic_dir . $new_picture;
        if (!move_uploaded_file($_FILES['picture']['tmp_name'], $picture_path)) {
          echo json_encode(['success' => false, 'message' => 'Could not save the profile picture.']); exit();
        }
      }

      $db->begin_transaction();

      $ins = $db->prepare("INSERT INTO employee (picture, id_number, first_name, middle_name, last_name, ext_name, gender, address, bday, email, phone_number, employment_status_id, appointment_status_id, section_id, office_id, position_id, is_manager, is_manager_office_staff) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
      $ins->bind_param("sssssssssssiiiii", $new_picture, $id_number, $first_name, $middle_name, $last_name, $ext_name, $gender, $address, $bday, $email, $phone, $emp_status, $appt_status, $section_id, $office_id, $position_id);
      $ins->execute();

      $del = $db->prepare("DELETE FROM applicant WHERE applicant_id = ?");
      $del->bind_param("i", $applicant_id);
      $del->execute();

      $db->commit();

      // Applicant is gone - remove their uploaded documents too
      $to_delete = [$app['resume_file'], $app['cover_letter_file'], $app['pds_file']];
      if (!empty($app['other_documents'])) { $to_delete = array_merge($to_delete, explode(',', $app['other_documents'])); }
      foreach ($to_delete as $f) {
        $f = trim((string)$f);
        if ($f !== '' && file_exists($upload_dir . $f)) { unlink($upload_dir . $f); }
      }

      echo json_encode(['success' => true, 'message' => trim($first_name . ' ' . $last_name) . ' was hired and added to the employee list (Employee ID: ' . $id_number . ').']);
    } catch (Exception $e) {
      try { $db->rollback(); } catch (Exception $e2) {}
      if ($picture_path !== '' && file_exists($picture_path)) { unlink($picture_path); }
      echo json_encode(['success' => false, 'message' => 'Hiring failed: ' . $e->getMessage()]);
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
    :root {
      --h-bg:       #eef7f2;
      --h-card:     #ffffff;
      --h-card-alt: #f0faf5;
      --h-surface3: #e2f3ea;
      --h-border:   rgba(42,152,99,0.18);
      --h-text:     #0f2d1e;
      --h-muted:    #4a7a5e;
      --h-primary:  #2a9863;
      --h-primary-dim: rgba(42,152,99,.10);
      --h-accent:   #24e78f;
      --h-success:  #2a9863;
      --h-warning:  #e67700;
      --h-danger:   #c92a2a;
      --h-blue:     #2a9863;
      --h-shadow:   0 4px 24px rgba(42,152,99,.12);
      --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
      --h-bg:       #0b1f17;
      --h-card:     #102f22;
      --h-card-alt: #0e2619;
      --h-surface3: #16352a;
      --h-border:   rgba(36,231,143,0.12);
      --h-text:     #d4f5e5;
      --h-muted:    #6aad8a;
      --h-primary:  #24e78f;
      --h-primary-dim: rgba(36,231,143,.12);
      --h-accent:   #2a9863;
      --h-success:  #24e78f;
      --h-warning:  #ffd43b;
      --h-danger:   #ff6b6b;
      --h-blue:     #24e78f;
      --h-shadow:   0 4px 24px rgba(0,0,0,.35);
      --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }

    .content-wrapper { background: var(--h-bg) !important; }

    /* CRITICAL Z-INDEX FIX FOR MODAL OVERLAP */
    .main-header.navbar, .main-header, nav.main-header, header.main-header {
        z-index: 1000 !important;
    }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    
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
  
        .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0;
            display:flex; flex-wrap:wrap; gap:2px;
        }
        .pg-hero-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color:rgba(212,245,229,.45); }
        .pg-hero-bc-link   { color:rgba(212,245,229,.65); text-decoration:none; font-size:.8rem; }
        .pg-hero-bc-link:hover { color:#24e78f; }
        .pg-hero-bc-active { color:rgba(212,245,229,.9); font-size:.8rem; }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-actions {
            position:relative; z-index:2;
            display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px;
        }
        .pg-hero-date { color:rgba(212,245,229,.65); font-size:.82rem; align-self:center; }
        .pg-hero-btn {
            background:rgba(36,231,143,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(36,231,143,.3); color:#d4f5e5;
            border-radius:10px; padding:8px 16px;
            font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .2s, transform .18s, box-shadow .2s;
        }
        .pg-hero-btn:hover {
            background:rgba(36,231,143,.22); border-color:rgba(36,231,143,.55);
            transform:translateY(-2px); box-shadow:0 4px 16px rgba(36,231,143,.2);
            color:#d4f5e5; text-decoration:none;
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
        }

        /* ── Card (mirrors the Scrum > Projects "card" look) ── */
        .card {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 12px !important;
            box-shadow: var(--h-shadow-sm) !important;
        }
        .card-header {
            background: var(--h-card) !important;
            border-bottom: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
        }
        .card-title {
            font-weight: 700 !important;
            font-size: .9rem !important;
            color: var(--h-text) !important;
            letter-spacing: -.2px;
        }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: .8rem !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }

        /* ── Table ── */
        #applicantsTable.table { color: var(--h-text) !important; }
        #applicantsTable.table thead th {
            background: var(--h-card-alt) !important;
            border: none !important;
            border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important;
            font-size: .68rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .6px !important;
            padding: 10px 14px !important;
        }
        #applicantsTable.table tbody tr { transition: background .12s; }
        #applicantsTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #applicantsTable.table tbody td {
            border-top: 1px solid var(--h-border) !important;
            border-left: none !important;
            border-right: none !important;
            padding: 12px 14px !important;
            vertical-align: middle !important;
            font-size: .85rem !important;
            color: var(--h-text) !important;
        }
        #applicantsTable.table-bordered { border: none !important; }

        /* Status badges - each status has its own distinct color */
        .badge { font-weight: 700 !important; padding: 5px 10px !important; border-radius: 6px !important; letter-spacing: .2px; }
        .badge-secondary { background: #e9ecef !important; color: #495057 !important; }
        .badge-warning   { background: #fff3cd !important; color: #9a6700 !important; }  /* Pending */
        .badge-info      { background: #dbeafe !important; color: #1d4ed8 !important; }  /* For Review */
        .badge-primary   { background: #ede9fe !important; color: #6d28d9 !important; }  /* For Interview */
        .badge-success   { background: #d1fae5 !important; color: #047857 !important; }  /* Accepted */
        .badge-danger    { background: #fee2e2 !important; color: #b91c1c !important; }  /* Rejected */

        /* Row action buttons */
        #applicantsTable .btn-sm.btn-info    { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
        #applicantsTable .btn-sm.btn-warning { background: rgba(230,119,0,.12) !important; color: var(--h-warning) !important; border: 1px solid rgba(230,119,0,.25) !important; border-radius: 7px !important; }
        #applicantsTable .btn-sm.btn-danger  { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 7px !important; }
        #applicantsTable .btn-sm:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* ── Scrum-style modal (mirrors the "Create New Project" modal in
           Scrum > Projects) — applied to Add/Edit/View Applicant ── */
        .scrum-style-modal .modal-content {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 14px !important;
            box-shadow: 0 24px 80px rgba(0,0,0,.25) !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-header {
            background: var(--h-card-alt) !important;
            border-bottom: 1px solid var(--h-border) !important;
            border-radius: 14px 14px 0 0 !important;
            padding: 16px 20px !important;
        }
        .scrum-style-modal .modal-title {
            font-weight: 700 !important;
            font-size: .95rem !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-title i { color: var(--h-primary) !important; margin-right: 8px; }
        .scrum-style-modal .modal-header .close {
            color: var(--h-muted) !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        .scrum-style-modal .modal-header .close:hover { opacity: .7 !important; }
        .scrum-style-modal .modal-body { padding: 20px !important; background: var(--h-card) !important; }
        .scrum-style-modal .modal-footer {
            border-top: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
            background: var(--h-card-alt) !important;
            border-radius: 0 0 14px 14px !important;
        }
        .scrum-style-modal label {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
            margin-bottom: 5px !important;
        }
        .scrum-style-modal label .text-danger { text-transform: none !important; }
        .scrum-style-modal .form-control,
        .scrum-style-modal .form-control:focus {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            font-size: .875rem !important;
        }
        .scrum-style-modal .form-control::placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .form-control:focus {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal select.form-control option { background: var(--h-card) !important; color: var(--h-text) !important; }
        .scrum-style-modal hr { border-color: var(--h-border) !important; }
        .scrum-style-modal h6 {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
        }
        .scrum-style-modal .form-text.text-muted { color: var(--h-muted) !important; }
        .scrum-style-modal .info-box {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            font-size: .8rem !important;
            color: var(--h-text) !important;
            /* AdminLTE's .info-box forces min-height:80px + shadow; make it a slim notice */
            min-height: 0 !important;
            height: auto !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: none !important;
            margin-bottom: 14px !important;
        }
        .scrum-style-modal .info-box i { margin: 0 !important; flex-shrink: 0; color: var(--h-primary); }

        /* Tighter spacing for file fields in the Documents tabs */
        #addTabDocuments .form-group,
        #editTabDocuments .form-group { margin-bottom: 12px !important; }
        #addTabDocuments .form-group > label,
        #editTabDocuments .form-group > label { margin-bottom: 4px !important; }
        #addTabDocuments .custom-file, #editTabDocuments .custom-file,
        #addTabDocuments .custom-file-label, #editTabDocuments .custom-file-label { height: 36px !important; line-height: 1.4 !important; }
        #addTabDocuments .form-text, #editTabDocuments .form-text { margin-top: 4px !important; font-size: .78rem !important; word-break: break-word; }

        /* Removable document chips (Other Documents) */
        .doc-chip-list { margin-top: 6px; }
        .doc-chip-list:empty { display: none; }
        .doc-chip {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            background: var(--h-card-alt); border: 1px solid var(--h-border); border-radius: 8px;
            padding: 5px 6px 5px 10px; margin-bottom: 4px; font-size: .8rem; color: var(--h-text);
        }
        .doc-chip .doc-chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .doc-chip .doc-chip-tag { font-size: .65rem; font-weight: 700; text-transform: uppercase; color: var(--h-primary); margin-right: 6px; }
        .doc-chip .doc-chip-remove {
            border: 0; background: transparent; color: #c92a2a; width: 24px; height: 24px; border-radius: 50%;
            line-height: 1; cursor: pointer; flex-shrink: 0;
        }
        .doc-chip .doc-chip-remove:hover { background: rgba(201,42,42,.12); }

        /* Bootstrap custom-file input */
        .scrum-style-modal .custom-file-label {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
        }
        .scrum-style-modal .custom-file-label::after {
            background: var(--h-surface3) !important;
            color: var(--h-muted) !important;
            border-left: 1.5px solid var(--h-border) !important;
            border-radius: 0 8px 8px 0 !important;
        }
        .scrum-style-modal .custom-file-input:focus ~ .custom-file-label {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }

        /* Select2 (default theme) — Position Applied picker */
        .scrum-style-modal .select2-container--default .select2-selection--multiple {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            min-height: 38px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--h-primary-dim) !important;
            border: 1px solid var(--h-primary) !important;
            color: var(--h-text) !important;
            border-radius: 5px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove { color: var(--h-muted) !important; }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove:hover { color: var(--h-danger) !important; }
        .scrum-style-modal .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .select2-dropdown {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            box-shadow: var(--h-shadow) !important;
        }
        .scrum-style-modal .select2-search--dropdown .select2-search__field {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 6px !important;
        }
        .scrum-style-modal .select2-results__option { color: var(--h-text) !important; background: transparent !important; }
        .scrum-style-modal .select2-results__option--highlighted[aria-selected] {
            background: var(--h-primary) !important;
            color: #fff !important;
        }

        /* Buttons */
        .scrum-style-modal .btn-secondary {
            background: var(--h-card-alt) !important;
            color: var(--h-muted) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
        }
        .scrum-style-modal .btn-primary,
        .scrum-style-modal .btn-warning {
            background: var(--h-primary) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .btn-primary:hover,
        .scrum-style-modal .btn-warning:hover { filter: brightness(1.1) !important; color: #fff !important; }

        /* View Applicant detail content */
        .scrum-style-modal #viewApplicantContent p { color: var(--h-text) !important; }
        .scrum-style-modal #viewApplicantContent strong { color: var(--h-muted) !important; font-weight: 700 !important; }
        .scrum-style-modal #viewApplicantContent a { color: var(--h-primary) !important; }
        .scrum-style-modal #viewApplicantContent hr { border-color: var(--h-border) !important; }

        /* Applicant Details - profile header + info grid */
        .ad-profile {
            display: flex; align-items: center; gap: 14px;
            background: var(--h-card-alt); border: 1px solid var(--h-border);
            border-radius: 12px; padding: 14px 16px; margin-bottom: 14px;
        }
        .ad-avatar {
            width: 54px; height: 54px; flex-shrink: 0; border-radius: 50%;
            background: var(--h-primary); color: #fff !important;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; font-weight: 800; letter-spacing: .5px;
            box-shadow: 0 2px 12px var(--h-primary-dim);
        }
        .ad-profile-info { min-width: 0; flex: 1; }
        .ad-name { font-size: 1.05rem; font-weight: 800; color: var(--h-text) !important; line-height: 1.2; margin: 0 0 3px; word-break: break-word; }
        .ad-position { font-size: .82rem; color: var(--h-muted) !important; margin: 0; }
        .ad-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .ad-field {
            background: var(--h-card-alt); border: 1px solid var(--h-border);
            border-radius: 10px; padding: 9px 12px; min-width: 0;
        }
        .ad-field.ad-full { grid-column: 1 / -1; }
        .ad-label {
            display: flex; align-items: center; gap: 6px;
            font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
            color: var(--h-muted) !important; margin-bottom: 2px;
        }
        .ad-label i { color: var(--h-primary); width: 12px; text-align: center; }
        .ad-value { font-size: .88rem; font-weight: 500; color: var(--h-text) !important; word-break: break-word; white-space: pre-line; }
        .ad-value.ad-empty { color: var(--h-muted) !important; font-style: italic; }
        @media (max-width: 575px) { .ad-grid { grid-template-columns: 1fr; } }

        /* Applicant Details tabs */
        .applicant-detail-tabs {
            border-bottom: 1px solid var(--h-border) !important;
            margin: -20px -20px 18px !important;
            padding: 0 20px !important;
        }
        .applicant-detail-tabs .nav-link {
            border: none !important;
            border-bottom: 2px solid transparent !important;
            color: var(--h-muted) !important;
            font-size: .82rem !important;
            font-weight: 700 !important;
            padding: 12px 14px !important;
            border-radius: 0 !important;
            transition: color .15s, border-color .15s;
        }
        .applicant-detail-tabs .nav-link:hover { color: var(--h-text) !important; }
        .applicant-detail-tabs .nav-link.active {
            color: var(--h-primary) !important;
            background: transparent !important;
            border-bottom-color: var(--h-primary) !important;
        }
        .applicant-detail-tab-content { padding-top: 2px; }

        /* Uploaded documents nav list */
        .doc-nav-list { display: flex; flex-direction: column; gap: 8px; }
        .doc-nav-item {
            display: flex; align-items: center; gap: 12px;
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            text-decoration: none !important;
            transition: border-color .15s, transform .15s, box-shadow .15s;
        }
        .doc-nav-item:not(.doc-nav-item-empty):hover {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 1px var(--h-primary-dim) !important;
            transform: translateY(-1px);
        }
        .doc-nav-item-empty { opacity: .6; cursor: default; }
        .doc-nav-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: var(--h-surface3) !important;
            border-radius: 8px !important;
            font-size: .95rem;
        }
        .doc-nav-info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .doc-nav-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--h-muted) !important;
        }
        .doc-nav-file {
            font-size: .84rem; color: var(--h-text) !important; font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .doc-nav-item-empty .doc-nav-file { color: var(--h-muted) !important; font-style: italic; }
        .doc-nav-open {
            color: var(--h-muted) !important;
            font-size: .8rem; flex-shrink: 0;
        }
        .doc-nav-item:hover .doc-nav-open { color: var(--h-primary) !important; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
      
        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-hex"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-user-friends"></i>Applicant Databank</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Browse and manage job applicants</p>
                </div>
            </div>
        </div>

      <div class="content">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Applicants</h3>
              <div class="card-tools">
                <?php
                  $years = [];
                  foreach ($applicants as $a) { $y = date('Y', strtotime($a['application_date'])); $years[$y] = $y; }
                  krsort($years);
                ?>
                <div class="btn-group mr-1">
                  <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" id="yearFilterBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-filter"></i> <span id="yearFilterLabel">All Years</span>
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item year-filter-item active" href="#" data-year="">All Years</a>
                    <?php foreach ($years as $y): ?>
                      <a class="dropdown-item year-filter-item" href="#" data-year="<?= $y ?>"><?= $y ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addApplicantModal">
                  <i class="fas fa-plus"></i> Add Applicant
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="applicantsTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Position Applied</th>
                    <th>Application Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $rowNo = 0; foreach ($applicants as $applicant): $rowNo++; ?>
                  <tr>
                    <td><?= $rowNo ?></td>
                    <td><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['middle_name'] . ' ' . $applicant['last_name']) ?></td>
                    <td><?= htmlspecialchars($applicant['gender'] ?? '') ?></td>
                    <td><?= $applicant['position_applied'] !== null && trim($applicant['position_applied']) !== '' ? htmlspecialchars($applicant['position_applied']) : '<span class="text-muted"><em>Any available position</em></span>' ?></td>
                    <td data-order="<?= date('Y-m-d', strtotime($applicant['application_date'])) ?>" data-year="<?= date('Y', strtotime($applicant['application_date'])) ?>"><?= date('M d, Y', strtotime($applicant['application_date'])) ?></td>
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
                      <?php if ($applicant['status'] === 'Accepted'): ?>
                      <button class="btn btn-sm btn-success hire-btn" data-id="<?= $applicant['applicant_id'] ?>" title="Hire as Employee">
                        <i class="fas fa-user-check"></i>
                      </button>
                      <?php endif; ?>
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
  <div class="modal fade scrum-style-modal" id="addApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addApplicantForm" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-plus"></i>Add New Applicant</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <ul class="nav nav-tabs applicant-detail-tabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#addTabInfo" role="tab">
                  <i class="fas fa-user mr-1"></i>Applicant Info
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#addTabDocuments" role="tab">
                  <i class="fas fa-paperclip mr-1"></i>Documents
                </a>
              </li>
            </ul>
            <div class="tab-content applicant-detail-tab-content">
              <div class="tab-pane fade show active" id="addTabInfo" role="tabpanel">
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
                  <div class="col-md-3">
                    <div class="form-group">
                      <label>Gender <span class="text-danger">*</span></label>
                      <select name="gender" class="form-control" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <div class="form-group">
                      <label>Email <span class="text-danger">*</span></label>
                      <input type="email" name="email" class="form-control" required>
                    </div>
                  </div>
                  <div class="col-md-4">
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
                  <div class="col-md-5">
                    <div class="form-group">
                      <label>Position Applied <small class="font-weight-normal" style="text-transform:none;">(optional — leave blank if applying to the office)</small></label>
                      <select name="position_applied" class="form-control select2" multiple="multiple">
                        <option value="">-- Select Position --</option>
                        <?php foreach ($positions as $position): ?>
                          <option value="<?= htmlspecialchars($position['position_name']) ?>">
                            <?= htmlspecialchars($position['position_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Application Date <span class="text-danger">*</span></label>
                      <input type="date" name="application_date" class="form-control" required>
                    </div>
                  </div>
                  <div class="col-md-3">
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
                  </div>
                </div>
                <div class="form-group">
                  <label>Remarks</label>
                  <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
              </div>
              <div class="tab-pane fade" id="addTabDocuments" role="tabpanel">
                <div class="info-box mb-3">
                  <i class="fas fa-info-circle mr-1"></i>Accepted formats: PDF, DOC, DOCX (PDS also accepts XLS, XLSX) — Max 5MB each.
                </div>
                <div class="form-group">
                  <label>Resume</label>
                  <div class="custom-file">
                    <input type="file" name="resume_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Cover Letter</label>
                  <div class="custom-file">
                    <input type="file" name="cover_letter_file" class="custom-file-input" accept=".pdf,.doc,.docx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
                <div class="form-group">
                  <label>PDS (Personal Data Sheet)</label>
                  <div class="custom-file">
                    <input type="file" name="pds_file" class="custom-file-input" accept=".pdf,.doc,.docx,.xls,.xlsx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Other Documents <small class="font-weight-normal" style="text-transform:none;">(you can select multiple files)</small></label>
                  <div class="custom-file">
                    <input type="file" name="other_documents[]" class="custom-file-input other-docs-input" accept=".pdf,.doc,.docx" multiple>
                    <label class="custom-file-label">Choose files</label>
                  </div>
                  <div class="doc-chip-list other-docs-selected"></div>
                </div>
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
  <div class="modal fade scrum-style-modal" id="editApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="editApplicantForm" enctype="multipart/form-data">
          <input type="hidden" name="applicant_id" id="edit_applicant_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-edit"></i>Edit Applicant</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <ul class="nav nav-tabs applicant-detail-tabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#editTabInfo" role="tab">
                  <i class="fas fa-user mr-1"></i>Applicant Info
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#editTabDocuments" role="tab">
                  <i class="fas fa-paperclip mr-1"></i>Documents
                </a>
              </li>
            </ul>
            <div class="tab-content applicant-detail-tab-content">
              <div class="tab-pane fade show active" id="editTabInfo" role="tabpanel">
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
                  <div class="col-md-3">
                    <div class="form-group">
                      <label>Gender <span class="text-danger">*</span></label>
                      <select name="gender" id="edit_gender" class="form-control" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <div class="form-group">
                      <label>Email <span class="text-danger">*</span></label>
                      <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                  </div>
                  <div class="col-md-4">
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
                  <div class="col-md-5">
                    <div class="form-group">
                      <label>Position Applied <small class="font-weight-normal" style="text-transform:none;">(optional — leave blank if applying to the office)</small></label>
                      <select name="position_applied" id="edit_position_applied" class="form-control select2" multiple="multiple">
                        <option value="">-- Select Position --</option>
                        <?php foreach ($positions as $position): ?>
                          <option value="<?= htmlspecialchars($position['position_name']) ?>">
                            <?= htmlspecialchars($position['position_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Application Date <span class="text-danger">*</span></label>
                      <input type="date" name="application_date" id="edit_application_date" class="form-control" required>
                    </div>
                  </div>
                  <div class="col-md-3">
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
                  </div>
                </div>
                <div class="form-group">
                  <label>Remarks</label>
                  <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
                </div>
              </div>
              <div class="tab-pane fade" id="editTabDocuments" role="tabpanel">
                <div class="info-box mb-3">
                  <i class="fas fa-info-circle mr-1"></i>Leave a field blank to keep the existing file.
                </div>
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
                  <label>PDS (Personal Data Sheet)</label>
                  <div class="custom-file">
                    <input type="file" name="pds_file" class="custom-file-input" accept=".pdf,.doc,.docx,.xls,.xlsx">
                    <label class="custom-file-label">Choose file</label>
                  </div>
                  <small class="form-text text-muted" id="current_pds"></small>
                </div>
                <div class="form-group">
                  <label>Other Documents <small class="font-weight-normal" style="text-transform:none;">(select multiple files; new ones are added to the existing list)</small></label>
                  <div class="custom-file">
                    <input type="file" name="other_documents[]" class="custom-file-input other-docs-input" accept=".pdf,.doc,.docx" multiple>
                    <label class="custom-file-label">Choose files</label>
                  </div>
                  <div class="doc-chip-list" id="current_other_list"></div>
                  <div class="doc-chip-list doc-chip-new other-docs-selected"></div>
                </div>
              </div>
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

  <!-- Hire Applicant Modal -->
  <div class="modal fade scrum-style-modal" id="hireApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="hireApplicantForm" enctype="multipart/form-data">
          <input type="hidden" name="applicant_id" id="hire_applicant_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-check"></i>Hire as Employee</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="info-box">
              <i class="fas fa-info-circle"></i>
              <span>Review the details below and complete the employee information. When you confirm, the applicant is added to the employee list and <strong>removed from the Applicant Databank</strong>.</span>
            </div>

            <div class="row">
              <div class="col-md-4"><div class="form-group"><label>First Name <span class="text-danger">*</span></label><input type="text" name="first_name" id="hire_first_name" class="form-control" required></div></div>
              <div class="col-md-3"><div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="hire_middle_name" class="form-control"></div></div>
              <div class="col-md-3"><div class="form-group"><label>Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" id="hire_last_name" class="form-control" required></div></div>
              <div class="col-md-2"><div class="form-group"><label>Ext.</label><input type="text" name="ext_name" id="hire_ext_name" class="form-control" placeholder="Jr."></div></div>
            </div>
            <div class="row">
              <div class="col-md-4"><div class="form-group"><label>Gender <span class="text-danger">*</span></label>
                <select name="gender" id="hire_gender" class="form-control" required><option value="">-- Select --</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div></div>
              <div class="col-md-4"><div class="form-group"><label>Birthday <span class="text-danger">*</span></label><input type="date" name="bday" id="hire_bday" class="form-control" max="<?= date('Y-m-d') ?>" required></div></div>
              <div class="col-md-4"><div class="form-group"><label>Employee ID <span class="text-danger">*</span></label>
                <input type="text" name="id_number" id="hire_id_number" class="form-control" placeholder="######" required></div></div>
            </div>
            <div class="row">
              <div class="col-md-6"><div class="form-group"><label>Email <span class="text-danger">*</span></label><input type="email" name="email" id="hire_email" class="form-control" required></div></div>
              <div class="col-md-6"><div class="form-group"><label>Phone Number <span class="text-danger">*</span></label><input type="text" name="phone_number" id="hire_phone" class="form-control" required></div></div>
            </div>
            <div class="form-group"><label>Address <span class="text-danger">*</span></label><textarea name="address" id="hire_address" class="form-control" rows="2" required></textarea></div>

            <div class="row">
              <div class="col-md-6"><div class="form-group"><label>Position <span class="text-danger">*</span></label>
                <select name="position_id" id="hire_position_id" class="form-control" required><option value="">-- Select Position --</option><?php foreach ($positions as $o): ?><option value="<?= (int)$o['position_id'] ?>"><?= htmlspecialchars($o['position_name']) ?></option><?php endforeach; ?></select>
                <small class="form-text text-muted" id="hire_applied_hint"></small></div></div>
              <div class="col-md-6"><div class="form-group"><label>Office <span class="text-danger">*</span></label>
                <select name="office_id" class="form-control" required><option value="">-- Select Office --</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option><?php endforeach; ?></select></div></div>
            </div>
            <div class="row">
              <div class="col-md-4"><div class="form-group"><label>Section <span class="text-danger">*</span></label>
                <select name="section_id" class="form-control" required><option value="">-- Select Section --</option><?php foreach ($sections as $o): ?><option value="<?= (int)$o['section_id'] ?>"><?= htmlspecialchars($o['section_name']) ?></option><?php endforeach; ?></select></div></div>
              <div class="col-md-4"><div class="form-group"><label>Employment Status <span class="text-danger">*</span></label>
                <select name="employment_status_id" class="form-control" required><option value="">-- Select --</option><?php foreach ($employmentStatuses as $o): ?><option value="<?= (int)$o['status_id'] ?>"><?= htmlspecialchars($o['status_name']) ?></option><?php endforeach; ?></select></div></div>
              <div class="col-md-4"><div class="form-group"><label>Appointment Status <span class="text-danger">*</span></label>
                <select name="appointment_status_id" class="form-control" required><option value="">-- Select --</option><?php foreach ($appointmentStatuses as $o): ?><option value="<?= (int)$o['appointment_id'] ?>"><?= htmlspecialchars($o['status_name']) ?></option><?php endforeach; ?></select></div></div>
            </div>
            <div class="form-group">
              <label>Profile Picture <small class="font-weight-normal" style="text-transform:none;">(optional)</small></label>
              <div class="custom-file">
                <input type="file" name="picture" class="custom-file-input" accept=".jpg,.jpeg,.png,.gif,.webp">
                <label class="custom-file-label">Choose file</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success" id="hireSubmitBtn"><i class="fas fa-user-check mr-1"></i>Confirm Hire</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Applicant Modal -->
  <div class="modal fade scrum-style-modal" id="viewApplicantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-id-badge"></i>Applicant Details</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
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
      placeholder: "-- Optional: Select Position --",
      allowClear: true,
      maximumSelectionLength: 1
    });
    
    // Initialize DataTable
    var applicantsTable = $('#applicantsTable').DataTable({
      responsive: true,
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
      columnDefs: [{ targets: 0, orderable: false, searchable: false }],
      order: [[4, 'desc']]
    });

    // Row count (1, 2, 3...) instead of the database ID; renumbers on sort/search/filter
    applicantsTable.on('draw.dt order.dt search.dt', function() {
      applicantsTable.column(0, { search: 'applied', order: 'applied' }).nodes().each(function(cell, i) {
        cell.innerHTML = i + 1;
      });
    }).draw();

    // Year filter
    var selectedYear = '';
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
      if (settings.nTable.id !== 'applicantsTable' || !selectedYear) return true;
      var row = applicantsTable.row(dataIndex).node();
      return $(row).find('td[data-year]').attr('data-year') === selectedYear;
    });
    $('.year-filter-item').on('click', function(e) {
      e.preventDefault();
      selectedYear = String($(this).data('year') || '');
      $('.year-filter-item').removeClass('active');
      $(this).addClass('active');
      $('#yearFilterLabel').text(selectedYear || 'All Years');
      applicantsTable.draw();
    });

    // Custom file input label
    $('.custom-file-input').on('change', function() {
      let files = this.files;
      let label;
      if (files.length > 1) {
        label = files.length + ' files selected';
      } else if (files.length === 1) {
        label = files[0].name;
      } else {
        label = $(this).is('[multiple]') ? 'Choose files' : 'Choose file';
      }
      $(this).next('.custom-file-label').html(label);
    });

    // ---- Other Documents: keep a removable list of the selected files ----
    function otherDocsRender($input) {
      let dt = $input.data('dt');
      let $list = $input.closest('.form-group').find('.other-docs-selected');
      $list.empty();
      Array.from(dt.files).forEach(function(file, i) {
        let $chip = $('<div class="doc-chip"></div>');
        $chip.append($('<span class="doc-chip-name"></span>').append($('<span class="doc-chip-tag">New</span>')).append(document.createTextNode(file.name)));
        $chip.append($('<button type="button" class="doc-chip-remove" title="Remove" aria-label="Remove"><i class="fas fa-times"></i></button>').on('click', function() {
          let next = new DataTransfer();
          Array.from(dt.files).forEach(function(f, j) { if (j !== i) next.items.add(f); });
          $input.data('dt', next);
          $input[0].files = next.files;
          otherDocsRender($input);
        }));
        $list.append($chip);
      });
      let n = dt.files.length;
      $input.next('.custom-file-label').text(n > 1 ? n + ' files selected' : (n === 1 ? dt.files[0].name : 'Choose files'));
    }
    function otherDocsReset($scope) {
      $scope.find('.other-docs-input').each(function() {
        let empty = new DataTransfer();
        $(this).data('dt', empty);
        this.files = empty.files;
        otherDocsRender($(this));
      });
    }
    $('.other-docs-input').each(function() { $(this).data('dt', new DataTransfer()); });
    $('.other-docs-input').on('change', function() {
      let $input = $(this);
      let dt = $input.data('dt') || new DataTransfer();
      let merged = new DataTransfer();
      Array.from(dt.files).forEach(function(f) { merged.items.add(f); });
      Array.from(this.files).forEach(function(f) {
        let dup = Array.from(merged.files).some(function(x) { return x.name === f.name && x.size === f.size; });
        if (!dup) merged.items.add(f);
      });
      $input.data('dt', merged);
      this.files = merged.files;
      otherDocsRender($input);
    });

    // Saved documents in the Edit modal: "x" marks one for removal on Update
    function renderExistingOther(listStr) {
      let $list = $('#current_other_list').empty();
      $('#editApplicantForm input[name="remove_other[]"]').remove();
      (listStr || '').split(',').map(function(f) { return f.trim(); }).filter(Boolean).forEach(function(name) {
        let $chip = $('<div class="doc-chip"></div>');
        $chip.append($('<span class="doc-chip-name"></span>').append($('<span class="doc-chip-tag">Saved</span>')).append(document.createTextNode(name)));
        $chip.append($('<button type="button" class="doc-chip-remove" title="Remove this document" aria-label="Remove"><i class="fas fa-times"></i></button>').on('click', function() {
          $('<input type="hidden" name="remove_other[]">').val(name).appendTo('#editApplicantForm');
          $chip.remove();
        }));
        $list.append($chip);
      });
    }

    $('#addApplicantModal, #editApplicantModal').on('show.bs.modal', function() {
      otherDocsReset($(this));
    });

    // Reset Add/Edit modals to the "Applicant Info" tab each time they open
    $('#addApplicantModal, #editApplicantModal').on('show.bs.modal', function() {
      $(this).find('.applicant-detail-tabs .nav-link').removeClass('active');
      $(this).find('.applicant-detail-tabs .nav-link:first').addClass('active');
      $(this).find('.tab-pane').removeClass('show active');
      $(this).find('.tab-pane:first').addClass('show active');
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

    // Hire Button Click: open the form pre-filled from the applicant
    $('.hire-btn').on('click', function() {
      let applicantId = $(this).data('id');
      $.ajax({
        url: 'applicant.php',
        type: 'POST',
        data: { action: 'get', applicant_id: applicantId },
        dataType: 'json',
        success: function(response) {
          if (!response.success) { Swal.fire('Error!', response.message, 'error'); return; }
          let d = response.data;
          let $f = $('#hireApplicantForm');
          $f[0].reset();
          $f.find('.custom-file-label').text('Choose file');
          $('#hire_applicant_id').val(d.applicant_id);
          $('#hire_first_name').val(d.first_name);
          $('#hire_middle_name').val(d.middle_name);
          $('#hire_last_name').val(d.last_name);
          $('#hire_gender').val(d.gender || '');
          $('#hire_email').val(d.email);
          $('#hire_phone').val(d.phone_number);
          $('#hire_address').val(d.address);
          // Try to match the position they applied for
          let applied = (d.position_applied || '').trim().toLowerCase();
          $('#hire_applied_hint').text(applied ? 'Applied for: ' + d.position_applied : 'Applied to the office (no specific position).');
          if (applied) {
            $('#hire_position_id option').each(function() {
              if ($(this).text().trim().toLowerCase() === applied) { $('#hire_position_id').val($(this).val()); }
            });
          }
          $('#hireApplicantModal').modal('show');
        },
        error: function() { Swal.fire('Error!', 'An error occurred while processing your request.', 'error'); }
      });
    });

    $('#hireApplicantForm input[type=file]').on('change', function() {
      $(this).next('.custom-file-label').text(this.files.length ? this.files[0].name : 'Choose file');
    });

    // Confirm Hire
    $('#hireApplicantForm').on('submit', function(e) {
      e.preventDefault();
      let form = this;
      let name = ($('#hire_first_name').val() + ' ' + $('#hire_last_name').val()).trim();
      Swal.fire({
        title: 'Hire ' + name + '?',
        text: 'They will be added to the employee list and removed from the Applicant Databank.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, hire',
        confirmButtonColor: '#28a745'
      }).then((result) => {
        if (!result.isConfirmed) return;
        let formData = new FormData(form);
        formData.append('action', 'hire');
        $('#hireSubmitBtn').prop('disabled', true);
        $.ajax({
          url: 'applicant.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              Swal.fire('Hired!', response.message, 'success').then(() => { location.reload(); });
            } else {
              $('#hireSubmitBtn').prop('disabled', false);
              Swal.fire('Error!', response.message, 'error');
            }
          },
          error: function() {
            $('#hireSubmitBtn').prop('disabled', false);
            Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
          }
        });
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
            $('#edit_position_applied').val(data.position_applied ? data.position_applied : null).trigger('change');
            $('#edit_gender').val(data.gender);
            $('#edit_application_date').val(data.application_date);
            $('#edit_status').val(data.status);
            $('#edit_remarks').val(data.remarks);
            
            $('#current_resume').text(data.resume_file ? 'Current: ' + data.resume_file : 'No file uploaded');
            $('#current_cover').text(data.cover_letter_file ? 'Current: ' + data.cover_letter_file : 'No file uploaded');
            $('#current_pds').text(data.pds_file ? 'Current: ' + data.pds_file : 'No file uploaded');
            renderExistingOther(data.other_documents);
            
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

            function docIcon(filename) {
              let ext = (filename || '').split('.').pop().toLowerCase();
              if (ext === 'pdf') return { icon: 'fa-file-pdf', color: '#c92a2a' };
              if (ext === 'xls' || ext === 'xlsx') return { icon: 'fa-file-excel', color: '#1d6f42' };
              if (ext === 'doc' || ext === 'docx') return { icon: 'fa-file-word', color: '#2a5c98' };
              return { icon: 'fa-file', color: 'var(--h-muted)' };
            }

            function docItem(label, filenames) {
              let list = (filenames || '').split(',').map(f => f.trim()).filter(Boolean);
              if (!list.length) {
                return `
                  <div class="doc-nav-item doc-nav-item-empty">
                    <span class="doc-nav-icon"><i class="fas fa-file"></i></span>
                    <span class="doc-nav-info">
                      <span class="doc-nav-label">${label}</span>
                      <span class="doc-nav-file">Not uploaded</span>
                    </span>
                  </div>`;
              }
              return list.map((filename, i) => {
                let meta = docIcon(filename);
                let itemLabel = list.length > 1 ? `${label} ${i + 1}` : label;
                return `
                  <a class="doc-nav-item" href="../uploads/applicants/${encodeURIComponent(filename)}" target="_blank">
                    <span class="doc-nav-icon" style="color:${meta.color}"><i class="fas ${meta.icon}"></i></span>
                    <span class="doc-nav-info">
                      <span class="doc-nav-label">${itemLabel}</span>
                      <span class="doc-nav-file">${$('<div>').text(filename).html()}</span>
                    </span>
                    <span class="doc-nav-open"><i class="fas fa-external-link-alt"></i></span>
                  </a>`;
              }).join('');
            }

            function esc(v) {
              return $('<div>').text(v == null ? '' : String(v)).html();
            }
            function field(icon, label, value, full) {
              let has = value != null && String(value).trim() !== '';
              return `
                <div class="ad-field${full ? ' ad-full' : ''}">
                  <div class="ad-label"><i class="fas ${icon}"></i>${label}</div>
                  <div class="ad-value${has ? '' : ' ad-empty'}">${has ? esc(value) : 'Not provided'}</div>
                </div>`;
            }
            let fullName = esc([data.first_name, data.middle_name, data.last_name].filter(function(x) { return x && x.trim(); }).join(' '));
            let initials = esc(((data.first_name || '').charAt(0) + (data.last_name || '').charAt(0)).toUpperCase()) || '?';
            let statusColors = { 'Pending': 'warning', 'For Review': 'info', 'For Interview': 'primary', 'Accepted': 'success', 'Rejected': 'danger' };
            let statusColor = statusColors[data.status] || 'secondary';
            let appDate = '';
            if (data.application_date) {
              let p = String(data.application_date).split('-');
              let d = p.length === 3 ? new Date(p[0], p[1] - 1, p[2]) : new Date(data.application_date);
              appDate = isNaN(d) ? data.application_date : d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            }

            let content = `
              <ul class="nav nav-tabs applicant-detail-tabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" data-toggle="tab" href="#tabApplicantDetails" role="tab">
                    <i class="fas fa-user mr-1"></i>Details
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" data-toggle="tab" href="#tabApplicantDocuments" role="tab">
                    <i class="fas fa-paperclip mr-1"></i>Documents
                  </a>
                </li>
              </ul>
              <div class="tab-content applicant-detail-tab-content">
                <div class="tab-pane fade show active" id="tabApplicantDetails" role="tabpanel">
                  <div class="ad-profile">
                    <div class="ad-avatar">${initials}</div>
                    <div class="ad-profile-info">
                      <p class="ad-name">${fullName}</p>
                      <p class="ad-position"><i class="fas fa-briefcase mr-1"></i>${esc(data.position_applied) || 'Any available position'}</p>
                    </div>
                    <span class="badge badge-${statusColor}">${esc(data.status)}</span>
                  </div>
                  <div class="ad-grid">
                    ${field('fa-venus-mars', 'Gender', data.gender)}
                    ${field('fa-calendar-alt', 'Application Date', appDate)}
                    ${field('fa-envelope', 'Email', data.email)}
                    ${field('fa-phone', 'Phone', data.phone_number)}
                    ${field('fa-map-marker-alt', 'Address', data.address, true)}
                    ${field('fa-comment-alt', 'Remarks', data.remarks && data.remarks.trim() !== 'N/A' ? data.remarks : '', true)}
                  </div>
                </div>
                <div class="tab-pane fade" id="tabApplicantDocuments" role="tabpanel">
                  <div class="doc-nav-list">
                    ${docItem('Resume', data.resume_file)}
                    ${docItem('Cover Letter', data.cover_letter_file)}
                    ${docItem('PDS', data.pds_file)}
                    ${docItem('Other Documents', data.other_documents)}
                  </div>
                </div>
              </div>
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