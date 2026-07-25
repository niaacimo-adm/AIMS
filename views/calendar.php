<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
// session_start();

$database = new Database();
$db = $database->getConnection();
checkPermission('view_calendar');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Calendar</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
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

    /* ── Legend ─────────────────────────────────────────────────── */
    .cal-legend {
        display: flex; flex-wrap: wrap; gap: 10px 18px;
        padding: 10px 14px;
        border-top: 1px solid var(--table-border);
        font-size: .78rem; color: var(--text-muted);
    }
    .cal-legend-item { display: flex; align-items: center; gap: 5px; }
    .cal-legend-dot  {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }

    /* ── Calendar + side panel layout ─────────────────────────────── */
    .calendar-layout {
        display: flex;
        align-items: stretch;
    }
    .calendar-main {
        flex: 0 0 66.6667%;   /* 8 of 12 columns */
        max-width: 66.6667%;
        min-width: 0;
        border-right: 1px solid var(--table-border);
    }
    .calendar-main #calendar { padding: .5rem; }

    /* Shrink the grid itself so it doesn't dominate the page */
    .fc-daygrid-day-frame {
        aspect-ratio: 1 / 1;
        max-width: 108px;
        max-height: 108px;
        margin: 0 auto;
        min-height: 0 !important;
        display: flex;
        flex-direction: column;
    }
    .fc-daygrid-day-events { overflow: hidden; }
    .fc .fc-daygrid-body-natural .fc-daygrid-day-events { margin-bottom: 0 !important; }
    .fc-daygrid-event {
        font-size: .68rem !important;
        line-height: 1.15 !important;
        padding: 1px 4px !important;
    }
    .fc-daygrid-event .fc-event-title,
    .fc-daygrid-event .fc-event-time {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fc-daygrid-event .fc-event-title {
        white-space: normal !important;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .fc-daygrid-day-bottom {
        font-size: .68rem;
        padding: 0 4px !important;
    }

    .calendar-side {
        flex: 0 0 33.3333%;   /* 4 of 12 columns */
        max-width: 33.3333%;
        display: flex;
        flex-direction: column;
        background: var(--table-stripe);
        max-height: 640px;
        overflow-y: auto;
    }
    .side-panel-header {
        padding: 1rem 1.1rem .75rem;
        border-bottom: 1px solid var(--table-border);
    }
    .side-panel-header h4 {
        margin: 0;
        font-family: var(--font-ui);
        font-size: .92rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .side-panel-header span {
        font-size: .74rem;
        color: var(--text-muted);
    }
    .side-panel-body { padding: .85rem 1.1rem; flex: 1 1 auto; }
    .side-empty {
        font-size: .82rem;
        color: var(--text-muted);
        margin: .5rem 0 0;
    }
    .side-event-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: .6rem .65rem;
        margin-bottom: .5rem;
        border-radius: var(--radius-sm);
        background: var(--card-bg);
        border: 1px solid var(--card-border);
    }
    .side-event-dot {
        width: 9px; height: 9px; border-radius: 50%;
        margin-top: 5px; flex-shrink: 0;
    }
    .side-event-info { flex: 1 1 auto; min-width: 0; }
    .side-event-title {
        font-size: .82rem; font-weight: 600;
        color: var(--text-primary);
        overflow-wrap: anywhere;
    }
    .side-event-time {
        font-size: .72rem; color: var(--text-muted); margin-top: 1px;
    }
    .side-event-edit {
        border: none; background: transparent; color: var(--text-muted);
        cursor: pointer; font-size: .78rem; padding: 2px 4px;
    }
    .side-event-edit:hover { color: var(--primary); }
    .side-panel-add {
        padding: .85rem 1.1rem 1.1rem;
        border-top: 1px solid var(--table-border);
    }
    .side-panel-add label {
        font-size: .7rem; font-weight: 600; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: .05em; margin-bottom: .2rem;
    }
    .side-panel-add .form-control-sm {
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius-sm) !important;
        background: var(--card-bg) !important;
        color: var(--text-primary) !important;
        font-size: .8rem !important;
    }

    .fc-daygrid-day.selected-day {
        background: rgba(36,231,143,.12) !important;
        box-shadow: inset 0 0 0 1.5px var(--primary);
    }

    @media (max-width: 991px) {
        .calendar-layout { flex-direction: column; }
        .calendar-main { border-right: none; border-bottom: 1px solid var(--table-border); }
        .calendar-side { flex: 1 1 auto; max-width: 100%; max-height: 360px; }
    }

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
</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
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
                    <div class="pg-hero-title"><i class="fas fa-calendar-alt"></i>Calendar</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Employee birthdays, events and schedules</p>
                </div>
            </div>
        </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Calendar</h3>
                <div class="card-tools ml-auto">
                  <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#create-event-modal">
                    <i class="fas fa-plus mr-1"></i>New event
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="calendar-layout">
                  <div class="calendar-main">
                    <div id="calendar"></div>
                    <!-- Legend -->
                    <div class="cal-legend">
                      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--event)"></span>General Event</span>
                      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--meeting)"></span>Meeting</span>
                      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--holiday)"></span>Holiday</span>
                      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--birthday)"></span>Birthday</span>
                      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--leave)"></span>Approved Leave</span>
                    </div>
                  </div>
                  <div class="calendar-side" id="calendar-side">
                    <div class="side-panel-header">
                      <h4 id="side-panel-date">Select a day</h4>
                      <span id="side-panel-sub">Click any date to view or add events</span>
                    </div>
                    <div class="side-panel-body" id="side-panel-events">
                      <p class="side-empty">Click a date on the calendar to see what's happening that day.</p>
                    </div>
                    <div class="side-panel-add" id="side-panel-add" style="display:none;">
                      <div class="form-group mb-2">
                        <label>Add event</label>
                        <input id="side-event-title" type="text" class="form-control form-control-sm" placeholder="Event title">
                      </div>
                      <div class="form-group mb-2">
                        <label>Type</label>
                        <select id="side-event-type" class="form-control form-control-sm">
                          <option value="event">General Event</option>
                          <option value="meeting">Meeting</option>
                          <option value="holiday">Holiday</option>
                        </select>
                      </div>
                      <div class="form-row">
                        <div class="col form-group mb-2">
                          <label>Start</label>
                          <input id="side-event-start-time" type="time" class="form-control form-control-sm" value="09:00">
                        </div>
                        <div class="col form-group mb-2">
                          <label>End</label>
                          <input id="side-event-end-time" type="time" class="form-control form-control-sm" value="17:00">
                        </div>
                      </div>
                      <div class="form-group mb-2">
                        <label>Description</label>
                        <textarea id="side-event-description" class="form-control form-control-sm" rows="2" placeholder="Optional"></textarea>
                      </div>
                      <button type="button" class="btn btn-primary btn-sm btn-block" id="side-save-event">Add event</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card card-primary mt-3">
                <div class="card-header">
                    <h4 class="card-title">Upcoming events</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="events-table" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody id="events-table-body">
                                <!-- AJAX DATA -->
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

  <!-- Create Event Modal -->
  <div class="modal fade" id="create-event-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create event</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Event Title</label>
            <input id="event-title" type="text" class="form-control" placeholder="Event Title">
          </div>
          <div class="form-group">
            <label>Event Type</label>
            <select id="event-type" class="form-control">
              <option value="event">General Event</option>
              <option value="meeting">Meeting</option>
              <option value="holiday">Holiday</option>
            </select>
          </div>
          <div class="form-group">
            <label>Start Date/Time</label>
            <input id="event-start" type="datetime-local" class="form-control">
          </div>
          <div class="form-group">
            <label>End Date/Time</label>
            <input id="event-end" type="datetime-local" class="form-control">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea id="event-description" class="form-control" rows="3" placeholder="Enter description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" id="add-event" class="btn btn-primary">Add event</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Event Modal -->
  <div class="modal fade" id="event-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Event details</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="event-id">
          <div class="form-group">
            <label>Title</label>
            <input id="modal-title-input" class="form-control">
          </div>
          <div class="form-group">
            <label>Type</label>
            <select id="modal-type" class="form-control">
              <option value="event">General Event</option>
              <option value="meeting">Meeting</option>
              <option value="holiday">Holiday</option>
            </select>
          </div>
          <div class="form-group">
            <label>Start Date/ Time</label>
            <input id="modal-start" class="form-control" type="datetime-local">
          </div>
          <div class="form-group">
            <label>End Date/ Time</label>
            <input id="modal-end" class="form-control" type="datetime-local">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea id="modal-description" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" id="delete-event">Delete</button>
          <button type="button" class="btn btn-primary" id="save-event">Save</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>

