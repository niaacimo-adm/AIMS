<?php
/**
 * leave_balance.php
 *
 * Leave Balance Management Module
 * Allows authorised roles to add or deduct leave credits per employee.
 *
 * Authorised roles  : Administrator (1), Manager (2), Unit Head (14), Focal Person (13)
 * Read-only access  : Heads (12) – can view but not edit
 * No access         : Employee (3) and all other roles
 */

session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db       = $database->getConnection();

$user_emp_id  = intval($_SESSION['emp_id']  ?? 0);
$user_role_id = intval($_SESSION['role_id'] ?? 0);

// ── Role gates ────────────────────────────────────────────────────────────────
$can_edit = in_array($user_role_id, [1, 2, 13, 14]);   // admin, manager, focal, unit-head
$can_view = in_array($user_role_id, [1, 2, 12, 13, 14]); // + Heads

if (!$can_view) {
    die('<p style="font-family:Arial;padding:30px;color:#c92a2a">Access denied. Insufficient privileges.</p>');
}

$current_year = (int) date('Y');

// ── AJAX / POST handler ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit();
    }

    $action        = $_POST['action'];
    $emp_id        = intval($_POST['emp_id']        ?? 0);
    $year          = intval($_POST['year']          ?? $current_year);
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);  // normalized: use leave_type_id
    $operation     = $_POST['operation']            ?? '';   // add | deduct
    $days          = round((float)($_POST['days']   ?? 0), 3);
    $reason        = trim($_POST['reason']          ?? '');

    if ($action === 'adjust') {
        if (!$emp_id || !$leave_type_id || !in_array($operation, ['add','deduct']) || $days <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit();
        }

        // Ensure balance row exists for this employee / leave type / year
        $db->query("INSERT IGNORE INTO leave_balance (emp_id, leave_type_id, year)
                    VALUES ($emp_id, $leave_type_id, $year)");

        // 'add' increases total_credits; 'deduct' increases used_days
        $col = ($operation === 'add') ? 'total_credits' : 'used_days';

        $stmt = $db->prepare("UPDATE leave_balance SET $col = $col + ?
                               WHERE emp_id = ? AND leave_type_id = ? AND year = ?");
        $stmt->bind_param('diii', $days, $emp_id, $leave_type_id, $year);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $db->error]);
            exit();
        }

        // Audit log — stores leave_type_id directly (no more enum limitation)
        $stmt2 = $db->prepare(
            "INSERT INTO leave_balance_log (emp_id, year, leave_type_id, action, days, reason, performed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt2->bind_param('iiisdsi', $emp_id, $year, $leave_type_id, $operation, $days, $reason, $user_emp_id);
        $stmt2->execute();
        $stmt2->close();

        // Return all balances for this employee/year
        $balances = [];
        $br = $db->query("SELECT lb.*, lt.leave_type_name
                           FROM leave_balance lb
                           JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id
                           WHERE lb.emp_id=$emp_id AND lb.year=$year
                           ORDER BY lt.leave_type_name");
        while ($row = $br->fetch_assoc()) $balances[] = $row;
        echo json_encode(['success' => true, 'balances' => $balances]);
        exit();
    }

    if ($action === 'get_balance') {
        $emp_id = intval($_POST['emp_id'] ?? 0);
        $year   = intval($_POST['year']   ?? $current_year);

        // Fetch all balance rows for this employee/year
        $balances = [];
        $br = $db->query("SELECT lb.*, lt.leave_type_name
                           FROM leave_balance lb
                           JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id
                           WHERE lb.emp_id=$emp_id AND lb.year=$year
                           ORDER BY lt.leave_type_name");
        while ($row = $br->fetch_assoc()) $balances[] = $row;

        // Fetch recent audit log
        $logs = [];
        $lr = $db->query(
            "SELECT l.*, lt.leave_type_name,
                    CONCAT(e.first_name,' ',e.last_name) AS done_by
             FROM leave_balance_log l
             LEFT JOIN leave_type lt ON l.leave_type_id = lt.leave_type_id
             LEFT JOIN employee   e  ON l.performed_by  = e.emp_id
             WHERE l.emp_id=$emp_id AND l.year=$year
             ORDER BY l.performed_at DESC LIMIT 10"
        );
        while ($row = $lr->fetch_assoc()) $logs[] = $row;
        echo json_encode(['success' => true, 'balances' => $balances, 'logs' => $logs]);
        exit();
    }

    // ── Initialize default balances for a single employee ──────────────────────
    if ($action === 'init_defaults') {
        if (!$emp_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee.']);
            exit();
        }
        // Default credits per leave_type_id (matches leave_type table seed data)
        $defaults = [
            1  => 15.0,   // Vacation Leave
            2  => 15.0,   // Sick Leave
            3  => 105.0,  // Maternity Leave
            4  => 7.0,    // Paternity Leave
            5  => 3.0,    // Special Privilege Leave
            6  => 7.0,    // Solo Parent Leave
            7  => 0.0,    // Study Leave (unlimited – seed as 0)
            8  => 10.0,   // VAWC Leave
            9  => 0.0,    // Rehabilitation Leave (case-to-case)
            10 => 5.0,    // Special Emergency Leave
            11 => 0.0,    // Forced Leave (computed)
            12 => 0.0,    // Terminal Leave (computed)
        ];
        $inserted = 0;
        foreach ($defaults as $lt_id => $credits) {
            // Only insert if no row yet — never overwrite existing balances
            $chk = $db->query("SELECT balance_id FROM leave_balance
                                WHERE emp_id=$emp_id AND leave_type_id=$lt_id AND year=$year");
            if ($chk->num_rows === 0) {
                $db->query("INSERT INTO leave_balance (emp_id, leave_type_id, year, total_credits)
                            VALUES ($emp_id, $lt_id, $year, $credits)");
                $inserted++;
            }
        }
        // Return fresh balances
        $balances = [];
        $br = $db->query("SELECT lb.*, lt.leave_type_name
                           FROM leave_balance lb
                           JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id
                           WHERE lb.emp_id=$emp_id AND lb.year=$year
                           ORDER BY lb.leave_type_id");
        while ($row = $br->fetch_assoc()) $balances[] = $row;
        echo json_encode(['success' => true, 'inserted' => $inserted, 'balances' => $balances]);
        exit();
    }

    // ── Bulk-initialize defaults for ALL non-Job-Order employees ────────────────
    if ($action === 'bulk_init_defaults') {
        $defaults = [
            1=>15.0, 2=>15.0, 3=>105.0, 4=>7.0, 5=>3.0,
            6=>7.0,  7=>0.0,  8=>10.0,  9=>0.0, 10=>5.0,
            11=>0.0, 12=>0.0,
        ];
        // Get all eligible employees (no Job Order)
        $emp_res = $db->query("
            SELECT e.emp_id FROM employee e
            LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
            WHERE (ap.status_name IS NULL OR ap.status_name != 'Job Order')
        ");
        $total_inserted = 0;
        $emp_count = 0;
        while ($emp_row = $emp_res->fetch_assoc()) {
            $eid = (int)$emp_row['emp_id'];
            $emp_count++;
            foreach ($defaults as $lt_id => $credits) {
                $chk = $db->query("SELECT balance_id FROM leave_balance
                                    WHERE emp_id=$eid AND leave_type_id=$lt_id AND year=$year");
                if ($chk->num_rows === 0) {
                    $db->query("INSERT INTO leave_balance (emp_id, leave_type_id, year, total_credits)
                                VALUES ($eid, $lt_id, $year, $credits)");
                    $total_inserted++;
                }
            }
        }
        echo json_encode([
            'success'  => true,
            'message'  => "Initialized $total_inserted balance rows across $emp_count employees for $year.",
            'inserted' => $total_inserted,
            'employees'=> $emp_count,
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// ── Fetch employees with their section (direct or via unit_section) ───────────
// ── Guard: ensure leave_balance tables exist ─────────────────────────────────
$tbl_check = $db->query("SHOW TABLES LIKE 'leave_balance'");
if ($tbl_check->num_rows === 0) {
    ?><!DOCTYPE html><html><head><title>Setup Required</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head><body><div class="container mt-5"><div class="card border-warning">
    <div class="card-header bg-warning"><h4>&#x26A0; Database Setup Required</h4></div>
    <div class="card-body">
    <p>The <code>leave_balance</code> table has not been created yet.</p>
    <p>Please run <strong>leave_balance.sql</strong> in phpMyAdmin first:</p>
    <ol>
      <li>Open phpMyAdmin &rarr; select the <code>sahur</code> database</li>
      <li>Click the <strong>SQL</strong> tab</li>
      <li>Paste the contents of <code>leave_balance.sql</code> and click <strong>Go</strong></li>
      <li>Refresh this page</li>
    </ol>
    <a href="javascript:history.back()" class="btn btn-primary">Back</a>
    </div></div></div></body></html><?php
    exit();
}

// Fetch all active leave types for the balance grid
$leave_types_all = [];
$lt_res = $db->query("SELECT * FROM leave_type WHERE is_active=1 ORDER BY leave_type_id");
while ($lt_row = $lt_res->fetch_assoc()) $leave_types_all[] = $lt_row;

$employees = [];
$result = $db->query("
    SELECT e.emp_id,
           e.first_name, e.last_name, e.middle_name, e.id_number, e.picture,
           pos.position_name,
           COALESCE(s.section_name, s2.section_name) AS section_name,
           COALESCE(s.section_code, s2.section_code) AS section_code,
           us.unit_name
    FROM employee e
    LEFT JOIN position          pos ON e.position_id          = pos.position_id
    LEFT JOIN section           s   ON e.section_id           = s.section_id
    LEFT JOIN unit_section      us  ON e.unit_section_id      = us.unit_id
    LEFT JOIN section           s2  ON us.section_id          = s2.section_id
    LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
    WHERE (ap.status_name IS NULL OR ap.status_name != 'Job Order')
    ORDER BY COALESCE(s.section_name, s2.section_name), e.last_name, e.first_name
");
while ($row = $result->fetch_assoc()) $employees[] = $row;

// Fetch ALL leave balances for the current year in one query, keyed by emp_id → leave_type_id
$all_balances = [];
$bal_res = $db->query("SELECT * FROM leave_balance WHERE year=$current_year");
while ($b = $bal_res->fetch_assoc()) {
    $all_balances[$b['emp_id']][$b['leave_type_id']] = $b;
}

// Group by section
$by_section = [];
foreach ($employees as $emp) {
    $sec = $emp['section_name'] ?? 'Unassigned';
    $by_section[$sec][] = $emp;
}

$year_options = range($current_year - 3, $current_year + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Leave Balance Management | NIA-ACIMO</title>
<?php include '../includes/header.php'; ?>
<style>
/* ══════════════════════════════════════════════
   CSS VARIABLES  — login theme
══════════════════════════════════════════════ */
:root {
    --lb-bg:        #f0faf5;
    --lb-card:      #ffffff;
    --lb-card-alt:  #f0faf5;
    --lb-border:    #c8e6d4;
    --lb-text:      #0f2d1e;
    --lb-muted:     #4a7c62;
    --lb-primary:   #1a5c38;
    --lb-accent:    #2a9863;
    --lb-green:     #24e78f;
    --lb-red:       #c92a2a;
    --lb-amber:     #d4af37;
    --lb-vac:       #2a9863;
    --lb-sick:      #1a5c38;
    --lb-shadow:    0 4px 24px rgba(26,92,56,.12);
}
body.dark-mode {
    --lb-bg:        #0b1f17;
    --lb-card:      #102f22;
    --lb-card-alt:  #0d2318;
    --lb-border:    #1c4d38;
    --lb-text:      #e0f7ec;
    --lb-muted:     #6db890;
    --lb-primary:   #24e78f;
    --lb-accent:    #2a9863;
    --lb-green:     #24e78f;
    --lb-amber:     #d4af37;
    --lb-vac:       #24e78f;
    --lb-sick:      #2a9863;
    --lb-shadow:    0 4px 24px rgba(0,0,0,.4);
}

/* ── Toolbar ── */
.lb-bar {
    background: linear-gradient(135deg,#1c4d38 0%,#2a9863 60%,#24e78f 100%);
    padding: 13px 22px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
    position: sticky; top: 57px; z-index: 200;
    box-shadow: 0 2px 12px rgba(26,92,56,.30);
}
body.dark-mode .lb-bar {
    background: linear-gradient(135deg,#1c4d38 0%,#102f22 60%,#091d14 100%);
}
.lb-bar-info h2 { color:#fff; font-size:.95rem; font-weight:800; margin:0 0 2px; }
.lb-bar-info p  { color:rgba(255,255,255,.72); font-size:.75rem; margin:0; }
.lb-bar-right { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

/* ── Search & Year ── */
.lb-search {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 8px; color:#fff;
    padding: 7px 12px; font-size:.82rem;
    outline:none; width:200px;
}
.lb-search::placeholder { color:rgba(255,255,255,.6); }
.lb-year-sel {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 8px; color:#fff;
    padding: 7px 10px; font-size:.82rem;
    outline:none; cursor:pointer;
}
.lb-year-sel option { color:#1e293b; background:#fff; }

/* ── Page wrap ── */
.lb-wrap { max-width:1200px; margin:20px auto; padding:0 16px 60px; }

/* ── Section group header ── */
.sec-group {
    margin-bottom: 28px;
    scroll-margin-top: 110px; /* clears lb-bar + sec-jump-bar */
}
.sec-header {
    display:flex; align-items:center; gap:10px;
    margin-bottom:12px;
}
.sec-chip {
    background: var(--lb-primary); color:#fff;
    padding: 4px 14px; border-radius:20px;
    font-size:.72rem; font-weight:800; letter-spacing:.5px; text-transform:uppercase;
}
.sec-count {
    font-size:.73rem; color:var(--lb-muted); font-weight:600;
}

/* ── Employee card grid ── */
.emp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 14px;
}

/* ── Employee card ── */
.emp-card {
    background: var(--lb-card);
    border: 1px solid var(--lb-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--lb-shadow);
    transition: transform .15s, box-shadow .15s;
    cursor: pointer;
}
.emp-card:hover { transform:translateY(-2px); box-shadow:0 8px 32px rgba(26,92,56,.15); }

.emp-card-top {
    display:flex; align-items:center; gap:12px;
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--lb-border);
}
.emp-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    object-fit: cover; flex-shrink:0;
    border: 2px solid var(--lb-border);
    background: #e2e8f0;
}
.emp-info { flex:1; min-width:0; }
.emp-name {
    font-size:.86rem; font-weight:800; color:var(--lb-text);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.emp-pos {
    font-size:.7rem; color:var(--lb-muted); margin-top:1px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.emp-unit {
    font-size:.65rem; color:var(--lb-accent); font-weight:700;
    margin-top:2px;
}

/* ── Balance bars ── */
.bal-section { padding: 12px 16px 14px; }
.bal-row { margin-bottom:10px; }
.bal-row:last-child { margin-bottom:0; }
.bal-labels {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:4px;
}
.bal-type {
    font-size:.67rem; font-weight:800; text-transform:uppercase; letter-spacing:.4px;
}
.bal-type.vac { color: var(--lb-vac); }
.bal-type.sick { color: var(--lb-sick); }
.bal-nums { font-size:.72rem; color:var(--lb-muted); }
.bal-nums strong { color:var(--lb-text); font-weight:700; }
.bal-track {
    height: 6px; background: var(--lb-border); border-radius:3px; overflow:hidden;
}
.bal-fill {
    height:100%; border-radius:3px; transition: width .4s ease;
}
.bal-fill.vac  { background: var(--lb-vac); }
.bal-fill.sick { background: var(--lb-sick); }

/* ── Edit btn ── */
.emp-card-foot {
    padding: 8px 16px;
    border-top: 1px solid var(--lb-border);
    display:flex; justify-content:flex-end;
}
.btn-adjust {
    display:inline-flex; align-items:center; gap:5px;
    background: linear-gradient(135deg,#1a5c38,#2a9863);
    color:#fff; border:none; border-radius:7px;
    padding: 6px 14px; font-size:.77rem; font-weight:700;
    cursor:pointer; transition:opacity .15s;
}
.btn-adjust:hover { opacity:.85; }
.btn-adjust.view-only {
    background: #e2e8f0; color:var(--lb-muted); cursor:default;
}
.btn-adjust.view-only:hover { opacity:1; }

/* ══════════════════════════════════════════════
   MODAL
══════════════════════════════════════════════ */
.lb-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,.5);
    z-index:1000; display:flex; align-items:center; justify-content:center;
    padding:16px; opacity:0; pointer-events:none;
    transition:opacity .2s;
}
.lb-overlay.open { opacity:1; pointer-events:all; }
.lb-modal {
    background:var(--lb-card); border-radius:16px;
    width:100%; max-width:540px; max-height:90vh;
    overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.3);
    transform:translateY(20px); transition:transform .2s;
}
.lb-overlay.open .lb-modal { transform:translateY(0); }

.modal-head {
    background:linear-gradient(135deg,#1a5c38,#2a9863);
    padding:18px 22px 14px;
    border-radius:16px 16px 0 0;
    display:flex; justify-content:space-between; align-items:flex-start;
}
.modal-head-info h3 { color:#fff; font-size:1rem; font-weight:800; margin:0 0 2px; }
.modal-head-info p  { color:rgba(255,255,255,.72); font-size:.75rem; margin:0; }
.modal-close {
    background:rgba(255,255,255,.15); border:none; border-radius:6px;
    color:#fff; width:28px; height:28px; cursor:pointer;
    font-size:1rem; display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
}
.modal-close:hover { background:rgba(255,255,255,.3); }

.modal-body { padding:20px 22px; }

/* Balance summary inside modal */
.modal-bal-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;
}
.mbal-box {
    border-radius:10px; padding:12px 14px;
    border:1px solid var(--lb-border);
}
.mbal-box.vac  { background:#e8f8f0; border-color:#86efba; }
.mbal-box.sick { background:#d1fae5; border-color:#4ade80; }
body.dark-mode .mbal-box.vac  { background:#0d2318; border-color:#2a9863; }
body.dark-mode .mbal-box.sick { background:#091d14; border-color:#1a5c38; }
.mbal-lbl  { font-size:.64rem; font-weight:800; text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
.mbal-lbl.vac  { color:var(--lb-vac); }
.mbal-lbl.sick { color:var(--lb-sick); }
.mbal-nums { display:flex; gap:10px; }
.mbal-item { text-align:center; flex:1; }
.mbal-val  { font-size:1.3rem; font-weight:900; color:var(--lb-text); line-height:1; }
.mbal-sub  { font-size:.6rem; color:var(--lb-muted); margin-top:2px; }
.mbal-balance {
    margin-top:8px; text-align:center;
    font-size:.78rem; font-weight:800; padding:4px;
    border-radius:6px;
}
.mbal-balance.vac  { background:var(--lb-vac);  color:#fff; }
.mbal-balance.sick { background:var(--lb-sick); color:#fff; }

/* Form inside modal */
.form-divider {
    font-size:.68rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.5px; color:var(--lb-muted);
    border-bottom:1px solid var(--lb-border);
    padding-bottom:6px; margin-bottom:14px;
}
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.form-row.full { grid-template-columns:1fr; }
.form-group { display:flex; flex-direction:column; gap:4px; }
.form-label {
    font-size:.67rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.4px; color:var(--lb-muted);
}
.form-control {
    background:var(--lb-card); border:1px solid var(--lb-border);
    border-radius:8px; padding:9px 12px;
    font-size:.85rem; color:var(--lb-text); outline:none;
    transition:border-color .15s;
}
.form-control:focus { border-color:var(--lb-accent); }
.op-toggle { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.op-btn {
    padding:9px; border-radius:8px; border:2px solid var(--lb-border);
    font-size:.8rem; font-weight:700; cursor:pointer;
    background:var(--lb-card); color:var(--lb-muted);
    transition:all .15s; text-align:center;
}
.op-btn.selected.add    { border-color:var(--lb-green); background:#d1fae5; color:var(--lb-green); }
.op-btn.selected.deduct { border-color:var(--lb-red);   background:#fff5f5; color:var(--lb-red); }
body.dark-mode .op-btn.selected.add    { background:#091d14; }
body.dark-mode .op-btn.selected.deduct { background:#2e0d0d; }

.lt-toggle { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.lt-btn {
    padding:9px; border-radius:8px; border:2px solid var(--lb-border);
    font-size:.8rem; font-weight:700; cursor:pointer;
    background:var(--lb-card); color:var(--lb-muted);
    transition:all .15s; text-align:center;
}
.lt-btn.selected.vac  { border-color:var(--lb-vac);  background:#e8f8f0; color:var(--lb-vac); }
.lt-btn.selected.sick { border-color:var(--lb-sick); background:#d1fae5; color:var(--lb-sick); }
body.dark-mode .lt-btn.selected.vac  { background:#0d2318; }
body.dark-mode .lt-btn.selected.sick { background:#091d14; }

.btn-submit {
    width:100%; padding:11px; border-radius:9px; border:none;
    background:linear-gradient(135deg,#1a5c38,#2a9863);
    color:#fff; font-size:.88rem; font-weight:800;
    cursor:pointer; margin-top:14px; transition:opacity .15s;
}
.btn-submit:hover { opacity:.87; }
.btn-submit:disabled { opacity:.5; cursor:not-allowed; }

/* Bulk-init button in toolbar */
.lb-bulk-btn {
    background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.35);
    color:#fff; border-radius:8px; padding:7px 14px;
    font-size:.78rem; font-weight:700; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px;
    transition:background .18s, transform .15s;
    white-space:nowrap;
}
.lb-bulk-btn:hover { background:rgba(255,255,255,.28); transform:translateY(-1px); }
.lb-bulk-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }

/* Toast */
.lb-toast {
    position:fixed; bottom:28px; right:24px;
    background:#0f2d1e; color:#e0f7ec;
    padding:12px 20px; border-radius:10px;
    font-size:.83rem; font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.25);
    transform:translateY(80px); opacity:0;
    transition:all .3s; z-index:2000;
}
.lb-toast.show { transform:translateY(0); opacity:1; }
.lb-toast.success { background:var(--lb-green); }
.lb-toast.error   { background:var(--lb-red); }

/* Log table */
.log-table { width:100%; border-collapse:collapse; font-size:.73rem; margin-top:10px; }
.log-table th { background:#f0faf5; padding:5px 8px; text-align:left; font-weight:700; color:var(--lb-muted); }
body.dark-mode .log-table th { background:#151827; }
.log-table td { padding:5px 8px; border-bottom:1px solid var(--lb-border); color:var(--lb-text); }
.badge-add    { display:inline-block; background:#d1fae5; color:var(--lb-green); padding:1px 7px; border-radius:10px; font-weight:700; font-size:.67rem; }
.badge-deduct { display:inline-block; background:#fff5f5; color:var(--lb-red);   padding:1px 7px; border-radius:10px; font-weight:700; font-size:.67rem; }
.badge-vac    { display:inline-block; background:#e8f8f0; color:var(--lb-vac);   padding:1px 7px; border-radius:10px; font-weight:700; font-size:.67rem; }
.badge-sick   { display:inline-block; background:#d1fae5; color:var(--lb-sick);  padding:1px 7px; border-radius:10px; font-weight:700; font-size:.67rem; }

/* ── Section Jump Bar ── */
.sec-jump-bar {
    background: var(--lb-card);
    border-bottom: 1px solid var(--lb-border);
    padding: 8px 22px;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
    position: sticky; top: 107px; z-index: 190;
    box-shadow: 0 2px 8px rgba(26,92,56,.07);
}
.sec-jump-label {
    font-size: .68rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .5px; color: var(--lb-muted); white-space: nowrap;
    display: flex; align-items: center; gap: 5px;
}
.sec-jump-btn {
    background: var(--lb-bg);
    border: 1px solid var(--lb-border);
    color: var(--lb-text);
    border-radius: 20px;
    padding: 4px 13px;
    font-size: .72rem; font-weight: 700;
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s, transform .12s;
    white-space: nowrap;
    display: inline-flex; align-items: center; gap: 5px;
}
.sec-jump-btn:hover {
    background: var(--lb-primary); color: #fff;
    border-color: var(--lb-primary); transform: translateY(-1px);
}
.sec-jump-btn.unassigned {
    border-style: dashed;
}
.sec-jump-btn.unassigned:hover {
    background: var(--lb-muted); border-color: var(--lb-muted);
}

/* ── Back to Top ── */
.btn-back-top {
    position: fixed; bottom: 80px; right: 24px;
    background: var(--lb-primary); color: #fff;
    border: none; border-radius: 50%;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 16px rgba(26,92,56,.3);
    opacity: 0; pointer-events: none;
    transition: opacity .25s, transform .25s;
    transform: translateY(10px);
}
.btn-back-top.visible { opacity: 1; pointer-events: all; transform: translateY(0); }
.btn-back-top:hover { background: var(--lb-accent); }

/* Responsive */
@media(max-width:640px){
    .emp-grid { grid-template-columns:1fr; }
    .modal-bal-grid, .form-row { grid-template-columns:1fr; }
    .sec-jump-bar { top: 100px; padding: 7px 12px; }
}
.content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
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
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper" style="background:var(--lb-bg,#f0faf5);">

<!-- ── Toolbar ── -->
<div class="lb-bar">
    <div class="lb-bar-info">
        <h2><i class="fas fa-calendar-check mr-2"></i>Leave Balance Management</h2>
        <p><?= count($employees) ?> employees (Job Order excluded) &bull; Year:
            <strong id="active-year-label" style="color:#fff;"><?= $current_year ?></strong>
        </p>
    </div>
    <div class="lb-bar-right">
        <input type="text" class="lb-search" id="empSearch" placeholder="&#xf002; Search employee…">
        <select class="lb-year-sel" id="yearSelect">
            <?php foreach ($year_options as $y): ?>
            <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($can_edit): ?>
        <button class="lb-bulk-btn" id="btnBulkInit" title="Initialize default leave credits for all employees who don't have balances yet for the selected year">
            <i class="fas fa-magic"></i> Init Defaults
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Section Jump Bar ── -->
<div class="sec-jump-bar" id="secJumpBar">
    <span class="sec-jump-label"><i class="fas fa-layer-group"></i> Jump to:</span>
    <?php foreach (array_keys($by_section) as $sec_name):
        $sec_slug = 'sec-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($sec_name));
    ?>
    <button class="sec-jump-btn <?= $sec_name === 'Unassigned' ? 'unassigned' : '' ?>"
            data-jump="<?= htmlspecialchars($sec_slug, ENT_QUOTES) ?>"
            title="<?= htmlspecialchars($sec_name, ENT_QUOTES) ?> — <?= count($by_section[$sec_name]) ?> employee<?= count($by_section[$sec_name]) !== 1 ? 's' : '' ?>">
        <?php if ($sec_name === 'Unassigned'): ?>
        <i class="fas fa-question-circle" style="opacity:.6"></i>
        <?php else: ?>
        <i class="fas fa-sitemap"></i>
        <?php endif; ?>
        <?= htmlspecialchars($sec_name) ?>
        <span style="opacity:.55;font-weight:600">(<?= count($by_section[$sec_name]) ?>)</span>
    </button>
    <?php endforeach; ?>
</div>

<!-- ── Content ── -->
<div class="lb-wrap" id="lb-wrap">
<?php foreach ($by_section as $sec_name => $emps): ?>
    <div class="sec-group"
         id="sec-<?= htmlspecialchars(preg_replace('/[^a-z0-9]+/', '-', strtolower($sec_name))) ?>"
         data-section="<?= htmlspecialchars(strtolower($sec_name)) ?>">
        <div class="sec-header">
            <span class="sec-chip"><?= htmlspecialchars($sec_name) ?></span>
            <span class="sec-count"><?= count($emps) ?> employee<?= count($emps) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="emp-grid">
        <?php foreach ($emps as $emp):
            $emp_balances = $all_balances[$emp['emp_id']] ?? [];
            // Quick summary: show VL (id=1) and SL (id=2) on the card; full list in modal
            $vl = $emp_balances[1] ?? ['total_credits'=>0,'used_days'=>0];
            $sl = $emp_balances[2] ?? ['total_credits'=>0,'used_days'=>0];
            $vac_earned = (float)$vl['total_credits'];
            $vac_used   = (float)$vl['used_days'];
            $sick_earned = (float)$sl['total_credits'];
            $sick_used   = (float)$sl['used_days'];
            $vac_bal  = round($vac_earned - $vac_used, 3);
            $sick_bal = round($sick_earned - $sick_used, 3);
            $vac_pct  = $vac_earned  > 0 ? min(100, round($vac_used  / $vac_earned  * 100)) : 0;
            $sick_pct = $sick_earned > 0 ? min(100, round($sick_used / $sick_earned * 100)) : 0;
            $pic_path = $emp['picture'] ? '../dist/img/employees/' . $emp['picture'] : '';
            $full_name = strtoupper(trim($emp['last_name'])) . ', ' . trim($emp['first_name']);
        ?>
        <div class="emp-card"
             data-emp-id="<?= $emp['emp_id'] ?>"
             data-name="<?= htmlspecialchars(strtolower($full_name)) ?>"
             data-section="<?= htmlspecialchars(strtolower($sec_name)) ?>"
             onclick="openModal(<?= $emp['emp_id'] ?>)">

            <div class="emp-card-top">
                <?php if ($pic_path): ?>
                <img src="<?= htmlspecialchars($pic_path) ?>" class="emp-avatar" alt="">
                <?php else: ?>
                <div class="emp-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:var(--lb-muted);">
                    <?= strtoupper(substr($emp['first_name'],0,1) . substr($emp['last_name'],0,1)) ?>
                </div>
                <?php endif; ?>
                <div class="emp-info">
                    <div class="emp-name"><?= htmlspecialchars($full_name) ?></div>
                    <div class="emp-pos"><?= htmlspecialchars($emp['position_name'] ?? '—') ?></div>
                    <?php if ($emp['unit_name']): ?>
                    <div class="emp-unit"><?= htmlspecialchars($emp['unit_name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bal-section">
                <!-- Vacation -->
                <div class="bal-row">
                    <div class="bal-labels">
                        <span class="bal-type vac"><i class="fas fa-plane-departure"></i> Vacation</span>
                        <span class="bal-nums">
                            <strong><?= number_format($vac_bal,3) ?></strong> / <?= number_format($vac_earned,3) ?> days
                        </span>
                    </div>
                    <div class="bal-track">
                        <div class="bal-fill vac" style="width:<?= $vac_pct ?>%"></div>
                    </div>
                </div>
                <!-- Sick -->
                <div class="bal-row">
                    <div class="bal-labels">
                        <span class="bal-type sick"><i class="fas fa-procedures"></i> Sick</span>
                        <span class="bal-nums">
                            <strong><?= number_format($sick_bal,3) ?></strong> / <?= number_format($sick_earned,3) ?> days
                        </span>
                    </div>
                    <div class="bal-track">
                        <div class="bal-fill sick" style="width:<?= $sick_pct ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="emp-card-foot">
                <?php if ($can_edit): ?>
                <button class="btn-adjust" onclick="event.stopPropagation();openModal(<?= $emp['emp_id'] ?>)">
                    <i class="fas fa-sliders-h"></i> Adjust Balance
                </button>
                <?php else: ?>
                <button class="btn-adjust view-only">
                    <i class="fas fa-eye"></i> View Only
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div><!-- .emp-grid -->
    </div><!-- .sec-group -->
<?php endforeach; ?>
</div><!-- .lb-wrap -->

</div><!-- .content-wrapper -->

<!-- ── Back to Top ── -->
<button class="btn-back-top" id="btnBackTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- ══════════════════════════════════════════════
     MODAL
══════════════════════════════════════════════ -->
<div class="lb-overlay" id="lbOverlay" onclick="closeModal(event)">
<div class="lb-modal" id="lbModal">

    <div class="modal-head">
        <div class="modal-head-info">
            <h3 id="modal-emp-name">—</h3>
            <p id="modal-emp-meta">—</p>
        </div>
        <button class="modal-close" onclick="closeModalDirect()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-body">

        <!-- Balance summary — all leave types, populated by JS -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
            <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--lb-muted);">
                <i class="fas fa-chart-bar mr-1"></i>Leave Balances
            </span>
            <?php if ($can_edit): ?>
            <button id="btn-init-defaults" onclick="initDefaults()"
                style="background:#f1f5ff;border:1px solid #c5cff8;color:var(--lb-accent);border-radius:7px;
                       padding:4px 12px;font-size:.73rem;font-weight:700;cursor:pointer;
                       display:inline-flex;align-items:center;gap:5px;transition:background .15s;"
                title="Seed default credits for this employee (only adds missing leave types)">
                <i class="fas fa-seedling"></i> Initialize Defaults
            </button>
            <?php endif; ?>
        </div>
        <div id="modal-bal-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-bottom:14px;">
            <p style="color:var(--lb-muted);font-size:.8rem;grid-column:1/-1;">Loading balances…</p>
        </div>

        <?php if ($can_edit): ?>
        <!-- Adjustment form -->
        <div class="form-divider">Adjust Balance</div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Leave Type</label>
                <select class="form-control" id="adj-leave-type-id">
                    <option value="">— Select Leave Type —</option>
                    <?php foreach ($leave_types_all as $lt): ?>
                    <option value="<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Operation</label>
                <div class="op-toggle">
                    <button type="button" class="op-btn selected add" id="op-add" onclick="setOP('add')">
                        <i class="fas fa-plus"></i> Add
                    </button>
                    <button type="button" class="op-btn deduct" id="op-deduct" onclick="setOP('deduct')">
                        <i class="fas fa-minus"></i> Deduct
                    </button>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Number of Days</label>
                <input type="number" class="form-control" id="adj-days" min="0.5" step="0.5" placeholder="e.g. 1.5">
            </div>
            <div class="form-group">
                <label class="form-label">Year</label>
                <select class="form-control" id="adj-year">
                    <?php foreach ($year_options as $y): ?>
                    <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row full">
            <div class="form-group">
                <label class="form-label">Reason / Remarks <span style="color:var(--lb-muted)">(optional)</span></label>
                <input type="text" class="form-control" id="adj-reason" placeholder="e.g. Monthly accrual, AWOL deduction…">
            </div>
        </div>

        <button class="btn-submit" id="btn-submit-adj" onclick="submitAdjustment()">
            <i class="fas fa-check mr-1"></i> Apply Adjustment
        </button>
        <?php endif; ?>

        <!-- Audit log -->
        <div class="form-divider" style="margin-top:22px;">Recent Adjustments</div>
        <div id="modal-log-wrap">
            <p style="color:var(--lb-muted);font-size:.8rem;">Loading…</p>
        </div>

    </div><!-- .modal-body -->
</div><!-- .lb-modal -->
</div><!-- .lb-overlay -->

<!-- Toast -->
<div class="lb-toast" id="lbToast"></div>

<?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
/* ══════════════════════════════════════════════
   STATE
══════════════════════════════════════════════ */
let activeEmpId = null;
let activeYear  = <?= $current_year ?>;
let activeOp    = 'add';

/* ══════════════════════════════════════════════
   YEAR SELECT
══════════════════════════════════════════════ */
document.getElementById('yearSelect').addEventListener('change', function() {
    activeYear = parseInt(this.value);
    document.getElementById('active-year-label').textContent = activeYear;
    location.reload(); // simplest – reload with new year
});

/* ══════════════════════════════════════════════
   SEARCH
══════════════════════════════════════════════ */
document.getElementById('empSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.emp-card').forEach(card => {
        const name = card.dataset.name || '';
        card.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
    // Hide empty section groups
    document.querySelectorAll('.sec-group').forEach(group => {
        const visible = [...group.querySelectorAll('.emp-card')].some(c => c.style.display !== 'none');
        group.style.display = visible ? '' : 'none';
    });
});

/* ══════════════════════════════════════════════
   MODAL OPEN / CLOSE
══════════════════════════════════════════════ */
function openModal(empId) {
    activeEmpId = empId;
    const card  = document.querySelector(`.emp-card[data-emp-id="${empId}"]`);
    if (card) {
        document.getElementById('modal-emp-name').textContent = card.querySelector('.emp-name').textContent;
        document.getElementById('modal-emp-meta').textContent =
            (card.querySelector('.emp-pos')?.textContent || '') +
            (card.querySelector('.emp-unit') ? '  •  ' + card.querySelector('.emp-unit').textContent : '');
    }
    document.getElementById('lbOverlay').classList.add('open');
    loadBalance();
}
function closeModal(e) {
    if (e.target === document.getElementById('lbOverlay')) closeModalDirect();
}
function closeModalDirect() {
    document.getElementById('lbOverlay').classList.remove('open');
    activeEmpId = null;
}

/* ══════════════════════════════════════════════
   LOAD BALANCE + LOGS
══════════════════════════════════════════════ */
function loadBalance() {
    const year = document.getElementById('adj-year')?.value || activeYear;
    fetch('leave_balance.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=get_balance&emp_id=${activeEmpId}&year=${year}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        updateBalanceDisplay(data.balances);
        renderLogs(data.logs);
    });
}

function updateBalanceDisplay(balances) {
    const grid = document.getElementById('modal-bal-grid');
    if (!balances || balances.length === 0) {
        grid.innerHTML = '<p style="color:var(--lb-muted);font-size:.8rem;grid-column:1/-1;">No balances recorded yet for this year.</p>';
        return;
    }
    let html = '';
    balances.forEach(function(b) {
        const credits = parseFloat(b.total_credits||0);
        const used    = parseFloat(b.used_days||0);
        const rem     = credits - used;
        const pct     = credits > 0 ? Math.min(100, Math.round(used/credits*100)) : 0;
        const color   = rem < 0 ? 'var(--lb-red)' : 'var(--lb-vac)';
        html += `<div style="background:var(--lb-card-alt);border:1px solid var(--lb-border);border-radius:10px;padding:10px 12px;">
            <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--lb-muted);margin-bottom:6px;">${b.leave_type_name}</div>
            <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:4px;">
                <span>Credits: <strong>${credits.toFixed(3)}</strong></span>
                <span>Used: <strong>${used.toFixed(3)}</strong></span>
            </div>
            <div style="height:5px;background:var(--lb-border);border-radius:3px;margin-bottom:4px;">
                <div style="height:100%;width:${pct}%;background:${color};border-radius:3px;transition:width .3s;"></div>
            </div>
            <div style="text-align:right;font-size:.75rem;font-weight:800;color:${color};">Balance: ${rem.toFixed(3)}</div>
        </div>`;
    });
    grid.innerHTML = html;
}

function renderLogs(logs) {
    const wrap = document.getElementById('modal-log-wrap');
    if (!logs || logs.length === 0) {
        wrap.innerHTML = '<p style="color:var(--lb-muted);font-size:.8rem;">No adjustments recorded yet.</p>';
        return;
    }
    let html = `<table class="log-table">
        <thead><tr>
            <th>Date</th><th>Type</th><th>Op</th><th>Days</th><th>By</th><th>Reason</th>
        </tr></thead><tbody>`;
    logs.forEach(l => {
        const dt = new Date(l.performed_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
        html += `<tr>
            <td>${dt}</td>
            <td><span style="font-size:.7rem;font-weight:700;">${l.leave_type_name||'—'}</span></td>
            <td><span class="badge-${l.action}">${l.action}</span></td>
            <td>${parseFloat(l.days).toFixed(3)}</td>
            <td>${l.done_by||'—'}</td>
            <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${l.reason||''}">${l.reason||'—'}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
}

/* ══════════════════════════════════════════════
   SECTION JUMP  — uses scrollIntoView (no scroll
   container guessing; works in any layout)
══════════════════════════════════════════════ */
function jumpToSection(secId) {
    const target = document.getElementById(secId);
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* Wire jump buttons via delegation */
document.getElementById('secJumpBar').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-jump]');
    if (btn) jumpToSection(btn.getAttribute('data-jump'));
});

/* Back-to-top */
(function() {
    const backBtn = document.getElementById('btnBackTop');
    /* Try content-wrapper first (AdminLTE), fall back to window */
    const scroller = document.querySelector('.content-wrapper') || window;
    const scrollTop = () => scroller === window ? window.scrollY : scroller.scrollTop;

    scroller.addEventListener('scroll', function() {
        if (backBtn) backBtn.classList.toggle('visible', scrollTop() > 300);
    });
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            /* scrollIntoView on first element is most reliable for "top" */
            document.getElementById('lb-wrap')?.previousElementSibling
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
})();


// setLT() removed — leave type is now a <select> dropdown
function setOP(op) {
    activeOp = op;
    document.getElementById('op-add').className    = 'op-btn' + (op==='add'    ? ' selected add'    : '');
    document.getElementById('op-deduct').className = 'op-btn' + (op==='deduct' ? ' selected deduct' : '');
}

/* ══════════════════════════════════════════════
   SUBMIT ADJUSTMENT
══════════════════════════════════════════════ */
function submitAdjustment() {
    const days   = parseFloat(document.getElementById('adj-days').value);
    const reason = document.getElementById('adj-reason').value.trim();
    const year   = document.getElementById('adj-year').value;

    if (!activeEmpId || isNaN(days) || days <= 0) {
        showToast('Please enter a valid number of days.', 'error'); return;
    }

    const leaveTypeId = document.getElementById('adj-leave-type-id').value;
    if (!leaveTypeId) { showToast('Please select a leave type.', 'error'); return; }

    const btn = document.getElementById('btn-submit-adj');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving…';

    const params = new URLSearchParams({
        action:        'adjust',
        emp_id:        activeEmpId,
        year:          year,
        leave_type_id: leaveTypeId,
        operation:     activeOp,
        days:          days,
        reason:        reason
    });

    fetch('leave_balance.php', {
        method: 'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i> Apply Adjustment';
        if (data.success) {
            showToast('Balance updated successfully!', 'success');
            updateBalanceDisplay(data.balances);
            document.getElementById('adj-days').value   = '';
            document.getElementById('adj-reason').value = '';
            // Reload logs
            loadBalance();
            // Update VL/SL summary on the card (find VL id=1, SL id=2)
            const vl = data.balances.find(b => b.leave_type_id == 1) || {total_credits:0, used_days:0};
            const sl = data.balances.find(b => b.leave_type_id == 2) || {total_credits:0, used_days:0};
            updateCard(activeEmpId, vl, sl);
        } else {
            showToast(data.message || 'Error occurred.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i> Apply Adjustment';
        showToast('Network error. Please try again.', 'error');
    });
}

/* ══════════════════════════════════════════════
   UPDATE CARD ON GRID (no page reload)
══════════════════════════════════════════════ */
function updateCard(empId, vl, sl) {
    const card = document.querySelector(`.emp-card[data-emp-id="${empId}"]`);
    if (!card) return;
    const ve = parseFloat(vl.total_credits||0), vu = parseFloat(vl.used_days||0);
    const se = parseFloat(sl.total_credits||0), su = parseFloat(sl.used_days||0);
    const vb = ve - vu, sb = se - su;
    const vp = ve > 0 ? Math.min(100, Math.round(vu/ve*100)) : 0;
    const sp = se > 0 ? Math.min(100, Math.round(su/se*100)) : 0;

    card.querySelectorAll('.bal-nums')[0].innerHTML =
        `<strong>${vb.toFixed(3)}</strong> / ${ve.toFixed(3)} days`;
    card.querySelectorAll('.bal-nums')[1].innerHTML =
        `<strong>${sb.toFixed(3)}</strong> / ${se.toFixed(3)} days`;
    card.querySelectorAll('.bal-fill')[0].style.width = vp + '%';
    card.querySelectorAll('.bal-fill')[1].style.width = sp + '%';
}

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
function showToast(msg, type='success') {
    const t = document.getElementById('lbToast');
    t.textContent = msg;
    t.className = `lb-toast show ${type}`;
    setTimeout(() => t.className = 'lb-toast', 3000);
}

/* Reload on year change inside modal */
document.getElementById('adj-year')?.addEventListener('change', function() {
    if (activeEmpId) loadBalance();
});

/* ══════════════════════════════════════════════
   INITIALIZE DEFAULTS (single employee)
══════════════════════════════════════════════ */
function initDefaults() {
    if (!activeEmpId) return;
    const year = document.getElementById('adj-year')?.value || activeYear;
    const btn  = document.getElementById('btn-init-defaults');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing…'; }

    fetch('leave_balance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=init_defaults&emp_id=${activeEmpId}&year=${year}`
    })
    .then(r => r.json())
    .then(data => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-seedling"></i> Initialize Defaults'; }
        if (data.success) {
            const msg = data.inserted > 0
                ? `${data.inserted} leave type(s) initialized with default credits.`
                : 'All leave types already have balances — nothing was changed.';
            showToast(msg, data.inserted > 0 ? 'success' : 'info');
            updateBalanceDisplay(data.balances);
            const vl = data.balances.find(b => b.leave_type_id == 1) || {total_credits:0, used_days:0};
            const sl = data.balances.find(b => b.leave_type_id == 2) || {total_credits:0, used_days:0};
            updateCard(activeEmpId, vl, sl);
        } else {
            showToast(data.message || 'Error initializing defaults.', 'error');
        }
    })
    .catch(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-seedling"></i> Initialize Defaults'; }
        showToast('Network error.', 'error');
    });
}

/* ══════════════════════════════════════════════
   BULK INITIALIZE DEFAULTS (all employees)
══════════════════════════════════════════════ */
<?php if ($can_edit): ?>
document.getElementById('btnBulkInit')?.addEventListener('click', function() {
    const year = document.getElementById('yearSelect').value;
    if (!confirm(`Initialize default leave credits for ALL employees for ${year}?\n\nThis only adds missing balances — existing records will NOT be changed.`)) return;

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing…';

    fetch('leave_balance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=bulk_init_defaults&year=${year}`
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Init Defaults';
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1800);
        } else {
            showToast(data.message || 'Bulk init failed.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Init Defaults';
        showToast('Network error.', 'error');
    });
});
<?php endif; ?>
</script>
</body>
</html>