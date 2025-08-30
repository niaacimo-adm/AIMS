<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

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
        $employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        
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
        $employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | User Management</title>
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
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>User Management</h1>
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
                <h3 class="card-title">Add New User</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                  </div>
                  <div class="form-group">
                    <label for="role_id">Role</label>
                    <select id="role_id" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="employee_id">Assign to Employee (Optional)</label>
                    <select id="employee_id" name="employee_id" class="form-control select2-unit-employees" multiple="multiple">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($availableEmployees as $employee): ?>
                        <option value="<?= $employee['emp_id'] ?>">
                            <?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-md-8">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Users</h3>
              </div>
              <div class="card-body">
                <table id="usersTable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Username</th>
                      <th>Role</th>
                      <th>Employee</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                      <td><?= htmlspecialchars($user['user']) ?></td>
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
                                <img src="../dist/img/employees/<?= htmlspecialchars($user['picture']) ?>" 
                                     class="img-circle mr-2" style="width:30px;height:30px;object-fit:cover;">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="btn-group">
                          <button type="button" class="btn btn-info" data-toggle="modal" 
                                  data-target="#editModal<?= $user['id'] ?>">
                            <i class="fas fa-edit"></i>
                          </button>
                          <button type="button" class="btn btn-danger delete-btn" 
                                  data-id="<?= $user['id'] ?>" 
                                  data-name="<?= htmlspecialchars($user['user']) ?>">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $user['id'] ?>">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h4 class="modal-title">Edit User</h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                  <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <form method="POST">
                                <div class="modal-body">
                                  <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                  <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?= htmlspecialchars($user['user']) ?>" required>
                                  </div>
                                  <div class="form-group">
                                    <label>Role</label>
                                    <select name="role_id" class="form-control" required>
                                      <?php foreach ($roles as $role): ?>
                                      <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                      </option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  <div class="form-group">
                                    <label>Assign to Employee</label>
                                    <select name="employee_id" class="form-control">
                                        <option value="">-- Select Employee --</option>
                                        <?php foreach ($availableEmployees as $employee): ?>
                                        <option value="<?= $employee['emp_id'] ?>" <?= $user['employee_id'] == $employee['emp_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if ($user['employee_id'] && $user['first_name']): ?>
                                        <option value="<?= $user['employee_id'] ?>" selected>
                                            <?= htmlspecialchars($user['last_name'] . ', ' . $user['first_name']) ?> (Current)
                                        </option>
                                        <?php endif; ?>
                                    </select>
                                  </div>
                                  <div class="form-group">
                                    <label>New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" name="password">
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                  <button type="submit" name="update_user" class="btn btn-primary">Save changes</button>
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

<!-- Delete Confirmation Script -->
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
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: userId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.message}`
                );
                return false;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: result.value.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.value?.message || 'Unknown error occurred',
                    showConfirmButton: true
                });
            }
        }
    });
});
</script>

<!-- DataTables Initialization -->
<script>
$(function () {
    $("#usersTable").DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 }
        ],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            lengthMenu: "Show _MENU_ users per page",
            paginate: {
                previous: "&laquo;",
                next: "&raquo;"
            }
        }
    });
});
</script>
<script>
$('.select2-unit-employees').select2({
    placeholder: "Select employees...",
    allowClear: true,
    maximumSelectionLength: 1
});
</script>
</body>
</html>