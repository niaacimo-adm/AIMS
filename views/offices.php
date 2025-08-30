<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

function validateOfficeManager($db, $emp_id, $current_office_id = null) {
    if (empty($emp_id)) {
        return false; // No employee selected, so no validation needed
    }

    // Check if employee is already a section or unit head
    $stmt = $db->prepare("SELECT COUNT(*) FROM section WHERE head_emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $is_section_head = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM unit_section WHERE head_emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $is_unit_head = $stmt->get_result()->fetch_row()[0];
    
    if ($is_section_head > 0 || $is_unit_head > 0) {
        return "This employee is already assigned as a section or unit head.";
    }
    
    // Check if employee is already a manager elsewhere (excluding current office if editing)
    $query = "SELECT COUNT(*) FROM office WHERE manager_emp_id = ?";
    $params = [$emp_id];
    $types = "i";
    
    if ($current_office_id !== null) {
        $query .= " AND office_id != ?";
        $params[] = $current_office_id;
        $types .= "i";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $is_manager_elsewhere = $stmt->get_result()->fetch_row()[0];
    
    if ($is_manager_elsewhere > 0) {
        return "This employee is already a manager of another office.";
    }
    
    return false;
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common variables for both add and update
    $manager_emp_id = !empty($_POST['manager_emp_id']) ? $_POST['manager_emp_id'] : null;
    $office_name = trim($_POST['office_name'] ?? '');
    $office_address = $_POST['office_address'] ?? '';
    $is_main_office = isset($_POST['is_main_office']) ? 1 : 0;
    $parent_office_id = $_POST['parent_office_id'] ?? null;
    
    // Validate main office selection
    if ($is_main_office && $parent_office_id) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => 'A main office cannot have a parent office.'
        ];
        header("Location: offices.php");
        exit();
    }
    
    // Validate manager assignment if provided
    if ($manager_emp_id) {
        $current_office_id = $_POST['id'] ?? null; // Only exists for update
        $validation = validateOfficeManager($db, $manager_emp_id, $current_office_id);
        if ($validation) {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => $validation
            ];
            header("Location: offices.php");
            exit();
        }
    }
    
    // Add new office
    if (isset($_POST['add_office'])) {
        if (!empty($office_name)) {
            // Convert empty parent_office_id to NULL
            $parent_office_id = !empty($parent_office_id) ? $parent_office_id : null;
            
            $stmt = $db->prepare("INSERT INTO office (office_name, office_address, manager_emp_id, is_main_office, parent_office_id) 
                                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $office_name, $office_address, $manager_emp_id, $is_main_office, $parent_office_id);
            
            if ($stmt->execute()) {
                // Update is_manager flag for the employee
                if ($manager_emp_id) {
                    $stmt = $db->prepare("UPDATE employee SET is_manager = 1 WHERE emp_id = ?");
                    $stmt->bind_param("i", $manager_emp_id);
                    $stmt->execute();
                }
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Office added successfully!'
                ];
                header("Location: offices.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add office: ' . $db->error
                ];
            }
        }
    }
    // Update office
    if (isset($_POST['update_status'])) {
        $id = $_POST['id'];
        
        if (!empty($office_name)) {
            // Convert empty parent_office_id to NULL
            $parent_office_id = !empty($parent_office_id) ? $parent_office_id : null;
            
            // Get current office data
            $stmt = $db->prepare("SELECT manager_emp_id FROM office WHERE office_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $current_office = $stmt->get_result()->fetch_assoc();
            
            // Clear previous manager flag if manager changed
            if ($manager_emp_id != $current_office['manager_emp_id'] && $current_office['manager_emp_id']) {
                $stmt = $db->prepare("UPDATE employee SET is_manager = 0 WHERE emp_id = ?");
                $stmt->bind_param("i", $current_office['manager_emp_id']);
                $stmt->execute();
            }
            
            $stmt = $db->prepare("UPDATE office SET office_name = ?, office_address = ?, manager_emp_id = ?, 
                                is_main_office = ?, parent_office_id = ? WHERE office_id = ?");
            $stmt->bind_param("ssiiii", $office_name, $office_address, $manager_emp_id, $is_main_office, $parent_office_id, $id);
            
            if ($stmt->execute()) {
                // Update new manager flag
                if ($manager_emp_id) {
                    $stmt = $db->prepare("UPDATE employee SET is_manager = 1 WHERE emp_id = ?");
                    $stmt->bind_param("i", $manager_emp_id);
                    $stmt->execute();
                }
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Office updated successfully!'
                ];
                header("Location: offices.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update office: ' . $db->error
                ];
            }
        }
    }

    // Validate parent office selection
    if (isset($_POST['has_parent_office']) && $_POST['has_parent_office'] && empty($_POST['parent_office_id'])) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => 'Please select a parent office when the checkbox is checked.'
        ];
        header("Location: offices.php");
        exit();
    }

    // Validate that parent office is not a main office
    if (!empty($_POST['parent_office_id'])) {
        $stmt = $db->prepare("SELECT is_main_office FROM office WHERE office_id = ?");
        $stmt->bind_param("i", $_POST['parent_office_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent_office = $result->fetch_assoc();
        
        if ($parent_office && $parent_office['is_main_office'] == 0) {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'The selected parent office must be a main office.'
            ];
            header("Location: offices.php");
            exit();
        }
    }
}

