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
           COALESCE(s1.section_name, o_from.office_name) AS from_section,
           s1.section_code AS from_code,
           s2.section_name AS to_section,   s2.section_code AS to_code,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit,
           (e1.section_id IS NULL AND e1.office_id IS NOT NULL) AS from_is_office,
           o_from.office_name AS from_office_name,
           CONCAT(TRIM(e1.first_name),' ',TRIM(e1.last_name)) AS forwarded_by_name_emp,
           CONCAT(TRIM(e2.first_name),' ',TRIM(e2.last_name)) AS forwarded_to_name_emp,
           CONCAT(TRIM(er.first_name),' ',TRIM(er.last_name)) AS received_by_name,
           (SELECT df_last.fwd_date FROM document_forwards df_last
            WHERE df_last.document_id = dr.id
            ORDER BY df_last.id DESC LIMIT 1) AS last_fwd_date
    FROM document_records dr
    LEFT JOIN document_types dt    ON dr.document_type_id        = dt.id
    LEFT JOIN section        s1    ON dr.from_section_id         = s1.section_id
    LEFT JOIN section        s2    ON dr.forwarded_to_section_id = s2.section_id
    LEFT JOIN unit_section   us1   ON dr.from_unit_id            = us1.unit_id
    LEFT JOIN unit_section   us2   ON dr.forwarded_to_unit_id    = us2.unit_id
    LEFT JOIN employee       e1    ON dr.forwarded_by_emp_id     = e1.emp_id
    LEFT JOIN employee       e2    ON dr.forwarded_to_emp_id     = e2.emp_id
    LEFT JOIN employee       er    ON dr.received_by_emp_id      = er.emp_id
    LEFT JOIN office         o_from ON e1.office_id              = o_from.office_id
    WHERE dr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
if (!$doc) { header('Location: document_list.php'); exit; }

$kind_colors = ['incoming' => '#2563eb', 'outgoing' => '#059669', 'external' => '#7c3aed'];
$kind_lights = ['incoming' => '#eff6ff', 'outgoing' => '#ecfdf5', 'external' => '#f5f3ff'];
$kind_color  = $kind_colors[$doc['kind']] ?? '#1a3c5e';
$kind_light  = $kind_lights[$doc['kind']] ?? '#f0f4f8';
$kind_icons  = ['incoming' => 'fa-inbox', 'outgoing' => 'fa-paper-plane', 'external' => 'fa-exchange-alt'];
$kind_icon   = $kind_icons[$doc['kind']] ?? 'fa-file-alt';