<script>
  $(function () {
      // Set admin theme
      setAdminTheme();
      
      // Initialize calendar with loading state
      $('#calendar').addClass('calendar-loading');
      
      var selectedDateStr = null;

      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
          headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
          },
          themeSystem: 'bootstrap',
          initialView: 'dayGridMonth',
          height: 'auto',
          dayMaxEventRows: 2,
          navLinks: true,
          editable: true,
          selectable: true,
          eventLimit: true,

          // Fires whenever the calendar's event set changes (initial load,
          // refetch, etc.) — keeps the side panel in sync automatically.
          eventsSet: function() {
              if (selectedDateStr) {
                  renderSidePanel(selectedDateStr);
              }
          },
          
          // Event styling based on type
          eventDidMount: function(info) {
              // Add custom classes based on event type
              if (info.event.extendedProps.type === 'birthday') {
                  info.el.classList.add('birthday-event');
              } else if (info.event.extendedProps.type === 'holiday') {
                  info.el.classList.add('holiday-event');
              } else if (info.event.extendedProps.type === 'meeting') {
                  info.el.classList.add('meeting-event');
              } else if (info.event.extendedProps.type === 'leave') {
                  info.el.classList.add('leave-event');
                  // Show tooltip with employee name on hover
                  info.el.title = info.event.extendedProps.emp_name
                                + ' — ' + (info.event.extendedProps.leave_type || 'Leave')
                                + ' (' + info.event.extendedProps.number_of_days + ' day/s)';
              } else {
                  info.el.classList.add('event-event');
              }
          },
          
          // Updated events loading with proper error handling
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
                  }),
                  $.ajax({
                      url: 'get_approved_leaves.php',
                      type: 'GET',
                      dataType: 'json'
                  })
              ).then(function(eventsResponse, birthdaysResponse, leavesResponse) {
                  $('#calendar').removeClass('calendar-loading');
                  
                  // Check if all requests succeeded
                  if (!eventsResponse[0].success || !birthdaysResponse[0].success) {
                      showCalendarError('Failed to load some events. Please try again.');
                      failureCallback('Failed to load events');
                      return;
                  }
                  
                  var events    = eventsResponse[0].data   || [];
                  var birthdays = birthdaysResponse[0].data || [];
                  var leaves    = (leavesResponse[0] && leavesResponse[0].success)
                                  ? leavesResponse[0].data : [];
                  successCallback(events.concat(birthdays).concat(leaves));
                  
              }, function(jqXHR, textStatus, errorThrown) {
                  $('#calendar').removeClass('calendar-loading');
                  showCalendarError('Failed to load calendar events: ' + textStatus);
                  failureCallback('Failed to load events');
              });
          },
          
          eventClick: function(info) {
              // Skip modal for birthday and leave events
              if (info.event.extendedProps.type === 'birthday') {
                  return;
              }
              if (info.event.extendedProps.type === 'leave') {
                  // Show a read-only Swal instead of the edit modal
                  Swal.fire({
                      title: info.event.extendedProps.emp_name || 'Approved Leave',
                      html: '<div style="text-align:left;font-size:.88rem;line-height:1.8">'
                          + '<p><strong>Leave Type:</strong> ' + (info.event.extendedProps.leave_type || 'N/A') + '</p>'
                          + '<p><strong>Days:</strong> ' + (info.event.extendedProps.number_of_days || '—') + ' day(s)</p>'
                          + '<p><strong>From:</strong> ' + moment(info.event.start).format('MMMM D, YYYY') + '</p>'
                          + '<p><strong>To:</strong> ' + (info.event.end ? moment(info.event.end).subtract(1,'day').format('MMMM D, YYYY') : moment(info.event.start).format('MMMM D, YYYY')) + '</p>'
                          + '</div>',
                      icon: 'info',
                      confirmButtonColor: '#e03131',
                      confirmButtonText: 'Close'
                  });
                  return;
              }
              
              $('#event-id').val(info.event.id);
              $('#modal-title-input').val(info.event.title);
              $('#modal-type').val(info.event.extendedProps.type || 'event');
              $('#modal-start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
              $('#modal-end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
              $('#modal-description').val(info.event.extendedProps.description || '');
              $('#event-modal').modal('show');
          },
          
          dateClick: function(info) {
              selectedDateStr = info.dateStr; // YYYY-MM-DD
              $('.fc-daygrid-day').removeClass('selected-day');
              $('.fc-daygrid-day[data-date="' + info.dateStr + '"]').addClass('selected-day');
              renderSidePanel(info.dateStr, info.date);
          },
      });
      
      calendar.render();
      
      // Function to set admin theme
      function setAdminTheme() {
          localStorage.setItem('currentTheme', 'admin');
          $('body').addClass('theme-admin');
      }
      
      // ── Side panel: render events for a clicked day ─────────────
      var typeColorVar = {
          birthday: 'var(--birthday)',
          holiday:  'var(--holiday)',
          meeting:  'var(--meeting)',
          leave:    'var(--leave)',
          event:    'var(--event)'
      };

      function eventFallsOnDate(ev, dateStr) {
          if (!ev.start) return false;
          var day = moment(dateStr, 'YYYY-MM-DD');
          var start = moment(ev.start).startOf('day');
          var end;

          if (ev.end) {
              var endMoment = moment(ev.end);
              end = endMoment.clone().startOf('day');
              // FullCalendar end dates are exclusive at midnight (this is how
              // all-day events express their range). But for a *timed* event
              // whose end time isn't exactly midnight, the event still runs
              // into that calendar day, so push the exclusive boundary out
              // by one more day — otherwise a same-day timed event (e.g.
              // 7:00am–8:00am) collapses to a zero-length range and vanishes.
              var isExactMidnight = endMoment.hours() === 0 && endMoment.minutes() === 0 && endMoment.seconds() === 0;
              if (!isExactMidnight) {
                  end.add(1, 'day');
              }
          } else {
              end = start.clone().add(1, 'day');
          }

          return day.isSameOrAfter(start) && day.isBefore(end);
      }

      function renderSidePanel(dateStr, dateObj) {
          var dateMoment = dateObj ? moment(dateObj) : moment(dateStr, 'YYYY-MM-DD');
          $('#side-panel-date').text(dateMoment.format('MMMM D, YYYY'));
          $('#side-panel-sub').text(dateMoment.format('dddd'));

          // Pull from FullCalendar's own event store — same normalized
          // objects eventClick already relies on (extendedProps etc.),
          // so it doesn't matter how each source endpoint shapes its JSON.
          var dayEvents = calendar.getEvents().filter(function(ev) {
              return eventFallsOnDate(ev, dateStr);
          });

          var $list = $('#side-panel-events');
          $list.empty();

          if (dayEvents.length === 0) {
              $list.append('<p class="side-empty">No events for this day yet.</p>');
          } else {
              dayEvents.forEach(function(ev) {
                  var type = ev.extendedProps.type || 'event';
                  var color = typeColorVar[type] || typeColorVar.event;
                  var timeLabel;
                  if (type === 'birthday') {
                      timeLabel = 'Birthday';
                  } else if (ev.allDay) {
                      timeLabel = 'All day';
                  } else {
                      timeLabel = moment(ev.start).format('h:mm A') + (ev.end ? ' – ' + moment(ev.end).format('h:mm A') : '');
                  }

                  var $item = $(
                      '<div class="side-event-item">' +
                          '<span class="side-event-dot" style="background:' + color + '"></span>' +
                          '<div class="side-event-info">' +
                              '<div class="side-event-title"></div>' +
                              '<div class="side-event-time"></div>' +
                          '</div>' +
                      '</div>'
                  );
                  $item.find('.side-event-title').text(ev.title || '(untitled)');
                  $item.find('.side-event-time').text(timeLabel);

                  // Editable events (not birthdays/leaves) get an edit button
                  if (type !== 'birthday' && type !== 'leave') {
                      var $editBtn = $('<button type="button" class="side-event-edit" title="Edit"><i class="fas fa-pen"></i></button>');
                      $editBtn.on('click', function() {
                          $('#event-id').val(ev.id);
                          $('#modal-title-input').val(ev.title);
                          $('#modal-type').val(type);
                          $('#modal-start').val(moment(ev.start).format('YYYY-MM-DDTHH:mm'));
                          $('#modal-end').val(ev.end ? moment(ev.end).format('YYYY-MM-DDTHH:mm') : '');
                          $('#modal-description').val(ev.extendedProps.description || '');
                          $('#event-modal').modal('show');
                      });
                      $item.append($editBtn);
                  } else if (type === 'leave') {
                      var $viewBtn = $('<button type="button" class="side-event-edit" title="View"><i class="fas fa-eye"></i></button>');
                      $viewBtn.on('click', function() {
                          Swal.fire({
                              title: ev.extendedProps.emp_name || 'Approved Leave',
                              html: '<div style="text-align:left;font-size:.88rem;line-height:1.8">'
                                  + '<p><strong>Leave Type:</strong> ' + (ev.extendedProps.leave_type || 'N/A') + '</p>'
                                  + '<p><strong>Days:</strong> ' + (ev.extendedProps.number_of_days || '—') + ' day(s)</p>'
                                  + '</div>',
                              icon: 'info',
                              confirmButtonColor: '#e03131',
                              confirmButtonText: 'Close'
                          });
                      });
                      $item.append($viewBtn);
                  }

                  $list.append($item);
              });
          }

          // Reset and reveal the quick-add form for this date
          $('#side-event-title').val('');
          $('#side-event-type').val('event');
          $('#side-event-start-time').val('09:00');
          $('#side-event-end-time').val('17:00');
          $('#side-event-description').val('');
          $('#side-panel-add').data('date', dateStr).show();
      }

      // Add-event handler for the side panel
      $('#side-save-event').click(function() {
          var date = $('#side-panel-add').data('date');
          var title = $('#side-event-title').val().trim();
          var type = $('#side-event-type').val();
          var startTime = $('#side-event-start-time').val();
          var endTime = $('#side-event-end-time').val();
          var description = $('#side-event-description').val().trim();

          if (!title) {
              alert('Title is required');
              return;
          }
          if (!date) {
              alert('Please select a date on the calendar first');
              return;
          }

          var startDateTime = date + 'T' + startTime;
          var endDateTime = date + 'T' + endTime;

          $.ajax({
              url: 'add_event.php',
              type: 'POST',
              data: {
                  title: title,
                  type: type,
                  start: startDateTime,
                  end: endDateTime,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();

                      Swal.fire(
                          'Success!',
                          'Event added successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to add event';
                  Swal.fire(
                      'Error!',
                      errorMsg,
                      'error'
                  );
              }
          });
      });
      
      // Function to show error messages
      function showCalendarError(message) {
          var errorHtml = `
              <div id="calendar-error" class="alert alert-danger alert-dismissible">
                  <button type="button" class="close" data-dismiss="alert">&times;</button>
                  <strong>Error!</strong> ${message}
                  <button class="btn btn-sm btn-default" onclick="location.reload()">Reload</button>
              </div>
          `;
          $('#calendar').before(errorHtml);
      }

      // Add event handler
      $('#add-event').click(function() {
          var title = $('#event-title').val().trim();
          var type = $('#event-type').val();
          var start = $('#event-start').val();
          var end = $('#event-end').val();
          var description = $('#event-description').val().trim();
          
          if (!title) {
              alert('Title is required');
              return;
          }
          
          if (!start) {
              alert('Start date is required');
              return;
          }
          
          if (end && new Date(end) < new Date(start)) {
              alert('End date must be after start date');
              return;
          }
          
          $.ajax({
              url: 'add_event.php',
              type: 'POST',
              data: {
                  title: title,
                  type: type,
                  start: start,
                  end: end,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();
                      $('#event-title, #event-description').val('');
                      $('#event-type').val('event');
                      $('#event-start, #event-end').val('');
                      $('#create-event-modal').modal('hide');
                      
                      Swal.fire(
                          'Success!',
                          'Event added successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to add event';
                  alert('Error: ' + errorMsg);
              }
          });
      });
      
      // Save event handler
      $('#save-event').click(function() {
          var eventId = $('#event-id').val();
          var title = $('#modal-title-input').val().trim();
          var type = $('#modal-type').val();
          var start = $('#modal-start').val();
          var end = $('#modal-end').val();
          var description = $('#modal-description').val().trim();
          
          if (!title) {
              alert('Title is required');
              return;
          }
          
          if (!start) {
              alert('Start date is required');
              return;
          }
          
          if (end && new Date(end) < new Date(start)) {
              alert('End date must be after start date');
              return;
          }
          
          $.ajax({
              url: 'update_event.php',
              type: 'POST',
              data: {
                  id: eventId,
                  title: title,
                  type: type,
                  start: start,
                  end: end,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();
                      $('#event-modal').modal('hide');
                      
                      Swal.fire(
                          'Success!',
                          'Event updated successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to update event';
                  alert('Error: ' + errorMsg);
              }
          });
      });
      
      // Delete event handler
      $('#delete-event').click(function() {
          Swal.fire({
              title: 'Are you sure?',
              text: "You won't be able to revert this!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
          }).then((result) => {
              if (result.isConfirmed) {
                  var eventId = $('#event-id').val();
                  
                  $.ajax({
                      url: 'delete_events.php',
                      type: 'POST',
                      data: { id: eventId },
                      dataType: 'json',
                      success: function(response) {
                          if (response.status === 'success') {
                              calendar.refetchEvents();
                              $('#event-modal').modal('hide');
                              loadEventsTable();
                              
                              Swal.fire(
                                  'Deleted!',
                                  'Your event has been deleted.',
                                  'success'
                              );
                          } else {
                              Swal.fire(
                                  'Error!',
                                  response.message,
                                  'error'
                              );
                          }
                      },
                      error: function(xhr) {
                          var errorMsg = xhr.responseJSON?.message || 'Failed to delete event';
                          Swal.fire(
                              'Error!',
                              errorMsg,
                              'error'
                          );
                      }
                  });
              }
          });
      });

      // Initialize DataTable for events
      var eventsTable = $('#events-table').DataTable({
          responsive: true,
          order: [[3, 'asc']],
          columns: [
              { data: 'title' },
              { 
                  data: 'type',
                  render: function(data, type, row) {
                      var badgeColor = '#4361ee'; // default admin blue
                      if (data === 'meeting') badgeColor = '#3f37c9';
                      if (data === 'holiday') badgeColor = '#ffa500';
                      if (data === 'birthday') badgeColor = '#4895ef';
                      
                      return '<span class="badge" style="background-color:' + badgeColor + '; color: white;">' + data + '</span>';
                  }
              },
              { data: 'description' },
              { 
                  data: 'start',
                  render: function(data) {
                      return data ? moment(data).format('MMM D, YYYY h:mm A') : '';
                  }
              },
              { 
                  data: 'end',
                  render: function(data) {
                      return data ? moment(data).format('MMM D, YYYY h:mm A') : '';
                  }
              }
          ],
          language: {
              emptyTable: "No upcoming events found",
              zeroRecords: "No matching events found"
          }
      });

      // Function to load events into the DataTable
      function loadEventsTable() {
          $.ajax({
              url: 'get_events.php?t=' + new Date().getTime(),
              type: 'GET',
              dataType: 'json',
              success: function(response) {
                  if (response.success) {
                      eventsTable.clear();
                      
                      if (response.data && response.data.length > 0) {
                          eventsTable.rows.add(response.data).draw();
                          
                          // Highlight today's events
                          var today = moment().startOf('day');
                          eventsTable.rows().every(function() {
                              var rowData = this.data();
                              var eventDate = moment(rowData.start).startOf('day');
                              if (eventDate.isSame(today)) {
                                  $(this.node()).addClass('table-info');
                              }
                          });
                      }
                  } else {
                      console.error('Error loading events:', response.message);
                  }
              },
              error: function(xhr, status, error) {
                  console.error('AJAX error loading events:', error);
                  eventsTable.clear().draw();
              }
          });
      }

      // Load initial data
      loadEventsTable();
  });
</script>
</body>
</html>