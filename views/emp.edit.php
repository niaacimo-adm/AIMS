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

// Fetch employee data with all joined details
$query = "SELECT 
            e.*,
            es.status_name as employment_status,
            o.office_name,
            s.section_name,
            s.section_id as current_section_id,
            p.position_name,
            ap.status_name as appointment_status,
            GROUP_CONCAT(us.unit_id) as unit_section_ids,
            GROUP_CONCAT(us.unit_name) as unit_section_names
          FROM employee e
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          LEFT JOIN employee_unit_sections eus ON e.emp_id = eus.emp_id
          LEFT JOIN unit_section us ON eus.unit_id = us.unit_id
          WHERE e.emp_id = ?
          GROUP BY e.emp_id";

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

// Fetch all lookup data
$employmentStatuses = [];
$appointmentStatuses = [];
$positions = [];
$sections = [];
$offices = [];
$unit_sections = [];

$stmt = $db->prepare("SELECT * FROM employment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employmentStatuses[] = $row;
}

$stmt = $db->prepare("SELECT * FROM appointment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointmentStatuses[] = $row;
}

$stmt = $db->prepare("SELECT * FROM position");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}

$stmt = $db->prepare("SELECT s.section_id, s.section_name, 
                      CONCAT(e.first_name, ' ', e.last_name) as head_name 
                      FROM section s
                      LEFT JOIN employee e ON s.head_emp_id = e.emp_id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$stmt = $db->prepare("SELECT * FROM office");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

$stmt = $db->prepare("SELECT us.*, s.section_name 
                      FROM unit_section us
                      LEFT JOIN section s ON us.section_id = s.section_id
                      ORDER BY us.unit_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $unit_sections[] = $row;
}

// Current unit section IDs for this employee
$current_unit_ids = !empty($employee['unit_section_ids']) ? explode(',', $employee['unit_section_ids']) : [];

// Statuses that disable assignment fields
$disableStatuses = ['Inactive', 'Separated - Death', 'Non-renewal', 'Resigned', 'Retired', 'AWOL'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | Edit Employee</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .modern-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
    }

    .modern-card:hover {
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
      transform: translateY(-2px);
    }

    .form-control-modern {
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 12px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: #ffffff;
    }

    .form-control-modern:focus {
      border-color: #4f46e5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
      background: #ffffff;
    }

    .form-label {
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .btn-modern {
      background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%);
      border: none;
      border-radius: 8px;
      padding: 12px 30px;
      font-weight: 600;
      color: white;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
    }

    .btn-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
      background: linear-gradient(135deg, #4338ca 0%, #6d63e0 100%);
      color: white;
    }

    .section-title {
      color: #4f46e5;
      font-weight: 700;
      font-size: 18px;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #eef2ff;
      position: relative;
    }

    .section-title:after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 50px;
      height: 2px;
      background: #4f46e5;
      border-radius: 2px;
    }

    .image-upload-area {
      border: 2px dashed #d1d5db;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      transition: all 0.3s ease;
      background: #fafafa;
      cursor: pointer;
    }

    .image-upload-area:hover {
      border-color: #4f46e5;
      background: #f0f4ff;
    }

    .image-preview-container {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .image-preview-container:hover {
      transform: scale(1.02);
    }

    .required-field::after {
      content: " *";
      color: #ef4444;
    }

    .modern-select {
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 0px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: #ffffff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") right 12px center no-repeat;
      background-size: 16px;
      appearance: none;
    }

    .modern-select:focus {
      border-color: #4f46e5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .modern-select:disabled {
      background-color: #e9ecef;
      opacity: 1;
      cursor: not-allowed;
    }

    .validation-error {
      color: #ef4444;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .image-upload-area.error {
      border-color: #ef4444 !important;
      background: #fef2f2 !important;
    }

    /* Tab styling */
    .nav-tabs-modern {
      border-bottom: 2px solid #eef2ff;
      margin-bottom: 25px;
    }

    .nav-tabs-modern .nav-link {
      border: none;
      border-bottom: 3px solid transparent;
      color: #6b7280;
      font-weight: 600;
      padding: 12px 24px;
      border-radius: 0;
      transition: all 0.2s ease;
      margin-bottom: -2px;
    }

    .nav-tabs-modern .nav-link:hover {
      color: #4f46e5;
      background: none;
      border-bottom-color: #c7d2fe;
    }

    .nav-tabs-modern .nav-link.active {
      color: #4f46e5;
      background: none;
      border-bottom: 3px solid #4f46e5;
    }

    .nav-tabs-modern .nav-link i {
      margin-right: 8px;
    }

    /* Dark mode overrides */
    body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
    body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
    body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
    body.dark-mode .text-muted { color: var(--text-muted) !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
    body.dark-mode .modern-card { background: var(--card-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .form-control-modern { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control-modern:focus { background: var(--input-bg) !important; }
    body.dark-mode .modern-select { background-color: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .image-upload-area { background: var(--table-stripe) !important; border-color: var(--input-border) !important; }
    body.dark-mode .image-upload-area:hover { background: var(--notification-unread-bg) !important; }
    body.dark-mode .section-title { color: #7aabdf !important; border-color: var(--card-border) !important; }
    body.dark-mode .section-title:after { background: #7aabdf !important; }
    body.dark-mode .form-label { color: var(--text-primary) !important; }
    body.dark-mode .nav-tabs-modern { border-color: var(--card-border) !important; }
    body.dark-mode .nav-tabs-modern .nav-link { color: var(--text-muted) !important; }
    body.dark-mode .nav-tabs-modern .nav-link.active { color: #7aabdf !important; border-bottom-color: #7aabdf !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
    body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
    body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
    body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>

  <div class="content-wrapper">
    <!-- Page Header -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 style="color: #4f46e5; font-weight: 700;">Edit Employee</h1>
            <p class="text-muted">
              Editing: <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
            </p>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#" style="color: #6b7280;">HR</a></li>
              <li class="breadcrumb-item"><a href="emp.list.php" style="color: #6b7280;">Employees</a></li>
              <li class="breadcrumb-item active" style="color: #4f46e5; font-weight: 600;">Edit</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card modern-card">
              <!-- Card Header -->
              <div class="card-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%); border-radius: 12px 12px 0 0;">
                <h3 class="card-title" style="font-weight: 600;">
                  <i class="fas fa-user-edit mr-2"></i>Employee Information
                </h3>
              </div>

              <div class="card-body" style="padding: 30px;">

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs-modern" id="editTabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal" role="tab">
                      <i class="fas fa-user"></i> Personal Information
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="assignment-tab" data-toggle="tab" href="#assignment" role="tab">
                      <i class="fas fa-briefcase"></i> Assignment Details
                    </a>
                  </li>
                </ul>

                <!-- ============================================================
                     TAB 1: PERSONAL INFORMATION  →  posts to emp.update.php
                     ============================================================ -->
                <div class="tab-content" id="editTabContent">
                  <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <form action="emp.update.php" method="POST" enctype="multipart/form-data" id="employeeForm">
                      <input type="hidden" name="emp_id" value="<?= $emp_id ?>">
                      <input type="hidden" name="old_picture" value="<?= htmlspecialchars($employee['picture'] ?? '') ?>">

                      <!-- Personal Information Section -->
                      <div class="row mb-4">
                        <div class="col-12">
                          <h4 class="section-title">Personal Information</h4>
                        </div>
                      </div>

                      <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-4 mb-4">
                          <label class="form-label required-field">Profile Picture</label>
                          <div class="image-upload-area" id="imageUploadArea" onclick="document.getElementById('picture').click()">
                            <input type="file" class="d-none" id="picture" name="picture" onchange="previewImage(this)" accept="image/*">
                            <input type="hidden" id="use_default_image" name="use_default_image" value="0">
                            <div id="uploadPlaceholder">
                              <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #9ca3af;"></i>
                              <p class="mb-1" style="color: #6b7280; font-weight: 500;">Click to upload photo</p>
                              <p class="small text-muted">PNG, JPG up to 5MB</p>
                            </div>
                            <div id="imagePreview" style="display: none;">
                              <div class="image-preview-container">
                                <img id="preview" src="#" alt="Image Preview" class="img-fluid rounded">
                              </div>
                              <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="removeImage(); event.stopPropagation();">
                                <i class="fas fa-trash mr-1"></i> Remove Photo
                              </button>
                            </div>
                          </div>
                          <div id="pictureError" class="validation-error">
                            <i class="fas fa-exclamation-circle mr-1"></i>Please upload a profile picture or use the default image
                          </div>
                          <button type="button" class="btn mt-2 w-100" id="defaultImageBtn"
                            style="background: linear-gradient(135deg,#10b981 0%,#34d399 100%); border:none; border-radius:8px; padding:10px 20px; font-weight:600; color:white; transition:all .3s ease; box-shadow:0 2px 8px rgba(16,185,129,.3);"
                            onclick="useDefaultImage()">
                            <i class="fas fa-user-circle mr-2"></i>Use Default Image
                          </button>
                          <div id="defaultImagePreview" style="display:none; margin-top:15px;">
                            <div class="image-preview-container">
                              <img src="../dist/img/nialogo.png" alt="Default Profile" class="img-fluid rounded">
                            </div>
                            <p class="text-success text-center mt-2 mb-0" style="font-weight:600;">
                              <i class="fas fa-check-circle mr-1"></i>Using Default Image
                            </p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="removeDefaultImage()">
                              <i class="fas fa-times mr-1"></i> Remove Default
                            </button>
                          </div>
                        </div>

                        <!-- Personal Details -->
                        <div class="col-md-8">
                          <div class="row">
                            <div class="col-12 mb-3">
                              <label for="id_number" class="form-label">Employee ID</label>
                              <input type="text" id="id_number" name="id_number" class="form-control form-control-modern"
                                placeholder="Enter employee ID"
                                value="<?= htmlspecialchars($employee['id_number'] ?? '') ?>">
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-4 mb-3">
                              <label for="first_name" class="form-label required-field">First Name</label>
                              <input type="text" id="first_name" name="first_name" class="form-control form-control-modern"
                                placeholder="First name" required
                                value="<?= htmlspecialchars($employee['first_name']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                              <label for="middle_name" class="form-label required-field">Middle Name</label>
                              <input type="text" id="middle_name" name="middle_name" class="form-control form-control-modern"
                                placeholder="Middle name"
                                value="<?= htmlspecialchars($employee['middle_name']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                              <label for="last_name" class="form-label required-field">Last Name</label>
                              <input type="text" id="last_name" name="last_name" class="form-control form-control-modern"
                                placeholder="Last name" required
                                value="<?= htmlspecialchars($employee['last_name']) ?>">
                            </div>
                            <div class="col-md-1 mb-3">
                              <label for="ext_name" class="form-label">Ext</label>
                              <input type="text" id="ext_name" name="ext_name" class="form-control form-control-modern"
                                placeholder="Jr."
                                value="<?= htmlspecialchars($employee['ext_name'] ?? '') ?>">
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-6 mb-3">
                              <label for="gender" class="form-label required-field">Gender</label>
                              <select id="gender" name="gender" class="form-control modern-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male"   <?= $employee['gender'] === 'Male'   ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $employee['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other"  <?= $employee['gender'] === 'Other'  ? 'selected' : '' ?>>Other</option>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label for="bday" class="form-label required-field">Birthday</label>
                              <input type="date" id="bday" name="bday" class="form-control form-control-modern" required
                                value="<?= htmlspecialchars($employee['bday']) ?>">
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Contact Information Section -->
                      <div class="row mb-4 mt-4">
                        <div class="col-12">
                          <h4 class="section-title">Contact Information</h4>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="email" class="form-label required-field">Email Address</label>
                          <input type="email" id="email" name="email" class="form-control form-control-modern"
                            placeholder="employee@company.com" required
                            value="<?= htmlspecialchars($employee['email']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="phone_number" class="form-label required-field">Phone Number</label>
                          <input type="tel" id="phone_number" name="phone_number" class="form-control form-control-modern"
                            placeholder="+63 912 345 6789" required
                            value="<?= htmlspecialchars($employee['phone_number']) ?>">
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 mb-3">
                          <label for="address" class="form-label required-field">Address</label>
                          <textarea id="address" name="address" class="form-control form-control-modern" rows="3"
                            placeholder="Enter complete address" required><?= htmlspecialchars($employee['address']) ?></textarea>
                        </div>
                      </div>

                      <!-- Manager's Office Toggle -->
                      <div class="row mb-4">
                        <div class="col-12">
                          <h4 class="section-title">Office Settings</h4>
                        </div>
                        <div class="col-12">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_manager" name="is_manager"
                              value="1" <?= ($employee['is_manager'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <label class="custom-control-label form-label" for="is_manager">Assign to Manager's Office</label>
                          </div>
                        </div>
                      </div>

                      <!-- Submit -->
                      <div class="row mt-4">
                        <div class="col-12 text-right">
                          <a href="emp.list.php" class="btn btn-secondary btn-lg mr-2">
                            <i class="fas fa-times mr-1"></i>Cancel
                          </a>
                          <button type="submit" class="btn btn-modern btn-lg">
                            <i class="fas fa-save mr-2"></i>Save Personal Info
                          </button>
                        </div>
                      </div>
                    </form>
                  </div><!-- /#personal -->

                  <!-- ============================================================
                       TAB 2: ASSIGNMENT DETAILS  →  posts to emp.update_assignment.php
                       ============================================================ -->
                  <div class="tab-pane fade" id="assignment" role="tabpanel">
                    <form action="emp.update_assignment.php" method="POST" id="assignmentForm">
                      <input type="hidden" name="emp_id" value="<?= $emp_id ?>">

                      <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                          <i class="fas fa-exclamation-circle mr-1"></i> Error updating assignment. Please try again.
                        </div>
                      <?php endif; ?>

                      <!-- Employment Details Section -->
                      <div class="row mb-4">
                        <div class="col-12">
                          <h4 class="section-title">Employment Details</h4>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-3 mb-3">
                          <label for="employment_status_id" class="form-label required-field">Employment Status</label>
                          <select id="employment_status_id" name="employment_status_id" class="form-control modern-select" required>
                            <?php foreach ($employmentStatuses as $status): ?>
                              <option value="<?= $status['status_id'] ?>"
                                <?= $status['status_id'] == $employee['employment_status_id'] ? 'selected' : '' ?>
                                data-status-name="<?= htmlspecialchars($status['status_name']) ?>">
                                <?= htmlspecialchars($status['status_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="appointment_status_id" class="form-label required-field">Appointment Status</label>
                          <select id="appointment_status_id" name="appointment_status_id" class="form-control modern-select" required>
                            <?php foreach ($appointmentStatuses as $status): ?>
                              <option value="<?= $status['appointment_id'] ?>"
                                <?= $status['appointment_id'] == $employee['appointment_status_id'] ? 'selected' : '' ?>
                                data-color="<?= htmlspecialchars($status['color'] ?? '#ffffff') ?>">
                                <?= htmlspecialchars($status['status_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="position_id" class="form-label required-field">Position</label>
                          <select id="position_id" name="position_id" class="form-control modern-select" required>
                            <option value="" disabled>Select Position</option>
                            <?php foreach ($positions as $position): ?>
                              <option value="<?= $position['position_id'] ?>"
                                <?= $position['position_id'] == $employee['position_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($position['position_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="office_id" class="form-label required-field">Office</label>
                          <select id="office_id" name="office_id" class="form-control modern-select" required>
                            <?php foreach ($offices as $office): ?>
                              <option value="<?= $office['office_id'] ?>"
                                <?= $office['office_id'] == $employee['office_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($office['office_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <!-- Section & Unit Section -->
                      <div class="row mb-4 mt-2">
                        <div class="col-12">
                          <h4 class="section-title">Section Assignment</h4>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="section_id" class="form-label">Section</label>
                          <select id="section_id" name="section_id" class="form-control modern-select">
                            <option value="nosec">-- No Section --</option>
                            <?php foreach ($sections as $section): ?>
                              <option value="<?= $section['section_id'] ?>"
                                <?= $section['section_id'] == $employee['section_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($section['section_name']) ?>
                                <?php if (!empty($section['head_name'])): ?>
                                  (Head: <?= htmlspecialchars($section['head_name']) ?>)
                                <?php endif; ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-6 mb-3">
                          <label for="unit_section_ids" class="form-label">Unit Sections <span class="text-muted" style="font-weight:400;">(Optional)</span></label>
                          <select class="form-control select2" id="unit_section_ids" name="unit_section_ids[]" multiple="multiple">
                            <option value="">-- No Unit Section --</option>
                            <?php foreach ($unit_sections as $unit): ?>
                              <option value="<?= $unit['unit_id'] ?>"
                                <?= in_array($unit['unit_id'], $current_unit_ids) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['unit_name']) ?>
                                (<?= htmlspecialchars($unit['section_name'] ?? '') ?>)
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <!-- Submit -->
                      <div class="row mt-4">
                        <div class="col-12 text-right">
                          <a href="emp.list.php" class="btn btn-secondary btn-lg mr-2">
                            <i class="fas fa-times mr-1"></i>Cancel
                          </a>
                          <button type="submit" class="btn btn-modern btn-lg" id="submitBtn">
                            <i class="fas fa-save mr-2"></i>Update Assignment
                          </button>
                        </div>
                      </div>
                    </form>
                  </div><!-- /#assignment -->

                </div><!-- /.tab-content -->
              </div><!-- /.card-body -->
            </div><!-- /.card -->
          </div>
        </div>
      </div>
    </section>
  </div>
  <!-- /.content-wrapper -->

  <aside class="control-sidebar control-sidebar-dark"></aside>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<!-- ./wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- SweetAlert Toast Notifications -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toast = <?php echo json_encode($_SESSION['toast']); ?>;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: toast.type,
        title: toast.message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (el) => {
            el.addEventListener('mouseenter', Swal.stopTimer);
            el.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script>
/* =====================================================
   IMAGE UPLOAD HELPERS
   ===================================================== */
function previewImage(input) {
    const preview          = document.getElementById('preview');
    const imagePreview     = document.getElementById('imagePreview');
    const uploadPlaceholder= document.getElementById('uploadPlaceholder');
    const defaultImagePrev = document.getElementById('defaultImagePreview');
    const defaultImageBtn  = document.getElementById('defaultImageBtn');
    const useDefaultInput  = document.getElementById('use_default_image');
    const uploadArea       = document.getElementById('imageUploadArea');
    const pictureError     = document.getElementById('pictureError');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            imagePreview.style.display = 'block';
            uploadPlaceholder.style.display = 'none';
            defaultImagePrev.style.display = 'none';
            defaultImageBtn.style.display = 'block';
            useDefaultInput.value = '0';
            uploadArea.classList.remove('default-image-active', 'error');
            pictureError.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('picture').value = '';
    document.getElementById('preview').src = '#';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('defaultImageBtn').style.display = 'block';
}

function useDefaultImage() {
    document.getElementById('picture').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'none';
    document.getElementById('defaultImagePreview').style.display = 'block';
    document.getElementById('defaultImageBtn').style.display = 'none';
    document.getElementById('use_default_image').value = '1';
    document.getElementById('imageUploadArea').classList.add('default-image-active');
    document.getElementById('pictureError').style.display = 'none';
    document.getElementById('imageUploadArea').classList.remove('error');
}

function removeDefaultImage() {
    document.getElementById('picture').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('defaultImagePreview').style.display = 'none';
    document.getElementById('defaultImageBtn').style.display = 'block';
    document.getElementById('use_default_image').value = '0';
    document.getElementById('imageUploadArea').classList.remove('default-image-active');
}

/* Show existing employee image on load */
<?php if (!empty($employee['picture'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    const preview          = document.getElementById('preview');
    const imagePreview     = document.getElementById('imagePreview');
    const uploadPlaceholder= document.getElementById('uploadPlaceholder');
    const defaultImageBtn  = document.getElementById('defaultImageBtn');

    preview.src = '../dist/img/employees/<?= $employee['picture'] ?>';
    imagePreview.style.display = 'block';
    uploadPlaceholder.style.display = 'none';
    defaultImageBtn.style.display = 'block';
});
<?php endif; ?>

/* =====================================================
   PERSONAL FORM VALIDATION
   ===================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('employeeForm');

    form.addEventListener('submit', function (e) {
        let isValid = true;

        form.querySelectorAll('[required]').forEach(function (field) {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                field.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.1)';
            }
        });

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#4f46e5'
            });
        }
    });

    // Real-time field validation colouring
    form.querySelectorAll('input, select, textarea').forEach(function (input) {
        input.addEventListener('input', function () {
            if (this.hasAttribute('required') && this.value.trim()) {
                this.style.borderColor = '#10b981';
                this.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.1)';
            } else if (this.hasAttribute('required')) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
    });
});

/* =====================================================
   ASSIGNMENT TAB: EMPLOYMENT STATUS DISABLE LOGIC
   ===================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const employmentStatusSelect  = document.getElementById('employment_status_id');
    const appointmentStatusSelect = document.getElementById('appointment_status_id');
    const positionSelect          = document.getElementById('position_id');
    const sectionSelect           = document.getElementById('section_id');
    const officeSelect            = document.getElementById('office_id');
    const submitBtn               = document.getElementById('submitBtn');

    const disableStatuses = <?php echo json_encode($disableStatuses); ?>;

    function checkEmploymentStatus() {
        const selectedOption = employmentStatusSelect.options[employmentStatusSelect.selectedIndex];
        const statusName     = selectedOption.getAttribute('data-status-name');
        const shouldDisable  = disableStatuses.includes(statusName);

        appointmentStatusSelect.disabled = shouldDisable;
        positionSelect.disabled          = shouldDisable;
        sectionSelect.disabled           = shouldDisable;
        officeSelect.disabled            = shouldDisable;
        if (submitBtn) submitBtn.disabled = false;

        if (shouldDisable) {
            Swal.fire({
                icon: 'info',
                title: 'Status Change',
                text: 'Since the employee status is "' + statusName + '", other assignment fields have been disabled.',
                toast: true,
                position: 'top',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }

    if (employmentStatusSelect) {
        checkEmploymentStatus();
        employmentStatusSelect.addEventListener('change', checkEmploymentStatus);
    }

    /* Appointment status colour coding */
    function updateStatusColor() {
        if (!appointmentStatusSelect) return;
        const selectedOption = appointmentStatusSelect.options[appointmentStatusSelect.selectedIndex];
        const color = selectedOption.getAttribute('data-color');
        if (!color) return;
        appointmentStatusSelect.style.backgroundColor = color;
        appointmentStatusSelect.style.color = getContrastColor(color);
    }

    function getContrastColor(hexColor) {
        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#000000' : '#ffffff';
    }

    if (appointmentStatusSelect) {
        updateStatusColor();
        appointmentStatusSelect.addEventListener('change', updateStatusColor);
    }

    /* Section → unit section state */
    function updateUnitSectionState() {
        const unitSectionSelect = document.getElementById('unit_section_ids');
        if (unitSectionSelect && sectionSelect) {
            unitSectionSelect.disabled = (sectionSelect.value === 'nosec');
        }
    }

    if (sectionSelect) {
        updateUnitSectionState();
        sectionSelect.addEventListener('change', updateUnitSectionState);
    }

    /* Initialize select2 */
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ placeholder: "Select unit sections...", allowClear: true });
    }
});
</script>

<!-- SweetAlert for session alerts -->
<script>
$(document).ready(function () {
    <?php if (isset($_SESSION['alert'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['title'] ?>',
        text: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 3000,
        confirmButtonColor: '#4f46e5'
    });
    <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>