<?php
require_once '../config/database.php';
require_once '../includes/auth.php';


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
    <div class="content-wrapper">
        
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
                    <div class="pg-hero-title"><i class="fas fa-key"></i>Permission Management</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Define granular system permissions for roles</p>
                </div>
            </div>
        </div>

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