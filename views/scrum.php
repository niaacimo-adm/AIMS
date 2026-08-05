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
$canCreateProject = $projectManager->canCreateProject($_SESSION['emp_id']);
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
  padding: 14px 24px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  flex-wrap: wrap !important;
  row-gap: 12px !important;
  column-gap: 20px !important;
}
.scrum-header-group {
  display: flex !important;
  align-items: center !important;
  flex-wrap: wrap !important;
  row-gap: 8px !important;
}
.scrum-header-group.scrum-header-meta { column-gap: 4px; min-width: 0; }
.scrum-header-group.scrum-header-actions { column-gap: 4px; margin-left: auto; }
.scrum-header-divider {
  width: 1px;
  height: 20px;
  background: var(--s-border) !important;
  margin: 0 12px;
  flex-shrink: 0;
}
.scrum-search-group {
  flex: 1 1 160px;
  min-width: 140px;
  max-width: 260px;
}
.scrum-view-toolbar {
  display: flex !important;
  align-items: center !important;
  flex-wrap: wrap !important;
  gap: 2px !important;
}
.scrum-view-toolbar .btn {
  white-space: nowrap;
}
.scrum-content {
  background: var(--s-bg) !important;
  padding: 16px 20px !important;
  /* Fills whatever space remains below the header, however tall it wraps */
  flex: 1 1 auto !important;
  min-height: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  overflow: hidden !important;
}

@media (max-width: 991.98px) {
  .scrum-header { justify-content: flex-start !important; }
  .scrum-header-group.scrum-header-actions { margin-left: 0; width: 100%; }
  .scrum-search-group { max-width: none; flex-basis: 100%; }
  .scrum-header-divider { display: none; }
}

/* ── Project title / status ── */
#currentProjectTitle {
  font-family: var(--s-font) !important;
  font-size: 1rem !important;
  font-weight: 700 !important;
  color: var(--s-text) !important;
  letter-spacing: -.2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}
#currentProjectStatus {
  background: transparent !important;
  border: none !important;
  color: var(--s-muted) !important;
  font-size: .72rem !important;
  font-weight: 600 !important;
  padding: 0 !important;
  display: inline-flex !important;
  align-items: center;
  gap: 5px;
}
#currentProjectStatus::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--s-teal);
  display: inline-block;
}
#currentProjectStatus.badge-secondary::before { background: var(--s-muted); }

