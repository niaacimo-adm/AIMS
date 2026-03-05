<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

// Check permissions
checkPermission('manage_roles');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_role'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("INSERT INTO user_roles (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Role added successfully!'
            ];
            header("Location: roles.php");
            exit();
        }
    }
    
    // Handle role update
    if (isset($_POST['update_role'])) {
        $roleId = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("UPDATE user_roles SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $roleId])) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Role updated successfully!'
            ];
            header("Location: roles.php");
            exit();
        }
    }
    
    // Handle role deletion
    if (isset($_POST['delete_role'])) {
        $roleId = (int)$_POST['role_id'];
        
        try {
            // Check if role is assigned to any users
            $stmt = $db->prepare("SELECT COUNT(*) as user_count FROM users WHERE role_id = ?");
            $stmt->bind_param("i", $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['user_count'];

            if ($count > 0) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Cannot delete role because it is assigned to users',
                    'userCount' => $count
                ];
                header("Location: roles.php");
                exit();
            }

            // Begin transaction
            $db->begin_transaction();
            
            // First delete role permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param("i", $roleId);
            $stmt->execute();
            
            // Then delete the role
            $stmt = $db->prepare("DELETE FROM user_roles WHERE id = ?");
            $stmt->bind_param("i", $roleId);
            
            if ($stmt->execute()) {
                $db->commit();
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Role deleted successfully!'
                ];
            } else {
                throw new Exception("Failed to delete role");
            }
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to delete role: ' . $e->getMessage()
            ];
        }
        
        header("Location: roles.php");
        exit();
    }
    
    if (isset($_POST['update_permissions'])) {
        $roleId = (int)$_POST['role_id'];
        $permissions = $_POST['permissions'] ?? [];
        
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Delete existing permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param("i", $roleId);
            $stmt->execute();
            
            // Add new permissions
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permId) {
                $permId = (int)$permId;
                $stmt->bind_param("ii", $roleId, $permId);
                $stmt->execute();
            }
            
            $db->commit();
            
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Permissions updated successfully!'
            ];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to update permissions: ' . $e->getMessage()
            ];
        }
        
        header("Location: roles.php");
        exit();
    }
}

