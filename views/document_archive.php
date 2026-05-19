<?php
ob_start();
date_default_timezone_set('Asia/Manila');
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->query("SET time_zone = '+08:00'");

$logged_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
$isMasteradmin = false;
if ($logged_emp_id) {
    $maStmt = $db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON u.role_id=ur.id WHERE u.employee_id=? AND ur.id=1 LIMIT 1");
    if ($maStmt) { $maStmt->bind_param("i",$logged_emp_id); $maStmt->execute(); $isMasteradmin = $maStmt->get_result()->num_rows > 0; }
}

$page_title = 'Daily Document Archive';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS — light (matches document_list + login palette)
        ══════════════════════════════════════════ */
        :root {
            --green:             #24e78f;
            --green-dark:        #2a9863;
            --green-mid:         #1a5c38;

            --doc-primary:       #1c4d38;
            --doc-primary-light: #e6f7ef;
            --doc-incoming:      #2563eb;
            --doc-outgoing:      #16a34a;
            --doc-internal:      #7c3aed;

            --doc-surface:       var(--card-bg, #ffffff);
            --doc-border:        var(--card-border, rgba(42,152,99,.18));
            --doc-text:          var(--text-primary, #0f2d1e);
            --doc-muted:         var(--text-muted, #4a7a5e);
            --doc-hover:         #e6f7ef;
            --doc-stripe:        #f0faf5;

            --radius-sm:   6px;
            --radius-md:   10px;
            --radius-lg:   14px;
            --shadow-card: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(42,152,99,.08);
            --shadow-btn:  0 1px 2px rgba(0,0,0,.08);

            --thead-bg:    #1c4d38;
            --thead-color: #ffffff;

            /* archive accent — teal-green (not brown, keep palette consistent) */
            --arc-accent:  #1c4d38;
            --arc-accent2: #24e78f;
        }

        /* ══════════════════════════════════════════
           DARK MODE TOKENS
        ══════════════════════════════════════════ */
        body.dark-mode {
            --doc-primary:       #24e78f;
            --doc-primary-light: rgba(36,231,143,.12);
            --doc-surface:       var(--card-bg, #102f22);
            --doc-border:        var(--card-border, rgba(36,231,143,.10));
            --doc-text:          var(--text-primary, #d4f5e5);
            --doc-muted:         #6aad8a;
            --doc-hover:         rgba(36,231,143,.07);
            --doc-stripe:        rgba(36,231,143,.04);
            --shadow-card:       0 1px 3px rgba(0,0,0,.3), 0 4px 16px rgba(0,0,0,.25);
            --thead-bg:          #1a5c38;
            --thead-color:       #d4f5e5;
            --arc-accent:        #24e78f;
            --arc-accent2:       #1a5c38;
        }

        /* ── Page header banner ─────────────────────────── */
        .arc-banner {
            background: linear-gradient(135deg, #0f2d1e 0%, #1c4d38 55%, #2a9863 100%);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(28,77,56,.35);
            margin-bottom: 6px;
        }
        .arc-banner h4 { font-size: 1.25rem; font-weight: 700; margin: 0; letter-spacing: -.01em; }
        .arc-banner small { opacity: .78; font-size: .82rem; }
        .countdown-chip {
            background: rgba(255,255,255,.13);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: .8rem;
            white-space: nowrap;
        }
        body.dark-mode .arc-banner { background: linear-gradient(135deg, #091d14 0%, #102f22 55%, #1a5c38 100%); }

        /* ── Filter / toolbar bar ──────────────────────── */
        .filter-bar {
            display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--radius-lg);
            padding: 10px 16px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-card);
        }
        body.dark-mode .filter-bar { background: var(--card-bg, #102f22); border-color: var(--card-border, rgba(36,231,143,.10)); }

        .filter-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px; border-radius: 8px;
            font-size: .8rem; font-weight: 600;
            cursor: pointer; border: 1.5px solid transparent;
            background: transparent; color: var(--doc-muted);
            transition: all .15s ease; letter-spacing: .01em;
        }
        .filter-pill:hover { background: var(--doc-hover); color: var(--doc-primary); }
        .filter-pill.fp-active-all      { background: var(--doc-primary); color: #fff; box-shadow: 0 2px 8px rgba(28,77,56,.35); }
        .filter-pill.fp-active-incoming { background: #2563eb; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.25); }
        .filter-pill.fp-active-outgoing { background: #16a34a; color: #fff; box-shadow: 0 2px 8px rgba(22,163,74,.25); }
        .filter-pill.fp-active-internal { background: #7c3aed; color: #fff; box-shadow: 0 2px 8px rgba(124,58,237,.25); }
        body.dark-mode .filter-pill { color: #6aad8a; }
        body.dark-mode .filter-pill:hover { background: rgba(36,231,143,.08); color: #d4f5e5; }
        body.dark-mode .filter-pill.fp-active-all { background: #24e78f; color: #091d14; }

        /* ── Toolbar buttons (matches document_list) ───── */
        .toolbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: var(--radius-sm);
            font-size: .8rem; font-weight: 600;
            border: 1.5px solid; cursor: pointer;
            transition: all .15s ease;
            box-shadow: var(--shadow-btn);
            white-space: nowrap;
            background: none;
        }
        .toolbar-btn:hover { filter: brightness(.93); transform: translateY(-1px); }
        .toolbar-btn-export { background:#fff; color:#4a7a5e; border-color:rgba(42,152,99,.3); }
        .toolbar-btn-export:hover { background:#e6f7ef; color:#1c4d38; }
        .toolbar-btn-arc    { background:#1c4d38; color:#fff; border-color:#1c4d38; }
        .toolbar-btn-arc:hover { background:#2a9863; border-color:#2a9863; }
        body.dark-mode .toolbar-btn-export { background:var(--card-bg,#102f22); color:#d4f5e5; border-color:var(--card-border,rgba(36,231,143,.12)); }
        body.dark-mode .toolbar-btn-arc    { background:#24e78f; color:#091d14; border-color:#24e78f; }
        body.dark-mode .toolbar-btn-arc:hover { background:#2a9863; color:#fff; }

        /* ── Day-picker column ─────────────────────────── */
        .arc-side-card {
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            margin-bottom: 16px;
        }
        body.dark-mode .arc-side-card { background:var(--card-bg,#102f22); border-color:var(--card-border,rgba(36,231,143,.10)); }

        .arc-side-header {
            background: var(--thead-bg);
            color: var(--thead-color);
            padding: 11px 16px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .arc-side-body { padding: 10px; }

        /* ── Inline Calendar ───────────────────────────── */
        .arc-cal { user-select: none; }

        .arc-cal-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 12px 6px;
        }
        .arc-cal-nav-btn {
            width: 28px; height: 28px;
            border: 1.5px solid var(--doc-border);
            border-radius: var(--radius-sm);
            background: transparent; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; color: var(--doc-muted);
            transition: all .15s;
        }
        .arc-cal-nav-btn:hover { background: var(--doc-hover); color: var(--doc-primary); border-color: rgba(42,152,99,.4); }
        body.dark-mode .arc-cal-nav-btn { border-color:rgba(36,231,143,.15); color:#6aad8a; }
        body.dark-mode .arc-cal-nav-btn:hover { background:rgba(36,231,143,.08); color:#d4f5e5; }

        .arc-cal-title {
            font-size: .85rem; font-weight: 700;
            color: var(--doc-text); letter-spacing: -.01em;
        }

        .arc-cal-grid {
            display: grid; grid-template-columns: repeat(7, 1fr);
            gap: 2px; padding: 0 8px 10px;
        }
        .arc-cal-dow {
            text-align: center; font-size: .62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--doc-muted); padding: 4px 0 6px;
        }
        .arc-cal-day {
            position: relative;
            aspect-ratio: 1;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border-radius: var(--radius-sm);
            font-size: .78rem; font-weight: 600;
            cursor: default;
            color: var(--doc-muted);
            transition: all .14s;
            min-height: 34px;
        }
        .arc-cal-day.cal-empty { /* blank filler */ }
        .arc-cal-day.cal-other-month { opacity: .28; }
        .arc-cal-day.cal-today {
            outline: 2px solid var(--doc-primary);
            outline-offset: -2px;
            color: var(--doc-primary);
            font-weight: 800;
        }
        body.dark-mode .arc-cal-day.cal-today { outline-color: #24e78f; color: #24e78f; }

        .arc-cal-day.cal-has-archive {
            cursor: pointer;
            color: var(--doc-text);
            background: var(--doc-primary-light);
        }
        .arc-cal-day.cal-has-archive:hover {
            background: rgba(42,152,99,.22);
            transform: scale(1.08);
            box-shadow: 0 2px 8px rgba(28,77,56,.18);
        }
        body.dark-mode .arc-cal-day.cal-has-archive {
            background: rgba(36,231,143,.1);
            color: #d4f5e5;
        }
        body.dark-mode .arc-cal-day.cal-has-archive:hover {
            background: rgba(36,231,143,.2);
        }

        .arc-cal-day.cal-selected {
            background: var(--doc-primary) !important;
            color: #fff !important;
            box-shadow: 0 3px 10px rgba(28,77,56,.35);
            transform: scale(1.08);
        }
        body.dark-mode .arc-cal-day.cal-selected {
            background: #24e78f !important;
            color: #091d14 !important;
            box-shadow: 0 3px 10px rgba(36,231,143,.3);
        }

        /* archive dot indicator */
        .arc-cal-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: var(--green-dark);
            margin-top: 2px; flex-shrink: 0;
        }
        .arc-cal-day.cal-selected .arc-cal-dot { background: rgba(255,255,255,.7); }
        body.dark-mode .arc-cal-day.cal-selected .arc-cal-dot { background: rgba(9,29,20,.6); }

        /* count bubble */
        .arc-cal-count {
            position: absolute; top: 1px; right: 2px;
            font-size: .55rem; font-weight: 800;
            background: var(--doc-primary); color: #fff;
            border-radius: 6px; padding: 0 3px; line-height: 1.5;
            min-width: 12px; text-align: center;
        }
        .arc-cal-day.cal-selected .arc-cal-count { background: rgba(255,255,255,.3); color: #fff; }
        body.dark-mode .arc-cal-count { background: #24e78f; color: #091d14; }
        body.dark-mode .arc-cal-day.cal-selected .arc-cal-count { background: rgba(9,29,20,.3); color: #091d14; }

        /* divider line between nav and grid */
        .arc-cal-sep { border:none; border-top:1px solid var(--doc-border); margin: 0 10px 6px; }

        /* legend below calendar */
        .arc-cal-legend {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 6px 12px 10px;
            font-size: .68rem; color: var(--doc-muted);
            border-top: 1px solid var(--doc-border);
        }
        .arc-cal-legend-item { display: flex; align-items: center; gap: 4px; }

        /* ── Kind filter pills in side panel ──────────── */
        .kind-filter-btn {
            display: flex; align-items: center; gap: 7px;
            width: 100%; padding: 7px 12px;
            border-radius: var(--radius-sm);
            font-size: .8rem; font-weight: 600;
            border: 1.5px solid var(--doc-border);
            color: var(--doc-muted); background: transparent;
            cursor: pointer; transition: all .15s;
            margin-bottom: 5px;
        }
        .kind-filter-btn:hover { background: var(--doc-hover); color: var(--doc-primary); }
        .kind-filter-btn.active { border-color: transparent; color: #fff; }
        .kind-filter-btn.active.kf-all      { background: var(--doc-primary); }
        .kind-filter-btn.active.kf-incoming { background: #2563eb; }
        .kind-filter-btn.active.kf-outgoing { background: #16a34a; }
        .kind-filter-btn.active.kf-internal { background: #7c3aed; }
        body.dark-mode .kind-filter-btn { border-color:rgba(36,231,143,.1); color:#6aad8a; }
        body.dark-mode .kind-filter-btn:hover { background:rgba(36,231,143,.07); color:#d4f5e5; }
        body.dark-mode .kind-filter-btn.active.kf-all { background:#24e78f; color:#091d14; }

        /* ── Main table card ───────────────────────────── */
        .doc-table-card {
            background: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        body.dark-mode .doc-table-card { background:var(--card-bg,#102f22); border-color:var(--card-border,rgba(36,231,143,.10)); }

        .doc-table-card-header {
            padding: 14px 18px 12px;
            border-bottom: 1px solid var(--doc-border);
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
        }
        body.dark-mode .doc-table-card-header { border-color:var(--card-border,rgba(36,231,143,.10)); }

        /* ── Archive table ─────────────────────────────── */
        #archiveTable {
            font-size: .83rem; color: var(--doc-text);
            border-collapse: collapse; width: 100%; margin: 0;
        }
        table#archiveTable thead tr,
        table#archiveTable > thead > tr {
            background-color: var(--thead-bg) !important;
        }
        table#archiveTable thead th,
        table#archiveTable > thead > tr > th {
            color: var(--thead-color) !important;
            background-color: var(--thead-bg) !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .07em !important;
            padding: 13px 14px !important;
            border: none !important;
            white-space: nowrap;
        }
        #archiveTable tbody tr {
            border-bottom: 1px solid var(--doc-border);
            transition: background .12s ease;
        }
        #archiveTable tbody tr:last-child { border-bottom: none; }
        #archiveTable tbody tr:nth-child(even) { background: var(--doc-stripe); }
        #archiveTable tbody tr:hover { background: var(--doc-primary-light) !important; }
        #archiveTable tbody td {
            padding: 11px 14px; border: none !important; vertical-align: middle;
        }
        body.dark-mode #archiveTable tbody tr { border-color:var(--card-border,rgba(36,231,143,.10)); }

        /* ── Kind badges (matches document_list exactly) ─ */
        .kind-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            border: 1px solid transparent;
        }
        .kind-incoming { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
        .kind-outgoing { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
        .kind-internal { background:#ede9fe; color:#6d28d9; border-color:#ddd6fe; }
        body.dark-mode .kind-incoming { background:#1e3a5f; color:#93c5fd; border-color:#1e40af; }
        body.dark-mode .kind-outgoing { background:#14532d; color:#86efac; border-color:#166534; }
        body.dark-mode .kind-internal { background:#2e1065; color:#c4b5fd; border-color:#4c1d95; }

        /* ── Status badges (matches document_list exactly) */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: .7rem; font-weight: 700; white-space: nowrap;
        }
        .status-badge::before {
            content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0;
        }
        .status-pending   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .status-pending::before   { background:#f97316; }
        .status-received  { background:#e6f7ef; color:#1c4d38; border:1px solid #a7f3d0; }
        .status-received::before  { background:#24e78f; }
        .status-returned  { background:#fdf2f8; color:#9d174d; border:1px solid #fbcfe8; }
        .status-returned::before  { background:#ec4899; }
        .status-completed { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .status-completed::before { background:#2a9863; }
        .status-archived  { background:#f0faf5; color:#4a7a5e; border:1px solid #a7d8bc; }
        .status-archived::before  { background:#6aad8a; }
        body.dark-mode .status-pending   { background:#431407; color:#fdba74; border-color:#7c2d12; }
        body.dark-mode .status-received  { background:#064e3b; color:#6ee7b7; border-color:#065f46; }
        body.dark-mode .status-returned  { background:#4a044e; color:#f0abfc; border-color:#86198f; }
        body.dark-mode .status-completed { background:#064e3b; color:#6ee7b7; border-color:#065f46; }
        body.dark-mode .status-archived  { background:#122b1d; color:#6aad8a; border-color:rgba(36,231,143,.18); }

        /* ── Doc number cell ───────────────────────────── */
        .doc-number-cell {
            font-family: 'Courier New', monospace;
            font-size: .76rem; font-weight: 700;
            color: var(--doc-primary);
            background: var(--doc-primary-light);
            padding: 3px 8px; border-radius: 5px;
            display: inline-block; letter-spacing: .02em;
            border: 1px solid rgba(42,152,99,.3); white-space: nowrap;
        }
        body.dark-mode .doc-number-cell { background:rgba(36,231,143,.10); color:#24e78f; border-color:rgba(36,231,143,.20); }

        .person-name { font-weight: 600; color: var(--doc-text); font-size: .83rem; }
        .cell-meta   { font-size: .72rem; color: var(--doc-muted); margin-top: 2px; display:flex; align-items:center; gap:4px; flex-wrap:wrap; }
        .cell-meta i { font-size: .63rem; }
        .doc-id-cell { font-weight:700; color:var(--doc-muted); font-size:.78rem; }
        .remarks-cell { max-width:130px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--doc-muted); font-size:.8rem; font-style:italic; }

        /* ── Action button ─────────────────────────────── */
        .action-btn {
            width:30px; height:30px; padding:0;
            border-radius:var(--radius-sm);
            display:inline-flex; align-items:center; justify-content:center;
            border:none; cursor:pointer; font-size:.78rem;
            transition:all .15s ease;
            box-shadow:0 1px 2px rgba(0,0,0,.1);
        }
        .action-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.18); }
        .action-btn-view { background:#0ea5e9; color:#fff; }

        /* ── DataTables overrides ──────────────────────── */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter { padding:14px 16px 10px; }
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label { font-size:.82rem; color:var(--doc-muted); font-weight:500; margin-bottom:0; }
        .dataTables_wrapper .dataTables_length select {
            border:1.5px solid var(--doc-border); border-radius:var(--radius-sm);
            padding:3px 8px; font-size:.82rem; color:var(--doc-text); box-shadow:none;
        }
        .dataTables_wrapper .dataTables_filter input {
            border:1.5px solid var(--doc-border); border-radius:var(--radius-sm);
            padding:5px 12px; font-size:.82rem; color:var(--doc-text);
            transition:border-color .15s; box-shadow:none; margin-left:6px;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color:var(--doc-primary); outline:none;
            box-shadow:0 0 0 3px rgba(26,60,94,.1);
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { padding:12px 16px; font-size:.8rem; color:var(--doc-muted); }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius:var(--radius-sm)!important; border:1.5px solid var(--doc-border)!important;
            font-size:.78rem!important; padding:4px 10px!important; margin:0 2px!important; color:var(--doc-text)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background:var(--doc-primary)!important; border-color:var(--doc-primary)!important; color:#fff!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background:var(--doc-hover)!important; color:var(--doc-primary)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color:#cbd5e1!important; border-color:#f1f5f9!important;
        }
        body.dark-mode .dataTables_wrapper .dataTables_filter input,
        body.dark-mode .dataTables_wrapper .dataTables_length select {
            background:var(--input-bg,#0e2619); color:var(--text-primary,#d4f5e5); border-color:var(--input-border,rgba(36,231,143,.18));
        }

        /* ── Empty / loading states ────────────────────── */
        #archiveTableWrapper { min-height: 160px; }
        .archive-empty {
            text-align:center; padding:50px 20px;
            color: var(--doc-muted); font-size:.92rem;
        }
        .archive-empty i { font-size:2.8rem; display:block; margin-bottom:12px; opacity:.35; color:var(--doc-primary); }

        /* ── Snapshot modal ────────────────────────────── */
        #snapshotModal .modal-dialog { max-width: 580px; }
        #snapshotModal .modal-content {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 40px rgba(0,0,0,.18);
            overflow: hidden;
        }
        #snapshotModal .modal-header {
            background: linear-gradient(135deg, #0f2d1e 0%, #1c4d38 60%, #2a9863 100%);
            padding: 16px 20px;
            border-bottom: none;
        }
        #snapshotModal .modal-title {
            color: #fff;
            font-weight: 700;
            font-size: .95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #snapshotModal .modal-title .snap-icon {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.15);
            border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .8rem;
        }
        #snapshotModal .modal-body {
            padding: 0;
            background: var(--doc-surface);
        }
        body.dark-mode #snapshotModal .modal-content { background: #102f22; }
        body.dark-mode #snapshotModal .modal-body { background: #102f22; }

        /* Snapshot fields grid */
        .snap-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .snap-field {
            padding: 11px 16px;
            border-bottom: 1px solid var(--doc-border);
            border-right: 1px solid var(--doc-border);
        }
        .snap-field:nth-child(even) { border-right: none; }
        .snap-field.snap-full {
            grid-column: 1 / -1;
            border-right: none;
        }
        .snap-field:last-child,
        .snap-field:nth-last-child(2):not(.snap-full) { border-bottom: none; }
        .snap-field-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--doc-muted);
            margin-bottom: 3px;
        }
        .snap-field-value {
            font-size: .84rem;
            font-weight: 500;
            color: var(--doc-text);
            word-break: break-word;
        }
        .snap-field-value.snap-muted { color: var(--doc-muted); font-style: italic; }
        .snap-field-value.snap-mono {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: .8rem;
            color: var(--doc-primary);
            font-weight: 700;
        }
        body.dark-mode .snap-field { border-color: rgba(36,231,143,.1); }
        body.dark-mode .snap-field-value { color: #d4f5e5; }
        body.dark-mode .snap-field-value.snap-mono { color: #24e78f; }

        /* Snapshot JSON block */
        #snapshotModal .modal-body pre {
            font-size:.74rem; background:var(--doc-stripe);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            max-height:300px;
            overflow-y:auto; padding:14px 16px;
            border: none;
            border-top: 1px solid var(--doc-border);
            color: var(--doc-text);
            margin: 0;
        }
        .snap-json-label {
            padding: 10px 16px 6px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--doc-muted);
            border-top: 1px solid var(--doc-border);
            background: var(--doc-stripe);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        body.dark-mode #snapshotModal .modal-body pre { background:#091d14; color:#d4f5e5; border-color:rgba(36,231,143,.1); }
        body.dark-mode .snap-json-label { background:#0a1f15; border-color:rgba(36,231,143,.1); }

        /* ── Stats chips in banner ─────────────────────── */
        .stat-chip {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
            border-radius:20px; padding:4px 12px; font-size:.78rem; font-weight:600;
        }
        .stat-chip .chip-dot { width:8px; height:8px; border-radius:50%; }

        /* ── Toast animation ───────────────────────────── */
        @keyframes slideInRight {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        /* ── Auto-archive status chip ──────────────────── */
        .arc-status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .76rem;
        }
        .arc-status-chip.ran    { background: rgba(36,231,143,.2); border-color: rgba(36,231,143,.35); }
        .arc-status-chip.pending { background: rgba(249,115,22,.2); border-color: rgba(249,115,22,.35); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper">

        <!-- ── Page Header ── -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.4rem;font-weight:700;color:var(--doc-primary);">
                            <i class="fas fa-archive mr-2" style="color:var(--green-dark,#2a9863);"></i>
                            Daily Document Archive
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Daily Archive</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- ── Banner ── -->
                <div class="arc-banner mb-3">
                    <div>
                        <div class="d-flex align-items-center flex-wrap" style="gap:10px;margin-bottom:6px;">
                            <h4><i class="fas fa-archive mr-2"></i>Daily Document Archive</h4>
                            <span id="bannerStats" style="display:flex;gap:6px;flex-wrap:wrap;"></span>
                        </div>
                        <small>Documents are automatically archived &amp; removed from the live table each midnight (PHT). The live table resets to zero every day.</small>
                        <div class="mt-2" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <span id="autoArchiveStatus" class="arc-status-chip">
                                <i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i> Checking auto-archive…
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="countdown-chip mb-1">
                            <i class="fas fa-clock mr-1"></i> Next reset in: <strong id="countdownTimer">--:--:--</strong>
                        </div>
                        <div style="font-size:.78rem;opacity:.75;margin-top:4px;">
                            <i class="fas fa-calendar mr-1"></i> Today (PHT): <strong><?= date('F j, Y') ?></strong>
                        </div>
                    </div>
                </div>

                <!-- ── Filter / Toolbar bar ── -->
                <div class="filter-bar">
                    <span class="filter-pill fp-active-all" data-kind="" id="fp-all">
                        <i class="fas fa-folder-open"></i> All
                    </span>
                    <span class="filter-pill" data-kind="incoming" id="fp-incoming">
                        <i class="fas fa-inbox"></i> Incoming
                    </span>
                    <span class="filter-pill" data-kind="outgoing" id="fp-outgoing">
                        <i class="fas fa-paper-plane"></i> Outgoing
                    </span>
                    <span class="filter-pill" data-kind="internal" id="fp-internal">
                        <i class="fas fa-exchange-alt"></i> Internal
                    </span>

                    <span class="toolbar-divider"></span>

                    <div class="ml-auto d-flex flex-wrap" style="gap:6px;align-items:center;">
                        <button class="toolbar-btn toolbar-btn-export" onclick="exportArchiveXLSX()" id="exportBtn" disabled>
                            <i class="fas fa-file-excel"></i> Export XLSX
                        </button>
                        <?php if ($isMasteradmin): ?>
                        <button class="toolbar-btn toolbar-btn-arc" onclick="triggerManualArchive()">
                            <i class="fas fa-archive"></i> Archive Manually
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">

                    <!-- ── Left: Day picker ── -->
                    <div class="col-lg-3 col-md-4 mb-3">

                        <div class="arc-side-card">
                            <div class="arc-side-header">
                                <i class="fas fa-calendar-alt"></i> Archived Days
                            </div>
                            <div class="arc-cal" id="calendarWidget">
                                <!-- nav -->
                                <div class="arc-cal-nav">
                                    <button class="arc-cal-nav-btn" id="calPrev" title="Previous month"><i class="fas fa-chevron-left"></i></button>
                                    <span class="arc-cal-title" id="calTitle">—</span>
                                    <button class="arc-cal-nav-btn" id="calNext" title="Next month"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <hr class="arc-cal-sep">
                                <!-- day-of-week headers -->
                                <div class="arc-cal-grid" id="calDowRow">
                                    <div class="arc-cal-dow">Su</div>
                                    <div class="arc-cal-dow">Mo</div>
                                    <div class="arc-cal-dow">Tu</div>
                                    <div class="arc-cal-dow">We</div>
                                    <div class="arc-cal-dow">Th</div>
                                    <div class="arc-cal-dow">Fr</div>
                                    <div class="arc-cal-dow">Sa</div>
                                </div>
                                <!-- day cells rendered by JS -->
                                <div class="arc-cal-grid" id="calDayGrid">
                                    <div class="arc-cal-day text-center" style="grid-column:1/-1;padding:18px 0;font-size:.82rem;">
                                        <i class="fas fa-spinner fa-spin" style="color:var(--doc-muted);"></i>
                                    </div>
                                </div>
                                <!-- legend -->
                                <div class="arc-cal-legend">
                                    <div class="arc-cal-legend-item">
                                        <div style="width:12px;height:12px;border-radius:3px;background:var(--doc-primary-light);border:1.5px solid rgba(42,152,99,.3);"></div>
                                        Has archive
                                    </div>
                                    <div class="arc-cal-legend-item">
                                        <div style="width:12px;height:12px;border-radius:3px;background:var(--doc-primary);"></div>
                                        Selected
                                    </div>
                                    <div class="arc-cal-legend-item">
                                        <div style="width:12px;height:12px;border-radius:3px;outline:2px solid var(--doc-primary);outline-offset:-2px;background:transparent;"></div>
                                        Today
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ── Right: Archive table ── -->
                    <div class="col-lg-9 col-md-8">
                        <div class="doc-table-card">
                            <div class="doc-table-card-header">
                                <div>
                                    <div style="font-size:.95rem;font-weight:700;color:var(--doc-text);">
                                        <i class="fas fa-table mr-1" style="color:var(--doc-muted);"></i>
                                        <span id="selectedDateLabel" style="color:var(--doc-primary);">Select a day →</span>
                                    </div>
                                    <div style="font-size:.78rem;color:var(--doc-muted);margin-top:3px;" id="archiveSummary"></div>
                                </div>
                            </div>
                            <div id="archiveTableWrapper">
                                <div class="archive-empty">
                                    <i class="fas fa-calendar-day"></i>
                                    Select a day from the left panel to view its archived documents.
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div><!-- /.content -->
    </div><!-- /.content-wrapper -->

    <?php include '../includes/footer.php'; ?>
</div><!-- /.wrapper -->

<!-- ── Snapshot Modal ── -->
<div class="modal fade" id="snapshotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <span class="snap-icon"><i class="fas fa-file-alt"></i></span>
                    Archived Document Snapshot
                </h6>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;">&times;</button>
            </div>
            <div class="modal-body" id="snapshotBody"></div>
        </div>
    </div>
</div>

<!-- SheetJS for XLSX export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
/* ── Midnight auto-archive engine ─────────────────────────── */
// On page load: check if today's archive has already run.
// If not, watch the clock — the moment it crosses midnight PHT,
// fire midnight_archive automatically (no user action needed).

let midnightFired = false;

function getPHTNow() {
    const now = new Date();
    return new Date(now.getTime() + now.getTimezoneOffset() * 60000 + 8 * 3600000);
}

function secondsUntilMidnightPHT() {
    const pht = getPHTNow();
    const midnight = new Date(pht);
    midnight.setHours(24, 0, 0, 0);
    return (midnight - pht) / 1000;
}

function runMidnightArchive(triggeredBy) {
    if (midnightFired && triggeredBy === 'auto') return;
    midnightFired = true;

    const payload = { action: 'midnight_archive', triggered_by: triggeredBy };
    <?php if ($isMasteradmin): ?>
    // If masteradmin manually set a date, that's handled by triggerManualArchive() separately
    <?php endif; ?>

    $.post('document_actions.php', payload, function(r) {
        if (triggeredBy === 'auto') {
            // Silent auto-run: just reload the calendar
            loadDays();
            if (r.archived > 0) {
                // Show a subtle toast-style notification
                showToast(
                    `<i class="fas fa-archive mr-1"></i> Auto-archived <strong>${r.archived}</strong> document(s) for yesterday. Live table reset.`,
                    'success'
                );
            }
        }
    }, 'json').fail(() => {
        if (triggeredBy === 'auto') console.warn('Auto-archive failed silently.');
    });
}

function startMidnightWatcher() {
    $.get('document_actions.php', { action: 'check_midnight_status' }, function(r) {
        const chip = $('#autoArchiveStatus');
        if (r.already_ran) {
            chip.removeClass('pending').addClass('ran').html(
                `<i class="fas fa-check-circle"></i> Auto-archived today (${r.today})`
            );
        } else {
            const secs = secondsUntilMidnightPHT();
            chip.removeClass('ran').addClass('pending').html(
                `<i class="fas fa-hourglass-half"></i> Auto-archive pending — fires at midnight PHT`
            );
            if (secs <= 0) {
                runMidnightArchive('auto');
            } else {
                setTimeout(() => runMidnightArchive('auto'), secs * 1000);
            }
        }
    }, 'json').fail(() => {
        $('#autoArchiveStatus').html('<i class="fas fa-exclamation-circle"></i> Status check failed');
    });
}

/* ── Countdown ─────────────────────────────────────────────── */
(function tick(){
    function upd(){
        const pht = getPHTNow();
        const midnight = new Date(pht); midnight.setHours(24,0,0,0);
        const d = midnight - pht;
        const h = String(Math.floor(d/3600000)).padStart(2,'0');
        const m = String(Math.floor((d%3600000)/60000)).padStart(2,'0');
        const s = String(Math.floor((d%60000)/1000)).padStart(2,'0');
        document.getElementById('countdownTimer').textContent=h+':'+m+':'+s;

        // Re-arm midnight watcher if countdown just hit 00:00:01
        if (d <= 2000 && !midnightFired) {
            setTimeout(() => runMidnightArchive('auto'), d);
        }
    }
    upd(); setInterval(upd,1000);
})();

/* ── Toast helper ──────────────────────────────────────────── */
function showToast(html, type) {
    const color = type === 'success' ? '#1c4d38' : '#dc2626';
    const toast = $(`<div style="
        position:fixed;bottom:24px;right:24px;z-index:9999;
        background:${color};color:#fff;border-radius:10px;
        padding:12px 20px;font-size:.85rem;font-weight:600;
        box-shadow:0 4px 20px rgba(0,0,0,.3);max-width:360px;
        display:flex;align-items:center;gap:8px;
        animation:slideInRight .3s ease;
    ">${html}</div>`);
    $('body').append(toast);
    setTimeout(() => toast.fadeOut(400, () => toast.remove()), 5000);
}

/* ── State ─────────────────────────────────────────────────── */
let selectedDate=null, selectedKind='', archiveDT=null, currentRows=[];

/* ── Filter pill click ─────────────────────────────────────── */
document.querySelectorAll('.filter-pill').forEach(pill=>{
    pill.addEventListener('click',function(){
        document.querySelectorAll('.filter-pill').forEach(p=>{
            p.className='filter-pill';
        });
        const kind=this.dataset.kind;
        selectedKind=kind;
        const cls=kind?'fp-active-'+kind:'fp-active-all';
        this.classList.add(cls);
        if(selectedDate) loadArchive();
    });
});

/* ── Calendar engine ───────────────────────────────────────── */
// archiveDaysMap: 'YYYY-MM-DD' -> {total, incoming, outgoing, internal}
let archiveDaysMap = {};

// Safe defaults so renderCalendar() never sees undefined
const _now = new Date();
let calYear  = _now.getFullYear();
let calMonth = _now.getMonth(); // 0-indexed

/* helper — always produces a clean YYYY-MM-DD string */
function fmtYMD(date){
    const y = date.getFullYear();
    const m = String(date.getMonth()+1).padStart(2,'0');
    const d = String(date.getDate()).padStart(2,'0');
    return `${y}-${m}-${d}`;
}

function loadDays(){
    $.get('document_actions.php',{action:'get_archive_days'},function(r){
        archiveDaysMap = {};
        if(r.success && r.days && r.days.length){
            r.days.forEach(d=>{
                archiveDaysMap[d.archive_date] = {
                    total:    +d.total,
                    incoming: +d.incoming,
                    outgoing: +d.outgoing,
                    internal: +d.internal
                };
            });
        }

        // Navigate to the most recent archived month (or stay on current month)
        const dates = Object.keys(archiveDaysMap).sort().reverse();
        if(dates.length){
            const startDate = new Date(dates[0]+'T00:00:00');
            calYear  = startDate.getFullYear();
            calMonth = startDate.getMonth();
        }

        renderCalendar();

        // Auto-select the most recent archived day
        if(dates.length){
            selectCalDay(dates[0]);
        }
    },'json').fail(()=>{
        $('#calDayGrid').html('<div style="grid-column:1/-1;text-align:center;padding:16px;color:#ef4444;font-size:.8rem;"><i class="fas fa-exclamation-circle mr-1"></i>Failed to load.</div>');
    });
}

function renderCalendar(){
    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    $('#calTitle').text(monthNames[calMonth]+' '+calYear);

    const todayStr = fmtYMD(new Date());

    const firstDay    = new Date(calYear, calMonth, 1).getDay();      // 0=Sun
    const daysInMonth = new Date(calYear, calMonth+1, 0).getDate();
    const daysInPrev  = new Date(calYear, calMonth, 0).getDate();

    let html = '';

    // Leading filler cells (previous month's tail)
    for(let i = firstDay-1; i >= 0; i--){
        html += `<div class="arc-cal-day cal-other-month"><span>${daysInPrev-i}</span></div>`;
    }

    // Current month's day cells
    for(let d = 1; d <= daysInMonth; d++){
        const dateStr = fmtYMD(new Date(calYear, calMonth, d));
        const info    = archiveDaysMap[dateStr];  // truthy only for archived days
        const isToday = dateStr === todayStr;
        const isSel   = dateStr === selectedDate;

        let cls = 'arc-cal-day';
        if(isToday) cls += ' cal-today';
        if(isSel)   cls += ' cal-selected';
        if(info)    cls += ' cal-has-archive';

        // Store the date on a data-attr — event delegation reads it (no inline onclick)
        const dataDate = info ? `data-arcdate="${dateStr}"` : '';
        const tipText  = info
            ? `${info.total} doc(s) · ${info.incoming||0} in / ${info.outgoing||0} out / ${info.internal||0} int`
            : '';
        const titleAttr = info ? `title="${tipText}"` : '';

        let inner = `<span>${d}</span>`;
        if(info){
            inner += `<div class="arc-cal-dot"></div>`;
            if(info.total > 0) inner += `<div class="arc-cal-count">${info.total}</div>`;
        }

        html += `<div class="${cls}" ${dataDate} ${titleAttr}>${inner}</div>`;
    }

    // Trailing filler cells
    const totalCells = firstDay + daysInMonth;
    const trailing   = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for(let d = 1; d <= trailing; d++){
        html += `<div class="arc-cal-day cal-other-month"><span>${d}</span></div>`;
    }

    $('#calDayGrid').html(html);

    // Disable "next" when already on current month
    const now = new Date();
    const atCurrentMonth = calYear === now.getFullYear() && calMonth === now.getMonth();
    $('#calNext').prop('disabled', atCurrentMonth).css('opacity', atCurrentMonth ? .35 : 1);
}

function selectCalDay(dateStr){
    if(!archiveDaysMap[dateStr]) return; // not an archived day — ignore
    selectedDate = dateStr;
    renderCalendar();  // re-render to move the selected highlight
    loadArchive();
}

/* ── Init — everything inside $(function) so DOM is ready ──── */
$(function(){
    if(localStorage.getItem('darkMode')==='1') $('body').addClass('dark-mode');

    // ── Event delegation for calendar day clicks ──────────────
    // Attached to the stable parent #calendarWidget; survives grid re-renders
    $('#calendarWidget').on('click', '.arc-cal-day[data-arcdate]', function(){
        selectCalDay($(this).data('arcdate'));
    });

    // ── Calendar nav buttons ──────────────────────────────────
    $('#calPrev').on('click', function(){
        calMonth--;
        if(calMonth < 0){ calMonth = 11; calYear--; }
        renderCalendar();
    });

    $('#calNext').on('click', function(){
        const now = new Date();
        if(calYear === now.getFullYear() && calMonth === now.getMonth()) return;
        calMonth++;
        if(calMonth > 11){ calMonth = 0; calYear++; }
        renderCalendar();
    });

    // ── Load archive data & start auto-archive watcher ───────
    loadDays();
    startMidnightWatcher();
});
function loadArchive(){
    if(!selectedDate) return;
    $('#selectedDateLabel').html('<i class="fas fa-spinner fa-spin mr-1" style="font-size:.8rem;"></i> '+formatDate(selectedDate));
    $('#archiveSummary').text('Loading…');
    $('#archiveTableWrapper').html('<div class="archive-empty"><i class="fas fa-spinner fa-spin" style="font-size:2rem;opacity:.4;"></i></div>');
    $('#exportBtn').prop('disabled',true);
    if(archiveDT){archiveDT.destroy();archiveDT=null;}

    const params={action:'get_archive',archive_date:selectedDate};
    if(selectedKind) params.kind=selectedKind;

    $.get('document_actions.php',params,function(r){
        if(!r.success){
            $('#archiveTableWrapper').html('<div class="archive-empty"><i class="fas fa-exclamation-circle"></i>'+esc(r.message||'Error.')+'</div>');
            return;
        }
        currentRows=r.documents||[];
        const kl=selectedKind?' — '+selectedKind.charAt(0).toUpperCase()+selectedKind.slice(1):'';
        $('#selectedDateLabel').text(formatDate(selectedDate)+kl);
        $('#archiveSummary').text(r.count+' document'+(r.count!==1?'s':'')+' archived on this date'+(selectedKind?' ('+selectedKind+')':''));

        // Banner stats
        const inc=currentRows.filter(d=>d.kind==='incoming').length;
        const out=currentRows.filter(d=>d.kind==='outgoing').length;
        const int_=currentRows.filter(d=>d.kind==='internal').length;
        let sc='';
        if(inc) sc+=`<span class="stat-chip"><span class="chip-dot" style="background:#93c5fd;"></span>${inc} Incoming</span>`;
        if(out) sc+=`<span class="stat-chip"><span class="chip-dot" style="background:#86efac;"></span>${out} Outgoing</span>`;
        if(int_) sc+=`<span class="stat-chip"><span class="chip-dot" style="background:#c4b5fd;"></span>${int_} Internal</span>`;
        $('#bannerStats').html(sc);

        if(!currentRows.length){
            $('#archiveTableWrapper').html('<div class="archive-empty"><i class="fas fa-box-open"></i>No documents archived for this date'+(selectedKind?' ('+selectedKind+')':'')+'.</div>');
            return;
        }

        let rows='';
        currentRows.forEach((doc,i)=>{
            const kindIcon={'incoming':'fa-inbox','outgoing':'fa-paper-plane','internal':'fa-exchange-alt'}[doc.kind]||'fa-file';
            const kb=`<span class="kind-badge kind-${esc(doc.kind)}"><i class="fas ${kindIcon}"></i>${esc(doc.kind)}</span>`;
            const sk=(doc.status||'pending').toLowerCase().replace(/\s+/g,'-');
            const sb=`<span class="status-badge status-${sk}">${esc(doc.status||'—')}</span>`;
            const df=doc.date_forwarded?formatDT(doc.date_forwarded):'—';
            const aa=doc.archived_at?formatDT(doc.archived_at):'—';
            rows+=`<tr>
                <td><span class="doc-id-cell">#${i+1}</span></td>
                <td>${esc(doc.document_number)}</span></td>
                <td>
                    <div style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;" title="${esc(doc.document_name)}">${esc(doc.document_name)}</div>
                </td>
                <td style="color:var(--doc-muted);font-size:.8rem;">${esc(doc.document_type||'—')}</td>
                <td>${kb}</td>
                <td>${sb}</td>
                <td>
                    <div class="person-name">${esc(doc.forwarded_by||'—')}</div>
                    ${doc.from_section?`<div class="cell-meta"><i class="fas fa-building"></i>${esc(doc.from_section)}</div>`:''}
                </td>
                <td>
                    ${doc.to_section?`<div class="cell-meta"><i class="fas fa-building"></i>${esc(doc.to_section)}</div>`:'<span style="color:var(--doc-muted);font-size:.8rem;">—</span>'}
                    ${doc.date_forwarded?`<div class="cell-meta"><i class="fas fa-clock"></i>${df}</div>`:''}
                </td>
                <td style="font-size:.75rem;color:var(--doc-muted);white-space:nowrap;">${aa}</td>
                <td>
                    <button class="action-btn action-btn-view" onclick="viewSnapshot(${i})" title="View full snapshot"><i class="fas fa-eye"></i></button>
                </td>
            </tr>`;
        });

        $('#archiveTableWrapper').html(`
            <div class="table-responsive">
            <table id="archiveTable" class="table mb-0">
                <thead><tr>
                    <th style="width:50px;">#</th>
                    <th style="min-width:155px;">Document No.</th>
                    <th style="min-width:200px;">Document Name</th>
                    <th style="width:110px;">Doc Type</th>
                    <th style="width:95px;">Kind</th>
                    <th style="width:105px;">Status</th>
                    <th style="min-width:160px;">Forwarded By</th>
                    <th style="min-width:160px;">Forwarded To / Date</th>
                    <th style="width:135px;">Archived At</th>
                    <th style="width:50px;"></th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
            </div>
        `);

        archiveDT=$('#archiveTable').DataTable({
            pageLength:25,
            order:[[0,'asc']],
            columnDefs:[{orderable:false,targets:[9]}],
            language:{search:'Search archive:',emptyTable:'No archived documents.'},
            dom:'<"d-flex align-items-center justify-content-between px-1 pt-2"lf>rt<"d-flex align-items-center justify-content-between px-1 pb-2"ip>'
        });

        $('#exportBtn').prop('disabled',false);
    },'json').fail(()=>{
        $('#archiveTableWrapper').html('<div class="archive-empty"><i class="fas fa-exclamation-triangle"></i>Server error loading archive.</div>');
    });
}

/* ── Export XLSX (server-side via document_export.php) ─────────── */
function exportArchiveXLSX(){
    if(!currentRows.length){ alert('No data to export.'); return; }

    // Build server-side export URL using the selected date and kind filter
    const params = new URLSearchParams({
        type: 'archive',
        date: selectedDate || '',
        kind: selectedKind || ''
    });
    window.location.href = 'document_export.php?' + params.toString();
}

/* ── View snapshot modal ───────────────────────────────────── */
function viewSnapshot(idx){
    const doc=currentRows[idx]; if(!doc) return;
    let snap={}; try{snap=JSON.parse(doc.snapshot_json||'{}')}catch(e){}

    function field(label, value, opts={}){
        const cls = opts.full ? 'snap-field snap-full' : 'snap-field';
        let valHtml;
        if(value && value.includes && value.includes('<span')){
            valHtml = `<div class="snap-field-value">${value}</div>`;
        } else {
            const isEmpty = !value || value === '—';
            const valClass = opts.mono ? 'snap-field-value snap-mono' : isEmpty ? 'snap-field-value snap-muted' : 'snap-field-value';
            valHtml = `<div class="${valClass}">${isEmpty ? '—' : esc(value)}</div>`;
        }
        return `<div class="${cls}">
            <div class="snap-field-label">${esc(label)}</div>
            ${valHtml}
        </div>`;
    }

    const kindBadge = `<span class="kind-badge kind-${esc(doc.kind)}">${esc(doc.kind)}</span>`;
    const statusBadge = `<span class="status-badge status-${(doc.status||'pending').toLowerCase().replace(/\s+/g,'-')}">${esc(doc.status||'—')}</span>`;

    let html = '<div class="snap-fields">';
    html += field('Document Number', doc.document_number, {mono:true});
    html += field('Type', doc.document_type);
    html += field('Document Name', doc.document_name, {full:true});
    html += field('Kind', kindBadge);
    html += field('Status', statusBadge);
    html += field('From Section', doc.from_section);
    html += field('To Section', doc.to_section);
    html += field('Forwarded By', doc.forwarded_by, {full:true});
    html += field('Date Forwarded', doc.date_forwarded ? formatDT(doc.date_forwarded) : null);
    html += field('Archived At', doc.archived_at ? formatDT(doc.archived_at) : null);
    html += field('Remarks', doc.remarks||'—');
    html += field('Original Record ID', doc.original_id ? '#'+doc.original_id : null, {mono:true});
    html += '</div>';

    if(Object.keys(snap).length>3){
        html+=`<div class="snap-json-label"><i class="fas fa-code"></i>Full JSON Snapshot</div>`;
        html+=`<pre>${esc(JSON.stringify(snap,null,2))}</pre>`;
    }

    $('#snapshotBody').html(html);
    $('#snapshotModal').modal('show');
}

/* ── Manual archive (masteradmin) ─────────────────────────── */
<?php if($isMasteradmin): ?>
function triggerManualArchive(){
    const today='<?= date('Y-m-d') ?>';
    Swal.fire({
        title:'Archive & Reset Documents',
        html:`<p style="font-size:.88rem;margin-bottom:6px;">
                This will <strong>permanently move</strong> all documents from the chosen date
                into the archive, then <strong>delete them</strong> from the live table
                (resetting it to zero).
              </p>
              <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:8px 12px;font-size:.8rem;color:#856404;margin-bottom:10px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>This cannot be undone.</strong> Documents will only exist in the archive after this.
              </div>
              <label style="font-size:.83rem;display:block;text-align:left;">
                Select date to archive &amp; reset:
                <input type="date" id="manualArcDate" class="swal2-input" value="${today}" max="${today}">
              </label>`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#1c4d38',
        cancelButtonColor:'#6c757d',
        confirmButtonText:'<i class="fas fa-archive mr-1"></i> Archive &amp; Reset',
        preConfirm:()=>document.getElementById('manualArcDate').value||today
    }).then(result=>{
        if(!result.isConfirmed) return;
        Swal.fire({title:'Archiving & Resetting…',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        $.post('document_actions.php',{
            action:'midnight_archive',
            triggered_by:'manual',
            archive_date:result.value
        },function(r){
            Swal.fire({
                icon:r.success?'success':'error',
                title:r.success?'Done!':'Failed',
                html:r.success
                    ?`<div style="font-size:.88rem;">
                        Moved <strong>${r.archived}</strong> document(s) to archive.<br>
                        Live table reset — <strong>${r.deleted}</strong> record(s) removed.
                      </div>`
                    :esc(r.message||'Unknown error'),
                timer:4000,showConfirmButton:false,timerProgressBar:true
            }).then(()=>{ loadDays(); });
        },'json').fail(()=>Swal.fire({icon:'error',title:'Server Error',text:'Could not reach server.'}));
    });
}
<?php else: ?>
function triggerManualArchive(){}
<?php endif; ?>

/* ── Helpers ───────────────────────────────────────────────── */
function esc(str){const d=document.createElement('div');d.textContent=str||'';return d.innerHTML;}

function formatDate(ds){
    if(!ds) return '—';
    const[y,mo,d]=ds.split('-').map(Number);
    return new Date(y,mo-1,d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'});
}

function formatDT(ds){
    if(!ds) return '—';
    try{
        const d=new Date(ds.replace(' ','T'));
        return d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})
               +' '+d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
    }catch(e){return ds.substring(0,16);}
}


</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>