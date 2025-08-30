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

// Fetch employee data
$query = "SELECT * FROM employee WHERE emp_id = ?";
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

$stmt = $db->prepare("SELECT * FROM section");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Edit Employee</title>
  <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Edit Employee: <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-body">
                <form id="editEmployeeForm" action="emp.update.php" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="emp_id" value="<?= $emp_id ?>">
                  <input type="hidden" name="old_picture" value="<?= htmlspecialchars($employee['picture']) ?>">
                  
                  <!-- Picture and ID Number -->
                  <div class="row">
                    <div class="col-6">
                      <div class="form-group">
                          <label for="picture">Picture (Required)</label>
                          <div class="custom-file">
                              <input type="file" class="custom-file-input" id="picture" name="picture" 
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
                      <div class="form-group">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number" class="form-control" value="<?= htmlspecialchars($employee['id_number']) ?>">
                      </div>
                    </div>
                  </div>
                  
                  <!-- Name Fields -->
                  <div class="row">
                    <div class="col-4">
                      <div class="form-group">
                        <label for="first_name">First Name (Required)</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars($employee['first_name']) ?>" required>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="form-group">
                        <label for="middle_name">Middle Name (Required)</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" value="<?= htmlspecialchars($employee['middle_name']) ?>" required>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="form-group">
                        <label for="last_name">Last Name (Required)</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars($employee['last_name']) ?>" required>
                      </div>
                    </div>
                    <div class="col-1">
                      <div class="form-group">
                        <label for="ext_name">Ext</label>
                        <input type="text" id="ext_name" name="ext_name" class="form-control" value="<?= htmlspecialchars($employee['ext_name']) ?>">
                      </div>
                    </div>
                  </div>
                  
                  <!-- Personal Information -->
                  <div class="form-group">
                    <label for="gender">Gender (Required)</label>
                    <select id="gender" name="gender" class="form-control" required>
                      <option value="Male" <?= $employee['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                      <option value="Female" <?= $employee['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                      <option value="Other" <?= $employee['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                  </div>
                  
                  <div class="form-group">
                    <label for="address">Address (Required)</label>
                    <textarea id="address" name="address" class="form-control" required><?= htmlspecialchars($employee['address']) ?></textarea>
                  </div>
                  
                  <div class="form-group">
                    <label for="bday">Birthday (Required)</label>
                    <input type="date" id="bday" name="bday" class="form-control" value="<?= htmlspecialchars($employee['bday']) ?>" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="email">Email (Required)</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email']) ?>" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="phone_number">Phone Number (Required)</label>
                    <input type="tel" id="phone_number" name="phone_number" class="form-control" value="<?= htmlspecialchars($employee['phone_number']) ?>" required>
                  </div>
                  <div class="form-group">
    <label>Manager's Office</label>
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="is_manager" name="is_manager" 
               value="1" <?= $employee['is_manager'] == 1 ? 'checked' : '' ?>>
        <label class="custom-control-label" for="is_manager">Assign to Manager's Office</label>
    </div>
</div>
                  <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Employee</button>
                    <a href="emp.list.php" class="btn btn-secondary">Cancel</a>
                  </div>
                </form>
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

<!-- SweetAlert2 Toast Notification -->
<?php if (isset($_SESSION['toast'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('appointment_status_id');
    
    function updateStatusColor() {
        const selectedOption = statusSelect.options[statusSelect.selectedIndex];
        const color = selectedOption.getAttribute('data-color');
        statusSelect.style.backgroundColor = color;
        statusSelect.style.color = getContrastColor(color);
    }
    
    // Helper function to determine text color based on background
    function getContrastColor(hexColor) {
        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#000000' : '#ffffff';
    }
    
    // Initial update
    updateStatusColor();
    
    // Update on change
    statusSelect.addEventListener('change', updateStatusColor);
});
</script>
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
    
    preview.src = '../dist/img/employees/<?= $employee['picture'] ?>';
    imagePreview.style.display = 'block';
    fileLabel.textContent = 'Current image: <?= $employee['picture'] ?>';
});
<?php endif; ?>
</script>
</body>
</html>