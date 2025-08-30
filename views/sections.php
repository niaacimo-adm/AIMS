<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

function isEmployeeAlreadyAssigned($db, $emp_id, $current_section_id = null, $current_unit_id = null) {
    // Convert null values to 0 for the queries
    $current_section_id = $current_section_id ?? 0;
    $current_unit_id = $current_unit_id ?? 0;
    
    // Check if employee is already an office manager
    $stmt = $db->prepare("SELECT COUNT(*) FROM employee WHERE emp_id = ? AND is_manager = 1");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $is_manager = $stmt->get_result()->fetch_row()[0];
    
    if ($is_manager > 0) {
        return "This employee is already assigned as an office manager elsewhere.";
    }
    
    return false;
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new section
    if (isset($_POST['add_section'])) {
        $section_name = trim($_POST['section_name']);
        $section_code = strtoupper(trim($_POST['section_code']));
        $head_emp_id = $_POST['head_emp_id'] ?? null;
        $office_id = $_POST['office_id'] ?? null;
        
        if (!empty($section_name) && !empty($section_code)) {
            // Validate head assignment
            if ($head_emp_id) {
                $validation = isEmployeeAlreadyAssigned($db, $head_emp_id);
                if ($validation) {
                    $_SESSION['swal'] = [
                        'type' => 'error',
                        'title' => 'Error!',
                        'text' => $validation
                    ];
                    header("Location: sections.php");
                    exit();
                }
            }
            
            $stmt = $db->prepare("INSERT INTO section (office_id, section_name, section_code, head_emp_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$office_id, $section_name, $section_code, $head_emp_id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Section added successfully!'
                ];
                header("Location: sections.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add section.'
                ];
            }
        }
    }

// Add new unit section
if (isset($_POST['add_unit_section'])) {
    $section_id = $_POST['section_id'];
    $unit_name = trim($_POST['unit_name']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $head_emp_id = $_POST['head_emp_id'] ?? null;
    
    if (!empty($unit_name) && !empty($unit_code)) {
        // Validate head assignment
        if ($head_emp_id) {
            $validation = isEmployeeAlreadyAssigned($db, $head_emp_id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: sections.php");
                exit();
            }
        }
        
        // Start transaction
        $db->begin_transaction();
        
        try {
            // Add unit section
            $stmt = $db->prepare("INSERT INTO unit_section (section_id, unit_name, unit_code, head_emp_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$section_id, $unit_name, $unit_code, $head_emp_id]);
            $unit_id = $db->insert_id;
            
            // Automatically assign head to the unit
            if ($head_emp_id) {
                $stmt = $db->prepare("UPDATE employee SET unit_section_id = ? WHERE emp_id = ?");
                $stmt->execute([$unit_id, $head_emp_id]);
                
                // Add notification
                $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                        VALUES (?, 'New Role Assignment', 'You have been assigned as head of unit {$unit_name}', 'role_change')");
                $stmt_notif->execute([$head_emp_id]);
            }
            
            $db->commit();
            
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Unit section added successfully!'
            ];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to add unit section: ' . $e->getMessage()
            ];
        }
        
        header("Location: sections.php");
        exit();
    }
}
    
    // Update section
    if (isset($_POST['update_section'])) {
        $id = $_POST['id'];
        $section_name = trim($_POST['section_name']);
        $section_code = strtoupper(trim($_POST['section_code']));
        $head_emp_id = $_POST['head_emp_id'] ?? null;
        $secretaries = $_POST['secretaries'] ?? [];
        $office_id = $_POST['office_id'] ?? null;

        if (!empty($section_name) && !empty($section_code)) {
            // First get the original head before updating
            $stmt = $db->prepare("SELECT head_emp_id FROM section WHERE section_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $original_head_result = $stmt->get_result();
            $original_head = $original_head_result->fetch_assoc();
            $original_head_id = $original_head['head_emp_id'] ?? null;

            // Validate head assignment
            if ($head_emp_id) {
                $validation = isEmployeeAlreadyAssigned($db, $head_emp_id, $id);
                if ($validation) {
                    $_SESSION['swal'] = [
                        'type' => 'error',
                        'title' => 'Error!',
                        'text' => $validation
                    ];
                    header("Location: sections.php");
                    exit();
                }
            }
            
            // Start transaction
            $db->begin_transaction();
            
            try {
                // Update section
                $stmt = $db->prepare("UPDATE section SET office_id = ?, section_name = ?, section_code = ?, head_emp_id = ? WHERE section_id = ?");
                $stmt->execute([$office_id, $section_name, $section_code, $head_emp_id, $id]);
                
                // Update secretaries
                // First delete existing secretaries
                $stmt = $db->prepare("DELETE FROM section_secretaries WHERE section_id = ?");
                $stmt->execute([$id]);
                
                if (!empty($secretaries)) {
                    $stmt = $db->prepare("INSERT INTO section_secretaries (section_id, emp_id) VALUES (?, ?)");
                    foreach ($secretaries as $emp_id) {
                        $stmt->execute([$id, $emp_id]);
                        
                        // Add notification
                        $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                                VALUES (?, 'New Role Assignment', 'You have been assigned as focal person of section {$section_name}', 'role_change')");
                        $stmt_notif->execute([$emp_id]);
                    }
                }
                // Add notification if head changed
                if ($head_emp_id && $head_emp_id != $original_head_id) {
                    $stmt = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                        VALUES (?, 'New Role Assignment', 'You have been assigned as head of section {$section_name}', 'role_change')");
                    $stmt->execute([$head_emp_id]);
                }
                
                $db->commit();
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Section updated successfully!'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update section: ' . $e->getMessage()
                ];
            }
            
            header("Location: sections.php");
            exit();
        }
    }
    
