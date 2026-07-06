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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Projects Monitoring | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini theme-scrum">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_scrum.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><i class="fas fa-chart-line mr-2" style="color:var(--s-teal)"></i>Projects Monitoring</h1>
          </div>
          <div class="col-sm-6">
            <button class="btn btn-success float-right" id="newProjectBtn" style="border-radius:9px;font-weight:700;">
              <i class="fas fa-plus mr-1"></i> New Project
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">All Projects</h3>
            <div class="card-tools">
              <div class="input-group input-group-sm" style="width: 200px;">
                <input type="text" class="form-control" id="projectSearch" placeholder="Search projects...">
                <div class="input-group-append">
                  <button class="btn btn-primary">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover" id="projectsTable">
                <thead>
                  <tr>
                    <th>Project Code</th>
                    <th>Project Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Tasks</th>
                    <th>Members</th>
                    <th>Progress</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="projectsTableBody">
                  <!-- Projects will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  --s-bg: #0d1117;
  --s-surface: #161b22;
  --s-surface2: #21262d;
  --s-surface3: #30363d;
  --s-border: #30363d;
  --s-teal: #2dd4bf;
  --s-teal-dim: rgba(45,212,191,.12);
  --s-teal-glow: 0 0 20px rgba(45,212,191,.2);
  --s-violet: #a78bfa;
  --s-text: #e6edf3;
  --s-muted: #7d8590;
  --s-danger: #f85149;
  --s-warning: #d29922;
  --s-green: #3fb950;
  --s-blue: #58a6ff;
  --s-radius: 10px;
  --s-shadow: 0 8px 32px rgba(0,0,0,.4);
  --s-font: 'Plus Jakarta Sans', sans-serif;
  --s-mono: 'JetBrains Mono', monospace;
}

/* ── Base ── */
body { font-family: var(--s-font) !important; background: var(--s-bg) !important; color: var(--s-text) !important; }
.content-wrapper { background: var(--s-bg) !important; }

/* ── Scrum board layout ── */
.scrumboard-container { background: var(--s-bg); }
.scrum-sidebar { background: var(--s-surface); border-right: 1px solid var(--s-border); box-shadow: none; }
.scrum-header {
  background: var(--s-surface) !important;
  border-bottom: 1px solid var(--s-border) !important;
  padding: 14px 20px !important;
}
.scrum-content { background: var(--s-bg) !important; padding: 20px !important; }

