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

$total_offices = count($offices);
$main_count    = count($main_offices);
$sub_count     = $total_offices - $main_count;

// Employees who can be picked as a manager (not already a manager / section head / unit head)
$stmt = $db->prepare("SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as full_name
                      FROM employee e
                      LEFT JOIN section s ON e.emp_id = s.head_emp_id
                      LEFT JOIN unit_section us ON e.emp_id = us.head_emp_id
                      WHERE e.is_manager = 0 AND s.head_emp_id IS NULL AND us.head_emp_id IS NULL
                      ORDER BY e.first_name, e.last_name");
$stmt->execute();
$result = $stmt->get_result();
$available_managers = [];
while ($row = $result->fetch_assoc()) {
    $available_managers[$row['emp_id']] = $row;
}

// Current managers, so each edit modal can keep its own office's manager selectable
$stmt = $db->prepare("SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as full_name
                      FROM employee e
                      WHERE e.emp_id IN (SELECT manager_emp_id FROM office WHERE manager_emp_id IS NOT NULL)");
$stmt->execute();
$result = $stmt->get_result();
$current_managers = [];
while ($row = $result->fetch_assoc()) {
    $current_managers[$row['emp_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Offices</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  <div class="content-wrapper">
    <div class="pg-hero">
        <div class="pg-hero-mesh"></div>
        <div class="pg-hero-dots"></div>
        <div class="pg-hero-hex"></div>
        <div class="pg-hero-orbs">
            <div class="pg-orb pg-orb-1"></div>
            <div class="pg-orb pg-orb-2"></div>
            <div class="pg-orb pg-orb-3"></div>
            <div class="pg-orb pg-orb-4"></div>
        </div>
        <div class="pg-hero-rings">
            <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
        </div>
        <div class="pg-hero-arc"></div>
        <div class="pg-hero-layout">
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-building"></i>Offices</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Manage office information</p>
            </div>
        </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Manage Office Table</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <button type="button" class="mod-chip active" data-filter="">All<span class="n"><?= $total_offices ?></span></button>
                <button type="button" class="mod-chip" data-filter="Main Office">Main<span class="n"><?= $main_count ?></span></button>
                <button type="button" class="mod-chip" data-filter="Sub-Office">Sub-Office<span class="n"><?= $sub_count ?></span></button>
              </div>
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal">
                <i class="fas fa-plus"></i> New Office
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="example1" class="table table-striped mod-table">
              <thead>
                <tr>
                  <th>Office Name</th>
                  <th>Office Address</th>
                  <th>Designation Office</th>
                  <th style="width:110px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($offices as $office): ?>
                <tr>
                  <td><span class="mod-title"><?= htmlspecialchars($office['office_name']) ?></span></td>
                  <td><span class="mod-desc"><?= htmlspecialchars($office['office_address']) ?></span></td>
                  <td>
                    <?php if ($office['is_main_office'] == 1): ?>
                      <span class="badge badge-success">Main Office</span>
                    <?php else: ?>
                      <span class="badge badge-info">Sub-Office</span>
                      <?php if ($office['parent_office_name']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($office['parent_office_name']) ?></small>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-warning" title="Edit"
                            data-toggle="modal" data-target="#editModal<?= (int)$office['office_id'] ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete"
                            data-id="<?= (int)$office['office_id'] ?>"
                            data-name="<?= htmlspecialchars($office['office_name']) ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add modal -->
<div class="modal fade scrum-style-modal" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle"></i>New Office</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="office_name">Office Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="office_name" name="office_name" required>
          </div>
          <div class="form-group">
            <label for="office_address">Office Address <span class="text-danger">*</span></label>
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
                <?php foreach ($main_offices as $mo): ?>
                  <option value="<?= (int)$mo['office_id'] ?>"><?= htmlspecialchars($mo['office_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="input-group-append">
                <div class="input-group-text">
                  <input type="checkbox" id="has_parent_office" name="has_parent_office" value="1">
                </div>
              </div>
            </div>
          </div>
          <div class="form-group mb-0">
            <label for="manager_emp_id">Office Manager</label>
            <select class="form-control select2-manager" id="manager_emp_id" name="manager_emp_id" multiple="multiple">
              <option value="">-- Select Manager --</option>
              <?php foreach ($available_managers as $manager): ?>
                <option value="<?= (int)$manager['emp_id'] ?>"><?= htmlspecialchars($manager['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_office" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Add Office</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit modals -->
<?php foreach ($offices as $office):
    $oid = (int)$office['office_id'];
    $manager_options = $available_managers;
    if (!empty($office['manager_emp_id']) && isset($current_managers[$office['manager_emp_id']])) {
        $manager_options[$office['manager_emp_id']] = $current_managers[$office['manager_emp_id']];
    }
?>
<div class="modal fade scrum-style-modal" id="editModal<?= $oid ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Office</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= $oid ?>">
          <div class="form-group">
            <label>Office Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="office_name"
                   value="<?= htmlspecialchars($office['office_name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Office Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="office_address"
                   value="<?= htmlspecialchars($office['office_address']) ?>" required>
          </div>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="is_main_office_<?= $oid ?>"
                     name="is_main_office" value="1" <?= $office['is_main_office'] ? 'checked' : '' ?>>
              <label class="custom-control-label" for="is_main_office_<?= $oid ?>">Main Office</label>
            </div>
          </div>
          <div class="form-group" id="parent_office_group_<?= $oid ?>" style="<?= $office['is_main_office'] ? 'display: none;' : '' ?>">
            <label>Parent Office</label>
            <div class="input-group">
              <select class="form-control" name="parent_office_id">
                <option value="">-- Select Parent Office --</option>
                <?php foreach ($main_offices as $main_office):
                    if ($main_office['office_id'] == $office['office_id']) continue; ?>
                  <option value="<?= (int)$main_office['office_id'] ?>"
                    <?= $main_office['office_id'] == $office['parent_office_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($main_office['office_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="input-group-append">
                <div class="input-group-text">
                  <input type="checkbox" id="has_parent_office_<?= $oid ?>" name="has_parent_office" value="1" <?= $office['parent_office_id'] ? 'checked' : '' ?>>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group mb-0">
            <label>Office Manager</label>
            <select class="form-control select2-manager" name="manager_emp_id" multiple="multiple">
              <option value="">-- Select Manager --</option>
              <?php foreach ($manager_options as $manager): ?>
                <option value="<?= (int)$manager['emp_id'] ?>"
                  <?= $manager['emp_id'] == $office['manager_emp_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($manager['full_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_status" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>

<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: <?= json_encode($_SESSION['swal']['type']) ?>,
        title: <?= json_encode($_SESSION['swal']['title']) ?>,
        text: <?= json_encode($_SESSION['swal']['text']) ?>,
        showConfirmButton: true,
        confirmButtonColor: '#2a9863',
        timer: 3000
    });
});
</script>
<?php unset($_SESSION['swal']); endif; ?>

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

    // Edit modals
    $('[id^="is_main_office_"]').change(function() {
        const officeId = this.id.split('_')[3];
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

    // Select2 manager picker (rendered inside its modal so search/focus work)
    $('.select2-manager').each(function() {
        $(this).select2({
            placeholder: "-- Select Manager --",
            allowClear: true,
            width: '100%',
            maximumSelectionLength: 1,
            dropdownParent: $(this).closest('.modal')
        });
    });

    var table = $("#example1").DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 5,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: 2 },
            { responsivePriority: 3, targets: -1 },
            { orderable: false, targets: -1 }
        ],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: { previous: "&laquo;", next: "&raquo;" }
        }
    });

    // Designation filter chips
    $('.mod-chip[data-filter]').on('click', function() {
        $('.mod-chip[data-filter]').removeClass('active');
        $(this).addClass('active');
        var f = $(this).data('filter');
        table.column(2).search(f ? '^\\s*' + f : '', true, false).draw();
    });
});

$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const officeId = $(this).data('id');
    const officeName = $(this).data('name');

    Swal.fire({
        title: 'Delete Office?',
        text: `Are you sure you want to delete "${officeName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('delete_office.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `office_id=${officeId}`
            })
            .then(response => response.json())
            .catch(error => { Swal.showValidationMessage('Request failed'); });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const response = result.value;
            if (response.success) {
                Swal.fire({
                    icon: 'success', title: 'Deleted!', text: response.message,
                    timer: 2000, showConfirmButton: false
                }).then(() => { window.location.reload(); });
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
                    confirmButtonColor: '#2a9863',
                    confirmButtonText: 'OK'
                });
            }
        }
    });
});
</script>
</body>
</html>