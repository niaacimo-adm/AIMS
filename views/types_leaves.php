<?php
/**
 * types_leaves.php
 *
 * Leave Types CRUD + Default Balance Management
 * Dynamically drives leave_type table — no hardcoding needed.
 *
 * Access : manage_settings permission required
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!hasPermission('manage_settings')) {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
$db       = $database->getConnection();

$current_year = (int) date('Y');

// ── Auto-migration: add default_credits column if it does not yet exist ──────
// Runs silently on every page load until the column exists. Safe to keep.
$_col_chk = $db->query("SHOW COLUMNS FROM `leave_type` LIKE 'default_credits'");
if ($_col_chk && $_col_chk->num_rows === 0) {
    $db->query("ALTER TABLE `leave_type`
                ADD COLUMN `default_credits` DECIMAL(8,3) DEFAULT NULL
                COMMENT 'Default balance credits to seed per employee per year'");
}

// ── Auto-migration: add is_main column if it does not yet exist ──────────────
// 1 = Main/standard leave type | 0 = Other/supplemental leave type
$_main_chk = $db->query("SHOW COLUMNS FROM `leave_type` LIKE 'is_main'");
if ($_main_chk && $_main_chk->num_rows === 0) {
    $db->query("ALTER TABLE `leave_type`
                ADD COLUMN `is_main` TINYINT(1) NOT NULL DEFAULT 1
                COMMENT '1 = Main/standard leave type | 0 = Other/supplemental'
                AFTER `is_active`");
    // Seed known "other" leave types
    $db->query("UPDATE `leave_type` SET `is_main` = 0
                WHERE `leave_type_name` IN ('Terminal Leave','Wellness Leave')");
}

// ══════════════════════════════════════════════════════════════════════════════
// AJAX / POST Handler
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = trim($_POST['action']);

    // ── CREATE ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name        = trim($_POST['leave_type_name']  ?? '');
        $description = trim($_POST['description']       ?? '');
        $max_days_raw    = $_POST['max_days']        ?? '';
        $def_credits_raw = $_POST['default_credits'] ?? '';
        $max_days        = ($max_days_raw    !== '') ? round((float)$max_days_raw,    1) : null;
        $default_credits = ($def_credits_raw !== '') ? round((float)$def_credits_raw, 3) : null;
        $is_active   = (isset($_POST["is_active"]) && $_POST["is_active"] == "1") ? 1 : 0;
        $is_main     = (isset($_POST["is_main"]) && $_POST["is_main"] == "1") ? 1 : 0;

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Leave type name is required.']);
            exit;
        }

        // Check duplicate name
        $chk = $db->prepare("SELECT leave_type_id FROM leave_type WHERE leave_type_name = ?");
        $chk->bind_param("s", $name);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A leave type with this name already exists.']);
            exit;
        }
        $chk->close();

        $stmt = $db->prepare(
            "INSERT INTO leave_type (leave_type_name, description, max_days, is_active, is_main, default_credits)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        // Column is auto-created by the migration block above if it does not exist yet.
        $stmt->bind_param("ssdiid", $name, $description, $max_days, $is_active, $is_main, $default_credits);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            echo json_encode(['success' => true, 'leave_type_id' => $new_id, 'message' => 'Leave type created successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $db->error]);
        }
        exit;
    }

    // ── READ / GET SINGLE ─────────────────────────────────────────────────────
    if ($action === 'get') {
        $id = intval($_POST['leave_type_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM leave_type WHERE leave_type_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Leave type not found.']);
        }
        exit;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $id          = intval($_POST['leave_type_id']   ?? 0);
        $name        = trim($_POST['leave_type_name']   ?? '');
        $description = trim($_POST['description']        ?? '');
        $max_days_raw    = $_POST['max_days']        ?? '';
        $def_credits_raw = $_POST['default_credits'] ?? '';
        $max_days        = ($max_days_raw    !== '') ? round((float)$max_days_raw,    1) : null;
        $default_credits = ($def_credits_raw !== '') ? round((float)$def_credits_raw, 3) : null;
        $is_active   = (isset($_POST["is_active"]) && $_POST["is_active"] == "1") ? 1 : 0;
        $is_main     = (isset($_POST["is_main"]) && $_POST["is_main"] == "1") ? 1 : 0;

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit;
        }

        // Check duplicate (excluding self)
        $chk = $db->prepare("SELECT leave_type_id FROM leave_type WHERE leave_type_name = ? AND leave_type_id != ?");
        $chk->bind_param("si", $name, $id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Another leave type with this name already exists.']);
            exit;
        }
        $chk->close();

        $stmt = $db->prepare(
            "UPDATE leave_type SET leave_type_name=?, description=?, max_days=?, is_active=?, is_main=?, default_credits=?
             WHERE leave_type_id=?"
        );
        $stmt->bind_param("ssdiidi", $name, $description, $max_days, $is_active, $is_main, $default_credits, $id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Leave type updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $db->error]);
        }
        exit;
    }

    // ── TOGGLE ACTIVE ─────────────────────────────────────────────────────────
    if ($action === 'toggle_active') {
        $id = intval($_POST['leave_type_id'] ?? 0);
        $stmt = $db->prepare("UPDATE leave_type SET is_active = !is_active WHERE leave_type_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Return new status
            $row = $db->query("SELECT is_active FROM leave_type WHERE leave_type_id=$id")->fetch_assoc();
            echo json_encode(['success' => true, 'is_active' => (int)$row['is_active']]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = intval($_POST['leave_type_id'] ?? 0);

        // Safety: check if any leave_request references this type
        $ref = $db->query("SELECT COUNT(*) AS cnt FROM leave_request WHERE leave_type_id=$id")->fetch_assoc();
        if ((int)$ref['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: this leave type has existing leave requests. Deactivate it instead.']);
            exit;
        }

        // Also check leave_balance
        $ref2 = $db->query("SELECT COUNT(*) AS cnt FROM leave_balance WHERE leave_type_id=$id")->fetch_assoc();
        if ((int)$ref2['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: this leave type has balance records. Deactivate it instead.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM leave_type WHERE leave_type_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave type deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $db->error]);
        }
        exit;
    }

    // ── BULK INIT DEFAULTS (uses leave_type.default_credits dynamically) ──────
    if ($action === 'bulk_init_defaults') {
        $year = intval($_POST['year'] ?? $current_year);

        // Fetch all active leave types with their default credits
        $lt_res = $db->query("SELECT leave_type_id, COALESCE(default_credits, 0) AS default_credits FROM leave_type WHERE is_active = 1");
        $leave_types = [];
        while ($r = $lt_res->fetch_assoc()) {
            $leave_types[(int)$r['leave_type_id']] = (float)($r['default_credits'] ?? 0);
        }

        // Get all eligible employees
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
            foreach ($leave_types as $lt_id => $credits) {
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
            'success' => true,
            'message' => "Initialized $total_inserted balance record(s) across $emp_count employee(s) for $year."
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Fetch all leave types for page render ─────────────────────────────────────
$leave_types = [];
$res = $db->query("SELECT * FROM leave_type ORDER BY leave_type_id ASC");
while ($row = $res->fetch_assoc()) {
    $leave_types[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave Types | NIA-ACIMO</title>

<!-- AdminLTE / Bootstrap -->
<link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="../dist/css/adminlte.min.css">
<link rel="stylesheet" href="../plugins/sweetalert2/sweetalert2.min.css">

<style>
/* ── CSS Variables (AdminLTE dark sidebar palette) ── */
:root {
    --primary: #2a9863;
    --primary-dark: #1e7a4f;
    --primary-light: #d4f5e5;
    --danger:  #c92a2a;
    --warning: #f59f00;
    --info:    #1c7ed6;
    --muted:   #6c757d;
    --border:  #dee2e6;
    --card-shadow: 0 2px 12px rgba(0,0,0,.08);
    --radius: 10px;
}

