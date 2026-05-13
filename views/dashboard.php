<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$module_name = 'Admin Dashboard';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();
if ($result->num_rows > 0) {
    $module = $result->fetch_assoc();
    if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
        $_SESSION['error'] = "The $module_name module is currently under maintenance.";
        header("Location: ../unauthorized.php");
        exit();
    }
}

$stmt = $db->prepare("SELECT u.id, u.user, r.name as role_name, r.id as role_id FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { session_destroy(); header("Location: login.php"); exit(); }
$user = $result->fetch_assoc();
$role_id   = $user['role_id'];
$role_name = $user['role_name'];

// Sections
$stmt = $db->prepare("SELECT s.*, CONCAT(e.first_name,' ',e.last_name) as head_name, e.picture as head_picture, (SELECT COUNT(*) FROM unit_section WHERE section_id=s.section_id) as unit_count FROM section s LEFT JOIN employee e ON s.head_emp_id=e.emp_id ORDER BY s.section_name");
$stmt->execute();
$sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Unit sections
$stmt = $db->prepare("SELECT us.*, s.section_name, CONCAT(e.first_name,' ',e.last_name) as head_name, e.picture as head_picture FROM unit_section us LEFT JOIN section s ON us.section_id=s.section_id LEFT JOIN employee e ON us.head_emp_id=e.emp_id ORDER BY us.unit_name");
$stmt->execute();
$unit_sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Manager
$stmt = $db->prepare("SELECT e.*, p.position_name, o.office_name FROM employee e LEFT JOIN position p ON e.position_id=p.position_id LEFT JOIN office o ON e.office_id=o.office_id WHERE e.is_manager=1 LIMIT 1");
$stmt->execute();
$manager = $stmt->get_result()->fetch_assoc();

// Manager staff
$stmt = $db->prepare("SELECT mos.*, CONCAT(e.first_name,' ',e.last_name) as employee_name, e.picture as employee_picture, e.email as employee_email, e.phone_number as employee_phone, p.position_name as employee_position, o.office_name as employee_office FROM managers_office_staff mos JOIN employee e ON mos.emp_id=e.emp_id LEFT JOIN position p ON e.position_id=p.position_id LEFT JOIN office o ON e.office_id=o.office_id ORDER BY mos.position LIMIT 6");
$stmt->execute();
$manager_staff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Appointment data
$stmt = $db->prepare("SELECT a.status_name, a.color, COUNT(e.emp_id) as count FROM appointment_status a LEFT JOIN employee e ON a.appointment_id=e.appointment_status_id GROUP BY a.appointment_id,a.status_name,a.color ORDER BY count DESC");
$stmt->execute();
$appointment_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Gender data
$stmt = $db->prepare("SELECT s.section_name, SUM(CASE WHEN e.gender='Male' THEN 1 ELSE 0 END) as male_count, SUM(CASE WHEN e.gender='Female' THEN 1 ELSE 0 END) as female_count, COUNT(e.emp_id) as total_count FROM section s LEFT JOIN employee e ON s.section_id=e.section_id GROUP BY s.section_id,s.section_name ORDER BY s.section_name");
$stmt->execute();
$gender_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Active employees
$stmt = $db->prepare("SELECT COUNT(*) as active_count FROM employee WHERE employment_status_id=1");
$stmt->execute();
$active_employees = $stmt->get_result()->fetch_assoc()['active_count'];

// Total employees (all statuses)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM employee");
$stmt->execute();
$total_employees = $stmt->get_result()->fetch_assoc()['total'];

function isUsingTemporaryPassword($emp_id, $db) {
    $q = $db->prepare("SELECT u.password,e.id_number FROM users u JOIN employee e ON u.employee_id=e.emp_id WHERE u.employee_id=?");
    $q->bind_param("i",$emp_id); $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return $r ? password_verify($r['id_number'],$r['password']) : false;
}
if (isset($_SESSION['emp_id']) && isUsingTemporaryPassword($_SESSION['emp_id'], $db)) {
    $_SESSION['toast'] = ['type'=>'warning','message'=>'You are using a temporary password. Please change it for security.'];
}

$uploads_url = '../dist/img/employees/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* ═══════════════════════════════════════════════════════
           DESIGN TOKENS
        ═══════════════════════════════════════════════════════ */
        :root {
            --brand:       #2563eb;
            --brand-dk:    #5a0a1d;
            --brand-lt:    rgba(37, 104, 221, 0.09);
            --blue:        #2563eb;
            --green:       #16a34a;
            --amber:       #d97706;
            --purple:      #7c3aed;
            --teal:        #0891b2;

            --bg:          #f0f2f7;
            --surface:     #ffffff;
            --surface2:    #f8fafc;
            --border:      #e4e7ef;
            --text:        #111827;
            --text2:       #4b5563;
            --text3:       #9ca3af;

            --radius:      16px;
            --radius-sm:   10px;
            --shadow:      0 1px 8px rgba(0,0,0,.07), 0 4px 20px rgba(0,0,0,.05);
            --shadow-md:   0 4px 16px rgba(0,0,0,.1), 0 10px 40px rgba(0,0,0,.08);
            --trans:       all .22s cubic-bezier(.4,0,.2,1);
        }

        body {
            background: var(--bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text);
        }

        /* ── Page title area ── */
        .dash-topbar {
            padding: 22px 0 6px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            background: var(--surface);
        }
        .dash-topbar h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            margin: 0;
            letter-spacing: -.02em;
        }
        .dash-topbar .sub { font-size: .85rem; color: var(--text3); margin-top: 2px; }
        .dash-topbar .breadcrumb { background: transparent; padding: 0; margin: 0; font-size: .8rem; }
        .dash-topbar .breadcrumb-item.active { color: var(--text3); }
        .dash-topbar .breadcrumb-item a { color: var(--brand); }

        /* ═══════════════════════════════════════════════════════
           STAT CARDS
        ═══════════════════════════════════════════════════════ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media(max-width:900px){ .stat-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:520px){ .stat-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 22px 20px 18px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            overflow: hidden;
            transition: var(--trans);
            border: 1px solid var(--border);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .stat-card.c-brand::before  { background: linear-gradient(90deg,var(--brand),var(--brand-dk)); }
        .stat-card.c-blue::before   { background: linear-gradient(90deg,#2563eb,#1d4ed8); }
        .stat-card.c-green::before  { background: linear-gradient(90deg,#16a34a,#15803d); }
        .stat-card.c-amber::before  { background: linear-gradient(90deg,#d97706,#b45309); }

        .stat-header { display: flex; align-items: flex-start; justify-content: space-between; }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
        }
        .si-brand { background: var(--brand-lt); color: var(--brand); }
        .si-blue  { background: #dbeafe; color: var(--blue); }
        .si-green { background: #dcfce7; color: var(--green); }
        .si-amber { background: #fef3c7; color: var(--amber); }

        .stat-pill {
            font-size: .68rem; font-weight: 700; padding: 3px 9px;
            border-radius: 20px; white-space: nowrap;
        }
        .pill-up   { background:#dcfce7; color:#16a34a; }
        .pill-info { background:#dbeafe; color:#1d4ed8; }
        .pill-warn { background:#fef3c7; color:#b45309; }

        .stat-value { font-size: 2.4rem; font-weight: 800; line-height: 1; color: var(--text); letter-spacing: -.03em; }
        .stat-label { font-size: .82rem; color: var(--text2); font-weight: 500; }
        .stat-bar-bg   { height: 4px; border-radius: 99px; background: var(--border); margin-top: 4px; }
        .stat-bar-fill { height: 100%; border-radius: 99px; }

        /* ═══════════════════════════════════════════════════════
           MANAGER HERO CARD
        ═══════════════════════════════════════════════════════ */
        .manager-hero {
            background: #007bff ;
            border-radius: var(--radius);
            padding: 28px 30px;
            color: white;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .manager-hero::before {
            content: '';
            position: absolute; right: -40px; top: -40px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            pointer-events: none;
        }
        .manager-hero::after {
            content: '';
            position: absolute; right: 60px; bottom: -60px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
            pointer-events: none;
        }
        .mh-inner { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
        .mh-avatar {
            width: 88px; height: 88px; border-radius: 50%; flex-shrink: 0;
            border: 3px solid rgba(255,255,255,.35);
            object-fit: cover;
            background: rgba(255,255,255,.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: rgba(255,255,255,.8);
            overflow: hidden;
        }
        .mh-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .mh-info { flex: 1; min-width: 0; }
        .mh-tag { font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.6); margin-bottom: 6px; }
        .mh-name { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 4px; }
        .mh-title { font-size: .95rem; color: rgba(255,255,255,.8); margin-bottom: 8px; }
        .mh-meta { display: flex; gap: 16px; flex-wrap: wrap; }
        .mh-meta-item { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: rgba(255,255,255,.7); }
        .mh-staff-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; border-top: 1px solid rgba(255,255,255,.15); padding-top: 16px; }
        .mh-staff-pill {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,.12); border-radius: 40px;
            padding: 5px 12px 5px 5px; font-size: .78rem; color: rgba(255,255,255,.9);
            backdrop-filter: blur(4px);
        }
        .mh-sp-av {
            width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,255,255,.25); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .mh-sp-av img { width: 100%; height: 100%; object-fit: cover; }

        /* ═══════════════════════════════════════════════════════
           DIRECTORY PANEL
        ═══════════════════════════════════════════════════════ */
        .dir-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .dir-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .dir-card-header h5 {
            font-size: .95rem; font-weight: 700; color: var(--text); margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .dir-card-header .icon-circle {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem;
        }
        .ic-brand { background: var(--brand-lt); color: var(--brand); }
        .ic-green { background: #dcfce7; color: var(--green); }
        .ic-blue  { background: #dbeafe; color: var(--blue); }

        /* Section list */
        .sec-list { list-style: none; padding: 8px; margin: 0; max-height: 420px; overflow-y: auto; }
        .sec-list::-webkit-scrollbar { width: 4px; }
        .sec-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .sec-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px; cursor: pointer;
            transition: var(--trans); margin-bottom: 2px;
            border: 1px solid transparent;
        }
        .sec-item:hover { background: var(--surface2); border-color: var(--border); }
        .sec-item.active { background: var(--brand-lt); border-color: rgba(128, 0, 32, 0); }
        .sec-item.active .sec-item-name { color: var(--brand); font-weight: 700; }
        .sec-item.active .sec-item-count { background: var(--brand); color: white; }

        .sec-item-av {
            width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
            overflow: hidden; background: var(--brand-lt);
            display: flex; align-items: center; justify-content: center;
            color: var(--brand); font-weight: 700; font-size: .8rem;
        }
        .sec-item-av img { width: 100%; height: 100%; object-fit: cover; }
        .sec-item-body { flex: 1; min-width: 0; }
        .sec-item-name { font-size: .84rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sec-item-sub  { font-size: .72rem; color: var(--text3); margin-top: 1px; }
        .sec-item-count {
            font-size: .68rem; font-weight: 700; padding: 2px 7px;
            border-radius: 20px; background: var(--surface2); color: var(--text2);
            white-space: nowrap;
        }

        /* Unit list - same style, green accent */
        .unit-item.active { background: #dcfce7; border-color: rgba(22,163,74,.2); }
        .unit-item.active .sec-item-name { color: var(--green); }
        .unit-item.active .sec-item-count { background: var(--green); color: white; }
        .unit-item .sec-item-av { background: #dcfce7; color: var(--green); }

        /* Employee grid */
        .emp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 14px; }

        .emp-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 16px;
            display: flex; align-items: center; gap: 14px;
            transition: var(--trans);
        }
        .emp-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }

        .emp-av {
            width: 50px; height: 50px; border-radius: 50%; flex-shrink: 0;
            overflow: hidden; background: var(--brand-lt);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 700; color: var(--brand);
            border: 2px solid var(--border);
        }
        .emp-av img { width: 100%; height: 100%; object-fit: cover; }

        .emp-body { flex: 1; min-width: 0; }
        .emp-name { font-size: .88rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-pos  { font-size: .76rem; color: var(--text3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-meta { display: flex; gap: 6px; align-items: center; margin-top: 6px; font-size: .72rem; color: var(--text3); }

        .status-dot {
            width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
        }
        .sd-active  { background: var(--green); box-shadow: 0 0 0 2px #dcfce7; }
        .sd-leave   { background: var(--amber); box-shadow: 0 0 0 2px #fef3c7; }
        .sd-inactive{ background: #9ca3af;  box-shadow: 0 0 0 2px #f3f4f6; }

        .status-label { font-size: .7rem; font-weight: 600; }
        .sl-active   { color: var(--green); }
        .sl-leave    { color: var(--amber); }
        .sl-inactive { color: var(--text3); }

        /* Directory controls */
        .dir-controls {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
            background: var(--surface2);
        }
        .dir-search {
            flex: 1; min-width: 160px;
            position: relative;
        }
        .dir-search input {
            width: 100%; padding: 8px 12px 8px 34px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: .82rem; background: var(--surface); color: var(--text);
            transition: var(--trans);
        }
        .dir-search input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-lt); }
        .dir-search i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: .8rem; }

        .dir-select {
            padding: 7px 10px; border: 1px solid var(--border); border-radius: 8px;
            font-size: .8rem; color: var(--text); background: var(--surface); cursor: pointer;
        }
        .dir-select:focus { outline: none; border-color: var(--brand); }

        .emp-count-badge {
            font-size: .78rem; font-weight: 700; padding: 4px 11px;
            border-radius: 20px; background: var(--brand-lt); color: var(--brand);
        }

        /* Loading state */
        .dir-loading {
            display: none; text-align: center; padding: 48px 20px;
        }
        .dir-spin {
            width: 36px; height: 36px; border-radius: 50%;
            border: 3px solid var(--border);
            border-top-color: var(--brand);
            animation: dspin .7s linear infinite;
            margin: 0 auto 12px;
        }
        @keyframes dspin { to { transform: rotate(360deg); } }

        .dir-empty {
            display: none; text-align: center; padding: 48px 20px; color: var(--text3);
        }
        .dir-empty i { font-size: 3rem; margin-bottom: 14px; display: block; opacity: .4; }
        .dir-empty p { font-size: .88rem; }

        /* ═══════════════════════════════════════════════════════
           CHART CARDS
        ═══════════════════════════════════════════════════════ */
        .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        @media(max-width:900px) { .chart-row { grid-template-columns: 1fr; } }

        /* ═══════════════════════════════════════════════════════
           DARK MODE
        ═══════════════════════════════════════════════════════ */
        body.dark-mode {
            --bg: var(--body-bg, #0f172a);
            --surface: var(--card-bg, #1e293b);
            --surface2: rgba(255,255,255,.04);
            --border: var(--card-border, #334155);
            --text: var(--text-primary, #f1f5f9);
            --text2: var(--text-secondary, #cbd5e1);
            --text3: var(--text-muted, #64748b);
        }
        body.dark-mode .stat-bar-bg { background: rgba(255,255,255,.1); }
        body.dark-mode .dir-search input,
        body.dark-mode .dir-select { background: var(--surface) !important; color: var(--text) !important; border-color: var(--border) !important; }
        body.dark-mode .emp-card { background: var(--surface); border-color: var(--border); }
        body.dark-mode .dash-topbar { background: var(--surface); border-color: var(--border); }
        body.dark-mode .si-blue  { background: rgba(37,99,235,.2); }
        body.dark-mode .si-green { background: rgba(22,163,74,.2); }
        body.dark-mode .si-amber { background: rgba(217,119,6,.2); }
        body.dark-mode .ic-green { background: rgba(22,163,74,.2); }
        body.dark-mode .ic-blue  { background: rgba(37,99,235,.2); }
        body.dark-mode .sec-item-av { background: rgba(128,0,32,.2); }
        body.dark-mode .unit-item .sec-item-av { background: rgba(22,163,74,.2); }
        body.dark-mode .sec-item:hover { background: rgba(255,255,255,.04); }
        body.dark-mode .unit-item.active { background: rgba(22,163,74,.15); }
        body.dark-mode .sec-item-count { background: rgba(255,255,255,.08); color: var(--text2); }
        body.dark-mode .mh-staff-pills .mh-staff-pill { background: rgba(255,255,255,.1); }

        /* ── Misc helpers ── */
        .card-collapse-btn {
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--text2); border-radius: 8px; padding: 4px 10px;
            font-size: .78rem; cursor: pointer; transition: var(--trans);
        }
        .card-collapse-btn:hover { background: var(--border); }
        .section-chip {
            display: inline-block; font-size: .7rem; font-weight: 700;
            padding: 2px 9px; border-radius: 20px;
            background: var(--brand-lt); color: var(--brand);
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper" style="background:var(--bg,#f0f2f7);">

        <!-- ── Top bar ── -->
        <div class="dash-topbar">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h1></i>Organization Dashboard</h1>
                        <div class="sub">Welcome back, <strong><?= htmlspecialchars($user['user']) ?></strong> &mdash; <?= htmlspecialchars($role_name) ?></div>
                    </div>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="content" style="padding:20px 0 0;">
            <div class="container-fluid">


                <!-- ══════════════════════════════════════════════
                     MANAGER HERO
                ══════════════════════════════════════════════ -->
                <?php if ($manager): ?>
                <div class="manager-hero mb-4">
                    <div class="mh-inner">
                        <div class="mh-avatar">
                            <?php if (!empty($manager['picture'])): ?>
                                <img src="<?= $uploads_url . htmlspecialchars($manager['picture']) ?>" alt="Manager"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <span style="display:none"><i class="fas fa-user-tie"></i></span>
                            <?php else: ?>
                                <i class="fas fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                        <div class="mh-info">
                            <div class="mh-tag"><i class="fas fa-star mr-1"></i>Office Manager</div>
                            <div class="mh-name"><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></div>
                            <div class="mh-title"><?= htmlspecialchars($manager['position_name'] ?? 'Manager') ?></div>
                            <div class="mh-meta">
                                <?php if (!empty($manager['office_name'])): ?>
                                <span class="mh-meta-item"><i class="fas fa-building"></i><?= htmlspecialchars($manager['office_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($manager['email'])): ?>
                                <span class="mh-meta-item"><i class="fas fa-envelope"></i><?= htmlspecialchars($manager['email']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($manager['phone_number'])): ?>
                                <span class="mh-meta-item"><i class="fas fa-phone"></i><?= htmlspecialchars($manager['phone_number']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($manager_staff)): ?>
                    <div class="mh-staff-pills">
                        <span style="font-size:.72rem;color:rgba(255,255,255,.5);align-self:center;margin-right:4px;white-space:nowrap;">Office Staff:</span>
                        <?php foreach ($manager_staff as $staff): ?>
                        <div class="mh-staff-pill">
                            <div class="mh-sp-av">
                                <?php if (!empty($staff['employee_picture'])): ?>
                                    <img src="<?= $uploads_url . htmlspecialchars($staff['employee_picture']) ?>" alt="">
                                <?php else: ?>
                                    <?= strtoupper(substr($staff['employee_name'],0,1)) ?>
                                <?php endif; ?>
                            </div>
                            <?= htmlspecialchars($staff['employee_name']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════
                     EMPLOYEE DIRECTORY
                ══════════════════════════════════════════════ -->
                <div class="dir-card mb-4">
                    <div class="dir-card-header">
                        <h5><div class="icon-circle ic-brand"><i class="fas fa-address-book"></i></div>Employee Directory</h5>
                        <button class="card-collapse-btn" onclick="toggleDirectory(this)"><i class="fas fa-minus"></i> Collapse</button>
                    </div>

                    <div id="directoryBody">
                        <div class="row m-0" style="min-height:520px;">

                            <!-- ── Left: Sections ── -->
                            <div class="col-md-3 p-0" style="border-right:1px solid var(--border);">
                                <div class="dir-card-header" style="border-radius:0;background:var(--surface2);">
                                    <h5 style="font-size:.82rem;"><div class="icon-circle ic-brand" style="width:24px;height:24px;font-size:.7rem;"><i class="fas fa-building"></i></div>Sections</h5>
                                    <span class="emp-count-badge"><?= count($sections) ?></span>
                                </div>
                                <ul class="sec-list" id="sectionsList">
                                    <?php foreach ($sections as $idx => $sec): ?>
                                    <li class="sec-item <?= $idx===0?'active':'' ?>"
                                        data-section-id="<?= $sec['section_id'] ?>"
                                        data-section-name="<?= htmlspecialchars($sec['section_name']) ?>"
                                        data-head-name="<?= htmlspecialchars($sec['head_name']??'') ?>"
                                        data-head-picture="<?= htmlspecialchars($sec['head_picture']??'') ?>"
                                        data-unit-count="<?= $sec['unit_count'] ?>"
                                        onclick="selectSection(this, <?= $sec['section_id'] ?>)">
                                        <div class="sec-item-av">
                                            <?php if (!empty($sec['head_picture'])): ?>
                                                <img src="<?= $uploads_url . htmlspecialchars($sec['head_picture']) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(substr($sec['section_name'],0,1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sec-item-body">
                                            <div class="sec-item-name"><?= htmlspecialchars($sec['section_name']) ?></div>
                                            <div class="sec-item-sub"><?= htmlspecialchars($sec['head_name']??'No head assigned') ?></div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- Units sub-panel -->
                                <div id="unitsPanel" style="display:none; border-top:1px solid var(--border);">
                                    <div class="dir-card-header" style="border-radius:0;background:var(--surface2);padding:10px 16px;">
                                        <h5 style="font-size:.78rem;"><div class="icon-circle ic-green" style="width:22px;height:22px;font-size:.68rem;"><i class="fas fa-code-branch"></i></div>Units — <span id="selectedSectionName" style="color:var(--green)"></span></h5>
                                    </div>
                                    <ul class="sec-list" id="unitsList" style="max-height:220px;">
                                        <li class="sec-item unit-item" onclick="showAllEmployeesInSection()" style="font-size:.8rem;font-weight:600;color:var(--green);">
                                            <div class="sec-item-av" style="background:#dcfce7;color:var(--green);"><i class="fas fa-users" style="font-size:.7rem;"></i></div>
                                            <div class="sec-item-body"><div class="sec-item-name">All in Section</div></div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- ── Right: Employees ── -->
                            <div class="col-md-9 p-0" style="display:flex;flex-direction:column;">
                                <!-- Controls bar -->
                                <div class="dir-controls">
                                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                                        <span style="font-size:.88rem;font-weight:700;color:var(--text);white-space:nowrap;" id="selectedDepartment">Select Department</span>
                                        <small style="color:var(--text3);" id="selectedUnitInfo"></small>
                                        <span class="emp-count-badge" id="employeeCount">0 employees</span>
                                    </div>
                                    <div class="dir-search">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="searchInput" onkeyup="searchEmployees()" placeholder="Search name, ID, position…">
                                    </div>
                                    <select class="dir-select" id="statusFilter" onchange="filterEmployees()">
                                        <option value="">All Status</option>
                                        <option value="1">Active</option>
                                        <option value="2">On Leave</option>
                                        <option value="3">Inactive</option>
                                    </select>
                                </div>

                                <div style="flex:1;padding:16px;overflow-y:auto;">
                                    <!-- Loading -->
                                    <div class="dir-loading" id="loadingIndicator">
                                        <div class="dir-spin"></div>
                                        <p style="font-size:.85rem;color:var(--text3);">Loading employees…</p>
                                    </div>

                                    <!-- Grid -->
                                    <div id="employeesGrid" style="display:none;">
                                        <div class="emp-grid" id="employeesContainer"></div>
                                    </div>

                                    <!-- Empty -->
                                    <div class="dir-empty" id="noEmployees">
                                        <i class="fas fa-users"></i>
                                        <p id="noEmployeesMessage">Please select a department to view employees</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div><!-- /directoryBody -->
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
// ── Global state ──────────────────────────────────────────────
let sections      = <?= json_encode($sections) ?>;
let unitSections  = <?= json_encode($unit_sections) ?>;
let currentSectionId = sections.length > 0 ? sections[0].section_id : null;
let currentUnitId    = null;
let allEmployees     = [];
let filteredEmployees= [];
let searchQuery      = '';
let statusFilter     = '';
const uploadsUrl     = '<?= $uploads_url ?>';

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    if (sections.length > 0) {
        updateUnitsPanel(sections[0].section_id);
        loadEmployees(sections[0].section_id, null);
        document.getElementById('selectedDepartment').textContent = sections[0].section_name;
    } else {
        document.getElementById('noEmployees').style.display = 'block';
    }
    initCharts();
});

// ── Charts ────────────────────────────────────────────────────
function initCharts() {
    // Appointment pie
    const apptEl = document.getElementById('appointmentChart');
    if (apptEl) {
        const apptData = <?= json_encode($appointment_data) ?>;
        if (apptData.length > 0) {
            new Chart(apptEl, {
                type: 'doughnut',
                data: {
                    labels: apptData.map(d => d.status_name),
                    datasets: [{
                        data: apptData.map(d => parseInt(d.count)),
                        backgroundColor: apptData.map(d => d.color || '#800020'),
                        borderWidth: 2,
                        borderColor: 'var(--surface, #fff)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } }
                    },
                    cutout: '65%'
                }
            });
        }
    }

    // Gender bar chart
    const genderEl = document.getElementById('genderChart');
    if (genderEl) {
        const gd = <?= json_encode($gender_data) ?>;
        if (gd.length > 0) {
            new Chart(genderEl, {
                type: 'bar',
                data: {
                    labels: gd.map(d => d.section_name),
                    datasets: [
                        { label:'Male',   data: gd.map(d=>parseInt(d.male_count)),   backgroundColor:'#2563eb', borderRadius:4 },
                        { label:'Female', data: gd.map(d=>parseInt(d.female_count)), backgroundColor:'#db2777', borderRadius:4 }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position:'top', labels:{ font:{size:11} } } },
                    scales: {
                        x: { grid:{display:false}, ticks:{font:{size:10}} },
                        y: { beginAtZero:true, grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:10}} }
                    }
                }
            });
        }
    }
}

// ── Directory toggle ──────────────────────────────────────────
function toggleDirectory(btn) {
    const body = document.getElementById('directoryBody');
    if (body.style.display === 'none') {
        body.style.display = '';
        btn.innerHTML = '<i class="fas fa-minus"></i> Collapse';
    } else {
        body.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-plus"></i> Expand';
    }
}

// ── Select section ────────────────────────────────────────────
function selectSection(el, sectionId) {
    document.querySelectorAll('#sectionsList .sec-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    const section = sections.find(s => s.section_id == sectionId);
    if (!section) return;
    currentSectionId = sectionId; currentUnitId = null;
    document.getElementById('selectedDepartment').textContent = section.section_name;
    document.getElementById('selectedUnitInfo').textContent = '';
    updateUnitsPanel(sectionId);
    if ((section.unit_count || 0) === 0) {
        loadEmployees(sectionId, null);
    } else {
        document.getElementById('employeesGrid').style.display = 'none';
        document.getElementById('noEmployees').style.display = 'block';
        document.getElementById('noEmployeesMessage').textContent = 'Please select a unit to view employees';
        document.getElementById('employeeCount').textContent = '0 employees';
        allEmployees = []; filteredEmployees = [];
    }
}

// ── Update units panel ────────────────────────────────────────
function updateUnitsPanel(sectionId) {
    const section = sections.find(s => s.section_id == sectionId);
    if (!section) return;
    const panel = document.getElementById('unitsPanel');
    const list  = document.getElementById('unitsList');
    const nameEl= document.getElementById('selectedSectionName');
    if ((section.unit_count || 0) > 0) {
        const units = unitSections.filter(u => u.section_id == sectionId);
        while (list.children.length > 1) list.removeChild(list.lastChild);
        units.forEach(unit => {
            const li = document.createElement('li');
            li.className = 'sec-item unit-item';
            li.setAttribute('data-unit-id', unit.unit_id);
            li.setAttribute('onclick', `selectUnit(this, ${unit.unit_id})`);
            li.innerHTML = `
                <div class="sec-item-av" style="background:#dcfce7;color:var(--green);">
                    ${unit.head_picture
                        ? `<img src="${uploadsUrl}${escapeHtml(unit.head_picture)}" alt="" onerror="this.parentElement.textContent='${unit.head_name ? unit.head_name.charAt(0) : '?'}';">`
                        : (unit.head_name ? unit.head_name.charAt(0).toUpperCase() : '?')}
                </div>
                <div class="sec-item-body">
                    <div class="sec-item-name">${escapeHtml(unit.unit_name)}</div>
                    <div class="sec-item-sub">${escapeHtml(unit.head_name || 'No head assigned')}</div>
                </div>`;
            list.appendChild(li);
        });
        nameEl.textContent = section.section_name;
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

// ── Select unit ───────────────────────────────────────────────
function selectUnit(el, unitId) {
    document.querySelectorAll('#unitsList .sec-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    const unit = unitSections.find(u => u.unit_id == unitId);
    if (!unit) return;
    currentUnitId = unitId;
    document.getElementById('selectedDepartment').textContent = unit.unit_name;
    document.getElementById('selectedUnitInfo').textContent = `(${unit.section_name})`;
    loadEmployees(null, unitId);
}

// ── Show all in section ───────────────────────────────────────
function showAllEmployeesInSection() {
    document.querySelectorAll('#unitsList .sec-item').forEach(i => i.classList.remove('active'));
    currentUnitId = null;
    const sec = sections.find(s => s.section_id == currentSectionId);
    if (sec) document.getElementById('selectedDepartment').textContent = sec.section_name;
    document.getElementById('selectedUnitInfo').textContent = '';
    loadEmployees(currentSectionId, null);
}

// ── Load employees ────────────────────────────────────────────
async function loadEmployees(sectionId, unitId) {
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('employeesGrid').style.display = 'none';
    document.getElementById('noEmployees').style.display = 'none';
    try {
        const fd = new FormData();
        if (sectionId) fd.append('section_id', sectionId);
        if (unitId)    fd.append('unit_id', unitId);
        const res  = await fetch('get_employees.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            allEmployees = data.employees || [];
            filteredEmployees = [...allEmployees];
            displayEmployees();
        } else {
            allEmployees = []; filteredEmployees = [];
            displayEmployees();
            if (typeof toastr !== 'undefined') toastr.error(data.message || 'Failed to load employees');
        }
    } catch(e) {
        allEmployees = []; filteredEmployees = [];
        displayEmployees();
        if (typeof toastr !== 'undefined') toastr.error('Failed to connect to server');
    } finally {
        document.getElementById('loadingIndicator').style.display = 'none';
    }
}

// ── Display employees ─────────────────────────────────────────
function displayEmployees() {
    applySearchAndFilter();
    const container = document.getElementById('employeesContainer');
    const grid      = document.getElementById('employeesGrid');
    const none      = document.getElementById('noEmployees');
    const countEl   = document.getElementById('employeeCount');

    if (filteredEmployees.length > 0) {
        container.innerHTML = '';
        filteredEmployees.forEach(emp => {
            const initials = ((emp.first_name||'').charAt(0) + (emp.last_name||'').charAt(0)).toUpperCase();
            let sdClass = 'sd-inactive', slClass = 'sl-inactive', statusTxt = emp.status_name || 'Unknown';
            if (emp.employment_status_id == 1) { sdClass='sd-active';   slClass='sl-active'; }
            if (emp.employment_status_id == 2) { sdClass='sd-leave';    slClass='sl-leave'; }

            const card = document.createElement('div');
            card.className = 'emp-card';
            card.innerHTML = `
                <div class="emp-av">
                    ${emp.picture
                        ? `<img src="${uploadsUrl}${escapeHtml(emp.picture)}" alt="${escapeHtml(emp.first_name+' '+emp.last_name)}"
                                onerror="this.style.display='none';this.parentElement.textContent='${initials}'">`
                        : initials}
                </div>
                <div class="emp-body">
                    <div class="emp-name">${escapeHtml(emp.first_name+' '+emp.last_name)}</div>
                    <div class="emp-pos">${escapeHtml(emp.position_name||'No Position')}</div>
                    <div class="emp-meta">
                        <span class="status-dot ${sdClass}"></span>
                        <span class="status-label ${slClass}">${escapeHtml(statusTxt)}</span>
                        <span style="margin-left:4px;">&middot; ID: ${escapeHtml(emp.id_number||'—')}</span>
                    </div>
                </div>`;
            container.appendChild(card);
        });
        grid.style.display = 'block';
        none.style.display = 'none';
        countEl.textContent = `${filteredEmployees.length} employee${filteredEmployees.length!==1?'s':''}`;
    } else {
        grid.style.display = 'none';
        none.style.display = 'block';
        document.getElementById('noEmployeesMessage').textContent =
            (currentSectionId || currentUnitId) ? 'No employees found in this department' : 'Please select a department to view employees';
        countEl.textContent = '0 employees';
    }
}

// ── Search / Filter ───────────────────────────────────────────
function applySearchAndFilter() {
    filteredEmployees = allEmployees.filter(emp => {
        if (searchQuery) {
            const full = (emp.first_name+' '+emp.last_name).toLowerCase();
            const id   = (emp.id_number||'').toLowerCase();
            const pos  = (emp.position_name||'').toLowerCase();
            const q    = searchQuery.toLowerCase();
            if (!full.includes(q) && !id.includes(q) && !pos.includes(q)) return false;
        }
        if (statusFilter && emp.employment_status_id != statusFilter) return false;
        return true;
    });
}
function searchEmployees() { searchQuery = document.getElementById('searchInput').value; displayEmployees(); }
function filterEmployees() { statusFilter = document.getElementById('statusFilter').value; displayEmployees(); }
function escapeHtml(t) { if (!t) return ''; const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

// ── Session toasts ────────────────────────────────────────────
<?php if (isset($_SESSION['toast'])): ?>
$(document).ready(function() {
    toastr.<?= $_SESSION['toast']['type'] ?>('<?= addslashes($_SESSION['toast']['message']) ?>');
});
<?php unset($_SESSION['toast']); endif; ?>
</script>
</body>
</html>