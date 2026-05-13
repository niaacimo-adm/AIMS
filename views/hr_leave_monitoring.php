<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$user_role_id = intval($_SESSION['role_id'] ?? 0);
$can_view     = in_array($user_role_id, [1, 2, 12, 14]);
$can_approve  = in_array($user_role_id, [1, 12, 14]);

if (!$can_view) {
    header('Location: dashboard.php');
    exit;
}

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action           = $_POST['action']           ?? '';
    $leave_request_id = intval($_POST['leave_request_id'] ?? 0);

    if (in_array($action, ['approve','reject','cancel']) && !$can_approve) {
        echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
    }

    if ($action === 'approve') {
        $hr_remarks = trim($_POST['hr_remarks'] ?? '');
        $hr_id      = $_SESSION['emp_id'] ?? 0;

        // Fetch the request details needed to update the balance
        $lr_row = $db->query("SELECT emp_id, leave_type_id, number_of_days, status
                               FROM leave_request
                               WHERE leave_request_id = $leave_request_id")->fetch_assoc();

        if (!$lr_row) {
            echo json_encode(['success'=>false,'message'=>'Leave request not found.']); exit;
        }
        if ($lr_row['status'] !== 'Pending') {
            echo json_encode(['success'=>false,'message'=>'Only pending requests can be approved.']); exit;
        }

        $s = $db->prepare("UPDATE leave_request SET status='Approved',hr_remarks=?,approved_by=?,approved_at=NOW() WHERE leave_request_id=?");
        $s->bind_param("sii",$hr_remarks,$hr_id,$leave_request_id);
        if (!$s->execute()) {
            echo json_encode(['success'=>false,'message'=>$db->error]); exit;
        }

        // Deduct used_days on normalized leave_balance for ALL leave types
        $current_year = (int) date('Y');
        $lt_id     = (int)   $lr_row['leave_type_id'];
        $emp_id_lr = (int)   $lr_row['emp_id'];
        $ndays     = (float) $lr_row['number_of_days'];
        $db->query("INSERT IGNORE INTO leave_balance (emp_id, leave_type_id, year)
                    VALUES ($emp_id_lr, $lt_id, $current_year)");
        $upd = $db->prepare("UPDATE leave_balance SET used_days = used_days + ?
                              WHERE emp_id = ? AND leave_type_id = ? AND year = ?");
        $upd->bind_param("diii", $ndays, $emp_id_lr, $lt_id, $current_year);
        $upd->execute();

        echo json_encode(['success'=>true]);

    } elseif ($action === 'reject') {
        $hr_remarks = trim($_POST['hr_remarks'] ?? '');
        $hr_id      = $_SESSION['emp_id'] ?? 0;

        $lr_row = $db->query("SELECT emp_id, leave_type_id, number_of_days, status
                               FROM leave_request
                               WHERE leave_request_id = $leave_request_id")->fetch_assoc();
        if (!$lr_row) {
            echo json_encode(['success'=>false,'message'=>'Leave request not found.']); exit;
        }

        $s = $db->prepare("UPDATE leave_request SET status='Rejected',hr_remarks=?,approved_by=?,approved_at=NOW() WHERE leave_request_id=?");
        $s->bind_param("sii",$hr_remarks,$hr_id,$leave_request_id);
        if (!$s->execute()) {
            echo json_encode(['success'=>false,'message'=>$db->error]); exit;
        }

        // If it was previously Approved, reverse the deduction
        if ($lr_row['status'] === 'Approved') {
            $current_year = (int) date('Y');
            $lt_id     = (int)   $lr_row['leave_type_id'];
            $emp_id_lr = (int)   $lr_row['emp_id'];
            $ndays     = (float) $lr_row['number_of_days'];
            $upd = $db->prepare("UPDATE leave_balance SET used_days = GREATEST(0, used_days - ?)
                                  WHERE emp_id = ? AND leave_type_id = ? AND year = ?");
            $upd->bind_param("diii", $ndays, $emp_id_lr, $lt_id, $current_year);
            $upd->execute();
        }

        echo json_encode(['success'=>true]);

    } elseif ($action === 'cancel') {
        $lr_row = $db->query("SELECT emp_id, leave_type_id, number_of_days, status
                               FROM leave_request
                               WHERE leave_request_id = $leave_request_id")->fetch_assoc();
        if (!$lr_row) {
            echo json_encode(['success'=>false,'message'=>'Leave request not found.']); exit;
        }

        $s = $db->prepare("UPDATE leave_request SET status='Cancelled' WHERE leave_request_id=?");
        $s->bind_param("i",$leave_request_id);
        if (!$s->execute()) {
            echo json_encode(['success'=>false,'message'=>$db->error]); exit;
        }

        // Reverse deduction if the request was already Approved
        if ($lr_row['status'] === 'Approved') {
            $current_year = (int) date('Y');
            $lt_id     = (int)   $lr_row['leave_type_id'];
            $emp_id_lr = (int)   $lr_row['emp_id'];
            $ndays     = (float) $lr_row['number_of_days'];
            $upd = $db->prepare("UPDATE leave_balance SET used_days = GREATEST(0, used_days - ?)
                                  WHERE emp_id = ? AND leave_type_id = ? AND year = ?");
            $upd->bind_param("diii", $ndays, $emp_id_lr, $lt_id, $current_year);
            $upd->execute();
        }

        echo json_encode(['success'=>true]);

    } elseif ($action === 'delete') {
        if (!$can_approve) {
            echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
        }
        $lr_row = $db->query("SELECT status FROM leave_request WHERE leave_request_id = $leave_request_id")->fetch_assoc();
        if (!$lr_row) {
            echo json_encode(['success'=>false,'message'=>'Record not found.']); exit;
        }
        if (!in_array($lr_row['status'], ['Cancelled','Rejected','Disapproved'])) {
            echo json_encode(['success'=>false,'message'=>'Only Cancelled or Rejected requests can be deleted.']); exit;
        }
        $del = $db->prepare("DELETE FROM leave_request WHERE leave_request_id = ?");
        $del->bind_param("i", $leave_request_id);
        if ($del->execute()) {
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }
        exit;

    } elseif ($action === 'get_details') {
        $s = $db->prepare("
            SELECT lr.*, lt.leave_type_name,
                   CONCAT(e.first_name,' ',e.last_name)   AS emp_name,
                   e.id_number,
                   ap.status_name  AS appointment_status,
                   ap.color,
                   s.section_name,
                   pos.position_name,
                   CONCAT(hr.first_name,' ',hr.last_name) AS approved_by_name
            FROM leave_request lr
            LEFT JOIN leave_type          lt  ON lr.leave_type_id         = lt.leave_type_id
            LEFT JOIN employee            e   ON lr.emp_id                = e.emp_id
            LEFT JOIN appointment_status  ap  ON e.appointment_status_id  = ap.appointment_id
            LEFT JOIN section             s   ON e.section_id             = s.section_id
            LEFT JOIN position            pos ON e.position_id            = pos.position_id
            LEFT JOIN employee            hr  ON lr.approved_by           = hr.emp_id
            WHERE lr.leave_request_id = ?
        ");
        $s->bind_param("i",$leave_request_id);
        $s->execute();
        echo json_encode($s->get_result()->fetch_assoc());
    } elseif ($action === 'validate_leave') {
        $emp_id         = intval($_POST['emp_id'] ?? 0);
        $leave_type_id  = intval($_POST['leave_type_id'] ?? 0);
        $selected_dates = trim($_POST['selected_dates'] ?? '');
        $date_arr       = array_filter(array_map('trim', explode(',', $selected_dates)));

        if (!$emp_id || !$leave_type_id || empty($date_arr)) {
            echo json_encode(['valid' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        sort($date_arr);
        $from = $date_arr[0];
        $to   = end($date_arr);

        // Conflict check
        $conflict_sql = "SELECT COUNT(*) AS cnt FROM leave_request 
                         WHERE emp_id = ? 
                           AND status IN ('Pending','Approved') 
                           AND date_from <= ? AND date_to >= ?";
        $stmt = $db->prepare($conflict_sql);
        $stmt->bind_param("iss", $emp_id, $to, $from);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($conflict > 0) {
            echo json_encode(['valid' => false, 'message' => 'One or more selected dates overlap with an existing pending/approved leave request.']);
            exit;
        }

        // Count working days
        $days = 0;
        foreach ($date_arr as $d) {
            $dow = (new DateTime($d))->format('N');
            if ($dow < 6) $days++;
        }

        // Check balance
        $current_year = (int) date('Y');
        $bal_sql = "SELECT COALESCE(total_credits,0) AS total_credits, 
                           COALESCE(used_days,0) AS used_days 
                    FROM leave_balance 
                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $stmt = $db->prepare($bal_sql);
        $stmt->bind_param("iii", $emp_id, $leave_type_id, $current_year);
        $stmt->execute();
        $bal = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $available = $bal ? ((float)$bal['total_credits'] - (float)$bal['used_days']) : 0;

        if ($days > $available) {
            echo json_encode([
                'valid' => false,
                'message' => "Insufficient leave balance. The employee has only " . number_format($available, 3) . " day(s) available for this leave type."
            ]);
            exit;
        }

        echo json_encode(['valid' => true, 'days' => $days, 'available' => $available]);
        exit;
        } elseif ($action === 'apply_leave_for_emp') {
        $emp_id_target   = intval($_POST['target_emp_id']   ?? 0);
        $leave_type_id   = intval($_POST['leave_type_id']   ?? 0);
        $selected_dates  = trim($_POST['selected_dates']    ?? '');
        $reason          = trim($_POST['reason']            ?? '');
        $inclusive_dates = trim($_POST['inclusive_dates']   ?? '');
        $hr_id           = $_SESSION['emp_id'] ?? 0;

        $date_arr = array_filter(array_map('trim', explode(',', $selected_dates)));

        if (!$emp_id_target || !$leave_type_id || empty($date_arr) || !$reason) {
            echo json_encode(['success'=>false,'message'=>'Please fill in all required fields.']); exit;
        }

        sort($date_arr);
        $date_from = $date_arr[0];
        $date_to   = $date_arr[count($date_arr)-1];
        $days = 0;
        foreach ($date_arr as $d) {
            $dow = (new DateTime($d))->format('N');
            if ($dow < 6) $days++;
        }

        // Server‑side conflict check
        $conflict_sql = "SELECT COUNT(*) AS cnt FROM leave_request 
                         WHERE emp_id = ? AND status IN ('Pending','Approved') 
                         AND date_from <= ? AND date_to >= ?";
        $stmt = $db->prepare($conflict_sql);
        $stmt->bind_param("iss", $emp_id_target, $date_to, $date_from);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($conflict > 0) {
            echo json_encode(['success'=>false,'message'=>'Date conflict with existing pending/approved leave.']); exit;
        }

        // Balance check
        $current_year = (int) date('Y');
        $bal_sql = "SELECT COALESCE(total_credits,0) - COALESCE(used_days,0) AS available 
                    FROM leave_balance 
                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $stmt = $db->prepare($bal_sql);
        $stmt->bind_param("iii", $emp_id_target, $leave_type_id, $current_year);
        $stmt->execute();
        $available = $stmt->get_result()->fetch_assoc()['available'] ?? 0;
        $stmt->close();
        if ($days > $available) {
            echo json_encode(['success'=>false,'message'=>"Insufficient balance. Only " . number_format($available,3) . " day(s) available."]); exit;
        }

        $ins = $db->prepare("INSERT INTO leave_request
            (emp_id, leave_type_id, date_from, date_to, number_of_days, reason, inclusive_dates, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $ins->bind_param("iissdss", $emp_id_target, $leave_type_id, $date_from, $date_to, $days, $reason, $inclusive_dates);
        echo json_encode(['success'=>$ins->execute(), 'message'=>$db->error]);

        } elseif ($action === 'get_employees') {
        /* For the Apply Leave modal employee search */
        $q = "SELECT e.emp_id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                     ap.status_name AS appointment_status, s.section_name, pos.position_name
              FROM employee e
              LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
              LEFT JOIN section s ON e.section_id = s.section_id
              LEFT JOIN position pos ON e.position_id = pos.position_id
              WHERE (ap.status_name IS NULL OR ap.status_name != 'Job Order')
              ORDER BY e.last_name, e.first_name";
        $res = $db->query($q);
        echo json_encode(['success'=>true,'employees'=>$res->fetch_all(MYSQLI_ASSOC)]);

    } elseif ($action === 'get_emp_approved_dates') {
        /* Returns every weekday date that falls inside an approved leave for the given employee */
        $target_emp = intval($_POST['emp_id'] ?? 0);
        if (!$target_emp) {
            echo json_encode(['success'=>false,'dates'=>[]]);
            exit;
        }
        $stmt = $db->prepare("
            SELECT date_from, date_to
            FROM leave_request
            WHERE emp_id = ? AND status = 'Approved'
            ORDER BY date_from ASC
        ");
        $stmt->bind_param("i", $target_emp);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $dates = [];
        foreach ($rows as $row) {
            $cur = new DateTime($row['date_from']);
            $end = new DateTime($row['date_to']);
            while ($cur <= $end) {
                $dow = (int)$cur->format('N'); // 1=Mon…7=Sun
                if ($dow < 6) $dates[] = $cur->format('Y-m-d');
                $cur->modify('+1 day');
            }
        }
        echo json_encode(['success'=>true, 'dates'=> array_values(array_unique($dates))]);
    }
    exit;
}

/* ── Filters ── */
$filter_status  = $_GET['status']  ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_appt    = $_GET['appt']    ?? '';
$filter_month   = $_GET['month']   ?? '';

$where  = ["(ap.status_name IS NULL OR ap.status_name != 'Job Order')"];
$params = [];
$types  = '';

if ($filter_status)  { $where[]="lr.status=?";                          $params[]=$filter_status;  $types.='s'; }
if ($filter_section) { $where[]="e.section_id=?";                       $params[]=$filter_section; $types.='i'; }
if ($filter_appt)    { $where[]="e.appointment_status_id=?";            $params[]=$filter_appt;    $types.='i'; }
if ($filter_month)   { $where[]="DATE_FORMAT(lr.date_from,'%Y-%m')=?";  $params[]=$filter_month;   $types.='s'; }

$where_sql = 'WHERE '.implode(' AND ',$where);

$stmt = $db->prepare("
    SELECT lr.*, lt.leave_type_name,
           CONCAT(e.first_name,' ',e.last_name) AS emp_name,
           e.id_number, e.picture,
           ap.status_name AS appointment_status, ap.color AS appt_color,
           s.section_name
    FROM leave_request lr
    LEFT JOIN employee           e  ON lr.emp_id               = e.emp_id
    LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
    LEFT JOIN section            s  ON e.section_id            = s.section_id
    LEFT JOIN leave_type         lt ON lr.leave_type_id        = lt.leave_type_id
    $where_sql
    ORDER BY lr.created_at DESC
");
if ($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = $db->query("
    SELECT
        SUM(CASE WHEN lr.status='Pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN lr.status='Approved'  THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN lr.status='Rejected'  THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN lr.status='Cancelled' THEN 1 ELSE 0 END) AS cancelled,
        COUNT(*) AS total
    FROM leave_request lr
    LEFT JOIN employee           e  ON lr.emp_id               = e.emp_id
    LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
    WHERE (ap.status_name IS NULL OR ap.status_name != 'Job Order')
")->fetch_assoc();

$sections      = $db->query("SELECT * FROM section ORDER BY section_name")->fetch_all(MYSQLI_ASSOC);
$appt_statuses = $db->query("SELECT * FROM appointment_status WHERE status_name!='Job Order' ORDER BY status_name")->fetch_all(MYSQLI_ASSOC);
$leave_types   = $db->query("SELECT * FROM leave_type ORDER BY leave_type_name")->fetch_all(MYSQLI_ASSOC);

$role_labels = [1=>'Administrator',2=>'Manager',12=>'Heads',14=>'Unit Head'];
$current_role_label = $role_labels[$user_role_id] ?? 'Viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Leave Monitoring | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ══ TOKENS ══ */
        :root {
            --h-bg:       #f0f4ff;
            --h-card:     #ffffff;
            --h-card-alt: #f8faff;
            --h-border:   #e2e8f0;
            --h-text:     #1e293b;
            --h-muted:    #64748b;
            --h-primary:  #3b5bdb;
            --h-accent:   #228be6;
            --h-success:  #099268;
            --h-warning:  #e67700;
            --h-danger:   #c92a2a;
            --h-shadow:   0 4px 24px rgba(59,91,219,.10);
            --h-shadow-sm:0 2px 8px rgba(59,91,219,.06);
            --cal-sel:    #3b5bdb;
            --cal-sel-t:  #ffffff;
            --cal-hover:  #dbe4ff;
            --cal-today:  #e8f0fe;
            --cal-head:   #f8faff;
        }
        body.dark-mode {
            --h-bg:       #0f1117;
            --h-card:     #1a1d2e;
            --h-card-alt: #151827;
            --h-border:   #2a2d3e;
            --h-text:     #e2e8f0;
            --h-muted:    #8892a4;
            --h-primary:  #4c6ef5;
            --h-accent:   #339af0;
            --h-success:  #20c997;
            --h-warning:  #ffd43b;
            --h-danger:   #ff6b6b;
            --h-shadow:   0 4px 24px rgba(0,0,0,.35);
            --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
            --cal-sel:    #4c6ef5;
            --cal-sel-t:  #ffffff;
            --cal-hover:  #1e2a5e;
            --cal-today:  #22253a;
            --cal-head:   #151827;
        }

        /* ══ LAYOUT ══ */
        .hr-page { background:var(--h-bg); min-height:calc(100vh - 57px); padding-bottom:48px; }

        /* Hero */
        .hr-hero {
            background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#3b5bdb 100%);
            padding:32px 28px 62px; position:relative; overflow:hidden;
        }
        .hr-hero::before {
            content:''; position:absolute; inset:0;
            background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
        }
        .hr-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--h-bg); clip-path:ellipse(58% 100% at 50% 100%);
        }
        .hr-hero-inner { position:relative; z-index:2; }
        .hr-hero h1 { color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px; letter-spacing:-.3px; }
        .hr-hero p  { color:rgba(255,255,255,.7); margin:0 0 14px; font-size:.9rem; }
        .hr-hero-actions { position:relative; z-index:2; display:flex; align-items:flex-start; gap:10px; }
        .role-chip {
            display:inline-flex; align-items:center; gap:6px;
            border-radius:20px; padding:5px 14px;
            font-size:.78rem; font-weight:700; position:relative;
        }
        .rc-admin    { background:rgba(239,68,68,.25);  border:1px solid rgba(239,68,68,.45);  color:#fca5a5; }
        .rc-manager  { background:rgba(59,130,246,.25); border:1px solid rgba(59,130,246,.45); color:#93c5fd; }
        .rc-approver { background:rgba(16,185,129,.25); border:1px solid rgba(16,185,129,.45); color:#6ee7b7; }

        /* Apply leave hero button */
        .btn-apply-leave-hero {
            background:rgba(255,255,255,.15); backdrop-filter:blur(6px);
            border:1px solid rgba(255,255,255,.3); color:#fff;
            border-radius:10px; padding:9px 18px;
            font-size:.85rem; font-weight:700; cursor:pointer;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .18s, transform .18s;
        }
        .btn-apply-leave-hero:hover { background:rgba(255,255,255,.25); transform:translateY(-2px); }

        /* Content */
        .hr-content { padding:0 20px; margin-top:-28px; position:relative; z-index:3; }

        /* Stat cards */
        .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:22px; }
        @media(max-width:1100px){ .stats-row{ grid-template-columns:repeat(3,1fr); } }
        @media(max-width:600px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
        .stat-card {
            background:var(--h-card); border:1px solid var(--h-border);
            border-radius:14px; padding:18px 20px;
            display:flex; align-items:center; gap:14px;
            box-shadow:var(--h-shadow-sm); transition:transform .2s,box-shadow .2s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:var(--h-shadow); }
        .stat-ico {
            width:48px; height:48px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:#fff; flex-shrink:0;
        }
        .si-tot  { background:linear-gradient(135deg,#3b5bdb,#228be6); }
        .si-pend { background:linear-gradient(135deg,#e67700,#f59f00); }
        .si-appr { background:linear-gradient(135deg,#099268,#20c997); }
        .si-rejt { background:linear-gradient(135deg,#c92a2a,#e03131); }
        .si-canc { background:linear-gradient(135deg,#495057,#868e96); }
        .stat-val { font-size:1.8rem; font-weight:800; color:var(--h-text); line-height:1; }
        .stat-lbl { font-size:.72rem; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }

        /* General card */
        .h-card {
            background:var(--h-card); border:1px solid var(--h-border);
            border-radius:14px; overflow:hidden; box-shadow:var(--h-shadow-sm);
            transition:box-shadow .2s;
        }
        .h-card:hover { box-shadow:var(--h-shadow); }
        .h-card-head {
            padding:16px 22px; border-bottom:1px solid var(--h-border);
            background:var(--h-card-alt); display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:8px;
        }
        .h-card-head-left { display:flex; align-items:center; gap:12px; }
        .h-card-ico {
            width:36px; height:36px; border-radius:9px;
            background:linear-gradient(135deg,#3b5bdb,#228be6);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:14px; flex-shrink:0;
        }
        .h-card-head h5 { margin:0; font-size:1rem; font-weight:700; color:var(--h-text); }
        .h-rec-count {
            font-size:.74rem; color:var(--h-muted);
            background:var(--h-bg); border-radius:20px;
            padding:3px 10px; border:1px solid var(--h-border);
        }

        /* Filter bar */
        .filter-bar {
            background:var(--h-card); border:1px solid var(--h-border);
            border-radius:14px; padding:16px 20px; margin-bottom:20px;
            display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
            box-shadow:var(--h-shadow-sm);
        }
        .fg { margin:0; flex:1; min-width:120px; }
        .fg label {
            font-size:.7rem; font-weight:700; color:var(--h-muted);
            text-transform:uppercase; letter-spacing:.5px;
            margin-bottom:5px; display:block;
        }
        .h-ctrl {
            width:100%; background:var(--h-card); border:1.5px solid var(--h-border);
            border-radius:8px; padding:8px 12px; font-size:.85rem; color:var(--h-text);
            transition:border-color .18s, box-shadow .18s; box-sizing:border-box;
        }
        .h-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px rgba(59,91,219,.13); }
        .btn-filter {
            background:linear-gradient(135deg,#3b5bdb,#228be6); color:#fff;
            border:none; border-radius:8px; padding:9px 18px;
            font-size:.85rem; font-weight:700; cursor:pointer;
            display:inline-flex; align-items:center; gap:6px;
            transition:transform .15s, box-shadow .15s;
        }
        .btn-filter:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(59,91,219,.35); }
        .btn-reset {
            background:var(--h-card); color:var(--h-muted);
            border:1.5px solid var(--h-border); border-radius:8px;
            padding:9px 14px; font-size:.85rem; cursor:pointer;
            text-decoration:none; display:inline-flex; align-items:center; gap:6px;
            transition:background .15s;
        }
        .btn-reset:hover { background:var(--h-bg); color:var(--h-text); }

        /* View-only banner */
        .view-only-banner {
            background:#fff8e1; border:1px solid #f59f00; border-radius:10px;
            padding:11px 16px; display:flex; align-items:center; gap:10px;
            margin-bottom:20px; font-size:.86rem; color:#7c4c00; font-weight:500;
        }
        body.dark-mode .view-only-banner { background:#2e2000; border-color:#c07000; color:#ffd43b; }

        /* Table */
        .h-tbl { width:100%; border-collapse:collapse; }
        .h-tbl th {
            background:var(--h-card-alt); padding:11px 14px;
            font-size:.7rem; font-weight:700; color:var(--h-muted);
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:2px solid var(--h-border); white-space:nowrap;
        }
        .h-tbl td {
            padding:13px 14px; font-size:.87rem; color:var(--h-text);
            border-bottom:1px solid var(--h-border); vertical-align:middle;
        }
        .h-tbl tr:last-child td { border-bottom:none; }
        .h-tbl tbody tr { transition:background .12s; }
        .h-tbl tbody tr:hover td { background:var(--h-card-alt); }

        .emp-cell { display:flex; align-items:center; gap:10px; }
        .emp-av {
            width:36px; height:36px; border-radius:50%;
            background:linear-gradient(135deg,#3b5bdb,#228be6);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-weight:700; font-size:12px; flex-shrink:0; overflow:hidden;
        }
        .emp-av img { width:100%; height:100%; object-fit:cover; }
        .emp-name    { font-weight:600; font-size:.87rem; color:var(--h-text); }
        .emp-section { font-size:.72rem; color:var(--h-muted); }
        .appt-badge  { display:inline-block; padding:3px 9px; border-radius:20px; font-size:.71rem; font-weight:600; color:#fff; }

        .h-badge { padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; display:inline-block; }
        .hb-pend { background:#fff8e1; color:#b45309; }
        .hb-appr { background:#e6fbf4; color:#087f5b; }
        .hb-rejt { background:#fff0f0; color:#c92a2a; }
        .hb-canc { background:#f1f5f9; color:#64748b; }
        body.dark-mode .hb-pend { background:#3d2e00; color:#ffd43b; }
        body.dark-mode .hb-appr { background:#0d3d2c; color:#63e6be; }
        body.dark-mode .hb-rejt { background:#3d0f0f; color:#ff8787; }
        body.dark-mode .hb-canc { background:#1e2030; color:#8892a4; }

        .days-pill {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:30px; height:26px; border-radius:20px;
            background:linear-gradient(135deg,#3b5bdb,#228be6);
            color:#fff; font-weight:800; font-size:.74rem; padding:0 8px;
        }
        .action-btns { display:flex; gap:5px; align-items:center; }
        .btn-act {
            width:30px; height:30px; border-radius:7px; border:none;
            cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
            font-size:12px; transition:all .15s;
        }
        .ba-view   { background:#eff6ff; color:#3b82f6; }
        .ba-view:hover   { background:#3b82f6; color:#fff; }
        .ba-appr   { background:#d1fae5; color:#059669; }
        .ba-appr:hover   { background:#059669; color:#fff; }
        .ba-rejt   { background:#fee2e2; color:#dc2626; }
        .ba-rejt:hover   { background:#dc2626; color:#fff; }
        .ba-form   { background:linear-gradient(135deg,#3b5bdb,#228be6); color:#fff; text-decoration:none; }
        .ba-form:hover   { opacity:.82; color:#fff; }
        .ba-del    { background:#fff0f0; color:#9b1c1c; border: 1.5px solid #fca5a5; }
        .ba-del:hover    { background:#9b1c1c; color:#fff; border-color:#9b1c1c; }
        .lock-ico  { color:var(--h-border); font-size:13px; cursor:default; }

        .h-empty { text-align:center; padding:50px 20px; }
        .h-empty i { font-size:46px; opacity:.2; display:block; margin-bottom:14px; color:var(--h-muted); }
        .h-empty p { color:var(--h-muted); }

        /* access indicator */
        .acc-ind { display:inline-flex; align-items:center; gap:5px; font-size:.72rem; font-weight:600; border-radius:6px; padding:3px 9px; }
        .ai-full { background:#d1fae5; color:#065f46; }
        .ai-view { background:#fff8e1; color:#92400e; }
        body.dark-mode .ai-full { background:#0d3d2c; color:#63e6be; }
        body.dark-mode .ai-view { background:#3d2e00; color:#ffd43b; }

        /* ══ MODALS ══ */
        .hm-modal .modal-content { border-radius:14px; border:none; overflow:hidden; background:var(--h-card); }
        .hm-modal .modal-header {
            background:linear-gradient(135deg,#0f172a,#3b5bdb);
            color:#fff; border:none; padding:18px 24px;
        }
        .hm-modal .modal-header .close { color:#fff; opacity:.7; }
        .hm-modal .modal-header .close:hover { opacity:1; }
        .hm-modal .modal-title { font-size:1rem; font-weight:700; }
        .hm-modal .modal-body  { padding:24px; }
        .hm-modal .modal-footer { border-top:1px solid var(--h-border); padding:14px 24px; background:var(--h-card-alt); }

        /* Detail grid */
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
        @media(max-width:520px){ .detail-grid{ grid-template-columns:1fr; } }
        .detail-item label { font-size:.68rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:3px; }
        .detail-item span  { font-size:.9rem; color:var(--h-text); font-weight:500; }
        .info-box {
            background:var(--h-card-alt); border:1px solid var(--h-border);
            border-radius:8px; padding:12px 14px; font-size:.87rem;
            color:var(--h-text); margin-top:4px;
        }
        .perm-note {
            background:#fff8e1; border:1px solid #f59f00; border-radius:8px;
            padding:9px 14px; font-size:.82rem; color:#7c4c00;
            display:flex; align-items:center; gap:7px; width:100%;
        }
        body.dark-mode .perm-note { background:#2e2000; border-color:#c07000; color:#ffd43b; }

        /* Apply Leave Modal form */
        .al-label {
            font-size:.72rem; font-weight:700; color:var(--h-muted);
            text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:5px;
        }
        .al-ctrl {
            width:100%; background:var(--h-card); border:1.5px solid var(--h-border);
            border-radius:8px; padding:9px 13px; font-size:.88rem; color:var(--h-text);
            transition:border-color .18s,box-shadow .18s; box-sizing:border-box;
        }
        .al-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px rgba(59,91,219,.13); }
        .al-ctrl select { appearance:none; }
        .al-fg { margin-bottom:14px; }

        /* Mini calendar for modal */
        .mini-cal-wrap { border:1.5px solid var(--h-border); border-radius:8px; overflow:hidden; }
        .mini-cal-nav {
            display:flex; align-items:center; justify-content:space-between;
            padding:8px 12px; background:var(--cal-head); border-bottom:1px solid var(--h-border);
        }
        .mini-cal-btn {
            background:var(--h-card); border:1px solid var(--h-border);
            border-radius:5px; width:26px; height:26px; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            color:var(--h-muted); font-size:10px; transition:background .14s,color .14s;
        }
        .mini-cal-btn:hover { background:var(--h-primary); color:#fff; border-color:var(--h-primary); }
        .mini-cal-lbl { font-weight:800; font-size:.82rem; color:var(--h-text); }
        .mini-cal-grid {
            display:grid; grid-template-columns:repeat(7,1fr);
            gap:2px; padding:8px; background:var(--h-card);
        }
        .mcdn { text-align:center; font-size:.6rem; font-weight:700; color:var(--h-muted); padding:3px 0; text-transform:uppercase; }
        .mcd {
            aspect-ratio:1; display:flex; align-items:center; justify-content:center;
            border-radius:5px; font-size:.78rem; cursor:pointer;
            color:var(--h-text); font-weight:500; user-select:none;
            transition:background .1s,color .1s;
        }
        .mcd:hover:not(.mcd-dis):not(.mcd-emp):not(.mcd-wknd) { background:var(--cal-hover); }
        .mcd.mcd-today { background:var(--cal-today); font-weight:700; }
        .mcd.mcd-sel   { background:var(--cal-sel); color:var(--cal-sel-t); font-weight:700; }
        .mcd.mcd-dis   { color:var(--h-border); cursor:default; pointer-events:none; }
        .mcd.mcd-emp   { cursor:default; pointer-events:none; }
        .mcd.mcd-wknd  { color:var(--h-danger); opacity:.4; cursor:not-allowed; pointer-events:none; }
        /* approved leave: red-tinted, unclickable */
        .mcd.mcd-leave {
            background:#fff0f0; color:#c92a2a;
            cursor:not-allowed; pointer-events:none;
            font-weight:600; position:relative;
        }
        .mcd.mcd-leave::after {
            content:''; position:absolute; bottom:3px; left:50%;
            transform:translateX(-50%);
            width:4px; height:4px; border-radius:50%; background:#c92a2a;
        }
        /* legend row inside the modal */
        .al-cal-legend {
            display:flex; flex-wrap:wrap; gap:8px 16px;
            padding:5px 8px 8px; font-size:.72rem; color:var(--h-muted);
        }
        .al-cal-legend span { display:flex; align-items:center; gap:4px; }
        .al-cal-legend-dot  { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
        /* tags */
        .al-tags { min-height:36px; padding:5px 8px 8px; display:flex; flex-wrap:wrap; gap:5px; border-top:1px solid var(--h-border); }
        .al-tag {
            display:inline-flex; align-items:center; gap:4px;
            background:#dbe4ff; color:#2f4ac0;
            border-radius:20px; padding:2px 8px 2px 10px;
            font-size:.73rem; font-weight:700; animation:tagIn .15s ease;
        }
        @keyframes tagIn{ from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
        body.dark-mode .al-tag { background:#1e2a5e; color:#91a7ff; }
        .al-tag-rm {
            background:none; border:none; cursor:pointer;
            color:#2f4ac0; opacity:.55; font-size:11px;
            padding:0; line-height:1; display:flex; align-items:center;
            transition:opacity .15s;
        }
        body.dark-mode .al-tag-rm { color:#91a7ff; }
        .al-tag-rm:hover { opacity:1; }
        .al-hint { color:var(--h-muted); font-size:.75rem; align-self:center; font-style:italic; }
        .al-footer-bar {
            display:flex; align-items:center; gap:7px;
            padding:4px 8px 8px; font-size:.78rem; color:var(--h-muted);
        }
        .al-pill {
            background:linear-gradient(135deg,#3b5bdb,#228be6);
            color:#fff; border-radius:20px; padding:1px 9px;
            font-weight:700; font-size:.75rem;
        }

        /* Submit btn in modal */
        .btn-submit-al {
            background:linear-gradient(135deg,#3b5bdb,#228be6);
            color:#fff; border:none; border-radius:9px;
            padding:11px 24px; font-size:.9rem; font-weight:700; cursor:pointer;
            display:inline-flex; align-items:center; gap:7px;
            transition:transform .15s,box-shadow .15s;
        }
        .btn-submit-al:hover { transform:translateY(-1px); box-shadow:0 5px 16px rgba(59,91,219,.4); }

        @media(max-width:768px){
            .hr-hero{ padding:24px 16px 50px; }
            .hr-content{ padding:0 12px; }
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
            <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:14px;position:relative;z-index:2;">
                <div class="hr-hero-inner">
                    <h1><i class="fas fa-clipboard-list mr-2" style="opacity:.85"></i>HR Leave Monitoring</h1>
                    <p>Review, manage &amp; file employee leave requests &mdash; Job Order excluded</p>
                    <?php
                    if ($user_role_id===1)
                        echo '<span class="role-chip rc-admin"><i class="fas fa-shield-alt"></i> Administrator — Full Access</span>';
                    elseif ($user_role_id===2)
                        echo '<span class="role-chip rc-manager"><i class="fas fa-eye"></i> Manager — View Only</span>';
                    else
                        echo '<span class="role-chip rc-approver"><i class="fas fa-check-circle"></i> '.htmlspecialchars($current_role_label).' — Can Approve</span>';
                    ?>
                </div>
                <div class="hr-hero-actions">
                    <span style="color:rgba(255,255,255,.65);font-size:.82rem;align-self:center;">
                        <i class="fas fa-calendar mr-1"></i><?= date('F d, Y') ?>
                    </span>
                    <a href="leave_balance.php" class="btn-apply-leave-hero" style="text-decoration:none;">
                        <i class="fas fa-wallet"></i> Leave Balances
                    </a>
                    <?php if ($can_approve): ?>
                    <button class="btn-apply-leave-hero" id="btnOpenApplyLeave" type="button">
                        <i class="fas fa-plus-circle"></i> Apply Leave for Employee
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="hr-content">

            <?php if (!$can_approve): ?>
            <div class="view-only-banner">
                <i class="fas fa-info-circle" style="color:var(--h-warning)"></i>
                You have <strong>view-only</strong> access. Only <strong>Administrator</strong>, <strong>Heads</strong>, and <strong>Unit Head</strong> roles can approve or reject leave requests.
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="stat-ico si-tot"><i class="fas fa-layer-group"></i></div><div><div class="stat-val"><?= $stats['total']??0 ?></div><div class="stat-lbl">Total</div></div></div>
                <div class="stat-card"><div class="stat-ico si-pend"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-val"><?= $stats['pending']??0 ?></div><div class="stat-lbl">Pending</div></div></div>
                <div class="stat-card"><div class="stat-ico si-appr"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?= $stats['approved']??0 ?></div><div class="stat-lbl">Approved</div></div></div>
                <div class="stat-card"><div class="stat-ico si-rejt"><i class="fas fa-times-circle"></i></div><div><div class="stat-val"><?= $stats['rejected']??0 ?></div><div class="stat-lbl">Rejected</div></div></div>
                <div class="stat-card"><div class="stat-ico si-canc"><i class="fas fa-ban"></i></div><div><div class="stat-val"><?= $stats['cancelled']??0 ?></div><div class="stat-lbl">Cancelled</div></div></div>
            </div>

            <!-- Filter bar -->
            <div class="filter-bar">
                <div class="fg">
                    <label>Status</label>
                    <select id="f_status" class="h-ctrl">
                        <option value="">All Status</option>
                        <option value="Pending"   <?= $filter_status==='Pending'   ?'selected':'' ?>>Pending</option>
                        <option value="Approved"  <?= $filter_status==='Approved'  ?'selected':'' ?>>Approved</option>
                        <option value="Rejected"  <?= $filter_status==='Rejected'  ?'selected':'' ?>>Rejected</option>
                        <option value="Cancelled" <?= $filter_status==='Cancelled' ?'selected':'' ?>>Cancelled</option>
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
                    <label>Appointment</label>
                    <select id="f_appt" class="h-ctrl">
                        <option value="">All Types</option>
                        <?php foreach($appt_statuses as $ap): ?>
                        <option value="<?= $ap['appointment_id'] ?>" <?= $filter_appt==$ap['appointment_id']?'selected':'' ?>><?= htmlspecialchars($ap['status_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Month</label>
                    <input type="month" id="f_month" class="h-ctrl" value="<?= $filter_month ?>">
                </div>
                <button class="btn-filter" onclick="applyFilters()"><i class="fas fa-filter"></i> Filter</button>
                <a href="hr_leave_monitoring.php" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
            </div>

            <!-- Table card -->
            <div class="h-card">
                <div class="h-card-head">
                    <div class="h-card-head-left">
                        <div class="h-card-ico"><i class="fas fa-table"></i></div>
                        <h5>Leave Requests</h5>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <?php if($can_approve): ?>
                        <span class="acc-ind ai-full"><i class="fas fa-unlock-alt"></i> Full Access</span>
                        <?php else: ?>
                        <span class="acc-ind ai-view"><i class="fas fa-lock"></i> View Only</span>
                        <?php endif; ?>
                        <span class="h-rec-count"><?= count($requests) ?> record(s)</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if(empty($requests)): ?>
                    <div class="h-empty">
                        <i class="fas fa-inbox"></i>
                        <p>No leave requests found matching your filters.</p>
                    </div>
                    <?php else: ?>
                    <table class="h-tbl" id="hrLeaveTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Appointment</th>
                                <th>Leave Type</th>
                                <th>Date From</th>
                                <th>Date To</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Filed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($requests as $i=>$req):
                            $init='';
                            foreach(explode(' ',$req['emp_name']) as $p) if($p) $init.=strtoupper(substr($p,0,1));
                            $init=substr($init,0,2);
                            $apptColor=$req['appt_color']??'#64748b';
                            $s=strtolower($req['status']??'pending');
                            $bc=$s==='approved'?'hb-appr':($s==='rejected'||$s==='disapproved'?'hb-rejt':($s==='cancelled'?'hb-canc':'hb-pend'));
                        ?>
                        <tr>
                            <td style="color:var(--h-muted);font-size:.77rem;"><?= $i+1 ?></td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-av">
                                        <?php if(!empty($req['picture'])): ?>
                                        <img src="../dist/img/employees/<?= htmlspecialchars($req['picture']) ?>"
                                             onerror="this.style.display='none';this.parentNode.textContent='<?= $init ?>'" alt="">
                                        <?php else: ?><?= $init ?><?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($req['emp_name']) ?></div>
                                        <div class="emp-section"><?= htmlspecialchars($req['section_name']??'No Section') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="appt-badge" style="background:<?= htmlspecialchars($apptColor) ?>"><?= htmlspecialchars($req['appointment_status']??'N/A') ?></span></td>
                            <td><?= htmlspecialchars($req['leave_type_name']??'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($req['date_from'])) ?></td>
                            <td><?= date('M d, Y', strtotime($req['date_to'])) ?></td>
                            <td><span class="days-pill"><?= $req['number_of_days'] ?>d</span></td>
                            <td><span class="h-badge <?= $bc ?>"><?= ucfirst($req['status']) ?></span></td>
                            <td style="color:var(--h-muted);font-size:.77rem;"><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                            <td>
                                <div class="action-btns">
                                    <?php if($s==='approved'): ?>
                                    <a class="btn-act ba-form"
                                       href="generate_leave_form.php?leave_request_id=<?= $req['leave_request_id'] ?>&hr=1"
                                       target="_blank"
                                       title="Generate Leave Form">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn-act ba-view btn-view-detail" data-id="<?= $req['leave_request_id'] ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if($s==='pending'): ?>
                                        <?php if($can_approve): ?>
                                        <button class="btn-act ba-appr btn-approve"
                                                data-id="<?= $req['leave_request_id'] ?>"
                                                data-name="<?= htmlspecialchars($req['emp_name']) ?>"
                                                title="Approve"><i class="fas fa-check"></i></button>
                                        <button class="btn-act ba-rejt btn-reject"
                                                data-id="<?= $req['leave_request_id'] ?>"
                                                data-name="<?= htmlspecialchars($req['emp_name']) ?>"
                                                title="Reject"><i class="fas fa-times"></i></button>
                                        <?php else: ?>
                                        <span class="lock-ico" title="Requires elevated role"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    <?php elseif(in_array($s,['cancelled','rejected','disapproved']) && $can_approve): ?>
                                        <button class="btn-act ba-del btn-hr-delete"
                                                data-id="<?= $req['leave_request_id'] ?>"
                                                data-name="<?= htmlspecialchars($req['emp_name']) ?>"
                                                title="Delete Record"><i class="fas fa-trash-alt"></i></button>
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

    <!-- ══ Detail Modal ══ -->
    <div class="modal fade hm-modal" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-alt mr-2"></i>Leave Request Details</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x" style="color:#3b5bdb;"></i></div>
                </div>
                <div class="modal-footer" id="detailModalFooter" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- ══ Apply Leave for Employee Modal ══ -->
    <?php if($can_approve): ?>
    <div class="modal fade hm-modal" id="applyLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Apply Leave for Employee</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="alEmpLoading" class="text-center py-3" style="display:none">
                        <i class="fas fa-spinner fa-spin fa-2x" style="color:#3b5bdb;"></i>
                        <p style="margin-top:10px;color:var(--h-muted);">Loading employees…</p>
                    </div>
                    <div id="alFormContent">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Employee select -->
                                <div class="al-fg">
                                    <label class="al-label">Employee <span style="color:#c92a2a">*</span></label>
                                    <select id="alEmpId" class="al-ctrl">
                                        <option value="">— Select Employee —</option>
                                    </select>
                                </div>
                                <!-- Leave type -->
                                <div class="al-fg">
                                    <label class="al-label">Leave Type <span style="color:#c92a2a">*</span></label>
                                    <select id="alLeaveType" class="al-ctrl">
                                        <option value="">— Select Leave Type —</option>
                                        <?php foreach($leave_types as $lt): ?>
                                        <option value="<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Reason -->
                                <div class="al-fg">
                                    <label class="al-label">Reason / Details <span style="color:#c92a2a">*</span></label>
                                    <textarea id="alReason" class="al-ctrl" rows="4" placeholder="State the reason for this leave…" style="resize:vertical;min-height:80px;"></textarea>
                                </div>
                                <!-- Day counter -->
                                <div class="al-footer-bar" id="alCountBar" style="display:none">
                                    <span class="al-pill" id="alCount">0</span> working day(s) selected
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Mini calendar -->
                                <div class="al-fg">
                                    <label class="al-label">Select Leave Date(s) <span style="color:#c92a2a">*</span>
                                        <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.7rem;margin-left:4px;">(click to pick, again to remove)</span>
                                    </label>
                                    <div class="mini-cal-wrap">
                                        <div class="mini-cal-nav">
                                            <button type="button" class="mini-cal-btn" id="alCalPrev"><i class="fas fa-chevron-left"></i></button>
                                            <span class="mini-cal-lbl" id="alCalLabel"></span>
                                            <button type="button" class="mini-cal-btn" id="alCalNext"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                        <div class="mini-cal-grid" id="alCalGrid"></div>
                                        <div class="al-tags" id="alTags"></div>
                                        <!-- legend -->
                                        <div class="al-cal-legend">
                                            <span><span class="al-cal-legend-dot" style="background:var(--cal-sel)"></span>Selected</span>
                                            <span><span class="al-cal-legend-dot" style="background:#c92a2a"></span>Approved Leave</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-submit-al" id="btnSubmitApplyLeave">
                        <i class="fas fa-paper-plane"></i> Submit Leave Request
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /wrapper -->

<?php include '../includes/footer.php'; ?>

<script>
var CAN_APPROVE = <?= $can_approve?'true':'false' ?>;

/* ════════════════════════════════════════════════════════
   MINI CALENDAR (for Apply Leave modal)
   Identical logic to main calendar, self-contained
════════════════════════════════════════════════════════ */
var alCal = (function(){
    var MONTHS=['January','February','March','April','May','June',
                'July','August','September','October','November','December'];
    var WDAYS=['Su','Mo','Tu','We','Th','Fr','Sa'];
    var sel      = {};   // selected dates
    var approved = {};   // employee's already-approved leave dates
    var yr, mo;
    var today = new Date(); today.setHours(0,0,0,0);

    function pad(n){ return String(n).padStart(2,'0'); }
    function toKey(y,m,d){ return y+'-'+pad(m+1)+'-'+pad(d); }
    function isWknd(y,m,d){ var w=new Date(y,m,d).getDay(); return w===0||w===6; }
    function isPast(y,m,d){ return new Date(y,m,d)<today; }
    function getKeys(){ return Object.keys(sel).sort(); }

    function renderGrid(){
        var grid=document.getElementById('alCalGrid');
        var lbl=document.getElementById('alCalLabel');
        lbl.textContent=MONTHS[mo]+' '+yr;
        var html='';
        for(var i=0;i<7;i++) html+='<div class="mcdn">'+WDAYS[i]+'</div>';
        var first=new Date(yr,mo,1).getDay();
        var dim  =new Date(yr,mo+1,0).getDate();
        for(var e=0;e<first;e++) html+='<div class="mcd mcd-emp"></div>';
        for(var d=1;d<=dim;d++){
            var key=toKey(yr,mo,d);
            var past=isPast(yr,mo,d), wknd=isWknd(yr,mo,d);
            var leave=!!approved[key];   // ← approved leave flag
            var s=!!sel[key];
            var isNow=(yr===today.getFullYear()&&mo===today.getMonth()&&d===today.getDate());
            var cls='mcd';
            if(past)       cls+=' mcd-dis';
            else if(wknd)  cls+=' mcd-wknd';
            else if(leave) cls+=' mcd-leave';   // ← block & highlight
            else if(s)     cls+=' mcd-sel';
            else if(isNow) cls+=' mcd-today';
            // only attach data-k when the day is actually clickable
            var da=(!past&&!wknd&&!leave)?'data-k="'+key+'"':'';
            html+='<div class="'+cls+'" '+da+' title="'+(leave?'Approved leave':'')+'">'+d+'</div>';
        }
        grid.innerHTML=html;
        var cells=grid.querySelectorAll('.mcd[data-k]');
        for(var c=0;c<cells.length;c++){
            (function(cell){ cell.addEventListener('click',function(){ toggle(cell.dataset.k); }); })(cells[c]);
        }
    }

    function toggle(key){
        if(sel[key]) delete sel[key]; else sel[key]=true;
        renderGrid(); renderTags();
    }

    function remove(key){
        delete sel[key]; renderGrid(); renderTags();
    }

    function renderTags(){
        var tagsEl=document.getElementById('alTags');
        var cntBar=document.getElementById('alCountBar');
        var cntEl =document.getElementById('alCount');
        tagsEl.innerHTML='';
        var keys=getKeys();
        if(keys.length===0){
            cntBar.style.display='none';
            var h=document.createElement('span');
            h.className='al-hint'; h.textContent='No dates selected yet';
            tagsEl.appendChild(h); return;
        }
        cntBar.style.display='';
        cntEl.textContent=keys.length;
        for(var i=0;i<keys.length;i++){
            var key=keys[i];
            var dt=new Date(key+'T00:00:00');
            var lbl=dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
            var tag=document.createElement('span');
            tag.className='al-tag';
            var txt=document.createTextNode(lbl+' ');
            tag.appendChild(txt);
            var btn=document.createElement('button');
            btn.type='button'; btn.className='al-tag-rm';
            btn.setAttribute('data-rk',key);
            btn.innerHTML='<i class="fas fa-times"></i>';
            tag.appendChild(btn);
            tagsEl.appendChild(tag);
        }
        var rms=tagsEl.querySelectorAll('.al-tag-rm');
        for(var j=0;j<rms.length;j++){
            (function(b){ b.addEventListener('click',function(){ remove(b.getAttribute('data-rk')); }); })(rms[j]);
        }
    }

    function reset(){
        sel={}; approved={}; yr=today.getFullYear(); mo=today.getMonth();
        renderGrid(); renderTags();
    }

    /* Called whenever the HR changes the selected employee */
    function setApproved(datesArray){
        approved={};
        for(var i=0;i<datesArray.length;i++) approved[datesArray[i]]=true;
        // Drop any selected date that now falls on an approved-leave day
        var selKeys=Object.keys(sel);
        for(var j=0;j<selKeys.length;j++){
            if(approved[selKeys[j]]) delete sel[selKeys[j]];
        }
        renderGrid(); renderTags();
    }

    function init(){
        yr=today.getFullYear(); mo=today.getMonth();
        document.getElementById('alCalPrev').addEventListener('click',function(){
            mo--; if(mo<0){mo=11;yr--;} renderGrid();
        });
        document.getElementById('alCalNext').addEventListener('click',function(){
            mo++; if(mo>11){mo=0;yr++;} renderGrid();
        });
        renderGrid(); renderTags();
    }

    return {
        init:        init,
        reset:       reset,
        getKeys:     getKeys,
        setApproved: setApproved
    };
})();


/* ════════════════════════════════════════════════════════
   JQUERY
════════════════════════════════════════════════════════ */
$(document).ready(function(){

    /* ── DataTable ── */
    if($('#hrLeaveTable tbody tr').length){
        $('#hrLeaveTable').DataTable({
            pageLength:15, order:[[8,'desc']],
            columnDefs:[{orderable:false,targets:[0,9]}],
            dom:'<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
            language:{search:'',searchPlaceholder:' Search employees…'}
        });
    }

    /* ── Filter apply ── */
    window.applyFilters = function(){
        var p=new URLSearchParams({
            status:$('#f_status').val(),
            section:$('#f_section').val(),
            appt:$('#f_appt').val(),
            month:$('#f_month').val()
        });
        window.location.href='hr_leave_monitoring.php?'+p.toString();
    };

    /* ── View Detail ── */
    $(document).on('click','.btn-view-detail',function(){
        var id=$(this).data('id');
        $('#detailModalBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x" style="color:#3b5bdb;"></i></div>');
        $('#detailModalFooter').hide().html('');
        $('#detailModal').modal('show');

        $.post('hr_leave_monitoring.php',{ajax:1,action:'get_details',leave_request_id:id},function(d){
            if(!d){$('#detailModalBody').html('<p class="text-center py-4" style="color:var(--h-danger)">Could not load details.</p>');return;}
            var s=(d.status||'').toLowerCase();
            var bc=s==='approved'?'hb-appr':s==='rejected'?'hb-rejt':s==='cancelled'?'hb-canc':'hb-pend';
            $('#detailModalBody').html(
                '<div class="detail-grid mb-4">'+
                '<div class="detail-item"><label>Employee</label><span>'+(d.emp_name||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>ID Number</label><span>'+(d.id_number||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Section</label><span>'+(d.section_name||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Position</label><span>'+(d.position_name||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Appointment</label><span class="appt-badge" style="background:'+(d.color||'#64748b')+'">'+(d.appointment_status||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Leave Type</label><span>'+(d.leave_type_name||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Date From</label><span>'+(d.date_from||'')+'</span></div>'+
                '<div class="detail-item"><label>Date To</label><span>'+(d.date_to||'')+'</span></div>'+
                '<div class="detail-item"><label>Inclusive Dates</label><span>'+(d.inclusive_dates||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Working Days</label><span>'+(d.number_of_days||0)+'</span></div>'+
                '<div class="detail-item"><label>Status</label><span class="h-badge '+bc+'">'+(d.status||'N/A')+'</span></div>'+
                '<div class="detail-item"><label>Filed On</label><span>'+(d.created_at||'')+'</span></div>'+
                (d.approved_by_name?'<div class="detail-item"><label>Processed By</label><span>'+d.approved_by_name+'</span></div>':'')+
                (d.approved_at     ?'<div class="detail-item"><label>Processed On</label><span>'+d.approved_at+'</span></div>':'')+
                '</div>'+
                '<div class="mb-3"><label style="font-size:.68rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;letter-spacing:.4px;">Reason / Details</label>'+
                '<div class="info-box">'+(d.reason||'N/A')+'</div></div>'+
                (d.hr_remarks?'<div><label style="font-size:.68rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;letter-spacing:.4px;">HR Remarks</label><div class="info-box">'+d.hr_remarks+'</div></div>':'')
            );

            if(s==='pending'){
                if(CAN_APPROVE){
                    $('#detailModalFooter').show().html(
                        '<button class="btn btn-success btn-sm btn-approve" data-id="'+d.leave_request_id+'" data-name="'+d.emp_name+'" data-dismiss="modal"><i class="fas fa-check mr-1"></i>Approve</button>'+
                        '<button class="btn btn-danger btn-sm btn-reject" data-id="'+d.leave_request_id+'" data-name="'+d.emp_name+'" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Reject</button>'
                    );
                } else {
                    $('#detailModalFooter').show().html(
                        '<div class="perm-note"><i class="fas fa-lock"></i>Approval requires <strong>Administrator</strong>, <strong>Heads</strong>, or <strong>Unit Head</strong> role.</div>'
                    );
                }
            } else if(s==='approved'){
                $('#detailModalFooter').show().html(
                    '<a class="btn btn-primary btn-sm" href="generate_leave_form.php?leave_request_id='+d.leave_request_id+'&hr=1" target="_blank"><i class="fas fa-file-download mr-1"></i>Generate Form</a>'
                );
            } else if((s==='cancelled'||s==='rejected'||s==='disapproved') && CAN_APPROVE){
                $('#detailModalFooter').show().html(
                    '<button class="btn btn-danger btn-sm btn-hr-delete" data-id="'+d.leave_request_id+'" data-name="'+d.emp_name+'" data-dismiss="modal"><i class="fas fa-trash-alt mr-1"></i>Delete Record</button>'
                );
            } else {
                $('#detailModalFooter').hide();
            }
        },'json');
    });

    /* ── Approve ── */
    $(document).on('click','.btn-approve',function(){
        if(!CAN_APPROVE){Swal.fire({icon:'error',title:'Permission Denied',text:'Not authorised.',confirmButtonColor:'#c92a2a'});return;}
        var id=$(this).data('id'),name=$(this).data('name');
        Swal.fire({
            title:'Approve Leave Request?',
            html:'<p style="margin-bottom:10px;font-size:.9rem">Employee: <strong>'+name+'</strong></p>'+
                 '<label style="display:block;text-align:left;font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;">HR REMARKS (optional)</label>'+
                 '<textarea id="swal-remarks" class="swal2-textarea" placeholder="Add remarks…" style="font-size:.87rem;border-radius:8px;width:100%;"></textarea>',
            icon:'question',showCancelButton:true,
            confirmButtonColor:'#099268',cancelButtonColor:'#64748b',
            confirmButtonText:'<i class="fas fa-check"></i>&nbsp;Approve',cancelButtonText:'Cancel',
            preConfirm:()=>document.getElementById('swal-remarks').value||''
        }).then(function(r){
            if(!r.isConfirmed) return;
            $.post('hr_leave_monitoring.php',{ajax:1,action:'approve',leave_request_id:id,hr_remarks:r.value},function(res){
                if(res.success){
                    Swal.fire({
                        icon:'success',
                        title:'Approved!',
                        text:'Leave request approved.',
                        confirmButtonColor:'#099268',
                        confirmButtonText:'<i class="fas fa-file-download"></i>&nbsp;Generate Form',
                        showDenyButton:true,
                        denyButtonText:'Close',
                        denyButtonColor:'#64748b'
                    }).then(function(sr){
                        if(sr.isConfirmed) window.open('generate_leave_form.php?leave_request_id='+id+'&hr=1','_blank');
                        location.reload();
                    });
                }
                else Swal.fire({icon:'error',title:'Error',text:res.message||'Could not approve.',confirmButtonColor:'#c92a2a'});
            },'json');
        });
    });

    /* ── Reject ── */
    $(document).on('click','.btn-reject',function(){
        if(!CAN_APPROVE){Swal.fire({icon:'error',title:'Permission Denied',text:'Not authorised.',confirmButtonColor:'#c92a2a'});return;}
        var id=$(this).data('id'),name=$(this).data('name');
        Swal.fire({
            title:'Reject Leave Request?',
            html:'<p style="margin-bottom:10px;font-size:.9rem">Employee: <strong>'+name+'</strong></p>'+
                 '<label style="display:block;text-align:left;font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;">REASON FOR REJECTION <span style="color:#c92a2a">*</span></label>'+
                 '<textarea id="swal-remarks" class="swal2-textarea" placeholder="State the reason…" style="font-size:.87rem;border-radius:8px;width:100%;"></textarea>',
            icon:'warning',showCancelButton:true,
            confirmButtonColor:'#c92a2a',cancelButtonColor:'#64748b',
            confirmButtonText:'<i class="fas fa-times"></i>&nbsp;Reject',cancelButtonText:'Cancel',
            preConfirm:()=>{ var v=document.getElementById('swal-remarks').value.trim(); if(!v){Swal.showValidationMessage('Please provide a reason.');return false;} return v; }
        }).then(function(r){
            if(!r.isConfirmed) return;
            $.post('hr_leave_monitoring.php',{ajax:1,action:'reject',leave_request_id:id,hr_remarks:r.value},function(res){
                if(res.success){Swal.fire({icon:'success',title:'Rejected',text:'Leave request rejected.',confirmButtonColor:'#3b5bdb'}).then(()=>location.reload());}
                else Swal.fire({icon:'error',title:'Error',text:res.message||'Could not reject.',confirmButtonColor:'#c92a2a'});
            },'json');
        });
    });

    /* ── HR Delete ── */
    $(document).on('click','.btn-hr-delete',function(){
        var id=$(this).data('id'), name=$(this).data('name');
        var $row=$(this).closest('tr');
        Swal.fire({
            title:'Delete Leave Record?',
            html:'<p style="font-size:.9rem;color:#495057;">You are about to permanently delete the cancelled/rejected leave record of <strong>'+name+'</strong>.<br><br><strong>This cannot be undone.</strong></p>',
            icon:'warning',
            showCancelButton:true,
            confirmButtonColor:'#9b1c1c',
            cancelButtonColor:'#64748b',
            confirmButtonText:'<i class="fas fa-trash-alt"></i>&nbsp;Yes, Delete It',
            cancelButtonText:'Keep It'
        }).then(function(r){
            if(!r.isConfirmed) return;
            $.post('hr_leave_monitoring.php',{ajax:1,action:'delete',leave_request_id:id},function(res){
                if(res.success){
                    if($.fn.DataTable.isDataTable('#hrLeaveTable')){
                        $('#hrLeaveTable').DataTable().row($row).remove().draw();
                    } else {
                        $row.fadeOut(300,function(){ $(this).remove(); });
                    }
                    Swal.fire({icon:'success',title:'Deleted',text:'Record permanently deleted.',confirmButtonColor:'#3b5bdb',timer:2000,showConfirmButton:false});
                } else {
                    Swal.fire({icon:'error',title:'Error',text:res.message||'Could not delete.',confirmButtonColor:'#c92a2a'});
                }
            },'json');
        });
    });

    /* ══════════════════════════════════════════════
       APPLY LEAVE FOR EMPLOYEE
    ══════════════════════════════════════════════ */
    <?php if($can_approve): ?>
    var employeesList = [];

    $('#btnOpenApplyLeave').on('click',function(){
        $('#applyLeaveModal').modal('show');
    });

    $('#applyLeaveModal').on('show.bs.modal',function(){
        alCal.reset();
        $('#alEmpId').val('');
        $('#alLeaveType').val('');
        $('#alReason').val('');

        if(employeesList.length===0){
            $('#alEmpLoading').show();
            $.post('hr_leave_monitoring.php',{ajax:1,action:'get_employees'},function(res){
                $('#alEmpLoading').hide();
                if(res.success){
                    employeesList=res.employees;
                    var opts='<option value="">— Select Employee —</option>';
                    for(var i=0;i<employeesList.length;i++){
                        var e=employeesList[i];
                        opts+='<option value="'+e.emp_id+'">'+e.full_name+' ('+( e.section_name||'No Section' )+')</option>';
                    }
                    $('#alEmpId').html(opts);
                }
            },'json');
        }
    });

    /* ── When HR picks an employee, load their approved leave dates ── */
    $(document).on('change','#alEmpId',function(){
        var empId=$(this).val();
        // clear any previously selected calendar dates and approved highlights
        alCal.reset();
        if(!empId) return;

        // show a subtle spinner on the calendar header while loading
        $('#alCalLabel').html('<i class="fas fa-spinner fa-spin" style="font-size:.75rem;color:var(--h-muted)"></i>');

        $.post('hr_leave_monitoring.php',{ajax:1,action:'get_emp_approved_dates',emp_id:empId},function(res){
            if(res.success){
                alCal.setApproved(res.dates);
            }
        },'json');
    });

    $('#btnSubmitApplyLeave').on('click',function(){
        var empId     = $('#alEmpId').val();
        var leaveType = $('#alLeaveType').val();
        var reason    = $('#alReason').val().trim();
        var keys      = alCal.getKeys();

        if(!empId){    Swal.fire({icon:'warning',title:'Select Employee',   text:'Please select an employee.',       confirmButtonColor:'#3b5bdb'}); return; }
        if(!leaveType){Swal.fire({icon:'warning',title:'Select Leave Type', text:'Please choose a leave type.',      confirmButtonColor:'#3b5bdb'}); return; }
        if(keys.length===0){ Swal.fire({icon:'warning',title:'No Dates',   text:'Please select at least one date.', confirmButtonColor:'#3b5bdb'}); return; }
        if(!reason){   Swal.fire({icon:'warning',title:'Reason Required',  text:'Please provide a reason.',         confirmButtonColor:'#3b5bdb'}); return; }

        // Show loading
        Swal.fire({
            title: 'Validating leave request...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post('hr_leave_monitoring.php', {
            ajax: 1,
            action: 'validate_leave',
            emp_id: empId,
            leave_type_id: leaveType,
            selected_dates: keys.join(',')
        }, function(resp){
            if(!resp.valid){
                Swal.fire({icon:'error',title:'Cannot Submit',text:resp.message,confirmButtonColor:'#c92a2a'});
                return;
            }

            var sorted = keys.slice().sort();
            var incl   = sorted.map(function(k){
                var dt=new Date(k+'T00:00:00');
                return dt.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
            }).join(', ');

            var empName = $('#alEmpId option:selected').text();
            var ltName  = $('#alLeaveType option:selected').text();
            var dHtml   = sorted.map(function(k){
                var dt=new Date(k+'T00:00:00');
                var l=dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
                return '<span style="display:inline-block;background:#dbe4ff;color:#2f4ac0;border-radius:12px;padding:2px 10px;margin:2px;font-size:.76rem;font-weight:700">'+l+'</span>';
            }).join('');

            Swal.fire({
                title:'Confirm Leave Application',
                html:'<div style="text-align:left;font-size:.87rem;line-height:1.8">'+
                     '<p><strong>Employee:</strong> '+empName+'</p>'+
                     '<p><strong>Leave Type:</strong> '+ltName+'</p>'+
                     '<p><strong>Dates ('+sorted.length+' day/s):</strong><br>'+dHtml+'</p>'+
                     '<p><strong>Available Balance:</strong> '+resp.available.toFixed(3)+' day(s)</p>'+
                     '<p style="margin-top:8px"><strong>Reason:</strong> '+reason+'</p></div>',
                icon:'question',showCancelButton:true,
                confirmButtonColor:'#3b5bdb',cancelButtonColor:'#64748b',
                confirmButtonText:'<i class="fas fa-paper-plane"></i> Submit',
                cancelButtonText:'Review Again'
            }).then(function(r){
                if(!r.isConfirmed) return;
                $('#btnSubmitApplyLeave').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Submitting…');

                $.post('hr_leave_monitoring.php',{
                    ajax:1, action:'apply_leave_for_emp',
                    target_emp_id: empId,
                    leave_type_id: leaveType,
                    selected_dates: sorted.join(','),
                    reason: reason,
                    inclusive_dates: incl
                },function(res){
                    $('#btnSubmitApplyLeave').prop('disabled',false).html('<i class="fas fa-paper-plane"></i> Submit Leave Request');
                    if(res.success){
                        $('#applyLeaveModal').modal('hide');
                        Swal.fire({icon:'success',title:'Leave Applied!',text:'Leave request has been filed successfully.',confirmButtonColor:'#3b5bdb'}).then(()=>location.reload());
                    } else {
                        Swal.fire({icon:'error',title:'Error',text:res.message||'Could not submit.',confirmButtonColor:'#c92a2a'});
                    }
                },'json');
            });
        }, 'json').fail(function(){
            Swal.fire({icon:'error',title:'Error',text:'Validation failed. Please try again.',confirmButtonColor:'#c92a2a'});
        });
    });

    alCal.init();
    <?php endif; ?>

});
</script>
</body>
</html>