<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Function to check if employee is already assigned as Manager's Office staff
function isEmployeeAlreadyManagerStaff($db, $emp_id, $current_assignment_id = null) {
    // Convert null values to 0 for the queries
    $current_assignment_id = $current_assignment_id ?? 0;
    
    // Check if employee is already assigned as Manager's Office staff
    $stmt = $db->prepare("SELECT COUNT(*) FROM managers_office_staff WHERE emp_id = ? AND id != ?");
    $stmt->bind_param("ii", $emp_id, $current_assignment_id);
    $stmt->execute();
    $is_assigned = $stmt->get_result()->fetch_row()[0];
    
    if ($is_assigned > 0) {
        return "This employee is already assigned as Manager's Office staff.";
    }
    
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new Manager's Office staff
    if (isset($_POST['add_manager_staff'])) {
        $emp_id = $_POST['emp_id'] ?? null;
        $position = trim($_POST['position']);
        $responsibilities = trim($_POST['responsibilities']);
        
        if (!empty($emp_id)) {
            // Validate assignment
            $validation = isEmployeeAlreadyManagerStaff($db, $emp_id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: managers_office.php");
                exit();
            }
            
            $stmt = $db->prepare("INSERT INTO managers_office_staff (emp_id, position, responsibilities) VALUES (?, ?, ?)");
            if ($stmt->execute([$emp_id, $position, $responsibilities])) {
                // Add notification
                $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                        VALUES (?, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change')");
                $stmt_notif->execute([$emp_id]);
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Manager\'s Office staff added successfully!'
                ];
                header("Location: managers_office.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add Manager\'s Office staff.'
                ];
            }
        }
    }
    
    // Update Manager's Office staff
    if (isset($_POST['update_manager_staff'])) {
        $id = $_POST['id'];
        $emp_id = $_POST['emp_id'] ?? null;
        $position = trim($_POST['position']);
        $responsibilities = trim($_POST['responsibilities']);
        
        if (!empty($emp_id)) {
            // Validate assignment
            $validation = isEmployeeAlreadyManagerStaff($db, $emp_id, $id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: managers_office.php");
                exit();
            }
            
            $stmt = $db->prepare("UPDATE managers_office_staff SET emp_id = ?, position = ?, responsibilities = ? WHERE id = ?");
            if ($stmt->execute([$emp_id, $position, $responsibilities, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Manager\'s Office staff updated successfully!'
                ];
                header("Location: managers_office.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update Manager\'s Office staff.'
                ];
            }
        }
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    $id = $_GET['id'];
    
    try {
        // First get the employee ID to send notification
        $stmt = $db->prepare("SELECT emp_id FROM managers_office_staff WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
        
        if ($staff) {
            // Add notification
            $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                    VALUES (?, 'Role Change', 'You have been removed from Manager\'s Office Staff', 'role_change')");
            $stmt_notif->execute([$staff['emp_id']]);
        }
        
        // Now delete the record
        $stmt = $db->prepare("DELETE FROM managers_office_staff WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Manager\'s Office staff removed successfully!'
            ];
        } else {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to remove Manager\'s Office staff.'
            ];
        }
    } catch (Exception $e) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => $e->getMessage()
        ];
    }
    
    header("Location: managers_office.php");
    exit();
}

// Fetch all Manager's Office staff with more details
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
          ORDER BY mos.position";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager_staff = [];
while ($row = $result->fetch_assoc()) {
    $manager_staff[] = $row;
}

