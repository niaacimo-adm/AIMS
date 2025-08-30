<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

// Handle Excel import if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        // Check for upload errors
        if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['excel_file']['error']);
        }

        // Validate file type
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($_FILES['excel_file']['type'], $allowedTypes)) {
            throw new Exception("Only .xlsx files are allowed.");
        }

        // Load the Excel file
        $inputFileName = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Skip header row
        array_shift($rows);

        $database = new Database();
        $db = $database->getConnection();

        $successCount = 0;
       // Get default values using prepared statements
        $validDefaults = [
            'employment_status_id'  => $db->query("SELECT MIN(status_id) FROM employment_status")->fetch_row()[0],
            'appointment_status_id' => $db->query("SELECT MIN(appointment_id) FROM appointment_status")->fetch_row()[0],
            'section_id'           => $db->query("SELECT MIN(section_id) FROM section")->fetch_row()[0],
            'office_id'            => $db->query("SELECT MIN(office_id) FROM office")->fetch_row()[0],
            'position_id'          => $db->query("SELECT MIN(position_id) FROM position")->fetch_row()[0]
        ];

foreach ($rows as $row) {
    $employeeData = [
        'id_number'      => $row[0] ?? '',
        'first_name'     => $row[1] ?? '',
        'last_name'      => $row[2] ?? '',
        'email'          => $row[3] ?? '',
        'phone_number'   => $row[4] ?? '',
        // Use validated defaults
        'employment_status_id'   => $validDefaults['employment_status_id'],
        'appointment_status_id'  => $validDefaults['appointment_status_id'],
        'section_id'             => $validDefaults['section_id'],
        'office_id'              => $validDefaults['office_id'],
        'position_id'            => $validDefaults['position_id']
    ];

    $query = "INSERT INTO employee 
              (id_number, first_name, last_name, email, phone_number, 
              employment_status_id, appointment_status_id, section_id, office_id, position_id)
              VALUES 
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("sssssiiiii",
        $employeeData['id_number'],
        $employeeData['first_name'],
        $employeeData['last_name'],
        $employeeData['email'],
        $employeeData['phone_number'],
        $employeeData['employment_status_id'],
        $employeeData['appointment_status_id'],
        $employeeData['section_id'],
        $employeeData['office_id'],
        $employeeData['position_id']
    );
    $stmt->execute();
    $successCount++;
}

        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => "Successfully imported $successCount employees!"
        ];
        header("Location: emp.list.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Import failed: " . $e->getMessage()
        ];
    }
}

// Create database instance and get connection
$database = new Database();
$db = $database->getConnection();

// Fetch employees with joined data
$query = "SELECT 
    e.*,
    es.status_name as employment_status,
    o.office_name,
    s.section_name,
    CONCAT(sh.first_name, ' ', sh.last_name) as section_head_name,
    us.unit_name as unit_section_name,
    CONCAT(uh.first_name, ' ', uh.last_name) as unit_head_name,
    p.position_name,
    ap.status_name as appointment_status,
    ap.color as appointment_color
FROM employee e
LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
LEFT JOIN office o ON e.office_id = o.office_id
LEFT JOIN section s ON e.section_id = s.section_id
LEFT JOIN employee sh ON s.head_emp_id = sh.emp_id
LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
LEFT JOIN employee uh ON us.head_emp_id = uh.emp_id
LEFT JOIN position p ON e.position_id = p.position_id
LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          ORDER BY e.emp_id DESC";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result(); // Get the result set from MySQLi

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

$query = "SELECT * FROM appointment_status";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
// Fetch all appointment statuses for reference
$appointmentStatuses = [];
while ($row = $result->fetch_assoc()) {
    $appointmentStatuses[] = $row;
}

