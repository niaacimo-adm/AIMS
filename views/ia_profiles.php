<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = "IA Profiles";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IA Profiles - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
    /* ============================================================
       CSS VARIABLES — light mode defaults
       ============================================================ */
    :root {
        --ia-accent:        #1a5c38;
        --ia-accent-light:  #24e78f;
        --ia-accent-dim:    rgba(26,92,56,.10);
        --ia-hero-bg:       #0b1f17;

        /* Card / surface */
        --surface:          #ffffff;
        --surface-alt:      #f4f7f6;
        --surface-border:   #dee2e6;

        /* Text */
        --tx-primary:       #1a2e1e;
        --tx-secondary:     #495057;
        --tx-muted:         #6c757d;

        /* Table */
        --tbl-head-bg:      #f0f4f2;
        --tbl-stripe:       #f8fbf9;
        --tbl-border:       #dee2e6;
        --tbl-hover:        rgba(26,92,56,.05);

        /* Input */
        --input-bg:         #ffffff;
        --input-border:     #ced4da;
        --input-color:      #212529;
        --input-focus:      #1a5c38;

        /* Modal */
        --modal-bg:         #ffffff;
        --modal-hd-bg:      #1a5c38;
        --modal-hd-color:   #ffffff;

        /* Badges */
        --badge-op-bg:      #d4edda;
        --badge-op-color:   #155724;
        --badge-nop-bg:     #f8d7da;
        --badge-nop-color:  #721c24;
        --badge-assign-bg:  #e3f2fd;
        --badge-assign-color:#1565c0;

        /* Timeline (history page) */
        --timeline-line:    #dee2e6;
        --history-card-bg:  #ffffff;
        --history-card-shadow: 0 2px 8px rgba(0,0,0,.08);
        --history-meta-color:#6c757d;
    }

    body.dark-mode {
        --surface:          #1e2d24;
        --surface-alt:      #172218;
        --surface-border:   #2d4035;

        --tx-primary:       #d4f5e5;
        --tx-secondary:     #a8c4b0;
        --tx-muted:         #6b8f78;

        --tbl-head-bg:      #172218;
        --tbl-stripe:       #1a2920;
        --tbl-border:       #2d4035;
        --tbl-hover:        rgba(36,231,143,.06);

        --input-bg:         #172218;
        --input-border:     #2d4035;
        --input-color:      #d4f5e5;
        --input-focus:      #24e78f;

        --modal-bg:         #1e2d24;
        --modal-hd-bg:      #0f2d1e;
        --modal-hd-color:   #d4f5e5;

        --badge-op-bg:      #1a3828;
        --badge-op-color:   #86efac;
        --badge-nop-bg:     #3b1f20;
        --badge-nop-color:  #fca5a5;
        --badge-assign-bg:  #1a2e3b;
        --badge-assign-color:#93c5fd;

        --timeline-line:    #2d4035;
        --history-card-bg:  #1e2d24;
        --history-card-shadow: 0 2px 8px rgba(0,0,0,.35);
        --history-meta-color:#6b8f78;
    }

    /* ============================================================
       GLOBAL DARK MODE — content-wrapper & cards
       ============================================================ */
    body.dark-mode,
    body.dark-mode .content-wrapper { background-color: var(--body-bg, var(--surface-alt)) !important; color: var(--tx-primary) !important; }

    body.dark-mode .card { background: var(--surface) !important; border-color: var(--surface-border) !important; color: var(--tx-primary) !important; }
    body.dark-mode .card-header { background: var(--surface-alt) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .card-body  { background: var(--surface) !important; color: var(--tx-primary) !important; }
    body.dark-mode .card-footer{ background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }

    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-header  { background: var(--modal-hd-bg) !important; color: var(--modal-hd-color) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .modal-body    { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-footer  { background: var(--modal-bg) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .close         { color: var(--modal-hd-color) !important; }

    body.dark-mode .table         { background: var(--surface) !important; color: var(--tx-primary) !important; }
    body.dark-mode .table thead th{ background: var(--tbl-head-bg) !important; color: var(--tx-primary) !important; border-color: var(--tbl-border) !important; }
    body.dark-mode .table td,
    body.dark-mode .table th      { border-color: var(--tbl-border) !important; color: var(--tx-primary) !important; }
    body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--tbl-stripe) !important; }
    body.dark-mode .table-hover tbody tr:hover { background: var(--tbl-hover) !important; }
    body.dark-mode .table-bordered { border-color: var(--tbl-border) !important; }

    body.dark-mode .form-control  { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control:focus { border-color: var(--input-focus) !important; box-shadow: 0 0 0 .2rem rgba(36,231,143,.18) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode label, body.dark-mode .form-label { color: var(--tx-primary) !important; }

    body.dark-mode .text-muted { color: var(--tx-muted) !important; }
    body.dark-mode .text-dark  { color: var(--tx-primary) !important; }
    body.dark-mode h1,body.dark-mode h2,body.dark-mode h3,
    body.dark-mode h4,body.dark-mode h5,body.dark-mode h6 { color: var(--tx-primary) !important; }
    body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--tx-primary); }

    body.dark-mode .breadcrumb { background: var(--surface) !important; }
    body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
    body.dark-mode .breadcrumb-item.active { color: var(--tx-muted) !important; }

    body.dark-mode .nav-tabs .nav-link       { color: var(--tx-muted) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .nav-tabs .nav-link.active{ background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .nav-tabs { border-color: var(--surface-border) !important; }

    body.dark-mode .list-group-item { background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .dropdown-menu  { background: var(--surface) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .dropdown-item  { color: var(--tx-primary) !important; }
    body.dark-mode .dropdown-item:hover { background: var(--tbl-stripe) !important; }

    body.dark-mode .alert-info    { background: #1e2f3e !important; color: #93c5fd !important; border-color: #2d4a5e !important; }
    body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; border-color: #2d4035 !important; }
    body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; border-color: #4a3a1a !important; }
    body.dark-mode .alert-danger  { background: #2e1515 !important; color: #fca5a5 !important; border-color: #4a2222 !important; }

    body.dark-mode .page-item .page-link { background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .page-item.active .page-link { background: var(--ia-accent) !important; border-color: var(--ia-accent) !important; }
    body.dark-mode hr { border-color: var(--surface-border) !important; }

    body.dark-mode .dataTables_wrapper { color: var(--tx-primary) !important; }
    body.dark-mode .dataTables_filter input,
    body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .dataTables_info { color: var(--tx-muted) !important; }

    body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
    body.dark-mode .select2-dropdown { background: var(--surface) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .select2-results__option { color: var(--tx-primary) !important; }
    body.dark-mode .select2-results__option--highlighted { background: var(--ia-accent) !important; color: #fff !important; }

    /* ============================================================
       FILTER CARD — light + dark
       ============================================================ */
    .filter-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        margin-bottom: 1.25rem;
    }
    .filter-card .card-header {
        background: linear-gradient(135deg, var(--ia-accent), #2d7a50);
        color: #fff !important;
        border-radius: 10px 10px 0 0;
        border: none;
    }
    .filter-card .card-header .card-title { color: #fff !important; }
    .filter-card .card-header .btn-tool   { color: rgba(255,255,255,.75) !important; }
    .filter-card .card-body { padding: 1.25rem; }

    body.dark-mode .filter-card { background: var(--surface) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .filter-card .card-header { background: linear-gradient(135deg, #0f2d1e, #1a5c38) !important; }
    body.dark-mode .filter-card .card-body   { background: var(--surface) !important; }

    /* ============================================================
       MAIN TABLE CARD
       ============================================================ */
    .profiles-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        overflow: hidden;
    }
    .profiles-card .card-header {
        background: linear-gradient(135deg, #f0f4f2, #e8f0ec);
        border-bottom: 1px solid var(--surface-border);
    }
    body.dark-mode .profiles-card { background: var(--surface) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .profiles-card .card-header { background: linear-gradient(135deg, #172218, #1a2920) !important; border-color: var(--surface-border) !important; }

    /* ============================================================
       STATUS / ASSIGNMENT BADGES
       ============================================================ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: .3px;
    }
    .status-operational   { background: var(--badge-op-bg);    color: var(--badge-op-color); }
    .status-nonoperational{ background: var(--badge-nop-bg);   color: var(--badge-nop-color); }
    .assignment-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 20px;
        background: var(--badge-assign-bg);
        color: var(--badge-assign-color);
        font-weight: 500;
    }

    /* ============================================================
       ACTION BUTTONS
       ============================================================ */
    .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }

    .btn-action {
        border: none;
        border-radius: 7px;
        padding: 5px 9px;
        font-size: 12px;
        font-weight: 500;
        transition: transform .2s, box-shadow .2s, opacity .2s;
        box-shadow: 0 1px 4px rgba(0,0,0,.12);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-action:hover  { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,.18); }
    .btn-action:active { transform: translateY(0); }

    .btn-view    { background: linear-gradient(135deg,#17a2b8,#138496); color:#fff; }
    .btn-view:hover { color:#fff; }
    .btn-history { background: linear-gradient(135deg,#6c757d,#5a6268); color:#fff; }
    .btn-history:hover { color:#fff; }
    .btn-edit    { background: linear-gradient(135deg,#28a745,#218838); color:#fff; }
    .btn-edit:hover { color:#fff; }
    .btn-assign  { background: linear-gradient(135deg,#ffc107,#e0a800); color:#212529; }
    .btn-delete  { background: linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
    .btn-delete:hover { color:#fff; }

    /* ============================================================
       TABLE TWEAKS
       ============================================================ */
    .table th {
        border-top: none;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .5px;
        background: var(--tbl-head-bg);
        color: var(--tx-secondary);
    }
    .table td { vertical-align: middle; font-size: 13px; color: var(--tx-primary); }
    .table-hover tbody tr:hover { background: var(--tbl-hover) !important; }
    body.dark-mode .table th { color: var(--tx-muted) !important; }

    /* ============================================================
       SECTION DIVIDER IN MODAL
       ============================================================ */
    .modal-section-title {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: var(--ia-accent);
        border-bottom: 2px solid var(--ia-accent-dim);
        padding-bottom: 6px;
        margin: 1rem 0 .75rem;
    }
    body.dark-mode .modal-section-title { color: var(--ia-accent-light) !important; }

    /* ============================================================
       MODAL STYLES
       ============================================================ */
    .modal-header-ia {
        background: linear-gradient(135deg, var(--ia-accent), #2d7a50);
        border-radius: 6px 6px 0 0;
    }
    body.dark-mode .modal-header-ia { background: linear-gradient(135deg,#0b1f17,#1a5c38) !important; }

    .modal-content { border: none; border-radius: 10px; overflow: hidden; }
    body.dark-mode .modal-content { box-shadow: 0 8px 40px rgba(0,0,0,.55) !important; }

    /* ============================================================
       FORM CONTROLS IN MODAL
       ============================================================ */
    .modal .form-control {
        background: var(--input-bg);
        color: var(--input-color);
        border: 1px solid var(--input-border);
        border-radius: 7px;
        font-size: 13.5px;
        transition: border-color .2s, box-shadow .2s;
    }
    .modal .form-control:focus {
        border-color: var(--ia-accent);
        box-shadow: 0 0 0 3px rgba(26,92,56,.15);
    }
    body.dark-mode .modal .form-control:focus { border-color: var(--ia-accent-light) !important; box-shadow: 0 0 0 3px rgba(36,231,143,.15) !important; }

    .modal label { font-size: 12.5px; font-weight: 600; color: var(--tx-secondary); margin-bottom: 4px; }
    body.dark-mode .modal label { color: var(--tx-muted) !important; }

    .required-star { color: #dc3545; }

    /* ============================================================
       ADD BUTTON IN CARD HEADER
       ============================================================ */
    .btn-add-ia {
        background: linear-gradient(135deg, var(--ia-accent), #2d7a50);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 7px 16px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: transform .2s, box-shadow .2s;
        box-shadow: 0 2px 8px rgba(26,92,56,.25);
    }
    .btn-add-ia:hover { transform: translateY(-2px); box-shadow: 0 5px 16px rgba(26,92,56,.35); color:#fff; }

    /* ============================================================
       HISTORY PAGE — timeline
       ============================================================ */
    .history-timeline { position: relative; padding-left: 30px; }
    .history-timeline::before { content:''; position:absolute; left:15px; top:0; bottom:0; width:2px; background: var(--timeline-line); }
    .history-item {
        position: relative;
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: var(--history-card-bg);
        border-radius: 8px;
        box-shadow: var(--history-card-shadow);
        border-left: 4px solid var(--ia-accent);
    }
    .history-item::before {
        content: '';
        position: absolute;
        left: -23px; top: 20px;
        width: 12px; height: 12px;
        border-radius: 50%;
        background: var(--ia-accent);
        border: 2px solid var(--surface);
    }
    .history-item.created       { border-left-color:#28a745; }
    .history-item.created::before { background:#28a745; }
    .history-item.updated       { border-left-color:#ffc107; }
    .history-item.updated::before { background:#ffc107; }
    .history-item.deleted       { border-left-color:#dc3545; }
    .history-item.deleted::before { background:#dc3545; }
    .history-item.assigned      { border-left-color:#6f42c1; }
    .history-item.assigned::before { background:#6f42c1; }
    .history-item.officer_added { border-left-color:#20c997; }
    .history-item.officer_added::before { background:#20c997; }
    .history-item.officer_deleted { border-left-color:#fd7e14; }
    .history-item.officer_deleted::before { background:#fd7e14; }

    .history-description { font-weight:500; margin-bottom:.5rem; color: var(--tx-primary); }
    .history-meta { font-size:.875rem; color: var(--history-meta-color); }
    .history-changes { margin-top:.5rem; padding:.5rem; background: var(--surface-alt); border-radius:4px; font-size:.875rem; }
    .change-item { display:flex; align-items:center; margin-bottom:.25rem; }
    .change-field { font-weight:500; min-width:120px; color: var(--tx-primary); }
    .change-values { display:flex; align-items:center; flex-grow:1; }
    .change-old { text-decoration:line-through; color:#dc3545; margin-right:.5rem; }
    .change-new { color:#28a745; font-weight:500; }
    .change-arrow { margin:0 .5rem; color: var(--tx-muted); }

    body.dark-mode .history-item    { background: var(--history-card-bg) !important; }
    body.dark-mode .history-changes { background: var(--tbl-stripe) !important; }

    /* ============================================================
       MISC
       ============================================================ */
    .content-header h1 { color: var(--tx-primary); }
    body.dark-mode .content-header h1 { color: var(--tx-primary) !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ia.php'; ?>

    <div class="content-wrapper">
        <!-- ── Page Header ── -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-leaf mr-2 text-success"></i>IA Profiles</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">IA Profiles</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <!-- ── FILTER CARD ── -->
                <div class="card profiles-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filter IA Profiles</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assigned Employee</label>
                                    <select class="form-control" id="filter_assigned_employee">
                                        <option value="">All Employees</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" id="filter_status">
                                        <option value="">All Status</option>
                                        <option value="operational">Operational</option>
                                        <option value="non-operational">Non-operational</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Region</label>
                                    <select class="form-control" id="filter_region">
                                        <option value="">All Regions</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Province</label>
                                    <select class="form-control" id="filter_province">
                                        <option value="">All Provinces</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Congressional District</label>
                                    <select class="form-control" id="filter_district">
                                        <option value="">All Districts</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>IA Name</label>
                                    <input type="text" class="form-control" id="filter_ia_name" placeholder="Search by IA name…">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>IA Code</label>
                                    <input type="text" class="form-control" id="filter_ia_code" placeholder="Search by IA code…">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" id="applyFilters">
                                    <i class="fas fa-search mr-1"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-secondary ml-2" id="resetFilters">
                                    <i class="fas fa-redo mr-1"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── PROFILES TABLE CARD ── -->
                <div class="card profiles-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users mr-2"></i>Irrigators' Association Profiles
                        </h3>
                        <div class="card-tools">
                            <?php if (hasPermission('add_ia_profile')): ?>
                            <button class="btn-add-ia" id="btnAddIaProfile">
                                <i class="fas fa-plus"></i> Add IA Profile
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="iaProfilesTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>IA Name</th>
                                        <th>Name of CIS</th>
                                        <th>President</th>
                                        <th>Contact</th>
                                        <th>Service Area (ha)</th>
                                        <th>Members</th>
                                        <th>TSAGs</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th style="width:200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $database = new Database();
                                    $db = $database->getConnection();
                                    $query  = "SELECT * FROM ia_profiles ORDER BY ia_name";
                                    $result = $db->query($query);
                                    if ($result && $result->num_rows > 0):
                                        while ($row = $result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['ia_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['cis_name'] ?? '') ?: '<span class="text-muted">N/A</span>' ?></td>
                                        <td><?= htmlspecialchars($row['president_name'] ?? '') ?: '<span class="text-muted">N/A</span>' ?></td>
                                        <td><?= htmlspecialchars($row['contact_number'] ?? '') ?: '<span class="text-muted">N/A</span>' ?></td>
                                        <td><strong><?= number_format($row['service_area_ha'] ?? 0, 2) ?></strong></td>
                                        <td><?= $row['actual_ia_members'] ? '<span class="badge badge-info">'.$row['actual_ia_members'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                                        <td><?= $row['tsags_count'] ? '<span class="badge badge-secondary">'.$row['tsags_count'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                                        <td>
                                            <?php $isOp = in_array($row['status'],['active','operational']); ?>
                                            <span class="status-badge status-<?= $isOp ? 'operational' : 'nonoperational' ?>">
                                                <i class="fas fa-circle" style="font-size:8px;"></i>
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>
                                        <td><div id="assigned-employee-<?= $row['id'] ?>"><span class="text-muted"><i class="fas fa-spinner fa-spin fa-xs"></i></span></div></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view view-profile" data-id="<?= $row['id'] ?>" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="ia_profile_history.php?id=<?= $row['id'] ?>" class="btn-action btn-history" title="View History">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                                <?php if (hasPermission('edit_ia_profile')): ?>
                                                <button class="btn-action btn-edit edit-profile" data-id="<?= $row['id'] ?>" title="Edit Profile">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn-action btn-assign assign-employee" data-id="<?= $row['id'] ?>" title="Assign Employee">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                                <?php if (hasPermission('delete_ia_profile')): ?>
                                                <button class="btn-action btn-delete delete-profile" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['ia_name']) ?>" title="Delete Profile">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                            <span class="text-muted">No IA Profiles found</span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /.card -->

            </div>
        </section>
    </div><!-- /.content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /.wrapper -->


<!-- ================================================================
     MODAL — ADD / EDIT IA PROFILE
     ================================================================ -->
<div class="modal fade" id="iaProfileModal" tabindex="-1" role="dialog" aria-labelledby="iaProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-ia">
                <h5 class="modal-title text-white" id="iaProfileModalLabel">
                    <i class="fas fa-leaf mr-2"></i><span id="modalTitleText">Add IA Profile</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="iaProfileForm" novalidate>
                <input type="hidden" id="modal_ia_id" name="id" value="">
                <input type="hidden" id="modal_action" name="action" value="create">

                <div class="modal-body" style="padding: 1.5rem;">

                    <!-- ── Section: Basic Information ── -->
                    <div class="modal-section-title">
                        <i class="fas fa-info-circle mr-1"></i> Basic Information
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>IA Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="modal_ia_name" name="ia_name"
                                       placeholder="e.g. Naga Irrigation Association" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>IA Code</label>
                                <input type="text" class="form-control" id="modal_ia_code" name="ia_code"
                                       placeholder="e.g. NIA-001">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label>Name of CIS</label>
                                <input type="text" class="form-control" id="modal_cis_name" name="cis_name"
                                       placeholder="e.g. Balading-Awang Communal Irrigation System">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status <span class="required-star">*</span></label>
                                <select class="form-control" id="modal_status" name="status" required>
                                    <option value="operational">Operational</option>
                                    <option value="non-operational">Non-operational</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date Organized</label>
                                <input type="date" class="form-control" id="modal_date_organized"
                                       name="date_organized">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label>Mailing Address</label>
                                <input type="text" class="form-control" id="modal_mailing_address"
                                       name="mailing_address" placeholder="Full mailing address">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section: Location ── -->
                    <div class="modal-section-title">
                        <i class="fas fa-map-marker-alt mr-1"></i> Location
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Region <span class="required-star">*</span></label>
                                <select class="form-control" id="modal_region" name="region" required>
                                    <option value="">Select Region</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Province <span class="required-star">*</span></label>
                                <select class="form-control" id="modal_province" name="province">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>District</label>
                                <select class="form-control" id="modal_district" name="district">
                                    <option value="">Select District</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Municipality / City</label>
                                <input type="text" class="form-control" id="modal_municipality"
                                       name="municipality" placeholder="Municipality or City">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Barangay</label>
                                <input type="text" class="form-control" id="modal_barangay"
                                       name="barangay" placeholder="Barangay">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section: Officers / Key People ── -->
                    <div class="modal-section-title">
                        <i class="fas fa-user-tie mr-1"></i> Officers / Key People
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>President Name</label>
                                <input type="text" class="form-control" id="modal_president_name"
                                       name="president_name" placeholder="Full name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="modal_contact_number"
                                           name="contact_number" placeholder="09XX-XXX-XXXX">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Section: Registration / Legal ── -->
                    <div class="modal-section-title">
                        <i class="fas fa-file-alt mr-1"></i> Registration / Legal
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>SEC Registration Number</label>
                                <input type="text" class="form-control" id="modal_sec_registration_number"
                                       name="sec_registration_number" placeholder="e.g. CN-202312345">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>SEC Registration Date</label>
                                <input type="date" class="form-control" id="modal_sec_registration_date"
                                       name="sec_registration_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>IA TIN</label>
                                <input type="text" class="form-control" id="modal_ia_tin"
                                       name="ia_tin" placeholder="e.g. 123-456-789-000">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Existing Contract</label>
                                <input type="text" class="form-control" id="modal_existing_contract"
                                       name="existing_contract" placeholder="e.g. MOA-2024-001">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contract Effectivity Date</label>
                                <input type="date" class="form-control" id="modal_contract_effectivity_date"
                                       name="contract_effectivity_date">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section: Operational Data ── -->
                    <div class="modal-section-title">
                        <i class="fas fa-chart-bar mr-1"></i> Operational Data
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Service Area (ha)</label>
                                <input type="number" class="form-control" id="modal_service_area_ha"
                                       name="service_area_ha" placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>FUSA (ha)</label>
                                <input type="number" class="form-control" id="modal_fusa_ha"
                                       name="fusa_ha" placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Canal Length (km)</label>
                                <input type="number" class="form-control" id="modal_canal_length_km"
                                       name="canal_length_km" placeholder="0.000" step="0.001" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>NIS Area (ha)</label>
                                <input type="number" class="form-control" id="modal_nis_area_ha"
                                       name="nis_area_ha" placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Actual IA Members</label>
                                <input type="number" class="form-control" id="modal_actual_ia_members"
                                       name="actual_ia_members" placeholder="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Farmer Beneficiaries</label>
                                <input type="number" class="form-control" id="modal_farmer_beneficiaries"
                                       name="farmer_beneficiaries" placeholder="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Male Members</label>
                                <input type="number" class="form-control" id="modal_male_members"
                                       name="male_members" placeholder="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Female Members</label>
                                <input type="number" class="form-control" id="modal_female_members"
                                       name="female_members" placeholder="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>TSAGs Count</label>
                                <input type="number" class="form-control" id="modal_tsags_count"
                                       name="tsags_count" placeholder="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label>Remarks / Notes</label>
                                <textarea class="form-control" id="modal_remarks" name="remarks"
                                          rows="3" placeholder="Any additional notes about this IA Profile…"></textarea>
                            </div>
                        </div>
                    </div>

                </div><!-- /.modal-body -->

                <div class="modal-footer" style="border-top: 1px solid var(--surface-border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="btnSaveIaProfile">
                        <i class="fas fa-save mr-1"></i> <span id="saveBtnText">Save Profile</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL — ASSIGN EMPLOYEE
     ================================================================ -->
<div class="modal fade" id="assignEmployeeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-ia">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user-plus mr-2"></i>Assign Employee
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="assignEmployeeForm">
                <div class="modal-body">
                    <input type="hidden" id="assign_ia_profile_id" name="ia_profile_id">
                    <div class="form-group">
                        <label><i class="fas fa-users mr-1 text-muted"></i> IDU – Operation and Maintenance Section</label>
                        <select class="form-control" id="assigned_employee" name="emp_id">
                            <option value="">— Unassign / No one assigned —</option>
                        </select>
                    </div>
                    <div id="current-assignment" class="alert alert-info d-none">
                        <i class="fas fa-user-check mr-1"></i>
                        Currently assigned to: <strong id="current-assigned-name"></strong>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--surface-border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>


<script>
/* ================================================================
   DATATABLE INIT
   ================================================================ */
$(document).ready(function () {

    const table = $('#iaProfilesTable').DataTable({
        responsive: true,
        autoWidth: false,
        processing: true,
        serverSide: false,
        ajax: {
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: function (d) {
                return {
                    action: 'get_ia_profiles',
                    filter_assigned_employee: $('#filter_assigned_employee').val(),
                    filter_status: $('#filter_status').val(),
                    filter_region: $('#filter_region').val(),
                    filter_province: $('#filter_province').val(),
                    filter_district: $('#filter_district').val(),
                    filter_ia_name: $('#filter_ia_name').val(),
                    filter_ia_code: $('#filter_ia_code').val()
                };
            },
            dataSrc: function (res) {
                return res.success ? res.data : [];
            }
        },
        columns: [
            { data: 'ia_name',  render: d => `<strong>${d}</strong>` },
            { data: 'cis_name', render: d => d || '<span class="text-muted">N/A</span>' },
            { data: 'president_name',   render: d => d || '<span class="text-muted">N/A</span>' },
            { data: 'contact_number',   render: d => d || '<span class="text-muted">N/A</span>' },
            { data: 'service_area_ha',  render: d => d ? `<strong>${parseFloat(d).toFixed(2)}</strong>` : '<span class="text-muted">0.00</span>' },
            { data: 'actual_ia_members',render: d => d ? `<span class="badge badge-info">${d}</span>` : '<span class="text-muted">0</span>' },
            { data: 'tsags_count',      render: d => d ? `<span class="badge badge-secondary">${d}</span>` : '<span class="text-muted">0</span>' },
            {
                data: 'status',
                render: function (d) {
                    const isOp  = (d === 'active' || d === 'operational');
                    const cls   = isOp ? 'status-operational' : 'status-nonoperational';
                    const label = d ? d.charAt(0).toUpperCase() + d.slice(1) : 'Unknown';
                    return `<span class="status-badge ${cls}"><i class="fas fa-circle" style="font-size:8px;"></i> ${label}</span>`;
                }
            },
            {
                data: 'id',
                render: d => `<div id="assigned-employee-${d}"><span class="text-muted"><i class="fas fa-spinner fa-spin fa-xs"></i></span></div>`
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                width: '200px',
                render: function (id, type, row) {
                    const name = row.ia_name ? row.ia_name.replace(/"/g,'&quot;') : '';
                    let b = `<div class="action-buttons">`;
                    b += `<button class="btn-action btn-view view-profile" data-id="${id}" title="View Profile"><i class="fas fa-eye"></i></button>`;
                    b += `<a href="ia_profile_history.php?id=${id}" class="btn-action btn-history" title="View History"><i class="fas fa-history"></i></a>`;
                    <?php if (hasPermission('edit_ia_profile')): ?>
                    b += `<button class="btn-action btn-edit edit-profile" data-id="${id}" title="Edit Profile"><i class="fas fa-edit"></i></button>`;
                    <?php endif; ?>
                    b += `<button class="btn-action btn-assign assign-employee" data-id="${id}" title="Assign Employee"><i class="fas fa-user-plus"></i></button>`;
                    <?php if (hasPermission('delete_ia_profile')): ?>
                    b += `<button class="btn-action btn-delete delete-profile" data-id="${id}" data-name="${name}" title="Delete Profile"><i class="fas fa-trash"></i></button>`;
                    <?php endif; ?>
                    b += `</div>`;
                    return b;
                }
            }
        ],
        language: { emptyTable: 'No IA Profiles found', zeroRecords: 'No matching records found' },
        initComplete: function () { loadAllAssignedEmployees(); }
    });

    /* ── Load regions on init ── */
    loadRegions();

    /* ── VIEW ── */
    $(document).on('click', '.view-profile', function () {
        window.location.href = 'ia_profile_view.php?id=' + $(this).data('id');
    });

    /* ── ADD button opens empty modal ── */
    $('#btnAddIaProfile').on('click', function () {
        openModal('create');
    });

    /* ── EDIT button opens filled modal ── */
    $(document).on('click', '.edit-profile', function () {
        const id = $(this).data('id');
        openModal('edit', id);
    });

    /* ── DELETE ── */
    $(document).on('click', '.delete-profile', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name') || 'this profile';

        Swal.fire({
            title: 'Delete IA Profile?',
            html: `<span style="color:var(--tx-primary)">You are about to permanently delete <strong>${name}</strong>.<br>This action cannot be undone.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash mr-1"></i> Yes, Delete',
            cancelButtonText:  'Cancel',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            preConfirm: () => new Promise(resolve => {
                $.post('../includes/ia_profiles_ajax.php',
                    { action: 'delete', id: id }, resolve, 'json');
            }),
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (!result.isConfirmed) return;
            if (result.value?.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: `"${name}" has been removed.`,
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => table.ajax.reload(loadAllAssignedEmployees, false));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.value?.message || 'Could not delete the profile.' });
            }
        });
    });

    /* ── ASSIGN employee ── */
    $(document).on('click', '.assign-employee', function () {
        const profileId = $(this).data('id');
        $('#assign_ia_profile_id').val(profileId);

        // Load current assignment
        $.post('../includes/ia_profiles_ajax.php',
            { action: 'get_assigned_employee', ia_profile_id: profileId },
            function (res) {
                if (res.success && res.assigned && res.employee_name) {
                    $('#current-assignment').removeClass('d-none');
                    $('#current-assigned-name').text(res.employee_name);
                    $('#assigned_employee').val(res.emp_id);
                } else {
                    $('#current-assignment').addClass('d-none');
                    $('#assigned_employee').val('');
                }
            }, 'json');

        loadIduEmployees();
        $('#assignEmployeeModal').modal('show');
    });

    /* ── ASSIGN submit ── */
    $('#assignEmployeeForm').on('submit', function (e) {
        e.preventDefault();
        const profileId = $('#assign_ia_profile_id').val();

        $.post('../includes/ia_profiles_ajax.php',
            $(this).serialize() + '&action=assign_employee',
            function (res) {
                $('#assignEmployeeModal').modal('hide');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Assigned!',
                        text: res.employee_name
                            ? `Assigned to ${res.employee_name} successfully.`
                            : 'Assignment updated successfully.',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => loadAssignedEmployee(profileId));
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Assignment failed.' });
                }
            }, 'json')
        .fail(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' }));
    });

    /* ── FORM submit (create / edit) ── */
    $('#iaProfileForm').on('submit', function (e) {
        e.preventDefault();

        // Manual validation — only check fields that are truly required
        // (avoid checkValidity() which fails on async-loaded province/district dropdowns)
        const missingFields = [];
        if (!$('#modal_ia_name').val().trim())  missingFields.push('IA Name');
        if (!$('#modal_status').val())           missingFields.push('Status');
        if (!$('#modal_region').val())           missingFields.push('Region');
        if (!$('#modal_province').val())         missingFields.push('Province');

        if (missingFields.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Fields',
                text: 'Please fill in: ' + missingFields.join(', ')
            });
            return;
        }

        const action   = $('#modal_action').val();
        const isEdit   = action === 'edit';
        const btnText  = $('#saveBtnText');
        const $btn     = $('#btnSaveIaProfile');

        $btn.prop('disabled', true);
        btnText.text(isEdit ? 'Updating…' : 'Saving…');

        $.post('../includes/ia_profiles_ajax.php',
            $(this).serialize(),
            function (res) {
                $btn.prop('disabled', false);
                btnText.text('Save Profile');

                if (res.success) {
                    $('#iaProfileModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: isEdit ? 'Updated!' : 'Created!',
                        text: isEdit
                            ? 'IA Profile updated successfully.'
                            : 'New IA Profile has been created.',
                        timer: 1900,
                        showConfirmButton: false
                    }).then(() => table.ajax.reload(loadAllAssignedEmployees, false));
                } else {
                    Swal.fire({ icon: 'error', title: 'Save Failed', text: res.message || 'An error occurred.' });
                }
            }, 'json')
        .fail(() => {
            $btn.prop('disabled', false);
            btnText.text('Save Profile');
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' });
        });
    });

    /* ================================================================
       FILTER BAR — cascading selects + server-side filtering
       ================================================================ */
    function loadFilterProvinces(regionCode, callback) {
        $('#filter_province').html('<option value="">All Provinces</option>');
        $('#filter_district').html('<option value="">All Districts</option>');
        if (!regionCode) { if (callback) callback(); return; }
        $.post('../includes/ia_profiles_ajax.php',
            { action: 'get_provinces', region_code: regionCode },
            function (res) {
                if (res.success) res.data.forEach(p => $('#filter_province').append(new Option(p.province_name, p.province_code)));
                if (callback) callback();
            }, 'json');
    }

    function loadFilterDistricts(provinceCode, callback) {
        $('#filter_district').html('<option value="">All Districts</option>');
        if (!provinceCode) { if (callback) callback(); return; }
        $.post('../includes/ia_profiles_ajax.php',
            { action: 'get_districts', province_code: provinceCode },
            function (res) {
                if (res.success) res.data.forEach(d => $('#filter_district').append(new Option(d.district_name, d.district_code)));
                if (callback) callback();
            }, 'json');
    }

    function loadFilterAssignedEmployees() {
        $.post('../includes/ia_profiles_ajax.php', { action: 'get_idu_employees' }, function (res) {
            if (res.success) res.data.forEach(e => $('#filter_assigned_employee').append(new Option(e.full_name, e.emp_id)));
        }, 'json');
    }
    loadFilterAssignedEmployees();

    $(document).on('change', '#filter_region',   function () { loadFilterProvinces($(this).val()); });
    $(document).on('change', '#filter_province', function () { loadFilterDistricts($(this).val()); });

    /* Filters are sent to the server on every ajax.reload() (see the
       DataTable's ajax.data function above) — the server does the actual
       filtering (get_ia_profiles in ia_profiles_ajax.php), so results stay
       correct even when codes/names don't match exactly client-side. */
    $('#applyFilters').on('click', function () { table.ajax.reload(); });

    $('#resetFilters').on('click', function () {
        $('#filter_assigned_employee').val('');
        $('#filter_status').val('');
        $('#filter_region').val('');
        $('#filter_province').html('<option value="">All Provinces</option>');
        $('#filter_district').html('<option value="">All Districts</option>');
        $('#filter_ia_name').val('');
        $('#filter_ia_code').val('');
        table.ajax.reload();
    });

}); /* end $(document).ready */


/* ================================================================
   MODAL HELPERS
   ================================================================ */
function openModal(mode, id) {
    const isEdit = mode === 'edit';

    // Reset form
    $('#iaProfileForm')[0].reset();
    $('#iaProfileForm').removeClass('was-validated');
    $('#modal_ia_id').val('');
    $('#modal_action').val(mode);
    $('#modal_region').html('<option value="">Select Region</option>');
    $('#modal_province').html('<option value="">Select Province</option>');
    $('#modal_district').html('<option value="">Select District</option>');

    // Update title
    $('#modalTitleText').text(isEdit ? 'Edit IA Profile' : 'Add IA Profile');
    $('#saveBtnText').text(isEdit ? 'Update Profile' : 'Save Profile');

    // Always load regions first
    loadModalRegions(function () {
        if (isEdit && id) {
            // Fetch existing data and populate
            $.post('../includes/ia_profiles_ajax.php',
                { action: 'get_ia_profile', id: id },
                function (res) {
                    if (res.success && res.data) {
                        const d = res.data;
                        // Map DB columns correctly to form fields
                        $('#modal_ia_id').val(d.id);
                        $('#modal_ia_name').val(d.ia_name || '');
                        $('#modal_ia_code').val(d.ia_code || '');
                        $('#modal_cis_name').val(d.cis_name || '');
                        $('#modal_status').val(d.status || 'operational');
                        $('#modal_date_organized').val(d.date_organized || '');
                        $('#modal_mailing_address').val(d.mailing_address || '');
                        $('#modal_president_name').val(d.president_name || '');
                        $('#modal_contact_number').val(d.contact_number || '');
                        // Registration / Legal
                        $('#modal_sec_registration_number').val(d.sec_registration_number || '');
                        $('#modal_sec_registration_date').val(d.sec_registration_date || '');
                        $('#modal_ia_tin').val(d.ia_tin || '');
                        $('#modal_existing_contract').val(d.existing_contract || '');
                        $('#modal_contract_effectivity_date').val(d.contract_effectivity_date || '');
                        // Operational Data
                        $('#modal_service_area_ha').val(d.service_area_ha || '');
                        $('#modal_fusa_ha').val(d.fusa_ha || '');
                        $('#modal_canal_length_km').val(d.canal_length_km || '');
                        $('#modal_actual_ia_members').val(d.actual_ia_members || '');
                        $('#modal_farmer_beneficiaries').val(d.farmer_beneficiaries || '');
                        $('#modal_male_members').val(d.male_members || '');
                        $('#modal_female_members').val(d.female_members || '');
                        $('#modal_tsags_count').val(d.tsags_count || '');
                        $('#modal_nis_area_ha').val(d.nis || '');       // DB col = nis
                        $('#modal_remarks').val(d.imo || '');           // DB col = imo
                        $('#modal_municipality').val('');               // not in DB
                        $('#modal_barangay').val('');                   // not in DB

                        // Cascading selects — DB stores codes (e.g. region=V, province=ALB)
                        if (d.region) {
                            $('#modal_region').val(d.region);
                            loadModalProvinces(d.region, function () {
                                if (d.province) {
                                    $('#modal_province').val(d.province);
                                    loadModalDistricts(d.province, function () {
                                        // DB column is congressional_district, not district
                                        if (d.congressional_district) {
                                            $('#modal_district').val(d.congressional_district);
                                        }
                                    });
                                }
                            });
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Load Error', text: res.message || 'Could not load profile data.' });
                    }
                }, 'json');
        }
    });

    $('#iaProfileModal').modal('show');
}

/* Modal-scoped cascading selects */
function loadModalRegions(callback) {
    $.post('../includes/ia_profiles_ajax.php', { action: 'get_regions' }, function (res) {
        if (res.success) {
            res.data.forEach(r => $('#modal_region').append(new Option(r.region_name, r.region_code)));
        }
        if (callback) callback();
    }, 'json');
}

function loadModalProvinces(regionCode, callback) {
    $('#modal_province').html('<option value="">Select Province</option>');
    $('#modal_district').html('<option value="">Select District</option>');
    if (!regionCode) { if (callback) callback(); return; }
    $.post('../includes/ia_profiles_ajax.php', { action: 'get_provinces', region_code: regionCode }, function (res) {
        if (res.success) res.data.forEach(p => $('#modal_province').append(new Option(p.province_name, p.province_code)));
        if (callback) callback();
    }, 'json');
}

function loadModalDistricts(provinceCode, callback) {
    $('#modal_district').html('<option value="">Select District</option>');
    if (!provinceCode) { if (callback) callback(); return; }
    $.post('../includes/ia_profiles_ajax.php', { action: 'get_districts', province_code: provinceCode }, function (res) {
        if (res.success) res.data.forEach(d => $('#modal_district').append(new Option(d.district_name, d.district_code)));
        if (callback) callback();
    }, 'json');
}

$(document).on('change', '#modal_region',   function () { loadModalProvinces($(this).val()); });
$(document).on('change', '#modal_province', function () { loadModalDistricts($(this).val()); });


/* ================================================================
   ASSIGNED-EMPLOYEE HELPERS
   ================================================================ */
function loadAllAssignedEmployees() {
    const dt = $('#iaProfilesTable').DataTable();
    dt.rows().data().each(function (row) {
        if (row?.id) loadAssignedEmployee(row.id);
    });
}

function loadAssignedEmployee(profileId) {
    $.post('../includes/ia_profiles_ajax.php',
        { action: 'get_assigned_employee', ia_profile_id: profileId },
        function (res) {
            const $el = $('#assigned-employee-' + profileId);
            if (res.success && res.assigned && res.employee_name) {
                $el.html(`<span class="assignment-badge"><i class="fas fa-user-check"></i> ${res.employee_name}</span>`);
            } else {
                $el.html('<span class="text-muted"><i class="fas fa-user-times"></i> Not assigned</span>');
            }
        }, 'json');
}

function loadIduEmployees() {
    $.post('../includes/ia_profiles_ajax.php', { action: 'get_idu_employees' }, function (res) {
        if (res.success) {
            const $s = $('#assigned_employee');
            $s.find('option:not(:first)').remove();
            res.data.forEach(e => $s.append(new Option(e.full_name, e.emp_id)));
        }
    }, 'json');
}


/* ================================================================
   FILTER-BAR region helpers (for filter dropdowns, not modal)
   ================================================================ */
function loadRegions() {
    $.post('../includes/ia_profiles_ajax.php', { action: 'get_regions' }, function (res) {
        if (!res.success) return;
        res.data.forEach(r => $('#filter_region').append(new Option(r.region_name, r.region_code)));
    }, 'json');
}

$(document).ready(function () {
    setTimeout(loadAllAssignedEmployees, 800);
});
</script>
</body>
</html>