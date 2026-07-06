<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['emp_id'])) { header('Location: ../login.php'); exit; }
if (!hasPermission('view_ict_equipment') && !hasPermission('manage_ict_maintenance')) {
    header('Location: dashboard.php');
    exit;
}
$can_manage = hasPermission('manage_ict_maintenance');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NIA-ACIMO | Assign / Return Equipment</title>
<?php include '../includes/header.php'; ?>
<style>
/* ═══════════════════════════════════════════════════
   DESIGN TOKENS — Light Mode
═══════════════════════════════════════════════════ */
:root {
  --rr-bg:          #f0f4f8;
  --rr-surface:     #ffffff;
  --rr-surface-2:   #f8fafc;
  --rr-border:      #e2e8f0;
  --rr-border-sub:  #f1f5f9;
  --rr-text:        #0f172a;
  --rr-text-2:      #475569;
  --rr-text-muted:  #94a3b8;
  --rr-primary:     #2563eb;
  --rr-primary-dk:  #1d4ed8;
  --rr-primary-lt:  #eff6ff;
  --rr-accent:      #06b6d4;
  --rr-success:     #10b981;
  --rr-warning:     #f59e0b;
  --rr-danger:      #ef4444;
  --rr-shadow-sm:   0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --rr-shadow:      0 4px 16px rgba(0,0,0,.08);
  --rr-shadow-lg:   0 12px 40px rgba(0,0,0,.14);
  --rr-radius-sm:   6px;
  --rr-radius:      12px;
  --rr-radius-lg:   18px;
  --rr-font:        'DM Sans',sans-serif;
  --rr-font-h:      'Syne',sans-serif;
}
body.dark-mode {
  --rr-bg:         #0f172a;
  --rr-surface:    #1e293b;
  --rr-surface-2:  #162032;
  --rr-border:     #334155;
  --rr-border-sub: #1e293b;
  --rr-text:       #f1f5f9;
  --rr-text-2:     #94a3b8;
  --rr-text-muted: #64748b;
  --rr-primary-lt: rgba(37,99,235,.18);
  --rr-shadow-sm:  0 1px 3px rgba(0,0,0,.3);
  --rr-shadow:     0 4px 20px rgba(0,0,0,.4);
  --rr-shadow-lg:  0 12px 40px rgba(0,0,0,.5);
}
body,.content-wrapper { background:var(--rr-bg)!important; font-family:var(--rr-font)!important; }
.content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

/* ═══ HERO ═══ */
@keyframes meshDrift  { 0%{transform:translate(0,0) rotate(0)} 100%{transform:translate(3%,2%) rotate(2deg)} }
@keyframes orbFloat   { 0%,100%{opacity:.4;transform:translate(0,0) scale(1)} 33%{opacity:.7;transform:translate(18px,-26px) scale(1.05)} 66%{opacity:.5;transform:translate(-12px,16px) scale(.95)} }
.pg-hero { background:#0b1f17;padding:36px 28px 66px;position:relative;overflow:hidden; }
.pg-hero-mesh { position:absolute;inset:-50%;width:200%;height:200%;z-index:0;
  background:radial-gradient(ellipse 60% 55% at 18% 28%,rgba(36,231,143,.16) 0%,transparent 58%),
             radial-gradient(ellipse 55% 60% at 82% 72%,rgba(42,152,99,.13) 0%,transparent 58%),
             linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
  animation:meshDrift 22s ease-in-out infinite alternate; }
.pg-hero-orbs { position:absolute;inset:0;z-index:0;pointer-events:none;overflow:hidden; }
.pg-orb { position:absolute;border-radius:50%;filter:blur(60px);animation:orbFloat 18s ease-in-out infinite; }
.pg-orb-1 { width:280px;height:280px;background:rgba(36,231,143,.11);top:-80px;left:-60px;animation-duration:21s; }
.pg-orb-2 { width:220px;height:220px;background:rgba(42,152,99,.10);bottom:-50px;right:-40px;animation-delay:-7s;animation-duration:17s; }
.pg-hero-dots { position:absolute;inset:0;z-index:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(36,231,143,.06) 1px,transparent 1px);background-size:36px 36px; }
.pg-hero::after { content:'';position:absolute;bottom:-32px;left:0;right:0;height:64px;
  background:var(--rr-bg);clip-path:ellipse(58% 100% at 50% 100%);z-index:1; }