/* ── Project title ── */
#currentProjectTitle {
  font-family: var(--s-font) !important;
  font-size: 1.1rem !important;
  font-weight: 700 !important;
  color: var(--s-text) !important;
  letter-spacing: -.3px;
}
#currentProjectStatus {
  background: var(--s-teal-dim) !important;
  color: var(--s-teal) !important;
  border: 1px solid rgba(45,212,191,.3) !important;
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
  border-color: var(--s-border) !important;
  color: var(--s-text) !important;
  background: var(--s-surface2) !important;
}
.scrum-header .btn-primary {
  background: var(--s-teal) !important;
  color: #0d1117 !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 700 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px rgba(45,212,191,.25) !important;
  transition: all .2s !important;
}
.scrum-header .btn-primary:hover { background: #14b8a6 !important; box-shadow: 0 4px 20px rgba(45,212,191,.4) !important; transform: translateY(-1px); }
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

/* ── Kanban columns ── */
.column {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 12px !important;
  box-shadow: none !important;
  min-width: 290px !important;
  padding: 14px !important;
}
.column-header { margin-bottom: 12px !important; }
.column-title {
  font-family: var(--s-font) !important;
  font-size: .85rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: .5px !important;
  color: var(--s-muted) !important;
}
.task-count {
  background: var(--s-surface3) !important;
  color: var(--s-muted) !important;
  border-radius: 6px !important;
  padding: 2px 8px !important;
  font-size: .7rem !important;
  font-family: var(--s-mono) !important;
}
.tasks-container {
  background: transparent !important;
  padding: 4px 0 !important;
}

/* ── Task cards ── */
.task-card {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 8px !important;
  padding: 12px !important;
  margin-bottom: 8px !important;
  box-shadow: none !important;
  transition: all .15s !important;
}
.task-card:hover {
  border-color: var(--s-teal) !important;
  box-shadow: 0 0 0 1px rgba(45,212,191,.2), 0 4px 16px rgba(0,0,0,.3) !important;
  transform: translateY(-2px) !important;
}
.task-card.dragging { opacity: .5 !important; border-style: dashed !important; }

.task-title {
  font-family: var(--s-font) !important;
  font-size: .85rem !important;
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
.task-priority { border-radius: 4px !important; font-size: .6rem !important; font-weight: 700 !important; letter-spacing: .5px; }
.priority-urgent { background: rgba(248,81,73,.15) !important; color: var(--s-danger) !important; border: 1px solid rgba(248,81,73,.3) !important; }
.priority-high   { background: rgba(210,153,34,.15) !important; color: #e3a520 !important; border: 1px solid rgba(210,153,34,.3) !important; }
.priority-medium { background: rgba(88,166,255,.12) !important; color: var(--s-blue) !important; border: 1px solid rgba(88,166,255,.25) !important; }
.priority-low    { background: rgba(63,185,80,.12) !important; color: var(--s-green) !important; border: 1px solid rgba(63,185,80,.25) !important; }

/* ── Labels ── */
.label-revise    { background: rgba(210,153,34,.15) !important; color: #e3a520 !important; }
.label-urgent    { background: rgba(248,81,73,.12) !important; color: var(--s-danger) !important; }
.label-design    { background: rgba(167,139,250,.12) !important; color: var(--s-violet) !important; }
.label-development { background: rgba(63,185,80,.12) !important; color: var(--s-green) !important; }
.label-review    { background: rgba(88,166,255,.12) !important; color: var(--s-blue) !important; }

/* ── Column footer add task ── */
.column-footer { border-top: 1px solid var(--s-border) !important; padding-top: 10px !important; }
.empty-column { color: var(--s-muted) !important; }
.empty-column i { opacity: .3 !important; }

/* ── Cards (monitoring / my tasks sections) ── */
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

/* ── Tables ── */
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
.table-danger td, tr.table-danger { background: rgba(248,81,73,.07) !important; }

/* ── Table action buttons ── */
.btn-sm.btn-info    { background: rgba(88,166,255,.12) !important; color: var(--s-blue) !important; border: 1px solid rgba(88,166,255,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-primary { background: rgba(45,212,191,.12) !important; color: var(--s-teal) !important; border: 1px solid rgba(45,212,191,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-success { background: rgba(63,185,80,.12) !important; color: var(--s-green) !important; border: 1px solid rgba(63,185,80,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-warning { background: rgba(210,153,34,.12) !important; color: #e3a520 !important; border: 1px solid rgba(210,153,34,.25) !important; border-radius: 7px !important; }
.btn-sm.btn-danger  { background: rgba(248,81,73,.12) !important; color: var(--s-danger) !important; border: 1px solid rgba(248,81,73,.25) !important; border-radius: 7px !important; }
.btn-sm:hover { filter: brightness(1.15); transform: translateY(-1px); }

/* Status badges in table */
.badge-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border-radius: 5px !important; }
.badge-warning   { background: rgba(210,153,34,.18) !important; color: #e3a520 !important; border-radius: 5px !important; }
.badge-info      { background: rgba(88,166,255,.15) !important; color: var(--s-blue) !important; border-radius: 5px !important; }
.badge-primary   { background: rgba(167,139,250,.15) !important; color: var(--s-violet) !important; border-radius: 5px !important; }
.badge-success   { background: rgba(63,185,80,.15) !important; color: var(--s-green) !important; border-radius: 5px !important; }
.badge-danger    { background: rgba(248,81,73,.15) !important; color: var(--s-danger) !important; border-radius: 5px !important; }

/* Progress bar */
.progress { background: var(--s-surface3) !important; border-radius: 6px !important; }
.progress-bar.bg-success { background: var(--s-teal) !important; border-radius: 6px !important; }

/* ── Modals ── */
.modal-content {
  background: var(--s-surface) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 14px !important;
  box-shadow: 0 24px 80px rgba(0,0,0,.6) !important;
  color: var(--s-text) !important;
  font-family: var(--s-font) !important;
}
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

/* View task modal special header */
#viewTaskModal .modal-header.bg-primary {
  background: linear-gradient(135deg, #1e2a3a, #1a2332) !important;
  border-bottom: 1px solid var(--s-teal) !important;
}
#viewTaskKey { font-family: var(--s-mono) !important; font-size: .8rem !important; color: var(--s-teal) !important; }
#viewTaskTitle { color: var(--s-text) !important; font-family: var(--s-font) !important; }
#viewTaskModal .modal-dialog { max-width: 50%; }
#viewTaskModal .modal-body { max-height: 80vh; overflow-y: auto; }
#viewTaskModal .border-right { border-right: 1px solid var(--s-border) !important; }
#viewTaskModal .card { background: var(--s-surface2) !important; border-color: var(--s-border) !important; }
#viewTaskModal .card-header.bg-light { background: var(--s-surface3) !important; color: var(--s-text) !important; border-color: var(--s-border) !important; }
#viewTaskModal .card-body { background: var(--s-surface2) !important; }

/* Status/priority badges in modal */
.status-badge { background: var(--s-surface3) !important; color: var(--s-muted) !important; border-radius: 5px !important; }
.priority-badge.priority-urgent { background: rgba(248,81,73,.15) !important; color: var(--s-danger) !important; }
.priority-badge.priority-high   { background: rgba(210,153,34,.15) !important; color: #e3a520 !important; }
.priority-badge.priority-medium { background: rgba(88,166,255,.12) !important; color: var(--s-blue) !important; }
.priority-badge.priority-low    { background: rgba(63,185,80,.12) !important; color: var(--s-green) !important; }

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
.modal .form-control:focus { border-color: var(--s-teal) !important; box-shadow: 0 0 0 3px rgba(45,212,191,.12) !important; }
.modal label { color: var(--s-muted) !important; font-size: .72rem !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: .5px !important; margin-bottom: 5px !important; }

/* Checkbox labels */
.custom-control-label { color: var(--s-text) !important; font-size: .85rem !important; text-transform: none !important; letter-spacing: 0 !important; }

/* Modal buttons */
.modal .btn-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }
.modal .btn-primary { background: var(--s-teal) !important; color: #0d1117 !important; border: none !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 700 !important; box-shadow: 0 2px 12px rgba(45,212,191,.25) !important; }
.modal .btn-primary:hover { background: #14b8a6 !important; }
.modal .btn-danger { background: rgba(248,81,73,.15) !important; color: var(--s-danger) !important; border: 1px solid rgba(248,81,73,.3) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }

/* Modal edit/close light buttons inside dark header */
.modal-header .btn-light { background: rgba(255,255,255,.1) !important; color: var(--s-text) !important; border: 1px solid rgba(255,255,255,.15) !important; border-radius: 7px !important; font-size: .78rem !important; }
.modal-header .btn-light:hover { background: rgba(255,255,255,.18) !important; }

/* Board dropdown */
.dropdown-menu {
  background: var(--s-surface2) !important;
  border: 1px solid var(--s-border) !important;
  border-radius: 10px !important;
  box-shadow: var(--s-shadow) !important;
  padding: 6px !important;
}
.dropdown-item { color: var(--s-text) !important; border-radius: 7px !important; font-family: var(--s-font) !important; font-size: .85rem !important; padding: 8px 12px !important; }
.dropdown-item:hover { background: var(--s-surface3) !important; color: var(--s-teal) !important; }

/* Board color badge */
.board-color-badge, .project-color-badge {
  width: 10px; height: 10px;
  border-radius: 3px !important;
  display: inline-block; flex-shrink: 0;
  margin-right: 7px;
}

/* Card tool buttons */
.btn-tool { color: var(--s-muted) !important; }
.btn-tool:hover { color: var(--s-text) !important; }

/* Page content header */
.content-header h1 {
  font-family: var(--s-font) !important;
  font-weight: 800 !important;
  font-size: 1.5rem !important;
  color: var(--s-text) !important;
  letter-spacing: -.5px;
}

/* Page-level buttons (outside scrum-header, in content-header) */
.content-header .btn-success {
  background: var(--s-teal) !important;
  color: #0d1117 !important;
  border: none !important;
  border-radius: 9px !important;
  font-weight: 700 !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px rgba(45,212,191,.25) !important;
}
.content-header .btn-success:hover { background: #14b8a6 !important; }

/* Page search (scrum_project.php) */
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

/* Filter select controls (my_scrum_task.php) */
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

/* Scrollbars */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--s-surface); }
::-webkit-scrollbar-thumb { background: var(--s-surface3); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--s-muted); }

/* No boards message */
#noBoardsMessage p { color: var(--s-muted) !important; }
#noBoardsMessage .btn-primary { background: var(--s-teal) !important; color: #0d1117 !important; border: none !important; border-radius: 9px !important; font-weight: 700 !important; }

/* Refresh button */
#refreshTasks.btn-tool { background: transparent !important; }

/* Select options (dark mode) */
select option { background: var(--s-surface2) !important; color: var(--s-text) !important; }

/* Color input */
input[type="color"] { background: var(--s-surface2) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; height: 38px !important; padding: 3px !important; cursor: pointer; }

/* Textarea comment */
#commentText { background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important; color: var(--s-text) !important; border-radius: 8px !important; font-family: var(--s-font) !important; }
#commentText::placeholder { color: var(--s-muted) !important; }
#addCommentBtn { background: var(--s-teal) !important; color: #0d1117 !important; border: none !important; border-radius: 7px !important; font-weight: 700 !important; font-family: var(--s-font) !important; }

/* No description / no labels text */
#noDescription, #noLabels { color: var(--s-muted) !important; }
.task-description { color: var(--s-text) !important; line-height: 1.7; }

/* ── Projects Monitoring specific ── */
.project-color-badge {
  width: 10px; height: 10px; border-radius: 3px;
  display: inline-block; margin-right: 7px; flex-shrink: 0;
}
</style>

<?php include '../includes/footer.php'; ?>

<!-- New Project Modal -->
<div class="modal fade" id="newProjectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-folder-plus mr-2" style="color:var(--s-teal)"></i>Create New Project</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="projectForm">
          <div class="form-group">
            <label>Project Name *</label>
            <input type="text" class="form-control" id="projectName" required>
          </div>
          <div class="form-group">
            <label>Project Code *</label>
            <input type="text" class="form-control" id="projectCode" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" id="projectDescription" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Start Date</label>
                <input type="date" class="form-control" id="projectStartDate">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>End Date</label>
                <input type="date" class="form-control" id="projectEndDate">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Members</label>
            <select class="form-control" id="projectMembers" multiple="multiple" style="width:100%">
              <!-- Employees will be loaded here -->
            </select>
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

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadProjects();
    
    $('#newProjectBtn').click(() => {
        loadAssignableEmployeesForProject();
        $('#newProjectModal').modal('show');
    });
    $('#saveProjectBtn').click(createProject);
    $('#projectSearch').on('input', filterProjects);
});

function loadProjects() {
    $.post('../includes/project_ajax.php', {
        action: 'get_projects_monitoring'
    }, function(response) {
        if (response.success) {
            renderProjectsTable(response.projects);
        }
    }, 'json');
}

function renderProjectsTable(projects) {
    const tbody = $('#projectsTableBody');
    tbody.empty();
    
    if (projects.length === 0) {
        tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No projects found</td></tr>');
        return;
    }
    
    projects.forEach(project => {
        const progress = calculateProgress(project);
        const row = $(`
            <tr>
                <td><strong>${project.project_code}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="project-color-badge" style="background-color: ${project.color}"></div>
                        ${project.project_name}
                    </div>
                </td>
                <td>${project.start_date || 'Not set'}</td>
                <td>${project.end_date || 'Not set'}</td>
                <td>
                    <span class="badge badge-${getStatusBadgeClass(project.status)}">
                        ${project.status.replace('_', ' ').toUpperCase()}
                    </span>
                </td>
                <td>${project.task_count || 0}</td>
                <td>${project.member_count || 0}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: ${progress}%">
                            ${progress}%
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-info view-project" data-project-id="${project.project_id}">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn btn-sm btn-warning edit-project" data-project-id="${project.project_id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-primary select-project" data-project-id="${project.project_id}">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-project" data-project-id="${project.project_id}" data-project-name="${project.project_name}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
        
        tbody.append(row);
    });
    
    // Add event listeners
    $('.view-project').click(function() {
        const projectId = $(this).data('project-id');
        // Redirect to scrumboard with project ID
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
    
    $('.select-project').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
    
    $('.edit-project').click(function() {
        const projectId = $(this).data('project-id');
        // Optional: Implement edit functionality
        editProject(projectId);
    });

    $('.delete-project').click(function() {
        const projectId = $(this).data('project-id');
        const projectName = $(this).data('project-name');
        deleteProject(projectId, projectName);
    });
}

function deleteProject(projectId, projectName) {
    Swal.fire({
        title: 'Delete Project?',
        text: `This will permanently delete "${projectName}" and all its boards and tasks. This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../includes/project_ajax.php', {
                action: 'delete_project',
                project_id: projectId
            }, function(response) {
                if (response.success) {
                    loadProjects();
                    Swal.fire('Deleted', 'Project deleted successfully', 'success');
                } else {
                    Swal.fire('Error', response.error || 'Failed to delete project', 'error');
                }
            }, 'json');
        }
    });
}

function calculateProgress(project) {
    if (!project.task_count) return 0;
    const completed = project.completed_tasks || 0;
    return Math.round((completed / project.task_count) * 100);
}

function getStatusBadgeClass(status) {
    const classes = {
        'active': 'success',
        'completed': 'primary',
        'on_hold': 'warning',
        'cancelled': 'danger'
    };
    return classes[status] || 'secondary';
}

function loadAssignableEmployeesForProject() {
    $.post('../includes/project_ajax.php', {
        action: 'get_assignable_employees'
    }, function(response) {
        if (response.success) {
            const select = $('#projectMembers');
            select.empty();

            response.employees.forEach(employee => {
                const role = employee.role_name || (employee.is_manager ? 'Manager' : 'Employee');
                select.append(`<option value="${employee.emp_id}">${employee.first_name} ${employee.last_name} (${role})</option>`);
            });

            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }
            select.select2({
                theme: 'bootstrap',
                width: '100%',
                placeholder: 'Select team members',
                allowClear: true,
                dropdownParent: $('#newProjectModal')
            }).val(null).trigger('change');
        }
    }, 'json');
}

function createProject() {
    const formData = {
        project_name: $('#projectName').val(),
        project_code: $('#projectCode').val(),
        project_description: $('#projectDescription').val(),
        start_date: $('#projectStartDate').val(),
        end_date: $('#projectEndDate').val(),
        members: $('#projectMembers').val() || []
    };
    
    if (!formData.project_name || !formData.project_code) {
        alert('Please fill in all required fields');
        return;
    }
    
    $.post('../includes/project_ajax.php', {
        action: 'create_project',
        ...formData
    }, function(response) {
        if (response.success) {
            $('#newProjectModal').modal('hide');
            $('#projectForm')[0].reset();
            $('#projectMembers').val(null).trigger('change');
            loadProjects();
            Swal.fire('Success', 'Project created successfully', 'success');
        } else {
            Swal.fire('Error', response.error || 'Failed to create project', 'error');
        }
    }, 'json');
}

function filterProjects() {
    const searchTerm = $('#projectSearch').val().toLowerCase();
    $('#projectsTable tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(searchTerm));
    });
}

function viewProjectDetails(projectId) {
    // Implementation for viewing project details
    window.location.href = `project_details.php?project_id=${projectId}`;
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
</body>
</html>