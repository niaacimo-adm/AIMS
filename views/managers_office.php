<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Function to check if employee is already assigned as Manager's Office staff
function isEmployeeAlreadyManagerStaff($db, $emp_id, $current_assignment_id = null) {
    // Convert null values to 0 for the queries
    $current_assignment_id = $current_assignment_id ?? 0;
    
    // Check if employee is already assigned as Manager's Office staff
    $stmt = $db->prepare("SELECT COUNT(*) FROM managers_office_staff WHERE emp_id = ? AND id != ?");
    $stmt->bind_param("ii", $emp_id, $current_assignment_id);
    $stmt->execute();
    $is_assigned = $stmt->get_result()->fetch_row()[0];
    
    if ($is_assigned > 0) {
        return "This employee is already assigned as Manager's Office staff.";
    }
    
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new Manager's Office staff
    if (isset($_POST['add_manager_staff'])) {
        $emp_id = $_POST['emp_id'] ?? null;
        $position = trim($_POST['position']);
        $responsibilities = trim($_POST['responsibilities']);
        
        if (!empty($emp_id)) {
            // Validate assignment
            $validation = isEmployeeAlreadyManagerStaff($db, $emp_id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: managers_office.php");
                exit();
            }
            
            $stmt = $db->prepare("INSERT INTO managers_office_staff (emp_id, position, responsibilities) VALUES (?, ?, ?)");
            if ($stmt->execute([$emp_id, $position, $responsibilities])) {
                // Add notification
                $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                        VALUES (?, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change')");
                $stmt_notif->execute([$emp_id]);
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Manager\'s Office staff added successfully!'
                ];
                header("Location: managers_office.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add Manager\'s Office staff.'
                ];
            }
        }
    }
    
    // Update Manager's Office staff
    if (isset($_POST['update_manager_staff'])) {
        $id = $_POST['id'];
        $emp_id = $_POST['emp_id'] ?? null;
        $position = trim($_POST['position']);
        $responsibilities = trim($_POST['responsibilities']);
        
        if (!empty($emp_id)) {
            // Validate assignment
            $validation = isEmployeeAlreadyManagerStaff($db, $emp_id, $id);
            if ($validation) {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => $validation
                ];
                header("Location: managers_office.php");
                exit();
            }
            
            $stmt = $db->prepare("UPDATE managers_office_staff SET emp_id = ?, position = ?, responsibilities = ? WHERE id = ?");
            if ($stmt->execute([$emp_id, $position, $responsibilities, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Manager\'s Office staff updated successfully!'
                ];
                header("Location: managers_office.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update Manager\'s Office staff.'
                ];
            }
        }
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    $id = $_GET['id'];
    
    try {
        // First get the employee ID to send notification
        $stmt = $db->prepare("SELECT emp_id FROM managers_office_staff WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
        
        if ($staff) {
            // Add notification
            $stmt_notif = $db->prepare("INSERT INTO notifications (emp_id, title, message, type) 
                                    VALUES (?, 'Role Change', 'You have been removed from Manager\'s Office Staff', 'role_change')");
            $stmt_notif->execute([$staff['emp_id']]);
        }
        
        // Now delete the record
        $stmt = $db->prepare("DELETE FROM managers_office_staff WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['swal'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Manager\'s Office staff removed successfully!'
            ];
        } else {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Failed to remove Manager\'s Office staff.'
            ];
        }
    } catch (Exception $e) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => $e->getMessage()
        ];
    }
    
    header("Location: managers_office.php");
    exit();
}

// Fetch all Manager's Office staff with more details
$query = "SELECT mos.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                 e.picture as employee_picture,
                 e.email as employee_email,
                 e.phone_number as employee_phone,
                 p.position_name as employee_position,
                 o.office_name as employee_office
          FROM managers_office_staff mos
          JOIN employee e ON mos.emp_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          ORDER BY mos.position";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager_staff = [];
