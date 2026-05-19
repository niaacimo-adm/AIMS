<?php
ob_start();
date_default_timezone_set('Asia/Manila');
require_once '../includes/auth.php';
require_once '../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: document_list.php'); exit; }

$database = new Database();
$db = $database->getConnection();
$db->query("SET time_zone = '+08:00'");

$stmt = $db->prepare("
    SELECT dr.*,
           dt.type_name,
           s1.section_name AS from_section, s1.section_code AS from_code,
           s2.section_name AS to_section,   s2.section_code AS to_code,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit,
           CONCAT(TRIM(e1.first_name),' ',TRIM(e1.last_name)) AS forwarded_by_name_emp,
           CONCAT(TRIM(e2.first_name),' ',TRIM(e2.last_name)) AS forwarded_to_name_emp,
           (SELECT df_last.fwd_date FROM document_forwards df_last
            WHERE df_last.document_id = dr.id
            ORDER BY df_last.id DESC LIMIT 1) AS last_fwd_date
    FROM document_records dr
    LEFT JOIN document_types dt  ON dr.document_type_id        = dt.id
    LEFT JOIN section        s1  ON dr.from_section_id         = s1.section_id
    LEFT JOIN section        s2  ON dr.forwarded_to_section_id = s2.section_id
    LEFT JOIN unit_section   us1 ON dr.from_unit_id            = us1.unit_id
    LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id    = us2.unit_id
    LEFT JOIN employee       e1  ON dr.forwarded_by_emp_id     = e1.emp_id
    LEFT JOIN employee       e2  ON dr.forwarded_to_emp_id     = e2.emp_id
    WHERE dr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
if (!$doc) { header('Location: document_list.php'); exit; }

$kind_colors = ['incoming' => '#2563eb', 'outgoing' => '#059669', 'internal' => '#7c3aed'];
$kind_lights = ['incoming' => '#eff6ff', 'outgoing' => '#ecfdf5', 'internal' => '#f5f3ff'];
$kind_color  = $kind_colors[$doc['kind']] ?? '#1a3c5e';
$kind_light  = $kind_lights[$doc['kind']] ?? '#f0f4f8';
$kind_icons  = ['incoming' => 'fa-inbox', 'outgoing' => 'fa-paper-plane', 'internal' => 'fa-exchange-alt'];
$kind_icon   = $kind_icons[$doc['kind']] ?? 'fa-file-alt';

$fhstmt = $db->prepare("
    SELECT df.*,
           CONCAT(TRIM(eb.first_name),' ',TRIM(eb.last_name)) AS fwd_by_name,
           CONCAT(TRIM(et.first_name),' ',TRIM(et.last_name)) AS fwd_to_name,
           s.section_name  AS to_section_name,
           us.unit_name    AS to_unit_name,
           o.office_name   AS to_office_name
    FROM document_forwards df
    LEFT JOIN employee       eb ON df.fwd_by_emp_id     = eb.emp_id
    LEFT JOIN employee       et ON df.fwd_to_emp_id     = et.emp_id
    LEFT JOIN section        s  ON df.fwd_to_section_id = s.section_id
    LEFT JOIN unit_section   us ON df.fwd_to_unit_id    = us.unit_id
    LEFT JOIN office         o  ON df.fwd_to_office_id  = o.office_id
    WHERE df.document_id = ?
    ORDER BY df.id ASC
");
$fhstmt->bind_param("i", $id);
$fhstmt->execute();
$fwd_history = $fhstmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!function_exists('safeDate')) {
function safeDate($dateStr, $format = 'M d, Y g:i A') {
    if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00') return null;
    try {
        // Dates returned by MariaDB with SET time_zone='+08:00' are already PHT strings.
        // Explicitly create the DateTime in PHT (+08:00) so no additional offset is applied,
        // regardless of the PHP server's default timezone setting.
        $pht = new DateTimeZone('Asia/Manila');
        $dt  = new DateTime($dateStr, $pht);
        return $dt->format($format);
    } catch (Exception $e) { return null; }
}
}

$view_sec_list = $db->query("SELECT section_id, section_name, section_code FROM section ORDER BY section_name");
$view_sec_arr  = $view_sec_list ? $view_sec_list->fetch_all(MYSQLI_ASSOC) : [];

$view_logged = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
$view_user   = [];
if ($view_logged) {
    $view_user_stmt = $db->prepare("
        SELECT CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name,
            s.section_name, s.section_code, us.unit_name,
            u.role_id AS user_role_id
        FROM employee e
        LEFT JOIN section      s  ON e.section_id      = s.section_id
        LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
        LEFT JOIN users        u  ON u.employee_id     = e.emp_id
        WHERE e.emp_id = ? LIMIT 1
    ");
    $view_user_stmt->bind_param("i", $view_logged);
    $view_user_stmt->execute();
    $view_user = $view_user_stmt->get_result()->fetch_assoc() ?? [];
}

// ── Masteradmin check ────────────────────────────────────────────────────────
$view_isMasteradmin = false;
if ($view_logged) {
    $vmaStmt = $db->prepare("
        SELECT 1 FROM users u
        JOIN user_roles ur ON u.role_id = ur.id
        WHERE u.employee_id = ? AND ur.id = 1 LIMIT 1
    ");
    if ($vmaStmt) {
        $vmaStmt->bind_param("i", $view_logged);
        $vmaStmt->execute();
        $view_isMasteradmin = $vmaStmt->get_result()->num_rows > 0;
    }
    if (!$view_isMasteradmin) {
        $session_uid = (int)($_SESSION['user_id'] ?? 0);
        if ($session_uid) {
            $vmaFb = $db->prepare("
                SELECT 1 FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
                WHERE u.id = ? AND ur.id = 1 LIMIT 1
            ");
            if ($vmaFb) {
                $vmaFb->bind_param("i", $session_uid);
                $vmaFb->execute();
                $view_isMasteradmin = $vmaFb->get_result()->num_rows > 0;
            }
        }
    }
}

// ── Is current user the document creator? ───────────────────────────────────
$view_isOwner = ($view_logged > 0 && (int)($doc['created_by_emp_id'] ?? 0) === $view_logged);

// ── Pending delete request status for this document (owner only) ─────────────
$view_delReqStatus = null;
if ($view_isOwner) {
    $vdrStmt = $db->prepare("
        SELECT status FROM document_delete_requests
        WHERE document_id = ? AND requested_by = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    if ($vdrStmt) {
        $vdrStmt->bind_param("ii", $id, $view_logged);
        $vdrStmt->execute();
        $vdrRow = $vdrStmt->get_result()->fetch_assoc();
        $view_delReqStatus = $vdrRow['status'] ?? null;
    }
}

// ── Unread notifications for current user (delete workflow) ──────────────────
$view_notifications = [];
if ($view_logged) {
    $vnStmt = $db->prepare("
        SELECT id, type, message, is_read, created_at
        FROM document_notifications
        WHERE recipient_emp_id = ? AND is_read = 0
        ORDER BY created_at DESC LIMIT 20
    ");
    if ($vnStmt) {
        $vnStmt->bind_param("i", $view_logged);
        $vnStmt->execute();
        $view_notifications = $vnStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document #<?= $doc['id'] ?> — <?= htmlspecialchars($doc['document_number']) ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS — light (default)
           Aligned with login.php green/forest palette
        ══════════════════════════════════════════ */
        :root {
            --kc:    <?= $kind_color ?>;
            --kl:    <?= $kind_light ?>;
            --brand: #1c4d38;
            --border: rgba(42,152,99,.18);
            --bg-sub: #f0faf5;
            --r-lg:  14px;
            --r-md:  10px;
            --r-sm:  6px;
            --shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(42,152,99,.08);
            --t-muted: #4a7a5e;
            --t-sub:   #6aad8a;
        }
        /* ══════════════════════════════════════════
           DESIGN TOKENS — dark mode
        ══════════════════════════════════════════ */
        body.dark-mode {
            --brand:   #24e78f;
            --border:  var(--card-border, rgba(36,231,143,.10));
            --bg-sub:  var(--table-stripe, #122b1d);
            --shadow:  0 1px 3px rgba(0,0,0,.3), 0 4px 16px rgba(0,0,0,.25);
            --t-muted: #6aad8a;
            --t-sub:   #4a7a5e;
        }
        .dv-layout { display:flex; gap:22px; align-items:flex-start; }
        .dv-main { flex:1; min-width:0; }
        .dv-side { width:296px; flex-shrink:0; position:sticky; top:10px; }
        .dv-hero { background: linear-gradient(135deg, var(--brand) 0%, var(--kc) 100%); border-radius: var(--r-lg); padding:26px 28px 22px; color:#fff; position:relative; overflow:hidden; margin-bottom:14px; }
        .dv-hero::before { content:''; position:absolute; right:-20px; top:-20px; width:160px; height:160px; border-radius:50%; background:rgba(255,255,255,.07); }
        .dv-hero::after { content:''; position:absolute; right:50px; bottom:-40px; width:100px; height:100px; border-radius:50%; background:rgba(255,255,255,.05); }
        .dv-hero-icon { width:50px; height:50px; border-radius:var(--r-md); background:rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .dv-hero-type { font-size:.69rem; font-weight:600; opacity:.7; text-transform:uppercase; letter-spacing:.1em; }
        .dv-hero-name { font-size:1.18rem; font-weight:700; line-height:1.3; margin:4px 0 10px; }
        .dv-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:.69rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .pill-glass { background:rgba(255,255,255,.2); color:#fff; }
        .pill-incoming { background:#dbeafe; color:#1d4ed8; }
        .pill-outgoing { background:#dcfce7; color:#166534; }
        .pill-internal { background:#ede9fe; color:#5b21b6; }
        .pill-pending { background:#ffedd5; color:#c2410c; }
        .pill-received { background:#dbeafe; color:#1d4ed8; }
        .pill-returned { background:#fce7f3; color:#9d174d; }
        .pill-completed { background:#d1fae5; color:#065f46; }
        .pill-archived { background:#f3f4f6; color:#374151; }
        .dv-card { background:#fff; border:1px solid var(--border); border-radius:var(--r-lg); box-shadow:var(--shadow); overflow:hidden; margin-bottom:14px; }
        .dv-card-hd { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--border); background:var(--bg-sub); }
        .dv-card-title { font-size:.69rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--t-muted); display:flex; align-items:center; gap:7px; }
        .dv-card-title i { color:var(--kc); }
        .dv-card-bd { padding:18px; }
        .dv-lbl { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.09em; color:var(--t-sub); margin-bottom:3px; }
        .dv-val { font-size:.89rem; font-weight:500; color:#111827; }
        .dv-val code { background:var(--kl); color:var(--kc); padding:2px 8px; border-radius:var(--r-sm); font-size:.8rem; font-weight:600; }
        .dv-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
        .dv-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .dv-flow { display:flex; align-items:stretch; gap:0; }
        .dv-fnode { flex:1; padding:15px 16px; background:var(--kl); border-radius:var(--r-md); border:1px solid rgba(0,0,0,.06); }
        .dv-fnode .flbl { font-size:.63rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--kc); margin-bottom:5px; }
        .dv-fnode .fname { font-size:.9rem; font-weight:600; color:#111827; }
        .dv-fnode .fsub { font-size:.75rem; color:var(--t-muted); margin-top:2px; }
        .dv-farrow { display:flex; align-items:center; padding:0 14px; color:var(--kc); font-size:1rem; }
        .dv-tl-item { display:flex; gap:12px; }
        .dv-tl-left { display:flex; flex-direction:column; align-items:center; width:34px; flex-shrink:0; }
        .dv-tl-dot { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.73rem; font-weight:700; color:#fff; flex-shrink:0; box-shadow:0 0 0 4px #fff, 0 0 0 5px rgba(0,0,0,.08); }
        .dv-tl-line { width:2px; flex:1; min-height:18px; background:var(--border); margin:5px 0; }
        .dv-tl-card { flex:1; background:var(--bg-sub); border:1px solid var(--border); border-radius:var(--r-md); padding:12px 14px; margin-bottom:10px; }
        .dv-tl-who { font-size:.86rem; font-weight:600; color:#111827; }
        .dv-tl-dest { margin-top:5px; display:flex; flex-wrap:wrap; gap:4px; }
        .dv-tl-chip { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:20px; font-size:.67rem; font-weight:600; }
        .dv-tl-rmk { font-size:.75rem; color:var(--t-muted); font-style:italic; margin-top:4px; }
        .dv-tl-dt { font-size:.69rem; color:var(--t-sub); margin-top:4px; display:flex; align-items:center; gap:3px; }
        .dv-tl-empty { text-align:center; padding:24px 16px; background:var(--bg-sub); border:1px dashed var(--border); border-radius:var(--r-md); color:var(--t-muted); font-size:.84rem; }
        .dv-dt-item { padding:13px 14px; background:var(--bg-sub); border:1px solid var(--border); border-radius:var(--r-md); }
        .dv-dt-item .v { font-size:.86rem; font-weight:500; color:#111827; margin-top:4px; }
        .dv-dt-item .v.dim { color:var(--t-muted); }
        .dv-remarks { background:var(--bg-sub); border:1px solid var(--border); border-radius:var(--r-md); padding:14px 16px; font-size:.87rem; color:#374151; line-height:1.65; min-height:54px; }
        .dv-btn { display:flex; align-items:center; gap:9px; width:100%; padding:9px 13px; border:none; border-radius:var(--r-md); font-size:.82rem; font-weight:600; cursor:pointer; transition:filter .12s, opacity .12s; text-decoration:none; margin-bottom:6px; text-align:left; }
        .dv-btn:last-child { margin-bottom:0; }
        .dv-btn i { width:16px; text-align:center; font-size:.88rem; flex-shrink:0; }
        .dv-btn:hover { filter:brightness(.92); }
        .dv-btn.g { background:#2a9863; color:#fff; }
        .dv-btn.y { background:#d97706; color:#fff; }
        .dv-btn.s { background:#f1f5f9; color:#475569; border:1px solid var(--border); }
        .dv-btn.b { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
        .dv-btn.r { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .dv-divider{ border:none; border-top:1px solid var(--border); margin:8px 0; }
        .dv-status-row { display:flex; gap:7px; align-items:center; }
        .dv-status-row select { flex:1; font-size:.83rem; }
        .dv-save-btn { padding:6px 13px; border:none; border-radius:var(--r-sm); background:#2a9863; color:#fff; font-size:.8rem; font-weight:700; cursor:pointer; white-space:nowrap; transition:filter .12s; }
        .dv-save-btn:hover { filter:brightness(.9); }
        @media (max-width:991px) { .dv-layout { flex-direction:column; } .dv-side { width:100%; position:static; } .dv-grid-4 { grid-template-columns:1fr 1fr; } }
        @media print { .dv-side, .content-header, .main-header, .main-sidebar, .main-footer { display:none!important; } .dv-main { width:100%!important; } }
        body.dark-mode .dv-card { background:var(--card-bg, #102f22); border-color:var(--card-border, rgba(36,231,143,.10)); }
        body.dark-mode .dv-card-hd { background:rgba(36,231,143,.04); border-color:var(--card-border, rgba(36,231,143,.10)); }
        body.dark-mode .dv-card-hd .dv-card-title { color:#6aad8a; }
        body.dark-mode .dv-fnode { background:rgba(36,231,143,.06); border-color:var(--card-border, rgba(36,231,143,.10)); }
        body.dark-mode .dv-fnode .fname { color:var(--text-primary, #d4f5e5); }
        body.dark-mode .dv-fnode .fsub  { color:#6aad8a; }
        body.dark-mode .dv-val { color:var(--text-primary, #d4f5e5); }
        body.dark-mode .dv-tl-card { background:var(--table-stripe, #122b1d); border-color:var(--card-border, rgba(36,231,143,.10)); }
        body.dark-mode .dv-tl-who { color:var(--text-primary, #d4f5e5); }
        body.dark-mode .dv-tl-dot { box-shadow:0 0 0 4px var(--card-bg, #102f22), 0 0 0 5px rgba(36,231,143,.15); }
        body.dark-mode .dv-tl-line { background:var(--card-border, rgba(36,231,143,.12)); }
        body.dark-mode .dv-tl-rmk { color:#6aad8a; }
        body.dark-mode .dv-tl-dt  { color:#4a7a5e; }
        body.dark-mode .dv-tl-empty { background:var(--table-stripe, #122b1d); border-color:var(--card-border, rgba(36,231,143,.10)); color:#6aad8a; }
        body.dark-mode .dv-dt-item { background:var(--table-stripe, #122b1d); border-color:var(--card-border, rgba(36,231,143,.10)); }
        body.dark-mode .dv-dt-item .v { color:var(--text-primary, #d4f5e5); }
        body.dark-mode .dv-dt-item .v.dim { color:#6aad8a; }
        body.dark-mode .dv-remarks { background:var(--table-stripe, #122b1d); border-color:var(--card-border, rgba(36,231,143,.10)); color:var(--text-primary, #d4f5e5); }
        body.dark-mode .dv-btn.s { background:rgba(36,231,143,.06); color:var(--text-primary, #d4f5e5); border-color:var(--card-border, rgba(36,231,143,.12)); }
        body.dark-mode .dv-btn.b { background:rgba(36,231,143,.08); color:#24e78f; border-color:rgba(36,231,143,.20); }
        body.dark-mode .dv-status-row select { background:var(--input-bg, #0e2619); color:var(--text-primary, #d4f5e5); border-color:var(--input-border, rgba(36,231,143,.18)); }
        /* pill overrides dark */
        body.dark-mode .pill-incoming { background:#1e3a5f; color:#93c5fd; }
        body.dark-mode .pill-outgoing { background:#14532d; color:#86efac; }
        body.dark-mode .pill-internal { background:#2e1065; color:#c4b5fd; }
        body.dark-mode .pill-pending  { background:#431407; color:#fdba74; }
        body.dark-mode .pill-received { background:#064e3b; color:#6ee7b7; }
        body.dark-mode .pill-returned { background:#4a044e; color:#f0abfc; }
        body.dark-mode .pill-completed{ background:#064e3b; color:#6ee7b7; }
        body.dark-mode .pill-archived { background:#122b1d; color:#6aad8a; }
        /* hero dark overlay */
        body.dark-mode .dv-hero { background: linear-gradient(135deg, #091d14 0%, var(--kc) 100%); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                    <h1 class="m-0" style="font-size:1.15rem;font-weight:700;color:var(--brand);"><i class="fas fa-file-alt mr-2" style="color:<?= $kind_color ?>;"></i>Document Detail</h1>
                    <ol class="breadcrumb mb-0" style="background:transparent;padding:0;font-size:.82rem;">
                        <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="document_list.php">Documents</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($doc['document_number']) ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <div class="dv-layout">
                    <div class="dv-main">
                        <!-- Hero -->
                        <div class="dv-hero">
                            <div class="d-flex align-items-start" style="gap:16px;position:relative;z-index:1;">
                                <div class="dv-hero-icon"><i class="fas <?= $kind_icon ?>"></i></div>
                                <div style="min-width:0;flex:1;">
                                    <div class="dv-hero-type"><?= htmlspecialchars($doc['type_name'] ?? 'Document') ?></div>
                                    <div class="dv-hero-name"><?= htmlspecialchars($doc['document_name']) ?></div>
                                    <div class="d-flex flex-wrap" style="gap:5px;">
                                        <span class="dv-pill pill-glass"><i class="fas fa-hashtag" style="font-size:.6rem;"></i><?= htmlspecialchars($doc['document_number']) ?></span>
                                        <span class="dv-pill pill-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span>
                                        <span class="dv-pill pill-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Document Information -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-info-circle"></i>Document Information</div></div>
                            <div class="dv-card-bd"><div class="dv-grid-4"><div><div class="dv-lbl">Document ID</div><div class="dv-val" style="font-weight:700;font-size:1rem;">#<?= $doc['id'] ?></div></div><div><div class="dv-lbl">Document Number</div><div class="dv-val"><code><?= htmlspecialchars($doc['document_number']) ?></code></div></div><div><div class="dv-lbl">Document Type</div><div class="dv-val"><?= htmlspecialchars($doc['type_name'] ?? '—') ?></div></div><div><div class="dv-lbl">Kind</div><span class="dv-pill pill-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span></div></div></div>
                        </div>
                        <!-- Document Flow -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-route"></i>Document Flow</div></div>
                            <div class="dv-card-bd"><div class="dv-flow"><div class="dv-fnode"><div class="flbl"><i class="fas fa-sign-out-alt mr-1"></i>From</div><div class="fname"><?= htmlspecialchars($doc['forwarded_by_name_emp'] ?: ($doc['forwarded_by_name'] ?: '—')) ?></div><div class="fsub"><?= htmlspecialchars($doc['from_section'] ?: 'External / Not Specified') ?></div><?php if (!empty($doc['from_unit'])): ?><div class="fsub"><i class="fas fa-layer-group mr-1" style="font-size:.58rem;"></i><?= htmlspecialchars($doc['from_unit']) ?></div><?php endif; ?></div><div class="dv-farrow"><i class="fas fa-arrow-right"></i></div><div class="dv-fnode"><div class="flbl"><i class="fas fa-sign-in-alt mr-1"></i>To</div><div class="fname"><?= htmlspecialchars($doc['forwarded_to_name_emp'] ?: ($doc['forwarded_to'] ?: 'Not yet forwarded')) ?></div><div class="fsub"><?= htmlspecialchars($doc['to_section'] ?: 'Not Specified') ?></div><?php if (!empty($doc['to_unit'])): ?><div class="fsub"><i class="fas fa-layer-group mr-1" style="font-size:.58rem;"></i><?= htmlspecialchars($doc['to_unit']) ?></div><?php endif; ?></div></div></div>
                        </div>
                        <!-- Forwarding History -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-history"></i>Forwarding History<span style="background:var(--kc);color:#fff;border-radius:20px;padding:1px 8px;font-size:.62rem;margin-left:2px;"><?= count($fwd_history) ?></span></div><button onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')" style="background:var(--kc);color:#fff;border:none;border-radius:var(--r-sm);padding:5px 12px;font-size:.76rem;font-weight:600;cursor:pointer;"><i class="fas fa-share mr-1"></i>Forward Again</button></div>
                            <div class="dv-card-bd">
                                <?php if (!empty($fwd_history)): ?>
                                <?php foreach ($fwd_history as $idx => $h):
                                    $fds = safeDate($h['fwd_date']) ?? null;
                                    if (!empty($h['to_office_name'])) {
                                        $di='fa-star'; $dc='#2563eb'; $db_='#dbeafe';
                                        $dl=$h['to_office_name']; $ds='IMO Office';
                                    } elseif (!empty($h['to_section_name'])) {
                                        $di='fa-building'; $dc='#059669'; $db_='#d1fae5';
                                        $dl=$h['to_section_name'];
                                        $ds=!empty($h['to_unit_name'])?$h['to_unit_name']:'Entire Section';
                                    } else {
                                        $di='fa-user'; $dc='#7c3aed'; $db_='#ede9fe';
                                        $dl=$h['fwd_to_name']?:'—'; $ds='Direct';
                                    }
                                ?>
                                <div class="dv-tl-item"><div class="dv-tl-left"><div class="dv-tl-dot" style="background:<?= $dc ?>;"><?= $idx+1 ?></div><?php if ($idx < count($fwd_history)-1): ?><div class="dv-tl-line"></div><?php endif; ?></div><div class="dv-tl-card"><div class="dv-tl-who"><span style="color:#374151;"><?= htmlspecialchars($h['fwd_by_name']?:'—') ?></span><i class="fas fa-arrow-right mx-2" style="color:#d1d5db;font-size:.65rem;"></i><span style="color:#1a3c5e;"><?= htmlspecialchars($h['fwd_to_name']?:'—') ?></span></div><div class="dv-tl-dest"><span class="dv-tl-chip" style="background:<?= $db_ ?>;color:<?= $dc ?>;"><i class="fas <?= $di ?>"></i><?= htmlspecialchars($dl) ?></span><?php if ($ds && ($ds!=='Entire Section'||!empty($h['to_unit_name']))): ?><span class="dv-tl-chip" style="background:#f3f4f6;color:#6b7280;"><?= htmlspecialchars($ds) ?></span><?php endif; ?></div><?php if (!empty($h['fwd_remarks'])): ?><div class="dv-tl-rmk"><i class="fas fa-comment mr-1" style="font-size:.58rem;color:#9ca3af;"></i><?= htmlspecialchars($h['fwd_remarks']) ?></div><?php endif; ?><?php if ($fds): ?><div class="dv-tl-dt"><i class="fas fa-clock" style="color:#9ca3af;"></i><?= $fds ?></div><?php endif; ?></div></div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="dv-tl-empty"><i class="fas fa-share-alt mb-2 d-block" style="font-size:1.4rem;opacity:.3;"></i>No forwarding history yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Dates & Timeline -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-calendar-alt"></i>Dates &amp; Timeline</div></div>
                            <div class="dv-card-bd"><div class="dv-grid-2"><div class="dv-dt-item"><div class="dv-lbl"><i class="fas fa-paper-plane mr-1"></i>Date &amp; Time Forwarded</div><?php $fd = safeDate($doc['last_fwd_date'] ?? $doc['date_forwarded']); ?><div class="v <?= $fd?'':'dim' ?>"><?= $fd??'—' ?></div></div><div class="dv-dt-item"><div class="dv-lbl"><i class="fas fa-check-circle mr-1" style="color:#059669;"></i>Date Received</div><?php $rd = safeDate($doc['date_received']); ?><div class="v <?= $rd?'':'dim' ?>"><?= $rd??'Not yet received' ?></div></div><div class="dv-dt-item"><div class="dv-lbl"><i class="fas fa-plus-circle mr-1"></i>Record Created</div><div class="v"><?= safeDate($doc['created_at'])??'—' ?></div></div><div class="dv-dt-item"><div class="dv-lbl"><i class="fas fa-pencil-alt mr-1"></i>Last Updated</div><div class="v"><?= safeDate($doc['updated_at'])??'—' ?></div></div></div></div>
                        </div>
                        <!-- Remarks -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-comment-alt"></i>Remarks</div></div>
                            <div class="dv-card-bd"><div class="dv-remarks"><?= !empty($doc['remarks']) ? nl2br(htmlspecialchars($doc['remarks'])) : '<span style="color:#9ca3af;font-style:italic;">No remarks provided.</span>' ?></div></div>
                        </div>

                        
                    </div>
                    <div class="dv-side">
                        <!-- Status -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-toggle-on"></i>Status</div><span class="dv-pill pill-<?= $doc['status'] ?>" id="statusBadge"><?= ucfirst($doc['status']) ?></span></div>
                            <div class="dv-card-bd"><div class="dv-status-row"><select class="form-control form-control-sm" id="quickStatusSelect"><option value="pending" <?= $doc['status']==='pending' ?'selected':'' ?>>Pending</option><option value="received" <?= $doc['status']==='received' ?'selected':'' ?>>Received</option><option value="returned" <?= $doc['status']==='returned' ?'selected':'' ?>>Returned</option><option value="completed" <?= $doc['status']==='completed' ?'selected':'' ?>>Completed</option><option value="archived" <?= $doc['status']==='archived' ?'selected':'' ?>>Archived</option></select><button class="dv-save-btn" onclick="updateStatus()"><i class="fas fa-check mr-1"></i>Save</button></div></div>
                        </div>
                        <!-- Actions -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-bolt"></i>Actions</div></div>
                            <div class="dv-card-bd" style="padding:14px;">
                                <button class="dv-btn g" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-share"></i>Forward Document</button>
                                <button class="dv-btn y" onclick="editDocumentFromView(<?= $doc['id'] ?>)"><i class="fas fa-pencil-alt"></i>Edit Document</button>
                                <hr class="dv-divider">
                                <a href="document_list.php" class="dv-btn s"><i class="fas fa-arrow-left"></i>Back to List</a>
                                <button class="dv-btn b" onclick="window.print()"><i class="fas fa-print"></i>Print Record</button>
                                <hr class="dv-divider">
                                <?php if ($view_isOwner): ?>
                                    <?php if ($view_delReqStatus === 'pending'): ?>
                                    <button class="dv-btn" style="background:#fef9c3;color:#92400e;border:1px solid #fde68a;cursor:default;" disabled>
                                        <i class="fas fa-clock"></i>Delete Request Pending…
                                    </button>
                                    <?php elseif ($view_delReqStatus === 'rejected'): ?>
                                    <button class="dv-btn r" onclick="openDeleteRequestModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')">
                                        <i class="fas fa-trash"></i>Re-request Deletion
                                        <small style="font-size:.66rem;opacity:.75;margin-left:auto;">Previously rejected</small>
                                    </button>
                                    <?php else: ?>
                                    <button class="dv-btn r" onclick="openDeleteRequestModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')">
                                        <i class="fas fa-trash"></i>Request Deletion
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- ── Attachments ── -->
                        <div class="dv-card" id="attachmentsCard">
                            <div class="dv-card-hd">
                                <div class="dv-card-title">
                                    <i class="fas fa-paperclip"></i>Attachments
                                    <span id="attachCountBadge" style="background:var(--kc);color:#fff;border-radius:20px;padding:1px 8px;font-size:.62rem;margin-left:2px;">0</span>
                                </div>
                            </div>
                            <div class="dv-card-bd" style="padding:14px;">
                                <!-- File list renders here -->
                                <div id="viewAttachmentList">
                                    <div style="text-align:center;padding:18px;color:var(--t-sub);font-size:.8rem;"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
                                </div>
                                <!-- Upload drop zone (at bottom) -->
                                <div id="viewFileDropZone" style="margin-top:10px;border:2px dashed rgba(42,152,99,.25);border-radius:10px;padding:18px 14px;text-align:center;background:var(--bg-sub);cursor:pointer;transition:border-color .2s,background .2s;">
                                    <input type="file" id="viewFileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv" style="display:none;">
                                    <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:rgba(42,152,99,.4);display:block;margin-bottom:5px;"></i>
                                    <div style="font-size:.78rem;color:var(--t-muted);font-weight:600;">Drag &amp; drop to upload, or <span style="color:var(--kc);text-decoration:underline;">click to browse</span></div>
                                    <div style="font-size:.68rem;color:var(--t-sub);margin-top:2px;">PDF &middot; Word &middot; Excel &middot; Images &middot; max 20 MB each</div>
                                </div>
                                <!-- Upload progress -->
                                <div id="viewUploadProgress" style="display:none;margin-top:8px;">
                                    <div style="background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden;">
                                        <div id="viewProgressBar" style="background:#2a9863;height:100%;width:0%;transition:width .3s;"></div>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--t-muted);margin-top:3px;text-align:center;" id="viewProgressLabel">Uploading…</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DELETE REQUEST MODAL -->
<?php if ($view_isOwner): ?>
<div class="modal fade" id="viewDeleteRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:var(--r-lg);overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="modal-header" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);color:#fff;border:none;padding:16px 22px;">
                <h5 class="modal-title" style="font-weight:700;font-size:.95rem;"><i class="fas fa-trash-alt mr-2"></i>Request Document Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:20px 22px;">
                <input type="hidden" id="viewDeleteReqDocId">
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--r-md);padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:#92400e;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Requesting deletion of <strong id="viewDeleteReqDocNum"></strong>.
                    This requires <strong>Masteradmin approval</strong> before the document is permanently removed.
                </div>
                <div class="form-group mb-0">
                    <label style="font-weight:700;font-size:.79rem;">Reason for deletion <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="viewDeleteReqReason" rows="3"
                              style="resize:none;" placeholder="Please explain why this document should be deleted..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="border:none;padding:12px 22px;background:#f9fafb;">
                <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" style="font-weight:600;">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm font-weight-bold" id="viewSubmitDeleteRequestBtn">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Request
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- FORWARD MODAL (IMO removed) -->
<div class="modal fade" id="forwardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:var(--r-lg);overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="modal-header" style="background:linear-gradient(135deg,#1c4d38,#24e78f);color:#fff;border:none;padding:16px 22px;">
                <h5 class="modal-title" style="font-weight:700;font-size:.95rem;"><i class="fas fa-share mr-2"></i>Forward Document</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:20px 22px;">
                <form id="forwardDocumentForm">
                    <input type="hidden" name="action" value="forward">
                    <input type="hidden" name="id" id="fwdDocId">
                    <input type="hidden" name="forward_to" value="section">
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:var(--r-md);padding:9px 13px;margin-bottom:14px;font-size:.82rem;"><i class="fas fa-file-alt mr-1" style="color:#0284c7;"></i> Forwarding: <strong id="fwdDocNumber" class="text-primary"></strong></div>
                    <div class="form-group"><label class="font-weight-bold" style="font-size:.79rem;">Forwarded By</label><input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($view_user['full_name']??'') ?>" readonly style="background:#f8fafc;"><small class="text-muted" style="font-size:.74rem;"><?= htmlspecialchars($view_user['section_name']??'') ?><?= !empty($view_user['unit_name'])?' · '.htmlspecialchars($view_user['unit_name']):'' ?></small></div>
                    <div class="form-group"><label class="font-weight-bold" style="font-size:.79rem;">Section <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="fwd_to_section_id" id="fwdVToSectionSelect"><option value="">— Select Section —</option><?php foreach ($view_sec_arr as $sl): ?><option value="<?= $sl['section_id'] ?>"><?= htmlspecialchars($sl['section_name']) ?> (<?= htmlspecialchars($sl['section_code']) ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group" id="fwdVUnitGroup" style="display:none;"><label class="font-weight-bold" style="font-size:.79rem;">Unit <small class="text-muted font-weight-normal">(optional)</small></label><select class="form-control form-control-sm" name="fwd_to_unit_id" id="fwdVToUnitSelect"><option value="">— Entire Section —</option></select></div>
                    <input type="hidden" name="fwd_date" id="fwdVDate">
                    <div class="form-group mb-0"><label class="font-weight-bold" style="font-size:.79rem;">Remarks</label><textarea class="form-control form-control-sm" name="fwd_remarks" rows="2" placeholder="Reason or notes..." style="resize:none;"></textarea></div>
                </form>
            </div>
            <div class="modal-footer" style="border:none;padding:12px 22px;background:#f9fafb;">
                <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" style="font-weight:600;">Cancel</button>
                <button type="button" class="btn btn-sm" id="confirmVForwardBtn" style="background:#2a9863;color:#fff;font-weight:600;border-radius:var(--r-sm);padding:6px 16px;"><i class="fas fa-share mr-1"></i>Forward Document</button>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/mainfooter.php'; ?>

<script>
function openForwardModal(id, docNum) {
    $('#fwdDocId').val(id);
    $('#fwdDocNumber').text(docNum);
    $('#forwardDocumentForm')[0].reset();
    $('#fwdVSectionGroup').show(); $('#fwdVImoGroup').hide();
    $('input[name="_fwd_dest_type"][value="section"]').prop('checked', true);
    $('#forwardDestType').val('section');
    $('#fwdVToUnitSelect').html('<option value="">— Entire Section —</option>');
    $('#fwdVUnitGroup').hide();
    $('#fwdVToSectionSelect, #fwdVToOfficeSelect').prop('disabled', false);
    $('#forwardModal').modal('show');
}

$(document).ready(function() {
    $(document).on('change', '#fwdVToSectionSelect', function() {
        const secId = $(this).val();
        $('#fwdVToUnitSelect').html('<option value="">— Entire Section —</option>');
        if (!secId) { $('#fwdVUnitGroup').hide(); return; }
        $.get('document_actions.php', { action:'get_units', section_id:secId }, function(r) {
            if (r.success && r.units.length) {
                r.units.forEach(u => $('#fwdVToUnitSelect').append($('<option>').val(u.id).text(u.unit_name+' ('+u.unit_code+')')));
                $('#fwdVUnitGroup').show();
            } else { $('#fwdVUnitGroup').hide(); }
        }, 'json');
    });
    $('#confirmVForwardBtn').on('click', function() {
        const secId = $('#fwdVToSectionSelect').val();
        if (!secId) { Swal.fire('Error', 'Please select a destination section.', 'error'); return; }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Forwarding...');
        $.post('document_actions.php', $('#forwardDocumentForm').serialize(), function(r) {
            if (r.success) {
                let msg = 'Forwarded to <strong>'+(r.destination||'destination')+'</strong>.';
                if (r.focal_person) msg += '<br><small>Assigned to: '+r.focal_person+'</small>';
                Swal.fire({ icon: 'success', title: 'Forwarded!', html: msg, timer: 2000, showConfirmButton: false });
                $('#forwardModal').modal('hide');
                setTimeout(() => location.reload(), 2000);
            } else { Swal.fire('Error', r.message||'Forward failed.', 'error'); }
        }, 'json').fail(function(xhr) {
            Swal.fire('Server error.', 'Check console.', 'error');
            console.error(xhr.responseText);
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-share mr-1"></i>Forward Document'));
    });
});

function updateStatus() {
    const status = $('#quickStatusSelect').val();
    const $btn = $('.dv-save-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.post('document_actions.php', { action:'update_status', id:<?= $doc['id'] ?>, status:status }, function(r) {
        if (r.success) {
            Swal.fire({ icon: 'success', title: 'Status updated', text: 'Status changed to '+status.charAt(0).toUpperCase()+status.slice(1), timer: 1500, showConfirmButton: false });
            $('#statusBadge').attr('class','dv-pill pill-'+status).text(status.charAt(0).toUpperCase()+status.slice(1));
            setTimeout(() => location.reload(), 1500);
        } else { Swal.fire('Error', r.message||'Update failed.', 'error'); }
    }, 'json').fail(() => Swal.fire('Server error.', '', 'error'))
    .always(() => $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save'));
}

function editDocumentFromView(id) { window.location.href = 'document_list.php?edit='+id; }

// ── Delete Request Modal ──────────────────────────────────────────────────────
function openDeleteRequestModal(id, docNum) {
    document.getElementById('viewDeleteReqDocId').value = id;
    document.getElementById('viewDeleteReqDocNum').textContent = docNum;
    document.getElementById('viewDeleteReqReason').value = '';
    $('#viewDeleteRequestModal').modal('show');
}

$(document).on('click', '#viewSubmitDeleteRequestBtn', function() {
    const id     = document.getElementById('viewDeleteReqDocId').value;
    const reason = document.getElementById('viewDeleteReqReason').value.trim();
    if (!reason) {
        Swal.fire({ icon: 'warning', title: 'Reason Required', text: 'Please explain why you want to delete this document.' });
        return;
    }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...');
    $.post('document_actions.php', { action: 'request_delete', id, reason }, function(r) {
        $('#viewDeleteRequestModal').modal('hide');
        if (r.success) {
            Swal.fire({ icon: 'success', title: 'Request Submitted', text: r.message, timer: 2500, showConfirmButton: false, timerProgressBar: true })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Request Failed', text: r.message || 'An error occurred.' });
        }
    }, 'json').fail(function() {
        Swal.fire({ icon: 'error', title: 'Server Error', text: 'Unexpected server error.' });
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Submit Request'));
});

// ── Notification Banner (unread delete-workflow alerts) ───────────────────────
<?php if (!empty($view_notifications)): ?>
$(function() {
    const notifs = <?= json_encode($view_notifications) ?>;
    if (!notifs.length) return;
    function escH(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
    let html = '<ul class="mb-0 pl-3">';
    notifs.forEach(n => {
        const icon = n.type === 'delete_approved' ? '✅' : n.type === 'delete_rejected' ? '❌' : '🔔';
        html += `<li>${icon} ${escH(n.message)}</li>`;
    });
    html += '</ul>';
    const hasApproved = notifs.some(n => n.type === 'delete_approved');
    const hasRejected = notifs.some(n => n.type === 'delete_rejected');
    const alertType   = hasApproved ? 'success' : hasRejected ? 'danger' : 'info';
    $('<div class="alert alert-'+alertType+' alert-dismissible fade show mx-3 mt-2" role="alert">' +
      '<strong><i class="fas fa-bell mr-1"></i> Document Notifications</strong>' + html +
      '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>')
      .insertBefore('.content .container-fluid > .dv-layout');
    $.post('document_actions.php', { action: 'mark_notifications_read' });
});
<?php endif; ?>

// ══════════════════════════════════════════════════════════════
//  FILE ATTACHMENTS  (document_view.php)
// ══════════════════════════════════════════════════════════════
const VIEW_DOC_ID = <?= (int)$doc['id'] ?>;

const FILE_ICONS_VIEW = {
    'application/pdf': { icon: 'fa-file-pdf',        color: '#dc2626' },
    'application/msword': { icon: 'fa-file-word',     color: '#2563eb' },
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': { icon: 'fa-file-word', color: '#2563eb' },
    'application/vnd.ms-excel': { icon: 'fa-file-excel', color: '#16a34a' },
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': { icon: 'fa-file-excel', color: '#16a34a' },
    'application/vnd.ms-powerpoint': { icon: 'fa-file-powerpoint', color: '#ea580c' },
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': { icon: 'fa-file-powerpoint', color: '#ea580c' },
};
function vFileIcon(mime) {
    if (FILE_ICONS_VIEW[mime]) return FILE_ICONS_VIEW[mime];
    if (mime && mime.startsWith('image/')) return { icon: 'fa-file-image', color: '#7c3aed' };
    return { icon: 'fa-file-alt', color: '#6b7280' };
}
function vFmtBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}
function vEsc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

function isPreviewable(mime) {
    return ['application/pdf','image/jpeg','image/png','image/gif','image/webp'].includes(mime);
}

function loadAttachments() {
    $.get('document_actions.php', { action: 'get_files', document_id: VIEW_DOC_ID }, function(r) {
        const files = r.files || [];
        $('#attachCountBadge').text(files.length);
        if (!files.length) {
            $('#viewAttachmentList').html(
                '<div style="text-align:center;padding:22px 16px;color:var(--t-sub);font-size:.82rem;">' +
                '<i class="fas fa-paperclip" style="font-size:1.8rem;opacity:.25;display:block;margin-bottom:8px;"></i>' +
                '<div style="font-weight:600;color:var(--t-muted);">No attachments yet</div>' +
                '<div style="font-size:.74rem;margin-top:3px;">Drop files or click the area below to upload</div></div>'
            );
            return;
        }
        let html = '<div style="display:flex;flex-direction:column;gap:7px;margin-bottom:4px;">';
        files.forEach(f => {
            const fi  = vFileIcon(f.mime_type);
            const fid = f.id;
            const isImg = f.mime_type && f.mime_type.startsWith('image/');
            const canPreview = isPreviewable(f.mime_type);
            const thumbHtml = isImg
                ? `<div style="width:40px;height:40px;border-radius:7px;overflow:hidden;flex-shrink:0;background:#f3f4f6;border:1px solid var(--border);">
                     <img src="document_actions.php?action=download_file&file_id=${fid}&inline=1" alt="" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                   </div>`
                : `<div style="width:40px;height:40px;border-radius:7px;flex-shrink:0;background:${fi.color}15;border:1px solid ${fi.color}30;display:flex;align-items:center;justify-content:center;">
                     <i class="fas ${fi.icon}" style="color:${fi.color};font-size:1.1rem;"></i>
                   </div>`;
            html += `
            <div id="vFile_${fid}" style="display:flex;align-items:center;gap:10px;background:var(--bg-sub);border:1px solid var(--border);border-radius:10px;padding:9px 11px;transition:box-shadow .15s;">
                ${thumbHtml}
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.82rem;font-weight:600;color:#1c4d38;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${vEsc(f.original_name)}">${vEsc(f.original_name)}</div>
                    <div style="font-size:.69rem;color:var(--t-sub);margin-top:2px;">
                        <span style="background:#f0faf5;color:#2a9863;border-radius:4px;padding:1px 6px;font-weight:600;">${vFmtBytes(f.file_size)}</span>
                        &nbsp;${vEsc(f.uploaded_by_name||'Unknown')} &middot; ${f.uploaded_at.substring(0,10)}
                    </div>
                </div>
                <div style="display:flex;gap:5px;flex-shrink:0;">
                    ${canPreview ? `<button onclick="previewFile(${fid},'${vEsc(f.original_name)}','${f.mime_type}')" title="Preview" style="width:30px;height:30px;border:none;border-radius:7px;background:#eff6ff;color:#2563eb;cursor:pointer;font-size:.8rem;display:flex;align-items:center;justify-content:center;transition:filter .1s;" onmouseover="this.style.filter='brightness(.88)'" onmouseout="this.style.filter='none'"><i class="fas fa-eye"></i></button>` : ''}
                    <a href="document_actions.php?action=download_file&file_id=${fid}&inline=0" download="${vEsc(f.original_name)}" title="Download" style="width:30px;height:30px;border:none;border-radius:7px;background:#f0faf5;color:#2a9863;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:filter .1s;" onmouseover="this.style.filter='brightness(.88)'" onmouseout="this.style.filter='none'"><i class="fas fa-download"></i></a>
                    <button onclick="deleteAttachment(${fid},'${vEsc(f.original_name)}')" title="Delete" style="width:30px;height:30px;border:none;border-radius:7px;background:#fef2f2;color:#dc2626;cursor:pointer;font-size:.8rem;display:flex;align-items:center;justify-content:center;transition:filter .1s;" onmouseover="this.style.filter='brightness(.88)'" onmouseout="this.style.filter='none'"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        });
        html += '</div>';
        $('#viewAttachmentList').html(html);
    }, 'json').fail(() => {
        $('#viewAttachmentList').html('<div style="color:#ef4444;font-size:.8rem;text-align:center;padding:12px;">Failed to load attachments.</div>');
    });
}

function uploadViewFiles(fileList) {
    if (!fileList.length) return;
    const fd = new FormData();
    fd.append('action', 'upload_file');
    fd.append('document_id', VIEW_DOC_ID);
    Array.from(fileList).forEach(f => fd.append('files[]', f));

    $('#viewUploadProgress').show();
    $('#viewProgressBar').css('width', '0%');
    $('#viewProgressLabel').text('Uploading…');

    $.ajax({
        url: 'document_actions.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        xhr: function() {
            const x = new window.XMLHttpRequest();
            x.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    $('#viewProgressBar').css('width', pct + '%');
                    $('#viewProgressLabel').text('Uploading… ' + pct + '%');
                }
            });
            return x;
        },
        success: function(r) {
            $('#viewUploadProgress').hide();
            if (r.errors && r.errors.length) {
                Swal.fire('Some files failed', r.errors.join('<br>'), 'warning');
            }
            loadAttachments();
        },
        error: function() {
            $('#viewUploadProgress').hide();
            Swal.fire('Upload Failed', 'An error occurred while uploading.', 'error');
        }
    });
}

function deleteAttachment(fileId, fileName) {
    Swal.fire({
        title: 'Delete attachment?',
        html: `Remove <strong>${vEsc(fileName)}</strong> from this document?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Delete',
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('document_actions.php', { action: 'delete_file', file_id: fileId }, function(r) {
            if (r.success) {
                $('#vFile_' + fileId).fadeOut(200, function() { $(this).remove(); loadAttachments(); });
            } else {
                Swal.fire('Failed', r.message || 'Could not delete file.', 'error');
            }
        }, 'json');
    });
}

// Preview modal for PDF and images
function previewFile(fileId, fileName, mimeType) {
    const url = `document_actions.php?action=download_file&file_id=${fileId}&inline=1`;
    const isPDF = (mimeType === 'application/pdf') || fileName.toLowerCase().endsWith('.pdf');
    const isImg = mimeType && mimeType.startsWith('image/');

    let bodyHtml;
    if (isPDF) {
        bodyHtml = `<div style="background:#f1f3f5;border-radius:8px;overflow:hidden;">
            <iframe src="${url}" style="width:100%;height:82vh;border:none;display:block;"></iframe>
        </div>`;
    } else if (isImg) {
        bodyHtml = `<div style="background:#0f172a;border-radius:8px;padding:8px;display:flex;align-items:center;justify-content:center;min-height:200px;">
            <img src="${url}" alt="${vEsc(fileName)}" style="max-width:100%;max-height:80vh;border-radius:6px;display:block;object-fit:contain;">
        </div>`;
    } else {
        bodyHtml = `<div style="text-align:center;padding:30px;color:#6b7280;">Preview not available for this file type.</div>`;
    }

    Swal.fire({
        title: `<span style="font-size:.88rem;font-weight:600;color:#374151;word-break:break-all;">${vEsc(fileName)}</span>`,
        html: bodyHtml,
        width: isPDF ? '92%' : '80%',
        padding: '0',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'swal2-file-preview-popup', closeButton: 'swal2-preview-close' },
    });
}

$(function() {
    // Load attachments on page load
    loadAttachments();

    // File input change
    document.getElementById('viewFileInput').addEventListener('change', function() {
        uploadViewFiles(this.files);
        this.value = '';
    });

    // Drop zone
    const dz = document.getElementById('viewFileDropZone');
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = '#2a9863'; dz.style.background = '#d1fae5'; });
    dz.addEventListener('dragleave', () => { dz.style.borderColor = 'rgba(42,152,99,.3)'; dz.style.background = 'var(--bg-sub)'; });
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.style.borderColor = 'rgba(42,152,99,.3)'; dz.style.background = 'var(--bg-sub)';
        uploadViewFiles(e.dataTransfer.files);
    });
    dz.addEventListener('click', () => document.getElementById('viewFileInput').click());
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>