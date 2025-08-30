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
  <title>AdminLTE 3 | Dashboard 3</title>

  <?php include '../includes/header.php'; ?>
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
            <h1>Create Employee</h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-12">
            <!-- jquery validation -->
                <div class="card card-primary">
                <div class="card-header">
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                    <div class="card-body">
                        <form action="emp.store.php" method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="picture">Picture (Required)</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="picture" name="picture" required
                                                onchange="previewImage(this)">
                                            <label class="custom-file-label" for="picture">Choose file</label>
                                        </div>
                                        <!-- Image preview container -->
                                        <div class="mt-2" id="imagePreview" style="display: none;">
                                            <img id="preview" src="#" alt="Image Preview" 
                                                class="img-thumbnail" style="max-height: 200px;">
                                            <button type="button" class="btn btn-danger btn-sm mt-2" 
                                                    onclick="removeImage()">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="id_number">ID Number</label>
                                    <input type="text" id="id_number" name="id_number" class="form-control">
                                </div>
                            </div>
                            
                            
                            <div class="row">
                                <div class="col-4">
                                    <label for="first_name">First Name (Required)</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-4">
                                    <label for="middle_name">Middle Name (Required)</label>
                                    <input type="text" id="middle_name" name="middle_name" class="form-control" required>
                                </div>
                                <div class="col-3">
                                    <label for="last_name">Last Name (Required)</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                                </div>
                                <div class="col-1">
                                    <label for="ext_name">Ext</label>
                                    <input type="text" id="ext_name" name="ext_name" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender (Required)</label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address (Required)</label>
                                <textarea id="address" name="address" class="form-control" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="bday">Birthday (Required)</label>
                                <input type="date" id="bday" name="bday" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email (Required)</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone_number">Phone Number (Required)</label>
                                <input type="tel" id="phone_number" name="phone_number" class="form-control" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-4">
                                    <label for="employment_status_id">Employment Status (Required)</label>
                                    <select id="employment_status_id" name="employment_status_id" class="form-control">
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
                                <div class="col-4">
                                    <label for="appointment_status_id">Appointment Status (Required)</label>
                                    <select id="appointment_status_id" name="appointment_status_id" class="form-control">
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
                                <div class="col-4">
                                    <label for="position_id"><strong>Position (Required)</strong></label>
                                    <select id="position_id" name="position_id" class="form-control">
                                        <option value="" disabled selected>Select a position</option>
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
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <label for="section_id">Section (Required)</label>
                                    <select id="section_id" name="section_id" class="form-control">
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
                                <div class="col-6">
                                    <label for="office_id">Office (Required)</label>
                                    <select id="office_id" name="office_id" class="form-control">
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
                            <br>
                            <button type="submit" class="btn btn-primary">Save Employee</button>
                        </form>
                    </div>
                </div>
            <!-- /.card -->
            </div>
          <!--/.col (right) -->
        </div>
        <!-- /.row -->
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
// Image preview function
function previewImage(input) {
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const fileLabel = document.querySelector('.custom-file-label');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            imagePreview.style.display = 'block';
            fileLabel.textContent = input.files[0].name;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove image function
function removeImage() {
    const fileInput = document.getElementById('picture');
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const fileLabel = document.querySelector('.custom-file-label');
    
    fileInput.value = '';
    preview.src = '#';
    imagePreview.style.display = 'none';
    fileLabel.textContent = 'Choose file';
}

// Show existing image in edit mode
<?php if (isset($employee) && !empty($employee['picture'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    const fileLabel = document.querySelector('.custom-file-label');
    
    preview.src = '../assets/images/employees/<?= $employee['picture'] ?>';
    imagePreview.style.display = 'block';
    fileLabel.textContent = 'Current image: <?= $employee['picture'] ?>';
});
<?php endif; ?>
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
            timer: 3000
        });
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
});
</script>
</html>