// Update unit section
if (isset($_POST['update_unit_section'])) {
    $unit_id = $_POST['unit_id'];
    $unit_name = trim($_POST['unit_name']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $head_emp_id = $_POST['head_emp_id'] ?? null;
    $unit_employees = $_POST['unit_employees'] ?? [];
    
    if (!empty($unit_name) && !empty($unit_code)) {
        // Get original head
        $stmt = $db->prepare("SELECT head_emp_id FROM unit_section WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $original_head_result = $stmt->get_result();
        $original_head = $original_head_result->fetch_assoc();
        $original_head_id = $original_head['head_emp_id'] ?? null;

        // Validate head assignment
        if ($head_emp_id) {
            $validation = isEmployeeAlreadyAssigned($db, $head_emp_id, null, $unit_id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: sections.php");
                exit();
            }
        }
        
        // Start transaction
        $db->begin_transaction();
        
        try {
            // Update unit section
            $stmt = $db->prepare("UPDATE unit_section SET unit_name = ?, unit_code = ?, head_emp_id = ? WHERE unit_id = ?");
            $stmt->execute([$unit_name, $unit_code, $head_emp_id, $unit_id]);
            
            // Clear previous head's assignment if changed and not in employee list
            if ($original_head_id && $original_head_id != $head_emp_id && !in_array($original_head_id, $unit_employees)) {
                $stmt = $db->prepare("UPDATE employee SET unit_section_id = NULL WHERE emp_id = ?");
                $stmt->execute([$original_head_id]);
            }
            
            // Assign new head to the unit
            if ($head_emp_id) {
                $stmt = $db->prepare("UPDATE employee SET unit_section_id = ? WHERE emp_id = ?");
                $stmt->execute([$unit_id, $head_emp_id]);
                
                // Add head to employees list if not already present
                if (!in_array($head_emp_id, $unit_employees)) {
                    $unit_employees[] = $head_emp_id;
                }
            }
            
            // Update all unit employees
            if (!empty($unit_employees)) {
                $stmt = $db->prepare("UPDATE employee SET unit_section_id = ? WHERE emp_id = ?");
                foreach ($unit_employees as $emp_id) {
                    $stmt->execute([$unit_id, $emp_id]);
                    
                    // Add notification for regular employees (not head)
                    if ($emp_id != $head_emp_id) {
                        $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                                VALUES (?, 'New Assignment', 'You have been assigned to unit {$unit_name}', 'assignment_change')");
                        $stmt_notif->execute([$emp_id]);
                    }
                }
            }
            
            // Notification for new head
            if ($head_emp_id && $head_emp_id != $original_head_id) {
                $stmt = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                    VALUES (?, 'New Role Assignment', 'You have been assigned as head of unit {$unit_name}', 'role_change')");
                $stmt->execute([$head_emp_id]);
            }
            
            $db->commit();
            
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Unit section updated successfully!'
            ];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to update unit section: ' . $e->getMessage()
            ];
        }
        
        header("Location: sections.php");
        exit();
    }
}
}

