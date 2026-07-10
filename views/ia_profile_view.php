<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$id = $_GET['id'] ?? 0;
if (empty($id)) { header('Location: ia_profiles.php'); exit(); }

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM ia_profiles WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) { header('Location: ia_profiles.php'); exit(); }

// Get assigned employee
$assign_q = "SELECT e.emp_id, CONCAT(e.first_name,' ',e.last_name) as full_name
             FROM ia_profiles ip
             LEFT JOIN employee e ON ip.assigned_employee_id = e.emp_id
             WHERE ip.id = ?";
$assign_s = $db->prepare($assign_q);
$assign_s->bind_param('i', $id);
$assign_s->execute();
$assigned = $assign_s->get_result()->fetch_assoc();

// Get officers
$off_q = "SELECT * FROM ia_officers WHERE ia_profile_id = ? ORDER BY position";
$off_s = $db->prepare($off_q);
$off_s->bind_param('i', $id);
$off_s->execute();
$officers = $off_s->get_result()->fetch_all(MYSQLI_ASSOC);

$isOperational = in_array($profile['status'], ['active', 'operational']);
$page_title = "IA Profile – " . htmlspecialchars($profile['ia_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
    /* ── CSS variables ───────────────────────────────────── */
    :root {
        --ia-accent:       #1a5c38;
        --ia-accent-lt:    #24e78f;
        --surface:         #ffffff;
        --surface-alt:     #f4f7f6;
        --surface-border:  #dee2e6;
        --tx-primary:      #1a2e1e;
        --tx-secondary:    #495057;
        --tx-muted:        #6c757d;
        --tbl-head:        #f0f4f2;
        --tbl-border:      #e9ecef;
        --tbl-hover:       rgba(26,92,56,.05);
        --input-bg:        #fff;
        --input-border:    #ced4da;
        --input-color:     #212529;
        --modal-bg:        #fff;
        --modal-hd-bg:     #1a5c38;
        --modal-hd-color:  #fff;
        --stat-border:     #1a5c38;
        --hero-bg:         #0b1f17;
    }
    body.dark-mode {
        --surface:         #1e2d24;
        --surface-alt:     #172218;
        --surface-border:  #2d4035;
        --tx-primary:      #d4f5e5;
        --tx-secondary:    #a8c4b0;
        --tx-muted:        #6b8f78;
        --tbl-head:        #172218;
        --tbl-border:      #2d4035;
        --tbl-hover:       rgba(36,231,143,.06);
        --input-bg:        #172218;
        --input-border:    #2d4035;
        --input-color:     #d4f5e5;
        --modal-bg:        #1e2d24;
        --modal-hd-bg:     #0f2d1e;
        --modal-hd-color:  #d4f5e5;
        --stat-border:     #24e78f;
        --hero-bg:         #0b1f17;
    }

    /* ── Global dark-mode overrides ───────────────────────── */
    body.dark-mode, body.dark-mode .content-wrapper { background-color: var(--body-bg, var(--surface-alt)) !important; color: var(--tx-primary) !important; }
    body.dark-mode .card  { background: var(--surface) !important; border-color: var(--surface-border) !important; color: var(--tx-primary) !important; }
    body.dark-mode .card-header { background: var(--surface-alt) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .card-body   { background: var(--surface) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-header  { background: var(--modal-hd-bg) !important; color: var(--modal-hd-color) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .modal-body    { background: var(--modal-bg) !important; color: var(--tx-primary) !important; }
    body.dark-mode .modal-footer  { background: var(--modal-bg) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .close { color: var(--modal-hd-color) !important; }
    body.dark-mode .table { background: var(--surface) !important; color: var(--tx-primary) !important; }
    body.dark-mode .table th, body.dark-mode .table td { border-color: var(--tbl-border) !important; color: var(--tx-primary) !important; }
    body.dark-mode .table thead th { background: var(--tbl-head) !important; color: var(--tx-secondary) !important; }
    body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--surface-alt) !important; }
    body.dark-mode .table-hover tbody tr:hover { background: var(--tbl-hover) !important; }
    body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control:focus { border-color: var(--ia-accent-lt) !important; box-shadow: 0 0 0 .2rem rgba(36,231,143,.15) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode label { color: var(--tx-secondary) !important; }
    body.dark-mode .text-muted { color: var(--tx-muted) !important; }
    body.dark-mode .text-dark  { color: var(--tx-primary) !important; }
    body.dark-mode h1,body.dark-mode h2,body.dark-mode h3,
    body.dark-mode h4,body.dark-mode h5,body.dark-mode h6 { color: var(--tx-primary) !important; }
    body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--tx-primary); }
    body.dark-mode .breadcrumb { background: var(--surface) !important; }
    body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
    body.dark-mode .dropdown-menu { background: var(--surface) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .dropdown-item { color: var(--tx-primary) !important; }
    body.dark-mode .dropdown-item:hover { background: var(--surface-alt) !important; }
    body.dark-mode hr { border-color: var(--surface-border) !important; }
    body.dark-mode .list-group-item { background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .alert-info    { background:#1e2f3e !important; color:#93c5fd !important; border-color:#2d4a5e !important; }
    body.dark-mode .alert-success { background:#1a2e1e !important; color:#86efac !important; border-color:#2d4035 !important; }
    body.dark-mode .alert-warning { background:#2e2412 !important; color:#fcd34d !important; border-color:#4a3a1a !important; }
    body.dark-mode .alert-danger  { background:#2e1515 !important; color:#fca5a5 !important; border-color:#4a2222 !important; }
    body.dark-mode .page-item .page-link { background: var(--surface) !important; color: var(--tx-primary) !important; border-color: var(--surface-border) !important; }
    body.dark-mode .dataTables_wrapper { color: var(--tx-primary) !important; }
    body.dark-mode .dataTables_filter input,
    body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .dataTables_info { color: var(--tx-muted) !important; }

    /* ── HERO BANNER ───────────────────────────────────────── */
    .ia-hero {
        background: var(--hero-bg);
        padding: 28px 28px 52px;
        position: relative;
        overflow: hidden;
        margin-bottom: 0;
    }
    .ia-hero-mesh {
        position: absolute; inset: -50%; width: 200%; height: 200%;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, rgba(36,231,143,.15) 0%, transparent 55%),
            radial-gradient(ellipse 50% 55% at 85% 75%, rgba(42,152,99,.12) 0%, transparent 55%),
            linear-gradient(155deg, #0f2d1e 0%, #071510 55%, #1c4d38 100%);
        animation: heroMesh 20s ease-in-out infinite alternate;
        z-index: 0;
    }
    @keyframes heroMesh { 0% { transform: translate(0,0); } 100% { transform: translate(2%,1.5%); } }
    .ia-hero-dots {
        position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background-image: radial-gradient(circle, rgba(36,231,143,.05) 1px, transparent 1px);
        background-size: 32px 32px;
    }
    .ia-hero-inner { position: relative; z-index: 2; }
    .ia-hero-name  { color: #fff; font-size: 1.7rem; font-weight: 800; margin: 0 0 4px; letter-spacing: -.3px; }
    .ia-hero-sub   { color: rgba(212,245,229,.7); font-size: .9rem; margin: 0 0 12px; }
    .ia-hero-divider { width: 44px; height: 2px; border-radius: 2px; margin: 0 0 14px; background: linear-gradient(90deg, transparent, #24e78f, transparent); }
    .ia-hero-badges { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

    .ia-status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 700;
    }
    .ia-status-pill.operational   { background: rgba(36,231,143,.15); color: #24e78f; border: 1px solid rgba(36,231,143,.3); }
    .ia-status-pill.nonoperational{ background: rgba(220,53,69,.15);  color: #ff8a90; border: 1px solid rgba(220,53,69,.3); }

    .ia-hero-tag {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
        color: rgba(212,245,229,.8); border-radius: 8px;
        padding: 4px 12px; font-size: 12px;
    }
    .ia-hero-actions {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        position: relative; z-index: 2; margin-top: 14px;
    }
    .ia-hero-btn {
        background: rgba(36,231,143,.1); border: 1px solid rgba(36,231,143,.3);
        color: #d4f5e5; border-radius: 9px; padding: 7px 16px;
        font-size: .83rem; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 7px;
        text-decoration: none;
        transition: background .2s, transform .18s, box-shadow .2s;
    }
    .ia-hero-btn:hover { background: rgba(36,231,143,.22); border-color: rgba(36,231,143,.55); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(36,231,143,.2); color: #d4f5e5; text-decoration: none; }
    .ia-hero-btn.danger { background: rgba(220,53,69,.12); border-color: rgba(220,53,69,.35); }
    .ia-hero-btn.danger:hover { background: rgba(220,53,69,.25); }

    /* Wave transition */
    .ia-hero::after {
        content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 48px;
        background: var(--body-bg, var(--surface-alt)); clip-path: ellipse(55% 100% at 50% 100%); z-index: 1;
    }
    body.dark-mode .ia-hero::after { background: var(--body-bg, #172218); }

    /* ── QUICK STATS ROW ────────────────────────────────────── */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        padding: 1.1rem 1rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        border-top: 3px solid var(--stat-border);
        transition: transform .2s, box-shadow .2s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.1); }
    .stat-number { font-size: 1.75rem; font-weight: 800; color: var(--ia-accent); display: block; line-height: 1; }
    body.dark-mode .stat-number { color: var(--ia-accent-lt) !important; }
    .stat-label  { font-size: 11px; color: var(--tx-muted); text-transform: uppercase; letter-spacing: .7px; margin-top: 4px; }

    /* ── INFO CARDS ─────────────────────────────────────────── */
    .info-card {
        background: var(--surface);
        border: 1px solid var(--surface-border);
        border-radius: 12px;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        overflow: hidden;
        transition: box-shadow .2s;
    }
    .info-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .info-card .ic-header {
        background: linear-gradient(135deg, var(--ia-accent), #2d7a50);
        color: #fff;
        padding: .75rem 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    body.dark-mode .info-card .ic-header { background: linear-gradient(135deg, #0f2d1e, #1a5c38) !important; }
    .info-card .ic-header h4 { margin: 0; font-size: 14px; font-weight: 700; color: #fff; }
    .info-card .ic-body { padding: 0; }

    .info-row {
        display: flex;
        border-bottom: 1px solid var(--tbl-border);
        padding: .65rem 1rem;
        align-items: flex-start;
        gap: .5rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row:hover { background: var(--tbl-hover); }
    .info-row .ir-label {
        width: 42%;
        flex-shrink: 0;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--tx-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .info-row .ir-value { font-size: 13px; color: var(--tx-primary); flex: 1; word-break: break-word; }
    body.dark-mode .info-row .ir-label  { color: var(--tx-muted) !important; }
    body.dark-mode .info-row .ir-value  { color: var(--tx-primary) !important; }

    /* Officers table */
    .officers-table th { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--tx-secondary); background: var(--tbl-head) !important; border-color: var(--tbl-border) !important; }
    .officers-table td { vertical-align: middle; font-size: 13px; color: var(--tx-primary); border-color: var(--tbl-border) !important; }
    body.dark-mode .officers-table th { color: var(--tx-muted) !important; }
    body.dark-mode .officers-table td { color: var(--tx-primary) !important; }

    .btn-officer-action {
        border: none; border-radius: 6px; padding: 4px 9px; font-size: 12px;
        cursor: pointer; transition: transform .15s, box-shadow .15s;
    }
    .btn-officer-action:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,.15); }
    .btn-officer-edit   { background: linear-gradient(135deg,#28a745,#218838); color:#fff; }
    .btn-officer-delete { background: linear-gradient(135deg,#dc3545,#c82333); color:#fff; }

    /* Modal */
    .modal-hd-ia { background: linear-gradient(135deg, var(--ia-accent), #2d7a50); border-radius: 6px 6px 0 0; }
    body.dark-mode .modal-hd-ia { background: linear-gradient(135deg,#0b1f17,#1a5c38) !important; }
    .modal-content { border: none; border-radius: 10px; overflow: hidden; }
    body.dark-mode .modal-content { box-shadow: 0 8px 40px rgba(0,0,0,.55) !important; }
    .modal .form-control { background: var(--input-bg); color: var(--input-color); border-color: var(--input-border); border-radius: 7px; font-size: 13.5px; }
    .modal .form-control:focus { border-color: var(--ia-accent); box-shadow: 0 0 0 3px rgba(26,92,56,.15); }
    body.dark-mode .modal .form-control:focus { border-color: var(--ia-accent-lt) !important; box-shadow: 0 0 0 3px rgba(36,231,143,.15) !important; }
    .modal label { font-size: 12.5px; font-weight: 600; color: var(--tx-secondary); margin-bottom: 4px; }

    /* Assigned employee pill */
    .assigned-pill {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(26,92,56,.1); border: 1px solid rgba(26,92,56,.25);
        color: var(--ia-accent); border-radius: 20px; padding: 4px 12px; font-size: 12.5px; font-weight: 600;
    }
    body.dark-mode .assigned-pill { background: rgba(36,231,143,.1) !important; border-color: rgba(36,231,143,.25) !important; color: var(--ia-accent-lt) !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ia.php'; ?>

    <div class="content-wrapper">

        <!-- ── HERO ── -->
        <div class="ia-hero">
            <div class="ia-hero-mesh"></div>
            <div class="ia-hero-dots"></div>
            <div class="ia-hero-inner">
                <!-- breadcrumb -->
                <nav aria-label="breadcrumb" style="margin-bottom:10px;">
                    <ol class="breadcrumb" style="background:transparent;padding:0;margin:0;">
                        <li class="breadcrumb-item"><a href="dashboard.php" style="color:rgba(212,245,229,.6);">Home</a></li>
                        <li class="breadcrumb-item"><a href="ia_profiles.php" style="color:rgba(212,245,229,.6);">IA Profiles</a></li>
                        <li class="breadcrumb-item active" style="color:rgba(212,245,229,.85);"><?= htmlspecialchars($profile['ia_name']) ?></li>
                    </ol>
                </nav>

                <div class="ia-hero-name"><i class="fas fa-leaf mr-2" style="color:#24e78f;"></i><?= htmlspecialchars($profile['ia_name']) ?></div>
                <div class="ia-hero-divider"></div>
                <div class="ia-hero-badges">
                    <span class="ia-status-pill <?= $isOperational ? 'operational' : 'nonoperational' ?>">
                        <i class="fas fa-circle" style="font-size:8px;"></i>
                        <?= ucfirst($profile['status']) ?>
                    </span>
                    <?php if ($profile['ia_code']): ?>
                    <span class="ia-hero-tag"><i class="fas fa-barcode"></i><?= htmlspecialchars($profile['ia_code']) ?></span>
                    <?php endif; ?>
                    <?php if ($profile['province']): ?>
                    <span class="ia-hero-tag"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($profile['province']) ?></span>
                    <?php endif; ?>
                    <?php if ($assigned && $assigned['full_name']): ?>
                    <span class="ia-hero-tag"><i class="fas fa-user-check"></i><?= htmlspecialchars(trim($assigned['full_name'])) ?></span>
                    <?php endif; ?>
                </div>

                <div class="ia-hero-actions">
                    <a href="ia_profiles.php" class="ia-hero-btn">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <?php if (hasPermission('edit_ia_profile')): ?>
                    <a href="ia_profile_edit.php?id=<?= $id ?>" class="ia-hero-btn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <?php endif; ?>
                    <a href="ia_profile_history.php?id=<?= $id ?>" class="ia-hero-btn">
                        <i class="fas fa-history"></i> View History
                    </a>
                    <a href="ia_lipa.php?id=<?= $id ?>" class="ia-hero-btn">
                        <i class="fas fa-seedling"></i> LIPA Records
                    </a>
                    <?php if (hasPermission('delete_ia_profile')): ?>
                    <button class="ia-hero-btn danger" id="btnDeleteProfile" data-id="<?= $id ?>" data-name="<?= htmlspecialchars($profile['ia_name']) ?>">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section class="content" style="padding-top:1.5rem;">
            <div class="container-fluid">

                <!-- ── QUICK STATS ── -->
                <div class="stats-row">
                    <div class="stat-card">
                        <span class="stat-number"><?= number_format($profile['service_area_ha'] ?? 0, 1) ?></span>
                        <div class="stat-label">Service Area (ha)</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= number_format($profile['fusa_ha'] ?? 0, 1) ?></span>
                        <div class="stat-label">FUSA (ha)</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= intval($profile['actual_ia_members'] ?? 0) ?></span>
                        <div class="stat-label">IA Members</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= intval($profile['farmer_beneficiaries'] ?? 0) ?></span>
                        <div class="stat-label">Farmer Beneficiaries</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= intval($profile['tsags_count'] ?? 0) ?></span>
                        <div class="stat-label">TSAGs</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= number_format($profile['canal_length_km'] ?? 0, 2) ?></span>
                        <div class="stat-label">Canal Length (km)</div>
                    </div>
                </div>

                <div class="row">
                    <!-- ── LEFT COLUMN ── -->
                    <div class="col-lg-6">

                        <!-- Basic Info -->
                        <div class="info-card">
                            <div class="ic-header">
                                <h4><i class="fas fa-info-circle mr-2"></i>Basic Information</h4>
                            </div>
                            <div class="ic-body">
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-tag"></i>IA Name</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['ia_name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-water"></i>Name of CIS</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['cis_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-barcode"></i>IA Code</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['ia_code'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-dot-circle"></i>Status</div>
                                    <div class="ir-value">
                                        <span class="badge badge-<?= $isOperational ? 'success' : 'danger' ?>">
                                            <?= ucfirst($profile['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($profile['date_organized'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-calendar-plus"></i>Date Organized</div>
                                    <div class="ir-value"><?= date('F j, Y', strtotime($profile['date_organized'])) ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-user-tie"></i>President</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['president_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-phone"></i>Contact Number</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['contact_number'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <?php if (!empty($profile['imo'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-building"></i>IMO</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['imo']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($assigned && $assigned['full_name']): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-user-check"></i>Assigned IDU</div>
                                    <div class="ir-value">
                                        <span class="assigned-pill">
                                            <i class="fas fa-user-check"></i>
                                            <?= htmlspecialchars(trim($assigned['full_name'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['mailing_address'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-envelope"></i>Mailing Address</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['mailing_address']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Area & Membership -->
                        <div class="info-card">
                            <div class="ic-header">
                                <h4><i class="fas fa-chart-area mr-2"></i>Area & Membership</h4>
                            </div>
                            <div class="ic-body">
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-ruler-combined"></i>Service Area</div>
                                    <div class="ir-value"><strong><?= number_format($profile['service_area_ha'] ?? 0, 2) ?></strong> ha</div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-tint"></i>FUSA</div>
                                    <div class="ir-value"><strong><?= number_format($profile['fusa_ha'] ?? 0, 2) ?></strong> ha</div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-road"></i>Canal Length</div>
                                    <div class="ir-value"><strong><?= number_format($profile['canal_length_km'] ?? 0, 3) ?></strong> km</div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-users"></i>Farmer Beneficiaries</div>
                                    <div class="ir-value"><?= intval($profile['farmer_beneficiaries']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-user-friends"></i>Actual IA Members</div>
                                    <div class="ir-value"><?= intval($profile['actual_ia_members']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-layer-group"></i>TSAGs Count</div>
                                    <div class="ir-value"><?= intval($profile['tsags_count']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-mars"></i>Male Members</div>
                                    <div class="ir-value"><?= intval($profile['male_members']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-venus"></i>Female Members</div>
                                    <div class="ir-value"><?= intval($profile['female_members']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── RIGHT COLUMN ── -->
                    <div class="col-lg-6">

                        <!-- Location & Legal -->
                        <div class="info-card">
                            <div class="ic-header">
                                <h4><i class="fas fa-map-marker-alt mr-2"></i>Location & Legal</h4>
                            </div>
                            <div class="ic-body">
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-globe-asia"></i>Region</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['region'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-map"></i>Province</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['province'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-landmark"></i>Congressional District</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['congressional_district'] ?? '') ?: '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <?php if (!empty($profile['sec_registration_number'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-file-contract"></i>SEC Reg. No.</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['sec_registration_number']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['sec_registration_date'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-calendar-check"></i>SEC Reg. Date</div>
                                    <div class="ir-value"><?= date('F j, Y', strtotime($profile['sec_registration_date'])) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['ia_tin'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-id-badge"></i>TIN</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['ia_tin']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['existing_contract'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-handshake"></i>Existing Contract</div>
                                    <div class="ir-value"><?= htmlspecialchars($profile['existing_contract']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['contract_effectivity_date'])): ?>
                                <div class="info-row">
                                    <div class="ir-label"><i class="fas fa-calendar-alt"></i>Contract Effectivity</div>
                                    <div class="ir-value"><?= date('F j, Y', strtotime($profile['contract_effectivity_date'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Officers -->
                        <div class="info-card">
                            <div class="ic-header">
                                <h4><i class="fas fa-users-cog mr-2"></i>IA Officers</h4>
                                <?php if (hasPermission('add_ia_officer')): ?>
                                <button type="button" class="ia-hero-btn" style="padding:5px 12px;font-size:12px;" data-toggle="modal" data-target="#addOfficerModal">
                                    <i class="fas fa-plus"></i> Add Officer
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="ic-body" style="padding:.75rem;">
                                <?php if (!empty($officers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover officers-table" style="margin-bottom:0;">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                                <th style="width:80px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($officers as $o): ?>
                                            <tr>
                                                <td class="font-weight-bold"><?= htmlspecialchars($o['officer_name']) ?></td>
                                                <td><span class="badge badge-info"><?= htmlspecialchars($o['position']) ?></span></td>
                                                <td><?= htmlspecialchars($o['contact_number']) ?: '<span class="text-muted">—</span>' ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $o['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $o['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display:flex;gap:4px;">
                                                        <?php if (hasPermission('delete_ia_officer')): ?>
                                                        <button class="btn-officer-action btn-officer-delete delete-officer" data-id="<?= $o['id'] ?>" data-name="<?= htmlspecialchars($o['officer_name']) ?>" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x d-block mb-2"></i>
                                    No officers recorded yet.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /.col-lg-6 -->
                </div><!-- /.row -->
            </div>
        </section>
    </div><!-- /.content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /.wrapper -->


<!-- ── ADD OFFICER MODAL ── -->
<div class="modal fade" id="addOfficerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-hd-ia">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user-plus mr-2"></i>Add New IA Officer
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="addOfficerForm">
                <input type="hidden" name="ia_profile_id" value="<?= $id ?>">
                <div class="modal-body" style="padding:1.25rem;">
                    <div class="form-group">
                        <label>Officer Name <span style="color:#dc3545;">*</span></label>
                        <input type="text" class="form-control" id="officer_name" name="officer_name" placeholder="Full name" required>
                    </div>
                    <div class="form-group">
                        <label>Position <span style="color:#dc3545;">*</span></label>
                        <input type="text" class="form-control" id="position" name="position" placeholder="e.g. President" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" placeholder="09XX-XXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="officer@email.com">
                    </div>
                    <div class="form-group mb-0">
                        <label>Status</label>
                        <select class="form-control" id="is_active" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-color:var(--surface-border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Save Officer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>
<script>
$(document).ready(function () {

    // Add Officer
    $('#addOfficerForm').on('submit', function (e) {
        e.preventDefault();
        $.post('../includes/ia_officers_ajax.php', $(this).serialize() + '&action=add', function (res) {
            if (res.success) {
                $('#addOfficerModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Officer Added!', text: 'The officer has been saved.', timer: 1800, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not add officer.' });
            }
        }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' }));
    });

    // Delete Officer
    $(document).on('click', '.delete-officer', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name') || 'this officer';
        Swal.fire({
            title: 'Remove Officer?',
            html: `Delete <strong>${name}</strong> from this IA Profile?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Remove',
            reverseButtons: true
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post('../includes/ia_officers_ajax.php', { action: 'delete', id: id }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Removed!', timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            }, 'json');
        });
    });

    // Delete Profile (from hero)
    $('#btnDeleteProfile').on('click', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Delete IA Profile?',
            html: `Permanently delete <strong>${name}</strong>? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            preConfirm: () => new Promise(resolve => {
                $.post('../includes/ia_profiles_ajax.php', { action: 'delete', id: id }, resolve, 'json');
            }),
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (!result.isConfirmed) return;
            if (result.value?.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1500, showConfirmButton: false })
                    .then(() => window.location.href = 'ia_profiles.php');
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.value?.message || 'Could not delete.' });
            }
        });
    });
});
</script>
</body>
</html>