.pg-hero-inner { position:relative;z-index:2; }
.pg-hero-title { color:#fff;font-size:1.75rem;font-weight:800;margin:0 0 6px;letter-spacing:-.3px;
  text-shadow:0 2px 14px rgba(0,0,0,.45);display:flex;align-items:center;gap:10px; }
.pg-hero-sub   { color:rgba(212,245,229,.75);margin:0 0 14px;font-size:.9rem; }
.pg-hero-divider { width:48px;height:2px;border-radius:2px;margin:0 0 12px;
  background:linear-gradient(90deg,transparent,#24e78f,transparent); }
.pg-hero-actions { position:relative;z-index:2;display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-top:4px; }
.pg-hero-btn {
  background:rgba(36,231,143,.1);backdrop-filter:blur(8px);
  border:1px solid rgba(36,231,143,.3);color:#d4f5e5;
  border-radius:10px;padding:8px 18px;font-size:.84rem;font-weight:700;cursor:pointer;
  display:inline-flex;align-items:center;gap:7px;transition:all .2s;text-decoration:none;
}
.pg-hero-btn:hover { background:rgba(36,231,143,.22);border-color:rgba(36,231,143,.55);
  transform:translateY(-2px);box-shadow:0 4px 16px rgba(36,231,143,.2);color:#d4f5e5;text-decoration:none; }

/* ═══ CARDS ═══ */
.card {
  background:var(--rr-surface)!important;border:1px solid var(--rr-border)!important;
  border-radius:var(--rr-radius-lg)!important;box-shadow:var(--rr-shadow-sm)!important;
  transition:box-shadow .2s;
}
.card:hover { box-shadow:var(--rr-shadow)!important; }
.card-header {
  background:var(--rr-surface)!important;border-bottom:1px solid var(--rr-border-sub)!important;
  padding:1rem 1.25rem!important;display:flex;align-items:center;gap:.6rem;
}
.card-header::before { content:'';display:inline-block;width:4px;height:18px;border-radius:4px;
  background:linear-gradient(160deg,var(--rr-primary),var(--rr-accent));flex-shrink:0; }
.card-header .card-title,.card-header h3 {
  font-family:var(--rr-font-h);font-size:.95rem!important;font-weight:700!important;
  color:var(--rr-text)!important;letter-spacing:-.01em;margin:0!important;
}
.card-body { background:var(--rr-surface)!important; }

/* ═══ BADGES ═══ */
.badge { border-radius:20px!important;font-size:.7rem!important;font-weight:700!important;padding:.3em .75em!important; }

/* ═══ FORMS ═══ */
.form-group label { font-size:.76rem;font-weight:700;color:var(--rr-text-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;display:block; }
.form-control {
  background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;
  border-radius:var(--rr-radius-sm)!important;color:var(--rr-text)!important;
  font-family:var(--rr-font)!important;font-size:.875rem!important;padding:.5rem .75rem!important;
  transition:border-color .15s,box-shadow .15s;
}
.form-control:focus { border-color:var(--rr-primary)!important;box-shadow:0 0 0 3px rgba(37,99,235,.12)!important;background:var(--rr-surface)!important; }
textarea.form-control { resize:vertical;min-height:80px; }
select.form-control option { background:var(--rr-surface);color:var(--rr-text); }

/* ═══ BUTTONS ═══ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-primary   { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;color:#fff!important;box-shadow:0 2px 8px rgba(37,99,235,.3)!important; }
.btn-primary:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,.4)!important; }
.btn-success   { background:linear-gradient(135deg,var(--rr-success),#059669)!important;border:none!important;color:#fff!important; }
.btn-secondary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }
.btn-secondary:hover { background:var(--rr-border)!important;color:var(--rr-text)!important; }
.btn-xs { font-size:.72rem!important;padding:.25rem .55rem!important; }

/* ═══ MODALS ═══ */
.modal-content { border:none!important;border-radius:var(--rr-radius-lg)!important;box-shadow:var(--rr-shadow-lg)!important;overflow:hidden;background:var(--rr-surface)!important; }
.modal-header { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;padding:1.1rem 1.5rem!important; }
.modal-title  { font-family:var(--rr-font-h)!important;font-weight:700!important;font-size:1rem!important;color:#fff!important; }
.modal-header .close { color:rgba(255,255,255,.8)!important;text-shadow:none!important;font-size:1.4rem; }
.modal-body  { padding:1.5rem!important;background:var(--rr-surface)!important; }
.modal-footer { padding:1rem 1.5rem!important;border-top:1px solid var(--rr-border-sub)!important;background:var(--rr-surface-2)!important;display:flex;gap:.5rem;flex-wrap:wrap; }

/* ═══ TABLES ═══ */
.table { font-size:.85rem;color:var(--rr-text); }
.table thead th { background:var(--rr-surface-2)!important;border-bottom:2px solid var(--rr-border)!important;font-weight:700;font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;color:var(--rr-text-muted)!important;padding:.75rem 1rem;white-space:nowrap; }
.table tbody td { padding:.65rem 1rem;border-color:var(--rr-border-sub)!important;vertical-align:middle;color:var(--rr-text); }
.table-hover tbody tr:hover td { background:var(--rr-primary-lt)!important; }
body.dark-mode .table-hover tbody tr:hover td { background:rgba(37,99,235,.1)!important; }
div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
  border:1.5px solid var(--rr-border)!important;border-radius:var(--rr-radius-sm)!important;
  padding:.3rem .6rem;font-size:.82rem;font-family:var(--rr-font);
  background:var(--rr-surface-2)!important;color:var(--rr-text)!important;
}
div.dataTables_wrapper div.dataTables_info,
div.dataTables_wrapper .dataTables_length label,
div.dataTables_wrapper .dataTables_filter label { font-size:.8rem;color:var(--rr-text-muted);font-family:var(--rr-font); }
.paginate_button { border-radius:var(--rr-radius-sm)!important;font-size:.8rem!important; }
.paginate_button.current { background:var(--rr-primary)!important;border-color:var(--rr-primary)!important;color:#fff!important; }
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar_ict.php'; ?>

<div class="content-wrapper">

  <!-- ── Hero ──────────────────────────────────────────────── -->
  <div class="pg-hero">
    <div class="pg-hero-mesh"></div>
    <div class="pg-hero-orbs">
      <div class="pg-orb pg-orb-1"></div>
      <div class="pg-orb pg-orb-2"></div>
    </div>
    <div class="pg-hero-dots"></div>
    <div class="pg-hero-inner">
      <h1 class="pg-hero-title"><i class="fas fa-user-tag"></i> Assign / Return Equipment</h1>
      <p class="pg-hero-sub">Issue equipment to employees and process returns with condition tracking.</p>
      <div class="pg-hero-divider"></div>
      <?php if ($can_manage): ?>
      <div class="pg-hero-actions">
        <button class="pg-hero-btn" id="btnAssign"><i class="fas fa-user-tag"></i> Assign Equipment</button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Content ───────────────────────────────────────────── -->
  <section class="content">
    <div class="card mt-4">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-list mr-1"></i> Assignment Records</h3></div>
      <div class="card-body">
        <table id="assignmentsTable" class="table table-bordered table-striped" style="width:100%">
          <thead>
            <tr>
              <th>Asset Tag</th>
              <th>Equipment</th>
              <th>Employee</th>
              <th>Assigned Date</th>
              <th>Expected Return</th>
              <th>Returned Date</th>
              <th>Status</th>
              <?php if ($can_manage): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>
</div>
<?php include '../includes/mainfooter.php'; ?>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="assignForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-tag mr-2"></i>Assign Equipment</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Equipment (Available only) *</label>
            <select class="form-control" name="equipment_id" id="assign_equipment" required>
              <option value="">-- Select Equipment --</option>
            </select>
          </div>
          <div class="form-group">
            <label>Employee *</label>
            <select class="form-control" name="employee_id" id="assign_employee" required>
              <option value="">-- Select Employee --</option>
            </select>
          </div>
          <div class="form-group">
            <label>Expected Return Date (optional)</label>
            <input type="date" class="form-control" name="expected_return_date">
          </div>
          <div class="form-group">
            <label>Condition Upon Assignment</label>
            <select class="form-control" name="condition_on_assign">
              <option selected>Good</option><option>New</option><option>Fair</option><option>Poor</option>
            </select>
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i>Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="returnForm">
        <input type="hidden" name="assignment_id" id="return_assignment_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-undo mr-2"></i>Return Equipment</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Condition Upon Return</label>
            <select class="form-control" name="condition_on_return">
              <option selected>Good</option><option>Fair</option><option>Poor</option><option>Defective</option>
            </select>
          </div>
          <div class="form-group">
            <label>Set Equipment Status To</label>
            <select class="form-control" name="new_status">
              <option selected>Available</option>
              <option value="Under Repair">Under Repair</option>
              <option value="Retired">Retired</option>
            </select>
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Confirm Return</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CAN_MANAGE = <?= $can_manage ? 'true' : 'false' ?>;
let assignmentsTable;

function buildColumns() {
    const cols = [
        { data: 'asset_tag', render: d => `<span class="badge badge-secondary">${d}</span>` },
        { data: 'equipment_name' },
        { data: 'employee_name' },
        { data: 'assigned_date' },
        { data: 'expected_return_date', defaultContent: '-' },
        { data: 'returned_date', defaultContent: '-' },
        {
            data: 'status',
            render: s => `<span class="badge badge-${s === 'Assigned' ? 'warning' : 'success'}">${s}</span>`
        }
    ];
    if (CAN_MANAGE) {
        cols.push({
            data: null,
            orderable: false,
            render: function(row) {
                if (row.status === 'Assigned') {
                    return `<button class="btn btn-xs btn-success btnReturn" data-id="${row.id}"><i class="fas fa-undo"></i> Return</button>`;
                }
                return '-';
            }
        });
    }
    return cols;
}

$(function() {
    assignmentsTable = $('#assignmentsTable').DataTable({
        ajax: { url: 'ict_ajax.php', type: 'POST', data: { action: 'list_assignments' }, dataSrc: 'data' },
        columns: buildColumns()
    });

    $('#btnAssign').on('click', function() {
        $('#assignForm')[0].reset();
        $.post('ict_ajax.php', { action: 'get_available_equipment' }, function(res) {
            let opts = '<option value="">-- Select Equipment --</option>';
            res.data.forEach(e => {
                opts += `<option value="${e.id}">${e.asset_tag} — ${e.equipment_name} ${e.brand || ''} ${e.model || ''}</option>`;
            });
            $('#assign_equipment').html(opts);
        }, 'json');
        $.post('ict_ajax.php', { action: 'get_employees' }, function(res) {
            let opts = '<option value="">-- Select Employee --</option>';
            res.data.forEach(e => { opts += `<option value="${e.emp_id}">${e.full_name}</option>`; });
            $('#assign_employee').html(opts);
        }, 'json');
        $('#assignModal').modal('show');
    });

    $('#assignForm').on('submit', function(e) {
        e.preventDefault();
        $.post('ict_ajax.php', $(this).serialize() + '&action=assign_equipment', function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#assignModal').modal('hide');
                assignmentsTable.ajax.reload(null, false);
            } else {
                toastr.error(res.message);
            }
        }, 'json');
    });

    $('#assignmentsTable').on('click', '.btnReturn', function() {
        $('#return_assignment_id').val($(this).data('id'));
        $('#returnModal').modal('show');
    });

    $('#returnForm').on('submit', function(e) {
        e.preventDefault();
        $.post('ict_ajax.php', $(this).serialize() + '&action=return_equipment', function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#returnModal').modal('hide');
                assignmentsTable.ajax.reload(null, false);
            } else {
                toastr.error(res.message);
            }
        }, 'json');
    });
});
</script>
</body>
</html>