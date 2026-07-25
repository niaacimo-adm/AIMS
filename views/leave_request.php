<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$emp_id = $_SESSION['emp_id'] ?? null;

$employee = null;
if ($emp_id) {
    $q = "SELECT e.*, ap.status_name AS appointment_status_name, ap.appointment_id,
                 COALESCE(s.section_name, s2.section_name) AS section_name,
                 pos.position_name
          FROM employee e
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
          LEFT JOIN section s2 ON us.section_id = s2.section_id
          LEFT JOIN position pos ON e.position_id = pos.position_id
          WHERE e.emp_id = ? AND (ap.status_name IS NULL OR ap.status_name != 'Job Order')";
    $stmt = $db->prepare($q);
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
    }
}

$success_msg = '';
$error_msg   = '';

// Pick up flash message set by PRG redirect
if (!empty($_SESSION['lr_flash_success'])) {
    $success_msg = $_SESSION['lr_flash_success'];
    unset($_SESSION['lr_flash_success']);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX Handler (for validation and other actions)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    if ($action === 'validate_leave') {
        // Validate leave dates and balance before submission
        $emp_id_target  = intval($_POST['emp_id'] ?? 0);
        $leave_type_id  = intval($_POST['leave_type_id'] ?? 0);
        $selected_dates = trim($_POST['selected_dates'] ?? '');
        $date_arr       = array_filter(array_map('trim', explode(',', $selected_dates)));

        if (!$emp_id_target || !$leave_type_id || empty($date_arr)) {
            echo json_encode(['valid' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        sort($date_arr);
        $from = $date_arr[0];
        $to   = end($date_arr);

        // 1. Conflict check – overlapping pending or approved requests
        $conflict_sql = "SELECT COUNT(*) AS cnt FROM leave_request 
                         WHERE emp_id = ? 
                           AND status IN ('Pending','Approved') 
                           AND date_from <= ? AND date_to >= ?";
        $stmt = $db->prepare($conflict_sql);
        $stmt->bind_param("iss", $emp_id_target, $to, $from);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($conflict > 0) {
            echo json_encode(['valid' => false, 'message' => 'One or more selected dates overlap with an existing pending/approved leave request.']);
            exit;
        }

        // 2. Count working days (Mon-Fri)
        $days = 0;
        foreach ($date_arr as $d) {
            $dow = (new DateTime($d))->format('N');
            if ($dow < 6) $days++;
        }

        // 3. Check available balance
        $current_year = (int) date('Y');
        $bal_sql = "SELECT COALESCE(total_credits,0) AS total_credits, 
                           COALESCE(used_days,0) AS used_days 
                    FROM leave_balance 
                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $stmt = $db->prepare($bal_sql);
        $stmt->bind_param("iii", $emp_id_target, $leave_type_id, $current_year);
        $stmt->execute();
        $bal = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bal) {
            $available = 0;
        } else {
            $available = (float)$bal['total_credits'] - (float)$bal['used_days'];
        }

        if ($days > $available) {
            echo json_encode([
                'valid' => false,
                'message' => "Insufficient leave balance. You have only " . number_format($available, 3) . " day(s) available for this leave type."
            ]);
            exit;
        }

        echo json_encode(['valid' => true, 'days' => $days, 'available' => $available]);
        exit;
    }

    // Other existing AJAX actions (if any) can remain here...
    if ($action === 'cancel') {
        $leave_request_id = intval($_POST['leave_request_id'] ?? 0);

        if (!$leave_request_id || !$emp_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        // Verify ownership and that request is still Pending
        $chk = $db->prepare("SELECT leave_request_id FROM leave_request WHERE leave_request_id = ? AND emp_id = ? AND status = 'Pending'");
        $chk->bind_param("ii", $leave_request_id, $emp_id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows === 0) {
            $chk->close();
            echo json_encode(['success' => false, 'message' => 'Request not found or cannot be cancelled.']);
            exit;
        }
        $chk->close();

        $upd = $db->prepare("UPDATE leave_request SET status = 'Cancelled' WHERE leave_request_id = ? AND emp_id = ?");
        $upd->bind_param("ii", $leave_request_id, $emp_id);
        if ($upd->execute()) {
            $upd->close();
            echo json_encode(['success' => true]);
        } else {
            $upd->close();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
        }
        exit;
    }

    if ($action === 'delete') {
        $leave_request_id = intval($_POST['leave_request_id'] ?? 0);

        if (!$leave_request_id || !$emp_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        // Only allow deleting own Cancelled or Rejected records
        $chk = $db->prepare("SELECT leave_request_id FROM leave_request 
                              WHERE leave_request_id = ? AND emp_id = ? 
                              AND status IN ('Cancelled','Rejected','Disapproved')");
        $chk->bind_param("ii", $leave_request_id, $emp_id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows === 0) {
            $chk->close();
            echo json_encode(['success' => false, 'message' => 'Record not found or cannot be deleted. Only cancelled or rejected requests may be deleted.']);
            exit;
        }
        $chk->close();

        $del = $db->prepare("DELETE FROM leave_request WHERE leave_request_id = ? AND emp_id = ?");
        $del->bind_param("ii", $leave_request_id, $emp_id);
        if ($del->execute()) {
            $del->close();
            echo json_encode(['success' => true]);
        } else {
            $del->close();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
        }
        exit;
    }

    if ($action === 'get_details') {
        $leave_request_id = intval($_POST['leave_request_id'] ?? 0);

        if (!$leave_request_id || !$emp_id) {
            echo json_encode(null); exit;
        }

        $s = $db->prepare("
            SELECT lr.*,
                   lt.leave_type_name,
                   CONCAT(hr.first_name,' ',hr.last_name) AS processed_by_name
            FROM leave_request lr
            LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
            LEFT JOIN employee   hr ON lr.approved_by   = hr.emp_id
            WHERE lr.leave_request_id = ? AND lr.emp_id = ?
        ");
        $s->bind_param("ii", $leave_request_id, $emp_id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        echo json_encode($row);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────
// Form Submission (POST without AJAX)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employee) {
    $leave_type_id_raw  = $_POST['leave_type_id'] ?? '';
    $others_leave_label = trim($_POST['others_leave_label'] ?? '');
    $selected_dates     = $_POST['selected_dates'] ?? '';
    $reason             = trim($_POST['reason'] ?? '');
    $inclusive_dates    = $_POST['inclusive_dates'] ?? '';

    // Resolve "Others" — try to match existing leave type by name, or default to a generic Others entry
    $leave_type_id = 0;
    if ($leave_type_id_raw === 'others') {
        if (!$others_leave_label) {
            $error_msg = 'Please specify the type of leave under "Others".';
        } else {
            // Try to find a matching leave_type by name (case-insensitive)
            $match_stmt = $db->prepare("SELECT leave_type_id FROM leave_type WHERE LOWER(leave_type_name) LIKE ? LIMIT 1");
            $like_val   = '%' . strtolower($others_leave_label) . '%';
            $match_stmt->bind_param('s', $like_val);
            $match_stmt->execute();
            $match_row = $match_stmt->get_result()->fetch_assoc();
            $match_stmt->close();
            if ($match_row) {
                $leave_type_id = intval($match_row['leave_type_id']);
            } else {
                // Store the label in reason as context; use leave_type_id=0 as sentinel only
                // Actually fall back: look for an "Others" leave type or use the first unmatched
                $fb_stmt = $db->prepare("SELECT leave_type_id FROM leave_type WHERE LOWER(leave_type_name) LIKE '%other%' LIMIT 1");
                $fb_stmt->execute();
                $fb_row  = $fb_stmt->get_result()->fetch_assoc();
                $fb_stmt->close();
                if ($fb_row) {
                    $leave_type_id = intval($fb_row['leave_type_id']);
                } else {
                    // Cannot resolve; we cannot insert with foreign key = 0.
                    // Insert a temporary leave_type row for this label.
                    $ins_lt = $db->prepare("INSERT INTO leave_type (leave_type_name, description, is_active, is_main) VALUES (?, 'User-specified leave type', 1, 0)");
                    $ins_lt->bind_param('s', $others_leave_label);
                    $ins_lt->execute();
                    $leave_type_id = intval($ins_lt->insert_id);
                    $ins_lt->close();
                }
            }
        }
    } else {
        $leave_type_id = intval($leave_type_id_raw);
    }

    $date_arr = array_filter(array_map('trim', explode(',', $selected_dates)));

    if (!$error_msg && (!$leave_type_id || empty($date_arr) || !$reason)) {
        $error_msg = 'Please fill in all required fields.';
    } else {
        sort($date_arr);
        $date_from = $date_arr[0];
        $date_to   = $date_arr[count($date_arr) - 1];

        // Count working days
        $days = 0;
        foreach ($date_arr as $d) {
            $dow = (new DateTime($d))->format('N');
            if ($dow < 6) $days++;
        }

        // Server‑side validation – conflict check
        $conflict_sql = "SELECT COUNT(*) AS cnt FROM leave_request 
                         WHERE emp_id = ? 
                           AND status IN ('Pending','Approved') 
                           AND date_from <= ? AND date_to >= ?";
        $stmt = $db->prepare($conflict_sql);
        $stmt->bind_param("iss", $emp_id, $date_to, $date_from);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($conflict > 0) {
            $error_msg = 'One or more selected dates overlap with an existing pending/approved leave request.';
        } else {
            // Server‑side validation – balance check
            $current_year = (int) date('Y');
            $bal_sql = "SELECT COALESCE(total_credits,0) - COALESCE(used_days,0) AS available 
                        FROM leave_balance 
                        WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            $stmt = $db->prepare($bal_sql);
            $stmt->bind_param("iii", $emp_id, $leave_type_id, $current_year);
            $stmt->execute();
            $available = $stmt->get_result()->fetch_assoc()['available'] ?? 0;
            $stmt->close();

            if ($days > $available) {
                $error_msg = "Insufficient leave balance. You have only " . number_format($available, 3) . " day(s) available for this leave type.";
            } else {
                // All checks passed – insert the request
                $insert = "INSERT INTO leave_request
                           (emp_id, leave_type_id, date_from, date_to, number_of_days, reason, inclusive_dates, status, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
                $stmt2 = $db->prepare($insert);
                $stmt2->bind_param("iissdss", $emp_id, $leave_type_id, $date_from, $date_to, $days, $reason, $inclusive_dates);
                if ($stmt2->execute()) {
                    $stmt2->close();
                    // PRG: redirect to prevent re-submission on browser refresh
                    $_SESSION['lr_flash_success'] = 'Leave request submitted successfully!';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $error_msg = 'Database error: ' . $db->error;
                }
                $stmt2->close();
            }
        }
    }
}

// Fetch leave types for dropdown — main types first, then others
$leave_types       = [];
$leave_types_main  = [];
$leave_types_other = [];
$lt_result = $db->query("SELECT * FROM leave_type WHERE is_active = 1 ORDER BY is_main DESC, leave_type_name");
if ($lt_result) {
    while ($row = $lt_result->fetch_assoc()) {
        $leave_types[] = $row;
        if (($row['is_main'] ?? 1) == 1) $leave_types_main[]  = $row;
        else                              $leave_types_other[] = $row;
    }
}

// Fetch user's own requests for history
$my_requests = [];
if ($emp_id) {
    $mq = "SELECT lr.*, lt.leave_type_name
            FROM leave_request lr
            LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.emp_id = ?
            ORDER BY lr.created_at DESC
            LIMIT 50";
    $stmt3 = $db->prepare($mq);
    $stmt3->bind_param("i", $emp_id);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    while ($row = $r3->fetch_assoc()) $my_requests[] = $row;
}

$approved_leave_dates = [];
if ($emp_id) {
    $alq = $db->prepare("
        SELECT date_from, date_to
        FROM leave_request
        WHERE emp_id = ? AND status = 'Approved'
        ORDER BY date_from ASC
    ");
    $alq->bind_param("i", $emp_id);
    $alq->execute();
    $alr = $alq->get_result();
    while ($alrow = $alr->fetch_assoc()) {
        $cur = new DateTime($alrow['date_from']);
        $end = new DateTime($alrow['date_to']);
        while ($cur <= $end) {
            $dow = (int)$cur->format('N'); // 1=Mon…7=Sun
            if ($dow < 6) {               // weekdays only
                $approved_leave_dates[] = $cur->format('Y-m-d');
            }
            $cur->modify('+1 day');
        }
    }
    $alq->close();
    $approved_leave_dates = array_values(array_unique($approved_leave_dates));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ── CSS Variables ── */
        :root {
            --lr-primary:   #2a9863;
            --lr-primary-d: #1a5c38;
            --lr-danger:    #c92a2a;
            --lr-success:   #2a9863;
            --lr-warning:   #e67700;
            --lr-text:      #0f2d1e;
            --lr-muted:     #4a7a5e;
            --lr-border:    rgba(42,152,99,0.18);
            --lr-bg:        #eef7f2;
            --lr-card-bg:   #ffffff;
            --lr-radius:    14px;
        }

        /* ── Page layout ── */
        .lr-page { background: var(--lr-bg); min-height: 100vh; }

        /* ── Hero banner ── */
        .lr-hero {
            background: linear-gradient(135deg, #0f2d1e 0%, #1c4d38 55%, #2a9863 100%);
            padding: 36px 32px 32px;
            position: relative;
            overflow: hidden;
        }
        .lr-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .lr-hero-inner { position: relative; z-index: 1; }
        .lr-hero h1 {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: .01em;
        }
        .lr-hero p {
            color: rgba(255,255,255,.78);
            margin: 0 0 14px;
            font-size: .9rem;
        }
        .lr-emp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            color: #fff;
            border-radius: 30px;
            padding: 5px 16px;
            font-size: .82rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        /* ── Content wrapper ── */
        .lr-content { padding: 28px 28px 20px; }

        /* ── Cards ── */
        .lr-card {
            background: var(--lr-card-bg);
            border-radius: var(--lr-radius);
            box-shadow: 0 4px 24px rgba(60,72,100,.09), 0 1px 4px rgba(60,72,100,.06);
            overflow: hidden;
            height: 100%;
        }
        .lr-card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 22px;
            border-bottom: 1.5px solid #f1f3f5;
        }
        .lr-card-head h5 {
            margin: 0;
            font-size: .97rem;
            font-weight: 700;
            color: var(--lr-text);
        }
        .lr-card-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(42,152,99,.14);
            color: var(--lr-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            flex-shrink: 0;
        }
        .lr-card-body { padding: 22px; }
        .lr-card-body.p-0 { padding: 0; }

        /* ── Form elements ── */
        .lr-label {
            display: block;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--lr-text);
            margin-bottom: 6px;
        }
        .lr-select, .lr-textarea {
            width: 100%;
            border: 1.5px solid var(--lr-border);
            border-radius: 9px;
            padding: 9px 12px;
            font-size: .88rem;
            color: var(--lr-text);
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }
        .lr-select:focus, .lr-textarea:focus {
            border-color: var(--lr-primary);
            box-shadow: 0 0 0 3px rgba(42,152,99,.15);
        }
        .lr-textarea { resize: vertical; min-height: 90px; }
        .lr-btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: linear-gradient(135deg, var(--lr-primary), var(--lr-primary-d));
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .15s, box-shadow .15s;
            box-shadow: 0 4px 14px rgba(42,152,99,.3);
            text-decoration: none;
        }
        .lr-btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(42,152,99,.4);
            color: #fff;
        }
        .lr-btn-submit:disabled {
            opacity: .65;
            transform: none;
            cursor: not-allowed;
        }

        /* ── Multi-date calendar ── */
        .mdc-wrap {
            border: 1.5px solid var(--lr-border);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .mdc-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--lr-border);
        }
        .mdc-nav-btn {
            background: none;
            border: 1.5px solid var(--lr-border);
            border-radius: 6px;
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: var(--lr-text);
            transition: background .12s;
        }
        .mdc-nav-btn:hover { background: #e9ecef; }
        .mdc-month-lbl { font-weight: 700; font-size: .9rem; color: var(--lr-text); }
        .mdc-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            padding: 8px;
        }
        .mdc-dn {
            text-align: center;
            font-size: .68rem;
            font-weight: 700;
            color: var(--lr-muted);
            padding: 4px 0;
            text-transform: uppercase;
        }
        .mdc-day {
            text-align: center;
            padding: 7px 2px;
            border-radius: 6px;
            font-size: .82rem;
            cursor: pointer;
            transition: background .12s, color .12s;
        }
        .mdc-day:hover:not(.mdc-dis):not(.mdc-wknd):not(.mdc-emp) {
            background: rgba(42,152,99,.14);
            color: var(--lr-primary);
        }
        .mdc-emp  { cursor: default; }
        .mdc-dis  { color: #ced4da; cursor: not-allowed; }
        .mdc-wknd { color: #ced4da; cursor: not-allowed; }
        .mdc-today { background: #e6f7ef; color: var(--lr-primary-d); font-weight: 700; }
        .mdc-sel   { background: var(--lr-primary); color: #fff; font-weight: 700; }
        /* approved leave dates – shown but not selectable */
        .mdc-leave {
            background: #fff0f0;
            color: #c92a2a;
            cursor: not-allowed;
            font-weight: 600;
            position: relative;
        }
        .mdc-leave::after {
            content: '';
            position: absolute;
            bottom: 3px; left: 50%;
            transform: translateX(-50%);
            width: 4px; height: 4px;
            border-radius: 50%;
            background: #c92a2a;
        }
        .mdc-tags-hdr {
            padding: 8px 12px 2px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--lr-muted);
        }
        .mdc-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 6px 10px 10px;
            min-height: 36px;
        }
        .mdc-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(42,152,99,.14);
            color: var(--lr-primary);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .75rem;
            font-weight: 700;
        }
        .mdc-tag-rm {
            background: none;
            border: none;
            color: #748ffc;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            font-size: .72rem;
        }
        .mdc-tag-rm:hover { color: var(--lr-danger); }
        .mdc-hint { font-size: .8rem; color: var(--lr-muted); padding: 4px 2px; }
        .mdc-footer {
            padding: 8px 12px;
            font-size: .8rem;
            color: var(--lr-muted);
            border-top: 1px solid #f1f3f5;
        }
        .mdc-pill {
            background: var(--lr-primary);
            color: #fff;
            border-radius: 20px;
            padding: 1px 9px;
            font-size: .75rem;
            font-weight: 700;
            margin-right: 4px;
        }

        /* ── History table ── */
        .lr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .lr-table thead th {
            background: #f8f9fa;
            color: var(--lr-muted);
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 12px 14px;
            border-bottom: 1.5px solid var(--lr-border);
            white-space: nowrap;
        }
        .lr-table tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f1f3f5;
            color: var(--lr-text);
            vertical-align: middle;
        }
        .lr-table tbody tr:last-child td { border-bottom: none; }
        .lr-table tbody tr:hover td { background: #f8f9fa; }

        /* ── Status badges ── */
        .lr-badge {
            display: inline-block;
            border-radius: 20px;
            padding: 3px 11px;
            font-size: .73rem;
            font-weight: 700;
        }
        .lr-pend { background: #fff3bf; color: #7c5a00; }
        .lr-appr { background: #d3f9d8; color: #1a6b2e; }
        .lr-rejt { background: #ffe3e3; color: #a61e1e; }
        .lr-canc { background: #f1f3f5; color: #868e96; }
        .lr-days-pill {
            background: rgba(42,152,99,.12);
            color: var(--lr-primary-d);
            border-radius: 20px;
            padding: 2px 9px;
            font-size: .75rem;
            font-weight: 700;
        }

        /* ── Action buttons in table ── */
        .lr-btn-cancel {
            display: inline-flex; align-items: center; gap: 4px;
            background: #fff5f5; color: var(--lr-danger);
            border: 1.5px solid #ffc9c9; border-radius: 6px;
            padding: 4px 10px; font-size: .75rem; font-weight: 600;
            cursor: pointer; transition: background .12s;
        }
        .lr-btn-cancel:hover { background: #ffe3e3; }
        .lr-btn-view {
            display: inline-flex; align-items: center; justify-content: center;
            background: #e6f7ef; color: var(--lr-primary-d);
            border: 1.5px solid rgba(42,152,99,.35); border-radius: 6px;
            width: 30px; height: 28px; font-size: .8rem;
            cursor: pointer; transition: background .12s, color .12s;
            flex-shrink: 0;
        }
        .lr-btn-view:hover { background: var(--lr-primary); color: #fff; border-color: var(--lr-primary); }
        .lr-btn-delete {
            display: inline-flex; align-items: center; gap: 4px;
            background: #fff5f5; color: #862e2e;
            border: 1.5px solid #ffa8a8; border-radius: 6px;
            padding: 4px 10px; font-size: .75rem; font-weight: 600;
            cursor: pointer; transition: background .12s, border-color .12s;
        }
        .lr-btn-delete:hover { background: #ffe3e3; border-color: #ff8787; }
        .lr-btn-dl {
            display: inline-flex; align-items: center; gap: 4px;
            background: #f3f0ff; color: #6741d9;
            border: 1.5px solid #d0bfff; border-radius: 6px;
            padding: 4px 10px; font-size: .75rem; font-weight: 600;
            text-decoration: none; transition: background .12s;
        }
        .lr-btn-dl:hover { background: #e5dbff; color: #5f3dc4; text-decoration: none; }

        /* ── Empty state ── */
        .lr-empty {
            padding: 48px 20px;
            text-align: center;
            color: var(--lr-muted);
        }
        .lr-empty i { font-size: 2.2rem; margin-bottom: 12px; display: block; opacity: .4; }
        .lr-empty p { margin: 0; font-size: .88rem; }

        /* ── Detail Modal ── */
        #lrDetailModal .modal-dialog { max-width: 560px; }
        #lrDetailModal .modal-content { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.18); }
        .lrm-header {
            display: flex; align-items: center; gap: 12px;
            padding: 20px 24px 16px;
            border-bottom: 1.5px solid #f1f3f5;
        }
        .lrm-header-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(42,152,99,.14); color: var(--lr-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .lrm-header h5 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--lr-text); }
        .lrm-header p  { margin: 0; font-size: .78rem; color: var(--lr-muted); }
        .lrm-body { padding: 20px 24px; }
        .lrm-status-bar {
            display: flex; align-items: center; gap: 10px;
            border-radius: 10px; padding: 12px 16px; margin-bottom: 20px;
        }
        .lrm-status-bar.bar-pend { background: #fff9db; }
        .lrm-status-bar.bar-appr { background: #d3f9d8; }
        .lrm-status-bar.bar-rejt { background: #ffe3e3; }
        .lrm-status-bar.bar-canc { background: #f1f3f5; }
        .lrm-status-bar .lrm-status-label {
            font-size: .7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: var(--lr-muted); margin-right: 4px;
        }
        .lrm-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 14px 20px; margin-bottom: 16px;
        }
        .lrm-field label {
            display: block; font-size: .68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--lr-muted); margin-bottom: 3px;
        }
        .lrm-field span { font-size: .88rem; color: var(--lr-text); font-weight: 500; }
        .lrm-field.full { grid-column: 1 / -1; }
        .lrm-infobox {
            background: #f8f9fa; border-radius: 8px;
            padding: 10px 14px; font-size: .87rem;
            color: var(--lr-text); line-height: 1.6;
            border-left: 3px solid var(--lr-border);
        }
        .lrm-infobox.lrm-remark { border-left-color: var(--lr-warning); background: #fff9f0; }
        .lrm-dates-wrap { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
        .lrm-date-chip {
            background: rgba(42,152,99,.14); color: var(--lr-primary);
            border-radius: 20px; padding: 2px 10px;
            font-size: .74rem; font-weight: 700;
        }
        .lrm-footer {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 8px; padding: 14px 24px;
            border-top: 1.5px solid #f1f3f5;
        }
        .lrm-btn-close {
            background: #f1f3f5; color: #495057;
            border: 1.5px solid #dee2e6; border-radius: 8px;
            padding: 8px 20px; font-size: .87rem; font-weight: 600;
            cursor: pointer; transition: background .12s;
        }
        .lrm-btn-close:hover { background: #e9ecef; }
        .lrm-btn-form {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg,var(--lr-primary),var(--lr-primary-d));
            color: #fff; border: none; border-radius: 8px;
            padding: 8px 20px; font-size: .87rem; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: opacity .15s;
        }
        .lrm-btn-form:hover { opacity: .88; color: #fff; text-decoration: none; }
        .lrm-spinner { text-align: center; padding: 48px 0; color: var(--lr-primary); font-size: 1.8rem; }

        /* ══════════════════ DARK MODE ══════════════════ */
        body.dark-mode {
            --lr-primary:   #24e78f;
            --lr-primary-d: #2a9863;
            --lr-danger:    #ff6b6b;
            --lr-success:   #24e78f;
            --lr-warning:   #ffd43b;
            --lr-text:      #d4f5e5;
            --lr-muted:     #6aad8a;
            --lr-border:    rgba(36,231,143,0.12);
            --lr-bg:        #0b1f17;
            --lr-card-bg:   #102f22;
        }
        body.dark-mode .lr-card-head       { border-bottom-color: var(--lr-border); }
        body.dark-mode .lr-card-icon       { background: rgba(36,231,143,.14); }
        body.dark-mode .lr-select,
        body.dark-mode .lr-textarea        { background: #0e2619; border-color: var(--lr-border); color: var(--lr-text); }
        body.dark-mode .lr-select:focus,
        body.dark-mode .lr-textarea:focus  { box-shadow: 0 0 0 3px rgba(36,231,143,.18); }
        body.dark-mode .mdc-wrap           { background: #0e2619; border-color: var(--lr-border); }
        body.dark-mode .mdc-nav            { background: #091d14; border-bottom-color: var(--lr-border); }
        body.dark-mode .mdc-nav-btn        { border-color: var(--lr-border); color: var(--lr-text); }
        body.dark-mode .mdc-nav-btn:hover  { background: rgba(36,231,143,.12); }
        body.dark-mode .mdc-day:hover:not(.mdc-dis):not(.mdc-wknd):not(.mdc-emp) { background: rgba(36,231,143,.16); color: var(--lr-primary); }
        body.dark-mode .mdc-dis,
        body.dark-mode .mdc-wknd           { color: #2f4a3d; }
        body.dark-mode .mdc-today          { background: #163523; color: var(--lr-primary); }
        body.dark-mode .mdc-leave          { background: rgba(255,107,107,.14); color: #ff8787; }
        body.dark-mode .mdc-leave::after   { background: #ff8787; }
        body.dark-mode .mdc-tag            { background: rgba(36,231,143,.16); color: var(--lr-primary); }
        body.dark-mode .mdc-tag-rm         { color: #6aad8a; }
        body.dark-mode .mdc-footer         { border-top-color: var(--lr-border); }

        body.dark-mode .lr-table thead th  { background: #0e2619; border-bottom-color: var(--lr-border); }
        body.dark-mode .lr-table tbody td  { border-bottom-color: var(--lr-border); }
        body.dark-mode .lr-table tbody tr:hover td { background: rgba(36,231,143,.06); }

        body.dark-mode .lr-pend { background: #3d2e00; color: #ffd43b; }
        body.dark-mode .lr-appr { background: #0d3d2c; color: #63e6be; }
        body.dark-mode .lr-rejt { background: #3d0f0f; color: #ff8787; }
        body.dark-mode .lr-canc { background: #1e2030; color: #8892a4; }
        body.dark-mode .lr-days-pill { background: rgba(36,231,143,.14); color: var(--lr-primary); }

        body.dark-mode .lr-btn-cancel { background: rgba(255,107,107,.1); border-color: rgba(255,107,107,.35); }
        body.dark-mode .lr-btn-cancel:hover { background: rgba(255,107,107,.2); }
        body.dark-mode .lr-btn-view   { background: rgba(36,231,143,.12); border-color: rgba(36,231,143,.35); color: var(--lr-primary); }
        body.dark-mode .lr-btn-view:hover { background: var(--lr-primary); color: #091d14; border-color: var(--lr-primary); }
        body.dark-mode .lr-btn-delete { background: rgba(255,107,107,.1); border-color: rgba(255,107,107,.35); color: #ff9b9b; }
        body.dark-mode .lr-btn-delete:hover { background: rgba(255,107,107,.2); border-color: #ff8787; }
        body.dark-mode .lr-btn-dl     { background: rgba(36,231,143,.14); border-color: rgba(36,231,143,.4); color: var(--lr-primary); }
        body.dark-mode .lr-btn-dl:hover { background: var(--lr-primary); border-color: var(--lr-primary); color: #091d14; }

        body.dark-mode #lrDetailModal .modal-content { background: var(--lr-card-bg); box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        body.dark-mode .lrm-header        { border-bottom-color: var(--lr-border); }
        body.dark-mode .lrm-header-icon   { background: rgba(36,231,143,.14); }
        body.dark-mode .lrm-status-bar.bar-pend { background: #3d2e00; }
        body.dark-mode .lrm-status-bar.bar-appr { background: #0d3d2c; }
        body.dark-mode .lrm-status-bar.bar-rejt { background: #3d0f0f; }
        body.dark-mode .lrm-status-bar.bar-canc { background: #1e2030; }
        body.dark-mode .lrm-infobox        { background: #0e2619; border-left-color: var(--lr-border); }
        body.dark-mode .lrm-infobox.lrm-remark { background: #2e2000; border-left-color: var(--lr-warning); }
        body.dark-mode .lrm-date-chip      { background: rgba(36,231,143,.14); color: var(--lr-primary); }
        body.dark-mode .lrm-footer         { border-top-color: var(--lr-border); }
        body.dark-mode .lrm-btn-close      { background: #0e2619; color: var(--lr-text); border-color: var(--lr-border); }
        body.dark-mode .lrm-btn-close:hover{ background: #163523; }
        body.dark-mode .lrm-spinner        { color: var(--lr-primary); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper lr-page">

    <?php if ($employee): ?>

        <div class="lr-hero">
            <div class="lr-hero-inner">
                <h1><i class="fas fa-calendar-alt mr-2" style="opacity:.85"></i>Leave Request</h1>
                <p>Submit and track your leave applications</p>
                <span class="lr-emp-badge">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                    <?php if (!empty($employee['appointment_status_name'])): ?>
                    &nbsp;&bull;&nbsp;<i class="fas fa-id-badge"></i>
                    <?= htmlspecialchars($employee['appointment_status_name']) ?>
                    <?php endif; ?>
                    <?php if (!empty($employee['section_name'])): ?>
                    &nbsp;&bull;&nbsp;<i class="fas fa-building"></i>
                    <?= htmlspecialchars($employee['section_name']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="lr-content">
            <div class="row">

                <!-- Form -->
                <div class="col-lg-5 mb-4">
                    <div class="lr-card">
                        <div class="lr-card-head">
                            <div class="lr-card-icon"><i class="fas fa-plus"></i></div>
                            <h5>New Leave Application</h5>
                        </div>
                        <div class="lr-card-body">
                            <form id="leaveForm" method="POST" action="">
                                <input type="hidden" id="current_emp_id" value="<?= $emp_id ?>">
                                <input type="hidden" name="selected_dates"  id="hidSelectedDates">
                                <input type="hidden" name="date_from"       id="hidDateFrom">
                                <input type="hidden" name="date_to"         id="hidDateTo">
                                <input type="hidden" name="inclusive_dates" id="hidInclusiveDates">

                                <div class="form-group">
                                    <label class="lr-label">Leave Type <span style="color:var(--lr-danger)">*</span></label>
                                    <select name="leave_type_id" id="leave_type_id" class="lr-select" required>
                                        <option value="">— Select Leave Type —</option>
                                        <?php if (!empty($leave_types_main)): ?>
                                        <optgroup label="── Main Leave Types ──">
                                            <?php foreach ($leave_types_main as $lt): ?>
                                            <option value="<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                        <?php if (!empty($leave_types_other)): ?>
                                        <optgroup label="── Other Leave Types ──">
                                            <?php foreach ($leave_types_other as $lt): ?>
                                            <option value="<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                        <option value="others">Others (please specify)</option>
                                    </select>
                                </div>

                                <div class="form-group" id="others_leave_wrap" style="display:none;">
                                    <label class="lr-label">Specify Leave Type <span style="color:var(--lr-danger)">*</span></label>
                                    <select name="others_leave_label" id="others_leave_label" class="lr-select">
                                        <option value="">— Choose a leave type —</option>
                                        <?php if (!empty($leave_types_other)): ?>
                                        <optgroup label="── Other Leave Types ──">
                                            <?php foreach ($leave_types_other as $lt): ?>
                                            <option value="<?= htmlspecialchars($lt['leave_type_name']) ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                        <?php if (!empty($leave_types_main)): ?>
                                        <optgroup label="── Main Leave Types ──">
                                            <?php foreach ($leave_types_main as $lt): ?>
                                            <option value="<?= htmlspecialchars($lt['leave_type_name']) ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <input type="hidden" name="leave_type_id_override" id="leave_type_id_override" value="">
                                </div>

                                    <div class="form-group">
                                    <label class="lr-label">
                                        Select Leave Date(s) <span style="color:var(--lr-danger)">*</span>
                                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--lr-muted);font-size:.7rem;margin-left:4px;">(click day to pick; click again to remove)</span>
                                    </label>
                                    <!-- legend -->
                                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px;font-size:.72rem;color:var(--lr-muted);">
                                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--lr-primary);margin-right:3px;vertical-align:middle;"></span>Selected</span>
                                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#c92a2a;margin-right:3px;vertical-align:middle;"></span>Approved Leave</span>
                                    </div>
                                    <div class="mdc-wrap">
                                        <div class="mdc-nav">
                                            <button type="button" class="mdc-nav-btn" id="mdcPrev"><i class="fas fa-chevron-left"></i></button>
                                            <span class="mdc-month-lbl" id="mdcLabel"></span>
                                            <button type="button" class="mdc-nav-btn" id="mdcNext"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                        <div class="mdc-grid" id="mdcGrid"></div>
                                        <div class="mdc-tags-hdr" id="mdcTagsHdr" style="display:none">Selected Dates</div>
                                        <div class="mdc-tags" id="mdcTags"></div>
                                        <div class="mdc-footer" id="mdcFooter" style="display:none">
                                            <span class="mdc-pill" id="mdcCount">0</span>
                                            working day(s) selected
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="lr-label">Reason / Details <span style="color:var(--lr-danger)">*</span></label>
                                    <textarea name="reason" id="reason" class="lr-textarea" placeholder="Briefly describe the purpose of your leave…" required></textarea>
                                </div>

                                <button type="submit" class="lr-btn-submit" id="submitBtn">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History -->
                <div class="col-lg-7 mb-4">
                    <div class="lr-card">
                        <div class="lr-card-head">
                            <div class="lr-card-icon"><i class="fas fa-history"></i></div>
                            <h5>My Leave History</h5>
                        </div>
                        <div class="lr-card-body p-0">
                            <?php if (empty($my_requests)): ?>
                            <div class="lr-empty">
                                <i class="fas fa-inbox"></i>
                                <p>No leave requests yet. Submit your first!</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="lr-table" id="leaveHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Type</th><th>Date From</th><th>Date To</th>
                                            <th>Days</th><th>Status</th><th>Filed</th><th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($my_requests as $req):
                                        $s = strtolower($req['status'] ?? 'pending');
                                        $bc = 'lr-pend';
                                        if ($s==='approved') $bc='lr-appr';
                                        elseif (in_array($s,['rejected','disapproved'])) $bc='lr-rejt';
                                        elseif ($s==='cancelled') $bc='lr-canc';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($req['leave_type_name'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($req['date_from'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($req['date_to'])) ?></td>
                                        <td><span class="lr-days-pill"><?= $req['number_of_days'] ?></span></td>
                                        <td><span class="lr-badge <?= $bc ?>"><?= ucfirst($req['status']) ?></span></td>
                                        <td style="color:var(--lr-muted);font-size:.8rem;"><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                                        <td>
                                            <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                                                <button class="lr-btn-view btn-view-request"
                                                        data-id="<?= $req['leave_request_id'] ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($s==='pending'): ?>
                                                <button class="lr-btn-cancel btn-cancel-request" data-id="<?= $req['leave_request_id'] ?>">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                                <?php elseif ($s==='approved'): ?>
                                                <a href="generate_leave_form.php?leave_request_id=<?= $req['leave_request_id'] ?>"
                                                   target="_blank"
                                                   class="lr-btn-dl"
                                                   title="View / Print Leave Form">
                                                    <i class="fas fa-file-download"></i> Form
                                                </a>
                                                <?php elseif (in_array($s, ['cancelled','rejected','disapproved'])): ?>
                                                <button class="lr-btn-delete btn-delete-request" data-id="<?= $req['leave_request_id'] ?>" title="Delete this record">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    <?php else: ?>
        <style>
            .jo-denied-wrap {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: calc(100vh - 160px);
                padding: 32px 16px;
            }
            .jo-denied-card {
                background: #fff;
                border-radius: 20px;
                box-shadow: 0 8px 40px rgba(60,72,100,.13), 0 1.5px 6px rgba(60,72,100,.07);
                max-width: 520px;
                width: 100%;
                overflow: hidden;
                position: relative;
                text-align: center;
            }
            .jo-denied-banner {
                background: linear-gradient(135deg, #c92a2a 0%, #e03131 60%, #fa5252 100%);
                padding: 44px 32px 36px;
                position: relative;
                overflow: hidden;
            }
            .jo-denied-banner::before {
                content: '';
                position: absolute;
                inset: 0;
                background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            }
            .jo-denied-icon-ring {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: rgba(255,255,255,.18);
                border: 3px solid rgba(255,255,255,.35);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 18px;
                position: relative;
                z-index: 1;
            }
            .jo-denied-icon-ring i {
                font-size: 2rem;
                color: #fff;
            }
            .jo-denied-banner h2 {
                color: #fff;
                font-size: 1.45rem;
                font-weight: 700;
                margin: 0 0 6px;
                letter-spacing: .01em;
                position: relative;
                z-index: 1;
            }
            .jo-denied-banner p {
                color: rgba(255,255,255,.82);
                font-size: .88rem;
                margin: 0;
                position: relative;
                z-index: 1;
            }
            .jo-denied-body {
                padding: 32px 36px 36px;
            }
            .jo-info-box {
                background: #fff5f5;
                border: 1.5px solid #ffc9c9;
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 24px;
                text-align: left;
                display: flex;
                gap: 14px;
                align-items: flex-start;
            }
            .jo-info-box-icon {
                width: 36px;
                height: 36px;
                border-radius: 8px;
                background: #ffe3e3;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 2px;
            }
            .jo-info-box-icon i { color: #c92a2a; font-size: .95rem; }
            .jo-info-box-text p {
                margin: 0;
                font-size: .855rem;
                color: #5c3030;
                line-height: 1.6;
            }
            .jo-info-box-text p strong { color: #c92a2a; }
            .jo-divider {
                border: none;
                border-top: 1.5px solid #f1f3f5;
                margin: 0 0 24px;
            }
            .jo-meta-row {
                display: flex;
                justify-content: center;
                gap: 28px;
                margin-bottom: 28px;
                flex-wrap: wrap;
            }
            .jo-meta-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
            }
            .jo-meta-item .jo-meta-label {
                font-size: .7rem;
                color: #adb5bd;
                text-transform: uppercase;
                letter-spacing: .08em;
                font-weight: 600;
            }
            .jo-meta-item .jo-meta-val {
                font-size: .88rem;
                font-weight: 700;
                color: #343a40;
            }
            .jo-meta-item .jo-meta-val.jo-status-badge {
                background: #fff3bf;
                color: #7c5a00;
                border-radius: 20px;
                padding: 2px 12px;
                font-size: .78rem;
                border: 1.5px solid #ffe066;
            }
            .jo-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .jo-btn-home {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: linear-gradient(135deg,#2a9863,#1a5c38);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 13px 24px;
                font-size: .9rem;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition: transform .15s, box-shadow .15s;
                box-shadow: 0 4px 14px rgba(42,152,99,.3);
            }
            .jo-btn-home:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(42,152,99,.4);
                color: #fff;
                text-decoration: none;
            }
            .jo-btn-contact {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: #f8f9fa;
                color: #495057;
                border: 1.5px solid #dee2e6;
                border-radius: 10px;
                padding: 11px 24px;
                font-size: .87rem;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition: background .15s, border-color .15s;
            }
            .jo-btn-contact:hover {
                background: #f1f3f5;
                border-color: #adb5bd;
                color: #343a40;
                text-decoration: none;
            }

            /* ── Dark mode ── */
            body.dark-mode .jo-denied-card     { background: #102f22; box-shadow: 0 8px 40px rgba(0,0,0,.5), 0 1.5px 6px rgba(0,0,0,.3); }
            body.dark-mode .jo-info-box        { background: rgba(255,107,107,.08); border-color: rgba(255,107,107,.35); }
            body.dark-mode .jo-info-box-icon   { background: rgba(255,107,107,.16); }
            body.dark-mode .jo-info-box-icon i { color: #ff8787; }
            body.dark-mode .jo-info-box-text p { color: #ffb3b3; }
            body.dark-mode .jo-info-box-text p strong { color: #ff8787; }
            body.dark-mode .jo-divider         { border-top-color: rgba(36,231,143,.16); }
            body.dark-mode .jo-meta-item .jo-meta-label { color: #6aad8a; }
            body.dark-mode .jo-meta-item .jo-meta-val   { color: #d4f5e5; }
            body.dark-mode .jo-meta-item .jo-meta-val.jo-status-badge { background: #3d2e00; color: #ffd43b; border-color: #6b5200; }
            body.dark-mode .jo-btn-contact     { background: #102f22; color: #d4f5e5; border-color: rgba(36,231,143,.2); }
            body.dark-mode .jo-btn-contact:hover { background: #163523; border-color: rgba(36,231,143,.4); color: #fff; }
        </style>

        <div class="jo-denied-wrap">
            <div class="jo-denied-card">

                <!-- Red banner -->
                <div class="jo-denied-banner">
                    <div class="jo-denied-icon-ring">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h2>Access Restricted</h2>
                    <p>Leave Request Module</p>
                </div>

                <!-- Body -->
                <div class="jo-denied-body">

                    <!-- Info box -->
                    <div class="jo-info-box">
                        <div class="jo-info-box-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="jo-info-box-text">
                            <p>Employees with <strong>Job Order</strong> appointment status are not eligible to file leave requests through this system. Please coordinate directly with your HR officer for any leave concerns.</p>
                        </div>
                    </div>

                    <!-- Meta row -->
                    <div class="jo-meta-row">
                        <div class="jo-meta-item">
                            <span class="jo-meta-label">Logged in as</span>
                            <span class="jo-meta-val"><?= htmlspecialchars($_SESSION['username'] ?? 'Employee') ?></span>
                        </div>
                        <div class="jo-meta-item">
                            <span class="jo-meta-label">Appointment Type</span>
                            <span class="jo-meta-val jo-status-badge"><i class="fas fa-briefcase" style="font-size:.7rem;margin-right:4px"></i>Job Order</span>
                        </div>
                    </div>

                    <hr class="jo-divider">

                    <!-- Buttons -->
                    <div class="jo-actions">
                        <a href="dashboard.php" class="jo-btn-home">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                        <a href="mailto:hr@nia-acimo.gov.ph" class="jo-btn-contact">
                            <i class="fas fa-envelope"></i> Contact HR Office
                        </a>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>

    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- ══════════════ Leave Detail Modal ══════════════ -->
<div class="modal fade" id="lrDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div id="lrModalInner">
                <div class="lrm-spinner"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
/* ═══════════════════════════ MULTI-DATE CALENDAR ═══════════════════════════ */
<?php if ($employee): ?>
(function(){
    var MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
    var WDAYS  = ['Su','Mo','Tu','We','Th','Fr','Sa'];

    var selectedDates = {};   // plain object used as Set: key -> true

    // ── Approved leave dates injected directly from PHP (no AJAX needed) ──────
    var approvedDates = (function(){
        var map = {};
        var list = <?= json_encode($approved_leave_dates) ?>;
        for (var i = 0; i < list.length; i++) map[list[i]] = true;
        return map;
    })();

    var viewYear, viewMonth;

    var today = new Date();
    today.setHours(0,0,0,0);
    viewYear  = today.getFullYear();
    viewMonth = today.getMonth();

    function pad(n){ return String(n).padStart(2,'0'); }
    function toKey(y,m,d){ return y+'-'+pad(m+1)+'-'+pad(d); }
    function isWeekend(y,m,d){ var w=new Date(y,m,d).getDay(); return w===0||w===6; }
    function isPast(y,m,d){ return new Date(y,m,d)<today; }
    function getKeys(){ return Object.keys(selectedDates).sort(); }

    function renderGrid(){
        var grid  = document.getElementById('mdcGrid');
        var label = document.getElementById('mdcLabel');
        label.textContent = MONTHS[viewMonth]+' '+viewYear;

        var html = '';
        for(var i=0;i<7;i++) html += '<div class="mdc-dn">'+WDAYS[i]+'</div>';

        var firstDow  = new Date(viewYear,viewMonth,1).getDay();
        var daysInMon = new Date(viewYear,viewMonth+1,0).getDate();

        for(var e=0;e<firstDow;e++) html += '<div class="mdc-day mdc-emp"></div>';

        for(var d=1;d<=daysInMon;d++){
            var key   = toKey(viewYear,viewMonth,d);
            var past  = isPast(viewYear,viewMonth,d);
            var wknd  = isWeekend(viewYear,viewMonth,d);
            var leave = !!approvedDates[key];       // ← approved leave date
            var sel   = !!selectedDates[key];
            var isNow = (viewYear===today.getFullYear()&&viewMonth===today.getMonth()&&d===today.getDate());
            var cls   = 'mdc-day';
            if(past)        cls+=' mdc-dis';
            else if(wknd)   cls+=' mdc-wknd';
            else if(leave)  cls+=' mdc-leave';      // ← highlight & block
            else if(sel)    cls+=' mdc-sel';
            else if(isNow)  cls+=' mdc-today';
            var data = (!past&&!wknd&&!leave)?'data-key="'+key+'"':'';
            html+='<div class="'+cls+'" '+data+' title="'+(leave?'Approved leave':'')+'">'+d+'</div>';
        }
        grid.innerHTML = html;

        var cells = grid.querySelectorAll('.mdc-day[data-key]');
        for(var c=0;c<cells.length;c++){
            (function(cell){ cell.addEventListener('click',function(){ toggleDate(cell.dataset.key); }); })(cells[c]);
        }
    }

    function toggleDate(key){
        if(selectedDates[key]) delete selectedDates[key];
        else selectedDates[key] = true;
        renderGrid(); renderTags(); syncInputs();
    }

    function removeDate(key){
        delete selectedDates[key];
        renderGrid(); renderTags(); syncInputs();
    }

    function renderTags(){
        var tagsEl   = document.getElementById('mdcTags');
        var hdrEl    = document.getElementById('mdcTagsHdr');
        var footerEl = document.getElementById('mdcFooter');
        var countEl  = document.getElementById('mdcCount');
        var keys     = getKeys();

        tagsEl.innerHTML = '';

        if(keys.length===0){
            hdrEl.style.display    = 'none';
            footerEl.style.display = 'none';
            var hint = document.createElement('span');
            hint.className   = 'mdc-hint';
            hint.textContent = 'No dates selected yet';
            tagsEl.appendChild(hint);
            return;
        }

        hdrEl.style.display    = '';
        footerEl.style.display = '';
        countEl.textContent    = keys.length;

        for(var i=0;i<keys.length;i++){
            var key = keys[i];
            var dt  = new Date(key+'T00:00:00');
            var lbl = dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});

            var tag = document.createElement('span');
            tag.className = 'mdc-tag';

            var txt = document.createTextNode(lbl+' ');
            tag.appendChild(txt);

            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'mdc-tag-rm';
            btn.title     = 'Remove';
            btn.setAttribute('data-rm', key);
            btn.innerHTML = '<i class="fas fa-times"></i>';
            tag.appendChild(btn);

            tagsEl.appendChild(tag);
        }

        var rmBtns = tagsEl.querySelectorAll('.mdc-tag-rm');
        for(var j=0;j<rmBtns.length;j++){
            (function(b){ b.addEventListener('click',function(){ removeDate(b.getAttribute('data-rm')); }); })(rmBtns[j]);
        }
    }

    function syncInputs(){
        var keys = getKeys();
        document.getElementById('hidSelectedDates').value  = keys.join(',');
        document.getElementById('hidDateFrom').value       = keys[0]||'';
        document.getElementById('hidDateTo').value         = keys[keys.length-1]||'';

        if(keys.length===0){
            document.getElementById('hidInclusiveDates').value = '';
        } else {
            var labels = [];
            for(var i=0;i<keys.length;i++){
                var dt = new Date(keys[i]+'T00:00:00');
                labels.push(dt.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}));
            }
            document.getElementById('hidInclusiveDates').value = labels.join(', ');
        }
    }

    document.getElementById('mdcPrev').addEventListener('click',function(){
        viewMonth--;
        if(viewMonth<0){viewMonth=11;viewYear--;}
        renderGrid();
    });
    document.getElementById('mdcNext').addEventListener('click',function(){
        viewMonth++;
        if(viewMonth>11){viewMonth=0;viewYear++;}
        renderGrid();
    });

    renderGrid();
    renderTags();
    syncInputs();
})();
<?php endif; ?>


/* ══════════════════════ JQUERY / SWAL ══════════════════════ */
$(document).ready(function(){

    <?php if($success_msg): ?>
    Swal.fire({icon:'success',title:'Request Submitted!',text:'<?= addslashes($success_msg) ?>',confirmButtonColor:'#2a9863'});
    <?php endif; ?>
    <?php if($error_msg): ?>
    Swal.fire({icon:'error',title:'Submission Failed',text:'<?= addslashes($error_msg) ?>',confirmButtonColor:'#c92a2a'});
    <?php endif; ?>

    /* ── Others leave type toggle ── */
    $('#leave_type_id').on('change', function(){
        var val = $(this).val();
        if(val === 'others'){
            $('#others_leave_wrap').slideDown(180);
            $('#others_leave_label').prop('required', true);
        } else {
            $('#others_leave_wrap').slideUp(180);
            $('#others_leave_label').prop('required', false).val('');
        }
    });

    $('#leaveForm').on('submit', function(e){
        e.preventDefault();
        var leaveType    = $('#leave_type_id').val();
        var othersLabel  = $('#others_leave_label').val().trim();
        var datesRaw     = $('#hidSelectedDates').val();
        var reason       = $('#reason').val().trim();
        var empId        = $('#current_emp_id').val();

        if(!leaveType){
            Swal.fire({icon:'warning',title:'Select Leave Type',text:'Please choose a leave type.',confirmButtonColor:'#2a9863'});
            return;
        }
        if(leaveType === 'others' && !othersLabel){
            Swal.fire({icon:'warning',title:'Specify Leave Type',text:'Please type the leave type under "Others".',confirmButtonColor:'#2a9863'});
            $('#others_leave_label').focus();
            return;
        }
        if(!datesRaw){
            Swal.fire({icon:'warning',title:'No Dates Selected',text:'Please select at least one leave date.',confirmButtonColor:'#2a9863'});
            return;
        }
        if(!reason){
            Swal.fire({icon:'warning',title:'Reason Required',text:'Please provide a reason.',confirmButtonColor:'#2a9863'});
            return;
        }

        // For "Others", skip balance validation (no leave_type_id integer available yet)
        if(leaveType === 'others'){
            var dateArr = datesRaw.split(',');
            var sorted  = dateArr.slice().sort();
            var datesHtml = sorted.map(function(k){
                var dt  = new Date(k+'T00:00:00');
                var lbl = dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
                return '<span style="display:inline-block;background:rgba(42,152,99,.14);color:#1a5c38;border-radius:12px;padding:2px 10px;margin:2px;font-size:.78rem;font-weight:700">'+lbl+'</span>';
            }).join('');

            Swal.fire({
                title:'Confirm Leave Request',
                html:`<div style="text-align:left;font-size:.88rem;line-height:1.8">
                        <p><strong>Leave Type:</strong> Others – ${othersLabel}</p>
                        <p><strong>Selected Dates (${sorted.length} day/s):</strong><br>${datesHtml}</p>
                        <p style="margin-top:8px"><strong>Reason:</strong> ${reason}</p>
                      </div>`,
                icon:'question',
                showCancelButton:true,
                confirmButtonColor:'#2a9863',
                cancelButtonColor:'#64748b',
                confirmButtonText:'<i class="fas fa-paper-plane"></i> Submit',
                cancelButtonText:'Review Again'
            }).then(function(r){
                if(r.isConfirmed){
                    $('#submitBtn').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Submitting…');
                    document.getElementById('leaveForm').submit();
                }
            });
            return;
        }

        // Show loading while validating
        Swal.fire({
            title: 'Validating leave request...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // Call validation endpoint
        $.post('leave_request.php', {
            ajax: 1,
            action: 'validate_leave',
            emp_id: empId,
            leave_type_id: leaveType,
            selected_dates: datesRaw
        }, function(resp){
            if(!resp.valid){
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Submit',
                    text: resp.message,
                    confirmButtonColor: '#c92a2a'
                });
                return;
            }

            // Validation passed – show confirmation with balance info
            var dateArr = datesRaw.split(',');
            var sorted = dateArr.slice().sort();
            var datesHtml = sorted.map(function(k){
                var dt = new Date(k+'T00:00:00');
                var lbl = dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
                return '<span style="display:inline-block;background:rgba(42,152,99,.14);color:#1a5c38;border-radius:12px;padding:2px 10px;margin:2px;font-size:.78rem;font-weight:700">'+lbl+'</span>';
            }).join('');

            Swal.fire({
                title:'Confirm Leave Request',
                html:`
                    <div style="text-align:left;font-size:.88rem;line-height:1.8">
                        <p><strong>Leave Type:</strong> ${$('#leave_type_id option:selected').text()}</p>
                        <p><strong>Selected Dates (${sorted.length} day/s):</strong><br>${datesHtml}</p>
                        <p><strong>Available Balance:</strong> ${resp.available.toFixed(3)} day(s)</p>
                        <p style="margin-top:8px"><strong>Reason:</strong> ${reason}</p>
                    </div>`,
                icon:'question',
                showCancelButton:true,
                confirmButtonColor:'#2a9863',
                cancelButtonColor:'#64748b',
                confirmButtonText:'<i class="fas fa-paper-plane"></i> Submit',
                cancelButtonText:'Review Again'
            }).then(function(r){
                if(r.isConfirmed){
                    $('#submitBtn').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Submitting…');
                    document.getElementById('leaveForm').submit();
                }
            });
        }, 'json').fail(function(){
            Swal.fire({icon:'error',title:'Error',text:'Validation failed. Please try again.'});
        });
    });

    $(document).on('click','.btn-cancel-request',function(){
        var id=$(this).data('id');
        Swal.fire({
            title:'Cancel Leave Request?',text:'This action cannot be undone.',icon:'warning',
            showCancelButton:true,confirmButtonColor:'#c92a2a',cancelButtonColor:'#64748b',
            confirmButtonText:'Yes, Cancel It',cancelButtonText:'Keep It'
        }).then(function(r){
            if(r.isConfirmed){
                $.post('leave_request.php',{ajax:1,action:'cancel',leave_request_id:id},function(res){
                    if(res.success){
                        Swal.fire({icon:'success',title:'Cancelled',text:'Your leave request has been cancelled.',confirmButtonColor:'#2a9863'}).then(function(){location.reload();});
                    } else {
                        Swal.fire({icon:'error',title:'Error',text:res.message||'Could not cancel.',confirmButtonColor:'#c92a2a'});
                    }
                },'json');
            }
        });
    });

    $(document).on('click','.btn-delete-request',function(){
        var id=$(this).data('id');
        var $row=$(this).closest('tr');
        Swal.fire({
            title:'Delete this record?',
            html:'<p style="font-size:.9rem;color:#495057;">This will permanently remove the cancelled/rejected leave record from your history.<br><br><strong>This cannot be undone.</strong></p>',
            icon:'warning',
            showCancelButton:true,
            confirmButtonColor:'#c92a2a',
            cancelButtonColor:'#64748b',
            confirmButtonText:'<i class="fas fa-trash-alt"></i>&nbsp;Yes, Delete It',
            cancelButtonText:'Keep It'
        }).then(function(r){
            if(r.isConfirmed){
                $.post('leave_request.php',{ajax:1,action:'delete',leave_request_id:id},function(res){
                    if(res.success){
                        // Remove the row from DataTable without full reload
                        if($.fn.DataTable.isDataTable('#leaveHistoryTable')){
                            $('#leaveHistoryTable').DataTable().row($row).remove().draw();
                        } else {
                            $row.fadeOut(300, function(){ $(this).remove(); });
                        }
                        Swal.fire({icon:'success',title:'Deleted',text:'Record has been permanently deleted.',confirmButtonColor:'#2a9863',timer:2000,showConfirmButton:false});
                    } else {
                        Swal.fire({icon:'error',title:'Error',text:res.message||'Could not delete.',confirmButtonColor:'#c92a2a'});
                    }
                },'json');
            }
        });
    });

    if($('#leaveHistoryTable tbody tr').length){
        $('#leaveHistoryTable').DataTable({
            pageLength:10,order:[[5,'desc']],
            columnDefs:[{orderable:false,targets:[6]}],
            dom:'<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
            language:{search:'',searchPlaceholder:'🔍 Search requests…'}
        });
    }

    /* ── View Detail ── */
    $(document).on('click','.btn-view-request',function(){
        var id = $(this).data('id');
        $('#lrModalInner').html('<div class="lrm-spinner"><i class="fas fa-spinner fa-spin"></i></div>');
        $('#lrDetailModal').modal('show');

        $.post('leave_request.php',{ajax:1,action:'get_details',leave_request_id:id},function(d){
            if(!d){ $('#lrModalInner').html('<p style="padding:32px;text-align:center;color:#c92a2a">Could not load details.</p>'); return; }

            var s = (d.status||'').toLowerCase();
            var badgeClass = s==='approved'?'lr-appr': s==='rejected'||s==='disapproved'?'lr-rejt': s==='cancelled'?'lr-canc':'lr-pend';
            var barClass   = s==='approved'?'bar-appr': s==='rejected'||s==='disapproved'?'bar-rejt': s==='cancelled'?'bar-canc':'bar-pend';

            // Build inclusive date chips
            var dateChips = '';
            if(d.inclusive_dates){
                d.inclusive_dates.split(',').forEach(function(dt){
                    dateChips += '<span class="lrm-date-chip">'+dt.trim()+'</span>';
                });
            }

            // HR remarks block
            var remarksHtml = d.hr_remarks
                ? '<div class="lrm-field full" style="margin-top:4px;">'+
                  '<label>HR Remarks</label>'+
                  '<div class="lrm-infobox lrm-remark">'+d.hr_remarks+'</div></div>'
                : '';

            // Processed by block
            var processedHtml = d.processed_by_name
                ? '<div class="lrm-field"><label>Processed By</label><span>'+d.processed_by_name+'</span></div>'+
                  '<div class="lrm-field"><label>Processed On</label><span>'+(d.approved_at||'—')+'</span></div>'
                : '';

            // Footer buttons
            var footerBtns = '<button class="lrm-btn-close" data-dismiss="modal">Close</button>';
            if(s==='approved'){
                footerBtns = '<a class="lrm-btn-form" href="generate_leave_form.php?leave_request_id='+d.leave_request_id+'" target="_blank"><i class="fas fa-file-download"></i> Download Form</a>' + footerBtns;
            }

            $('#lrModalInner').html(
                '<div class="lrm-header">'+
                    '<div class="lrm-header-icon"><i class="fas fa-calendar-check"></i></div>'+
                    '<div>'+
                        '<h5>Leave Request Details</h5>'+
                        '<p>Reference #'+d.leave_request_id+' &bull; Filed '+formatDate(d.created_at)+'</p>'+
                    '</div>'+
                    '<button type="button" class="close ml-auto" data-dismiss="modal" style="font-size:1.3rem;line-height:1;opacity:.5;background:none;border:none;">&times;</button>'+
                '</div>'+
                '<div class="lrm-body">'+
                    '<div class="lrm-status-bar '+barClass+'">'+
                        '<span class="lrm-status-label">Status</span>'+
                        '<span class="lr-badge '+badgeClass+'">'+ucFirst(d.status)+'</span>'+
                    '</div>'+
                    '<div class="lrm-grid">'+
                        '<div class="lrm-field"><label>Leave Type</label><span>'+(d.leave_type_name||'N/A')+'</span></div>'+
                        '<div class="lrm-field"><label>Working Days</label><span>'+(d.number_of_days||0)+' day(s)</span></div>'+
                        '<div class="lrm-field"><label>Date From</label><span>'+formatDate(d.date_from)+'</span></div>'+
                        '<div class="lrm-field"><label>Date To</label><span>'+formatDate(d.date_to)+'</span></div>'+
                        (dateChips ? '<div class="lrm-field full"><label>Inclusive Dates</label><div class="lrm-dates-wrap">'+dateChips+'</div></div>' : '')+
                        '<div class="lrm-field full"><label>Reason / Details</label><div class="lrm-infobox">'+(d.reason||'N/A')+'</div></div>'+
                        remarksHtml+
                        processedHtml+
                    '</div>'+
                '</div>'+
                '<div class="lrm-footer">'+footerBtns+'</div>'
            );
        },'json');
    });

    function formatDate(str){
        if(!str) return '—';
        var d=new Date(str.replace(' ','T'));
        return isNaN(d)?str:d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
    }
    function ucFirst(s){ return s?s.charAt(0).toUpperCase()+s.slice(1):s; }

});
</script>
</body>
</html>