$query = "SELECT * FROM employment_status";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employmentStatuses = [];
while ($row = $result->fetch_assoc()) {
    $employmentStatuses[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Employee List</title>
  <?php include '../includes/header.php'; ?>
<style>
    .profile-grid-container {
        width: 100%;
        overflow-y: auto;
        min-height: calc(100vh - 300px);
        padding-bottom: 20px;
    }

    /* Grid container */
    .profile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        padding: 10px;
        margin-top: 20px;
        width: 100%;
    }

    /* Card styling */
    .profile-card {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
        background: white;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }

    .profile-header {
        position: relative;
        height: 120px;
        background: linear-gradient(135deg, #6e8efb, #a777e3);
        display: flex;
        align-items: flex-end;
        justify-content: center;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid white;
        background: #f8f9fa;
        position: absolute;
        bottom: -50px;
        object-fit: cover;
    }

    .profile-body {
        padding: 60px 20px 20px;
        text-align: center;
        flex-grow: 1;
    }

    .profile-name {
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 1.1rem;
    }

    .profile-position {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }

    .profile-details {
        text-align: left;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }

    .profile-detail {
        display: flex;
        margin-bottom: 8px;
    }

    .profile-detail i {
        width: 20px;
        color: #6c757d;
        margin-right: 10px;
    }

    /* PROFILE ACTIONS - FIXED STYLING */
    .profile-actions {
        display: flex;
        justify-content: center;
        padding: 10px;
        border-top: 1px solid #eee;
        background: white;
        margin-top: auto; /* Push to bottom of card */
    }

    .profile-actions a {
        margin: 0 5px;
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    /* Badges and other elements */
    .badge-custom {
        font-size: 0.75rem;
        padding: 4px 8px;
        margin: 2px;
    }

    /* Search and pagination */
    .card-header .card-title {
        line-height: 1.8;
    }

    #gridSearch {
        transition: all 0.3s;
    }

    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 20px;
        color: #6c757d;
        font-style: italic;
    }

    .grid-pagination {
        padding: 15px;
        border-top: 1px solid #dee2e6;
        margin-top: 10px;
        background: white;
    }

    .grid-pagination button {
        margin-left: 5px;
    }

    .grid-pagination .disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .profile-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
        
        .profile-grid-container {
            min-height: calc(100vh - 350px);
        }
        
        .profile-actions a {
            margin: 0 3px;
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
        }
    }
    #gridViewBtn.active {
    background-color: #007bff;
    color: white;
    }

    #tableViewBtn.active {
    background-color: #007bff;
    color: white;
    }
