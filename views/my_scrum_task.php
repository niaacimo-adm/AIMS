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
<body class="hold-transition sidebar-mini">
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
            <div class="float-right d-flex align-items-center">
              <div class="btn-group btn-group-sm view-toggle-group mr-2" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary view-toggle-btn active" data-view="list">
                  <i class="fas fa-list"></i> List
                </button>
                <button type="button" class="btn btn-outline-secondary view-toggle-btn" data-view="board">
                  <i class="fas fa-columns"></i> Board
                </button>
              </div>
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
            <div id="listViewContainer">
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
            <div id="boardViewContainer" style="display:none;">
              <div class="grid-search-bar">
                <input type="text" id="taskGridSearch" class="grid-search-input" placeholder="Search tasks by title, project…">
                <span class="grid-count-badge" id="taskGridCountBadge">0 tasks</span>
              </div>
              <div class="task-grid" id="myTasksGrid">
                <!-- Task cards will be rendered here -->
              </div>
              <div class="grid-pagination-bar" id="taskGridPaginationBar"></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
let currentView = localStorage.getItem('myTasksView') || 'list';
let currentTasks = [];

$(document).ready(function() {
    setView(currentView, false);
    loadMyTasks();
    loadProjectFilter();
    
    $('#projectFilter, #statusFilter').change(loadMyTasks);
    $('#refreshTasks').click(loadMyTasks);

    $('.view-toggle-btn').click(function() {
        setView($(this).data('view'));
    });
});

function setView(view, rerender = true) {
    currentView = view;
    localStorage.setItem('myTasksView', view);

    $('.view-toggle-btn').removeClass('active');
    $(`.view-toggle-btn[data-view="${view}"]`).addClass('active');

    if (view === 'board') {
        $('#listViewContainer').hide();
        $('#boardViewContainer').show();
    } else {
        $('#boardViewContainer').hide();
        $('#listViewContainer').show();
    }

    if (rerender && currentTasks.length >= 0) {
        renderCurrentView();
    }
}

function renderCurrentView() {
    if (currentView === 'board') {
        renderMyTasksBoard(currentTasks);
    } else {
        renderMyTasks(currentTasks);
    }
}