$fhstmt = $db->prepare("
    SELECT df.*,
           TRIM(eb.first_name) AS fwd_by_fname, TRIM(eb.last_name) AS fwd_by_lname,
           TRIM(et.first_name) AS fwd_to_fname, TRIM(et.last_name) AS fwd_to_lname,
           TRIM(er.first_name) AS fwd_recv_fname, TRIM(er.last_name) AS fwd_recv_lname,
           CONCAT(TRIM(eb.first_name),' ',TRIM(eb.last_name)) AS fwd_by_name,
           CONCAT(TRIM(et.first_name),' ',TRIM(et.last_name)) AS fwd_to_name,
           CONCAT(TRIM(er.first_name),' ',TRIM(er.last_name)) AS fwd_received_by_name,
           s.section_name  AS to_section_name,
           s.section_code  AS to_section_code,
           us.unit_name    AS to_unit_name,
           us.unit_code    AS to_unit_code,
           o.office_name   AS to_office_name,
           sb.section_code AS from_section_code,
           usb.unit_code   AS from_unit_code
    FROM document_forwards df
    LEFT JOIN employee       eb  ON df.fwd_by_emp_id      = eb.emp_id
    LEFT JOIN employee       et  ON df.fwd_to_emp_id      = et.emp_id
    LEFT JOIN employee       er  ON df.received_by_emp_id = er.emp_id
    LEFT JOIN section        s   ON df.fwd_to_section_id  = s.section_id
    LEFT JOIN unit_section   us  ON df.fwd_to_unit_id     = us.unit_id
    LEFT JOIN office         o   ON df.fwd_to_office_id   = o.office_id
    LEFT JOIN section        sb  ON eb.section_id         = sb.section_id
    LEFT JOIN unit_section   usb ON eb.unit_section_id    = usb.unit_id
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


// ── Initials helpers ────────────────────────────────────────────────────────
if (!function_exists("nameInitials")) {
function nameInitials(string $fullName): string {
    $fullName = trim($fullName);
    if (!$fullName) return "?";
    $parts = preg_split("/\\s+/", $fullName);
    $initials = "";
    foreach ($parts as $p) { if ($p !== "") $initials .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $initials ?: "?";
}}
if (!function_exists("fhInitials")) {
function fhInitials(string $first, string $last): string {
    return nameInitials(trim($first) . " " . trim($last));
}}
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

// ── Is current user the IMO manager/office-staff for this document's office? ─
// Allows them to forward, edit, and change status on documents sent to their office.
$view_isOfficeRecipient = false;
if ($view_logged && !empty($doc['forwarded_to_office_id'])) {
    $fwd_office = (int)$doc['forwarded_to_office_id'];
    // Check if office manager
    $vorChk = $db->prepare("SELECT 1 FROM office WHERE office_id = ? AND manager_emp_id = ? LIMIT 1");
    if ($vorChk) {
        $vorChk->bind_param("ii", $fwd_office, $view_logged);
        $vorChk->execute();
        $view_isOfficeRecipient = $vorChk->get_result()->num_rows > 0;
    }
    // Check if manager_office_staff
    if (!$view_isOfficeRecipient) {
        $vorChk2 = $db->prepare("
            SELECT 1 FROM employee
            WHERE emp_id = ? AND office_id = ?
              AND (is_manager = 1 OR is_manager_office_staff = 1)
            LIMIT 1
        ");
        if ($vorChk2) {
            $vorChk2->bind_param("ii", $view_logged, $fwd_office);
            $vorChk2->execute();
            $view_isOfficeRecipient = $vorChk2->get_result()->num_rows > 0;
        }
    }
}
// Once forwarded (to a section or IMO office), only the creator or Masteradmin may edit.
// Office recipients retain the ability to forward onward, but not to edit document details.
$view_canEdit     = $view_isOwner || $view_isMasteradmin;
$view_canForward  = $view_isOwner || $view_isMasteradmin || $view_isOfficeRecipient;
$view_canActOnDoc = $view_canEdit || $view_canForward; // kept for any legacy checks

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        .pill-external { background:#ede9fe; color:#5b21b6; }
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
        /* ── Forwarding History 3-col grid ───────────────────────────────── */
        .fh-grid { display:grid; grid-template-columns:1fr 1px 1fr 1px 1fr; }
        @media (max-width:767px) {
            .fh-grid { grid-template-columns:1fr; }
            .fh-divider-v { display:none; }
            .fh-col { border-bottom:1px solid var(--border); }
            .fh-col:last-child { border-bottom:none; }
        }
        body.dark-mode .fh-col-header { background:rgba(36,231,143,.04); }
        body.dark-mode .fh-row:nth-child(even) { background:rgba(36,231,143,.02); }
        @media print {
            body > *:not(#receiptPrintArea) { display:none!important; }
            #receiptPrintArea { display:block!important; position:static!important; }
            .wrapper, .content-wrapper, .main-header, .main-sidebar, .main-footer { display:none!important; }
        }
        /* ── Receipt print area (hidden on screen) ── */
        #receiptPrintArea {
            display: none;
        }
        /* ── Receipt modal ── */
        #receiptPreviewModal .receipt-paper {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 28px 30px 22px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #111827;
            max-width: 520px;
            margin: 0 auto;
        }
        #receiptPreviewModal .rcp-header {
            text-align: center;
            border-bottom: 2px solid #1c4d38;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        #receiptPreviewModal .rcp-org {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #1c4d38;
        }
        #receiptPreviewModal .rcp-title {
            font-size: 1rem;
            font-weight: 800;
            color: #111827;
            margin: 4px 0 2px;
        }
        #receiptPreviewModal .rcp-sub {
            font-size: .72rem;
            color: #6b7280;
        }
        #receiptPreviewModal .rcp-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 5px 0;
            border-bottom: 1px dashed #e5e7eb;
            font-size: .78rem;
            gap: 12px;
        }
        #receiptPreviewModal .rcp-row:last-child { border-bottom: none; }
        #receiptPreviewModal .rcp-lbl {
            color: #6b7280;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #receiptPreviewModal .rcp-val {
            color: #111827;
            font-weight: 500;
            text-align: right;
            word-break: break-word;
        }
        #receiptPreviewModal .rcp-qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 2px solid #1c4d38;
            gap: 6px;
        }
        #receiptPreviewModal .rcp-qr-label {
            font-size: .65rem;
            color: #6b7280;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        #receiptPreviewModal .rcp-footer-note {
            font-size: .62rem;
            color: #9ca3af;
            text-align: center;
            margin-top: 10px;
        }
        /* ── Actual printable receipt (injected to DOM, printed) ── */
        #receiptPrintArea {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #111;
            padding: 28px 32px;
            max-width: 780px;
            margin: 0 auto;
        }
        #receiptPrintArea .rp-header {
            text-align: center;
            border-bottom: 2.5px solid #1c4d38;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        #receiptPrintArea .rp-org  { font-size: 9pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #1c4d38; }
        #receiptPrintArea .rp-title { font-size: 13pt; font-weight: 800; margin: 4px 0 2px; }
        #receiptPrintArea .rp-sub  { font-size: 8pt; color: #6b7280; }
        /* Two-column body */
        #receiptPrintArea .rp-body-columns {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        #receiptPrintArea .rp-col-left  { flex: 1; min-width: 0; }
        #receiptPrintArea .rp-col-right { width: 240px; flex-shrink: 0; }
        #receiptPrintArea table.rp-table { width: 100%; border-collapse: collapse; }
        #receiptPrintArea table.rp-table td { padding: 3px 3px; font-size: 7.5pt; border-bottom: 1px dashed #ddd; vertical-align: top; line-height: 1.3; }
        #receiptPrintArea table.rp-table td:first-child { color: #555; font-weight: 600; white-space: nowrap; width: 38%; }
        #receiptPrintArea table.rp-table td:last-child  { color: #111; font-weight: 500; word-break: break-word; }
        /* Clamp long text fields */
        #receiptPrintArea table.rp-table td.rp-clamp {
            max-height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        /* Forwarding history panel */
        #receiptPrintArea .rp-fh-panel { border: 1.5px solid #1c4d38; border-radius: 6px; overflow: hidden; font-size: 7.5pt; }
        #receiptPrintArea .rp-fh-head  { background: #1c4d38; color: #fff; padding: 6px 9px; font-weight: 700; font-size: 7pt; text-transform: uppercase; letter-spacing: .07em; display: flex; justify-content: space-between; align-items: center; }
        #receiptPrintArea .rp-fh-badge { background: rgba(255,255,255,.25); border-radius: 20px; padding: 1px 7px; font-size: 6.5pt; }
        #receiptPrintArea table.rp-fh-table { width: 100%; border-collapse: collapse; font-size: 6.5pt; }
        #receiptPrintArea table.rp-fh-table th { padding: 3px 5px; background: #f0faf5; color: #1c4d38; font-weight: 700; text-align: left; border-bottom: 1px solid #d1fae5; border-right: 1px solid #d1fae5; }
        #receiptPrintArea table.rp-fh-table th:last-child { border-right: none; }
        #receiptPrintArea table.rp-fh-table td { padding: 4px 5px; border-bottom: 1px solid #eee; border-right: 1px solid #eee; vertical-align: top; line-height: 1.3; }
        #receiptPrintArea table.rp-fh-table td:last-child { border-right: none; max-width: 70px; word-break: break-word; }
        #receiptPrintArea table.rp-fh-table tr:last-child td { border-bottom: none; }
        /* Forwarding history remarks — show full text */
        #receiptPrintArea .rp-fh-remark {
            word-break: break-word;
        }
        #receiptPrintArea .rp-qr-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            border-top: 2.5px solid #1c4d38;
            padding-top: 14px;
            gap: 6px;
        }
        #receiptPrintArea .rp-qr-lbl { font-size: 7.5pt; color: #6b7280; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; }
        #receiptPrintArea .rp-footer { font-size: 7pt; color: #aaa; text-align: center; margin-top: 10px; }
        /* ── Print media rules ── */
        @media print {
            body > *:not(#receiptPrintArea) { display: none !important; }
            #receiptPrintArea { display: block !important; padding: 20px 28px; max-width: 100%; }
            #receiptPrintArea .rp-body-columns { display: flex !important; flex-direction: row !important; gap: 18px; }
            #receiptPrintArea .rp-col-left  { flex: 1 !important; min-width: 0; }
            #receiptPrintArea .rp-col-right { width: 240px !important; flex-shrink: 0; }
            #receiptPrintArea .rp-fh-panel  { page-break-inside: avoid; }
        }
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
        body.dark-mode .pill-external { background:#2e1065; color:#c4b5fd; }
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
                            <div class="dv-card-bd"><div class="dv-flow"><div class="dv-fnode"><div class="flbl"><i class="fas fa-sign-out-alt mr-1"></i>From</div><div class="fname"><?= htmlspecialchars($doc['forwarded_by_name_emp'] ?: ($doc['forwarded_by_name'] ?: '—')) ?></div><div class="fsub"><?php if (!empty($doc['from_is_office']) && !empty($doc['from_office_name'])): ?><i class="fas fa-star mr-1" style="color:#2563eb;font-size:.65rem;"></i><?= htmlspecialchars($doc['from_office_name']) ?><?php else: ?><?= htmlspecialchars($doc['from_section'] ?: 'External / Not Specified') ?><?php endif; ?></div><?php if (!empty($doc['from_unit'])): ?><div class="fsub"><i class="fas fa-layer-group mr-1" style="font-size:.58rem;"></i><?= htmlspecialchars($doc['from_unit']) ?></div><?php endif; ?></div><div class="dv-farrow"><i class="fas fa-arrow-right"></i></div><div class="dv-fnode"><div class="flbl"><i class="fas fa-sign-in-alt mr-1"></i>To</div><div class="fname"><?= htmlspecialchars($doc['forwarded_to_name_emp'] ?: ($doc['forwarded_to'] ?: 'Not yet forwarded')) ?></div><div class="fsub"><?= htmlspecialchars($doc['to_section'] ?: 'Not Specified') ?></div><?php if (!empty($doc['to_unit'])): ?><div class="fsub"><i class="fas fa-layer-group mr-1" style="font-size:.58rem;"></i><?= htmlspecialchars($doc['to_unit']) ?></div><?php endif; ?></div></div></div>
                        </div>
                        <!-- Forwarding History -->
                        <div class="dv-card">
                            <div class="dv-card-hd">
                                <div class="dv-card-title">
                                    <i class="fas fa-history"></i>Forwarding History
                                    <span style="background:var(--kc);color:#fff;border-radius:20px;padding:1px 8px;font-size:.62rem;margin-left:2px;"><?= count($fwd_history) ?></span>
                                </div>
                                <?php if ($view_canForward): ?>
                                <button onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')" style="background:var(--kc);color:#fff;border:none;border-radius:var(--r-sm);padding:5px 12px;font-size:.76rem;font-weight:600;cursor:pointer;">
                                    <i class="fas fa-share mr-1"></i>Forward Again
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="dv-card-bd" style="padding:0;">

                                <?php if (!empty($fwd_history)): ?>

                                <!-- Column headers -->
                                <div style="display:grid;grid-template-columns:1fr 1px 1fr 1px 1fr;background:var(--bg-sub);border-bottom:1px solid var(--border);padding:8px 0;">
                                    <div style="padding:0 16px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--t-sub);"><i class="fas fa-route mr-1"></i>Route</div>
                                    <div style="background:var(--border);"></div>
                                    <div style="padding:0 16px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--t-sub);"><i class="fas fa-comment-alt mr-1"></i>Remarks</div>
                                    <div style="background:var(--border);"></div>
                                    <div style="padding:0 16px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--t-sub);"><i class="fas fa-user-check mr-1"></i>Received By</div>
                                </div>

                                <?php
                                $lastIdx = count($fwd_history) - 1;
                                // received_by and date_received are on the document (last hop fallback)
                                $recvName = trim($doc['received_by_name'] ?? '');
                                $recvDate = safeDate($doc['date_received'] ?? '');
                                foreach ($fwd_history as $idx => $h):
                                    $fds = safeDate($h['fwd_date']) ?? null;
                                    // Per-row received info (from document_forwards.received_by_emp_id)
                                    $rowRecvName = trim($h['fwd_received_by_name'] ?? '');
                                    $rowRecvDate = !empty($h['received_at']) ? safeDate($h['received_at']) : null;
                                    // Fallback: last row uses document-level received info if forward row not stamped yet
                                    $isLast = ($idx === $lastIdx);
                                    if ($isLast && !$rowRecvName && $recvName) {
                                        $rowRecvName = $recvName;
                                        $rowRecvDate = $rowRecvDate ?? $recvDate;
                                    }
                                    if (!empty($h['to_office_name'])) {
                                        $di='fa-star'; $dc='#2563eb'; $db_='#dbeafe';
                                        $dl=$h['to_office_name']; $ds='IMO Office';
                                    } elseif (!empty($h['to_section_name'])) {
                                        $di='fa-building'; $dc='#059669'; $db_='#d1fae5';
                                        $dl=$h['to_section_name'];
                                        $ds=!empty($h['to_unit_name'])?$h['to_unit_name']:'';
                                    } else {
                                        $di='fa-user'; $dc='#7c3aed'; $db_='#ede9fe';
                                        $dl=$h['fwd_to_name']?:'—'; $ds='';
                                    }
                                    $rowBg  = ($idx % 2 === 0) ? '' : 'background:rgba(0,0,0,.018);';
                                ?>
                                <div style="display:grid;grid-template-columns:1fr 1px 1fr 1px 1fr;border-bottom:<?= $isLast ? 'none' : '1px solid var(--border)' ?>;<?= $rowBg ?>min-height:72px;">

                                    <!-- Col 1 : Route -->
                                    <div style="padding:12px 16px;display:flex;gap:10px;align-items:flex-start;">
                                        <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;padding-top:2px;">
                                            <div style="width:26px;height:26px;border-radius:50%;background:<?= $dc ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;box-shadow:0 0 0 3px #fff,0 0 0 4px <?= $dc ?>44;"><?= $idx+1 ?></div>
                                        </div>
                                        <div style="min-width:0;flex:1;">
                                            <!-- From name -->
                                            <div style="font-size:.82rem;font-weight:600;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($h['fwd_by_name'] ?: '—') ?></div>
                                            <!-- Arrow + To name -->
                                            <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                                                <i class="fas fa-arrow-right" style="color:var(--kc);font-size:.6rem;flex-shrink:0;"></i>
                                                <div style="font-size:.82rem;font-weight:600;color:#1a3c5e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($h['fwd_to_name'] ?: '—') ?></div>
                                            </div>
                                            <!-- Destination chips -->
                                            <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:5px;">
                                                <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;font-size:.65rem;font-weight:600;background:<?= $db_ ?>;color:<?= $dc ?>;"><i class="fas <?= $di ?>" style="font-size:.6rem;"></i><?= htmlspecialchars($dl) ?></span>
                                                <?php if ($ds): ?>
                                                <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;font-size:.65rem;font-weight:600;background:#f3f4f6;color:#6b7280;"><?= htmlspecialchars($ds) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Date forwarded -->
                                            <?php if ($fds): ?>
                                            <div style="font-size:.69rem;color:var(--t-sub);margin-top:5px;display:flex;align-items:center;gap:3px;">
                                                <i class="fas fa-clock" style="color:#9ca3af;"></i><?= $fds ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Divider -->
                                    <div style="background:var(--border);"></div>

                                    <!-- Col 2 : Remarks -->
                                    <div style="padding:12px 16px;display:flex;align-items:flex-start;">
                                        <?php if (!empty($h['fwd_remarks'])): ?>
                                        <div style="font-size:.82rem;color:#374151;line-height:1.55;font-style:italic;">
                                            <i class="fas fa-quote-left" style="font-size:.58rem;color:#d1d5db;margin-right:4px;vertical-align:middle;"></i><?= nl2br(htmlspecialchars($h['fwd_remarks'])) ?>
                                        </div>
                                        <?php else: ?>
                                        <div style="font-size:.78rem;color:#d1d5db;font-style:italic;">No remarks</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Divider -->
                                    <div style="background:var(--border);"></div>

                                    <!-- Col 3 : Received By (per forwarding hop) -->
                                    <div style="padding:12px 16px;display:flex;align-items:flex-start;">
                                        <?php if ($rowRecvName): ?>
                                        <div>
                                            <div style="font-size:.82rem;font-weight:600;color:#065f46;"><?= htmlspecialchars($rowRecvName) ?></div>
                                            <?php if ($rowRecvDate): ?>
                                            <div style="font-size:.69rem;color:var(--t-sub);margin-top:4px;display:flex;align-items:center;gap:3px;">
                                                <i class="fas fa-calendar-check" style="color:#6ee7b7;"></i><?= $rowRecvDate ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div style="font-size:.78rem;color:#d1d5db;font-style:italic;">Not yet received</div>
                                        <?php endif; ?>
                                    </div>

                                </div><!-- end row -->
                                <?php endforeach; ?>

                                <?php else: ?>
                                <div class="dv-tl-empty" style="padding:24px 16px;"><i class="fas fa-share-alt mb-2 d-block" style="font-size:1.4rem;opacity:.3;"></i>No forwarding history yet.</div>
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
                            <div class="dv-card-bd"><div class="dv-status-row"><select class="form-control form-control-sm" id="quickStatusSelect" <?= (!$view_canActOnDoc) ? 'disabled title="Only the document creator, a Masteradmin, or the assigned office recipient can change the status"' : '' ?>><option value="pending" <?= $doc['status']==='pending' ?'selected':'' ?>>Pending</option><option value="received" <?= $doc['status']==='received' ?'selected':'' ?>>Received</option><option value="returned" <?= $doc['status']==='returned' ?'selected':'' ?>>Returned</option><option value="completed" <?= $doc['status']==='completed' ?'selected':'' ?>>Completed</option><option value="archived" <?= $doc['status']==='archived' ?'selected':'' ?>>Archived</option></select><button class="dv-save-btn" onclick="updateStatus()" <?= (!$view_canActOnDoc) ? 'disabled title="Only the document creator, a Masteradmin, or the assigned office recipient can change the status" style="opacity:.45;cursor:not-allowed;"' : '' ?>><i class="fas fa-check mr-1"></i>Save</button></div></div>
                        </div>
                        <!-- Actions -->
                        <div class="dv-card">
                            <div class="dv-card-hd"><div class="dv-card-title"><i class="fas fa-bolt"></i>Actions</div></div>
                            <div class="dv-card-bd" style="padding:14px;">
                                <?php if ($view_canForward): ?>
                                <button class="dv-btn g" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-share"></i>Forward Document</button>
                                <?php else: ?>
                                <button class="dv-btn" style="background:#f1f5f9;color:#9ca3af;border:1px solid #e2e8f0;cursor:not-allowed;" disabled title="Only the document creator can forward this document"><i class="fas fa-share"></i>Forward Document</button>
                                <?php endif; ?>
                                <?php if ($view_canEdit): ?>
                                <button class="dv-btn y" onclick="editDocumentFromView(<?= $doc['id'] ?>)"><i class="fas fa-pencil-alt"></i>Edit Document</button>
                                <?php else: ?>
                                <button class="dv-btn" style="background:#f1f5f9;color:#9ca3af;border:1px solid #e2e8f0;cursor:not-allowed;" disabled title="Only the document creator can edit this document"><i class="fas fa-pencil-alt"></i>Edit Document</button>
                                <?php endif; ?>
                                <hr class="dv-divider">
                                <a href="document_list.php" class="dv-btn s"><i class="fas fa-arrow-left"></i>Back to List</a>
                                <button class="dv-btn b" onclick="window.print()"><i class="fas fa-print"></i>Print Record</button>
                                <button class="dv-btn" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;" onclick="openReceiptModal()"><i class="fas fa-receipt"></i>Print Receipt</button>
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

<!-- ══════════════ PRINT RECEIPT MODAL ══════════════ -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18);">
            <div class="modal-header" style="background:linear-gradient(135deg,#1c4d38,#2a9863);color:#fff;border:none;padding:14px 20px;">
                <h5 class="modal-title" style="font-weight:700;font-size:.95rem;"><i class="fas fa-receipt mr-2"></i>Document Receipt Preview</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="background:#f9fafb;padding:22px;">
                <!-- Preview receipt rendered here — two-column layout -->
                <div id="receiptPreviewBody" style="font-family:'Segoe UI',Arial,sans-serif;color:#111827;max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:26px 28px;">

                    <!-- Title bar -->
                    <div style="text-align:center;border-bottom:2px solid #1c4d38;padding-bottom:12px;margin-bottom:16px;">
                        <div style="font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#1c4d38;">NIA — ACIMO</div>
                        <div style="font-size:1rem;font-weight:800;color:#111827;margin:3px 0 2px;">Document Tracking Receipt</div>
                    </div>

                    <!-- Two-column body -->
                    <div style="display:flex;gap:20px;align-items:flex-start;margin-bottom:16px;">

                        <!-- LEFT: Document details -->
                        <div style="flex:1;min-width:0;">
                            <?php
                            $rcp_from_label = !empty($doc['from_section']) ? $doc['from_section'] : (!empty($doc['from_office_name']) ? $doc['from_office_name'] : '');
                            $rcp_rows = [
                                ['Document ID',    '#'.$doc['id']],
                                ['Document Number', htmlspecialchars($doc['document_number'])],
                                ['Document Name',  htmlspecialchars($doc['document_name'])],
                                ['Type',           htmlspecialchars($doc['type_name'] ?? '—')],
                                ['Kind',           ucfirst(htmlspecialchars($doc['kind']))],
                                ['Status',         ucfirst(htmlspecialchars($doc['status']))],
                                ['From',           htmlspecialchars($doc['forwarded_by_name_emp'] ?: ($doc['forwarded_by_name'] ?: '—')) . ($rcp_from_label ? ' · '.htmlspecialchars($rcp_from_label) : '')],
                                ['To',             htmlspecialchars($doc['forwarded_to_name_emp'] ?: ($doc['forwarded_to'] ?: '—')) . (!empty($doc['to_section']) ? ' · '.htmlspecialchars($doc['to_section']) : '')],
                                ['Date Forwarded', safeDate($doc['last_fwd_date'] ?? $doc['date_forwarded']) ?? '—'],
                                ['Date Received',  safeDate($doc['date_received']) ?? 'Not yet received'],
                                ['Record Created', safeDate($doc['created_at']) ?? '—'],
                            ];
                            if (!empty($doc['remarks'])) $rcp_rows[] = ['Remarks', htmlspecialchars($doc['remarks'])];
                            foreach ($rcp_rows as $ri => $rr):
                                // Only clamp Document Name, not Remarks
                                $isLong = ($rr[0] === 'Document Name');
                            ?>
                            <div style="display:flex;justify-content:space-between;align-items:baseline;padding:4px 0;border-bottom:1px dashed #e5e7eb;font-size:.73rem;gap:10px;">
                                <span style="color:#6b7280;font-weight:600;white-space:nowrap;flex-shrink:0;"><?= $rr[0] ?></span>
                                <span style="color:#111827;font-weight:500;text-align:right;word-break:break-word;<?= $isLong ? 'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;max-height:2.6em;' : '' ?>"><?= $rr[1] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- RIGHT: Forwarding History panel -->
                        <?php if (!empty($fwd_history)): ?>
                        <div style="width:300px;flex-shrink:0;border:1.5px solid #1c4d38;border-radius:8px;overflow:hidden;font-size:.72rem;">
                            <!-- Panel header -->
                            <div style="background:#1c4d38;color:#fff;padding:8px 12px;font-weight:700;font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;display:flex;justify-content:space-between;align-items:center;">
                                <span><i class="fas fa-history" style="margin-right:5px;"></i>Forwarding History</span>
                                <span style="background:rgba(255,255,255,.25);border-radius:20px;padding:1px 7px;font-size:.6rem;"><?= count($fwd_history) ?></span>
                            </div>
                            <!-- Table layout -->
                            <table style="width:100%;border-collapse:collapse;font-size:.67rem;">
                                <thead>
                                    <tr style="background:#f0faf5;border-bottom:1px solid #d1fae5;">
                                        <th style="padding:5px 8px;color:#1c4d38;font-weight:700;text-align:left;border-right:1px solid #d1fae5;white-space:nowrap;">Route</th>
                                        <th style="padding:5px 8px;color:#1c4d38;font-weight:700;text-align:left;border-right:1px solid #d1fae5;">Date &amp; Received By</th>
                                        <th style="padding:5px 8px;color:#1c4d38;font-weight:700;text-align:left;">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <!-- Rows -->
                            <?php
                            $fhLastIdx = count($fwd_history) - 1;
                            foreach ($fwd_history as $rfi => $rfh):
                                // Build initials
                                $rfByInit  = fhInitials($rfh['fwd_by_fname'] ?? '', $rfh['fwd_by_lname'] ?? '');
                                $rfToInit  = fhInitials($rfh['fwd_to_fname'] ?? '', $rfh['fwd_to_lname'] ?? '');
                                // Section/unit code for "from" person
                                $rfFromCode = !empty($rfh['from_unit_code']) ? $rfh['from_unit_code'] : (!empty($rfh['from_section_code']) ? $rfh['from_section_code'] : '');
                                // Section/unit code for destination
                                $rfToCode   = !empty($rfh['to_unit_code']) ? $rfh['to_unit_code'] : (!empty($rfh['to_section_code']) ? $rfh['to_section_code'] : (!empty($rfh['to_office_name']) ? 'IMO' : ''));
                                // Route string: ER(ADM) -> VM(FIN)
                                $rfRoute = $rfByInit . ($rfFromCode ? '('.$rfFromCode.')' : '') . ' → ' . $rfToInit . ($rfToCode ? '('.$rfToCode.')' : '');
                                // Received by initials
                                $rfRecvName = trim($rfh['fwd_received_by_name'] ?? '');
                                $rfRecvDate = !empty($rfh['received_at']) ? safeDate($rfh['received_at']) : null;
                                if ($rfi === $fhLastIdx && !$rfRecvName && !empty($doc['received_by_name'])) {
                                    $rfRecvName = trim($doc['received_by_name']);
                                    $rfRecvDate = $rfRecvDate ?? safeDate($doc['date_received']);
                                }
                                $rfRecvInit = $rfRecvName ? nameInitials($rfRecvName) : '';
                                $rfDate     = safeDate($rfh['fwd_date']) ?? '—';
                                $rfRowBg    = ($rfi % 2 === 0) ? '#fff' : '#f7fdf9';
                                $rfIsLast   = ($rfi === $fhLastIdx);
                            ?>
                                <tr style="background:<?= $rfRowBg ?>;">
                                    <!-- Route col -->
                                    <td style="padding:7px 8px;border-right:1px solid #e5e7eb;font-weight:700;color:#1c4d38;white-space:nowrap;vertical-align:top;border-bottom:1px solid #f0f0f0;">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#1c4d38;color:#fff;font-size:.55rem;font-weight:700;margin-right:4px;flex-shrink:0;"><?= $rfi+1 ?></span><?= htmlspecialchars($rfRoute) ?>
                                    </td>
                                    <!-- Date & Received col -->
                                    <td style="padding:7px 8px;border-right:1px solid #e5e7eb;vertical-align:top;border-bottom:1px solid #f0f0f0;">
                                        <div style="color:#6b7280;font-size:.63rem;"><?= $rfDate ?></div>
                                        <?php if ($rfRecvInit): ?>
                                        <div style="margin-top:3px;">
                                            <span style="font-weight:700;color:#065f46;" title="<?= htmlspecialchars($rfRecvName) ?>"><?= htmlspecialchars($rfRecvInit) ?></span>
                                            <?php if ($rfRecvDate): ?>
                                            <div style="font-size:.6rem;color:#9ca3af;"><?= $rfRecvDate ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div style="color:#d1d5db;font-style:italic;font-size:.63rem;margin-top:2px;">Pending</div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Remarks col -->
                                    <td style="padding:6px 7px;color:#6b7280;font-style:italic;font-size:.63rem;vertical-align:top;border-bottom:1px solid #f0f0f0;max-width:70px;">
                                        <div style="word-break:break-word;"><?= !empty($rfh['fwd_remarks']) ? htmlspecialchars($rfh['fwd_remarks']) : '<span style="color:#e5e7eb;">—</span>' ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    </div><!-- /two-column -->

                    <!-- QR + footer -->
                    <div style="display:flex;flex-direction:column;align-items:center;margin-top:4px;padding-top:14px;border-top:2px solid #1c4d38;gap:6px;">
                        <div style="font-size:.63rem;color:#6b7280;font-weight:600;letter-spacing:.06em;text-transform:uppercase;"><i class="fas fa-qrcode mr-1"></i>Scan to view this document</div>
                        <div id="receiptQrCode"></div>
                        <div style="font-size:.6rem;color:#9ca3af;text-align:center;margin-top:8px;">This receipt was generated by the NIA-ACIMO Document Tracking System.<br>Printed on <span id="receiptPrintDate"></span></div>
                    </div>

                </div><!-- /receiptPreviewBody -->
            </div>
            <div class="modal-footer" style="border:none;padding:12px 20px;background:#f1f5f9;">
                <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" style="font-weight:600;">Close</button>
                <button type="button" class="btn btn-sm" onclick="printReceipt()" style="background:#1c4d38;color:#fff;font-weight:600;border-radius:8px;padding:6px 18px;">
                    <i class="fas fa-print mr-1"></i>Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden printable receipt area (injected to DOM, only visible during print) -->
<div id="receiptPrintArea">
    <div class="rp-header">
        <div class="rp-org">NIA — ACIMO</div>
        <div class="rp-title">Document Tracking Receipt</div>
    </div>

    <!-- Two-column print layout -->
    <div class="rp-body-columns">

        <!-- LEFT: Document info table -->
         
        <div class="rp-col-left">
            <table class="rp-table">
                <tr><td>Document ID</td><td>#<?= $doc['id'] ?></td></tr>
                <tr><td>Document Number</td><td><?= htmlspecialchars($doc['document_number']) ?></td></tr>
                <tr><td>Document Name</td><td class="rp-clamp"><?= htmlspecialchars($doc['document_name']) ?></td></tr>
                <tr><td>Type</td><td><?= htmlspecialchars($doc['type_name'] ?? '—') ?></td></tr>
                <tr><td>Kind</td><td><?= ucfirst(htmlspecialchars($doc['kind'])) ?></td></tr>
                <tr><td>Status</td><td><?= ucfirst(htmlspecialchars($doc['status'])) ?></td></tr>
                <tr><td>From</td><td><?= htmlspecialchars($doc['forwarded_by_name_emp'] ?: ($doc['forwarded_by_name'] ?: '—')) ?><?php $from_print = !empty($doc['from_section']) ? $doc['from_section'] : (!empty($doc['from_office_name']) ? $doc['from_office_name'] : ''); echo $from_print ? ' · '.htmlspecialchars($from_print) : ''; ?></td></tr>
                <tr><td>To</td><td><?= htmlspecialchars($doc['forwarded_to_name_emp'] ?: ($doc['forwarded_to'] ?: '—')) ?><?= !empty($doc['to_section']) ? ' · '.htmlspecialchars($doc['to_section']) : '' ?></td></tr>
                <tr><td>Date Forwarded</td><td><?= safeDate($doc['last_fwd_date'] ?? $doc['date_forwarded']) ?? '—' ?></td></tr>
                <tr><td>Date Received</td><td><?= safeDate($doc['date_received']) ?? 'Not yet received' ?></td></tr>
                <tr><td>Record Created</td><td><?= safeDate($doc['created_at']) ?? '—' ?></td></tr>
                <?php if (!empty($doc['remarks'])): ?>
                <tr><td>Remarks</td><td><?= htmlspecialchars($doc['remarks']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- RIGHT: Forwarding History panel -->
        <?php if (!empty($fwd_history)): ?>
        <div class="rp-col-right">
            <div class="rp-fh-panel">
                <div class="rp-fh-head">
                    <span>&#10227; Forwarding History</span>
                    <span class="rp-fh-badge"><?= count($fwd_history) ?></span>
                </div>
                <table class="rp-fh-table">
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th>Date &amp; Received By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $prLastIdx = count($fwd_history) - 1;
                    foreach ($fwd_history as $pri => $prh):
                        $prByInit   = fhInitials($prh['fwd_by_fname'] ?? '', $prh['fwd_by_lname'] ?? '');
                        $prToInit   = fhInitials($prh['fwd_to_fname'] ?? '', $prh['fwd_to_lname'] ?? '');
                        $prFromCode = !empty($prh['from_unit_code']) ? $prh['from_unit_code'] : (!empty($prh['from_section_code']) ? $prh['from_section_code'] : '');
                        $prToCode   = !empty($prh['to_unit_code']) ? $prh['to_unit_code'] : (!empty($prh['to_section_code']) ? $prh['to_section_code'] : (!empty($prh['to_office_name']) ? 'IMO' : ''));
                        $prRoute    = $prByInit . ($prFromCode ? '('.$prFromCode.')' : '') . ' → ' . $prToInit . ($prToCode ? '('.$prToCode.')' : '');
                        $prRecvName = trim($prh['fwd_received_by_name'] ?? '');
                        $prRecvDate = !empty($prh['received_at']) ? safeDate($prh['received_at']) : null;
                        if ($pri === $prLastIdx && !$prRecvName && !empty($doc['received_by_name'])) {
                            $prRecvName = trim($doc['received_by_name']);
                            $prRecvDate = $prRecvDate ?? safeDate($doc['date_received']);
                        }
                        $prRecvInit = $prRecvName ? nameInitials($prRecvName) : '';
                        $prDate     = safeDate($prh['fwd_date']) ?? '—';
                        $prRowBg    = ($pri % 2 === 0) ? '#fff' : '#f7fdf9';
                    ?>
                        <tr style="background:<?= $prRowBg ?>;">
                            <td style="font-weight:700;color:#1c4d38;white-space:nowrap;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#1c4d38;color:#fff;font-size:5.5pt;font-weight:700;margin-right:3px;flex-shrink:0;"><?= $pri+1 ?></span><?= htmlspecialchars($prRoute) ?>
                            </td>
                            <td>
                                <div style="color:#555;font-size:6.5pt;"><?= $prDate ?></div>
                                <?php if ($prRecvInit): ?>
                                <div style="font-weight:700;color:#065f46;margin-top:2px;" title="<?= htmlspecialchars($prRecvName) ?>"><?= htmlspecialchars($prRecvInit) ?></div>
                                <?php if ($prRecvDate): ?><div style="color:#aaa;font-size:6pt;"><?= $prRecvDate ?></div><?php endif; ?>
                                <?php else: ?>
                                <div style="color:#ccc;font-style:italic;font-size:6.5pt;">Pending</div>
                                <?php endif; ?>
                            </td>
                            <td style="color:#777;font-style:italic;word-break:break-word;">
                                <div class="rp-fh-remark"><?= !empty($prh['fwd_remarks']) ? htmlspecialchars($prh['fwd_remarks']) : '<span style="color:#e5e7eb;">—</span>' ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /rp-body-columns -->

    <div class="rp-qr-wrap">
        <div class="rp-qr-lbl">Scan to view this document</div>
        <div id="receiptPrintQrCode"></div>
        <div class="rp-footer">This receipt was generated by the NIA-ACIMO Document Tracking System. &nbsp;|&nbsp; Printed on <span id="receiptPrintAreaDate"></span></div>
    </div>
</div>

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
// ══════════════════════════════════════════════════════════════
//  PRINT RECEIPT  (document_view.php)
// ══════════════════════════════════════════════════════════════
const RECEIPT_URL = window.location.href.split('?')[0] + '?id=<?= $doc['id'] ?>';

let _qrModalBuilt   = false;
let _qrPrintBuilt   = false;

function openReceiptModal() {
    // Set print date
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    document.getElementById('receiptPrintDate').textContent = dateStr;

    // Build QR code in modal preview (only once)
    if (!_qrModalBuilt) {
        const el = document.getElementById('receiptQrCode');
        el.innerHTML = '';
        new QRCode(el, {
            text:         RECEIPT_URL,
            width:        110,
            height:       110,
            colorDark:    '#1c4d38',
            colorLight:   '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
        _qrModalBuilt = true;
    }

    // Build QR code in print area (only once)
    if (!_qrPrintBuilt) {
        const pel = document.getElementById('receiptPrintQrCode');
        pel.innerHTML = '';
        new QRCode(pel, {
            text:         RECEIPT_URL,
            width:        120,
            height:       120,
            colorDark:    '#1c4d38',
            colorLight:   '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
        _qrPrintBuilt = true;
    }

    $('#receiptPreviewModal').modal('show');
}

function printReceipt() {
    // Sync the print date into the print area
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    document.getElementById('receiptPrintAreaDate').textContent = dateStr;

    // Temporarily show print area and hide everything else, then print
    document.getElementById('receiptPrintArea').style.display = 'block';
    $('#receiptPreviewModal').modal('hide');

    // Small delay to let modal animate out before print dialog opens
    setTimeout(function() {
        window.print();
        // After print dialog closes, hide the print area again
        setTimeout(function() {
            document.getElementById('receiptPrintArea').style.display = 'none';
        }, 500);
    }, 350);
}

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