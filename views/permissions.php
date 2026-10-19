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

if (empty($_SESSION['csrf_permissions'])) {
    $_SESSION['csrf_permissions'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_permissions'];

function pm_reply(bool $ok, string $msg = '', array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

function pm_plural(int $n, string $word): string {
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/* ═══════════════ AJAX actions (POST) ═══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        pm_reply(false, 'Session expired. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'save') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($name === '' || $description === '') pm_reply(false, 'Name and description are required.');
            if (mb_strlen($name) > 100)               pm_reply(false, 'Name must be 100 characters or fewer.');
            if (mb_strlen($description) > 255)        pm_reply(false, 'Description must be 255 characters or fewer.');

            // Unique name (ignore the row being edited)
            $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ? AND id <> ? LIMIT 1");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                pm_reply(false, 'A permission with that name already exists.');
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param('ssi', $name, $description, $id);
            } else {
                $stmt = $db->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                $stmt->bind_param('ss', $name, $description);
            }
            $stmt->execute();
            pm_reply(true, $id > 0 ? 'Permission updated.' : 'Permission added.');
        }

        if ($id <= 0) pm_reply(false, 'Invalid permission.');

        if ($action === 'delete') {
            $db->begin_transaction();

            // First remove from role_permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            // Then delete the permission
            $stmt = $db->prepare("DELETE FROM permissions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $db->commit();
            pm_reply(true, 'Permission deleted.');
        }

        pm_reply(false, 'Unknown action.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        http_response_code(500);
        pm_reply(false, 'Something went wrong. Please try again.');
    }
}

/* ═══════════════ Page data ═══════════════ */
$rows = $db->query("
    SELECT p.id, p.name, p.description, COUNT(rp.role_id) AS role_count
    FROM permissions p
    LEFT JOIN role_permissions rp ON rp.permission_id = p.id
    GROUP BY p.id, p.name, p.description
    ORDER BY p.name
")->fetch_all(MYSQLI_ASSOC);

$totalRoles = (int)($db->query("SELECT COUNT(*) AS c FROM user_roles")->fetch_assoc()['c'] ?? 0);

$inUse = 0;
foreach ($rows as $r) { if ((int)$r['role_count'] > 0) $inUse++; }
$total  = count($rows);
$unused = $total - $inUse;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permissions — NIA ACIMO</title>
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
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
                <div class="pg-hero-title"><i class="fas fa-key"></i>Permission Management</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Define granular system permissions for roles</p>
            </div>
        </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">System Permissions</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <button type="button" class="mod-chip active" data-status="">All<span class="n"><?= $total ?></span></button>
                <button type="button" class="mod-chip" data-status="In use">In use<span class="n"><?= $inUse ?></span></button>
                <button type="button" class="mod-chip" data-status="Unused">Unused<span class="n"><?= $unused ?></span></button>
              </div>
              <button type="button" class="btn btn-primary btn-sm" id="btnNew"><i class="fas fa-plus"></i> New Permission</button>
            </div>
          </div>
          <div class="card-body">
            <table id="permTable" class="table table-striped mod-table">
              <thead>
                <tr>
                  <th>Permission</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Used by</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r):
                  $rc = (int)$r['role_count'];
                  $payload = [
                      'id' => (int)$r['id'], 'name' => $r['name'],
                      'description' => $r['description'], 'role_count' => $rc,
                  ];
              ?>
                <tr data-id="<?= (int)$r['id'] ?>" data-row="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>">
                  <td data-order="<?= htmlspecialchars(strtolower($r['name']), ENT_QUOTES) ?>">
                    <div class="mod-title"><i class="fas fa-key"></i><?= htmlspecialchars($r['name']) ?></div>
                  </td>
                  <td><div class="mod-desc"><?= htmlspecialchars($r['description']) ?></div></td>
                  <td><span class="badge badge-<?= $rc > 0 ? 'success' : 'secondary' ?>"><?= $rc > 0 ? 'In use' : 'Unused' ?></span></td>
                  <td data-order="<?= $rc ?>">
                    <span class="mod-count<?= $rc > 0 ? '' : ' none' ?>"><i class="fas fa-user-shield"></i><?= pm_plural($rc, 'role') ?></span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-warning js-edit" title="Edit"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-danger js-delete" title="Delete"><i class="fas fa-trash"></i></button>
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

<!-- Add / Edit Permission Modal -->
<div class="modal fade scrum-style-modal" id="permModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="permForm" autocomplete="off">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-key"></i><span id="permModalTitle">New Permission</span></h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Permission Name <span class="text-danger">*</span></label>
            <input type="text" id="f_name" name="name" class="form-control" maxlength="100" placeholder="e.g. manage_roles" required>
          </div>
          <div class="form-group mb-0">
            <label>Description <span class="text-danger">*</span></label>
            <textarea id="f_desc" name="description" class="form-control" rows="3" maxlength="255" placeholder="What does this permission allow?" required></textarea>
            <div class="char-count"><span id="descCount">0</span> / 255</div>
          </div>
          <div class="info-box mt-3 mb-0">
            <i class="fas fa-info-circle"></i><span>This is the key the system checks, so renaming one can affect pages that use it.</span>
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

<?php include '../includes/footer.php'; ?>

<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function ($) {
    var CSRF = <?= json_encode($csrf) ?>;
    var URL_ = 'permissions.php';
    var STATUS_COL = 2;

    /* ── DataTable ───────────────────────── */
    var table = $('#permTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, searchable: false, targets: 4 },
            { searchable: false, targets: 3 }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search permissions…',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_–_END_ of _TOTAL_ permission(s)',
            infoEmpty: 'No permissions',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No permissions yet. Click “New Permission” to add one.',
            zeroRecords: 'No matching permissions found.'
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

    /* ── Modal ───────────────────────────── */
    function openModal(d) {
        d = d || { id:0, name:'', description:'' };
        $('#permModalTitle').text(d.id ? 'Edit Permission' : 'New Permission');
        $('#btnSave').html('<i class="fas fa-save mr-1"></i>' + (d.id ? 'Update' : 'Save'));
        $('#f_id').val(d.id);
        $('#f_name').val(d.name);
        $('#f_desc').val(d.description);
        $('#descCount').text($('#f_desc').val().length);
        $('#permModal').modal('show');
    }
    $('#permModal').on('shown.bs.modal', function () { $('#f_name').trigger('focus'); });
    $('#btnNew').on('click', function () { openModal(); });
    $('#permTable').on('click', '.js-edit', function () { openModal(rowData(this)); });
    $('#f_desc').on('input', function () { $('#descCount').text(this.value.length); });

    $('#permForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#btnSave').prop('disabled', true);
        post({
            action: 'save',
            id: $('#f_id').val(),
            name: $('#f_name').val(),
            description: $('#f_desc').val()
        }).done(function (r) {
            if (r && r.success) $('#permModal').modal('hide');
            done(r);
        }).fail(fail).always(function () { $btn.prop('disabled', false); });
    });

    /* ── Delete ──────────────────────────── */
    $('#permTable').on('click', '.js-delete', function () {
        var d = rowData(this), n = +d.role_count;
        var usage = n > 0
            ? 'It is currently used by <strong>' + n + ' role' + (n === 1 ? '' : 's') + '</strong>, which will lose this access.'
            : 'No role is using it right now.';
        Swal.fire({
            icon:'warning', title:'Delete this permission?',
            html:'<p style="font-size:.88rem;">&ldquo;' + $('<span>').text(d.name).html() + '&rdquo; will be deleted <strong>permanently</strong>.<br>' + usage + '</p>',
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