// Fetch all offices with their parent office names
$query = "SELECT o.*, parent.office_name as parent_office_name 
          FROM office o
          LEFT JOIN office parent ON o.parent_office_id = parent.office_id
          ORDER BY o.is_main_office DESC, o.office_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$offices = [];
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

// Fetch main offices for parent office dropdown
$main_offices = array_filter($offices, function($office) {
    return $office['is_main_office'] == 1;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Appointment Statuses</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .input-group-text input[type="checkbox"] {
          margin: 0;
          cursor: pointer;
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
            <h1>Appointment Statuses</h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Add New Office</h3>
                </div>
                <form method="POST">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="office_name">Office Name</label>
                            <input type="text" class="form-control" id="office_name" name="office_name" required>
                        </div>
                        <div class="form-group">
                            <label for="office_address">Office Address</label>
                            <input type="text" class="form-control" id="office_address" name="office_address" required>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_main_office" name="is_main_office" value="1">
                                <label class="custom-control-label" for="is_main_office">Main Office</label>
                            </div>
                        </div>
                        <div class="form-group" id="parent_office_group">
                            <label for="parent_office_id">Parent Office</label>
                            <div class="input-group">
                                <select class="form-control" id="parent_office_id" name="parent_office_id">
                                    <option value="">-- Select Parent Office --</option>
                                    <?php foreach ($main_offices as $office): ?>
                                        <option value="<?= $office['office_id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <input type="checkbox" id="has_parent_office" name="has_parent_office" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="manager_emp_id">Office Manager</label>
                            <select class="form-control select2-manager" id="manager_emp_id" name="manager_emp_id" multiple="multiple">
                                <option value="">-- Select Manager --</option>
                                <?php 
                                // Fetch employees who aren't already managers or heads elsewhere
                                $stmt = $db->prepare("SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as full_name 
                                                    FROM employee e
                                                    LEFT JOIN section s ON e.emp_id = s.head_emp_id
                                                    LEFT JOIN unit_section us ON e.emp_id = us.head_emp_id
                                                    WHERE (e.is_manager = 0 AND s.head_emp_id IS NULL AND us.head_emp_id IS NULL)
                                                    OR e.emp_id = ?");
                                $current_manager = $office['manager_emp_id'] ?? 0;
                                $stmt->bind_param("i", $current_manager);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $available_managers = [];
                                while ($row = $result->fetch_assoc()) {
                                    $available_managers[] = $row;
                                }
                                
                                foreach ($available_managers as $manager): ?>
                                    <option value="<?= $manager['emp_id'] ?>">
                                        <?= htmlspecialchars($manager['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="add_office" class="btn btn-primary">Add Office</button>
                    </div>
                </form>
            </div>
          </div>
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Office Table</h3>
              </div>
              <div class="card-body">
                <form method="POST">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Office Name</th>
                                <th>Office Address</th>
                                <th>Designation Office</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offices as $office): ?>
                            <tr>
                                <td><?= htmlspecialchars($office['office_name']) ?></td>
                                <td><?= htmlspecialchars($office['office_address']) ?></td>
                                <td>
                                    <?php if ($office['is_main_office'] == 1): ?>
                                        <span class="badge badge-success">Main Office</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Sub-Office</span>
                                        <?php if ($office['parent_office_name']): ?>
                                            <br><small class="text-muted"> <?= htmlspecialchars($office['parent_office_name']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info" data-toggle="modal" 
                                                data-target="#editModal<?= $office['office_id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger delete-btn" 
                                                data-id="<?= $office['office_id'] ?>" 
                                                data-name="<?= htmlspecialchars($office['office_name']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="modal fade" id="editModal<?= $office['office_id'] ?>">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h4 class="modal-title">Edit Office</h4>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?= $office['office_id'] ?>">
                                                        <div class="form-group">
                                                            <label>Office Name</label>
                                                            <input type="text" class="form-control" name="office_name" 
                                                                  value="<?= htmlspecialchars($office['office_name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Office Address</label>
                                                            <input type="text" class="form-control" name="office_address" 
                                                                  value="<?= htmlspecialchars($office['office_address']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="is_main_office_<?= $office['office_id'] ?>" 
                                                                      name="is_main_office" value="1" <?= $office['is_main_office'] ? 'checked' : '' ?>>
                                                                <label class="custom-control-label" for="is_main_office_<?= $office['office_id'] ?>">Main Office</label>
                                                            </div>
                                                        </div>
                                                        <div class="form-group" id="parent_office_group_<?= $office['office_id'] ?>" style="<?= $office['is_main_office'] ? 'display: none;' : '' ?>">
                                                            <label>Parent Office</label>
                                                            <div class="input-group">
                                                                <select class="form-control" name="parent_office_id">
                                                                    <option value="">-- Select Parent Office --</option>
                                                                    <?php foreach ($main_offices as $main_office): 
                                                                        if ($main_office['office_id'] == $office['office_id']) continue; ?>
                                                                        <option value="<?= $main_office['office_id'] ?>" 
                                                                            <?= $main_office['office_id'] == $office['parent_office_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($main_office['office_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <div class="input-group-append">
                                                                    <div class="input-group-text">
                                                                        <input type="checkbox" id="has_parent_office_<?= $office['office_id'] ?>" name="has_parent_office" value="1" <?= $office['parent_office_id'] ? 'checked' : '' ?>>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Office Manager</label>
                                                            <select class="form-control select2-manager" name="manager_emp_id" multiple="multiple">
                                                                <option value="">-- Select Manager --</option>
                                                                <?php foreach ($available_managers as $manager): ?>
                                                                    <option value="<?= $manager['emp_id'] ?>" 
                                                                        <?= $manager['emp_id'] == $office['manager_emp_id'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($manager['full_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Save changes</button>
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

<script>
$(document).ready(function() {
$('#is_main_office').change(function() {
    if ($(this).is(':checked')) {
        $('#parent_office_group').hide();
        $('#parent_office_id').val('');
        $('#has_parent_office').prop('checked', false);
    } else {
        $('#parent_office_group').show();
    }
});

// For edit modals
$('[id^="is_main_office_"]').change(function() {
    const officeId = this.id.split('_')[2];
    if ($(this).is(':checked')) {
        $('#parent_office_group_' + officeId).hide();
        $('#parent_office_group_' + officeId + ' select').val('');
        $('#has_parent_office_' + officeId).prop('checked', false);
    } else {
        $('#parent_office_group_' + officeId).show();
    }
});

// Toggle parent office selection
$('[id^="has_parent_office"]').change(function() {
    const checkbox = $(this);
    const select = checkbox.closest('.input-group').find('select');
    
    if (checkbox.is(':checked')) {
        select.prop('required', true);
    } else {
        select.prop('required', false);
        select.val('');
    }
});
    
    // Initialize select2 for manager selection
    $('.select2-manager').select2({
        placeholder: "-- Select Manager --",
        allowClear: true,
        width: '100%',
        maximumSelectionLength: 1
    });
});
</script>
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

<!-- Delete Confirmation Script -->
<script>
$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const officeId = $(this).data('id');
    const statusName = $(this).data('name');
    
            // Then show the delete confirmation
            Swal.fire({
                title: 'Delete Status?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_office.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `office_id=${officeId}`
                    })
                    .then(response => response.json())
                    .catch(error => {
                        Swal.showValidationMessage('Request failed');
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const response = result.value;
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Delete',
                            html: `
                                <p>${response.message}</p>
                                ${response.employeeCount > 0 ? 
                                  '<div class="mt-3"><i class="fas fa-users mr-2"></i>Assigned to: ' + 
                                  response.employeeCount + ' employee(s)</div>' : ''}
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        });
                    }
                }
            });
});
</script>
<!-- DataTables Initialization -->
<script>
  
$(function () {
    $("#example1").DataTable({
        responsive: true,
        lengthChange: true, // Changed to true to show length menu
        autoWidth: false,
        pageLength: 5, // Default number of rows per page
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]], // Pagination options
        columnDefs: [
            { responsivePriority: 1, targets: 1 }, // Picture
            { responsivePriority: 2, targets: 2 }, // Name
            { responsivePriority: 3, targets: -1 } // Actions
        ],
        dom: '<"top"lf>rt<"bottom"ip>', // Layout control
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: {
                previous: "&laquo;",
                next: "&raquo;"
            }
        }
    });
});

</script>
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
    
    // Immediately clear the session after showing
    fetch('clear_session.php')
        .then(response => response.text())
        .then(data => console.log('Session cleared'))
        .catch(error => console.error('Error clearing session:', error));
});
</script>
<?php endif; ?>
</body>
</html>