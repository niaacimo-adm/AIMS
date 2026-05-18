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
<style>
        .content { padding:0 20px; margin-top:-50px; position:relative; z-index:3; }
        .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0;
            display:flex; flex-wrap:wrap; gap:2px;
        }
        .pg-hero-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color:rgba(212,245,229,.45); }
        .pg-hero-bc-link   { color:rgba(212,245,229,.65); text-decoration:none; font-size:.8rem; }
        .pg-hero-bc-link:hover { color:#24e78f; }
        .pg-hero-bc-active { color:rgba(212,245,229,.9); font-size:.8rem; }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-actions {
            position:relative; z-index:2;
            display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px;
        }
        .pg-hero-date { color:rgba(212,245,229,.65); font-size:.82rem; align-self:center; }
        .pg-hero-btn {
            background:rgba(36,231,143,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(36,231,143,.3); color:#d4f5e5;
            border-radius:10px; padding:8px 16px;
            font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .2s, transform .18s, box-shadow .2s;
        }
        .pg-hero-btn:hover {
            background:rgba(36,231,143,.22); border-color:rgba(36,231,143,.55);
            transform:translateY(-2px); box-shadow:0 4px 16px rgba(36,231,143,.2);
            color:#d4f5e5; text-decoration:none;
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
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
    
        <!-- Page Hero -->
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
                    <p class="pg-hero-sub">Create, edit and control system user accounts</p>
                </div>
            </div>
        </div>

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