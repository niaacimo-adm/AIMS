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
      $gender = $_POST['gender'];
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
      $profile_picture = null;
      
      // Handle profile picture upload
      if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_picture']['type'], $allowed_types)) {
          $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
          $profile_picture = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
          move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $profile_picture);
        }
      }
      
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
      
    // Update the SQL statement to include profile_picture
    $stmt = $db->prepare("INSERT INTO intern (first_name, middle_name, last_name, gender, email, phone_number, address, school, course, year_level, department_assigned, supervisor_name, start_date, end_date, status, performance_rating, number_of_hours, remarks, resume_file, recommendation_letter, school_endorsement, other_documents, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
$stmt->bind_param("ssssssssssssssssiisssss", $first_name, $middle_name, $last_name, $gender, $email, $phone_number, $address, $school, $course, $year_level, $department_assigned, $supervisor_name, $start_date, $end_date, $status, $performance_rating, $number_of_hours, $remarks, $resume_file, $recommendation_letter, $school_endorsement, $other_documents, $profile_picture);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Intern added successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add intern: ' . $stmt->error]);
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
      $gender = $_POST['gender'];
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
      $stmt = $db->prepare("SELECT resume_file, recommendation_letter, school_endorsement, other_documents, profile_picture FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      
      $resume_file = $existing['resume_file'];
      $recommendation_letter = $existing['recommendation_letter'];
      $school_endorsement = $existing['school_endorsement'];
      $other_documents = $existing['other_documents'];
      $profile_picture = $existing['profile_picture'];
      
      // Check if files should be deleted
      $delete_flags = [
        'resume_file' => $_POST['delete_resume_file'] ?? '0',
        'recommendation_letter' => $_POST['delete_recommendation_letter'] ?? '0',
        'school_endorsement' => $_POST['delete_school_endorsement'] ?? '0',
        'other_documents' => $_POST['delete_other_documents'] ?? '0'
      ];
      
      foreach ($delete_flags as $field => $delete_flag) {
        if ($delete_flag === '1' && $$field) {
          // Delete the file
          if (file_exists($upload_dir . $$field)) {
            unlink($upload_dir . $$field);
          }
          // Clear the field value
          $$field = null;
        }
      }
      
      // Handle profile picture upload
      if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_picture']['type'], $allowed_types)) {
          if ($profile_picture && file_exists($upload_dir . $profile_picture)) {
            unlink($upload_dir . $profile_picture);
          }
          $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
          $profile_picture = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
          move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $profile_picture);
        }
      }
      
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
      
      $stmt = $db->prepare("UPDATE intern SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, email = ?, phone_number = ?, address = ?, school = ?, course = ?, year_level = ?, department_assigned = ?, supervisor_name = ?, start_date = ?, end_date = ?, status = ?, performance_rating = ?, number_of_hours = ?, remarks = ?, resume_file = ?, recommendation_letter = ?, school_endorsement = ?, other_documents = ?, profile_picture = ? WHERE intern_id = ?");
      
      $stmt->bind_param("sssssssssssssssiissssssi", $first_name, $middle_name, $last_name, $gender, $email, $phone_number, $address, $school, $course, $year_level, $department_assigned, $supervisor_name, $start_date, $end_date, $status, $performance_rating, $number_of_hours, $remarks, $resume_file, $recommendation_letter, $school_endorsement, $other_documents, $profile_picture, $intern_id);
      
      if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Intern updated successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update intern: ' . $stmt->error]);
      }
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
  }
  
  if ($_POST['action'] === 'delete') {
    try {
      $intern_id = $_POST['intern_id'];
      
      // Get file names before deletion
      $stmt = $db->prepare("SELECT resume_file, recommendation_letter, school_endorsement, other_documents, profile_picture FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      $stmt->execute();
      $files = $stmt->get_result()->fetch_assoc();
      
      // Delete the record
      $stmt = $db->prepare("DELETE FROM intern WHERE intern_id = ?");
      $stmt->bind_param("i", $intern_id);
      
      if ($stmt->execute()) {
        // Delete files
        foreach ($files as $file) {
          if ($file && file_exists($upload_dir . $file)) {
            unlink($upload_dir . $file);
          }
        }
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
      
      if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
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
$query = "SELECT * FROM intern ORDER BY created_at DESC";
$result = $db->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Intern Databank</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/intern.css">
  <style>
    /* ── View modal ── */
    #viewInternModal .modal-header { padding: 10px 20px !important; }
    #viewInternModal .modal-footer { padding: 10px 20px !important; gap: 8px; }
    #viewInternModal .modal-footer .btn { margin: 0; }

    /* Hero */
    #viewInternModal .vi-hero {
      background: linear-gradient(160deg,#0f2d1e 0%,#1c4d38 100%);
      padding: 16px 20px 14px; color: #fff; text-align: center;
    }
    #viewInternModal .vi-avatar {
      width: 76px !important; height: 76px !important; max-width: 76px; border-radius: 50%; object-fit: cover; background: #fff;
      border: 3px solid var(--h-accent);
      box-shadow: 0 0 0 4px rgba(36,231,143,.18), 0 4px 12px rgba(0,0,0,.3);
    }
    #viewInternModal .vi-avatar-empty { display: inline-flex; align-items: center; justify-content: center; background: #16352a; }
    #viewInternModal .vi-avatar-empty i { font-size: 30px; color: #6aad8a; }
    #viewInternModal .vi-name { margin: 8px 0 4px; font-size: 1.15rem; font-weight: 800; color: #fff; line-height: 1.2; letter-spacing: -.2px; }
    #viewInternModal .vi-status {
      display: inline-flex; align-items: center; gap: 6px; padding: 2px 12px; border-radius: 999px;
      font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    }
    #viewInternModal .vi-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    #viewInternModal .vi-status.success   { background: #d9f7e8; color: #146c43; }
    #viewInternModal .vi-status.primary   { background: #dbeafe; color: #1d4ed8; }
    #viewInternModal .vi-status.danger    { background: #fde2e2; color: #b42318; }
    #viewInternModal .vi-status.warning   { background: #fff1cc; color: #92600a; }
    #viewInternModal .vi-status.secondary { background: #e5e7eb; color: #374151; }

    #viewInternModal .vi-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
    #viewInternModal .vi-stat { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); border-radius: 8px; padding: 5px 8px; min-width: 0; }
    #viewInternModal .vi-stat small { display: block; font-size: .6rem; text-transform: uppercase; letter-spacing: .6px; color: rgba(255,255,255,.7); line-height: 1.3; }
    #viewInternModal .vi-stat strong { display: block; font-size: .8rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Tabs (same approach as before: !important colours beat the global .nav-tabs rules) */
    #viewInternModal .intern-detail-tabs {
      display: flex !important; flex-wrap: nowrap; justify-content: flex-start; gap: 2px;
      margin: 0 !important; padding: 0 14px !important;
      border-bottom: 1px solid var(--h-border) !important;
      background: var(--h-card);
    }
    #viewInternModal .intern-detail-tabs .nav-item { flex: 0 0 auto; width: auto !important; margin: 0; }
    #viewInternModal .intern-detail-tabs .nav-link {
      display: inline-flex !important; align-items: center; gap: 6px; width: auto !important;
      border: none !important; border-bottom: 2px solid transparent !important; border-radius: 0 !important;
      background: transparent !important; box-shadow: none !important; outline: none !important;
      color: var(--h-muted) !important; -webkit-text-fill-color: var(--h-muted);
      font-size: .82rem !important; font-weight: 700 !important; line-height: 1.2;
      padding: 10px 14px !important; margin: 0 0 -1px; white-space: nowrap;
      transition: color .15s, border-color .15s;
    }
    #viewInternModal .intern-detail-tabs .nav-link i { color: var(--h-primary) !important; -webkit-text-fill-color: var(--h-primary); margin: 0 !important; }
    #viewInternModal .intern-detail-tabs .nav-link:hover { color: var(--h-text) !important; -webkit-text-fill-color: var(--h-text); }
    #viewInternModal .intern-detail-tabs .nav-link.active {
      color: var(--h-primary) !important; -webkit-text-fill-color: var(--h-primary);
      border-bottom-color: var(--h-primary) !important;
    }
    .scrum-style-modal .tab-content, .scrum-style-modal .tab-pane { background: transparent !important; color: var(--h-text) !important; }

    /* Panels */
    #viewInternModal .vi-panels { padding: 14px 16px; }
    #viewInternModal .vi-card { background: var(--h-card-alt); border: 1px solid var(--h-border); border-radius: 10px; padding: 0 16px; }
    #viewInternModal .vi-card-title {
      margin: 0; padding: 10px 0 8px; display: flex; align-items: center; gap: 8px;
      font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
      color: var(--h-muted); border-bottom: 1px solid var(--h-border);
    }
    #viewInternModal .vi-card-title i { color: var(--h-primary); }
    #viewInternModal .vi-row { display: grid; grid-template-columns: 130px 1fr; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--h-border); align-items: start; }
    #viewInternModal .vi-row:last-child { border-bottom: 0; }
    #viewInternModal .vi-label { font-size: .8rem; font-weight: 700; color: var(--h-muted); }
    #viewInternModal .vi-value { font-size: .85rem; color: var(--h-text); word-break: break-word; }
    #viewInternModal .vi-value a { color: var(--h-primary); }
    #viewInternModal .vi-value.empty { color: var(--h-muted); font-style: italic; opacity: .7; }
    #viewInternModal .vi-bar { height: 6px; border-radius: 99px; background: var(--h-border); overflow: hidden; margin-top: 4px; max-width: 200px; }
    #viewInternModal .vi-bar > span { display: block; height: 100%; background: var(--h-primary); }

    /* Documents */
    #viewInternModal .vi-doc { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--h-border); }
    #viewInternModal .vi-doc:last-child { border-bottom: 0; }
    #viewInternModal .vi-doc-icon {
      width: 34px; height: 34px; flex: 0 0 34px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      background: var(--h-primary-dim); color: var(--h-primary); font-size: .85rem;
    }
    #viewInternModal .vi-doc.missing .vi-doc-icon { background: var(--h-surface3); color: var(--h-muted); }
    #viewInternModal .vi-doc-info { flex: 1; min-width: 0; }
    #viewInternModal .vi-doc-name { font-size: .84rem; font-weight: 600; color: var(--h-text); line-height: 1.2; }
    #viewInternModal .vi-doc-status { font-size: .72rem; color: var(--h-muted); }
    #viewInternModal .vi-doc:not(.missing) .vi-doc-status { color: var(--h-primary); font-weight: 600; }
    #viewInternModal .vi-doc-btn {
      font-size: .75rem; font-weight: 600; padding: 4px 10px; border-radius: 8px; white-space: nowrap;
      border: 1px solid var(--h-primary); color: var(--h-primary) !important; background: transparent; text-decoration: none;
    }
    #viewInternModal .vi-doc-btn:hover { background: var(--h-primary); color: #fff !important; text-decoration: none; }

    @media (max-width: 576px) {
      #viewInternModal .vi-row { grid-template-columns: 1fr; gap: 2px; }
      #viewInternModal .vi-stats { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
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
                  <div class="pg-hero-title"><i class="fas fa-user-graduate"></i>Intern Databank</div>
                  <div class="pg-hero-divider"></div>
                  <p class="pg-hero-sub">Browse and manage OJT interns and their documents</p>
              </div>
          </div>
      </div>
      <section class="content">
        <div class="container-fluid mt-4">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title">Manage Interns</h3>
              <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addInternModal">
                  <i class="fas fa-plus mr-1"></i> Add Intern
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="internTable" class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>School</th>
                    <th>Department</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?= $row['intern_id'] ?></td>
                      <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td><?= htmlspecialchars($row['school']) ?></td>
                      <td><?= htmlspecialchars($row['department_assigned'] ?? 'N/A') ?></td>
                      <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                      <td>
                        <?php
                          $status_class = [
                            'Active' => 'success',
                            'Completed' => 'primary',
                            'Terminated' => 'danger',
                            'On Hold' => 'warning'
                          ];
                          $class = $status_class[$row['status']] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $class ?>"><?= $row['status'] ?></span>
                      </td>
                      <td>
                        <button class="btn btn-sm btn-info view-btn" data-id="<?= $row['intern_id'] ?>" title="View Details">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $row['intern_id'] ?>" title="Edit">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $row['intern_id'] ?>" title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                        <a href="generate_certificate.php?intern_id=<?= $row['intern_id'] ?>" class="btn btn-sm btn-success" title="Generate Certificate" target="_blank">
                          <i class="fas fa-certificate"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    <!-- Add Intern Modal -->
    <div class="modal fade scrum-style-modal" id="addInternModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Add New Intern</h5>
            <button type="button" class="close" data-dismiss="modal">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form id="addInternForm" enctype="multipart/form-data">
              <!-- Profile Picture Section -->
              <div class="text-center mb-4">
                <div class="profile-upload-container">
                  <div class="profile-preview" id="profilePreview">
                    <i class="fas fa-user"></i>
                  </div>
                  <label class="profile-upload-btn" for="profilePictureInput">
                    <i class="fas fa-camera"></i>
                    <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*">
                  </label>
                </div>
                <small class="text-muted">Click camera icon to upload profile picture</small>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-user mr-2"></i>Personal Information
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>First Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="first_name" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Middle Name</label>
                      <input type="text" class="form-control" name="middle_name">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Last Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="last_name" required>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Gender</label>
                      <select class="form-control" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Email <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" name="email" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Phone Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="phone_number" required>
                    </div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Address <span class="text-danger">*</span></label>
                  <textarea class="form-control" name="address" rows="2" required></textarea>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-graduation-cap mr-2"></i>Academic Information
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>School <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="school" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Course <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="course" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Year Level <span class="text-danger">*</span></label>
                      <select name="year_level" class="form-control" required>
                        <option value="">Select Year Level</option>
                        <option value="Senior High">Senior High</option>
                        <option value="4th Year">4th Year</option>
                        <option value="Graduate">Graduate</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-briefcase mr-2"></i>Internship Details
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Department Assigned</label>
                      <select class="form-control" name="department_assigned">
                        <option value="">Select Department</option>
                        <optgroup label="Sections">
                          <?php foreach ($sections as $section): ?>
                            <option value="<?= htmlspecialchars($section['section_name']) ?>">
                              <?= htmlspecialchars($section['section_name']) ?> (<?= htmlspecialchars($section['section_code']) ?>)
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Units">
                          <?php foreach ($unit_sections as $unit): ?>
                            <option value="<?= htmlspecialchars($unit['unit_name']) ?>">
                              <?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_code']) ?>)
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Supervisor Name</label>
                      <input type="text" class="form-control" name="supervisor_name">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Number of Hours <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" name="number_of_hours" value="500" required>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Start Date <span class="text-danger">*</span></label>
                      <input type="date" class="form-control" name="start_date" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>End Date</label>
                      <input type="date" class="form-control" name="end_date">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Status <span class="text-danger">*</span></label>
                      <select class="form-control" name="status" required>
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
                      <label>Performance Rating (1-100)</label>
                      <input type="number" class="form-control" name="performance_rating" min="1" max="100">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Remarks</label>
                      <textarea class="form-control" name="remarks" rows="2"></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-paperclip mr-2"></i>Document Attachments
                </div>
                
                <div class="attachments-grid">
                  <!-- Resume -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="attachment-label">Resume / CV</div>
                    <div class="file-upload-modern" data-target="resume_file">
                      <input type="file" name="resume_file" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- Recommendation Letter -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="attachment-label">Recommendation Letter</div>
                    <div class="file-upload-modern" data-target="recommendation_letter">
                      <input type="file" name="recommendation_letter" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- School Endorsement -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-stamp"></i>
                    </div>
                    <div class="attachment-label">School Endorsement</div>
                    <div class="file-upload-modern" data-target="school_endorsement">
                      <input type="file" name="school_endorsement" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- Other Documents -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="attachment-label">Other Documents</div>
                    <div class="file-upload-modern" data-target="other_documents">
                      <input type="file" name="other_documents" accept=".pdf,.doc,.docx,.zip">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX, ZIP (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="submit" form="addInternForm" class="btn btn-primary">
              <i class="fas fa-save mr-2"></i>Save Intern
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Intern Modal -->
    <div class="modal fade scrum-style-modal" id="editInternModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Edit Intern</h5>
            <button type="button" class="close" data-dismiss="modal">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form id="editInternForm" enctype="multipart/form-data">
              <input type="hidden" name="intern_id" id="edit_intern_id">
              
              <!-- Profile Picture Section -->
              <div class="text-center mb-4">
                <div class="profile-upload-container">
                  <div class="profile-preview" id="editProfilePreview">
                    <i class="fas fa-user"></i>
                  </div>
                  <label class="profile-upload-btn" for="editProfilePictureInput">
                    <i class="fas fa-camera"></i>
                    <input type="file" id="editProfilePictureInput" name="profile_picture" accept="image/*">
                  </label>
                </div>
                <small class="text-muted" id="currentProfileText">Click camera icon to upload profile picture</small>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-user mr-2"></i>Personal Information
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>First Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Middle Name</label>
                      <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Last Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Gender</label>
                      <select class="form-control" name="gender" id="edit_gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Email <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Phone Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="phone_number" id="edit_phone_number" required>
                    </div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Address <span class="text-danger">*</span></label>
                  <textarea class="form-control" name="address" id="edit_address" rows="2" required></textarea>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-graduation-cap mr-2"></i>Academic Information
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>School <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="school" id="edit_school" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Course <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="course" id="edit_course" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Year Level <span class="text-danger">*</span></label>
                      <select name="year_level" id="edit_year_level" class="form-control" required>
                        <option value="">Select Year Level</option>
                        <option value="Senior High">Senior High</option>
                        <option value="4th Year">4th Year</option>
                        <option value="Graduate">Graduate</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-briefcase mr-2"></i>Internship Details
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Department Assigned</label>
                      <select class="form-control" name="department_assigned" id="edit_department_assigned">
                        <option value="">Select Department</option>
                        <optgroup label="Sections">
                          <?php foreach ($sections as $section): ?>
                            <option value="<?= htmlspecialchars($section['section_name']) ?>">
                              <?= htmlspecialchars($section['section_name']) ?> (<?= htmlspecialchars($section['section_code']) ?>)
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Units">
                          <?php foreach ($unit_sections as $unit): ?>
                            <option value="<?= htmlspecialchars($unit['unit_name']) ?>">
                              <?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_code']) ?>)
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Supervisor Name</label>
                      <input type="text" class="form-control" name="supervisor_name" id="edit_supervisor_name">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Number of Hours <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" name="number_of_hours" id="edit_number_of_hours" required>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Start Date <span class="text-danger">*</span></label>
                      <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>End Date</label>
                      <input type="date" class="form-control" name="end_date" id="edit_end_date">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Status <span class="text-danger">*</span></label>
                      <select class="form-control" name="status" id="edit_status" required>
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
                      <label>Performance Rating (1-100)</label>
                      <input type="number" class="form-control" name="performance_rating" id="edit_performance_rating" min="1" max="100">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Remarks</label>
                      <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-section-title">
                  <i class="fas fa-paperclip mr-2"></i>Document Attachments
                </div>
                
                <div class="attachments-grid">
                  <!-- Resume -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="attachment-label">Resume / CV</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small id="current_resume" class="text-muted"></small>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-attachment-btn" data-field="resume_file" style="display: none;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    <input type="hidden" name="delete_resume_file" id="delete_resume_file" value="0">
                    <div class="file-upload-modern" data-target="edit_resume_file">
                      <input type="file" name="resume_file" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- Recommendation Letter -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="attachment-label">Recommendation Letter</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small id="current_recommendation" class="text-muted"></small>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-attachment-btn" data-field="recommendation_letter" style="display: none;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    <input type="hidden" name="delete_recommendation_letter" id="delete_recommendation_letter" value="0">
                    <div class="file-upload-modern" data-target="edit_recommendation_letter">
                      <input type="file" name="recommendation_letter" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- School Endorsement -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-stamp"></i>
                    </div>
                    <div class="attachment-label">School Endorsement</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small id="current_endorsement" class="text-muted"></small>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-attachment-btn" data-field="school_endorsement" style="display: none;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    <input type="hidden" name="delete_school_endorsement" id="delete_school_endorsement" value="0">
                    <div class="file-upload-modern" data-target="edit_school_endorsement">
                      <input type="file" name="school_endorsement" accept=".pdf,.doc,.docx">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>

                  <!-- Other Documents -->
                  <div class="attachment-card">
                    <div class="attachment-icon text-center">
                      <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="attachment-label">Other Documents</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small id="current_other" class="text-muted"></small>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-attachment-btn" data-field="other_documents" style="display: none;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                    <input type="hidden" name="delete_other_documents" id="delete_other_documents" value="0">
                    <div class="file-upload-modern" data-target="edit_other_documents">
                      <input type="file" name="other_documents" accept=".pdf,.doc,.docx,.zip">
                      <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                      </div>
                      <div class="file-upload-text">
                        <strong>Click to upload</strong> or drag and drop<br>
                        <small>PDF, DOC, DOCX, ZIP (Max 5MB)</small>
                      </div>
                    </div>
                    <div class="uploaded-file-preview">
                      <i class="fas fa-check-circle"></i>
                      <span class="file-name"></span>
                      <i class="fas fa-times remove-file-btn"></i>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="submit" form="editInternForm" class="btn btn-primary">
              <i class="fas fa-save mr-2"></i>Update Intern
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Intern Modal -->
    <div class="modal fade scrum-style-modal" id="viewInternModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-id-badge"></i>Intern Details</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body p-0">
            <div id="viewInternContent"></div>
          </div>
          <div class="modal-footer">
            <a href="#" id="viewModalCertBtn" class="btn btn-success" target="_blank">
              <i class="fas fa-certificate mr-1"></i>Generate Certificate
            </a>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i>Close
            </button>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
  <?php include '../includes/footer.php'; ?>
  <script>
    $(document).ready(function() {
      // Initialize DataTable with AJAX
      var internTable = $('#internTable').DataTable({
          ajax: {
              url: 'get_interns.php',
              dataSrc: 'data'
          },
          columns: [
              { data: 'intern_id' },
              { data: 'name' },
              { data: 'email' },
              { data: 'school' },
              { data: 'department_assigned' },
              { data: 'start_date' },
              { 
                  data: 'status',
                  render: function(data, type, row) {
                      const status_class = {
                          'Active': 'success',
                          'Completed': 'primary',
                          'Terminated': 'danger',
                          'On Hold': 'warning'
                      };
                      const class_name = status_class[data] || 'secondary';
                      return `<span class="badge badge-${class_name}">${data}</span>`;
                  }
              },
              {
                  data: 'actions',
                  render: function(data, type, row) {
                      return `
                          <button class="btn btn-sm btn-info view-btn" data-id="${data}">
                              <i class="fas fa-eye"></i>
                          </button>
                          <button class="btn btn-sm btn-warning edit-btn" data-id="${data}">
                              <i class="fas fa-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-danger delete-btn" data-id="${data}">
                              <i class="fas fa-trash"></i>
                          </button>
                      `;
                  }
              }
          ],
          order: [[0, 'desc']],
          pageLength: 25
      });

      // Initialize Select2
      $('.select2').select2({
        theme: 'bootstrap',
        width: '100%'
      });

      // Profile Picture Preview - Add Form
      $('#profilePictureInput').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            $('#profilePreview').html(`<img src="${e.target.result}" alt="Profile">`);
          }
          reader.readAsDataURL(file);
        }
      });

      // Profile Picture Preview - Edit Form
      $('#editProfilePictureInput').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            $('#editProfilePreview').html(`<img src="${e.target.result}" alt="Profile">`);
          }
          reader.readAsDataURL(file);
        }
      });

      // File Upload Handlers
      $('.file-upload-modern input[type="file"]').on('change', function() {
        const fileName = this.files[0] ? this.files[0].name : '';
        const card = $(this).closest('.attachment-card');
        const preview = card.find('.uploaded-file-preview');
        
        if (fileName) {
          preview.find('.file-name').text(fileName);
          preview.addClass('active');
        } else {
          preview.removeClass('active');
        }
      });

      // Remove File
      $('.remove-file-btn').on('click', function() {
        const card = $(this).closest('.attachment-card');
        const input = card.find('input[type="file"]');
        const preview = card.find('.uploaded-file-preview');
        
        input.val('');
        preview.removeClass('active');
      });

      // Drag and Drop
      $('.file-upload-modern').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
      });

      $('.file-upload-modern').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
      });

      $('.file-upload-modern').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const input = $(this).find('input[type="file"]')[0];
        const files = e.originalEvent.dataTransfer.files;
        
        if (files.length > 0) {
          input.files = files;
          $(input).trigger('change');
        }
      });

