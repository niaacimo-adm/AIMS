<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!hasPermission('manage_settings')) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($_SESSION['csrf_announcements'])) {
    $_SESSION['csrf_announcements'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_announcements'];

$database = new Database();
$db = $database->getConnection();

const ANN_CATEGORIES = ['advisory', 'maintenance', 'holiday', 'policy', 'general'];
const ANN_LOGIN_LIMIT = 10;   // keep in sync with LIMIT in includes/ajax_announcements.php

function ann_reply(bool $ok, string $msg = '', array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

/** '' => null, valid => 'Y-m-d H:i:s', invalid => false */
function ann_parse_dt(string $v) {
    $v = trim($v);
    if ($v === '') return null;
    $t = strtotime($v);
    return $t === false ? false : date('Y-m-d H:i:s', $t);
}

/* ═══════════════ AJAX actions (POST) ═══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        ann_reply(false, 'Session expired. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'save') {
            $title    = trim($_POST['title'] ?? '');
            $body     = trim($_POST['body'] ?? '');
            $category = $_POST['category'] ?? 'general';
            $pinned   = !empty($_POST['is_pinned']) ? 1 : 0;
            $active   = !empty($_POST['is_active']) ? 1 : 0;
            $pub      = ann_parse_dt($_POST['publish_at'] ?? '');
            $exp      = ann_parse_dt($_POST['expires_at'] ?? '');

            if ($title === '' || $body === '')            ann_reply(false, 'Title and message are required.');
            if (mb_strlen($title) > 150)                  ann_reply(false, 'Title must be 150 characters or fewer.');
            if (mb_strlen($body) > 2000)                  ann_reply(false, 'Message must be 2,000 characters or fewer.');
            if (!in_array($category, ANN_CATEGORIES, true)) ann_reply(false, 'Invalid category.');
            if ($pub === false || $exp === false)         ann_reply(false, 'Invalid date.');
            if ($pub !== null && $exp !== null && $exp <= $pub) ann_reply(false, 'Expiry must be after the start date.');

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE announcements
                    SET title=?, body=?, category=?, is_pinned=?, is_active=?, publish_at=?, expires_at=?
                    WHERE id=?");
                $stmt->bind_param('sssiissi', $title, $body, $category, $pinned, $active, $pub, $exp, $id);
            } else {
                $stmt = $db->prepare("INSERT INTO announcements
                    (title, body, category, is_pinned, is_active, publish_at, expires_at)
                    VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('sssiiss', $title, $body, $category, $pinned, $active, $pub, $exp);
            }
            $stmt->execute();
            ann_reply(true, $id > 0 ? 'Announcement updated.' : 'Announcement posted.');
        }

        if ($id <= 0) ann_reply(false, 'Invalid announcement.');

        if ($action === 'toggle_active') {
            $stmt = $db->prepare("UPDATE announcements SET is_active = 1 - is_active WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            ann_reply(true, 'Visibility updated.');
        }
        if ($action === 'toggle_pin') {
            $stmt = $db->prepare("UPDATE announcements SET is_pinned = 1 - is_pinned WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            ann_reply(true, 'Pin updated.');
        }
        if ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM announcements WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            ann_reply(true, 'Announcement deleted.');
        }

        ann_reply(false, 'Unknown action.');
    } catch (Throwable $e) {
        http_response_code(500);
        ann_reply(false, 'Something went wrong. Please try again.');
    }
}

/* ═══════════════ Page data ═══════════════ */
$rows = $db->query("
    SELECT id, title, body, category, is_pinned, is_active, publish_at, expires_at, created_at,
           CASE WHEN is_active = 0 THEN 'hidden'
                WHEN publish_at IS NOT NULL AND publish_at > NOW() THEN 'scheduled'
                WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 'expired'
                ELSE 'live' END AS status
    FROM announcements
    ORDER BY is_pinned DESC, COALESCE(publish_at, created_at) DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$status_label = ['live' => 'Live', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'hidden' => 'Hidden'];
$cat_color = [
    'advisory'    => '#f59e0b',
    'maintenance' => '#06b6d4',
    'holiday'     => '#ec4899',
    'policy'      => '#8b5cf6',
    'general'     => '#2a9863',
];

$counts = ['live' => 0, 'scheduled' => 0, 'expired' => 0, 'hidden' => 0];
foreach ($rows as $r) { $counts[$r['status']]++; }
$total = count($rows);

function ann_fmt($d) { return $d ? date('M j, Y g:i A', strtotime($d)) : '—'; }
function ann_ts($d)  { return $d ? (int)strtotime($d) : 0; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements — NIA ACIMO</title>
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <?php include '../includes/header.php'; ?>
    <style>
        /* ── Tokens (same palette as Leave Types) ── */
        :root {
            --primary:#2a9863; --primary-dark:#1e7a4f; --primary-light:#d4f5e5;
            --danger:#c92a2a; --warning:#f59f00; --info:#1c7ed6;
            --muted:#6c757d; --border:#dee2e6;
            --card-shadow:0 2px 12px rgba(0,0,0,.08); --radius:10px;
            /* kept for the sidebar if header include isn't providing them */
            --sidebar-bg:#0b2a1c; --sidebar-text:#d6f5e6; --sidebar-active-bg:#14855a;
            --sidebar-active-text:#fff; --sidebar-hover-bg:rgba(36,231,143,.12); --sidebar-brand-bg:#0f7a4e;
        }
        .content-wrapper { background:#f4f6f9; }

        /* ── Page header ── */
        .an-page-header {
            background:linear-gradient(135deg,#1a6b42 0%,#2a9863 60%,#3dbf7e 100%);
            border-radius:var(--radius); color:#fff; padding:24px 28px; margin-bottom:24px;
            display:flex; align-items:center; justify-content:space-between; gap:16px;
            box-shadow:0 4px 18px rgba(42,152,99,.35);
        }
        .an-page-header h4 { margin:0; font-size:1.3rem; font-weight:700; letter-spacing:-.2px; }
        .an-page-header p  { margin:4px 0 0; opacity:.85; font-size:.88rem; }
        .an-page-header .header-icon {
            width:56px; height:56px; background:rgba(255,255,255,.15); border-radius:50%;
            display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;
        }

        /* ── Stat cards ── */
        .an-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:22px; }
        .an-stat-card {
            flex:1; min-width:150px; background:#fff; border-radius:var(--radius);
            padding:16px 18px; box-shadow:var(--card-shadow);
            display:flex; align-items:center; gap:14px;
        }
        .an-stat-icon {
            width:42px; height:42px; border-radius:10px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; font-size:1.1rem;
        }
        .an-stat-icon.blue   { background:#d0ebff; color:var(--info); }
        .an-stat-icon.green  { background:var(--primary-light); color:var(--primary); }
        .an-stat-icon.yellow { background:#fff3bf; color:#7c5a00; }
        .an-stat-icon.gray   { background:#e9ecef; color:#495057; }
        .an-stat-value { font-size:1.5rem; font-weight:700; line-height:1; color:#212529; }
        .an-stat-label { font-size:.78rem; color:var(--muted); margin-top:2px; }

        /* ── Info note ── */
        .an-note {
            background:linear-gradient(135deg,#ebfbee 0%,#d3f9d8 100%);
            border:1.5px solid #b2f2bb; border-radius:var(--radius);
            padding:14px 20px; margin-bottom:22px;
            display:flex; align-items:center; gap:14px;
        }
        .an-note .note-icon {
            width:40px; height:40px; background:var(--primary); border-radius:10px;
            display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0;
        }
        .an-note h6 { margin:0 0 2px; font-size:.92rem; font-weight:700; color:#1a4d2e; }
        .an-note p  { margin:0; font-size:.82rem; color:#2d7a50; }

        /* ── Buttons ── */
        .btn-an {
            display:inline-flex; align-items:center; gap:7px; padding:8px 18px;
            border:none; border-radius:8px; cursor:pointer;
            font-size:.88rem; font-weight:600; transition:all .18s;
        }
        .btn-an-primary { background:var(--primary); color:#fff; }
        .btn-an-primary:hover { background:var(--primary-dark); color:#fff; }
        .btn-an-secondary { background:#e9ecef; color:#495057; }
        .btn-an-secondary:hover { background:#dee2e6; }
        .btn-an:disabled { opacity:.6; cursor:not-allowed; }

        /* ── Toolbar (status filter chips + add button) ── */
        .an-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px; }
        .an-chip {
            border:1.5px solid var(--border); background:#fff; color:#495057;
            border-radius:20px; padding:6px 14px; font-size:.82rem; font-weight:600;
            cursor:pointer; transition:all .15s;
        }
        .an-chip:hover { border-color:var(--primary); color:var(--primary); }
        .an-chip.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .an-chip .n {
            display:inline-block; margin-left:5px; min-width:18px; padding:0 6px; border-radius:10px;
            background:rgba(0,0,0,.08); font-size:.72rem; line-height:18px; text-align:center;
        }
        .an-chip.active .n { background:rgba(255,255,255,.25); }

        /* ── Table card ── */
        .an-card { background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); overflow:hidden; }
        .an-card-header {
            padding:16px 22px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
            font-weight:700; color:#343a40; font-size:.95rem;
        }
        .an-card-body { padding:16px 22px 12px; }

        /* DataTables skin */
        #annTable { width:100% !important; border-collapse:collapse; }
        #annTable thead th {
            background:#f8f9fa; padding:12px 16px; font-size:.78rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.5px; color:var(--muted);
            border-bottom:2px solid var(--border) !important; border-top:none; white-space:nowrap;
        }
        #annTable tbody td { padding:13px 16px; font-size:.88rem; color:#343a40; vertical-align:middle; border-top:1px solid #f1f3f5; }
        #annTable tbody tr:hover { background:#f8fff9; }
        table.dataTable thead .sorting:before, table.dataTable thead .sorting:after,
        table.dataTable thead .sorting_asc:before, table.dataTable thead .sorting_asc:after,
        table.dataTable thead .sorting_desc:before, table.dataTable thead .sorting_desc:after { opacity:.35; }
        table.dataTable thead .sorting_asc:before, table.dataTable thead .sorting_desc:after { opacity:1; color:var(--primary); }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border:1.5px solid var(--border); border-radius:8px; padding:6px 12px; outline:none; font-size:.88rem;
            transition:border-color .2s, box-shadow .2s;
        }
        .dataTables_wrapper .dataTables_filter input { min-width:240px; }
        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(42,152,99,.12); }
        .dataTables_wrapper .dataTables_info { font-size:.8rem; color:var(--muted); padding-top:14px; }
        .dataTables_wrapper .dataTables_paginate { padding-top:8px; }
        .page-item.active .page-link { background:var(--primary); border-color:var(--primary); }
        .page-link { color:var(--primary); }
        .page-link:hover { color:var(--primary-dark); }
        .dataTables_wrapper .row { margin-left:0; margin-right:0; align-items:center; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom:12px; }

        /* ── Cells ── */
        .an-title { font-weight:700; color:#212529; display:flex; align-items:center; gap:6px; }
        .an-title .fa-thumbtack { color:var(--warning); font-size:.78rem; transform:rotate(20deg); }
        .an-excerpt {
            color:#6c757d; font-size:.82rem; max-width:420px; margin-top:2px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .an-badge {
            display:inline-block; border-radius:20px; padding:3px 12px;
            font-size:.75rem; font-weight:700; white-space:nowrap;
        }
        .an-live      { background:#d3f9d8; color:#2b7a3a; }
        .an-scheduled { background:#d0ebff; color:#1864ab; }
        .an-expired   { background:#e9ecef; color:#495057; }
        .an-hidden    { background:#fff3bf; color:#7c5a00; }
        .an-cat {
            --c:#2a9863; display:inline-flex; align-items:center; gap:7px;
            border-radius:20px; padding:3px 12px 3px 10px; font-size:.75rem; font-weight:700;
            background:color-mix(in srgb, var(--c) 14%, #fff);
            color:color-mix(in srgb, var(--c) 60%, #000);
        }
        .an-cat::before { content:''; width:8px; height:8px; border-radius:50%; background:var(--c); }
        .an-date { font-size:.82rem; color:#495057; white-space:nowrap; }
        .an-date.none { color:#adb5bd; }

        /* ── Row action buttons ── */
        .tbl-actions { display:flex; gap:6px; flex-wrap:nowrap; }
        .tbl-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:4px;
            min-width:32px; height:30px; padding:0 10px; border:none; border-radius:6px;
            font-size:.78rem; font-weight:600; cursor:pointer; transition:all .15s;
        }
        .tbl-btn-edit   { background:#d0ebff; color:var(--info); }
        .tbl-btn-edit:hover { background:#a5d8ff; }
        .tbl-btn-pin    { background:#f1f3f5; color:#868e96; }
        .tbl-btn-pin.on { background:#fff3bf; color:#e67700; }
        .tbl-btn-pin:hover { opacity:.8; }
        .tbl-btn-vis    { background:#fff3bf; color:#7c5a00; }
        .tbl-btn-vis.off { background:#d3f9d8; color:#2b7a3a; }
        .tbl-btn-vis:hover { opacity:.8; }
        .tbl-btn-delete { background:#ffe3e3; color:var(--danger); }
        .tbl-btn-delete:hover { background:#ffc9c9; }

        /* ── Modal ── */
        .an-modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
            z-index:9999; align-items:center; justify-content:center; padding:16px;
        }
        .an-modal-overlay.open { display:flex; }
        .an-modal {
            background:#fff; border-radius:14px; width:100%; max-width:600px; max-height:calc(100vh - 32px);
            display:flex; flex-direction:column; overflow:hidden;
            box-shadow:0 20px 60px rgba(0,0,0,.2); animation:anModalIn .22s ease;
        }
        @keyframes anModalIn { from{opacity:0;transform:translateY(-18px) scale(.97);} to{opacity:1;transform:translateY(0) scale(1);} }
        .an-modal-header {
            background:linear-gradient(135deg,#1a6b42,#2a9863); color:#fff;
            padding:18px 24px; display:flex; align-items:center; gap:12px; flex-shrink:0;
        }
        .an-modal-header h5 { margin:0; font-size:1.05rem; font-weight:700; }
        .an-modal-header .modal-close {
            margin-left:auto; background:rgba(255,255,255,.2); border:none; border-radius:50%;
            width:30px; height:30px; display:flex; align-items:center; justify-content:center;
            color:#fff; cursor:pointer; font-size:1.1rem; line-height:1; transition:background .15s;
        }
        .an-modal-header .modal-close:hover { background:rgba(255,255,255,.35); }
        .an-modal-body { padding:24px; overflow-y:auto; }
        .an-modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; flex-shrink:0; }

        .an-form-group { margin-bottom:18px; }
        .an-form-group > label {
            display:block; font-size:.82rem; font-weight:700; color:#495057; margin-bottom:6px;
            text-transform:uppercase; letter-spacing:.3px;
        }
        .an-form-group .required { color:var(--danger); margin-left:2px; }
        .an-form-group input[type="text"], .an-form-group input[type="datetime-local"],
        .an-form-group textarea, .an-form-group select {
            width:100%; padding:10px 14px; border:1.5px solid var(--border); border-radius:8px;
            font-size:.9rem; color:#343a40; outline:none; background:#fff;
            transition:border-color .2s, box-shadow .2s;
        }
        .an-form-group input:focus, .an-form-group textarea:focus, .an-form-group select:focus {
            border-color:var(--primary); box-shadow:0 0 0 3px rgba(42,152,99,.12);
        }
        .an-form-group textarea { resize:vertical; min-height:110px; }
        .an-form-row { display:flex; gap:14px; }
        .an-form-row .an-form-group { flex:1; min-width:0; }
        .an-helper { font-size:.78rem; color:var(--muted); margin-top:5px; display:flex; justify-content:space-between; gap:8px; }

        .an-switch-group {
            display:flex; align-items:center; gap:12px; padding:10px 14px;
            background:#f8f9fa; border-radius:8px; border:1.5px solid var(--border);
        }
        .an-switch { position:relative; display:inline-block; width:42px; height:24px; margin:0; flex-shrink:0; }
        .an-switch input { opacity:0; width:0; height:0; }
        .an-switch-slider { position:absolute; inset:0; background:#ced4da; border-radius:24px; cursor:pointer; transition:.25s; }
        .an-switch-slider::before {
            content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px;
            background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 4px rgba(0,0,0,.2);
        }
        .an-switch input:checked + .an-switch-slider { background:var(--primary); }
        .an-switch input:checked + .an-switch-slider::before { transform:translateX(18px); }
        .an-switch-label { font-size:.88rem; font-weight:600; color:#495057; }

        /* ── Toast ── */
        #anToast {
            position:fixed; bottom:24px; right:24px; padding:14px 22px; border-radius:10px;
            font-size:.88rem; font-weight:600; color:#fff; z-index:99999;
            transform:translateY(80px); opacity:0; transition:all .3s; pointer-events:none;
            box-shadow:0 4px 20px rgba(0,0,0,.2); max-width:320px;
        }
        #anToast.show { transform:translateY(0); opacity:1; }
        #anToast.success { background:var(--primary); }
        #anToast.error   { background:var(--danger); }

        @media (max-width:640px) {
            .an-form-row { flex-direction:column; gap:0; }
            .an-page-header { flex-direction:column; align-items:flex-start; }
            .an-stats .an-stat-card { min-width:100%; }
            .dataTables_wrapper .dataTables_filter input { min-width:0; width:100%; }
        }
    </style>
    <!-- jQuery must load before the sidebar include (it uses $(document).ready) -->
    <script src="../plugins/jquery/jquery.min.js"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">

        <section class="content">
            <div class="container-fluid">

                <!-- Page header -->
                <div class="an-page-header">
                    <div>
                        <h4><i class="fas fa-bullhorn" style="margin-right:10px;"></i>Announcements Management</h4>
                    </div>
                    <div class="header-icon"><i class="fas fa-bell"></i></div>
                </div>
                <div class="an-toolbar">
                    <button type="button" class="an-chip active" data-status="">All<span class="n"><?= $total ?></span></button>
                    <button type="button" class="an-chip" data-status="Live">Live<span class="n"><?= $counts['live'] ?></span></button>
                    <button type="button" class="an-chip" data-status="Scheduled">Scheduled<span class="n"><?= $counts['scheduled'] ?></span></button>
                    <button type="button" class="an-chip" data-status="Hidden">Hidden<span class="n"><?= $counts['hidden'] ?></span></button>
                    <button type="button" class="an-chip" data-status="Expired">Expired<span class="n"><?= $counts['expired'] ?></span></button>
                    <div style="margin-left:auto;">
                        <button type="button" class="btn-an btn-an-primary" id="btnNew"><i class="fas fa-plus"></i> New Announcement</button>
                    </div>
                </div>

                <!-- Table card -->
                <div class="an-card">
                    <div class="an-card-header">
                        <span><i class="fas fa-list" style="margin-right:8px;color:var(--primary);"></i>Announcements</span>
                    </div>
                    <div class="an-card-body">
                        <table id="annTable" class="table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Announcement</th>
                                    <th>Category</th>
                                    <th>Starts</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r):
                                $color = $cat_color[$r['category']] ?? $cat_color['general'];
                                $payload = [
                                    'id' => (int)$r['id'], 'title' => $r['title'], 'body' => $r['body'],
                                    'category' => $r['category'], 'is_pinned' => (int)$r['is_pinned'], 'is_active' => (int)$r['is_active'],
                                    'publish_at' => $r['publish_at'] ? date('Y-m-d\TH:i', strtotime($r['publish_at'])) : '',
                                    'expires_at' => $r['expires_at'] ? date('Y-m-d\TH:i', strtotime($r['expires_at'])) : '',
                                ];
                            ?>
                                <tr data-id="<?= (int)$r['id'] ?>" data-row="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>">
                                    <td><span class="an-badge an-<?= $r['status'] ?>"><?= $status_label[$r['status']] ?></span></td>
                                    <td data-order="<?= htmlspecialchars(strtolower($r['title']), ENT_QUOTES) ?>"
                                        data-search="<?= htmlspecialchars($r['title'] . ' ' . $r['body'], ENT_QUOTES) ?>">
                                        <div class="an-title">
                                            <?php if ($r['is_pinned']): ?><i class="fas fa-thumbtack" title="Pinned"></i><?php endif; ?>
                                            <?= htmlspecialchars($r['title']) ?>
                                        </div>
                                        <div class="an-excerpt"><?= htmlspecialchars($r['body']) ?></div>
                                    </td>
                                    <td><span class="an-cat" style="--c:<?= $color ?>"><?= htmlspecialchars(ucfirst($r['category'])) ?></span></td>
                                    <td data-order="<?= ann_ts($r['publish_at']) ?>"><span class="an-date<?= $r['publish_at'] ? '' : ' none' ?>"><?= ann_fmt($r['publish_at']) ?></span></td>
                                    <td data-order="<?= ann_ts($r['expires_at']) ?>"><span class="an-date<?= $r['expires_at'] ? '' : ' none' ?>"><?= ann_fmt($r['expires_at']) ?></span></td>
                                    <td>
                                        <div class="tbl-actions">
                                            <button type="button" class="tbl-btn tbl-btn-edit js-edit" title="Edit"><i class="fas fa-edit"></i> Edit</button>
                                            <button type="button" class="tbl-btn tbl-btn-pin js-pin <?= $r['is_pinned'] ? 'on' : '' ?>" title="<?= $r['is_pinned'] ? 'Unpin' : 'Pin to top' ?>"><i class="fas fa-thumbtack"></i></button>
                                            <button type="button" class="tbl-btn tbl-btn-vis js-toggle <?= $r['is_active'] ? '' : 'off' ?>" title="<?= $r['is_active'] ? 'Hide from login page' : 'Show on login page' ?>"><i class="fas <?= $r['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button>
                                            <button type="button" class="tbl-btn tbl-btn-delete js-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>
    </div>
</div>

<!-- Add / edit modal -->
<div class="an-modal-overlay" id="annModal" aria-hidden="true">
    <form class="an-modal" id="annForm" autocomplete="off" role="dialog" aria-modal="true" aria-labelledby="annModalTitle">
        <div class="an-modal-header">
            <i class="fas fa-bullhorn"></i>
            <h5 id="annModalTitle">New Announcement</h5>
            <button type="button" class="modal-close" id="btnClose" aria-label="Close">&times;</button>
        </div>
        <div class="an-modal-body">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="an-form-group">
                <label for="f_title">Title <span class="required">*</span></label>
                <input type="text" id="f_title" name="title" maxlength="150" placeholder="e.g. Scheduled system maintenance" required>
            </div>

            <div class="an-form-group">
                <label for="f_body">Message <span class="required">*</span></label>
                <textarea id="f_body" name="body" rows="5" maxlength="2000" placeholder="What should staff know?" required></textarea>
                <div class="an-helper"><span>Shown as a 2-line preview; the full text opens when clicked.</span><span><span id="bodyCount">0</span> / 2000</span></div>
            </div>

            <div class="an-form-row">
                <div class="an-form-group">
                    <label for="f_category">Category</label>
                    <select id="f_category" name="category">
                        <?php foreach (ANN_CATEGORIES as $c): ?>
                            <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="an-form-group">
                    <label for="f_publish">Show from <small style="text-transform:none;font-weight:500;">(optional)</small></label>
                    <input type="datetime-local" id="f_publish" name="publish_at">
                </div>
                <div class="an-form-group">
                    <label for="f_expires">Hide after <small style="text-transform:none;font-weight:500;">(optional)</small></label>
                    <input type="datetime-local" id="f_expires" name="expires_at">
                </div>
            </div>

            <div class="an-form-row">
                <div class="an-form-group">
                    <label>Pinned</label>
                    <div class="an-switch-group">
                        <label class="an-switch">
                            <input type="checkbox" id="f_pinned" name="is_pinned" value="1">
                            <span class="an-switch-slider"></span>
                        </label>
                        <span class="an-switch-label" id="pinLabel">Not pinned</span>
                    </div>
                </div>
                <div class="an-form-group">
                    <label>Visibility</label>
                    <div class="an-switch-group">
                        <label class="an-switch">
                            <input type="checkbox" id="f_active" name="is_active" value="1" checked>
                            <span class="an-switch-slider"></span>
                        </label>
                        <span class="an-switch-label" id="activeLabel">Visible</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="an-modal-footer">
            <button type="button" class="btn-an btn-an-secondary" id="btnCancel">Cancel</button>
            <button type="submit" class="btn-an btn-an-primary" id="btnSave"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>

<!-- Toast -->
<div id="anToast" role="status" aria-live="polite"></div>

  <?php include '../includes/mainfooter.php'; ?>
<?php include '../includes/footer.php'; ?>

<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function ($) {
    var CSRF = <?= json_encode($csrf) ?>;
    var URL_ = 'announcements.php';
    var STATUS_COL = 0;

    /* ── DataTable ───────────────────────── */
    var table = $('#annTable').DataTable({
        order: [],                       // keep server order: pinned first, then newest
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, searchable: false, targets: 5 },
            { searchable: false, targets: [3, 4] }
        ],
        dom: "<'row'<'col-sm-6'l><'col-sm-6 text-sm-right'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            search: '',
            searchPlaceholder: 'Search announcements…',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_–_END_ of _TOTAL_ announcement(s)',
            infoEmpty: 'No announcements',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No announcements yet. Click “New Announcement” to post one.',
            zeroRecords: 'No matching announcements found.'
        }
    });

    /* status chips */
    $('.an-chip').on('click', function () {
        $('.an-chip').removeClass('active');
        $(this).addClass('active');
        var s = $(this).data('status');
        table.column(STATUS_COL).search(s ? '^' + s + '$' : '', true, false).draw();
    });

    /* ── Helpers ─────────────────────────── */
    function toast(msg, type) {
        var $t = $('#anToast');
        $t.text(msg).attr('class', 'show ' + (type || 'success'));
        clearTimeout(window._anToastTimer);
        window._anToastTimer = setTimeout(function () { $t.attr('class', ''); }, 3200);
    }
    function post(data) {
        data.csrf = CSRF;
        return $.ajax({ url: URL_, type: 'POST', data: data, dataType: 'json' });
    }
    function done(res) {
        if (res && res.success) {
            toast(res.message, 'success');
            setTimeout(function () { location.reload(); }, 800);
        } else {
            toast((res && res.message) || 'Please try again.', 'error');
        }
    }
    function fail(xhr) {
        toast((xhr.responseJSON && xhr.responseJSON.message) || 'Network error. Please try again.', 'error');
    }
    function rowData(el) { return $(el).closest('tr').data('row'); }

    /* ── Modal ───────────────────────────── */
    function syncLabels() {
        $('#pinLabel').text($('#f_pinned').is(':checked') ? 'Pinned to top' : 'Not pinned');
        $('#activeLabel').text($('#f_active').is(':checked') ? 'Visible' : 'Hidden');
    }
    function openModal(d) {
        d = d || { id:0, title:'', body:'', category:'general', is_pinned:0, is_active:1, publish_at:'', expires_at:'' };
        $('#annModalTitle').text(d.id ? 'Edit Announcement' : 'New Announcement');
        $('#btnSave').html('<i class="fas fa-save"></i> ' + (d.id ? 'Update' : 'Save'));
        $('#f_id').val(d.id);
        $('#f_title').val(d.title);
        $('#f_body').val(d.body);
        $('#f_category').val(d.category);
        $('#f_publish').val(d.publish_at);
        $('#f_expires').val(d.expires_at);
        $('#f_pinned').prop('checked', !!+d.is_pinned);
        $('#f_active').prop('checked', !!+d.is_active);
        $('#bodyCount').text($('#f_body').val().length);
        syncLabels();
        $('#annModal').addClass('open').attr('aria-hidden', 'false');
        setTimeout(function () { $('#f_title').trigger('focus'); }, 80);
    }
    function closeModal() { $('#annModal').removeClass('open').attr('aria-hidden', 'true'); }

    $('#btnNew').on('click', function () { openModal(); });
    $('#annTable').on('click', '.js-edit', function () { openModal(rowData(this)); });
    $('#btnClose, #btnCancel').on('click', closeModal);
    $('#annModal').on('mousedown', function (e) { if (e.target === this) closeModal(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    $('#f_body').on('input', function () { $('#bodyCount').text(this.value.length); });
    $('#f_pinned, #f_active').on('change', syncLabels);

    $('#annForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#btnSave').prop('disabled', true);
        post({
            action: 'save',
            id: $('#f_id').val(),
            title: $('#f_title').val(),
            body: $('#f_body').val(),
            category: $('#f_category').val(),
            publish_at: $('#f_publish').val(),
            expires_at: $('#f_expires').val(),
            is_pinned: $('#f_pinned').is(':checked') ? 1 : 0,
            is_active: $('#f_active').is(':checked') ? 1 : 0
        }).done(function (r) {
            if (r && r.success) closeModal();
            done(r);
        }).fail(fail).always(function () { $btn.prop('disabled', false); });
    });

    /* ── Row actions ─────────────────────── */
    $('#annTable').on('click', '.js-pin', function () {
        post({ action:'toggle_pin', id: rowData(this).id }).done(done).fail(fail);
    });
    $('#annTable').on('click', '.js-toggle', function () {
        post({ action:'toggle_active', id: rowData(this).id }).done(done).fail(fail);
    });
    $('#annTable').on('click', '.js-delete', function () {
        var d = rowData(this);
        Swal.fire({
            icon:'warning', title:'Delete this announcement?',
            html:'<p style="font-size:.88rem;color:#495057;">&ldquo;' + $('<span>').text(d.title).html() + '&rdquo; will be removed from the login page <strong>permanently</strong>.<br>To keep it for later, use <em>Hide</em> instead.</p>',
            showCancelButton:true, confirmButtonColor:'#c92a2a', cancelButtonColor:'#6c757d',
            confirmButtonText:'<i class="fas fa-trash-alt"></i> Delete'
        }).then(function (r) {
            if (r.isConfirmed) post({ action:'delete', id:d.id }).done(done).fail(fail);
        });
    });
})(jQuery);
</script>
</body>
</html>