/* ── Layout ── */
.content-wrapper { background: #f4f6f9; }

/* ── Page Header ── */
.lt-page-header {
    background: linear-gradient(135deg, #1a6b42 0%, #2a9863 60%, #3dbf7e 100%);
    border-radius: var(--radius);
    color: #fff;
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 4px 18px rgba(42,152,99,.35);
}
.lt-page-header h4 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: -.2px;
}
.lt-page-header p {
    margin: 4px 0 0;
    opacity: .85;
    font-size: .88rem;
}
.lt-page-header .header-icon {
    width: 56px; height: 56px;
    background: rgba(255,255,255,.15);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

/* ── Toolbar ── */
.lt-toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}
.lt-toolbar input[type="text"] {
    flex: 1; min-width: 200px; max-width: 320px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .9rem;
    outline: none;
    transition: border-color .2s;
}
.lt-toolbar input[type="text"]:focus { border-color: var(--primary); }

/* ── Buttons ── */
.btn-lt {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px;
    border: none; border-radius: 8px; cursor: pointer;
    font-size: .88rem; font-weight: 600;
    transition: all .18s;
}
.btn-lt-primary   { background: var(--primary); color: #fff; }
.btn-lt-primary:hover  { background: var(--primary-dark); }
.btn-lt-secondary { background: #e9ecef; color: #495057; }
.btn-lt-secondary:hover { background: #dee2e6; }
.btn-lt-danger    { background: var(--danger); color: #fff; }
.btn-lt-danger:hover { background: #a61f1f; }
.btn-lt-info      { background: var(--info); color: #fff; }
.btn-lt-info:hover { background: #155fa0; }
.btn-lt-warning   { background: var(--warning); color: #fff; }
.btn-lt-warning:hover { background: #d48c00; }
.btn-lt:disabled { opacity: .6; cursor: not-allowed; }

/* ── Stats Bar ── */
.lt-stats {
    display: flex; gap: 14px; flex-wrap: wrap;
    margin-bottom: 22px;
}
.lt-stat-card {
    flex: 1; min-width: 130px;
    background: #fff;
    border-radius: var(--radius);
    padding: 16px 18px;
    box-shadow: var(--card-shadow);
    display: flex; align-items: center; gap: 14px;
}
.lt-stat-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.lt-stat-icon.green  { background: var(--primary-light); color: var(--primary); }
.lt-stat-icon.red    { background: #ffe3e3; color: var(--danger); }
.lt-stat-icon.blue   { background: #d0ebff; color: var(--info); }
.lt-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1; color: #212529; }
.lt-stat-label { font-size: .78rem; color: var(--muted); margin-top: 2px; }

/* ── Table Card ── */
.lt-card {
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}
.lt-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    font-weight: 700; color: #343a40; font-size: .95rem;
}

/* ── Table ── */
.lt-table { width: 100%; border-collapse: collapse; }
.lt-table thead th {
    background: #f8f9fa;
    padding: 12px 16px;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}
.lt-table tbody tr {
    border-bottom: 1px solid #f1f3f5;
    transition: background .12s;
}
.lt-table tbody tr:hover { background: #f8fff9; }
.lt-table tbody td {
    padding: 13px 16px;
    font-size: .88rem;
    color: #343a40;
    vertical-align: middle;
}

/* ── Badge ── */
.badge-active   { background: #d3f9d8; color: #2b7a3a; border-radius: 20px; padding: 3px 12px; font-size: .75rem; font-weight: 700; }
.badge-inactive { background: #ffe3e3; color: #a61f1f; border-radius: 20px; padding: 3px 12px; font-size: .75rem; font-weight: 700; }

/* ── Leave Name Pill ── */
.lt-name {
    display: flex; align-items: center; gap: 8px;
}
.lt-color-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    background: var(--primary);
}

/* ── Action Buttons (table) ── */
.tbl-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.tbl-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 11px; border: none; border-radius: 6px;
    font-size: .78rem; font-weight: 600; cursor: pointer;
    transition: all .15s;
}
.tbl-btn-edit    { background: #d0ebff; color: var(--info); }
.tbl-btn-edit:hover { background: #a5d8ff; }
.tbl-btn-toggle  { background: #fff3bf; color: #7c5a00; }
.tbl-btn-toggle.deact { background: #ffe3e3; color: var(--danger); }
.tbl-btn-toggle:hover { opacity: .8; }
.tbl-btn-delete  { background: #ffe3e3; color: var(--danger); }
.tbl-btn-delete:hover { background: #ffc9c9; }

/* ── Max Days display ── */
.max-days-badge {
    background: #e7f5ff; color: #1c7ed6;
    border-radius: 6px; padding: 2px 10px;
    font-size: .8rem; font-weight: 700;
}
.max-days-unlimited {
    color: var(--muted); font-style: italic; font-size: .82rem;
}

/* ── Default Credits ── */
.default-credits-badge {
    background: #ebfbee; color: #2b8a3e;
    border-radius: 6px; padding: 2px 10px;
    font-size: .8rem; font-weight: 700;
}

/* ── Category Badges ── */
.badge-main  { background: #d0ebff; color: #1864ab; border-radius: 20px; padding: 3px 12px; font-size: .75rem; font-weight: 700; }
.badge-other { background: #fff3bf; color: #7c5a00; border-radius: 20px; padding: 3px 12px; font-size: .75rem; font-weight: 700; }

/* ── Group Header Rows ── */
.lt-group-header td { padding: 0 !important; border-bottom: none !important; }
.lt-group-label {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 16px;
    background: linear-gradient(90deg, #f0faf5 0%, #f8f9fa 100%);
    border-top: 2px solid var(--primary);
    border-bottom: 1px solid #dee2e6;
    font-size: .78rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--primary);
}
.lt-group-others .lt-group-label {
    background: linear-gradient(90deg, #fffbeb 0%, #f8f9fa 100%);
    border-top-color: var(--warning);
    color: #7c5a00;
}
.lt-group-count {
    margin-left: auto;
    background: rgba(0,0,0,.06);
    border-radius: 10px; padding: 1px 9px;
    font-size: .72rem; font-weight: 600; color: inherit;
}

/* ── Empty state ── */
.lt-empty {
    text-align: center; padding: 60px 20px; color: var(--muted);
}
.lt-empty i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 12px; }

/* ══════════════════════════════════════
   MODAL
══════════════════════════════════════ */
.lt-modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center; justify-content: center;
}
.lt-modal-overlay.open { display: flex; }
.lt-modal {
    background: #fff;
    border-radius: 14px;
    width: 100%; max-width: 540px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    overflow: hidden;
    animation: ltModalIn .22s ease;
}
@keyframes ltModalIn {
    from { opacity:0; transform:translateY(-18px) scale(.97); }
    to   { opacity:1; transform:translateY(0)     scale(1);   }
}
.lt-modal-header {
    background: linear-gradient(135deg, #1a6b42, #2a9863);
    color: #fff;
    padding: 18px 24px;
    display: flex; align-items: center; gap: 12px;
}
.lt-modal-header h5 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.lt-modal-header .modal-close {
    margin-left: auto;
    background: rgba(255,255,255,.2);
    border: none; border-radius: 50%;
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; cursor: pointer; font-size: 1.1rem; line-height: 1;
    transition: background .15s;
}
.lt-modal-header .modal-close:hover { background: rgba(255,255,255,.35); }
.lt-modal-body { padding: 24px; }
.lt-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
}

/* ── Form Fields ── */
.lt-form-group { margin-bottom: 18px; }
.lt-form-group label {
    display: block; font-size: .82rem;
    font-weight: 700; color: #495057; margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: .3px;
}
.lt-form-group label .required { color: var(--danger); margin-left: 2px; }
.lt-form-group input[type="text"],
.lt-form-group input[type="number"],
.lt-form-group textarea,
.lt-form-group select {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-size: .9rem; color: #343a40;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
}
.lt-form-group input:focus,
.lt-form-group textarea:focus,
.lt-form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(42,152,99,.12);
}
.lt-form-group textarea { resize: vertical; min-height: 80px; }
.lt-form-row { display: flex; gap: 14px; }
.lt-form-row .lt-form-group { flex: 1; }

/* Toggle Switch */
.lt-switch-group {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px;
    background: #f8f9fa; border-radius: 8px;
    border: 1.5px solid var(--border);
}
.lt-switch { position: relative; display: inline-block; width: 42px; height: 24px; }
.lt-switch input { opacity: 0; width: 0; height: 0; }
.lt-switch-slider {
    position: absolute; inset: 0;
    background: #ced4da; border-radius: 24px; cursor: pointer;
    transition: .25s;
}
.lt-switch-slider::before {
    content: '';
    position: absolute;
    width: 18px; height: 18px;
    left: 3px; bottom: 3px;
    background: #fff; border-radius: 50%;
    transition: .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.lt-switch input:checked + .lt-switch-slider { background: var(--primary); }
.lt-switch input:checked + .lt-switch-slider::before { transform: translateX(18px); }
.lt-switch-label { font-size: .88rem; font-weight: 600; color: #495057; }

/* Helper text */
.lt-helper { font-size: .78rem; color: var(--muted); margin-top: 5px; }

/* ── Bulk Init Section ── */
.bulk-init-card {
    background: linear-gradient(135deg, #ebfbee 0%, #d3f9d8 100%);
    border: 1.5px solid #b2f2bb;
    border-radius: var(--radius);
    padding: 18px 22px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.bulk-init-card .bulk-icon {
    width: 46px; height: 46px;
    background: var(--primary);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.2rem; flex-shrink: 0;
}
.bulk-init-card h6 { margin: 0 0 2px; font-size: .95rem; font-weight: 700; color: #1a4d2e; }
.bulk-init-card p  { margin: 0; font-size: .82rem; color: #2d7a50; }
.bulk-init-card .bulk-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; }
.bulk-init-card select {
    padding: 7px 12px;
    border: 1.5px solid #b2f2bb;
    border-radius: 8px; background: #fff;
    font-size: .88rem; outline: none;
}

/* ── Responsive ── */
@media (max-width: 640px) {
    .lt-form-row { flex-direction: column; }
    .lt-stats .lt-stat-card { min-width: 100%; }
    .lt-page-header { flex-direction: column; align-items: flex-start; }
    .bulk-init-card .bulk-actions { margin-left: 0; }
}

/* ── Toast ── */
#ltToast {
    position: fixed; bottom: 24px; right: 24px;
    padding: 14px 22px; border-radius: 10px;
    font-size: .88rem; font-weight: 600; color: #fff;
    z-index: 99999; transform: translateY(80px);
    opacity: 0; transition: all .3s; pointer-events: none;
    box-shadow: 0 4px 20px rgba(0,0,0,.2);
    max-width: 320px;
}
#ltToast.show { transform: translateY(0); opacity: 1; }
#ltToast.success { background: var(--primary); }
#ltToast.error   { background: var(--danger); }
#ltToast.info    { background: var(--info); }
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <ol class="breadcrumb" style="background:none;padding:10px 0 0;font-size:.82rem;">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Leave Types</li>
            </ol>
        </div>
    </div>

    <section class="content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="lt-page-header">
            <div>
                <h4><i class="fas fa-tags" style="margin-right:10px;"></i>Leave Types Management</h4>
                <p>Add, edit, and manage leave types dynamically. Changes reflect immediately across the system.</p>
            </div>
            <div class="header-icon"><i class="fas fa-file-medical-alt"></i></div>
        </div>

        <!-- Stats Bar -->
        <?php
            $total   = count($leave_types);
            $active  = count(array_filter($leave_types, fn($r) => $r['is_active']));
            $inactive = $total - $active;
        ?>
        <div class="lt-stats">
            <div class="lt-stat-card">
                <div class="lt-stat-icon blue"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="lt-stat-value"><?= $total ?></div>
                    <div class="lt-stat-label">Total Leave Types</div>
                </div>
            </div>
            <div class="lt-stat-card">
                <div class="lt-stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="lt-stat-value"><?= $active ?></div>
                    <div class="lt-stat-label">Active Types</div>
                </div>
            </div>
            <div class="lt-stat-card">
                <div class="lt-stat-icon red"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="lt-stat-value"><?= $inactive ?></div>
                    <div class="lt-stat-label">Inactive Types</div>
                </div>
            </div>
        </div>

        <!-- Bulk Init Section -->
        <div class="bulk-init-card">
            <div class="bulk-icon"><i class="fas fa-magic"></i></div>
            <div>
                <h6>Initialize Default Leave Balances</h6>
                <p>Seeds leave_balance records for all employees using each type's Default Credits value below.</p>
            </div>
            <div class="bulk-actions">
                <select id="bulkInitYear">
                    <?php for ($y = $current_year + 1; $y >= $current_year - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $current_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn-lt btn-lt-primary" id="btnBulkInit">
                    <i class="fas fa-seedling"></i> Init All Defaults
                </button>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="lt-toolbar">
            <input type="text" id="ltSearch" placeholder="🔍 Search leave types…">
            <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn-lt btn-lt-secondary" id="btnFilterAll"   onclick="filterStatus('all')"     >All</button>
                <button class="btn-lt btn-lt-primary"   id="btnFilterActive" onclick="filterStatus('active')"  ><i class="fas fa-check-circle"></i> Active</button>
                <button class="btn-lt btn-lt-danger"    id="btnFilterInact"  onclick="filterStatus('inactive')"><i class="fas fa-ban"></i> Inactive</button>
                <button class="btn-lt btn-lt-primary"   onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Add Leave Type
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="lt-card">
            <div class="lt-card-header">
                <span><i class="fas fa-list" style="margin-right:8px;color:var(--primary);"></i>Leave Types</span>
                <span style="font-size:.78rem;color:var(--muted);font-weight:400;" id="tableCount">
                    Showing <?= $total ?> record(s)
                </span>
            </div>
            <div style="overflow-x:auto;">
                <table class="lt-table" id="ltTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Leave Type Name</th>
                            <th>Description</th>
                            <th>Max Days/Year</th>
                            <th>Default Credits</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ltTableBody">
                    <?php if (empty($leave_types)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="lt-empty">
                                    <i class="fas fa-inbox"></i>
                                    No leave types found. Add one to get started.
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $main_types  = array_values(array_filter($leave_types, fn($r) => ($r['is_main'] ?? 1) == 1));
                        $other_types = array_values(array_filter($leave_types, fn($r) => ($r['is_main'] ?? 1) == 0));
                        $row_tpl = function($lt) { ?>
                        <tr data-status="<?= $lt['is_active'] ? 'active' : 'inactive' ?>"
                            data-name="<?= htmlspecialchars(strtolower($lt['leave_type_name'])) ?>">
                            <td style="color:var(--muted);font-size:.8rem;"><?= $lt['leave_type_id'] ?></td>
                            <td>
                                <div class="lt-name">
                                    <span class="lt-color-dot"></span>
                                    <strong><?= htmlspecialchars($lt['leave_type_name']) ?></strong>
                                </div>
                            </td>
                            <td style="max-width:250px;white-space:normal;color:#555;">
                                <?= htmlspecialchars($lt['description'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!is_null($lt['max_days'])): ?>
                                    <span class="max-days-badge"><?= number_format($lt['max_days'], 1) ?> days</span>
                                <?php else: ?>
                                    <span class="max-days-unlimited">Unlimited</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $dc = $lt['default_credits'] ?? null; ?>
                                <?php if (!is_null($dc)): ?>
                                    <span class="default-credits-badge"><?= number_format((float)$dc, 3) ?> days</span>
                                <?php else: ?>
                                    <span class="max-days-unlimited">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($lt['is_main'] ?? 1) == 1): ?>
                                    <span class="badge-main">Main</span>
                                <?php else: ?>
                                    <span class="badge-other">Others</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lt['is_active']): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <button class="tbl-btn tbl-btn-edit"
                                        onclick="openEditModal(<?= $lt['leave_type_id'] ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="tbl-btn tbl-btn-toggle <?= $lt['is_active'] ? 'deact' : '' ?>"
                                        onclick="toggleActive(<?= $lt['leave_type_id'] ?>, this)"
                                        title="<?= $lt['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas fa-<?= $lt['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                        <?= $lt['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                    <button class="tbl-btn tbl-btn-delete"
                                        onclick="deleteType(<?= $lt['leave_type_id'] ?>, '<?= htmlspecialchars(addslashes($lt['leave_type_name'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php }; ?>

                        <!-- ── GROUP HEADER: Main Leave Types ── -->
                        <tr class="lt-group-header" data-group="main">
                            <td colspan="8">
                                <div class="lt-group-label">
                                    <i class="fas fa-star"></i> Main Leave Types
                                    <span class="lt-group-count"><?= count($main_types) ?> type(s)</span>
                                </div>
                            </td>
                        </tr>
                        <?php if (empty($main_types)): ?>
                        <tr data-group="main"><td colspan="8" style="padding:12px 16px;color:var(--muted);font-style:italic;font-size:.85rem;">No main leave types defined.</td></tr>
                        <?php else: foreach ($main_types as $lt) $row_tpl($lt); endif; ?>

                        <!-- ── GROUP HEADER: Other Leave Types ── -->
                        <tr class="lt-group-header lt-group-others" data-group="others">
                            <td colspan="8">
                                <div class="lt-group-label">
                                    <i class="fas fa-ellipsis-h"></i> Other Leave Types
                                    <span class="lt-group-count"><?= count($other_types) ?> type(s)</span>
                                </div>
                            </td>
                        </tr>
                        <?php if (empty($other_types)): ?>
                        <tr data-group="others"><td colspan="8" style="padding:12px 16px;color:var(--muted);font-style:italic;font-size:.85rem;">No other leave types defined.</td></tr>
                        <?php else: foreach ($other_types as $lt) $row_tpl($lt); endif; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    </section>
</div><!-- /.content-wrapper -->

</div><!-- /.wrapper -->

<!-- ══════════════════════════════════════
     CREATE / EDIT MODAL
══════════════════════════════════════ -->
<div class="lt-modal-overlay" id="ltModal">
    <div class="lt-modal">
        <div class="lt-modal-header">
            <i class="fas fa-tags"></i>
            <h5 id="modalTitle">Add Leave Type</h5>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="lt-modal-body">
            <input type="hidden" id="editId" value="">

            <div class="lt-form-group">
                <label>Leave Type Name <span class="required">*</span></label>
                <input type="text" id="ltName" placeholder="e.g. Vacation Leave" maxlength="100">
            </div>

            <div class="lt-form-group">
                <label>Description</label>
                <textarea id="ltDesc" placeholder="Brief description of this leave type…"></textarea>
            </div>

            <div class="lt-form-row">
                <div class="lt-form-group">
                    <label>Max Allowable Days / Year</label>
                    <input type="number" id="ltMaxDays" placeholder="e.g. 15" min="0" step="0.5">
                    <div class="lt-helper">Leave blank for unlimited.</div>
                </div>
                <div class="lt-form-group">
                    <label>Default Credits to Seed</label>
                    <input type="number" id="ltDefaultCredits" placeholder="e.g. 15.000" min="0" step="0.001">
                    <div class="lt-helper">Used by "Init All Defaults". Leave blank = 0.</div>
                </div>
            </div>

            <div class="lt-form-group">
                <label>Status</label>
                <div class="lt-switch-group">
                    <label class="lt-switch">
                        <input type="checkbox" id="ltIsActive" checked>
                        <span class="lt-switch-slider"></span>
                    </label>
                    <span class="lt-switch-label" id="ltStatusLabel">Active</span>
                </div>
            </div>

            <div class="lt-form-group">
                <label>Category</label>
                <div class="lt-switch-group">
                    <label class="lt-switch">
                        <input type="checkbox" id="ltIsMain" checked>
                        <span class="lt-switch-slider"></span>
                    </label>
                    <span class="lt-switch-label" id="ltMainLabel">Main Leave Type</span>
                    <span class="lt-helper" style="margin-left:8px;">Uncheck for "Others" (e.g. Terminal, Wellness)</span>
                </div>
            </div>
        </div>
        <div class="lt-modal-footer">
            <button class="btn-lt btn-lt-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn-lt btn-lt-primary" id="btnSave" onclick="saveLeaveType()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="ltToast"></div>

<!-- Scripts -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>

<script>
// ══════════════════════════════════════
// Modal helpers
// ══════════════════════════════════════
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Leave Type';
    document.getElementById('editId').value = '';
    document.getElementById('ltName').value = '';
    document.getElementById('ltDesc').value = '';
    document.getElementById('ltMaxDays').value = '';
    document.getElementById('ltDefaultCredits').value = '';
    document.getElementById('ltIsActive').checked = true;
    document.getElementById('ltIsMain').checked = true;
    updateStatusLabel();
    updateMainLabel();
    document.getElementById('ltModal').classList.add('open');
    setTimeout(() => document.getElementById('ltName').focus(), 100);
}

function openEditModal(id) {
    document.getElementById('modalTitle').textContent = 'Edit Leave Type';
    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save"></i> Update';

    $.post('types_leaves.php', {action:'get', leave_type_id: id}, function(res) {
        if (!res.success) { showToast(res.message || 'Could not load data.', 'error'); return; }
        const d = res.data;
        document.getElementById('editId').value            = d.leave_type_id;
        document.getElementById('ltName').value            = d.leave_type_name;
        document.getElementById('ltDesc').value            = d.description || '';
        document.getElementById('ltMaxDays').value         = d.max_days !== null ? d.max_days : '';
        document.getElementById('ltDefaultCredits').value  = d.default_credits !== null ? d.default_credits : '';
        document.getElementById('ltIsActive').checked      = d.is_active == 1;
        document.getElementById('ltIsMain').checked        = (d.is_main === undefined || d.is_main == 1);
        updateStatusLabel();
        updateMainLabel();
        document.getElementById('ltModal').classList.add('open');
    }, 'json').fail(function() { showToast('Network error.', 'error'); });
}

function closeModal() {
    document.getElementById('ltModal').classList.remove('open');
    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save"></i> Save';
}

// Close on backdrop click
document.getElementById('ltModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Toggle label
document.getElementById('ltIsActive').addEventListener('change', updateStatusLabel);
function updateStatusLabel() {
    document.getElementById('ltStatusLabel').textContent =
        document.getElementById('ltIsActive').checked ? 'Active' : 'Inactive';
}

document.getElementById('ltIsMain').addEventListener('change', updateMainLabel);
function updateMainLabel() {
    document.getElementById('ltMainLabel').textContent =
        document.getElementById('ltIsMain').checked ? 'Main Leave Type' : 'Other Leave Type';
}

// ══════════════════════════════════════
// SAVE (Create / Update)
// ══════════════════════════════════════
function saveLeaveType() {
    const id       = document.getElementById('editId').value;
    const name     = document.getElementById('ltName').value.trim();
    const desc     = document.getElementById('ltDesc').value.trim();
    const maxDays  = document.getElementById('ltMaxDays').value.trim();
    const defCred  = document.getElementById('ltDefaultCredits').value.trim();
    const isActive = document.getElementById('ltIsActive').checked ? 1 : 0;
    const isMain   = document.getElementById('ltIsMain').checked   ? 1 : 0;

    if (!name) { showToast('Leave type name is required.', 'error'); document.getElementById('ltName').focus(); return; }

    const action = id ? 'update' : 'create';
    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    $.post('types_leaves.php', {
        action: action,
        leave_type_id:   id,
        leave_type_name: name,
        description:     desc,
        max_days:        maxDays,
        default_credits: defCred,
        is_active:       isActive,
        is_main:         isMain
    }, function(res) {
        btn.disabled = false;
        btn.innerHTML = id ? '<i class="fas fa-save"></i> Update' : '<i class="fas fa-save"></i> Save';
        if (res.success) {
            closeModal();
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(res.message || 'Save failed.', 'error');
        }
    }, 'json').fail(function() {
        btn.disabled = false;
        btn.innerHTML = id ? '<i class="fas fa-save"></i> Update' : '<i class="fas fa-save"></i> Save';
        showToast('Network error.', 'error');
    });
}

// ══════════════════════════════════════
// TOGGLE ACTIVE
// ══════════════════════════════════════
function toggleActive(id, btn) {
    const isCurrentlyActive = btn.classList.contains('deact');
    const action = isCurrentlyActive ? 'Deactivate' : 'Activate';

    Swal.fire({
        title: action + ' this leave type?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: isCurrentlyActive ? '#c92a2a' : '#2a9863',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, ' + action,
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('types_leaves.php', {action:'toggle_active', leave_type_id: id}, function(res) {
            if (res.success) {
                showToast((res.is_active ? 'Activated' : 'Deactivated') + ' successfully.', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Toggle failed.', 'error');
            }
        }, 'json');
    });
}

// ══════════════════════════════════════
// DELETE
// ══════════════════════════════════════
function deleteType(id, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        html: '<p style="font-size:.88rem;color:#495057;">This will <strong>permanently remove</strong> this leave type.<br>If it has existing leave requests or balances, deletion will be blocked — deactivate it instead.</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c92a2a',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Delete',
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('types_leaves.php', {action:'delete', leave_type_id: id}, function(res) {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                Swal.fire({icon:'error', title:'Cannot Delete', text: res.message, confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });
}

// ══════════════════════════════════════
// BULK INIT DEFAULTS
// ══════════════════════════════════════
document.getElementById('btnBulkInit').addEventListener('click', function() {
    const year = document.getElementById('bulkInitYear').value;
    Swal.fire({
        title: 'Initialize Defaults for ' + year + '?',
        html: '<p style="font-size:.88rem;">This will seed <strong>leave_balance</strong> records for <strong>all non-Job-Order employees</strong> using each leave type\'s <em>Default Credits</em> value.<br><br>Existing balance records will <strong>not</strong> be overwritten.</p>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2a9863',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-magic"></i> Yes, Initialize',
    }).then(function(r) {
        if (!r.isConfirmed) return;
        const btn = document.getElementById('btnBulkInit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing…';

        $.post('types_leaves.php', {action:'bulk_init_defaults', year: year}, function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-seedling"></i> Init All Defaults';
            if (res.success) {
                Swal.fire({icon:'success', title:'Done!', text: res.message, confirmButtonColor:'#2a9863'});
            } else {
                showToast(res.message || 'Bulk init failed.', 'error');
            }
        }, 'json').fail(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-seedling"></i> Init All Defaults';
            showToast('Network error.', 'error');
        });
    });
});

// ══════════════════════════════════════
// SEARCH + FILTER
// ══════════════════════════════════════
var currentFilter = 'all';

document.getElementById('ltSearch').addEventListener('input', applyFilters);

function filterStatus(status) {
    currentFilter = status;
    applyFilters();
}

function applyFilters() {
    const q = document.getElementById('ltSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#ltTableBody tr[data-status]');
    let visible = 0;

    rows.forEach(function(row) {
        const nameMatch = row.dataset.name.includes(q);
        const statusMatch = currentFilter === 'all' || row.dataset.status === currentFilter;
        if (nameMatch && statusMatch) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('tableCount').textContent = 'Showing ' + visible + ' record(s)';
}

// ══════════════════════════════════════
// TOAST
// ══════════════════════════════════════
function showToast(msg, type) {
    const t = document.getElementById('ltToast');
    t.textContent = msg;
    t.className = 'lt-toast show ' + (type || 'success');
    clearTimeout(window._ltToastTimer);
    window._ltToastTimer = setTimeout(function() {
        t.className = 'lt-toast';
    }, 3200);
}

// Keyboard: Escape closes modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'Enter' && document.getElementById('ltModal').classList.contains('open')) {
        saveLeaveType();
    }
});
</script>
</body>
</html>