/* ── Project members avatar stack ── */
.project-members-stack {
  display: flex;
  align-items: center;
  cursor: pointer;
}
.project-members-stack .pm-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid var(--s-surface, #fff);
  margin-left: -8px;
  object-fit: cover;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .65rem;
  font-weight: 700;
  color: #fff;
  font-family: var(--s-mono);
  background: var(--s-teal);
}
.project-members-stack .pm-avatar:first-child {
  margin-left: 0;
}
.project-members-stack .pm-more {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid var(--s-surface, #fff);
  margin-left: -8px;
  background: var(--s-muted, #6c757d);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .62rem;
  font-weight: 700;
  flex-shrink: 0;
}
.pm-add-btn {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px dashed var(--s-border, #ccc) !important;
  background: transparent !important;
  color: var(--s-muted) !important;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .7rem;
  padding: 0 !important;
  flex-shrink: 0;
  position: relative;
  transition: all .15s ease;
}
.pm-add-btn:hover {
  color: var(--s-teal) !important;
  border-color: var(--s-teal) !important;
  border-style: solid !important;
}
.pm-request-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  background: #dc3545;
  color: #fff;
  font-size: .62rem;
  font-weight: 700;
  line-height: 16px;
  text-align: center;
  border: 2px solid var(--s-surface, #fff);
}

/* ── Header buttons: flat, borderless "ghost" style ── */
/* Every header control is text/icon on transparent, no boxes at rest —
   the filled Add Task button stays the one deliberate accent. */
.scrum-header .btn-ghost,
.scrum-header .btn-outline-primary,
.scrum-header .btn-outline-secondary {
  background: transparent !important;
  color: var(--s-muted) !important;
  border: 1px solid transparent !important;
  border-radius: 7px !important;
  font-size: .8rem !important;
  font-weight: 600 !important;
  font-family: var(--s-font) !important;
  padding: 7px 12px !important;
  transition: color .15s ease, background-color .15s ease !important;
}
.scrum-header .btn-ghost:hover,
.scrum-header .btn-outline-primary:hover,
.scrum-header .btn-outline-secondary:hover {
  color: var(--s-text) !important;
  background: var(--s-surface2) !important;
}
/* Active / toggled state for the view toolbar (Filter, My Tasks, Calendar) */
.scrum-view-toolbar .btn.active {
  color: var(--s-teal) !important;
  background: var(--s-teal-dim) !important;
}
.scrum-header .btn-primary {
  background: var(--s-teal) !important;
  color: #fff !important;
  border: 1px solid transparent !important;
  border-radius: 7px !important;
  font-weight: 700 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  padding: 7px 14px !important;
  transition: filter .15s ease !important;
}
body:not(.dark-mode) .scrum-header .btn-primary { color: #fff !important; }
.scrum-header .btn-primary:hover { filter: brightness(1.08) !important; }
.scrum-header .btn-success {
  background: transparent !important;
  color: var(--s-teal) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 7px !important;
  font-weight: 600 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  padding: 6px 13px !important;
  transition: all .15s ease !important;
}
.scrum-header .btn-success:hover {
  border-color: var(--s-teal) !important;
  background: var(--s-teal-dim) !important;
}
.scrum-header .btn-outline-danger {
  background: transparent !important;
  color: var(--s-muted) !important;
  border: 1px solid transparent !important;
  border-radius: 7px !important;
  font-weight: 600 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  padding: 7px 12px !important;
  transition: color .15s ease, background-color .15s ease !important;
}
.scrum-header .btn-outline-danger:hover {
  color: var(--s-danger) !important;
  background: rgba(207,34,46,.08) !important;
}

/* ── Search: hairline underline instead of a boxed input ── */
.scrum-search-group {
  position: relative !important;
  display: flex !important;
  align-items: center !important;
}
.scrum-search-group .form-control {
  background: transparent !important;
  border: none !important;
  border-bottom: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  border-radius: 0 !important;
  font-family: var(--s-font) !important;
  font-size: .83rem !important;
  padding: 6px 4px 6px 26px !important;
  height: auto !important;
  transition: border-color .15s ease !important;
}
.scrum-search-group .form-control:focus {
  border-color: var(--s-teal) !important;
  box-shadow: none !important;
}
.scrum-search-group .form-control::placeholder { color: var(--s-muted) !important; }
.scrum-search-group .input-group-append { position: absolute; left: 0; top: 0; bottom: 0; }
.scrum-search-group .input-group-append .btn {
  background: transparent !important;
  border: none !important;
  color: var(--s-muted) !important;
  padding: 0 4px !important;
  height: 100%;
  font-size: .78rem !important;
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
  position: relative !important;
}
.task-card:last-child { margin-bottom: 0 !important; }
.task-card:hover {
  border-color: var(--s-teal) !important;
  box-shadow: 0 3px 12px rgba(0,0,0,.12) !important;
  transform: translateY(-1px) !important;
}
.task-card.dragging { opacity: .45 !important; border-style: dashed !important; }

/* Delete-task shortcut on the card itself (project creator only — same
   restriction the edit-modal delete used to enforce). Tucked in the corner
   and only revealed on hover so it doesn't compete with the card content. */
.task-delete-btn {
  position: absolute !important; top: 6px !important; right: 6px !important;
  width: 22px !important; height: 22px !important; border-radius: 50% !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  background: var(--s-card-bg) !important; border: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important; font-size: 11px !important; cursor: pointer !important;
  opacity: 0; transition: opacity .15s, color .15s, border-color .15s !important;
  z-index: 2;
}
.task-card:hover .task-delete-btn { opacity: 1; }
.task-delete-btn:hover { color: #fff !important; background: #dc3545 !important; border-color: #dc3545 !important; }

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
  background: var(--s-surface3) !important;
  color: var(--s-text) !important;
  border: 1px solid var(--s-border) !important;
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

/* ── Free-typed label tag input ── */
.label-tag-box {
  display: flex !important;
  flex-wrap: wrap !important;
  align-items: center !important;
  gap: 6px !important;
  padding: 6px 8px !important;
  background: var(--s-surface3) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 6px !important;
}
.label-tag-chip {
  display: inline-flex !important;
  align-items: center !important;
  background: var(--s-surface2, var(--s-surface3)) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  font-size: .72rem !important;
  font-weight: 600 !important;
  border-radius: 4px !important;
  padding: 2px 6px 2px 8px !important;
}
.label-tag-remove {
  cursor: pointer !important;
  margin-left: 6px !important;
  color: var(--s-muted) !important;
  font-weight: 700 !important;
  line-height: 1 !important;
}
.label-tag-remove:hover { color: var(--s-danger) !important; }
.label-tag-text {
  border: none !important;
  background: transparent !important;
  outline: none !important;
  box-shadow: none !important;
  color: var(--s-text) !important;
  flex: 1 1 120px !important;
  min-width: 120px !important;
  padding: 3px 2px !important;
  font-family: var(--s-font) !important;
  font-size: .82rem !important;
}

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

/* Activity — same one-line-per-entry look as the project activity log */
.task-activity-log { max-height: 180px; overflow-y: auto; }
.task-activity-log .activity-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid var(--s-border) !important;
  transition: background-color .3s ease;
}
.task-activity-log .activity-item:last-child { border-bottom: none !important; }
.task-activity-log .activity-avatar {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--s-surface3) !important; color: var(--s-teal) !important;
  border: 1px solid var(--s-border) !important;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
  font-family: var(--s-mono) !important;
}
.task-activity-log img.activity-avatar { object-fit: cover; background: none !important; }
.task-activity-log .activity-content { flex: 1; min-width: 0; }
.task-activity-log .activity-desc { font-size: 13px; color: var(--s-text) !important; }
.task-activity-log .activity-comment-text { font-style: italic; color: var(--s-muted) !important; }

/* ── Activity / Comments tabs in the view-task modal ── */
.task-detail-tabs { border-bottom: 1px solid var(--s-border) !important; margin-bottom: 12px !important; }
.task-detail-tabs .nav-link {
  color: var(--s-muted) !important;
  border: none !important;
  border-bottom: 2px solid transparent !important;
  font-size: .8rem !important;
  font-weight: 700 !important;
  padding: 8px 4px !important;
  margin-right: 20px !important;
  background: transparent !important;
  border-radius: 0 !important;
}
.task-detail-tabs .nav-link:hover { color: var(--s-text) !important; }
.task-detail-tabs .nav-link.active {
  color: var(--s-teal) !important;
  border-bottom-color: var(--s-teal) !important;
}
.task-detail-tab-content .task-activity-log { max-height: 180px; }
.task-activity-log .activity-meta { font-size: 11px; color: var(--s-muted) !important; margin-top: 2px; }
.comment-input { border-bottom: 1px solid var(--s-border) !important; }

/* ── Comment replies & attachments ── */
.comment-reply-banner {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important;
    border-radius: 8px !important; padding: 6px 10px; margin-bottom: 6px; font-size: 12px;
}
.comment-reply-banner-text { color: var(--s-text) !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.comment-reply-cancel { padding: 0 4px !important; color: var(--s-muted) !important; }
.comment-attachment-preview {
    display: flex; align-items: center; font-size: 12px; color: var(--s-text) !important;
    background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important;
    border-radius: 8px !important; padding: 5px 10px; margin-top: 6px;
}
.comment-attachment-preview span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-left: 2px; }

.activity-reply-quote {
    display: block; background: var(--s-surface3) !important;
    border-left: 3px solid var(--s-teal) !important; border-radius: 6px !important;
    padding: 4px 8px; margin-bottom: 5px; cursor: pointer;
}
.activity-reply-quote:hover { background: var(--s-surface2) !important; }
.activity-reply-quote-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    color: var(--s-muted) !important; display: flex; align-items: center; gap: 4px;
}
.activity-reply-quote-text {
    display: block; font-size: 12px; font-style: italic; color: var(--s-text) !important;
    margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.activity-item.activity-item-highlight { background: var(--s-surface3) !important; border-radius: 8px !important; }
.activity-attachment-chip {
    display: inline-flex; align-items: center; margin-top: 4px; padding: 4px 8px;
    background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important;
    border-radius: 6px !important; font-size: 12px; color: var(--s-text) !important; text-decoration: none !important;
    max-width: 100%;
}
.activity-attachment-chip:hover { border-color: var(--s-teal) !important; }
.activity-attachment-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.activity-attachment-thumb {
    width: 48px; height: 48px; object-fit: cover; border-radius: 6px !important;
    border: 1px solid var(--s-border) !important; margin-top: 4px; cursor: pointer; display: block;
}
.activity-comment-reply-btn {
    font-size: 11px; color: var(--s-muted) !important; background: none !important; border: none !important;
    padding: 0; margin-top: 2px; cursor: pointer;
}
.activity-comment-reply-btn:hover { color: var(--s-teal) !important; text-decoration: underline; }

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

/* ── Calendar Timeline ─────────────────────────────────────────── */
.calendar-card {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 12px !important;
  overflow: hidden;
}
.calendar-card-header {
  background: var(--s-surface2) !important;
  border-bottom: 1px solid var(--s-border) !important;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 14px 18px !important;
}
.calendar-nav { display: flex; align-items: center; gap: 10px; }
.calendar-nav-btn {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  color: var(--s-text) !important;
  width: 32px; height: 32px;
  border-radius: 8px !important;
  display: flex; align-items: center; justify-content: center;
  padding: 0 !important;
}
.calendar-nav-btn:hover { border-color: var(--s-teal) !important; color: var(--s-teal) !important; background: var(--s-teal-dim) !important; }
.calendar-title {
  font-family: var(--s-font) !important;
  font-weight: 700 !important;
  font-size: 1.05rem !important;
  color: var(--s-text) !important;
  min-width: 160px;
  text-align: center;
}
.calendar-today-btn {
  border: 1px solid var(--s-border) !important;
  color: var(--s-muted) !important;
  font-size: .75rem !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
  padding: 5px 12px !important;
}
.calendar-today-btn:hover { border-color: var(--s-teal) !important; color: var(--s-teal) !important; background: var(--s-teal-dim) !important; }
.calendar-legend { display: flex; align-items: center; gap: 14px; }
.calendar-legend-item {
  display: flex; align-items: center; gap: 5px;
  font-size: .72rem !important; color: var(--s-muted) !important; font-weight: 600;
}
.calendar-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.priority-urgent-dot { background: var(--s-danger); }
.priority-high-dot   { background: var(--s-warning); }
.priority-medium-dot { background: var(--s-blue); }
.priority-low-dot    { background: var(--s-green); }

.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: var(--s-surface2) !important;
  border-bottom: 1px solid var(--s-border) !important;
}
.calendar-weekdays div {
  text-align: center;
  padding: 8px 4px;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--s-muted) !important;
}
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}
.calendar-day-cell {
  min-height: 110px;
  border-right: 1px solid var(--s-border) !important;
  border-bottom: 1px solid var(--s-border) !important;
  padding: 6px !important;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--s-surface) !important;
  transition: background-color .15s ease;
}
.calendar-day-cell:nth-child(7n) { border-right: none !important; }
.calendar-day-cell.calendar-day-outside { background: var(--s-surface2) !important; }
.calendar-day-cell.calendar-day-outside .calendar-day-number { color: var(--s-muted) !important; opacity: .5; }
.calendar-day-cell.calendar-day-today { background: var(--s-teal-dim) !important; }
.calendar-day-number {
  font-size: .78rem;
  font-weight: 700;
  color: var(--s-text) !important;
  font-family: var(--s-mono) !important;
}
.calendar-day-cell.calendar-day-today .calendar-day-number {
  color: var(--s-teal) !important;
  display: inline-flex;
  align-items: center; justify-content: center;
}
.calendar-day-tasks { display: flex; flex-direction: column; gap: 3px; overflow: hidden; }
.calendar-task-chip {
  display: flex; align-items: center; gap: 5px;
  background: var(--s-surface3) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 5px !important;
  padding: 2px 6px;
  font-size: .68rem;
  font-weight: 600;
  color: var(--s-text) !important;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: all .15s ease;
}
.calendar-task-chip:hover { border-color: var(--s-teal) !important; background: var(--s-teal-dim) !important; }
.calendar-task-chip .calendar-dot { flex-shrink: 0; }
.calendar-task-chip.task-done { opacity: .55; text-decoration: line-through; }
.calendar-more-btn {
  background: transparent !important;
  border: none !important;
  color: var(--s-muted) !important;
  font-size: .68rem !important;
  font-weight: 700 !important;
  text-align: left;
  padding: 1px 6px !important;
  cursor: pointer;
}
.calendar-more-btn:hover { color: var(--s-teal) !important; }
body.dark-mode .calendar-day-cell.calendar-day-outside { background: rgba(0,0,0,.15) !important; }

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
        <div class="scrum-header-group scrum-header-meta">
          <h3 class="mb-0" id="currentProjectTitle" title="Select a Project">Select a Project</h3>
          <span class="badge badge-secondary ml-2" id="currentProjectStatus">No Project</span>
          <div class="project-members-stack ml-3" id="projectMembersStack" title="View project members">
            <!-- Member avatars loaded here -->
          </div>
          <button type="button" class="btn pm-add-btn ml-1" id="addProjectMemberBtn" title="Add member">
            <i class="fas fa-plus"></i>
            <span class="pm-request-badge" id="pmRequestBadge" style="display:none;">0</span>
          </button>
          <span class="scrum-header-divider"></span>
          <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown">
              <i class="fas fa-project-diagram mr-1"></i> Projects
            </button>
            <div class="dropdown-menu" id="projectsDropdown">
              <!-- Projects will be loaded here -->
            </div>
          </div>
          <button class="btn btn-outline-secondary" id="addNewBoardBtn">
              <i class="fas fa-plus mr-2"></i>Add New Board
            </button>
          <button class="btn btn-outline-danger" id="deleteProjectBtn" style="display:none;" title="Delete this project">
              <i class="fas fa-trash-alt mr-2"></i>Delete Project
            </button>
        </div>
        <div class="scrum-header-group scrum-header-actions">
          <div class="input-group scrum-search-group">
            <input type="text" class="form-control" id="taskSearch" placeholder="Search tasks...">
            <div class="input-group-append">
              <button class="btn" type="button" id="searchBtn">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
          <span class="scrum-header-divider"></span>
          <div class="scrum-view-toolbar">
            <button type="button" class="btn btn-outline-primary" id="filterBtn">
              <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" class="btn btn-outline-primary" id="viewMyTasksBtn">
              <i class="fas fa-tasks"></i> My Tasks
            </button>
            <button type="button" class="btn btn-outline-primary" id="viewCalendarBtn">
              <i class="fas fa-calendar-alt"></i> Calendar
            </button>
          </div>
          <span class="scrum-header-divider"></span>
          <?php if ($canCreateProject): ?>
          <button class="btn btn-success" id="newProjectBtn">
            <i class="fas fa-plus mr-1"></i> New Project
          </button>
          <?php endif; ?>
          <?php if ($canAssignTasks): ?>
          <button class="btn btn-primary" id="addTaskBtn">
            <i class="fas fa-plus mr-1"></i> Add Task
          </button>
          <?php endif; ?>
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

      <!-- Calendar Timeline Section -->
      <div class="container-fluid mt-4" id="calendarSection" style="display: none;">
        <div class="row">
          <div class="col-12">
            <div class="card calendar-card">
              <div class="card-header calendar-card-header">
                <div class="calendar-nav">
                  <button type="button" class="btn calendar-nav-btn" id="calendarPrevBtn" title="Previous month">
                    <i class="fas fa-chevron-left"></i>
                  </button>
                  <h3 class="calendar-title mb-0" id="calendarMonthLabel">Month Year</h3>
                  <button type="button" class="btn calendar-nav-btn" id="calendarNextBtn" title="Next month">
                    <i class="fas fa-chevron-right"></i>
                  </button>
                  <button type="button" class="btn btn-outline-secondary calendar-today-btn" id="calendarTodayBtn">Today</button>
                </div>
                <div class="calendar-legend">
                  <span class="calendar-legend-item"><span class="calendar-dot priority-urgent-dot"></span>Urgent</span>
                  <span class="calendar-legend-item"><span class="calendar-dot priority-high-dot"></span>High</span>
                  <span class="calendar-legend-item"><span class="calendar-dot priority-medium-dot"></span>Medium</span>
                  <span class="calendar-legend-item"><span class="calendar-dot priority-low-dot"></span>Low</span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="calendar-weekdays">
                  <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                </div>
                <div class="calendar-grid" id="calendarGrid">
                  <!-- Calendar day cells rendered here -->
                </div>
                <div class="calendar-empty-state text-center py-5" id="calendarEmptyState" style="display:none;">
                  <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                  <p class="text-muted">Select a project to see its task timeline.</p>
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
            <label for="taskLabelsInput">Labels</label>
            <div class="label-tag-box" id="taskLabelsBox">
              <div id="taskLabelsChips"></div>
              <input type="text" class="label-tag-text" id="taskLabelsInput" placeholder="Type a label, press Enter">
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
            <div class="label-tag-box" id="editTaskLabelsBox">
              <div id="editTaskLabelsChips"></div>
              <input type="text" class="label-tag-text" id="editTaskLabelsInput" placeholder="Type a label, press Enter">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
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
          <ul class="nav nav-tabs task-detail-tabs" id="taskDetailTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="taskActivityTabBtn" data-toggle="tab" href="#taskActivityTabPane" role="tab" aria-controls="taskActivityTabPane" aria-selected="true">
                <i class="fas fa-history mr-1"></i>Activity
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="taskCommentsTabBtn" data-toggle="tab" href="#taskCommentsTabPane" role="tab" aria-controls="taskCommentsTabPane" aria-selected="false">
                <i class="fas fa-comments mr-1"></i>Comments
                <span class="badge badge-pill badge-secondary ml-1" id="taskCommentsCount" style="display:none;">0</span>
              </a>
            </li>
          </ul>
          <div class="tab-content task-detail-tab-content" id="taskDetailTabsContent">
            <div class="tab-pane fade show active" id="taskActivityTabPane" role="tabpanel" aria-labelledby="taskActivityTabBtn">
              <div id="activityTimeline" class="mb-0 task-activity-log"></div>
            </div>
            <div class="tab-pane fade" id="taskCommentsTabPane" role="tabpanel" aria-labelledby="taskCommentsTabBtn">
              <div id="commentsTimeline" class="mb-3 task-activity-log"></div>

              <div id="commentReplyBanner" class="comment-reply-banner" style="display:none;">
                <div class="comment-reply-banner-text">
                  <i class="fas fa-reply mr-1"></i>Replying to <strong id="commentReplyAuthor"></strong>: <span id="commentReplySnippet"></span>
                </div>
                <button type="button" class="btn btn-sm btn-link comment-reply-cancel" id="cancelCommentReplyBtn" title="Cancel reply">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <textarea class="form-control" id="commentText" rows="2" placeholder="Add a comment..." style="resize:none;"></textarea>

              <div id="commentAttachmentPreview" class="comment-attachment-preview" style="display:none;">
                <i class="fas fa-paperclip mr-1"></i><span id="commentAttachmentName"></span>
                <button type="button" class="btn btn-sm btn-link comment-reply-cancel" id="removeCommentAttachmentBtn" title="Remove attachment">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <input type="file" id="commentAttachmentInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp" style="display:none;">

              <div class="mt-2 d-flex justify-content-between align-items-center">
                <button class="btn btn-sm btn-outline-secondary" id="attachCommentFileBtn" type="button" title="Attach a file (PDF, Word, Excel, or image)">
                  <i class="fas fa-paperclip"></i>
                </button>
                <button class="btn btn-primary btn-sm" id="addCommentBtn">
                  <i class="fas fa-paper-plane mr-1"></i> Comment
                </button>
              </div>
            </div>
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
        this.canCreateProject = <?php echo $canCreateProject ? 'true' : 'false'; ?>;
        this.currentEmpId = <?= (int)$_SESSION['emp_id'] ?>;
        this.selectedBoardId = null;
        this.commentReplyTo = null;
        this.commentAttachmentFile = null;
        this.calendarDate = new Date();
        this.calendarDate.setDate(1);
        this.init();
    }
    
    init() {
        this.loadProjects();
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.handleUrlParameters();
        this.setupLabelTagInput('#taskLabelsInput', '#taskLabelsChips');
        this.setupLabelTagInput('#editTaskLabelsInput', '#editTaskLabelsChips');
    }

    // ── Free-typed label tags ──────────────────────────────────────────
    // Labels used to be a fixed checkbox list; now the user can just type
    // any label they want and press Enter (or comma) to turn it into a chip.
    // Chips are collected back into the same comma-separated string the
    // backend already expects, so no server-side change is needed.
    setupLabelTagInput(inputSelector, chipsSelector) {
        $(document).on('keydown', inputSelector, (e) => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const $input = $(e.currentTarget);
                this.addLabelTag(chipsSelector, $input.val());
                $input.val('');
            } else if (e.key === 'Backspace' && $(e.currentTarget).val() === '') {
                $(chipsSelector).find('.label-tag-chip').last().remove();
            }
        });
    }

    addLabelTag(chipsSelector, value) {
        value = (value || '').trim();
        if (!value) return;
        const existing = this.getLabelTags(chipsSelector).map(v => v.toLowerCase());
        if (existing.includes(value.toLowerCase())) return;

        const $chip = $('<span>').addClass('label-tag-chip').attr('data-value', value).text(value);
        const $remove = $('<span>').addClass('label-tag-remove').html('&times;').on('click', () => $chip.remove());
        $chip.append($remove);
        $(chipsSelector).append($chip);
    }

    getLabelTags(chipsSelector) {
        return $(chipsSelector).find('.label-tag-chip').map(function () {
            return $(this).attr('data-value');
        }).get();
    }

    setLabelTags(chipsSelector, labelsCsvOrArray) {
        $(chipsSelector).empty();
        const arr = Array.isArray(labelsCsvOrArray)
            ? labelsCsvOrArray
            : (labelsCsvOrArray ? labelsCsvOrArray.split(',') : []);
        arr.map(l => l.trim()).filter(Boolean).forEach(l => this.addLabelTag(chipsSelector, l));
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
        $('#projectMembersStack').click(() => this.showProjectMembersModal());
        $('#addProjectMemberBtn').click(() => this.showAddMemberModal());
        
        // Task buttons
        $('#addTaskBtn').click(() => this.showAddTaskModal());
        $('#saveTaskBtn').click(() => this.createTask());
        
        // Navigation buttons
        $('#projectsMonitoringBtn').click(() => this.toggleProjectsMonitoring());
        $('#viewMyTasksBtn').click(() => this.toggleMyTasks());
        $('#viewCalendarBtn').click(() => this.toggleCalendarView());
        $('#calendarPrevBtn').click(() => this.changeCalendarMonth(-1));
        $('#calendarNextBtn').click(() => this.changeCalendarMonth(1));
        $('#calendarTodayBtn').click(() => this.goToCalendarToday());
        $(document).on('click', '.calendar-task-chip', (e) => {
            const taskId = $(e.currentTarget).data('task-id');
            if (taskId) this.viewTask(taskId);
        });
        $(document).on('click', '.calendar-more-btn', (e) => {
            const dateKey = $(e.currentTarget).data('date-key');
            if (dateKey) this.showCalendarDayTasks(dateKey);
        });
        
        // Search
        $('#searchBtn').click(() => this.searchTasks());
        $('#taskSearch').on('keypress', (e) => {
            if (e.which === 13) this.searchTasks();
        });
        
        // Board settings
        $('#boardSettingsBtn').click(() => this.showBoardSettings());
        $('#addNewBoardBtn').click(() => this.showAddBoardModal());
        $('#deleteProjectBtn').click(() => this.confirmDeleteProject());
        $('#manageBoardsBtn').click(() => this.showManageBoards());
        $('#resetBoardsBtn').click(() => this.resetBoards());
        $('#saveBoardBtn').click(() => this.saveBoard());
        $('#deleteBoardBtn').click(() => this.deleteBoard());
        $('#updateTaskBtn').click(() => this.updateTask());
        $('#editTaskBtn').click(() => this.editCurrentTask());
        $('#addCommentBtn').click(() => this.addComment());
        $('#commentText').on('keydown', (e) => {
            // Ctrl/Cmd+Enter submits, same convenience as most comment boxes
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.addComment();
            }
        });
        $('#attachCommentFileBtn').click(() => $('#commentAttachmentInput').trigger('click'));
        $('#commentAttachmentInput').on('change', (e) => {
            const file = e.target.files[0];
            if (file) this.setCommentAttachment(file);
        });
        $('#removeCommentAttachmentBtn').click(() => this.setCommentAttachment(null));
        $('#cancelCommentReplyBtn').click(() => this.cancelCommentReply());
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
            if ($(e.target).closest('.task-priority, .task-label, .task-delete-btn').length) {
                return;
            }
            
            const taskId = $(e.currentTarget).data('task-id');
            this.viewTask(taskId);
        });

        // Delete-task shortcut on the card itself (see createTaskHtml —
        // only rendered for the project creator to begin with).
        $(document).on('click', '.task-delete-btn', (e) => {
            e.stopPropagation();
            const taskId = $(e.currentTarget).data('task-id');
            this.deleteTaskById(taskId);
        });

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
        this.setLabelTags('#editTaskLabelsChips', labelsString);
    }

    // Add update task method
    async updateTask() {
        const taskId = $('#editTaskId').val();
        const labels = this.getLabelTags('#editTaskLabelsChips');
        
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
    async deleteTaskById(taskId) {
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
                        // In case the task's own detail/edit modal happens
                        // to be open when it's deleted from the card.
                        $('#editTaskModal').modal('hide');
                        $('#viewTaskModal').modal('hide');
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

    // ── Two-step delete verification for projects ──────────────────────
    // Deleting a project wipes its boards, tasks, and membership, so a
    // single "are you sure" isn't enough. Step 1 is a plain warning; step 2
    // only unlocks once the person retypes the project's own name, the
    // same "prove you mean it" pattern used for destructive actions like
    // repo deletion. Both steps must pass before the request is sent.
    async confirmDeleteProject() {
        if (!this.currentProject) return;
        const project = this.currentProject;

        const step1 = await Swal.fire({
            title: 'Delete this project?',
            html: `This permanently deletes <strong>${project.project_name}</strong>, including all of its boards, tasks, and member access. This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Continue',
            cancelButtonText: 'Cancel'
        });
        if (!step1.isConfirmed) return;

        const step2 = await Swal.fire({
            title: 'Confirm deletion',
            html: `Type <strong>${project.project_name}</strong> below to confirm.`,
            input: 'text',
            inputPlaceholder: project.project_name,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Delete project',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if (value !== project.project_name) {
                    return 'That doesn\'t match the project name';
                }
            }
        });
        if (!step2.isConfirmed) return;

        await this.deleteProjectRequest(project.project_id);
    }

    async deleteProjectRequest(projectId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'delete_project',
                project_id: projectId
            });

            if (response.success) {
                this.showSuccess('Project deleted successfully');
                this.currentProject = null;
                this.currentProjectId = null;
                $('#deleteProjectBtn').hide();
                await this.loadProjects();
            } else {
                this.showError(response.error || 'Failed to delete project');
            }
        } catch (error) {
            console.error('Error deleting project:', error);
            this.showError('Failed to delete project');
        }
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
            $('#calendarSection').hide();
            this.loadProjectsMonitoring();
        }
    }

    toggleMyTasks() {
        const myTasksSection = $('#myTasksSection');
        const isVisible = myTasksSection.is(':visible');
        
        if (isVisible) {
            myTasksSection.hide();
            $('#scrumBoardContent').show();
            $('#viewMyTasksBtn').removeClass('active');
        } else {
            myTasksSection.show();
            $('#scrumBoardContent').hide();
            $('#projectsMonitoring').hide();
            $('#calendarSection').hide();
            $('#viewMyTasksBtn').addClass('active');
            $('#viewCalendarBtn').removeClass('active');
            this.loadMyTasks();
        }
    }

    toggleCalendarView() {
        const calendarSection = $('#calendarSection');
        const isVisible = calendarSection.is(':visible');

        if (isVisible) {
            calendarSection.hide();
            $('#scrumBoardContent').show();
            $('#viewCalendarBtn').removeClass('active');
        } else {
            calendarSection.show();
            $('#scrumBoardContent').hide();
            $('#projectsMonitoring').hide();
            $('#myTasksSection').hide();
            $('#viewCalendarBtn').addClass('active');
            $('#viewMyTasksBtn').removeClass('active');
            this.renderCalendar();
        }
    }

    changeCalendarMonth(delta) {
        this.calendarDate.setMonth(this.calendarDate.getMonth() + delta);
        this.renderCalendar();
    }

    goToCalendarToday() {
        this.calendarDate = new Date();
        this.calendarDate.setDate(1);
        this.renderCalendar();
    }

    // Formats a Date as a local YYYY-MM-DD key (avoids UTC off-by-one issues)
    dateKey(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    renderCalendar() {
        const monthLabel = this.calendarDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        $('#calendarMonthLabel').text(monthLabel);

        const grid = $('#calendarGrid');
        grid.empty();

        if (!this.currentProjectId) {
            grid.hide();
            $('#calendarEmptyState').text('Select a project to see its task timeline.').show();
            return;
        }
        $('#calendarEmptyState').hide();
        grid.show();

        // Group this project's tasks by due date (YYYY-MM-DD)
        const tasksByDate = {};
        (this.tasks || []).forEach(task => {
            if (!task.due_date) return;
            const key = task.due_date.substring(0, 10); // handles 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
            if (!tasksByDate[key]) tasksByDate[key] = [];
            tasksByDate[key].push(task);
        });

        const year = this.calendarDate.getFullYear();
        const month = this.calendarDate.getMonth();
        const firstOfMonth = new Date(year, month, 1);
        const startOffset = firstOfMonth.getDay(); // 0=Sun
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const gridStart = new Date(year, month, 1 - startOffset);

        const todayKey = this.dateKey(new Date());
        const totalCells = 42; // 6 weeks, keeps the grid a stable rectangle

        for (let i = 0; i < totalCells; i++) {
            const cellDate = new Date(gridStart);
            cellDate.setDate(gridStart.getDate() + i);
            const key = this.dateKey(cellDate);
            const isOutside = cellDate.getMonth() !== month;
            const isToday = key === todayKey;

            const $cell = $('<div>').addClass('calendar-day-cell');
            if (isOutside) $cell.addClass('calendar-day-outside');
            if (isToday) $cell.addClass('calendar-day-today');

            const $num = $('<div>').addClass('calendar-day-number').text(cellDate.getDate());
            $cell.append($num);

            const dayTasks = tasksByDate[key] || [];
            if (dayTasks.length > 0) {
                const $taskList = $('<div>').addClass('calendar-day-tasks');
                const maxVisible = 3;
                dayTasks.slice(0, maxVisible).forEach(task => {
                    const priority = task.priority || 'medium';
                    const isDone = task.status === 'done';
                    const $chip = $('<div>')
                        .addClass(`calendar-task-chip priority-${priority}${isDone ? ' task-done' : ''}`)
                        .attr('data-task-id', task.task_id)
                        .attr('title', task.title)
                        .append($('<span>').addClass(`calendar-dot priority-${priority}-dot`))
                        .append($('<span>').text(task.title));
                    $taskList.append($chip);
                });
                if (dayTasks.length > maxVisible) {
                    const $more = $('<button>')
                        .addClass('calendar-more-btn')
                        .attr('data-date-key', key)
                        .text(`+${dayTasks.length - maxVisible} more`);
                    $taskList.append($more);
                }
                $cell.append($taskList);
            }

            grid.append($cell);
        }
    }

    showCalendarDayTasks(dateKey) {
        const dayTasks = (this.tasks || []).filter(t => t.due_date && t.due_date.substring(0, 10) === dateKey);
        if (dayTasks.length === 0) return;

        const dateLabel = new Date(dateKey + 'T00:00:00').toLocaleDateString(undefined, {
            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
        });

        const listHtml = dayTasks.map(task => {
            const priority = task.priority || 'medium';
            return `
                <div class="calendar-task-chip priority-${priority}" data-task-id="${task.task_id}" style="width:100%; margin-bottom:6px; white-space:normal;">
                    <span class="calendar-dot priority-${priority}-dot"></span>
                    <span>${task.title}</span>
                </div>`;
        }).join('');

        Swal.fire({
            title: dateLabel,
            html: `<div style="text-align:left; max-height:320px; overflow-y:auto;">${listHtml}</div>`,
            showConfirmButton: false,
            showCloseButton: true
        });

        // Let clicks inside the SweetAlert open the task modal too
        $('.swal2-html-container .calendar-task-chip').off('click').on('click', (e) => {
            const taskId = $(e.currentTarget).data('task-id');
            Swal.close();
            if (taskId) this.viewTask(taskId);
        });
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
        $('#deleteProjectBtn').hide();
        const container = $('.columns-container');
        const createBtn = this.canCreateProject
            ? `<button class="btn btn-primary" onclick="scrumboard.showNewProjectModal()">
                   <i class="fas fa-plus mr-1"></i> Create Your First Project
               </button>`
            : `<p class="text-muted"><small>Ask an Administrator, Head, Manager, or Unit Head to create a project and add you as a member.</small></p>`;

        container.html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Projects Found</h4>
                <p class="text-muted">You are not a member of any projects yet.</p>
                ${createBtn}
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

            // Only the project's creator may delete it (also enforced server-side)
            const isCreator = String(this.currentProject.created_by) === String(this.currentEmpId);
            $('#deleteProjectBtn').toggle(isCreator);
            
            // Load boards and tasks
            await this.loadProjectBoards();
            await this.loadProjectTasks();
            await this.loadAssignableEmployees();
            await this.loadProjectMembers(projectId);
            await this.loadPendingJoinRequests(projectId);
            
            // Hide other sections
            $('#projectsMonitoring').hide();
            $('#myTasksSection').hide();
            $('#calendarSection').hide();
            $('#scrumBoardContent').show();
            $('#viewMyTasksBtn, #viewCalendarBtn').removeClass('active');
            
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

    async loadProjectMembers(projectId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_project_details',
                project_id: projectId
            });

            if (response.success && response.project) {
                this.currentProjectMembers = response.project.members || [];
                this.renderProjectMembersStack(this.currentProjectMembers);
            } else {
                this.currentProjectMembers = [];
                $('#projectMembersStack').empty();
            }
        } catch (error) {
            console.error('Error loading project members:', error);
            this.currentProjectMembers = [];
            $('#projectMembersStack').empty();
        }
    }

    renderProjectMembersStack(members) {
        const stack = $('#projectMembersStack');
        stack.empty();

        if (!members || members.length === 0) {
            return;
        }

        const maxShown = 5;
        members.slice(0, maxShown).forEach(member => {
            const name = `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Unknown';
            const initials = ((member.first_name ? member.first_name[0] : '') + (member.last_name ? member.last_name[0] : '')).toUpperCase() || '?';
            const title = `${name}${member.role ? ' (' + member.role + ')' : ''}`;

            if (member.picture) {
                const $img = $('<img>')
                    .addClass('pm-avatar')
                    .attr({ src: `../dist/img/employees/${member.picture}`, alt: name, title: title });
                $img.on('error', function() {
                    $(this).replaceWith(
                        $('<div>').addClass('pm-avatar').attr('title', title).text(initials)
                    );
                });
                stack.append($img);
            } else {
                stack.append(
                    $('<div>').addClass('pm-avatar').attr('title', title).text(initials)
                );
            }
        });

        if (members.length > maxShown) {
            stack.append(
                $('<div>').addClass('pm-more').attr('title', `${members.length - maxShown} more`).text(`+${members.length - maxShown}`)
            );
        }
    }

    showProjectMembersModal() {
        const members = this.currentProjectMembers || [];

        if (members.length === 0) {
            Swal.fire('No Members', 'This project has no members yet.', 'info');
            return;
        }

        const rows = members.map(member => {
            const name = `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Unknown';
            const initials = ((member.first_name ? member.first_name[0] : '') + (member.last_name ? member.last_name[0] : '')).toUpperCase() || '?';
            const avatarHtml = member.picture
                ? `<img src="../dist/img/employees/${member.picture}" alt="${name}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;margin-right:10px;">`
                : `<div style="width:36px;height:36px;border-radius:50%;background:#3498db;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;margin-right:10px;">${initials}</div>`;
            const roleLabel = member.role ? member.role.charAt(0).toUpperCase() + member.role.slice(1) : 'Member';
            const removeBtn = member.role === 'owner'
                ? ''
                : `<button type="button" class="pm-remove-btn" data-emp-id="${member.emp_id}" title="Remove from project" style="background:none;border:none;color:#dc3545;margin-left:8px;font-size:.9rem;cursor:pointer;">
                       <i class="fas fa-user-minus"></i>
                   </button>`;

            return `
                <div class="pm-member-row" style="display:flex;align-items:center;padding:8px 4px;border-bottom:1px solid #eee;">
                    ${avatarHtml}
                    <div style="text-align:left;flex:1;">
                        <div style="font-weight:600;">${name}</div>
                    </div>
                    <span class="badge badge-secondary">${roleLabel}</span>
                    ${removeBtn}
                </div>
            `;
        }).join('');

        Swal.fire({
            title: `Project Members (${members.length})`,
            html: `<div style="max-height:320px;overflow-y:auto;">${rows}</div>`,
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-plus mr-1"></i> Add Member',
            confirmButtonText: 'Close',
            didOpen: () => {
                $('.pm-remove-btn').on('click', (e) => {
                    const empId = $(e.currentTarget).data('emp-id');
                    Swal.close();
                    this.confirmRemoveMember(empId);
                });
            }
        }).then((result) => {
            if (result.isDenied) {
                this.showAddMemberModal();
            }
        });
    }

    confirmRemoveMember(empId) {
        const member = (this.currentProjectMembers || []).find(m => String(m.emp_id) === String(empId));
        const name = member ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : 'this person';

        Swal.fire({
            title: 'Remove Member?',
            text: `Remove ${name} from this project?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                this.removeProjectMember(empId);
            } else {
                this.showProjectMembersModal();
            }
        });
    }

    async removeProjectMember(empId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'remove_project_member',
                project_id: this.currentProjectId,
                emp_id: empId
            });

            if (response.success) {
                this.currentProjectMembers = response.members || [];
                this.renderProjectMembersStack(this.currentProjectMembers);
                await this.loadAssignableEmployees();
                this.showSuccess('Member removed successfully');
                this.showProjectMembersModal();
            } else {
                this.showError(response.error || 'Failed to remove member');
            }
        } catch (error) {
            console.error('Error removing project member:', error);
            this.showError('Failed to remove member');
        }
    }

    async loadPendingJoinRequests(projectId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_pending_join_requests',
                project_id: projectId
            });

            this.pendingJoinRequests = (response.success && response.requests) ? response.requests : [];
            this.renderRequestBadge();
        } catch (error) {
            console.error('Error loading join requests:', error);
            this.pendingJoinRequests = [];
            this.renderRequestBadge();
        }
    }

    renderRequestBadge() {
        const count = (this.pendingJoinRequests || []).length;
        const badge = $('#pmRequestBadge');

        if (count > 0) {
            badge.text(count > 9 ? '9+' : count).show();
            $('#addProjectMemberBtn').attr('title', `${count} pending join request${count === 1 ? '' : 's'}`);
        } else {
            badge.hide();
            $('#addProjectMemberBtn').attr('title', 'Add member');
        }
    }

    renderPendingRequestsHtml() {
        const requests = this.pendingJoinRequests || [];
        if (requests.length === 0) return '';

        const rows = requests.map(req => {
            const name = `${req.first_name || ''} ${req.last_name || ''}`.trim() || 'Unknown';
            const initials = ((req.first_name ? req.first_name[0] : '') + (req.last_name ? req.last_name[0] : '')).toUpperCase() || '?';
            const avatarHtml = req.picture
                ? `<img src="../dist/img/employees/${req.picture}" alt="${name}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:10px;">`
                : `<div style="width:32px;height:32px;border-radius:50%;background:#f0ad4e;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;margin-right:10px;">${initials}</div>`;

            return `
                <div class="pm-request-row" data-request-id="${req.request_id}" style="display:flex;align-items:center;padding:7px 8px;border-bottom:1px solid #eee;text-align:left;">
                    ${avatarHtml}
                    <span style="flex:1;font-weight:600;">${name}</span>
                    <button type="button" class="pm-approve-btn btn btn-sm btn-success mr-1" data-request-id="${req.request_id}" title="Approve"><i class="fas fa-check"></i></button>
                    <button type="button" class="pm-deny-btn btn btn-sm btn-outline-danger" data-request-id="${req.request_id}" title="Deny"><i class="fas fa-times"></i></button>
                </div>
            `;
        }).join('');

        return `
            <div style="text-align:left;margin-bottom:14px;">
                <label class="small text-muted mb-1 d-block"><i class="fas fa-bell mr-1"></i>Pending Join Requests (${requests.length})</label>
                <div id="pendingRequestsList" style="max-height:180px;overflow-y:auto;border:1px solid #eee;border-radius:4px;">
                    ${rows}
                </div>
            </div>
        `;
    }

    bindPendingRequestActions() {
        $('.pm-approve-btn').on('click', (e) => {
            e.stopPropagation();
            this.respondJoinRequest($(e.currentTarget).data('request-id'), 'approve');
        });
        $('.pm-deny-btn').on('click', (e) => {
            e.stopPropagation();
            const requestId = $(e.currentTarget).data('request-id');
            Swal.fire({
                icon: 'warning',
                title: 'Deny Request?',
                text: "Are you sure you're denying/rejecting this request?",
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Deny',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.respondJoinRequest(requestId, 'deny');
                } else {
                    this.showAddMemberModal();
                }
            });
        });
    }

    async respondJoinRequest(requestId, decision) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'respond_join_request',
                request_id: requestId,
                decision: decision
            });

            if (response.success) {
                this.currentProjectMembers = response.members || this.currentProjectMembers;
                this.pendingJoinRequests = response.requests || [];
                this.renderProjectMembersStack(this.currentProjectMembers);
                this.renderRequestBadge();
                await this.loadAssignableEmployees();

                if (decision === 'approve') {
                    this.showToast('success', 'Request approved — member added');
                } else {
                    this.showToast('info', 'Request denied');
                }

                this.showAddMemberModal();
            } else {
                this.showError(response.error || 'Failed to respond to request');
            }
        } catch (error) {
            console.error('Error responding to join request:', error);
            this.showError('Failed to respond to request');
        }
    }

    async showAddMemberModal() {
        if (!this.currentProjectId) {
            this.showError('Please select a project first');
            return;
        }

        try {
            await this.loadPendingJoinRequests(this.currentProjectId);

            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_assignable_employees'
            });

            if (!response.success) {
                this.showError(response.error || 'Failed to load employees');
                return;
            }

            const existingIds = new Set((this.currentProjectMembers || []).map(m => String(m.emp_id)));
            const available = response.employees.filter(emp => !existingIds.has(String(emp.emp_id)));
            const pendingHtml = this.renderPendingRequestsHtml();

            if (available.length === 0 && (this.pendingJoinRequests || []).length === 0) {
                Swal.fire('No Employees Available', 'Everyone eligible is already a member of this project.', 'info');
                return;
            }

            const rowsHtml = available.map(emp => {
                const name = `${emp.first_name} ${emp.last_name}`;
                const sub = emp.section_name ? emp.section_name : '';
                return `
                    <label class="add-member-row" style="display:flex;align-items:center;padding:7px 8px;border-bottom:1px solid #eee;cursor:pointer;text-align:left;">
                        <input type="checkbox" class="add-member-checkbox" value="${emp.emp_id}" style="margin-right:10px;">
                        <span style="flex:1;">
                            <div style="font-weight:600;">${name}</div>
                            ${sub ? `<small class="text-muted">${sub}</small>` : ''}
                        </span>
                    </label>
                `;
            }).join('');

            const employeeListHtml = available.length > 0
                ? `
                    <label class="small text-muted mb-1 d-block" style="text-align:left;">Add from employees</label>
                    <input type="text" id="addMemberSearch" class="swal2-input" placeholder="Search employees..." style="margin-bottom:8px;width:90%;">
                    <div id="addMemberList" style="max-height:220px;overflow-y:auto;border:1px solid #eee;border-radius:4px;">
                        ${rowsHtml}
                    </div>
                `
                : '';

            Swal.fire({
                title: 'Add Project Members',
                html: `${pendingHtml}${employeeListHtml}`,
                showCancelButton: true,
                showConfirmButton: available.length > 0,
                confirmButtonText: 'Add Selected',
                cancelButtonText: available.length > 0 ? 'Cancel' : 'Close',
                focusConfirm: false,
                didOpen: () => {
                    this.bindPendingRequestActions();
                    $('#addMemberSearch').on('input', function() {
                        const term = $(this).val().toLowerCase();
                        $('#addMemberList .add-member-row').each(function() {
                            $(this).toggle($(this).text().toLowerCase().includes(term));
                        });
                    });
                },
                preConfirm: () => {
                    const empIds = $('#addMemberList .add-member-checkbox:checked').map(function() {
                        return $(this).val();
                    }).get();

                    if (empIds.length === 0) {
                        Swal.showValidationMessage('Please select at least one employee');
                        return false;
                    }

                    return { empIds };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    this.addProjectMembers(result.value.empIds);
                }
            });
        } catch (error) {
            console.error('Error loading assignable employees:', error);
            this.showError('Failed to load employees');
        }
    }

    async addProjectMembers(empIds) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'add_project_member',
                project_id: this.currentProjectId,
                emp_ids: empIds
            });

            if (response.success) {
                this.currentProjectMembers = response.members || [];
                this.renderProjectMembersStack(this.currentProjectMembers);
                await this.loadAssignableEmployees();

                let message = `${response.added_count} member(s) added successfully`;
                if (response.skipped_count > 0) {
                    message += ` (${response.skipped_count} were already members)`;
                }
                this.showSuccess(message);
            } else {
                this.showError(response.error || 'Failed to add members');
            }
        } catch (error) {
            console.error('Error adding project members:', error);
            this.showError('Failed to add members');
        }
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
                if ($('#calendarSection').is(':visible')) {
                    this.renderCalendar();
                }
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

        // Only the people this task is assigned to can drag it — unless the
        // viewer has broader task-assignment permission (manager/admin/
        // section head/project owner-manager), in which case they can move
        // any card on the board.
        const assignedIds = (task.assignees || []).map(a => String(a.emp_id));
        const canMove = this.canAssignTasks || assignedIds.includes(String(this.currentEmpId));
        const lockedAttrs = canMove ? '' : 'title="Only tasks assigned to you can be moved"';

        // Only the project creator can delete a task — enforced again
        // server-side in task_ajax.php; this just controls whether the
        // shortcut shows up on the card.
        const isProjectCreator = this.currentProject && (this.currentProject.created_by == this.currentEmpId);

        return `
            <div class="task-card${canMove ? '' : ' task-card-locked'}" draggable="${canMove}" data-task-id="${task.task_id}" data-board-id="${task.board_id}" ${lockedAttrs}>
                ${isProjectCreator ? `<button type="button" class="task-delete-btn" data-task-id="${task.task_id}" title="Delete task"><i class="fas fa-trash"></i></button>` : ''}
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
                    ${canMove ? '' : '<span class="task-lock-icon" title="Only tasks assigned to you can be moved"><i class="fas fa-lock"></i></span>'}
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
        $('#taskLabelsChips').empty();
        $('#taskLabelsInput').val('');
        $('#addTaskModal').modal('show');
    }
    
    async createTask() {
        // Get the selected board ID - prioritize the board where "Add Task" was clicked
        const boardId = this.selectedBoardId || (this.boards.length > 0 ? this.boards[0].board_id : null);
        
        if (!boardId) {
            this.showError('No boards available. Please create a board first.');
            return;
        }

        const labels = this.getLabelTags('#taskLabelsChips');
        
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
                $('#taskLabelsChips').empty();
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
                this.showError(response.error || 'Failed to move task');
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

    showToast(icon, title) {
        // A corner toast instead of a full modal — used when we need to notify
        // the user right before/after reopening another Swal modal, since two
        // full Swal.fire() modals in a row would otherwise clobber each other.
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        }).fire({ icon, title });
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

    async requestProjectMembership(projectId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'request_project_membership',
                project_id: projectId
            });

            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Request Sent!',
                    text: 'The project owner has been notified of your request to join.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                this.showError(response.error || 'Failed to send request');
            }
        } catch (error) {
            console.error('Error requesting project membership:', error);
            this.showError('Failed to send request');
        }
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
        $('#commentText').val('');
        this.setCommentAttachment(null);
        this.cancelCommentReply();
        $('#taskActivityTabBtn').tab('show');

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

        // Only an assignee (or someone with elevated permission, same as who
        // can drag cards between boards) may edit the task. Everyone else on
        // the project can still view it and leave a comment.
        const viewAssignedIds = (task.assignees || []).map(a => String(a.emp_id));
        const canEditThisTask = this.canAssignTasks || viewAssignedIds.includes(String(this.currentEmpId));
        $('#editTaskBtn').toggle(canEditThisTask);

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

    renderActivityAvatar($container, picturePath, initial) {
        // Same real-photo-with-fallback pattern as renderAvatar(), sized to
        // fit the existing 26px .activity-avatar circle used in the log.
        const $fallback = $('<div>').addClass('activity-avatar').text(initial);
        if (picturePath) {
            const $img = $('<img>')
                .addClass('activity-avatar')
                .attr({ src: `../dist/img/employees/${picturePath}`, alt: initial })
                .on('error', function () { $(this).replaceWith($fallback); });
            $container.append($img);
        } else {
            $container.append($fallback);
        }
    }

    // File-type icon for a comment attachment chip, keyed off its extension.
    attachmentIconClass(ext) {
        const map = {
            pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word',
            xls: 'fa-file-excel', xlsx: 'fa-file-excel',
            png: 'fa-file-image', jpg: 'fa-file-image', jpeg: 'fa-file-image',
            gif: 'fa-file-image', webp: 'fa-file-image'
        };
        return map[ext] || 'fa-file';
    }

    isImageExt(ext) {
        return ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext);
    }

    // entry.meta comes back from the server as a JSON string (or already
    // parsed / absent) — normalize it here so callers don't have to care.
    parseEntryMeta(entry) {
        if (!entry.meta) return null;
        if (typeof entry.meta === 'object') return entry.meta;
        try {
            return JSON.parse(entry.meta);
        } catch (e) {
            return null;
        }
    }

    buildActivityItem(entry, isComment) {
        const who = entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone';
        const when = new Date(entry.created_at).toLocaleString();
        const initial = (entry.first_name || '?').charAt(0).toUpperCase();
        const meta = isComment ? this.parseEntryMeta(entry) : null;

        const $item = $('<div>').addClass('activity-item');
        if (isComment && entry.log_id) {
            $item.attr('id', `comment-log-${entry.log_id}`);
        }
        this.renderActivityAvatar($item, entry.picture, initial);
        const $content = $('<div>').addClass('activity-content');

        if (isComment && meta && meta.reply_to) {
            $content.append(
                $('<div>').addClass('activity-reply-quote')
                    .append($('<div>').addClass('activity-reply-quote-label')
                        .append($('<i>').addClass('fas fa-reply'))
                        .append(document.createTextNode(`Replying to ${meta.reply_to.author}`)))
                    .append($('<div>').addClass('activity-reply-quote-text').text(`"${meta.reply_to.snippet}"`))
                    .on('click', () => this.jumpToComment(meta.reply_to.log_id))
            );
        }

        const $desc = $('<div>').addClass('activity-desc');
        if (isComment) {
            $desc.append($('<strong>').text(who));
            $desc.append(document.createTextNode(': '));
            $desc.append($('<span>').addClass('activity-comment-text').text(`"${entry.description}"`));
        } else {
            $desc.append($('<strong>').text(who));
            $desc.append(document.createTextNode(` ${entry.description}`));
        }
        $content.append($desc);

        if (isComment && meta && meta.attachment) {
            const att = meta.attachment;
            if (this.isImageExt(att.ext)) {
                $content.append(
                    $('<img>').addClass('activity-attachment-thumb')
                        .attr({ src: att.url, alt: att.name, title: att.name })
                        .on('click', () => window.open(att.url, '_blank'))
                );
            } else {
                $content.append(
                    $('<a>').addClass('activity-attachment-chip')
                        .attr({ href: att.url, target: '_blank', rel: 'noopener' })
                        .append($('<i>').addClass(`fas ${this.attachmentIconClass(att.ext)} mr-1`))
                        .append($('<span>').text(att.name))
                );
            }
        }

        $content.append($('<div>').addClass('activity-meta').text(when));

        if (isComment && entry.log_id) {
            $content.append(
                $('<button>').addClass('activity-comment-reply-btn').attr('type', 'button')
                    .html('<i class="fas fa-reply mr-1"></i>Reply')
                    .on('click', () => this.startCommentReply(entry))
            );
        }

        $item.append($content);
        return $item;
    }

    startCommentReply(entry) {
        this.commentReplyTo = {
            log_id: entry.log_id,
            author: entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone',
            snippet: entry.description.length > 80 ? entry.description.slice(0, 80) + '…' : entry.description
        };
        $('#commentReplyAuthor').text(this.commentReplyTo.author);
        $('#commentReplySnippet').text(`"${this.commentReplyTo.snippet}"`);
        $('#commentReplyBanner').show();
        $('#taskCommentsTabBtn').tab('show');
        $('#commentText').focus();
    }

    cancelCommentReply() {
        this.commentReplyTo = null;
        $('#commentReplyBanner').hide();
    }

    // Scrolls the referenced comment into view within the comments panel and
    // briefly highlights it, so a reply quote is unambiguous even when the
    // original comment is out of sight or several comments back.
    jumpToComment(logId) {
        const $target = $(`#comment-log-${logId}`);
        if (!$target.length) return;
        $target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        $target.addClass('activity-item-highlight');
        setTimeout(() => $target.removeClass('activity-item-highlight'), 1200);
    }

    setCommentAttachment(file) {
        this.commentAttachmentFile = file || null;
        if (file) {
            $('#commentAttachmentName').text(`${file.name} (${(file.size / 1024).toFixed(0)} KB)`);
            $('#commentAttachmentPreview').show();
        } else {
            $('#commentAttachmentPreview').hide();
        }
        // Always reset the underlying input so choosing the same file twice
        // still fires a change event.
        $('#commentAttachmentInput').val('');
    }

    async loadTaskActivity(taskId) {
        const activityTimeline = $('#activityTimeline');
        const commentsTimeline = $('#commentsTimeline');
        activityTimeline.html('<div class="text-muted small">Loading activity&hellip;</div>');
        commentsTimeline.html('<div class="text-muted small">Loading comments&hellip;</div>');

        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_task_activity',
                task_id: taskId
            }, null, 'json');

            // Modal may have been closed while the request was in flight
            if (!$('#activityTimeline').length) return;

            if (!response.success) {
                activityTimeline.html('<div class="text-muted small">Couldn\'t load activity.</div>');
                commentsTimeline.html('<div class="text-muted small">Couldn\'t load comments.</div>');
                return;
            }

            const allEntries = response.activity || [];
            const activityEntries = allEntries.filter(e => e.action !== 'commented');
            const commentEntries = allEntries.filter(e => e.action === 'commented');

            activityTimeline.empty();
            if (activityEntries.length === 0) {
                activityTimeline.html('<div class="text-muted small">No activity yet.</div>');
            } else {
                activityEntries.forEach(entry => activityTimeline.append(this.buildActivityItem(entry, false)));
            }

            commentsTimeline.empty();
            if (commentEntries.length === 0) {
                commentsTimeline.html('<div class="text-muted small">No comments yet.</div>');
            } else {
                commentEntries.forEach(entry => commentsTimeline.append(this.buildActivityItem(entry, true)));
            }

            const $commentsCount = $('#taskCommentsCount');
            if (commentEntries.length > 0) {
                $commentsCount.text(commentEntries.length).show();
            } else {
                $commentsCount.hide();
            }
        } catch (error) {
            console.error('Error loading activity:', error);
            if ($('#activityTimeline').length) {
                activityTimeline.html('<div class="text-muted small">Couldn\'t load activity.</div>');
                commentsTimeline.html('<div class="text-muted small">Couldn\'t load comments.</div>');
            }
        }
    }

    async addComment() {
        if (!this.currentViewingTask) return;

        const text = $('#commentText').val().trim();
        if (!text && !this.commentAttachmentFile) {
            this.showError('Please enter a comment or attach a file');
            return;
        }

        const taskId = this.currentViewingTask.task_id;
        const $btn = $('#addCommentBtn');
        $btn.prop('disabled', true);

        const formData = new FormData();
        formData.append('action', 'add_comment');
        formData.append('task_id', taskId);
        formData.append('comment', text);
        if (this.commentReplyTo) {
            formData.append('reply_to', this.commentReplyTo.log_id);
        }
        if (this.commentAttachmentFile) {
            formData.append('attachment', this.commentAttachmentFile);
        }

        try {
            const response = await $.ajax({
                url: '../includes/task_ajax.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            });

            if (response.success) {
                $('#commentText').val('');
                this.setCommentAttachment(null);
                this.cancelCommentReply();
                await this.loadTaskActivity(taskId);
            } else {
                this.showError(response.error || 'Failed to add comment');
            }
        } catch (error) {
            console.error('Error adding comment:', error);
            this.showError('Failed to add comment');
        } finally {
            $btn.prop('disabled', false);
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