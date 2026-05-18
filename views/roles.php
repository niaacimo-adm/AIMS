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
    .content { padding:0 20px; margin-top:-50px; position:relative; z-index:3; }
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
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
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
                    <div class="pg-hero-title"><i class="fas fa-user-shield"></i>Role Management</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Define and manage system roles and their permissions</p>
                </div>
            </div>
        </div>

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