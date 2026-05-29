<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
checkPermission('view_calendar'); // reuse calendar permission or create 'view_room_reservation'
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-ACIMO | Room Reservation</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
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
  /* icon bg light */
  --ic-blue-bg:#dbeafe; --ic-blue-fg:var(--rr-primary);
  --ic-green-bg:#d1fae5;--ic-green-fg:var(--rr-success);
  --ic-yellow-bg:#fef3c7;--ic-yellow-fg:var(--rr-warning);
  --ic-purple-bg:#ede9fe;--ic-purple-fg:var(--rr-purple);
  --ic-cyan-bg:#cffafe; --ic-cyan-fg:var(--rr-cyan);
  /* avail colours */
  --av-free:#d1fae5;    --av-free-txt:#065f46;
  --av-busy:#fee2e2;    --av-busy-txt:#7f1d1d;
  --av-warn:#fef3c7;    --av-warn-txt:#92400e;
}

/* ═══════════════════════════════════════════════════
   DARK MODE — detect body.dark-mode (AdminLTE pattern)
═══════════════════════════════════════════════════ */
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
  --ic-blue-bg:rgba(37,99,235,.18);  --ic-blue-fg:#93c5fd;
  --ic-green-bg:rgba(16,185,129,.15);--ic-green-fg:#6ee7b7;
  --ic-yellow-bg:rgba(245,158,11,.15);--ic-yellow-fg:#fcd34d;
  --ic-purple-bg:rgba(124,58,237,.15);--ic-purple-fg:#c4b5fd;
  --ic-cyan-bg:rgba(8,145,178,.15);  --ic-cyan-fg:#67e8f9;
  --av-free:#052e16;  --av-free-txt:#6ee7b7;
  --av-busy:#450a0a;  --av-busy-txt:#fca5a5;
  --av-warn:#451a03;  --av-warn-txt:#fcd34d;
}

/* ═══════════════════════════════════════════════════
   BASE
═══════════════════════════════════════════════════ */
body,.content-wrapper { background:var(--rr-bg)!important; font-family:var(--rr-font)!important; }
.content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

/* ═══════════════════════════════════════════════════
   HERO
═══════════════════════════════════════════════════ */
@keyframes meshDrift  { 0%{transform:translate(0,0) rotate(0)} 100%{transform:translate(3%,2%) rotate(2deg)} }
@keyframes orbFloat   { 0%,100%{opacity:.4;transform:translate(0,0) scale(1)} 33%{opacity:.7;transform:translate(18px,-26px) scale(1.05)} 66%{opacity:.5;transform:translate(-12px,16px) scale(.95)} }
@keyframes ringPulse  { 0%,100%{opacity:.45;transform:scale(1)} 50%{opacity:.85;transform:scale(1.04)} }

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
.pg-hero-rings { position:absolute;top:50%;right:6%;transform:translateY(-50%);width:240px;height:240px;pointer-events:none;z-index:0; }
.pg-ring { position:absolute;inset:0;border-radius:50%;border:1px solid rgba(36,231,143,.10);animation:ringPulse 4s ease-in-out infinite; }
.pg-ring:nth-child(2){inset:28px;animation-delay:.8s;opacity:.7;}
.pg-ring:nth-child(3){inset:56px;animation-delay:1.6s;opacity:.5;}
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
.pg-hero-bc-link { color:rgba(212,245,229,.65);text-decoration:none;font-size:.8rem; }
.pg-hero-bc-link:hover { color:#24e78f; }
.pg-hero-bc-active { color:rgba(212,245,229,.9);font-size:.8rem; }

/* ═══════════════════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════════════════ */
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
.stat-ic-pending  { background:rgba(245,158,11,.15);color:var(--rr-warning); }
.stat-ic-approved { background:rgba(16,185,129,.15);color:var(--rr-success); }
.stat-ic-rooms    { background:var(--ic-blue-bg);color:var(--ic-blue-fg); }
.stat-ic-today    { background:rgba(124,58,237,.15);color:var(--rr-purple); }
.stat-val-pending  { color:var(--rr-warning); }
.stat-val-approved { color:var(--rr-success); }
.stat-val-rooms    { color:var(--rr-primary); }
.stat-val-today    { color:var(--rr-purple); }

/* ═══════════════════════════════════════════════════
   CARDS
═══════════════════════════════════════════════════ */
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

/* ═══════════════════════════════════════════════════
   ROOM GRID
═══════════════════════════════════════════════════ */
.rooms-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px; }
.room-card {
  background:var(--rr-surface-2);border:2px solid var(--rr-border);border-radius:var(--rr-radius);
  padding:16px;cursor:pointer;transition:all .2s;position:relative;
}
.room-card:hover { border-color:var(--rr-primary);box-shadow:var(--rr-shadow);transform:translateY(-2px);background:var(--rr-surface); }
.room-card.selected { border-color:var(--rr-primary);background:var(--rr-primary-lt); }
body.dark-mode .room-card.selected { background:rgba(37,99,235,.12); }
.rc-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;margin-bottom:10px; }
.rc-icon-blue   { background:var(--ic-blue-bg);color:var(--ic-blue-fg); }
.rc-icon-green  { background:var(--ic-green-bg);color:var(--ic-green-fg); }
.rc-icon-yellow { background:var(--ic-yellow-bg);color:var(--ic-yellow-fg); }
.rc-icon-purple { background:var(--ic-purple-bg);color:var(--ic-purple-fg); }
.rc-icon-cyan   { background:var(--ic-cyan-bg);color:var(--ic-cyan-fg); }
.rc-name { font-weight:700;font-size:.88rem;color:var(--rr-text);margin-bottom:4px;line-height:1.3; }
.rc-cap  { font-size:.74rem;color:var(--rr-text-muted); }
.rc-dot  { position:absolute;top:10px;right:10px;width:9px;height:9px;border-radius:50%; }
.rc-dot-free { background:var(--rr-success);box-shadow:0 0 0 2px rgba(16,185,129,.25); }
.rc-dot-busy { background:var(--rr-danger);box-shadow:0 0 0 2px rgba(239,68,68,.25); }
.rc-dot-maint{ background:var(--rr-warning);box-shadow:0 0 0 2px rgba(245,158,11,.25); }

