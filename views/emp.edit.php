<?php
require_once '../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get employee ID from URL
$emp_id = $_GET['emp_id'] ?? null;
if (!$emp_id) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Employee ID is required'
    ];
    header("Location: emp.list.php");
    exit();
}

// Fetch employee data with all joined details
$query = "SELECT 
            e.*,
            es.status_name as employment_status,
            o.office_name,
            s.section_name,
            s.section_id as current_section_id,
            p.position_name,
            ap.status_name as appointment_status,
            GROUP_CONCAT(us.unit_id) as unit_section_ids,
            GROUP_CONCAT(us.unit_name) as unit_section_names
          FROM employee e
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          LEFT JOIN employee_unit_sections eus ON e.emp_id = eus.emp_id
          LEFT JOIN unit_section us ON eus.unit_id = us.unit_id
          WHERE e.emp_id = ?
          GROUP BY e.emp_id";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Employee not found'
    ];
    header("Location: emp.list.php");
    exit();
}

// Fetch all lookup data
$employmentStatuses = [];
$appointmentStatuses = [];
$positions = [];
$sections = [];
$offices = [];
$unit_sections = [];

$stmt = $db->prepare("SELECT * FROM employment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employmentStatuses[] = $row;
}

$stmt = $db->prepare("SELECT * FROM appointment_status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointmentStatuses[] = $row;
}

$stmt = $db->prepare("SELECT * FROM position");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}

