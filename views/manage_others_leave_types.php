<?php
/**
 * manage_others_leave_types.php
 *
 * CRUD management for "Others" leave sub-types.
 * - Add / Edit / Delete sub-types (stored in `others_leave_type` table)
 * - Set default_credits per sub-type
 * - Balances are stored in the standard `leave_balance` table using
 *   the `others_leave_type_id` foreign key via a bridge leave_type row
 *
 * Authorised roles: Administrator (1), Manager (2), Focal Person (13), Unit Head (14)
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

$user_role_id = intval($_SESSION['role_id'] ?? 0);
$user_emp_id  = intval($_SESSION['emp_id']  ?? 0);
$can_edit     = in_array($user_role_id, [1, 2, 13, 14]);

if (!$can_edit) {
    die('<p style="font-family:Arial;padding:30px;color:#c92a2a">Access denied.</p>');
}


// ── AJAX / POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // LIST
    if ($action === 'list') {
        $rows = [];
        $r = $db->query("SELECT * FROM others_leave_type ORDER BY name");
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // GET single
    if ($action === 'get') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM others_leave_type WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }

    // CREATE
    if ($action === 'create') {
        $name    = trim($_POST['name']    ?? '');
        $desc    = trim($_POST['desc']    ?? '');
        $credits = round((float)($_POST['credits'] ?? 0), 3);
        $active  = intval($_POST['active'] ?? 1);

        if (!$name) { echo json_encode(['success'=>false,'message'=>'Name is required.']); exit; }

        // Check duplicate name
        $dup = $db->prepare("SELECT id FROM others_leave_type WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $dup->bind_param('s', $name);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            echo json_encode(['success'=>false,'message'=>'A leave type with this name already exists.']); exit;
        }

        $stmt = $db->prepare("INSERT INTO others_leave_type (name, description, default_credits, is_active) VALUES (?,?,?,?)");
        $stmt->bind_param('ssdi', $name, $desc, $credits, $active);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'id'=>$db->insert_id]);
        } else {
            echo json_encode(['success'=>false,'message'=>'DB error: '.$db->error]);
        }
        exit;
    }

    // UPDATE
    if ($action === 'update') {
        $id      = intval($_POST['id']   ?? 0);
        $name    = trim($_POST['name']   ?? '');
        $desc    = trim($_POST['desc']   ?? '');
        $credits = round((float)($_POST['credits'] ?? 0), 3);
        $active  = intval($_POST['active'] ?? 1);

        if (!$id || !$name) { echo json_encode(['success'=>false,'message'=>'Missing fields.']); exit; }

        // Check duplicate (exclude self)
        $dup = $db->prepare("SELECT id FROM others_leave_type WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1");
        $dup->bind_param('si', $name, $id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            echo json_encode(['success'=>false,'message'=>'Another leave type with this name already exists.']); exit;
        }

        $stmt = $db->prepare("UPDATE others_leave_type SET name=?, description=?, default_credits=?, is_active=? WHERE id=?");
        $stmt->bind_param('ssdii', $name, $desc, $credits, $active, $id);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'DB error: '.$db->error]);
        }
        exit;
    }

    // DELETE
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }

        $stmt = $db->prepare("DELETE FROM others_leave_type WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'DB error: '.$db->error]);
        }
        exit;
    }

    // TOGGLE ACTIVE
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE others_leave_type SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $db->query("SELECT is_active FROM others_leave_type WHERE id=$id")->fetch_assoc();
        echo json_encode(['success'=>true,'is_active'=>(int)$row['is_active']]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

// Fetch all rows for initial render
$all_types = [];
$r = $db->query("SELECT * FROM others_leave_type ORDER BY name");
while ($row = $r->fetch_assoc()) $all_types[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Others Leave Types | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --primary: #1a6b3c;
            --primary-d: #145530;
            --primary-light: #d4edda;
            --danger: #c92a2a;
            --warning: #e67700;
            --text: #212529;
            --muted: #6c757d;
            --border: #dee2e6;
            --bg: #f4f6fb;
            --card: #fff;
            --radius: 14px;
        }

        .olt-page { background: var(--bg); min-height: 100vh; }

        /* Hero */
        .olt-hero {
            background: linear-gradient(135deg, #145530 0%, #1a6b3c 55%, #28a745 100%);
            padding: 34px 32px 30px;
            position: relative; overflow: hidden;
        }
        .olt-hero::after {
            content:''; position:absolute; inset:0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .olt-hero-inner { position:relative; z-index:1; }
        .olt-hero h1 { color:#fff; font-size:1.55rem; font-weight:700; margin:0 0 4px; }
        .olt-hero p  { color:rgba(255,255,255,.78); margin:0; font-size:.88rem; }

        /* Content */
        .olt-content { padding: 26px 28px; }

        /* Card */
        .olt-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(60,72,100,.09), 0 1px 4px rgba(60,72,100,.06);
            overflow: hidden;
        }
        .olt-card-head {
            display:flex; align-items:center; justify-content:space-between;
            padding: 18px 22px;
            border-bottom: 1.5px solid #f1f3f5;
        }
        .olt-card-head-left { display:flex; align-items:center; gap:10px; }
        .olt-card-icon {
            width:34px; height:34px; border-radius:8px;
            background:#d4edda; color:var(--primary);
            display:flex; align-items:center; justify-content:center;
            font-size:.88rem; flex-shrink:0;
        }
        .olt-card-head h5 { margin:0; font-size:.97rem; font-weight:700; color:var(--text); }

        /* Buttons */
        .btn-add {
            display:inline-flex; align-items:center; gap:6px;
            background: linear-gradient(135deg, var(--primary), #28a745);
            color:#fff; border:none; border-radius:9px;
            padding:9px 18px; font-size:.85rem; font-weight:700;
            cursor:pointer; transition:transform .15s, box-shadow .15s;
            box-shadow: 0 3px 10px rgba(26,107,60,.3);
        }
        .btn-add:hover { transform:translateY(-1px); box-shadow:0 5px 16px rgba(26,107,60,.4); }

        /* Table */
        .olt-table { width:100%; border-collapse:collapse; font-size:.855rem; }
        .olt-table thead th {
            background:#f8f9fa; color:var(--muted);
            font-size:.72rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.06em;
            padding:12px 16px; border-bottom:1.5px solid var(--border);
            white-space:nowrap;
        }
        .olt-table tbody td {
            padding:13px 16px; border-bottom:1px solid #f1f3f5;
            color:var(--text); vertical-align:middle;
        }
        .olt-table tbody tr:last-child td { border-bottom:none; }
        .olt-table tbody tr:hover td { background:#f8f9fa; }

        /* Status badge */
        .badge-active   { display:inline-block; background:#d4edda; color:#145530; border-radius:20px; padding:3px 12px; font-size:.73rem; font-weight:700; }
        .badge-inactive { display:inline-block; background:#f1f3f5; color:var(--muted); border-radius:20px; padding:3px 12px; font-size:.73rem; font-weight:700; }

        /* Credits pill */
        .credits-pill { background:#e0f2fe; color:#0c4a6e; border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:700; }

        /* Action buttons */
        .btn-edit, .btn-toggle, .btn-del {
            display:inline-flex; align-items:center; gap:4px;
            border-radius:7px; padding:5px 11px;
            font-size:.75rem; font-weight:600; cursor:pointer;
            border:1.5px solid; transition:background .12s;
        }
        .btn-edit   { background:#eff6ff; color:#3b82f6; border-color:#bfdbfe; }
        .btn-edit:hover { background:#3b82f6; color:#fff; }
        .btn-toggle { background:#fffbeb; color:#92400e; border-color:#fde68a; }
        .btn-toggle:hover { background:#f59e0b; color:#fff; border-color:#f59e0b; }
        .btn-del    { background:#fff5f5; color:var(--danger); border-color:#fca5a5; }
        .btn-del:hover { background:var(--danger); color:#fff; border-color:var(--danger); }

        /* Empty state */
        .olt-empty { padding:48px 20px; text-align:center; color:var(--muted); }
        .olt-empty i { font-size:2.2rem; opacity:.4; display:block; margin-bottom:12px; }

        /* Description truncate */
        .desc-cell { max-width:280px; }
        .desc-text { overflow:hidden; white-space:nowrap; text-overflow:ellipsis; max-width:260px; display:inline-block; color:var(--muted); font-size:.82rem; }

        /* Info strip */
        .olt-info {
            background:#e8f5e9; border:1px solid #c8e6c9; border-radius:10px;
            padding:14px 18px; margin-bottom:22px; font-size:.85rem;
            color:#2e7d32; display:flex; gap:10px; align-items:flex-start;
        }
        .olt-info i { margin-top:2px; flex-shrink:0; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper olt-page">

        <div class="olt-hero">
            <div class="olt-hero-inner">
                <h1><i class="fas fa-list-alt mr-2" style="opacity:.85"></i>Manage Others Leave Types</h1>
                <p>Add, edit, or remove sub-types shown under "Others (please specify)" in the leave request form</p>
            </div>
        </div>

        <div class="olt-content">

            <div class="olt-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    These entries populate the <strong>"Others (please specify)"</strong> dropdown in the Leave Request form.
                    Each sub-type can carry its own <strong>default credits</strong> which HR can assign to employees via the
                    Leave Balance module. When a request is filed using one of these types, balance is checked against
                    the matching leave type record.
                </div>
            </div>

            <div class="olt-card">
                <div class="olt-card-head">
                    <div class="olt-card-head-left">
                        <div class="olt-card-icon"><i class="fas fa-tags"></i></div>
                        <h5>Others Leave Sub-Types <span id="countBadge" style="background:#e9ecef;color:var(--muted);border-radius:20px;padding:1px 10px;font-size:.75rem;font-weight:700;margin-left:6px;"><?= count($all_types) ?></span></h5>
                    </div>
                    <button class="btn-add" id="btnAdd">
                        <i class="fas fa-plus"></i> Add New Type
                    </button>
                </div>

                <div style="padding:0;">
                    <?php if (empty($all_types)): ?>
                    <div class="olt-empty">
                        <i class="fas fa-folder-open"></i>
                        <p>No leave types yet. Click <strong>Add New Type</strong> to create one.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="olt-table" id="oltTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Leave Sub-Type Name</th>
                                    <th>Description</th>
                                    <th>Default Credits</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="oltTbody">
                            <?php foreach ($all_types as $i => $t): ?>
                            <tr data-id="<?= $t['id'] ?>">
                                <td style="color:var(--muted);font-size:.8rem;"><?= $i+1 ?></td>
                                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                                <td class="desc-cell">
                                    <span class="desc-text" title="<?= htmlspecialchars($t['description'] ?? '') ?>">
                                        <?= $t['description'] ? htmlspecialchars($t['description']) : '<em style="opacity:.5">—</em>' ?>
                                    </span>
                                </td>
                                <td><span class="credits-pill"><?= number_format((float)$t['default_credits'], 3) ?> day(s)</span></td>
                                <td>
                                    <span class="<?= $t['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                        <button class="btn-edit" data-id="<?= $t['id'] ?>"><i class="fas fa-pen"></i> Edit</button>
                                        <button class="btn-toggle" data-id="<?= $t['id'] ?>" data-active="<?= $t['is_active'] ?>">
                                            <i class="fas fa-<?= $t['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                            <?= $t['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                        <button class="btn-del" data-id="<?= $t['id'] ?>"><i class="fas fa-trash"></i> Delete</button>
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

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- ══════ Add / Edit Modal ══════ -->
<div class="modal fade" id="oltModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;" role="document">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.18);">

            <div style="display:flex;align-items:center;gap:12px;padding:20px 24px 16px;border-bottom:1.5px solid #f1f3f5;">
                <div style="width:38px;height:38px;border-radius:9px;background:#d4edda;color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;">
                    <i class="fas fa-tag" id="modalIcon"></i>
                </div>
                <div>
                    <h5 style="margin:0;font-size:.97rem;font-weight:700;" id="modalTitle">Add Leave Sub-Type</h5>
                    <p style="margin:0;font-size:.78rem;color:var(--muted);">Fill in the details below</p>
                </div>
                <button type="button" class="close ml-auto" data-dismiss="modal" style="font-size:1.3rem;background:none;border:none;opacity:.4;">&times;</button>
            </div>

            <div style="padding:22px 24px;">
                <input type="hidden" id="oltId" value="">

                <div class="form-group">
                    <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;">
                        Sub-Type Name <span style="color:var(--danger)">*</span>
                    </label>
                    <input type="text" id="oltName" class="form-control"
                           placeholder="e.g. Wellness Leave"
                           style="border-radius:8px;border:1.5px solid var(--border);font-size:.88rem;">
                </div>

                <div class="form-group">
                    <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;">
                        Description / Policy Note
                    </label>
                    <textarea id="oltDesc" class="form-control" rows="3"
                              placeholder="Brief description or governing policy…"
                              style="border-radius:8px;border:1.5px solid var(--border);font-size:.88rem;resize:vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;">
                        Default Credits (days)
                    </label>
                    <input type="number" id="oltCredits" class="form-control" value="0" min="0" step="0.001"
                           style="border-radius:8px;border:1.5px solid var(--border);font-size:.88rem;">
                    <small style="color:var(--muted);font-size:.77rem;margin-top:4px;display:block;">
                        Used when HR bulk-initialises leave balances. Set 0 for case-by-case allocation.
                    </small>
                </div>

                <div class="form-group">
                    <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;">
                        Status
                    </label>
                    <select id="oltActive" class="form-control" style="border-radius:8px;border:1.5px solid var(--border);font-size:.88rem;">
                        <option value="1">Active (visible in form)</option>
                        <option value="0">Inactive (hidden from form)</option>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1.5px solid #f1f3f5;">
                <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius:8px;font-weight:600;font-size:.87rem;">Cancel</button>
                <button type="button" id="oltSave"
                        style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--primary),#28a745);color:#fff;border:none;border-radius:9px;padding:9px 22px;font-size:.87rem;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(26,107,60,.3);">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function () {

    /* ── helpers ── */
    function reindex() {
        $('#oltTbody tr').each(function(i){ $(this).find('td:first').text(i+1); });
        $('#countBadge').text($('#oltTbody tr').length);
    }

    function buildRow(t) {
        var activeLabel   = t.is_active == 1 ? 'Active'   : 'Inactive';
        var activeCls     = t.is_active == 1 ? 'badge-active' : 'badge-inactive';
        var toggleIcon    = t.is_active == 1 ? 'eye-slash' : 'eye';
        var toggleLabel   = t.is_active == 1 ? 'Disable'  : 'Enable';
        var descSafe      = $('<div>').text(t.description||'').html();
        var nameSafe      = $('<div>').text(t.name).html();
        return `<tr data-id="${t.id}">
            <td style="color:var(--muted);font-size:.8rem;"></td>
            <td><strong>${nameSafe}</strong></td>
            <td class="desc-cell"><span class="desc-text" title="${descSafe}">${descSafe||'<em style="opacity:.5">—</em>'}</span></td>
            <td><span class="credits-pill">${parseFloat(t.default_credits).toFixed(3)} day(s)</span></td>
            <td><span class="${activeCls}">${activeLabel}</span></td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <button class="btn-edit" data-id="${t.id}"><i class="fas fa-pen"></i> Edit</button>
                    <button class="btn-toggle" data-id="${t.id}" data-active="${t.is_active}"><i class="fas fa-${toggleIcon}"></i> ${toggleLabel}</button>
                    <button class="btn-del" data-id="${t.id}"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </td>
        </tr>`;
    }

    /* ── Open Add modal ── */
    $('#btnAdd').on('click', function () {
        $('#oltId').val('');
        $('#oltName').val('');
        $('#oltDesc').val('');
        $('#oltCredits').val('0');
        $('#oltActive').val('1');
        $('#modalTitle').text('Add Leave Sub-Type');
        $('#modalIcon').attr('class','fas fa-plus');
        $('#oltModal').modal('show');
        setTimeout(function(){ $('#oltName').focus(); }, 350);
    });

    /* ── Open Edit modal ── */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $.post('manage_others_leave_types.php', {ajax:1, action:'get', id:id}, function(res){
            if (!res.success || !res.data) { Swal.fire('Error','Could not load record.','error'); return; }
            var t = res.data;
            $('#oltId').val(t.id);
            $('#oltName').val(t.name);
            $('#oltDesc').val(t.description||'');
            $('#oltCredits').val(parseFloat(t.default_credits).toFixed(3));
            $('#oltActive').val(t.is_active);
            $('#modalTitle').text('Edit Leave Sub-Type');
            $('#modalIcon').attr('class','fas fa-pen');
            $('#oltModal').modal('show');
            setTimeout(function(){ $('#oltName').focus(); }, 350);
        }, 'json');
    });

    /* ── Save (Create / Update) ── */
    $('#oltSave').on('click', function () {
        var id      = $('#oltId').val();
        var name    = $('#oltName').val().trim();
        var desc    = $('#oltDesc').val().trim();
        var credits = $('#oltCredits').val();
        var active  = $('#oltActive').val();

        if (!name) {
            Swal.fire({icon:'warning', title:'Required', text:'Please enter a sub-type name.', confirmButtonColor:'#1a6b3c'});
            $('#oltName').focus();
            return;
        }

        var action = id ? 'update' : 'create';
        var payload = {ajax:1, action:action, name:name, desc:desc, credits:credits, active:active};
        if (id) payload.id = id;

        $('#oltSave').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');

        $.post('manage_others_leave_types.php', payload, function(res){
            $('#oltSave').prop('disabled',false).html('<i class="fas fa-save"></i> Save');
            if (!res.success) {
                Swal.fire({icon:'error', title:'Error', text:res.message||'Something went wrong.', confirmButtonColor:'#c92a2a'});
                return;
            }
            $('#oltModal').modal('hide');

            // Refresh table
            $.post('manage_others_leave_types.php', {ajax:1, action:'list'}, function(lr){
                if (!lr.success) return;
                if (lr.data.length === 0) {
                    $('#oltTbody').closest('.table-responsive').html(`
                        <div class="olt-empty">
                            <i class="fas fa-folder-open"></i>
                            <p>No leave types yet.</p>
                        </div>`);
                    return;
                }
                // Re-render tbody
                var html = '';
                lr.data.forEach(function(t){ html += buildRow(t); });
                if ($('#oltTbody').length) {
                    $('#oltTbody').html(html);
                } else {
                    // table didn't exist yet – reload page
                    location.reload();
                    return;
                }
                reindex();
            }, 'json');

            Swal.fire({icon:'success', title:'Saved!', text: id ? 'Leave type updated.' : 'Leave type created.', timer:2000, showConfirmButton:false, confirmButtonColor:'#1a6b3c'});
        }, 'json').fail(function(){
            $('#oltSave').prop('disabled',false).html('<i class="fas fa-save"></i> Save');
            Swal.fire({icon:'error', title:'Error', text:'Request failed. Please try again.', confirmButtonColor:'#c92a2a'});
        });
    });

    /* ── Toggle active ── */
    $(document).on('click', '.btn-toggle', function(){
        var id  = $(this).data('id');
        var cur = parseInt($(this).data('active'));
        var label = cur === 1 ? 'disable' : 'enable';
        var $row  = $(this).closest('tr');
        var $btn  = $(this);

        Swal.fire({
            title: (cur===1?'Disable':'Enable') + ' this leave type?',
            text: cur===1
                ? 'It will be hidden from the leave request form.'
                : 'It will become visible in the leave request form.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: cur===1 ? '#e67700' : '#1a6b3c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, ' + label + ' it'
        }).then(function(r){
            if (!r.isConfirmed) return;
            $.post('manage_others_leave_types.php', {ajax:1, action:'toggle', id:id}, function(res){
                if (!res.success) { Swal.fire('Error','Could not update.','error'); return; }
                var newActive = res.is_active;
                $btn.data('active', newActive);
                $btn.html('<i class="fas fa-'+(newActive?'eye-slash':'eye')+'"></i> '+(newActive?'Disable':'Enable'));
                var $badge = $row.find('td:nth-child(5) span');
                $badge.removeClass('badge-active badge-inactive')
                      .addClass(newActive?'badge-active':'badge-inactive')
                      .text(newActive?'Active':'Inactive');
                Swal.fire({icon:'success', title:newActive?'Enabled':'Disabled', timer:1500, showConfirmButton:false});
            }, 'json');
        });
    });

    /* ── Delete ── */
    $(document).on('click', '.btn-del', function(){
        var id   = $(this).data('id');
        var name = $(this).closest('tr').find('td:nth-child(2) strong').text();
        var $row = $(this).closest('tr');

        Swal.fire({
            title: 'Delete "' + name + '"?',
            html: '<p style="font-size:.88rem;color:#495057">This will permanently remove the sub-type.<br>Existing leave requests already filed will not be affected.</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete'
        }).then(function(r){
            if (!r.isConfirmed) return;
            $.post('manage_others_leave_types.php', {ajax:1, action:'delete', id:id}, function(res){
                if (!res.success) { Swal.fire('Error', res.message||'Could not delete.','error'); return; }
                $row.fadeOut(250, function(){
                    $(this).remove();
                    reindex();
                    if ($('#oltTbody tr').length === 0) {
                        $('#oltTbody').closest('.table-responsive').replaceWith(`
                            <div class="olt-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>No leave types. Click <strong>Add New Type</strong> to create one.</p>
                            </div>`);
                    }
                });
                Swal.fire({icon:'success', title:'Deleted', timer:1500, showConfirmButton:false});
            }, 'json');
        });
    });

    /* ── Enter key in modal ── */
    $('#oltModal').on('keydown', function(e){
        if (e.key === 'Enter' && !$(e.target).is('textarea')) {
            $('#oltSave').trigger('click');
        }
    });

});
</script>
</body>
</html>