/* ═══════════════════════════════════════════════════
   AVAILABILITY INLINE BADGE (auto-validates in modal)
═══════════════════════════════════════════════════ */
.avail-banner {
  display:none;border-radius:var(--rr-radius-sm);padding:10px 14px;font-size:.84rem;font-weight:600;
  display:flex;align-items:flex-start;gap:8px;margin-top:12px;
}
.avail-banner.free    { background:var(--av-free);color:var(--av-free-txt);display:flex; }
.avail-banner.busy    { background:var(--av-busy);color:var(--av-busy-txt);display:flex; }
.avail-banner.checking{ background:var(--av-warn);color:var(--av-warn-txt);display:flex; }
.avail-banner.hidden  { display:none!important; }
.avail-banner i { margin-top:2px;flex-shrink:0; }
.avail-conflict-list  { font-size:.78rem;margin-top:4px;opacity:.85; }

/* ═══════════════════════════════════════════════════
   SCHEDULE TIMELINE
═══════════════════════════════════════════════════ */
.sched-wrap { display:flex;flex-direction:column;gap:6px;margin-top:4px; }
.sched-slot { display:grid;grid-template-columns:60px 1fr;gap:8px;align-items:center; }
.sched-time { font-size:.72rem;font-weight:700;color:var(--rr-text-muted);text-align:right; }
.sched-bar  { height:26px;border-radius:5px;background:var(--rr-border);position:relative;overflow:hidden; }
.sched-fill { position:absolute;inset:0;border-radius:5px;display:flex;align-items:center;
  padding-left:8px;font-size:.68rem;font-weight:700;white-space:nowrap;overflow:hidden; }
