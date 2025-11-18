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
        // Redirect to maintenance page or show message
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
          LIMIT 6"; // Limit to 6 for dashboard display
          
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
          WHERE employment_status_id = 1"; // Assuming 1 is the ID for active status
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$active_employees = $result->fetch_assoc()['active_count'];

// Add this to your dashboard.php or profile.php after login
function isUsingTemporaryPassword($emp_id, $db) {
    $query = "SELECT u.password, e.id_number 
              FROM users u 
              JOIN employee e ON u.employee_id = e.emp_id 
              WHERE u.employee_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        // Check if password matches the employee number (temporary password)
        return password_verify($result['id_number'], $result['password']);
    }
    return false;
}

// Usage in your dashboard or profile:
if (isUsingTemporaryPassword($_SESSION['emp_id'], $db)) {
    $_SESSION['toast'] = [
        'type' => 'warning',
        'message' => 'You are using a temporary password. Please change your password for security.'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Organization Dashboard</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/dashboard.css">
  <!-- Chart.js -->
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
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Organization Dashboard</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item active">Dashboard</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
          <div class="col-md-3 col-sm-6">
            <?php
            $total_employees = 0;
            foreach ($gender_data as $section) {
                $total_employees += $section['total_count'];
            }
            ?>
            <div class="stats-card">
              <div class="stats-value"><?= $total_employees ?></div>
              <div class="stats-label">Total Employees</div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="stats-card">
              <div class="stats-value"><?= $active_employees ?></div>
              <div class="stats-label">Active Employees</div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="stats-card">
              <div class="stats-value"><?= count($sections) ?></div>
              <div class="stats-label">Total Sections</div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="stats-card">
              <div class="stats-value"><?= count($unit_sections) ?></div>
              <div class="stats-label">Total Units</div>
            </div>
          </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
          <!-- Appointment Status Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Appointment Status Distribution</h3>
              </div>
              <div class="card-body">
                <canvas id="appointmentChart" height="250"></canvas>
              </div>
            </div>
          </div>
          
          <!-- Gender Distribution Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Gender Distribution by Section</h3>
              </div>
              <div class="card-body">
                <canvas id="genderChart" height="250"></canvas>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <!-- Main Content Area -->
          <div class="col-md-12">
            <!-- Organization Structure -->
            <div class="org-structure">
              <!-- Manager Section -->
              <div class="org-manager">
                <div class="manager-section" data-toggle="modal" data-target="#managerStaffModal" style="cursor: pointer;">
                  <div class="manager-info">
                    <?php if ($manager): ?>
                      <?php if (!empty($manager['picture']) && file_exists("../dist/img/employees/" . $manager['picture'])): ?>
                        <img src="../dist/img/employees/<?= htmlspecialchars($manager['picture']) ?>" 
                             class="manager-avatar" 
                             alt="<?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?>">
                      <?php else: ?>
                        <div class="default-manager-avatar">
                          <i class="fas fa-user-tie"></i>
                        </div>
                      <?php endif; ?>
                      <div class="manager-details">
                        <div class="manager-name">Engr. <?= htmlspecialchars($manager['first_name'] . 'G. ' . $manager['last_name']) .' ,MPA'?></div>
                        <div class="manager-title">
                          <h5>ACTING DIVISION MANAGER</h5>
                        </div>
                        <div class="manager-contact">
                          <i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($manager['email']) ?>
                          <br>
                          <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($manager['phone_number']) ?>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="text-center w-100">
                        <i class="fas fa-user-tie fa-2x mb-2"></i>
                        <div>No Manager Assigned</div>
                        <small>Set an employee as manager in the employee management system</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Sections -->
              <h4 class="mb-3"><i class="fas fa-sitemap mr-2"></i>SECTIONS</h4>
              
              <?php if (empty($sections)): ?>
                <div class="empty-state">
                  <i class="fas fa-sitemap"></i>
                  <h5>No Sections Found</h5>
                  <p>There are no sections configured in the system yet.</p>
                </div>
              <?php else: ?>
                <div class="org-sections">
                  <?php foreach ($sections as $section): ?>
                    <div class="section-container">
                      <div class="section-card section-card-container">
                        <!-- Section Header -->
                        <div class="section-header">
                          <div class="section-title">
                            <?= htmlspecialchars($section['section_name']) ?>
                            <span class="section-badge">
                              <?= $section['unit_count'] ?> units
                            </span>
                          </div>
                          
                          <!-- Section Head Information -->
                          <?php if ($section['head_emp_id']): ?>
                            <div class="section-head-info">
                              <?php if (!empty($section['head_picture']) && file_exists("../dist/img/employees/" . $section['head_picture'])): ?>
                                <img src="../dist/img/employees/<?= htmlspecialchars($section['head_picture']) ?>" 
                                    class="section-head-avatar" 
                                    alt="<?= htmlspecialchars($section['head_name']) ?>">
                              <?php else: ?>
                                <div class="default-section-head-avatar">
                                  <i class="fas fa-user"></i>
                                </div>
                              <?php endif; ?>
                              
                              <div class="section-head-details">
                                <div class="section-head-name">
                                  <i class="fas fa-user-shield mr-1"></i>
                                  <?= htmlspecialchars($section['head_name']) ?>
                                </div>
                                <div class="section-head-role">Section Head</div>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Unit Buttons -->
                        <div class="unit-buttons-container">
                          <?php 
                          // Get unit sections for this section
                          $section_units = array_filter($unit_sections, function($unit) use ($section) {
                              return $unit['section_id'] == $section['section_id'];
                          });
                          ?>
                          
                          <?php if (!empty($section_units)): ?>
                            <?php foreach ($section_units as $unit): ?>
                              <?php
                              // Count employees per unit
                              $stmt = $db->prepare("SELECT COUNT(*) as emp_count FROM employee WHERE unit_section_id = ?");
                              $stmt->bind_param("i", $unit['unit_id']);
                              $stmt->execute();
                              $count_result = $stmt->get_result();
                              $emp_count = $count_result->fetch_assoc()['emp_count'];
                              ?>
                              
                              <button type="button" class="unit-button" 
                                      data-toggle="modal" 
                                      data-target="#unitEmployeesModal<?= $unit['unit_id'] ?>">
                                <div class="unit-button-title">
                                  <?= htmlspecialchars($unit['unit_name']) ?>
                                </div>
                                <div class="unit-button-count">
                                  <i class="fas fa-users mr-1"></i>
                                  <?= $emp_count ?> employees
                                </div>
                              </button>
                              
                            <!-- Modal for viewing all employees in unit -->
                            <div class="modal fade" id="unitEmployeesModal<?= $unit['unit_id'] ?>">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">
                                                <i class="fas fa-users mr-2"></i>
                                                Employees in <?= htmlspecialchars($unit['unit_name']) ?>
                                            </h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Unit Head Information -->
                                            <?php if ($unit['head_emp_id']): ?>
                                                <div class="unit-head-info">
                                                    <div class="unit-head-content">
                                                        <?php if (!empty($unit['head_picture']) && file_exists("../dist/img/employees/" . $unit['head_picture'])): ?>
                                                            <img src="../dist/img/employees/<?= htmlspecialchars($unit['head_picture']) ?>" 
                                                                class="unit-head-avatar" 
                                                                alt="<?= htmlspecialchars($unit['head_name']) ?>">
                                                        <?php else: ?>
                                                            <div class="default-unit-head-avatar">
                                                                <i class="fas fa-user-shield"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="unit-head-details">
                                                            <div class="unit-head-name">
                                                                <i class="fas fa-circle mr-1"></i>
                                                                <?= htmlspecialchars($unit['head_name']) ?>
                                                            </div>
                                                            <div class="unit-head-role">Unit Head</div>
                                                            <div class="unit-head-section">
                                                                <?= htmlspecialchars($unit['section_name']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Employees List -->
                                            <div class="modal-employee-list">
                                                <?php
                                                // Get all employees for this unit
                                                $stmt_all = $db->prepare("SELECT emp_id, first_name, last_name, picture, id_number, position_id 
                                                                        FROM employee 
                                                                        WHERE unit_section_id = ? 
                                                                        ORDER BY first_name, last_name");
                                                $stmt_all->bind_param("i", $unit['unit_id']);
                                                $stmt_all->execute();
                                                $all_emp_result = $stmt_all->get_result();
                                                $all_employees = [];
                                                while ($row = $all_emp_result->fetch_assoc()) {
                                                    $all_employees[] = $row;
                                                }
                                                ?>
                                                
                                                <?php if (!empty($all_employees)): ?>
                                                    <div class="list-group">
                                                        <?php foreach ($all_employees as $emp): ?>
                                                            <div class="employee-item">
                                                                <a href="emp.profile.php?emp_id=<?= $emp['emp_id'] ?>" class="employee-link">
                                                                    <?php if (!empty($emp['picture']) && file_exists("../dist/img/employees/" . $emp['picture'])): ?>
                                                                        <img src="../dist/img/employees/<?= htmlspecialchars($emp['picture']) ?>" 
                                                                            class="avatar" 
                                                                            alt="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                                                                    <?php else: ?>
                                                                        <div class="default-avatar">
                                                                            <i class="fas fa-user"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <div class="employee-info">
                                                                        <div class="employee-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                                                        <div class="employee-id">ID: <?= $emp['id_number'] ?></div>
                                                                        <div class="employee-id">Position: <?= $emp['position_id'] ?></div>
                                                                    </div>
                                                                    <i class="fas fa-chevron-right text-muted ml-2"></i>
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="modal-empty-state">
                                                        <i class="fas fa-users-slash"></i>
                                                        <h5>No Employees Found</h5>
                                                        <p class="text-muted">There are no employees assigned to this unit yet.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                <i class="fas fa-times mr-1"></i> Close
                                            </button>
                                            <?php if (!empty($all_employees)): ?>
                                                <small class="text-muted mr-auto">
                                                    Total: <?= count($all_employees) ?> employee<?= count($all_employees) !== 1 ? 's' : '' ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="empty-state py-4">
                              <i class="fas fa-sitemap"></i>
                              <h5>No Unit Sections</h5>
                              <p>This section doesn't have any unit sections yet.</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Manager's Staff Modal -->
  <div class="modal fade" id="managerStaffModal">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">
            <i class="fas fa-users mr-2"></i>
            Manager's Office Staff
          </h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <?php if (!empty($manager_staff)): ?>
            <div class="row">
              <?php foreach ($manager_staff as $staff): ?>
                <div class="col-md-6 mb-3">
                  <div class="staff-card">
                    <div class="staff-info">
                      <?php if (!empty($staff['employee_picture']) && file_exists("../dist/img/employees/" . $staff['employee_picture'])): ?>
                        <img src="../dist/img/employees/<?= htmlspecialchars($staff['employee_picture']) ?>" 
                            class="staff-avatar" 
                            alt="<?= htmlspecialchars($staff['employee_name']) ?>">
                      <?php else: ?>
                        <div class="default-staff-avatar">
                          <i class="fas fa-user"></i>
                        </div>
                      <?php endif; ?>
                      
                      <div class="staff-details">
                        <div class="staff-name"><?= htmlspecialchars($staff['employee_name']) ?></div>
                        <div class="staff-position"><?= htmlspecialchars($staff['employee_position']) ?></div>
                        <div class="staff-office"><?= htmlspecialchars($staff['employee_office']) ?></div>
                      </div>
                    </div>
                    <div class="staff-responsibilities">
                      <strong>Responsibilities:</strong> <?= htmlspecialchars($staff['responsibilities']) ?>
                    </div>
                    <div class="staff-contact mt-2">
                      <small class="text-muted">
                        <i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($staff['employee_email']) ?>
                        <br>
                      </small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-users-slash"></i>
              <h5>No Staff Members</h5>
              <p>There are no staff members assigned to the manager's office yet.</p>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Close
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
// Initialize charts when the page loads
document.addEventListener('DOMContentLoaded', function() {
  // Appointment Status Chart
  const appointmentCtx = document.getElementById('appointmentChart').getContext('2d');
  const appointmentChart = new Chart(appointmentCtx, {
    type: 'doughnut',
    data: {
      labels: [
        <?php foreach($appointment_data as $data): ?>
          '<?= $data['status_name'] ?>',
        <?php endforeach; ?>
      ],
      datasets: [{
        data: [
          <?php foreach($appointment_data as $data): ?>
            <?= $data['count'] ?>,
          <?php endforeach; ?>
        ],
        backgroundColor: [
          <?php foreach($appointment_data as $data): ?>
            '<?= $data['color'] ?>',
          <?php endforeach; ?>
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.label || '';
              if (label) {
                label += ': ';
              }
              label += context.raw + ' employees';
              return label;
            }
          }
        }
      }
    }
  });

  // Gender Distribution Chart
  const genderCtx = document.getElementById('genderChart').getContext('2d');
  const genderChart = new Chart(genderCtx, {
    type: 'bar',
    data: {
      labels: [
        <?php foreach($gender_data as $data): ?>
          '<?= $data['section_name'] ?>',
        <?php endforeach; ?>
      ],
      datasets: [
        {
          label: 'Male',
          data: [
            <?php foreach($gender_data as $data): ?>
              <?= $data['male_count'] ?>,
            <?php endforeach; ?>
          ],
          backgroundColor: '#3498db',
          borderColor: '#2980b9',
          borderWidth: 1
        },
        {
          label: 'Female',
          data: [
            <?php foreach($gender_data as $data): ?>
              <?= $data['female_count'] ?>,
            <?php endforeach; ?>
          ],
          backgroundColor: '#e83e8c',
          borderColor: '#d81b60',
          borderWidth: 1
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          stacked: false,
        },
        y: {
          stacked: false,
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            afterLabel: function(context) {
              const dataset = context.dataset;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = Math.round((context.raw / total) * 100);
              return `Percentage: ${percentage}%`;
            }
          }
        }
      }
    }
  });
});
</script>
</body>
</html>