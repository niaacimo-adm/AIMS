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
$user_id = $_SESSION['emp_id'];

// Get project ID from URL if specified
$project_id = $_GET['project_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Tasks | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            <h1><i class="fas fa-tasks mr-2" style="color:var(--s-teal)"></i>My Tasks</h1>
          </div>
          <div class="col-sm-6">
            <div class="float-right">
              <select class="form-control form-control-sm" id="projectFilter" style="width: 200px; display: inline-block;">
                <option value="">All Projects</option>
                <!-- Projects will be loaded here -->
              </select>
              <select class="form-control form-control-sm ml-2" id="statusFilter" style="width: 150px; display: inline-block;">
                <option value="">All Status</option>
                <option value="backlog">Backlog</option>
                <option value="todo">To Do</option>
                <option value="inprogress">In Progress</option>
                <option value="review">Review</option>
                <option value="done">Done</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Task List</h3>
            <div class="card-tools">
              <button class="btn btn-tool" id="refreshTasks" title="Refresh">
                <i class="fas fa-sync-alt"></i>
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
                  <!-- Tasks will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadMyTasks();
    loadProjectFilter();
    
    $('#projectFilter, #statusFilter').change(loadMyTasks);
    $('#refreshTasks').click(loadMyTasks);
});

function loadMyTasks() {
    const projectId = $('#projectFilter').val();
    const status = $('#statusFilter').val();
    
    $.post('../includes/task_ajax.php', {
        action: 'get_user_tasks',
        project_id: projectId,
        status: status
    }, function(response) {
        if (response.success) {
            renderMyTasks(response.tasks);
        }
    }, 'json');
}

function loadProjectFilter() {
    $.post('../includes/project_ajax.php', {
        action: 'get_my_projects'
    }, function(response) {
        if (response.success) {
            const filter = $('#projectFilter');
            response.projects.forEach(project => {
                filter.append(`<option value="${project.project_id}">${project.project_name}</option>`);
            });
            
            // Select specific project if provided in URL
            const urlParams = new URLSearchParams(window.location.search);
            const projectId = urlParams.get('project_id');
            if (projectId) {
                filter.val(projectId);
            }
        }
    }, 'json');
}

function renderMyTasks(tasks) {
    const tbody = $('#myTasksTableBody');
    tbody.empty();
    
    if (tasks.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No tasks found</td></tr>');
        return;
    }
    
    tasks.forEach(task => {
        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
        const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
        const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done';
        
        const row = $(`
            <tr class="${isOverdue ? 'table-danger' : ''}">
                <td>
                    <div class="task-title">${escapeHtml(task.title)}</div>
                    ${task.description ? `<small class="text-muted">${escapeHtml(task.description.substring(0, 100))}...</small>` : ''}
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="project-color-badge" style="background-color: ${task.color || '#007bff'}"></div>
                        ${task.project_name}
                    </div>
                </td>
                <td>
                    <span class="badge badge-${getTaskStatusBadgeClass(task.status)}">
                        ${task.status.replace('_', ' ').toUpperCase()}
                    </span>
                </td>
                <td>
                    <span class="badge priority-${task.priority}">
                        ${task.priority.toUpperCase()}
                    </span>
                </td>
                <td>
                    ${dueDate}
                    ${isOverdue ? '<br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overdue</small>' : ''}
                </td>
                <td>${creatorName}</td>
                <td>
                    <button class="btn btn-sm btn-info view-task" data-task-id="${task.task_id}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-primary update-status" data-task-id="${task.task_id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-success open-project" data-project-id="${task.project_id}">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                </td>
            </tr>
        `);
        
        tbody.append(row);
    });
    
    // Add event listeners
    $('.view-task').click(function() {
        const taskId = $(this).data('task-id');
        viewTaskDetails(taskId);
    });
    
    $('.update-status').click(function() {
        const taskId = $(this).data('task-id');
        updateTaskStatus(taskId);
    });
    
    $('.open-project').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
}

function getTaskStatusBadgeClass(status) {
    const classes = {
        'backlog': 'secondary',
        'todo': 'warning',
        'inprogress': 'info',
        'review': 'primary',
        'done': 'success'
    };
    return classes[status] || 'secondary';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function viewTaskDetails(taskId) {
    $.post('../includes/task_ajax.php', {
        action: 'get_task_details',
        task_id: taskId
    }, function(response) {
        if (response.success) {
            const task = response.task;
            const labels = task.labels ? task.labels.split(',') : [];
            const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
            const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
            
            Swal.fire({
                title: task.title,
                html: `
                    <div class="text-left">
                        <p><strong>Description:</strong> ${task.description || 'No description'}</p>
                        <p><strong>Project:</strong> ${task.project_name} (${task.project_code})</p>
                        <p><strong>Status:</strong> <span class="badge badge-${getTaskStatusBadgeClass(task.status)}">${task.status.toUpperCase()}</span></p>
                        <p><strong>Priority:</strong> <span class="badge priority-${task.priority}">${task.priority.toUpperCase()}</span></p>
                        <p><strong>Due Date:</strong> ${dueDate}</p>
                        <p><strong>Created By:</strong> ${creatorName}</p>
                        ${labels.length > 0 ? `
                            <p><strong>Labels:</strong> 
                                ${labels.map(label => `<span class="badge label-${label}">${label.toUpperCase()}</span>`).join(' ')}
                            </p>
                        ` : ''}
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'OK',
                width: '600px'
            });
        }
    }, 'json');
}

function updateTaskStatus(taskId) {
    Swal.fire({
        title: 'Update Task Status',
        input: 'select',
        inputOptions: {
            'backlog': 'Backlog',
            'todo': 'To Do',
            'inprogress': 'In Progress',
            'review': 'Review',
            'done': 'Done'
        },
        inputPlaceholder: 'Select status',
        showCancelButton: true,
        confirmButtonText: 'Update Status'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../includes/task_ajax.php', {
                action: 'update_task_status',
                task_id: taskId,
                status: result.value
            }, function(response) {
                if (response.success) {
                    loadMyTasks();
                    Swal.fire('Success', 'Task status updated successfully', 'success');
                } else {
                    Swal.fire('Error', response.error || 'Failed to update task status', 'error');
                }
            }, 'json');
        }
    });
}
</script>

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

/* ── My Tasks page specific ── */
.project-color-badge {
  width: 10px; height: 10px; border-radius: 3px;
  display: inline-block; margin-right: 7px; flex-shrink: 0;
}
</style>
</body>
</html>