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

$total    = count($leave_types);
$active   = count(array_filter($leave_types, fn($r) => $r['is_active']));
$inactive = $total - $active;
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
<link rel="stylesheet" href="../css/module-theme.css">
<!-- jQuery must load in <head>: mainheader.php / sidebar.php run inline scripts that use $ before the page footer -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- toastr: the included header/sidebar call toastr on document-ready -->
<link rel="stylesheet" href="../plugins/toastr/toastr.min.css">
<script src="../plugins/toastr/toastr.min.js"></script>
<script>
// Fallback so the header/sidebar never crash if the toastr plugin file is missing
window.toastr = window.toastr || {
    success: function(m){ console.log('toastr.success:', m); },
    error:   function(m){ console.error('toastr.error:', m); },
    info:    function(m){ console.info('toastr.info:', m); },
    warning: function(m){ console.warn('toastr.warning:', m); },
    options: {}
};
</script>

<style>
/* Page-specific additions on top of assets/css/module-theme.css */

/* card header: title left, tools right */
.lt-card-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
.lt-card-header::after { display:none; }
.lt-tools { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
.lt-search {
    width:220px; background:var(--h-card-alt); border:1.5px solid var(--h-border); color:var(--h-text);
    border-radius:8px; padding:5px 12px; font-size:.82rem; outline:none;
}
.lt-search::placeholder { color:var(--h-muted); }
.lt-search:focus { border-color:var(--h-primary); box-shadow:0 0 0 3px var(--h-primary-dim); }
.lt-count { font-size:.78rem; color:var(--h-muted); }

/* extra badge colours (same palette as the applicant status badges) */
.badge-info    { background:#dbeafe !important; color:#1d4ed8 !important; }
.badge-warning { background:#fff3cd !important; color:#9a6700 !important; }
.badge-danger  { background:#fee2e2 !important; color:#b91c1c !important; }
body.dark-mode .badge-info    { background:rgba(96,165,250,.15) !important; color:#93c5fd !important; }
body.dark-mode .badge-warning { background:rgba(255,212,59,.15) !important; color:#ffd43b !important; }
body.dark-mode .badge-danger  { background:rgba(255,107,107,.15) !important; color:#ff8787 !important; }

/* name + chips */
.lt-name { display:flex; align-items:center; gap:8px; font-weight:700; }
.lt-color-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; background:var(--h-primary); }
.lt-id { color:var(--h-muted); font-size:.8rem; }
.lt-desc { max-width:250px; white-space:normal; color:var(--h-muted); }
.lt-pill { display:inline-block; background:var(--h-primary-dim); color:var(--h-primary); border-radius:6px; padding:2px 10px; font-size:.78rem; font-weight:700; }
.lt-unlimited { color:var(--h-muted); font-style:italic; font-size:.82rem; }

/* group header rows */
.mod-table.table tbody tr.lt-group-header td { padding:0 !important; border-top:none !important; }
.mod-table.table tbody tr.lt-group-header:hover { background:transparent !important; }
.lt-group-label {
    display:flex; align-items:center; gap:8px; padding:9px 14px;
    background:var(--h-card-alt); border-top:2px solid var(--h-primary); border-bottom:1px solid var(--h-border);
    font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--h-primary);
}
.lt-group-others .lt-group-label { border-top-color:var(--h-warning); color:var(--h-warning); }
.lt-group-count { margin-left:auto; background:var(--h-primary-dim); border-radius:10px; padding:1px 9px; font-size:.7rem; font-weight:600; color:inherit; }
.lt-empty { text-align:center; padding:50px 20px; color:var(--h-muted); }
.lt-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }

/* bulk init banner */
.lt-bulk {
    background:var(--h-card); border:1px solid var(--h-border); border-radius:12px; box-shadow:var(--h-shadow-sm);
    padding:14px 18px; margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;
}
.lt-bulk-icon {
    width:42px; height:42px; border-radius:10px; flex-shrink:0; background:var(--h-primary); color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:1.05rem;
}
body.dark-mode .lt-bulk-icon { color:#0b1f17; }
.lt-bulk h6 { margin:0 0 2px; font-size:.88rem; font-weight:700; color:var(--h-text); }
.lt-bulk p  { margin:0; font-size:.78rem; color:var(--h-muted); }
.lt-bulk-actions { margin-left:auto; display:flex; gap:8px; align-items:center; }
.lt-bulk select {
    background:var(--h-card-alt); border:1.5px solid var(--h-border); color:var(--h-text);
    border-radius:8px; padding:5px 10px; font-size:.82rem; outline:none;
}
.lt-bulk .btn-primary {
    background:var(--h-primary) !important; border:none !important; border-radius:8px !important;
    font-weight:700 !important; font-size:.8rem !important; box-shadow:0 2px 12px var(--h-primary-dim) !important; color:#fff !important;
}
body.dark-mode .lt-bulk .btn-primary { color:#0b1f17 !important; }

/* modal: overlay keeps working without Bootstrap's modal JS, but uses the same scrum-style look */
.lt-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060;
    align-items:center; justify-content:center; padding:16px; overflow-y:auto;
}
.lt-modal-overlay.open { display:flex; }
.lt-dialog { width:100%; max-width:560px; margin:auto; }
.lt-dialog .modal-content { position:relative; display:flex; flex-direction:column; width:100%; }
.lt-row { display:flex; gap:14px; }
.lt-row > .form-group { flex:1; }
.lt-helper { font-size:.75rem; color:var(--h-muted); margin-top:4px; }
.lt-switch-group {
    display:flex; align-items:center; gap:12px; padding:8px 12px; flex-wrap:wrap;
    background:var(--h-card-alt); border:1.5px solid var(--h-border); border-radius:8px;
}
.scrum-style-modal .lt-switch { margin:0 !important; position:relative; display:inline-block; width:42px; height:24px; text-transform:none; }
.lt-switch input { opacity:0; width:0; height:0; }
.lt-switch-slider { position:absolute; inset:0; background:#adb5bd; border-radius:24px; cursor:pointer; transition:.25s; }
.lt-switch-slider::before {
    content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 4px rgba(0,0,0,.2);
}
.lt-switch input:checked + .lt-switch-slider { background:var(--h-primary); }
.lt-switch input:checked + .lt-switch-slider::before { transform:translateX(18px); }
.lt-switch-label { font-size:.85rem; font-weight:600; color:var(--h-text); }

@media (max-width:640px) {
    .lt-row { flex-direction:column; gap:0; }
    .lt-search { width:100%; }
    .lt-bulk-actions { margin-left:0; }
}
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">

    <!-- Page Hero -->
    <div class="pg-hero">
        <div class="pg-hero-mesh"></div>
        <div class="pg-hero-dots"></div>
        <div class="pg-hero-hex"></div>
        <div class="pg-hero-orbs">
            <div class="pg-orb pg-orb-1"></div>
            <div class="pg-orb pg-orb-2"></div>
            <div class="pg-orb pg-orb-3"></div>
            <div class="pg-orb pg-orb-4"></div>
        </div>
        <div class="pg-hero-rings">
            <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
        </div>
        <div class="pg-hero-arc"></div>
        <div class="pg-hero-layout">
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-tags"></i>Leave Types Management</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Add, edit, and manage leave types. Changes reflect immediately across the system.</p>
            </div>
        </div>
    </div>

    <div class="content">
    <div class="container-fluid">

        <!-- Bulk Init -->
        <div class="lt-bulk">
            <div class="lt-bulk-icon"><i class="fas fa-magic"></i></div>
            <div>
                <h6>Initialize Default Leave Balances</h6>
                <p>Seeds leave_balance records for all employees using each type's Default Credits value below.</p>
            </div>
            <div class="lt-bulk-actions">
                <select id="bulkInitYear">
                    <?php for ($y = $current_year + 1; $y >= $current_year - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $current_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-primary btn-sm" id="btnBulkInit">
                    <i class="fas fa-seedling"></i> Init All Defaults
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header lt-card-header">
                <h3 class="card-title">Leave Types <span class="lt-count ml-2" id="tableCount">Showing <?= $total ?> record(s)</span></h3>
                <div class="lt-tools">
                    <input type="text" id="ltSearch" class="lt-search" placeholder="Search leave types…">
                    <div class="mod-chips">
                        <button type="button" class="mod-chip active" data-filter="all"      onclick="filterStatus('all', this)">All<span class="n"><?= $total ?></span></button>
                        <button type="button" class="mod-chip"        data-filter="active"   onclick="filterStatus('active', this)">Active<span class="n"><?= $active ?></span></button>
                        <button type="button" class="mod-chip"        data-filter="inactive" onclick="filterStatus('inactive', this)">Inactive<span class="n"><?= $inactive ?></span></button>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i> Add Leave Type
                    </button>
                </div>
            </div>
            <div class="card-body" style="overflow-x:auto;">
                <table class="table mod-table" id="ltTable">
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
                            <td class="lt-id"><?= $lt['leave_type_id'] ?></td>
                            <td>
                                <div class="lt-name">
                                    <span class="lt-color-dot"></span>
                                    <?= htmlspecialchars($lt['leave_type_name']) ?>
                                </div>
                            </td>
                            <td class="lt-desc">
                                <?= htmlspecialchars($lt['description'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!is_null($lt['max_days'])): ?>
                                    <span class="lt-pill"><?= number_format($lt['max_days'], 1) ?> days</span>
                                <?php else: ?>
                                    <span class="lt-unlimited">Unlimited</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $dc = $lt['default_credits'] ?? null; ?>
                                <?php if (!is_null($dc)): ?>
                                    <span class="lt-pill"><?= number_format((float)$dc, 3) ?> days</span>
                                <?php else: ?>
                                    <span class="lt-unlimited">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($lt['is_main'] ?? 1) == 1): ?>
                                    <span class="badge badge-info">Main</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Others</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lt['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning" title="Edit"
                                    onclick="openEditModal(<?= $lt['leave_type_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-info <?= $lt['is_active'] ? 'deact' : '' ?>"
                                    onclick="toggleActive(<?= $lt['leave_type_id'] ?>, this)"
                                    title="<?= $lt['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fas fa-<?= $lt['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" title="Delete"
                                    onclick="deleteType(<?= $lt['leave_type_id'] ?>, '<?= htmlspecialchars(addslashes($lt['leave_type_name'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                        <tr data-group="main"><td colspan="8" class="lt-unlimited" style="padding:12px 16px;">No main leave types defined.</td></tr>
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
                        <tr data-group="others"><td colspan="8" class="lt-unlimited" style="padding:12px 16px;">No other leave types defined.</td></tr>
                        <?php else: foreach ($other_types as $lt) $row_tpl($lt); endif; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    </div>
</div><!-- /.content-wrapper -->

</div><!-- /.wrapper -->

<!-- ══════════════════════════════════════
     CREATE / EDIT MODAL
══════════════════════════════════════ -->
<div class="lt-modal-overlay scrum-style-modal" id="ltModal">
    <div class="lt-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tags"></i><span id="modalTitle">Add Leave Type</span></h5>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <div class="form-group">
                    <label>Leave Type Name <span class="text-danger">*</span></label>
                    <input type="text" id="ltName" class="form-control" placeholder="e.g. Vacation Leave" maxlength="100">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="ltDesc" class="form-control" rows="3" placeholder="Brief description of this leave type…"></textarea>
                </div>

                <div class="lt-row">
                    <div class="form-group">
                        <label>Max Allowable Days / Year</label>
                        <input type="number" id="ltMaxDays" class="form-control" placeholder="e.g. 15" min="0" step="0.5">
                        <div class="lt-helper">Leave blank for unlimited.</div>
                    </div>
                    <div class="form-group">
                        <label>Default Credits to Seed</label>
                        <input type="number" id="ltDefaultCredits" class="form-control" placeholder="e.g. 15.000" min="0" step="0.001">
                        <div class="lt-helper">Used by "Init All Defaults". Blank = 0.</div>
                    </div>
                </div>

                <div class="lt-row">
                    <div class="form-group mb-0">
                        <label>Status</label>
                        <div class="lt-switch-group">
                            <label class="lt-switch">
                                <input type="checkbox" id="ltIsActive" checked>
                                <span class="lt-switch-slider"></span>
                            </label>
                            <span class="lt-switch-label" id="ltStatusLabel">Active</span>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Category</label>
                        <div class="lt-switch-group">
                            <label class="lt-switch">
                                <input type="checkbox" id="ltIsMain" checked>
                                <span class="lt-switch-slider"></span>
                            </label>
                            <span class="lt-switch-label" id="ltMainLabel">Main Leave Type</span>
                        </div>
                        <div class="lt-helper">Turn off for "Others" (e.g. Terminal, Wellness).</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSave" onclick="saveLeaveType()">
                    <i class="fas fa-save mr-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>

<script>
// ══════════════════════════════════════
// Modal helpers
// ══════════════════════════════════════
var SAVE_HTML = '<i class="fas fa-save mr-1"></i>Save';
var UPDATE_HTML = '<i class="fas fa-save mr-1"></i>Update';

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Leave Type';
    document.getElementById('btnSave').innerHTML = SAVE_HTML;
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
    document.getElementById('btnSave').innerHTML = UPDATE_HTML;

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
    document.getElementById('btnSave').innerHTML = SAVE_HTML;
}

// Close on backdrop click
document.getElementById('ltModal').addEventListener('mousedown', function(e) {
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

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
        btn.innerHTML = id ? UPDATE_HTML : SAVE_HTML;
        if (res.success) {
            closeModal();
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(res.message || 'Save failed.', 'error');
        }
    }, 'json').fail(function() {
        btn.disabled = false;
        btn.innerHTML = id ? UPDATE_HTML : SAVE_HTML;
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
        html: '<p style="font-size:.88rem;">This will <strong>permanently remove</strong> this leave type.<br>If it has existing leave requests or balances, deletion will be blocked — deactivate it instead.</p>',
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

function filterStatus(status, el) {
    currentFilter = status;
    document.querySelectorAll('.mod-chip').forEach(function(c) { c.classList.remove('active'); });
    if (el) el.classList.add('active');
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
// TOAST (SweetAlert toast, same as the other admin pages)
// ══════════════════════════════════════
function showToast(msg, type) {
    Swal.fire({
        toast: true, position: 'bottom-end', icon: type || 'success', title: msg,
        showConfirmButton: false, timer: 3000, timerProgressBar: true
    });
}

// Keyboard: Escape closes modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'Enter'
        && document.getElementById('ltModal').classList.contains('open')
        && e.target.tagName !== 'TEXTAREA'
        && !document.querySelector('.swal2-container')) {
        saveLeaveType();
    }
});
</script>
</body>
</html>