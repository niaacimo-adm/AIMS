<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

// Check permissions
checkPermission('manage_users');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $roleId = (int)$_POST['role_id'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $employeeId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        
        if (!empty($username)) {
            $stmt = $db->prepare("INSERT INTO users (user, role_id, password, employee_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $roleId, $password, $employeeId])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'User added successfully!'
                ];
                header("Location: users.php");
                exit();
            }
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $userId = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $roleId = (int)$_POST['role_id'];
        $employeeId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        
        // Only update password if provided
        $updatePassword = !empty($_POST['password']);
        $passwordSet = $updatePassword ? ", password = ?" : "";
        
        $query = "UPDATE users SET user = ?, role_id = ?, employee_id = ?" . $passwordSet . " WHERE id = ?";
        $stmt = $db->prepare($query);
        
        $params = [$username, $roleId, $employeeId];
        if ($updatePassword) {
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        $params[] = $userId;
        
        if ($stmt->execute($params)) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'User updated successfully!'
            ];
            header("Location: users.php");
            exit();
        }
    }
}

// Get all users with their roles and employee info
$users = $db->query("
    SELECT u.id, u.user, u.employee_id, r.name as role_name, r.id as role_id,
           e.first_name, e.last_name, e.picture
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.id
    LEFT JOIN employee e ON u.employee_id = e.emp_id
    ORDER BY u.user
")->fetch_all(MYSQLI_ASSOC);

// Get all roles for dropdown
$roles = getAllRoles();

// Get all employees not yet assigned to users
$availableEmployees = $db->query("
    SELECT e.emp_id, e.first_name, e.last_name, e.picture
    FROM employee e
    LEFT JOIN users u ON e.emp_id = u.employee_id
    WHERE u.id IS NULL
    ORDER BY e.last_name, e.first_name
")->fetch_all(MYSQLI_ASSOC);

// If editing, load that user's record and make sure their currently
// assigned employee (excluded from $availableEmployees above) is
// still selectable in the dropdown.
$editUser = null;
$employeeOptions = $availableEmployees;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ($u['id'] == $editId) {
            $editUser = $u;
            break;
        }
    }
    if ($editUser && $editUser['employee_id'] && $editUser['first_name']) {
        $alreadyListed = false;
        foreach ($employeeOptions as $opt) {
            if ($opt['emp_id'] == $editUser['employee_id']) { $alreadyListed = true; break; }
        }
        if (!$alreadyListed) {
            $employeeOptions[] = [
                'emp_id'     => $editUser['employee_id'],
                'first_name' => $editUser['first_name'],
                'last_name'  => $editUser['last_name'],
                'picture'    => $editUser['picture'],
            ];
        }
    }
}
$isEditing = $editUser !== null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-ACIMO | User Management</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
  <style>
    .badge-light { background:var(--h-surface3) !important; color:var(--h-muted) !important; border:1px solid var(--h-border); }
    .mod-avatar { width:30px; height:30px; object-fit:cover; border-radius:50%; margin-right:8px; border:1px solid var(--h-border); }
  </style>
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
                <div class="pg-hero-title"><i class="fas fa-users-cog"></i>User Management</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Create, edit and control system user accounts.</p>
            </div>
        </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Manage Users</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <span class="mod-chip active" style="cursor:default">Total<span class="n"><?= count($users) ?></span></span>
              </div>
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal">
                <i class="fas fa-user-plus"></i> New User
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="usersTable" class="table table-striped mod-table">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Employee</th>
                  <th style="width:110px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                  <td><span class="mod-title"><?= htmlspecialchars($user['user']) ?></span></td>
                  <td>
                    <span class="badge <?=
                        $user['role_name'] === 'Administrator' ? 'badge-danger' :
                        ($user['role_name'] === 'Employee' ? 'badge-warning' :
                        ($user['role_name'] === 'Focal Person' ? 'badge-light' :
                        ($user['role_name'] === 'Manager' ? 'badge-success' : 'badge-primary')))
                    ?>">
                      <?= htmlspecialchars($user['role_name'] ?? 'No role') ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($user['employee_id'] && $user['first_name']): ?>
                      <div class="d-flex align-items-center">
                        <?php if ($user['picture']): ?>
                        <img src="../dist/img/employees/<?= htmlspecialchars($user['picture']) ?>" class="mod-avatar" alt="">
                        <?php endif; ?>
                        <span><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-warning" title="Edit"
                            data-toggle="modal" data-target="#editModal<?= (int)$user['id'] ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete"
                            data-id="<?= (int)$user['id'] ?>"
                            data-name="<?= htmlspecialchars($user['user']) ?>">
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