// Add Intern Form Submit - SIMPLIFIED VERSION
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
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        Swal.fire({
          title: 'Success!',
          text: response.message,
          icon: 'success',
          confirmButtonText: 'OK'
        }).then((result) => {
          if (result.isConfirmed) {
            // Close modal and reload page
            $('#addInternModal').modal('hide');
            setTimeout(function() {
              location.reload();
            }, 300);
          }
        });
      } else {
        Swal.fire('Error!', response.message, 'error');
      }
    },
    error: function() {
      Swal.fire('Error!', 'Failed to add intern. Please try again.', 'error');
    }
  });
});

      // Edit Button Click - using event delegation
      $(document).on('click', '.edit-btn', function() {
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
              $('#edit_gender').val(data.gender);
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
              
              // Profile Picture
              if (data.profile_picture) {
                $('#editProfilePreview').html(`<img src="../uploads/interns/${data.profile_picture}" alt="Profile">`);
                $('#currentProfileText').text('Current profile picture uploaded');
              } else {
                $('#editProfilePreview').html('<i class="fas fa-user"></i>');
                $('#currentProfileText').text('No profile picture uploaded');
              }
              
              // Show/hide delete buttons based on existing files
              if (data.resume_file) {
                $('#current_resume').text('Current: ' + data.resume_file);
                $('#editInternForm').find('.delete-attachment-btn[data-field="resume_file"]').show();
              } else {
                $('#current_resume').text('No file uploaded');
                $('#editInternForm').find('.delete-attachment-btn[data-field="resume_file"]').hide();
              }
              
              if (data.recommendation_letter) {
                $('#current_recommendation').text('Current: ' + data.recommendation_letter);
                $('#editInternForm').find('.delete-attachment-btn[data-field="recommendation_letter"]').show();
              } else {
                $('#current_recommendation').text('No file uploaded');
                $('#editInternForm').find('.delete-attachment-btn[data-field="recommendation_letter"]').hide();
              }
              
              if (data.school_endorsement) {
                $('#current_endorsement').text('Current: ' + data.school_endorsement);
                $('#editInternForm').find('.delete-attachment-btn[data-field="school_endorsement"]').show();
              } else {
                $('#current_endorsement').text('No file uploaded');
                $('#editInternForm').find('.delete-attachment-btn[data-field="school_endorsement"]').hide();
              }
              
              if (data.other_documents) {
                $('#current_other').text('Current: ' + data.other_documents);
                $('#editInternForm').find('.delete-attachment-btn[data-field="other_documents"]').show();
              } else {
                $('#current_other').text('No file uploaded');
                $('#editInternForm').find('.delete-attachment-btn[data-field="other_documents"]').hide();
              }
              
              // Reset delete flags
              $('#delete_resume_file').val('0');
              $('#delete_recommendation_letter').val('0');
              $('#delete_school_endorsement').val('0');
              $('#delete_other_documents').val('0');
              
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
                $('#editInternModal').modal('hide');
                setTimeout(function() {
                  location.reload();
                }, 300);
              });
            } else {
              Swal.fire('Error!', response.message, 'error');
            }
          }
        });
      });

      // Delete existing attachment
      $(document).on('click', '.delete-attachment-btn', function() {
        const field = $(this).data('field');
        const btn = $(this);
        
        Swal.fire({
          title: 'Delete Attachment?',
          text: "Are you sure you want to delete this attachment? This action cannot be undone.",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {
            // Set the delete flag
            $('#delete_' + field).val('1');
            
            // Clear the current file display
            $('#current_' + field.replace('_file', '')).text('File will be deleted on save');
            
            // Hide the delete button
            btn.hide();
            
            // Clear any uploaded file preview
            const card = btn.closest('.attachment-card');
            card.find('input[type="file"]').val('');
            card.find('.uploaded-file-preview').removeClass('active');
            
            // Show success message
            Swal.fire('Deleted!', 'Attachment will be deleted when you save changes.', 'success');
          }
        });
      });

      // Reset delete flags when new file is selected
      $('#editInternForm input[type="file"]').on('change', function() {
        const fieldName = $(this).attr('name');
        const deleteField = 'delete_' + fieldName;
        
        // If user selects a new file, reset the delete flag
        if (this.files.length > 0) {
          $('#' + deleteField).val('0');
          // Show the delete button again if it was hidden
          const field = fieldName.replace('_file', '');
          $('#editInternForm').find('.delete-attachment-btn[data-field="' + fieldName + '"]').show();
        }
      });

