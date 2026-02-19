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
  <style>
    .select2-container, .select2-dropdown {
        z-index: 1055 !important;
    }
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
    .profile-upload-container {
      position: relative;
      width: 150px;
      height: 150px;
      margin: 0 auto 20px;
    }
    
    .profile-preview {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #e9ecef;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      background: #f8f9fa;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    
    .profile-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-preview i {
      font-size: 60px;
      color: #adb5bd;
    }
    
    .profile-upload-btn {
      position: absolute;
      bottom: 5px;
      right: 5px;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #007bff;
      color: white;
      border: 3px solid white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      transition: all 0.3s;
    }
    
    .profile-upload-btn:hover {
      background: #0056b3;
      transform: scale(1.1);
    }
    
    .profile-upload-btn input {
      display: none;
    }
    
    .file-upload-modern {
      border: 2px dashed #dee2e6;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s;
      background: #f8f9fa;
      cursor: pointer;
      position: relative;
    }
    
    .file-upload-modern:hover {
      border-color: #007bff;
      background: #e7f3ff;
    }
    
    .file-upload-modern.dragover {
      border-color: #28a745;
      background: #d4edda;
    }
    
    .file-upload-modern input[type="file"] {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      opacity: 0;
      cursor: pointer;
    }
    
    .file-upload-icon {
      font-size: 48px;
      color: #6c757d;
      margin-bottom: 10px;
    }
    
    .file-upload-text {
      color: #495057;
      font-size: 14px;
    }
    
    .file-upload-text strong {
      color: #007bff;
    }
    
    .uploaded-file-preview {
      display: none;
      margin-top: 10px;
      padding: 10px;
      background: #e7f3ff;
      border-radius: 6px;
      border-left: 4px solid #007bff;
    }
    
    .uploaded-file-preview.active {
      display: block;
    }
    
    .uploaded-file-preview i {
      color: #28a745;
      margin-right: 8px;
    }
    
    .file-name {
      font-weight: 500;
      color: #212529;
    }
    
    .remove-file-btn {
      color: #dc3545;
      cursor: pointer;
      margin-left: 10px;
      transition: all 0.2s;
    }
    
    .remove-file-btn:hover {
      color: #bd2130;
      transform: scale(1.1);
    }
    
    .attachments-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .attachment-card {
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 20px;
      background: white;
      transition: all 0.3s;
    }
    
    .attachment-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transform: translateY(-2px);
    }
    
    .attachment-icon {
      font-size: 40px;
      margin-bottom: 10px;
      color: #007bff;
    }
    
    .attachment-label {
      font-weight: 600;
      color: #495057;
      font-size: 14px;
      margin-bottom: 8px;
    }
    
    /* View Modal Styles */
    .intern-profile-header {
      background: #667eea;
      padding: 8px;
      border-radius: 10px 10px 0 0;
      color: white;
      text-align: center;
      /* margin: -1rem -1rem 2rem -1rem; */
    }
    
    .intern-profile-pic {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 4px solid white;
      object-fit: cover;
      margin-bottom: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .intern-profile-name {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .intern-profile-status {
      display: inline-block;
      padding: 5px 15px;
      border-radius: 20px;
      background: rgba(255,255,255,0.2);
      font-size: 14px;
    }
    
    .info-card {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      border-left: 4px solid #007bff;
    }
    
    .info-card h6 {
      color: #007bff;
      font-weight: 600;
      margin-bottom: 15px;
      font-size: 16px;
    }
    
    .info-row {
      display: flex;
      padding: 8px 0;
      border-bottom: 1px solid #e9ecef;
    }
    
    .info-row:last-child {
      border-bottom: none;
    }
    
    .info-label {
      font-weight: 600;
      color: #6c757d;
      width: 140px;
      flex-shrink: 0;
    }
    
    .info-value {
      color: #212529;
      flex: 1;
    }
    
    .document-item {
      display: flex;
      align-items: center;
      padding: 12px;
      background: white;
      border-radius: 6px;
      margin-bottom: 10px;
      border: 1px solid #e9ecef;
      transition: all 0.2s;
    }
    
    .document-item:hover {
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transform: translateX(5px);
    }
    
    .document-icon {
      width: 40px;
      height: 40px;
      background: #e7f3ff;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 12px;
    }
    
    .document-icon i {
      color: #007bff;
      font-size: 18px;
    }
    
    .document-info {
      flex: 1;
    }
    
    .document-name {
      font-weight: 500;
      color: #212529;
      font-size: 14px;
      margin-bottom: 2px;
    }
    
    .document-status {
      font-size: 12px;
      color: #6c757d;
    }
    
    .document-actions a {
      color: #007bff;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
    }
    
    .document-actions a:hover {
      color: #0056b3;
      text-decoration: underline;
    }
    
    .badge-status {
      padding: 6px 12px;
      font-size: 13px;
      font-weight: 500;
    }
    
    .modal-header {
      background: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
    }
    
    .form-section {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .form-section-title {
      font-weight: 600;
      color: #495057;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #dee2e6;
      font-size: 16px;
    }
    /* Add to existing CSS */
    .nav-tabs {
      border-bottom: 2px solid #dee2e6;
    }

    .nav-tabs .nav-link {
      border: none;
      border-bottom: 3px solid transparent;
      color: #6c757d;
      font-weight: 500;
      padding: 12px 20px;
      margin-bottom: -2px;
      transition: all 0.3s;
    }

    .nav-tabs .nav-link:hover {
      border-color: #0088ff;
      color: #574a49;
    }

    .nav-tabs .nav-link.active {
      color: #007bff;
      background-color: transparent;
      border-color: #007bff;
      font-weight: 600;
    }

    .tab-content {
      background: white;
      padding: 0;
      border-radius: 0 0 8px 8px;
    }

    .tab-pane {
      animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    .delete-attachment-btn {
      font-size: 12px;
      padding: 3px 8px;
      transition: all 0.2s;
    }
    
    .delete-attachment-btn:hover {
      transform: scale(1.05);
    }
    /* Add to existing CSS */
.file-name {
    font-weight: 500;
    color: #212529;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px; /* Adjust as needed */
}

/* For the edit modal current file display */
#current_resume,
#current_recommendation,
#current_endorsement,
#current_other {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}

/* For the view modal document names */
.document-name {
    font-weight: 500;
    color: #212529;
    font-size: 14px;
    margin-bottom: 2px;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px;
}

/* Make the document item flexible to accommodate long names */
.document-item {
    display: flex;
    align-items: center;
    padding: 12px;
    background: white;
    border-radius: 6px;
    margin-bottom: 10px;
    border: 1px solid #e9ecef;
    transition: all 0.2s;
    gap: 12px;
}

.document-info {
    flex: 1;
    min-width: 0; /* This is important for text truncation to work */
}

/* For the uploaded file preview in modals */
.uploaded-file-preview {
    display: none;
    margin-top: 10px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 6px;
    border-left: 4px solid #007bff;
    align-items: center;
    gap: 8px;
}

.uploaded-file-preview.active {
    display: flex;
}

.uploaded-file-preview .file-name {
    flex: 1;
    min-width: 0; /* Important for truncation */
}

/* For current file text in edit modal */
.attachment-card .d-flex {
    min-height: 30px;
}

.attachment-card small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
  </style>
</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
  
    <div class="container-fluid mt-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h4 class="mb-0"><i class="fas fa-users-cog mr-2"></i>Intern Management</h4>
          <button class="btn btn-primary" data-toggle="modal" data-target="#addInternModal">
            <i class="fas fa-plus mr-2"></i>Add New Intern
          </button>
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
                    <button class="btn btn-sm btn-info view-btn" data-id="<?= $row['intern_id'] ?>">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $row['intern_id'] ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $row['intern_id'] ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Add Intern Modal -->
    <div class="modal fade" id="addInternModal" tabindex="-1">
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
    <div class="modal fade" id="editInternModal" tabindex="-1">
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
    <div class="modal fade" id="viewInternModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-body p-0">
            <div id="viewInternContent"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-2"></i>Close
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
        
        // Status badge colors
        const statusColors = {
          'Active': 'success',
          'Completed': 'primary',
          'Terminated': 'danger',
          'On Hold': 'warning'
        };
        const statusClass = statusColors[data.status] || 'secondary';
        
        // Profile picture
        const profilePic = data.profile_picture 
          ? `<img src="../uploads/interns/${data.profile_picture}" class="intern-profile-pic" alt="Profile">`
          : `<div class="intern-profile-pic" style="background: white; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-user" style="font-size: 50px; color: #6c757d;"></i>
            </div>`;
        
        let content = `
          <div class="intern-profile-header">
            ${profilePic}
            <div class="intern-profile-name">${data.first_name} ${data.middle_name || ''} ${data.last_name}</div>
            <div class="intern-profile-status">
              <span class="badge badge-${statusClass}">${data.status}</span>
            </div>
          </div>
          
          <div class="px-4 pb-4">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="internTab" role="tablist">
              <li class="nav-item ">
                <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal" role="tab">
                  <font color="#007bff"><i class="fas fa-user mr-1 "></i> Personal</font>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="academic-tab" data-toggle="tab" href="#academic" role="tab">
                  <font color="#007bff"><i class="fas fa-graduation-cap mr-1"></i> Academic</font>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="internship-tab" data-toggle="tab" href="#internship" role="tab">
                  <font color="#007bff"><i class="fas fa-briefcase mr-1"></i> Internship</font>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab">
                  <font color="#007bff"><i class="fas fa-paperclip mr-1"></i> Documents</font>
                </a>
              </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content mt-4" id="internTabContent">
              
              <!-- Personal Information Tab -->
              <div class="tab-pane fade show active" id="personal" role="tabpanel">
                <div class="info-card">
                  <h6><i class="fas fa-user mr-2"></i>Personal Information</h6>
                  <div class="info-row">
                    <div class="info-label">Full Name</div>
                    <div class="info-value">${data.first_name} ${data.middle_name || ''} ${data.last_name}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Gender</div>
                    <div class="info-value">${data.gender || 'N/A'}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">${data.email}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value">${data.phone_number}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Address</div>
                    <div class="info-value">${data.address}</div>
                  </div>
                </div>
              </div>
              
              <!-- Academic Information Tab -->
              <div class="tab-pane fade" id="academic" role="tabpanel">
                <div class="info-card">
                  <h6><i class="fas fa-graduation-cap mr-2"></i>Academic Information</h6>
                  <div class="info-row">
                    <div class="info-label">School</div>
                    <div class="info-value">${data.school}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Course</div>
                    <div class="info-value">${data.course}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Year Level</div>
                    <div class="info-value">${data.year_level}</div>
                  </div>
                </div>
              </div>
              
              <!-- Internship Details Tab -->
              <div class="tab-pane fade" id="internship" role="tabpanel">
                <div class="info-card">
                  <h6><i class="fas fa-briefcase mr-2"></i>Internship Details</h6>
                  <div class="info-row">
                    <div class="info-label">Department</div>
                    <div class="info-value">${data.department_assigned || 'N/A'}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Supervisor</div>
                    <div class="info-value">${data.supervisor_name || 'N/A'}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Duration</div>
                    <div class="info-value">${new Date(data.start_date).toLocaleDateString()} - ${data.end_date ? new Date(data.end_date).toLocaleDateString() : 'Ongoing'}</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Required Hours</div>
                    <div class="info-value">${data.number_of_hours} hours</div>
                  </div>
                  <div class="info-row">
                    <div class="info-label">Performance</div>
                    <div class="info-value">${data.performance_rating ? data.performance_rating + '/100' : 'Not rated'}</div>
                  </div>
                  ${data.remarks ? `
                  <div class="info-row">
                    <div class="info-label">Remarks</div>
                    <div class="info-value">${data.remarks}</div>
                  </div>` : ''}
                </div>
              </div>
              
              <!-- Documents Tab -->
              <div class="tab-pane fade" id="documents" role="tabpanel">
                <div class="info-card">
                  <h6><i class="fas fa-paperclip mr-2"></i>Uploaded Documents</h6>
                  
                  <div class="document-item">
                    <div class="document-icon">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="document-info">
                      <div class="document-name">Resume / CV</div>
                      <div class="document-status">${data.resume_file ? 'Uploaded' : 'Not uploaded'}</div>
                    </div>
                    <div class="document-actions">
                      ${data.resume_file ? `<a href="../uploads/interns/${data.resume_file}" target="_blank"><i class="fas fa-download mr-1"></i>Download</a>` : '<span class="text-muted">-</span>'}
                    </div>
                  </div>
                  
                  <div class="document-item">
                    <div class="document-icon">
                      <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="document-info">
                      <div class="document-name">Recommendation Letter</div>
                      <div class="document-status">${data.recommendation_letter ? 'Uploaded' : 'Not uploaded'}</div>
                    </div>
                    <div class="document-actions">
                      ${data.recommendation_letter ? `<a href="../uploads/interns/${data.recommendation_letter}" target="_blank"><i class="fas fa-download mr-1"></i>Download</a>` : '<span class="text-muted">-</span>'}
                    </div>
                  </div>
                  
                  <div class="document-item">
                    <div class="document-icon">
                      <i class="fas fa-stamp"></i>
                    </div>
                    <div class="document-info">
                      <div class="document-name">School Endorsement</div>
                      <div class="document-status">${data.school_endorsement ? 'Uploaded' : 'Not uploaded'}</div>
                    </div>
                    <div class="document-actions">
                      ${data.school_endorsement ? `<a href="../uploads/interns/${data.school_endorsement}" target="_blank"><i class="fas fa-download mr-1"></i>Download</a>` : '<span class="text-muted">-</span>'}
                    </div>
                  </div>
                  
                  <div class="document-item">
                    <div class="document-icon">
                      <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="document-info">
                      <div class="document-name">Other Documents</div>
                      <div class="document-status">${data.other_documents ? 'Uploaded' : 'Not uploaded'}</div>
                    </div>
                    <div class="document-actions">
                      ${data.other_documents ? `<a href="../uploads/interns/${data.other_documents}" target="_blank"><i class="fas fa-download mr-1"></i>Download</a>` : '<span class="text-muted">-</span>'}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
        $('#viewInternContent').html(content);
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