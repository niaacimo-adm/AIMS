<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Any logged-in employee can view this page — no special ICT permission needed.
if (!isset($_SESSION['emp_id'])) { header('Location: ../login.php'); exit; }
$emp_id = $_SESSION['emp_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NIA-ACIMO | My ICT Equipment</title>
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

/* ═══ EQUIPMENT CARD (currently assigned) ═══ */
.eq-card {
  background:var(--rr-surface-2);border:2px solid var(--rr-border);border-radius:var(--rr-radius);
  transition:all .2s;height:100%;
}
.eq-card:hover { border-color:var(--rr-primary);box-shadow:var(--rr-shadow);transform:translateY(-2px);background:var(--rr-surface); }
.eq-card .card-body { background:transparent!important; }
.eq-card-icon { width:38px;height:38px;border-radius:9px;background:var(--rr-primary-lt);color:var(--rr-primary);
  display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:8px; }
.eq-card .card-title { font-family:var(--rr-font-h);font-weight:700;font-size:1rem;color:var(--rr-text); }
.eq-card table th { color:var(--rr-text-muted);font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em; }
.eq-card table td { color:var(--rr-text);font-size:.84rem; }
.eq-card .card-footer { background:var(--rr-surface)!important;border-top:1px solid var(--rr-border-sub)!important; }

/* ═══ BADGES ═══ */
.badge { border-radius:20px!important;font-size:.7rem!important;font-weight:700!important;padding:.3em .75em!important; }

/* ═══ BUTTONS ═══ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-outline-primary { border:1.5px solid var(--rr-primary)!important;color:var(--rr-primary)!important;background:transparent!important; }
.btn-outline-primary:hover { background:var(--rr-primary)!important;color:#fff!important; }
.btn-secondary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }

/* ═══ MODALS ═══ */
.modal-content { border:none!important;border-radius:var(--rr-radius-lg)!important;box-shadow:var(--rr-shadow-lg)!important;overflow:hidden;background:var(--rr-surface)!important; }
.modal-header { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;padding:1.1rem 1.5rem!important; }
.modal-title  { font-family:var(--rr-font-h)!important;font-weight:700!important;font-size:1rem!important;color:#fff!important; }
.modal-header .close { color:rgba(255,255,255,.8)!important;text-shadow:none!important;font-size:1.4rem; }
.modal-body  { padding:1.5rem!important;background:var(--rr-surface)!important; }

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

/* ═══ MISC ═══ */
.empty-state { text-align:center;padding:36px 20px;color:var(--rr-text-muted); }
.empty-state i { font-size:2.2rem;margin-bottom:10px;opacity:.35; }
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>

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
      <h1 class="pg-hero-title"><i class="fas fa-laptop"></i> My ICT Equipment</h1>
      <p class="pg-hero-sub">Equipment currently issued to you, and your full assignment history.</p>
      <div class="pg-hero-divider"></div>
    </div>
  </div>

  <!-- ── Content ───────────────────────────────────────────── -->
  <section class="content">

    <div class="row" id="myEquipmentCards">
      <div class="col-12"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div></div>
    </div>

    <div class="card mt-4">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i> My Assignment History</h3>
      </div>
      <div class="card-body">
        <table id="historyTable" class="table table-bordered table-striped" style="width:100%">
          <thead>
            <tr>
              <th>Asset Tag</th><th>Equipment</th><th>Assigned Date</th>
              <th>Returned Date</th><th>Status</th>
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

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-qrcode mr-2"></i>Equipment QR Label</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body text-center">
        <div id="qrCodeCanvas" class="d-flex justify-content-center mb-2"></div>
        <h5 id="qrAssetTag" class="mb-0"></h5>
        <p id="qrEquipmentName" class="text-muted"></p>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
    // Currently assigned equipment — shown as cards
    $.post('ict_ajax.php', { action: 'get_my_equipment' }, function(res) {
        if (!res.success) return;
        if (res.data.length === 0) {
            $('#myEquipmentCards').html('<div class="col-12"><div class="empty-state alert alert-light border"><i class="fas fa-laptop"></i><p>You currently have no ICT equipment assigned to you.</p></div></div>');
            return;
        }
        let html = '';
        res.data.forEach(function(item) {
            html += `
            <div class="col-md-4 mb-3">
              <div class="card eq-card">
                <div class="card-body">
                  <div class="eq-card-icon"><i class="fas fa-desktop"></i></div>
                  <h5 class="card-title mb-1">${item.equipment_name}</h5>
                  <span class="badge badge-secondary mb-2">${item.asset_tag}</span>
                  <table class="table table-sm table-borderless mb-2">
                    <tr><th width="110">Category</th><td>${item.category_name || '-'}</td></tr>
                    <tr><th>Brand/Model</th><td>${(item.brand||'')} ${(item.model||'')}</td></tr>
                    <tr><th>Serial No.</th><td>${item.serial_number || '-'}</td></tr>
                    <tr><th>Condition</th><td>${item.condition_on_assign || item.condition_status}</td></tr>
                    <tr><th>Assigned</th><td>${item.assigned_date}</td></tr>
                    <tr><th>Expected Return</th><td>${item.expected_return_date || 'No fixed date'}</td></tr>
                  </table>
                </div>
                <div class="card-footer">
                  <button class="btn btn-sm btn-outline-primary btnViewQr" data-tag="${item.asset_tag}" data-name="${item.equipment_name}">
                    <i class="fas fa-qrcode"></i> View QR
                  </button>
                </div>
              </div>
            </div>`;
        });
        $('#myEquipmentCards').html(html);
    }, 'json');

    // Full personal history table
    $('#historyTable').DataTable({
        ajax: { url: 'ict_ajax.php', type: 'POST', data: { action: 'get_my_equipment_history' }, dataSrc: 'data' },
        columns: [
            { data: 'asset_tag', render: d => `<span class="badge badge-secondary">${d}</span>` },
            { data: 'equipment_name' },
            { data: 'assigned_date' },
            { data: 'returned_date', defaultContent: '-' },
            {
                data: 'status',
                render: s => `<span class="badge badge-${s === 'Assigned' ? 'warning' : 'success'}">${s}</span>`
            }
        ]
    });

    $(document).on('click', '.btnViewQr', function() {
        const tag = $(this).data('tag');
        const name = $(this).data('name');
        $('#qrCodeCanvas').empty();
        $('#qrAssetTag').text(tag);
        $('#qrEquipmentName').text(name);
        const scanUrl = window.location.origin + window.location.pathname.replace('my_ict_equipment.php', 'ict_scanner.php');
        new QRCode(document.getElementById('qrCodeCanvas'), {
            text: scanUrl + '?tag=' + encodeURIComponent(tag),
            width: 160,
            height: 160
        });
        $('#qrModal').modal('show');
    });
});
</script>
</body>
</html>