<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !hasPermission('manage_settings')) {
  header("Location: ../login.php");
  exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle module actions: toggle maintenance, add module, delete module
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $action = $_POST['action'] ?? 'toggle';

  // --- ADD MODULE ---
  if ($action === 'add_module') {
    $module_name = trim($_POST['module_name'] ?? '');
    $module_description = trim($_POST['module_description'] ?? '');

    if ($module_name === '') {
      $_SESSION['error_message'] = "Module name is required.";
    } else {
      $insert_stmt = $db->prepare("INSERT INTO system_modules (module_name, module_description, is_under_maintenance, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())");
      $insert_stmt->bind_param("ss", $module_name, $module_description);

      if ($insert_stmt->execute()) {
        $_SESSION['success_message'] = "Module \"$module_name\" added successfully!";
      } else {
        $_SESSION['error_message'] = "Error adding module. It may already exist.";
      }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  // --- DELETE MODULE ---
  if ($action === 'delete_module' && isset($_POST['module_id'])) {
    $module_id = (int)$_POST['module_id'];

    $delete_stmt = $db->prepare("DELETE FROM system_modules WHERE id = ?");
    $delete_stmt->bind_param("i", $module_id);

    if ($delete_stmt->execute()) {
      $_SESSION['success_message'] = "Module deleted successfully!";
    } else {
      $_SESSION['error_message'] = "Error deleting module.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  // --- TOGGLE MAINTENANCE STATUS ---
  if (isset($_POST['module_id'])) {
    $module_id = (int)$_POST['module_id'];
    $is_under_maintenance = isset($_POST['is_under_maintenance']) ? 1 : 0;

    $update_stmt = $db->prepare("UPDATE system_modules SET is_under_maintenance = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("ii", $is_under_maintenance, $module_id);

    if ($update_stmt->execute()) {
      $_SESSION['success_message'] = "Module status updated successfully!";
    } else {
      $_SESSION['error_message'] = "Error updating module status.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// Fetch modules from database
$modules_stmt = $db->prepare("SELECT * FROM system_modules ORDER BY module_name");
$modules_stmt->execute();
$modules = $modules_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_modules       = count($modules);
$maintenance_modules = count(array_filter($modules, function($m) { return $m['is_under_maintenance']; }));
$active_modules      = $total_modules - $maintenance_modules;

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message   = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Module icons
$icons = [
  'Admin Dashboard' => 'fa-tachometer-alt',
  'Attachment Monitoring' => 'fa-paperclip',
  'Calendar System' => 'fa-calendar-alt',
  'Employee Management' => 'fa-users',
  'Employee Creation' => 'fa-user-plus',
  'Employee Directory' => 'fa-users',
  'Module Maintenance' => 'fa-tools',
  'Content Management' => 'fa-tv',
  'Appointment Settings' => 'fa-briefcase',
  'Position Management' => 'fa-id-card-alt',
  'Section Management' => 'fa-sitemap',
  'Office Management' => 'fa-building',
  'Employment Status' => 'fa-user-check',
  'User Management' => 'fa-user-cog',
  'Role Management' => 'fa-user-shield',
  'Permission Management' => 'fa-key',
  'Service Dashboard' => 'fa-tachometer-alt',
  'Service Calendar' => 'fa-calendar-alt',
  'Service Information' => 'fa-truck',
  'Operator/Driver Management' => 'fa-id-card',
  'Transportation Request' => 'fa-file-alt',
  'Inventory Dashboard' => 'fa-tachometer-alt',
  'Inventory Management' => 'fa-boxes',
  'Supply Requests' => 'fa-clipboard-check',
  'My Supply Requests' => 'fa-list-ol',
  'File Management' => 'fa-folder',
  'ICT Equipment Inventory' => 'fa-laptop',

  // HR Management extras
  'Leave Request' => 'fa-newspaper',
  'Leave Types' => 'fa-arrow-circle-right',
  'Personal Locator Slip' => 'fa-location-arrow',
  'Slip Monitoring' => 'fa-tasks',
  'Applicant Databank' => 'fa-clipboard-check',
  'HR Leave Monitoring' => 'fa-clipboard-list',
  'Intern Databank' => 'fa-user-graduate',
  'Room Reservation' => 'fa-bed',
  'My ICT Equipment' => 'fa-laptop',

  // Document Management module
  'Document Dashboard' => 'fa-tachometer-alt',
  'Document Records' => 'fa-folder-open',
  'Document Types' => 'fa-tags',
  'Document Archive' => 'fa-archive',

  // IA Profiles / SAHUR module
  'IA Dashboard' => 'fa-tachometer-alt',
  'IA Profiles' => 'fa-list',
  'IA Reports' => 'fa-file-pdf',
  'IA Analytics' => 'fa-chart-line',

  // ICT Equipment module extras
  'Equipment Inventory' => 'fa-desktop',
  'ICT Assign/Return' => 'fa-user-tag',
  'ICT QR Scanner' => 'fa-qrcode',
  'ICT Maintenance Logs' => 'fa-tools',
  'ICT Categories' => 'fa-sitemap',

  // Queue Management module
  'Queue Display' => 'fa-tv',
  'Section/IMO Queue' => 'fa-building',
  'Visitor Registration' => 'fa-user-plus',
  'Queue Reports' => 'fa-chart-bar',
  'Visitor History' => 'fa-history',
  'Queue Settings' => 'fa-cog',
  'Section/Unit Counters' => 'fa-desktop',
  'Purpose Categories' => 'fa-tags',

  // Scrum Board module
  'Scrum Dashboard' => 'fa-tachometer-alt',
  'Projects Monitoring' => 'fa-project-diagram',
  'Teams' => 'fa-project-diagram',
  'My Projects' => 'fa-project-diagram',
  'My Tasks' => 'fa-tasks',
  'Scrum Calendar' => 'fa-calendar-alt',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Module Maintenance</title>
  <?php include '../includes/header.php'; ?>
  <style>
    :root {
      --h-bg:       #eef7f2;
      --h-card:     #ffffff;
      --h-card-alt: #f0faf5;
      --h-surface3: #e2f3ea;
      --h-border:   rgba(42,152,99,0.18);
      --h-text:     #0f2d1e;
      --h-muted:    #4a7a5e;
      --h-primary:  #2a9863;
      --h-primary-dim: rgba(42,152,99,.10);
      --h-accent:   #24e78f;
      --h-success:  #2a9863;
      --h-warning:  #e67700;
      --h-danger:   #c92a2a;
      --h-blue:     #2a9863;
      --h-shadow:   0 4px 24px rgba(42,152,99,.12);
      --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
      --h-bg:       #0b1f17;
      --h-card:     #102f22;
      --h-card-alt: #0e2619;
      --h-surface3: #16352a;
      --h-border:   rgba(36,231,143,0.12);
      --h-text:     #d4f5e5;
      --h-muted:    #6aad8a;
      --h-primary:  #24e78f;
      --h-primary-dim: rgba(36,231,143,.12);
      --h-accent:   #2a9863;
      --h-success:  #24e78f;
      --h-warning:  #ffd43b;
      --h-danger:   #ff6b6b;
      --h-blue:     #24e78f;
      --h-shadow:   0 4px 24px rgba(0,0,0,.35);
      --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }

    .content-wrapper { background: var(--h-bg) !important; }

    /* Z-INDEX FIX FOR MODAL OVERLAP */
    .main-header.navbar, .main-header, nav.main-header, header.main-header { z-index: 1000 !important; }
    .main-sidebar, aside.main-sidebar { z-index: 999 !important; }
    .modal-backdrop { z-index: 1040 !important; }
    .modal { z-index: 1050 !important; }
    .modal-dialog { z-index: 1051 !important; }
    .modal-content { z-index: 1052 !important; }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

    /* ══ HERO — same as applicant.php ══ */
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
    .pg-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
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
      position:absolute; top:50%; right:6%; transform:translateY(-50%);
      width:240px; height:240px; pointer-events:none; z-index:0;
    }
    .pg-hero-arc {
      position:absolute; top:-50px; right:-50px; width:200px; height:200px; border-radius:50%;
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
    .pg-hero-actions { position:relative; z-index:2; display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px; }
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
      position:absolute; top:50%; right:3%; transform:translateY(-50%);
      width:180px; height:auto; pointer-events:none; z-index:0; opacity:0.50;
    }

    /* ── Stats ── */
    .mm-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px; }
    .mm-stat {
      background:var(--h-card); border:1px solid var(--h-border); border-left:4px solid var(--h-primary);
      border-radius:12px; padding:18px 20px; box-shadow:var(--h-shadow-sm);
      transition:transform .2s, box-shadow .2s;
    }
    .mm-stat:hover { transform:translateY(-3px); box-shadow:var(--h-shadow); }
    .mm-stat h3 { font-size:2rem; font-weight:800; margin:0 0 2px; color:var(--h-text); }
    .mm-stat p  { margin:0; color:var(--h-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
    .mm-stat.active      { border-left-color:var(--h-accent); }
    .mm-stat.maintenance { border-left-color:var(--h-danger); }
    .mm-stat.other       { border-left-color:var(--h-warning); }

    /* ── Card (matches applicant.php) ── */
    .card {
      background: var(--h-card) !important;
      border: 1px solid var(--h-border) !important;
      border-radius: 12px !important;
      box-shadow: var(--h-shadow-sm) !important;
    }
    .card-header {
      background: var(--h-card) !important;
      border-bottom: 1px solid var(--h-border) !important;
      padding: 14px 20px !important;
    }
    .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; }
    .card-body { background: var(--h-card) !important; padding: 18px !important; }
    .card-tools .btn-primary {
      background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
      font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
    }
    .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }

    /* ── Module grid ── */
    .mm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:18px; }
    .mm-module {
      background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:12px;
      padding:18px; position:relative; overflow:hidden; display:flex; flex-direction:column;
      transition:transform .2s, box-shadow .2s;
      animation:mmFadeUp .4s ease both;
    }
    @keyframes mmFadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .mm-module::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg, var(--h-accent), var(--h-primary));
    }
    .mm-module.maintenance::before { background:linear-gradient(90deg, var(--h-danger), var(--h-warning)); }
    .mm-module:hover { transform:translateY(-3px); box-shadow:var(--h-shadow); }

    .mm-head { display:flex; align-items:flex-start; gap:12px; }
    .mm-icon {
      width:46px; height:46px; flex-shrink:0; border-radius:10px;
      display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff;
      background:linear-gradient(135deg, var(--h-accent), var(--h-primary));
    }
    body:not(.dark-mode) .mm-icon { background:linear-gradient(135deg, #2a9863, #1a5c38); }
    .mm-module.maintenance .mm-icon { background:linear-gradient(135deg, var(--h-danger), var(--h-warning)) !important; }
    .mm-info { flex:1; min-width:0; }
    .mm-title { font-size:.95rem; font-weight:700; color:var(--h-text); margin:0 0 3px; line-height:1.3; word-wrap:break-word; }
    .mm-desc {
      color:var(--h-muted); font-size:.8rem; line-height:1.4; margin:0;
      display:-webkit-box; -webkit-line-clamp:3; line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;
      min-height:3.4em;
    }
    .mm-badge {
      flex-shrink:0; padding:4px 10px; border-radius:6px; font-size:.65rem; font-weight:700;
      text-transform:uppercase; letter-spacing:.5px; white-space:nowrap;
    }
    .mm-badge.active      { background:#d1fae5; color:#047857; }
    .mm-badge.maintenance { background:#fee2e2; color:#b91c1c; }
    body.dark-mode .mm-badge.active      { background:rgba(36,231,143,.15); color:#24e78f; }
    body.dark-mode .mm-badge.maintenance { background:rgba(255,107,107,.15); color:#ff6b6b; }

    .mm-toggle-section { margin-top:auto; padding-top:14px; border-top:1px solid var(--h-border); }
    .mm-toggle-row { display:flex; align-items:center; justify-content:space-between; margin-top:14px; }
    .mm-toggle-text { font-size:.82rem; font-weight:600; color:var(--h-muted); }
    .mm-toggle-row form { margin:0; }

    /* Toggle switch */
    .toggle-switch { position:relative; display:inline-block; width:52px; height:26px; flex-shrink:0; margin:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider {
      position:absolute; cursor:pointer; inset:0; background:#b8c9bf; transition:.3s; border-radius:34px;
    }
    .toggle-slider:before {
      position:absolute; content:""; height:20px; width:20px; left:3px; bottom:3px;
      background:#fff; transition:.3s; border-radius:50%; box-shadow:0 2px 4px rgba(0,0,0,.2);
    }
    .toggle-switch input:checked + .toggle-slider { background:linear-gradient(135deg, var(--h-danger), var(--h-warning)); }
    .toggle-switch input:checked + .toggle-slider:before { transform:translateX(26px); }

    .mm-footer { display:flex; align-items:center; justify-content:space-between; margin-top:12px; }
    .mm-updated { font-size:.72rem; color:var(--h-muted); }
    .btn-delete-module {
      background:transparent; border:1px solid rgba(201,34,46,.3); color:var(--h-danger);
      border-radius:7px; padding:3px 10px; font-size:.75rem; font-weight:600; transition:all .2s;
    }
    .btn-delete-module:hover { background:var(--h-danger); color:#fff; }

    /* ── Info card ── */
    .mm-info-card {
      background:var(--h-card-alt); border:1px solid var(--h-border); border-left:4px solid var(--h-primary);
      border-radius:12px; padding:18px 22px; margin-top:20px;
    }
    .mm-info-card h5 { color:var(--h-text); font-size:.9rem; font-weight:700; margin-bottom:10px; }
    .mm-info-card h5 i { color:var(--h-primary); }
    .mm-info-card p, .mm-info-card li { color:var(--h-muted); font-size:.84rem; }
    .mm-info-card ul { margin-bottom:0; padding-left:20px; }
    .mm-info-card li { margin-bottom:5px; }

    .mm-empty { text-align:center; padding:40px 10px; color:var(--h-muted); }
    .mm-empty i { font-size:2rem; color:var(--h-warning); margin-bottom:10px; }

    /* Flash alerts */
    .mm-alert { border-radius:10px !important; border:1px solid var(--h-border) !important; font-size:.85rem; }

    /* ── Scrum-style modal (same as applicant.php) ── */
    .scrum-style-modal .modal-content {
      background: var(--h-card) !important; border: 1px solid var(--h-border) !important;
      border-radius: 14px !important; box-shadow: 0 24px 80px rgba(0,0,0,.25) !important; color: var(--h-text) !important;
    }
    .scrum-style-modal .modal-header {
      background: var(--h-card-alt) !important; border-bottom: 1px solid var(--h-border) !important;
      border-radius: 14px 14px 0 0 !important; padding: 16px 20px !important;
    }
    .scrum-style-modal .modal-title { font-weight: 700 !important; font-size: .95rem !important; color: var(--h-text) !important; }
    .scrum-style-modal .modal-title i { color: var(--h-primary) !important; margin-right: 8px; }
    .scrum-style-modal .modal-header .close { color: var(--h-muted) !important; text-shadow: none !important; opacity: 1 !important; }
    .scrum-style-modal .modal-header .close:hover { opacity: .7 !important; }
    .scrum-style-modal .modal-body { padding: 20px !important; background: var(--h-card) !important; }
    .scrum-style-modal .modal-footer {
      border-top: 1px solid var(--h-border) !important; padding: 14px 20px !important;
      background: var(--h-card-alt) !important; border-radius: 0 0 14px 14px !important;
    }
    .scrum-style-modal label {
      color: var(--h-muted) !important; font-size: .72rem !important; font-weight: 600 !important;
      text-transform: uppercase !important; letter-spacing: .5px !important; margin-bottom: 5px !important;
    }
    .scrum-style-modal label .text-danger { text-transform: none !important; }
    .scrum-style-modal .form-control,
    .scrum-style-modal .form-control:focus {
      background: var(--h-card-alt) !important; border: 1.5px solid var(--h-border) !important;
      color: var(--h-text) !important; border-radius: 8px !important; font-size: .875rem !important;
    }
    .scrum-style-modal .form-control::placeholder { color: var(--h-muted) !important; }
    .scrum-style-modal .form-control:focus { border-color: var(--h-primary) !important; box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
    .scrum-style-modal .btn-secondary {
      background: var(--h-card-alt) !important; color: var(--h-muted) !important;
      border: 1px solid var(--h-border) !important; border-radius: 8px !important; font-weight: 600 !important;
    }
    .scrum-style-modal .btn-primary {
      background: var(--h-primary) !important; color: #fff !important; border: none !important;
      border-radius: 8px !important; font-weight: 700 !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
    }
    .scrum-style-modal .btn-primary:hover { filter: brightness(1.1) !important; color: #fff !important; }

    @media (max-width: 768px) {
      .mm-grid { grid-template-columns:1fr; }
      .mm-stats { grid-template-columns:repeat(2, 1fr); }
    }
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
            <div class="pg-hero-title"><i class="fas fa-tools"></i>Module Maintenance</div>
            <div class="pg-hero-divider"></div>
            <p class="pg-hero-sub">Manage system modules and maintenance status with real-time controls</p>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="container-fluid">

          <!-- Statistics -->
          <div class="mm-stats">
            <div class="mm-stat total">
              <h3><?= $total_modules ?></h3>
              <p>Total Modules</p>
            </div>
            <div class="mm-stat active">
              <h3><?= $active_modules ?></h3>
              <p>Active Modules</p>
            </div>
            <div class="mm-stat maintenance">
              <h3><?= $maintenance_modules ?></h3>
              <p>Under Maintenance</p>
            </div>
            <div class="mm-stat other">
              <h3>0</h3>
              <p>Other Status</p>
            </div>
          </div>

          <!-- Messages -->
          <?php if ($success_message): ?>
            <div class="alert alert-success mm-alert alert-dismissible fade show">
              <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
          <?php endif; ?>
          <?php if ($error_message): ?>
            <div class="alert alert-danger mm-alert alert-dismissible fade show">
              <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_message) ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Modules</h3>
              <div class="card-tools">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mr-1">
                  <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModuleModal">
                  <i class="fas fa-plus"></i> Add Module
                </button>
              </div>
            </div>
            <div class="card-body">
              <?php if (empty($modules)): ?>
                <div class="mm-empty">
                  <div><i class="fas fa-exclamation-triangle"></i></div>
                  <h5>No Modules Found</h5>
                  <p class="mb-0">The system_modules table is empty. Please run the database setup to populate the modules.</p>
                </div>
              <?php else: ?>
                <div class="mm-grid">
                  <?php foreach ($modules as $i => $module):
                    $under = (bool)$module['is_under_maintenance'];
                    $state = $under ? 'maintenance' : 'active';
                    $icon  = $icons[$module['module_name']] ?? 'fa-cube';
                  ?>
                  <div class="mm-module <?= $state ?>" style="animation-delay: <?= min($i, 20) * 0.05 ?>s">
                    <div class="mm-head">
                      <div class="mm-icon"><i class="fas <?= $icon ?>"></i></div>
                      <div class="mm-info">
                        <h5 class="mm-title"><?= htmlspecialchars($module['module_name']) ?></h5>
                        <p class="mm-desc"><?= htmlspecialchars($module['module_description'] ?? '') ?></p>
                      </div>
                      <span class="mm-badge <?= $state ?>"><?= $under ? 'Maintenance' : 'Active' ?></span>
                    </div>

                    <div class="mm-toggle-section">
                      <div class="mm-toggle-row" style="margin-top:0;">
                        <span class="mm-toggle-text">Maintenance Mode</span>
                        <form method="POST">
                          <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                          <label class="toggle-switch">
                            <input type="checkbox" name="is_under_maintenance" value="1" <?= $under ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                          </label>
                        </form>
                      </div>
                      <div class="mm-footer">
                        <span class="mm-updated">Updated: <?= date('M j, Y g:i A', strtotime($module['updated_at'])) ?></span>
                        <form method="POST" class="delete-module-form m-0">
                          <input type="hidden" name="action" value="delete_module">
                          <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                          <button type="submit" class="btn btn-delete-module" data-module-name="<?= htmlspecialchars($module['module_name']) ?>">
                            <i class="fas fa-trash-alt mr-1"></i>Delete
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Information Card -->
          <div class="mm-info-card">
            <h5><i class="fas fa-info-circle mr-2"></i>Maintenance Mode Information</h5>
            <p class="mb-2"><strong>When a module is in maintenance mode:</strong></p>
            <ul>
              <li>Users will see a maintenance message instead of the module interface</li>
              <li>All functionality within the module will be temporarily disabled</li>
              <li>Administrators can still access the module for configuration</li>
              <li>Scheduled maintenance helps prevent errors during updates</li>
            </ul>
          </div>

        </div>
      </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
  </div>

  <!-- Add Module Modal -->
  <div class="modal fade scrum-style-modal" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="action" value="add_module">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-plus-circle"></i>Add New Module</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Module Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="module_name" placeholder="e.g. Vehicle Tracking" required maxlength="150">
            </div>
            <div class="form-group mb-0">
              <label>Description</label>
              <textarea class="form-control" name="module_description" rows="3" placeholder="Short description of what this module does"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Add Module</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
  $(document).ready(function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() { $('.mm-alert').alert('close'); }, 5000);

    // Confirm maintenance mode changes
    $('.toggle-switch input').on('change', function() {
      const checkbox = this;
      const moduleName = $(checkbox).closest('.mm-module').find('.mm-title').text();
      const newStatus = checkbox.checked ? 'maintenance' : 'active';
      const form = $(checkbox).closest('form');

      Swal.fire({
        title: 'Confirm Status Change',
        html: 'Are you sure you want to set <strong>' + $('<div>').text(moduleName).html() + '</strong> to <strong>' + newStatus + ' mode</strong>?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2a9863',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, change it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        } else {
          checkbox.checked = !checkbox.checked;
        }
      });
    });

    // Confirm module deletion
    $('.delete-module-form').on('submit', function(e) {
      e.preventDefault();
      const form = $(this);
      const moduleName = form.find('.btn-delete-module').data('module-name');

      Swal.fire({
        title: 'Delete Module?',
        html: 'This will permanently remove <strong>' + $('<div>').text(moduleName).html() + '</strong> from the system. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          form.off('submit').submit();
        }
      });
    });
  });
  </script>
</body>
</html>