$stmt = $db->prepare("SELECT s.section_id, s.section_name, 
                      CONCAT(e.first_name, ' ', e.last_name) as head_name 
                      FROM section s
                      LEFT JOIN employee e ON s.head_emp_id = e.emp_id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$stmt = $db->prepare("SELECT * FROM office");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

$stmt = $db->prepare("SELECT us.*, s.section_name 
                      FROM unit_section us
                      LEFT JOIN section s ON us.section_id = s.section_id
                      ORDER BY us.unit_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $unit_sections[] = $row;
}

// Current unit section IDs for this employee
$current_unit_ids = !empty($employee['unit_section_ids']) ? explode(',', $employee['unit_section_ids']) : [];

// Statuses that disable assignment fields
$disableStatuses = ['Inactive', 'Separated - Death', 'Non-renewal', 'Resigned', 'Retired', 'AWOL'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | Edit Employee</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .content { padding:0 20px; margin-top:-30px; position:relative; z-index:3; }
    .modern-card {
      border: 1px solid #e5ece8;
      border-radius: 18px;
      box-shadow: 0 1px 2px rgba(16,40,30,.04), 0 10px 28px -10px rgba(16,40,30,.12);
      transition: all 0.3s ease;
      background: #ffffff;
    }

    .modern-card:hover {
      box-shadow: 0 4px 10px -2px rgba(16,40,30,.10), 0 18px 34px -12px rgba(16,40,30,.16);
    }

    .form-control-modern {
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      padding: 11px 14px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: #ffffff;
    }

    .form-control-modern:focus {
      border-color: #1a5c38;
      box-shadow: 0 0 0 3px rgba(26, 92, 56, 0.1);
      background: #ffffff;
    }

    .form-label {
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .btn-modern {
      background: linear-gradient(135deg, #1a5c38 0%, #2a9863 100%);
      border: none;
      border-radius: 10px;
      padding: 12px 30px;
      font-weight: 600;
      color: white;
      transition: all 0.3s ease;
      box-shadow: 0 4px 14px -3px rgba(26, 92, 56, 0.45);
    }

    .btn-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px -3px rgba(26, 92, 56, 0.55);
      background: linear-gradient(135deg, #123b2a 0%, #1f7a4d 100%);
      color: white;
    }

    .section-title {
      color: #1a5c38;
      font-weight: 700;
      font-size: 18px;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #eef8f2;
      position: relative;
    }
    .section-title1 {
      color: #1a5c38;
      font-weight: 700;
      font-size: 18px;
      position: relative;
    }

    .section-title:after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 50px;
      height: 2px;
      background: #1a5c38;
      border-radius: 2px;
    }

    .image-upload-area {
      border: 2px dashed #d1d5db;
      border-radius: 16px;
      padding: 30px;
      text-align: center;
      transition: all 0.3s ease;
      background: #fafafa;
      cursor: pointer;
    }

    .image-upload-area:hover {
      border-color: #1a5c38;
      background: #eef8f2;
    }

    .image-preview-container {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .image-preview-container:hover {
      transform: scale(1.02);
    }

    .required-field::after {
      content: " *";
      color: #ef4444;
    }

    .modern-select {
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 0px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: #ffffff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") right 12px center no-repeat;
      background-size: 16px;
      appearance: none;
    }

    .modern-select:focus {
      border-color: #1a5c38;
      box-shadow: 0 0 0 3px rgba(26, 92, 56, 0.1);
    }

    .modern-select:disabled {
      background-color: #e9ecef;
      opacity: 1;
      cursor: not-allowed;
    }

    .btn-default-image {
      background: linear-gradient(135deg, #b8952f 0%, #d4af37 100%);
      border: none;
      border-radius: 10px;
      padding: 10px 20px;
      font-weight: 600;
      color: white;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
      margin-top: 10px;
      width: 100%;
    }

    .btn-default-image:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(212, 175, 55, 0.4);
      background: linear-gradient(135deg, #9c7f28 0%, #b8952f 100%);
      color: white;
    }

    .default-image-active {
      border: 3px solid #d4af37 !important;
      background: #fdf9ee !important;
    }

    .validation-error {
      color: #ef4444;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .image-upload-area.error {
      border-color: #ef4444 !important;
      background: #fef2f2 !important;
    }

    /* Tab styling */
    .nav-tabs-modern {
      border-bottom: 2px solid #eef8f2;
      margin-bottom: 25px;
    }

    .nav-tabs-modern .nav-link {
      border: none;
      border-bottom: 3px solid transparent;
      color: #6b7280;
      font-weight: 600;
      padding: 12px 24px;
      border-radius: 0;
      transition: all 0.2s ease;
      margin-bottom: -2px;
    }

    .nav-tabs-modern .nav-link:hover {
      color: #1a5c38;
      background: none;
      border-bottom-color: #bfe3cd;
    }

    .nav-tabs-modern .nav-link.active {
      color: #1a5c38;
      background: none;
      border-bottom: 3px solid #1a5c38;
    }

    .nav-tabs-modern .nav-link i {
      margin-right: 8px;
    }

    /* Dark mode overrides */
    body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
    body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
    body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
    body.dark-mode .text-muted { color: var(--text-muted) !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
    body.dark-mode .modern-card { background: var(--card-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .form-control-modern { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control-modern:focus { background: var(--input-bg) !important; }
    body.dark-mode .modern-select { background-color: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .image-upload-area { background: var(--table-stripe) !important; border-color: var(--input-border) !important; }
    body.dark-mode .image-upload-area:hover { background: var(--notification-unread-bg) !important; }
    body.dark-mode .section-title { color: #7aabdf !important; border-color: var(--card-border) !important; }
    body.dark-mode .section-title:after { background: #7aabdf !important; }
    body.dark-mode .form-label { color: var(--text-primary) !important; }
    body.dark-mode .nav-tabs-modern { border-color: var(--card-border) !important; }
    body.dark-mode .nav-tabs-modern .nav-link { color: var(--text-muted) !important; }
    body.dark-mode .nav-tabs-modern .nav-link.active { color: #7aabdf !important; border-bottom-color: #7aabdf !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
    body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
    body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
    body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }
          .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0 0 14px;
            display:flex; flex-wrap:wrap; gap:2px; list-style:none;
        }
        .pg-hero-bc-item { display:flex; align-items:center; }
        .pg-hero-bc-item + .pg-hero-bc-item::before { content:'/'; color:rgba(212,245,229,.45); padding:0 8px; }
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
            padding:26px 28px 50px; position:relative; overflow:hidden;
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
    
    /* ── Compact layout overrides ── */
    .card-body { padding: 22px 26px !important; }
    .section-title { margin-bottom: 12px !important; padding-bottom: 6px !important; font-size: 16px !important; }
    .card-body .row.mb-4 { margin-bottom: 10px !important; }
    .card-body .row.mt-4 { margin-top: 14px !important; }
    .card-body .row.mt-5 { margin-top: 18px !important; }
    .card-body .mb-3 { margin-bottom: 10px !important; }
    .card-body .mb-4 { margin-bottom: 12px !important; }
    .form-label { margin-bottom: 4px !important; font-size: 13px !important; }
    .form-control-modern { padding: 8px 12px !important; }
    .modern-select { padding: 0 15px !important; height: calc(1.5em + 0.9rem + 2px); }
    .image-upload-area { padding: 18px !important; }
    .image-upload-area i.fa-3x { font-size: 2rem !important; }
    .nav-tabs-modern { margin-bottom: 16px !important; }
    .nav-tabs-modern .nav-link { padding: 9px 20px !important; }
</style>
</head>
<body class="hold-transition sidebar-mini">
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
                <ol class="pg-hero-breadcrumb">
                    <li class="pg-hero-bc-item"><a href="dashboard.php" class="pg-hero-bc-link">HR</a></li>
                    <li class="pg-hero-bc-item"><a href="emp.list.php" class="pg-hero-bc-link">Employees</a></li>
                    <li class="pg-hero-bc-item pg-hero-bc-active">Edit</li>
                </ol>
                <div class="pg-hero-title"><i class="fas fa-user-edit"></i>Edit Employee</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Editing: <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card modern-card">
              <!-- Card Header -->
              <div class="card-header" style="background: linear-gradient(135deg, #1a5c38 0%, #2a9863 100%); border-radius: 12px 12px 0 0;">
                <h3 class="card-title" style="font-weight: 600;">
                  <i class="fas fa-user-edit mr-2"></i>Employee Information
                </h3>
              </div>

              <div class="card-body" style="padding: 30px;">

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs-modern" id="editTabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal" role="tab">
                      <h4 class="section-title1">Personal Information</h4>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="assignment-tab" data-toggle="tab" href="#assignment" role="tab">
                      <h4 class="section-title1">Assignment Details</h4>
                    </a>
                  </li>
                </ul>

                <!-- ============================================================
                     TAB 1: PERSONAL INFORMATION  →  posts to emp.update.php
                     ============================================================ -->
                <div class="tab-content" id="editTabContent">
                  <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <form action="emp.update.php" method="POST" enctype="multipart/form-data" id="employeeForm">
                      <input type="hidden" name="emp_id" value="<?= $emp_id ?>">
                      <input type="hidden" name="old_picture" value="<?= htmlspecialchars($employee['picture'] ?? '') ?>">
                       

                      <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-2 mb-2">
                          <label class="form-label required-field">Profile Picture</label>
                          <div class="image-upload-area" id="imageUploadArea" onclick="document.getElementById('picture').click()">
                            <input type="file" class="d-none" id="picture" name="picture" onchange="previewImage(this)" accept="image/*">
                            <input type="hidden" id="use_default_image" name="use_default_image" value="0">
                            <div id="uploadPlaceholder">
                              <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #9ca3af;"></i>
                              <p class="mb-1" style="color: #6b7280; font-weight: 500;">Click to upload photo</p>
                              <p class="small text-muted">PNG, JPG up to 5MB</p>
                            </div>
                            <div id="imagePreview" style="display: none;">
                              <div class="image-preview-container">
                                <img id="preview" src="#" alt="Image Preview" class="img-fluid rounded">
                              </div>
                              <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="removeImage(); event.stopPropagation();">
                                <i class="fas fa-trash mr-1"></i> Remove Photo
                              </button>
                            </div>
                          </div>
                          <div id="pictureError" class="validation-error">
                            <i class="fas fa-exclamation-circle mr-1"></i>Please upload a profile picture or use the default image
                          </div>
                          <button type="button" class="btn mt-2 w-100" id="defaultImageBtn"
                            style="background: linear-gradient(135deg,#10b981 0%,#34d399 100%); border:none; border-radius:8px; padding:10px 20px; font-weight:600; color:white; transition:all .3s ease; box-shadow:0 2px 8px rgba(16,185,129,.3);"
                            onclick="useDefaultImage()">
                            <i class="fas fa-user-circle mr-2"></i>Use Default Image
                          </button>
                          <div id="defaultImagePreview" style="display:none; margin-top:15px;">
                            <div class="image-preview-container">
                              <img src="../dist/img/nialogo.png" alt="Default Profile" class="img-fluid rounded">
                            </div>
                            <p class="text-success text-center mt-2 mb-0" style="font-weight:600;">
                              <i class="fas fa-check-circle mr-1"></i>Using Default Image
                            </p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="removeDefaultImage()">
                              <i class="fas fa-times mr-1"></i> Remove Default
                            </button>
                          </div>
                        </div>

                          <!-- Personal Details -->
                          <div class="col-md-10">
                            <div class="col-12">
                            <h4 class="section-title">Personal Information</h4>
                          </div>
                          <div class="row">
                            <div class="col-2 mb-3">
                              <label for="id_number" class="form-label">Employee ID</label>
                              <input type="text" id="id_number" name="id_number" class="form-control form-control-modern"
                                placeholder="Enter employee ID"
                                value="<?= htmlspecialchars($employee['id_number'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-2 mb-3">
                              <label for="first_name" class="form-label required-field">First Name</label>
                              <input type="text" id="first_name" name="first_name" class="form-control form-control-modern"
                                placeholder="First name" required
                                value="<?= htmlspecialchars($employee['first_name']) ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                              <label for="middle_name" class="form-label required-field">Middle Name</label>
                              <input type="text" id="middle_name" name="middle_name" class="form-control form-control-modern"
                                placeholder="Middle name"
                                value="<?= htmlspecialchars($employee['middle_name']) ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                              <label for="last_name" class="form-label required-field">Last Name</label>
                              <input type="text" id="last_name" name="last_name" class="form-control form-control-modern"
                                placeholder="Last name" required
                                value="<?= htmlspecialchars($employee['last_name']) ?>">
                            </div>
                            <div class="col-md-1 mb-3">
                              <label for="ext_name" class="form-label">Ext</label>
                              <input type="text" id="ext_name" name="ext_name" class="form-control form-control-modern"
                                placeholder="Jr."
                                value="<?= htmlspecialchars($employee['ext_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-1 mb-3">
                              <label for="gender" class="form-label required-field">Gender</label>
                              <select id="gender" name="gender" class="form-control modern-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male"   <?= $employee['gender'] === 'Male'   ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $employee['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other"  <?= $employee['gender'] === 'Other'  ? 'selected' : '' ?>>Other</option>
                              </select>
                            </div>
                            <div class="col-md-2 mb-3">
                              <label for="bday" class="form-label required-field">Birthday</label>
                              <input type="date" id="bday" name="bday" class="form-control form-control-modern" required
                                value="<?= htmlspecialchars($employee['bday']) ?>">
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-12 mb-4 mt-4">
                              <h4 class="section-title">Contact Information</h4>
                            </div>
                            <div class="col-md-4 mb-3">
                              <label for="email" class="form-label required-field">Email Address</label>
                              <input type="email" id="email" name="email" class="form-control form-control-modern"
                                placeholder="employee@company.com" required
                                value="<?= htmlspecialchars($employee['email']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                              <label for="phone_number" class="form-label required-field">Phone Number</label>
                              <input type="tel" id="phone_number" name="phone_number" class="form-control form-control-modern"
                                placeholder="+63 912 345 6789" required
                                value="<?= htmlspecialchars($employee['phone_number']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                              <label for="address" class="form-label required-field">Address</label>
                              <textarea id="address" name="address" class="form-control form-control-modern" rows="3"
                                placeholder="Enter complete address" required><?= htmlspecialchars($employee['address']) ?></textarea>
                            </div>
                          </div>
                          <!-- Manager's Office Toggle -->
                      <div class="row">
                        <div class="col-12">
                          <h4 class="section-title">Office Settings</h4>
                        </div>
                        <div class="col-12">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_manager" name="is_manager"
                              value="1" <?= ($employee['is_manager'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <label class="custom-control-label form-label" for="is_manager">Assign to Manager's Office</label>
                          </div>
                        </div>
                      </div>
                        </div>
                      </div>

                      

                      <!-- Submit -->
                      <div class="row mt-4">
                        <div class="col-12 text-right">
                          <a href="emp.list.php" class="btn btn-secondary btn-lg mr-2">
                            <i class="fas fa-times mr-1"></i>Cancel
                          </a>
                          <button type="submit" class="btn btn-modern btn-lg">
                            <i class="fas fa-save mr-2"></i>Save Personal Info
                          </button>
                        </div>
                      </div>
                    </form>
                  </div><!-- /#personal -->

                  <!-- ============================================================
                       TAB 2: ASSIGNMENT DETAILS  →  posts to emp.update_assignment.php
                       ============================================================ -->
                  <div class="tab-pane fade" id="assignment" role="tabpanel">
                    <form action="emp.update_assignment.php" method="POST" id="assignmentForm">
                      <input type="hidden" name="emp_id" value="<?= $emp_id ?>">

                      <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                          <i class="fas fa-exclamation-circle mr-1"></i> Error updating assignment. Please try again.
                        </div>
                      <?php endif; ?>

                      <!-- Employment Details Section -->
                      <div class="row mb-4">
                        <div class="col-12">
                          <h4 class="section-title">Employment Details</h4>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-3 mb-3">
                          <label for="employment_status_id" class="form-label required-field">Employment Status</label>
                          <select id="employment_status_id" name="employment_status_id" class="form-control modern-select" required>
                            <?php foreach ($employmentStatuses as $status): ?>
                              <option value="<?= $status['status_id'] ?>"
                                <?= $status['status_id'] == $employee['employment_status_id'] ? 'selected' : '' ?>
                                data-status-name="<?= htmlspecialchars($status['status_name']) ?>">
                                <?= htmlspecialchars($status['status_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="appointment_status_id" class="form-label required-field">Appointment Status</label>
                          <select id="appointment_status_id" name="appointment_status_id" class="form-control modern-select" required>
                            <?php foreach ($appointmentStatuses as $status): ?>
                              <option value="<?= $status['appointment_id'] ?>"
                                <?= $status['appointment_id'] == $employee['appointment_status_id'] ? 'selected' : '' ?>
                                data-color="<?= htmlspecialchars($status['color'] ?? '#ffffff') ?>">
                                <?= htmlspecialchars($status['status_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="position_id" class="form-label required-field">Position</label>
                          <select id="position_id" name="position_id" class="form-control modern-select" required>
                            <option value="" disabled>Select Position</option>
                            <?php foreach ($positions as $position): ?>
                              <option value="<?= $position['position_id'] ?>"
                                <?= $position['position_id'] == $employee['position_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($position['position_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label for="office_id" class="form-label required-field">Office</label>
                          <select id="office_id" name="office_id" class="form-control modern-select" required>
                            <?php foreach ($offices as $office): ?>
                              <option value="<?= $office['office_id'] ?>"
                                <?= $office['office_id'] == $employee['office_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($office['office_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <!-- Section & Unit Section -->
                      <div class="row mb-4 mt-2">
                        <div class="col-12">
                          <h4 class="section-title">Section Assignment</h4>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="section_id" class="form-label">Section</label>
                          <select id="section_id" name="section_id" class="form-control modern-select">
                            <option value="nosec">-- No Section --</option>
                            <?php foreach ($sections as $section): ?>
                              <option value="<?= $section['section_id'] ?>"
                                <?= $section['section_id'] == $employee['section_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($section['section_name']) ?>
                                <?php if (!empty($section['head_name'])): ?>
                                  (Head: <?= htmlspecialchars($section['head_name']) ?>)
                                <?php endif; ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-md-6 mb-3">
                          <label for="unit_section_ids" class="form-label">Unit Sections <span class="text-muted" style="font-weight:400;">(Optional)</span></label>
                          <select class="form-control select2" id="unit_section_ids" name="unit_section_ids[]" multiple="multiple">
                            <option value="">-- No Unit Section --</option>
                            <?php foreach ($unit_sections as $unit): ?>
                              <option value="<?= $unit['unit_id'] ?>"
                                <?= in_array($unit['unit_id'], $current_unit_ids) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['unit_name']) ?>
                                (<?= htmlspecialchars($unit['section_name'] ?? '') ?>)
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <!-- Submit -->
                      <div class="row mt-4">
                        <div class="col-12 text-right">
                          <a href="emp.list.php" class="btn btn-secondary btn-lg mr-2">
                            <i class="fas fa-times mr-1"></i>Cancel
                          </a>
                          <button type="submit" class="btn btn-modern btn-lg" id="submitBtn">
                            <i class="fas fa-save mr-2"></i>Update Assignment
                          </button>
                        </div>
                      </div>
                    </form>
                  </div><!-- /#assignment -->

                </div><!-- /.tab-content -->
              </div><!-- /.card-body -->
            </div><!-- /.card -->
          </div>
        </div>
      </div>
    </section>
  </div>
  <!-- /.content-wrapper -->

  <aside class="control-sidebar control-sidebar-dark"></aside>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<!-- ./wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- SweetAlert Toast Notifications -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toast = <?php echo json_encode($_SESSION['toast']); ?>;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: toast.type,
        title: toast.message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (el) => {
            el.addEventListener('mouseenter', Swal.stopTimer);
            el.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script>
/* =====================================================
   IMAGE UPLOAD HELPERS
   ===================================================== */
function previewImage(input) {
    const preview          = document.getElementById('preview');
    const imagePreview     = document.getElementById('imagePreview');
    const uploadPlaceholder= document.getElementById('uploadPlaceholder');
    const defaultImagePrev = document.getElementById('defaultImagePreview');
    const defaultImageBtn  = document.getElementById('defaultImageBtn');
    const useDefaultInput  = document.getElementById('use_default_image');
    const uploadArea       = document.getElementById('imageUploadArea');
    const pictureError     = document.getElementById('pictureError');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            imagePreview.style.display = 'block';
            uploadPlaceholder.style.display = 'none';
            defaultImagePrev.style.display = 'none';
            defaultImageBtn.style.display = 'block';
            useDefaultInput.value = '0';
            uploadArea.classList.remove('default-image-active', 'error');
            pictureError.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('picture').value = '';
    document.getElementById('preview').src = '#';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('defaultImageBtn').style.display = 'block';
}

function useDefaultImage() {
    document.getElementById('picture').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'none';
    document.getElementById('defaultImagePreview').style.display = 'block';
    document.getElementById('defaultImageBtn').style.display = 'none';
    document.getElementById('use_default_image').value = '1';
    document.getElementById('imageUploadArea').classList.add('default-image-active');
    document.getElementById('pictureError').style.display = 'none';
    document.getElementById('imageUploadArea').classList.remove('error');
}

function removeDefaultImage() {
    document.getElementById('picture').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('defaultImagePreview').style.display = 'none';
    document.getElementById('defaultImageBtn').style.display = 'block';
    document.getElementById('use_default_image').value = '0';
    document.getElementById('imageUploadArea').classList.remove('default-image-active');
}

/* Show existing employee image on load */
<?php if (!empty($employee['picture'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    const preview          = document.getElementById('preview');
    const imagePreview     = document.getElementById('imagePreview');
    const uploadPlaceholder= document.getElementById('uploadPlaceholder');
    const defaultImageBtn  = document.getElementById('defaultImageBtn');

    preview.src = '../dist/img/employees/<?= $employee['picture'] ?>';
    imagePreview.style.display = 'block';
    uploadPlaceholder.style.display = 'none';
    defaultImageBtn.style.display = 'block';
});
<?php endif; ?>

/* =====================================================
   PERSONAL FORM VALIDATION
   ===================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('employeeForm');

    form.addEventListener('submit', function (e) {
        let isValid = true;

        form.querySelectorAll('[required]').forEach(function (field) {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                field.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.1)';
            }
        });

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#1a5c38'
            });
        }
    });

    // Real-time field validation colouring
    form.querySelectorAll('input, select, textarea').forEach(function (input) {
        input.addEventListener('input', function () {
            if (this.hasAttribute('required') && this.value.trim()) {
                this.style.borderColor = '#10b981';
                this.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.1)';
            } else if (this.hasAttribute('required')) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
    });
});

/* =====================================================
   ASSIGNMENT TAB: EMPLOYMENT STATUS DISABLE LOGIC
   ===================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const employmentStatusSelect  = document.getElementById('employment_status_id');
    const appointmentStatusSelect = document.getElementById('appointment_status_id');
    const positionSelect          = document.getElementById('position_id');
    const sectionSelect           = document.getElementById('section_id');
    const officeSelect            = document.getElementById('office_id');
    const submitBtn               = document.getElementById('submitBtn');

    const disableStatuses = <?php echo json_encode($disableStatuses); ?>;

    function checkEmploymentStatus() {
        const selectedOption = employmentStatusSelect.options[employmentStatusSelect.selectedIndex];
        const statusName     = selectedOption.getAttribute('data-status-name');
        const shouldDisable  = disableStatuses.includes(statusName);

        appointmentStatusSelect.disabled = shouldDisable;
        positionSelect.disabled          = shouldDisable;
        sectionSelect.disabled           = shouldDisable;
        officeSelect.disabled            = shouldDisable;
        if (submitBtn) submitBtn.disabled = false;

        if (shouldDisable) {
            Swal.fire({
                icon: 'info',
                title: 'Status Change',
                text: 'Since the employee status is "' + statusName + '", other assignment fields have been disabled.',
                toast: true,
                position: 'top',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }

    if (employmentStatusSelect) {
        checkEmploymentStatus();
        employmentStatusSelect.addEventListener('change', checkEmploymentStatus);
    }

    /* Appointment status colour coding */
    function updateStatusColor() {
        if (!appointmentStatusSelect) return;
        const selectedOption = appointmentStatusSelect.options[appointmentStatusSelect.selectedIndex];
        const color = selectedOption.getAttribute('data-color');
        if (!color) return;
        appointmentStatusSelect.style.backgroundColor = color;
        appointmentStatusSelect.style.color = getContrastColor(color);
    }

    function getContrastColor(hexColor) {
        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#000000' : '#ffffff';
    }

    if (appointmentStatusSelect) {
        updateStatusColor();
        appointmentStatusSelect.addEventListener('change', updateStatusColor);
    }

    /* Section → unit section state */
    function updateUnitSectionState() {
        const unitSectionSelect = document.getElementById('unit_section_ids');
        if (unitSectionSelect && sectionSelect) {
            unitSectionSelect.disabled = (sectionSelect.value === 'nosec');
        }
    }

    if (sectionSelect) {
        updateUnitSectionState();
        sectionSelect.addEventListener('change', updateUnitSectionState);
    }

    /* Initialize select2 */
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ placeholder: "Select unit sections...", allowClear: true });
    }
});
</script>

<!-- SweetAlert for session alerts -->
<script>
$(document).ready(function () {
    <?php if (isset($_SESSION['alert'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['title'] ?>',
        text: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 3000,
        confirmButtonColor: '#1a5c38'
    });
    <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>