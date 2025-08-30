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
    if (isset($_POST['add_permission'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Permission added successfully!'
            ];
            header("Location: permissions.php");
            exit();
        }
    }

    if (isset($_POST['edit_permission'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $id])) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Permission updated successfully!'
            ];
            header("Location: permissions.php");
            exit();
        }
    }
    
    if (isset($_POST['delete_permission'])) {
        $permissionId = (int)$_POST['id'];
        
        try {
            $db->begin_transaction();
            
            // First remove from role_permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
            $stmt->bind_param("i", $permissionId);
            $stmt->execute();
            
            // Then delete the permission
            $stmt = $db->prepare("DELETE FROM permissions WHERE id = ?");
            $stmt->bind_param("i", $permissionId);
            
            if ($stmt->execute()) {
                $db->commit();
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Permission deleted successfully!'
                ];
            } else {
                $db->rollback();
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to delete permission.'
                ];
            }
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Database error: ' . $e->getMessage()
            ];
        }
        
        header("Location: permissions.php");
        exit();
    }
}

// Get all permissions
$permissions = $db->query("SELECT * FROM permissions ORDER BY name")->fetch_all(MYSQLI_ASSOC);
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
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Permission Management</h1>
            </div>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?= isset($_GET['edit']) ? 'Edit' : 'Add New' ?> Permission</h3>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <?php if (isset($_GET['edit'])): 
                                    $editId = (int)$_GET['edit'];
                                    $editPermission = array_filter($permissions, function($p) use ($editId) { return $p['id'] == $editId; });
                                    $editPermission = reset($editPermission);
                                ?>
                                    <input type="hidden" name="id" value="<?= $editPermission['id'] ?>">
                                <?php endif; ?>
                                <div class="form-group">
                                    <label>Permission Name</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?= isset($editPermission) ? htmlspecialchars($editPermission['name']) : '' ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea class="form-control" name="description" required><?= isset($editPermission) ? htmlspecialchars($editPermission['description']) : '' ?></textarea>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="<?= isset($_GET['edit']) ? 'edit_permission' : 'add_permission' ?>" 
                                        class="btn btn-primary">
                                    <?= isset($_GET['edit']) ? 'Update' : 'Add' ?> Permission
                                </button>
                                <?php if (isset($_GET['edit'])): ?>
                                    <a href="permissions.php" class="btn btn-default">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">System Permissions</h3>
                        </div>
                        <div class="card-body">
                            <table id="usersTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permissions as $permission): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($permission['name']) ?></td>
                                        <td><?= htmlspecialchars($permission['description']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $permission['id'] ?>">
                                                <a href="permissions.php?edit=<?= $permission['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="submit" name="delete_permission" class="btn btn-danger btn-sm" 
                                                       onclick="return confirmDelete()">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
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
function confirmDelete() {
    return Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        return result.isConfirmed;
    });
}

<?php if (isset($_SESSION['toast'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const toast = <?php echo json_encode($_SESSION['toast']); ?>;
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: toast.type,
        title: toast.message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
});
<?php unset($_SESSION['toast']); endif; ?>
</script>
</html>