// View Button Click - using event delegation
$(document).on('click', '.view-btn', function() {
  let internId = $(this).data('id');
  
  $.ajax({
    url: 'intern.php',
    type: 'POST',
    data: { action: 'get', intern_id: internId },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        let data = response.data;
        
        // ---- helpers ----
        const esc = (v) => $('<div>').text(v == null ? '' : v).html();
        const val = (v) => (v === null || v === undefined || String(v).trim() === '')
          ? '<span class="vi-value empty">Not provided</span>' : esc(v);
        const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
        const row = (label, html) => `
          <div class="vi-row">
            <div class="vi-label">${label}</div>
            <div class="vi-value">${html}</div>
          </div>`;
        const doc = (icon, name, file) => `
          <div class="vi-doc ${file ? '' : 'missing'}">
            <div class="vi-doc-icon"><i class="fas ${icon}"></i></div>
            <div class="vi-doc-info">
              <div class="vi-doc-name">${name}</div>
              <div class="vi-doc-status">${file ? 'Uploaded' : 'Not uploaded'}</div>
            </div>
            ${file ? `<a class="vi-doc-btn" href="../uploads/interns/${encodeURI(file)}" target="_blank"><i class="fas fa-download mr-1"></i>Download</a>` : ''}
          </div>`;

        const statusColors = { 'Active': 'success', 'Completed': 'primary', 'Terminated': 'danger', 'On Hold': 'warning' };
        const statusClass = statusColors[data.status] || 'secondary';

        const fullName = [data.first_name, data.middle_name, data.last_name]
          .filter(p => p && String(p).trim()).join(' ');

        const profilePic = data.profile_picture
          ? `<img src="../uploads/interns/${encodeURI(data.profile_picture)}" class="vi-avatar" alt="Profile">`
          : `<div class="vi-avatar vi-avatar-empty"><i class="fas fa-user"></i></div>`;

        const rating = parseFloat(data.performance_rating);
        const performanceHtml = isNaN(rating)
          ? '<span class="vi-value empty">Not rated</span>'
          : `${rating}/100<div class="vi-bar"><span style="width:${Math.max(0, Math.min(100, rating))}%"></span></div>`;

        const duration = data.start_date
          ? `${fmtDate(data.start_date)} &ndash; ${data.end_date ? fmtDate(data.end_date) : 'Ongoing'}`
          : '';

        let content = `
          <div class="vi-hero">
            ${profilePic}
            <div class="vi-name">${esc(fullName)}</div>
            <span class="vi-status ${statusClass}">${esc(data.status)}</span>
            <div class="vi-stats">
              <div class="vi-stat"><small>Department</small><strong>${esc(data.department_assigned) || '&mdash;'}</strong></div>
              <div class="vi-stat"><small>Start Date</small><strong>${fmtDate(data.start_date) || '&mdash;'}</strong></div>
              <div class="vi-stat"><small>Required Hours</small><strong>${data.number_of_hours ? esc(data.number_of_hours) + ' hrs' : '&mdash;'}</strong></div>
            </div>
          </div>

          <ul class="nav nav-tabs intern-detail-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#vi-personal" role="tab"><i class="fas fa-user mr-1"></i> Personal</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#vi-academic" role="tab"><i class="fas fa-graduation-cap mr-1"></i> Academic</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#vi-internship" role="tab"><i class="fas fa-briefcase mr-1"></i> Internship</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#vi-documents" role="tab"><i class="fas fa-paperclip mr-1"></i> Documents</a></li>
          </ul>

          <div class="tab-content vi-panels">
            <div class="tab-pane fade show active" id="vi-personal" role="tabpanel">
              <div class="vi-card">
                <h6 class="vi-card-title"><i class="fas fa-user"></i>Personal Information</h6>
                ${row('Full Name', val(fullName))}
                ${row('Gender', val(data.gender))}
                ${row('Email', data.email ? `<a href="mailto:${esc(data.email)}">${esc(data.email)}</a>` : val(''))}
                ${row('Phone', val(data.phone_number))}
                ${row('Address', val(data.address))}
              </div>
            </div>

            <div class="tab-pane fade" id="vi-academic" role="tabpanel">
              <div class="vi-card">
                <h6 class="vi-card-title"><i class="fas fa-graduation-cap"></i>Academic Information</h6>
                ${row('School', val(data.school))}
                ${row('Course', val(data.course))}
                ${row('Year Level', val(data.year_level))}
              </div>
            </div>

            <div class="tab-pane fade" id="vi-internship" role="tabpanel">
              <div class="vi-card">
                <h6 class="vi-card-title"><i class="fas fa-briefcase"></i>Internship Details</h6>
                ${row('Department', val(data.department_assigned))}
                ${row('Supervisor', val(data.supervisor_name))}
                ${row('Duration', duration || val(''))}
                ${row('Required Hours', data.number_of_hours ? esc(data.number_of_hours) + ' hours' : val(''))}
                ${row('Performance', performanceHtml)}
                ${data.remarks ? row('Remarks', esc(data.remarks)) : ''}
              </div>
            </div>

            <div class="tab-pane fade" id="vi-documents" role="tabpanel">
              <div class="vi-card">
                <h6 class="vi-card-title"><i class="fas fa-paperclip"></i>Uploaded Documents</h6>
                ${doc('fa-file-alt', 'Resume / CV', data.resume_file)}
                ${doc('fa-envelope-open-text', 'Recommendation Letter', data.recommendation_letter)}
                ${doc('fa-stamp', 'School Endorsement', data.school_endorsement)}
                ${doc('fa-folder-open', 'Other Documents', data.other_documents)}
              </div>
            </div>
          </div>
        `;
        $('#viewInternContent').html(content);
        // Set the certificate button link
        $('#viewModalCertBtn').attr('href', 'generate_certificate.php?intern_id=' + data.intern_id);
        $('#viewInternModal').modal('show');
      }
    }
  });
});

      // Delete Button Click - using event delegation
      $(document).on('click', '.delete-btn', function() {
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

      // Department change handler for supervisor
      $(document).on('change', 'select[name="department_assigned"], #edit_department_assigned', function() {
        var selectedDepartment = $(this).val();
        var targetInput = $(this).closest('form').find('input[name="supervisor_name"], #edit_supervisor_name');
        
        if (!selectedDepartment) {
          targetInput.val('');
          return;
        }
        
        $.ajax({
          url: 'get_supervisor.php',
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
    });
  </script>
</body>
</html>