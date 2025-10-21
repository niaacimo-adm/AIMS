<?php 
require_once '../config/database.php';

// Create database instance and get connection
$database = new Database();
$db = $database->getConnection();

// Fetch employment statuses
$employmentStatuses = [];
$stmt = $db->prepare("SELECT * FROM employment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employmentStatuses[] = $row;
}

// Fetch appointment statuses
$appointmentStatuses = [];
$stmt = $db->prepare("SELECT * FROM appointment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointmentStatuses[] = $row;
}

// Fetch positions
$positions = [];
$stmt = $db->prepare("SELECT * FROM position");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}

// Fetch sections
$sections = [];
$stmt = $db->prepare("SELECT * FROM section");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

// Fetch offices
$offices = [];
$stmt = $db->prepare("SELECT * FROM office");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | Create Employee</title>

  <?php include '../includes/header.php'; ?>
  <style>
    .modern-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
    }
    .modern-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
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
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
        padding: 12px 15px;
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
            <h1 style="color: #4f46e5; font-weight: 700;">Create Employee</h1>
            <p class="text-muted">Add a new employee to the system</p>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#" style="color: #6b7280;">HR</a></li>
              <li class="breadcrumb-item"><a href="#" style="color: #6b7280;">Employees</a></li>
              <li class="breadcrumb-item active" style="color: #4f46e5; font-weight: 600;">Create</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card modern-card">
              <div class="card-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%); border-radius: 12px 12px 0 0;">
                <h3 class="card-title" style="color: white; font-weight: 600;">
                  <i class="fas fa-user-plus mr-2"></i>Employee Information
                </h3>
              </div>
              <div class="card-body" style="padding: 30px;">
                <form action="emp.store.php" method="POST" enctype="multipart/form-data" id="employeeForm">
                  
                  <!-- Personal Information Section -->
                  <div class="row mb-4">
                    <div class="col-12">
                      <h4 class="section-title">Personal Information</h4>
                    </div>
                  </div>

                  <div class="row">
                    <!-- Image Upload -->
                    <div class="col-md-4 mb-4">
                      <label class="form-label required-field">Profile Picture</label>
                      <div class="image-upload-area" onclick="document.getElementById('picture').click()">
                        <input type="file" class="d-none" id="picture" name="picture" required onchange="previewImage(this)">
                        <div id="uploadPlaceholder">
                          <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #9ca3af;"></i>
                          <p class="mb-1" style="color: #6b7280; font-weight: 500;">Click to upload photo</p>
                          <p class="small text-muted">PNG, JPG up to 5MB</p>
                        </div>
                        <div id="imagePreview" style="display: none;">
                          <div class="image-preview-container">
                            <img id="preview" src="#" alt="Image Preview" class="img-fluid rounded">
                          </div>
                          <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="removeImage()">
                            <i class="fas fa-trash mr-1"></i> Remove Photo
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="col-md-8">
                      <div class="row">
                        <div class="col-12 mb-3">
                          <label for="id_number" class="form-label">Employee ID</label>
                          <input type="text" id="id_number" name="id_number" class="form-control form-control-modern" placeholder="Enter employee ID">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-4 mb-3">
                          <label for="first_name" class="form-label required-field">First Name</label>
                          <input type="text" id="first_name" name="first_name" class="form-control form-control-modern" placeholder="First name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                          <label for="middle_name" class="form-label required-field">Middle Name</label>
                          <input type="text" id="middle_name" name="middle_name" class="form-control form-control-modern" placeholder="Middle name" required>
                        </div>
                        <div class="col-md-3 mb-3">
                          <label for="last_name" class="form-label required-field">Last Name</label>
                          <input type="text" id="last_name" name="last_name" class="form-control form-control-modern" placeholder="Last name" required>
                        </div>
                        <div class="col-md-1 mb-3">
                          <label for="ext_name" class="form-label">Ext</label>
                          <input type="text" id="ext_name" name="ext_name" class="form-control form-control-modern" placeholder="Ext">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="gender" class="form-label required-field">Gender</label>
                          <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                          </select>
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="bday" class="form-label required-field">Birthday</label>
                          <input type="date" id="bday" name="bday" class="form-control form-control-modern" required>
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
                      <input type="email" id="email" name="email" class="form-control form-control-modern" placeholder="employee@company.com" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="phone_number" class="form-label required-field">Phone Number</label>
                      <input type="tel" id="phone_number" name="phone_number" class="form-control form-control-modern" placeholder="+1 (555) 123-4567" required>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-12 mb-3">
                      <label for="address" class="form-label required-field">Address</label>
                      <textarea id="address" name="address" class="form-control form-control-modern" rows="3" placeholder="Enter complete address" required></textarea>
                    </div>
                  </div>

                  <!-- Employment Details Section -->
                  <div class="row mb-4 mt-4">
                    <div class="col-12">
                      <h4 class="section-title">Employment Details</h4>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label for="employment_status_id" class="form-label required-field">Employment Status</label>
                      <select id="employment_status_id" name="employment_status_id" class="form-control" required>
                        <?php if (!empty($employmentStatuses)): ?>
                          <?php foreach ($employmentStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status['status_id']) ?>">
                              <?= htmlspecialchars($status['status_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="">-- No statuses available --</option>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="col-md-3 mb-3">
                      <label for="appointment_status_id" class="form-label required-field">Appointment Status</label>
                      <select id="appointment_status_id" name="appointment_status_id" class="form-control" required>
                        <?php if (!empty($appointmentStatuses)): ?>
                          <?php foreach ($appointmentStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status['appointment_id']) ?>">
                              <?= htmlspecialchars($status['status_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="">-- No statuses available --</option>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="col-md-3 mb-3">
                      <label for="position_id" class="form-label required-field">Position</label>
                      <select id="position_id" name="position_id" class="form-control" required>
                        <option value="" disabled selected>Select Position</option>
                        <?php if (!empty($positions)): ?>
                          <?php foreach ($positions as $position): ?>
                            <option value="<?= htmlspecialchars($position['position_id']) ?>">
                              <?= htmlspecialchars($position['position_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="">-- No positions available --</option>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="col-md-3 mb-3">
                      <label for="section_id" class="form-label required-field">Section</label>
                      <select id="section_id" name="section_id" class="form-control" required>
                        <?php if (!empty($sections)): ?>
                          <?php foreach ($sections as $section): ?>
                            <option value="<?= htmlspecialchars($section['section_id']) ?>">
                              <?= htmlspecialchars($section['section_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="">-- No sections available --</option>
                        <?php endif; ?>
                      </select>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="office_id" class="form-label required-field">Office</label>
                      <select id="office_id" name="office_id" class="form-control" required>
                        <?php if (!empty($offices)): ?>
                          <?php foreach ($offices as $office): ?>
                            <option value="<?= htmlspecialchars($office['office_id']) ?>">
                              <?= htmlspecialchars($office['office_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="">-- No offices available --</option>
                        <?php endif; ?>
                      </select>
                    </div>
                  </div>

                  <!-- Submit Button -->
                  <div class="row mt-5">
                    <div class="col-12 text-right">
                      <button type="submit" class="btn btn-modern btn-lg">
                        <i class="fas fa-save mr-2"></i>Create Employee
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
  <?php include '../includes/mainfooter.php'; ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<?php include '../includes/footer.php'; ?>

</body>
<script>
// Enhanced Image preview function
function previewImage(input) {
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            imagePreview.style.display = 'block';
            uploadPlaceholder.style.display = 'none';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove image function
function removeImage() {
    const fileInput = document.getElementById('picture');
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    
    fileInput.value = '';
    preview.src = '#';
    imagePreview.style.display = 'none';
    uploadPlaceholder.style.display = 'block';
}

// Show existing image in edit mode
<?php if (isset($employee) && !empty($employee['picture'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    
    preview.src = '../assets/images/employees/<?= $employee['picture'] ?>';
    imagePreview.style.display = 'block';
    uploadPlaceholder.style.display = 'none';
});
<?php endif; ?>

// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('employeeForm');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in all required fields marked with *',
                confirmButtonColor: '#4f46e5'
            });
        }
    });

    // Real-time validation feedback
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.hasAttribute('required') && this.value.trim()) {
                this.style.borderColor = '#10b981';
                this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
            } else if (this.hasAttribute('required')) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
    });
});
</script>
<!-- SweetAlert for notifications -->
<script>
$(document).ready(function() {
    <?php if (isset($_SESSION['alert'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['alert']['type'] ?>',
            title: '<?= $_SESSION['alert']['title'] ?>',
            text: '<?= $_SESSION['alert']['message'] ?>',
            showConfirmButton: false,
            timer: 3000,
            background: '#ffffff',
            confirmButtonColor: '#4f46e5'
        });
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
});
</script>
</html>