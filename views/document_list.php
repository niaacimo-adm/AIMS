<?php
ob_start();
date_default_timezone_set('Asia/Manila');
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$kind = isset($_GET['kind']) && in_array($_GET['kind'], ['incoming','outgoing','internal']) ? $_GET['kind'] : '';
$page_title = $kind ? ucfirst($kind) . ' Documents' : 'All Documents';

// Logged-in user info
$logged_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

$user_info = [];
if ($logged_emp_id) {
    $ustmt = $db->prepare("
        SELECT e.emp_id,
               CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name,
               s.section_id, s.section_name, s.section_code,
               us.unit_id, us.unit_name, us.unit_code,
               u.role_id AS user_role_id
        FROM employee e
        LEFT JOIN section      s  ON e.section_id      = s.section_id
        LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
        LEFT JOIN users        u  ON u.employee_id     = e.emp_id
        WHERE e.emp_id = ?
        LIMIT 1
    ");
    $ustmt->bind_param("i", $logged_emp_id);
    $ustmt->execute();
    $user_info = $ustmt->get_result()->fetch_assoc() ?? [];
}

// Check if current user is Masteradmin (role_id = 1 via users table)
$isMasteradmin = false;
if ($logged_emp_id) {
    $maStmt = $db->prepare("
        SELECT 1 FROM users u
        JOIN user_roles ur ON u.role_id = ur.id
        WHERE u.employee_id = ? AND ur.id = 1
        LIMIT 1
    ");
    if ($maStmt) {
        $maStmt->bind_param("i", $logged_emp_id);
        $maStmt->execute();
        $isMasteradmin = $maStmt->get_result()->num_rows > 0;
    }
    if (!$isMasteradmin) {
        $session_user_id = (int)($_SESSION['user_id'] ?? 0);
        if ($session_user_id) {
            $maFb = $db->prepare("
                SELECT 1 FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
                WHERE u.id = ? AND ur.id = 1
                LIMIT 1
            ");
            if ($maFb) {
                $maFb->bind_param("i", $session_user_id);
                $maFb->execute();
                $isMasteradmin = $maFb->get_result()->num_rows > 0;
            }
        }
    }
}

// Pending delete requests count (for admin badge)
$pendingDeleteCount = 0;
if ($isMasteradmin) {
    $pdRes = $db->query("SELECT COUNT(*) AS cnt FROM document_delete_requests WHERE status = 'pending'");
    if ($pdRes) { $pendingDeleteCount = (int)($pdRes->fetch_assoc()['cnt'] ?? 0); }
}

// Pending delete request status for docs the current user created
$myPendingRequests = [];
if ($logged_emp_id) {
    $prRes = $db->prepare("
        SELECT document_id, status
        FROM document_delete_requests
        WHERE requested_by = ? AND status IN ('pending','rejected')
        ORDER BY created_at DESC
    ");
    if ($prRes) {
        $prRes->bind_param("i", $logged_emp_id);
        $prRes->execute();
        foreach ($prRes->get_result()->fetch_all(MYSQLI_ASSOC) as $pr) {
            if (!isset($myPendingRequests[$pr['document_id']])) {
                $myPendingRequests[$pr['document_id']] = $pr['status'];
            }
        }
    }
}

$section_code = $user_info['section_code'] ?? 'IMO';
$date_part    = date('mdY');
$prefix       = $section_code . '-' . $date_part . '-';

$seq_res  = $db->prepare("SELECT document_number FROM document_records WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
$like_val = $prefix . '%';
$seq_res->bind_param("s", $like_val);
$seq_res->execute();
$last_row   = $seq_res->get_result()->fetch_assoc();
$next_seq   = 1;
if ($last_row) {
    $parts    = explode('-', $last_row['document_number']);
    $last_num = intval(end($parts));
    $next_seq = $last_num + 1;
}
$auto_doc_number = $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);

// Fetch lists for dropdowns
$doc_types_res = $db->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$doc_types_arr = [];
if ($doc_types_res) {
    while ($t = $doc_types_res->fetch_assoc()) {
        $doc_types_arr[] = $t;
    }
}

$all_sections = $db->query("SELECT section_id AS id, section_name, section_code FROM section ORDER BY section_name");
$sections_arr = [];
if ($all_sections) {
    while ($sr = $all_sections->fetch_assoc()) { $sections_arr[] = $sr; }
}

// Build document list query
$where     = "WHERE 1=1";
$params    = [];
$types_str = '';
if ($kind) {
    $where    .= " AND dr.kind = ?";
    $params[]  = $kind;
    $types_str = 's';
}

$query = "
    SELECT dr.*,
           dt.type_name,
           CONCAT(TRIM(fbe.first_name),' ',TRIM(fbe.last_name)) AS forwarded_by_name,
           s1.section_name AS from_section,
           s2.section_name AS to_section,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit
    FROM document_records dr
    LEFT JOIN document_types dt  ON dr.document_type_id          = dt.id
    LEFT JOIN employee       fbe ON dr.forwarded_by_emp_id       = fbe.emp_id
    LEFT JOIN section        s1  ON dr.from_section_id           = s1.section_id
    LEFT JOIN section        s2  ON dr.forwarded_to_section_id   = s2.section_id
    LEFT JOIN unit_section   us1 ON dr.from_unit_id              = us1.unit_id
    LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id      = us2.unit_id
    $where
    ORDER BY dr.date_forwarded DESC
";

$stmt = $db->prepare($query);
if ($params) { $stmt->bind_param($types_str, ...$params); }
$stmt->execute();
$documents = $stmt->get_result();

// Fetch unit_sections for dropdowns
$units_res = $db->query("SELECT us.unit_id AS id, us.unit_name, us.unit_code, us.section_id FROM unit_section us ORDER BY us.unit_name");
$units_arr = [];
if ($units_res) {
    while ($ur = $units_res->fetch_assoc()) { $units_arr[] = $ur; }
}

// Fetch main IMO office
$imo_office = null;
$imo_res = $db->query("SELECT office_id, office_name FROM office WHERE is_main_office = 1 ORDER BY office_id ASC LIMIT 1");
if ($imo_res && $imo_res->num_rows > 0) {
    $imo_office = $imo_res->fetch_assoc();
} else {
    $imo_fb = $db->query("SELECT office_id, office_name FROM office ORDER BY office_id ASC LIMIT 1");
    if ($imo_fb) $imo_office = $imo_fb->fetch_assoc();
}
$imo_office_id   = $imo_office['office_id']   ?? 0;
$imo_office_name = $imo_office['office_name'] ?? 'IMO Office';

// Fetch section list for forward modal
$sec_list_res = $db->query("SELECT section_id, section_name, section_code FROM section ORDER BY section_name");
$sec_list_arr = [];
if ($sec_list_res) {
    while ($sl = $sec_list_res->fetch_assoc()) { $sec_list_arr[] = $sl; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ── Design tokens ───────────────────────────────────────────────────── */
        :root {
            --doc-primary:   #1a3c5e;
            --doc-primary-light: #e8f0f8;
            --doc-incoming:  #2563eb;
            --doc-outgoing:  #16a34a;
            --doc-internal:  #7c3aed;
            --doc-surface:   #ffffff;
            --doc-border:    #e2e8f0;
            --doc-text:      #1e293b;
            --doc-muted:     #64748b;
            --doc-hover:     #f8fafc;
            --doc-stripe:    #f9fafb;
            --radius-sm:     6px;
            --radius-md:     10px;
            --radius-lg:     14px;
            --shadow-card:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
            --shadow-btn:    0 1px 2px rgba(0,0,0,.08);
        }

        /* ── Kind badges ─────────────────────────────────────────────────────── */
        .kind-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            border: 1px solid transparent;
        }
        .kind-incoming { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
        .kind-outgoing { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
        .kind-internal { background:#ede9fe; color:#6d28d9; border-color:#ddd6fe; }

        /* ── Status badges ───────────────────────────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: .7rem; font-weight: 700;
            white-space: nowrap;
        }
        .status-badge::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; flex-shrink: 0;
        }
        .status-pending   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .status-pending::before   { background:#f97316; }
        .status-received  { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .status-received::before  { background:#3b82f6; }
        .status-returned  { background:#fdf2f8; color:#9d174d; border:1px solid #fbcfe8; }
        .status-returned::before  { background:#ec4899; }
        .status-completed { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
        .status-completed::before { background:#22c55e; }
        .status-archived  { background:#f8fafc; color:#475569; border:1px solid #e2e8f0; }
        .status-archived::before  { background:#94a3b8; }

        /* ── Filter pills ────────────────────────────────────────────────────── */
        .filter-bar {
            display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--radius-lg);
            padding: 10px 16px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-card);
        }
        .filter-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px; border-radius: 8px;
            font-size: .8rem; font-weight: 600;
            cursor: pointer; text-decoration: none;
            color: var(--doc-muted);
            border: 1.5px solid transparent;
            background: transparent;
            transition: all .15s ease;
            letter-spacing: .01em;
        }
        .filter-pill:hover { background: var(--doc-hover); color: var(--doc-primary); text-decoration: none; }
        .filter-pill.active-all      { background: var(--doc-primary); color: #fff; box-shadow: 0 2px 8px rgba(26,60,94,.25); }
        .filter-pill.active-incoming { background: #2563eb; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.25); }
        .filter-pill.active-outgoing { background: #16a34a; color: #fff; box-shadow: 0 2px 8px rgba(22,163,74,.25); }
        .filter-pill.active-internal { background: #7c3aed; color: #fff; box-shadow: 0 2px 8px rgba(124,58,237,.25); }

        /* ── Action toolbar buttons ──────────────────────────────────────────── */
        .toolbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: var(--radius-sm);
            font-size: .8rem; font-weight: 600;
            border: 1.5px solid; cursor: pointer;
            transition: all .15s ease;
            box-shadow: var(--shadow-btn);
            white-space: nowrap;
            text-decoration: none;
        }
        .toolbar-btn:hover { filter: brightness(.93); transform: translateY(-1px); }
        .toolbar-btn-export  { background:#fff; color:#475569; border-color:#cbd5e1; }
        .toolbar-btn-export:hover { color:#1e293b; }
        .toolbar-btn-print   { background:#fff; color:#dc2626; border-color:#fca5a5; }
        .toolbar-btn-print:hover { background:#fef2f2; }
        .toolbar-btn-delete  { background:#fff; color:#374151; border-color:#d1d5db; }
        .toolbar-btn-delete:hover { background:#f9fafb; }
        .toolbar-btn-add     { background: var(--doc-primary); color:#fff; border-color: var(--doc-primary); }
        .toolbar-btn-add:hover { background:#153251; }

        /* ── Main table card ─────────────────────────────────────────────────── */
        .doc-table-card {
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        /* ── Table ───────────────────────────────────────────────────────────── */
        #documentsTable {
            font-size: .83rem;
            color: var(--doc-text);
            border-collapse: collapse;
            width: 100%;
            margin: 0;
        }
        /* Use !important + double selector to beat AdminLTE specificity in both light & dark */
        table#documentsTable thead tr,
        table#documentsTable > thead > tr {
            background-color: #1a3c5e !important;
        }
        table#documentsTable thead th,
        table#documentsTable > thead > tr > th {
            color: #ffffff !important;
            background-color: #1a3c5e !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .07em !important;
            padding: 13px 14px !important;
            border: none !important;
            border-bottom: none !important;
            white-space: nowrap;
        }
        table#documentsTable thead th.sorting,
        table#documentsTable thead th.sorting_asc,
        table#documentsTable thead th.sorting_desc {
            background-color: #1a3c5e !important;
        }
        #documentsTable thead th.sorting::after,
        #documentsTable thead th.sorting_asc::after,
        #documentsTable thead th.sorting_desc::after { color: rgba(255,255,255,.55) !important; }

        #documentsTable tbody tr {
            border-bottom: 1px solid var(--doc-border);
            transition: background .12s ease;
        }
        #documentsTable tbody tr:last-child { border-bottom: none; }
        #documentsTable tbody tr:nth-child(even) { background: var(--doc-stripe); }
        #documentsTable tbody tr:hover { background: var(--doc-primary-light) !important; }
        #documentsTable tbody td {
            padding: 11px 14px;
            border: none !important;
            vertical-align: middle;
        }

        /* ── Doc number cell ─────────────────────────────────────────────────── */
        .doc-number-cell {
            font-family: 'Courier New', monospace;
            font-size: .76rem;
            font-weight: 700;
            color: var(--doc-primary);
            background: var(--doc-primary-light);
            padding: 3px 8px;
            border-radius: 5px;
            display: inline-block;
            letter-spacing: .02em;
            border: 1px solid #c7d9ec;
            white-space: nowrap;
        }

        /* ── Person cell ─────────────────────────────────────────────────────── */
        .person-name { font-weight: 600; color: var(--doc-text); font-size: .83rem; }
        .cell-meta   { font-size: .72rem; color: var(--doc-muted); margin-top: 2px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .cell-meta i { font-size: .63rem; }

        /* ── ID cell ─────────────────────────────────────────────────────────── */
        .doc-id-cell {
            font-weight: 700; color: var(--doc-muted);
            font-size: .78rem;
        }

        /* ── Action buttons ──────────────────────────────────────────────────── */
        .action-btn {
            width: 30px; height: 30px; padding: 0;
            border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            border: none; cursor: pointer;
            font-size: .78rem;
            transition: all .15s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,.1);
        }
        .action-btn:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,.18); }
        .action-btn-view    { background: #0ea5e9; color: #fff; }
        .action-btn-edit    { background: #f59e0b; color: #fff; }
        .action-btn-forward { background: #10b981; color: #fff; }
        .action-btn-delete  { background: #ef4444; color: #fff; }
        .action-btn-pending { background: #9ca3af; color: #fff; cursor: not-allowed; }
        .action-btn-pending:hover { transform: none; box-shadow: 0 1px 2px rgba(0,0,0,.1); }
        .actions-cell { display: flex; gap: 5px; align-items: center; flex-wrap: nowrap; }

        /* ── Remarks cell ────────────────────────────────────────────────────── */
        .remarks-cell {
            max-width: 130px; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
            color: var(--doc-muted); font-size: .8rem;
            font-style: italic;
        }

        /* ── DataTables overrides ────────────────────────────────────────────── */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            padding: 14px 16px 10px;
        }
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            font-size: .82rem; color: var(--doc-muted); font-weight: 500; margin-bottom: 0;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1.5px solid var(--doc-border);
            border-radius: var(--radius-sm);
            padding: 3px 8px; font-size: .82rem;
            color: var(--doc-text);
            box-shadow: none;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1.5px solid var(--doc-border);
            border-radius: var(--radius-sm);
            padding: 5px 12px; font-size: .82rem;
            color: var(--doc-text);
            transition: border-color .15s;
            box-shadow: none;
            margin-left: 6px;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--doc-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26,60,94,.1);
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 16px;
            font-size: .8rem;
            color: var(--doc-muted);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: var(--radius-sm) !important;
            border: 1.5px solid var(--doc-border) !important;
            font-size: .78rem !important;
            padding: 4px 10px !important;
            margin: 0 2px !important;
            color: var(--doc-text) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--doc-primary) !important;
            border-color: var(--doc-primary) !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: var(--doc-hover) !important;
            color: var(--doc-primary) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: #cbd5e1 !important;
            border-color: #f1f5f9 !important;
        }

        /* ── Pending badge on Delete Requests button ─────────────────────────── */
        .pending-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 18px; height: 18px; border-radius: 50%;
            background: #ef4444; color: #fff;
            font-size: .6rem; font-weight: 800;
            margin-left: 4px;
        }

        /* ── Kind option (modal) ─────────────────────────────────────────────── */
        .kind-option { text-align:center;padding:12px 8px;border-radius:10px;border:2px solid #dee2e6;cursor:pointer;font-size:.82rem;font-weight:600;color:#495057;transition:all .15s;background:#f8f9fa; }
        .kind-option:hover { border-color:#adb5bd; }
        .kind-radio:checked + .kind-opt-incoming { border-color:#2563eb;background:#dbeafe;color:#1d4ed8; }
        .kind-radio:checked + .kind-opt-outgoing { border-color:#16a34a;background:#dcfce7;color:#15803d; }
        .kind-radio:checked + .kind-opt-internal { border-color:#7c3aed;background:#ede9fe;color:#6d28d9; }

        /* ── Dark mode ───────────────────────────────────────────────────────── */
        body.dark-mode {
            --doc-surface: var(--card-bg, #1e2535);
            --doc-border: var(--card-border, #2d3748);
            --doc-hover: rgba(255,255,255,.04);
            --doc-stripe: rgba(255,255,255,.02);
            --doc-text: var(--text-primary, #e2e8f0);
            --doc-muted: #94a3b8;
            --doc-primary-light: rgba(26,60,94,.35);
        }
        body.dark-mode .kind-incoming { background:#1e3a5f;color:#93c5fd;border-color:#1e40af; }
        body.dark-mode .kind-outgoing { background:#14532d;color:#86efac;border-color:#166534; }
        body.dark-mode .kind-internal { background:#2e1065;color:#c4b5fd;border-color:#4c1d95; }
        body.dark-mode .status-pending   { background:#431407;color:#fdba74;border-color:#7c2d12; }
        body.dark-mode .status-received  { background:#1e3a5f;color:#93c5fd;border-color:#1e40af; }
        body.dark-mode .status-completed { background:#064e3b;color:#6ee7b7;border-color:#065f46; }
        body.dark-mode .status-archived  { background:#1e293b;color:#94a3b8;border-color:#334155; }
        body.dark-mode .filter-bar { background:var(--card-bg,#1e2535);border-color:var(--card-border,#2d3748); }
        body.dark-mode .filter-pill { color:#94a3b8; }
        body.dark-mode .filter-pill:hover { background:rgba(255,255,255,.06);color:#e2e8f0; }
        body.dark-mode .doc-number-cell { background:rgba(26,60,94,.4);color:#7aabdf;border-color:#2d4a6a; }
        body.dark-mode .doc-table-card { background:var(--card-bg,#1e2535);border-color:var(--card-border,#2d3748); }
        body.dark-mode #documentsTable tbody tr { border-color:var(--card-border,#2d3748); }
        body.dark-mode .toolbar-btn-export,
        body.dark-mode .toolbar-btn-print,
        body.dark-mode .toolbar-btn-delete { background:var(--card-bg,#1e2535);color:#cbd5e1;border-color:var(--card-border,#2d3748); }
        body.dark-mode .kind-option { background:var(--input-bg);color:var(--text-primary);border-color:var(--input-border); }
        body.dark-mode .page-section-title { color:#7aabdf;border-color:#7aabdf; }
        body.dark-mode .dataTables_wrapper .dataTables_filter input,
        body.dark-mode .dataTables_wrapper .dataTables_length select {
            background: var(--input-bg, #2d3748); color: var(--text-primary, #e2e8f0);
            border-color: var(--input-border, #4a5568);
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.4rem;font-weight:700;color:var(--doc-primary);">
                            <?php if ($kind==='incoming'):  ?><i class="fas fa-inbox mr-2" style="color:var(--doc-incoming);"></i>
                            <?php elseif ($kind==='outgoing'): ?><i class="fas fa-paper-plane mr-2" style="color:var(--doc-outgoing);"></i>
                            <?php elseif ($kind==='internal'): ?><i class="fas fa-exchange-alt mr-2" style="color:var(--doc-internal);"></i>
                            <?php else: ?><i class="fas fa-file-alt mr-2"></i><?php endif; ?>
                            <?= htmlspecialchars($page_title) ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($page_title) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="filter-bar">
                    <!-- Kind filters -->
                    <a href="document_list.php"               class="filter-pill <?= !$kind ? 'active-all' : '' ?>"><i class="fas fa-folder-open"></i> All</a>
                    <a href="document_list.php?kind=incoming" class="filter-pill <?= $kind==='incoming' ? 'active-incoming' : '' ?>"><i class="fas fa-inbox"></i> Incoming</a>
                    <a href="document_list.php?kind=outgoing" class="filter-pill <?= $kind==='outgoing' ? 'active-outgoing' : '' ?>"><i class="fas fa-paper-plane"></i> Outgoing</a>
                    <a href="document_list.php?kind=internal" class="filter-pill <?= $kind==='internal' ? 'active-internal' : '' ?>"><i class="fas fa-exchange-alt"></i> Internal</a>

                    <!-- Divider -->
                    <span style="width:1px;height:24px;background:#e2e8f0;margin:0 4px;flex-shrink:0;"></span>

                    <!-- Toolbar actions -->
                    <div class="ml-auto d-flex flex-wrap" style="gap:6px;align-items:center;">
                        <button class="toolbar-btn toolbar-btn-export" onclick="exportTableToCSV()">
                            <i class="fas fa-file-csv"></i> Export
                        </button>
                        <button class="toolbar-btn toolbar-btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <?php if ($isMasteradmin): ?>
                        <button class="toolbar-btn toolbar-btn-delete" onclick="openAdminDeletePanel()">
                            <i class="fas fa-trash-alt"></i> Delete Requests
                            <?php if ($pendingDeleteCount > 0): ?>
                            <span class="pending-badge"><?= $pendingDeleteCount ?></span>
                            <?php endif; ?>
                        </button>
                        <?php endif; ?>
                        <button class="toolbar-btn toolbar-btn-add" data-toggle="modal" data-target="#addDocumentModal">
                            <i class="fas fa-plus"></i> Add Document
                        </button>
                    </div>
                </div>

                <div class="doc-table-card">
                        <div class="table-responsive">
                            <table id="documentsTable" class="table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">ID</th>
                                        <th style="min-width:155px;">Document No.</th>
                                        <th style="min-width:220px;">Document Name / Particulars</th>
                                        <th style="width:115px;">Doc Type</th>
                                        <th style="width:95px;">Kind</th>
                                        <th style="min-width:165px;">Forwarded By / Section</th>
                                        <th style="min-width:190px;">Forwarded To / Date &amp; Time</th>
                                        <th style="width:105px;">Status</th>
                                        <th style="min-width:120px;">Remarks</th>
                                        <th style="width:140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($documents && $documents->num_rows > 0):
                                    while ($doc = $documents->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="doc-id-cell">#<?= $doc['id'] ?></span></td>
                                        <td><span class="doc-number-cell"><?= htmlspecialchars($doc['document_number']) ?></span></td>
                                        <td>
                                            <div style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;" title="<?= htmlspecialchars($doc['document_name']) ?>">
                                                <?= htmlspecialchars($doc['document_name']) ?>
                                            </div>
                                        </td>
                                        <td style="color:var(--doc-muted);font-size:.8rem;"><?= htmlspecialchars($doc['type_name'] ?? '—') ?></td>
                                        <td><span class="kind-badge kind-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span></td>
                                        <td>
                                            <div class="person-name"><?= htmlspecialchars($doc['forwarded_by_name'] ?? '—') ?></div>
                                            <?php if (!empty($doc['from_section'])): ?>
                                            <div class="cell-meta"><i class="fas fa-building"></i><?= htmlspecialchars($doc['from_section']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['from_unit'])): ?>
                                            <div class="cell-meta"><i class="fas fa-layer-group"></i><?= htmlspecialchars($doc['from_unit']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight:500;font-size:.83rem;"><?= htmlspecialchars($doc['forwarded_to'] ?? '—') ?></div>
                                            <?php if (!empty($doc['to_section'])): ?>
                                            <div class="cell-meta"><i class="fas fa-building"></i><?= htmlspecialchars($doc['to_section']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['to_unit'])): ?>
                                            <div class="cell-meta"><i class="fas fa-layer-group"></i><?= htmlspecialchars($doc['to_unit']) ?></div>
                                            <?php endif; ?>
                                            <?php
                                            $fwd_ts = strtotime($doc['date_forwarded']);
                                            if ($fwd_ts && $fwd_ts > 0): ?>
                                            <div class="cell-meta"><i class="fas fa-clock"></i><?= date('M d, Y h:i A', $fwd_ts) ?></div>
                                            <?php else: ?>
                                            <div class="cell-meta">—</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-badge status-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span></td>
                                        <td>
                                            <span class="remarks-cell" title="<?= htmlspecialchars($doc['remarks'] ?? '') ?>">
                                                <?= htmlspecialchars($doc['remarks'] ?: '—') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <a href="document_view.php?id=<?= $doc['id'] ?>" class="action-btn action-btn-view" title="View Document"><i class="fas fa-eye"></i></a>
                                                <button class="action-btn action-btn-edit"    title="Edit Document"   onclick="editDocument(<?= $doc['id'] ?>)"><i class="fas fa-pencil-alt"></i></button>
                                                <button class="action-btn action-btn-forward" title="Forward Document" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-share"></i></button>
                                                <?php
                                                $isOwner = ((int)($doc['created_by_emp_id'] ?? 0) === $logged_emp_id);
                                                $delReqStatus = $myPendingRequests[$doc['id']] ?? null;
                                                if ($isOwner):
                                                    if ($delReqStatus === 'pending'): ?>
                                                <button class="action-btn action-btn-pending" title="Delete request pending approval" disabled><i class="fas fa-clock"></i></button>
                                                <?php elseif ($delReqStatus === 'rejected'): ?>
                                                <button class="action-btn action-btn-delete" title="Previous request rejected — re-request deletion"
                                                        onclick="openDeleteRequestModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-trash"></i></button>
                                                <?php else: ?>
                                                <button class="action-btn action-btn-delete" title="Request deletion"
                                                        onclick="openDeleteRequestModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-trash"></i></button>
                                                <?php endif; endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                </div><!-- /.doc-table-card -->

            </div><!-- /.container-fluid -->
        </div><!-- /.content -->
    </div><!-- /.content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /.wrapper -->

<!-- ══════════════ MODALS (outside .wrapper for correct Bootstrap z-index stacking) ══════════════ -->

<div class="modal fade" id="deleteRequestModal" tabindex="-1" role="dialog" aria-labelledby="deleteRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#dc3545;color:#fff;">
                <h5 class="modal-title" id="deleteRequestModalLabel"><i class="fas fa-trash-alt mr-2"></i>Request Document Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteReqDocId">
                <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle mr-1"></i> You are requesting deletion of document <strong id="deleteReqDocNum"></strong>. This requires <strong>Masteradmin approval</strong> before the document is permanently removed.</div>
                <div class="form-group mb-0"><label class="font-weight-bold">Reason for deletion <span class="text-danger">*</span></label><textarea class="form-control" id="deleteReqReason" rows="3" placeholder="Please explain why this document should be deleted..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="submitDeleteRequestBtn"><i class="fas fa-paper-plane mr-1"></i> Submit Request</button></div>
        </div>
    </div>
</div>

<?php if ($isMasteradmin): ?>
<div class="modal fade" id="adminDeleteModal" tabindex="-1" role="dialog" aria-labelledby="adminDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:#fff;"><h5 class="modal-title" id="adminDeleteModalLabel"><i class="fas fa-trash-alt mr-2"></i>Document Delete Requests<?php if ($pendingDeleteCount > 0): ?><span class="badge badge-danger ml-1"><?= $pendingDeleteCount ?> Pending</span><?php endif; ?></h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body p-0"><div class="p-3 border-bottom d-flex align-items-center" style="gap:8px;"><label class="mb-0 font-weight-bold mr-2">Filter:</label><select id="adminDeleteFilterSelect" class="form-control form-control-sm" style="width:140px;" onchange="loadDeleteRequests(this.value)"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All</option></select><button class="btn btn-sm btn-outline-secondary ml-auto" onclick="loadDeleteRequests($('#adminDeleteFilterSelect').val())"><i class="fas fa-sync-alt mr-1"></i> Refresh</button></div><div class="table-responsive" style="max-height:500px;overflow-y:auto;"><table class="table table-bordered table-hover table-sm mb-0" style="font-size:.83rem;"><thead class="thead-light" style="position:sticky;top:0;z-index:1;"><tr><th style="width:90px;">Status</th><th style="min-width:180px;">Document</th><th style="width:90px;">Kind</th><th style="min-width:130px;">Requested By</th><th style="min-width:200px;">Reason</th><th style="min-width:180px;">Action</th></tr></thead><tbody id="adminDeleteTableBody"><tr><td colspan="6" class="text-center py-4 text-muted">Loading...</td></tr></tbody></table></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="modal fade" id="addDocumentModal" tabindex="-1" role="dialog" aria-labelledby="addDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:#fff;">
                <h5 class="modal-title" id="addDocumentModalLabel"><i class="fas fa-plus-circle mr-2"></i>Add Document Record</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <!-- Kind selector -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Kind of Document <span class="text-danger">*</span></label>
                            <div class="d-flex" style="gap:8px;">
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="incoming" required style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-incoming"><i class="fas fa-inbox fa-lg mb-1"></i><br>Incoming</div>
                                </label>
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="outgoing" style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-outgoing"><i class="fas fa-paper-plane fa-lg mb-1"></i><br>Outgoing</div>
                                </label>
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="internal" style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-internal"><i class="fas fa-exchange-alt fa-lg mb-1"></i><br>Internal</div>
                                </label>
                            </div>
                        </div>

                        <!-- Document Number -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="document_number" id="addDocNumber" value="<?= htmlspecialchars($auto_doc_number) ?>" required>
                                <div class="input-group-append">
                                    <span class="input-group-text" style="font-size:.72rem;color:#6c757d;" title="Auto-generated"><i class="fas fa-magic"></i></span>
                                </div>
                            </div>
                            <small class="text-muted">Format: SECTION-MMDDYYYY-SEQ</small>
                        </div>

                        <!-- Document Type -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="document_type_id" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($doc_types_arr as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Document Name -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Document Name / Particulars <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="document_name" rows="2" required></textarea>
                        </div>

                        <!-- Remarks -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveDocumentBtn">
                    <i class="fas fa-save mr-1"></i> Save Document
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT DOCUMENT MODAL -->
<div class="modal fade" id="editDocumentModal" tabindex="-1" role="dialog" aria-labelledby="editDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#856404;color:#fff;">
                <h5 class="modal-title" id="editDocumentModalLabel"><i class="fas fa-pencil-alt mr-2"></i>Edit Document — <span id="editDocModalNum"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="updateDocumentBtn" style="display:none;">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FORWARD MODAL -->
<div class="modal fade" id="forwardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#198754;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-share mr-2"></i>Forward Document</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="forwardDocumentForm">
                    <input type="hidden" name="action"         value="forward">
                    <input type="hidden" name="id"             id="fwdDocId">
                    <input type="hidden" name="forward_to"     id="forwardDestType" value="section">
                    <!-- IMO office_id auto-injected from server — no user selection needed -->
                    <input type="hidden" name="fwd_to_office_id" id="fwdHiddenOfficeId" value="<?= $imo_office_id ?>">

                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:8px 13px;margin-bottom:14px;font-size:.83rem;">
                        <i class="fas fa-file-alt mr-1" style="color:#0284c7;"></i>
                        Forwarding: <strong id="fwdDocNumber" class="text-primary"></strong>
                    </div>

                    <!-- Forwarded By (read-only) -->
                    <div class="form-group">
                        <label class="font-weight-bold">Forwarded By</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['full_name'] ?? '') ?>" readonly>
                        <small class="text-muted"><?= htmlspecialchars($user_info['section_name'] ?? '') ?><?= !empty($user_info['unit_name']) ? ' &middot; ' . htmlspecialchars($user_info['unit_name']) : '' ?></small>
                    </div>

                    <!-- Destination Type toggle -->
                    <div class="form-group">
                        <label class="font-weight-bold">Destination Type</label>
                        <div class="d-flex" style="gap:8px;">
                            <label style="flex:1;cursor:pointer;margin:0;" id="fwdTypeSectionLabel">
                                <input type="radio" name="_fwd_dest_type" value="section" checked style="display:none;" class="fwd-type-radio">
                                <div class="fwd-dest-btn text-center py-2 rounded border" id="fwdBtnSection" style="font-size:.82rem;font-weight:600;border-color:#198754!important;background:#d1fae5;color:#065f46;">
                                    <i class="fas fa-building mr-1"></i> Section / Unit
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;margin:0;" id="fwdTypeImoLabel">
                                <input type="radio" name="_fwd_dest_type" value="imo" style="display:none;" class="fwd-type-radio">
                                <div class="fwd-dest-btn text-center py-2 rounded border" id="fwdBtnImo" style="font-size:.82rem;font-weight:600;border-color:#dee2e6;background:#f8f9fa;color:#495057;">
                                    <i class="fas fa-star mr-1"></i> IMO Office
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Section / Unit group -->
                    <div id="fwdSectionGroup">
                        <div class="form-group">
                            <label class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select class="form-control" name="fwd_to_section_id" id="fwdToSectionSelect">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sec_list_arr as $sl): ?>
                                <option value="<?= $sl['section_id'] ?>"><?= htmlspecialchars($sl['section_name']) ?> (<?= htmlspecialchars($sl['section_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="fwdUnitGroup" style="display:none;">
                            <label class="font-weight-bold">Unit <small class="text-muted font-weight-normal">(optional)</small></label>
                            <select class="form-control" name="fwd_to_unit_id" id="fwdToUnitSelect">
                                <option value="">-- Entire Section --</option>
                            </select>
                        </div>
                    </div>

                    <!-- IMO Office group — no dropdown, just confirmation info -->
                    <div id="fwdImoGroup" style="display:none;">
                        <div class="alert alert-info border-0" style="background:#eff6ff;border-radius:10px;font-size:.84rem;">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-star text-primary mr-2" style="font-size:1.1rem;"></i>
                                <strong><?= htmlspecialchars($imo_office_name) ?></strong>
                            </div>
                            <div class="text-muted" style="font-size:.79rem;">
                                <i class="fas fa-info-circle mr-1 text-info"></i>
                                This document will be automatically routed to the IMO office manager or designated focal person.
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="fwd_date" id="fwdDate">

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Remarks</label>
                        <textarea class="form-control" name="fwd_remarks" rows="2" placeholder="Reason or notes for forwarding..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmForwardBtn">
                    <i class="fas fa-share mr-1"></i> Forward Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// FIX: doc types and sections now come from PHP arrays (no iterator / data_seek needed)
const sectionsArr  = <?= json_encode($sections_arr,  JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const unitsArr     = <?= json_encode($units_arr,     JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const docTypesArr  = <?= json_encode($doc_types_arr, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const statusOpts   = ['pending','received','returned','completed','archived'];

$(document).ready(function() {

    // Destroy any existing DataTable instance first to prevent duplicate init
    if ($.fn.DataTable.isDataTable('#documentsTable')) {
        $('#documentsTable').DataTable().destroy();
    }

    $('#documentsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [0, 9] },
            { searchable: false, targets: [0] }
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: '',
            searchPlaceholder: 'Search documents...',
            lengthMenu: 'Show _MENU_ entries',
            zeroRecords: '<div class="text-center py-4" style="color:#9ca3af;"><i class="fas fa-search fa-2x d-block mb-2" style="opacity:.4;"></i><div style="font-size:.92rem;font-weight:600;color:#6b7280;">No matching documents</div></div>',
            emptyTable: '<div class="text-center py-4" style="color:#9ca3af;"><i class="fas fa-folder-open fa-2x d-block mb-2" style="opacity:.4;"></i><div style="font-size:.92rem;font-weight:600;color:#6b7280;">No documents found</div><div style="font-size:.8rem;margin-top:3px;">Try a different filter or add a new document.</div></div>'
        },
        rowCallback: function(row, data, displayIndex) {
            $('td:first', row).html('<span class="doc-id-cell">' + (displayIndex + 1) + '</span>');
        }
    });

    // ── Select2 ─────────────────────────────────────────────────────────────
    $('.select2').select2({ theme: 'bootstrap4', dropdownParent: $('body') });

    // ── Save Document (Add) ──────────────────────────────────────────────────
    $('#saveDocumentBtn').on('click', function() {
        const form = $('#addDocumentForm');
        if (!form.find('input[name="kind"]:checked').val()) {
            Swal.fire({ icon: 'warning', title: 'Kind Required', text: 'Please select the kind of document.' }); return;
        }
        if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.post('document_actions.php', form.serialize(), function(r) {
            if (r.success) {
                $('#addDocumentModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Document Saved!',
                    text: 'The document record has been saved successfully.',
                    timer: 1800,
                    showConfirmButton: false,
                    timerProgressBar: true
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Failed to Save', text: r.message || 'An error occurred while saving.' });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected server error occurred. Check console for details.' });
            console.error(xhr.responseText);
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Document'));
    });

    // ── Kind option visual feedback ─────────────────────────────────────────
    $(document).on('change', '.kind-radio', function() {
        const val = $(this).val();
        $(this).closest('.d-flex').find('.kind-option').css({border: '2px solid #dee2e6', background: '#f8f9fa', color: '#495057'});
        const colors = { incoming: ['#0d6efd','#dbeafe','#1d4ed8'], outgoing: ['#198754','#dcfce7','#166534'], internal: ['#6f42c1','#ede9fe','#5b21b6'] };
        if (colors[val]) {
            $(this).next('.kind-option').css({ borderColor: colors[val][0], background: colors[val][1], color: colors[val][2] });
        }
    });

    // ── FORWARD MODAL: toggle Section/Unit vs IMO Office ─────────────────────
    function setForwardDestType(type) {
        if (type === 'section') {
            $('#fwdSectionGroup').show();
            $('#fwdImoGroup').hide();
            $('#fwdBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
            $('#fwdBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('section');
        } else {
            $('#fwdSectionGroup').hide();
            $('#fwdImoGroup').show();
            $('#fwdBtnImo').css({ borderColor: '#0d6efd', background: '#dbeafe', color: '#1d4ed8' });
            $('#fwdBtnSection').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('imo');
        }
    }

    $(document).on('change', 'input[name="_fwd_dest_type"]', function() {
        setForwardDestType($(this).val());
    });
    $(document).on('click', '.fwd-dest-btn', function() {
        var $radio = $(this).closest('label').find('input[type="radio"]');
        $radio.prop('checked', true).trigger('change');
    });

    // ── Submit Forward ────────────────────────────────────────────────────────
    $('#confirmForwardBtn').on('click', function() {
        const destType = $('input[name="_fwd_dest_type"]:checked').val();
        const secId    = $('#fwdToSectionSelect').val();

        if (destType === 'section' && !secId) {
            Swal.fire({ icon: 'warning', title: 'Section Required', text: 'Please select a destination section.' });
            return;
        }

        // For section: disable the hidden office field so it doesn't get serialized
        // For IMO: disable section/unit fields so they don't interfere
        if (destType === 'section') {
            $('#fwdToSectionSelect').prop('disabled', false);
            $('#fwdToUnitSelect').prop('disabled', false);
            $('#fwdHiddenOfficeId').prop('disabled', true);
        } else {
            // IMO path — office_id is the hidden input; disable section fields
            $('#fwdToSectionSelect').prop('disabled', true);
            $('#fwdToUnitSelect').prop('disabled', true);
            $('#fwdHiddenOfficeId').prop('disabled', false);
        }

        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Forwarding...');

        $.post('document_actions.php', $('#forwardDocumentForm').serialize(), function(r) {
            if (r.success) {
                let msgHtml = 'Document forwarded to <strong>' + (r.destination || 'destination') + '</strong>.';
                if (r.focal_person) msgHtml += '<br><small class="text-muted">Assigned to: ' + r.focal_person + '</small>';
                $('#forwardModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Document Forwarded!',
                    html: msgHtml,
                    timer: 2000,
                    showConfirmButton: false,
                    timerProgressBar: true
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Forward Failed', text: r.message || 'Unable to forward the document.' });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected error occurred. Check console.' });
            console.error(xhr.responseText);
        }).always(() => {
            // Re-enable all fields after request
            $('#fwdToSectionSelect, #fwdToUnitSelect').prop('disabled', false);
            $('#fwdHiddenOfficeId').prop('disabled', false);
            $btn.prop('disabled', false).html('<i class="fas fa-share mr-1"></i> Forward Document');
        });
    });

    // ── Section change → load units via AJAX ─────────────────────────────────
    $(document).on('change', '#fwdToSectionSelect', function() {
        const secId = $(this).val();
        $('#fwdToUnitSelect').html('<option value="">-- Entire Section --</option>');
        if (!secId) { $('#fwdUnitGroup').hide(); return; }

        $.get('document_actions.php', { action: 'get_units', section_id: secId }, function(r) {
            if (r.success && r.units.length) {
                r.units.forEach(u => {
                    $('#fwdToUnitSelect').append($('<option>').val(u.id).text(u.unit_name + ' (' + u.unit_code + ')'));
                });
                $('#fwdUnitGroup').show();
            } else {
                $('#fwdUnitGroup').hide();
            }
        }, 'json');
    });

    // ── Reset modals on close ─────────────────────────────────────────────────
    $('#addDocumentModal').on('hidden.bs.modal', function() {
        $('#addDocumentForm')[0].reset();
        $('.kind-option').css({ border: '2px solid #dee2e6', background: '#f8f9fa', color: '#495057' });
    });

    $('#forwardModal').on('show.bs.modal', function() {
        $('#fwdToSectionSelect, #fwdToUnitSelect, #fwdToOfficeSelect').prop('disabled', false);
        $('#fwdToSectionSelect, #fwdToOfficeSelect').select2({ theme: 'bootstrap4', dropdownParent: $('#forwardModal') });
    });
});

// ── Edit Document Modal ───────────────────────────────────────────────────────
function editDocument(id) {
    $('#editModalBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#updateDocumentBtn').hide();
    $('#editDocumentModal').modal('show');

    $.get('document_actions.php', { action: 'get', id: id }, function(r) {
        try {
            if (!r.success) { Swal.fire({ icon: 'error', title: 'Load Failed', text: r.message || 'Failed to load document.' }); return; }
            const d = r.data;
            $('#editDocModalNum').text(d.document_number);

            let typeOpts = '<option value="">-- Select Type --</option>';
            docTypesArr.forEach(t => {
                typeOpts += `<option value="${t.id}" ${d.document_type_id == t.id ? 'selected' : ''}>${t.type_name}</option>`;
            });

            let statusHtml = '';
            statusOpts.forEach(s => {
                statusHtml += `<option value="${s}" ${d.status === s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`;
            });

            const kindIcons = { incoming: 'fa-inbox', outgoing: 'fa-paper-plane', internal: 'fa-exchange-alt' };
            const kindHtml = ['incoming','outgoing','internal'].map(k => `
                <label style="flex:1;">
                    <input type="radio" name="kind" value="${k}" class="kind-radio" style="display:none;" ${d.kind === k ? 'checked' : ''}>
                    <div class="kind-option kind-opt-${k}">
                        <i class="fas ${kindIcons[k]} fa-lg mb-1"></i><br>${k.charAt(0).toUpperCase() + k.slice(1)}
                    </div>
                </label>`).join('');

            // FIX: use textContent assignment for document_name and remarks to avoid XSS
            const docNameEscaped   = $('<div>').text(d.document_name || '').html();
            const remarksEscaped   = $('<div>').text(d.remarks || '').html();
            const docNumEscaped    = $('<div>').text(d.document_number || '').html();

            $('#editModalBody').html(`
                <form id="editDocumentForm">
                    <input type="hidden" name="id"     value="${d.id}">
                    <input type="hidden" name="action" value="update">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Kind of Document <span class="text-danger">*</span></label>
                            <div class="d-flex" style="gap:8px;">${kindHtml}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="document_number" value="${docNumEscaped}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control select2e" name="document_type_id" required>${typeOpts}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Document Name / Particulars <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="document_name" rows="2" required>${docNameEscaped}</textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Date Received</label>
                            <input type="datetime-local" class="form-control" name="date_received"
                                value="${d.date_received && d.date_received !== '0000-00-00 00:00:00' ? d.date_received.replace(' ','T').substring(0,16) : ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Status</label>
                            <select class="form-control" name="status">${statusHtml}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2">${remarksEscaped}</textarea>
                        </div>
                    </div>
                </form>
            `);

            // FIX: re-apply kind-radio visual state after dynamic HTML injection
            $('#editModalBody input[name="kind"]:checked').trigger('change');
            $('#editDocumentModal .select2e').select2({ theme: 'bootstrap4', dropdownParent: $('#editDocumentModal') });
            $('#updateDocumentBtn').show();
        } catch(e) {
            $('#editModalBody').html('<div class="alert alert-danger">Failed to load document: ' + e.message + '</div>');
        }
    }, 'json');
}

// ── Save Edit ─────────────────────────────────────────────────────────────────
$(document).on('click', '#updateDocumentBtn', function() {
    const form = $('#editDocumentForm');
    if (!form.find('input[name="kind"]:checked').val()) {
        Swal.fire({ icon: 'warning', title: 'Kind Required', text: 'Please select the kind of document.' }); return;
    }
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

    $.post('document_actions.php', form.serialize(), function(r) {
        if (r.success) {
            $('#editDocumentModal').modal('hide');
            Swal.fire({
                icon: 'success',
                title: 'Document Updated!',
                text: 'The document has been updated successfully.',
                timer: 1800,
                showConfirmButton: false,
                timerProgressBar: true
            }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: r.message || 'An error occurred while updating.' });
        }
    }, 'json').fail(function(xhr) {
        Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected server error occurred.' });
        console.error(xhr.responseText);
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes'));
});

function openForwardModal(id, docNum) {
    $('#fwdDocId').val(id);
    $('#fwdDocNumber').text(docNum);
    $('#forwardDocumentForm')[0].reset();
    $('#fwdSectionGroup').show();
    $('#fwdImoGroup').hide();
    $('#fwdToSectionSelect, #fwdToUnitSelect').prop('disabled', false);
    $('#fwdHiddenOfficeId').prop('disabled', false);
    $('input[name="_fwd_dest_type"][value="section"]').prop('checked', true);
    $('#forwardDestType').val('section');
    $('#fwdToUnitSelect').html('<option value="">-- Entire Section --</option>');
    $('#fwdUnitGroup').hide();
    $('#fwdBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
    $('#fwdBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
    $('#forwardModal').modal('show');
}

// ── CSV Export ──────────────────────────────────────────────────────────────
function exportTableToCSV() {
    let csv = [];
    const rows = document.querySelectorAll('#documentsTable tr');
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length - 1; j++) { // skip Actions column
            row.push('"' + cols[j].innerText.replace(/"/g, '""').replace(/,/g, ';') + '"');
        }
        csv.push(row.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'documents_export_<?= date('Ymd') ?>.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ── Delete Request Modal ──────────────────────────────────────────────────────
function openDeleteRequestModal(id, docNum) {
    $('#deleteReqDocId').val(id);
    $('#deleteReqDocNum').text(docNum);
    $('#deleteReqReason').val('');
    $('#deleteRequestModal').modal('show');
}

$(document).on('click', '#submitDeleteRequestBtn', function() {
    const id     = $('#deleteReqDocId').val();
    const reason = $('#deleteReqReason').val().trim();
    if (!reason) {
        Swal.fire({ icon: 'warning', title: 'Reason Required', text: 'Please explain why you want to delete this document.' });
        return;
    }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...');
    $.post('document_actions.php', { action: 'request_delete', id, reason }, function(r) {
        $('#deleteRequestModal').modal('hide');
        if (r.success) {
            Swal.fire({
                icon: 'success',
                title: 'Request Submitted',
                text: r.message,
                timer: 2500,
                showConfirmButton: false,
                timerProgressBar: true
            }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Request Failed', text: r.message || 'An error occurred.' });
        }
    }, 'json').fail(function() {
        Swal.fire({ icon: 'error', title: 'Server Error', text: 'Unexpected server error.' });
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit Request'));
});

// ── Admin Delete-Requests Panel ───────────────────────────────────────────────
function openAdminDeletePanel() {
    $('#adminDeleteModal').modal('show');
    loadDeleteRequests('pending');
}

function loadDeleteRequests(filter) {
    $('#adminDeleteTableBody').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
    $.get('document_actions.php', { action: 'get_delete_requests', filter }, function(r) {
        if (!r.success) {
            $('#adminDeleteTableBody').html('<tr><td colspan="6" class="text-danger text-center">' + (r.message || 'Error') + '</td></tr>');
            return;
        }
        if (!r.requests.length) {
            $('#adminDeleteTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">No ' + filter + ' requests.</td></tr>');
            return;
        }
        let html = '';
        r.requests.forEach(req => {
            const statusBadge = {
                pending:  '<span class="badge badge-warning">Pending</span>',
                approved: '<span class="badge badge-success">Approved</span>',
                rejected: '<span class="badge badge-danger">Rejected</span>'
            }[req.status] || req.status;

            const kindBadge = `<span class="kind-badge kind-${req.kind}">${req.kind.charAt(0).toUpperCase()+req.kind.slice(1)}</span>`;
            const actions = req.status === 'pending'
                ? `<button class="btn btn-xs btn-success mr-1" onclick="adminActOnRequest(${req.id},'approve')"><i class="fas fa-check"></i> Approve</button>
                   <button class="btn btn-xs btn-danger"  onclick="adminActOnRequest(${req.id},'reject')"><i class="fas fa-times"></i> Reject</button>`
                : `<small class="text-muted">${req.reviewer_name || '—'}<br>${req.reviewed_at ? req.reviewed_at.substring(0,16) : ''}</small>`;

            html += `<tr>
                <td>${statusBadge}</td>
                <td><code style="font-size:.75rem;">${escHtml(req.document_number)}</code><br><small class="text-muted">${escHtml(req.document_name)}</small></td>
                <td>${kindBadge}</td>
                <td>${escHtml(req.requester_name)}<br><small class="text-muted">${req.created_at.substring(0,16)}</small></td>
                <td style="max-width:180px;white-space:normal;">${escHtml(req.reason)}</td>
                <td>${actions}</td>
            </tr>`;
        });
        $('#adminDeleteTableBody').html(html);
    }, 'json');
}

function adminActOnRequest(requestId, action) {
    const label = action === 'approve' ? 'Approve' : 'Reject';
    const color = action === 'approve' ? '#198754' : '#dc3545';
    Swal.fire({
        title: label + ' Delete Request?',
        html: `<textarea id="adminNoteInput" class="swal2-textarea" placeholder="Optional note to requester (${action === 'reject' ? 'required reason' : 'optional'})"></textarea>`,
        icon: action === 'approve' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: label,
        preConfirm: () => {
            const note = document.getElementById('adminNoteInput').value.trim();
            if (action === 'reject' && !note) {
                Swal.showValidationMessage('Please provide a reason for rejection.');
                return false;
            }
            return note;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        const admin_note = result.value || '';
        $.post('document_actions.php', { action: action + '_delete', request_id: requestId, admin_note }, function(r) {
            if (r.success) {
                Swal.fire({ icon: 'success', title: label + 'd!', text: r.message, timer: 2000, showConfirmButton: false, timerProgressBar: true })
                    .then(() => loadDeleteRequests($('#adminDeleteFilterSelect').val() || 'pending'));
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: r.message });
            }
        }, 'json');
    });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

// ── User Delete-Request Notifications (SweetAlert2) ──────────────────────────
$(function() {
    $.get('document_actions.php', { action: 'get_delete_notifications' }, function(r) {
        if (!r.success || !r.notifications.length) return;
        const unread = r.notifications.filter(n => !n.is_read);
        if (!unread.length) return;

        const hasApproved = unread.some(n => n.type === 'delete_approved');
        const hasRejected = unread.some(n => n.type === 'delete_rejected');

        const swalIcon  = hasApproved ? 'success' : hasRejected ? 'error' : 'info';
        const swalTitle = hasApproved && hasRejected ? 'Document Request Updates'
                        : hasApproved               ? 'Delete Request Approved'
                        : hasRejected               ? 'Delete Request Rejected'
                        :                             'Document Notifications';
        const swalColor = hasApproved && !hasRejected ? '#065f46'
                        : hasRejected && !hasApproved ? '#991b1b'
                        :                               '#1e3a5e';

        let itemsHtml = '';
        unread.forEach(n => {
            const icon  = n.type === 'delete_approved' ? '✅' : n.type === 'delete_rejected' ? '❌' : '🔔';
            const color = n.type === 'delete_approved' ? '#065f46' : n.type === 'delete_rejected' ? '#991b1b' : '#1e3a5e';
            itemsHtml += `
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:left;">
                    <span style="font-size:1.1rem;margin-top:1px;">${icon}</span>
                    <span style="font-size:.88rem;color:${color};line-height:1.45;">${escHtml(n.message)}</span>
                </div>`;
        });

        Swal.fire({
            icon: swalIcon,
            title: `<span style="font-size:1.05rem;font-weight:700;color:${swalColor};">`
                 + `<i class="fas fa-bell" style="margin-right:7px;"></i>${swalTitle}</span>`,
            html: `<div style="max-height:280px;overflow-y:auto;padding:0 4px;">${itemsHtml}</div>`,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Got it',
            confirmButtonColor: swalColor,
            showCloseButton: true,
            allowOutsideClick: true,
            customClass: {
                popup:   'swal2-notification-popup',
                title:   'swal2-notification-title',
                htmlContainer: 'swal2-notification-html'
            }
        });

        // Mark as read after showing
        $.post('document_actions.php', { action: 'mark_notifications_read' });
    }, 'json');
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>