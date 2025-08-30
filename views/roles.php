<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

session_start();

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
  <title>AdminLTE 3 | Role Management</title>
  <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <!-- Main Sidebar Container -->
    <?php include '../includes/sidebar.php'; ?>
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <h1>Role Management</h1>
                </div>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Add New Role</h3>
                            </div>
                            <form method="POST">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Role Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea class="form-control" name="description" required></textarea>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" name="add_role" class="btn btn-primary">Add Role</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Manage Roles and Permissions</h3>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="rolesAccordion">
                                    <?php foreach ($roles as $role): ?>
                                    <div class="card">
                                        <div class="card-header" id="heading<?= $role['id'] ?>">
                                            <h2 class="mb-0 d-flex justify-content-between align-items-center">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" 
                                                        data-target="#collapse<?= $role['id'] ?>" 
                                                        aria-expanded="true" aria-controls="collapse<?= $role['id'] ?>">
                                                    <?= htmlspecialchars($role['name']) ?>
                                                </button>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" 
                                                            data-target="#editRoleModal<?= $role['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-role-btn" 
                                                            data-id="<?= $role['id'] ?>" 
                                                            data-name="<?= htmlspecialchars($role['name']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </h2>
                                        </div>
                                        <div id="collapse<?= $role['id'] ?>" class="collapse" 
                                            aria-labelledby="heading<?= $role['id'] ?>" data-parent="#rolesAccordion">
                                            <div class="card-body">
                                                <p><?= htmlspecialchars($role['description']) ?></p>
                                                <form method="POST">
                                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                                    <div class="form-group">
                                                        <label>Permissions</label>
                                                        <?php 
                                                        $rolePermissions = getRolePermissions($role['id']);
                                                        foreach ($allPermissions as $permission): 
                                                        ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                name="permissions[]" value="<?= $permission['id'] ?>"
                                                                <?= in_array($permission['id'], $rolePermissions) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">
                                                                <?= htmlspecialchars($permission['name']) ?>
                                                                <small class="text-muted"> - <?= htmlspecialchars($permission['description']) ?></small>
                                                            </label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="submit" name="update_permissions" class="btn btn-primary">
                                                        Update Permissions
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Role Modal -->
                                    <div class="modal fade" id="editRoleModal<?= $role['id'] ?>">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h4 class="modal-title">Edit Role</h4>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                                        <div class="form-group">
                                                            <label>Role Name</label>
                                                            <input type="text" class="form-control" name="name" 
                                                                   value="<?= htmlspecialchars($role['name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Description</label>
                                                            <textarea class="form-control" name="description" required><?= 
                                                                htmlspecialchars($role['description']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_role" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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
        text: `Are you sure you want to delete "${roleName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete',
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
                // Enhanced error message with user count and list
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