// Fetch all employees for dropdown
$query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name 
          FROM employee 
          WHERE emp_id NOT IN (SELECT emp_id FROM managers_office_staff)
          ORDER BY first_name, last_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Manager's Office Staff</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .employee-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
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
    .manager-badge {
        background-color: #28a745;
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: bold;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
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
            <h1>Manager's Office Staff</h1>
          </div>
          <div class="col-sm-6">
            <button type="button" class="btn btn-success float-right" data-toggle="modal" data-target="#addManagerModal">
              <i class="fas fa-user-plus"></i> Add Staff
            </button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Current Manager's Office Staff</h3>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table id="managersTable" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Office</th>
                        <th>Responsibilities</th>
                        <th>Contact</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($manager_staff as $staff): ?>
                      <tr>
                        <td>
                          <?php if (!empty($staff['employee_picture']) && file_exists("../dist/img/employees/" . $staff['employee_picture'])): ?>
                            <img src="../dist/img/employees/<?= htmlspecialchars($staff['employee_picture']) ?>" 
                                class="employee-avatar" 
                                alt="<?= htmlspecialchars($staff['employee_name']) ?>">
                          <?php else: ?>
                            <div class="default-avatar">
                              <i class="fas fa-user"></i>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?= htmlspecialchars($staff['employee_name']) ?>
                          <?php if (!empty($staff['employee_position'])): ?>
                            <div class="text-muted small"><?= htmlspecialchars($staff['employee_position']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="manager-badge"><?= htmlspecialchars($staff['position']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($staff['employee_office'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($staff['responsibilities']) ?></td>
                        <td>
                          <?php if (!empty($staff['employee_email'])): ?>
                            <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($staff['employee_email']) ?></div>
                          <?php endif; ?>
                          <?php if (!empty($staff['employee_phone'])): ?>
                            <div><i class="fas fa-phone"></i> <?= htmlspecialchars($staff['employee_phone']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                          <div class="btn-group">
                            <a href="emp.profile.php?emp_id=<?= $staff['emp_id'] ?>" 
                              class="btn btn-sm btn-primary" title="View Profile">
                              <i class="fas fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" 
                                    data-target="#editModal<?= $staff['id'] ?>" title="Edit">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" 
                                    data-id="<?= $staff['id'] ?>" 
                                    data-name="<?= htmlspecialchars($staff['employee_name']) ?>"
                                    title="Remove">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      
                      <!-- Edit Modal -->
                      <div class="modal fade" id="editModal<?= $staff['id'] ?>">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h4 class="modal-title">Edit Manager's Office Staff</h4>
                              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <form method="POST">
                              <div class="modal-body">
                                <input type="hidden" name="id" value="<?= $staff['id'] ?>">
                                <div class="form-group">
                                  <label>Employee</label>
                                  <select class="form-control select2" name="emp_id" multiple="multiple" required>
                                    <?php foreach ($employees as $employee): ?>
                                      <option value="<?= $employee['emp_id'] ?>" 
                                        <?= $employee['emp_id'] == $staff['emp_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($employee['full_name']) ?>
                                      </option>
                                    <?php endforeach; ?>
                                    <!-- Include current employee even if they're in the managers_office_staff table -->
                                    <option value="<?= $staff['emp_id'] ?>" selected>
                                      <?= htmlspecialchars($staff['employee_name']) ?> (Current)
                                    </option>
                                  </select>
                                </div>
                                <div class="form-group">
                                  <label>Position in Manager's Office</label>
                                  <input type="text" class="form-control" name="position" 
                                         value="<?= htmlspecialchars($staff['position']) ?>" required>
                                </div>
                                <div class="form-group">
                                  <label>Responsibilities</label>
                                  <textarea class="form-control" name="responsibilities" rows="3"><?= htmlspecialchars($staff['responsibilities']) ?></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                <button type="submit" name="update_manager_staff" class="btn btn-primary">Save changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  
  <!-- Add Manager Modal -->
  <div class="modal fade" id="addManagerModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Add to Manager's Office</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label>Employee</label>
              <select class="form-control select2" name="emp_id" multiple="multiple" required>
                <option value="">-- Select Employee --</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= $employee['emp_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Position in Manager's Office</label>
              <input type="text" class="form-control" name="position" required>
            </div>
            <div class="form-group">
              <label>Responsibilities</label>
              <textarea class="form-control" name="responsibilities" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" name="add_manager_staff" class="btn btn-primary">Add Staff</button>
          </div>
        </form>
      </div>
    </div>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#managersTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: [0, -1] }
        ]
    });

    // Initialize Select2
    $('.select2').select2({
        placeholder: "-- Select Employee --",
        allowClear: true
    });
    
    // Delete button handler
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        Swal.fire({
            title: 'Remove Staff?',
            text: `Are you sure you want to remove ${name} from Manager's Office?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `managers_office.php?delete=1&id=${id}`;
            }
        });
    });
});
</script>
</body>
</html>