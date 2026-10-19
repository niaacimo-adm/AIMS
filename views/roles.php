<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Check permissions
checkPermission('manage_roles');

if (empty($_SESSION['csrf_roles'])) {
    $_SESSION['csrf_roles'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_roles'];

function rl_reply(bool $ok, string $msg = '', array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

function rl_plural(int $n, string $word): string {
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/* ═══════════════ AJAX actions (POST) ═══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        rl_reply(false, 'Session expired. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        /* Create / update role details */
        if ($action === 'save') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($name === '' || $description === '') rl_reply(false, 'Name and description are required.');
            if (mb_strlen($name) > 100)               rl_reply(false, 'Name must be 100 characters or fewer.');
            if (mb_strlen($description) > 500)        rl_reply(false, 'Description must be 500 characters or fewer.');

            // Unique name (ignore the row being edited)
            $stmt = $db->prepare("SELECT id FROM user_roles WHERE name = ? AND id <> ? LIMIT 1");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                rl_reply(false, 'A role with that name already exists.');
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE user_roles SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param('ssi', $name, $description, $id);
            } else {
                $stmt = $db->prepare("INSERT INTO user_roles (name, description) VALUES (?, ?)");
                $stmt->bind_param('ss', $name, $description);
            }
            $stmt->execute();
            rl_reply(true, $id > 0 ? 'Role updated.' : 'Role created.');
        }

        if ($id <= 0) rl_reply(false, 'Invalid role.');

        /* Replace the permission set of a role */
        if ($action === 'save_permissions') {
            $stmt = $db->prepare("SELECT id FROM user_roles WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) rl_reply(false, 'Role not found.');

            $raw  = $_POST['permissions'] ?? [];
            $perm = is_array($raw) ? array_values(array_unique(array_filter(array_map('intval', $raw), function ($v) { return $v > 0; }))) : [];

            $db->begin_transaction();

            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($perm as $permId) {
                $stmt->bind_param('ii', $id, $permId);
                $stmt->execute();
            }

            $db->commit();
            rl_reply(true, 'Permissions updated (' . count($perm) . ' assigned).');
        }

        /* Delete a role (blocked while users are assigned) */
        if ($action === 'delete') {
            $stmt = $db->prepare("SELECT COUNT(*) AS c FROM users WHERE role_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $count = (int)$stmt->get_result()->fetch_assoc()['c'];

            if ($count > 0) {
                rl_reply(false, 'Cannot delete this role: it is assigned to ' . rl_plural($count, 'user') . '. Reassign them first.');
            }

            $db->begin_transaction();

            // First delete role permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            // Then delete the role
            $stmt = $db->prepare("DELETE FROM user_roles WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $db->commit();
            rl_reply(true, 'Role deleted.');
        }

        rl_reply(false, 'Unknown action.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        http_response_code(500);
        rl_reply(false, 'Something went wrong. Please try again.');
    }
}

/* ═══════════════ Page data ═══════════════ */
$roles          = getAllRoles();
$allPermissions = getAllPermissions();
$totalPerms     = count($allPermissions);

// Users per role
$userCounts = [];
$res = $db->query("SELECT role_id, COUNT(*) AS c FROM users GROUP BY role_id");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $u) {
    $userCounts[(int)$u['role_id']] = (int)$u['c'];
}

$rows = [];
$inUse = 0;
foreach ($roles as $role) {
    $rid       = (int)$role['id'];
    $permIds   = array_map('intval', getRolePermissions($rid));
    $userCount = $userCounts[$rid] ?? 0;
    if ($userCount > 0) $inUse++;
    $rows[] = [
        'id'          => $rid,
        'name'        => $role['name'],
        'description' => $role['description'],
        'perm_ids'    => $permIds,
        'perm_count'  => count($permIds),
        'user_count'  => $userCount,
    ];
}
$total  = count($rows);
$unused = $total - $inUse;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Role Management — NIA ACIMO</title>
    <!-- <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css"> -->
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/module-theme.css">
    <!-- jQuery must load before the sidebar include (it uses $(document).ready) -->
    <script src="../plugins/jquery/jquery.min.js"></script>
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
                <div class="pg-hero-title"><i class="fas fa-user-shield"></i>Role Management</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Define and manage system roles and their permissions</p>
            </div>
        </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Roles &amp; Permissions</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <button type="button" class="mod-chip active" data-status="">All<span class="n"><?= $total ?></span></button>
                <button type="button" class="mod-chip" data-status="In use">In use<span class="n"><?= $inUse ?></span></button>
                <button type="button" class="mod-chip" data-status="Unused">Unused<span class="n"><?= $unused ?></span></button>
              </div>
              <button type="button" class="btn btn-primary btn-sm" id="btnNew"><i class="fas fa-plus"></i> New Role</button>
            </div>
          </div>
          <div class="card-body">
            <table id="roleTable" class="table table-striped mod-table">
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Permissions</th>
                  <th>Users</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr data-id="<?= $r['id'] ?>" data-row="<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>">
                  <td data-order="<?= htmlspecialchars(strtolower($r['name']), ENT_QUOTES) ?>"
                      data-search="<?= htmlspecialchars($r['name'] . ' ' . $r['description'], ENT_QUOTES) ?>">
                    <div class="mod-title"><i class="fas fa-user-shield"></i><?= htmlspecialchars($r['name']) ?></div>
                    <div class="mod-excerpt"><?= htmlspecialchars($r['description']) ?></div>
                  </td>
                  <td data-order="<?= $r['perm_count'] ?>">
                    <span class="mod-count<?= $r['perm_count'] > 0 ? '' : ' none' ?>">
                      <i class="fas fa-key"></i><?= $r['perm_count'] ?> <small>of <?= $totalPerms ?></small>
                    </span>
                  </td>
                  <td data-order="<?= $r['user_count'] ?>">
                    <span class="mod-count<?= $r['user_count'] > 0 ? '' : ' none' ?>">
                      <i class="fas fa-users"></i><?= $r['user_count'] ?>
                    </span>
                  </td>
                  <td><span class="badge badge-<?= $r['user_count'] > 0 ? 'success' : 'secondary' ?>"><?= $r['user_count'] > 0 ? 'In use' : 'Unused' ?></span></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-info js-perms" title="Manage permissions"><i class="fas fa-key"></i></button>
                    <button type="button" class="btn btn-sm btn-warning js-edit" title="Edit role"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-danger js-delete" title="Delete role"><i class="fas fa-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add / Edit Role Modal -->
<div class="modal fade scrum-style-modal" id="roleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="roleForm" autocomplete="off">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-shield"></i><span id="roleModalTitle">New Role</span></h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Role Name <span class="text-danger">*</span></label>
            <input type="text" id="f_name" name="name" class="form-control" maxlength="100" placeholder="e.g. Records Officer" required>
          </div>
          <div class="form-group mb-0">
            <label>Description <span class="text-danger">*</span></label>
            <textarea id="f_desc" name="description" class="form-control" rows="3" maxlength="500" placeholder="What is this role responsible for?" required></textarea>
            <div class="char-count"><span id="descCount">0</span> / 500</div>
          </div>
          <div class="info-box mt-3 mb-0" id="roleHint">
            <i class="fas fa-info-circle"></i><span>Permissions are assigned after the role is created.</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSave"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade scrum-style-modal" id="permModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="permForm" autocomplete="off">
        <input type="hidden" id="pm_role_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-key"></i>Permissions &mdash; <span id="permRoleName"></span></h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="perm-tools">
            <input type="text" id="permSearch" class="form-control" placeholder="Search permissions…">
            <button type="button" class="perm-link" id="permAll" title="Applies to the permissions currently shown">Select all</button>
            <button type="button" class="perm-link" id="permNone" title="Applies to the permissions currently shown">Clear all</button>
          </div>

          <div class="perm-grid" id="permGrid">
            <?php foreach ($allPermissions as $p): ?>
              <label class="perm-item" data-search="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['description']), ENT_QUOTES) ?>">
                <input type="checkbox" value="<?= (int)$p['id'] ?>">
                <span>
                  <span class="pn"><?= htmlspecialchars($p['name']) ?></span>
                  <span class="pd"><?= htmlspecialchars($p['description']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="perm-empty" id="permEmpty"><i class="fas fa-search"></i> No matching permissions.</div>
        </div>
        <div class="modal-footer">
          <span class="perm-selected" id="permSelected"></span>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSavePerms"><i class="fas fa-save mr-1"></i>Save Permissions</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function ($) {
    var CSRF = <?= json_encode($csrf) ?>;
    var URL_ = 'roles.php';
    var STATUS_COL = 3;

    /* ── DataTable ───────────────────────── */
    var table = $('#roleTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, searchable: false, targets: 4 },
            { searchable: false, targets: [1, 2] }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search roles…',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_–_END_ of _TOTAL_ role(s)',
            infoEmpty: 'No roles',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No roles yet. Click “New Role” to create one.',
            zeroRecords: 'No matching roles found.'
        }
    });

    /* status chips */
    $('.mod-chip').on('click', function () {
        $('.mod-chip').removeClass('active');
        $(this).addClass('active');
        var s = $(this).data('status');
        table.column(STATUS_COL).search(s ? '^' + s + '$' : '', true, false).draw();
    });

    /* ── Helpers ─────────────────────────── */
    function toast(msg, type) {
        Swal.fire({ toast: true, position: 'bottom-end', icon: type || 'success', title: msg,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }
    function post(data) {
        data.csrf = CSRF;
        return $.ajax({ url: URL_, type: 'POST', data: data, dataType: 'json' });
    }
    function done(res) {
        if (res && res.success) {
            toast(res.message, 'success');
            setTimeout(function () { location.reload(); }, 800);
        } else {
            toast((res && res.message) || 'Please try again.', 'error');
        }
    }
    function fail(xhr) {
        toast((xhr.responseJSON && xhr.responseJSON.message) || 'Network error. Please try again.', 'error');
    }
    function rowData(el) { return $(el).closest('tr').data('row'); }
    function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }

    /* ── Role modal (add / edit) ─────────── */
    function openRole(d) {
        d = d || { id:0, name:'', description:'' };
        $('#roleModalTitle').text(d.id ? 'Edit Role' : 'New Role');
        $('#btnSave').html('<i class="fas fa-save mr-1"></i>' + (d.id ? 'Update' : 'Save'));
        $('#roleHint').toggle(!d.id);
        $('#f_id').val(d.id);
        $('#f_name').val(d.name);
        $('#f_desc').val(d.description);
        $('#descCount').text($('#f_desc').val().length);
        $('#roleModal').modal('show');
    }
    $('#roleModal').on('shown.bs.modal', function () { $('#f_name').trigger('focus'); });
    $('#btnNew').on('click', function () { openRole(); });
    $('#roleTable').on('click', '.js-edit', function () { openRole(rowData(this)); });
    $('#f_desc').on('input', function () { $('#descCount').text(this.value.length); });

    $('#roleForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#btnSave').prop('disabled', true);
        post({
            action: 'save',
            id: $('#f_id').val(),
            name: $('#f_name').val(),
            description: $('#f_desc').val()
        }).done(function (r) {
            if (r && r.success) $('#roleModal').modal('hide');
            done(r);
        }).fail(fail).always(function () { $btn.prop('disabled', false); });
    });

    /* ── Permissions modal ───────────────── */
    function updatePermCount() {
        var all = $('#permGrid input').length, on = $('#permGrid input:checked').length;
        $('#permSelected').text(on + ' of ' + all + ' selected');
    }
    function filterPerms() {
        var q = $.trim($('#permSearch').val()).toLowerCase(), shown = 0;
        $('#permGrid .perm-item').each(function () {
            var hit = !q || String($(this).data('search')).indexOf(q) !== -1;
            $(this).toggle(hit);
            if (hit) shown++;
        });
        $('#permEmpty').toggle(shown === 0);
    }
    function openPerms(d) {
        var sel = {};
        $.each(d.perm_ids || [], function (_, id) { sel[id] = true; });
        $('#pm_role_id').val(d.id);
        $('#permRoleName').text(d.name);
        $('#permSearch').val('');
        $('#permGrid input').each(function () {
            var on = !!sel[this.value];
            this.checked = on;
            $(this).closest('.perm-item').toggleClass('checked', on);
        });
        filterPerms();
        updatePermCount();
        $('#permModal').modal('show');
    }
    $('#permModal').on('shown.bs.modal', function () { $('#permSearch').trigger('focus'); });
    $('#roleTable').on('click', '.js-perms', function () { openPerms(rowData(this)); });
    $('#permSearch').on('input', filterPerms);
    $('#permGrid').on('change', 'input', function () {
        $(this).closest('.perm-item').toggleClass('checked', this.checked);
        updatePermCount();
    });
    function setVisible(on) {
        $('#permGrid .perm-item:visible input').each(function () {
            this.checked = on;
            $(this).closest('.perm-item').toggleClass('checked', on);
        });
        updatePermCount();
    }
    $('#permAll').on('click', function () { setVisible(true); });
    $('#permNone').on('click', function () { setVisible(false); });

    $('#permForm').on('submit', function (e) {
        e.preventDefault();
        var ids = $('#permGrid input:checked').map(function () { return this.value; }).get();
        var $btn = $('#btnSavePerms').prop('disabled', true);
        post({ action: 'save_permissions', id: $('#pm_role_id').val(), permissions: ids })
            .done(function (r) {
                if (r && r.success) $('#permModal').modal('hide');
                done(r);
            }).fail(fail).always(function () { $btn.prop('disabled', false); });
    });

    /* ── Delete ──────────────────────────── */
    $('#roleTable').on('click', '.js-delete', function () {
        var d = rowData(this);
        if (+d.user_count > 0) {
            Swal.fire({
                icon:'info', title:'Role is in use',
                html:'<p style="font-size:.88rem;">&ldquo;' + $('<span>').text(d.name).html() + '&rdquo; is assigned to <strong>' + plural(+d.user_count, 'user') + '</strong>.<br>Reassign them to another role before deleting it.</p>',
                confirmButtonColor:'#2a9863'
            });
            return;
        }
        Swal.fire({
            icon:'warning', title:'Delete this role?',
            html:'<p style="font-size:.88rem;">&ldquo;' + $('<span>').text(d.name).html() + '&rdquo; and its <strong>' + plural(+d.perm_count, 'permission') + '</strong> assignment will be removed <strong>permanently</strong>.</p>',
            showCancelButton:true, confirmButtonColor:'#c92a2a', cancelButtonColor:'#6c757d',
            confirmButtonText:'<i class="fas fa-trash-alt"></i> Delete'
        }).then(function (r) {
            if (r.isConfirmed) post({ action:'delete', id:d.id }).done(done).fail(fail);
        });
    });
})(jQuery);
</script>
</body>
</html>