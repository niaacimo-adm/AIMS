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
$canCreateProject = $projectManager->canCreateProject($_SESSION['emp_id']);
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
            <div class="float-right d-flex align-items-center">
              <div class="btn-group btn-group-sm view-toggle-group mr-2" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary view-toggle-btn active" data-view="list">
                  <i class="fas fa-list"></i> List
                </button>
                <button type="button" class="btn btn-outline-secondary view-toggle-btn" data-view="board">
                  <i class="fas fa-columns"></i> Board
                </button>
              </div>
              <?php if ($canCreateProject): ?>
              <button class="btn btn-success" id="newProjectBtn" style="border-radius:9px;font-weight:700;">
                <i class="fas fa-plus mr-1"></i> New Project
              </button>
              <?php endif; ?>
            </div>
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
            <div id="projectsListViewContainer">
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
            <div id="projectsBoardViewContainer" style="display:none;">
              <div class="grid-search-bar">
                <input type="text" id="projectGridSearch" class="grid-search-input" placeholder="Search projects by name, code…">
                <span class="grid-count-badge" id="projectGridCountBadge">0 projects</span>
              </div>
              <div class="task-grid" id="projectsGrid">
                <!-- Project cards will be rendered here -->
              </div>
              <div class="grid-pagination-bar" id="projectGridPaginationBar"></div>
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
  /* palette — sourced from the app-wide theme defined in mainheader.php */
  --s-bg: var(--body-bg, #eef7f2);
  --s-surface: var(--card-bg, #ffffff);
  --s-surface2: var(--table-stripe, #f0faf5);
  --s-surface3: #e2f3ea;
  --s-border: var(--card-border, rgba(42,152,99,0.15));
  --s-teal: var(--green-dark, #2a9863);
  --s-teal-dim: rgba(42,152,99,.10);
  --s-teal-glow: 0 0 20px rgba(36,231,143,.15);
  --s-violet: var(--green-mid, #1a5c38);
  --s-text: var(--text-primary, #0f2d1e);
  --s-muted: var(--text-muted, #4a7a5e);
  --s-danger: #cf222e;
  --s-warning: #9a6700;
  --s-green: #1a7f37;
  --s-blue: var(--green-dark, #2a9863);
  --s-radius: 10px;
  --s-shadow: 0 1px 3px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.06);
  --s-font: 'Plus Jakarta Sans', sans-serif;
  --s-mono: 'JetBrains Mono', monospace;
}

/* Mirrors body.dark-mode from mainheader.php so this page always
   matches the app-wide light/dark theme instead of a fixed dark look. */
body.dark-mode {
  --s-bg: var(--body-bg, #0b1f17);
  --s-surface: var(--card-bg, #102f22);
  --s-surface2: var(--table-stripe, #122b1d);
  --s-surface3: #16352a;
  --s-border: var(--card-border, rgba(36,231,143,0.10));
  --s-teal: var(--green, #24e78f);
  --s-teal-dim: rgba(36,231,143,.12);
  --s-teal-glow: 0 0 20px rgba(36,231,143,.2);
  --s-violet: var(--green-dark, #2a9863);
  --s-text: var(--text-primary, #d4f5e5);
  --s-muted: var(--text-muted, #6aad8a);
  --s-danger: #f85149;
  --s-warning: #d29922;
  --s-green: #3fb950;
  --s-blue: var(--green, #24e78f);
  --s-shadow: 0 8px 32px rgba(0,0,0,.4);
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
  border: 1px solid var(--s-teal-dim) !important;
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
  color: #fff !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 700 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px var(--s-teal-dim) !important;
  transition: all .2s !important;
}
.scrum-header .btn-primary:hover { filter: brightness(1.1) !important; box-shadow: 0 4px 20px var(--s-teal-dim) !important; transform: translateY(-1px); }
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
  box-shadow: 0 0 0 1px var(--s-teal-dim), 0 4px 16px rgba(0,0,0,.3) !important;
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
.btn-sm.btn-primary { background: var(--s-teal-dim) !important; color: var(--s-teal) !important; border: 1px solid var(--s-teal-dim) !important; border-radius: 7px !important; }
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
  background: linear-gradient(135deg, var(--s-surface2), var(--s-surface)) !important;
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

/* Select2 (theme: 'bootstrap') — not covered by .modal .form-control since
   Select2 renders its own markup outside the native <select> */
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

/* Checkbox labels */
.custom-control-label { color: var(--s-text) !important; font-size: .85rem !important; text-transform: none !important; letter-spacing: 0 !important; }

/* Modal buttons */
.modal .btn-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }
.modal .btn-primary { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 700 !important; box-shadow: 0 2px 12px var(--s-teal-dim) !important; }
.modal .btn-primary:hover { filter: brightness(1.1) !important; }
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
  color: #fff !important;
  border: none !important;
  border-radius: 9px !important;
  font-weight: 700 !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px var(--s-teal-dim) !important;
}
.content-header .btn-success:hover { filter: brightness(1.1) !important; }

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
#noBoardsMessage .btn-primary { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 9px !important; font-weight: 700 !important; }

/* Refresh button */
#refreshTasks.btn-tool { background: transparent !important; }

/* Select options (dark mode) */
select option { background: var(--s-surface2) !important; color: var(--s-text) !important; }

/* Color input */
input[type="color"] { background: var(--s-surface2) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; height: 38px !important; padding: 3px !important; cursor: pointer; }

/* Textarea comment */
#commentText { background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important; color: var(--s-text) !important; border-radius: 8px !important; font-family: var(--s-font) !important; }
#commentText::placeholder { color: var(--s-muted) !important; }
#addCommentBtn { background: var(--s-teal) !important; color: #fff !important; border: none !important; border-radius: 7px !important; font-weight: 700 !important; font-family: var(--s-font) !important; }

/* No description / no labels text */
#noDescription, #noLabels { color: var(--s-muted) !important; }
.task-description { color: var(--s-text) !important; line-height: 1.7; }

/* ── Projects Monitoring specific ── */
.project-color-badge {
  width: 10px; height: 10px; border-radius: 3px;
  display: inline-block; margin-right: 7px; flex-shrink: 0;
}

/* ── View toggle buttons ── */
.view-toggle-group .view-toggle-btn {
  background: var(--s-surface2) !important;
  color: var(--s-muted) !important;
  border: 1px solid var(--s-border) !important;
  font-family: var(--s-font) !important;
  font-size: .78rem !important;
  font-weight: 600 !important;
}
.view-toggle-group .view-toggle-btn.active {
  background: var(--s-teal) !important;
  color: #fff !important;
  border-color: var(--s-teal) !important;
}
.view-toggle-group .view-toggle-btn:hover:not(.active) {
  background: var(--s-surface3) !important;
  color: var(--s-text) !important;
}

/* ── Board grid search bar ── */
.grid-search-bar { display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
.grid-search-input {
  flex:1; min-width:180px; border-radius:8px !important;
  border:1px solid var(--s-border) !important;
  padding:9px 14px 9px 36px !important;
  font-size:13px !important; font-family: var(--s-font) !important;
  background: var(--s-surface2) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a7a5e' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M13 13l3 3m-5-3a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3e%3c/svg%3e") 10px center no-repeat !important;
  background-size:16px !important;
  color: var(--s-text) !important;
  transition: all .2s;
}
.grid-search-input:focus { outline:none; border-color: var(--s-teal) !important; box-shadow: 0 0 0 3px var(--s-teal-dim) !important; }
.grid-search-input::placeholder { color: var(--s-muted) !important; }
.grid-count-badge { font-size:12px; font-weight:600; color: var(--s-muted) !important; white-space:nowrap; }

/* ── Board grid pagination ── */
.grid-pagination-bar { display:flex; align-items:center; justify-content:space-between; padding:14px 0 0; flex-wrap:wrap; gap:8px; }
.grid-pagination-info { font-size:12px; color: var(--s-muted) !important; font-weight:500; }
.grid-pagination-btns { display:flex; gap:6px; }
.grid-page-btn {
  border:1px solid var(--s-border) !important; background: var(--s-surface2) !important; color: var(--s-text) !important;
  border-radius:8px !important; padding:6px 14px !important; font-size:12px !important; font-weight:600 !important;
  cursor:pointer; transition: all .2s; font-family: var(--s-font) !important;
}
.grid-page-btn:hover:not(:disabled) { border-color: var(--s-teal) !important; color: var(--s-teal) !important; background: var(--s-teal-dim) !important; }
.grid-page-btn:disabled { opacity:.4; cursor:not-allowed; }
.grid-page-btn.active { background: var(--s-teal) !important; border-color: var(--s-teal) !important; color: #fff !important; }

.no-results-card { grid-column: 1 / -1; text-align:center; padding:60px 20px; color: var(--s-muted) !important; }

/* ── Project detail card grid ── */
.task-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:20px; padding:4px 0 20px; }
.task-grid-card {
  background: var(--s-surface) !important;
  border-radius:16px !important;
  box-shadow: var(--s-shadow);
  overflow:hidden; transition: all .3s ease;
  display:flex; flex-direction:column;
  border:1px solid var(--s-border) !important;
  position:relative;
}
.task-grid-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px var(--s-teal-dim); border-color: var(--s-teal) !important; }
.task-grid-card-accent { height:5px; width:100%; }
.task-grid-card-icon-wrap { display:flex; justify-content:center; padding:20px 20px 10px; }
.task-grid-card-icon-placeholder {
  width:64px; height:64px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:18px; font-weight:700; text-transform:uppercase;
}
.task-grid-card-body { padding:0 16px 14px; flex:1; display:flex; flex-direction:column; align-items:center; text-align:center; }
.task-grid-card-title { font-size:15px; font-weight:700; color: var(--s-text) !important; margin:0 0 3px; line-height:1.3; font-family: var(--s-font) !important; }
.task-grid-card-subtitle { font-size:12px; color: var(--s-muted) !important; margin-bottom:10px; font-weight:500; }
.task-grid-card-badges { display:flex; flex-wrap:wrap; gap:5px; justify-content:center; margin-bottom:12px; }
.task-grid-card-badge { font-size:10px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:.3px; text-transform:uppercase; }
.task-grid-card-meta { width:100%; border-top:1px solid var(--s-border) !important; padding-top:10px; display:flex; flex-direction:column; gap:5px; }
.task-grid-card-meta-row { display:flex; align-items:center; gap:7px; font-size:11.5px; color: var(--s-muted) !important; }
.task-grid-card-meta-row i { width:14px; text-align:center; color: var(--s-teal) !important; flex-shrink:0; }
.task-grid-card-meta-row span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.task-grid-card-actions { display:grid; grid-template-columns: repeat(2, 1fr); border-top:1px solid var(--s-border) !important; overflow:hidden; border-radius:0 0 16px 16px; }
.task-grid-card-action-btn {
  border:none; background:none; padding:9px 4px; font-size:11.5px; cursor:pointer;
  transition: all .2s; display:flex; align-items:center; justify-content:center; gap:4px; font-weight:600;
  color: var(--s-muted) !important;
  border-bottom:1px solid var(--s-border) !important;
  white-space: nowrap; overflow: hidden;
}
.task-grid-card-action-btn span { overflow:hidden; text-overflow:ellipsis; }
.task-grid-card-action-btn:nth-child(odd) { border-right:1px solid var(--s-border) !important; }
.task-grid-card-action-btn:nth-last-child(-n+2) { border-bottom:none !important; }
.task-grid-card-action-btn.go       { color: var(--s-teal) !important; }
.task-grid-card-action-btn.edit     { color: var(--s-warning) !important; }
.task-grid-card-action-btn.activity { color: var(--s-violet) !important; }
.task-grid-card-action-btn.del      { color: var(--s-danger) !important; }
.task-grid-card-action-btn:hover { background: var(--s-surface2) !important; }

/* ── Activity log popup (same look as my_scrum_project.php / my_scrum_task.php) ── */
.task-activity-log { max-height: 320px; overflow-y: auto; text-align: left; }
.task-activity-log .activity-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid var(--s-border) !important;
}
.task-activity-log .activity-item:last-child { border-bottom: none !important; }
.task-activity-log .activity-avatar {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--s-surface3) !important; color: var(--s-teal) !important;
  border: 1px solid var(--s-border) !important;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.task-activity-log .activity-content { flex: 1; }
.task-activity-log .activity-desc { font-size: 13px; color: var(--s-text) !important; }
.task-activity-log .activity-meta { font-size: 11px; color: var(--s-muted) !important; margin-top: 2px; }
.activity-scope-tag {
  display: inline-block; font-size: 9px; font-weight: 700; text-transform: uppercase;
  padding: 1px 6px; border-radius: 8px; margin-left: 4px;
  background: var(--s-teal-dim) !important; color: var(--s-teal) !important;
}
.activity-scope-tag.project { background: rgba(167,139,250,.15) !important; color: var(--s-violet) !important; }
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
<?php include '../includes/mainfooter.php'; ?>
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

    $('.view-toggle-btn').click(function() {
        setProjectsView($(this).data('view'));
    });
});

let currentProjectsView = localStorage.getItem('projectsView') || 'list';
let currentProjects = [];
// Only the project's own creator may delete it — mirrors the server-side
// check in project_ajax.php's delete_project action.
const currentEmpId = <?= (int)($_SESSION['emp_id'] ?? 0); ?>;

function setProjectsView(view, rerender = true) {
    currentProjectsView = view;
    localStorage.setItem('projectsView', view);

    $('.view-toggle-btn').removeClass('active');
    $(`.view-toggle-btn[data-view="${view}"]`).addClass('active');

    if (view === 'board') {
        $('#projectsListViewContainer').hide();
        $('#projectsBoardViewContainer').show();
    } else {
        $('#projectsBoardViewContainer').hide();
        $('#projectsListViewContainer').show();
    }

    if (rerender) {
        renderCurrentProjectsView();
    }
}

function renderCurrentProjectsView() {
    if (currentProjectsView === 'board') {
        renderProjectsBoard(currentProjects);
    } else {
        renderProjectsTable(currentProjects);
    }
}

function loadProjects() {
    $.post('../includes/project_ajax.php', {
        action: 'get_projects_monitoring'
    }, function(response) {
        if (response.success) {
            currentProjects = response.projects;
            setProjectsView(currentProjectsView, false);
            renderCurrentProjectsView();
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
        const isCreator = Number(project.created_by) === currentEmpId;
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
                    <button class="btn btn-sm view-project-activity" style="background: var(--s-violet, #8B5CF6); color:#fff;" data-project-id="${project.project_id}" data-project-name="${escapeHtml(project.project_name)}" title="Activity Log">
                        <i class="fas fa-history"></i>
                    </button>
                    ${isCreator ? `
                    <button class="btn btn-sm btn-danger delete-project" data-project-id="${project.project_id}" data-project-name="${project.project_name}" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
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

    $('.view-project-activity').click(function() {
        const projectId = $(this).data('project-id');
        const projectName = $(this).data('project-name');
        viewProjectActivity(projectId, projectName);
    });
}

let projectGridPage = 1;
const PROJECT_GRID_PAGE_SIZE = 12;

function renderProjectsBoard(projects) {
    const searchTerm = ($('#projectGridSearch').val() || '').toLowerCase();
    const filtered = searchTerm
        ? projects.filter(p => (`${p.project_name} ${p.project_code} ${p.status}`).toLowerCase().includes(searchTerm))
        : projects;

    $('#projectGridCountBadge').text(`${filtered.length} project${filtered.length !== 1 ? 's' : ''}`);

    const grid = $('#projectsGrid');
    grid.empty();

    if (filtered.length === 0) {
        grid.html('<div class="no-results-card"><i class="fas fa-inbox fa-2x mb-2"></i><p>No projects found</p></div>');
        $('#projectGridPaginationBar').empty();
        return;
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / PROJECT_GRID_PAGE_SIZE));
    if (projectGridPage > totalPages) projectGridPage = totalPages;
    const start = (projectGridPage - 1) * PROJECT_GRID_PAGE_SIZE;
    const pageProjects = filtered.slice(start, start + PROJECT_GRID_PAGE_SIZE);

    pageProjects.forEach(project => {
        const progress = calculateProgress(project);
        const initials = project.project_code ? project.project_code.substring(0, 3).toUpperCase() : project.project_name.substring(0, 2).toUpperCase();
        const isCreator = Number(project.created_by) === currentEmpId;

        const card = $(`
            <div class="task-grid-card">
                <div class="task-grid-card-accent" style="background: ${project.color}"></div>
                <div class="task-grid-card-icon-wrap">
                    <div class="task-grid-card-icon-placeholder" style="background: ${project.color}22; color:${project.color}">
                        ${initials}
                    </div>
                </div>
                <div class="task-grid-card-body">
                    <h5 class="task-grid-card-title">${project.project_name}</h5>
                    <p class="task-grid-card-subtitle">${project.project_code}</p>

                    <div class="task-grid-card-badges">
                        <span class="task-grid-card-badge badge-${getStatusBadgeClass(project.status)}">${project.status.replace('_', ' ').toUpperCase()}</span>
                    </div>

                    <div class="task-grid-card-meta">
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span>${project.start_date || 'Not set'} &rarr; ${project.end_date || 'Not set'}</span>
                        </div>
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-tasks"></i>
                            <span>${project.task_count || 0} tasks</span>
                        </div>
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-users"></i>
                            <span>${project.member_count || 0} members</span>
                        </div>
                    </div>

                    <div class="progress mt-2 mb-1" style="height: 6px; width:100%;">
                        <div class="progress-bar bg-success" style="width: ${progress}%"></div>
                    </div>
                    <small class="task-grid-card-subtitle" style="margin-bottom:0;">${progress}% complete</small>
                </div>
                <div class="task-grid-card-actions">
                    <button type="button" class="task-grid-card-action-btn go grid-select-project" data-project-id="${project.project_id}" title="Open">
                        <i class="fas fa-play"></i><span class="d-none d-md-inline">Open</span>
                    </button>
                    <button type="button" class="task-grid-card-action-btn edit grid-edit-project" data-project-id="${project.project_id}" title="Edit">
                        <i class="fas fa-edit"></i><span class="d-none d-md-inline">Edit</span>
                    </button>
                    <button type="button" class="task-grid-card-action-btn activity grid-view-activity" data-project-id="${project.project_id}" data-project-name="${escapeHtml(project.project_name)}" title="Activity Log">
                        <i class="fas fa-history"></i><span class="d-none d-md-inline">Activity</span>
                    </button>
                    ${isCreator ? `
                    <button type="button" class="task-grid-card-action-btn del grid-delete-project" data-project-id="${project.project_id}" data-project-name="${project.project_name}" title="Delete">
                        <i class="fas fa-trash"></i><span class="d-none d-md-inline">Delete</span>
                    </button>` : ''}
                </div>
            </div>
        `);

        grid.append(card);
    });

    $('.grid-select-project').click(function() { window.location.href = `scrum.php?project_id=${$(this).data('project-id')}`; });
    $('.grid-edit-project').click(function() { editProject($(this).data('project-id')); });
    $('.grid-view-activity').click(function() { viewProjectActivity($(this).data('project-id'), $(this).data('project-name')); });
    $('.grid-delete-project').click(function() { deleteProject($(this).data('project-id'), $(this).data('project-name')); });

    renderProjectGridPagination(totalPages, filtered.length);
}

function renderProjectGridPagination(totalPages, totalCount) {
    const bar = $('#projectGridPaginationBar').empty();
    if (totalCount === 0) return;

    bar.append(`<span class="grid-pagination-info">Page ${projectGridPage} of ${totalPages} &nbsp;·&nbsp; ${totalCount} project${totalCount !== 1 ? 's' : ''}</span>`);

    const btns = $('<div class="grid-pagination-btns"></div>');
    btns.append(`<button class="grid-page-btn" id="projectPrevPage" ${projectGridPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i> Prev</button>`);
    for (let p = 1; p <= totalPages; p++) {
        btns.append(`<button class="grid-page-btn${p === projectGridPage ? ' active' : ''}" data-page="${p}">${p}</button>`);
    }
    btns.append(`<button class="grid-page-btn" id="projectNextPage" ${projectGridPage === totalPages ? 'disabled' : ''}>Next <i class="fas fa-chevron-right"></i></button>`);

    btns.find('#projectPrevPage').click(function() { if (projectGridPage > 1) { projectGridPage--; renderProjectsBoard(currentProjects); } });
    btns.find('#projectNextPage').click(function() { if (projectGridPage < totalPages) { projectGridPage++; renderProjectsBoard(currentProjects); } });
    btns.find('[data-page]').click(function() { projectGridPage = parseInt($(this).data('page')); renderProjectsBoard(currentProjects); });

    bar.append(btns);
}

$('#projectGridSearch').on('input', function() {
    projectGridPage = 1;
    renderProjectsBoard(currentProjects);
});

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

function viewProjectActivity(projectId, projectName) {
    Swal.fire({
        title: projectName || 'Project Activity',
        html: `<div id="projectActivityLog" class="task-activity-log">
                   <div class="text-muted small">Loading activity&hellip;</div>
               </div>`,
        icon: 'info',
        confirmButtonText: 'Close',
        width: '600px',
        didOpen: () => loadProjectActivity(projectId)
    });
}

function loadProjectActivity(projectId) {
    $.post('../includes/project_ajax.php', {
        action: 'get_project_activity',
        project_id: projectId
    }, function(response) {
        const container = $('#projectActivityLog');
        if (!container.length) return; // modal already closed

        if (!response.success || !response.activity || response.activity.length === 0) {
            container.html('<div class="text-muted small">No activity yet.</div>');
            return;
        }

        const items = response.activity.map(entry => {
            const who = entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone';
            const when = new Date(entry.created_at).toLocaleString();
            const initial = (entry.first_name || '?').charAt(0).toUpperCase();
            const scopeTag = entry.entity_type === 'task'
                ? '<span class="activity-scope-tag">task</span>'
                : '<span class="activity-scope-tag project">project</span>';
            return `
                <div class="activity-item">
                    <div class="activity-avatar">${initial}</div>
                    <div class="activity-content">
                        <div class="activity-desc"><strong>${escapeHtml(who)}</strong> ${escapeHtml(entry.description)} ${scopeTag}</div>
                        <div class="activity-meta">${when}</div>
                    </div>
                </div>
            `;
        }).join('');

        container.html(items);
    }, 'json').fail(function() {
        $('#projectActivityLog').html('<div class="text-muted small">Couldn\'t load activity.</div>');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
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