.sf-approved { background:#10b981;color:#fff; }
.sf-pending  { background:#f59e0b;color:#fff; }
.sf-free     { background:rgba(16,185,129,.12);color:var(--rr-success);border:1px dashed rgba(16,185,129,.4); }

/* ═══════════════════════════════════════════════════
   MY RESERVATIONS FEED
═══════════════════════════════════════════════════ */
.res-feed { display:flex;flex-direction:column; }
.res-feed-item {
  padding:12px 16px;border-bottom:1px solid var(--rr-border-sub);cursor:pointer;
  transition:background .15s;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;
}
.res-feed-item:last-child { border-bottom:none; }
.res-feed-item:hover { background:var(--rr-primary-lt); }
body.dark-mode .res-feed-item:hover { background:rgba(37,99,235,.1); }
.res-feed-title { font-weight:700;font-size:.86rem;color:var(--rr-text);margin-bottom:2px; }
.res-feed-meta  { font-size:.74rem;color:var(--rr-text-muted); }
.res-feed-meta i { margin-right:3px; }

/* ═══════════════════════════════════════════════════
   ROOMS CRUD SECTION (inline on page)
═══════════════════════════════════════════════════ */
.crud-tabs { display:flex;gap:0;border-bottom:2px solid var(--rr-border);margin-bottom:16px; }
.crud-tab {
  padding:8px 18px;font-size:.83rem;font-weight:700;color:var(--rr-text-muted);cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;
}
.crud-tab.active { color:var(--rr-primary);border-bottom-color:var(--rr-primary); }
.crud-tab:hover  { color:var(--rr-text); }
.crud-pane { display:none; }
.crud-pane.active { display:block; }

/* room crud form */
.rform {
  background:var(--rr-surface-2);border:1px solid var(--rr-border);border-radius:var(--rr-radius);
  padding:20px;margin-bottom:16px;
}
.rform-title { font-family:var(--rr-font-h);font-size:.9rem;font-weight:700;color:var(--rr-text);margin-bottom:14px;
  display:flex;align-items:center;gap:8px; }
.rform-title .badge-edit { background:var(--rr-warning);color:#fff;font-size:.68rem;padding:3px 8px;border-radius:20px; }

/* color swatch picker */
.color-swatches { display:flex;gap:8px;flex-wrap:wrap;margin-top:4px; }
.color-swatch {
  width:32px;height:32px;border-radius:8px;cursor:pointer;border:2px solid transparent;
  transition:all .15s;display:flex;align-items:center;justify-content:center;font-size:.8rem;
}
.color-swatch.active { border-color:var(--rr-text);box-shadow:0 0 0 2px var(--rr-surface),0 0 0 4px var(--rr-primary); }

/* rooms manage table */
.rm-table { width:100%;border-collapse:separate;border-spacing:0; }
.rm-table thead th {
  background:var(--rr-surface-2)!important;border-bottom:2px solid var(--rr-border)!important;
  font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  color:var(--rr-text-muted);padding:.7rem 1rem;white-space:nowrap;
}
.rm-table tbody td { padding:.7rem 1rem;border-bottom:1px solid var(--rr-border-sub);vertical-align:middle;color:var(--rr-text); }
.rm-table tbody tr:hover td { background:var(--rr-primary-lt); }
body.dark-mode .rm-table tbody tr:hover td { background:rgba(37,99,235,.1); }

/* ═══════════════════════════════════════════════════
   AMENITY CHIPS
═══════════════════════════════════════════════════ */
.am-chip {
  display:inline-flex;align-items:center;gap:4px;background:var(--rr-primary-lt);
  color:var(--rr-primary);border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:600;margin:2px;
}
body.dark-mode .am-chip { background:rgba(37,99,235,.18);color:#93c5fd; }

/* ═══════════════════════════════════════════════════
   BADGES
═══════════════════════════════════════════════════ */
.badge { border-radius:20px!important;font-size:.7rem!important;font-weight:700!important;padding:.3em .75em!important; }
.badge-pending   { background:#fef3c7!important;color:#92400e!important; }
.badge-approved  { background:#d1fae5!important;color:#065f46!important; }
.badge-rejected  { background:#fee2e2!important;color:#7f1d1d!important; }
.badge-cancelled { background:var(--rr-border)!important;color:var(--rr-text-2)!important; }
.badge-active    { background:#d1fae5!important;color:#065f46!important; }
.badge-inactive  { background:var(--rr-border)!important;color:var(--rr-text-2)!important; }
.badge-maintenance{ background:#fef3c7!important;color:#92400e!important; }
body.dark-mode .badge-pending    { background:rgba(245,158,11,.2)!important;color:#fcd34d!important; }
body.dark-mode .badge-approved   { background:rgba(16,185,129,.2)!important;color:#6ee7b7!important; }
body.dark-mode .badge-rejected   { background:rgba(239,68,68,.2)!important;color:#fca5a5!important; }
body.dark-mode .badge-cancelled  { background:rgba(148,163,184,.1)!important;color:var(--rr-text-muted)!important; }
body.dark-mode .badge-active     { background:rgba(16,185,129,.2)!important;color:#6ee7b7!important; }
body.dark-mode .badge-inactive   { background:rgba(148,163,184,.1)!important;color:var(--rr-text-muted)!important; }
body.dark-mode .badge-maintenance{ background:rgba(245,158,11,.2)!important;color:#fcd34d!important; }

/* ═══════════════════════════════════════════════════
   FORMS
═══════════════════════════════════════════════════ */
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

/* ═══════════════════════════════════════════════════
   BUTTONS
═══════════════════════════════════════════════════ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-primary   { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;color:#fff!important;box-shadow:0 2px 8px rgba(37,99,235,.3)!important; }
.btn-primary:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,.4)!important; }
.btn-success   { background:linear-gradient(135deg,var(--rr-success),#059669)!important;border:none!important;color:#fff!important; }
.btn-danger    { background:linear-gradient(135deg,var(--rr-danger),#dc2626)!important;border:none!important;color:#fff!important; }
.btn-warning   { background:linear-gradient(135deg,var(--rr-warning),#d97706)!important;border:none!important;color:#fff!important; }
.btn-secondary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }
.btn-secondary:hover { background:var(--rr-border)!important;color:var(--rr-text)!important; }
.btn-xs { font-size:.72rem!important;padding:.25rem .55rem!important; }
.btn-outline-primary { border:1.5px solid var(--rr-primary)!important;color:var(--rr-primary)!important;background:transparent!important; }
.btn-outline-primary:hover { background:var(--rr-primary)!important;color:#fff!important; }

/* ═══════════════════════════════════════════════════
   CALENDAR
═══════════════════════════════════════════════════ */
.fc-event { border-radius:5px!important;border:none!important;font-size:.74rem!important;font-weight:600!important;padding:2px 6px!important; }
.ev-pending  { background:#f59e0b!important; }
.ev-approved { background:#10b981!important; }
.ev-rejected { background:#ef4444!important;opacity:.8; }
.fc-day-today { background:var(--rr-primary-lt)!important; }
.fc-day-today .fc-daygrid-day-number {
  background:var(--rr-primary);color:#fff!important;border-radius:50%;width:26px;height:26px;
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;
}
.fc-col-header-cell {
  background:var(--rr-surface-2)!important;font-family:var(--rr-font)!important;
  font-size:.76rem!important;font-weight:700!important;color:var(--rr-text-muted)!important;
  text-transform:uppercase;letter-spacing:.07em;
}
.fc .fc-toolbar-title { font-family:var(--rr-font-h)!important;font-size:1.05rem!important;font-weight:800!important;color:var(--rr-text)!important; }
.fc .fc-button { border-radius:var(--rr-radius-sm)!important;font-family:var(--rr-font)!important;font-size:.78rem!important;font-weight:600!important;padding:.32rem .7rem!important; }
.fc .fc-button-primary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important;box-shadow:none!important; }
.fc .fc-button-primary:hover,
.fc .fc-button-primary:not(:disabled):active,
.fc .fc-button-primary:not(:disabled).fc-button-active { background:var(--rr-primary)!important;border-color:var(--rr-primary)!important;color:#fff!important; }
.fc .fc-today-button { background:linear-gradient(135deg,var(--rr-accent),#0891b2)!important;border-color:transparent!important;color:#fff!important; }
.fc td,.fc th { border-color:var(--rr-border-sub)!important; }
.fc-daygrid-day-number { font-size:.8rem;font-weight:500;color:var(--rr-text-2)!important;padding:5px 8px!important; }
.cal-legend { display:flex;flex-wrap:wrap;gap:8px 16px;padding:10px 14px;border-top:1px solid var(--rr-border-sub);font-size:.76rem;color:var(--rr-text-2); }
.cal-legend-item { display:flex;align-items:center;gap:5px; }
.cal-legend-dot { width:9px;height:9px;border-radius:50%;flex-shrink:0; }

/* ═══════════════════════════════════════════════════
   MODALS
═══════════════════════════════════════════════════ */
.modal-content { border:none!important;border-radius:var(--rr-radius-lg)!important;box-shadow:var(--rr-shadow-lg)!important;overflow:hidden;background:var(--rr-surface)!important; }
.modal-header { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;padding:1.1rem 1.5rem!important; }
.modal-title  { font-family:var(--rr-font-h)!important;font-weight:700!important;font-size:1rem!important;color:#fff!important; }
.modal-header .close { color:rgba(255,255,255,.8)!important;text-shadow:none!important;font-size:1.4rem; }
.modal-body  { padding:1.5rem!important;background:var(--rr-surface)!important; }
.modal-footer { padding:1rem 1.5rem!important;border-top:1px solid var(--rr-border-sub)!important;background:var(--rr-surface-2)!important;display:flex;gap:.5rem;flex-wrap:wrap; }

/* ═══════════════════════════════════════════════════
   TABLES
═══════════════════════════════════════════════════ */
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

/* ═══════════════════════════════════════════════════
   MISC
═══════════════════════════════════════════════════ */
.section-label { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--rr-text-muted);margin-bottom:8px; }
.empty-state { text-align:center;padding:36px 20px;color:var(--rr-text-muted); }
.empty-state i { font-size:2.2rem;margin-bottom:10px;opacity:.35; }
.empty-state p { margin:0;font-size:.88rem; }
.sticky-panel { position:sticky;top:16px; }
.divider { height:1px;background:var(--rr-border-sub);margin:16px 0; }

/* Availability check inline spinner */
@keyframes spin { to{transform:rotate(360deg)} }
.spin-icon { animation:spin 1s linear infinite;display:inline-block; }
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
      <div class="pg-hero-rings">
        <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
    </div>
      <div class="pg-hero-inner">
        <h1 class="pg-hero-title"><i class="fas fa-door-open"></i> Room Reservation</h1>
        <p class="pg-hero-sub">Book conference rooms &amp; venues — check availability in real time.</p>
        <div class="pg-hero-divider"></div>
        <div class="pg-hero-actions">
          <button class="pg-hero-btn" data-toggle="modal" data-target="#reservationModal">
            <i class="fas fa-plus"></i> New Reservation
          </button>
          <?php if (hasPermission('manage_employees')): ?>
          <button class="pg-hero-btn" data-toggle="modal" data-target="#manageRoomsModal">
            <i class="fas fa-cog"></i> Manage Rooms
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Content ───────────────────────────────────────────── -->
    <section class="content">

      <!-- Stat row -->
      <div class="stat-row mt-4">
        <div class="stat-chip"><span class="sc-label">My Pending</span><span class="sc-val sc-pending" id="stat-pending">–</span></div>
        <div class="stat-chip"><span class="sc-label">My Approved</span><span class="sc-val sc-approved" id="stat-approved">–</span></div>
        <div class="stat-chip"><span class="sc-label">Total Rooms</span><span class="sc-val sc-total" id="stat-rooms">–</span></div>
        <div class="stat-chip"><span class="sc-label">Today's Bookings</span><span class="sc-val sc-total" id="stat-today">–</span></div>
      </div>

      <div class="row">

        <!-- ── LEFT: Room picker + Calendar ──────────────────── -->
        <div class="col-lg-8">

          <!-- Room grid -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">Available Rooms</h3>
              <div class="ml-auto d-flex align-items-center gap-2">
                <span class="badge badge-success" style="font-size:.7rem;">● Available</span>
                <span class="badge badge-danger ml-1" style="font-size:.7rem;">● Occupied Now</span>
              </div>
            </div>
            <div class="card-body">
              <div class="rooms-grid" id="rooms-grid">
                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading rooms…</p></div>
              </div>
            </div>
          </div>

          <!-- Calendar -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title" id="calendar-room-title">All Rooms — Reservation Calendar</h3>
            </div>
            <div class="card-body p-0">
              <div id="reservation-calendar" style="padding:12px;"></div>
            </div>
            <div class="cal-legend">
              <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#f59e0b"></span> Pending</div>
              <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#10b981"></span> Approved</div>
              <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#ef4444"></span> Rejected</div>
            </div>
          </div>

        </div>

        <!-- ── RIGHT: Room detail + My reservations ───────────── -->
        <div class="col-lg-4">

          <!-- Room detail panel -->
          <div class="card mb-3 sticky-top" id="room-detail-panel">
            <div class="card-header">
              <h3 class="card-title" id="rd-name">Room Details</h3>
            </div>
            <div class="card-body">
              <p class="mb-1" style="font-size:.82rem;color:var(--text-muted);" id="rd-desc">–</p>
              <div class="d-flex align-items-center gap-2 mb-2" style="gap:8px;">
                <span style="font-size:.82rem;"><i class="fas fa-users text-primary mr-1"></i><strong id="rd-cap">–</strong> capacity</span>
                <span class="ml-2" style="font-size:.82rem;"><i class="fas fa-map-marker-alt text-danger mr-1"></i><span id="rd-floor">–</span></span>
              </div>
              <div id="rd-amenities" class="mb-3"></div>
              <div class="section-title">Today's Schedule</div>
              <div class="timeline-wrap" id="rd-timeline">
                <div class="empty-state"><p>Select a room to view today's schedule.</p></div>
              </div>
              <button class="btn btn-primary btn-block mt-3" id="btn-reserve-room">
                <i class="fas fa-calendar-plus mr-1"></i> Reserve This Room
              </button>
            </div>
          </div>

        </div>
      </div>

      <!-- All Reservations Table (admin) -->
      <?php if (hasPermission('manage_employees')): ?>
      <div class="card mt-2 mb-4">
        <div class="card-header">
          <h3 class="card-title">All Reservations</h3>
          <div class="ml-auto">
            <select id="filter-status" class="form-control form-control-sm" style="width:140px;display:inline-block;">
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0">
          <table id="all-reservations-table" class="table table-hover table-bordered mb-0" style="width:100%">
            <thead>
              <tr>
                <th>Room</th>
                <th>Reserved By</th>
                <th>Purpose</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </section>
  </div>

  <?php include '../includes/footer.php'; ?>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  RESERVATION MODAL                                         -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="reservationModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus mr-2"></i>New Room Reservation</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label>Room <span class="text-danger">*</span></label>
              <select id="res-room" class="form-control select2-room" required>
                <option value="">— Select Room —</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Reservation Date <span class="text-danger">*</span></label>
              <input type="date" id="res-date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Start Time <span class="text-danger">*</span></label>
              <input type="time" id="res-start" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>End Time <span class="text-danger">*</span></label>
              <input type="time" id="res-end" class="form-control" required>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <label>Purpose / Title <span class="text-danger">*</span></label>
              <input type="text" id="res-purpose" class="form-control" placeholder="e.g. Team meeting, Training session…" required>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <label>Description</label>
              <textarea id="res-desc" class="form-control" placeholder="Optional details…"></textarea>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Number of Attendees</label>
              <input type="number" id="res-attendees" class="form-control" min="1" placeholder="e.g. 10">
            </div>
          </div>
        </div>
        <!-- Conflict alert -->
        <div id="conflict-alert" class="alert alert-danger" style="display:none;">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          <strong>Conflict!</strong> This room is already reserved during the selected time.
          <div id="conflict-detail" class="mt-1" style="font-size:.82rem;"></div>
        </div>
        <div id="avail-ok" class="alert alert-success" style="display:none;">
          <i class="fas fa-check-circle mr-1"></i> Room is available for the selected time!
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="btn-check-avail">
          <i class="fas fa-search mr-1"></i> Check Availability
        </button>
        <button type="button" class="btn btn-primary" id="btn-submit-reservation">
          <i class="fas fa-save mr-1"></i> Submit Reservation
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  VIEW RESERVATION MODAL                                    -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewReservationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Reservation Details</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="view-res-body">
        <!-- populated by JS -->
      </div>
      <div class="modal-footer" id="view-res-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  MANAGE ROOMS MODAL (admin only)                          -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if (hasPermission('manage_employees')): ?>
<div class="modal fade" id="manageRoomsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-cog mr-2"></i>Manage Rooms</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- Add/Edit form -->
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title" id="room-form-title">Add New Room</h3></div>
          <div class="card-body">
            <input type="hidden" id="edit-room-id">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Room Name <span class="text-danger">*</span></label>
                  <input type="text" id="room-name" class="form-control" placeholder="e.g. Conference Room A">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Capacity <span class="text-danger">*</span></label>
                  <input type="number" id="room-capacity" class="form-control" min="1" placeholder="20">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Floor / Location</label>
                  <input type="text" id="room-floor" class="form-control" placeholder="2nd Floor">
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-group">
                  <label>Description</label>
                  <textarea id="room-description" class="form-control" rows="2"></textarea>
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-group">
                  <label>Amenities (comma-separated)</label>
                  <input type="text" id="room-amenities" class="form-control" placeholder="Projector, Whiteboard, AC, Wi-Fi">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Icon Color</label>
                  <select id="room-color" class="form-control">
                    <option value="rc-icon-blue">Blue</option>
                    <option value="rc-icon-green">Green</option>
                    <option value="rc-icon-yellow">Yellow</option>
                    <option value="rc-icon-purple">Purple</option>
                    <option value="rc-icon-cyan">Cyan</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Status</label>
                  <select id="room-status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Under Maintenance</option>
                  </select>
                </div>
              </div>
            </div>
            <button class="btn btn-primary" id="btn-save-room"><i class="fas fa-save mr-1"></i> Save Room</button>
            <button class="btn btn-secondary ml-1" id="btn-cancel-room-edit" style="display:none;">Cancel Edit</button>
          </div>
        </div>
        <!-- Room list -->
        <table id="manage-rooms-table" class="table table-hover table-bordered" style="width:100%">
          <thead><tr><th>Room</th><th>Capacity</th><th>Floor</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  SCRIPTS                                                   -->
<!-- ══════════════════════════════════════════════════════════ -->
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script>
$(document).ready(function() {

  /* ── State ─────────────────────────────────────────────── */
  var selectedRoomId   = null;
  var calendar         = null;
  var allReservTable   = null;
  var manageRoomTable  = null;
  var currentResId     = null; // for view modal

  /* ── Helpers ────────────────────────────────────────────── */
  function statusBadge(s) {
    var map = { pending:'badge-pending', approved:'badge-approved', rejected:'badge-rejected', cancelled:'badge-cancelled' };
    return '<span class="badge ' + (map[s]||'badge-secondary') + '">' + s.charAt(0).toUpperCase()+s.slice(1) + '</span>';
  }
  function fmtDate(d)  { return d ? moment(d).format('MMM D, YYYY') : '–'; }
  function fmtTime(t)  { return t ? moment(t,'HH:mm:ss').format('h:mm A') : '–'; }
  function fmtDT(d,t)  { return fmtDate(d) + ' &nbsp;' + fmtTime(t); }

  /* ─────────────────────────────────────────────────────────
     LOAD ROOMS
  ───────────────────────────────────────────────────────── */
  function loadRooms() {
    $.get('../includes/room_reservation_ajax.php', { action: 'get_rooms' }, function(res) {
      if (!res.success) return;
      var rooms = res.data;
      $('#stat-rooms').text(rooms.length);

      // Populate grid
      var grid = $('#rooms-grid').empty();
      if (!rooms.length) {
        grid.html('<div class="empty-state"><i class="fas fa-door-closed"></i><p>No rooms configured yet.</p></div>');
        return;
      }
      var icons = { 'rc-icon-blue':'fas fa-chalkboard-teacher','rc-icon-green':'fas fa-leaf','rc-icon-yellow':'fas fa-sun','rc-icon-purple':'fas fa-magic','rc-icon-cyan':'fas fa-water' };
      rooms.forEach(function(r) {
        var colorClass = r.color || 'rc-icon-blue';
        var icon = icons[colorClass] || 'fas fa-door-open';
        var occupied = r.is_occupied == 1;
        var card = $('<div class="room-card" data-room-id="'+r.room_id+'" data-room-name="'+r.room_name+'">'+
          '<div class="rc-status '+(occupied?'rc-status-occupied':'rc-status-available')+'"></div>'+
          '<div class="rc-icon '+colorClass+'"><i class="'+icon+'"></i></div>'+
          '<div class="rc-name">'+r.room_name+'</div>'+
          '<div class="rc-cap"><i class="fas fa-users mr-1"></i>'+r.capacity+' pax &nbsp;|&nbsp; <i class="fas fa-map-marker-alt mr-1"></i>'+(r.floor_location||'N/A')+'</div>'+
        '</div>');
        grid.append(card);
      });

      // Populate select in modal
      var sel = $('#res-room').html('<option value="">— Select Room —</option>');
      rooms.forEach(function(r) {
        sel.append('<option value="'+r.room_id+'">'+r.room_name+' (cap: '+r.capacity+')</option>');
      });

    }, 'json');
  }

  /* ── Room card click ──────────────────────────────────── */
  $(document).on('click', '.room-card', function() {
    $('.room-card').removeClass('selected');
    $(this).addClass('selected');
    selectedRoomId = $(this).data('room-id');
    var roomName   = $(this).data('room-name');
    $('#calendar-room-title').text(roomName + ' — Reservation Calendar');
    loadRoomDetail(selectedRoomId);
    calendar.refetchEvents();
  });

  /* ── Room detail ──────────────────────────────────────── */
  function loadRoomDetail(roomId) {
    $('#room-detail-panel').show();
    $.get('../includes/room_reservation_ajax.php', { action:'get_room_detail', room_id:roomId }, function(res) {
      if (!res.success) return;
      var r = res.data;
      $('#rd-name').text(r.room_name);
      $('#rd-desc').text(r.description || 'No description.');
      $('#rd-cap').text(r.capacity);
      $('#rd-floor').text(r.floor_location || 'N/A');
      // Amenities
      var am = $('#rd-amenities').empty();
      if (r.amenities) {
        r.amenities.split(',').forEach(function(a) {
          a = a.trim();
          if (a) am.append('<span class="amenity-chip"><i class="fas fa-check-circle"></i>'+a+'</span>');
        });
      }
      // Timeline
      loadTimeline(roomId);
    }, 'json');

    $('#btn-reserve-room').off('click').on('click', function() {
      $('#res-room').val(roomId).trigger('change');
      $('#reservationModal').modal('show');
    });
  }

  function loadTimeline(roomId) {
    $.get('../includes/room_reservation_ajax.php', { action:'get_today_timeline', room_id:roomId }, function(res) {
      var tl = $('#rd-timeline').empty();
      if (!res.success || !res.slots || !res.slots.length) {
        tl.html('<div class="empty-state"><p>No bookings today.</p></div>');
        return;
      }
      res.slots.forEach(function(slot) {
        var pct = Math.min(100, ((slot.duration||60) / 60) * 8); // visual width hint
        var cls = slot.status === 'approved' ? 'tl-fill-approved' : (slot.status==='pending'?'tl-fill-pending':'tl-fill-free');
        var label = slot.status === 'free' ? 'Free' : slot.title;
        tl.append(
          '<div class="tl-slot">'+
            '<div class="tl-time">'+fmtTime(slot.start_time)+'</div>'+
            '<div class="tl-bar"><div class="tl-bar-fill '+cls+'" style="width:'+pct+'%">'+label+'</div></div>'+
          '</div>'
        );
      });
    }, 'json');
  }

  /* ─────────────────────────────────────────────────────────
     FULLCALENDAR
  ───────────────────────────────────────────────────────── */
  var calEl = document.getElementById('reservation-calendar');
  calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    events: function(info, successCb, failureCb) {
      $.get('../includes/room_reservation_ajax.php', {
        action:   'get_calendar_events',
        room_id:  selectedRoomId || '',
        start:    info.startStr,
        end:      info.endStr
      }, function(res) {
        if (res.success) successCb(res.events);
        else failureCb();
      }, 'json');
    },
    eventClick: function(info) {
      var resId = info.event.extendedProps.reservation_id;
      openViewModal(resId);
    },
    eventDidMount: function(info) {
      var status = info.event.extendedProps.status;
      if (status === 'pending')  info.el.classList.add('ev-pending');
      if (status === 'approved') info.el.classList.add('ev-approved');
      if (status === 'rejected') info.el.classList.add('ev-rejected');
    }
  });
  calendar.render();

  /* ─────────────────────────────────────────────────────────
     STATS
  ───────────────────────────────────────────────────────── */
  function loadStats() {
    $.get('../includes/room_reservation_ajax.php', { action:'get_stats' }, function(res) {
      if (!res.success) return;
      $('#stat-pending').text(res.pending || 0);
      $('#stat-approved').text(res.approved || 0);
      $('#stat-today').text(res.today || 0);
    }, 'json');
  }

  /* ─────────────────────────────────────────────────────────
     MY RESERVATIONS LIST
  ───────────────────────────────────────────────────────── */
  function loadMyReservations() {
    $.get('../includes/room_reservation_ajax.php', { action:'get_my_reservations' }, function(res) {
      var wrap = $('#my-reservations-list').empty();
      if (!res.success || !res.data.length) {
        wrap.html('<div class="empty-state"><i class="fas fa-calendar-times"></i><p>No reservations yet.</p></div>');
        return;
      }
      res.data.forEach(function(r) {
        wrap.append(
          '<div class="p-3" style="border-bottom:1px solid var(--border-subtle);cursor:pointer;" class="my-res-item" data-res-id="'+r.reservation_id+'">'+
            '<div class="d-flex justify-content-between align-items-start">'+
              '<div>'+
                '<div style="font-weight:700;font-size:.88rem;">'+r.purpose+'</div>'+
                '<div style="font-size:.78rem;color:var(--text-muted);">'+
                  '<i class="fas fa-door-open mr-1"></i>'+r.room_name+
                '</div>'+
                '<div style="font-size:.78rem;color:var(--text-muted);">'+
                  '<i class="fas fa-calendar mr-1"></i>'+fmtDate(r.reservation_date)+' &nbsp;'+
                  fmtTime(r.start_time)+' – '+fmtTime(r.end_time)+
                '</div>'+
              '</div>'+
              statusBadge(r.status)+
            '</div>'+
          '</div>'
        );
      });
      wrap.find('> div').on('click', function() {
        openViewModal($(this).data('res-id'));
      });
    }, 'json');
  }

  /* ─────────────────────────────────────────────────────────
     VIEW RESERVATION MODAL
  ───────────────────────────────────────────────────────── */
  function openViewModal(resId) {
    currentResId = resId;
    $.get('../includes/room_reservation_ajax.php', { action:'get_reservation', reservation_id:resId }, function(res) {
      if (!res.success) return;
      var r = res.data;
      var isAdmin = <?= hasPermission('manage_employees') ? 'true' : 'false' ?>;
      var isOwner = r.emp_id == <?= $_SESSION['emp_id'] ?? 0 ?>;

      $('#view-res-body').html(
        '<table class="table table-sm table-borderless mb-0">'+
          '<tr><th style="width:35%;color:var(--text-muted)">Room</th><td><strong>'+r.room_name+'</strong></td></tr>'+
          '<tr><th>Reserved By</th><td>'+r.full_name+'</td></tr>'+
          '<tr><th>Purpose</th><td>'+r.purpose+'</td></tr>'+
          '<tr><th>Description</th><td>'+(r.description||'–')+'</td></tr>'+
          '<tr><th>Date</th><td>'+fmtDate(r.reservation_date)+'</td></tr>'+
          '<tr><th>Time</th><td>'+fmtTime(r.start_time)+' – '+fmtTime(r.end_time)+'</td></tr>'+
          '<tr><th>Attendees</th><td>'+(r.attendees||'–')+'</td></tr>'+
          '<tr><th>Status</th><td>'+statusBadge(r.status)+'</td></tr>'+
          (r.admin_notes?'<tr><th>Notes</th><td>'+r.admin_notes+'</td></tr>':'')+
        '</table>'
      );

      var footer = '<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';

      // Cancel button (owner, pending only)
      if (isOwner && r.status === 'pending') {
        footer += ' <button class="btn btn-danger" id="btn-cancel-res"><i class="fas fa-times mr-1"></i>Cancel Reservation</button>';
      }
      // Admin approve/reject
      if (isAdmin && r.status === 'pending') {
        footer +=
          ' <input type="text" id="admin-note-input" class="form-control form-control-sm" placeholder="Admin note (optional)" style="width:200px;display:inline-block;">'+
          ' <button class="btn btn-success" id="btn-approve-res"><i class="fas fa-check mr-1"></i>Approve</button>'+
          ' <button class="btn btn-danger ml-1" id="btn-reject-res"><i class="fas fa-ban mr-1"></i>Reject</button>';
      }
      $('#view-res-footer').html(footer);

      $('#btn-cancel-res').on('click', function() {
        updateReservationStatus(currentResId, 'cancelled', '');
      });
      $('#btn-approve-res').on('click', function() {
        updateReservationStatus(currentResId, 'approved', $('#admin-note-input').val());
      });
      $('#btn-reject-res').on('click', function() {
        updateReservationStatus(currentResId, 'rejected', $('#admin-note-input').val());
      });

      $('#viewReservationModal').modal('show');
    }, 'json');
  }

  function updateReservationStatus(resId, status, note) {
    $.post('../includes/room_reservation_ajax.php', {
      action: 'update_status',
      reservation_id: resId,
      status: status,
      admin_notes: note
    }, function(res) {
      $('#viewReservationModal').modal('hide');
      if (res.success) {
        Swal.fire('Updated!', 'Reservation has been ' + status + '.', 'success');
        refreshAll();
      } else {
        Swal.fire('Error', res.message || 'Something went wrong.', 'error');
      }
    }, 'json');
  }

  /* ─────────────────────────────────────────────────────────
     CHECK AVAILABILITY
  ───────────────────────────────────────────────────────── */
  $('#btn-check-avail').on('click', function() {
    var roomId = $('#res-room').val();
    var date   = $('#res-date').val();
    var start  = $('#res-start').val();
    var end    = $('#res-end').val();
    if (!roomId || !date || !start || !end) {
      Swal.fire('Incomplete', 'Please fill in Room, Date, Start and End time.', 'warning');
      return;
    }
    $.get('../includes/room_reservation_ajax.php', {
      action: 'check_availability',
      room_id: roomId,
      date: date,
      start_time: start,
      end_time: end
    }, function(res) {
      if (res.available) {
        $('#conflict-alert').hide();
        $('#avail-ok').show();
      } else {
        $('#avail-ok').hide();
        var detail = '';
        if (res.conflicts && res.conflicts.length) {
          res.conflicts.forEach(function(c) {
            detail += '• ' + c.purpose + ' (' + fmtTime(c.start_time) + ' – ' + fmtTime(c.end_time) + ')<br>';
          });
        }
        $('#conflict-detail').html(detail);
        $('#conflict-alert').show();
      }
    }, 'json');
  });

  // Reset alerts on input change
  $('#res-room, #res-date, #res-start, #res-end').on('change', function() {
    $('#conflict-alert, #avail-ok').hide();
  });

  /* ─────────────────────────────────────────────────────────
     SUBMIT RESERVATION
  ───────────────────────────────────────────────────────── */
  $('#btn-submit-reservation').on('click', function() {
    var payload = {
      action:      'create_reservation',
      room_id:     $('#res-room').val(),
      date:        $('#res-date').val(),
      start_time:  $('#res-start').val(),
      end_time:    $('#res-end').val(),
      purpose:     $('#res-purpose').val(),
      description: $('#res-desc').val(),
      attendees:   $('#res-attendees').val()
    };
    if (!payload.room_id || !payload.date || !payload.start_time || !payload.end_time || !payload.purpose) {
      Swal.fire('Incomplete', 'Please fill in all required fields.', 'warning'); return;
    }
    if (payload.start_time >= payload.end_time) {
      Swal.fire('Invalid Time', 'End time must be after start time.', 'warning'); return;
    }
    $.post('../includes/room_reservation_ajax.php', payload, function(res) {
      if (res.success) {
        $('#reservationModal').modal('hide');
        // Clear form
        $('#res-room,#res-date,#res-start,#res-end,#res-purpose,#res-desc,#res-attendees').val('');
        $('#conflict-alert,#avail-ok').hide();
        Swal.fire('Submitted!', 'Your reservation request has been submitted and is pending approval.', 'success');
        refreshAll();
      } else {
        Swal.fire('Error', res.message || 'Could not create reservation.', 'error');
      }
    }, 'json');
  });

  /* ─────────────────────────────────────────────────────────
     ALL RESERVATIONS TABLE (admin)
  ───────────────────────────────────────────────────────── */
  <?php if (hasPermission('manage_employees')): ?>
  allReservTable = $('#all-reservations-table').DataTable({
    processing: true,
    order: [[3, 'desc']],
    columns: [
      { data: 'room_name' },
      { data: 'full_name' },
      { data: 'purpose' },
      { data: 'reservation_date', render: function(d){ return fmtDate(d); } },
      { data: null, render: function(d,t,r){ return fmtTime(r.start_time)+' – '+fmtTime(r.end_time); } },
      { data: 'status', render: function(d){ return statusBadge(d); } },
      { data: null, render: function(d,t,r){
          return '<button class="btn btn-xs btn-primary btn-view-res" data-res-id="'+r.reservation_id+'"><i class="fas fa-eye"></i></button>';
      }}
    ],
    language: { emptyTable: 'No reservations found.' }
  });

  function loadAllReservations() {
    var statusFilter = $('#filter-status').val();
    $.get('../includes/room_reservation_ajax.php', { action:'get_all_reservations', status: statusFilter }, function(res) {
      if (!res.success) return;
      allReservTable.clear().rows.add(res.data).draw();
    }, 'json');
  }

  $('#filter-status').on('change', loadAllReservations);

  $(document).on('click', '.btn-view-res', function() {
    openViewModal($(this).data('res-id'));
  });

  /* ── Manage Rooms Table ─────────────────────────────── */
  manageRoomTable = $('#manage-rooms-table').DataTable({
    columns: [
      { data: 'room_name' },
      { data: 'capacity', render: function(d){ return d + ' pax'; } },
      { data: 'floor_location', defaultContent: '–' },
      { data: 'status', render: function(d){ return '<span class="badge badge-'+(d==='active'?'approved':(d==='maintenance'?'pending':'cancelled'))+'">'+d+'</span>'; } },
      { data: null, render: function(d,t,r){
          return '<button class="btn btn-xs btn-warning btn-edit-room mr-1" data-room=\''+JSON.stringify(r)+'\'><i class="fas fa-edit"></i></button>'+
                 '<button class="btn btn-xs btn-danger btn-del-room" data-id="'+r.room_id+'"><i class="fas fa-trash"></i></button>';
      }}
    ],
    pageLength: 5, lengthChange: false,
    language: { emptyTable: 'No rooms yet.' }
  });

  function loadManageRooms() {
    $.get('../includes/room_reservation_ajax.php', { action:'get_rooms' }, function(res) {
      if (!res.success) return;
      manageRoomTable.clear().rows.add(res.data).draw();
    }, 'json');
  }

  $('#manageRoomsModal').on('show.bs.modal', loadManageRooms);

  /* edit room */
  $(document).on('click', '.btn-edit-room', function() {
    var r = $(this).data('room');
    $('#edit-room-id').val(r.room_id);
    $('#room-name').val(r.room_name);
    $('#room-capacity').val(r.capacity);
    $('#room-floor').val(r.floor_location);
    $('#room-description').val(r.description);
    $('#room-amenities').val(r.amenities);
    $('#room-color').val(r.color||'rc-icon-blue');
    $('#room-status').val(r.status||'active');
    $('#room-form-title').text('Edit Room');
    $('#btn-cancel-room-edit').show();
  });

  $('#btn-cancel-room-edit').on('click', function() {
    $('#edit-room-id').val('');
    $('#room-name,#room-capacity,#room-floor,#room-description,#room-amenities').val('');
    $('#room-color').val('rc-icon-blue'); $('#room-status').val('active');
    $('#room-form-title').text('Add New Room'); $(this).hide();
  });

  /* save room */
  $('#btn-save-room').on('click', function() {
    var id = $('#edit-room-id').val();
    $.post('../includes/room_reservation_ajax.php', {
      action:         id ? 'update_room' : 'create_room',
      room_id:        id,
      room_name:      $('#room-name').val(),
      capacity:       $('#room-capacity').val(),
      floor_location: $('#room-floor').val(),
      description:    $('#room-description').val(),
      amenities:      $('#room-amenities').val(),
      color:          $('#room-color').val(),
      status:         $('#room-status').val()
    }, function(res) {
      if (res.success) {
        toastr.success(id ? 'Room updated!' : 'Room created!');
        loadManageRooms(); loadRooms();
        $('#btn-cancel-room-edit').click();
      } else {
        toastr.error(res.message || 'Error saving room.');
      }
    }, 'json');
  });

  /* delete room */
  $(document).on('click', '.btn-del-room', function() {
    var id = $(this).data('id');
    Swal.fire({
      title:'Delete Room?',text:'This will also delete all its reservations.',icon:'warning',
      showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Yes, delete'
    }).then(function(r){ if(r.isConfirmed) {
      $.post('../includes/room_reservation_ajax.php',{action:'delete_room',room_id:id},function(res){
        if(res.success){ loadManageRooms();loadRooms();toastr.success('Room deleted.'); }
        else toastr.error(res.message||'Error');
      },'json');
    }});
  });

  <?php endif; ?>

  /* ─────────────────────────────────────────────────────────
     REFRESH ALL
  ───────────────────────────────────────────────────────── */
  function refreshAll() {
    loadRooms();
    loadStats();
    loadMyReservations();
    calendar.refetchEvents();
    if (selectedRoomId) loadTimeline(selectedRoomId);
    <?php if (hasPermission('manage_employees')): ?>
    loadAllReservations();
    <?php endif; ?>
  }

  /* ─────────────────────────────────────────────────────────
     INIT
  ───────────────────────────────────────────────────────── */
  loadRooms();
  loadStats();
  loadMyReservations();
  <?php if (hasPermission('manage_employees')): ?>
  loadAllReservations();
  <?php endif; ?>

  // Auto-refresh every 60 seconds
  setInterval(refreshAll, 60000);

  // Set today's date as default in modal
  $('#res-date').val(moment().format('YYYY-MM-DD'));
});
</script>
</body>
</html>