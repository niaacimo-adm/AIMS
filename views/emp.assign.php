<?php 
require_once '../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database instance and get connection
$database = new Database();
$db = $database->getConnection();

// Get employee ID from URL
$emp_id = $_GET['emp_id'] ?? null;
if (!$emp_id) {
    header("Location: emp.list.php?error=invalid_id");
    exit();
}

// Replace the employee query with this:
$query = "SELECT 
            e.*,
            es.status_name as employment_status,
            o.office_name,
            s.section_name,
            s.section_id,
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
    header("Location: emp.list.php?error=employee_not_found");
    exit();
}

// Fetch all lookup data for dropdowns
$employmentStatuses = [];
$appointmentStatuses = [];
$positions = [];
$sections = [];
$offices = [];

// Get employment statuses
$stmt = $db->prepare("SELECT * FROM employment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employmentStatuses[] = $row;
}

// Get appointment statuses
$stmt = $db->prepare("SELECT * FROM appointment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointmentStatuses[] = $row;
}

// Get positions
$stmt = $db->prepare("SELECT * FROM position");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}

// Get sections
$stmt = $db->prepare("SELECT s.section_id, s.section_name, 
                      CONCAT(e.first_name, ' ', e.last_name) as head_name 
                      FROM section s
                      LEFT JOIN employee e ON s.head_emp_id = e.emp_id");
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

// Get offices
$stmt = $db->prepare("SELECT * FROM office");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

$query = "SELECT us.*, s.section_name 
          FROM unit_section us
          LEFT JOIN section s ON us.section_id = s.section_id
          ORDER BY us.unit_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$unit_sections = [];
while ($row = $result->fetch_assoc()) {
    $unit_sections[] = $row;
}
// Define statuses that should disable other fields
$disableStatuses = ['Inactive', 'Separated - Death', 'Non-renewal', 'Resigned', 'Retired', 'AWOL'];
// echo "<pre>Employee Data: ";
// print_r($employee);
// echo "</pre>";

// echo "<pre>Unit Sections: ";
// print_r($unit_sections);
// echo "</pre>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Assign Employee</title>
  <?php include '../includes/header.php'; ?>
  <style>
    select:disabled {
        background-color: #e9ecef;
        opacity: 1;
    }
        /* Add to your existing style section */
    select option[style*="color:red"] {
        color: red !important;
        font-style: italic;
    }
    select option[style*="color:blue"] {
        color: blue !important;
        font-weight: bold;
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
            <h1>Assign Employee: <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Assignment Details</h3>
              </div>
              <div class="card-body">
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">Error updating assignment. Please try again.</div>
                <?php endif; ?>
                
                <form action="emp.update_assignment.php" method="POST">
                    <input type="hidden" name="emp_id" value="<?= $emp_id ?>">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employment_status_id">Employment Status</label>
                                <select class="form-control" id="employment_status_id" name="employment_status_id" required>
                                    <?php foreach ($employmentStatuses as $status): ?>
                                        <option value="<?= $status['status_id'] ?>" 
                                            <?= $status['status_id'] == $employee['employment_status_id'] ? 'selected' : '' ?>
                                            data-status-name="<?= htmlspecialchars($status['status_name']) ?>">
                                            <?= htmlspecialchars($status['status_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                              <label for="appointment_status_id">Appointment Status (Required)</label>
                              <select id="appointment_status_id" name="appointment_status_id" class="form-control" required>
                                  <?php foreach ($appointmentStatuses as $status): ?>
                                      <option value="<?= $status['appointment_id'] ?>" 
                                              <?= $status['appointment_id'] == $employee['appointment_status_id'] ? 'selected' : '' ?>
                                              data-color="<?= htmlspecialchars($status['color']) ?>">
                                          <?= htmlspecialchars($status['status_name']) ?>
                                      </option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="position_id">Position</label>
                                <select class="form-control" id="position_id" name="position_id" required>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?= $position['position_id'] ?>" <?= $position['position_id'] == $employee['position_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($position['position_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Section Dropdown -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="section_id">Section</label>
                                <select class="form-control" id="section_id" name="section_id">
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
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="unit_section_ids">Unit Sections (Optional)</label>
                                <select class="form-control select2" id="unit_section_ids" name="unit_section_ids[]" multiple="multiple">
                                    <option value="">-- No Unit Section --</option>
                                    <?php 
                                    $current_unit_ids = !empty($employee['unit_section_ids']) ? explode(',', $employee['unit_section_ids']) : [];
                                    foreach ($unit_sections as $unit): ?>
                                        <option value="<?= $unit['unit_id'] ?>" 
                                            <?= in_array($unit['unit_id'], $current_unit_ids) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unit['unit_name']) ?> 
                                            (<?= htmlspecialchars($unit['section_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="office_id">Office</label>
                                <select class="form-control" id="office_id" name="office_id" required>
                                    <?php foreach ($offices as $office): ?>
                                        <option value="<?= $office['office_id'] ?>" <?= $office['office_id'] == $employee['office_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($office['office_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">Update Assignment</button>
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
</body>
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
<?php 
    unset($_SESSION['toast']);
endif; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
        const employmentStatusSelect = document.getElementById('employment_status_id');
        const appointmentStatusSelect = document.getElementById('appointment_status_id');
        const positionSelect = document.getElementById('position_id');
        const sectionSelect = document.getElementById('section_id');
        const unitSectionSelect = document.getElementById('unit_section_id');
        const officeSelect = document.getElementById('office_id');
        const submitBtn = document.getElementById('submitBtn');
        
        // List of status names that should disable other fields
        const disableStatuses = <?php echo json_encode($disableStatuses); ?>;
        
        function checkEmploymentStatus() {
            const selectedOption = employmentStatusSelect.options[employmentStatusSelect.selectedIndex];
            const statusName = selectedOption.getAttribute('data-status-name');
            
            // Check if the selected status is in our disable list
            const shouldDisable = disableStatuses.includes(statusName);
            
            // Disable/enable fields accordingly (except submit button)
            appointmentStatusSelect.disabled = shouldDisable;
            positionSelect.disabled = shouldDisable;
            sectionSelect.disabled = shouldDisable;
            officeSelect.disabled = shouldDisable;
            
            // Always keep the submit button enabled
            submitBtn.disabled = false;
            
            // If disabling, show a message to the user
            if (shouldDisable) {
                Swal.fire({
                    icon: 'info',
                    title: 'Status Change',
                    text: 'Since the employee status is ' + statusName + ', other assignment fields have been disabled.',
                    toast: true,
                    position: 'top',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        }


    // Initial setup
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the unit section dropdown
        updateUnitSections();
        
        // Update when section changes
        document.getElementById('section_id').addEventListener('change', updateUnitSections);
    });
    // Initial update
    updateUnitSections();

    // Update unit sections when section changes
    sectionSelect.addEventListener('change', updateUnitSections);

    // Initial update
    updateUnitSections();

    sectionSelect.addEventListener('change', updateUnitSections);
    // Initial check when page loads
    checkEmploymentStatus();
    
    // Check whenever the status changes
    employmentStatusSelect.addEventListener('change', checkEmploymentStatus);
    
    // Appointment status color handling (existing code)
    function updateStatusColor() {
        const selectedOption = appointmentStatusSelect.options[appointmentStatusSelect.selectedIndex];
        const color = selectedOption.getAttribute('data-color');
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
    
    // Initial update
    updateStatusColor();
    
    // Update on change
    appointmentStatusSelect.addEventListener('change', updateStatusColor);
});
document.addEventListener('DOMContentLoaded', function() {
    const sectionSelect = document.getElementById('section_id');
    const unitSectionSelect = document.getElementById('unit_section_id');
    
    function updateUnitSectionState() {
        unitSectionSelect.disabled = (sectionSelect.value === 'nosec');
    }
    
    // Initial state
    updateUnitSectionState();
    
    // Update when section changes
    sectionSelect.addEventListener('change', updateUnitSectionState);
});
$('.select2').select2({
    placeholder: "Select unit sections...",
    allowClear: true
});
</script>
</html>