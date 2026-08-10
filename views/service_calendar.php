<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

$database = new Database();
$db = $database->getConnection();
checkPermission('view_calendar');

// Fetch service requests for the calendar
$query = "SELECT sr.*, 
          CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
          v.model as vehicle_model,
          v.plate_no,
          CONCAT(drv.first_name, ' ', drv.last_name) AS driver_name
          FROM service_requests sr
          JOIN employee req ON sr.requesting_emp_id = req.emp_id
          LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
          LEFT JOIN employee drv ON sr.driver_emp_id = drv.emp_id
          WHERE sr.status = 'approved'";
$service_requests = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Fetch approved passengers for each request
foreach ($service_requests as &$request) {
    $passenger_query = "SELECT CONCAT(e.first_name, ' ', e.last_name) AS passenger_name 
                       FROM service_request_passengers p
                       JOIN employee e ON p.emp_id = e.emp_id
                       WHERE p.request_id = ? AND p.approved = 1";
    $stmt = $db->prepare($passenger_query);
    $stmt->bind_param("i", $request['request_id']);
    $stmt->execute();
    $passengers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $request['passengers'] = array_column($passengers, 'passenger_name');
}
unset($request); // Break the reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transport Service Calendar</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <style>
    /* ── Design Tokens ─────────────────────────────────────────── */
    :root {
        /* Brand + radius/shadow/font tokens only — these don't change
           between light/dark mode. Surface, border and text colors are
           read directly from mainheader.php's site-wide variables
           (--body-bg, --card-bg, --card-border, --text-primary, etc.)
           at each point of use below, so they react live to body.dark-mode
           instead of being frozen to a single mode here at :root. */
        --primary:        var(--green, #24e78f);
        --primary-dark:   var(--green-dark, #2a9863);
        --accent:         #c9a227;   /* muted gold — echoes the hero's gold accents */
        --accent-dark:    #a9861c;
        --success:        #10b981;
        --warning:        #f59e0b;
        --danger:         #ef4444;
        --birthday:       #8b5cf6;
        --meeting:        #3b5bdb;
        --holiday:        #f59e0b;
        --event:          var(--green-dark, #2a9863);
        --leave:          #e03131;   /* approved leave */

        --radius-sm:  6px;
        --radius:     12px;
        --radius-lg:  18px;
        --shadow-sm:  0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --shadow:     0 4px 16px rgba(0,0,0,.08);
        --shadow-lg:  0 12px 40px rgba(0,0,0,.12);

        --font-ui:    'DM Sans', sans-serif;
        --font-head:  'Syne', sans-serif;
    }

    /* ── Base overrides ─────────────────────────────────────────── */
    body, .content-wrapper { background: var(--body-bg) !important; font-family: var(--font-ui) !important; }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    
    /* ── Page header ────────────────────────────────────────────── */
    .content-header h1 {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-primary);
        letter-spacing: -0.03em;
    }

    /* ── Cards ──────────────────────────────────────────────────── */
    .card {
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius) !important;
        box-shadow: none !important;
        overflow: hidden;
        background: var(--card-bg) !important;
    }

    .card-primary { border-color: var(--card-border) !important; }

    .card-header {
        background: var(--card-bg) !important;
        border-bottom: 1px solid var(--table-border) !important;
        padding: 1rem 1.25rem !important;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .card-header h3,
    .card-header h4,
    .card-header .card-title {
        font-family: var(--font-ui);
        font-size: .95rem !important;
        font-weight: 600 !important;
        color: var(--text-primary) !important;
        letter-spacing: 0;
        margin: 0 !important;
    }

    /* ── Sidebar form ───────────────────────────────────────────── */
    .form-group label {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: .3rem;
    }
    .form-control {
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius-sm) !important;
        font-family: var(--font-ui) !important;
        font-size: .875rem !important;
        color: var(--text-primary) !important;
        padding: .5rem .75rem !important;
        background: var(--table-stripe) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(36,231,143,.18) !important;
        background: var(--card-bg) !important;
    }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* ── Buttons ────────────────────────────────────────────────── */
    .btn {
        font-family: var(--font-ui) !important;
        font-weight: 600 !important;
        font-size: .85rem !important;
        border-radius: var(--radius-sm) !important;
        transition: background .15s ease, border-color .15s ease !important;
        letter-spacing: 0;
    }
    .btn-primary, .btn-primary.btn-block {
        background: var(--primary) !important;
        border: 1px solid var(--primary) !important;
        color: #fff !important;
        box-shadow: none !important;
    }
    .btn-primary:hover {
        background: var(--primary-dark) !important;
        border-color: var(--primary-dark) !important;
        box-shadow: none !important;
        transform: none;
    }
    .btn-danger {
        background: var(--danger) !important;
        border: 1px solid var(--danger) !important;
        box-shadow: none !important;
    }
    .btn-secondary {
        background: var(--table-stripe) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-muted) !important;
    }
    .btn-secondary:hover {
        background: var(--table-border) !important;
        color: var(--text-primary) !important;
    }
    /* ── FullCalendar toolbar ───────────────────────────────────── */
    .fc .fc-toolbar { padding: 1rem 1.25rem .5rem; }
    .fc .fc-toolbar-title {
        font-family: var(--font-ui) !important;
        font-size: 1.1rem !important;
        font-weight: 600 !important;
        color: var(--text-primary) !important;
        letter-spacing: 0;
    }
    .fc .fc-button {
        border-radius: var(--radius-sm) !important;
        font-family: var(--font-ui) !important;
        font-size: .8rem !important;
        font-weight: 600 !important;
        padding: .35rem .75rem !important;
        transition: background .15s, color .15s, border-color .15s !important;
    }
    .fc .fc-button-primary {
        background: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-muted) !important;
        box-shadow: none !important;
    }
    .fc .fc-button-primary:hover,
    .fc .fc-button-primary:not(:disabled):active,
    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
        box-shadow: none !important;
    }
    .fc .fc-today-button {
        background: transparent !important;
        border: 1px solid var(--accent) !important;
        color: var(--accent-dark) !important;
        box-shadow: none !important;
    }
    .fc .fc-today-button:hover {
        background: var(--notification-unread-bg) !important;
    }

    /* ── Calendar grid ──────────────────────────────────────────── */
    .fc-day-today { background: var(--notification-unread-bg) !important; }
    .fc-day-today .fc-daygrid-day-number {
        background: var(--primary);
        color: #fff !important;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: .82rem;
    }
    .fc-col-header-cell {
        background: var(--table-stripe) !important;
        font-family: var(--font-ui) !important;
        font-size: .78rem !important;
        font-weight: 700 !important;
        color: var(--text-muted) !important;
        text-transform: uppercase;
        letter-spacing: .07em;
        border-color: var(--card-border) !important;
    }
    .fc-daygrid-day-number {
        font-size: .82rem;
        font-weight: 500;
        color: var(--text-muted) !important;
        padding: 6px 8px !important;
    }
    .fc td, .fc th { border-color: var(--table-border) !important; }

    /* ── Event pills ────────────────────────────────────────────── */
    .fc-event {
        border-radius: 5px !important;
        border: none !important;
        font-size: .76rem !important;
        font-weight: 600 !important;
        padding: 2px 6px !important;
    }
    .fc-event-title { white-space: normal !important; }
    .birthday-event  { background: var(--birthday)  !important; }
    .holiday-event   { background: var(--holiday)   !important; }
    .meeting-event   { background: var(--meeting)   !important; }
    .event-event     { background: var(--event)     !important; }
    .leave-event     { background: var(--leave)     !important; opacity: .88; }

    /* ── Tables ─────────────────────────────────────────────────── */
    .table { font-size: .86rem; color: var(--text-primary); }
    .table thead th {
        background: var(--table-stripe) !important;
        border-bottom: 2px solid var(--card-border) !important;
        font-weight: 700;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--text-muted) !important;
        padding: .75rem 1rem;
        white-space: nowrap;
    }
    .table tbody td { padding: .7rem 1rem; border-color: var(--table-border) !important; vertical-align: middle; }
    .table-hover tbody tr { transition: background .15s; }
    .table-hover tbody tr:hover td { background: var(--notification-unread-bg) !important; }
    #events-table tr.table-info td { background: rgba(36,231,143,.1) !important; }

    /* ── Badges ─────────────────────────────────────────────────── */
    .badge {
        border-radius: 20px !important;
        font-size: .72rem !important;
        font-weight: 700 !important;
        padding: .28em .7em !important;
        letter-spacing: .03em;
    }
    .badge-primary { background: var(--primary) !important; color: #fff; }
    span.badge[style*="background-color:#4361ee"] { background: var(--event)   !important; }
    span.badge[style*="background-color:#3f37c9"] { background: var(--meeting) !important; }
    span.badge[style*="background-color:#ffa500"] { background: var(--holiday) !important; }
    span.badge[style*="background-color:#4895ef"] { background: var(--birthday)!important; }

    /* ── Loading spinner ────────────────────────────────────────── */
    .calendar-loading { position: relative; min-height: 300px; }
    .calendar-loading::after {
        content: "";
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        width: 36px; height: 36px;
        border: 3px solid var(--card-border);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        z-index: 1000;
    }
    @keyframes spin {
        to { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* ── Error box ──────────────────────────────────────────────── */
    #calendar-error {
        margin: 1rem;
        padding: 1rem 1.25rem;
        border-left: 4px solid var(--danger);
        background: #fef2f2;
        color: #7f1d1d;
        border-radius: var(--radius-sm);
        font-size: .875rem;
    }
    #calendar-error .close { color: #7f1d1d; opacity: .7; }
    #calendar-error .btn   { margin-left: .5rem; }
    /* ── Modals ─────────────────────────────────────────────────── */
    .modal-content {
        border: none !important;
        border-radius: var(--radius) !important;
        box-shadow: var(--shadow) !important;
        overflow: hidden;
    }
    .modal-header {
        background: var(--primary) !important;
        border: none !important;
        padding: 1.1rem 1.5rem !important;
    }
    .modal-title {
        font-family: var(--font-ui) !important;
        font-weight: 600 !important;
        font-size: 1rem !important;
        color: #fff !important;
        letter-spacing: 0;
    }
    .modal-header .close { color: rgba(255,255,255,.8) !important; text-shadow: none !important; font-size: 1.4rem; }
    .modal-header .close:hover { color: #fff !important; }
    .modal-body  { padding: 1.5rem !important; background: var(--card-bg); }
    .modal-footer {
        padding: 1rem 1.5rem !important;
        border-top: 1px solid var(--table-border) !important;
        background: var(--table-stripe);
        gap: .5rem;
        display: flex;
    }

    /* ── DataTables tweaks ──────────────────────────────────────── */
    div.dataTables_wrapper div.dataTables_length select,
    div.dataTables_wrapper div.dataTables_filter input {
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius-sm);
        padding: .3rem .6rem;
        font-size: .82rem;
        font-family: var(--font-ui);
        color: var(--text-primary);
        background: var(--table-stripe);
    }
    div.dataTables_wrapper div.dataTables_info,
    div.dataTables_wrapper div.dataTables_length label,
    div.dataTables_wrapper div.dataTables_filter label {
        font-size: .8rem;
        color: var(--text-muted);
        font-family: var(--font-ui);
    }
    .paginate_button { border-radius: var(--radius-sm) !important; font-size: .8rem !important; }
    .paginate_button.current {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
    }

    /* ── Misc ───────────────────────────────────────────────────── */
    .sticky-top { top: 1rem; }
    .toast { font-size: .875rem; padding: 1rem; }
    #events-table { width: 100% !important; }
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

    /* ── Service Calendar specific ─────────────────────────────────── */
    .calendar-stats {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
    }
    .calendar-stats h5 {
        font-family: var(--font-ui);
        font-size: .85rem;
        font-weight: 700;
        color: var(--text-primary);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin: 0 0 .65rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .calendar-stats h5 i { color: var(--primary); }
    .calendar-stats .d-flex {
        font-size: .84rem;
        color: var(--text-muted);
        padding: .3rem 0;
        border-bottom: 1px dashed var(--card-border);
    }
    .calendar-stats .d-flex:last-child { border-bottom: none; }
    .calendar-stats .d-flex strong { color: var(--text-primary); font-weight: 700; }

    .passenger-list { max-height: 150px; overflow-y: auto; }
    .passenger-list li { color: var(--text-primary); }

    #calendar { min-height: 600px; }
    .fc-event.fc-event-multi-day {
        border-left: 3px solid rgba(255,255,255,.35) !important;
        border-right: 3px solid rgba(255,255,255,.35) !important;
    }

    /* ── Event legend ───────────────────────────────────────────── */
    .cal-legend {
        display: flex; flex-wrap: wrap; gap: 10px 18px;
        padding: .85rem 1.25rem;
        border-top: 1px solid var(--table-border);
        font-size: .78rem; color: var(--text-muted);
    }
    .cal-legend-item { display: flex; align-items: center; gap: 5px; }
    .cal-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

    @media (max-width: 991px) {
        .sticky-top { position: relative !important; top: 0 !important; }
    }
</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar_service.php'; ?>
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
                    <div class="pg-hero-title"><i class="fas fa-calendar-alt"></i>Transport Service Calendar</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Approved trips, birthdays and office events</p>
                </div>
            </div>
        </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-4">
            <div class="sticky-top" style="z-index: 1000; top: 20px;">
              
              <!-- Calendar Stats -->
              <div class="calendar-stats">
                <h5><i class="fas fa-chart-bar"></i> Statistics</h5>
                <div class="d-flex justify-content-between">
                  <span>This Month:</span>
                  <strong id="month-stats">0 trips</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>This Week:</span>
                  <strong id="week-stats">0 trips</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>Today:</span>
                  <strong id="today-stats">0 trips</strong>
                </div>
              </div>

              <!-- Upcoming Service Requests -->
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-list"></i> Upcoming Service Requests</h3>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm" id="upcoming-requests-table">
                      <thead>
                        <tr>
                          <th>Destination</th>
                          <th>Date Range</th>
                          <th>Requester</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- Will be populated by JavaScript -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Recent Activity -->
              <div class="card mt-3">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm" id="recent-activity-table">
                    <thead>
                      <tr>
                        <th>Activity</th>
                        <th>Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Will be populated by JavaScript -->
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>

          <div class="col-md-8">
            <div class="card card-primary">
              <div class="card-body p-0">
                <div id="calendar" style="min-height: 600px;"></div>
                <div class="cal-legend">
                  <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--meeting)"></span>Approved Trip</span>
                  <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--event)"></span>Multi-day Trip</span>
                  <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--birthday)"></span>Birthday</span>
                  <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--holiday)"></span>Holiday</span>
                </div>
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
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
  // Global calendar variable
  let calendar;
  let upcomingRequestsTable;
  let recentActivityTable;

  $(function () {
      // Initialize DataTables
      upcomingRequestsTable = $('#upcoming-requests-table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "order": [[1, 'asc']],
        "columns": [
          { "data": "destination" },
          { "data": "daterange" },
          { "data": "requester" }
        ]
      });
      
      recentActivityTable = $('#recent-activity-table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": false,
        "info": false,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "columns": [
          { "data": "activity" },
          { "data": "time" }
        ]
      });

      // Prepare service request data for calendar
      const serviceRequests = <?php echo json_encode($service_requests); ?>;
      const serviceRequestEvents = serviceRequests.map(request => {
        // Parse the date_of_travel and date_of_travel_end to ensure they're in the correct format
        const travelStartDate = moment(request.date_of_travel);
        const travelEndDate = request.date_of_travel_end ? moment(request.date_of_travel_end) : travelStartDate;
        
        const departureTime = moment(request.time_departure, 'HH:mm:ss');
        const returnTime = moment(request.time_return, 'HH:mm:ss');
        
        // Check if this is a multi-day event
        const isMultiDay = travelStartDate.format('YYYY-MM-DD') !== travelEndDate.format('YYYY-MM-DD');
        
        // For multi-day events, we need to handle the start and end differently
        let startDateTime, endDateTime;
        
        if (isMultiDay) {
          // For multi-day events, use the full date range without specific times in the calendar
          startDateTime = travelStartDate.format('YYYY-MM-DD');
          endDateTime = travelEndDate.add(1, 'day').format('YYYY-MM-DD'); // Add 1 day for FullCalendar's exclusive end
        } else {
          // For single day events, include the specific times
          startDateTime = travelStartDate.clone()
            .set({
              hour: departureTime.hour(),
              minute: departureTime.minute(),
              second: 0
            })
            .format('YYYY-MM-DDTHH:mm:ss');
          
          endDateTime = travelStartDate.clone()
            .set({
              hour: returnTime.hour(),
              minute: returnTime.minute(),
              second: 0
            })
            .format('YYYY-MM-DDTHH:mm:ss');
        }
        
        return {
          id: 'service_' + request.request_id,
          title: '🚗 ' + request.destination + (isMultiDay ? ' (' + (travelEndDate.diff(travelStartDate, 'days') + 1) + ' days)' : ''),
          start: startDateTime,
          end: endDateTime,
          allDay: isMultiDay, // Make multi-day events all-day events
          extendedProps: {
            type: 'service_request',
            request_id: request.request_id,
            requester: request.requester_name,
            vehicle: request.vehicle_model || 'N/A',
            plate_no: request.plate_no || 'N/A',
            driver: request.driver_name || 'N/A',
            destination: request.destination,
            purpose: request.purpose,
            passengers: request.passengers || [],
            raw_date: request.date_of_travel, // Keep original for reference
            date_of_travel_end: request.date_of_travel_end,
            is_multi_day: isMultiDay,
            time_departure: request.time_departure,
            time_return: request.time_return
          },
          backgroundColor: isMultiDay ? 'var(--event)' : 'var(--meeting)',
          borderColor: isMultiDay ? 'var(--event)' : 'var(--meeting)',
          textColor: '#fff',
          classNames: isMultiDay ? ['fc-event-multi-day'] : []
        };
      });
      
      // Initialize calendar
      const calendarEl = document.getElementById('calendar');
      calendar = new FullCalendar.Calendar(calendarEl, {
          headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
          },
          themeSystem: 'bootstrap',
          initialView: 'dayGridMonth',
          navLinks: true,
          editable: false,
          selectable: true,
          dayMaxEvents: 3,
          eventDisplay: 'block',
          eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
          },
          
          events: function(fetchInfo, successCallback, failureCallback) {
              $.when(
                  $.ajax({
                      url: 'get_events.php',
                      type: 'GET',
                      dataType: 'json'
                  }),
                  $.ajax({
                      url: 'get_birthdays.php',
                      type: 'GET',
                      dataType: 'json'
                  })
              ).then(function(eventsResponse, birthdaysResponse) {
                  let events = eventsResponse[0].data || [];
                  let birthdays = birthdaysResponse[0].data || [];
                  
                  const allEvents = events.concat(birthdays, serviceRequestEvents);
                  successCallback(allEvents);
                  
                  // Update statistics
                  updateStatistics(allEvents);
                  
              }).catch(function(error) {
                  console.error('Error loading events:', error);
                  failureCallback('Failed to load events');
              });
          },
          
          eventClick: function(info) {
              if (info.event.extendedProps.type === 'service_request') {
                  showServiceRequestDetails(info.event);
                  return;
              }
              
              if (info.event.extendedProps.type === 'birthday') {
                  return;
              }
              
              // Handle other events
              $('#event-id').val(info.event.id);
              $('#modal-title-input').val(info.event.title);
              $('#modal-type').val(info.event.extendedProps.type || 'event');
              $('#modal-start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
              $('#modal-end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
              $('#modal-description').val(info.event.extendedProps.description || '');
              $('#event-modal').modal('show');
          },
          
          dateClick: function(info) {
              const dateStr = moment(info.date).format('MMMM D, YYYY');
              $('#modal-date-title').text(dateStr);
              $('#quick-event-start-time').val('09:00');
              $('#quick-event-end-time').val('17:00');
              $('#quick-event-title').val('');
              $('#quick-event-type').val('event');
              $('#quick-event-description').val('');
              $('#day-click-modal').data('date', info.date);
              $('#day-click-modal').modal('show');
          },
          
          eventDidMount: function(info) {
              // Add tooltips to events
              if (info.event.extendedProps.type === 'service_request') {
                  const props = info.event.extendedProps;
                  let tooltipText = `Transport to ${props.destination}\n`;
                  
                  if (props.is_multi_day) {
                    const startDate = moment(info.event.start);
                    const endDate = moment(info.event.end).subtract(1, 'day');
                    tooltipText += `Date: ${startDate.format('MMM D')} - ${endDate.format('MMM D, YYYY')}\n`;
                  } else {
                    tooltipText += `Date: ${moment(info.event.start).format('MMM D, YYYY')}\n`;
                    tooltipText += `Time: ${moment(props.time_departure, 'HH:mm:ss').format('h:mm A')} - ${moment(props.time_return, 'HH:mm:ss').format('h:mm A')}\n`;
                  }
                  
                  tooltipText += `Driver: ${props.driver}\n`;
                  tooltipText += `Vehicle: ${props.vehicle}`;
                  
                  $(info.el).attr('title', tooltipText);
              }
          }
      });
      
      calendar.render();
      
      // Load initial data
      loadServiceRequestsTable();
      loadRecentActivity();
  });

  // Function to show service request details with passengers
  function showServiceRequestDetails(event) {
      const props = event.extendedProps;
      const passengersHtml = props.passengers.length > 0 
          ? props.passengers.map(p => `<li>${p}</li>`).join('')
          : '<li class="text-muted">No passengers</li>';
      
      // Format date range display
      let dateRangeHtml;
      if (props.is_multi_day) {
          const startDate = moment(event.start);
          const endDate = moment(event.end).subtract(1, 'day'); // Subtract 1 day because FullCalendar's end is exclusive
          dateRangeHtml = `${startDate.format('MMM D, YYYY')} - ${endDate.format('MMM D, YYYY')}`;
      } else {
          dateRangeHtml = `${moment(event.start).format('MMM D, YYYY')}<br>
                          <strong>Time:</strong> ${moment(props.time_departure, 'HH:mm:ss').format('h:mm A')} - ${moment(props.time_return, 'HH:mm:ss').format('h:mm A')}`;
      }
      
      const html = `
        <div class="service-request-details">
          <h4>Transport Request Details</h4>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Requester:</strong> ${props.requester}</p>
              <p><strong>Destination:</strong> ${props.destination}</p>
              <p><strong>Date Range:</strong> ${dateRangeHtml}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Vehicle:</strong> ${props.vehicle}</p>
              <p><strong>Plate Number:</strong> ${props.plate_no}</p>
              <p><strong>Driver:</strong> ${props.driver}</p>
            </div>
          </div>
          <p><strong>Purpose:</strong> ${props.purpose}</p>
          <p><strong>Approved Passengers:</strong></p>
          <ul class="passenger-list">${passengersHtml}</ul>
        </div>
      `;
      
      Swal.fire({
        title: 'Transport Request',
        html: html,
        icon: 'info',
        width: '700px',
        confirmButtonText: 'Close',
        showCloseButton: true
      });
  }

  // Function to update calendar statistics
  function updateStatistics(events) {
      const today = moment().startOf('day');
      const weekStart = moment().startOf('week');
      const weekEnd = moment().endOf('week');
      const monthStart = moment().startOf('month');
      const monthEnd = moment().endOf('month');
      
      const todayTrips = events.filter(event => {
          if (event.extendedProps?.type !== 'service_request') return false;
          
          if (event.extendedProps.is_multi_day) {
              // For multi-day events, check if today is within the event range
              const eventStart = moment(event.start);
              const eventEnd = moment(event.end).subtract(1, 'day'); // Subtract 1 day because FullCalendar's end is exclusive
              return today.isBetween(eventStart, eventEnd, null, '[]');
          } else {
              // For single day events, check if it's today
              return moment(event.start).isSame(today, 'day');
          }
      }).length;
      
      const weekTrips = events.filter(event => {
          if (event.extendedProps?.type !== 'service_request') return false;
          
          if (event.extendedProps.is_multi_day) {
              // For multi-day events, check if the week overlaps with the event
              const eventStart = moment(event.start);
              const eventEnd = moment(event.end).subtract(1, 'day'); // Subtract 1 day because FullCalendar's end is exclusive
              return (eventStart.isBetween(weekStart, weekEnd, null, '[]') ||
                     eventEnd.isBetween(weekStart, weekEnd, null, '[]') ||
                     (eventStart.isBefore(weekStart) && eventEnd.isAfter(weekEnd)));
          } else {
              // For single day events, check if it's within the week
              return moment(event.start).isBetween(weekStart, weekEnd, null, '[]');
          }
      }).length;
      
      const monthTrips = events.filter(event => {
          if (event.extendedProps?.type !== 'service_request') return false;
          
          if (event.extendedProps.is_multi_day) {
              // For multi-day events, check if the month overlaps with the event
              const eventStart = moment(event.start);
              const eventEnd = moment(event.end).subtract(1, 'day'); // Subtract 1 day because FullCalendar's end is exclusive
              return (eventStart.isBetween(monthStart, monthEnd, null, '[]') ||
                     eventEnd.isBetween(monthStart, monthEnd, null, '[]') ||
                     (eventStart.isBefore(monthStart) && eventEnd.isAfter(monthEnd)));
          } else {
              // For single day events, check if it's within the month
              return moment(event.start).isBetween(monthStart, monthEnd, null, '[]');
          }
      }).length;
      
      $('#today-stats').text(`${todayTrips} trip${todayTrips !== 1 ? 's' : ''}`);
      $('#week-stats').text(`${weekTrips} trip${weekTrips !== 1 ? 's' : ''}`);
      $('#month-stats').text(`${monthTrips} trip${monthTrips !== 1 ? 's' : ''}`);
  }

  // Function to load service requests into the DataTable
  function loadServiceRequestsTable() {
      const serviceRequests = <?php echo json_encode($service_requests); ?>;
      const today = moment().startOf('day');
      const upcomingRequests = serviceRequests
          .filter(request => {
              // Parse the date_of_travel using moment for consistent comparison
              const travelDate = moment(request.date_of_travel);
              const travelEndDate = request.date_of_travel_end ? moment(request.date_of_travel_end) : travelDate;
              return travelEndDate.isSameOrAfter(today, 'day');
          })
          .sort((a, b) => {
              // Sort by date_of_travel
              const dateA = moment(a.date_of_travel);
              const dateB = moment(b.date_of_travel);
              return dateA - dateB;
          });
      
      upcomingRequestsTable.clear();
      
      if (upcomingRequests.length > 0) {
          upcomingRequests.forEach(request => {
              // Parse the date using moment for consistent formatting
              const travelDate = moment(request.date_of_travel);
              const travelEndDate = request.date_of_travel_end ? moment(request.date_of_travel_end) : travelDate;
              
              let dateRange;
              if (travelDate.format('YYYY-MM-DD') === travelEndDate.format('YYYY-MM-DD')) {
                  // Single day event
                  dateRange = travelDate.format('MMM D') + '<br><small class="text-muted">' + 
                            moment(request.time_departure, 'HH:mm:ss').format('h:mm A') + 
                            ' - ' + 
                            moment(request.time_return, 'HH:mm:ss').format('h:mm A') + '</small>';
              } else {
                  // Multi-day event
                  dateRange = travelDate.format('MMM D') + ' - ' + travelEndDate.format('MMM D');
              }
              
              upcomingRequestsTable.row.add({
                "destination": `<strong>${request.destination}</strong>`,
                "daterange": dateRange,
                "requester": `${request.requester_name}<br><small class="text-success">${request.vehicle_model || 'No vehicle'}</small>`
              }).draw();
          });
      }
  }

  // Function to focus on a specific request in the calendar
  function focusOnRequest(requestId) {
      const event = calendar.getEventById('service_' + requestId);
      if (event) {
          calendar.changeView('dayGridMonth');
          calendar.gotoDate(event.start);
          event.setProp('backgroundColor', '#dc3545');
          setTimeout(() => {
              event.setProp('backgroundColor', event.extendedProps.is_multi_day ? 'var(--event)' : 'var(--meeting)');
          }, 1000);
      }
  }

  // Function to load recent activity into DataTable
    function loadRecentActivity() {
        $.ajax({
            url: 'get_recent_activity.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                recentActivityTable.clear();
                
                if (response.data && response.data.length > 0) {
                    response.data.forEach(activity => {
                        let icon = 'fa-info-circle';
                        let color = 'text-info';
                        
                        if (activity.type === 'approval') {
                            icon = 'fa-check-circle';
                            color = 'text-success';
                        } else if (activity.type === 'completion') {
                            icon = 'fa-flag-checkered';
                            color = 'text-primary';
                        } else if (activity.type === 'rejection') {
                            icon = 'fa-times-circle';
                            color = 'text-danger';
                        } else if (activity.type === 'creation') {
                            icon = 'fa-plus-circle';
                            color = 'text-warning';
                        }
                        
                        recentActivityTable.row.add({
                            "activity": `<i class="fas ${icon} ${color} mr-2"></i> ${activity.message}`,
                            "time": activity.time
                        }).draw();
                    });
                } else {
                    recentActivityTable.row.add({
                        "activity": `<i class="fas fa-info-circle text-info mr-2"></i> No recent activity`,
                        "time": ""
                    }).draw();
                }
            },
            error: function() {
                console.error('Failed to load recent activity');
                recentActivityTable.row.add({
                    "activity": `<i class="fas fa-exclamation-triangle text-danger mr-2"></i> Error loading activity`,
                    "time": ""
                }).draw();
            }
        });
    }

  // Export calendar function
  function exportCalendar() {
      Swal.fire({
          title: 'Export Calendar',
          html: `
              <div class="form-group">
                  <label>Select Format:</label>
                  <select class="form-control" id="export-format">
                      <option value="pdf">PDF</option>
                      <option value="excel">Excel</option>
                      <option value="csv">CSV</option>
                  </select>
              </div>
              <div class="form-group">
                  <label>Date Range:</label>
                  <select class="form-control" id="export-range">
                      <option value="month">Current Month</option>
                      <option value="week">Current Week</option>
                      <option value="custom">Custom Range</option>
                  </select>
              </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Export',
          preConfirm: () => {
              const format = $('#export-format').val();
              const range = $('#export-range').val();
              // Here you would implement the actual export functionality
              Swal.fire('Exported!', `Calendar exported as ${format.toUpperCase()}`, 'success');
          }
      });
  }
</script>
</body>
</html>