</style>

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
            <!-- <h1>Employee List</h1> -->
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
                <?php if (isset($_GET['success'])): ?>
                  <div class="alert alert-success">
                    <?= htmlspecialchars($_GET['success'] == '1' ? "Employee created successfully!" : 
                                          "Employee assignment updated successfully!") ?>
                  </div>
                <?php endif; ?>
                <div class="card-header">
                  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                      <h1 class="h3 mb-3 mb-md-0"><b>Employee Table</b></h1>
                      
                      <div class="d-flex flex-wrap align-items-center gap-2">
                          <!-- View Toggle Buttons -->
                          <div class="btn-group order-md-2">
                              <button id="tableViewBtn" class="btn btn-outline-primary active">
                                  <i class="fas fa-table"></i>
                                  <span class="d-none d-sm-inline"> Table View</span>
                              </button>
                              <button id="gridViewBtn" class="btn btn-outline-primary">
                                  <i class="fas fa-th-large"></i>
                                  <span class="d-none d-sm-inline"> Grid View</span>
                              </button>
                          </div>
                          
                          <!-- Action Buttons -->
                          <div class="btn-group order-md-1">
                              <a href="emp.create.php" class="btn btn-primary" title="Add Employee">
                                  <i class="fas fa-plus"></i>
                                  <span class="d-none d-sm-inline"> Add New</span>
                              </a>
                              
                              <button type="button" class="btn btn-success" data-toggle="modal" data-target="#importModal" title="Import">
                                  <i class="fas fa-file-import"></i>
                                  <span class="d-none d-sm-inline"> Import</span>
                              </button>
                              
                              <a href="emp.export.php" class="btn btn-info" title="Export">
                                  <i class="fas fa-file-export"></i>
                                  <span class="d-none d-sm-inline"> Export</span>
                              </a>
                          </div>
                      </div>
                  </div>
              </div>
                <!-- Import Excel Modal -->
                <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Import Employees from Excel</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <form action="emp.list.php" method="post" enctype="multipart/form-data">
                        <div class="modal-body">
                          <div class="form-group">
                            <label for="excel_file">Excel File</label>
                            <input type="file" class="form-control-file" id="excel_file" name="excel_file" accept=".xlsx, .xls, .csv" required>
                            <small class="form-text text-muted">
                              Please upload an Excel file (.xlsx, .xls) or CSV file.
                              <a href="path/to/sample_template.xlsx" download>Download sample template</a>
                            </small>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary">Import</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="table-responsive" style="display: none;">
                  <table id="employeeTable" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Picture</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Birthday</th>
                        <th>Employment Status</th>
                        <th>Appointment Status</th>
                        <th>Position</th>
                        <th>Office</th>
                        <th>Section</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($employees as $employee): ?>
                      <tr>
                        <td><?= htmlspecialchars($employee['id_number']) ?></td>
                        <td>
                          <?php 
                          $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                          if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                            <img src="<?= $imagePath ?>" 
                                 class="img-circle elevation-2" 
                                 style="width:50px; height:50px; object-fit:cover;"
                                 alt="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>">
                          <?php else: ?>
                            <div class="text-center">
                              <i class="fas fa-user-circle fa-3x text-muted"></i>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        <td><?= htmlspecialchars($employee['email']) ?></td>
                        <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                        <td><?= htmlspecialchars($employee['bday']) ?></td>
                        <td>
                          <?php 
                            // Find the matching status for this employee
                            $statusInfo = null;
                            foreach ($employmentStatuses as $status) {
                                if ($status['status_id'] == $employee['employment_status_id']) {
                                    $statusInfo = $status;
                                    break;
                                }
                            }
                            
                            if ($statusInfo): ?>
                                <span class="badge" style="background-color: <?= htmlspecialchars($statusInfo['color']) ?>; 
                                                          color: <?= (hexdec(substr($statusInfo['color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                                    <?= htmlspecialchars($statusInfo['status_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Unknown Status</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            // Find the matching status for this employee
                            $statusInfo = null;
                            foreach ($appointmentStatuses as $status) {
                                if ($status['appointment_id'] == $employee['appointment_status_id']) {
                                    $statusInfo = $status;
                                    break;
                                }
                            }
                            
                            if ($statusInfo): ?>
                                <span class="badge" style="background-color: <?= htmlspecialchars($statusInfo['color']) ?>; 
                                                          color: <?= (hexdec(substr($statusInfo['color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                                    <?= htmlspecialchars($statusInfo['status_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Unknown Status</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($employee['position_name']) ?></td>
                        <td><?= htmlspecialchars($employee['office_name']) ?></td>
                        <td>
                            <?= htmlspecialchars($employee['section_name']) ?>
                            <?php if (!empty($employee['unit_section_names'])): ?>
                                <small class="text-muted d-block">Units: <?= htmlspecialchars($employee['unit_section_names']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                          <div class="btn-group">
                            <a href="emp.assign.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-primary">
                              <i class="fas fa-tasks"></i>
                            </a>
                            <a href="emp.edit.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-warning">
                              <i class="fas fa-edit"></i>
                            </a>
                            <a href="emp.profile.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-secondary">
                              <i class="fas fa-user"></i>
                            </a>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div id="profileGrid" class="profile-grid-container">
                    <div class="card">
                        <div class="card-header">
                        <h3 class="card-title">Employee Grid View</h3>
                        <div class="card-tools">
                            <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="text" id="gridSearch" class="form-control float-right" placeholder="Search...">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default">
                                <i class="fas fa-search"></i>
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>
                        <div class="card-body p-0">
                        <div class="profile-grid">
                            <?php foreach ($employees as $employee): ?>
                            <div class="profile-card" data-search="<?= strtolower(htmlspecialchars($employee['first_name'].' '.$employee['last_name'].' '.$employee['id_number'].' '.$employee['position_name'].' '.$employee['office_name'])) ?>">
                            <!-- Your existing profile card content -->
                            <div class="profile-header">
                                <?php 
                                $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                                if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                                <img src="<?= $imagePath ?>" class="profile-avatar" 
                                    alt="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>">
                                <?php else: ?>
                                <div class="profile-avatar d-flex align-items-center justify-content-center">
                                    <i class="fas fa-user fa-3x text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-body">
                                <h5 class="profile-name"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
                                <div class="profile-position"><?= htmlspecialchars($employee['position_name']) ?></div>
                                
                                <div class="profile-details">
                                <div class="profile-detail">
                                    <i class="fas fa-id-card"></i>
                                    <span><?= htmlspecialchars($employee['id_number']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="fas fa-envelope"></i>
                                    <span><?= htmlspecialchars($employee['email']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?= htmlspecialchars($employee['phone_number']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="fas fa-building"></i>
                                    <span><?= htmlspecialchars($employee['office_name']) ?></span>
                                </div>
                                </div>
                                
                                <div class="mb-3">
                                <?php 
                                // Employment status badge
                                $statusInfo = null;
                                foreach ($employmentStatuses as $status) {
                                    if ($status['status_id'] == $employee['employment_status_id']) {
                                    $statusInfo = $status;
                                    break;
                                    }
                                }
                                if ($statusInfo): ?>
                                    <span class="badge badge-custom" style="background-color: <?= htmlspecialchars($statusInfo['color']) ?>; 
                                        color: <?= (hexdec(substr($statusInfo['color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                                    <?= htmlspecialchars($statusInfo['status_name']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php 
                                // Appointment status badge
                                $statusInfo = null;
                                foreach ($appointmentStatuses as $status) {
                                    if ($status['appointment_id'] == $employee['appointment_status_id']) {
                                    $statusInfo = $status;
                                    break;
                                    }
                                }
                                if ($statusInfo): ?>
                                    <span class="badge badge-custom" style="background-color: <?= htmlspecialchars($statusInfo['color']) ?>; 
                                        color: <?= (hexdec(substr($statusInfo['color'], 1)) > 0xffffff/2) ? '#000000' : '#ffffff' ?>">
                                    <?= htmlspecialchars($statusInfo['status_name']) ?>
                                    </span>
                                <?php endif; ?>
                                </div>
                            </div>
                            <div class="profile-actions">
                                <a href="emp.assign.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-primary" title="Assign">
                                <i class="fas fa-tasks"></i>
                                </a>
                                <a href="emp.edit.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                                </a>
                                <a href="emp.profile.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-secondary" title="Profile">
                                <i class="fas fa-user"></i>
                                </a>
                            </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    </div>
                </div>
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

<!-- SweetAlert Toast Notification -->
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
<?php unset($_SESSION['toast']); endif; ?>

<!-- Combined DataTables and View Toggle Script -->
<script>
$(document).ready(function() {
    // Initialize DataTable only once
    var employeeTable = $("#employeeTable").DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 5,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 1 }, // Picture
            { responsivePriority: 2, targets: 2 }, // Name
            { responsivePriority: 3, targets: -1 } // Actions
        ],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: {
                previous: "&laquo;",
                next: "&raquo;"
            }
        }
    });

    // Grid View with Pagination - DECLARE VARIABLES FIRST
    let currentGridPage = 1;
    const itemsPerPage = 16;
    let filteredEmployees = [];

    function updateGridPagination() {
        // Hide all cards first
        $('.profile-card').hide();
        
        // Calculate start and end index
        const startIndex = (currentGridPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        
        // Show only cards for current page
        filteredEmployees.slice(startIndex, endIndex).forEach(index => {
            $('.profile-card').eq(index).show();
        });
        
        // Update pagination controls
        updatePaginationControls();
    }

    function updatePaginationControls() {
        const totalPages = Math.ceil(filteredEmployees.length / itemsPerPage);
        
        // Clear existing controls
        $('.grid-pagination').remove();
        
        // Only show pagination if needed
        if (totalPages > 1) {
            const paginationHtml = `
                <div class="grid-pagination clearfix">
                    <div class="float-left">
                        Showing page ${currentGridPage} of ${totalPages}
                    </div>
                    <div class="float-right">
                        <button class="btn btn-sm btn-outline-secondary ${currentGridPage === 1 ? 'disabled' : ''}" 
                                id="prevGridPage">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <button class="btn btn-sm btn-outline-secondary ${currentGridPage === totalPages ? 'disabled' : ''}" 
                                id="nextGridPage">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            `;
            
            $('#profileGrid .card-body').append(paginationHtml);
            
            // Bind click events
            $('#prevGridPage').click(function() {
                if (currentGridPage > 1) {
                    currentGridPage--;
                    updateGridPagination();
                }
            });
            
            $('#nextGridPage').click(function() {
                if (currentGridPage < totalPages) {
                    currentGridPage++;
                    updateGridPagination();
                }
            });
        }
    }

    // Grid View Search Functionality
    $('#gridSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filteredEmployees = [];
        
        $('.profile-card').each(function(index) {
            const cardData = $(this).data('search');
            if (cardData.includes(searchTerm)) {
                filteredEmployees.push(index);
            }
        });
        
        // Reset to first page when searching
        currentGridPage = 1;
        
        // Update display
        updateGridPagination();
        
        // Show no results message if needed
        $('.no-results').remove();
        if (filteredEmployees.length === 0) {
            $('.profile-grid').append('<div class="no-results">No employees match your search</div>');
        }
    });

    // Initialize grid view as default
    function initializeGridAsDefault() {
        // Set grid as active view
        $('#gridViewBtn').addClass('active');
        $('#tableViewBtn').removeClass('active');
        
        // Initialize grid pagination with all employees
        filteredEmployees = Array.from({length: $('.profile-card').length}, (_, i) => i);
        updateGridPagination();
        
        // Calculate and set container height
        const headerHeight = $('.content-header').outerHeight(true);
        const cardHeaderHeight = $('#profileGrid .card-header').outerHeight(true);
        const windowHeight = $(window).height();
        const availableHeight = windowHeight - headerHeight - cardHeaderHeight - 80;
        
        $('#profileGrid .card-body').css({
            'height': availableHeight + 'px',
            'overflow-y': 'auto'
        });
    }

    // Set grid view as default
    initializeGridAsDefault();

    // Toggle between table and grid view
    $('#tableViewBtn').click(function() {
        $('.table-responsive').show();
        $('#profileGrid').hide();
        $(this).addClass('active');
        $('#gridViewBtn').removeClass('active');
        employeeTable.columns.adjust().responsive.recalc();
    });
    
    $('#gridViewBtn').click(function() {
        $('.table-responsive').hide();
        $('#profileGrid').show();
        $(this).addClass('active');
        $('#tableViewBtn').removeClass('active');
        
        // Reinitialize grid view when switching back from table
        filteredEmployees = Array.from({length: $('.profile-card').length}, (_, i) => i);
        currentGridPage = 1;
        updateGridPagination();
    });
    
    // Handle window resize
    $(window).on('resize', function() {
        if ($('#profileGrid').is(':visible')) {
            const headerHeight = $('.content-header').outerHeight(true);
            const cardHeaderHeight = $('#profileGrid .card-header').outerHeight(true);
            const windowHeight = $(window).height();
            const availableHeight = windowHeight - headerHeight - cardHeaderHeight - 80;
            
            $('#profileGrid .card-body').css('height', availableHeight + 'px');
        }
    });
});
</script>
</body>
</html>