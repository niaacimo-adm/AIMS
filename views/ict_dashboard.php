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
<title>NIA-ACIMO | ICT Equipment Dashboard</title>
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
  --ic-blue-bg:#dbeafe;   --ic-blue-fg:var(--rr-primary);
  --ic-green-bg:#d1fae5;  --ic-green-fg:var(--rr-success);
  --ic-yellow-bg:#fef3c7; --ic-yellow-fg:var(--rr-warning);
  --ic-red-bg:#fee2e2;    --ic-red-fg:var(--rr-danger);
  --ic-purple-bg:#ede9fe; --ic-purple-fg:var(--rr-purple);
  --ic-cyan-bg:#cffafe;   --ic-cyan-fg:var(--rr-cyan);
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
  --ic-blue-bg:rgba(37,99,235,.18);   --ic-blue-fg:#93c5fd;
  --ic-green-bg:rgba(16,185,129,.15); --ic-green-fg:#6ee7b7;
  --ic-yellow-bg:rgba(245,158,11,.15);--ic-yellow-fg:#fcd34d;
  --ic-red-bg:rgba(239,68,68,.15);    --ic-red-fg:#fca5a5;
  --ic-purple-bg:rgba(124,58,237,.15);--ic-purple-fg:#c4b5fd;
  --ic-cyan-bg:rgba(8,145,178,.15);   --ic-cyan-fg:#67e8f9;
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

/* ═══ STAT CARDS ═══ */
.stat-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:1.5rem; }
@media(max-width:768px){ .stat-row{grid-template-columns:repeat(2,1fr);} }
.stat-card {
  background:var(--rr-surface);border:1px solid var(--rr-border);border-radius:var(--rr-radius);
  padding:16px 20px;box-shadow:var(--rr-shadow-sm);display:flex;align-items:center;gap:14px;
  transition:box-shadow .2s,transform .2s;
}
.stat-card:hover { box-shadow:var(--rr-shadow);transform:translateY(-1px); }
.stat-card-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
.stat-card-body { flex:1; }
.stat-card-label { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--rr-text-muted);margin-bottom:2px; }
.stat-card-val { font-size:1.7rem;font-weight:800;font-family:var(--rr-font-h);line-height:1; }
.stat-ic-blue   { background:var(--ic-blue-bg);color:var(--ic-blue-fg); }
.stat-ic-green  { background:var(--ic-green-bg);color:var(--ic-green-fg); }
.stat-ic-yellow { background:var(--ic-yellow-bg);color:var(--ic-yellow-fg); }
.stat-ic-red    { background:var(--ic-red-bg);color:var(--ic-red-fg); }

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

/* ═══ BUTTONS ═══ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-primary   { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;color:#fff!important;box-shadow:0 2px 8px rgba(37,99,235,.3)!important; }
.btn-primary:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,.4)!important; }
.btn-success   { background:linear-gradient(135deg,var(--rr-success),#059669)!important;border:none!important;color:#fff!important; }

/* ═══ TABLES ═══ */
.table { font-size:.85rem;color:var(--rr-text); }
.table thead th { background:var(--rr-surface-2)!important;border-bottom:2px solid var(--rr-border)!important;font-weight:700;font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;color:var(--rr-text-muted)!important;padding:.75rem 1rem;white-space:nowrap; }
.table tbody td { padding:.65rem 1rem;border-color:var(--rr-border-sub)!important;vertical-align:middle;color:var(--rr-text); }
.table-striped tbody tr:hover td { background:var(--rr-primary-lt)!important; }
body.dark-mode .table-striped tbody tr:hover td { background:rgba(37,99,235,.1)!important; }

/* ═══ MISC ═══ */
.empty-state { text-align:center;padding:36px 20px;color:var(--rr-text-muted); }
.empty-state i { font-size:2.2rem;margin-bottom:10px;opacity:.35; }
.empty-state p { margin:0;font-size:.88rem; }
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
      <h1 class="pg-hero-title"><i class="fas fa-desktop"></i> ICT Equipment Dashboard</h1>
      <p class="pg-hero-sub">Monitor equipment status, assignments, and inventory health in real time.</p>
      <div class="pg-hero-divider"></div>
      <div class="pg-hero-actions">
        <a href="ict_scanner.php" class="pg-hero-btn"><i class="fas fa-qrcode"></i> Scan QR Code</a>
        <?php if ($can_manage): ?>
        <a href="ict_equipment.php" class="pg-hero-btn"><i class="fas fa-plus"></i> Add Equipment</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Content ───────────────────────────────────────────── -->
  <section class="content">

    <div class="stat-row mt-4">
      <div class="stat-card">
        <div class="stat-card-icon stat-ic-blue"><i class="fas fa-desktop"></i></div>
        <div class="stat-card-body">
          <div class="stat-card-label">Total Equipment</div>
          <div class="stat-card-val" id="statTotal">–</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon stat-ic-green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-card-body">
          <div class="stat-card-label">Available</div>
          <div class="stat-card-val" id="statAvailable">–</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon stat-ic-yellow"><i class="fas fa-user-tag"></i></div>
        <div class="stat-card-body">
          <div class="stat-card-label">Assigned</div>
          <div class="stat-card-val" id="statAssigned">–</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon stat-ic-red"><i class="fas fa-tools"></i></div>
        <div class="stat-card-body">
          <div class="stat-card-label">Under Repair</div>
          <div class="stat-card-val" id="statRepair">–</div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card card-outline">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i> Recent Assignments</h3></div>
          <div class="card-body p-0">
            <table class="table table-striped mb-0">
              <thead>
                <tr><th>Date</th><th>Asset Tag</th><th>Equipment</th><th>Employee</th></tr>
              </thead>
              <tbody id="recentAssignmentsBody">
                <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<?php include '../includes/footer.php'; ?>
</div>
<?php include '../includes/mainfooter.php'; ?>

<script>
$(function() {
    $.post('ict_ajax.php', { action: 'dashboard_stats' }, function(res) {
        if (!res.success) return;
        $('#statTotal').text(res.stats.total);
        $('#statAvailable').text(res.stats.available);
        $('#statAssigned').text(res.stats.assigned);
        $('#statRepair').text(res.stats.under_repair);

        let rows = '';
        if (res.recent_assignments.length === 0) {
            rows = '<tr><td colspan="4" class="text-center text-muted">No assignments yet.</td></tr>';
        } else {
            res.recent_assignments.forEach(function(a) {
                rows += `<tr>
                    <td>${a.assigned_date}</td>
                    <td><span class="badge badge-secondary">${a.asset_tag}</span></td>
                    <td>${a.equipment_name}</td>
                    <td>${a.employee_name}</td>
                </tr>`;
            });
        }
        $('#recentAssignmentsBody').html(rows);
    }, 'json');
});
</script>
</body>
</html>