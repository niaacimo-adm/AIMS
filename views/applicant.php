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

  // Saves every file from a multi-file input (name="field[]") and returns
  // their generated filenames as a comma-separated string (or null if none).
  function handleMultiUpload($fieldName, $prefix, $upload_dir) {
    if (!isset($_FILES[$fieldName])) return null;
    $names = $_FILES[$fieldName]['name'];
    if (!is_array($names)) return null; // no files selected
    $saved = [];
    foreach ($names as $i => $name) {
      if ($_FILES[$fieldName]['error'][$i] === 0 && $name !== '') {
        $file_ext = pathinfo($name, PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES[$fieldName]['tmp_name'][$i], $upload_dir . $filename);
        $saved[] = $filename;
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
      
      if (isset($_FILES['other_documents']) && is_array($_FILES['other_documents']['name'])) {
        $other_documents = handleMultiUpload('other_documents', 'other', $upload_dir);
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
      
      if (isset($_FILES['other_documents']) && is_array($_FILES['other_documents']['name'])) {
        $new_other = handleMultiUpload('other_documents', 'other', $upload_dir);
        if ($new_other !== null) {
          // Remove previously uploaded "other" files before replacing them
          if ($other_documents) {
            foreach (explode(',', $other_documents) as $old_file) {
              if ($old_file && file_exists($upload_dir . $old_file)) {
                unlink($upload_dir . $old_file);
              }
            }
          }
          $other_documents = $new_other;
        }
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

        /* Status badges */
        .badge-secondary { background: var(--h-surface3) !important; color: var(--h-muted) !important; border-radius: 5px !important; }
        .badge-warning   { background: rgba(230,119,0,.15) !important; color: var(--h-warning) !important; border-radius: 5px !important; }
        .badge-info      { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border-radius: 5px !important; }
        .badge-primary   { background: rgba(167,139,250,.15) !important; color: #7c5cd6 !important; border-radius: 5px !important; }
        .badge-success   { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger    { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }

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
        }

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
              </div>
              <div class="tab-pane fade" id="addTabDocuments" role="tabpanel">
                <div class="info-box mb-3">
                  <i class="fas fa-info-circle mr-1"></i>Accepted formats: PDF, DOC, DOCX — Max 5MB each.
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
                  <label>Other Documents <small class="font-weight-normal" style="text-transform:none;">(you can select multiple files)</small></label>
                  <div class="custom-file">
                    <input type="file" name="other_documents[]" class="custom-file-input" accept=".pdf,.doc,.docx" multiple>
                    <label class="custom-file-label">Choose files</label>
                  </div>
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
                  <label>Other Documents <small class="font-weight-normal" style="text-transform:none;">(you can select multiple files; uploading new ones replaces all existing)</small></label>
                  <div class="custom-file">
                    <input type="file" name="other_documents[]" class="custom-file-input" accept=".pdf,.doc,.docx" multiple>
                    <label class="custom-file-label">Choose files</label>
                  </div>
                  <small class="form-text text-muted" id="current_other"></small>
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
            $('#current_other').text(data.other_documents ? 'Current: ' + data.other_documents.split(',').join(', ') : 'No file uploaded');
            
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
                  <a class="doc-nav-item" href="../uploads/applicants/${filename}" target="_blank">
                    <span class="doc-nav-icon" style="color:${meta.color}"><i class="fas ${meta.icon}"></i></span>
                    <span class="doc-nav-info">
                      <span class="doc-nav-label">${itemLabel}</span>
                      <span class="doc-nav-file">${filename}</span>
                    </span>
                    <span class="doc-nav-open"><i class="fas fa-external-link-alt"></i></span>
                  </a>`;
              }).join('');
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
                </div>
                <div class="tab-pane fade" id="tabApplicantDocuments" role="tabpanel">
                  <div class="doc-nav-list">
                    ${docItem('Resume', data.resume_file)}
                    ${docItem('Cover Letter', data.cover_letter_file)}
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