// Handle delete actions
if (isset($_GET['delete'])) {
    $type = $_GET['type'];
    $id = $_GET['id'];
    
    try {
        if ($type === 'section') {
    // Start transaction
            $db->begin_transaction();
            
            try {
                // First, remove all employees from this section
                $stmt = $db->prepare("UPDATE employee SET section_id = NULL, unit_section_id = NULL WHERE section_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Remove all employees from unit sections under this section
                $stmt = $db->prepare("UPDATE employee e 
                                    JOIN unit_section us ON e.unit_section_id = us.unit_id
                                    SET e.unit_section_id = NULL 
                                    WHERE us.section_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Delete unit sections first
                $stmt = $db->prepare("DELETE FROM unit_section WHERE section_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Then delete the section
                $stmt = $db->prepare("DELETE FROM section WHERE section_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                $db->commit();
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Section and its unit sections deleted successfully! All assigned employees have been unassigned.'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to delete section: ' . $e->getMessage()
                ];
            }
        } 
        elseif ($type === 'unit') {
            // Start transaction
            $db->begin_transaction();
            
            try {
                // First remove all employees from this unit
                $stmt = $db->prepare("UPDATE employee SET unit_section_id = NULL WHERE unit_section_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Then delete the unit section
                $stmt = $db->prepare("DELETE FROM unit_section WHERE unit_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                $db->commit();
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Unit section deleted successfully! All assigned employees have been unassigned.'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to delete unit section: ' . $e->getMessage()
                ];
            }
        }
    } catch (Exception $e) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => $e->getMessage()
        ];
    }
    
    header("Location: sections.php");
    exit();
}


// Fetch all sections with their unit sections and heads
$query = "SELECT s.*, 
                 o.office_name,
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture,
                 (SELECT COUNT(*) FROM unit_section WHERE section_id = s.section_id) as unit_count,
                 (SELECT GROUP_CONCAT(CONCAT(sec.first_name, ' ', sec.last_name) SEPARATOR ', ') 
                  FROM section_secretaries ss
                  JOIN employee sec ON ss.emp_id = sec.emp_id
                  WHERE ss.section_id = s.section_id) as secretaries
          FROM section s
          LEFT JOIN office o ON s.office_id = o.office_id
          LEFT JOIN employee e ON s.head_emp_id = e.emp_id
          ORDER BY o.office_name, s.section_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

// Fetch all unit sections with their heads
// In the section where you fetch unit sections, modify the query to include employees:
// Fetch all unit sections with their heads
$query = "SELECT us.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture,
                 s.section_name,
                 s.section_code,
                 (SELECT COUNT(*) FROM employee WHERE unit_section_id = us.unit_id) as employee_count
          FROM unit_section us
          LEFT JOIN employee e ON us.head_emp_id = e.emp_id
          LEFT JOIN section s ON us.section_id = s.section_id
          ORDER BY us.unit_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$unit_sections = [];
while ($row = $result->fetch_assoc()) {
    $unit_sections[] = $row;
}

// Fetch all employees for dropdowns
$query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name 
          FROM employee 
          ORDER BY first_name, last_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
// Fetch all offices for dropdown
$query = "SELECT * FROM office ORDER BY office_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$offices = [];
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Section Management</title>
  <?php include '../includes/header.php'; ?>
<style>
    .unit-section-item {
        padding-left: 30px;
        border-left: 3px solid #6c757d;
        margin-bottom: 15px;
        position: relative;
    }
    .unit-actions {
        position: absolute;
        right: 0;
        top: 0;
    }
    .section-head {
        font-weight: bold;
        color: #007bff;
    }
    .employee-count-btn {
        cursor: pointer;
        color: #007bff;
        background: none;
        border: none;
        padding: 0;
        font-size: 0.8rem;
    }
    .employee-count-btn:hover {
        text-decoration: underline;
        color: #0056b3;
    }
    .employee-list-item {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
    }
    .employee-list-item:last-child {
        border-bottom: none;
    }
    .employee-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
    }
    .employee-info {
        flex-grow: 1;
    }
    .default-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        color: #999;
    }
    .section-head-info {
        display: flex;
        align-items: center;
        margin-top: 5px;
    }
    .section-head-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 8px;
    }
    .default-head-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        color: #999;
        font-size: 0.8rem;
    }
    .section-secretaries {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 3px;
}
.section-secretaries i {
    margin-right: 3px;
}
.select2-container--default .select2-selection--multiple {
    min-height: 38px;
    border: 1px solid #ced4da;
    border-radius: 4px;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff;
    border-color: #006fe6;
    color: white;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: rgba(255,255,255,0.7);
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
            <h1>Section Management</h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-4">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Add New Section</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                    <div class="form-group">
                        <label for="office_id">Office</label>
                        <select class="form-control" id="office_id" name="office_id" required>
                            <option value="">-- Select Office --</option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?= $office['office_id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section_name">Section Name</label>
                        <input type="text" class="form-control" id="section_name" name="section_name" required>
                    </div>
                    <div class="form-group">
                        <label for="section_code">Section Code (2-3 letters)</label>
                        <input type="text" class="form-control" id="section_code" name="section_code" required maxlength="3" pattern="[A-Za-z]{2,3}" title="2-3 letter code">
                    </div>
                  <div class="form-group">
                    <label for="section_head_emp_id">Section Head</label>
                    <select class="form-control select2" id="section_head_emp_id" name="head_emp_id" multiple="multiple">
                      <option value="">-- Select Head --</option>
                      <?php foreach ($employees as $employee): ?>
                        <option value="<?= $employee['emp_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_section" class="btn btn-primary">Add Section</button>
                </div>
              </form>
            </div>
            
            <div class="card card-success">
              <div class="card-header">
                <h3 class="card-title">Add New Unit Section</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label for="section_id">Parent Section</label>
                    <select class="form-control" id="section_id" name="section_id" required>
                      <?php foreach ($sections as $section): ?>
                        <option value="<?= $section['section_id'] ?>"><?= htmlspecialchars($section['section_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                        <label for="unit_name">Unit Name</label>
                        <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_code">Unit Code (2-3 letters)</label>
                        <input type="text" class="form-control" id="unit_code" name="unit_code" required maxlength="3" pattern="[A-Za-z]{2,3}" title="2-3 letter code">
                    </div>
                  <div class="form-group">
                    <label for="head_emp_id">Unit Head</label>
                    <select class="form-control select2" id="head_emp_id" name="head_emp_id" multiple="multiple">
                      <option value="">-- Select Head --</option>
                      <?php foreach ($employees as $employee): ?>
                        <option value="<?= $employee['emp_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_unit_section" class="btn btn-success">Add Unit Section</button>
                </div>
              </form>
            </div>
          </div>
          
          <div class="col-md-8">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Sections & Units</h3>
                <div class="card-tools">
                <a href="managers_office.php" class="btn btn-success btn-sm">
                    <i class="fas fa-user-tie"></i> Manager's Office
                </a>
            </div>
              </div>
              
              <div class="card-body">
                <table id="sectable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Section Name</th>
                      <th>Unit Sections</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sections as $section): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($section['section_name']) ?></strong>
                        <?php if (!empty($section['office_name'])): ?>
                            <div class="text-muted small">Office: <?= htmlspecialchars($section['office_name']) ?></div>
                        <?php endif; ?>
                        
                        <?php 
                        // Get employee count for this section
                        $stmt = $db->prepare("SELECT COUNT(*) as emp_count FROM employee WHERE section_id = ?");
                        $stmt->bind_param("i", $section['section_id']);
                        $stmt->execute();
                        $emp_count_result = $stmt->get_result();
                        $emp_count = $emp_count_result->fetch_assoc()['emp_count'];
                        ?>
                        
                        <span class="badge bg-info float-right">
                            <?= $section['unit_count'] ?> units, <?= $emp_count ?> employees
                        </span>

                          <?php if ($section['head_emp_id']): ?>
                              <div class="section-head-info d-flex align-items-start mt-2">
                                  <?php 
                                  // Fetch section head details including picture
                                  $stmt = $db->prepare("SELECT picture, first_name, last_name FROM employee WHERE emp_id = ?");
                                  $stmt->bind_param("i", $section['head_emp_id']);
                                  $stmt->execute();
                                  $head_result = $stmt->get_result();
                                  $head_info = $head_result->fetch_assoc();
                                  ?>
                                  
                                  <?php if (!empty($head_info['picture']) && file_exists("../dist/img/employees/" . $head_info['picture'])): ?>
                                      <img src="../dist/img/employees/<?= htmlspecialchars($head_info['picture']) ?>" 
                                          class="section-head-avatar mr-2" 
                                          alt="<?= htmlspecialchars($head_info['first_name'] . ' ' . $head_info['last_name']) ?>">
                                  <?php else: ?>
                                      <div class="default-head-avatar mr-2">
                                          <i class="fas fa-user"></i>
                                      </div>
                                  <?php endif; ?>
                                  
                                  <div class="flex-grow-1">
                                      <div class="section-head">
                                          Head: 
                                          <a href="emp.profile.php?emp_id=<?= $section['head_emp_id'] ?>" class="text-primary">
                                              <?= htmlspecialchars($section['head_name']) ?>
                                          </a>
                                      </div>
                                      
                                      <?php if (!empty($section['secretaries'])): ?>
                                          <div class="section-secretaries mt-2">
                                              <div><i class="fas fa-user-secret"></i> <?= htmlspecialchars($section['section_name']) ?><strong> Staff/s :</strong></div>
                                              <div class="pl-3">
                                                  <?php 
                                                  // Fetch individual secretary details for better display
                                                  $stmt = $db->prepare("SELECT e.emp_id, e.first_name, e.last_name, e.picture 
                                                                      FROM section_secretaries ss
                                                                      JOIN employee e ON ss.emp_id = e.emp_id
                                                                      WHERE ss.section_id = ?");
                                                  $stmt->bind_param("i", $section['section_id']);
                                                  $stmt->execute();
                                                  $secretaries_result = $stmt->get_result();
                                                  $secretaries = [];
                                                  while ($row = $secretaries_result->fetch_assoc()) {
                                                      $secretaries[] = $row;
                                                  }
                                                  
                                                  foreach ($secretaries as $secretary): ?>
                                                      <div class="d-flex align-items-center mb-1">
                                                          <?php if (!empty($secretary['picture']) && file_exists("../dist/img/employees/" . $secretary['picture'])): ?>
                                                              <img src="../dist/img/employees/<?= htmlspecialchars($secretary['picture']) ?>" 
                                                                  class="employee-avatar mr-2" 
                                                                  alt="<?= htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']) ?>">
                                                          <?php else: ?>
                                                              <div class="default-avatar mr-2">
                                                                  <i class="fas fa-user"></i>
                                                              </div>
                                                          <?php endif; ?>
                                                          <a href="emp.profile.php?emp_id=<?= $secretary['emp_id'] ?>" class="text-dark">
                                                              <?= htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']) ?>
                                                          </a>
                                                      </div>
                                                  <?php endforeach; ?>
                                              </div>
                                          </div>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          <?php endif; ?>
                      </td>
                      <td>
                        <?php 
                        $section_units = array_filter($unit_sections, function($unit) use ($section) {
                            return $unit['section_id'] == $section['section_id'];
                        });
                        if (empty($section_units)): ?>
                        <div class="flex-grow-1">
                              <small class="text-muted">No unit sections</small>
                            <?php else: ?>
                              <?php foreach ($section_units as $unit): ?>
                                <div class="unit-section-item">
                                  <strong><?= htmlspecialchars($unit['unit_name']) ?></strong>
                                  <!-- <div class="section-head"> -->
                                    <?php if ($unit['head_name']): ?>
                                      <small class="text-muted">(Head: <?= htmlspecialchars($unit['head_name']) ?>)</small>
                                    <?php endif; ?>
                                  <!-- </div> -->
                                  
                                  <!-- Fetch employees for this unit with their pictures -->
                                  <?php
                                  $stmt = $db->prepare("SELECT emp_id, first_name, last_name, picture 
                                                      FROM employee 
                                                      WHERE unit_section_id = ? 
                                                      ORDER BY first_name, last_name");
                                  $stmt->bind_param("i", $unit['unit_id']);
                                  $stmt->execute();
                                  $result = $stmt->get_result();
                                  $unit_employees = [];
                                  while ($row = $result->fetch_assoc()) {
                                      $unit_employees[] = $row;
                                  }
                                  ?>
                                  
                                  <div class="mt-2">
                                    <button type="button" class="employee-count-btn" data-toggle="modal" data-target="#employeeModal<?= $unit['unit_id'] ?>">
                                      <?= count($unit_employees) ?> employee(s) <i class="fas fa-users"></i>
                                    </button>
                                  </div>
                               
                                  <div class="modal fade" id="employeeModal<?= $unit['unit_id'] ?>">
                                      <div class="modal-dialog modal-lg">
                                          <div class="modal-content">
                                              <div class="modal-header">
                                                  <h5 class="modal-title">
                                                      <i class="fas fa-users mr-2"></i>
                                                      Employees in <?= htmlspecialchars($unit['unit_name']) ?>
                                                  </h5>
                                                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                      <span aria-hidden="true">&times;</span>
                                                  </button>
                                              </div>
                                              <div class="modal-body">
                                                  <?php if (!empty($unit_employees)): ?>
                                                      <div class="table-responsive">
                                                          <table id="unitEmployeesTable<?= $unit['unit_id'] ?>" class="table table-bordered table-striped">
                                                              <thead>
                                                                  <tr>
                                                                      <th>Photo</th>
                                                                      <th>Name</th>
                                                                      <th>Position</th>
                                                                      <th>Email</th>
                                                                      <th>Phone</th>
                                                                      <th>Actions</th>
                                                                  </tr>
                                                              </thead>
                                                              <tbody>
                                                                  <?php foreach ($unit_employees as $emp): 
                                                                      // Fetch additional employee details if needed
                                                                      $stmt = $db->prepare("SELECT e.*, p.position_name 
                                                                                          FROM employee e 
                                                                                          LEFT JOIN position p ON e.position_id = p.position_id 
                                                                                          WHERE e.emp_id = ?");
                                                                      $stmt->bind_param("i", $emp['emp_id']);
                                                                      $stmt->execute();
                                                                      $emp_details = $stmt->get_result()->fetch_assoc();
                                                                  ?>
                                                                  <tr>
                                                                      <td>
                                                                          <?php if (!empty($emp['picture']) && file_exists("../dist/img/employees/" . $emp['picture'])): ?>
                                                                              <img src="../dist/img/employees/<?= htmlspecialchars($emp['picture']) ?>" 
                                                                                  class="employee-avatar" 
                                                                                  alt="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                                                                          <?php else: ?>
                                                                              <div class="default-avatar">
                                                                                  <i class="fas fa-user"></i>
                                                                              </div>
                                                                          <?php endif; ?>
                                                                      </td>
                                                                      <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                                                                      <td><?= htmlspecialchars($emp_details['position_name'] ?? 'N/A') ?></td>
                                                                      <td><?= htmlspecialchars($emp_details['email'] ?? 'N/A') ?></td>
                                                                      <td><?= htmlspecialchars($emp_details['phone_number'] ?? 'N/A') ?></td>
                                                                      <td>
                                                                          <div class="btn-group">
                                                                              <a href="emp.profile.php?emp_id=<?= $emp['emp_id'] ?>" 
                                                                                class="btn btn-sm btn-primary" title="View Profile">
                                                                                  <i class="fas fa-eye"></i>
                                                                              </a>
                                                                              <a href="emp.edit.php?emp_id=<?= $emp['emp_id'] ?>" 
                                                                                class="btn btn-sm btn-warning" title="Edit">
                                                                                  <i class="fas fa-edit"></i>
                                                                              </a>
                                                                              <button class="btn btn-sm btn-danger remove-employee-btn" 
                                                                                      data-emp-id="<?= $emp['emp_id'] ?>" 
                                                                                      data-unit-id="<?= $unit['unit_id'] ?>"
                                                                                      title="Remove from Unit">
                                                                                  <i class="fas fa-user-minus"></i>
                                                                              </button>
                                                                          </div>
                                                                      </td>
                                                                  </tr>
                                                                  <?php endforeach; ?>
                                                              </tbody>
                                                          </table>
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
                                  <div class="unit-actions btn-group">
                                    <button type="button" class="btn btn-xs btn-info" data-toggle="modal" 
                                            data-target="#editUnitModal<?= $unit['unit_id'] ?>">
                                      <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-xs btn-danger delete-unit-btn"
                                            data-id="<?= $unit['unit_id'] ?>"
                                            data-name="<?= htmlspecialchars($unit['unit_name']) ?>">
                                    <i class="fas fa-trash"></i>
                                    </button>
                                  </div>
                                  
                                  <div class="modal fade" id="editUnitModal<?= $unit['unit_id'] ?>">
                                      <div class="modal-dialog">
                                        <div class="modal-content">
                                          <div class="modal-header">
                                            <h4 class="modal-title">Edit Unit Section</h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                              <span aria-hidden="true">&times;</span>
                                            </button>
                                          </div>
                                          <form method="POST">
                                              <div class="modal-body">
                                                  <input type="hidden" name="unit_id" value="<?= $unit['unit_id'] ?>">
                                                  <div class="form-group">
                                                      <label>Parent Section</label>
                                                      <select class="form-control" name="section_id" required>
                                                          <?php foreach ($sections as $sec): ?>
                                                              <option value="<?= $sec['section_id'] ?>" 
                                                                  <?= $sec['section_id'] == $unit['section_id'] ? 'selected' : '' ?>>
                                                                  <?= htmlspecialchars($sec['section_name']) ?>
                                                              </option>
                                                          <?php endforeach; ?>
                                                      </select>
                                                  </div>
                                                  <div class="form-group">
                                                        <label>Unit Name</label>
                                                        <input type="text" class="form-control" name="unit_name" 
                                                                value="<?= htmlspecialchars($unit['unit_name']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Unit Code</label>
                                                        <input type="text" class="form-control" name="unit_code" 
                                                                value="<?= htmlspecialchars($unit['unit_code']) ?>" required maxlength="3" pattern="[A-Za-z]{2,3}">
                                                    </div>
                                                  <div class="form-group">
                                                      <label>Unit Head</label>
                                                      <select class="form-control" name="head_emp_id">
                                                          <option value="">-- Select Head --</option>
                                                          <?php foreach ($employees as $employee): ?>
                                                              <option value="<?= $employee['emp_id'] ?>" 
                                                                  <?= $employee['emp_id'] == $unit['head_emp_id'] ? 'selected' : '' ?>>
                                                                  <?= htmlspecialchars($employee['full_name']) ?>
                                                              </option>
                                                          <?php endforeach; ?>
                                                      </select>
                                                  </div>
                                                  <div class="form-group">
                                                      <label>Unit Employees</label>
                                                      <select class="form-control select2-unit-employees" name="unit_employees[]" multiple="multiple">
                                                          <?php 
                                                          // Get current employees assigned to this unit
                                                          $stmt = $db->prepare("SELECT emp_id FROM employee WHERE unit_section_id = ?");
                                                          $stmt->bind_param("i", $unit['unit_id']);
                                                          $stmt->execute();
                                                          $result = $stmt->get_result();
                                                          $current_employees = [];
                                                          while ($row = $result->fetch_assoc()) {
                                                              $current_employees[] = $row['emp_id'];
                                                          }
                                                          ?>
                                                          <?php foreach ($employees as $employee): ?>
                                                              <option value="<?= $employee['emp_id'] ?>" 
                                                                  <?= in_array($employee['emp_id'], $current_employees) ? 'selected' : '' ?>>
                                                                  <?= htmlspecialchars($employee['full_name']) ?>
                                                              </option>
                                                          <?php endforeach; ?>
                                                      </select>
                                                  </div>
                                              </div>
                                              <div class="modal-footer">
                                                  <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                  <button type="submit" name="update_unit_section" class="btn btn-primary">Save changes</button>
                                              </div>
                                          </form>
                                        </div>
                                      </div>
                                    </div>
                                </div>
                              <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <div class="btn-group">
                          <button type="button" class="btn btn-info" data-toggle="modal" 
                                  data-target="#editModal<?= $section['section_id'] ?>">
                            <i class="fas fa-edit"></i>
                          </button>
                            <button class="btn btn-danger delete-btn" 
                                    data-id="<?= $section['section_id'] ?>" 
                                    data-name="<?= htmlspecialchars($section['section_name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <!-- Edit Section Modal -->
                        <div class="modal fade" id="editModal<?= $section['section_id'] ?>">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h4 class="modal-title">Edit Section</h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                  <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <form method="POST">
                                <div class="modal-body">
                                  <input type="hidden" name="id" value="<?= $section['section_id'] ?>">
                                <div class="form-group">
                                    <label>Office</label>
                                    <select class="form-control" name="office_id" required>
                                        <option value="">-- Select Office --</option>
                                        <?php foreach ($offices as $office): ?>
                                            <option value="<?= $office['office_id'] ?>" 
                                                <?= $office['office_id'] == $section['office_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($office['office_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Section Name</label>
                                    <input type="text" class="form-control" name="section_name" 
                                        value="<?= htmlspecialchars($section['section_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Section Code</label>
                                    <input type="text" class="form-control" name="section_code" 
                                            value="<?= htmlspecialchars($section['section_code']) ?>" required maxlength="3" pattern="[A-Za-z]{2,3}">
                                </div>
                                  <div class="form-group">
                                    <label>Section Head</label>
                                    <select class="form-control select2" name="head_emp_id" multiple="multiple">
                                      <option value="">-- Select Head --</option>
                                      <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['emp_id'] ?>" 
                                          <?= $employee['emp_id'] == $section['head_emp_id'] ? 'selected' : '' ?>>
                                          <?= htmlspecialchars($employee['full_name']) ?>
                                        </option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  <div class="form-group">
                                      <label>Focal Person</label>
                                      <select class="form-control select2-sec" name="secretaries[]" multiple="multiple">
                                          <?php 
                                          // Get current secretaries for this section
                                          $stmt = $db->prepare("SELECT emp_id FROM section_secretaries WHERE section_id = ?");
                                          $stmt->bind_param("i", $section['section_id']);
                                          $stmt->execute();
                                          $secretaries_result = $stmt->get_result();
                                          $current_secretaries = [];
                                          while ($row = $secretaries_result->fetch_assoc()) {
                                              $current_secretaries[] = $row['emp_id'];
                                          }
                                          ?>
                                          
                                          <?php foreach ($employees as $employee): ?>
                                              <option value="<?= $employee['emp_id'] ?>" 
                                                  <?= in_array($employee['emp_id'], $current_secretaries) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($employee['full_name']) ?>
                                              </option>
                                          <?php endforeach; ?>
                                      </select>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                  <button type="submit" name="update_section" class="btn btn-primary">Save changes</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

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

<!-- SweetAlert Notifications -->
<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $_SESSION['swal']['type'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        showConfirmButton: true,
        timer: 3000
    });
});
</script>
<?php 
    unset($_SESSION['swal']);
endif; 
?>

<!-- Replace the existing delete button handler with: -->
<script>
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const sectionId = $(this).data('id');
        const sectionName = $(this).data('name');
        
        Swal.fire({
            title: 'Delete Section?',
            text: `Are you sure you want to delete "${sectionName}"? This will also delete all unit sections under it and unassign all employees from this section and its units.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Delete',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: 'delete_section.php',
                    method: 'POST',
                    data: { section_id: sectionId },
                    dataType: 'json'
                }).then(response => {
                    if (!response.success) {
                        throw new Error(response.message);
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(
                        `Request failed: ${error.responseJSON?.message || error.statusText || 'Unknown error'}`
                    );
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: result.value.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    });

    // Similar update for unit section deletion
    $(document).on('click', '.delete-unit-btn', function(e) {
        e.preventDefault();
        const unitId = $(this).data('id');
        const unitName = $(this).data('name');
        
        Swal.fire({
            title: 'Delete Unit Section?',
            text: `Are you sure you want to delete "${unitName}"? This will unassign all employees from this unit.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Delete',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: 'delete_unit_section.php', // You'll need to create this
                    method: 'POST',
                    data: { unit_id: unitId },
                    dataType: 'json'
                }).then(response => {
                    if (!response.success) {
                        throw new Error(response.message);
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(
                        `Request failed: ${error.responseJSON?.message || error.statusText || 'Unknown error'}`
                    );
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: result.value.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    });
</script>
<script>
$(document).ready(function() {
        // Initialize DataTables for each unit's employee modal
    $('[id^="employeeModal"]').on('shown.bs.modal', function () {
        var unitId = $(this).attr('id').replace('employeeModal', '');
        var table = $('#unitEmployeesTable' + unitId);
        
        // Check if DataTable is already initialized
        if (!$.fn.DataTable.isDataTable(table)) {
            table.DataTable({
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                columnDefs: [
                    { responsivePriority: 1, targets: 1 },
                    { responsivePriority: 2, targets: -1 },
                    { orderable: false, targets: [0, -1] }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search employees...",
                    lengthMenu: "Show _MENU_ employees",
                    zeroRecords: "No matching employees found",
                    info: "Showing _START_ to _END_ of _TOTAL_ employees",
                    infoEmpty: "No employees available",
                    infoFiltered: "(filtered from _MAX_ total employees)"
                }
            });
        }
    });

    $(document).on('click', '.remove-employee-btn', function() {
        var empId = $(this).data('emp-id');
        var unitId = $(this).data('unit-id');
        var employeeName = $(this).closest('tr').find('td:nth-child(2)').text();
        
        Swal.fire({
            title: 'Remove Employee?',
            text: 'Are you sure you want to remove ' + employeeName + ' from this unit?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'update_unit_employee.php',
                    method: 'POST',
                    dataType: 'json', // Expect JSON response
                    data: {
                        action: 'remove',
                        emp_id: empId,
                        unit_id: unitId
                    },
                    success: function(response) {
                        // No need to parse JSON since we're using dataType: 'json'
                        if (response.success) {
                            Swal.fire(
                                'Removed!',
                                employeeName + ' has been removed from the unit.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire(
                            'Error!',
                            'Failed to remove employee: ' + error,
                            'error'
                        );
                    }
                });
            }
        });
    });
});
</script>
<!-- DataTables Initialization -->
<script>
$('#sectable').DataTable({
    responsive: true,
    autoWidth: false,
    pageLength: 10,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
});
</script>
<script>
// Initialize Select2 for secretaries dropdown
$('.select2').select2({
    placeholder: "-- Please Select --",
    allowClear: true,
    maximumSelectionLength: 1
});
$('.select2-sec').select2({
    placeholder: "-- Please Select --",
    allowClear: true
});
// Initialize Select2 for unit employees dropdown
$('.select2-unit-employees').select2({
    placeholder: "Select employees...",
    allowClear: true
});
</script>
<script>
    // Add this to your existing JavaScript section
    $(document).on('click', '.remove-manager-btn', function() {
        var empId = $(this).data('emp-id');
        var employeeName = $(this).closest('tr').find('td:nth-child(2)').text();
        
        Swal.fire({
            title: 'Remove from Manager\'s Office?',
            text: 'Are you sure you want to remove ' + employeeName + ' from the Manager\'s Office?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'update_manager_status.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        emp_id: empId,
                        is_manager: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Removed!',
                                employeeName + ' has been removed from the Manager\'s Office.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire(
                            'Error!',
                            'Failed to remove manager: ' + error,
                            'error'
                        );
                    }
                });
            }
        });
    });

    // Initialize DataTable for managers table
    $('#managersTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: [0, -1] }
        ]
    });
</script>
</body>
</html>