while ($row = $result->fetch_assoc()) {
    $manager_staff[] = $row;
}

// Fetch all employees for dropdown
$query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name 
          FROM employee 
          WHERE emp_id NOT IN (SELECT emp_id FROM managers_office_staff)
          ORDER BY first_name, last_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Manager's Office Staff</title>
  <?php include '../includes/header.php'; ?>
  <style>
    /* ── Modern Manager's Office UI ── */
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap');

    :root {
      --mo-primary: #1a1f36;
      --mo-accent: #4f6ef7;
      --mo-accent2: #22c55e;
      --mo-surface: #ffffff;
      --mo-surface2: #f5f7ff;
      --mo-border: #e4e8f5;
      --mo-text: #1a1f36;
      --mo-muted: #8892b0;
      --mo-danger: #ef4444;
      --mo-warning: #f59e0b;
      --mo-shadow: 0 4px 24px rgba(79,110,247,.10);
      --mo-radius: 14px;
    }

    body { font-family: 'DM Sans', sans-serif !important; background: var(--mo-surface2) !important; }

    /* Page header */
    .content-header h1 {
      font-family: 'Syne', sans-serif !important;
      font-weight: 800;
      font-size: 1.7rem;
      color: var(--mo-primary);
      letter-spacing: -0.5px;
    }

    /* Stat strip */
    .mo-stat-strip {
      display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;
    }
    .mo-stat {
      flex: 1; min-width: 140px;
      background: var(--mo-surface);
      border: 1px solid var(--mo-border);
      border-radius: var(--mo-radius);
      padding: 18px 22px;
      display: flex; align-items: center; gap: 14px;
      box-shadow: var(--mo-shadow);
    }
    .mo-stat-icon {
      width: 46px; height: 46px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem; flex-shrink: 0;
    }
    .mo-stat-icon.blue  { background: #eef1ff; color: var(--mo-accent); }
    .mo-stat-icon.green { background: #dcfce7; color: var(--mo-accent2); }
    .mo-stat-icon.amber { background: #fef3c7; color: var(--mo-warning); }
    .mo-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .6px; color: var(--mo-muted); margin-bottom: 2px; }
    .mo-stat-value { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--mo-primary); line-height: 1; }

    /* Card overrides */
    .card {
      border: 1px solid var(--mo-border) !important;
      border-radius: var(--mo-radius) !important;
      box-shadow: var(--mo-shadow) !important;
      overflow: hidden;
    }
    .card-header {
      background: var(--mo-surface) !important;
      border-bottom: 1px solid var(--mo-border) !important;
      padding: 18px 22px !important;
    }
    .card-title {
      font-family: 'Syne', sans-serif !important;
      font-weight: 700 !important;
      font-size: 1rem !important;
      color: var(--mo-primary) !important;
      letter-spacing: -.3px;
    }
    .card-body { padding: 20px 22px !important; }

    /* Add Staff button */
    .btn-add-staff {
      background: var(--mo-accent) !important;
      color: #fff !important;
      border: none !important;
      border-radius: 10px !important;
      padding: 8px 20px !important;
      font-family: 'DM Sans', sans-serif !important;
      font-weight: 600 !important;
      font-size: 0.875rem !important;
      display: inline-flex; align-items: center; gap: 7px;
      transition: all .2s;
      box-shadow: 0 2px 12px rgba(79,110,247,.25) !important;
    }
    .btn-add-staff:hover { background: #3b5bdb !important; transform: translateY(-1px); box-shadow: 0 4px 18px rgba(79,110,247,.35) !important; color: #fff !important; }

    /* Table */
    #managersTable { border-collapse: separate !important; border-spacing: 0 !important; width: 100% !important; }
    #managersTable thead tr {
      background: var(--mo-surface2) !important;
    }
    #managersTable thead th {
      font-family: 'DM Sans', sans-serif !important;
      font-weight: 600 !important; font-size: 0.72rem !important;
      text-transform: uppercase; letter-spacing: .7px;
      color: var(--mo-muted) !important;
      border: none !important;
      padding: 11px 16px !important;
      white-space: nowrap;
    }
    #managersTable tbody tr {
      background: var(--mo-surface);
      transition: background .15s;
    }
    #managersTable tbody tr:hover { background: var(--mo-surface2); }
    #managersTable tbody td {
      border-top: 1px solid var(--mo-border) !important;
      border-bottom: none !important;
      border-left: none !important;
      border-right: none !important;
      padding: 13px 16px !important;
      vertical-align: middle !important;
      font-size: 0.875rem;
      color: var(--mo-text);
    }

    /* Avatar */
    .employee-avatar {
      width: 40px; height: 40px;
      border-radius: 50%; object-fit: cover;
      border: 2px solid var(--mo-border);
    }
    .default-avatar {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg,#e0e7ff,#c7d2fe);
      display: flex; align-items: center; justify-content: center;
      color: var(--mo-accent); font-size: 0.95rem;
      border: 2px solid var(--mo-border);
    }

    /* Manager badge */
    .manager-badge {
      display: inline-block;
      background: linear-gradient(135deg,#4f6ef7,#6366f1);
      color: #fff;
      padding: 3px 11px; border-radius: 20px;
      font-size: 0.72rem; font-weight: 600; letter-spacing: .3px;
    }

    /* Contact chips */
    .contact-chip {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 0.78rem; color: var(--mo-muted);
      background: var(--mo-surface2); border-radius: 6px;
      padding: 2px 8px; margin: 2px 0;
    }
    .contact-chip i { font-size: 0.7rem; }

    /* Action buttons */
    .action-buttons .btn { border-radius: 8px !important; padding: 5px 9px !important; font-size: 0.78rem !important; border: none !important; margin-right: 3px; transition: all .15s; }
    .btn-view   { background: #e0e7ff !important; color: var(--mo-accent)  !important; }
    .btn-edit   { background: #dcfce7 !important; color: #16a34a             !important; }
    .btn-remove { background: #fee2e2 !important; color: var(--mo-danger)   !important; }
    .action-buttons .btn:hover { filter: brightness(0.92); transform: translateY(-1px); }

    /* Name cell */
    .emp-name { font-weight: 600; color: var(--mo-primary); }
    .emp-sub   { font-size: 0.75rem; color: var(--mo-muted); margin-top: 1px; }

    /* Responsibilities truncate */
    .resp-cell { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--mo-muted); font-size: 0.82rem; }

    /* Modals */
    .modal-content {
      border: none !important;
      border-radius: 16px !important;
      box-shadow: 0 20px 60px rgba(0,0,0,.15) !important;
      font-family: 'DM Sans', sans-serif;
    }
    .modal-header {
      background: var(--mo-primary) !important;
      color: #fff !important;
      border-radius: 16px 16px 0 0 !important;
      padding: 18px 24px !important;
      border: none !important;
    }
    .modal-title { font-family: 'Syne', sans-serif !important; font-weight: 700 !important; font-size: 1rem !important; color: #fff !important; }
    .modal-header .close { color: rgba(255,255,255,.8) !important; text-shadow: none !important; font-size: 1.2rem; }
    .modal-body  { padding: 24px !important; }
    .modal-footer { border-top: 1px solid var(--mo-border) !important; padding: 14px 24px !important; }

    /* Form controls inside modals */
    .modal .form-group label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--mo-muted); margin-bottom: 6px; }
    .modal .form-control {
      border: 1.5px solid var(--mo-border) !important;
      border-radius: 10px !important;
      font-family: 'DM Sans', sans-serif !important;
      font-size: 0.875rem !important;
      padding: 9px 13px !important;
      color: var(--mo-primary) !important;
      transition: border-color .2s;
    }
    .modal .form-control:focus { border-color: var(--mo-accent) !important; box-shadow: 0 0 0 3px rgba(79,110,247,.12) !important; }

    /* Modal action buttons */
    .btn-modal-cancel  { background: var(--mo-surface2) !important; color: var(--mo-muted)  !important; border: none !important; border-radius: 10px !important; padding: 8px 18px !important; font-weight: 600 !important; }
    .btn-modal-save    { background: var(--mo-accent)   !important; color: #fff            !important; border: none !important; border-radius: 10px !important; padding: 8px 18px !important; font-weight: 600 !important; box-shadow: 0 2px 10px rgba(79,110,247,.25) !important; }
    .btn-modal-cancel:hover { background: var(--mo-border) !important; }
    .btn-modal-save:hover   { background: #3b5bdb !important; }

    /* DataTables overrides */
    .dataTables_wrapper .dataTables_filter input {
      border: 1.5px solid var(--mo-border) !important; border-radius: 8px !important;
      padding: 5px 12px !important; font-family: 'DM Sans',sans-serif !important;
    }
    .dataTables_wrapper .dataTables_length select {
      border: 1.5px solid var(--mo-border) !important; border-radius: 8px !important;
    }
    .paginate_button.current, .paginate_button.current:hover {
      background: var(--mo-accent) !important; color: #fff !important;
      border: none !important; border-radius: 7px !important;
    }
    .paginate_button:hover { background: var(--mo-surface2) !important; border-radius: 7px !important; border: none !important; }
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
                    <div class="pg-hero-title">Manager's Office Staff</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Manage staff members within the manager's office</p>
                </div>
                <button type="button" class="btn btn-add-staff" data-toggle="modal" data-target="#addManagerModal">
                  <i class="fas fa-user-plus"></i> Add Staff
                </button>
            </div>
        </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">

        <!-- Stat strip -->
        <div class="mo-stat-strip">
          <div class="mo-stat">
            <div class="mo-stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
              <div class="mo-stat-label">Total Staff</div>
              <div class="mo-stat-value"><?= count($manager_staff) ?></div>
            </div>
          </div>
          <div class="mo-stat">
            <div class="mo-stat-icon green"><i class="fas fa-briefcase"></i></div>
            <div>
              <div class="mo-stat-label">Positions</div>
              <div class="mo-stat-value"><?= count(array_unique(array_column($manager_staff, 'position'))) ?></div>
            </div>
          </div>
          <div class="mo-stat">
            <div class="mo-stat-icon amber"><i class="fas fa-building"></i></div>
            <div>
              <div class="mo-stat-label">Offices</div>
              <div class="mo-stat-value"><?= count(array_filter(array_unique(array_column($manager_staff, 'employee_office')))) ?></div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Current Manager's Office Staff</h3>
                <span class="badge" style="background:var(--mo-surface2);color:var(--mo-accent);border-radius:8px;padding:5px 12px;font-size:.75rem;font-weight:600;"><?= count($manager_staff) ?> member<?= count($manager_staff) != 1 ? 's' : '' ?></span>
                
              </div>
              
              <div class="card-body">
                <div class="table-responsive">
                  <table id="managersTable" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Office</th>
                        <th>Responsibilities</th>
                        <th>Contact</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($manager_staff as $staff): ?>
                      <tr>
                        <td>
                          <?php if (!empty($staff['employee_picture']) && file_exists("../dist/img/employees/" . $staff['employee_picture'])): ?>
                            <img src="../dist/img/employees/<?= htmlspecialchars($staff['employee_picture']) ?>" 
                                class="employee-avatar" 
                                alt="<?= htmlspecialchars($staff['employee_name']) ?>">
                          <?php else: ?>
                            <div class="default-avatar">
                              <i class="fas fa-user"></i>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="emp-name"><?= htmlspecialchars($staff['employee_name']) ?></div>
                          <?php if (!empty($staff['employee_position'])): ?>
                            <div class="emp-sub"><?= htmlspecialchars($staff['employee_position']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="manager-badge"><?= htmlspecialchars($staff['position']) ?></span>
                        </td>
                        <td style="font-size:.85rem"><?= htmlspecialchars($staff['employee_office'] ?? 'N/A') ?></td>
                        <td>
                          <div class="resp-cell" title="<?= htmlspecialchars($staff['responsibilities']) ?>">
                            <?= htmlspecialchars($staff['responsibilities']) ?>
                          </div>
                        </td>
                        <td>
                          <?php if (!empty($staff['employee_email'])): ?>
                            <div class="contact-chip"><i class="fas fa-envelope"></i> <?= htmlspecialchars($staff['employee_email']) ?></div>
                          <?php endif; ?>
                          <?php if (!empty($staff['employee_phone'])): ?>
                            <div class="contact-chip"><i class="fas fa-phone"></i> <?= htmlspecialchars($staff['employee_phone']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                          <div class="btn-group">
                            <a href="emp.profile.php?emp_id=<?= $staff['emp_id'] ?>" 
                              class="btn btn-view" title="View Profile">
                              <i class="fas fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-edit" data-toggle="modal" 
                                    data-target="#editModal<?= $staff['id'] ?>" title="Edit">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-remove delete-btn" 
                                    data-id="<?= $staff['id'] ?>" 
                                    data-name="<?= htmlspecialchars($staff['employee_name']) ?>"
                                    title="Remove">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      
                      <!-- Edit Modal -->
                      <div class="modal fade" id="editModal<?= $staff['id'] ?>">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h4 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Edit Manager's Office Staff</h4>
                              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <form method="POST">
                              <div class="modal-body">
                                <input type="hidden" name="id" value="<?= $staff['id'] ?>">
                                <div class="form-group">
                                  <label>Employee</label>
                                  <select class="form-control select2" name="emp_id" multiple="multiple" required>
                                    <?php foreach ($employees as $employee): ?>
                                      <option value="<?= $employee['emp_id'] ?>" 
                                        <?= $employee['emp_id'] == $staff['emp_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($employee['full_name']) ?>
                                      </option>
                                    <?php endforeach; ?>
                                    <!-- Include current employee even if they're in the managers_office_staff table -->
                                    <option value="<?= $staff['emp_id'] ?>" selected>
                                      <?= htmlspecialchars($staff['employee_name']) ?> (Current)
                                    </option>
                                  </select>
                                </div>
                                <div class="form-group">
                                  <label>Position in Manager's Office</label>
                                  <input type="text" class="form-control" name="position" 
                                         value="<?= htmlspecialchars($staff['position']) ?>" required>
                                </div>
                                <div class="form-group">
                                  <label>Responsibilities</label>
                                  <textarea class="form-control" name="responsibilities" rows="3"><?= htmlspecialchars($staff['responsibilities']) ?></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_manager_staff" class="btn btn-modal-save">Save Changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  
  <!-- Add Manager Modal -->
  <div class="modal fade" id="addManagerModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Add to Manager's Office</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label>Employee</label>
              <select class="form-control select2" name="emp_id" multiple="multiple" required>
                <option value="">-- Select Employee --</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= $employee['emp_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Position in Manager's Office</label>
              <input type="text" class="form-control" name="position" required>
            </div>
            <div class="form-group">
              <label>Responsibilities</label>
              <textarea class="form-control" name="responsibilities" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
            <button type="submit" name="add_manager_staff" class="btn btn-modal-save">Add Staff</button>
          </div>
        </form>
      </div>
    </div>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#managersTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: [0, -1] }
        ]
    });

    // Initialize Select2
    $('.select2').select2({
        placeholder: "-- Select Employee --",
        allowClear: true
    });
    
    // Delete button handler
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        Swal.fire({
            title: 'Remove Staff?',
            text: `Are you sure you want to remove ${name} from Manager's Office?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `managers_office.php?delete=1&id=${id}`;
            }
        });
    });
});
</script>
</body>
</html>