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
            <h1>My Tasks</h1>
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
              <button class="btn btn-tool" id="refreshTasks">
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
.project-color-badge {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}
.task-title {
    font-weight: 500;
}
.priority-urgent { background: #DC2626; color: white; }
.priority-high { background: #EA580C; color: white; }
.priority-medium { background: #CA8A04; color: white; }
.priority-low { background: #16A34A; color: white; }
.label-revise { background: #FEF3C7; color: #D97706; }
.label-urgent { background: #FECACA; color: #DC2626; }
.label-design { background: #DBEAFE; color: #1D4ED8; }
.label-development { background: #D1FAE5; color: #047857; }
.label-review { background: #E0E7FF; color: #3730A3; }
</style>
</body>
</html>