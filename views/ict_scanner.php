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

// Support a shared/deep link like ict_scanner.php?tag=ICT-2026-0001 (e.g. scanned by a phone camera app)
$preload_tag = trim($_GET['tag'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NIA-ACIMO | QR Scanner</title>
<?php include '../includes/header.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
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

/* ═══ QR READER ═══ */
#qr-reader { border-radius:var(--rr-radius); overflow:hidden; border:1.5px dashed var(--rr-border); }

/* ═══ RESULT AREA ═══ */
.empty-state { text-align:center;padding:36px 20px;color:var(--rr-text-muted); }
.empty-state i { font-size:2.2rem;margin-bottom:10px;opacity:.35; }
.alert-light { background:var(--rr-surface-2)!important;border:1px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }
.table-sm.table-borderless th { color:var(--rr-text-muted);font-weight:700;font-size:.76rem;text-transform:uppercase;letter-spacing:.05em; }
.table-sm.table-borderless td { color:var(--rr-text); }

/* ═══ BADGES ═══ */
.badge { border-radius:20px!important;font-size:.7rem!important;font-weight:700!important;padding:.3em .75em!important; }

/* ═══ FORMS ═══ */
.form-control {
  background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;
  border-radius:var(--rr-radius-sm)!important;color:var(--rr-text)!important;
  font-family:var(--rr-font)!important;font-size:.875rem!important;padding:.5rem .75rem!important;
  transition:border-color .15s,box-shadow .15s;
}
.form-control:focus { border-color:var(--rr-primary)!important;box-shadow:0 0 0 3px rgba(37,99,235,.12)!important;background:var(--rr-surface)!important; }

/* ═══ BUTTONS ═══ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-primary   { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;color:#fff!important;box-shadow:0 2px 8px rgba(37,99,235,.3)!important; }
.btn-primary:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,.4)!important; }
.btn-success   { background:linear-gradient(135deg,var(--rr-success),#059669)!important;border:none!important;color:#fff!important; }
.btn-secondary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }
.btn-secondary:hover { background:var(--rr-border)!important;color:var(--rr-text)!important; }
.btn-outline-secondary { border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important;background:transparent!important; }
.btn-outline-secondary:hover { background:var(--rr-surface-2)!important;color:var(--rr-text)!important; }
.btn-outline-danger { border:1.5px solid var(--rr-danger)!important;color:var(--rr-danger)!important;background:transparent!important; }
.btn-sm { font-size:.8rem!important; }
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
      <h1 class="pg-hero-title"><i class="fas fa-qrcode"></i> QR Scanner</h1>
      <p class="pg-hero-sub">Scan an asset label or enter its tag to pull up details instantly.</p>
      <div class="pg-hero-divider"></div>
    </div>
  </div>

  <!-- ── Content ───────────────────────────────────────────── -->
  <section class="content">
    <div class="row mt-4">
      <div class="col-md-5">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Scan with Camera</h3></div>
          <div class="card-body">
            <div id="qr-reader" style="width:100%;"></div>
            <button class="btn btn-outline-secondary btn-sm mt-2" id="btnToggleScan">Start Camera</button>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Or Enter Asset Tag Manually</h3></div>
          <div class="card-body">
            <form id="manualForm" class="form-inline">
              <input type="text" class="form-control mr-2" id="manualTag" placeholder="e.g. ICT-2026-0001" style="flex:1;">
              <button type="submit" class="btn btn-primary mt-2 mt-md-0"><i class="fas fa-search mr-1"></i>Look Up</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="card-title">Or Upload QR Image</h3></div>
          <div class="card-body">
            <div class="form-group mb-2">
              <input type="file" accept="image/*" class="form-control-file" id="qrFileInput">
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btnScanFile"><i class="fas fa-upload mr-1"></i>Scan Uploaded Image</button>
            <div id="qr-reader-file" style="display:none;"></div>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="card" id="resultCard" style="display:none;">
          <div class="card-header"><h3 class="card-title">Equipment Details</h3></div>
          <div class="card-body" id="resultBody"></div>
          <div class="card-footer" id="resultActions" style="background:var(--rr-surface-2);border-top:1px solid var(--rr-border-sub);"></div>
        </div>
        <div class="empty-state alert alert-light border" id="emptyState">
          <i class="fas fa-qrcode"></i>
          <p>Scan a QR code or enter an asset tag to view equipment details, current custodian, and quick assign/return actions.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>
</div>
<?php include '../includes/mainfooter.php'; ?>

<script>
const CAN_MANAGE = <?= $can_manage ? 'true' : 'false' ?>;
const PRELOAD_TAG = <?= json_encode($preload_tag) ?>;
let html5QrCode = null;
let scanning = false;
let html5QrCodeFile = null;

function lookupTag(tag) {
    if (!tag) return;
    $.post('ict_ajax.php', { action: 'lookup_by_qr', tag: tag }, function(res) {
        $('#emptyState').hide();
        $('#resultCard').show();
        if (!res.success) {
            $('#resultBody').html(`<div class="alert alert-danger mb-0">${res.message}</div>`);
            $('#resultActions').html('');
            return;
        }
        const e = res.equipment;
        const a = res.current_assignment;
        const statusMap = { Available: 'success', Assigned: 'warning', 'Under Repair': 'danger', Retired: 'secondary', Lost: 'dark' };

        let html = `
            <table class="table table-sm table-borderless mb-2">
                <tr><th width="160">Asset Tag</th><td><span class="badge badge-secondary">${e.asset_tag}</span></td></tr>
                <tr><th>Equipment</th><td>${e.equipment_name}</td></tr>
                <tr><th>Category</th><td>${e.category_name || '-'}</td></tr>
                <tr><th>Brand / Model</th><td>${(e.brand || '')} ${(e.model || '')}</td></tr>
                <tr><th>Serial No.</th><td>${e.serial_number || '-'}</td></tr>
                <tr><th>Condition</th><td>${e.condition_status}</td></tr>
                <tr><th>Status</th><td><span class="badge badge-${statusMap[e.status] || 'secondary'}">${e.status}</span></td></tr>
                <tr><th>Custodian Office</th><td>${e.office_name || '-'}</td></tr>
            </table>`;

        if (a) {
            html += `<div class="alert alert-warning mb-0"><strong>Currently assigned to:</strong> ${a.employee_name}<br>
                      Since ${a.assigned_date}${a.expected_return_date ? ' — expected return ' + a.expected_return_date : ''}</div>`;
        }
        $('#resultBody').html(html);

        let actions = `<a href="ict_equipment.php" class="btn btn-sm btn-secondary">View in Inventory</a>`;
        if (CAN_MANAGE) {
            if (e.status === 'Available') {
                actions += ` <a href="ict_assignments.php" class="btn btn-sm btn-primary">Assign This Item</a>`;
            } else if (a) {
                actions += ` <button class="btn btn-sm btn-success btnQuickReturn" data-assignment-id="${a.id}">Return This Item</button>`;
            }
        }
        $('#resultActions').html(actions);
    }, 'json');
}

$(function() {
    if (PRELOAD_TAG) {
        $('#manualTag').val(PRELOAD_TAG);
        lookupTag(PRELOAD_TAG);
    }

    $('#manualForm').on('submit', function(e) {
        e.preventDefault();
        lookupTag($('#manualTag').val().trim());
    });

    $(document).on('click', '.btnQuickReturn', function() {
        const assignment_id = $(this).data('assignment-id');
        Swal.fire({
            title: 'Return this equipment?',
            input: 'select',
            inputOptions: { Available: 'Available', 'Under Repair': 'Under Repair', Retired: 'Retired' },
            inputValue: 'Available',
            showCancelButton: true,
            confirmButtonText: 'Confirm Return'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ict_ajax.php', {
                    action: 'return_equipment',
                    assignment_id: assignment_id,
                    new_status: result.value,
                    condition_on_return: 'Good'
                }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        lookupTag($('#manualTag').val().trim() || PRELOAD_TAG);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        });
    });

    $('#btnScanFile').on('click', function() {
        const fileInput = document.getElementById('qrFileInput');
        if (!fileInput.files || !fileInput.files.length) {
            toastr.warning('Please choose an image file first.');
            return;
        }
        const file = fileInput.files[0];

        // Stop the live camera scan first, if running, to avoid device conflicts
        const proceed = () => {
            if (!html5QrCodeFile) { html5QrCodeFile = new Html5Qrcode("qr-reader-file"); }
            html5QrCodeFile.scanFile(file, true)
                .then(function(decodedText) {
                    $('#manualTag').val(decodedText);
                    lookupTag(decodedText);
                })
                .catch(function() {
                    toastr.error('Could not detect a QR code in that image.');
                });
        };

        if (scanning) {
            html5QrCode.stop().then(() => {
                scanning = false;
                $('#btnToggleScan').text('Start Camera').removeClass('btn-outline-danger').addClass('btn-outline-secondary');
                proceed();
            });
        } else {
            proceed();
        }
    });

    $('#btnToggleScan').on('click', function() {
        if (!scanning) {
            html5QrCode = new Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 220 },
                function(decodedText) {
                    lookupTag(decodedText);
                }
            ).then(() => {
                scanning = true;
                $('#btnToggleScan').text('Stop Camera').removeClass('btn-outline-secondary').addClass('btn-outline-danger');
            }).catch(() => {
                toastr.error('Unable to access camera. You can still use manual entry below.');
            });
        } else {
            html5QrCode.stop().then(() => {
                scanning = false;
                $('#btnToggleScan').text('Start Camera').removeClass('btn-outline-danger').addClass('btn-outline-secondary');
            });
        }
    });
});
</script>
</body>
</html>