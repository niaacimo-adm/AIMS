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
          ORDER BY e.last_name ASC, e.first_name ASC"; // Changed from emp_id DESC to last_name ASC
          
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
  <link rel="stylesheet" href="../css/emp_list.css">
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
                                <button type="button" class="btn btn-warning" id="advancedSearchBtn" title="Advanced Search">
                                    <i class="fas fa-search-plus"></i>
                                    <span class="d-none d-sm-inline"> Advanced Search</span>
                                    <span id="activeFilterCount" class="search-active-badge" style="display: none;">0</span>
                                </button>
                          </div>
                      </div>
                  </div>
              </div>
              <!-- Advanced Search Modal -->
                <div class="advanced-search-backdrop" id="advancedSearchBackdrop"></div>
                <div class="advanced-search-modal" id="advancedSearchModal">
                    <div class="advanced-search-header">
                        <h4><i class="fas fa-search-plus mr-2"></i>Advanced Search</h4>
                        <button type="button" class="advanced-search-close" id="advancedSearchClose">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="advanced-search-body">
                        <!-- Basic Information Section -->
                        <div class="advanced-search-section">
                            <h6><i class="fas fa-user"></i> Basic Information</h6>
                            
                            <div class="search-form-group">
                                <label for="searchName">Name</label>
                                <input type="text" class="search-form-control" id="searchName" 
                                    placeholder="Search by first or last name...">
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchIdNumber">ID Number</label>
                                <input type="text" class="search-form-control" id="searchIdNumber" 
                                    placeholder="Enter employee ID...">
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchEmail">Email</label>
                                <input type="text" class="search-form-control" id="searchEmail" 
                                    placeholder="Search by email...">
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchPhone">Phone Number</label>
                                <input type="text" class="search-form-control" id="searchPhone" 
                                    placeholder="Search by phone...">
                            </div>
                        </div>
                        
                        <!-- Employment Details Section -->
                        <div class="advanced-search-section">
                            <h6><i class="fas fa-briefcase"></i> Employment Details</h6>
                            
                            <div class="search-form-group">
                                <label for="searchPosition">Position</label>
                                <select class="search-form-control search-select" id="searchPosition">
                                    <option value="">All Positions</option>
                                    <?php
                                    $positionQuery = "SELECT position_id, position_name FROM position ORDER BY position_name";
                                    $positionStmt = $db->prepare($positionQuery);
                                    $positionStmt->execute();
                                    $positions = $positionStmt->get_result();
                                    
                                    while ($position = $positions->fetch_assoc()): ?>
                                        <option value="<?= $position['position_id'] ?>">
                                            <?= htmlspecialchars($position['position_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchOffice">Office</label>
                                <select class="search-form-control search-select" id="searchOffice">
                                    <option value="">All Offices</option>
                                    <?php
                                    $officeQuery = "SELECT office_id, office_name FROM office ORDER BY office_name";
                                    $officeStmt = $db->prepare($officeQuery);
                                    $officeStmt->execute();
                                    $offices = $officeStmt->get_result();
                                    
                                    while ($office = $offices->fetch_assoc()): ?>
                                        <option value="<?= $office['office_id'] ?>">
                                            <?= htmlspecialchars($office['office_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchSection">Section</label>
                                <select class="search-form-control search-select" id="searchSection">
                                    <option value="">All Sections</option>
                                    <?php
                                    $sectionQuery = "SELECT section_id, section_name FROM section ORDER BY section_name";
                                    $sectionStmt = $db->prepare($sectionQuery);
                                    $sectionStmt->execute();
                                    $sections = $sectionStmt->get_result();
                                    
                                    while ($section = $sections->fetch_assoc()): ?>
                                        <option value="<?= $section['section_id'] ?>">
                                            <?= htmlspecialchars($section['section_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Status Filters Section -->
                        <div class="advanced-search-section">
                            <h6><i class="fas fa-tags"></i> Status Filters</h6>
                            
                            <div class="search-form-group">
                                <label for="searchEmploymentStatus">Employment Status</label>
                                <select class="search-form-control search-select" id="searchEmploymentStatus">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($employmentStatuses as $status): ?>
                                        <option value="<?= $status['status_id'] ?>">
                                            <?= htmlspecialchars($status['status_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchAppointmentStatus">Appointment Status</label>
                                <select class="search-form-control search-select" id="searchAppointmentStatus">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($appointmentStatuses as $status): ?>
                                        <option value="<?= $status['appointment_id'] ?>">
                                            <?= htmlspecialchars($status['status_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Date Range Section -->
                        <div class="advanced-search-section">
                            <h6><i class="fas fa-calendar-alt"></i> Date Range</h6>
                            
                            <div class="search-form-group">
                                <label for="searchDateFrom">From Date</label>
                                <input type="date" class="search-form-control" id="searchDateFrom">
                            </div>
                            
                            <div class="search-form-group">
                                <label for="searchDateTo">To Date</label>
                                <input type="date" class="search-form-control" id="searchDateTo">
                            </div>
                        </div>
                    </div>
                    
                    <div class="advanced-search-footer">
                        <button type="button" class="btn-search-clear" id="clearSearchFilters">
                            <i class="fas fa-eraser mr-1"></i> Clear All
                        </button>
                        <button type="button" class="btn-search-apply" id="applySearchFilters">
                            <i class="fas fa-filter mr-1"></i> Apply Filters
                        </button>
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
                      <tr data-employment-status="<?= $employee['employment_status_id'] ?>" 
                            data-appointment-status="<?= $employee['appointment_status_id'] ?>">
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
                                <button type="button" class="btn btn-sm btn-danger delete-employee" 
                                        data-emp-id="<?= $employee['emp_id'] ?>" 
                                        data-emp-name="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>"
                                        data-emp-id-number="<?= htmlspecialchars($employee['id_number']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                        <div class="profile-card" 
                            data-search="<?= strtolower(htmlspecialchars($employee['first_name'].' '.$employee['last_name'].' '.$employee['id_number'].' '.$employee['position_name'].' '.$employee['office_name'])) ?>"
                            data-position="<?= $employee['position_id'] ?>"
                            data-office="<?= $employee['office_id'] ?>"
                            data-section="<?= $employee['section_id'] ?>"
                            data-employment-status="<?= $employee['employment_status_id'] ?>"
                            data-appointment-status="<?= $employee['appointment_status_id'] ?>"
                            data-last-name="<?= htmlspecialchars(strtolower($employee['last_name'])) ?>">
                            <div class="profile-header">
                                <?php 
                                $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                                if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                                    <img src="<?= $imagePath ?>" class="profile-avatarr" 
                                        alt="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>">
                                <?php else: ?>
                                        <img class="profile-avatarr d-flex align-items-center justify-content-center" src="../dist/img/nialogo.png">
                                <?php endif; ?>
                            </div>
                            <div class="profile-body">
                                <h5 class="profile-named"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
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
                                <button type="button" class="btn btn-sm btn-danger delete-employee" 
                                        data-emp-id="<?= $employee['emp_id'] ?>" 
                                        data-emp-name="<?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>"
                                        data-emp-id-number="<?= htmlspecialchars($employee['id_number']) ?>"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
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
        order: [[2, 'asc']], // Sort by Name column (index 2) ascending
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
        
        // Sort grid cards alphabetically by last name when switching to grid view
        sortGridCardsAlphabetically();
        
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
// Delete employee function with SweetAlert and Employee ID validation
function deleteEmployee(empId, empName, empIdNumber) {
    Swal.fire({
        title: 'Confirm Deletion',
        html: `<strong>You are about to delete employee:</strong><br>${empName}<br><br>
               <span class="text-danger">This action cannot be undone!</span>
               <div class="mt-3">
                   <label for="confirmEmpId" class="form-label text-left d-block">
                       <small>Please enter the Employee ID to confirm deletion:</small>
                   </label>
                   <div class="input-group">
                       <input type="password" id="confirmEmpId" class="form-control" 
                              placeholder="Enter Employee ID" 
                              style="border: 1px solid #ddd; border-radius: 4px 0 0 4px; padding: 8px;">
                       <div class="input-group-append">
                           <button type="button" id="toggleIdVisibility" class="btn btn-outline-secondary" 
                                   style="border: 1px solid #ddd; border-left: none; border-radius: 0 4px 4px 0;">
                               <i class="fas fa-eye"></i>
                           </button>
                       </div>
                   </div>
                   <div id="empIdError" class="text-danger small mt-1" style="display: none;">
                       Employee ID does not match!
                   </div>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete Employee',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary',
            popup: 'swal2-popup-custom'
        },
        buttonsStyling: false,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const enteredId = document.getElementById('confirmEmpId').value.trim();
            const errorDiv = document.getElementById('empIdError');
            
            if (!enteredId) {
                errorDiv.textContent = 'Please enter the Employee ID';
                errorDiv.style.display = 'block';
                return false;
            }
            
            // Compare with the actual Employee ID number (id_number from database)
            if (enteredId !== empIdNumber.toString()) {
                errorDiv.textContent = 'Employee ID does not match! Please try again.';
                errorDiv.style.display = 'block';
                return false;
            }
            
            errorDiv.style.display = 'none';
            return true;
        },
        didOpen: () => {
            // Focus on the input field when modal opens
            const input = document.getElementById('confirmEmpId');
            input.focus();
            
            // Toggle password visibility
            const toggleBtn = document.getElementById('toggleIdVisibility');
            toggleBtn.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Show ID number toggle
            const showIdBtn = document.getElementById('showIdNumber');
            const idDisplay = document.getElementById('idNumberDisplay');
            showIdBtn.addEventListener('click', function() {
                if (idDisplay.style.display === 'none') {
                    idDisplay.style.display = 'inline';
                    this.innerHTML = '<i class="fas fa-times"></i> Hide ID Number';
                    this.classList.remove('btn-outline-info');
                    this.classList.add('btn-outline-warning');
                } else {
                    idDisplay.style.display = 'none';
                    this.innerHTML = '<i class="fas fa-question-circle"></i> Show ID Number';
                    this.classList.remove('btn-outline-warning');
                    this.classList.add('btn-outline-info');
                }
            });
            
            // Add real-time validation
            input.addEventListener('input', function() {
                const errorDiv = document.getElementById('empIdError');
                const enteredId = this.value.trim();
                
                if (enteredId && enteredId === empIdNumber.toString()) {
                    errorDiv.style.display = 'none';
                    input.style.borderColor = '#28a745';
                } else if (enteredId) {
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#ddd';
                    errorDiv.style.display = 'none';
                }
            });
            
            // Allow Enter key to confirm if ID matches
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const confirmButton = Swal.getConfirmButton();
                    if (this.value.trim() === empIdNumber.toString()) {
                        confirmButton.click();
                    }
                }
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Deleting Employee...',
                html: 'Please wait while we remove the employee record',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Perform AJAX delete request
            $.ajax({
                url: 'emp.delete.php',
                type: 'POST',
                data: {
                    emp_id: empId
                },
                dataType: 'json',
                success: function(response) {
                    if (response && typeof response === 'object') {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                confirmButtonColor: '#3085d6',
                                timer: 3000,
                                showConfirmButton: true,
                                showClass: {
                                    popup: 'animate__animated animate__fadeInDown'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__fadeOutUp'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Delete Failed!',
                                text: 'Failed to delete employee: ' + response.message,
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    } else {
                        console.error('Invalid JSON response:', response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Invalid response from server. Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    let errorMessage = 'Failed to delete employee. ';
                    
                    if (xhr.status === 0) {
                        errorMessage += 'Network error. Please check your connection.';
                    } else if (xhr.status === 500) {
                        errorMessage += 'Server error. Please try again later.';
                    } else {
                        errorMessage += 'Please try again.';
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: errorMessage,
                        confirmButtonColor: '#3085d6'
                    });
                }
            });
        }
    });
}

// Add event listeners for delete buttons
$(document).on('click', '.delete-employee', function() {
    const empId = $(this).data('emp-id');
    const empName = $(this).data('emp-name');
    const empIdNumber = $(this).data('emp-id-number');
    deleteEmployee(empId, empName, empIdNumber);
});
// Advanced Search Functionality
$(document).ready(function() {
    let activeFilters = {};
    let filterCount = 0;

    // Open advanced search modal
    $('#advancedSearchBtn').click(function() {
        $('#advancedSearchModal').addClass('show');
        $('#advancedSearchBackdrop').addClass('show');
        $('body').css('overflow', 'hidden');
    });

    // Close advanced search modal
    $('#advancedSearchClose, #advancedSearchBackdrop').click(function() {
        $('#advancedSearchModal').removeClass('show');
        $('#advancedSearchBackdrop').removeClass('show');
        $('body').css('overflow', '');
    });

    // Clear all filters
    $('#clearSearchFilters').click(function() {
        // Clear all form fields
        $('#advancedSearchModal .search-form-control').val('');
        $('#advancedSearchModal select').val('');
        
        // Reset active filters
        activeFilters = {};
        filterCount = 0;
        updateFilterBadge();
        
        // Reset both table and grid views
        resetSearch();
        
        // Close modal
        $('#advancedSearchModal').removeClass('show');
        $('#advancedSearchBackdrop').removeClass('show');
        $('body').css('overflow', '');
    });

    // Apply filters
    $('#applySearchFilters').click(function() {
        // Collect filter values
        activeFilters = {
            name: $('#searchName').val().trim().toLowerCase(),
            idNumber: $('#searchIdNumber').val().trim().toLowerCase(),
            email: $('#searchEmail').val().trim().toLowerCase(),
            phone: $('#searchPhone').val().trim().toLowerCase(),
            position: $('#searchPosition').val(),
            office: $('#searchOffice').val(),
            section: $('#searchSection').val(),
            employmentStatus: $('#searchEmploymentStatus').val(),
            appointmentStatus: $('#searchAppointmentStatus').val(),
            dateFrom: $('#searchDateFrom').val(),
            dateTo: $('#searchDateTo').val()
        };

        // Count active filters
        filterCount = Object.values(activeFilters).filter(value => 
            value !== '' && value !== null && value !== undefined
        ).length;

        updateFilterBadge();
        applyAdvancedFilters();
        
        // Close modal
        $('#advancedSearchModal').removeClass('show');
        $('#advancedSearchBackdrop').removeClass('show');
        $('body').css('overflow', '');
    });

    // Update filter badge
    function updateFilterBadge() {
        const badge = $('#activeFilterCount');
        if (filterCount > 0) {
            badge.text(filterCount).show();
        } else {
            badge.hide();
        }
    }

    // Apply advanced filters to both table and grid views
    function applyAdvancedFilters() {
        if ($('#tableViewBtn').hasClass('active')) {
            // Filter table view
            filterTableView();
        } else {
            // Filter grid view
            filterGridView();
        }

        // Show/hide no results message
        showNoResultsIfNeeded();
    }

    // Filter table view 
    function filterTableView() {
        // Clear all searches first
        employeeTable.search('');
        employeeTable.columns().search('');
        
        if (filterCount === 0) {
            employeeTable.draw();
            return;
        }

        // Apply individual column filters for text searches
        if (activeFilters.name) {
            employeeTable.column(2).search(activeFilters.name, true, false);
        }
        if (activeFilters.idNumber) {
            employeeTable.column(0).search(activeFilters.idNumber, true, false);
        }
        if (activeFilters.email) {
            employeeTable.column(3).search(activeFilters.email, true, false);
        }
        if (activeFilters.phone) {
            employeeTable.column(4).search(activeFilters.phone, true, false);
        }

        // For status filters, use custom filtering
        if (activeFilters.employmentStatus || activeFilters.appointmentStatus) {
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const row = employeeTable.row(dataIndex).node();
                    if (!row) return true;

                    let employmentMatch = true;
                    let appointmentMatch = true;

                    // Check employment status
                    if (activeFilters.employmentStatus) {
                        const rowEmploymentStatus = $(row).data('employment-status');
                        employmentMatch = rowEmploymentStatus && rowEmploymentStatus.toString() === activeFilters.employmentStatus.toString();
                    }

                    // Check appointment status
                    if (activeFilters.appointmentStatus) {
                        const rowAppointmentStatus = $(row).data('appointment-status');
                        appointmentMatch = rowAppointmentStatus && rowAppointmentStatus.toString() === activeFilters.appointmentStatus.toString();
                    }

                    return employmentMatch && appointmentMatch;
                }
            );
        }

        employeeTable.draw();
        
        // Remove the custom filter function after drawing
        if (activeFilters.employmentStatus || activeFilters.appointmentStatus) {
            $.fn.dataTable.ext.search.pop();
        }
    }

    function sortVisibleGridCardsAlphabetically() {
        const $visibleCards = $('.profile-card:visible');
        
        $visibleCards.sort(function(a, b) {
            const aLastName = $(a).data('last-name') || '';
            const bLastName = $(b).data('last-name') || '';
            return aLastName.localeCompare(bLastName);
        });
        
        // Re-append visible cards in sorted order
        $('.profile-grid').append($visibleCards);
        
        // Hide all cards again (they'll be shown in updateGridPagination)
        $('.profile-card').hide();
    }

    // Filter grid view
    function filterGridView() {
        let visibleCount = 0;
        
        $('.profile-card').each(function() {
            const card = $(this);
            const matches = checkCardMatchesFilters(card);
            
            if (matches) {
                card.show();
                visibleCount++;
            } else {
                card.hide();
            }
        });

        // Sort visible cards alphabetically by last name
        sortVisibleGridCardsAlphabetically();
        
        // Update grid pagination with filtered results
        updateGridWithFilteredResults();
        
        return visibleCount;
    }

    // Check if card matches all active filters
    function checkCardMatchesFilters(card) {
        const cardData = card.data('search').toLowerCase();
        
        // Basic text search in card data
        if (activeFilters.name && !cardData.includes(activeFilters.name)) {
            return false;
        }
        if (activeFilters.idNumber && !cardData.includes(activeFilters.idNumber)) {
            return false;
        }
        if (activeFilters.email && !cardData.includes(activeFilters.email)) {
            return false;
        }
        if (activeFilters.phone && !cardData.includes(activeFilters.phone)) {
            return false;
        }

        // Position filter
        if (activeFilters.position) {
            const cardPosition = card.data('position');
            if (!cardPosition || cardPosition.toString() !== activeFilters.position.toString()) {
                return false;
            }
        }

        // Office filter
        if (activeFilters.office) {
            const cardOffice = card.data('office');
            if (!cardOffice || cardOffice.toString() !== activeFilters.office.toString()) {
                return false;
            }
        }

        // Section filter
        if (activeFilters.section) {
            const cardSection = card.data('section');
            if (!cardSection || cardSection.toString() !== activeFilters.section.toString()) {
                return false;
            }
        }

        // Employment Status filter
        if (activeFilters.employmentStatus) {
            const cardEmploymentStatus = card.data('employment-status');
            if (!cardEmploymentStatus || cardEmploymentStatus.toString() !== activeFilters.employmentStatus.toString()) {
                return false;
            }
        }

        // Appointment Status filter
        if (activeFilters.appointmentStatus) {
            const cardAppointmentStatus = card.data('appointment-status');
            if (!cardAppointmentStatus || cardAppointmentStatus.toString() !== activeFilters.appointmentStatus.toString()) {
                return false;
            }
        }

        // Date range filter (you'll need to add date data attributes to cards)
        if (activeFilters.dateFrom || activeFilters.dateTo) {
            // Implement date filtering if you have date data in your cards
            // const cardDate = card.data('date');
            // if (cardDate) {
            //     const cardDateObj = new Date(cardDate);
            //     if (activeFilters.dateFrom && cardDateObj < new Date(activeFilters.dateFrom)) {
            //         return false;
            //     }
            //     if (activeFilters.dateTo && cardDateObj > new Date(activeFilters.dateTo)) {
            //         return false;
            //     }
            // }
        }

        return true;
    }

    // Update grid pagination with filtered results
    function updateGridWithFilteredResults() {
        filteredEmployees = [];
        
        $('.profile-card').each(function(index) {
            if ($(this).is(':visible')) {
                filteredEmployees.push(index);
            }
        });
        
        currentGridPage = 1;
        updateGridPagination();
        
        // Show no results message if needed
        if (filteredEmployees.length === 0 && filterCount > 0) {
            $('.no-results').remove();
            $('.profile-grid').append('<div class="no-results">No employees match your search criteria</div>');
        } else {
            $('.no-results').remove();
        }
    }

    // Reset search
    function resetSearch() {
        if ($('#tableViewBtn').hasClass('active')) {
            employeeTable.search('').columns().search('').draw();
            // Remove any custom filters
            $.fn.dataTable.ext.search = [];
        } else {
            filteredEmployees = Array.from({length: $('.profile-card').length}, (_, i) => i);
            currentGridPage = 1;
            updateGridPagination();
            $('.profile-card').show();
        }
        
        $('.no-results').remove();
    }

    // Show no results message if needed
    function showNoResultsIfNeeded() {
        $('.no-results').remove();
        
        const visibleCount = $('#tableViewBtn').hasClass('active') ? 
            employeeTable.rows({ filter: 'applied' }).count() : 
            $('.profile-card:visible').length;
            
        if (visibleCount === 0 && filterCount > 0) {
            const noResultsHtml = '<div class="no-results">No employees match your search criteria</div>';
            
            if ($('#tableViewBtn').hasClass('active')) {
                $('#employeeTable_wrapper').append(noResultsHtml);
            } else {
                $('.profile-grid').append(noResultsHtml);
            }
        }
    }

    // Prevent modal close when clicking inside modal
    $('#advancedSearchModal').click(function(e) {
        e.stopPropagation();
    });

    // Close modal with Escape key
    $(document).keyup(function(e) {
        if (e.keyCode === 27 && $('#advancedSearchModal').hasClass('show')) {
            $('#advancedSearchModal').removeClass('show');
            $('#advancedSearchBackdrop').removeClass('show');
            $('body').css('overflow', '');
        }
    });
});
// Function to sort grid cards by last name
function sortGridCardsAlphabetically() {
    const $cards = $('.profile-card');
    
    $cards.sort(function(a, b) {
        const aLastName = $(a).data('last-name') || '';
        const bLastName = $(b).data('last-name') || '';
        return aLastName.localeCompare(bLastName);
    });
    
    // Re-append cards in sorted order
    $('.profile-grid').append($cards);
}

// Initialize grid view as default
function initializeGridAsDefault() {
    // Set grid as active view
    $('#gridViewBtn').addClass('active');
    $('#tableViewBtn').removeClass('active');
    
    // Sort grid cards alphabetically by last name
    sortGridCardsAlphabetically();
    
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
</script>
</body>
</html>