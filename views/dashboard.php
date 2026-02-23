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

$module_name = 'Admin Dashboard';
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

// Get user role information
$stmt = $db->prepare("
    SELECT u.id, u.user, r.name as role_name, r.id as role_id 
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$role_id = $user['role_id'];
$role_name = $user['role_name'];

// Fetch all sections with their unit sections and heads
$query = "SELECT s.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture,
                 (SELECT COUNT(*) FROM unit_section WHERE section_id = s.section_id) as unit_count
          FROM section s
          LEFT JOIN employee e ON s.head_emp_id = e.emp_id
          ORDER BY s.section_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

// Fetch all unit sections with their heads
$query = "SELECT us.*, 
                 s.section_name,
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture
          FROM unit_section us
          LEFT JOIN section s ON us.section_id = s.section_id
          LEFT JOIN employee e ON us.head_emp_id = e.emp_id
          ORDER BY us.unit_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$unit_sections = [];
while ($row = $result->fetch_assoc()) {
    $unit_sections[] = $row;
}

// Fetch Manager from employee table using is_manager field
$query = "SELECT e.*, 
                 p.position_name,
                 o.office_name
          FROM employee e
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          WHERE e.is_manager = 1
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager = $result->fetch_assoc();

// Fetch Manager's Office Staff
$query = "SELECT mos.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                 e.picture as employee_picture,
                 e.email as employee_email,
                 e.phone_number as employee_phone,
                 p.position_name as employee_position,
                 o.office_name as employee_office
          FROM managers_office_staff mos
          JOIN employee e ON mos.emp_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          ORDER BY mos.position
          LIMIT 6";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager_staff = [];
while ($row = $result->fetch_assoc()) {
    $manager_staff[] = $row;
}

// Fetch data for appointment status chart
$query = "SELECT a.status_name, a.color, COUNT(e.emp_id) as count
          FROM appointment_status a
          LEFT JOIN employee e ON a.appointment_id = e.appointment_status_id
          GROUP BY a.appointment_id, a.status_name, a.color
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$appointment_data = [];
while ($row = $result->fetch_assoc()) {
    $appointment_data[] = $row;
}

// Fetch data for gender distribution by section
$query = "SELECT s.section_name, 
                 SUM(CASE WHEN e.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                 SUM(CASE WHEN e.gender = 'Female' THEN 1 ELSE 0 END) as female_count,
                 COUNT(e.emp_id) as total_count
          FROM section s
          LEFT JOIN employee e ON s.section_id = e.section_id
          GROUP BY s.section_id, s.section_name
          ORDER BY s.section_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$gender_data = [];
while ($row = $result->fetch_assoc()) {
    $gender_data[] = $row;
}

// Fetch count of active employees
$query = "SELECT COUNT(*) as active_count 
          FROM employee 
          WHERE employment_status_id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$active_employees = $result->fetch_assoc()['active_count'];

function isUsingTemporaryPassword($emp_id, $db)
{
    $query = "SELECT u.password, e.id_number 
              FROM users u 
              JOIN employee e ON u.employee_id = e.emp_id 
              WHERE u.employee_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        return password_verify($result['id_number'], $result['password']);
    }
    return false;
}

if (isset($_SESSION['emp_id']) && isUsingTemporaryPassword($_SESSION['emp_id'], $db)) {
    $_SESSION['toast'] = [
        'type' => 'warning',
        'message' => 'You are using a temporary password. Please change your password for security.'
    ];
}

// Get base URL for correct path handling
$uploads_url = '../dist/img/employees/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 3 | Organization Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .employee-card .employee-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .employee-card .employee-position {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .employee-card {
            min-height: 180px;
        }
        .image-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-circle-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-circle-xs {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .list-group-item.active .bg-primary {
            background-color: #fff !important;
            color: #007bff !important;
        }
        .section-unit-panel {
            transition: all 0.3s ease;
        }
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(0,0,0,.1);
            border-left-color: #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Organization Dashboard</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="container-fluid">
                    <!-- Info boxes -->
                    <div class="row">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Employees</span>
                                    <span class="info-box-number"><?php echo $active_employees; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="info-box mb-3">
                                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-building"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Sections</span>
                                    <span class="info-box-number"><?php echo count($sections); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="info-box mb-3">
                                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-code-branch"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Units</span>
                                    <span class="info-box-number"><?php echo count($unit_sections); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="info-box mb-3">
                                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-tie"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Manager's Staff</span>
                                    <span class="info-box-number"><?php echo count($manager_staff); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Directory Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Employee Directory</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="employeeSelector">
                                        <div class="row">
                                            <!-- Sections Column -->
                                            <div class="col-md-3">
                                                <div class="card">
                                                    <div class="card-header bg-info">
                                                        <h3 class="card-title">Departments</h3>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;" id="sectionsList">
                                                            <?php foreach ($sections as $index => $section): ?>
                                                            <a href="#" class="list-group-item list-group-item-action <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                               data-section-id="<?php echo $section['section_id']; ?>"
                                                               data-section-name="<?php echo htmlspecialchars($section['section_name']); ?>"
                                                               data-head-name="<?php echo htmlspecialchars($section['head_name'] ?? ''); ?>"
                                                               data-head-picture="<?php echo htmlspecialchars($section['head_picture'] ?? ''); ?>"
                                                               data-unit-count="<?php echo $section['unit_count']; ?>"
                                                               onclick="selectSection(this, <?php echo $section['section_id']; ?>)">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-1"><?php echo htmlspecialchars($section['section_name']); ?></h6>
                                                                        <small><?php echo $section['unit_count']; ?> units</small>
                                                                    </div>
                                                                    <div class="ml-2">
                                                                        <!-- In the units panel section, update the image display -->
<div class="image-circle-xs">
    <?php if (!empty($section['head_picture'])): ?>
        <img src="<?php echo $uploads_url . htmlspecialchars($section['head_picture']); ?>" 
             class="img-fluid img-circle" 
             alt="<?php echo htmlspecialchars($section['head_name'] ?? ''); ?>"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
        <div class="bg-primary text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle" style="display: none;">
            <img src="../dist/img/nialogo.png" alt="NIA Logo" style="width: 20px; height: 20px; object-fit: contain;">
        </div>
    <?php else: ?>
        <div class="bg-primary text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle">
            <img src="../dist/img/nialogo.png" alt="NIA Logo" style="width: 20px; height: 20px; object-fit: contain;">
        </div>
    <?php endif; ?>
</div>
                                                                    </div>
                                                                </div>
                                                            </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Units Panel (shows when section has units) -->
                                                <div class="card mt-3 section-unit-panel" id="unitsPanel" style="display: none;">
                                                    <div class="card-header bg-success">
                                                        <h3 class="card-title">Units in <span id="selectedSectionName"></span></h3>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <div class="list-group list-group-flush" id="unitsList">
                                                            <a href="#" class="list-group-item list-group-item-action list-group-item-info" 
                                                               onclick="showAllEmployeesInSection()">
                                                                <i class="fas fa-users mr-2"></i> Show All Employees in Section
                                                            </a>
                                                            <!-- Units will be loaded here dynamically -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Employees Column -->
                                            <div class="col-md-9">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                                            <div>
                                                                <h3 class="card-title">
                                                                    <span id="selectedDepartment">Select Department</span>
                                                                    <small class="text-muted ml-2" id="selectedUnitInfo"></small>
                                                                </h3>
                                                                <span class="badge badge-success ml-2" id="employeeCount">0 employees</span>
                                                            </div>
                                                            <div class="d-flex mt-2 mt-sm-0">
                                                                <div class="mr-2" style="width: 200px;">
                                                                    <input type="text" class="form-control form-control-sm" 
                                                                           id="searchInput"
                                                                           onkeyup="searchEmployees()" 
                                                                           placeholder="Search employee...">
                                                                </div>
                                                                <div style="width: 150px;">
                                                                    <select class="form-control form-control-sm" 
                                                                            id="statusFilter"
                                                                            onchange="filterEmployees()">
                                                                        <option value="">All Status</option>
                                                                        <option value="1">Active</option>
                                                                        <option value="2">On Leave</option>
                                                                        <option value="3">Inactive</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <!-- Loading Indicator -->
                                                        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
                                                            <div class="spinner-border text-primary mb-3" role="status">
                                                                <span class="sr-only">Loading...</span>
                                                            </div>
                                                            <h5>Loading employees...</h5>
                                                        </div>

                                                        <!-- Employees Grid -->
                                                        <div id="employeesGrid" style="display: none;">
                                                            <div class="row" id="employeesContainer">
                                                                <!-- Employees will be loaded here dynamically -->
                                                            </div>
                                                        </div>

                                                        <!-- No Employees Found -->
                                                        <div id="noEmployees" class="text-center py-5" style="display: none;">
                                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                            <h5>No employees found</h5>
                                                            <p class="text-muted" id="noEmployeesMessage">Please select a department to view employees</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../includes/mainfooter.php'; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>

    <script>
        // Global variables
        let sections = <?php echo json_encode($sections); ?>;
        let unitSections = <?php echo json_encode($unit_sections); ?>;
        let currentSectionId = sections.length > 0 ? sections[0].section_id : null;
        let currentUnitId = null;
        let allEmployees = [];
        let filteredEmployees = [];
        let searchQuery = '';
        let statusFilter = '';
        const uploadsUrl = '<?php echo $uploads_url; ?>';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial selected section
            if (sections.length > 0) {
                updateUnitsPanel(sections[0].section_id);
                loadEmployees(sections[0].section_id, null);
                document.getElementById('selectedDepartment').textContent = sections[0].section_name;
            }
        });

        // Select section
        function selectSection(element, sectionId) {
            // Remove active class from all sections
            document.querySelectorAll('#sectionsList .list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked section
            element.classList.add('active');
            
            // Find section data
            const section = sections.find(s => s.section_id == sectionId);
            if (!section) return;
            
            currentSectionId = sectionId;
            currentUnitId = null;
            
            // Update selected department text
            document.getElementById('selectedDepartment').textContent = section.section_name;
            document.getElementById('selectedUnitInfo').textContent = '';
            
            // Update units panel
            updateUnitsPanel(sectionId);
            
            // Load employees if section has no units
            const unitCount = section.unit_count || 0;
            if (unitCount === 0) {
                loadEmployees(sectionId, null);
            } else {
                // Clear employees display
                document.getElementById('employeesGrid').style.display = 'none';
                document.getElementById('noEmployees').style.display = 'block';
                document.getElementById('noEmployeesMessage').textContent = 'Please select a unit to view employees';
                document.getElementById('employeeCount').textContent = '0 employees';
                allEmployees = [];
                filteredEmployees = [];
            }
        }

        // Update units panel
        function updateUnitsPanel(sectionId) {
            const section = sections.find(s => s.section_id == sectionId);
            if (!section) return;
            
            const unitCount = section.unit_count || 0;
            const unitsPanel = document.getElementById('unitsPanel');
            const unitsList = document.getElementById('unitsList');
            const selectedSectionName = document.getElementById('selectedSectionName');
            
            if (unitCount > 0) {
                // Filter units for this section
                const units = unitSections.filter(u => u.section_id == sectionId);
                
                // Clear existing units (keep the first "Show All" item)
                while (unitsList.children.length > 1) {
                    unitsList.removeChild(unitsList.lastChild);
                }
                
                // Add units to the list
                units.forEach(unit => {
                    const unitItem = document.createElement('a');
                    unitItem.href = '#';
                    unitItem.className = 'list-group-item list-group-item-action';
                    unitItem.setAttribute('data-unit-id', unit.unit_id);
                    unitItem.setAttribute('data-unit-name', unit.unit_name);
                    unitItem.setAttribute('data-head-name', unit.head_name || '');
                    unitItem.setAttribute('data-head-picture', unit.head_picture || '');
                    unitItem.setAttribute('onclick', `selectUnit(this, ${unit.unit_id})`);
                    
                    unitItem.innerHTML = `
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(unit.unit_name)}</h6>
                                <small>${escapeHtml(unit.head_name || 'No Head Assigned')}</small>
                            </div>
                            <div class="ml-2">
                                <div class="image-circle-xs">
                                    ${unit.head_picture ? 
                                        `<img src="${uploadsUrl}${escapeHtml(unit.head_picture)}" 
                                              class="img-fluid img-circle" 
                                              alt="${escapeHtml(unit.head_name || '')}"
                                              onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                         <div class="bg-success text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle" style="display: none;">
                                             <span>${unit.head_name ? unit.head_name.charAt(0) : '?'}</span>
                                         </div>` : 
                                        `<div class="bg-success text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle">
                                             <span>${unit.head_name ? unit.head_name.charAt(0) : '?'}</span>
                                         </div>`
                                    }
                                </div>
                            </div>
                        </div>
                    `;
                    
                    unitsList.appendChild(unitItem);
                });
                
                selectedSectionName.textContent = section.section_name;
                unitsPanel.style.display = 'block';
            } else {
                unitsPanel.style.display = 'none';
            }
        }

        // Select unit
        function selectUnit(element, unitId) {
            // Remove active class from all units
            document.querySelectorAll('#unitsList .list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked unit
            element.classList.add('active');
            
            const unit = unitSections.find(u => u.unit_id == unitId);
            if (!unit) return;
            
            currentUnitId = unitId;
            
            // Update selected department text
            document.getElementById('selectedDepartment').textContent = unit.unit_name;
            document.getElementById('selectedUnitInfo').textContent = `(${unit.section_name})`;
            
            // Load employees for this unit
            loadEmployees(null, unitId);
        }

        // Show all employees in section
        function showAllEmployeesInSection() {
            // Remove active class from all units
            document.querySelectorAll('#unitsList .list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            
            currentUnitId = null;
            
            // Update selected department text
            const section = sections.find(s => s.section_id == currentSectionId);
            if (section) {
                document.getElementById('selectedDepartment').textContent = section.section_name;
            }
            document.getElementById('selectedUnitInfo').textContent = '';
            
            // Load all employees in section
            loadEmployees(currentSectionId, null);
        }

        // Load employees
        async function loadEmployees(sectionId, unitId) {
            // Show loading indicator
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('employeesGrid').style.display = 'none';
            document.getElementById('noEmployees').style.display = 'none';
            
            try {
                const formData = new FormData();
                
                if (sectionId) {
                    formData.append('section_id', sectionId);
                }
                
                if (unitId) {
                    formData.append('unit_id', unitId);
                }

                const response = await fetch('get_employees.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    allEmployees = data.employees || [];
                    filteredEmployees = [...allEmployees];
                    displayEmployees();
                } else {
                    console.error('API error:', data.message);
                    allEmployees = [];
                    filteredEmployees = [];
                    displayEmployees();
                    
                    // Show error toast
                    if (typeof toastr !== 'undefined') {
                        toastr.error(data.message || 'Failed to load employees');
                    }
                }
            } catch (error) {
                console.error('Error loading employees:', error);
                allEmployees = [];
                filteredEmployees = [];
                displayEmployees();
                
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to connect to server');
                }
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // Display employees
        function displayEmployees() {
            const container = document.getElementById('employeesContainer');
            const employeesGrid = document.getElementById('employeesGrid');
            const noEmployees = document.getElementById('noEmployees');
            const employeeCountSpan = document.getElementById('employeeCount');
            
            // Apply search and filter
            applySearchAndFilter();
            
            if (filteredEmployees.length > 0) {
                // Clear container
                container.innerHTML = '';
                
                // Add employee cards
                filteredEmployees.forEach(employee => {
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-sm-6 mb-3';
                    
                    // Determine status badge class
                    let statusClass = '';
                    let statusText = employee.status_name || 'Unknown';
                    
                    if (employee.employment_status_id == 1) {
                        statusClass = 'badge-success';
                    } else if (employee.employment_status_id == 2) {
                        statusClass = 'badge-warning';
                    } else if (employee.employment_status_id == 3) {
                        statusClass = 'badge-danger';
                    }
                    
                    // Get initials for avatar
                    const initials = (employee.first_name ? employee.first_name.charAt(0) : '') + 
                                    (employee.last_name ? employee.last_name.charAt(0) : '');
                    
                    // In the displayEmployees function, update the image section
col.innerHTML = `
    <div class="card employee-card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="image-circle">
                        ${employee.picture ? 
                            `<img src="${uploadsUrl}${escapeHtml(employee.picture)}" 
                                  class="img-fluid img-circle" 
                                  alt="${escapeHtml(employee.first_name + ' ' + employee.last_name)}"
                                  onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                             <div class="bg-primary text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle" style="display: none;">
                                 <img src="../dist/img/nialogo.png" alt="NIA Logo" style="width: 30px; height: 30px; object-fit: contain;">
                             </div>` : 
                            `<div class="bg-primary text-white d-flex align-items-center justify-content-center w-100 h-100 rounded-circle">
                                 <img src="../dist/img/nialogo.png" alt="NIA Logo" style="width: 30px; height: 30px; object-fit: contain;">
                             </div>`
                        }
                    </div>
                </div>
                <div class="flex-grow-1 ml-3">
                    <h5 class="employee-name mb-1">${escapeHtml(employee.first_name + ' ' + employee.last_name)}</h5>
                    <p class="employee-position text-muted mb-1">${escapeHtml(employee.position_name || 'No Position')}</p>
                    <span class="badge ${statusClass}">${escapeHtml(statusText)}</span>
                </div>
            </div>
            <hr class="my-2">
            <div class="row small">
                <div class="col-6">
                    <strong>ID:</strong> <span>${escapeHtml(employee.id_number || '')}</span>
                </div>
                <div class="col-6">
                    <strong>Phone:</strong> <span>${escapeHtml(employee.phone_number || 'N/A')}</span>
                </div>
            </div>
        </div>
    </div>
`;
                    
                    container.appendChild(col);
                });
                
                employeesGrid.style.display = 'block';
                noEmployees.style.display = 'none';
                employeeCountSpan.textContent = `${filteredEmployees.length} employees`;
            } else {
                employeesGrid.style.display = 'none';
                noEmployees.style.display = 'block';
                
                if (currentSectionId || currentUnitId) {
                    document.getElementById('noEmployeesMessage').textContent = 'No employees found in this department';
                } else {
                    document.getElementById('noEmployeesMessage').textContent = 'Please select a department to view employees';
                }
                
                employeeCountSpan.textContent = '0 employees';
            }
        }

        // Apply search and filter
        function applySearchAndFilter() {
            filteredEmployees = allEmployees.filter(employee => {
                // Apply search filter
                if (searchQuery) {
                    const fullName = (employee.first_name + ' ' + employee.last_name).toLowerCase();
                    const idNumber = (employee.id_number || '').toLowerCase();
                    const position = (employee.position_name || '').toLowerCase();
                    const query = searchQuery.toLowerCase();
                    
                    if (!fullName.includes(query) && !idNumber.includes(query) && !position.includes(query)) {
                        return false;
                    }
                }
                
                // Apply status filter
                if (statusFilter && employee.employment_status_id != statusFilter) {
                    return false;
                }
                
                return true;
            });
        }

        // Search employees
        function searchEmployees() {
            searchQuery = document.getElementById('searchInput').value;
            displayEmployees();
        }

        // Filter employees
        function filterEmployees() {
            statusFilter = document.getElementById('statusFilter').value;
            displayEmployees();
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize any toasts from session
        <?php if (isset($_SESSION['toast'])): ?>
        $(document).ready(function() {
            toastr.<?php echo $_SESSION['toast']['type']; ?>('<?php echo $_SESSION['toast']['message']; ?>');
        });
        <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>

</html>