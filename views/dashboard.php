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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Dashboard</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .section-card {
        margin-bottom: 20px;
    }
    .section-title {
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .unit-section-item {
        padding-left: 30px;
        border-left: 3px solid #6c757d;
        margin-bottom: 15px;
    }
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
    }
    .default-avatar {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
    }
    .head-info {
        display: flex;
        align-items: center;
        margin: 10px 0;
    }
    .view-employees-btn {
        margin-left: 10px;
    }
    .employee-list-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    .employee-list-item:last-child {
        border-bottom: none;
    }
    .modal-avatar {
        width: 60px;
        height: 60px;
    }
    .employee-info {
        flex-grow: 1;
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
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Dashboard</h1>
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
        <!-- Organization Structure Card -->
        <div class="row">
          <div class="col-md-12">
            <div class="card card-primary">
              <div class="card-header">
                <h5 class="card-title">Organization Structure</h5>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover table-striped">
                    <thead class="bg-light">
                      <tr>
                        <th>Section</th>
                        <th>Head</th>
                        <th>Units</th>
                        <!-- <th>Actions</th> -->
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($sections as $section): ?>
                        <tr data-toggle="collapse" data-target="#section-<?= $section['section_id'] ?>">
                          <td>
                            <strong><?= htmlspecialchars($section['section_name']) ?></strong>
                          </td>
                          <td>
                            <?php if ($section['head_name']): ?>
                              <div class="d-flex align-items-center">
                                <?php if (!empty($section['head_picture']) && file_exists("../dist/img/employees/" . $section['head_picture'])): ?>
                                  <img src="../dist/img/employees/<?= htmlspecialchars($section['head_picture']) ?>" 
                                      class="img-circle elevation-2 avatar" 
                                      alt="<?= htmlspecialchars($section['head_name']) ?>">
                                <?php else: ?>
                                  <div class="avatar bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-user text-muted"></i>
                                  </div>
                                <?php endif; ?>
                                <span class="ml-2"><?= htmlspecialchars($section['head_name']) ?></span>
                              </div>
                            <?php else: ?>
                              <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <span class="badge bg-primary"><?= $section['unit_count'] ?> units</span>
                          </td>
                          
                        </tr>
                        
                        <!-- Unit Sections (collapsible row) -->
                        <tr class="collapse" id="section-<?= $section['section_id'] ?>">
                          <td colspan="4" class="p-0">
                            <div class="p-3 bg-light">
                              <h6 class="mb-3"><i class="fas fa-sitemap mr-2"></i> Unit Sections</h6>
                              <div class="row">
                                <?php 
                                $section_units = array_filter($unit_sections, function($unit) use ($section) {
                                    return $unit['section_id'] == $section['section_id'];
                                });
                                
                                if (!empty($section_units)): ?>
                                  <?php foreach ($section_units as $unit): ?>
                                    <div class="col-md-4 mb-3">
                                      <div class="card card-outline card-info">
                                        <div class="card-header">
                                          <h6 class="card-title mb-0"><?= htmlspecialchars($unit['unit_name']) ?></h6>
                                        </div>
                                        <div class="card-body">
                                          <?php if ($unit['head_name']): ?>
                                            <div class="d-flex align-items-center mb-2">
                                              <?php if (!empty($unit['head_picture']) && file_exists("../dist/img/employees/" . $unit['head_picture'])): ?>
                                                <img src="../dist/img/employees/<?= htmlspecialchars($unit['head_picture']) ?>" 
                                                    class="img-circle elevation-2 avatar" 
                                                    alt="<?= htmlspecialchars($unit['head_name']) ?>">
                                              <?php else: ?>
                                                <div class="avatar-sm bg-light d-flex align-items-center justify-content-center">
                                                  <i class="fas fa-user text-muted"></i>
                                                </div>
                                              <?php endif; ?>
                                              <div class="ml-2">
                                                <small class="text-muted">Unit Head</small>
                                                <div><?= htmlspecialchars($unit['head_name']) ?></div>
                                              </div>
                                            </div>
                                          <?php else: ?>
                                            <div class="text-muted mb-2">No unit head assigned</div>
                                          <?php endif; ?>
                                          
                                          <?php
                                          // Count employees per unit
                                          $stmt = $db->prepare("SELECT COUNT(*) as emp_count FROM employee WHERE unit_section_id = ?");
                                          $stmt->bind_param("i", $unit['unit_id']);
                                          $stmt->execute();
                                          $count_result = $stmt->get_result();
                                          $emp_count = $count_result->fetch_assoc()['emp_count'];
                                          ?>
                                          
                                          <button type="button" class="btn btn-sm btn-block btn-outline-info" 
                                                  data-toggle="modal" data-target="#unitEmployeesModal<?= $unit['unit_id'] ?>">
                                            <i class="fas fa-users mr-1"></i> View Employees (<?= $emp_count ?>)
                                          </button>
                                        </div>
                                      </div>
                                    </div>
                                  <?php endforeach; ?>
                                <?php else: ?>
                                  <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                      <i class="fas fa-info-circle mr-2"></i> No unit sections in this section
                                    </div>
                                  </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          </td>
                        </tr>
                        
                       <?php foreach ($unit_sections as $unit): ?>
                        <div class="modal fade" id="unitEmployeesModal<?= $unit['unit_id'] ?>">
                          <div class="modal-dialog modal-lg">
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
                                <?php
                                $stmt = $db->prepare("SELECT emp_id, first_name, last_name, picture, id_number 
                                                      FROM employee 
                                                      WHERE unit_section_id = ? 
                                                      ORDER BY first_name, last_name");
                                $stmt->bind_param("i", $unit['unit_id']);
                                $stmt->execute();
                                $emp_result = $stmt->get_result();
                                $employees = [];
                                while ($row = $emp_result->fetch_assoc()) {
                                    $employees[] = $row;
                                }
                                ?>
                                
                                <?php if (!empty($employees)): ?>
                                  <div class="list-group">
                                    <?php foreach ($employees as $emp): ?>
                                      <div class="employee-list-item">
                                        <a href="emp.profile.php?emp_id=<?= $emp['emp_id'] ?>" class="d-flex align-items-center w-100">
                                          <?php if (!empty($emp['picture']) && file_exists("../dist/img/employees/" . $emp['picture'])): ?>
                                            <img src="../dist/img/employees/<?= htmlspecialchars($emp['picture']) ?>" 
                                                  class="avatar modal-avatar" 
                                                  alt="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                                          <?php else: ?>
                                            <div class="default-avatar modal-avatar">
                                              <i class="fas fa-user"></i>
                                            </div>
                                          <?php endif; ?>
                                          
                                          <div class="employee-info">
                                            <h5><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h5>
                                            <small class="text-muted">Employee ID: <?= $emp['id_number'] ?></small>
                                          </div>
                                          <i class="fas fa-chevron-right text-muted"></i>
                                        </a>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                <?php else: ?>
                                  <div class="text-center py-4">
                                    <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No employees assigned to this unit</p>
                                  </div>
                                <?php endif; ?>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                        
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Role-specific widgets -->
        <?php if ($role_name === 'Administrator'): ?>
          <!-- Admin Widgets -->
          <div class="row">
            <div class="col-lg-3 col-6">
              <div class="small-box bg-info">
                <div class="inner">
                  <h3>150</h3>
                  <p>Total Users</p>
                </div>
                <div class="icon">
                  <i class="fas fa-users"></i>
                </div>
                <a href="users.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- Add more admin widgets as needed -->
          </div>

          <!-- Employee Widgets -->
          <div class="row">
            <div class="col-lg-3 col-6">
              <div class="small-box bg-success">
                <div class="inner">
                  <h3>15</h3>
                  <p>Your Tasks</p>
                </div>
                <div class="icon">
                  <i class="fas fa-tasks"></i>
                </div>
                <a href="tasks.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- Add more employee widgets as needed -->
          </div>

          <!-- Regular User Widgets -->
          <div class="row">
            <div class="col-lg-3 col-6">
              <div class="small-box bg-warning">
                <div class="inner">
                  <h3>5</h3>
                  <p>Your Projects</p>
                </div>
                <div class="icon">
                  <i class="fas fa-project-diagram"></i>
                </div>
                <a href="projects.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
              </div>
            </div>
            <!-- Add more regular user widgets as needed -->
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>