// Get all roles
$roles = getAllRoles();
$allPermissions = getAllPermissions();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Role Management | NIA-ACIMO</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .role-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .role-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    .role-header {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
        color: white;
        border-radius: 12px 12px 0 0 !important;
        padding: 15px 20px;
        border: none;
    }
    
    .permission-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }
    
    .permission-item {
        background:dark;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 12px 15px;
        transition: all 0.2s ease;
    }
    
    .permission-item:hover {
        background: #303438;
        border-color: #4361ee;
    }
    
    .permission-item .form-check-label {
        font-weight: 500;
        color: #2d3748;
    }
    
    .permission-item .text-muted {
        font-size: 0.85em;
        display: block;
        margin-top: 4px;
    }
    
    .role-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .role-actions .btn {
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 0.875rem;
    }
    
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 0;
    }
    
    .stats-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .accordion-button {
        background: transparent;
        border: none;
        padding: 15px 20px;
        font-weight: 600;
        color: #2d3748;
    }
    
    .accordion-button:not(.collapsed) {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }
    
    .modern-form .form-control {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 10px 15px;
        transition: all 0.2s ease;
    }
    
    .modern-form .form-control:focus {
        border-color: #4361ee;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }
    
    .btn-modern {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .btn-modern-primary {
        background: linear-gradient(135deg, #4361ee, #3f37c9);
        border: none;
        color: white;
    }
    
    .btn-modern-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
    }
    
    .section-title {
        color: #2d3748;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4361ee;
    }
    
    .role-badge {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
  
/* =========================================================
   DARK MODE OVERRIDES — applied via body.dark-mode
   ========================================================= */
body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
body.dark-mode .table-hover tbody tr:hover { background: var(--notification-unread-bg) !important; }
body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
body.dark-mode .nav-tabs .nav-link { color: var(--text-muted) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs .nav-link.active { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs { border-color: var(--card-border) !important; }
body.dark-mode .tab-content, body.dark-mode .tab-pane { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .accordion .card { background: var(--card-bg) !important; }
body.dark-mode .accordion .card-header { background: var(--table-stripe) !important; }
body.dark-mode .list-group-item { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .alert { border-color: var(--card-border) !important; }
body.dark-mode .alert-info { background: #1e2f3e !important; color: #93c5fd !important; }
body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; }
body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .dataTables_info { color: var(--text-muted) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }

body.dark-mode .role-card { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer.bg-white { background: var(--card-bg) !important; }
body.dark-mode .card-header.bg-white { background: var(--card-bg) !important; }
body.dark-mode .permission-check { color: var(--text-primary) !important; }
body.dark-mode .bg-primary.text-white { background: var(--modal-header-bg) !important; }
body.dark-mode .close.text-white { color: var(--modal-header-color) !important; }

</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Role Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Role Management</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card role-card">
                            <div class="role-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-plus-circle mr-2"></i>Create New Role
                                </h3>
                            </div>
                            <form method="POST" class="modern-form">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Role Name</label>
                                        <input type="text" class="form-control" name="name" placeholder="Enter role name" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold">Description</label>
                                        <textarea class="form-control" name="description" rows="3" placeholder="Enter role description" required></textarea>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <button type="submit" name="add_role" class="btn btn-modern btn-modern-primary btn-block">
                                        <i class="fas fa-plus mr-2"></i>Create Role
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card role-card">
                            <div class="role-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-cogs mr-2"></i>Manage Roles & Permissions
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($roles)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted">No Roles Found</h4>
                                        <p class="text-muted">Create your first role to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="accordion" id="rolesAccordion">
                                        <?php foreach ($roles as $index => $role): ?>
                                        <div class="card mb-3 border-0">
                                            <div class="card-header bg-white p-0 border-0" id="heading<?= $role['id'] ?>">
                                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <button class="btn btn-link text-decoration-none font-weight-bold text-dark p-0 mr-3" 
                                                                type="button" data-toggle="collapse" 
                                                                data-target="#collapse<?= $role['id'] ?>" 
                                                                aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                                                                aria-controls="collapse<?= $role['id'] ?>">
                                                            <i class="fas fa-chevron-down mr-2"></i>
                                                            <?= htmlspecialchars($role['name']) ?>
                                                        </button>
                                                    </div>
                                                    <div class="role-actions">
                                                        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" 
                                                                data-target="#editRoleModal<?= $role['id'] ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm delete-role-btn" 
                                                                data-id="<?= $role['id'] ?>" 
                                                                data-name="<?= htmlspecialchars($role['name']) ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div id="collapse<?= $role['id'] ?>" class="collapse <?= $index === 0 ? 'show' : '' ?>" 
                                                aria-labelledby="heading<?= $role['id'] ?>" data-parent="#rolesAccordion">
                                                <div class="card-body">
                                                    <p class="text-muted mb-4"><?= htmlspecialchars($role['description']) ?></p>
                                                    
                                                    <h6 class="font-weight-bold mb-3">Role Permissions</h6>
                                                    <form method="POST">
                                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                                        <div class="permission-grid">
                                                            <?php 
                                                            $rolePermissions = getRolePermissions($role['id']);
                                                            foreach ($allPermissions as $permission): 
                                                            ?>
                                                            <div class="permission-item">
                                                                <div class="form-check mb-0">
                                                                    <input class="form-check-input" type="checkbox" 
                                                                        name="permissions[]" value="<?= $permission['id'] ?>"
                                                                        <?= in_array($permission['id'], $rolePermissions) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label">
                                                                        <?= htmlspecialchars($permission['name']) ?>
                                                                        <span class="text-muted"><?= htmlspecialchars($permission['description']) ?></span>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="mt-4">
                                                            <button type="submit" name="update_permissions" class="btn btn-modern btn-modern-primary">
                                                                <i class="fas fa-save mr-2"></i>Update Permissions
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Role Modal -->
                                        <div class="modal fade" id="editRoleModal<?= $role['id'] ?>">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h4 class="modal-title">Edit Role</h4>
                                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                                            <div class="form-group">
                                                                <label class="font-weight-bold">Role Name</label>
                                                                <input type="text" class="form-control" name="name" 
                                                                       value="<?= htmlspecialchars($role['name']) ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label class="font-weight-bold">Description</label>
                                                                <textarea class="form-control" name="description" rows="3" required><?= 
                                                                    htmlspecialchars($role['description']) ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_role" class="btn btn-modern btn-modern-primary">
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
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
$(document).on('click', '.delete-role-btn', function(e) {
    e.preventDefault();
    const roleId = $(this).data('id');
    const roleName = $(this).data('name');
    
    Swal.fire({
        title: 'Delete Role?',
        text: `Are you sure you want to delete "${roleName}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('delete_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `role_id=${roleId}`
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
                let errorMessage = result.value.message;
                
                if (result.value.userCount > 0) {
                    errorMessage += '\n\nAssigned to:';
                    if (result.value.userList.length > 0) {
                        errorMessage += '\n- ' + result.value.userList.join('\n- ');
                    }
                    if (result.value.userCount > 5) {
                        errorMessage += `\n...and ${result.value.userCount - 5} more`;
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Delete',
                    html: errorMessage.replace(/\n/g, '<br>'),
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    width: '600px'
                });
            }
        }
    });
});
</script>
</body>
</html>