<?php $add_employee_options = $availableEmployees; ?>
<!-- Add modal -->
<div class="modal fade scrum-style-modal" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-plus"></i>New User</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="add_username">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add_username" name="username" value="<?= '' ?>" required>
          </div>
          <div class="form-group">
            <label for="add_role">Role <span class="text-danger">*</span></label>
            <select id="add_role" name="role_id" class="form-control" required>
              <?php foreach ($roles as $role): ?>
              <option value="<?= $role['id'] ?>" ><?= htmlspecialchars($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="add_employee">Assign to Employee (Optional)</label>
            <select id="add_employee" name="employee_id" class="form-control select2-unit-employees">
              <option value="">-- Select Employee --</option>
              <?php foreach ($add_employee_options as $employee): ?>
              <option value="<?= $employee['emp_id'] ?>" ><?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label for="add_password">Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="add_password" name="password" autocomplete="new-password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit modals -->
<?php foreach ($users as $user):
    // available employees plus this user's current one (otherwise excluded from the list)
    $user_employee_options = $availableEmployees;
    if ($user['employee_id'] && $user['first_name']) {
        $user_employee_options[] = [
            'emp_id'     => $user['employee_id'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'picture'    => $user['picture'],
        ];
    }
?>
<div class="modal fade scrum-style-modal" id="editModal<?= (int)$user['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-edit"></i>Edit User</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <div class="form-group">
            <label for="edit<?= (int)$user['id'] ?>_username">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit<?= (int)$user['id'] ?>_username" name="username" value="<?= htmlspecialchars($user['user']) ?>" required>
          </div>
          <div class="form-group">
            <label for="edit<?= (int)$user['id'] ?>_role">Role <span class="text-danger">*</span></label>
            <select id="edit<?= (int)$user['id'] ?>_role" name="role_id" class="form-control" required>
              <?php foreach ($roles as $role): ?>
              <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="edit<?= (int)$user['id'] ?>_employee">Assign to Employee (Optional)</label>
            <select id="edit<?= (int)$user['id'] ?>_employee" name="employee_id" class="form-control select2-unit-employees">
              <option value="">-- Select Employee --</option>
              <?php foreach ($user_employee_options as $employee): ?>
              <option value="<?= $employee['emp_id'] ?>" <?= $user['employee_id'] == $employee['emp_id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label for="edit<?= (int)$user['id'] ?>_password">New Password</label>
            <input type="password" class="form-control" id="edit<?= (int)$user['id'] ?>_password" name="password" autocomplete="new-password">
            <small class="text-muted">Leave blank to keep the current password.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_user" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Changes</button>
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
$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const userId = $(this).data('id');
    const user = $(this).data('name');

    Swal.fire({
        title: 'Delete User?',
        text: `Are you sure you want to delete "${user}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            })
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok'); }
                return response.json();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
                return false;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value && result.value.success) {
                Swal.fire({
                    icon: 'success', title: 'Deleted!', text: result.value.message,
                    timer: 2000, showConfirmButton: false
                }).then(() => { window.location.reload(); });
            } else {
                Swal.fire({
                    icon: 'error', title: 'Error',
                    text: result.value?.message || 'Unknown error occurred',
                    showConfirmButton: true, confirmButtonColor: '#2a9863'
                });
            }
        }
    });
});

$(function () {
    $("#usersTable").DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: -1 }
        ],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            lengthMenu: "Show _MENU_ users per page",
            paginate: { previous: "&laquo;", next: "&raquo;" }
        }
    });

    // Select2 employee picker, rendered inside its modal so search/focus work
    $('.select2-unit-employees').each(function () {
        $(this).select2({
            placeholder: "Select employee...",
            allowClear: true,
            width: '100%',
            dropdownParent: $(this).closest('.modal')
        });
    });

    <?php if ($isEditing): ?>
    // Keep the old ?edit=<id> links working: open that user's edit modal
    $('#editModal<?= (int)$editUser['id'] ?>').modal('show');
    <?php endif; ?>
});
</script>
</body>
</html>