<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/projects.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize project manager

$projectManager = new ProjectManager();
$canAssignTasks = $projectManager->canAssignTasks($_SESSION['emp_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scrumboard | NIA-ACIMO AIMS</title>
  
  <?php include '../includes/header.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <!-- Select2 (multi-select assignee) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
  <!-- Modern Scrumboard UI -->
  <style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

/* ══════════════════════════════════════════════════
   SCRUM BOARD — LIGHT MODE (default)
   ══════════════════════════════════════════════════ */
:root {
  /* palette — sourced from the app-wide theme defined in mainheader.php */
  --s-bg:       var(--body-bg, #eef7f2);
  --s-surface:  var(--card-bg, #ffffff);
  --s-surface2: var(--table-stripe, #f0faf5);
  --s-surface3: #e2f3ea;
  --s-border:   var(--card-border, rgba(42,152,99,0.15));

  /* accent */
  --s-teal:     var(--green-dark, #2a9863);
  --s-teal-dim: rgba(42,152,99,.10);
  --s-teal-glow: 0 0 20px rgba(36,231,143,.15);
  --s-violet:   var(--green-mid, #1a5c38);

  /* text */
  --s-text:     var(--text-primary, #0f2d1e);
  --s-muted:    var(--text-muted, #4a7a5e);

  /* status */
  --s-danger:   #cf222e;
  --s-warning:  #9a6700;
  --s-green:    #1a7f37;
  --s-blue:     var(--green-dark, #2a9863);

  /* misc */
  --s-radius: 10px;
  --s-shadow: 0 1px 3px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.06);
  --s-font: 'Plus Jakarta Sans', sans-serif;
  --s-mono: 'JetBrains Mono', monospace;

  /* column specific */
  --s-col-bg:      #e9f5ee;
  --s-col-header:  var(--card-bg, #ffffff);
  --s-col-border:  var(--card-border, rgba(42,152,99,0.15));
  --s-card-bg:     var(--card-bg, #ffffff);
  --s-card-hover:  var(--table-stripe, #f0faf5);
}

/* ══════════════════════════════════════════════════
   SCRUM BOARD — DARK MODE overrides
   Mirrors body.dark-mode from mainheader.php so the board
   always matches the app-wide light/dark theme.
   ══════════════════════════════════════════════════ */
body.dark-mode {
  --s-bg:       var(--body-bg, #0b1f17);
  --s-surface:  var(--card-bg, #102f22);
  --s-surface2: var(--table-stripe, #122b1d);
  --s-surface3: #16352a;
  --s-border:   var(--card-border, rgba(36,231,143,0.10));
  --s-teal:     var(--green, #24e78f);
  --s-teal-dim: rgba(36,231,143,.12);
  --s-teal-glow: 0 0 20px rgba(36,231,143,.2);
  --s-violet:   var(--green-dark, #2a9863);
  --s-text:     var(--text-primary, #d4f5e5);
  --s-muted:    var(--text-muted, #6aad8a);
  --s-danger:   #f85149;
  --s-warning:  #d29922;
  --s-green:    #3fb950;
  --s-blue:     var(--green, #24e78f);
  --s-shadow:   0 8px 32px rgba(0,0,0,.4);
  --s-col-bg:      #0e2619;
  --s-col-header:  var(--card-bg, #102f22);
  --s-col-border:  var(--card-border, rgba(36,231,143,0.10));
  --s-card-bg:     var(--card-bg, #102f22);
  --s-card-hover:  #16352a;
}

/* ══════════════════════════════════════════════════
   BASE
   ══════════════════════════════════════════════════ */
body { font-family: var(--s-font) !important; background: var(--s-bg) !important; color: var(--s-text) !important; }
.content-wrapper { background: var(--s-bg) !important; }

/* ══════════════════════════════════════════════════
   SCRUM HEADER BAR
   ══════════════════════════════════════════════════ */
.scrum-header {
  background: var(--s-surface) !important;
  border-bottom: 1px solid var(--s-border) !important;
  padding: 12px 20px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  flex-wrap: wrap !important;
  gap: 10px !important;
}
.scrum-content {
  background: var(--s-bg) !important;
  padding: 16px 20px !important;
  /* Full viewport height minus header bars → kanban fills vertically */
  height: calc(100vh - 116px);
  display: flex !important;
  flex-direction: column !important;
  overflow: hidden !important;
}

/* ── Project title / status ── */
#currentProjectTitle {
  font-family: var(--s-font) !important;
  font-size: 1.05rem !important;
  font-weight: 700 !important;
  color: var(--s-text) !important;
  letter-spacing: -.3px;
}
#currentProjectStatus {
  background: var(--s-teal-dim) !important;
  color: var(--s-teal) !important;
  border: 1px solid color-mix(in srgb, var(--s-teal) 30%, transparent) !important;
  border-radius: 20px !important;
  font-size: .7rem !important;
  font-weight: 600 !important;
  padding: 3px 10px !important;
}

/* ── Header buttons ── */
.scrum-header .btn-outline-primary {
  background: transparent !important;
  color: var(--s-text) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  font-size: .8rem !important;
  font-weight: 500 !important;
  font-family: var(--s-font) !important;
  transition: all .2s !important;
}
.scrum-header .btn-outline-primary:hover {
  border-color: var(--s-teal) !important;
  color: var(--s-teal) !important;
  background: var(--s-teal-dim) !important;
}
.scrum-header .btn-outline-secondary {
  background: transparent !important;
  color: var(--s-muted) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  transition: all .2s !important;
}
.scrum-header .btn-outline-secondary:hover {
  color: var(--s-text) !important;
  background: var(--s-surface2) !important;
}
.scrum-header .btn-primary {
  background: var(--s-teal) !important;
  color: #fff !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 700 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  transition: all .2s !important;
}
body:not(.dark-mode) .scrum-header .btn-primary { color: #fff !important; }
.scrum-header .btn-primary:hover { filter: brightness(1.1) !important; transform: translateY(-1px); }
.scrum-header .btn-success {
  background: var(--s-surface2) !important;
  color: var(--s-green) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
}
.scrum-header .btn-info {
  background: var(--s-surface2) !important;
  color: var(--s-blue) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
}
.scrum-header .form-control {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 8px !important;
  font-family: var(--s-font) !important;
  font-size: .85rem !important;
}
.scrum-header .form-control::placeholder { color: var(--s-muted) !important; }
.scrum-header .input-group-append .btn {
  background: var(--s-surface3) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important;
  border-radius: 0 8px 8px 0 !important;
}

/* ══════════════════════════════════════════════════
   JIRA-STYLE VERTICAL KANBAN BOARD
   ══════════════════════════════════════════════════ */

/* The outer wrapper that holds all columns side-by-side */
.columns-container {
  display: flex !important;
  flex-direction: row !important;
  align-items: flex-start !important;
  gap: 12px !important;
  height: 100% !important;
  overflow-x: auto !important;
  overflow-y: hidden !important;
  padding-bottom: 8px !important;
}
/* Scrollbar for horizontal board scroll */
.columns-container::-webkit-scrollbar { height: 6px; }
.columns-container::-webkit-scrollbar-track { background: var(--s-bg); border-radius: 3px; }
.columns-container::-webkit-scrollbar-thumb { background: var(--s-surface3); border-radius: 3px; }
.columns-container::-webkit-scrollbar-thumb:hover { background: var(--s-muted); }

/* Each kanban column — fixed width, full height, scrolls cards vertically */
.column {
  background: var(--s-col-bg) !important;
  border: 1px solid var(--s-col-border) !important;
  border-radius: 12px !important;
  box-shadow: var(--s-shadow) !important;
  width: 280px !important;
  min-width: 280px !important;
  max-width: 280px !important;
  height: 100% !important;
  display: flex !important;
  flex-direction: column !important;
  flex-shrink: 0 !important;
  padding: 0 !important;
  overflow: hidden !important;
}

/* Column header — fixed, never scrolls */
.column-header {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 12px 14px 10px !important;
  background: var(--s-col-header) !important;
  border-radius: 12px 12px 0 0 !important;
  border-bottom: 1px solid var(--s-col-border) !important;
  flex-shrink: 0 !important;
}
.column-title {
  font-family: var(--s-font) !important;
  font-size: .78rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: .6px !important;
  color: var(--s-muted) !important;
}
.column-actions { display: flex; align-items: center; gap: 6px; }
.task-count {
  background: var(--s-surface3) !important;
  color: var(--s-muted) !important;
  border-radius: 10px !important;
  padding: 1px 8px !important;
  font-size: .68rem !important;
  font-family: var(--s-mono) !important;
  font-weight: 600 !important;
}

/* Task list area — scrolls vertically, fills remaining column height */
.tasks-container {
  flex: 1 !important;
  overflow-y: auto !important;
  overflow-x: hidden !important;
  padding: 10px 10px 6px !important;
  background: var(--s-col-bg) !important;
  min-height: 60px !important;
}
.tasks-container::-webkit-scrollbar { width: 4px; }
.tasks-container::-webkit-scrollbar-track { background: transparent; }
.tasks-container::-webkit-scrollbar-thumb { background: var(--s-surface3); border-radius: 2px; }

/* Add Task footer inside column */
.column-footer {
  flex-shrink: 0 !important;
  padding: 8px 10px 10px !important;
  background: var(--s-col-bg) !important;
  border-top: 1px solid var(--s-col-border) !important;
  border-radius: 0 0 12px 12px !important;
}

/* ══════════════════════════════════════════════════
   TASK CARDS
   ══════════════════════════════════════════════════ */
.task-card {
  background: var(--s-card-bg) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  padding: 10px 12px !important;
  margin-bottom: 8px !important;
  box-shadow: 0 1px 2px rgba(0,0,0,.07) !important;
  transition: box-shadow .15s, transform .15s, border-color .15s !important;
  cursor: pointer !important;
}
.task-card:last-child { margin-bottom: 0 !important; }
.task-card:hover {
  border-color: var(--s-teal) !important;
  box-shadow: 0 3px 12px rgba(0,0,0,.12) !important;
  transform: translateY(-1px) !important;
}
.task-card.dragging { opacity: .45 !important; border-style: dashed !important; }

/* drag-over highlight on column */
.tasks-container.drag-over {
  background: var(--s-teal-dim) !important;
  border-radius: 8px;
}

.task-title {
  font-family: var(--s-font) !important;
  font-size: .84rem !important;
  font-weight: 600 !important;
  color: var(--s-text) !important;
  margin-bottom: 8px !important;
  line-height: 1.4 !important;
}
.task-meta { color: var(--s-muted) !important; font-size: .72rem !important; }
.task-label {
  font-size: .65rem !important;
  font-weight: 600 !important;
  border-radius: 4px !important;
  padding: 2px 7px !important;
  letter-spacing: .3px;
}

/* ── Priority badges ── */
.task-priority { border-radius: 4px !important; font-size: .6rem !important; font-weight: 700 !important; letter-spacing: .5px; display: inline-block; margin-bottom: 6px; }
.priority-urgent { background: rgba(207,34,46,.12) !important; color: var(--s-danger) !important; border: 1px solid rgba(207,34,46,.3) !important; padding: 2px 6px; border-radius: 4px; }
.priority-high   { background: rgba(154,103,0,.12) !important; color: var(--s-warning) !important; border: 1px solid rgba(154,103,0,.3) !important; padding: 2px 6px; border-radius: 4px; }
.priority-medium { background: rgba(9,105,218,.10) !important; color: var(--s-blue) !important; border: 1px solid rgba(9,105,218,.25) !important; padding: 2px 6px; border-radius: 4px; }
.priority-low    { background: rgba(26,127,55,.10) !important; color: var(--s-green) !important; border: 1px solid rgba(26,127,55,.25) !important; padding: 2px 6px; border-radius: 4px; }
body.dark-mode .priority-urgent { background: rgba(248,81,73,.15) !important; border-color: rgba(248,81,73,.3) !important; }
body.dark-mode .priority-high   { background: rgba(210,153,34,.15) !important; color: #e3a520 !important; border-color: rgba(210,153,34,.3) !important; }
body.dark-mode .priority-medium { background: rgba(88,166,255,.12) !important; border-color: rgba(88,166,255,.25) !important; }
body.dark-mode .priority-low    { background: rgba(63,185,80,.12) !important; border-color: rgba(63,185,80,.25) !important; }

/* ── Labels ── */
.label-revise    { background: rgba(154,103,0,.12) !important; color: var(--s-warning) !important; }
.label-urgent    { background: rgba(207,34,46,.12) !important; color: var(--s-danger) !important; }
.label-design    { background: rgba(130,80,223,.12) !important; color: var(--s-violet) !important; }
.label-development { background: rgba(26,127,55,.12) !important; color: var(--s-green) !important; }
.label-review    { background: rgba(9,105,218,.10) !important; color: var(--s-blue) !important; }

/* ── Empty column state ── */
.empty-column { color: var(--s-muted) !important; text-align: center; padding: 24px 12px; }
.empty-column i { opacity: .3 !important; font-size: 1.5rem; display: block; margin-bottom: 6px; }

/* ══════════════════════════════════════════════════
   CARDS (monitoring / my tasks)
   ══════════════════════════════════════════════════ */
.card {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 12px !important;
  box-shadow: var(--s-shadow) !important;
}
.card-header {
  background: var(--s-surface) !important;
  border-bottom: 1px solid var(--s-border) !important;
  padding: 14px 20px !important;
}
.card-title {
  font-family: var(--s-font) !important;
  font-weight: 700 !important;
  font-size: .9rem !important;
  color: var(--s-text) !important;
  letter-spacing: -.2px;
}
.card-body { background: var(--s-surface) !important; padding: 18px !important; }

/* ══════════════════════════════════════════════════
   TABLES
   ══════════════════════════════════════════════════ */
.table { color: var(--s-text) !important; }
.table thead th {
  background: var(--s-surface2) !important;
  border: none !important;
  border-bottom: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important;
  font-family: var(--s-font) !important;
  font-size: .68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: .7px !important;
  padding: 10px 14px !important;
}
.table tbody tr { transition: background .12s; }
.table tbody tr:hover { background: var(--s-surface2) !important; }
.table tbody td {
  border-top: 1px solid var(--s-border) !important;
  border-left: none !important;
  border-right: none !important;
  padding: 12px 14px !important;
  vertical-align: middle !important;
  font-size: .85rem !important;
  color: var(--s-text) !important;
}
.table-bordered { border: none !important; }
.table-danger td, tr.table-danger { background: rgba(207,34,46,.07) !important; }

/* ── Table action buttons ── */
.btn-sm.btn-info    { background: rgba(9,105,218,.10) !important; color: var(--s-blue) !important; border: 1px solid rgba(9,105,218,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-primary { background: rgba(9,105,218,.10) !important; color: var(--s-teal) !important; border: 1px solid rgba(9,105,218,.20) !important; border-radius: 7px !important; }
.btn-sm.btn-success { background: rgba(26,127,55,.10) !important; color: var(--s-green) !important; border: 1px solid rgba(26,127,55,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-warning { background: rgba(154,103,0,.10) !important; color: var(--s-warning) !important; border: 1px solid rgba(154,103,0,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-danger  { background: rgba(207,34,46,.10) !important; color: var(--s-danger) !important; border: 1px solid rgba(207,34,46,.25) !important; border-radius: 7px !important; }
.btn-sm:hover { filter: brightness(1.1); transform: translateY(-1px); }

body.dark-mode .btn-sm.btn-info    { background: var(--s-teal-dim) !important; color: var(--s-blue) !important; border-color: color-mix(in srgb, var(--s-blue) 25%, transparent) !important; }
body.dark-mode .btn-sm.btn-primary { background: var(--s-teal-dim) !important; color: var(--s-teal) !important; border-color: color-mix(in srgb, var(--s-teal) 25%, transparent) !important; }
body.dark-mode .btn-sm.btn-success { background: rgba(63,185,80,.12) !important; color: #3fb950 !important; border-color: rgba(63,185,80,.25) !important; }
body.dark-mode .btn-sm.btn-warning { background: rgba(210,153,34,.12) !important; color: #e3a520 !important; border-color: rgba(210,153,34,.25) !important; }
body.dark-mode .btn-sm.btn-danger  { background: rgba(248,81,73,.12) !important; color: #f85149 !important; border-color: rgba(248,81,73,.25) !important; }

/* Status badges */
.badge-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border-radius: 5px !important; }
.badge-warning   { background: rgba(154,103,0,.15) !important; color: var(--s-warning) !important; border-radius: 5px !important; }
.badge-info      { background: rgba(9,105,218,.12) !important; color: var(--s-blue) !important; border-radius: 5px !important; }
.badge-primary   { background: rgba(130,80,223,.12) !important; color: var(--s-violet) !important; border-radius: 5px !important; }
.badge-success   { background: rgba(26,127,55,.12) !important; color: var(--s-green) !important; border-radius: 5px !important; }
.badge-danger    { background: rgba(207,34,46,.12) !important; color: var(--s-danger) !important; border-radius: 5px !important; }

body.dark-mode .badge-warning { background: rgba(210,153,34,.18) !important; color: #e3a520 !important; }
body.dark-mode .badge-info    { background: rgba(88,166,255,.15) !important; color: #58a6ff !important; }
body.dark-mode .badge-primary { background: var(--s-teal-dim) !important; color: var(--s-violet) !important; }
body.dark-mode .badge-success { background: rgba(63,185,80,.15) !important; color: #3fb950 !important; }
body.dark-mode .badge-danger  { background: rgba(248,81,73,.15) !important; color: #f85149 !important; }

/* Progress bar */
.progress { background: var(--s-surface3) !important; border-radius: 6px !important; }
.progress-bar.bg-success { background: var(--s-teal) !important; border-radius: 6px !important; }

/* ══════════════════════════════════════════════════
   MODALS
   ══════════════════════════════════════════════════ */
.modal-content {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 14px !important;
  box-shadow: 0 24px 80px rgba(0,0,0,.18) !important;
  color: var(--s-text) !important;
  font-family: var(--s-font) !important;
}
body.dark-mode .modal-content { box-shadow: 0 24px 80px rgba(0,0,0,.6) !important; }
.modal-header {
  background: var(--s-surface2) !important;
  border-bottom: 1px solid var(--s-border) !important;
  border-radius: 14px 14px 0 0 !important;
  padding: 16px 20px !important;
}
.modal-title {
  font-family: var(--s-font) !important;
  font-weight: 700 !important;
  font-size: .95rem !important;
  color: var(--s-text) !important;
}
.modal-header .close { color: var(--s-muted) !important; text-shadow: none !important; opacity: 1 !important; }
.modal-body { padding: 20px !important; background: var(--s-surface) !important; }
.modal-footer { border-top: 1px solid var(--s-border) !important; padding: 14px 20px !important; background: var(--s-surface2) !important; border-radius: 0 0 14px 14px !important; }

/* View task modal */
#viewTaskKey { font-family: var(--s-mono) !important; font-size: .8rem !important; }
#viewTaskModal .modal-body { max-height: 80vh; overflow-y: auto; }

/* Status/priority badges in modal */
.status-badge { background: var(--s-surface3) !important; color: var(--s-muted) !important; border-radius: 5px !important; padding: 2px 8px; font-size: .75rem; }
.priority-badge.priority-urgent { background: rgba(207,34,46,.12) !important; color: var(--s-danger) !important; padding: 2px 8px; border-radius: 5px; font-size: .75rem; }
.priority-badge.priority-high   { background: rgba(154,103,0,.12) !important; color: var(--s-warning) !important; padding: 2px 8px; border-radius: 5px; font-size: .75rem; }
.priority-badge.priority-medium { background: rgba(9,105,218,.10) !important; color: var(--s-blue) !important; padding: 2px 8px; border-radius: 5px; font-size: .75rem; }
.priority-badge.priority-low    { background: rgba(26,127,55,.10) !important; color: var(--s-green) !important; padding: 2px 8px; border-radius: 5px; font-size: .75rem; }
body.dark-mode .priority-badge.priority-urgent { background: rgba(248,81,73,.15) !important; color: #f85149 !important; }
body.dark-mode .priority-badge.priority-high   { background: rgba(210,153,34,.15) !important; color: #e3a520 !important; }
body.dark-mode .priority-badge.priority-medium { background: rgba(88,166,255,.12) !important; color: #58a6ff !important; }
body.dark-mode .priority-badge.priority-low    { background: rgba(63,185,80,.12) !important; color: #3fb950 !important; }

/* Detail labels in modal */
.detail-item label, .text-muted.small.mb-1 { color: var(--s-muted) !important; font-size: .7rem !important; text-transform: uppercase; letter-spacing: .5px; }

/* Activity */
.activity-item { border-bottom: 1px solid var(--s-border) !important; }
.activity-avatar { background: var(--s-surface3) !important; color: var(--s-teal) !important; border: 1px solid var(--s-border) !important; font-family: var(--s-mono) !important; }
.activity-meta { color: var(--s-muted) !important; }
.comment-input { border-bottom: 1px solid var(--s-border) !important; }

/* Modal form controls */
.modal .form-control, .modal .form-control:focus {
  background: var(--s-surface2) !important;
  border: 1.5px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 8px !important;
  font-family: var(--s-font) !important;
  font-size: .875rem !important;
}
.modal .form-control::placeholder { color: var(--s-muted) !important; }

/* Chrome/Edge autofill ignores background-color and forces its own white
   or pale-blue fill via an internal UA style — this is the trick to
   override it: an inset box-shadow the size of the field paints over it. */
.modal .form-control:-webkit-autofill,
.modal .form-control:-webkit-autofill:hover,
.modal .form-control:-webkit-autofill:focus,
.modal .form-control:-webkit-autofill:active,
input.form-control:-webkit-autofill,
input.form-control:-webkit-autofill:hover,
input.form-control:-webkit-autofill:focus,
input.form-control:-webkit-autofill:active {
  -webkit-box-shadow: 0 0 0 1000px var(--s-surface2) inset !important;
  box-shadow: 0 0 0 1000px var(--s-surface2) inset !important;
  -webkit-text-fill-color: var(--s-text) !important;
  caret-color: var(--s-text) !important;
  transition: background-color 5000s ease-in-out 0s;
}
.modal .form-control:focus { border-color: var(--s-teal) !important; box-shadow: 0 0 0 3px var(--s-teal-dim) !important; }
.modal label { color: var(--s-muted) !important; font-size: .72rem !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: .5px !important; margin-bottom: 5px !important; }

/* Checkbox labels */
.custom-control-label { color: var(--s-text) !important; font-size: .85rem !important; text-transform: none !important; letter-spacing: 0 !important; }

/* Modal buttons */
.modal .btn-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }
.modal .btn-primary   { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 700 !important; }
.modal .btn-primary:hover { filter: brightness(1.1) !important; }
.modal .btn-danger    { background: rgba(207,34,46,.12) !important; color: var(--s-danger) !important; border: 1px solid rgba(207,34,46,.3) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }
body.dark-mode .modal .btn-primary { color: #0f2d1e !important; }
body.dark-mode .modal .btn-danger  { background: rgba(248,81,73,.15) !important; color: #f85149 !important; border-color: rgba(248,81,73,.3) !important; }

/* Modal edit/close buttons inside coloured header */
.modal-header .btn-light { background: rgba(255,255,255,.15) !important; color: #fff !important; border: 1px solid rgba(255,255,255,.2) !important; border-radius: 7px !important; font-size: .78rem !important; }
.modal-header .btn-light:hover { background: rgba(255,255,255,.25) !important; }

/* ══════════════════════════════════════════════════
   DROPDOWNS
   ══════════════════════════════════════════════════ */
.dropdown-menu {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 10px !important;
  box-shadow: var(--s-shadow) !important;
  padding: 6px !important;
}
.dropdown-item { color: var(--s-text) !important; border-radius: 7px !important; font-family: var(--s-font) !important; font-size: .85rem !important; padding: 8px 12px !important; }
.dropdown-item:hover { background: var(--s-surface3) !important; color: var(--s-teal) !important; }

/* Board / project color dot */
.board-color-badge, .project-color-badge {
  width: 10px; height: 10px;
  border-radius: 3px !important;
  display: inline-block; flex-shrink: 0;
  margin-right: 7px;
}

/* Card tool buttons */
.btn-tool { color: var(--s-muted) !important; }
.btn-tool:hover { color: var(--s-text) !important; }

/* ══════════════════════════════════════════════════
   CONTENT HEADER
   ══════════════════════════════════════════════════ */
.content-header h1 {
  font-family: var(--s-font) !important;
  font-weight: 800 !important;
  font-size: 1.5rem !important;
  color: var(--s-text) !important;
  letter-spacing: -.5px;
}
.content-header .btn-success {
  background: var(--s-teal) !important;
  color: #fff !important;
  border: none !important;
  border-radius: 9px !important;
  font-weight: 700 !important;
  font-family: var(--s-font) !important;
}
body.dark-mode .content-header .btn-success { color: #0f2d1e !important; }
.content-header .btn-success:hover { filter: brightness(1.1) !important; }

.content-header .input-group .form-control, .card-tools .form-control {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 8px 0 0 8px !important;
  font-family: var(--s-font) !important;
}
.content-header .input-group .btn, .card-tools .input-group .btn {
  background: var(--s-surface3) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important;
  border-radius: 0 8px 8px 0 !important;
}
.content-header .form-control {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 8px !important;
  font-family: var(--s-font) !important;
  font-size: .85rem !important;
}

/* Task title in table */
.task-title { font-weight: 600 !important; color: var(--s-text) !important; }

/* ══════════════════════════════════════════════════
   SCROLLBARS
   ══════════════════════════════════════════════════ */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--s-surface); }
::-webkit-scrollbar-thumb { background: var(--s-surface3); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--s-muted); }

/* No boards message */
#noBoardsMessage p { color: var(--s-muted) !important; }
#noBoardsMessage .btn-primary { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 9px !important; font-weight: 700 !important; }
body.dark-mode #noBoardsMessage .btn-primary { color: #0f2d1e !important; }

/* Empty-state buttons rendered dynamically inside the board (e.g. "No Projects
   Found" → "Create Your First Project"), not covered by #noBoardsMessage */
.columns-container .btn-primary {
  background: var(--s-teal) !important;
  color: #fff !important;
  border: none !important;
  border-radius: 9px !important;
  font-weight: 700 !important;
  font-family: var(--s-font) !important;
}
body.dark-mode .columns-container .btn-primary { color: #0f2d1e !important; }
.columns-container .btn-primary:hover { filter: brightness(1.1) !important; }

/* Refresh button */
#refreshTasks.btn-tool { background: transparent !important; }

/* Select options */
select option { background: var(--s-surface2) !important; color: var(--s-text) !important; }

/* Color input */
input[type="color"] { background: var(--s-surface2) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; height: 38px !important; padding: 3px !important; cursor: pointer; }

/* Comment textarea */
#commentText { background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important; color: var(--s-text) !important; border-radius: 8px !important; font-family: var(--s-font) !important; }
#commentText::placeholder { color: var(--s-muted) !important; }
#addCommentBtn { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 7px !important; font-weight: 700 !important; font-family: var(--s-font) !important; }
body.dark-mode #addCommentBtn { color: #0f2d1e !important; }

/* No description / no labels */
#noDescription, #noLabels { color: var(--s-muted) !important; }
.task-description { color: var(--s-text) !important; line-height: 1.7; }

/* Select2 (theme: 'bootstrap') — not covered by the global .form-control
   rules since Select2 renders its own markup outside the native <select> */
.select2-container--bootstrap .select2-selection--single,
.select2-container--bootstrap .select2-selection--multiple {
  background: var(--s-surface2) !important;
  border: 1.5px solid var(--s-border) !important;
  border-radius: 8px !important;
  min-height: 38px !important;
}
.select2-container--bootstrap .select2-selection__rendered { color: var(--s-text) !important; }
.select2-container--bootstrap .select2-selection__placeholder { color: var(--s-muted) !important; }
.select2-container--bootstrap .select2-selection__choice {
  background: var(--s-teal-dim) !important;
  border: 1px solid var(--s-teal) !important;
  color: var(--s-text) !important;
  border-radius: 5px !important;
}
.select2-container--bootstrap .select2-selection__choice__remove { color: var(--s-muted) !important; }
.select2-container--bootstrap .select2-selection__choice__remove:hover { color: var(--s-danger) !important; }
.select2-container--bootstrap .select2-search--inline .select2-search__field { color: var(--s-text) !important; }

/* Multi-select layout: when chips wrap to multiple lines, the default
   select2-bootstrap markup lets the clear-all (x) button and the search
   input overlap the wrapped chips. Force a proper flex-wrap layout and
   pin the clear button so it never sits on top of a chip. */
.select2-container--bootstrap .select2-selection--multiple {
  position: relative !important;
  padding: 5px 30px 3px 5px !important;
  height: auto !important;
  min-height: 38px !important;
}
.select2-container--bootstrap .select2-selection--multiple .select2-selection__rendered {
  display: flex !important;
  flex-wrap: wrap !important;
  align-items: center !important;
  gap: 4px !important;
  padding: 0 !important;
  margin: 0 !important;
}
.select2-container--bootstrap .select2-selection--multiple .select2-selection__choice {
  float: none !important;
  margin: 0 !important;
}
.select2-container--bootstrap .select2-selection--multiple .select2-search--inline {
  flex: 1 1 60px !important;
  margin: 0 !important;
}
.select2-container--bootstrap .select2-selection--multiple .select2-search--inline .select2-search__field {
  width: 100% !important;
  min-width: 60px !important;
  margin: 0 !important;
  height: 22px !important;
}
.select2-container--bootstrap .select2-selection__clear {
  position: absolute !important;
  top: 8px !important;
  right: 8px !important;
  left: auto !important;
  float: none !important;
  color: var(--s-muted) !important;
  background: transparent !important;
  font-size: 1rem !important;
  z-index: 2 !important;
}
.select2-container--bootstrap .select2-selection__clear:hover { color: var(--s-danger) !important; }
.select2-dropdown {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 8px !important;
  box-shadow: var(--s-shadow) !important;
}
.select2-container--bootstrap .select2-search--dropdown .select2-search__field {
  background: var(--s-surface3) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 6px !important;
}
.select2-container--bootstrap .select2-results__option {
  color: var(--s-text) !important;
  background: transparent !important;
}
.select2-container--bootstrap .select2-results__option--highlighted[aria-selected] {
  background: var(--s-teal) !important;
  color: #fff !important;
}
.select2-container--bootstrap .select2-results__option[aria-selected="true"] {
  background: var(--s-surface3) !important;
  color: var(--s-muted) !important;
}

/* Column "Add Task" button */
.add-task-to-board {
  background: transparent !important;
  border: 1px dashed var(--s-border) !important;
  color: var(--s-muted) !important;
  border-radius: 8px !important;
  font-size: .78rem !important;
  font-family: var(--s-font) !important;
  font-weight: 600 !important;
  transition: all .2s !important;
  width: 100% !important;
}
.add-task-to-board:hover {
  border-color: var(--s-teal) !important;
  color: var(--s-teal) !important;
  background: var(--s-teal-dim) !important;
}

/* Column gear / settings button */
.board-settings {
  background: transparent !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important;
  border-radius: 6px !important;
  padding: 2px 6px !important;
  font-size: .72rem !important;
}
.board-settings:hover { border-color: var(--s-teal) !important; color: var(--s-teal) !important; background: var(--s-teal-dim) !important; }

  </style>
</head>
<body class="hold-transition sidebar-mini theme-scrum">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar_scrum.php'; ?>
  <div class="content-wrapper" style="display:flex; flex-direction:column; overflow:hidden;">
    <!-- Content Wrapper. Contains page content -->
    <div class="scrum-main-content" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
      <!-- Scrum Header -->
      <div class="scrum-header">
        <div class="d-flex align-items-center">
          <h3 class="mb-0" id="currentProjectTitle">Select a Project</h3>
          <span class="badge badge-secondary ml-2" id="currentProjectStatus">No Project</span>
          <div class="btn-group ml-3">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown">
              <i class="fas fa-project-diagram mr-1"></i> Projects
            </button>
            <div class="dropdown-menu" id="projectsDropdown">
              <!-- Projects will be loaded here -->
            </div>
          </div>
          <button class="btn btn-outline-secondary ml-2" id="addNewBoardBtn" style="border-radius:8px;font-size:.8rem;font-weight:500;">
              <i class="fas fa-plus mr-2"></i>Add New Board
            </button>
        </div>
        <div class="d-flex align-items-center">
          <div class="input-group mr-3" style="width: 300px;">
            <input type="text" class="form-control" id="taskSearch" placeholder="Search tasks...">
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
          <div class="btn-group mr-2">
            <button type="button" class="btn btn-outline-primary" id="filterBtn">
              <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" class="btn btn-outline-primary" id="viewMyTasksBtn">
              <i class="fas fa-tasks"></i> My Tasks
            </button>
          </div>
          <?php if ($canAssignTasks): ?>
          <button class="btn btn-primary" id="addTaskBtn">
            <i class="fas fa-plus mr-1"></i> Add Task
          </button>
          <?php endif; ?>
          <button class="btn btn-success ml-2" id="newProjectBtn">
            <i class="fas fa-plus mr-1"></i> New Project
          </button>
        </div>
      </div>


      <!-- My Tasks Section -->
      <div class="container-fluid mt-4" id="myTasksSection" style="display: none;">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">My Tasks</h3>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-hover" id="myTasksTable">
                    <thead>
                      <tr>
                        <th>Task Title</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Created By</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="myTasksTableBody">
                      <!-- My tasks will be loaded here -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <!-- Scrum Content -->
    <div class="scrum-content" id="scrumBoardContent" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
        <div class="columns-container" id="dynamicColumnsContainer">
            <!-- Boards will be loaded dynamically here -->
            <div class="col-12 text-center py-5" id="noBoardsMessage" style="display: none;">
                <i class="fas fa-columns fa-3x text-muted mb-3"></i>
                <p class="text-muted">No boards found. Create your first board to get started.</p>
                <button class="btn btn-primary" onclick="scrumboard.showAddBoardModal()" style="border-radius:9px;font-weight:700;">
                    <i class="fas fa-plus mr-1"></i> Create First Board
                </button>
            </div>
        </div>
    </div>
    </div>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addTaskModalLabel"><i class="fas fa-plus-circle mr-2" style="color:var(--s-teal)"></i>Add New Task</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="taskForm">
          <input type="hidden" id="currentProjectId" value="">
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="taskTitle">Task Title *</label>
                <input type="text" class="form-control" id="taskTitle" placeholder="Enter task title" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="taskPriority">Priority</label>
                <select class="form-control" id="taskPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="taskDescription">Description</label>
            <textarea class="form-control" id="taskDescription" rows="3" placeholder="Enter task description"></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="taskAssignee">Assignees</label>
                <select class="form-control" id="taskAssignee" multiple="multiple" style="width:100%">
                  <!-- Assignable employees will be loaded here -->
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="taskDueDate">Due Date</label>
                <input type="date" class="form-control" id="taskDueDate">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label>Labels</label>
            <div class="d-flex flex-wrap">
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelRevise" value="revise">
                <label class="custom-control-label" for="labelRevise">Revise</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelUrgent" value="urgent">
                <label class="custom-control-label" for="labelUrgent">Urgent</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelDesign" value="design">
                <label class="custom-control-label" for="labelDesign">Design</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelDevelopment" value="development">
                <label class="custom-control-label" for="labelDevelopment">Development</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelReview" value="review">
                <label class="custom-control-label" for="labelReview">Review</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveTaskBtn">Save Task</button>
      </div>
    </div>
  </div>
</div>
<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog" aria-labelledby="editTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editTaskModalLabel"><i class="fas fa-pen mr-2" style="color:var(--s-teal)"></i>Edit Task</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editTaskForm">
          <input type="hidden" id="editTaskId" value="">
          <input type="hidden" id="editProjectId" value="">
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="editTaskTitle">Task Title *</label>
                <input type="text" class="form-control" id="editTaskTitle" placeholder="Enter task title" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="editTaskPriority">Priority</label>
                <select class="form-control" id="editTaskPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="editTaskDescription">Description</label>
            <textarea class="form-control" id="editTaskDescription" rows="3" placeholder="Enter task description"></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="editTaskAssignee">Assignees</label>
                <select class="form-control" id="editTaskAssignee" multiple="multiple" style="width:100%">
                  <!-- Assignable employees will be loaded here -->
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="editTaskDueDate">Due Date</label>
                <input type="date" class="form-control" id="editTaskDueDate">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="editTaskBoard">Board</label>
            <select class="form-control" id="editTaskBoard">
              <!-- Boards will be loaded here -->
            </select>
          </div>
          
          <div class="form-group">
            <label>Labels</label>
            <div class="d-flex flex-wrap">
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="editLabelRevise" value="revise">
                <label class="custom-control-label" for="editLabelRevise">Revise</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="editLabelUrgent" value="urgent">
                <label class="custom-control-label" for="editLabelUrgent">Urgent</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="editLabelDesign" value="design">
                <label class="custom-control-label" for="editLabelDesign">Design</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="editLabelDevelopment" value="development">
                <label class="custom-control-label" for="editLabelDevelopment">Development</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="editLabelReview" value="review">
                <label class="custom-control-label" for="editLabelReview">Review</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger mr-auto" id="deleteTaskBtn">Delete Task</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="updateTaskBtn">Update Task</button>
      </div>
    </div>
  </div>
</div>
<!-- New Project Modal -->
<div class="modal fade" id="newProjectModal" tabindex="-1" role="dialog" aria-labelledby="newProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newProjectModalLabel"><i class="fas fa-folder-plus mr-2" style="color:var(--s-teal)"></i>Create New Project</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="projectForm">
          <div class="form-group">
            <label for="projectName">Project Name *</label>
            <input type="text" class="form-control" id="projectName" placeholder="Enter project name" required>
          </div>
          <div class="form-group">
            <label for="projectCode">Project Code *</label>
            <input type="text" class="form-control" id="projectCode" placeholder="Enter project code" required>
          </div>
          <div class="form-group">
            <label for="projectDescription">Description</label>
            <textarea class="form-control" id="projectDescription" rows="3" placeholder="Enter project description"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="projectStartDate">Start Date</label>
                <input type="date" class="form-control" id="projectStartDate">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="projectEndDate">End Date</label>
                <input type="date" class="form-control" id="projectEndDate">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label for="projectColor">Color</label>
            <input type="color" class="form-control" id="projectColor" value="#007bff">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveProjectBtn">Create Project</button>
      </div>
    </div>
  </div>
</div>
<!-- Add Board Modal -->
<div class="modal fade" id="addBoardModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addBoardModalLabel"><i class="fas fa-columns mr-2" style="color:var(--s-teal)"></i>Add New Board</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="boardForm">
          <input type="hidden" id="editBoardId" value="">
          <div class="form-group">
            <label for="boardName">Board Name *</label>
            <input type="text" class="form-control" id="boardName" required>
          </div>
          <div class="form-group">
            <label for="boardDescription">Description</label>
            <textarea class="form-control" id="boardDescription" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label for="boardColor">Color</label>
            <input type="color" class="form-control" id="boardColor" value="#007bff">
          </div>
          <div class="form-group">
            <label for="boardOrder">Order</label>
            <input type="number" class="form-control" id="boardOrder" min="0" value="0">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteBoardBtn" style="display: none;">Delete</button>
        <button type="button" class="btn btn-primary" id="saveBoardBtn">Save Board</button>
      </div>
    </div>
  </div>
</div>

<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewTaskModalLabel">
          <i class="fas fa-eye mr-2" style="color:var(--s-teal)"></i>
          <span id="viewTaskKey" class="mr-1" style="color:var(--s-muted);font-size:.85em;"></span>
          <span id="viewTaskTitle"></span>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8">
            <div class="form-group">
              <label class="text-muted small">Task Title</label>
              <div class="form-control" id="viewTaskTitleField" style="background:var(--s-surface2);border-color:var(--s-border);color:var(--s-text);pointer-events:none;"></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="text-muted small">Priority</label>
              <div class="form-control d-flex align-items-center" style="background:var(--s-surface2);border-color:var(--s-border);pointer-events:none;">
                <span id="viewTaskPriority"></span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="text-muted small">Description</label>
          <div class="form-control" id="viewTaskDescription"
               style="min-height:80px;background:var(--s-surface2);border-color:var(--s-border);color:var(--s-text);pointer-events:none;white-space:pre-wrap;height:auto;"></div>
          <div id="noDescription" class="text-muted small mt-1" style="display:none;"><em>No description provided</em></div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Assignees</label>
              <div class="form-control d-flex align-items-center" id="viewTaskAssignee" style="background:var(--s-surface2);border-color:var(--s-border);pointer-events:none;height:auto;min-height:calc(1.5em + .75rem + 2px);"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Due Date</label>
              <div class="form-control" id="viewTaskDueDate" style="background:var(--s-surface2);border-color:var(--s-border);color:var(--s-text);pointer-events:none;"></div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Board</label>
              <div class="form-control d-flex align-items-center" style="background:var(--s-surface2);border-color:var(--s-border);pointer-events:none;">
                <span id="viewTaskBoard"></span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Reporter</label>
              <div class="form-control d-flex align-items-center" id="viewTaskReporter" style="background:var(--s-surface2);border-color:var(--s-border);pointer-events:none;"></div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="text-muted small">Labels</label>
          <div class="form-control d-flex flex-wrap align-items-center" style="min-height:38px;background:var(--s-surface2);border-color:var(--s-border);pointer-events:none;height:auto;gap:4px;">
            <span id="viewTaskLabels"></span>
            <span id="noLabels" class="text-muted small" style="display:none;"><em>No labels</em></span>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Created</label>
              <div class="form-control" id="viewTaskCreated" style="background:var(--s-surface2);border-color:var(--s-border);color:var(--s-muted);font-size:.85em;pointer-events:none;"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="text-muted small">Last Updated</label>
              <div class="form-control" id="viewTaskUpdated" style="background:var(--s-surface2);border-color:var(--s-border);color:var(--s-muted);font-size:.85em;pointer-events:none;"></div>
            </div>
          </div>
        </div>

        <hr style="border-color:var(--s-border);">

        <div class="form-group">
          <label class="text-muted small"><i class="fas fa-comments mr-1"></i>Activity &amp; Comments</label>
          <div id="activityTimeline" class="mb-3" style="max-height:180px;overflow-y:auto;"></div>
          <textarea class="form-control" id="commentText" rows="2" placeholder="Add a comment..." style="resize:none;"></textarea>
          <div class="mt-2 text-right">
            <button class="btn btn-primary btn-sm" id="addCommentBtn">
              <i class="fas fa-paper-plane mr-1"></i> Comment
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="editTaskBtn">
          <i class="fas fa-pen mr-1"></i> Edit Task
        </button>
      </div>
    </div>
  </div>
</div>

<script>
class Scrumboard {
  
    constructor() {
        this.currentProjectId = null;
        this.currentProject = null;
        this.projects = [];
        this.tasks = [];
        this.boards = [];
        this.canAssignTasks = <?php echo $canAssignTasks ? 'true' : 'false'; ?>;
        this.currentEmpId = <?= (int)$_SESSION['emp_id'] ?>;
        this.selectedBoardId = null;
        this.init();
    }
    
    init() {
        this.loadProjects();
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.handleUrlParameters();
    }
    
    handleUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);
        const projectId = urlParams.get('project_id');
        
        if (projectId) {
            this.pendingProjectId = projectId;
        }
    }
    
    setupEventListeners() {
        // Project buttons
        $('#newProjectBtn').click(() => this.showNewProjectModal());
        $('#saveProjectBtn').click(() => this.createProject());
        
        // Task buttons
        $('#addTaskBtn').click(() => this.showAddTaskModal());
        $('#saveTaskBtn').click(() => this.createTask());
        
        // Navigation buttons
        $('#projectsMonitoringBtn').click(() => this.toggleProjectsMonitoring());
        $('#viewMyTasksBtn').click(() => this.toggleMyTasks());
        
        // Search
        $('#searchBtn').click(() => this.searchTasks());
        $('#taskSearch').on('keypress', (e) => {
            if (e.which === 13) this.searchTasks();
        });
        
        // Board settings
        $('#boardSettingsBtn').click(() => this.showBoardSettings());
        $('#addNewBoardBtn').click(() => this.showAddBoardModal());
        $('#manageBoardsBtn').click(() => this.showManageBoards());
        $('#resetBoardsBtn').click(() => this.resetBoards());
        $('#saveBoardBtn').click(() => this.saveBoard());
        $('#deleteBoardBtn').click(() => this.deleteBoard());
        $('#updateTaskBtn').click(() => this.updateTask());
        $('#deleteTaskBtn').click(() => this.deleteTask());
        $('#editTaskBtn').click(() => this.editCurrentTask());
        // Board actions
        $(document).on('click', '.board-settings', (e) => {
            const boardId = $(e.currentTarget).data('board-id');
            const board = this.boards.find(b => b.board_id == boardId);
            if (board) {
                this.showAddBoardModal(board);
            }
        });
        
        // Task card click handler - KEEP THIS ONE
        $(document).on('click', '.task-card', (e) => {
            if ($(e.target).closest('.task-priority, .task-label').length) {
                return;
            }
            
            const taskId = $(e.currentTarget).data('task-id');
            this.viewTask(taskId);
        });

        $('#editTaskBtn').click(() => this.editCurrentTask());
        
        $('#addTaskModal').on('show.bs.modal', function () {
            $(this).removeAttr('aria-hidden');
            $('body').addClass('modal-open');
        });

        $('#addTaskModal').on('hide.bs.modal', function () {
            $(this).attr('aria-hidden', 'true');
            $('body').removeClass('modal-open');
        });

        $('#addTaskModal').on('shown.bs.modal', function () {
            $('#taskTitle').trigger('focus');
        });

        // Update the add task button handlers
        $(document).on('click', '.add-task-to-board', (e) => {
            const boardId = $(e.currentTarget).data('board-id');
            this.selectedBoardId = boardId;
            this.showAddTaskModal();
        });
    }

    // Add the showEditTaskModal method:
    showEditTaskModal(task) {
        // Populate the edit modal with task data
        $('#editTaskId').val(task.task_id);
        $('#editProjectId').val(task.project_id);
        $('#editTaskTitle').val(task.title);
        $('#editTaskDescription').val(task.description || '');
        $('#editTaskPriority').val(task.priority);
        $('#editTaskDueDate').val(task.due_date ? task.due_date.split(' ')[0] : '');
        
        // Populate assignee dropdown (multi-select)
        const assignedIds = (task.assignees || []).map(a => a.emp_id);
        this.populateAssigneeDropdown('#editTaskAssignee', assignedIds);
        
        // Populate boards dropdown
        this.populateBoardsDropdown('#editTaskBoard', task.board_id);
        
        // Set labels
        this.setEditTaskLabels(task.labels);

        // Only the project creator is allowed to delete tasks (enforced
        // again server-side in task_ajax.php — this just keeps the button
        // from being shown to people who can't use it).
        const isProjectCreator = this.currentProject && (this.currentProject.created_by == this.currentEmpId);
        $('#deleteTaskBtn').toggle(!!isProjectCreator);

        // Show the modal
        $('#editTaskModal').modal('show');
    }

    // Helper method to set the selected assignees on the (multi-select) dropdown
    populateAssigneeDropdown(selector, selectedValues) {
        const select = $(selector);
        const values = (selectedValues || []).map(v => String(v));
        select.val(values).trigger('change');
    }

    // Helper method to populate boards dropdown
    populateBoardsDropdown(selector, selectedBoardId) {
        const select = $(selector);
        select.empty();
        
        this.boards.forEach(board => {
            const option = $('<option>', {
                value: board.board_id,
                text: board.board_name
            });
            if (board.board_id == selectedBoardId) {
                option.prop('selected', true);
            }
            select.append(option);
        });
    }

    // Helper method to set labels in edit form
    setEditTaskLabels(labelsString) {
        // Clear all checkboxes first
        $('#editLabelRevise, #editLabelUrgent, #editLabelDesign, #editLabelDevelopment, #editLabelReview').prop('checked', false);
        
        if (labelsString) {
            const labels = labelsString.split(',');
            labels.forEach(label => {
                $(`#editLabel${label.charAt(0).toUpperCase() + label.slice(1)}`).prop('checked', true);
            });
        }
    }

    // Add update task method
    async updateTask() {
        const taskId = $('#editTaskId').val();
        const labels = [];
        
        if ($('#editLabelRevise').is(':checked')) labels.push('revise');
        if ($('#editLabelUrgent').is(':checked')) labels.push('urgent');
        if ($('#editLabelDesign').is(':checked')) labels.push('design');
        if ($('#editLabelDevelopment').is(':checked')) labels.push('development');
        if ($('#editLabelReview').is(':checked')) labels.push('review');
        
        const formData = {
            action: 'update_task',
            task_id: taskId,
            title: $('#editTaskTitle').val().trim(),
            description: $('#editTaskDescription').val().trim(),
            board_id: $('#editTaskBoard').val(),
            priority: $('#editTaskPriority').val(),
            labels: labels.join(','),
            due_date: $('#editTaskDueDate').val(),
            assigned_to: $('#editTaskAssignee').val() || []
        };
        
        // Validation
        if (!formData.title) {
            this.showError('Please enter a task title');
            return;
        }
        
        if (!formData.board_id) {
            this.showError('Please select a board');
            return;
        }
        
        try {
            const response = await $.ajax({
                url: '../includes/task_ajax.php',
                method: 'POST',
                data: formData,
                dataType: 'json'
            });

            if (response.success) {
                $('#editTaskModal').modal('hide');
                await this.loadProjectTasks();
                this.showSuccess('Task updated successfully');
            } else {
                this.showError(response.error || 'Failed to update task');
            }
        } catch (error) {
            console.error('Error updating task:', error);
            let errorMessage = 'Failed to update task: ';
            if (error.responseJSON && error.responseJSON.error) {
                errorMessage += error.responseJSON.error;
            } else {
                errorMessage += 'Unknown error occurred';
            }
            this.showError(errorMessage);
        }
    }

    // Add delete task method
    async deleteTask() {
        const taskId = $('#editTaskId').val();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "This task will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await $.post('../includes/task_ajax.php', {
                        action: 'delete_task',
                        task_id: taskId
                    });

                    if (response.success) {
                        $('#editTaskModal').modal('hide');
                        await this.loadProjectTasks();
                        this.showSuccess('Task deleted successfully');
                    } else {
                        this.showError(response.error || 'Failed to delete task');
                    }
                } catch (error) {
                    console.error('Error deleting task:', error);
                    this.showError('Failed to delete task');
                }
            }
        });
    }
    toggleProjectsMonitoring() {
        const monitoringSection = $('#projectsMonitoring');
        const isVisible = monitoringSection.is(':visible');
        
        if (isVisible) {
            monitoringSection.hide();
            $('#scrumBoardContent').show();
        } else {
            monitoringSection.show();
            $('#scrumBoardContent').hide();
            $('#myTasksSection').hide();
            this.loadProjectsMonitoring();
        }
    }

    toggleMyTasks() {
        const myTasksSection = $('#myTasksSection');
        const isVisible = myTasksSection.is(':visible');
        
        if (isVisible) {
            myTasksSection.hide();
            $('#scrumBoardContent').show();
        } else {
            myTasksSection.show();
            $('#scrumBoardContent').hide();
            $('#projectsMonitoring').hide();
            this.loadMyTasks();
        }
    }

    searchTasks() {
        const searchTerm = $('#taskSearch').val().toLowerCase();
        
        $('.task-card').each(function() {
            const taskTitle = $(this).find('.task-title').text().toLowerCase();
            if (taskTitle.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    async loadProjectsMonitoring() {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_projects_monitoring'
            });
            
            if (response.success) {
                this.renderProjectsMonitoring(response.projects);
            }
        } catch (error) {
            console.error('Error loading projects monitoring:', error);
        }
    }

    async loadMyTasks() {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_my_tasks'
            });
            
            if (response.success) {
                this.renderMyTasks(response.tasks);
            }
        } catch (error) {
            console.error('Error loading my tasks:', error);
        }
    }

    renderMyTasks(tasks) {
        const tbody = $('#myTasksTableBody');
        tbody.empty();
        
        tasks.forEach(task => {
            const statusDisplay = this.getStatusDisplayText(task.status);
            const row = `
                <tr>
                    <td>${task.title}</td>
                    <td>${task.project_name}</td>
                    <td><span class="status-badge">${statusDisplay}</span></td>
                    <td><span class="priority-badge priority-${task.priority}">${task.priority}</span></td>
                    <td>${task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set'}</td>
                    <td>${task.creator_name || 'Unknown'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="scrumboard.viewTask(${task.task_id})">
                            View
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    setupDragAndDrop() {
        $(document).on('dragstart', '.task-card', function(e) {
            const taskId = $(this).data('task-id');
            e.originalEvent.dataTransfer.setData('text/plain', taskId);
            setTimeout(() => {
                $(this).addClass('dragging');
            }, 0);
        });
        
        $(document).on('dragend', '.task-card', function() {
            $(this).removeClass('dragging');
        });
        
        $(document).on('dragover', '.tasks-container', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drag-over');
        });

        $(document).on('dragleave', '.tasks-container', function() {
            $(this).removeClass('drag-over');
        });
        
        $(document).on('drop', '.tasks-container', async (e) => {
            e.preventDefault();
            $(e.currentTarget).removeClass('drag-over');
            const taskId = e.originalEvent.dataTransfer.getData('text/plain');
            const newBoardId = $(e.currentTarget).data('board-id');
            
            if (taskId && newBoardId) {
                await this.updateTaskBoard(taskId, newBoardId);
            }
        });
    }
    
    async loadProjects() {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_user_projects'
            });
            
            if (response.success) {
                this.projects = response.projects;
                this.renderProjectsDropdown();
                
                let projectToSelect = null;
                let deniedProjectId = null;
                
                if (this.pendingProjectId) {
                    projectToSelect = this.projects.find(p => p.project_id == this.pendingProjectId);
                    if (!projectToSelect) {
                        deniedProjectId = this.pendingProjectId;
                    }
                    this.pendingProjectId = null;
                }
                
                if (!projectToSelect && this.projects.length > 0) {
                    projectToSelect = this.projects[0];
                }
                
                if (projectToSelect) {
                    await this.selectProject(projectToSelect.project_id);
                } else {
                    this.showNoProjectsMessage();
                }

                if (deniedProjectId) {
                    this.showNotMemberAlert(deniedProjectId);
                }
            } else {
                this.showError('Failed to load projects: ' + (response.error || 'Unknown error'));
                this.showNoProjectsMessage();
            }
        } catch (error) {
            this.showError('Failed to load projects: ' + error.message);
            this.showNoProjectsMessage();
        }
    }

    showNoProjectsMessage() {
        const container = $('.columns-container');
        container.html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Projects Found</h4>
                <p class="text-muted">You are not a member of any projects yet.</p>
                <button class="btn btn-primary" onclick="scrumboard.showNewProjectModal()">
                    <i class="fas fa-plus mr-1"></i> Create Your First Project
                </button>
            </div>
        `);
    }
    
    renderProjectsDropdown() {
        const dropdown = $('#projectsDropdown');
        dropdown.empty();
        
        if (this.projects.length === 0) {
            dropdown.append('<a class="dropdown-item text-muted" href="#">No projects found</a>');
            return;
        }
        
        this.projects.forEach(project => {
            const item = $(`
                <a class="dropdown-item project-item" href="#" data-project-id="${project.project_id}">
                    <div class="d-flex align-items-center">
                        <div class="project-color-badge" style="background-color: ${project.color}"></div>
                        <div>
                            <div class="font-weight-bold">${project.project_name}</div>
                            <small class="text-muted">${project.project_code}</small>
                        </div>
                    </div>
                </a>
            `);
            
            item.click(() => this.selectProject(project.project_id));
            dropdown.append(item);
        });
    }
    
    async selectProject(projectId) {
        try {
            this.currentProject = this.projects.find(p => p.project_id == projectId);
            
            if (!this.currentProject) {
                this.showError('Project not found or you do not have access to it');
                return;
            }
            
            this.currentProjectId = projectId;
            
            // Update UI
            $('#currentProjectTitle').text(this.currentProject.project_name);
            $('#currentProjectStatus').text(this.currentProject.status.charAt(0).toUpperCase() + this.currentProject.status.slice(1));
            $('#currentProjectStatus').removeClass('badge-secondary badge-success badge-warning badge-danger')
                .addClass(this.getStatusBadgeClass(this.currentProject.status));
            $('#currentProjectId').val(projectId);
            
            // Load boards and tasks
            await this.loadProjectBoards();
            await this.loadProjectTasks();
            await this.loadAssignableEmployees();
            
            // Hide other sections
            $('#projectsMonitoring').hide();
            $('#myTasksSection').hide();
            $('#scrumBoardContent').show();
            
        } catch (error) {
            this.showError('Failed to load project');
        }
    }
    
    getStatusBadgeClass(status) {
        const classes = {
            'active': 'badge-success',
            'completed': 'badge-primary',
            'on_hold': 'badge-warning',
            'cancelled': 'badge-danger'
        };
        return classes[status] || 'badge-secondary';
    }
    
    async loadProjectTasks() {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_project_tasks',
                project_id: this.currentProjectId
            });
            
            if (response.success) {
                this.tasks = response.tasks;
                this.renderTasks();
            }
        } catch (error) {
            this.showError('Failed to load tasks');
        }
    }
    
    renderTasks() {
        // Clear all task containers first
        $('.tasks-container').empty();
        
        console.log('All tasks:', this.tasks); // Debug log
        console.log('All boards:', this.boards); // Debug log
        
        // Group tasks by board_id
        const tasksByBoard = {};
        this.tasks.forEach(task => {
            const boardId = task.board_id;
            if (!tasksByBoard[boardId]) {
                tasksByBoard[boardId] = [];
            }
            tasksByBoard[boardId].push(task);
        });
        
        console.log('Tasks grouped by board:', tasksByBoard); // Debug log
        
        // Render tasks for each board
        this.boards.forEach(board => {
            const container = $(`.tasks-container[data-board-id="${board.board_id}"]`);
            const boardTasks = tasksByBoard[board.board_id] || [];
            
            console.log(`Board ${board.board_id} (${board.board_name}) has ${boardTasks.length} tasks`); // Debug log
            
            // Always clear the container first
            container.empty();
            
            if (boardTasks.length === 0) {
                container.append(`
                    <div class="empty-column">
                        <i class="fas fa-inbox"></i>
                        <p>No tasks</p>
                    </div>
                `);
            } else {
                boardTasks.forEach(task => {
                    const taskHtml = this.createTaskHtml(task);
                    container.append(taskHtml);
                });
            }
            
            // Update task count for this board
            const column = $(`.column[data-board-id="${board.board_id}"]`);
            column.find('.task-count').text(boardTasks.length);
        });
    }
    
    createTaskHtml(task) {
        const labels = task.labels ? task.labels.split(',') : [];
        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
        const assigneeName = (task.assignees && task.assignees.length)
            ? task.assignees.map(a => `${a.first_name} ${a.last_name}`).join(', ')
            : 'Unassigned';
        const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
        
        return `
            <div class="task-card" draggable="true" data-task-id="${task.task_id}" data-board-id="${task.board_id}">
                ${task.priority !== 'medium' ? 
                    `<div class="task-priority priority-${task.priority}">${task.priority}</div>` : ''}
                <div class="task-labels">
                    ${labels.map(label => 
                        `<span class="task-label label-${label}">${label.charAt(0).toUpperCase() + label.slice(1)}</span>`
                    ).join('')}
                </div>
                <div class="task-title" style="cursor: pointer;">${this.escapeHtml(task.title)}</div>
                <div class="task-meta">
                    <span><i class="far fa-calendar mr-1"></i> ${dueDate}</span>
                    <span><i class="far fa-user mr-1"></i> ${assigneeName}</span>
                </div>
                <small class="text-muted">Created by: ${creatorName}</small>
            </div>
        `;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async loadAssignableEmployees() {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_project_assignable_members',
                project_id: this.currentProjectId
            });
            
            if (response.success) {
                const buildOptions = (select) => {
                    select.empty();
                    response.employees.forEach(employee => {
                        const role = employee.role ? employee.role.charAt(0).toUpperCase() + employee.role.slice(1) : 'Member';
                        select.append(`<option value="${employee.emp_id}">${employee.first_name} ${employee.last_name} (${role})</option>`);
                    });
                };

                buildOptions($('#taskAssignee'));
                buildOptions($('#editTaskAssignee'));

                // Initialize Select2 for multi-assignee selection
                [$('#taskAssignee'), $('#editTaskAssignee')].forEach($el => {
                    if ($el.hasClass('select2-hidden-accessible')) {
                        $el.select2('destroy');
                    }
                });

                $('#taskAssignee').select2({
                    theme: 'bootstrap',
                    width: '100%',
                    placeholder: 'Unassigned',
                    allowClear: true,
                    dropdownParent: $('#addTaskModal')
                }).val(null).trigger('change');

                $('#editTaskAssignee').select2({
                    theme: 'bootstrap',
                    width: '100%',
                    placeholder: 'Unassigned',
                    allowClear: true,
                    dropdownParent: $('#editTaskModal')
                }).val(null).trigger('change');
            }
        } catch (error) {
            console.error('Error loading assignable employees:', error);
        }
    }
    
    async loadProjectBoards() {
        try {
            const response = await $.post('../includes/board_ajax.php', {
                action: 'get_project_boards',
                project_id: this.currentProjectId
            });
            
            if (response.success) {
                this.boards = response.boards;
                this.renderBoards();
            } else {
                this.boards = [];
                this.renderBoards();
            }
        } catch (error) {
            console.error('Error loading boards:', error);
            this.boards = [];
            this.renderBoards();
        }
    }

    renderBoards() {
        const container = $('#dynamicColumnsContainer');
        container.empty();
        
        if (this.boards.length === 0) {
            $('#noBoardsMessage').show();
            return;
        }

        $('#noBoardsMessage').hide();

        // Sort boards by order
        const sortedBoards = this.boards.sort((a, b) => a.board_order - b.board_order);
        
        sortedBoards.forEach(board => {
            const columnHtml = this.createBoardColumn(board);
            container.append(columnHtml);
        });
    }

    createBoardColumn(board) {
        const statusTasks = this.tasks.filter(task => task.board_id == board.board_id);
        const taskCount = statusTasks.length;
        
        return `
            <div class="column" data-board-id="${board.board_id}">
                <div class="column-header">
                    <div class="d-flex align-items-center">
                        <div class="board-color-badge mr-2" style="background-color: ${board.board_color}"></div>
                        <h4 class="column-title mb-0">${board.board_name}</h4>
                    </div>
                    <div class="column-actions">
                        <span class="task-count">${taskCount}</span>
                        ${this.canAssignTasks ? `
                            <button type="button" class="btn btn-sm btn-outline-secondary board-settings" 
                                    data-board-id="${board.board_id}" title="Board Settings">
                                <i class="fas fa-cog"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="tasks-container" data-board-id="${board.board_id}">
                    ${taskCount === 0 ? `
                        <div class="empty-column">
                            <i class="fas fa-inbox"></i>
                            <p>No tasks</p>
                        </div>
                    ` : ''}
                    ${statusTasks.map(task => this.createTaskHtml(task)).join('')}
                </div>
                ${this.canAssignTasks ? `
                    <div class="column-footer mt-2">
                        <button class="btn btn-sm btn-outline-primary btn-block add-task-to-board" 
                                data-board-id="${board.board_id}">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    showBoardSettings() {
        $('#boardSettingsModal').modal('show');
    }

    showAddBoardModal(board = null) {
        $('#addBoardModalLabel').text(board ? 'Edit Board' : 'Add New Board');
        $('#editBoardId').val(board ? board.board_id : '');
        $('#boardName').val(board ? board.board_name : '');
        $('#boardDescription').val(board ? board.board_description : '');
        $('#boardColor').val(board ? board.board_color : '#007bff');
        $('#boardOrder').val(board ? board.board_order : this.boards.length);
        $('#deleteBoardBtn').toggle(!!board);
        
        $('#addBoardModal').modal('show');
    }

    async saveBoard() {
        const formData = {
            board_id: $('#editBoardId').val(),
            project_id: this.currentProjectId,
            board_name: $('#boardName').val().trim(),
            board_description: $('#boardDescription').val().trim(),
            board_color: $('#boardColor').val(),
            board_order: $('#boardOrder').val()
        };

        if (!formData.board_name) {
            this.showError('Please enter a board name');
            return;
        }

        try {
            const action = formData.board_id ? 'update_board' : 'create_board';
            const response = await $.post('../includes/board_ajax.php', {
                action: action,
                ...formData
            });

            if (response.success) {
                $('#addBoardModal').modal('hide');
                $('#boardForm')[0].reset();
                await this.loadProjectBoards();
                this.showSuccess(`Board ${formData.board_id ? 'updated' : 'created'} successfully`);
            } else {
                this.showError(response.error || `Failed to ${formData.board_id ? 'update' : 'create'} board`);
            }
        } catch (error) {
            console.error('Error saving board:', error);
            this.showError(`Failed to ${formData.board_id ? 'update' : 'create'} board`);
        }
    }

    async deleteBoard() {
        const boardId = $('#editBoardId').val();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete the board and all tasks in it!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await $.post('../includes/board_ajax.php', {
                        action: 'delete_board',
                        board_id: boardId
                    });

                    if (response.success) {
                        $('#addBoardModal').modal('hide');
                        await this.loadProjectBoards();
                        this.showSuccess('Board deleted successfully');
                    } else {
                        this.showError(response.error || 'Failed to delete board');
                    }
                } catch (error) {
                    console.error('Error deleting board:', error);
                    this.showError('Failed to delete board');
                }
            }
        });
    }

    showManageBoards() {
        $('#boardSettingsModal').modal('hide');
        // For now, just show the first board for editing
        if (this.boards.length > 0) {
            this.showAddBoardModal(this.boards[0]);
        } else {
            this.showAddBoardModal();
        }
    }

    async resetBoards() {
        Swal.fire({
            title: 'Reset Boards?',
            text: "This will replace all current boards with default boards (Backlog, To Do, In Progress, Review, Done)",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await $.post('../includes/board_ajax.php', {
                        action: 'reset_boards',
                        project_id: this.currentProjectId
                    });

                    if (response.success) {
                        $('#boardSettingsModal').modal('hide');
                        await this.loadProjectBoards();
                        this.showSuccess('Boards reset to default successfully');
                    } else {
                        this.showError(response.error || 'Failed to reset boards');
                    }
                } catch (error) {
                    console.error('Error resetting boards:', error);
                    this.showError('Failed to reset boards');
                }
            }
        });
    }

    showNewProjectModal() {
        $('#newProjectModal').modal('show');
    }
    
    async createProject() {
        const formData = {
            project_name: $('#projectName').val().trim(),
            project_code: $('#projectCode').val().trim(),
            project_description: $('#projectDescription').val().trim(),
            start_date: $('#projectStartDate').val(),
            end_date: $('#projectEndDate').val(),
            color: $('#projectColor').val(),
            created_by: <?= $_SESSION['emp_id'] ?>
        };
        
        if (!formData.project_name || !formData.project_code) {
            this.showError('Please fill in all required fields');
            return;
        }
        
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'create_project',
                ...formData
            });
            
            if (response.success) {
                $('#newProjectModal').modal('hide');
                $('#projectForm')[0].reset();
                await this.loadProjects();
                this.showSuccess('Project created successfully');
                
                if (response.project_id) {
                    this.selectProject(response.project_id);
                }
            } else {
                this.showError(response.error || 'Failed to create project');
            }
        } catch (error) {
            console.error('Error creating project:', error);
            this.showError('Failed to create project');
        }
    }
    
    showAddTaskModal() {
        if (!this.currentProjectId) {
            this.showError('Please select a project first');
            return;
        }
        $('#addTaskModal').modal('show');
    }
    
    async createTask() {
        // Get the selected board ID - prioritize the board where "Add Task" was clicked
        const boardId = this.selectedBoardId || (this.boards.length > 0 ? this.boards[0].board_id : null);
        
        if (!boardId) {
            this.showError('No boards available. Please create a board first.');
            return;
        }

        const labels = [];
        if ($('#labelRevise').is(':checked')) labels.push('revise');
        if ($('#labelUrgent').is(':checked')) labels.push('urgent');
        if ($('#labelDesign').is(':checked')) labels.push('design');
        if ($('#labelDevelopment').is(':checked')) labels.push('development');
        if ($('#labelReview').is(':checked')) labels.push('review');
        
        const formData = {
            action: 'create_task',
            project_id: this.currentProjectId,
            title: $('#taskTitle').val().trim(),
            description: $('#taskDescription').val().trim(),
            board_id: boardId,
            priority: $('#taskPriority').val(),
            labels: labels.join(','),
            due_date: $('#taskDueDate').val(),
            assigned_to: $('#taskAssignee').val() || [],
            created_by: <?= $_SESSION['emp_id'] ?>
        };
        
        // Validation
        if (!formData.title) {
            this.showError('Please enter a task title');
            return;
        }
        
        if (!formData.project_id) {
            this.showError('Please select a project first');
            return;
        }
        
        try {
            console.log('Creating task with data:', formData);
            
            const response = await $.ajax({
                url: '../includes/task_ajax.php',
                method: 'POST',
                data: formData,
                dataType: 'json'
            });

            console.log('Task creation response:', response);

            if (response.success) {
                $('#addTaskModal').modal('hide');
                $('#taskForm')[0].reset();
                $('#taskAssignee').val(null).trigger('change');
                this.selectedBoardId = null;
                await this.loadProjectTasks();
                this.showSuccess('Task created successfully');
            } else {
                this.showError(response.error || 'Failed to create task');
            }
        } catch (error) {
            console.error('Error creating task:', error);
            console.error('Error status:', error.status);
            console.error('Error response text:', error.responseText);
            
            let errorMessage = 'Failed to create task: ';
            if (error.responseJSON && error.responseJSON.error) {
                errorMessage += error.responseJSON.error;
            } else if (error.statusText) {
                errorMessage += error.statusText;
            } else {
                errorMessage += 'Unknown error occurred';
            }
            
            this.showError(errorMessage);
        }
    }
    
    async updateTaskBoard(taskId, newBoardId) {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'update_task_board',
                task_id: taskId,
                board_id: newBoardId
            });
            
            if (response.success) {
                await this.loadProjectTasks();
                this.showSuccess('Task moved successfully');
            } else {
                this.showError('Failed to move task');
            }
        } catch (error) {
            console.error('Error moving task:', error);
            this.showError('Failed to move task');
        }
    }
    
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    async showNotMemberAlert(projectId) {
        let projectName = 'this project';
        let creatorLine = '';

        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_project_basic_info',
                project_id: projectId
            });
            if (response.success && response.project) {
                projectName = response.project.project_name || projectName;
                const creator = `${response.project.creator_first || ''} ${response.project.creator_last || ''}`.trim();
                if (creator) creatorLine = ` (created by ${creator})`;
            }
        } catch (error) {
            console.error('Error loading project info:', error);
        }

        Swal.fire({
            icon: 'error',
            title: "You're not a member of this project",
            text: `You don't have permission to open "${projectName}"${creatorLine}. Only project members can view its board.`,
            showCancelButton: true,
            confirmButtonText: 'Request to Join',
            confirmButtonColor: '#3085d6',
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                this.requestProjectMembership(projectId);
            }
        });
    }

    requestProjectMembership(projectId) {
        $.post('../includes/project_ajax.php', {
            action: 'request_project_membership',
            project_id: projectId
        }, function(response) {
            if (response.success) {
                Swal.fire('Request Sent', 'The project creator has been notified of your request to join.', 'success');
            } else {
                Swal.fire('Error', response.error || 'Failed to send request', 'error');
            }
        }, 'json').fail(function() {
            Swal.fire('Error', 'Failed to send request', 'error');
        });
    }
    // Add these methods to the Scrumboard class
    async viewTask(taskId) {
        try {
            const task = this.tasks.find(t => t.task_id == taskId);
            if (!task) {
                this.showError('Task not found');
                return;
            }

            // Populate modal with task data
            this.populateTaskModal(task);
            
            // Show modal with proper positioning
            $('#viewTaskModal').modal({
                backdrop: 'static',
                keyboard: true
            }).modal('show');
            
            // Center the modal
            $('#viewTaskModal').css('display', 'block');
            $('#viewTaskModal').addClass('show');
            
        } catch (error) {
            console.error('Error loading task:', error);
            this.showError('Failed to load task details');
        }
    }

    renderAvatar(container, picturePath, name, bgColor) {
        // container: jQuery element to prepend the avatar into (alongside existing text)
        const initials = (name || '?').charAt(0).toUpperCase();
        const color    = bgColor || 'var(--s-teal)';
        const css = { width:'34px', height:'34px', borderRadius:'50%', flexShrink:'0',
                      marginRight:'10px', display:'flex', alignItems:'center', justifyContent:'center',
                      fontSize:'.78rem', fontWeight:'700', color:'#fff',
                      fontFamily:'var(--s-mono)', border:'2px solid var(--s-border)' };
        const $fallback = $('<div>').css({ ...css, background: color }).text(initials);
        if (picturePath) {
            const $img = $('<img>')
                .attr({ src: `../dist/img/employees/${picturePath}`, alt: name })
                .css({ width:'34px', height:'34px', borderRadius:'50%', objectFit:'cover',
                       flexShrink:'0', marginRight:'10px', border:'2px solid var(--s-border)' })
                .on('error', function() { $(this).replaceWith($fallback); });
            container.prepend($img);
        } else {
            container.prepend($fallback);
        }
    }

    populateTaskModal(task) {
        this.currentViewingTask = task;

        // Header
        $('#viewTaskKey').text(`TASK-${task.task_id}`);
        $('#viewTaskTitle').text(task.title);
        $('#viewTaskTitleField').text(task.title);

        // Description
        if (task.description && task.description.trim()) {
            $('#viewTaskDescription').text(task.description).show();
            $('#noDescription').hide();
        } else {
            $('#viewTaskDescription').hide();
            $('#noDescription').show();
        }

        // Priority
        const priorityLabel = (task.priority || 'medium');
        $('#viewTaskPriority').html(`<span class="priority-badge priority-${priorityLabel}">${priorityLabel.charAt(0).toUpperCase() + priorityLabel.slice(1)}</span>`);

        // Assignees
        $('#viewTaskAssignee').empty();
        if (task.assignees && task.assignees.length) {
            $('#viewTaskAssignee').css('flex-wrap', 'wrap');
            task.assignees.forEach(assignee => {
                const assigneeName = `${assignee.first_name || ''} ${assignee.last_name || ''}`.trim();
                const $chip = $('<div>').css({ display: 'flex', alignItems: 'center', marginRight: '14px', marginBottom: '4px' });
                $chip.append(`<span>${assigneeName}</span>`);
                this.renderAvatar($chip, assignee.picture, assigneeName, 'var(--s-teal)');
                $('#viewTaskAssignee').append($chip);
            });
        } else {
            $('#viewTaskAssignee').html('<span class="text-muted">Unassigned</span>');
        }

        // Due date
        $('#viewTaskDueDate').text(task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set');

        // Board
        const board = this.boards.find(b => b.board_id == task.board_id);
        if (board) {
            $('#viewTaskBoard').html(`<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${board.board_color};margin-right:6px;flex-shrink:0;"></span><span>${board.board_name}</span>`);
        } else {
            $('#viewTaskBoard').text('Unknown');
        }

        // Reporter
        const reporterName = `${task.creator_first || ''} ${task.creator_last || ''}`.trim();
        $('#viewTaskReporter').html(`<span>${reporterName || 'Unknown'}</span>`);
        this.renderAvatar($('#viewTaskReporter'), task.creator_picture, reporterName, '#10b981');

        // Labels
        const labelsContainer = $('#viewTaskLabels');
        labelsContainer.empty();
        if (task.labels) {
            const labels = task.labels.split(',').filter(l => l.trim());
            if (labels.length > 0) {
                labels.forEach(label => {
                    labelsContainer.append(`<span class="task-label label-${label.trim()} mr-1">${label.trim().charAt(0).toUpperCase() + label.trim().slice(1)}</span>`);
                });
                $('#noLabels').hide();
            } else {
                $('#noLabels').show();
            }
        } else {
            $('#noLabels').show();
        }

        // Timestamps
        $('#viewTaskCreated').text(new Date(task.created_at).toLocaleString());
        $('#viewTaskUpdated').text(new Date(task.updated_at || task.created_at).toLocaleString());

        // Activity log (fetched from the server, keyed on this task)
        this.loadTaskActivity(task.task_id);
    }

    getStatusDisplayText(status) {
        // If status is empty or invalid, try to get from board
        if (!status || status === '') {
            if (this.currentViewingTask && this.currentViewingTask.board_id) {
                const board = this.boards.find(b => b.board_id == this.currentViewingTask.board_id);
                return board ? board.board_name : 'Unknown';
            }
            return 'Unknown';
        }
        
        // If status starts with 'board_', it's a custom board - get the board name
        if (status.startsWith('board_')) {
            const boardId = status.replace('board_', '');
            const board = this.boards.find(b => b.board_id == boardId);
            return board ? board.board_name : status;
        }
        
        // Convert default status like 'inprogress' to 'In Progress' for display
        const statusMap = {
            'backlog': 'Backlog',
            'todo': 'To Do',
            'inprogress': 'In Progress',
            'review': 'Review',
            'done': 'Done',
            'in progress': 'In Progress', // Handle space variation
            'to do': 'To Do' // Handle space variation
        };
        
        // If it's a known status, use the mapped display text
        const normalizedStatus = status.toLowerCase();
        if (statusMap[normalizedStatus]) {
            return statusMap[normalizedStatus];
        }
        
        // For custom statuses, convert from 'customstatus' to 'Custom Status'
        let displayText = status
            .replace(/([A-Z])/g, ' $1')
            .replace(/_/g, ' ')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .trim();
        
        // Capitalize first letter of each word
        displayText = displayText.replace(/\b\w/g, l => l.toUpperCase());
        
        return displayText || status;
    }

    async loadTaskActivity(taskId) {
        const activityTimeline = $('#activityTimeline');
        activityTimeline.html('<div class="text-muted small">Loading activity&hellip;</div>');

        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_task_activity',
                task_id: taskId
            }, null, 'json');

            // Modal may have been closed while the request was in flight
            if (!$('#activityTimeline').length) return;

            if (!response.success || !response.activity || response.activity.length === 0) {
                activityTimeline.html('<div class="text-muted small">No activity yet.</div>');
                return;
            }

            const items = response.activity.map(entry => {
                const who = entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone';
                const when = new Date(entry.created_at).toLocaleString();
                const initial = (entry.first_name || '?').charAt(0).toUpperCase();
                return `
                    <div class="activity-item d-flex">
                        <div class="activity-avatar mr-3">${this.escapeHtml(initial)}</div>
                        <div class="activity-content">
                            <div class="font-weight-bold">${this.escapeHtml(who)}</div>
                            <div class="text-muted">${this.escapeHtml(entry.description)}</div>
                            <div class="activity-meta">${when}</div>
                        </div>
                    </div>
                `;
            }).join('');

            activityTimeline.html(items);
        } catch (error) {
            console.error('Error loading activity:', error);
            if ($('#activityTimeline').length) {
                activityTimeline.html('<div class="text-muted small">Couldn\'t load activity.</div>');
            }
        }
    }

    editCurrentTask() {
        if (!this.currentViewingTask) return;
        
        // Close view modal
        $('#viewTaskModal').modal('hide');
        
        // Open edit modal with current task data
        this.showEditTaskModal(this.currentViewingTask);
    }

    // Optional: Add info message method
    showInfo(message) {
        Swal.fire({
            icon: 'info',
            title: 'Information',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
  }

// Fix the duplicate initialization - remove one of these
$(document).ready(function() {
    // Set scrumboard theme
    localStorage.setItem('currentTheme', 'scrum');
    $('.main-header').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    $('#mainFooter').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    
    window.scrumboard = new Scrumboard();
});


</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
</body>
</html>