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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Organization Dashboard</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .dashboard-header {
        background-color: #f8f9fa;
        padding: 15px 0;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 20px;
    }
    
    /* Manager Section Styles */
    .manager-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .manager-info {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
    }
    
    .manager-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 20px;
        border: 3px solid rgba(255,255,255,0.3);
    }
    
    .default-manager-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background-color: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        color: rgba(255,255,255,0.8);
        border: 3px solid rgba(255,255,255,0.3);
        font-size: 2.5rem;
    }
    
    .manager-details {
        flex-grow: 1;
    }
    
    .manager-name {
        font-size: 1.8rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .manager-title {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .manager-contact {
        font-size: 1rem;
        opacity: 0.8;
    }
    
    /* Section Card Styles */
    .section-container {
        margin-bottom: 30px;
    }
    
    .section-card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
        background: white;
    }
    
    .section-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 20px;
        border-bottom: none;
    }
    
    .section-title {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    
    .section-head-info {
        display: flex;
        align-items: center;
        margin-top: 15px;
        padding: 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
    }
    
    .section-head-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
        border: 2px solid rgba(255,255,255,0.3);
    }
    
    .default-section-head-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: rgba(255,255,255,0.7);
        border: 2px solid rgba(255,255,255,0.3);
    }
    
    .section-head-details {
        flex-grow: 1;
    }
    
    .section-head-name {
        font-weight: bold;
        margin-bottom: 3px;
        font-size: 1.1rem;
    }
    
    .section-head-role {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    /* Unit Button Styles */
    .unit-buttons-container {
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .unit-button {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .unit-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        background: linear-gradient(135deg, #218838 0%, #1e9e8a 100%);
    }
    
    .unit-button-title {
        font-weight: bold;
        font-size: 1rem;
        margin-bottom: 5px;
    }
    
    .unit-button-count {
        font-size: 0.8rem;
        opacity: 0.9;
    }
    
    /* Manager's Office Staff Styles */
    .managers-staff-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .managers-staff-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #007bff;
    }
    
    .managers-staff-title {
        font-size: 1.5rem;
        font-weight: bold;
        color: #495057;
        margin: 0;
    }
    
    .managers-staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .staff-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        border-left: 4px solid #007bff;
        transition: all 0.3s ease;
    }
    
    .staff-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .staff-info {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .staff-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
        border: 2px solid #e9ecef;
    }
    
    .default-staff-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: #6c757d;
        border: 2px solid #dee2e6;
    }
    
    .staff-details {
        flex-grow: 1;
    }
    
    .staff-name {
        font-weight: bold;
        color: #495057;
        margin-bottom: 2px;
    }
    
    .staff-position {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 3px;
    }
    
    .staff-office {
        font-size: 0.8rem;
        color: #868e96;
    }
    
    .staff-responsibilities {
        font-size: 0.85rem;
        color: #495057;
        line-height: 1.4;
    }
    
    .view-all-staff {
        text-align: center;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }
    
    /* Stats and Sidebar Styles */
    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
        text-align: center;
        border-left: 4px solid #007bff;
    }
    
    .stats-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
        color: #007bff;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .sidebar-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
    }
    
    .sidebar-title {
        font-weight: bold;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #007bff;
        color: #495057;
    }
    
    .quick-link {
        display: flex;
        align-items: center;
        padding: 10px 0;
        color: #495057;
        text-decoration: none;
        border-bottom: 1px solid #f1f1f1;
        transition: color 0.3s ease;
    }
    
    .quick-link:last-child {
        border-bottom: none;
    }
    
    .quick-link:hover {
        color: #007bff;
    }
    
    .quick-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    /* Organization Structure Layout */
    .org-structure {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .org-manager {
        margin-bottom: 10px;
        width: auto;
    }
    
    .org-sections {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 10px;
        width: 100%;
    }
    
    /* Section Card Layout */
    .section-card-container {
        height: 100%;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .org-sections {
            grid-template-columns: 1fr;
        }
        
        .manager-info {
            flex-direction: column;
            text-align: center;
        }
        
        .manager-avatar, .default-manager-avatar {
            margin-right: 0;
            margin-bottom: 15px;
        }
        
        .managers-staff-grid {
            grid-template-columns: 1fr;
        }
        
        .unit-buttons-container {
            grid-template-columns: 1fr;
        }
    }
    .employee-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s ease;
        }

        .employee-item:hover {
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .employee-item:last-child {
            border-bottom: none;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #e9ecef;
        }

        .default-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #999;
            border: 2px solid #e9ecef;
        }

        .employee-info {
            flex-grow: 1;
        }

        .employee-name {
            font-weight: 500;
            color: #495057;
            margin-bottom: 2px;
            font-size: 0.95rem;
        }

        .employee-id {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .employee-link {
            display: flex;
            align-items: center;
            width: 100%;
            text-decoration: none;
            color: inherit;
        }

        .employee-link:hover {
            color: inherit;
            text-decoration: none;
        }

        /* Modal Specific Styles */
        .modal-employee-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .modal-employee-list .list-group {
            border-radius: 8px;
            overflow: hidden;
        }

        .modal-employee-list .employee-item {
            border: none;
            border-bottom: 1px solid #e9ecef;
            margin: 0;
        }

        .modal-employee-list .employee-item:last-child {
            border-bottom: none;
        }

        /* Modal Header Improvements */
        .modal-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-bottom: none;
            padding: 20px;
        }

        .modal-header .modal-title {
            font-weight: bold;
            font-size: 1.3rem;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
            text-shadow: none;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        /* Modal Body Improvements */
        .modal-body {
            padding: 0;
        }

        /* Unit Head Information in Modal */
        .unit-head-info {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 0;
        }

        .unit-head-content {
            display: flex;
            align-items: center;
        }

        .unit-head-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 3px solid #007bff;
        }

        .default-unit-head-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #6c757d;
            border: 3px solid #007bff;
        }

        .unit-head-details {
            flex-grow: 1;
        }

        .unit-head-name {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .unit-head-role {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .unit-head-section {
            color: #868e96;
            font-size: 0.85rem;
        }

        /* Empty State for Modal */
        .modal-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .modal-empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .modal-empty-state h5 {
            margin-bottom: 10px;
            color: #495057;
        }

        /* Responsive adjustments for modal */
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 10px;
            }
            
            .employee-item {
                padding: 10px;
            }
            
            .avatar, .default-avatar {
                width: 40px;
                height: 40px;
                margin-right: 12px;
            }
            
            .employee-name {
                font-size: 0.9rem;
            }
            
            .unit-head-info {
                padding: 15px;
            }
            
            .unit-head-avatar, .default-unit-head-avatar {
                width: 50px;
                height: 50px;
            }
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
        
        <div class="row">
          <!-- Main Content Area -->
          <div class="col-md-12">
            <!-- Organization Structure -->
            <div class="org-structure">
              <!-- Manager Section -->
              <div class="org-manager">
                <div class="manager-section">
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
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>