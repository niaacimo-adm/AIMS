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

$database = new Database();
$db = $database->getConnection();
$categories = $db->query("SELECT * FROM ict_categories ORDER BY category_name");
$offices    = $db->query("SELECT office_id, office_name FROM office ORDER BY office_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NIA-ACIMO | Equipment Inventory</title>
<?php include '../includes/header.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
  --rr-purple:      #7c3aed;
  --rr-cyan:        #0891b2;
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
.btn-danger    { background:linear-gradient(135deg,var(--rr-danger),#dc2626)!important;border:none!important;color:#fff!important; }
.btn-warning   { background:linear-gradient(135deg,var(--rr-warning),#d97706)!important;border:none!important;color:#fff!important; }
.btn-info      { background:linear-gradient(135deg,var(--rr-cyan),#0e7490)!important;border:none!important;color:#fff!important; }
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
#qrLabelArea { padding:8px 0; }
#qrCodeCanvas canvas, #qrCodeCanvas img { border-radius:var(--rr-radius-sm); }

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
      <h1 class="pg-hero-title"><i class="fas fa-boxes-stacked"></i> Equipment Inventory</h1>
      <p class="pg-hero-sub">Track every asset — specs, custodianship, condition, and QR labels — in one place.</p>
      <div class="pg-hero-divider"></div>
      <?php if ($can_manage): ?>
      <div class="pg-hero-actions">
        <button class="pg-hero-btn" id="btnAddEquipment"><i class="fas fa-plus"></i> Add Equipment</button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Content ───────────────────────────────────────────── -->
  <section class="content">
    <div class="card mt-4">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-1"></i> Inventory List</h3></div>
      <div class="card-body">
        <table id="equipmentTable" class="table table-bordered table-striped" style="width:100%">
          <thead>
            <tr>
              <th>Asset Tag</th>
              <th>Equipment</th>
              <th>Category</th>
              <th>Brand / Model</th>
              <th>Serial No.</th>
              <th>Status</th>
              <th>Assigned To</th>
              <th>Actions</th>
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

<!-- Add / Edit Equipment Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="equipmentForm">
        <div class="modal-header">
          <h5 class="modal-title" id="equipmentModalTitle"><i class="fas fa-desktop mr-2"></i>Add Equipment</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="eq_id">
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Equipment Name *</label>
              <input type="text" class="form-control" name="equipment_name" id="eq_name" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Category</label>
              <select class="form-control" name="category_id" id="eq_category">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Brand</label>
              <input type="text" class="form-control" name="brand" id="eq_brand">
            </div>
            <div class="col-md-6 form-group">
              <label>Model</label>
              <input type="text" class="form-control" name="model" id="eq_model">
            </div>
            <div class="col-md-6 form-group">
              <label>Serial Number</label>
              <input type="text" class="form-control" name="serial_number" id="eq_serial">
            </div>
            <div class="col-md-6 form-group">
              <label>Custodian Office</label>
              <select class="form-control" name="office_id" id="eq_office">
                <option value="">-- Select Office --</option>
                <?php foreach ($offices as $o): ?>
                  <option value="<?= $o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Date Acquired</label>
              <input type="date" class="form-control" name="date_acquired" id="eq_date_acquired">
            </div>
            <div class="col-md-6 form-group">
              <label>Acquisition Cost</label>
              <input type="number" step="0.01" class="form-control" name="acquisition_cost" id="eq_cost">
            </div>
            <div class="col-md-6 form-group">
              <label>Supplier</label>
              <input type="text" class="form-control" name="supplier" id="eq_supplier">
            </div>
            <div class="col-md-6 form-group">
              <label>Condition</label>
              <select class="form-control" name="condition_status" id="eq_condition">
                <option>New</option><option selected>Good</option><option>Fair</option><option>Poor</option><option>Defective</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Status</label>
              <select class="form-control" name="status" id="eq_status">
                <option selected>Available</option><option>Assigned</option><option>Under Repair</option><option>Retired</option><option>Lost</option>
              </select>
            </div>
            <div class="col-md-12 form-group">
              <label>Specifications</label>
              <textarea class="form-control" name="specifications" id="eq_specs" rows="2"></textarea>
            </div>
            <div class="col-md-12 form-group">
              <label>Remarks</label>
              <textarea class="form-control" name="remarks" id="eq_remarks" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Equipment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-qrcode mr-2"></i>Equipment QR Label</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body text-center" id="qrLabelArea">
        <div id="qrCodeCanvas" class="d-flex justify-content-center mb-2"></div>
        <h5 id="qrAssetTag" class="mb-0"></h5>
        <p id="qrEquipmentName" class="text-muted"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="btnDownloadQr"><i class="fas fa-download"></i> Download QR</button>
        <button type="button" class="btn btn-primary" id="btnPrintQr"><i class="fas fa-print"></i> Print Label</button>
      </div>
    </div>
  </div>
</div>

<script>
const CAN_MANAGE = <?= $can_manage ? 'true' : 'false' ?>;
// Full URL scanned devices will decode — points back to the scanner lookup
const SCAN_BASE_URL = window.location.origin + window.location.pathname.replace('ict_equipment.php', 'ict_scanner.php');

let equipmentTable;

$(function() {
    equipmentTable = $('#equipmentTable').DataTable({
        ajax: {
            url: 'ict_ajax.php',
            type: 'POST',
            data: { action: 'list_equipment' },
            dataSrc: 'data'
        },
        columns: [
            { data: 'asset_tag', render: d => `<span class="badge badge-secondary">${d}</span>` },
            { data: 'equipment_name' },
            { data: 'category_name', defaultContent: '-' },
            { data: null, render: r => `${r.brand || ''} ${r.model || ''}`.trim() || '-' },
            { data: 'serial_number', defaultContent: '-' },
            {
                data: 'status',
                render: function(status) {
                    const map = { Available: 'success', Assigned: 'warning', 'Under Repair': 'danger', Retired: 'secondary', Lost: 'dark' };
                    return `<span class="badge badge-${map[status] || 'secondary'}">${status}</span>`;
                }
            },
            { data: 'assigned_to', defaultContent: '-' },
            {
                data: null,
                orderable: false,
                render: function(row) {
                    let btns = `<button class="btn btn-xs btn-info btnQr" data-id="${row.id}" data-tag="${row.asset_tag}" data-name="${row.equipment_name}"><i class="fas fa-qrcode"></i></button> `;
                    if (CAN_MANAGE) {
                        btns += `<button class="btn btn-xs btn-warning btnEdit" data-id="${row.id}"><i class="fas fa-edit"></i></button> `;
                        btns += `<button class="btn btn-xs btn-danger btnDelete" data-id="${row.id}"><i class="fas fa-trash"></i></button>`;
                    }
                    return btns;
                }
            }
        ]
    });

    $('#btnAddEquipment').on('click', function() {
        $('#equipmentForm')[0].reset();
        $('#eq_id').val('');
        $('#equipmentModalTitle').html('<i class="fas fa-desktop mr-2"></i>Add Equipment');
        $('#equipmentModal').modal('show');
    });

    $('#equipmentTable').on('click', '.btnEdit', function() {
        const id = $(this).data('id');
        $.post('ict_ajax.php', { action: 'get_equipment', id }, function(res) {
            if (!res.success) { toastr.error('Equipment not found.'); return; }
            const d = res.data;
            $('#eq_id').val(d.id);
            $('#eq_name').val(d.equipment_name);
            $('#eq_category').val(d.category_id);
            $('#eq_brand').val(d.brand);
            $('#eq_model').val(d.model);
            $('#eq_serial').val(d.serial_number);
            $('#eq_office').val(d.office_id);
            $('#eq_date_acquired').val(d.date_acquired);
            $('#eq_cost').val(d.acquisition_cost);
            $('#eq_supplier').val(d.supplier);
            $('#eq_condition').val(d.condition_status);
            $('#eq_status').val(d.status);
            $('#eq_specs').val(d.specifications);
            $('#eq_remarks').val(d.remarks);
            $('#equipmentModalTitle').html('<i class="fas fa-edit mr-2"></i>Edit Equipment — ' + d.asset_tag);
            $('#equipmentModal').modal('show');
        }, 'json');
    });

    $('#equipmentForm').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=save_equipment';
        $.post('ict_ajax.php', data, function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#equipmentModal').modal('hide');
                equipmentTable.ajax.reload(null, false);
            } else {
                toastr.error(res.message);
            }
        }, 'json');
    });

    $('#equipmentTable').on('click', '.btnDelete', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete this equipment?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ict_ajax.php', { action: 'delete_equipment', id }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        equipmentTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        });
    });

    // QR code generation — encodes a scannable URL back to the scanner page
    $('#equipmentTable').on('click', '.btnQr', function() {
        const tag = $(this).data('tag');
        const name = $(this).data('name');
        $('#qrCodeCanvas').empty();
        $('#qrAssetTag').text(tag);
        $('#qrEquipmentName').text(name);
        new QRCode(document.getElementById('qrCodeCanvas'), {
            text: SCAN_BASE_URL + '?tag=' + encodeURIComponent(tag),
            width: 180,
            height: 180
        });
        $('#qrModal').modal('show');
    });

    // Download QR — exports the on-screen QR canvas as a PNG file
    $('#btnDownloadQr').on('click', function() {
        const canvas = document.querySelector('#qrCodeCanvas canvas');
        if (!canvas) {
            toastr.error('QR code is not ready yet. Please try again.');
            return;
        }
        const tag = ($('#qrAssetTag').text().trim() || 'equipment-qr').replace(/[^a-zA-Z0-9_-]/g, '_');
        const link = document.createElement('a');
        link.download = tag + '.png';
        link.href = canvas.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    $('#btnPrintQr').on('click', function() {
        const printContents = document.getElementById('qrLabelArea').innerHTML;
        const w = window.open('', '', 'width=400,height=500');
        w.document.write(`<html><head><title>Print Label</title>
            <style>body{font-family:sans-serif;text-align:center;padding-top:30px;} img,canvas{margin:auto;}</style>
            </head><body>${printContents}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => { w.print(); w.close(); }, 300);
    });
});
</script>
</body>
</html>