function loadMyTasks() {
    const projectId = $('#projectFilter').val();
    const status = $('#statusFilter').val();
    
    $.post('../includes/task_ajax.php', {
        action: 'get_user_tasks',
        project_id: projectId,
        status: status
    }, function(response) {
        if (response.success) {
            currentTasks = response.tasks;
            renderCurrentView();
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
                        ${getStatusDisplayText(task.status).toUpperCase()}
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

let taskGridPage = 1;
const TASK_GRID_PAGE_SIZE = 12;

function renderMyTasksBoard(tasks) {
    const searchTerm = ($('#taskGridSearch').val() || '').toLowerCase();
    const filtered = searchTerm
        ? tasks.filter(t => (`${t.title} ${t.project_name} ${t.priority} ${t.status}`).toLowerCase().includes(searchTerm))
        : tasks;

    $('#taskGridCountBadge').text(`${filtered.length} task${filtered.length !== 1 ? 's' : ''}`);

    const grid = $('#myTasksGrid');
    grid.empty();

    if (filtered.length === 0) {
        grid.html('<div class="no-results-card"><i class="fas fa-inbox fa-2x mb-2"></i><p>No tasks found</p></div>');
        $('#taskGridPaginationBar').empty();
        return;
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / TASK_GRID_PAGE_SIZE));
    if (taskGridPage > totalPages) taskGridPage = totalPages;
    const start = (taskGridPage - 1) * TASK_GRID_PAGE_SIZE;
    const pageTasks = filtered.slice(start, start + TASK_GRID_PAGE_SIZE);

    pageTasks.forEach(task => {
        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : null;
        const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done';
        const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
        const initials = (task.project_name || '?').substring(0, 2).toUpperCase();

        const card = $(`
            <div class="task-grid-card">
                <div class="task-grid-card-accent" style="background: ${task.color || 'var(--s-teal)'}"></div>
                <div class="task-grid-card-icon-wrap">
                    <div class="task-grid-card-icon-placeholder" style="background: ${task.color ? task.color + '22' : 'var(--s-teal-dim)'}; color:${task.color || 'var(--s-teal)'}">
                        ${initials}
                    </div>
                </div>
                <div class="task-grid-card-body">
                    <h5 class="task-grid-card-title">${escapeHtml(task.title)}</h5>
                    <p class="task-grid-card-subtitle">${escapeHtml(task.project_name)}</p>

                    <div class="task-grid-card-badges">
                        <span class="task-grid-card-badge badge-${getTaskStatusBadgeClass(task.status)}">${getStatusDisplayText(task.status).toUpperCase()}</span>
                        <span class="task-grid-card-badge priority-${task.priority}">${task.priority.toUpperCase()}</span>
                    </div>

                    <div class="task-grid-card-meta">
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="${isOverdue ? 'text-danger' : ''}">${dueDate ? dueDate : 'No due date'}${isOverdue ? ' (Overdue)' : ''}</span>
                        </div>
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-user"></i>
                            <span>${escapeHtml(creatorName)}</span>
                        </div>
                        ${task.project_code ? `
                        <div class="task-grid-card-meta-row">
                            <i class="fas fa-hashtag"></i>
                            <span>${escapeHtml(task.project_code)}</span>
                        </div>` : ''}
                    </div>
                </div>
                <div class="task-grid-card-actions">
                    <button type="button" class="task-grid-card-action-btn view grid-view-task" data-task-id="${task.task_id}" title="View">
                        <i class="fas fa-eye"></i><span class="d-none d-md-inline">View</span>
                    </button>
                    <button type="button" class="task-grid-card-action-btn edit grid-update-status" data-task-id="${task.task_id}" title="Update Status">
                        <i class="fas fa-edit"></i><span class="d-none d-md-inline">Status</span>
                    </button>
                    <button type="button" class="task-grid-card-action-btn go grid-open-project" data-project-id="${task.project_id}" title="Open Project">
                        <i class="fas fa-external-link-alt"></i><span class="d-none d-md-inline">Project</span>
                    </button>
                </div>
            </div>
        `);

        grid.append(card);
    });

    $('.grid-view-task').click(function() { viewTaskDetails($(this).data('task-id')); });
    $('.grid-update-status').click(function() { updateTaskStatus($(this).data('task-id')); });
    $('.grid-open-project').click(function() { window.location.href = `scrum.php?project_id=${$(this).data('project-id')}`; });

    renderTaskGridPagination(totalPages, filtered.length);
}

function renderTaskGridPagination(totalPages, totalCount) {
    const bar = $('#taskGridPaginationBar').empty();
    if (totalCount === 0) return;

    bar.append(`<span class="grid-pagination-info">Page ${taskGridPage} of ${totalPages} &nbsp;·&nbsp; ${totalCount} task${totalCount !== 1 ? 's' : ''}</span>`);

    const btns = $('<div class="grid-pagination-btns"></div>');
    btns.append(`<button class="grid-page-btn" id="taskPrevPage" ${taskGridPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i> Prev</button>`);
    for (let p = 1; p <= totalPages; p++) {
        btns.append(`<button class="grid-page-btn${p === taskGridPage ? ' active' : ''}" data-page="${p}">${p}</button>`);
    }
    btns.append(`<button class="grid-page-btn" id="taskNextPage" ${taskGridPage === totalPages ? 'disabled' : ''}>Next <i class="fas fa-chevron-right"></i></button>`);

    btns.find('#taskPrevPage').click(function() { if (taskGridPage > 1) { taskGridPage--; renderMyTasksBoard(currentTasks); } });
    btns.find('#taskNextPage').click(function() { if (taskGridPage < totalPages) { taskGridPage++; renderMyTasksBoard(currentTasks); } });
    btns.find('[data-page]').click(function() { taskGridPage = parseInt($(this).data('page')); renderMyTasksBoard(currentTasks); });

    bar.append(btns);
}

$('#taskGridSearch').on('input', function() {
    taskGridPage = 1;
    renderMyTasksBoard(currentTasks);
});

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

// Turns a raw status string into something readable. Handles the normal
// 'backlog'/'todo'/'inprogress'/'review'/'done' values, but also falls back
// gracefully for empty values or leftover 'board_<id>' strings from custom
// boards / older data, instead of rendering a blank badge.
function getStatusDisplayText(status) {
    if (!status || status === '') return 'Unknown';

    const statusMap = {
        'backlog': 'Backlog',
        'todo': 'To Do',
        'inprogress': 'In Progress',
        'review': 'Review',
        'done': 'Done'
    };
    const normalized = status.toLowerCase();
    if (statusMap[normalized]) return statusMap[normalized];

    let text = status
        .replace(/^board_/i, 'Board ')
        .replace(/_/g, ' ')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .trim();
    text = text.replace(/\b\w/g, l => l.toUpperCase());
    return text || 'Unknown';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function viewTaskDetails(taskId) {
    // currentTasks (already loaded by loadMyTasks) has everything the old
    // 'get_task_details' AJAX call was trying to fetch — that action doesn't
    // exist in task_ajax.php, so this avoids relying on it.
    const task = currentTasks.find(t => t.task_id == taskId);
    if (!task) return;

    const labels = task.labels ? task.labels.split(',') : [];
    const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
    const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';

    Swal.fire({
        title: task.title,
        html: `
            <div class="text-left">
                <p><strong>Description:</strong> ${task.description || 'No description'}</p>
                <p><strong>Project:</strong> ${task.project_name} (${task.project_code})</p>
                <p><strong>Status:</strong> <span class="badge badge-${getTaskStatusBadgeClass(task.status)}">${getStatusDisplayText(task.status).toUpperCase()}</span></p>
                <p><strong>Priority:</strong> <span class="badge priority-${task.priority}">${task.priority.toUpperCase()}</span></p>
                <p><strong>Due Date:</strong> ${dueDate}</p>
                <p><strong>Created By:</strong> ${creatorName}</p>
                ${labels.length > 0 ? `
                    <p><strong>Labels:</strong> 
                        ${labels.map(label => `<span class="badge label-${label}">${label.toUpperCase()}</span>`).join(' ')}
                    </p>
                ` : ''}
                <hr>
                <p class="mb-2"><strong><i class="fas fa-history mr-1"></i>Activity</strong></p>
                <div id="taskActivityLog" class="task-activity-log">
                    <div class="text-muted small">Loading activity&hellip;</div>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'OK',
        width: '600px',
        didOpen: () => loadTaskActivity(taskId)
    });
}

function loadTaskActivity(taskId) {
    $.post('../includes/task_ajax.php', {
        action: 'get_task_activity',
        task_id: taskId
    }, function(response) {
        const container = $('#taskActivityLog');
        if (!container.length) return; // modal already closed

        if (!response.success || !response.activity || response.activity.length === 0) {
            container.html('<div class="text-muted small">No activity yet.</div>');
            return;
        }

        const items = response.activity.map(entry => {
            const who = entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone';
            const when = new Date(entry.created_at).toLocaleString();
            const initial = (entry.first_name || '?').charAt(0).toUpperCase();
            return `
                <div class="activity-item">
                    <div class="activity-avatar">${initial}</div>
                    <div class="activity-content">
                        <div class="activity-desc"><strong>${escapeHtml(who)}</strong> ${escapeHtml(entry.description)}</div>
                        <div class="activity-meta">${when}</div>
                    </div>
                </div>
            `;
        }).join('');

        container.html(items);
    }, 'json').fail(function() {
        $('#taskActivityLog').html('<div class="text-muted small">Couldn\'t load activity.</div>');
    });
}

function updateTaskStatus(taskId) {
    // Look up the task's current status so the select opens pre-filled
    // instead of always showing the blank placeholder — this is what
    // was missing compared to scrum.php, where the status is obvious
    // from which column the task card sits in.
    const currentTask = currentTasks.find(t => t.task_id == taskId);
    const currentStatus = currentTask ? currentTask.status : '';

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
        inputValue: currentStatus,
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
  /* Aliased to mainheader.php's site-wide green theme (light/dark mode aware)
     instead of this page's own hardcoded dark/teal palette. */
  --s-bg:         var(--body-bg);
  --s-surface:    var(--card-bg);
  --s-surface2:   var(--table-stripe);
  --s-surface3:   var(--notification-unread-bg);
  --s-border:     var(--card-border);
  --s-teal:       var(--green);
  --s-teal-dim:   rgba(36,231,143,.12);
  --s-teal-glow:  0 0 20px rgba(36,231,143,.25);
  --s-teal-text:  var(--sidebar-active-text);
  --s-violet:     #a78bfa;
  --s-text:       var(--text-primary);
  --s-muted:      var(--text-muted);
  --s-danger:     #f85149;
  --s-warning:    #d29922;
  --s-green:      #3fb950;
  --s-blue:       #58a6ff;
  --s-radius:     10px;
  --s-shadow:     0 8px 32px rgba(15,45,30,.12);
  --s-font:       'Plus Jakarta Sans', sans-serif;
  --s-mono:       'JetBrains Mono', monospace;
}

/* Re-resolve the theme-dependent aliases when dark mode is active.
   A custom property that references var(--card-bg) etc. only re-substitutes
   at the point it's declared — declaring it once at :root freezes it to the
   light value forever, even after body.dark-mode redefines --card-bg. Mirror
   mainheader.php's own pattern here so these aliases pick up the dark values. */
body.dark-mode {
  --s-bg:         var(--body-bg);
  --s-surface:    var(--card-bg);
  --s-surface2:   var(--table-stripe);
  --s-surface3:   var(--notification-unread-bg);
  --s-border:     var(--card-border);
  --s-teal-text:  var(--sidebar-active-text);
  --s-text:       var(--text-primary);
  --s-muted:      var(--text-muted);
  --s-shadow:     0 8px 32px rgba(0,0,0,.35);
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
  border: 1px solid rgba(36,231,143,.3) !important;
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
  color: var(--s-teal-text) !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 700 !important;
  font-size: .8rem !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px rgba(36,231,143,.25) !important;
  transition: all .2s !important;
}
.scrum-header .btn-primary:hover { background: var(--green-dark) !important; box-shadow: 0 4px 20px rgba(36,231,143,.4) !important; transform: translateY(-1px); }
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
  box-shadow: 0 0 0 1px rgba(36,231,143,.2), 0 4px 16px rgba(0,0,0,.3) !important;
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
.btn-sm.btn-primary { background: rgba(36,231,143,.12) !important; color: var(--s-teal) !important; border: 1px solid rgba(36,231,143,.25) !important; border-radius: 7px !important; }
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

/* Activity log inside the task detail popup (viewTaskDetails) */
.task-activity-log { max-height: 220px; overflow-y: auto; text-align: left; }
.task-activity-log .activity-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid var(--s-border) !important;
}
.task-activity-log .activity-item:last-child { border-bottom: none !important; }
.task-activity-log .activity-avatar {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.task-activity-log .activity-content { flex: 1; }
.task-activity-log .activity-desc { font-size: 13px; color: var(--s-text) !important; }
.task-activity-log .activity-meta { font-size: 11px; margin-top: 2px; }

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
.modal .form-control:focus { border-color: var(--s-teal) !important; box-shadow: 0 0 0 3px rgba(36,231,143,.12) !important; }
.modal label { color: var(--s-muted) !important; font-size: .72rem !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: .5px !important; margin-bottom: 5px !important; }

/* Checkbox labels */
.custom-control-label { color: var(--s-text) !important; font-size: .85rem !important; text-transform: none !important; letter-spacing: 0 !important; }

/* Modal buttons */
.modal .btn-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 600 !important; }
.modal .btn-primary { background: var(--s-teal) !important; color: var(--s-teal-text) !important; border: none !important; border-radius: 8px !important; font-family: var(--s-font) !important; font-weight: 700 !important; box-shadow: 0 2px 12px rgba(36,231,143,.25) !important; }
.modal .btn-primary:hover { background: var(--green-dark) !important; }
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
  color: var(--s-teal-text) !important;
  border: none !important;
  border-radius: 9px !important;
  font-weight: 700 !important;
  font-family: var(--s-font) !important;
  box-shadow: 0 2px 12px rgba(36,231,143,.25) !important;
}
.content-header .btn-success:hover { background: var(--green-dark) !important; }

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
#noBoardsMessage .btn-primary { background: var(--s-teal) !important; color: var(--s-teal-text) !important; border: none !important; border-radius: 9px !important; font-weight: 700 !important; }

/* Refresh button */
#refreshTasks.btn-tool { background: transparent !important; }

/* Select options (dark mode) */
select option { background: var(--s-surface2) !important; color: var(--s-text) !important; }

/* Color input */
input[type="color"] { background: var(--s-surface2) !important; border: 1px solid var(--s-border) !important; border-radius: 8px !important; height: 38px !important; padding: 3px !important; cursor: pointer; }

/* Textarea comment */
#commentText { background: var(--s-surface3) !important; border: 1px solid var(--s-border) !important; color: var(--s-text) !important; border-radius: 8px !important; font-family: var(--s-font) !important; }
#commentText::placeholder { color: var(--s-muted) !important; }
#addCommentBtn { background: var(--s-teal) !important; color: var(--s-teal-text) !important; border: none !important; border-radius: 7px !important; font-weight: 700 !important; font-family: var(--s-font) !important; }

/* No description / no labels text */
#noDescription, #noLabels { color: var(--s-muted) !important; }
.task-description { color: var(--s-text) !important; line-height: 1.7; }

/* ── My Tasks page specific ── */
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
  color: var(--s-teal-text, #fff) !important;
  border-color: var(--s-teal) !important;
}
.view-toggle-group .view-toggle-btn:hover:not(.active) {
  background: var(--s-surface3) !important;
  color: var(--s-text) !important;
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
  color: var(--s-teal-text, #fff) !important;
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
  background: var(--s-surface2) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236aad8a' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M13 13l3 3m-5-3a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3e%3c/svg%3e") 10px center no-repeat !important;
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
.grid-page-btn.active { background: var(--s-teal) !important; border-color: var(--s-teal) !important; color: var(--s-teal-text, #fff) !important; }

.no-results-card { grid-column: 1 / -1; text-align:center; padding:60px 20px; color: var(--s-muted) !important; }

/* ── Task detail card grid ── */
.task-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:20px; padding:4px 0 20px; }
.task-grid-card {
  background: var(--s-surface) !important;
  border-radius:16px !important;
  box-shadow: 0 2px 10px rgba(0,0,0,.07);
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
  font-size:20px; font-weight:700; text-transform:uppercase;
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
.task-grid-card-actions { display:flex; border-top:1px solid var(--s-border) !important; overflow:hidden; border-radius:0 0 16px 16px; }
.task-grid-card-action-btn {
  flex:1; border:none; background:none; padding:10px 6px; font-size:13px; cursor:pointer;
  transition: all .2s; display:flex; align-items:center; justify-content:center; gap:5px; font-weight:600;
  color: var(--s-muted) !important;
}
.task-grid-card-action-btn:not(:last-child) { border-right:1px solid var(--s-border) !important; }
.task-grid-card-action-btn.view { color: var(--s-blue) !important; }
.task-grid-card-action-btn.edit { color: #e3a520 !important; }
.task-grid-card-action-btn.go   { color: var(--s-teal) !important; }
.task-grid-card-action-btn:hover { background: var(--s-surface2) !important; }
</style>
</body>
</html>