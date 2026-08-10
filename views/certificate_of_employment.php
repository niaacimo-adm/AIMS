<?php
/**
 * Certificate of Employment (COE) — Issuance & Monitoring
 * UI/UX intentionally mirrors views/hr_leave_monitoring.php so the HR
 * Management section of the sidebar stays visually consistent.
 *
 * Place this file at:  views/certificate_of_employment.php
 * Needs:                sql/certificate_of_employment.sql   (run once)
 *                        views/generate_coe.php              (docx generator)
 *                        templates/coe_template.docx          (letterhead template)
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Same role gating pattern as HR Leave Monitoring.
$user_role_id = intval($_SESSION['role_id'] ?? 0);
$can_view   = in_array($user_role_id, [1, 2, 12, 14, 25]);
$can_issue  = in_array($user_role_id, [1, 12, 14, 25]);
$can_delete = in_array($user_role_id, [1, 25]);

if (!$can_view) {
    header('Location: dashboard.php');
    exit;
}

/** Re-verify the logged-in user's password before a destructive action. */
function verifyCurrentUserPassword(mysqli $db, string $password): bool {
    if ($password === '') return false;
    $emp_id = intval($_SESSION['emp_id'] ?? 0);
    if (!$emp_id) return false;

    $stmt = $db->prepare("SELECT password FROM users WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return false;

    return password_verify($password, $row['password']);
}

/** Audit trail entry, mirrors logLeaveHistory() in hr_leave_monitoring.php */
function logCoeHistory(mysqli $db, int $coe_id, string $action, ?array $snapshot = null, ?string $remarks = null): void {
    $emp_id = intval($_SESSION['emp_id'] ?? 0);

    $performed_by_name = null;
    if ($emp_id) {
        $ns = $db->prepare("SELECT CONCAT(first_name,' ',last_name) AS full_name FROM employee WHERE emp_id = ?");
        $ns->bind_param("i", $emp_id);
        $ns->execute();
        $name_row = $ns->get_result()->fetch_assoc();
        $performed_by_name = $name_row['full_name'] ?? null;
    }
    $performed_by_name = $performed_by_name ?? ($_SESSION['username'] ?? null);
    $snapshot_json = $snapshot !== null ? json_encode($snapshot) : null;

    $stmt = $db->prepare("
        INSERT INTO certificate_of_employment_history
            (coe_id, action, performed_by_emp_id, performed_by_name, remarks, snapshot_json)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->bind_param("isisss", $coe_id, $action, $emp_id, $performed_by_name, $remarks, $snapshot_json);
    $stmt->execute();
}

/** Ordinal day suffix used inline on the AJAX preview + by generate_coe.php */
function ordinalSuffix(int $day): string {
    if ($day % 100 >= 11 && $day % 100 <= 13) return 'th';
    switch ($day % 10) {
        case 1:  return 'st';
        case 2:  return 'nd';
        case 3:  return 'rd';
        default: return 'th';
    }
}

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $coe_id = intval($_POST['coe_id'] ?? 0);

    if ($action === 'issue_coe') {
        if (!$can_issue) { echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit; }

        $emp_id          = intval($_POST['emp_id'] ?? 0);
        $purpose         = trim($_POST['purpose'] ?? '');
        $appt_text       = trim($_POST['appointment_status_text'] ?? '');
        $position_text   = trim($_POST['position_text'] ?? '');
        $include_salary  = isset($_POST['include_salary']) && $_POST['include_salary'] == '1' ? 1 : 0;
        $salary_amount   = $include_salary ? (float)($_POST['salary_amount'] ?? 0) : null;
        $requestor_ref   = trim($_POST['requestor_ref'] ?? '');
        $place_issued    = trim($_POST['place_issued'] ?? 'Tuburan, Ligao City, Albay');
        $issued_date     = trim($_POST['issued_date'] ?? date('Y-m-d'));
        $signatory_name  = trim($_POST['signatory_name'] ?? 'ENGR. MARK CLOYD G. SO, MPA');
        $signatory_title = trim($_POST['signatory_title'] ?? 'Acting Division Manager');
        $issued_by       = intval($_SESSION['emp_id'] ?? 0);
        $request_id      = intval($_POST['request_id'] ?? 0); // set when issued from an employee self-service request

        if (!$emp_id || !$purpose || !$appt_text || !$position_text || !$requestor_ref || !$issued_date) {
            echo json_encode(['success'=>false,'message'=>'Please fill in all required fields.']); exit;
        }
        if ($include_salary && $salary_amount <= 0) {
            echo json_encode(['success'=>false,'message'=>'Enter a valid salary amount, or turn off "Include Salary".']); exit;
        }

        $ins = $db->prepare("
            INSERT INTO certificate_of_employment
                (emp_id, purpose, appointment_status_text, position_text, include_salary, salary_amount,
                 requestor_ref, place_issued, issued_date, signatory_name, signatory_title, status, issued_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'Issued',?)
        ");
        $ins->bind_param(
            "isssidsssssi",
            $emp_id, $purpose, $appt_text, $position_text, $include_salary, $salary_amount,
            $requestor_ref, $place_issued, $issued_date, $signatory_name, $signatory_title, $issued_by
        );
        if ($ins->execute()) {
            $new_id = $ins->insert_id;
            logCoeHistory($db, $new_id, 'issued', null, $purpose);

            if ($request_id) {
                $ru = $db->prepare("
                    UPDATE certificate_of_employment_requests
                    SET status='Issued', coe_id=?, reviewed_by=?, reviewed_at=NOW()
                    WHERE request_id=? AND status='Pending'
                ");
                $ru->bind_param("iii", $new_id, $issued_by, $request_id);
                $ru->execute();
            }

            echo json_encode(['success'=>true, 'coe_id'=>$new_id]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }

    } elseif ($action === 'void_coe') {
        if (!$can_issue) { echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit; }
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        if (!verifyCurrentUserPassword($db, $confirm_password)) {
            echo json_encode(['success'=>false,'message'=>'Incorrect password. Action cancelled.']); exit;
        }
        $reason = trim($_POST['void_reason'] ?? '');

        $row = $db->query("SELECT status FROM certificate_of_employment WHERE coe_id = $coe_id")->fetch_assoc();
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Record not found.']); exit; }
        if ($row['status'] !== 'Issued') { echo json_encode(['success'=>false,'message'=>'Only Issued certificates can be voided.']); exit; }

        $s = $db->prepare("UPDATE certificate_of_employment SET status='Voided', void_reason=? WHERE coe_id=?");
        $s->bind_param("si", $reason, $coe_id);
        if ($s->execute()) {
            logCoeHistory($db, $coe_id, 'voided', null, $reason ?: null);
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }

    } elseif ($action === 'delete_coe') {
        if (!$can_delete) { echo json_encode(['success'=>false,'message'=>'Permission denied. Only Administrator or HR Unit Focal Person can delete COE records.']); exit; }
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        if (!verifyCurrentUserPassword($db, $confirm_password)) {
            echo json_encode(['success'=>false,'message'=>'Incorrect password. Deletion cancelled.']); exit;
        }

        $row = $db->query("SELECT * FROM certificate_of_employment WHERE coe_id = $coe_id")->fetch_assoc();
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Record not found.']); exit; }
        if ($row['status'] !== 'Voided') { echo json_encode(['success'=>false,'message'=>'Only Voided certificates can be permanently deleted.']); exit; }

        $del = $db->prepare("DELETE FROM certificate_of_employment WHERE coe_id = ?");
        $del->bind_param("i", $coe_id);
        if ($del->execute()) {
            logCoeHistory($db, $coe_id, 'deleted', $row);
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }

    } elseif ($action === 'delete_all_coe') {
        if (!$can_delete) { echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit; }
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        if (!verifyCurrentUserPassword($db, $confirm_password)) {
            echo json_encode(['success'=>false,'message'=>'Incorrect password. Deletion cancelled.']); exit;
        }

        $rows_to_delete = $db->query("SELECT * FROM certificate_of_employment WHERE status='Voided'")->fetch_all(MYSQLI_ASSOC);
        $del = $db->query("DELETE FROM certificate_of_employment WHERE status='Voided'");
        if ($del) {
            foreach ($rows_to_delete as $row) {
                logCoeHistory($db, (int)$row['coe_id'], 'deleted_all', $row);
            }
            echo json_encode(['success'=>true, 'deleted'=>$db->affected_rows]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }

    } elseif ($action === 'get_details') {
        $s = $db->prepare("
            SELECT c.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.id_number, e.picture,
                   s.section_name, CONCAT(hr.first_name,' ',hr.last_name) AS issued_by_name
            FROM certificate_of_employment c
            LEFT JOIN employee e  ON c.emp_id = e.emp_id
            LEFT JOIN section  s  ON e.section_id = s.section_id
            LEFT JOIN employee hr ON c.issued_by = hr.emp_id
            WHERE c.coe_id = ?
        ");
        $s->bind_param("i", $coe_id);
        $s->execute();
        echo json_encode($s->get_result()->fetch_assoc());

    } elseif ($action === 'get_history') {
        $h = $db->prepare("
            SELECT action, performed_by_name, remarks, created_at
            FROM certificate_of_employment_history
            WHERE coe_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $h->bind_param("i", $coe_id);
        $h->execute();
        echo json_encode($h->get_result()->fetch_all(MYSQLI_ASSOC));

    } elseif ($action === 'get_request_detail') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $s = $db->prepare("
            SELECT r.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.id_number, e.emp_id,
                   ap.status_name AS appointment_status, pos.position_name, pos.salary,
                   sec.section_name, CONCAT(hr.first_name,' ',hr.last_name) AS reviewed_by_name
            FROM certificate_of_employment_requests r
            LEFT JOIN employee e ON r.emp_id = e.emp_id
            LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
            LEFT JOIN position pos ON e.position_id = pos.position_id
            LEFT JOIN section sec ON e.section_id = sec.section_id
            LEFT JOIN employee hr ON r.reviewed_by = hr.emp_id
            WHERE r.request_id = ?
        ");
        $s->bind_param("i", $request_id);
        $s->execute();
        echo json_encode($s->get_result()->fetch_assoc());

    } elseif ($action === 'reject_request') {
        if (!$can_issue) { echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit; }
        $request_id = intval($_POST['request_id'] ?? 0);
        $remarks    = trim($_POST['remarks'] ?? '');
        if (!$remarks) { echo json_encode(['success'=>false,'message'=>'Please provide a reason for rejection.']); exit; }
        $reviewer = intval($_SESSION['emp_id'] ?? 0);

        $u = $db->prepare("
            UPDATE certificate_of_employment_requests
            SET status='Rejected', remarks=?, reviewed_by=?, reviewed_at=NOW()
            WHERE request_id=? AND status='Pending'
        ");
        $u->bind_param("sii", $remarks, $reviewer, $request_id);
        if ($u->execute() && $u->affected_rows > 0) {
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Request not found or already processed.']);
        }

    } elseif ($action === 'get_employees') {
        // COE can be issued to any appointment type, including Job Order.
        $q = "SELECT e.emp_id, CONCAT(e.first_name,' ',e.last_name) AS full_name, e.id_number,
                     ap.status_name AS appointment_status, s.section_name,
                     pos.position_name, pos.salary
              FROM employee e
              LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
              LEFT JOIN section s ON e.section_id = s.section_id
              LEFT JOIN position pos ON e.position_id = pos.position_id
              ORDER BY e.last_name, e.first_name";
        $res = $db->query($q);
        echo json_encode(['success'=>true,'employees'=>$res->fetch_all(MYSQLI_ASSOC)]);
    }
    exit;
}

/* ── Filters ── */
$filter_status  = $_GET['status']  ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_month   = $_GET['month']   ?? '';
$filter_q       = trim($_GET['q']  ?? '');

$where  = ["1=1"];
$params = [];
$types  = '';

if ($filter_status)  { $where[] = "c.status=?";                          $params[] = $filter_status;  $types.='s'; }
if ($filter_section) { $where[] = "e.section_id=?";                      $params[] = $filter_section; $types.='i'; }
if ($filter_month)   { $where[] = "DATE_FORMAT(c.issued_date,'%Y-%m')=?"; $params[] = $filter_month;   $types.='s'; }
if ($filter_q)       { $where[] = "CONCAT(e.first_name,' ',e.last_name) LIKE ?"; $params[] = '%'.$filter_q.'%'; $types.='s'; }

$where_sql = 'WHERE '.implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT c.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.id_number, e.picture,
           ap.status_name AS appointment_status, ap.color AS appt_color,
           s.section_name
    FROM certificate_of_employment c
    LEFT JOIN employee           e  ON c.emp_id               = e.emp_id
    LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
    LEFT JOIN section            s  ON e.section_id            = s.section_id
    $where_sql
    ORDER BY c.created_at DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='Issued' THEN 1 ELSE 0 END) AS issued,
        SUM(CASE WHEN status='Voided' THEN 1 ELSE 0 END) AS voided,
        SUM(CASE WHEN status='Issued' AND MONTH(issued_date)=MONTH(CURDATE()) AND YEAR(issued_date)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_month
    FROM certificate_of_employment
")->fetch_assoc();

$deletable_count = (int) ($db->query("
    SELECT COUNT(*) AS c FROM certificate_of_employment WHERE status='Voided'
")->fetch_assoc()['c'] ?? 0);

$sections = $db->query("SELECT * FROM section ORDER BY section_name")->fetch_all(MYSQLI_ASSOC);

/* ── Employee self-service requests awaiting HR review ── */
$pending_requests = $db->query("
    SELECT r.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.id_number, s.section_name
    FROM certificate_of_employment_requests r
    LEFT JOIN employee e ON r.emp_id     = e.emp_id
    LEFT JOIN section  s ON e.section_id = s.section_id
    WHERE r.status = 'Pending'
    ORDER BY r.created_at ASC
")->fetch_all(MYSQLI_ASSOC);
$pending_requests_count = count($pending_requests);

$request_detail_labels = [
    'with_salary'    => 'With Salary',
    'without_salary' => 'Without Salary',
    'dates_only'     => 'Inclusive Dates Only',
];

$common_purposes = [
    'Employment Verification', 'Loan Application', 'Visa Application / Travel Abroad',
    'Bank Requirement', 'Scholarship / Further Studies', 'Local Employment Application', 'Other Purpose',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Employment | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
    <style>
        /* ══ TOKENS — reuses the same green-forest theme as HR Leave Monitoring ══ */
        :root {
            --h-bg:       #eef7f2;
            --h-card:     #ffffff;
            --h-card-alt: #f0faf5;
            --h-border:   rgba(42,152,99,0.18);
            --h-text:     #0f2d1e;
            --h-muted:    #4a7a5e;
            --h-primary:  #2a9863;
            --h-accent:   #24e78f;
            --h-success:  #2a9863;
            --h-warning:  #e67700;
            --h-danger:   #c92a2a;
            --h-shadow:   0 4px 24px rgba(42,152,99,.12);
            --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
        }
        body.dark-mode {
            --h-bg:       #0b1f17;
            --h-card:     #102f22;
            --h-card-alt: #0e2619;
            --h-border:   rgba(36,231,143,0.12);
            --h-text:     #d4f5e5;
            --h-muted:    #6aad8a;
            --h-primary:  #24e78f;
            --h-accent:   #2a9863;
            --h-success:  #24e78f;
            --h-warning:  #ffd43b;
            --h-danger:   #ff6b6b;
            --h-shadow:   0 4px 24px rgba(0,0,0,.35);
            --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }

        .hr-page { background:var(--h-bg); min-height:calc(100vh - 57px); padding-bottom:48px; }

        @keyframes hrMeshDrift { 0%{transform:translate(0,0) rotate(0deg);} 100%{transform:translate(3%,2%) rotate(2deg);} }
        @keyframes hrOrbFloat  { 0%,100%{opacity:.4;transform:translate(0,0) scale(1);} 33%{opacity:.7;transform:translate(18px,-26px) scale(1.05);} 66%{opacity:.5;transform:translate(-12px,16px) scale(.95);} }
        @keyframes hrRingPulse { 0%,100%{opacity:.45;transform:scale(1);} 50%{opacity:.85;transform:scale(1.04);} }

        .hr-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
        .hr-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:hrMeshDrift 22s ease-in-out infinite alternate; z-index:0;
        }
        .hr-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .hr-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:hrOrbFloat 18s ease-in-out infinite; }
        .hr-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;   left:-60px;  animation-duration:21s; }
        .hr-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px;right:-40px; animation-delay:-7s; animation-duration:17s; }
        .hr-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;     right:20%;   animation-delay:-13s; animation-duration:24s; }
        .hr-hero-dots { position:absolute; inset:0; z-index:0; pointer-events:none; background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px); background-size:36px 36px; }
        .hr-hero-rings { position:absolute; top:50%; right:6%; transform:translateY(-50%); width:260px; height:260px; pointer-events:none; z-index:0; }
        .hr-ring { position:absolute; inset:0; border-radius:50%; border:1px solid rgba(36,231,143,.10); animation:hrRingPulse 4s ease-in-out infinite; }
        .hr-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .hr-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .mh-logo-watermark { width:100%; height:100%; object-fit:contain; opacity:.14; }

        .hr-hero-inner { position:relative; z-index:2; }
        .hr-hero h1 { color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px; letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45); }
        .hr-hero p  { color:rgba(255,255,255,.7); margin:0 0 14px; font-size:.9rem; }
        .hr-hero-divider { width:54px; height:3px; background:linear-gradient(90deg,#2a9863,#24e78f); border-radius:3px; margin-bottom:12px; }
        .hr-hero-actions { position:relative; z-index:2; display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; }
        .btn-apply-leave-hero {
            background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; border:none; border-radius:9px;
            padding:9px 18px; font-size:.85rem; font-weight:700; cursor:pointer;
            display:inline-flex; align-items:center; gap:7px; transition:transform .15s, box-shadow .15s;
        }
        .btn-apply-leave-hero:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(36,231,143,.35); color:#fff; }

        .hr-content { max-width:auto; margin:-38px auto 0; padding:0 28px; position:relative; z-index:3; }

        .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:20px; }
        @media(max-width:1100px){ .stats-row{ grid-template-columns:repeat(3,1fr); } }
        @media(max-width:900px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
        .stat-card { background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:12px; box-shadow:var(--h-shadow-sm); }
        .stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; flex-shrink:0; }
        .si-tot  { background:linear-gradient(135deg,#2a9863,#24e78f); }
        .si-iss  { background:linear-gradient(135deg,#087f5b,#20c997); }
        .si-mon  { background:linear-gradient(135deg,#1c7ed6,#4dabf7); }
        .si-void { background:linear-gradient(135deg,#c92a2a,#ff8787); }
        .si-req  { background:linear-gradient(135deg,#e67700,#ffa94d); }
        .stat-val { font-size:1.4rem; font-weight:800; color:var(--h-text); line-height:1.1; }
        .stat-lbl { font-size:.74rem; color:var(--h-muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

        .filter-bar { background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; padding:16px 20px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; box-shadow:var(--h-shadow-sm); }
        .fg { margin:0; flex:1; min-width:120px; }
        .fg label { font-size:.7rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; display:block; }
        .h-ctrl { width:100%; background:var(--h-card); border:1.5px solid var(--h-border); border-radius:8px; padding:8px 12px; font-size:.85rem; color:var(--h-text); transition:border-color .18s, box-shadow .18s; box-sizing:border-box; }
        .h-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px rgba(42,152,99,.13); }
        .btn-filter { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; border:none; border-radius:8px; padding:9px 18px; font-size:.85rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:transform .15s, box-shadow .15s; }
        .btn-filter:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,152,99,.35); }
        .btn-reset { background:var(--h-card); color:var(--h-muted); border:1.5px solid var(--h-border); border-radius:8px; padding:9px 14px; font-size:.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
        .btn-reset:hover { background:var(--h-bg); color:var(--h-text); }

        .view-only-banner { background:#fff8e1; border:1px solid #f59f00; border-radius:10px; padding:11px 16px; display:flex; align-items:center; gap:10px; margin-bottom:20px; font-size:.86rem; color:#7c4c00; font-weight:500; }
        body.dark-mode .view-only-banner { background:#2e2000; border-color:#c07000; color:#ffd43b; }

        .h-card { background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; box-shadow:var(--h-shadow-sm); overflow:hidden; margin-bottom:24px; }
        .h-card-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:16px 20px; border-bottom:1px solid var(--h-border); background:var(--h-card-alt); }
        .h-card-head-left { display:flex; align-items:center; gap:10px; }
        .h-card-ico { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; }
        .h-card-head h5 { margin:0; font-size:.98rem; font-weight:700; color:var(--h-text); }
        .h-rec-count { font-size:.74rem; color:var(--h-muted); background:var(--h-bg); border-radius:20px; padding:3px 10px; border:1px solid var(--h-border); transition:background .15s, color .15s, border-color .15s; }
        .h-rec-count-alert { background:linear-gradient(135deg,#e67700,#ffa94d); color:#fff; border-color:transparent; font-weight:700; }

        .h-card-head-collapsible { cursor:pointer; user-select:none; }
        .h-card-head-right { display:flex; align-items:center; gap:12px; }
        .h-card-toggle-ico { color:var(--h-muted); font-size:.85rem; transition:transform .2s ease; }
        .h-card-head-collapsible.is-collapsed .h-card-toggle-ico { transform:rotate(-90deg); }
        .h-card-collapsible-body { overflow:hidden; }

        .h-tbl { width:100%; border-collapse:collapse; }
        .h-tbl th { background:var(--h-card-alt); padding:11px 14px; font-size:.7rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid var(--h-border); white-space:nowrap; }
        .h-tbl td { padding:13px 14px; font-size:.87rem; color:var(--h-text); border-bottom:1px solid var(--h-border); vertical-align:middle; }
        .h-tbl tr:last-child td { border-bottom:none; }
        .h-tbl tbody tr { transition:background .12s; }
        .h-tbl tbody tr:hover td { background:var(--h-card-alt); }

        .emp-cell { display:flex; align-items:center; gap:10px; }
        .emp-av { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#2a9863,#24e78f); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:12px; flex-shrink:0; overflow:hidden; }
        .emp-av img { width:100%; height:100%; object-fit:cover; }
        .emp-name { font-weight:600; font-size:.87rem; color:var(--h-text); }
        .emp-section { font-size:.72rem; color:var(--h-muted); }
        .appt-badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:.71rem; font-weight:600; color:#fff; }

        .h-badge { padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; display:inline-block; }
        .hb-iss { background:#e6fbf4; color:#087f5b; }
        .hb-void{ background:#fff0f0; color:#c92a2a; }
        .hb-pend{ background:#fff4e0; color:#b45309; }
        body.dark-mode .hb-iss  { background:#0d3d2c; color:#63e6be; }
        body.dark-mode .hb-void { background:#3d0f0f; color:#ff8787; }
        body.dark-mode .hb-pend { background:#3d2e00; color:#ffd43b; }

        .req-opt-line { font-size:.9rem; color:var(--h-text); }

        .sal-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:.72rem; font-weight:600; background:var(--h-card-alt); color:var(--h-muted); border:1px solid var(--h-border); }

        .action-btns { display:flex; gap:5px; align-items:center; }
        .btn-act { width:30px; height:30px; border-radius:7px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:12px; transition:all .15s; }
        .ba-view { background:#e6f7ef; color:#2a9863; } .ba-view:hover { background:#2a9863; color:#fff; }
        .ba-dl   { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; text-decoration:none; } .ba-dl:hover { opacity:.85; color:#fff; }
        .ba-void { background:#fff8e1; color:#b45309; } .ba-void:hover { background:#b45309; color:#fff; }
        .ba-hist { background:#eef3ff; color:#3b5bdb; } .ba-hist:hover { background:#3b5bdb; color:#fff; }
        .ba-del  { background:#fff0f0; color:#9b1c1c; border:1.5px solid #fca5a5; } .ba-del:hover { background:#9b1c1c; color:#fff; border-color:#9b1c1c; }
        .ba-review { background:#eef3ff; color:#3b5bdb; } .ba-review:hover { background:#3b5bdb; color:#fff; }
        .lock-ico { color:var(--h-border); font-size:13px; cursor:default; }

        .h-empty { text-align:center; padding:50px 20px; }
        .h-empty i { font-size:46px; opacity:.2; display:block; margin-bottom:14px; color:var(--h-muted); }
        .h-empty p { color:var(--h-muted); }

        .acc-ind { display:inline-flex; align-items:center; gap:5px; font-size:.72rem; font-weight:600; border-radius:6px; padding:3px 9px; }
        .ai-full { background:#e6f7ef; color:#1c4d38; } .ai-view { background:#fff8e1; color:#92400e; }
        body.dark-mode .ai-full { background:#0d3d2c; color:#63e6be; } body.dark-mode .ai-view { background:#3d2e00; color:#ffd43b; }

        .hm-modal .modal-content { border-radius:14px; border:none; overflow:hidden; background:var(--h-card); }
        .hm-modal .modal-header { background:linear-gradient(135deg,#0f2d1e,#2a9863); color:#fff; border:none; padding:18px 24px; }
        .hm-modal .modal-header .close { color:#fff; opacity:.7; } .hm-modal .modal-header .close:hover { opacity:1; }
        .hm-modal .modal-title { font-size:1rem; font-weight:700; }
        .hm-modal .modal-body { padding:24px; }
        .hm-modal .modal-footer { border-top:1px solid var(--h-border); padding:14px 24px; background:var(--h-card-alt); }

        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
        @media(max-width:520px){ .detail-grid{ grid-template-columns:1fr; } }
        .detail-item label { font-size:.68rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:3px; }
        .detail-item span { font-size:.9rem; color:var(--h-text); font-weight:500; }
        .info-box { background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:8px; padding:12px 14px; font-size:.87rem; color:var(--h-text); margin-top:4px; }
        .perm-note { background:#fff8e1; border:1px solid #f59f00; border-radius:8px; padding:9px 14px; font-size:.82rem; color:#7c4c00; display:flex; align-items:center; gap:7px; width:100%; }
        body.dark-mode .perm-note { background:#2e2000; border-color:#c07000; color:#ffd43b; }

        .purpose-chip { display:inline-block; padding:5px 12px; margin:3px 4px 3px 0; border-radius:20px; font-size:.78rem; font-weight:600; cursor:pointer; border:1.5px solid var(--h-border); color:var(--h-muted); background:var(--h-card); transition:all .15s; }
        .purpose-chip.active, .purpose-chip:hover { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; border-color:transparent; }

        .toggle-switch { display:inline-flex; border:1.5px solid var(--h-border); border-radius:8px; overflow:hidden; }
        .toggle-switch button { border:none; background:var(--h-card); color:var(--h-muted); padding:8px 16px; font-size:.83rem; font-weight:700; cursor:pointer; transition:all .15s; }
        .toggle-switch button.active { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; }

        @media(max-width:768px){ .hr-content{ padding:0 14px; } .hr-hero{ padding:24px 16px 50px; } }

        /* Select2 (theme: 'bootstrap') — matches the "Create New Project" members
           select. Not covered by .h-ctrl since Select2 renders its own markup
           outside the native <select>. */
        .select2-container--bootstrap .select2-selection--single,
        .select2-container--bootstrap .select2-selection--multiple {
            background: var(--h-card) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            min-height: 38px !important;
        }
        .select2-container--bootstrap .select2-selection--single {
            height: 38px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--bootstrap .select2-selection--single .select2-selection__rendered {
            color: var(--h-text) !important;
            line-height: normal !important;
            padding: 0 30px 0 12px !important;
            flex: 1 1 auto !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        .select2-container--bootstrap .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            top: 0 !important;
            right: 4px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--bootstrap .select2-selection--single .select2-selection__clear {
            height: auto !important;
            line-height: normal !important;
            margin-top: 0 !important;
            position: absolute !important;
            right: 26px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }
        .select2-container--bootstrap .select2-selection__placeholder { color: var(--h-muted) !important; }
        .select2-container--bootstrap.select2-container--focus .select2-selection--single,
        .select2-container--bootstrap.select2-container--open .select2-selection--single {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px rgba(42,152,99,.13) !important;
        }
        .select2-dropdown {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            box-shadow: var(--h-shadow) !important;
        }
        .select2-container--bootstrap .select2-search--dropdown .select2-search__field {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 6px !important;
        }
        .select2-container--bootstrap .select2-results__option {
            color: var(--h-text) !important;
            background: transparent !important;
        }
        .select2-container--bootstrap .select2-results__option--highlighted[aria-selected] {
            background: var(--h-primary) !important;
            color: #fff !important;
        }
        .select2-container--bootstrap .select2-results__option[aria-selected="true"] {
            background: var(--h-card-alt) !important;
            color: var(--h-muted) !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper hr-page">

        <!-- Hero -->
        <div class="hr-hero">
            <div class="hr-hero-mesh"></div>
            <div class="hr-hero-dots"></div>
            <div class="hr-hero-orbs">
                <div class="hr-orb hr-orb-1"></div>
                <div class="hr-orb hr-orb-2"></div>
                <div class="hr-orb hr-orb-3"></div>
            </div>
            <div class="hr-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>

            <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:14px;position:relative;z-index:2;">
                <div class="hr-hero-inner">
                    <h1><i class="fas fa-certificate mr-2" style="opacity:.85"></i>Certificate of Employment</h1>
                    <div class="hr-hero-divider"></div>
                    <p>Issue, track, and re-download employee Certificates of Employment</p>
                </div>
                <div class="hr-hero-actions">
                    <span style="color:rgba(212,245,229,.65);font-size:.82rem;align-self:center;">
                        <i class="fas fa-calendar mr-1"></i><?= date('F d, Y') ?>
                    </span>
                    <?php if ($can_issue): ?>
                    <button class="btn-apply-leave-hero" id="btnOpenIssueCoe" type="button">
                        <i class="fas fa-plus-circle"></i> Issue Certificate
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="hr-content">

            <?php if (!$can_issue): ?>
            <div class="view-only-banner">
                <i class="fas fa-info-circle" style="color:var(--h-warning)"></i>
                You have <strong>view-only</strong> access. Only <strong>Administrator</strong>, <strong>Heads</strong>, and <strong>Unit Head</strong> roles can issue or void certificates.
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="stat-ico si-tot"><i class="fas fa-layer-group"></i></div><div><div class="stat-val"><?= $stats['total']??0 ?></div><div class="stat-lbl">Total</div></div></div>
                <div class="stat-card"><div class="stat-ico si-req"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-val"><?= $pending_requests_count ?></div><div class="stat-lbl">Pending Requests</div></div></div>
                <div class="stat-card"><div class="stat-ico si-iss"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?= $stats['issued']??0 ?></div><div class="stat-lbl">Issued</div></div></div>
                <div class="stat-card"><div class="stat-ico si-mon"><i class="fas fa-calendar-check"></i></div><div><div class="stat-val"><?= $stats['this_month']??0 ?></div><div class="stat-lbl">This Month</div></div></div>
                <div class="stat-card"><div class="stat-ico si-void"><i class="fas fa-ban"></i></div><div><div class="stat-val"><?= $stats['voided']??0 ?></div><div class="stat-lbl">Voided</div></div></div>
            </div>

            <!-- Filter bar -->
            <div class="filter-bar">
                <div class="fg">
                    <label>Employee</label>
                    <input type="text" id="f_q" class="h-ctrl" placeholder="Search name..." value="<?= htmlspecialchars($filter_q) ?>">
                </div>
                <div class="fg">
                    <label>Status</label>
                    <select id="f_status" class="h-ctrl">
                        <option value="">All Status</option>
                        <option value="Issued" <?= $filter_status==='Issued'?'selected':'' ?>>Issued</option>
                        <option value="Voided" <?= $filter_status==='Voided'?'selected':'' ?>>Voided</option>
                    </select>
                </div>
                <div class="fg">
                    <label>Section</label>
                    <select id="f_section" class="h-ctrl">
                        <option value="">All Sections</option>
                        <?php foreach($sections as $sec): ?>
                        <option value="<?= $sec['section_id'] ?>" <?= $filter_section==$sec['section_id']?'selected':'' ?>><?= htmlspecialchars($sec['section_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Month Issued</label>
                    <input type="month" id="f_month" class="h-ctrl" value="<?= htmlspecialchars($filter_month) ?>">
                </div>
                <button class="btn-filter" onclick="applyFilters()"><i class="fas fa-filter"></i> Filter</button>
                <a href="certificate_of_employment.php" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
            </div>

            <!-- Employee Requests card -->
            <div class="h-card">
                <div class="h-card-head h-card-head-collapsible<?= $pending_requests_count === 0 ? ' is-collapsed' : '' ?>"
                     id="empReqCardHead" role="button" tabindex="0"
                     aria-expanded="<?= $pending_requests_count === 0 ? 'false' : 'true' ?>"
                     aria-controls="empReqCardBody">
                    <div class="h-card-head-left">
                        <div class="h-card-ico"><i class="fas fa-inbox"></i></div>
                        <h5>Employee Requests</h5>
                    </div>
                    <div class="h-card-head-right">
                        <span class="h-rec-count<?= $pending_requests_count > 0 ? ' h-rec-count-alert' : '' ?>" id="empReqCount">
                            <?= $pending_requests_count ?> pending
                        </span>
                        <i class="fas fa-chevron-down h-card-toggle-ico"></i>
                    </div>
                </div>
                <div class="table-responsive h-card-collapsible-body" id="empReqCardBody"<?= $pending_requests_count === 0 ? ' style="display:none;"' : '' ?>>
                    <?php if (empty($pending_requests)): ?>
                    <div class="h-empty">
                        <i class="fas fa-inbox"></i>
                        <p>No pending employee requests right now.</p>
                    </div>
                    <?php else: ?>
                    <table class="h-tbl">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Purpose</th>
                                <th>Details Requested</th>
                                <th>Copies</th>
                                <th>Date Needed</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_requests as $req):
                            $reqPurposeDisplay = $req['purpose_category'] === 'Other' ? $req['purpose_other'] : $req['purpose_category'];
                        ?>
                        <tr>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-av"><?= strtoupper(substr($req['emp_name'] ?? '?', 0, 1)) ?></div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($req['emp_name'] ?? 'Unknown') ?></div>
                                        <div class="emp-section"><?= htmlspecialchars($req['section_name'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($reqPurposeDisplay) ?></td>
                            <td><?= htmlspecialchars($request_detail_labels[$req['detail_type']] ?? $req['detail_type']) ?></td>
                            <td><?= (int)$req['num_copies'] ?></td>
                            <td><?= $req['date_needed'] ? date('M d, Y', strtotime($req['date_needed'])) : '—' ?></td>
                            <td><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($can_issue): ?>
                                    <button class="btn-act ba-review btn-review-request" data-id="<?= $req['request_id'] ?>" title="Review Request">
                                        <i class="fas fa-clipboard-check"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="lock-ico" title="Requires elevated role"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table card -->
            <div class="h-card">
                <div class="h-card-head">
                    <div class="h-card-head-left">
                        <div class="h-card-ico"><i class="fas fa-table"></i></div>
                        <h5>Issued Certificates</h5>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <?php if($can_issue): ?>
                        <span class="acc-ind ai-full"><i class="fas fa-unlock-alt"></i> Full Access</span>
                        <?php else: ?>
                        <span class="acc-ind ai-view"><i class="fas fa-lock"></i> View Only</span>
                        <?php endif; ?>
                        <span class="h-rec-count"><?= count($records) ?> record(s)</span>
                        <?php if($can_delete): ?>
                        <button type="button" id="btnDeleteAll" class="btn btn-danger btn-sm" <?= $deletable_count===0?'disabled':'' ?>
                                title="Permanently delete all Voided certificate records">
                            <i class="fas fa-trash-alt mr-1"></i>Delete All Voided (<?= $deletable_count ?>)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if(empty($records)): ?>
                    <div class="h-empty">
                        <i class="fas fa-file-certificate"></i>
                        <p>No certificates issued yet matching your filters.</p>
                    </div>
                    <?php else: ?>
                    <table class="h-tbl" id="coeTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Purpose</th>
                                <th>Salary Shown</th>
                                <th>Issued Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($records as $i=>$rec):
                            $init='';
                            foreach(explode(' ',$rec['emp_name']) as $p) if($p) $init.=strtoupper(substr($p,0,1));
                            $init=substr($init,0,2);
                            $apptColor=$rec['appt_color']??'#4a7a5e';
                            $isVoided = $rec['status']==='Voided';
                        ?>
                        <tr>
                            <td style="color:var(--h-muted);font-size:.77rem;"><?= $i+1 ?></td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-av">
                                        <?php if(!empty($rec['picture'])): ?>
                                        <img src="../dist/img/employees/<?= htmlspecialchars($rec['picture']) ?>"
                                             onerror="this.style.display='none';this.parentNode.textContent='<?= $init ?>'" alt="">
                                        <?php else: ?><?= $init ?><?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($rec['emp_name']) ?></div>
                                        <div class="emp-section"><?= htmlspecialchars($rec['section_name']??'No Section') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($rec['position_text']) ?></td>
                            <td style="max-width:220px;white-space:normal;"><?= htmlspecialchars($rec['purpose']) ?></td>
                            <td>
                                <?php if($rec['include_salary']): ?>
                                <span class="sal-pill"><i class="fas fa-check"></i> P<?= number_format((float)$rec['salary_amount'],2) ?></span>
                                <?php else: ?>
                                <span class="sal-pill"><i class="fas fa-minus"></i> Not shown</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--h-muted);font-size:.8rem;"><?= date('M d, Y', strtotime($rec['issued_date'])) ?></td>
                            <td><span class="h-badge <?= $isVoided?'hb-void':'hb-iss' ?>"><?= htmlspecialchars($rec['status']) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-act ba-dl" href="generate_coe.php?coe_id=<?= $rec['coe_id'] ?>" target="_blank" title="Download COE (.docx)">
                                        <i class="fas fa-file-word"></i>
                                    </a>
                                    <button class="btn-act ba-view btn-view-detail" data-id="<?= $rec['coe_id'] ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-act ba-hist btn-view-history" data-id="<?= $rec['coe_id'] ?>" title="History">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <?php if(!$isVoided): ?>
                                        <?php if($can_issue): ?>
                                        <button class="btn-act ba-void btn-void" data-id="<?= $rec['coe_id'] ?>" data-name="<?= htmlspecialchars($rec['emp_name']) ?>" title="Void"><i class="fas fa-ban"></i></button>
                                        <?php else: ?>
                                        <span class="lock-ico" title="Requires elevated role"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if($can_delete): ?>
                                        <button class="btn-act ba-del btn-coe-delete" data-id="<?= $rec['coe_id'] ?>" data-name="<?= htmlspecialchars($rec['emp_name']) ?>" title="Delete Record"><i class="fas fa-trash-alt"></i></button>
                                        <?php else: ?>
                                        <span class="lock-ico" title="Requires Administrator or HR Unit Focal Person role"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /hr-content -->

        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <!-- ══ Issue COE Modal ══ -->
    <?php if ($can_issue): ?>
    <div class="modal fade hm-modal" id="issueCoeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-certificate mr-2"></i>Issue Certificate of Employment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="icRequestId" value="">
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Employee</label>
                        <select id="icEmpId" class="h-ctrl" style="width:100%;">
                            <option value="">-- Select Employee --</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Appointment Status (as it will read on the COE)</label>
                            <input type="text" id="icApptText" class="h-ctrl" placeholder="e.g. Permanent, Job Order, Contract of Service" disabled>
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Position (as it will read on the COE)</label>
                            <input type="text" id="icPositionText" class="h-ctrl" placeholder="e.g. Engineer A">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Purpose</label>
                        <div id="icPurposeChips">
                            <?php foreach($common_purposes as $p): ?>
                            <span class="purpose-chip" data-val="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="icPurpose" class="h-ctrl mt-2" placeholder="Purpose that will appear on the certificate (editable)">
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Include Salary?</label><br>
                            <div class="toggle-switch">
                                <button type="button" id="icSalYes" class="salary-toggle-btn">Yes</button>
                                <button type="button" id="icSalNo" class="salary-toggle-btn active">No</button>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Monthly Salary (₱)</label>
                            <input type="number" step="0.01" id="icSalaryAmount" class="h-ctrl">
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Issued Date</label>
                            <input type="date" id="icIssuedDate" class="h-ctrl" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Requested By (e.g. "Mr. Dela Cruz")</label>
                            <input type="text" id="icRequestorRef" class="h-ctrl">
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Place Issued</label>
                            <input type="text" id="icPlaceIssued" class="h-ctrl" value="Tuburan, Ligao City, Albay">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Signatory Name</label>
                            <input type="text" id="icSignatoryName" class="h-ctrl" value="ENGR. MARK CLOYD G. SO, MPA">
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Signatory Title</label>
                            <input type="text" id="icSignatoryTitle" class="h-ctrl" value="Acting Division Manager">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnSubmitIssueCoe" class="btn-filter"><i class="fas fa-file-word"></i> Issue &amp; Generate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Void Modal ══ -->
    <div class="modal fade hm-modal" id="voidCoeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban mr-2"></i>Void Certificate</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>You are about to void the certificate issued to <strong id="voidEmpName"></strong>. This does not delete the record — it simply marks it invalid.</p>
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Reason (optional)</label>
                        <input type="text" id="voidReason" class="h-ctrl">
                    </div>
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Confirm Your Password</label>
                        <input type="password" id="voidPassword" class="h-ctrl">
                    </div>
                    <input type="hidden" id="voidCoeId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmVoid" class="btn btn-warning"><i class="fas fa-ban"></i> Void Certificate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Review Request Modal ══ -->
    <div class="modal fade hm-modal" id="reviewRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div id="reviewRequestModalInner">
                    <div class="modal-header"><h5 class="modal-title">Loading…</h5></div>
                    <div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Reject Request Modal ══ -->
    <div class="modal fade hm-modal" id="rejectRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Reject Request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Let <strong id="rejectEmpName"></strong> know why this request can't be processed as-is.</p>
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Reason (required)</label>
                        <input type="text" id="rejectRemarks" class="h-ctrl">
                    </div>
                    <input type="hidden" id="rejectRequestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmReject" class="btn btn-danger"><i class="fas fa-times-circle"></i> Reject Request</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ Delete Modal ══ -->
    <?php if ($can_delete): ?>
    <div class="modal fade hm-modal" id="deleteCoeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash-alt mr-2"></i>Delete Certificate Record</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>This will <strong>permanently</strong> delete the voided certificate record for <strong id="delEmpName"></strong>. This cannot be undone.</p>
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Confirm Your Password</label>
                        <input type="password" id="delPassword" class="h-ctrl">
                    </div>
                    <input type="hidden" id="delCoeId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmDelete" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade hm-modal" id="deleteAllModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash-alt mr-2"></i>Delete All Voided Records</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>This will permanently delete <strong><?= $deletable_count ?></strong> voided certificate record(s). This cannot be undone.</p>
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Confirm Your Password</label>
                        <input type="password" id="delAllPassword" class="h-ctrl">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmDeleteAll" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete All</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ Detail Modal ══ -->
    <div class="modal fade hm-modal" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div id="detailModalInner">
                    <div class="modal-header"><h5 class="modal-title">Loading…</h5></div>
                    <div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ History Modal ══ -->
    <div class="modal fade hm-modal" id="historyModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-history mr-2"></i>Certificate History</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body" id="historyModalBody">
                    <div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
<script>
var CAN_ISSUE  = <?= $can_issue ? 'true' : 'false' ?>;
var employeesCache = [];

function applyFilters() {
    var q       = encodeURIComponent($('#f_q').val());
    var status  = encodeURIComponent($('#f_status').val());
    var section = encodeURIComponent($('#f_section').val());
    var month   = encodeURIComponent($('#f_month').val());
    window.location.href = 'certificate_of_employment.php?q='+q+'&status='+status+'&section='+section+'&month='+month;
}

$(document).ready(function() {

    // Employee Requests card — collapse/expand
    $('#empReqCardHead').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
        e.preventDefault();
        var $head = $(this);
        var $body = $('#empReqCardBody');
        $body.stop(true, true).slideToggle(180, function() {
            var expanded = $body.is(':visible');
            $head.toggleClass('is-collapsed', !expanded);
            $head.attr('aria-expanded', expanded ? 'true' : 'false');
        });
    });

    <?php if ($can_issue): ?>
    // Load employees for the Issue COE select (Select2, same setup as the
    // "Create New Project" members select)
    function loadEmployees(onLoaded) {
        $.post('certificate_of_employment.php', {ajax:1, action:'get_employees'}, function(res) {
            if (!res.success) return;
            employeesCache = res.employees;
            var $sel = $('#icEmpId');

            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }

            $sel.empty().append('<option value=""></option>');
            res.employees.forEach(function(e) {
                $sel.append('<option value="'+e.emp_id+'">'+e.full_name+' ('+(e.position_name||'No Position')+')</option>');
            });

            $sel.select2({
                theme: 'bootstrap',
                width: '100%',
                placeholder: '-- Select Employee --',
                allowClear: true,
                dropdownParent: $('#issueCoeModal')
            }).val(null).trigger('change');

            if (typeof onLoaded === 'function') onLoaded();
        }, 'json');
    }

    $('#btnOpenIssueCoe').on('click', function() {
        loadEmployees();
        $('#icRequestId').val('');
        $('#icPurpose,#icApptText,#icPositionText,#icRequestorRef,#icSalaryAmount').val('');
        $('#icIssuedDate').val('<?= date('Y-m-d') ?>');
        $('#icPlaceIssued').val('Tuburan, Ligao City, Albay');
        $('#icSignatoryName').val('ENGR. MARK CLOYD G. SO, MPA');
        $('#icSignatoryTitle').val('Acting Division Manager');
        $('.purpose-chip').removeClass('active');
        $('#icSalNo').addClass('active'); $('#icSalYes').removeClass('active');
        $('#icSalaryAmount').val('').prop('disabled', true);
        $('#issueCoeModal').modal('show');
    });

    // Pre-fill the Issue COE modal from an approved employee self-service request
    var REQUEST_DETAIL_LABELS = { with_salary:'With Salary', without_salary:'Without Salary', dates_only:'Inclusive Dates Only' };

    function openIssueFromRequest(req) {
        $('#icRequestId').val(req.request_id);
        var purposeText = req.purpose_category === 'Other' ? req.purpose_other : req.purpose_category;
        $('#icPurpose').val(purposeText);
        $('.purpose-chip').removeClass('active');
        $('#icIssuedDate').val('<?= date('Y-m-d') ?>');
        $('#icPlaceIssued').val('Tuburan, Ligao City, Albay');
        $('#icSignatoryName').val('ENGR. MARK CLOYD G. SO, MPA');
        $('#icSignatoryTitle').val('Acting Division Manager');

        if (req.detail_type === 'with_salary') {
            $('#icSalYes').trigger('click');
        } else {
            $('#icSalNo').addClass('active'); $('#icSalYes').removeClass('active');
            $('#icSalaryAmount').val('').prop('disabled', true);
        }

        $('#issueCoeModal').modal('show');
        loadEmployees(function() {
            $('#icEmpId').val(req.emp_id).trigger('change');
        });
    }

    // Auto-fill appointment/position/salary/requestor-ref when employee changes
    $(document).on('change', '#icEmpId', function() {
        var empId = $(this).val();
        var emp = employeesCache.find(function(e){ return String(e.emp_id) === String(empId); });
        if (!emp) return;
        $('#icApptText').val(emp.appointment_status || '');
        $('#icPositionText').val(emp.position_name || '');
        $('#icSalaryAmount').val(emp.salary || '');
        var parts = emp.full_name.split(' ');
        var last = parts[parts.length - 1];
        $('#icRequestorRef').val(last);
    });

    // Purpose quick-select chips
    $(document).on('click', '.purpose-chip', function() {
        $('.purpose-chip').removeClass('active');
        $(this).addClass('active');
        $('#icPurpose').val($(this).data('val'));
    });

    // Salary include toggle
    $('#icSalYes').on('click', function() {
        $(this).addClass('active'); $('#icSalNo').removeClass('active');
        $('#icSalaryAmount').prop('disabled', false);
    });
    $('#icSalNo').on('click', function() {
        $(this).addClass('active'); $('#icSalYes').removeClass('active');
        $('#icSalaryAmount').prop('disabled', true);
    });

    $('#btnSubmitIssueCoe').on('click', function() {
        var empId = $('#icEmpId').val();
        var appt  = $('#icApptText').val().trim();
        var pos   = $('#icPositionText').val().trim();
        var purpose = $('#icPurpose').val().trim();
        var includeSalary = $('#icSalYes').hasClass('active') ? 1 : 0;
        var salaryAmount = $('#icSalaryAmount').val();
        var requestorRef = $('#icRequestorRef').val().trim();
        var issuedDate = $('#icIssuedDate').val();
        var placeIssued = $('#icPlaceIssued').val().trim();
        var sigName = $('#icSignatoryName').val().trim();
        var sigTitle = $('#icSignatoryTitle').val().trim();

        if (!empId)      { Swal.fire({icon:'warning',title:'Select Employee',confirmButtonColor:'#2a9863'}); return; }
        if (!appt)       { Swal.fire({icon:'warning',title:'Appointment status is required',confirmButtonColor:'#2a9863'}); return; }
        if (!pos)        { Swal.fire({icon:'warning',title:'Position is required',confirmButtonColor:'#2a9863'}); return; }
        if (!purpose)    { Swal.fire({icon:'warning',title:'Purpose is required',confirmButtonColor:'#2a9863'}); return; }
        if (!requestorRef){Swal.fire({icon:'warning',title:'"Requested By" is required',confirmButtonColor:'#2a9863'}); return; }
        if (!issuedDate) { Swal.fire({icon:'warning',title:'Issued date is required',confirmButtonColor:'#2a9863'}); return; }
        if (includeSalary && (!salaryAmount || parseFloat(salaryAmount) <= 0)) {
            Swal.fire({icon:'warning',title:'Enter a valid salary amount',confirmButtonColor:'#2a9863'}); return;
        }

        $('#btnSubmitIssueCoe').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating…');
        $.post('certificate_of_employment.php', {
            ajax:1, action:'issue_coe',
            emp_id: empId,
            appointment_status_text: appt,
            position_text: pos,
            purpose: purpose,
            include_salary: includeSalary,
            salary_amount: salaryAmount,
            requestor_ref: requestorRef,
            issued_date: issuedDate,
            place_issued: placeIssued,
            signatory_name: sigName,
            signatory_title: sigTitle,
            request_id: $('#icRequestId').val()
        }, function(res) {
            $('#btnSubmitIssueCoe').prop('disabled', false).html('<i class="fas fa-file-word"></i> Issue &amp; Generate');
            if (res.success) {
                $('#issueCoeModal').modal('hide');
                Swal.fire({icon:'success',title:'Certificate Issued!',text:'The document will now download.',confirmButtonColor:'#2a9863'}).then(function() {
                    window.open('generate_coe.php?coe_id=' + res.coe_id, '_blank');
                    location.reload();
                });
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message||'Could not issue certificate.',confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });

    // Void
    $(document).on('click', '.btn-void', function() {
        $('#voidCoeId').val($(this).data('id'));
        $('#voidEmpName').text($(this).data('name'));
        $('#voidReason').val('');
        $('#voidPassword').val('');
        $('#voidCoeModal').modal('show');
    });
    $('#btnConfirmVoid').on('click', function() {
        var pwd = $('#voidPassword').val();
        if (!pwd) { Swal.fire({icon:'warning',title:'Password required',confirmButtonColor:'#2a9863'}); return; }
        $.post('certificate_of_employment.php', {
            ajax:1, action:'void_coe', coe_id:$('#voidCoeId').val(),
            void_reason:$('#voidReason').val(), confirm_password:pwd
        }, function(res) {
            if (res.success) {
                $('#voidCoeModal').modal('hide');
                Swal.fire({icon:'success',title:'Certificate Voided',confirmButtonColor:'#2a9863'}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message,confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });

    // Review an employee's self-service COE request
    $(document).on('click', '.btn-review-request', function() {
        var id = $(this).data('id');
        $('#reviewRequestModal').modal('show');
        $('#reviewRequestModalInner').html('<div class="modal-header"><h5 class="modal-title">Loading…</h5></div><div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>');
        $.post('certificate_of_employment.php', {ajax:1, action:'get_request_detail', request_id:id}, function(d) {
            var purposeDisplay = d.purpose_category === 'Other' ? d.purpose_other : d.purpose_category;
            var html = ''
                + '<div class="modal-header"><h5 class="modal-title"><i class="fas fa-inbox mr-2"></i>Review Request</h5>'
                + '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>'
                + '<div class="modal-body">'
                + '<div class="detail-grid">'
                + '<div class="detail-item"><label>Employee</label><span>'+(d.emp_name||'Unknown')+'</span></div>'
                + '<div class="detail-item"><label>ID Number</label><span>'+(d.id_number||'N/A')+'</span></div>'
                + '<div class="detail-item"><label>Position</label><span>'+(d.position_name||'N/A')+'</span></div>'
                + '<div class="detail-item"><label>Appointment Status</label><span>'+(d.appointment_status||'N/A')+'</span></div>'
                + '<div class="detail-item"><label>Section</label><span>'+(d.section_name||'N/A')+'</span></div>'
                + '<div class="detail-item"><label>Purpose</label><span>'+purposeDisplay+'</span></div>'
                + '<div class="detail-item"><label>Details Requested</label><span>'+(REQUEST_DETAIL_LABELS[d.detail_type]||d.detail_type)+'</span></div>'
                + '<div class="detail-item"><label>Number of Copies</label><span>'+d.num_copies+'</span></div>'
                + '<div class="detail-item"><label>Date Needed</label><span>'+(d.date_needed||'Not specified')+'</span></div>'
                + '<div class="detail-item"><label>Contact No.</label><span>'+(d.contact_no||'Not provided')+'</span></div>'
                + '<div class="detail-item"><label>Requested On</label><span>'+d.created_at+'</span></div>'
                + '</div>'
                + '</div>'
                + '<div class="modal-footer">'
                + '<button type="button" class="btn-reset" data-dismiss="modal">Close</button>'
                + '<button type="button" class="btn btn-danger btn-open-reject" data-id="'+d.request_id+'" data-name="'+(d.emp_name||'')+'"><i class="fas fa-times-circle"></i> Reject</button>'
                + '<button type="button" class="btn-filter btn-approve-request"><i class="fas fa-check-circle"></i> Approve &amp; Issue</button>'
                + '</div>';
            $('#reviewRequestModalInner').html(html);
            $('#reviewRequestModalInner').data('request', d);
        }, 'json');
    });

    // Approve -> hand off to the Issue COE modal, pre-filled
    $(document).on('click', '.btn-approve-request', function() {
        var req = $('#reviewRequestModalInner').data('request');
        if (!req) return;
        $('#reviewRequestModal').modal('hide');
        openIssueFromRequest(req);
    });

    // Reject
    $(document).on('click', '.btn-open-reject', function() {
        $('#reviewRequestModal').modal('hide');
        $('#rejectRequestId').val($(this).data('id'));
        $('#rejectEmpName').text($(this).data('name'));
        $('#rejectRemarks').val('');
        $('#rejectRequestModal').modal('show');
    });
    $('#btnConfirmReject').on('click', function() {
        var remarks = $('#rejectRemarks').val().trim();
        if (!remarks) { Swal.fire({icon:'warning',title:'Reason required',confirmButtonColor:'#2a9863'}); return; }
        $.post('certificate_of_employment.php', {
            ajax:1, action:'reject_request', request_id:$('#rejectRequestId').val(), remarks:remarks
        }, function(res) {
            if (res.success) {
                $('#rejectRequestModal').modal('hide');
                Swal.fire({icon:'success',title:'Request Rejected',confirmButtonColor:'#2a9863'}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message,confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });
    <?php endif; ?>

    <?php if ($can_delete): ?>
    $(document).on('click', '.btn-coe-delete', function() {
        $('#delCoeId').val($(this).data('id'));
        $('#delEmpName').text($(this).data('name'));
        $('#delPassword').val('');
        $('#deleteCoeModal').modal('show');
    });
    $('#btnConfirmDelete').on('click', function() {
        var pwd = $('#delPassword').val();
        if (!pwd) { Swal.fire({icon:'warning',title:'Password required',confirmButtonColor:'#2a9863'}); return; }
        $.post('certificate_of_employment.php', {
            ajax:1, action:'delete_coe', coe_id:$('#delCoeId').val(), confirm_password:pwd
        }, function(res) {
            if (res.success) {
                $('#deleteCoeModal').modal('hide');
                Swal.fire({icon:'success',title:'Deleted',confirmButtonColor:'#2a9863'}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message,confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });

    $('#btnDeleteAll').on('click', function() {
        $('#delAllPassword').val('');
        $('#deleteAllModal').modal('show');
    });
    $('#btnConfirmDeleteAll').on('click', function() {
        var pwd = $('#delAllPassword').val();
        if (!pwd) { Swal.fire({icon:'warning',title:'Password required',confirmButtonColor:'#2a9863'}); return; }
        $.post('certificate_of_employment.php', {
            ajax:1, action:'delete_all_coe', confirm_password:pwd
        }, function(res) {
            if (res.success) {
                $('#deleteAllModal').modal('hide');
                Swal.fire({icon:'success',title:'Deleted '+res.deleted+' record(s)',confirmButtonColor:'#2a9863'}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message,confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });
    <?php endif; ?>

    // View details
    $(document).on('click', '.btn-view-detail', function() {
        var id = $(this).data('id');
        $('#detailModal').modal('show');
        $('#detailModalInner').html('<div class="modal-header"><h5 class="modal-title">Loading…</h5></div><div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>');
        $.post('certificate_of_employment.php', {ajax:1, action:'get_details', coe_id:id}, function(d) {
            var salLine = d.include_salary == 1
                ? '<div class="detail-item"><label>Salary Shown</label><span>P'+parseFloat(d.salary_amount).toLocaleString(undefined,{minimumFractionDigits:2})+'</span></div>'
                : '<div class="detail-item"><label>Salary Shown</label><span>Not included</span></div>';
            var html = ''
                + '<div class="modal-header"><h5 class="modal-title"><i class="fas fa-certificate mr-2"></i>Certificate Details</h5>'
                + '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>'
                + '<div class="modal-body">'
                + '<div class="detail-grid">'
                + '<div class="detail-item"><label>Employee</label><span>'+d.emp_name+'</span></div>'
                + '<div class="detail-item"><label>ID Number</label><span>'+(d.id_number||'N/A')+'</span></div>'
                + '<div class="detail-item"><label>Appointment (on COE)</label><span>'+d.appointment_status_text+'</span></div>'
                + '<div class="detail-item"><label>Position (on COE)</label><span>'+d.position_text+'</span></div>'
                + salLine
                + '<div class="detail-item"><label>Issued Date</label><span>'+d.issued_date+'</span></div>'
                + '<div class="detail-item"><label>Place Issued</label><span>'+d.place_issued+'</span></div>'
                + '<div class="detail-item"><label>Signatory</label><span>'+d.signatory_name+'</span></div>'
                + '<div class="detail-item"><label>Signatory Title</label><span>'+d.signatory_title+'</span></div>'
                + '<div class="detail-item"><label>Status</label><span>'+d.status+'</span></div>'
                + '<div class="detail-item"><label>Issued By</label><span>'+(d.issued_by_name||'N/A')+'</span></div>'
                + '</div>'
                + '<div class="info-box mt-3"><strong>Purpose:</strong> '+d.purpose+'</div>'
                + (d.void_reason ? '<div class="info-box mt-2"><strong>Void Reason:</strong> '+d.void_reason+'</div>' : '')
                + '</div>'
                + '<div class="modal-footer">'
                + '<a href="generate_coe.php?coe_id='+d.coe_id+'" target="_blank" class="btn-filter" style="text-decoration:none;"><i class="fas fa-file-word"></i> Download</a>'
                + '</div>';
            $('#detailModalInner').html(html);
        }, 'json');
    });

    // History
    $(document).on('click', '.btn-view-history', function() {
        var id = $(this).data('id');
        $('#historyModal').modal('show');
        $('#historyModalBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>');
        $.post('certificate_of_employment.php', {ajax:1, action:'get_history', coe_id:id}, function(rows) {
            if (!rows.length) { $('#historyModalBody').html('<p class="text-muted text-center">No history recorded.</p>'); return; }
            var html = '<ul class="list-unstyled mb-0">';
            rows.forEach(function(r) {
                html += '<li class="mb-2 pb-2" style="border-bottom:1px solid var(--h-border);">'
                     + '<strong>'+r.action.charAt(0).toUpperCase()+r.action.slice(1)+'</strong> by '+(r.performed_by_name||'System')
                     + '<br><span style="font-size:.78rem;color:var(--h-muted);">'+r.created_at+'</span>'
                     + (r.remarks ? '<br><span style="font-size:.82rem;">'+r.remarks+'</span>' : '')
                     + '</li>';
            });
            html += '</ul>';
            $('#historyModalBody').html(html);
        }, 'json');
    });

});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
</body>
</html>